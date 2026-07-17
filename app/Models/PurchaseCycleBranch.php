<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseCycleBranch extends Model
{
    protected $fillable = [
        'purchase_cycle_id',
        'branch_id',
        'purchase_order_id',
        'submitted_without_items',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_without_items' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(PurchaseCycle::class, 'purchase_cycle_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
