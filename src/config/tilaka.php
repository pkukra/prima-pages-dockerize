<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tilaka OAuth Credentials
    |--------------------------------------------------------------------------
    | Credential sistem (machine-to-machine), BUKAN user credential.
    | Diambil dari .env dan TIDAK boleh di-expose ke frontend.
    */

    'client_id' => env('TILAKA_CHANNEL_ID'),
    'client_secret' => env('TILAKA_CHANNEL_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Tilaka Endpoint Configuration
    |--------------------------------------------------------------------------
    | Bedakan sandbox / production lewat .env
    */

    'auth_url' => env('TILAKA_AUTH_URL'),
    'base_url' => env('TILAKA_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Grant Type
    |--------------------------------------------------------------------------
    | Fixed value sesuai OAuth2 spec & PRD Tilaka.
    | JANGAN dipindah ke ENV.
    */

    'grant_type' => 'client_credentials',

    /*
    |--------------------------------------------------------------------------
    | Token Cache Configuration
    |--------------------------------------------------------------------------
    | Token Tilaka short-lived (~5 menit).
    | Kita cache sedikit lebih pendek untuk safety.
    */

    'token_cache_key' => 'tilaka_access_token',
    'token_cache_ttl' => 240, // detik (4 menit)

];
