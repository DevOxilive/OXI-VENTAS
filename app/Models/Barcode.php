<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barcode extends Model
{
    use SoftDeletes;

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
