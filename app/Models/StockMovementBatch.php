<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementBatch extends Model
{
    public const ALLOCATION_MANUAL = 'MANUAL';
    public const ALLOCATION_FEFO_AUTO = 'FEFO_AUTO';

    protected $fillable = [
        'stock_movement_id',
        'product_batch_id',
        'quantity',
        'previous_batch_quantity',
        'new_batch_quantity',
        'allocation_method',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'previous_batch_quantity' => 'decimal:2',
        'new_batch_quantity' => 'decimal:2',
    ];

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function productBatch()
    {
        return $this->belongsTo(ProductBatch::class);
    }
}