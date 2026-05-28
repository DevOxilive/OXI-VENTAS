<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    protected $fillable = [
        'branch_product_id',
        'lot_number',
        'expiration_date',
        'initial_quantity',
        'quantity',
        'supplier',
        'received_at',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'received_at' => 'date',
    ];

    protected $appends = [
        'expiration_status',
        'days_to_expire',
        'is_expired',
        'is_near_expiration',
        'formatted_expiration_date',
        'formatted_received_at',
        'expiration_human_text',
    ];

    public function branchProduct()
    {
        return $this->belongsTo(BranchProduct::class);
    }

    public function movementBatches()
    {
        return $this->hasMany(StockMovementBatch::class);
    }

    public function getExpirationStatusAttribute(): string
    {
        if (!$this->expiration_date) {
            return 'NO_EXPIRATION';
        }

        $daysToExpire = now()
            ->startOfDay()
            ->diffInDays($this->expiration_date->copy()->startOfDay(), false);

        if ($daysToExpire < 0) {
            return 'EXPIRED';
        }

        if ($daysToExpire <= 30) {
            return 'NEAR_EXPIRATION';
        }

        return 'VALID';
    }

    public function getExpirationHumanTextAttribute(): string
    {
        if (!$this->expiration_date) {
            return 'Sin fecha de caducidad';
        }

        $daysToExpire = now()
            ->startOfDay()
            ->diffInDays($this->expiration_date->copy()->startOfDay(), false);

        if ($daysToExpire < 0) {
            return 'Caducó hace ' . abs($daysToExpire) . ' día(s)';
        }

        if ($daysToExpire === 0) {
            return 'Caduca hoy';
        }

        if ($daysToExpire === 1) {
            return 'Caduca mañana';
        }

        $months = intdiv($daysToExpire, 30);
        $days = $daysToExpire % 30;

        if ($months <= 0) {
            return "Caduca en {$days} día(s)";
        }

        if ($days === 0) {
            return "Caduca en {$months} mes(es)";
        }

        return "Caduca en {$months} mes(es) y {$days} día(s)";
    }

    public function getDaysToExpireAttribute(): ?int
    {
        if (!$this->expiration_date) {
            return null;
        }

        return now()
            ->startOfDay()
            ->diffInDays(
                $this->expiration_date->copy()->startOfDay(),
                false
            );
    }

    public function getFormattedExpirationDateAttribute(): ?string
    {
        return $this->formatDate($this->expiration_date);
    }

    public function getFormattedReceivedAtAttribute(): ?string
    {
        return $this->formatDate($this->received_at);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiration_status === 'EXPIRED';
    }

    public function getIsNearExpirationAttribute(): bool
    {
        return $this->expiration_status === 'NEAR_EXPIRATION';
    }

    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date->translatedFormat('d \\d\\e F \\d\\e Y');
    }
}
