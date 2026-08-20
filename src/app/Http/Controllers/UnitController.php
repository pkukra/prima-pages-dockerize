<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Helpers\ApiResponse;
use App\Repositories\Unit\UnitRepository;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    protected $repo;

    public function __construct(UnitRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index()
    {
        return Inertia::render('Units/Index');
    }

    public function list()
    {
        $result = $this->repo->list();
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:units,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        $result = $this->repo->store($request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:units,code,' . $id,
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) return ApiResponse::error('Validasi gagal', $validator->errors(), 422);

        $result = $this->repo->update($id, $request);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success($result->data, $result->message, 200);
    }

    public function destroy($id)
    {
        $result = $this->repo->delete($id);
        if (!$result->status) return ApiResponse::error($result->message, $result->errors, 500);
        return ApiResponse::success(null, $result->message, 200);
    }
}
