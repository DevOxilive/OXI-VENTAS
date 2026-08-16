<?php

namespace App\Support;

class QuantityFormatter
{
    public static function normalizeUnit(?string $unit): string
    {
        return in_array(strtolower((string) $unit), ['kg', 'kilo', 'kilogramo'], true) ? 'kg' : 'pza';
    }

    public static function format(float|int|string|null $quantity, ?string $unit = 'pza'): string
    {
        $value = (float) ($quantity ?? 0);

        if (self::normalizeUnit($unit) !== 'kg') {
            return (string) (int) round($value);
        }

        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    public static function withUnit(float|int|string|null $quantity, ?string $unit = 'pza'): string
    {
        $normalizedUnit = self::normalizeUnit($unit);

        return self::format($quantity, $normalizedUnit).' '.($normalizedUnit === 'kg' ? 'kg' : 'pzas');
    }

    public static function excelNumberFormat(?string $unit = 'pza'): string
    {
        return self::normalizeUnit($unit) === 'kg' ? '#,##0.###' : '#,##0';
    }
}
