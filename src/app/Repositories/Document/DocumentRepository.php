<?php

namespace App\Repositories\Document;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\DocumentSignature;
use App\Helpers\RepoResponse;
use App\Models\DocumentOwner;
use App\Models\DocumentType;
use App\Services\Tilaka\TilakaService;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class DocumentRepository
{
    protected $tilakaService;

    public function __construct(
        TilakaService $tilakaService,
    ) {
        $this->tilakaService = $tilakaService;
    }

    public function index()
    {
        return false;
    }

    public function owners()
    {
        return DocumentOwner::select('id', 'name')->get();
    }

    public function signer_candidates()
    {
        return User::with('role:id,name')
            ->select('id', 'name', 'email', 'role_id')
            ->orderBy('name')
            ->get();
    }

    public function list_documents($user = null)
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return RepoResponse::error('User tidak ditemukan');
        }

        $query = Document::with(['owner:id,name', 'type:id,name'])
            ->withCount([
                'signers as total_signers',
                'signers as signed_signers_count' => function ($q) {
                    $q->where('status_sign', 'signed');
                },
            ])
            ->orderByDesc('created_at');

        if (!$this->isPrivilegedUser($user)) {
            $query->whereHas('signers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $documents = $query->get();
        return RepoResponse::success($documents);
    }

    public function list_types()
    {
        $data = DocumentType::select('*')->get();
        return RepoResponse::success($data);
    }

    public function store($request)
    {
        DB::beginTransaction();

        try {
            $signerIds = collect((array) $request->input('signer_ids', []))
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values();

            $disk = Storage::disk(config('filesystems.default'));
            $file = $request->file('file');
            $fileName = Str::random(10) . '_' . $file->getClientOriginalName();
            $filePath = $disk->putFileAs('documents', $file, $fileName);
            if (!$filePath) {
                throw new \RuntimeException('Gagal menyimpan file ke storage');
            }

            $document = Document::create([
                'created_by' => Auth::user()->email,
                'updated_by' => Auth::user()->email,
                'name' => $request->name,
                'description' => $request->description,
                'file_path' => $filePath,
                'owner_id' => $request->owner_id,
                'type_id' => $request->type_id,
            ]);

            $now = now();
            $signerRows = $signerIds->map(function ($userId) use ($document, $now) {
                return [
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'status_sign' => 'pending',
                    'signed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            if (!empty($signerRows)) {
                DocumentSigner::insert($signerRows);
            }

            DB::commit();
            return RepoResponse::success(
                $document->load(['signers.user:id,name,email']),
                'Dokumen berhasil disimpan'
            );
        } catch (\Exception $e) {
            if (isset($filePath)) {
                $disk = Storage::disk(config('filesystems.default'));
                if ($disk->exists($filePath)) {
                    $disk->delete($filePath);
                }
            }

            DB::rollBack();
            return RepoResponse::error('Gagal menyimpan dokumen', $e->getMessage());
        }
    }

    public function add_signers(int $documentId, array $signerIds)
    {
        $document = Document::find($documentId);
        if (!$document) {
            return RepoResponse::error('Dokumen tidak ditemukan');
        }

        $normalizedSignerIds = collect($signerIds)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        if ($normalizedSignerIds->isEmpty()) {
            return RepoResponse::error('Signer tidak valid');
        }

        DB::beginTransaction();
        try {
            $existingSignerIds = DocumentSigner::where('document_id', $documentId)
                ->whereIn('user_id', $normalizedSignerIds)
                ->pluck('user_id')
                ->map(fn($id) => (int) $id)
                ->all();

            $newSignerIds = $normalizedSignerIds
                ->reject(fn($id) => in_array((int) $id, $existingSignerIds, true))
                ->values();

            $addedCount = 0;
            if ($newSignerIds->isNotEmpty()) {
                $now = now();
                $rows = $newSignerIds->map(function ($userId) use ($documentId, $now) {
                    return [
                        'document_id' => $documentId,
                        'user_id' => (int) $userId,
                        'status_sign' => 'pending',
                        'signed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DocumentSigner::insert($rows);
                $addedCount = count($rows);
            }

            DB::commit();

            $document->load(['signers.user:id,name,email,role_id', 'signers.user.role:id,name']);
            return RepoResponse::success(
                [
                    'document' => $document,
                    'added_count' => $addedCount,
                    'already_exists_count' => count($existingSignerIds),
                ],
                $addedCount > 0
                    ? 'Signer berhasil ditambahkan'
                    : 'Semua user terpilih sudah menjadi signer'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return RepoResponse::error('Gagal menambahkan signer', $e->getMessage());
        }
    }

    public function remove_signer(int $documentId, int $userId)
    {
        $document = Document::find($documentId);
        if (!$document) {
            return RepoResponse::error('Dokumen tidak ditemukan');
        }

        $signer = DocumentSigner::where('document_id', $documentId)
            ->where('user_id', $userId)
            ->first();

        if (!$signer) {
            return RepoResponse::error('Signer tidak ditemukan');
        }

        $totalSigners = DocumentSigner::where('document_id', $documentId)->count();
        if ($totalSigners <= 1) {
            return RepoResponse::error('Dokumen harus memiliki minimal satu signer');
        }

        DB::beginTransaction();
        try {
            DocumentSignature::where('document_id', $documentId)
                ->where('user_id', $userId)
                ->delete();

            $signer->delete();

            DB::commit();

            $document->load(['signers.user:id,name,email,role_id', 'signers.user.role:id,name']);
            return RepoResponse::success(
                [
                    'document' => $document,
                    'removed_user_id' => $userId,
                ],
                'Signer berhasil dihapus'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return RepoResponse::error('Gagal menghapus signer', $e->getMessage());
        }
    }

    public function show($id, $user = null)
    {
        $user = $user ?: Auth::user();
        $doc = Document::with([
            'owner:id,name',
            'type:id,name',
            'signatures.user:id,name,email',
            'signers.user:id,name,email,role_id',
            'signers.user.role:id,name',
        ])->find($id);

        if (!$doc) {
            return RepoResponse::error('Dokumen tidak ditemukan');
        }

        if (!$this->canAccessDocument($id, $user)) {
            return RepoResponse::error('Akses ditolak');
        }

        return RepoResponse::success($doc, 'Dokumen ditemukan');
    }

    public function preview($id, $user = null)
    {
        $user = $user ?: Auth::user();
        $doc = Document::find($id);
        $disk = Storage::disk(config('filesystems.default'));
        if (!$doc) {
            abort(404, 'File tidak ditemukan');
        }

        if (!$this->canAccessDocument($id, $user)) {
            abort(403, 'Akses ditolak.');
        }

        if (!$disk->exists($doc->file_path)) {
            abort(404, 'File tidak ditemukan');
        }

        $stream = $disk->readStream($doc->file_path);
        if ($stream === false) {
            abort(404, 'File tidak ditemukan');
        }

        $fileName = basename($doc->file_path);
        $mime = $disk->mimeType($doc->file_path) ?? 'application/octet-stream';

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

    public function download($id, $user = null)
    {
        $user = $user ?: Auth::user();
        $doc = Document::find($id);
        $disk = Storage::disk(config('filesystems.default'));
        if (!$doc) {
            abort(404, 'File tidak ditemukan');
        }

        if (!$this->canAccessDocument($id, $user)) {
            abort(403, 'Akses ditolak.');
        }

        if (!$disk->exists($doc->file_path)) {
            abort(404, 'File tidak ditemukan');
        }

        $ext = pathinfo($doc->file_path, PATHINFO_EXTENSION);
        $downloadName = $doc->name . '.' . $ext;

        return $disk->download($doc->file_path, $downloadName);
    }

    public function canAccessDocument($documentId, $user = null): bool
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->isPrivilegedUser($user)) {
            return true;
        }

        return DocumentSigner::where('document_id', $documentId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function isAssignedSigner($documentId, $userId): bool
    {
        return DocumentSigner::where('document_id', $documentId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function markSignerSigned($documentId, $userId): void
    {
        DocumentSigner::where('document_id', $documentId)
            ->where('user_id', $userId)
            ->update([
                'status_sign' => 'signed',
                'signed_at' => now(),
            ]);
    }

    public function allSignersSigned($documentId): bool
    {
        return !DocumentSigner::where('document_id', $documentId)
            ->where(function ($q) {
                $q->where('status_sign', '!=', 'signed')
                    ->orWhereNull('signed_at');
            })
            ->exists();
    }

    private function isPrivilegedUser($user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('role');
        $roleName = $user->role->name ?? null;

        return in_array($roleName, ['admin', 'superadmin'], true);
    }

    public function addTemplate($documentId): bool
    {
        $doc = Document::find($documentId);

        if (!$doc) {
            return false;
        }

        $signers = $doc->signers()
            ->with('user.tilakaProfile')
            ->get();

        // Build assigned users from signers
        $assignedUsers = $signers
            ->filter(function ($signer) {
                return $signer->user && $signer->user->tilakaProfile;
            })
            ->map(function ($signer) {

                $tilaka = $signer->user->tilakaProfile;

                return [
                    'user_identifier' => $tilaka->user_identifier,
                    'email' => $tilaka->email,
                ];
            })
            ->values()
            ->toArray();

        if (empty($assignedUsers)) {
            Log::error('No valid assigned users found.');
            return false;
        }

        // Read document and convert to base64
        try {
            $disk = Storage::disk(config('filesystems.default'));
            if (!$disk->exists($doc->file_path)) {
                Log::error('Document file not found.');
                return false;
            }
            $fileContent = $disk->get($doc->file_path);
            $doc_base_64 = base64_encode($fileContent);
        } catch (\Throwable $e) {
            Log::error('Error occurred while reading document: ' . $e->getMessage());
            return false;
        }

        // Upload template to Tilaka
        try {

            $payload = [
                "file_name"       => $doc->name,
                "template_number" => "template_" . $documentId,
                "template_file"   => $doc_base_64,
                "assigned_users"  => $assignedUsers,
            ];

            $this->tilakaService->request(
                'POST',
                '/quicksign-addtemplates',
                $payload
            );

        } catch (\Throwable $e) {
            Log::error('Error occurred while uploading template: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
