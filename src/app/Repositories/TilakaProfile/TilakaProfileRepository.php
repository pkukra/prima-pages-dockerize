<?php

namespace App\Repositories\TilakaProfile;

use App\Models\TilakaProfile;
use App\Helpers\RepoResponse;
use App\Services\Tilaka\TilakaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TilakaProfileRepository
{
    protected $tilakaService;

    public function __construct(TilakaService $tilakaService)
    {
        $this->tilakaService = $tilakaService;
    }

    /**
     * Get authenticated user's tilaka profile
     */
    public function getProfile($userId)
    {
        $profile = TilakaProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return RepoResponse::error('Profil Tilaka tidak ditemukan');
        }

        return RepoResponse::success($profile);
    }

    /**
     * Create or update tilaka profile (upsert)
     */
    public function upsertProfile($userId, $data)
    {
        DB::beginTransaction();
        try {
            $profile = TilakaProfile::where('user_id', $userId)->first();

            $fillableData = [
                'nik' => $data['nik'] ?? null,
                'full_name' => $data['full_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'updated_by' => Auth::user()->email ?? 'system',
            ];

            if ($profile) {
                // Update existing profile only if editable
                if (!$profile->canEdit()) {
                    return RepoResponse::error('Profil tidak dapat diubah. Status: ' . $profile->verification_status);
                }

                $profile->update($fillableData);
            } else {
                // Create new profile
                $profile = TilakaProfile::create(array_merge(
                    $fillableData,
                    [
                        'id' => Str::uuid(),
                        'user_id' => $userId,
                        'created_by' => Auth::user()->email ?? 'system',
                        'verification_status' => 'draft',
                    ]
                ));
            }

            DB::commit();
            return RepoResponse::success($profile, 'Profil Tilaka berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();
            return RepoResponse::error('Gagal menyimpan profil Tilaka', $e->getMessage());
        }
    }

    /**
     * Upload document (KTP, selfie, or signature)
     */
    public function uploadDocument($userId, $request, $documentType)
    {
        // Validate document type
        if (!in_array($documentType, ['ktp', 'selfie', 'signature'])) {
            return RepoResponse::error('Tipe dokumen tidak valid. Gunakan: ktp, selfie, atau signature');
        }

        $profile = TilakaProfile::where('user_id', $userId)->first();
        if (!$profile) {
            return RepoResponse::error('Profil Tilaka tidak ditemukan');
        }

        // Check if profile can be edited
        if (!$profile->canEdit()) {
            return RepoResponse::error('Dokumen tidak dapat diunggah. Status: ' . $profile->verification_status);
        }

        // Validate file
        if (!$request->hasFile('file')) {
            return RepoResponse::error('File tidak ditemukan di request');
        }

        $file = $request->file('file');
        $mimes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Validate MIME type
        if (!in_array($file->getMimeType(), $mimes)) {
            return RepoResponse::error('File harus berupa gambar (JPG/PNG)');
        }

        // Validate file size
        if ($file->getSize() > $maxSize) {
            return RepoResponse::error('Ukuran file maksimal 5MB');
        }

        DB::beginTransaction();
        try {
            // Determine storage path and field
            $pathField = match ($documentType) {
                'ktp' => 'photo_ktp_path',
                'selfie' => 'selfie_path',
                'signature' => 'signature_path',
                default => null,
            };

            if (!$pathField) {
                throw new \RuntimeException('Tipe dokumen tidak valid');
            }

            $storagePath = 'tilaka_profiles/' . $userId;

            // Delete old file if exists
            $oldPath = $profile->{$pathField};
            if ($oldPath && Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }

            // Store new file
            $fileName = $documentType . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($storagePath, $fileName);

            // Update profile
            $profile->update([
                $pathField => $filePath,
                'updated_by' => Auth::user()->email ?? 'system',
            ]);

            DB::commit();
            return RepoResponse::success($profile, 'Dokumen ' . $documentType . ' berhasil diunggah');
        } catch (\Exception $e) {
            DB::rollBack();
            return RepoResponse::error('Gagal mengunggah dokumen', $e->getMessage());
        }
    }

    /**
     * Submit profile for verification
     */
    public function submitForVerification($userId)
    {
        $profile = TilakaProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return RepoResponse::error('Profil Tilaka tidak ditemukan');
        }

        /**
         * ============================
         * Validate Required Fields
         * ============================
         */
        $requiredFields = ['nik', 'full_name', 'email', 'photo_ktp_path'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($profile->{$field})) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return RepoResponse::error(
                'Data tidak lengkap. Field yang diperlukan: ' . implode(', ', $missingFields)
            );
        }

        /**
         * ============================
         * Validate KTP File Exists
         * ============================
         */
        if (!Storage::exists($profile->photo_ktp_path)) {
            return RepoResponse::error('File KTP tidak ditemukan di storage');
        }

        DB::beginTransaction();

        try {

            /**
             * ============================
             * STEP 1: Generate UUID (IF NEEDED)
             * ============================
             */
            if (empty($profile->tilaka_uuid)) {

                $query = http_build_query([
                    'name'  => $profile->full_name,
                    'email' => $profile->email,
                ]);

                $response = $this->tilakaService->request(
                    'POST',
                    '/generateUUID?' . $query,
                    []
                );

                $tilaka_uuid = data_get($response, 'data.0');

                if (!$tilaka_uuid) {
                    throw new \RuntimeException('UUID Tilaka tidak ditemukan di response');
                }

                $profile->update([
                    'tilaka_uuid' => $tilaka_uuid,
                    'updated_by'  => Auth::user()->email ?? 'system',
                ]);
            } else {
                $tilaka_uuid = $profile->tilaka_uuid;
            }

            /**
             * ============================
             * STEP 2: Register User KYC
             * ============================
             */
            $consentTimestamp = now()->format('Y-m-d H:i:s');

            $consent_text = "Dengan ini saya menyetujui syarat dan ketentuan penggunaan layanan Tilaka.";

            $consent_version = "TNT – v.1.0.1";

            /**
             * PRD:
             * HMAC-SHA256(
             *   client_secret,
             *   channel_id + consent_text + version + consent_timestamp
             * )
             */
            $hashConsent = hash_hmac(
                'sha256',
                config('tilaka.client_id')
                    . $consent_text
                    . $consent_version
                    . $consentTimestamp,
                config('tilaka.client_secret')
            );

            /**
             * ============================
             * Read KTP File From Storage
             * ============================
             */
            $mime = Storage::mimeType($profile->photo_ktp_path);

            if (!$mime) {
                throw new \RuntimeException('Gagal membaca MIME type file KTP');
            }

            $data = Storage::get($profile->photo_ktp_path);

            if ($data === false || empty($data)) {
                throw new \RuntimeException('Gagal membaca file KTP');
            }

            $registerPayload = [
                'registration_id'   => $tilaka_uuid,
                'email'             => $profile->email,
                'name'              => $profile->full_name,
                'nik'               => $profile->nik,
                'date_expire'       => now()->addDays(7)->format('Y-m-d 23:59'),
                'is_approved'       => true,
                'consent_text'      => $consent_text,
                'version'           => $consent_version,
                'consent_timestamp' => $consentTimestamp,
                'hash_consent'      => $hashConsent,
                'photo_ktp'         => 'data:' . $mime . ';base64,' . base64_encode($data),
            ];

            $registerResponse = $this->tilakaService->request(
                'POST',
                '/registerForKycCheck',
                $registerPayload
            );

            /**
             * ============================
             * STEP 3: Update Status
             * ============================
             */
            $tilakaReturnedUuid = data_get($registerResponse, 'data.0');

            if (
                data_get($registerResponse, 'success') === true
                && !empty($tilakaReturnedUuid)
            ) {
                $profile->update([
                    'verification_status' => 'submitted',
                    'updated_by'          => Auth::user()->email ?? 'system',
                ]);
            }
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Tilaka submit verification failed', [
                'user_id'    => $userId,
                'profile_id' => $profile->id ?? null,
                'error'      => $e->getMessage(),
            ]);

            return RepoResponse::error(
                'Gagal submit profil ke Tilaka',
                ['exception' => $e->getMessage()]
            );
        }

        $is_tilaka_success = data_get($registerResponse, 'success') === true;

        if (!$is_tilaka_success) {

            DB::rollBack();

            return RepoResponse::error(
                'Tilaka menolak pendaftaran KYC',
                ['response' => $registerResponse]
            );
        }

        DB::commit();

        return RepoResponse::success(
            $registerResponse,
            'Response dari Tilaka'
        );
    }

    /**
     * Get document file
     */
    public function getDocument($userId, $documentType)
    {
        $profile = TilakaProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return RepoResponse::error('Profil Tilaka tidak ditemukan');
        }

        $pathField = match ($documentType) {
            'ktp' => 'photo_ktp_path',
            'selfie' => 'selfie_path',
            'signature' => 'signature_path',
            default => null,
        };

        if (!$pathField) {
            return RepoResponse::error('Tipe dokumen tidak valid');
        }

        $filePath = $profile->{$pathField};

        if (!$filePath || !Storage::exists($filePath)) {
            return RepoResponse::error('Dokumen tidak ditemukan');
        }

        return RepoResponse::success(['path' => $filePath]);
    }

    /**
     * Get status user id
     */
    public function userRegStatus($userId)
    {
        $profile = TilakaProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return RepoResponse::error('Profil Tilaka tidak ditemukan');
        }

        if (empty($profile->tilaka_uuid)) {
            return RepoResponse::error('tilaka_uuid belum di set');
        }

        try {
            $response = $this->tilakaService->request(
                'POST',
                '/userregstatus',
                [
                    'register_id' => $profile->tilaka_uuid,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('userregstatus gagal', [
                'user_id'     => $userId,
                'register_id' => $profile->tilaka_uuid,
                'error'       => $e->getMessage(),
            ]);

            return RepoResponse::error(
                'Gagal mengambil status registrasi Tilaka',
                ['exception' => $e->getMessage()]
            );
        }

        $data = $response['data'] ?? null;
        unset($data['photo_selfie']);
        Log::info(json_encode($data));
        try {
            $fillableData = [
                'tilaka_name' => $data['tilaka_name'] ?? null,
                'verification_result' => $data ?? null,
                'updated_by' => Auth::user()->email ?? 'system',
            ];

            if ($profile) {
                // // Update existing profile only if editable
                // if (!$profile->canEdit()) {
                //     return RepoResponse::error('Profil tidak dapat diubah. Status: ' . $profile->verification_status);
                // }
                $profile->update($fillableData);
            }
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan status registrasi Tilaka', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            DB::rollBack();
            return RepoResponse::error('Gagal menyimpan profil Tilaka', $e->getMessage());
        }

        DB::commit();
        return RepoResponse::success(
            $data,
            'Berhasil mengambil status registrasi Tilaka'
        );
    }
}
