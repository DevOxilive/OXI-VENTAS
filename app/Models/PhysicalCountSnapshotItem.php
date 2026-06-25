<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCountSnapshotItem extends Model
{
    protected $fillable = [
        'physical_count_snapshot_id',
        'branch_product_id',
        'product_id',
        'category_id',
        'subcategory_id',
        'product_batch_id',
        'barcode',
        'product_name',
        'category_name',
        'subcategory_name',
        'lot_number',
        'expiration_date',
        'branch_product_status',
        'batch_status',
        'system_stock',
        'batch_stock',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'system_stock' => 'decimal:2',
        'batch_stock' => 'decimal:2',
    ];

    public function snapshot()
    {
        return $this->belongsTo(PhysicalCountSnapshot::class, 'physical_count_snapshot_id');
    }

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function productBatch()
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
