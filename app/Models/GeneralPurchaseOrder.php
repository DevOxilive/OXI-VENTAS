<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralPurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PURCHASING = 'PURCHASING';

    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'purchase_cycle_id',
        'created_by',
        'completed_by',
        'folio',
        'status',
        'estimated_total',
        'gross_total',
        'discount_total',
        'actual_total',
        'purchased_at',
        'completed_at',
    ];

    protected $casts = [
        'estimated_total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'actual_total' => 'decimal:2',
        'purchased_at' => 'date',
        'completed_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(PurchaseCycle::class, 'purchase_cycle_id');
    }

    public function items()
    {
        return $this->hasMany(GeneralPurchaseOrderItem::class);
    }

    public function branchOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
