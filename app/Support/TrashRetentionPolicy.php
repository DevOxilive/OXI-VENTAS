<?php

namespace App\Support;

use Carbon\CarbonInterface;
/** Single source of truth for how long recoverable records remain in the global trash. */
final class TrashRetentionPolicy
{
    private const RETENTION_DAYS = [
        'users' => 180,
        'employees' => 180,
        'branches' => 180,
        'customers' => 180,
        'products' => 180,
        'categories' => 180,
        'purchase-reports' => 180,
        'ticket-templates' => 180,
    ];

    public static function days(string $resource): ?int
    {
        return self::RETENTION_DAYS[$resource] ?? null;
    }

    public static function isPurgeable(string $resource): bool
    {
        return self::days($resource) !== null;
    }

    public static function purgeableResources(): array
    {
        return array_keys(array_filter(self::RETENTION_DAYS, fn (?int $days) => $days !== null));
    }

    public static function expiresAt(string $resource, CarbonInterface $deletedAt): ?CarbonInterface
    {
        $days = self::days($resource);

        return $days === null ? null : $deletedAt->copy()->addDays($days);
    }
}
