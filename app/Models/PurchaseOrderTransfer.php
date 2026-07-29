<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTransfer extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'from_user_id',
        'to_user_id',
        'transferred_by',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
