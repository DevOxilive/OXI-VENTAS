<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCreditPaymentAllocation extends Model
{
    protected $fillable = ['employee_credit_payment_id', 'employee_credit_charge_id', 'amount'];
    protected $casts = ['amount' => 'decimal:2'];
    public function payment() { return $this->belongsTo(EmployeeCreditPayment::class, 'employee_credit_payment_id'); }
    public function charge() { return $this->belongsTo(EmployeeCreditCharge::class, 'employee_credit_charge_id'); }
}
