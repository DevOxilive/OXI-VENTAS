<?php

namespace App\Exports;

final class PhysicalCountBarcodeList
{
    public static function fromRow(array $row, string $fallback = '-'): string
    {
        return collect([
            $row['scanned_code'] ?? null,
            ...($row['product_codes'] ?? []),
        ])
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->join(', ') ?: $fallback;
    }
}
