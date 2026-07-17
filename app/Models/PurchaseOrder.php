<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    public const SOURCE_CENTRAL = 'CENTRAL';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_GENERATED = 'GENERATED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'branch_id',
        'purchase_cycle_id',
        'general_purchase_order_id',
        'user_id',
        'completed_by',
        'folio',
        'source',
        'status',
        'estimated_total',
        'actual_total',
        'purchased_at',
        'notes',
        'adjustment_notes',
        'generated_at',
        'completed_at',
    ];

    protected $casts = [
        'estimated_total' => 'decimal:2',
        'actual_total' => 'decimal:2',
        'purchased_at' => 'date',
        'generated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cycle()
    {
        return $this->belongsTo(PurchaseCycle::class, 'purchase_cycle_id');
    }

    public function generalOrder()
    {
        return $this->belongsTo(GeneralPurchaseOrder::class, 'general_purchase_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
