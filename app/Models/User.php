<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Employee;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'password',
        'role_id',
        'branch_id',
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

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getAllPermissionsAttribute()
    {
        $directPermissions = $this->permissions ?? collect();

        $rolePermissions = $this->role
            ? $this->role->permissions
            : collect();

        return $directPermissions
            ->merge($rolePermissions)
            ->unique('id')
            ->values();
    }

    public function branches()
    {
        return $this->belongsToMany(\App\Models\Branch::class, 'branch_user');
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
