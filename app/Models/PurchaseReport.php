<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'user_id',
        'folio',
        'status',
        'notes',
        'generated_at',
        'cancelled_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseReportItem::class);
    }
    
}
