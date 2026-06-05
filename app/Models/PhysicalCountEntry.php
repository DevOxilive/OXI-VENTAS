<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCountEntry extends Model
{
   protected $fillable = [
    'physical_count_id',
    'branch_product_id',
    'product_batch_id',
    'product_id',
    'user_id',
    'scanned_code',
    'counted_quantity',
    'damaged_quantity',
    'expired_quantity',
    'expiration_date',
    'notes',
];

    public function physicalCount()
    {
        return $this->belongsTo(PhysicalCount::class);
    }
public function productBatch()
{
    return $this->belongsTo(ProductBatch::class);
}
    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}