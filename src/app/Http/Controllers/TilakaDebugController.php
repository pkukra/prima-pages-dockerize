<?php

namespace App\Http\Controllers;

use App\Services\Tilaka\TilakaService;

class TilakaDebugController extends Controller
{
    public function getToken(TilakaService $tilaka)
    {
        // panggil method token secara langsung
        $token = app()->call(function () use ($tilaka) {
            // pakai reflection kecil karena getToken protected
            $ref = new \ReflectionClass($tilaka);
            $method = $ref->getMethod('getToken');
            $method->setAccessible(true);

            return $method->invoke($tilaka);
        });

        return response()->json([
            'token' => $token,
            'length' => strlen($token),
        ]);
    }

    public function getUuid(TilakaService $tilaka)
    {
        $query = http_build_query([
            'name'  => 'muhammad iqbal',
            'email' => 'emixbal@gmail.com',
        ]);

        $response = $tilaka->request(
            'POST',
            '/generateUUID?' . $query,
            [] // payload kosong, karena param ada di query string
        );

        return response()->json([
            'raw_response' => $response,
            'uuid'         => $response['data'][0] ?? null,
        ]);
    }

    public function validasiStatus(TilakaService $tilaka)
    {
        $payload = [
            'request_id' => '50DEA7D1-CCE5-4B0C-9140-568AEC6AB65E',
        ];
        $registerResponse = $tilaka->request(
            'POST',
            '/validasi',
            $payload
        );

        
    }
}
