<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalCountEntry extends Model
{
   use SoftDeletes;

   protected $fillable = [
    'physical_count_id',
    'physical_count_round_id',
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

    protected $casts = [
        'counted_quantity' => 'decimal:3',
        'damaged_quantity' => 'decimal:3',
        'expired_quantity' => 'decimal:3',
        'expiration_date' => 'date',
    ];

    public function physicalCount()
    {
        return $this->belongsTo(PhysicalCount::class);
    }

    public function round()
    {
        return $this->belongsTo(PhysicalCountRound::class, 'physical_count_round_id');
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
