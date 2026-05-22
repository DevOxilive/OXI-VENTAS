<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementBatch extends Model
{
    protected $fillable = [
        'stock_movement_id',
        'product_batch_id',
        'quantity',
        'previous_batch_quantity',
        'new_batch_quantity',
        'allocation_method',
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
