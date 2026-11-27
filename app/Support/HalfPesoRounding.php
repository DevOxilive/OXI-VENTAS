<?php

namespace App\Support;

class HalfPesoRounding
{
    /**
     * Lleva un importe a la moneda de 50 centavos más cercana.
     * Los empates de 25 y 75 centavos se resuelven hacia arriba.
     */
    public static function apply(float $amount): float
    {
        $centavos = (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);

        return intdiv($centavos + 25, 50) * 50 / 100;
    }
}
