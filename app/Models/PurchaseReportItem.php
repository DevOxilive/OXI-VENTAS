<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReportItem extends Model
{
    protected $fillable = [
        'purchase_report_id',
        'branch_product_id',
        'current_stock',
        'min_stock',
        'requested_quantity',
        'estimated_price',
        'estimated_total',
        'notes',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'requested_quantity' => 'decimal:2',
        'estimated_price' => 'decimal:2',
        'estimated_total' => 'decimal:2',
    ];

    public function report()
    {
        return $this->belongsTo(PurchaseReport::class, 'purchase_report_id');
    }

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }
}
