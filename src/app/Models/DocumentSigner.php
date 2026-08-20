<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSigner extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'status_sign',
        'signed_at',
    ];

    protected $casts = [
        'document_id' => 'integer',
        'user_id' => 'integer',
        'signed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

