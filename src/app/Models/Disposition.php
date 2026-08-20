<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disposition extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'incoming_mail_id',
        'from_user_id',
        'to_user_id',
        'to_unit',
        'to_unit_id',
        'instruction',
        'due_date',
        'status',
        'is_unit_read',
        'resolved_note',
        'resolved_at',
        'resolved_by_user_id',
        'resolved_image_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_unit_read' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function mail()
    {
        return $this->belongsTo(IncomingMail::class, 'incoming_mail_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function unit()
    {
        return $this->belongsTo(\App\Models\Unit::class, 'to_unit_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
