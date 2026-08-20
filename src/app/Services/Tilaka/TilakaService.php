<?php

namespace App\Services\Tilaka;

use App\Models\TilakaToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TilakaService
{
    /**
     * Ambil access token Tilaka
     * - DB sebagai source of truth
     * - Cache hanya optimasi
     */
    protected function getToken(): string
    {
        return Cache::remember('tilaka_access_token', 300, function () {

            return Cache::lock('tilaka_token_lock', 10)->block(5, function () {

                $token = TilakaToken::first();

                // Jika token ada & belum expired → pakai
                if ($token && !$token->isExpired()) {
                    return $token->access_token;
                }

                // Kalau tidak ada / expired → request baru
                return $this->requestNewToken();
            });
        });
    }

    /**
     * Request token baru ke Tilaka
     */
    protected function requestNewToken(): string
    {
        $res = Http::asForm()->post(
            config('tilaka.auth_url'),
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('tilaka.client_id'),
                'client_secret' => config('tilaka.client_secret'),
            ]
        );

        if ($res->failed()) {
            Log::error('Tilaka token request failed', [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);

            throw new \Exception('Gagal ambil token Tilaka');
        }

        $data = $res->json();

        TilakaToken::updateOrCreate(
            ['id' => 1],
            [
                'access_token' => $data['access_token'],
                'expires_at'   => now()->addSeconds($data['expires_in']),
                'token_type'   => $data['token_type'] ?? 'Bearer',
            ]
        );

        return $data['access_token'];
    }

    /**
     * Generic request ke API Tilaka
     * (FLEXIBLE: ganti method, url, payload saja)
     */
    public function request(
        string $method,
        string $uri,
        array $payload = []
    ): array {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->send($method, config('tilaka.base_url') . $uri, [
                'json' => $payload,
            ]);

        // retry 1x kalau token invalid
        if ($response->status() === 401) {
            Cache::forget('tilaka_access_token');

            $token = $this->getToken();

            $response = Http::withToken($token)
                ->acceptJson()
                ->send($method, config('tilaka.base_url') . $uri, [
                    'json' => $payload,
                ]);
        }

        if ($response->failed()) {
            Log::error('Tilaka API error', [
                'uri'    => $uri,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \Exception('Tilaka API error');
        }

        return $response->json();
    }

    public function request_local(
        string $method,
        string $uri,
        array $payload = []
    ): array {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->send($method, "http://10.10.10.88:8088" . $uri, [
                'json' => $payload,
            ]);

        // retry 1x kalau token invalid
        if ($response->status() === 401) {
            Cache::forget('tilaka_access_token');

            $token = $this->getToken();

            $response = Http::withToken($token)
                ->acceptJson()
                ->send($method, config('tilaka.base_url') . $uri, [
                    'json' => $payload,
                ]);
        }

        if ($response->failed()) {
            Log::error('Tilaka API error', [
                'uri'    => $uri,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \Exception('Tilaka API error');
        }

        return $response->json();
    }

    public function request_local_multipart(
        string $method,
        string $uri,
        array $multipart
    ): array {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->send($method, "http://10.10.10.88:8088" . $uri, [
                'multipart' => $multipart,
            ]);

        // retry 1x kalau token invalid
        if ($response->status() === 401) {
            Cache::forget('tilaka_access_token');

            $token = $this->getToken();

            $response = Http::withToken($token)
                ->acceptJson()
                ->send($method, config('tilaka.base_url') . $uri, [
                    'multipart' => $multipart,
                ]);
        }

        if ($response->failed()) {
            Log::error('Tilaka API error (multipart)', [
                'uri'    => $uri,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \Exception('Tilaka API error (multipart)');
        }

        return $response->json();
    }
}
