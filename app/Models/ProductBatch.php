<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    protected $fillable = [
        'branch_product_id',
        'lot_number',
        'expiration_date',
        'initial_quantity',
        'quantity',
        'supplier',
        'received_at',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'received_at' => 'date',
    ];

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function movementBatches()
    {
        return $this->hasMany(StockMovementBatch::class);
    }
}
