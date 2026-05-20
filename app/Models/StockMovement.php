<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'branch_product_id',
        'type',
        'reason',
        'quantity',
        'previous_stock',
        'new_stock',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
    ];

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
