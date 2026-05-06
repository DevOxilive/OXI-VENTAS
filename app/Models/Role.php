<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(
            \App\Models\Permission::class,
            'role_permission', // 👈 tabla pivot
            'role_id',
            'permission_id'
        );
    }
    
}