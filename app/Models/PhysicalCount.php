<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class PhysicalCount extends Model
{
 use SoftDeletes;

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
    'finalized_at',
    'finalized_by',
    'last_applied_at',
];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'recapture_started_at' => 'datetime',
        'finalized_at' => 'datetime',
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

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function entries()
    {
        return $this->hasMany(PhysicalCountEntry::class);
    }

    public function rounds()
    {
        return $this->hasMany(PhysicalCountRound::class)->orderBy('round_number');
    }

    public function currentRound()
    {
        return $this->hasOne(PhysicalCountRound::class)->latestOfMany('round_number');
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
