<?php

namespace App\Helpers;

use App\Models\IncomingMail;
use App\Models\IncomingMailRead;
use App\Models\WakilDireksi;

class IncomingMailHelper
{
    /**
     * Catat bahwa user telah membaca surat
     */
    public static function markAsRead(string $incomingMailId, int $userId): bool
    {
        try {
            $existingRead = IncomingMailRead::where('incoming_mail_id', $incomingMailId)
                ->where('user_id', $userId)
                ->first();

            if (!$existingRead) {
                IncomingMailRead::create([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'incoming_mail_id' => $incomingMailId,
                    'user_id' => $userId,
                    'read_at' => now(),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error marking mail as read', [
                'incoming_mail_id' => $incomingMailId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cek apakah semua wakil direksi sudah membaca surat
     */
    public static function allWadirRead(string $incomingMailId): bool
    {
        // Ambil semua user_id yang merupakan wakil direksi
        $wakilDireksiUserIds = WakilDireksi::pluck('user_id')->toArray();

        if (empty($wakilDireksiUserIds)) {
            // Jika tidak ada wakil direksi terdaftar, anggap sudah selesai
            return true;
        }

        // Cek berapa banyak wakil direksi yang sudah membaca
        $readCount = IncomingMailRead::where('incoming_mail_id', $incomingMailId)
            ->whereIn('user_id', $wakilDireksiUserIds)
            ->distinct('user_id')
            ->count('user_id');

        // Jika jumlah yang membaca == jumlah wakil direksi, semua sudah baca
        return $readCount === count($wakilDireksiUserIds);
    }

    /**
     * Cek apakah user sudah membaca surat tertentu
     */
    public static function hasUserRead(string $incomingMailId, int $userId): bool
    {
        return IncomingMailRead::where('incoming_mail_id', $incomingMailId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Cek apakah dirut (user dengan role 'dirut') sudah membaca surat
     */
    public static function hasDirutRead(string $incomingMailId): bool
    {
        return IncomingMailRead::whereHas('user', function ($query) {
            $query->whereHas('role', function ($q) {
                $q->where('name', 'dirut');
            });
        })
            ->where('incoming_mail_id', $incomingMailId)
            ->exists();
    }

    /**
     * Ambil daftar wakil direksi yang belum membaca surat
     */
    public static function getUnreadWadir(string $incomingMailId): array
    {
        $wakilDireksiUserIds = WakilDireksi::pluck('user_id')->toArray();

        $readUserIds = IncomingMailRead::where('incoming_mail_id', $incomingMailId)
            ->whereIn('user_id', $wakilDireksiUserIds)
            ->pluck('user_id')
            ->toArray();

        return array_diff($wakilDireksiUserIds, $readUserIds);
    }
}
