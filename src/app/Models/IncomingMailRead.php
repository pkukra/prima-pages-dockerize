<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingMailRead extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'incoming_mail_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function mail()
    {
        return $this->belongsTo(IncomingMail::class, 'incoming_mail_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
