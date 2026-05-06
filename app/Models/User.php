<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasProfilePhoto, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    } // 🔥 ESTA LLAVE FALTABA

    // 🔥 RELACIONES
    public function permissions()
    {
        return $this->belongsToMany(\App\Models\Permission::class);
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class);
    }

    // 🔥 TODOS LOS PERMISOS (ROL + DIRECTOS)
    public function getAllPermissionsAttribute()
{
    return $this->permissions;
}

    // 🔥 VERIFICAR PERMISO
public function hasPermission($permiso)
{
    $permisosDirectos = $this->permissions->pluck('name');

    $permisosRol = $this->role
        ? $this->role->permissions->pluck('name')
        : collect();

    return $permisosDirectos
        ->merge($permisosRol)
        ->contains($permiso);
}
}