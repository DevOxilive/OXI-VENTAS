<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCreditCharge extends Model
{
    protected $fillable = ['employee_credit_account_id', 'sale_id', 'branch_id', 'amount', 'outstanding_amount', 'estimated_payment_date', 'status'];
    protected $casts = ['amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'estimated_payment_date' => 'date'];
    public function account() { return $this->belongsTo(EmployeeCreditAccount::class, 'employee_credit_account_id'); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function allocations() { return $this->hasMany(EmployeeCreditPaymentAllocation::class); }
}
