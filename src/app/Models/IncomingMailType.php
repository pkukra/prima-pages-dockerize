<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingMailType extends Model
{
    protected $table = 'incoming_mails_type';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function incomingMails()
    {
        return $this->hasMany(IncomingMail::class, 'incoming_mail_type_id');
    }
}

