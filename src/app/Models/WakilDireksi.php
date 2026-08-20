<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WakilDireksi extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
