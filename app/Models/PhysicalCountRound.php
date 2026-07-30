<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCountRound extends Model
{
    protected $fillable = [
        'physical_count_id',
        'round_number',
        'type',
        'scope',
        'opened_by',
        'started_at',
        'closed_at',
        'applied_at',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function physicalCount()
    {
        return $this->belongsTo(PhysicalCount::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function entries()
    {
        return $this->hasMany(PhysicalCountEntry::class);
    }
}
