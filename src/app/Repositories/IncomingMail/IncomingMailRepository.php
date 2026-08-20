<?php

namespace App\Repositories\IncomingMail;

use App\Models\IncomingMail;
use App\Models\IncomingMailRead;
use App\Helpers\RepoResponse;
use App\Helpers\IncomingMailHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\MailStatus;
use App\Models\IncomingMailType;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmail;
use App\Models\WakilDireksi;

class IncomingMailRepository
{
    public function list_incoming_mails($filter = null, $forDirut = false, $search = null, $incomingMailTypeId = null, $page = 1, $perPage = 8)
    {
        $userId = Auth::user()->id;
        $query = IncomingMail::query()
            ->with('type:id,name')
            ->orderBy('created_at', 'desc');

        // Jika dirut, hanya tampilkan READY_DIRUT
        if ($forDirut) {
            $query->where('status_code', 'READY_DIRUT');
        } else {
            // Filter berdasarkan read status (untuk user login) - hanya untuk superadmin
            if ($filter === 'read') {
                $query->whereHas('reads', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            } elseif ($filter === 'unread') {
                $query->whereDoesntHave('reads', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mail_number', 'like', '%' . $search . '%')
                    ->orWhere('sender', 'like', '%' . $search . '%')
                    ->orWhere('subject', 'like', '%' . $search . '%');
            });
        }

        if ($incomingMailTypeId) {
            $query->where('incoming_mail_type_id', $incomingMailTypeId);
        }

        $mails = $query->paginate($perPage, ['*'], 'page', $page);
        $mails->getCollection()->transform(function ($mail) use ($userId) {
            // Tambahkan info read & dirut status
            $mail->is_read = IncomingMailHelper::hasUserRead($mail->id, $userId);
            $mail->dirut_read = IncomingMailHelper::hasDirutRead($mail->id);
            $mail->all_wadir_read = IncomingMailHelper::allWadirRead($mail->id);
            return $mail;
        });

        return RepoResponse::success($mails);
    }

    public function statuses()
    {
        $data = MailStatus::select('code', 'name')->where('type', 'incoming')->get();
        return RepoResponse::success($data);
    }

    public function types()
    {
        $data = IncomingMailType::select('id', 'name')
            ->orderBy('name')
            ->get();
        return RepoResponse::success($data);
    }

    public function show($id)
    {
        $mail = IncomingMail::with('type:id,name')->find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');
        return RepoResponse::success($mail);
    }

    public function update($id, $request)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        try {
            $mail->update([
                'mail_number' => $request->mail_number ?? $mail->mail_number,
                'sender' => $request->sender ?? $mail->sender,
                'subject' => $request->subject ?? $mail->subject,
                'mail_date' => $request->mail_date ?? $mail->mail_date,
                'received_date' => $request->received_date ?? $mail->received_date,
                'summary' => $request->summary ?? $mail->summary,
                'status_code' => $request->status_code ?? $mail->status_code,
                'incoming_mail_type_id' => $request->incoming_mail_type_id ?? $mail->incoming_mail_type_id,
                'recipient_id' => $request->recipient_id ?? $mail->recipient_id,
                'updated_by' => Auth::user()->email ?? $mail->updated_by,
            ]);

            return RepoResponse::success($mail, 'Surat berhasil diperbarui');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal memperbarui surat', $e->getMessage());
        }
    }

    public function replace_document($id, $request)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        if (!$request->hasFile('file')) {
            return RepoResponse::error('File tidak ditemukan di request');
        }

        DB::beginTransaction();
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension();
            $fileName = $id . '_' . now()->format('YmdHis') . ($ext ? ('.' . $ext) : '');
            $filePath = $disk->putFileAs('incoming_mails', $file, $fileName);
            if (!$filePath) {
                throw new \RuntimeException('Gagal menyimpan file ke storage');
            }

            // delete old file if exists
            if ($mail->file_path && $disk->exists($mail->file_path)) {
                $disk->delete($mail->file_path);
            }

            $mail->file_path = $filePath;
            $mail->updated_by = Auth::user()->email ?? $mail->updated_by;
            $mail->save();

            DB::commit();
            return RepoResponse::success($mail, 'File berhasil diganti');
        } catch (\Exception $e) {
            DB::rollBack();
            if (!empty($filePath) && $disk->exists($filePath)) {
                $disk->delete($filePath);
            }
            return RepoResponse::error('Gagal mengganti file', $e->getMessage());
        }
    }

    public function edit_document($id, $request)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        try {
            $mail->summary = $request->summary ?? $mail->summary;
            $mail->updated_by = Auth::user()->email ?? $mail->updated_by;
            $mail->save();
            return RepoResponse::success($mail, 'Metadata dokumen berhasil diperbarui');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal memperbarui metadata', $e->getMessage());
        }
    }

    public function preview($id)
    {
        $mail = IncomingMail::find($id);
        if (!$mail || !$mail->file_path) {
            abort(404, 'File tidak ditemukan');
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (!$disk->exists($mail->file_path)) {
            abort(404, 'File tidak ditemukan');
        }

        $stream = $disk->readStream($mail->file_path);
        if ($stream === false) {
            abort(404, 'File tidak ditemukan');
        }

        $fileName = basename($mail->file_path);
        $mime = $disk->mimeType($mail->file_path) ?? 'application/octet-stream';

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

    public function store($request)
    {
        DB::beginTransaction();
        try {
            $filePath = null;
            $mailId = Str::uuid()->toString();
            if ($request->hasFile('file')) {
                $disk = Storage::disk(config('filesystems.default'));
                $file = $request->file('file');
                $ext = $file->getClientOriginalExtension();
                $fileName = $mailId . '_' . now()->format('YmdHis') . ($ext ? ('.' . $ext) : '');
                $filePath = $disk->putFileAs('incoming_mails', $file, $fileName);
                if (!$filePath) {
                    throw new \RuntimeException('Gagal menyimpan file ke storage');
                }
            }

            $mail = IncomingMail::create([
                'id' => $mailId,
                'created_by' => Auth::user()->email ?? 'system',
                'updated_by' => Auth::user()->email ?? 'system',
                'mail_number' => $request->mail_number,
                'sender' => $request->sender,
                'subject' => $request->subject,
                'mail_date' => $request->mail_date,
                'received_date' => $request->received_date,
                'summary' => $request->summary,
                'file_path' => $filePath,
                'incoming_mail_type_id' => $request->incoming_mail_type_id,
                'recipient_id' => $request->recipient_id,
            ]);

            DB::commit();
            $this->notifyNewIncomingMail($mail);
            return RepoResponse::success($mail, 'Surat masuk berhasil disimpan');
        } catch (\Exception $e) {
            if (!empty($filePath)) {
                $disk = Storage::disk(config('filesystems.default'));
                if ($disk->exists($filePath)) {
                    $disk->delete($filePath);
                }
            }
            DB::rollBack();
            return RepoResponse::error('Gagal menyimpan surat masuk', $e->getMessage());
        }
    }

    /**
     * Notify admin/superadmin/wadir when admin/superadmin adds new incoming mail.
     */
    private function notifyNewIncomingMail(IncomingMail $mail): void
    {
        $creatorRole = Auth::user()?->role?->name;
        if (!in_array($creatorRole, ['admin', 'superadmin'], true)) {
            return;
        }

        $emails = User::whereHas('role', function ($q) {
                $q->whereIn('name', ['admin', 'superadmin', 'wadir']);
            })
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            return;
        }

        $subject = 'Notifikasi Surat Masuk Baru';
        $message = "Perihal: {$mail->subject}\nDari: {$mail->sender}";
        $actionUrl = route('incoming.viewPage', ['id' => $mail->id]);

        try {
            Mail::to($emails)->send(new SendEmail($subject, $message, $actionUrl));
        } catch (\Throwable $e) {
            // Do not block the main flow if email fails
        }
    }

    /**
     * Mark incoming mail as read by current user
     */
    public function markAsRead($id)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        try {
            IncomingMailHelper::markAsRead($id, Auth::user()->id);
            return RepoResponse::success($mail, 'Surat ditandai sudah dibaca');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal menandai surat sebagai dibaca', $e->getMessage());
        }
    }

    /**
     * Set status to READY_DIRUT (hanya jika semua wakil direksi sudah baca)
     */
    public function setReadyForDirut($id)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        // Check if all wakil direksi have read
        if (!IncomingMailHelper::allWadirRead($id)) {
            return RepoResponse::error('Belum semua wakil direksi membaca surat', null, 422);
        }

        try {
            $mail->update([
                'status_code' => 'READY_DIRUT',
                'updated_by' => Auth::user()->email ?? $mail->updated_by,
            ]);

            $this->notifyReadyForDirut($mail);
            return RepoResponse::success($mail, 'Notifikasi Surat Masuk Baru');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal mengubah status surat', $e->getMessage());
        }
    }

    /**
     * Notify dirut when mail is ready.
     */
    private function notifyReadyForDirut(IncomingMail $mail): void
    {
        $emails = User::whereHas('role', function ($q) {
                $q->where('name', 'dirut');
            })
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            return;
        }

        $subject = 'Notifikasi Surat Masuk Baru';
        $message = "Perihal: {$mail->subject}\nDari: {$mail->sender}";
        $actionUrl = route('incoming.viewPage', ['id' => $mail->id]);

        try {
            Mail::to($emails)->send(new SendEmail($subject, $message, $actionUrl));
        } catch (\Throwable $e) {
            // Do not block the main flow if email fails
        }
    }

    /**
     * Get unread wakil direksi for a mail
     */
    public function getUnreadWadir($id)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        try {
            $unreadUserIds = IncomingMailHelper::getUnreadWadir($id);
            
            if (empty($unreadUserIds)) {
                $unreadWadirs = [];
            } else {
                $unreadWadirs = \App\Models\User::whereIn('id', $unreadUserIds)
                    ->select('id', 'name', 'email')
                    ->get();
            }

            return RepoResponse::success($unreadWadirs, 'Daftar wakil direksi yang belum baca');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal mengambil data', $e->getMessage());
        }
    }

    /**
     * Get read tracking for wakil direksi and dirut
     */
    public function getReadTracking($id)
    {
        $mail = IncomingMail::find($id);
        if (!$mail) return RepoResponse::error('Surat tidak ditemukan');

        try {
            $wakilDireksiUserIds = WakilDireksi::pluck('user_id')->toArray();

            $readWadir = IncomingMailRead::with('user:id,name,email')
                ->where('incoming_mail_id', $id)
                ->whereIn('user_id', $wakilDireksiUserIds)
                ->orderBy('read_at', 'asc')
                ->get()
                ->map(function ($read) {
                    return [
                        'id' => $read->user_id,
                        'name' => $read->user?->name,
                        'email' => $read->user?->email,
                        'read_at' => $read->read_at,
                    ];
                })
                ->values();

            $dirutRead = IncomingMailRead::with('user:id,name,email')
                ->where('incoming_mail_id', $id)
                ->whereHas('user', function ($query) {
                    $query->whereHas('role', function ($q) {
                        $q->where('name', 'dirut');
                    });
                })
                ->orderBy('read_at', 'asc')
                ->first();

            $dirutData = $dirutRead
                ? [
                    'read' => true,
                    'name' => $dirutRead->user?->name,
                    'email' => $dirutRead->user?->email,
                    'read_at' => $dirutRead->read_at,
                ]
                : ['read' => false];

            return RepoResponse::success([
                'read_wadir' => $readWadir,
                'dirut' => $dirutData,
            ], 'Tracking pembacaan surat');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal mengambil tracking baca', $e->getMessage());
        }
    }

    /**
     * Show surat untuk Dirut (hanya yang READY_DIRUT)
     */
    public function showDirut($id)
    {
        $mail = IncomingMail::where('id', $id)
            ->where('status_code', 'READY_DIRUT')
            ->with('type:id,name')
            ->first();

        if (!$mail) {
            return RepoResponse::error('Surat tidak ditemukan atau tidak dalam status siap untuk Dirut');
        }

        return RepoResponse::success($mail);
    }

    /**
     * List surat untuk Dirut (hanya READY_DIRUT)
     */
    public function listDirut()
    {
        try {
            $mails = IncomingMail::where('status_code', 'READY_DIRUT')
                ->orderBy('created_at', 'desc')
                ->get();

            return RepoResponse::success($mails, 'Daftar surat siap untuk Dirut');
        } catch (\Exception $e) {
            return RepoResponse::error('Gagal mengambil daftar surat', $e->getMessage());
        }
    }
}
