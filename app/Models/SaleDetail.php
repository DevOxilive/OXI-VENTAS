<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'barcode_id',
        'lot_id',
        'quantity',
        'sale_unit',
        'base_quantity',
        'pieces_per_box',
        'original_unit_price',
        'discount_percentage',
        'discount_amount',
        'unit_price',
        'unit_cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'base_quantity' => 'decimal:3',
        'pieces_per_box' => 'integer',
        'original_unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'subtotal' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function cancellationDetail()
    {
        return $this->hasOne(SaleCancellationDetail::class);
    }
}
