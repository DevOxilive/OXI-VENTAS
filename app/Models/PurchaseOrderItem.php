<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    public const STATUS_REQUESTED = 'REQUESTED';
    public const STATUS_PURCHASED = 'PURCHASED';
    public const STATUS_ADJUSTED = 'ADJUSTED';
    public const STATUS_UNAVAILABLE = 'UNAVAILABLE';

    protected $fillable = [
        'purchase_order_id',
        'branch_product_id',
        'product_id',
        'current_stock',
        'min_stock',
        'requested_quantity',
        'purchased_quantity',
        'received_quantity',
        'estimated_price',
        'estimated_total',
        'actual_price',
        'discount_amount',
        'actual_total',
        'status',
        'receipt_notes',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'requested_quantity' => 'decimal:2',
        'purchased_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'estimated_price' => 'decimal:2',
        'estimated_total' => 'decimal:2',
        'actual_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'actual_total' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
