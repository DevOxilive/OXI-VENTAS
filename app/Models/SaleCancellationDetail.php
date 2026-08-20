<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleCancellationDetail extends Model
{
    protected $fillable = [
        'sale_cancellation_id',
        'sale_detail_id',
        'branch_product_id',
        'product_id',
        'barcode_id',
        'return_stock_movement_id',
        'quantity',
        'sale_unit',
        'base_quantity',
        'pieces_per_box',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'base_quantity' => 'decimal:3',
        'pieces_per_box' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function cancellation()
    {
        return $this->belongsTo(SaleCancellation::class, 'sale_cancellation_id');
    }

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function barcode()
    {
        return $this->belongsTo(Barcode::class);
    }

    public function returnStockMovement()
    {
        return $this->belongsTo(StockMovement::class, 'return_stock_movement_id');
    }
}
