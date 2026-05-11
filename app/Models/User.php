<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Empleado;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'empleado_id',
        'name',
        'email',
        'password',
        'role_id',
        'sucursal_id',
    ];

    protected $hidden = [
        'password', 
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

   

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function permissions()
    {
        return $this->belongsToMany(\App\Models\Permission::class);
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getAllPermissionsAttribute()
    {
        $directos = $this->permissions ?? collect();

        $delRol = $this->role
            ? $this->role->permissions
            : collect();

        return $directos
            ->merge($delRol)
            ->unique('id')
            ->values();
    }
    public function sucursal()
{
    return $this->belongsTo(\App\Models\Sucursal::class);
}

    public function hasPermission($permission)
    {
        if ($this->permissions()->where('name', $permission)->exists()) {
            return true;
        }

        if ($this->role && $this->role->permissions()->where('name', $permission)->exists()) {
            return true;
        }

        return false;
    }
}