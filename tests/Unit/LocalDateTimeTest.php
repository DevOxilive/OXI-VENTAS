<?php

namespace Tests\Unit;

use App\Support\LocalDateTime;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LocalDateTimeTest extends TestCase
{
    public function test_it_converts_a_utc_instant_for_operational_display(): void
    {
        $utcSale = Carbon::parse('2026-10-06 14:02:00', 'UTC');

        $this->assertSame('06/10/2026 08:02', LocalDateTime::format($utcSale, 'd/m/Y H:i'));
        $this->assertSame('2026-10-06T08:02:00-06:00', LocalDateTime::iso($utcSale));
        $this->assertSame('UTC', $utcSale->getTimezone()->getName());
    }

    public function test_it_builds_utc_query_bounds_from_a_local_calendar_day(): void
    {
        $this->assertSame('2026-10-06 06:00:00', LocalDateTime::startOfDay('2026-10-06')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 05:59:59', LocalDateTime::endOfDay('2026-10-06')->format('Y-m-d H:i:s'));
    }
}
