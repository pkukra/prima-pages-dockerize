<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TilakaProfile extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'tilaka_uuid',
        'created_by',
        'updated_by',
        'nik',
        'full_name',
        'email',
        'tilaka_name',
        'user_identifier',
        'phone',
        'photo_ktp_path',
        'selfie_path',
        'signature_path',
        'verification_status',
        'rejection_reason',
        'verification_result',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'verification_result' => 'object',
    ];

    /**
     * Relation ke User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if profile can be edited
     */
    public function canEdit(): bool
    {
        return in_array($this->verification_status, ['draft', 'rejected']);
    }

    /**
     * Check if profile is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->verification_status === 'submitted';
    }

    /**
     * Check if profile is approved
     */
    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    /**
     * Check if profile is rejected
     */
    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }
}
