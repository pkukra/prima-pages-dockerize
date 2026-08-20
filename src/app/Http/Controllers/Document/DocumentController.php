<?php

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

use App\Helpers\ApiResponse;
use App\Repositories\Document\DocumentRepository;

class DocumentController extends Controller
{
    protected $pageRepo;
    protected $bridgingEKlaimRepo;
    protected $RanapMonitRepo;

    public function __construct(
        DocumentRepository $pageRepo,
    ) {
        $this->pageRepo = $pageRepo;
    }

    public function hash()
    {
        // Ambil konfigurasi dari .env
        $clientId = env('TILAKA_CHANNEL_ID');
        $clientSecret = env('TILAKA_CHANNEL_SECRET');

        // Pastikan tidak ada spasi tak sengaja
        $consent_text = trim("Terms of service are abc and d");
        $version = trim("TNT – v.1.0.1");
        $consent_timestamp = trim("2025-10-06 14:57:00");

        // Gabungkan string sesuai spesifikasi Tilaka
        $message = $clientId . $consent_text . $version . $consent_timestamp;

        // Hitung hash pakai HMAC-SHA256 (hasil hex lowercase)
        $hash_consent = hash_hmac('sha256', $message, $clientSecret);

        // Return hasil dengan informasi tambahan untuk debug
        return ApiResponse::success((object)[
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'message' => $message, // tambahkan agar bisa validasi di Postman
            'hash_consent' => $hash_consent,
        ]);
    }

    public function index()
    {
        return Inertia::render('Document/Index');
    }

    public function list_documents()
    {
        $result = $this->pageRepo->list_documents(request()->user());
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function add()
    {
        return Inertia::render('Document/Add');
    }

    public function list_owners()
    {
        $result = $this->pageRepo->owners();
        return response()->json($result);
    }

    public function list_signers()
    {
        $result = $this->pageRepo->signer_candidates();
        return ApiResponse::success($result, 'Data user signer');
    }

    public function list_types()
    {
        $result = $this->pageRepo->list_types();
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }


    public function store(Request $request)
    {
        if (!$this->isAdminOrSuperadmin($request->user())) {
            return ApiResponse::error('Akses ditolak.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', Rule::unique('documents', 'name')],
            'owner_id' => 'required|exists:document_owners,id',
            'type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|max:10240',
            'signer_ids' => 'required|array|min:1',
            'signer_ids.*' => 'required|integer|distinct|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $result = $this->pageRepo->store($request);
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }

        return ApiResponse::success($result->data, $result->message, 201);
    }

    public function index_data()
    {
        $data = $this->pageRepo->index();
        return ApiResponse::success($data);
    }

    public function add_signers(Request $request, $id)
    {
        if (!$this->isAdminOrSuperadmin($request->user())) {
            return ApiResponse::error('Akses ditolak.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'signer_ids' => 'required|array|min:1',
            'signer_ids.*' => 'required|integer|distinct|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $result = $this->pageRepo->add_signers((int) $id, (array) $request->input('signer_ids', []));
        if (!$result->status) {
            if ($result->message === 'Dokumen tidak ditemukan') {
                return ApiResponse::error($result->message, $result->errors, 404);
            }

            return ApiResponse::error($result->message, $result->errors, 500);
        }

        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function remove_signer(Request $request, $id, $userId)
    {
        if (!$this->isAdminOrSuperadmin($request->user())) {
            return ApiResponse::error('Akses ditolak.', null, 403);
        }

        $result = $this->pageRepo->remove_signer((int) $id, (int) $userId);
        if (!$result->status) {
            if (in_array($result->message, ['Dokumen tidak ditemukan', 'Signer tidak ditemukan'], true)) {
                return ApiResponse::error($result->message, $result->errors, 404);
            }

            if ($result->message === 'Dokumen harus memiliki minimal satu signer') {
                return ApiResponse::error($result->message, $result->errors, 422);
            }

            return ApiResponse::error($result->message, $result->errors, 500);
        }

        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function show($id)
    {
        $result = $this->pageRepo->show($id, request()->user());
        if (!$result->status) {
            if ($result->message === 'Akses ditolak') {
                return ApiResponse::error('Akses ditolak.', null, 403);
            }

            return ApiResponse::error($result->message, $result->errors, 404);
        }

        $document = $result->data;
        $user = request()->user();
        $isSigner = $document->signers->contains(function ($item) use ($user) {
            return (int) $item->user_id === (int) $user->id;
        });

        $document->is_assigned_signer = $isSigner;
        $document->can_sign = $isSigner;
        $document->all_signers_signed = $this->pageRepo->allSignersSigned((int) $document->id);

        return ApiResponse::success($result->data, $result->message);
    }

    public function preview($id)
    {
        return $this->pageRepo->preview($id, request()->user());
    }

    public function download($id)
    {
        return $this->pageRepo->download($id, request()->user());
    }

    public function viewPage($id)
    {
        if (!$this->pageRepo->canAccessDocument($id, request()->user())) {
            abort(403, 'Akses ditolak.');
        }

        return Inertia::render('Document/view', [
            'docId' => $id,
        ]);
    }

    private function isAdminOrSuperadmin($user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('role');
        $roleName = $user->role->name ?? null;

        return in_array($roleName, ['admin', 'superadmin'], true);
    }
}
