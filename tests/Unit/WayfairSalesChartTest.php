<?php

namespace Tests\Unit;

use App\Services\TemuShopifySalesService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WayfairSalesChartTest extends TestCase
{
    public function test_rolling_window_does_not_copy_the_same_total_onto_consecutive_days(): void
    {
        $byDay = [
            '2026-09-07' => ['sales' => 457.10],
            '2026-09-08' => ['sales' => 303.53],
            '2026-09-09' => ['sales' => 384.20],
            '2026-09-10' => ['sales' => 200.81],
            '2026-09-11' => ['sales' => 112.23],
            '2026-09-12' => ['sales' => 294.93],
            '2026-09-13' => ['sales' => 90.92],
            '2026-09-14' => ['sales' => 183.64],
        ];

        $out = TemuShopifySalesService::rollingSalesSeries(
            $byDay,
            Carbon::parse('2026-09-13', 'America/Los_Angeles'),
            Carbon::parse('2026-09-14', 'America/Los_Angeles'),
            7
        );

        $this->assertSame('Sep 13', $out[0]['date']);
        $this->assertSame('Sep 14', $out[1]['date']);
        $this->assertEqualsWithDelta(1843.72, $out[0]['value'], 0.01);
        $this->assertEqualsWithDelta(1570.26, $out[1]['value'], 0.01);
        $this->assertNotEquals($out[0]['value'], $out[1]['value']);
    }
}
