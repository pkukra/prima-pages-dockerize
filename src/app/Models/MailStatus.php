<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailStatus extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'created_by',
        'updated_by',
        'code',
        'name',
        'type',
    ];
}
