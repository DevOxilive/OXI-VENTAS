<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralPurchaseOrderItemBatch extends Model
{
    protected $fillable = [
        'general_purchase_order_item_id',
        'lot_number',
        'allocations',
        'supplier',
        'expiration_date',
        'purchase_presentation',
        'presentation_quantity',
        'base_quantity',
    ];

    protected $casts = [
        'allocations' => 'array',
        'expiration_date' => 'date',
        'presentation_quantity' => 'decimal:3',
        'base_quantity' => 'decimal:3',
    ];

    public function item()
    {
        return $this->belongsTo(GeneralPurchaseOrderItem::class, 'general_purchase_order_item_id');
    }
}
