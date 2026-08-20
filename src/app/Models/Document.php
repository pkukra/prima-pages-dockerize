<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'file_path',
        'owner_id',
        'created_by',
        'updated_by',
        'type_id',
    ];

    public function owner()
    {
        return $this->belongsTo(DocumentOwner::class, 'owner_id');
    }

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'type_id');
    }

    public function signatures()
    {
        return $this->hasMany(DocumentSignature::class, 'document_id')
            ->orderBy('page')
            ->orderBy('sort_order');
    }

    public function signers()
    {
        return $this->hasMany(DocumentSigner::class, 'document_id');
    }
}
