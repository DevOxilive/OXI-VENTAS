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
        'application_type',
        'account_balance_before',
        'account_balance_after',
        'credit_charge_reduction',
        'credit_balance_increase',
        'reason',
        'voided_by_user_id',
        'void_reason',
        'voided_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'account_balance_before' => 'decimal:2',
        'account_balance_after' => 'decimal:2',
        'credit_charge_reduction' => 'decimal:2',
        'credit_balance_increase' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
