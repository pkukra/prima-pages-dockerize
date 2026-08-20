<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingMail extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'created_by',
        'updated_by',
        'mail_number',
        'sender',
        'subject',
        'mail_date',
        'received_date',
        'summary',
        'file_path',
        'status_code',
        'incoming_mail_type_id',
        'recipient_id',
    ];

    protected $casts = [
        'mail_date' => 'date',
        'received_date' => 'date',
    ];

    /**
     * Get the dispositions for this incoming mail.
     */
    public function dispositions()
    {
        return $this->hasMany(Disposition::class, 'incoming_mail_id', 'id');
    }

    /**
     * Get read-tracking records for this incoming mail.
     */
    public function reads()
    {
        return $this->hasMany(IncomingMailRead::class, 'incoming_mail_id', 'id');
    }

    /**
     * Get type of this incoming mail.
     */
    public function type()
    {
        return $this->belongsTo(IncomingMailType::class, 'incoming_mail_type_id');
    }
}
