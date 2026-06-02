<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const TYPE_IN = 'IN';
    public const TYPE_OUT = 'OUT';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const REASON_PURCHASE = 'PURCHASE';
    public const REASON_SALE = 'SALE';
    public const REASON_DAMAGED = 'DAMAGED';
    public const REASON_EXPIRED = 'EXPIRED';
    public const REASON_INVENTORY_DIFFERENCE = 'INVENTORY_DIFFERENCE';

    protected $fillable = [
        'branch_product_id',
        'type',
        'reason',
        'quantity',
        'previous_stock',
        'new_stock',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'previous_stock' => 'decimal:2',
        'new_stock' => 'decimal:2',
    ];

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batches()
    {
        return $this->hasMany(StockMovementBatch::class);
    }

    public function isIn(): bool
    {
        return $this->type === self::TYPE_IN;
    }

    public function isOut(): bool
    {
        return $this->type === self::TYPE_OUT;
    }

    public function isAdjustment(): bool
    {
        return $this->type === self::TYPE_ADJUSTMENT;
    }
}