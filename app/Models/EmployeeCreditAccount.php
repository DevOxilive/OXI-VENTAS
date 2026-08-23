<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCreditAccount extends Model
{
    protected $fillable = ['employee_id', 'credit_limit', 'credit_balance', 'estimated_payment_date', 'active'];
    protected $casts = ['credit_limit' => 'decimal:2', 'credit_balance' => 'decimal:2', 'estimated_payment_date' => 'date', 'active' => 'boolean'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function charges() { return $this->hasMany(EmployeeCreditCharge::class); }
    public function payments() { return $this->hasMany(EmployeeCreditPayment::class); }
}
