<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegisterClosure extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'folio',
        'branch_id',
        'user_id',
        'cash_box_number',
        'period_start',
        'period_end',
        'sales_count',
        'sales_total',
        'expected_cash',
        'card_total',
        'other_total',
        'recharge_total',
        'expected_drawer_cash',
        'counted_cash',
        'cash_left',
        'counted_card',
        'cash_difference',
        'card_difference',
        'payment_breakdown',
        'denomination_breakdown',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'sales_total' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'card_total' => 'decimal:2',
        'other_total' => 'decimal:2',
        'recharge_total' => 'decimal:2',
        'expected_drawer_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'cash_left' => 'decimal:2',
        'counted_card' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'card_difference' => 'decimal:2',
        'payment_breakdown' => 'array',
        'denomination_breakdown' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
