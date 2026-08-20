<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];
}
