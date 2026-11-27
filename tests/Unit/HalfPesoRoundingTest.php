<?php

namespace Tests\Unit;

use App\Support\HalfPesoRounding;
use PHPUnit\Framework\TestCase;

class HalfPesoRoundingTest extends TestCase
{
    public function test_it_rounds_to_the_nearest_half_peso_with_ties_up(): void
    {
        $this->assertSame(10.00, HalfPesoRounding::apply(10.24));
        $this->assertSame(10.50, HalfPesoRounding::apply(10.25));
        $this->assertSame(10.50, HalfPesoRounding::apply(10.50));
        $this->assertSame(10.50, HalfPesoRounding::apply(10.74));
        $this->assertSame(11.00, HalfPesoRounding::apply(10.75));
    }
}
