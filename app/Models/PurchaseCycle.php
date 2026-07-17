<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseCycle extends Model
{
    public const STATUS_OPEN = 'OPEN';

    public const STATUS_CONSOLIDATED = 'CONSOLIDATED';

    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'folio',
        'status',
        'created_by',
        'opened_at',
        'consolidated_at',
        'completed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'consolidated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branches()
    {
        return $this->hasMany(PurchaseCycleBranch::class);
    }

    public function orders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function generalOrder()
    {
        return $this->hasOne(GeneralPurchaseOrder::class);
    }
}
