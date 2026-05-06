<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name'];

 public function roles()
{
    return $this->belongsToMany(
        \App\Models\Role::class,
        'role_permission', // 👈 MISMA TABLA
        'permission_id',
        'role_id'
    );
}
    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class);
    }
}

    //
