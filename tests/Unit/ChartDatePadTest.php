<?php

namespace Tests\Unit;

use App\Support\Marketplace\ChartDatePad;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ChartDatePadTest extends TestCase
{
    public function test_zero_sales_days_after_last_point_still_get_dates(): void
    {
        $now = Carbon::parse('2026-09-05 16:00:00', 'America/Los_Angeles');
        $out = ChartDatePad::fillGapsThroughYesterday([
            ['date' => 'Sep 01', 'value' => 18.0],
            ['date' => 'Sep 02', 'value' => 0.0],
        ], 30, 'America/Los_Angeles', $now);

        $labels = array_column($out, 'date');
        $this->assertContains('Sep 01', $labels);
        $this->assertContains('Sep 02', $labels);
        $this->assertContains('Sep 03', $labels);
        $this->assertContains('Sep 04', $labels);
        $this->assertSame(0.0, $out[array_search('Sep 03', $labels, true)]['value']);
        $this->assertSame(0.0, $out[array_search('Sep 04', $labels, true)]['value']);
        $this->assertSame('Sep 04', $out[array_key_last($out)]['date']);
    }

    public function test_does_not_invent_zero_days_before_first_real_point(): void
    {
        $now = Carbon::parse('2026-09-05 16:00:00', 'America/Los_Angeles');
        $out = ChartDatePad::fillGapsThroughYesterday([
            ['date' => 'Sep 01', 'value' => 18.0],
        ], 30, 'America/Los_Angeles', $now);

        $this->assertSame('Sep 01', $out[0]['date']);
        $this->assertSame(18.0, $out[0]['value']);
    }

    public function test_empty_series_still_shows_the_window_as_zero(): void
    {
        $now = Carbon::parse('2026-09-05 16:00:00', 'America/Los_Angeles');
        $out = ChartDatePad::fillGapsThroughYesterday([], 7, 'America/Los_Angeles', $now);

        $this->assertCount(7, $out);
        $this->assertSame('Aug 29', $out[0]['date']);
        $this->assertSame('Sep 04', $out[array_key_last($out)]['date']);
        foreach ($out as $pt) {
            $this->assertSame(0.0, $pt['value']);
        }
    }
}
