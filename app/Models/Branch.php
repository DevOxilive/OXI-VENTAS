<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $table = 'branches';

    protected $fillable = [
        'name',
        'address',
        'slug',
        'color',
        'active',
        'attendance_latitude',
        'attendance_longitude',
        'attendance_geofence_radius_meters',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user');
    }

    public function inventories()
    {
        return $this->hasMany(BranchInventory::class);
    }
}
