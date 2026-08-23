<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCreditPayment extends Model
{
    protected $fillable = ['folio', 'employee_credit_account_id', 'branch_id', 'payment_method_id', 'received_by_user_id', 'cash_box_number', 'amount', 'cash_received', 'change_due', 'paid_at'];
    protected $casts = ['amount' => 'decimal:2', 'cash_received' => 'decimal:2', 'change_due' => 'decimal:2', 'paid_at' => 'datetime'];
    public function account() { return $this->belongsTo(EmployeeCreditAccount::class, 'employee_credit_account_id'); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by_user_id'); }
    public function allocations() { return $this->hasMany(EmployeeCreditPaymentAllocation::class); }
}
