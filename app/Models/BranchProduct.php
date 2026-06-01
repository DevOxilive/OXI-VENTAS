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
        'price',
        'cost',
        'stock',
        'min_stock',
        'entry_date',
        'status',
        'last_restocked_at',
        'inactive_candidate_after_days',
        'name',
        'barcode',
        'category_id',
        'tracks_batches',
        'tracks_expiration',
    ];

    protected $casts = [
        'tracks_batches' => 'boolean',
        'tracks_expiration' => 'boolean',
        'last_restocked_at' => 'datetime',
        'inactive_candidate_after_days' => 'integer',
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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function activeBatches()
    {
        return $this->hasMany(ProductBatch::class)
            ->where('status', 'ACTIVE')
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
        if (!$this->last_restocked_at) {
            return false;
        }

        return $this->last_restocked_at
            ->copy()
            ->addDays($this->inactive_candidate_after_days)
            ->isPast();
    }
}