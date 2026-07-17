<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralPurchaseOrderItem extends Model
{
    protected $fillable = [
        'general_purchase_order_id',
        'product_id',
        'product_name',
        'product_code',
        'base_unit',
        'requested_quantity',
        'estimated_unit_price',
        'estimated_total',
        'purchase_presentation',
        'package_quantity',
        'units_per_package',
        'package_price',
        'purchased_quantity',
        'gross_total',
        'discount_amount',
        'actual_total',
        'net_unit_cost',
        'unavailable',
        'promotion_notes',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:2',
        'estimated_unit_price' => 'decimal:2',
        'estimated_total' => 'decimal:2',
        'package_quantity' => 'decimal:2',
        'units_per_package' => 'decimal:2',
        'package_price' => 'decimal:2',
        'purchased_quantity' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'actual_total' => 'decimal:2',
        'net_unit_cost' => 'decimal:4',
        'unavailable' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(GeneralPurchaseOrder::class, 'general_purchase_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
