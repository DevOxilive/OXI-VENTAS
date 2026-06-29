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
        return $this->belongsToMany(Permission::class);
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

    if ($this->relationLoaded('permissions') && $this->permissions->contains('name', $permission)) {
        return true;
    }

    if (
        $this->relationLoaded('role') &&
        $this->role?->relationLoaded('permissions') &&
        $this->role->permissions->contains('name', $permission)
    ) {
        return true;
    }

    return $this->permissions()->where('name', $permission)->exists()
        || $this->role?->permissions()->where('name', $permission)->exists();
}
}
