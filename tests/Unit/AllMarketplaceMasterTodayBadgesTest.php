<?php

namespace Tests\Unit;

use App\Support\Badges\AllMarketplaceMasterBadgeAggregator;
use PHPUnit\Framework\TestCase;

class AllMarketplaceMasterTodayBadgesTest extends TestCase
{
    public function test_today_block_metrics_are_computed_for_history(): void
    {
        $rows = [
            [
                'Channel ' => 'Amazon',
                'Today Sales' => 1000,
                'L30 Sales' => 10000,
                'L30 Orders' => 100,
                'Total Views' => 5000,
                'cogs' => 4000,
                'Gprofit%' => '30%',
                'N PFT' => '20%',
                'Ads%' => '10%',
            ],
            [
                'Channel ' => 'Ebay',
                'Today Sales' => 0,
                'L30 Sales' => 5000,
                'L30 Orders' => 50,
                'Total Views' => 1000,
                'cogs' => 2000,
                'Gprofit%' => '40%',
                'N PFT' => '30%',
                'Ads%' => '5%',
            ],
        ];

        $out = AllMarketplaceMasterBadgeAggregator::todayMetrics($rows);

        $this->assertSame(1000.0, $out['today_sales']);
        $this->assertSame(10, $out['t_orders']);
        $this->assertSame(500, $out['t_views']);
        $this->assertSame(20.0, $out['t_npft_pct']);
        $this->assertSame(10.0, $out['t_ads_pct']);
        // today COGS = 4000 * (1000 / 10000) = 400
        $this->assertSame(75.0, $out['t_groi_pct']);
        $this->assertSame(50.0, $out['t_nroi_pct']);

        $agg = AllMarketplaceMasterBadgeAggregator::aggregate($rows);
        foreach (AllMarketplaceMasterBadgeAggregator::TODAY_BADGE_FIELDS as $field) {
            $this->assertSame($out[$field], $agg[$field]);
        }
    }

    public function test_projected_block_orders_ads_views_are_computed_for_history(): void
    {
        $rows = [
            [
                'Channel ' => 'Amazon',
                'L7 Sales' => 700,       // P-Sales = 700 / 7 * 30 = 3000
                'L30 Sales' => 2000,
                'L30 Orders' => 20,
                'Total Views' => 1000,
                'Total Ad Spend' => 300,
            ],
            [
                'Channel ' => 'Doba',
                'L7 Sales' => 0,
                'L30 Sales' => 500,
                'L30 Orders' => 5,
                'Total Views' => 0,
                'Total Ad Spend' => 0,
            ],
        ];

        $out = AllMarketplaceMasterBadgeAggregator::projectedMetrics($rows);

        $this->assertSame(30, $out['p_orders']);     // 20 * 3000 / 2000
        $this->assertSame(1500, $out['p_views']);    // 1000 * 3000 / 2000
        $this->assertSame(10.0, $out['p_ads_pct']);  // 300 / 3000

        $agg = AllMarketplaceMasterBadgeAggregator::aggregate($rows);
        foreach (AllMarketplaceMasterBadgeAggregator::PROJECTED_BADGE_FIELDS as $field) {
            $this->assertSame($out[$field], $agg[$field]);
        }
    }

    public function test_today_block_metrics_are_zero_without_sales(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::todayMetrics([
            ['Today Sales' => 0, 'L30 Sales' => 100, 'cogs' => 50],
        ]);

        $this->assertSame(0, $out['t_orders']);
        $this->assertSame(0.0, $out['t_npft_pct']);
        $this->assertSame(0.0, $out['t_groi_pct']);
    }
}
