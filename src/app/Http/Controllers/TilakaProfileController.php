<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Repositories\TilakaProfile\TilakaProfileRepository;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Storage;

class TilakaProfileController extends Controller
{
    protected $repo;

    public function __construct(TilakaProfileRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Show tilaka profile page
     */
    public function index()
    {
        return Inertia::render('TilakaProfile/Index');
    }

    /**
     * GET /tilaka/profile - Get authenticated user's tilaka profile
     */
    public function show(Request $request)
    {
        $userId = $request->user()->id;
        $result = $this->repo->getProfile($userId);

        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 404);
        }

        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * POST /tilaka/profile - Create or update tilaka profile (upsert)
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'nik' => 'required|string|max:16',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $userId = $request->user()->id;
        $result = $this->repo->upsertProfile($userId, $validated);

        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 400);
        }

        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * POST /tilaka/profile/upload - Upload KTP, selfie, or signature
     */
    public function uploadDocument(Request $request)
    {
        // Validate input
        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png',
            'document_type' => 'required|in:ktp,selfie,signature',
        ]);

        $userId = $request->user()->id;
        $documentType = $request->input('document_type');

        $result = $this->repo->uploadDocument($userId, $request, $documentType);

        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 400);
        }

        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * POST /tilaka/profile/submit - Submit profile for verification
     */
    public function submit(Request $request)
    {
        $userId = $request->user()->id;
        $result = $this->repo->submitForVerification($userId);

        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 400);
        }

        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * GET /tilaka/profile/document/{documentType} - Get document file
     */
    public function downloadDocument(Request $request, $documentType)
    {
        $userId = $request->user()->id;
        $result = $this->repo->getDocument($userId, $documentType);

        if (!$result->status) {
            abort(404, $result->message);
        }

        $filePath = $result->data['path'];

        if (!Storage::exists($filePath)) {
            abort(404, 'File tidak ditemukan');
        }

        return Storage::download($filePath);
    }

    /**
     * GET /tilaka/profile/preview/{documentType} - Preview document file
     */
    public function previewDocument(Request $request, $documentType)
    {
        $userId = $request->user()->id;
        $result = $this->repo->getDocument($userId, $documentType);

        if (!$result->status) {
            abort(404, $result->message);
        }

        $filePath = $result->data['path'];

        if (!Storage::exists($filePath)) {
            abort(404, 'File tidak ditemukan');
        }

        return Storage::response($filePath);
    }
    /**
     * POST /tilaka/profile/tilaka_userregstatus - Update user registration status
     */
    public function userRegStatus(Request $request)
    {
        $userId = $request->user()->id;
        $result = $this->repo->userRegStatus($userId);

        if (!$result->status) {
            return ApiResponse::error(
                $result->message,
                $result->errors,
                400
            );
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }
}
