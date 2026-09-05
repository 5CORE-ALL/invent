<?php

namespace Tests\Unit;

use App\Services\TemuShopifySalesService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class Temu3RollingSalesChartTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rolling_series_keeps_days_after_last_order(): void
    {
        $byDay = [
            '2026-09-02' => ['sales' => 106.0],
        ];
        $start = Carbon::parse('2026-09-02', 'America/Los_Angeles');
        $end = Carbon::parse('2026-09-05', 'America/Los_Angeles');

        $series = TemuShopifySalesService::rollingSalesSeries($byDay, $start, $end, 30);

        $this->assertSame(['Sep 02', 'Sep 03', 'Sep 04', 'Sep 05'], array_column($series, 'date'));
        $this->assertSame([106.0, 106.0, 106.0, 106.0], array_column($series, 'value'));
    }

    public function test_temu3_sheet_l30_end_is_pacific_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 15:27', 'America/Los_Angeles'));
        [$start, $end] = TemuShopifySalesService::temu3SheetL30Window();

        $this->assertSame('2026-08-07', $start->toDateString());
        $this->assertSame('2026-09-05', $end->toDateString());
    }
}
