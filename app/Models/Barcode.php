<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    protected $table = 'barcodes';

    protected $fillable = [
        'product_id',
        'code',
        'type',
        'base_quantity',
        'active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}