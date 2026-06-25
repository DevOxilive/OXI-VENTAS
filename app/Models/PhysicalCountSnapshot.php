<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCountSnapshot extends Model
{
    protected $fillable = [
        'physical_count_id',
        'branch_id',
        'created_by',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function physicalCount()
    {
        return $this->belongsTo(PhysicalCount::class);
    }

    public function items()
    {
        return $this->hasMany(PhysicalCountSnapshotItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
