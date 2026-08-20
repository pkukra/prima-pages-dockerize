<?php

namespace App\Http\Controllers\Document;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DocumentSignature;
use App\Models\TilakaProfile;
use App\Repositories\Document\DocumentRepository;
use App\Services\Tilaka\TilakaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class DocumentSignController extends Controller
{
    protected $tilakaService;
    protected $documentRepo;

    public function __construct(
        TilakaService $tilakaService,
        DocumentRepository $documentRepo
    ) {
        $this->tilakaService = $tilakaService;
        $this->documentRepo = $documentRepo;
    }

    public function add_template(Request $request, $id)
    {
        $documentId = (int) $id;
        $docResult = $this->documentRepo->addTemplate($documentId);
        if (!$docResult) {
            return ApiResponse::error('Gagal menambahkan template ', null, 500);
        }
        return ApiResponse::success($docResult, 'Template berhasil ditambahkan');
    }

    public function sign(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'placements' => 'required|array|min:1',
            'placements.*.page' => 'required|integer|min:1',
            'placements.*.x' => 'required|numeric|min:0|max:1',
            'placements.*.y' => 'required|numeric|min:0|max:1',
            'placements.*.width' => 'required|numeric|gt:0|max:1',
            'placements.*.height' => 'required|numeric|gt:0|max:1',
            'placements.*.sort_order' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $user = $request->user();
        $docResult = $this->documentRepo->show($id, $user);
        if (!$docResult->status) {
            if ($docResult->message === 'Akses ditolak') {
                return ApiResponse::error('Akses ditolak.', null, 403);
            }

            return ApiResponse::error('Dokumen tidak ditemukan', null, 404);
        }

        $document = $docResult->data;
        if (!$this->documentRepo->isAssignedSigner((int) $document->id, (int) $user->id)) {
            return ApiResponse::error('User tidak terdaftar sebagai signer dokumen ini', null, 403);
        }

        $profile = TilakaProfile::where('user_id', $user->id)->first();
        if (!$profile) {
            return ApiResponse::error('User belum terdaftar sebagai Quick Sign user');
        }

        if (empty($profile->signature_path)) {
            return ApiResponse::error('Tanda tangan belum diupload. Silakan upload terlebih dahulu pada menu Tilaka.', null, 422);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (!$disk->exists($document->file_path)) {
            return ApiResponse::error('File dokumen tidak ditemukan di storage', null, 404);
        }

        if (!$disk->exists($profile->signature_path)) {
            return ApiResponse::error('File tanda tangan tidak ditemukan di storage', null, 404);
        }

        $pdfMime = strtolower((string) ($disk->mimeType($document->file_path) ?? ''));
        $pdfExt = strtolower((string) pathinfo($document->file_path, PATHINFO_EXTENSION));
        if ($pdfExt !== 'pdf' && !str_contains($pdfMime, 'pdf')) {
            return ApiResponse::error('Dokumen harus berupa PDF untuk proses placement tanda tangan', null, 422);
        }

        $signatureMime = strtolower((string) ($disk->mimeType($profile->signature_path) ?? ''));
        if (!in_array($signatureMime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return ApiResponse::error('File tanda tangan harus berupa gambar JPG atau PNG', null, 422);
        }

        $signatureImageType = str_contains($signatureMime, 'png') ? 'PNG' : 'JPG';

        $placements = $this->normalizePlacements((array) $request->input('placements', []));

        $sourcePdfTmpPath = null;
        $signatureTmpPath = null;
        $signedPdfTmpPath = null;

        try {
            $sourcePdfTmpPath = $this->copyDiskFileToTemp($disk, $document->file_path, 'doc_sign_src_');
            $signatureTmpPath = $this->copyDiskFileToTemp($disk, $profile->signature_path, 'doc_sign_img_');

            $pageSizes = $this->getPdfPageSizesInPoints($sourcePdfTmpPath);
            $totalPages = count($pageSizes);

            foreach ($placements as $placement) {
                if ($placement['page'] > $totalPages) {
                    return ApiResponse::error(
                        "Halaman placement tidak valid. Dokumen hanya memiliki {$totalPages} halaman.",
                        null,
                        422
                    );
                }
            }

            $this->persistPlacements(
                (int) $document->id,
                (int) $user->id,
                $placements,
                (string) $profile->signature_path,
                $user->email ?? null
            );

            $signedPdfBinary = $this->renderSignatureToPdf(
                $sourcePdfTmpPath,
                $signatureTmpPath,
                $signatureImageType,
                $placements,
                $pageSizes
            );

            $signedPdfTmpPath = tempnam(sys_get_temp_dir(), 'doc_sign_final_');
            if ($signedPdfTmpPath === false) {
                throw new \RuntimeException('Gagal membuat file temporer PDF final');
            }

            if (file_put_contents($signedPdfTmpPath, $signedPdfBinary) === false) {
                throw new \RuntimeException('Gagal menulis PDF final ke file temporer');
            }

            $pdfHandle = fopen($signedPdfTmpPath, 'r');
            if ($pdfHandle === false) {
                throw new \RuntimeException('Gagal membuka PDF final');
            }

            try {
                $uploadResponse = $this->tilakaService->request_local_multipart(
                    'POST',
                    '/api/v1/upload',
                    [
                        [
                            'name' => 'file',
                            'contents' => $pdfHandle,
                            'filename' => basename($document->file_path),
                        ],
                    ]
                );
            } finally {
                if (is_resource($pdfHandle)) {
                    fclose($pdfHandle);
                }
            }

            $filename = data_get($uploadResponse, 'filename');
            if (!$filename) {
                throw new \RuntimeException('Filename dari upload tidak ditemukan');
            }

            $signatureDataUri = $this->buildImageDataUri($signatureTmpPath, $signatureMime);
            $userIdentifier = (string) ($profile->tilaka_uuid ?: $profile->email);
            $tilakaCoordinates = $this->buildTilakaCoordinates($placements, $pageSizes, $userIdentifier);

            $payload = [
                'request_id' => (string) Str::uuid(),
                'signatures' => [
                    [
                        'user_identifier' => "iqbal1234",
                        'email' => $profile->email,
                        'signature_image' => $signatureDataUri,
                    ],
                ],
                'list_pdf' => [
                    [
                        'filename' => $filename,
                        'template_no' => "template_" . $id,
                        'signatures' => $tilakaCoordinates,
                    ],
                ],
            ];

            $response = $this->tilakaService->request_local(
                'POST',
                '/api/v1/requestquicksign',
                $payload
            );

            $this->documentRepo->markSignerSigned((int) $document->id, (int) $user->id);
            $allSigned = $this->documentRepo->allSignersSigned((int) $document->id);
            $responseData = $response;
            if (is_array($responseData)) {
                $responseData['all_signers_signed'] = $allSigned;
                $responseData['signed_by_user_id'] = (int) $user->id;
            } elseif (is_object($responseData)) {
                $responseData->all_signers_signed = $allSigned;
                $responseData->signed_by_user_id = (int) $user->id;
            } else {
                $responseData = [
                    'tilaka_response' => $responseData,
                    'all_signers_signed' => $allSigned,
                    'signed_by_user_id' => (int) $user->id,
                ];
            }

            return ApiResponse::success(
                $responseData,
                $allSigned
                    ? 'Request tanda tangan berhasil. Semua signer sudah menyelesaikan proses.'
                    : 'Request tanda tangan berhasil. Menunggu signer lain.'
            );
        } catch (\Throwable $e) {
            Log::error('Proses tanda tangan dokumen gagal', [
                'doc_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Gagal memproses tanda tangan',
                ['exception' => $e->getMessage()]
            );
        } finally {
            foreach ([$sourcePdfTmpPath, $signatureTmpPath, $signedPdfTmpPath] as $tmpPath) {
                if ($tmpPath && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }
    }

    private function persistPlacements(
        int $documentId,
        int $userId,
        array $placements,
        string $signaturePath,
        ?string $createdBy
    ): void {
        DB::transaction(function () use ($documentId, $userId, $placements, $signaturePath, $createdBy) {
            DocumentSignature::where('document_id', $documentId)
                ->where(function ($q) use ($userId, $createdBy) {
                    $q->where('user_id', $userId);
                    if (!empty($createdBy)) {
                        $q->orWhere(function ($legacy) use ($createdBy) {
                            $legacy->whereNull('user_id')
                                ->where('created_by', $createdBy);
                        });
                    }
                })
                ->delete();

            foreach ($placements as $placement) {
                DocumentSignature::create([
                    'document_id' => $documentId,
                    'user_id' => $userId,
                    'page' => $placement['page'],
                    'x' => $placement['x'],
                    'y' => $placement['y'],
                    'width' => $placement['width'],
                    'height' => $placement['height'],
                    'signature_path' => $signaturePath,
                    'sort_order' => $placement['sort_order'],
                    'created_by' => $createdBy,
                ]);
            }
        });
    }

    private function copyDiskFileToTemp($disk, string $path, string $prefix): string
    {
        $stream = $disk->readStream($path);
        if ($stream === false) {
            throw new \RuntimeException('Gagal membaca file dari storage');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), $prefix);
        if ($tmpPath === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new \RuntimeException('Gagal membuat file temporer');
        }

        $target = fopen($tmpPath, 'wb');
        if ($target === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new \RuntimeException('Gagal membuka file temporer');
        }

        stream_copy_to_stream($stream, $target);

        if (is_resource($stream)) {
            fclose($stream);
        }
        fclose($target);

        return $tmpPath;
    }

    private function getPdfPageSizesInPoints(string $sourcePdfPath): array
    {
        $this->ensureFpdfCompatibility();

        $pdf = new Fpdi('P', 'pt');
        $pageCount = $pdf->setSourceFile($sourcePdfPath);
        $sizes = [];

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $sizes[$pageNo] = [
                'width' => (float) $size['width'],
                'height' => (float) $size['height'],
            ];
        }

        return $sizes;
    }

    private function renderSignatureToPdf(
        string $sourcePdfPath,
        string $signatureImagePath,
        string $signatureImageType,
        array $placements,
        array $pageSizes
    ): string {
        $this->ensureFpdfCompatibility();

        $placementsByPage = [];
        foreach ($placements as $placement) {
            $placementsByPage[$placement['page']][] = $placement;
        }

        $pdf = new Fpdi('P', 'pt');
        $pageCount = $pdf->setSourceFile($sourcePdfPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            foreach (($placementsByPage[$pageNo] ?? []) as $placement) {
                [$x, $y, $width, $height] = $this->placementToAbsolute(
                    $placement,
                    (float) $pageSizes[$pageNo]['width'],
                    (float) $pageSizes[$pageNo]['height']
                );

                $pdf->Image(
                    $signatureImagePath,
                    $x,
                    $y,
                    $width,
                    $height,
                    $signatureImageType
                );
            }
        }

        return $pdf->Output('S');
    }

    private function buildImageDataUri(string $signatureImagePath, string $mimeType): string
    {
        $binary = file_get_contents($signatureImagePath);
        if ($binary === false) {
            throw new \RuntimeException('Gagal membaca file tanda tangan');
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }

    private function buildTilakaCoordinates(array $placements, array $pageSizes, string $userIdentifier): array
    {
        $result = [];

        foreach ($placements as $placement) {
            $page = (int) $placement['page'];
            if (!isset($pageSizes[$page])) {
                continue;
            }

            [$x, $y, $width, $height] = $this->placementToAbsolute(
                $placement,
                (float) $pageSizes[$page]['width'],
                (float) $pageSizes[$page]['height']
            );

            $result[] = [
                'user_identifier' => $userIdentifier,
                'page_number' => $page,
                'coordinate_x' => round($x, 2),
                'coordinate_y' => round($y, 2),
                'width' => round($width, 2),
                'height' => round($height, 2),
            ];
        }

        return $result;
    }

    private function normalizePlacements(array $placements): array
    {
        $normalized = [];

        foreach ($placements as $index => $placement) {
            $normalized[] = [
                'page' => (int) ($placement['page'] ?? 1),
                'x' => $this->clamp((float) ($placement['x'] ?? 0), 0, 1),
                'y' => $this->clamp((float) ($placement['y'] ?? 0), 0, 1),
                'width' => $this->clamp((float) ($placement['width'] ?? 0.2), 0.01, 1),
                'height' => $this->clamp((float) ($placement['height'] ?? 0.08), 0.01, 1),
                'sort_order' => (int) ($placement['sort_order'] ?? ($index + 1)),
            ];
        }

        usort($normalized, function ($a, $b) {
            return [$a['page'], $a['sort_order']] <=> [$b['page'], $b['sort_order']];
        });

        return $normalized;
    }

    private function placementToAbsolute(array $placement, float $pageWidth, float $pageHeight): array
    {
        $widthRatio = $this->clamp((float) $placement['width'], 0.01, 1);
        $heightRatio = $this->clamp((float) $placement['height'], 0.01, 1);

        $xRatio = $this->clamp((float) $placement['x'], 0, 1);
        $yRatio = $this->clamp((float) $placement['y'], 0, 1);

        if ($xRatio + $widthRatio > 1) {
            $xRatio = max(0, 1 - $widthRatio);
        }

        if ($yRatio + $heightRatio > 1) {
            $yRatio = max(0, 1 - $heightRatio);
        }

        return [
            $xRatio * $pageWidth,
            $yRatio * $pageHeight,
            $widthRatio * $pageWidth,
            $heightRatio * $pageHeight,
        ];
    }

    private function clamp(float $value, float $min = 0, float $max = 1): float
    {
        return max($min, min($max, $value));
    }

    private function ensureFpdfCompatibility(): void
    {
        if (!function_exists('get_magic_quotes_runtime')) {
            require_once app_path('Support/fpdf_polyfill.php');
        }
    }
}
