<?php

namespace Tests\Unit;

use App\Support\AmazonAdsYesterdayBadgeHistory;
use PHPUnit\Framework\TestCase;

class AmazonAdsYesterdayBadgeHistoryTest extends TestCase
{
    public function test_series_fills_gaps_and_acos_uses_spend_over_sales(): void
    {
        $series = AmazonAdsYesterdayBadgeHistory::series([
            '2026-10-12' => ['spend' => 100, 'sales' => 200],
            '2026-10-14' => ['spend' => 50, 'sales' => 0],
        ], '2026-10-12', '2026-10-14');

        $this->assertCount(3, $series);
        $this->assertSame(50.0, $series[0]['acos']);
        $this->assertSame(0.0, $series[1]['spend']);
        $this->assertSame(0.0, $series[1]['acos']);
        $this->assertSame(100.0, $series[2]['acos']);
    }

    public function test_tones_are_green_red_and_gray(): void
    {
        $series = AmazonAdsYesterdayBadgeHistory::series([
            '2026-10-13' => ['spend' => 700, 'sales' => 2000],
            '2026-10-14' => ['spend' => 753, 'sales' => 1800],
        ], '2026-10-13', '2026-10-14');

        $tones = AmazonAdsYesterdayBadgeHistory::tones($series);

        $this->assertSame('green', $tones['yspend']);
        $this->assertSame('red', $tones['ysales']);
        $this->assertSame('red', $tones['yacos']);
    }

    public function test_unchanged_and_missing_prior_day_are_gray(): void
    {
        $flat = AmazonAdsYesterdayBadgeHistory::series([
            '2026-10-13' => ['spend' => 10, 'sales' => 20],
            '2026-10-14' => ['spend' => 10, 'sales' => 20],
        ], '2026-10-13', '2026-10-14');
        $this->assertSame([
            'yspend' => 'gray',
            'ysales' => 'gray',
            'yacos' => 'gray',
        ], AmazonAdsYesterdayBadgeHistory::tones($flat));

        $one = AmazonAdsYesterdayBadgeHistory::series([
            '2026-10-14' => ['spend' => 10, 'sales' => 40],
        ], '2026-10-14', '2026-10-14');
        $this->assertSame('gray', AmazonAdsYesterdayBadgeHistory::tones($one)['yspend']);
    }

    public function test_acos_down_is_green(): void
    {
        $this->assertSame('green', AmazonAdsYesterdayBadgeHistory::tone('yacos', 30.0, 35.0));
        $this->assertSame('red', AmazonAdsYesterdayBadgeHistory::tone('yacos', 40.0, 35.0));
    }

    public function test_chart_points_use_the_history_key(): void
    {
        $series = AmazonAdsYesterdayBadgeHistory::series([
            '2026-10-14' => ['spend' => 12.5, 'sales' => 40],
        ], '2026-10-14', '2026-10-14');

        $this->assertSame([
            ['date' => '2026-10-14', 'yspend' => 12.5],
        ], AmazonAdsYesterdayBadgeHistory::chartPoints($series, 'yspend'));
        $this->assertSame(31.0, AmazonAdsYesterdayBadgeHistory::chartPoints($series, 'yacos')[0]['yacos']);
    }
}
