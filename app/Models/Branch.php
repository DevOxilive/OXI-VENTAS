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
        'street',
        'external_number',
        'internal_number',
        'postal_code',
        'neighborhood',
        'municipality',
        'address_state',
        'maps_url',
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
