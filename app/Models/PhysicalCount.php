<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PhysicalCount extends Model
{
 protected $fillable = [
    'folio',
    'branch_id',
    'created_by',
    'name',
    'status',
    'recapture_scope',
    'started_at',
    'closed_at',
    'recapture_started_at',
    'last_applied_at',
];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'recapture_started_at' => 'datetime',
        'last_applied_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries()
    {
        return $this->hasMany(PhysicalCountEntry::class);
    }

    public function snapshot()
    {
        return $this->hasOne(PhysicalCountSnapshot::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'physical_count_user')
            ->withTimestamps();
    }
}
