<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

/**
 * Convierte instantes almacenados en UTC a la hora operativa mostrada al usuario.
 *
 * La aplicación conserva UTC como zona interna para no alterar registros ya
 * guardados. Esta clase solamente afecta etiquetas, comprobantes y reportes.
 */
final class LocalDateTime
{
    public const TIMEZONE = 'America/Mexico_City';

    public static function format(?CarbonInterface $date, string $format): ?string
    {
        return $date?->copy()->setTimezone(self::TIMEZONE)->format($format);
    }

    public static function iso(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(self::TIMEZONE)->toIso8601String();
    }

    /**
     * Devuelve el inicio del día elegido por el usuario, expresado en UTC para
     * consultar columnas que se almacenan en UTC.
     */
    public static function startOfDay(?string $date = null): CarbonImmutable
    {
        return CarbonImmutable::parse($date ?? 'now', self::TIMEZONE)->startOfDay()->utc();
    }

    /**
     * Devuelve el fin del día local, expresado en UTC y sin perder operaciones
     * realizadas después de las 18:00 UTC.
     */
    public static function endOfDay(?string $date = null): CarbonImmutable
    {
        return CarbonImmutable::parse($date ?? 'now', self::TIMEZONE)->endOfDay()->utc();
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->toDateString();
    }

    public static function now(string $format): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->format($format);
    }
}
