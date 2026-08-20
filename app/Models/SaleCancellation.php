<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleCancellation extends Model
{
    protected $fillable = [
        'sale_id',
        'branch_id',
        'payment_method_id',
        'cancelled_by_user_id',
        'cash_box_number',
        'amount',
        'reason',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function details()
    {
        return $this->hasMany(SaleCancellationDetail::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
