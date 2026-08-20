<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;
use App\Models\Unit;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nik',
        'eklaim_key',
        'unit_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Define the relationship between User and Role.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The unit the user belongs to.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the Tilaka Profile for the user.
     */
    public function tilakaProfile()
    {
        return $this->hasOne(TilakaProfile::class);
    }

    public function documentSigners()
    {
        return $this->hasMany(DocumentSigner::class, 'user_id');
    }

    /**
     * Get the role name for the user.
     *
     * @return string
     */
    public function getRoleName()
    {
        return $this->role ? $this->role->name : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
