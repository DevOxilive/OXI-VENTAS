<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class BranchProduct extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SEASONAL = 'seasonal';

    protected $fillable = [
        'branch_id',
        'product_id',
        'barcode',
        'stock',
        'min_stock',
        'status',
        'last_restocked_at',
        'inactive_candidate_after_days',
        'tracks_batches',
        'unit',
        'tracks_expiration',
        'season_start_date',
        'season_end_date',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'tracks_batches' => 'boolean',
        'tracks_expiration' => 'boolean',
        'last_restocked_at' => 'datetime',
        'inactive_candidate_after_days' => 'integer',
        'season_start_date' => 'date',
        'season_end_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function activeBatches()
    {
        return $this->hasMany(ProductBatch::class)
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->where('quantity', '>', 0);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isSeasonal(): bool
    {
        return $this->status === self::STATUS_SEASONAL;
    }

    public function isInactiveCandidate(): bool
    {
        if (!$this->last_restocked_at || !$this->inactive_candidate_after_days) {
            return false;
        }

        return $this->last_restocked_at
            ->copy()
            ->addDays($this->inactive_candidate_after_days)
            ->isPast();
    }
}