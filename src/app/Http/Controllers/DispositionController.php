<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Repositories\Disposition\DispositionRepository;
use Illuminate\Support\Facades\Validator;
use App\Models\Disposition;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use setasign\Fpdi\Fpdi;

class DispositionController extends Controller
{
    protected $repo;

    public function __construct(DispositionRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index($incomingMailId)
    {
        $result = $this->repo->listByMail($incomingMailId);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data);
    }

    public function my(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->unit_id) {
            return Inertia::render('Disposition/My', ['dispositions' => []]);
        }

        $res = $this->repo->listByUnit($user->unit_id);
        $dispositions = $res->status ? $res->data : [];

        return Inertia::render('Disposition/My', [
            'dispositions' => $dispositions,
        ]);
    }

    public function createdByMe(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return Inertia::render('Disposition/CreatedByMe', ['dispositions' => []]);
        }

        $roleName = $user->role->name ?? null;
        if (in_array($roleName, ['superadmin', 'admin'])) {
            $res = $this->repo->listAll();
        } else {
            $res = $this->repo->listByCreator($user->id);
        }

        $dispositions = $res->status ? $res->data : [];

        return Inertia::render('Disposition/CreatedByMe', [
            'dispositions' => $dispositions,
        ]);
    }

    public function store(Request $request, $incomingMailId)
    {
        $validator = Validator::make($request->all(), [
            'instruction' => 'required|string',
            'due_date' => 'nullable|date',
            'to_unit_id' => 'nullable|exists:units,id',
            'to_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        $result = $this->repo->storeForMail($incomingMailId, $request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 201);
    }

    public function update(Request $request, $incomingMailId, $dispositionId)
    {
        $validator = Validator::make($request->all(), [
            'instruction' => 'required|string',
            'due_date' => 'nullable|date',
            'to_unit_id' => 'required|exists:units,id',
            'to_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        // check permission: allow if creator or superadmin
        $disp = Disposition::find($dispositionId);
        if (!$disp) return ApiResponse::error('Disposisi tidak ditemukan', null, 404);

        $user = Auth::user();
        $roleName = $user->role->name;
        if ($disp->from_user_id !== $user->id && $roleName !== 'superadmin') {
            return ApiResponse::error('Tidak memiliki akses untuk mengubah disposisi', null, 403);
        }

        $result = $this->repo->updateDisposisi($dispositionId, $request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function destroy(Request $request, $incomingMailId, $dispositionId)
    {
        $disp = Disposition::find($dispositionId);
        if (!$disp) return ApiResponse::error('Disposisi tidak ditemukan', null, 404);

        $user = Auth::user();
        $roleName = $user->role->name;
        if ($disp->from_user_id !== $user->id && $roleName !== 'superadmin') {
            return ApiResponse::error('Tidak memiliki akses untuk menghapus disposisi', null, 403);
        }

        $result = $this->repo->deleteDisposisi($dispositionId);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success(null, $result->message, 200);
    }

    public function resolve(Request $request, $dispositionId)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'nullable|string|max:500',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        $disp = Disposition::find($dispositionId);
        if (!$disp) return ApiResponse::error('Disposisi tidak ditemukan', null, 404);

        $user = Auth::user();
        if (!$user || !$user->unit_id) {
            return ApiResponse::error('Tidak memiliki akses untuk resolve disposisi', null, 403);
        }

        if (!$disp->to_unit_id || (string)$disp->to_unit_id !== (string)$user->unit_id) {
            return ApiResponse::error('Hanya unit yang ditugaskan yang dapat resolve', null, 403);
        }

        if ($disp->status === 'resolved') {
            return ApiResponse::error('Disposisi sudah di-resolve', null, 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $disk = Storage::disk(config('filesystems.default'));
            $file = $request->file('image');
            $ext = $file->getClientOriginalExtension();
            $fileName = $dispositionId . '_' . now()->format('YmdHis') . ($ext ? ('.' . $ext) : '');
            $imagePath = $disk->putFileAs('disposition_resolves', $file, $fileName);
            if (!$imagePath) {
                return ApiResponse::error('Gagal menyimpan file ke storage', null, 500);
            }
        }

        $result = $this->repo->resolveDisposisi($dispositionId, $request->note ?? null, $imagePath);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function resolveFile(Request $request, $dispositionId)
    {
        $disp = Disposition::find($dispositionId);
        if (!$disp) abort(404, 'Disposisi tidak ditemukan');

        $user = Auth::user();
        if (!$user) abort(403, 'Akses ditolak.');

        $role = $user->role->name ?? null;
        $canAccess = in_array($role, ['superadmin', 'admin', 'dirut', 'wadir'], true);
        if (!$canAccess) {
            if ($disp->from_user_id && (string)$disp->from_user_id === (string)$user->id) {
                $canAccess = true;
            } elseif ($disp->to_unit_id && $user->unit_id && (string)$disp->to_unit_id === (string)$user->unit_id) {
                $canAccess = true;
            }
        }

        if (!$canAccess) abort(403, 'Akses ditolak.');

        $path = $disp->resolved_image_path;
        if (!$path) abort(404, 'File tidak ditemukan');

        $download = (bool) $request->query('download', false);

        $disk = Storage::disk(config('filesystems.default'));
        if ($disk->exists($path)) {
            if ($download) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $downloadName = 'resolve' . ($ext ? ('.' . $ext) : '');
                return $disk->download($path, $downloadName);
            }

            $stream = $disk->readStream($path);
            if ($stream === false) {
                abort(404, 'File tidak ditemukan');
            }

            $fileName = basename($path);
            $mime = $disk->mimeType($path) ?? 'application/octet-stream';

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        }

        abort(404, 'File tidak ditemukan');
    }

    public function downloadDispositionPdf(Request $request, $dispositionId)
    {
        $disp = Disposition::with(['mail', 'unit', 'fromUser.unit'])->find($dispositionId);

        if (!$disp) {
            abort(404, 'Disposisi tidak ditemukan');
        }

        $user = Auth::user();
        // if ($user) {
        //     if (!$this->canUserAccessDisposition($user, $disp)) {
        //         abort(403, 'Akses ditolak.');
        //     }
        // }
        $this->markDispositionAsRead($disp, $user);

        $mailDate = $disp->mail?->mail_date ? \Carbon\Carbon::parse($disp->mail->mail_date)->format('d-m-Y') : '-';
        $dueDate = $disp->due_date ? \Carbon\Carbon::parse($disp->due_date)->format('d-m-Y') : '-';
        $createdAt = $disp->created_at ? \Carbon\Carbon::parse($disp->created_at)->format('d-m-Y H:i') : '-';
        $downloadUrl = url('/dispositions/' . $disp->id . '/download-pdf');
        $logoPath = public_path('statics/logo.png');
        $logoSrc = file_exists($logoPath) ? $logoPath : null;
        $createdBy = $this->buildCreatedByText($disp);

        $basePdfBinary = DomPdf::loadView('pdf.disposition', [
            'disp' => $disp,
            'mailDate' => $mailDate,
            'dueDate' => $dueDate,
            'createdAt' => $createdAt,
            'createdBy' => $createdBy,
            'downloadUrl' => $downloadUrl,
            'logoSrc' => $logoSrc,
            'appName' => config('app.name', 'PRIMA'),
        ])->setPaper('a4', 'portrait')->output();

        $pdfBinary = $this->mergeDispositionWithMailPdf($basePdfBinary, $disp);

        $fileName = 'disposisi_' . ($disp->id ?? 'dokumen') . '.pdf';
        $disposition = $request->query('download') ? 'attachment' : 'inline';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $fileName . '"',
        ]);
    }

    private function markDispositionAsRead(Disposition $disp, $user = null): void
    {
        if ((int) $disp->is_unit_read === 1) {
            return;
        }

        $shouldMark = false;

        // Link download/preview juga bisa dibuka tanpa login.
        if (!$user) {
            $shouldMark = true;
        } else {
            if ($disp->to_user_id && (string) $disp->to_user_id === (string) $user->id) {
                $shouldMark = true;
            }

            if (
                !$shouldMark &&
                $disp->to_unit_id &&
                $user->unit_id &&
                (string) $disp->to_unit_id === (string) $user->unit_id
            ) {
                $shouldMark = true;
            }
        }

        if ($shouldMark) {
            $disp->is_unit_read = 1;
            $disp->updated_by = $user->email ?? $disp->updated_by;
            $disp->save();
        }
    }

    private function canUserAccessDisposition($user, Disposition $disp): bool
    {
        $role = $user->role->name ?? null;
        $canAccess = in_array($role, ['superadmin', 'admin', 'dirut', 'wadir'], true);
        if ($canAccess) {
            return true;
        }

        if ($disp->from_user_id && (string) $disp->from_user_id === (string) $user->id) {
            return true;
        }

        if ($disp->to_unit_id && $user->unit_id && (string) $disp->to_unit_id === (string) $user->unit_id) {
            return true;
        }

        return false;
    }

    private function buildCreatedByText(Disposition $disp): string
    {
        $createdByUnit = $disp->fromUser?->unit?->name;
        $createdByName = $disp->fromUser?->name;
        $createdBy = trim(implode(' - ', array_filter([$createdByUnit, $createdByName], fn($v) => filled($v))));
        if ($createdBy === '') {
            return '-';
        }

        return $createdBy;
    }

    private function mergeDispositionWithMailPdf(string $basePdfBinary, Disposition $disp): string
    {
        $mailPath = (string) ($disp->mail?->file_path ?? '');
        if ($mailPath === '') {
            return $basePdfBinary;
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (!$disk->exists($mailPath)) {
            return $basePdfBinary;
        }

        $ext = strtolower((string) pathinfo($mailPath, PATHINFO_EXTENSION));
        $mime = strtolower((string) ($disk->mimeType($mailPath) ?? ''));
        $isPdf = ($ext === 'pdf' || str_contains($mime, 'pdf'));
        if (!$isPdf) {
            return $basePdfBinary;
        }

        $mailPdfPath = null;
        try {
            $mailPdfPath = tempnam(sys_get_temp_dir(), 'disp_mail_pdf_');
            if ($mailPdfPath === false) {
                throw new \RuntimeException('Gagal membuat file temporer');
            }

            $stream = $disk->readStream($mailPath);
            if ($stream === false) {
                throw new \RuntimeException('Gagal membaca stream file surat');
            }

            $target = fopen($mailPdfPath, 'wb');
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

            return $this->mergePdfBinaries($basePdfBinary, $mailPdfPath);
        } catch (\Throwable $e) {
            Log::warning('Gagal merge PDF surat ke PDF disposisi', [
                'disposition_id' => $disp->id,
                'mail_path' => $mailPath,
                'mail_mime' => $mime,
                'error' => $e->getMessage(),
            ]);

            return $basePdfBinary;
        } finally {
            if ($mailPdfPath && file_exists($mailPdfPath)) {
                @unlink($mailPdfPath);
            }
        }
    }

    private function ensureFpdfCompatibility(): void
    {
        if (!function_exists('get_magic_quotes_runtime')) {
            require_once app_path('Support/fpdf_polyfill.php');
        }
    }

    private function mergePdfBinaries(string $basePdfBinary, string $appendPdfPath): string
    {
        $this->ensureFpdfCompatibility();

        $basePdfPath = tempnam(sys_get_temp_dir(), 'disp_base_pdf_');
        if ($basePdfPath === false) {
            throw new \RuntimeException('Gagal membuat file temporer PDF');
        }

        try {
            if (file_put_contents($basePdfPath, $basePdfBinary) === false) {
                throw new \RuntimeException('Gagal menyimpan PDF utama ke file temporer');
            }

            $mergedPdf = new Fpdi();
            $this->appendPdfPages($mergedPdf, $basePdfPath);
            $this->appendPdfPages($mergedPdf, $appendPdfPath);

            return $mergedPdf->Output('S');
        } finally {
            if (file_exists($basePdfPath)) {
                @unlink($basePdfPath);
            }
        }
    }

    private function appendPdfPages(Fpdi $pdf, string $sourcePath): void
    {
        $pageCount = $pdf->setSourceFile($sourcePath);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }
    }
}
