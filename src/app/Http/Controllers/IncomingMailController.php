<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Repositories\IncomingMail\IncomingMailRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;

class IncomingMailController extends Controller
{
    protected $repo;

    public function __construct(IncomingMailRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index()
    {
        return Inertia::render('IncomingMail/Index');
    }

    public function list_incoming_mails(Request $request)
    {
        $filter = $request->query('filter'); // 'read', 'unread', atau null
        $search = trim((string) $request->query('queryfilter', $request->query('search', '')));
        $incomingMailTypeId = (int) $request->query('incoming_mail_type_id', 0);
        $page = max((int) $request->query('page', 1), 1);
        $perPage = (int) $request->query('per_page', 8);
        $perPage = max(min($perPage, 100), 1);

        if ($search === '') {
            $search = null;
        }

        if ($incomingMailTypeId <= 0) {
            $incomingMailTypeId = null;
        }

        $user = Auth::user();

        // Jika dirut, auto filter READY_DIRUT
        $forDirut = $user->role->name === 'dirut';

        $result = $this->repo->list_incoming_mails($filter, $forDirut, $search, $incomingMailTypeId, $page, $perPage);
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function list_statuses()
    {
        $result = $this->repo->statuses();
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function list_types()
    {
        $result = $this->repo->types();
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function viewPage($id)
    {
        $user = Auth::user();

        // Jika dirut, cek apakah surat status READY_DIRUT
        if ($user->role->name === 'dirut') {
            $result = $this->repo->showDirut($id);
        } else {
            $result = $this->repo->show($id);
        }

        if (!$result->status) {
            abort(404, $result->message);
        }

        $statuses = $this->repo->statuses();

        return Inertia::render('IncomingMail/View', [
            'id' => $id,
            'mail' => $result->data,
            'statuses' => $statuses->data ?? [],
            'isDirut' => $user->role->name === 'dirut',
        ]);
    }

    public function show($id)
    {
        $result = $this->repo->show($id);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 404);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'mail_number' => ['required', Rule::unique('incoming_mails', 'mail_number')->ignore($id, 'id')],
            'sender' => 'required|string',
            'subject' => 'required|string',
            'mail_date' => 'required|date',
            'received_date' => 'required|date',
            'incoming_mail_type_id' => 'nullable|exists:incoming_mails_type,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $result = $this->repo->update($id, $request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function replace_document(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:512000',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        $result = $this->repo->replace_document($id, $request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function edit_document(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'summary' => 'nullable|string',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        $result = $this->repo->edit_document($id, $request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function preview($id)
    {
        return $this->repo->preview($id);
    }

    public function add()
    {
        return Inertia::render('IncomingMail/Add');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_number' => ['required', Rule::unique('incoming_mails', 'mail_number')],
            'sender' => 'required|string',
            'subject' => 'required|string',
            'mail_date' => 'required|date',
            'received_date' => 'required|date',
            'incoming_mail_type_id' => 'nullable|exists:incoming_mails_type,id',
            'file' => 'nullable|file|max:1048576',
        ]);

        if ($validator->fails()) {
            Log::warning('Incoming mail validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['file']),
            ]);

            return ApiResponse::error(
                'Validasi gagal',
                $validator->errors(),
                422
            );
        }

        $result = $this->repo->store($request);

        if (!$result->status) {
            Log::error('Incoming mail store failed', [
                'message' => $result->message,
                'errors' => $result->errors,
                'mail_number' => $request->mail_number,
            ]);

            return ApiResponse::error(
                $result->message,
                $result->errors,
                500
            );
        }

        return ApiResponse::success(
            $result->data,
            $result->message,
            201
        );
    }

    /**
     * Mark incoming mail as read
     * POST /incoming-mails/{id}/read
     */
    public function markAsRead($id)
    {
        $result = $this->repo->markAsRead($id);
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * Set status to READY_DIRUT (admin only)
     * PATCH /incoming-mails/{id}/ready-dirut
     */
    public function setReadyForDirut($id)
    {
        $result = $this->repo->setReadyForDirut($id);
        if (!$result->status) {
            $statusCode = $result->errors ? 422 : 500;
            return ApiResponse::error($result->message, $result->errors, $statusCode);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * Get unread wakil direksi for this mail
     * GET /incoming-mails/{id}/unread-wadir
     */
    public function getUnreadWadir($id)
    {
        $result = $this->repo->getUnreadWadir($id);
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }

    /**
     * Get read tracking (wadir + dirut)
     * GET /incoming-mails/{id}/read-tracking
     */
    public function getReadTracking($id)
    {
        $result = $this->repo->getReadTracking($id);
        if (!$result->status) {
            return ApiResponse::error($result->message, $result->errors, 500);
        }
        return ApiResponse::success($result->data, $result->message, 200);
    }
}
