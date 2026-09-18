<?php

namespace Tests\Unit;

use App\Models\MiraklDailyData;
use PHPUnit\Framework\TestCase;

class MiraklDailyDataTimezoneTest extends TestCase
{
    public function test_macys_portal_evening_et_stays_on_pacific_17(): void
    {
        // Seller page: 9/17/2026 10:50 PM ET → 2026-09-18 02:50 UTC
        $this->assertSame('2026-09-17', MiraklDailyData::pacificYmd('2026-09-18 02:50:00'));
        $this->assertSame('2026-09-17 19:50:00', MiraklDailyData::pacificDateTime('2026-09-18 02:50:00'));

        // Seller page: 9/17/2026 10:13 PM ET → 2026-09-18 02:13 UTC
        $this->assertSame('2026-09-17', MiraklDailyData::pacificYmd('2026-09-18 02:13:00'));

        // Seller page: 9/17/2026 4:12 AM ET → 2026-09-17 08:12 UTC
        $this->assertSame('2026-09-17', MiraklDailyData::pacificYmd('2026-09-17 08:12:00'));
        $this->assertSame('2026-09-17 01:12:00', MiraklDailyData::pacificDateTime('2026-09-17 08:12:00'));
    }

    public function test_pacific_sept_17_utc_window_covers_evening_et_orders(): void
    {
        [$start, $end] = MiraklDailyData::utcBoundsForPacificDate('2026-09-17');

        $this->assertSame('2026-09-17 07:00:00', $start);
        $this->assertSame('2026-09-18 06:59:59', $end);
        $this->assertTrue($start <= '2026-09-17 08:12:00' && '2026-09-17 08:12:00' <= $end);
        $this->assertTrue($start <= '2026-09-18 02:13:00' && '2026-09-18 02:13:00' <= $end);
        $this->assertTrue($start <= '2026-09-18 02:50:00' && '2026-09-18 02:50:00' <= $end);
    }
}
