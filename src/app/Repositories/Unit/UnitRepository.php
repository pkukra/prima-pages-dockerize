<?php

namespace App\Repositories\Unit;

use App\Models\Unit;
use App\Helpers\RepoResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UnitRepository
{
    public function list()
    {
        $data = Unit::orderBy('name')->get();
        return RepoResponse::success($data);
    }

    public function find($id)
    {
        $unit = Unit::find($id);
        if (!$unit) return RepoResponse::error('Unit tidak ditemukan');
        return RepoResponse::success($unit);
    }

    public function store($request)
    {
        DB::beginTransaction();
        try {
            $unit = Unit::create([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => Auth::user()->email ?? 'system',
                'updated_by' => Auth::user()->email ?? 'system',
            ]);
            DB::commit();
            return RepoResponse::success($unit, 'Unit berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return RepoResponse::error('Gagal membuat unit', $e->getMessage());
        }
    }

    public function update($id, $request)
    {
        $unit = Unit::find($id);
        if (!$unit) return RepoResponse::error('Unit tidak ditemukan');

        try {
            $unit->update([
                'code' => $request->code ?? $unit->code,
                'name' => $request->name ?? $unit->name,
                'description' => $request->description ?? $unit->description,
                'updated_by' => Auth::user()->email ?? 'system',
            ]);
            return RepoResponse::success($unit, 'Unit berhasil diperbarui');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal memperbarui unit', $e->getMessage());
        }
    }

    public function delete($id)
    {
        $unit = Unit::find($id);
        if (!$unit) return RepoResponse::error('Unit tidak ditemukan');

        try {
            $unit->delete();
            return RepoResponse::success(null, 'Unit berhasil dihapus');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal menghapus unit', $e->getMessage());
        }
    }
}
