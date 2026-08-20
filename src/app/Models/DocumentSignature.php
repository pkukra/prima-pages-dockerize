<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'page',
        'x',
        'y',
        'width',
        'height',
        'signature_path',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'page' => 'integer',
        'x' => 'float',
        'y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'sort_order' => 'integer',
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
