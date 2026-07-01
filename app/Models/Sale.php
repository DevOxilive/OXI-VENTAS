<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'folio',
        'date',
        'employee_id',
        'customer_id',
        'branch_id',
        'payment_method_id',
        'total',
        'cash_received',
        'change_due',
        'status',
    ];

    protected $casts = [
        'date' => 'datetime',
        'total' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_due' => 'decimal:2',
    ];

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
