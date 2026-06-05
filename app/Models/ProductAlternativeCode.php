<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAlternativeCode extends Model
{
    protected $fillable = [
        'product_id',
        'code',
        'description',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}