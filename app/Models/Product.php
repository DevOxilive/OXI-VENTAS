<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image',
        'cost',
        'sale_price',
        'margin_percentage',
        'unit',
        'category_id',
        'subcategory_id',
        'active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function branchInventories()
    {
        return $this->hasMany(BranchInventory::class);
    }

    public function inventories()
    {
        return $this->hasMany(BranchInventory::class);
    }

    public function barcodes()
    {
        return $this->hasMany(Barcode::class);
    }
}
