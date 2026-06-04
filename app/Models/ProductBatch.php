<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_SEASONAL = 'SEASONAL';

    public const EXPIRATION_STATUS_NO_EXPIRATION = 'NO_EXPIRATION';
    public const EXPIRATION_STATUS_EXPIRED = 'EXPIRED';
    public const EXPIRATION_STATUS_NEAR_EXPIRATION = 'NEAR_EXPIRATION';
    public const EXPIRATION_STATUS_VALID = 'VALID';

    protected $fillable = [
        'branch_product_id',
        'lot_number',
        'expiration_date',
        'initial_quantity',
        'quantity',
        'supplier',
        'received_at',
        'status',
        'season_start_date',
        'season_end_date',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'received_at' => 'date',
        'initial_quantity' => 'decimal:2',
        'quantity' => 'decimal:2',
        'season_start_date' => 'date',
        'season_end_date' => 'date',
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
            return self::EXPIRATION_STATUS_NO_EXPIRATION;
        }

        $daysToExpire = $this->days_to_expire;

        if ($daysToExpire < 0) {
            return self::EXPIRATION_STATUS_EXPIRED;
        }

        if ($daysToExpire <= 30) {
            return self::EXPIRATION_STATUS_NEAR_EXPIRATION;
        }

        return self::EXPIRATION_STATUS_VALID;
    }

    public function getExpirationHumanTextAttribute(): string
    {
        if (!$this->expiration_date) {
            return 'Sin fecha de caducidad';
        }

        $daysToExpire = $this->days_to_expire;

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
        return $this->expiration_status === self::EXPIRATION_STATUS_EXPIRED;
    }

    public function getIsNearExpirationAttribute(): bool
    {
        return $this->expiration_status === self::EXPIRATION_STATUS_NEAR_EXPIRATION;
    }

    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date->translatedFormat('d \\d\\e F \\d\\e Y');
    }
}