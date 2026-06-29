<?php

namespace App\Models;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        return $this->belongsToMany(Permission::class)
            ->withPivot('mode')
            ->withTimestamps();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getAllPermissionsAttribute()
    {
        $this->loadMissing(['role.permissions', 'permissions']);

        $rolePermissions = ($this->role?->permissions ?? collect())->keyBy('id');
        $directPermissions = ($this->permissions ?? collect())->keyBy('id');

        $allowedPermissions = $directPermissions
            ->filter(fn ($permission) => ($permission->pivot?->mode ?? 'allow') === 'allow');

        $deniedPermissionIds = $directPermissions
            ->filter(fn ($permission) => $permission->pivot?->mode === 'deny')
            ->keys();

        return $rolePermissions
            ->merge($allowedPermissions)
            ->except($deniedPermissionIds->all())
            ->values();
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_user');
    }

    public function assignedPhysicalCounts()
    {
        return $this->belongsToMany(PhysicalCount::class, 'physical_count_user')
            ->withTimestamps();
    }

    public function hasPermission($permission)
    {
        if ($this->role?->name === 'Administrador') {
            return true;
        }

        return $this->all_permissions->contains('name', $permission);
    }
}
