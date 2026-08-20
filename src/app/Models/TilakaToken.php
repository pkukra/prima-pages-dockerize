<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TilakaToken extends Model
{
    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
        'token_type',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return !$this->expires_at
            || now()->gte($this->expires_at->subSeconds(60));
    }
}
