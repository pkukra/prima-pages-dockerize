<?php

namespace App\Repositories\Disposition;

use App\Models\Disposition;
use App\Models\IncomingMail;
use App\Models\User;
use App\Helpers\RepoResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmail;
use App\Models\WakilDireksi;

class DispositionRepository
{
    public function listByMail($incomingMailId)
    {
        $data = Disposition::query()
            ->leftJoin('users as from_users', 'from_users.id', '=', 'dispositions.from_user_id')
            ->select('dispositions.*', 'from_users.name as from_user_name')
            ->with(['toUser:id,name', 'unit:id,code,name'])
            ->where('incoming_mail_id', $incomingMailId)
            ->orderBy('created_at', 'desc')
            ->get();

        return RepoResponse::success($data);
    }

    public function listByUnit($unitId)
    {
        $data = Disposition::query()
            ->leftJoin('users as from_users', 'from_users.id', '=', 'dispositions.from_user_id')
            ->select('dispositions.*', 'from_users.name as from_user_name')
            ->with(['mail:id,mail_number,subject', 'unit:id,code,name'])
            ->where('to_unit_id', $unitId)
            ->orderBy('created_at', 'desc')
            ->get();

        return RepoResponse::success($data);
    }

    public function listByCreator($userId)
    {
        $data = Disposition::query()
            ->leftJoin('users as from_users', 'from_users.id', '=', 'dispositions.from_user_id')
            ->select('dispositions.*', 'from_users.name as from_user_name')
            ->with(['mail:id,mail_number,subject', 'unit:id,code,name'])
            ->where('from_user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return RepoResponse::success($data);
    }

    public function listAll()
    {
        $data = Disposition::query()
            ->leftJoin('users as from_users', 'from_users.id', '=', 'dispositions.from_user_id')
            ->select('dispositions.*', 'from_users.name as from_user_name')
            ->with(['mail:id,mail_number,subject', 'unit:id,code,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return RepoResponse::success($data);
    }

    public function storeForMail($incomingMailId, $request)
    {
        DB::beginTransaction();
        try {
            $disp = Disposition::create([
                'incoming_mail_id' => $incomingMailId,
                'from_user_id' => Auth::id(),
                'to_user_id' => $request->to_user_id ?? null,
                'to_unit_id' => $request->to_unit_id ?? null,
                'instruction' => $request->instruction ?? null,
                'due_date' => $request->due_date ?? null,
                'status' => $request->status ?? 'open',
                'created_by' => Auth::user()->email ?? 'system',
                'updated_by' => Auth::user()->email ?? 'system',
            ]);

            DB::commit();
            $this->notifyDispositionAssigned($disp);
            return RepoResponse::success($disp, 'Disposisi berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return RepoResponse::error('Gagal membuat disposisi', $e->getMessage());
        }
    }


    private function createDispositionForAllWadir(Disposition $sourceDisp): void
    {
        $wadirUserIds = WakilDireksi::pluck('user_id');

        foreach ($wadirUserIds as $userId) {

            // optional: hindari duplikat jika creator = wadir tsb
            if ($userId == Auth::id()) {
                continue;
            }

            Disposition::create([
                'incoming_mail_id' => $sourceDisp->incoming_mail_id,
                'from_user_id'     => $sourceDisp->from_user_id,
                'to_user_id'       => $userId,
                'instruction'      => $sourceDisp->instruction,
                'due_date'         => $sourceDisp->due_date,
                'status'           => 'open',
                'created_by'       => $sourceDisp->created_by,
                'updated_by'       => $sourceDisp->updated_by,
            ]);
        }
    }


    public function updateDisposisi($id, $request)
    {
        $disp = Disposition::find($id);
        if (!$disp) return RepoResponse::error('Disposisi tidak ditemukan');

        try {
            $disp->instruction = $request->instruction ?? $disp->instruction;
            $disp->due_date = $request->due_date ?? $disp->due_date;
            $disp->to_unit_id = $request->to_unit_id ?? $disp->to_unit_id;
            $disp->to_user_id = $request->to_user_id ?? $disp->to_user_id;
            $disp->status = $request->status ?? $disp->status;
            $disp->updated_by = Auth::user()->email ?? 'system';
            $disp->save();

            return RepoResponse::success($disp, 'Disposisi berhasil diperbarui');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal memperbarui disposisi', $e->getMessage());
        }
    }

    public function deleteDisposisi($id)
    {
        $disp = Disposition::find($id);
        if (!$disp) return RepoResponse::error('Disposisi tidak ditemukan');

        try {
            $disp->delete();
            return RepoResponse::success(null, 'Disposisi berhasil dihapus');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal menghapus disposisi', $e->getMessage());
        }
    }

    public function resolveDisposisi($id, $note = null, $imagePath = null)
    {
        $disp = Disposition::find($id);
        if (!$disp) {
            return RepoResponse::error('Disposisi tidak ditemukan');
        }

        try {
            $disp->status = 'resolved';
            $disp->resolved_note = $note;
            $disp->resolved_at = now();
            $disp->resolved_by_user_id = Auth::id();

            if ($imagePath) {
                $disp->resolved_image_path = $imagePath;
            }

            $disp->updated_by = Auth::user()->email ?? 'system';
            $disp->save();

            // 🔔 kirim notifikasi ke pemberi disposisi
            $this->notifyDispositionAssigned($disp);

            return RepoResponse::success($disp, 'Disposisi berhasil di-resolve');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal resolve disposisi', $e->getMessage());
        }
    }

    private function notifyDispositionAssigned(Disposition $disp): void
    {
        $creatorRole = Auth::user()?->role?->name;

        // hanya jika dibuat oleh dirut / wadir
        if (!in_array($creatorRole, ['dirut', 'wadir'], true)) {
            return;
        }

        $emails = collect();
        $targetUsers = [];
        $targetUnits = [];

        // ===== Tujuan USER =====
        if ($disp->to_user_id) {
            $user = User::select('name', 'email')
                ->where('id', $disp->to_user_id)
                ->first();

            if ($user?->email) {
                $emails->push($user->email);
                $targetUsers[] = $user->name;
            }
        }

        // ===== Tujuan UNIT =====
        if ($disp->to_unit_id) {
            $unitUsers = User::where('unit_id', $disp->to_unit_id)
                ->whereNotNull('email')
                ->get(['name', 'email']);

            foreach ($unitUsers as $u) {
                $emails->push($u->email);
            }

            // nama unit (opsional, kalau ada tabel units)
            $unitName = optional(
                \App\Models\Unit::find($disp->to_unit_id)
            )->name;

            if ($unitName) {
                $targetUnits[] = $unitName;
            }
        }

        // ===== SEMUA WADIR =====
        $wadirEmails = WakilDireksi::query()
            ->join('users', 'users.id', '=', 'wakil_direksis.user_id')
            ->whereNotNull('users.email')
            ->pluck('users.email');

        $emails = $emails->merge($wadirEmails);

        // ===== Cleanup =====
        $emails = $emails->unique()->values()->all();
        if (empty($emails)) {
            return;
        }

        $mail = IncomingMail::find($disp->incoming_mail_id);

        // ===== BODY EMAIL =====
        $message =
            "Perihal        : " . ($mail->subject ?? '-') . "\n" .
            "Instruksi     : " . ($disp->instruction ?? '-') . "\n" .
            "Tujuan User   : " . (!empty($targetUsers) ? implode(', ', $targetUsers) : '-') . "\n" .
            "Tujuan Unit   : " . (!empty($targetUnits) ? implode(', ', $targetUnits) : '-') . "\n" .
            "Status        : " . ($disp->status ?? '-');

        $subject = 'Notifikasi Disposisi Baru';

        $actionUrl = route('incoming.viewPage', [
            'id' => $disp->incoming_mail_id
        ]);

        try {
            Mail::to($emails)->send(
                new SendEmail($subject, $message, $actionUrl)
            );
            \Log::info('Email disposisi terkirim', [
                'disposition_id' => $disp->id,
                'incoming_mail_id' => $disp->incoming_mail_id,
                'creator_role' => $creatorRole,
                'emails' => $emails,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Gagal kirim email disposisi', [
                'error' => $e->getMessage(),
                'emails' => $emails
            ]);
        }
    }
}
