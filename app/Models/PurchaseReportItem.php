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
        'purchase_presentation',
        'package_quantity',
        'units_per_package',
        'estimated_price',
        'estimated_total',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'requested_quantity' => 'decimal:3',
        'package_quantity' => 'decimal:3',
        'units_per_package' => 'decimal:3',
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
