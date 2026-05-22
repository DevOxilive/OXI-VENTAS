<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchProduct extends Model
{
  protected $fillable = [
    'branch_id',
    'product_id',
    'price',
    'cost',
    'stock',
    'min_stock',
    'entry_date',
    'active',
    'name',
'barcode',
'category_id',
];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
    public function category()
{
    return $this->belongsTo(Category::class);
}
}
