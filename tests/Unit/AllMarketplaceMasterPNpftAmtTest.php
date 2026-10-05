<?php

namespace Tests\Unit;

use App\Support\Badges\AllMarketplaceMasterBadgeAggregator;
use PHPUnit\Framework\TestCase;

class AllMarketplaceMasterPNpftAmtTest extends TestCase
{
    public function test_p_npft_amt_is_p_sales_times_gpft_minus_spend(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'L7 Sales' => 280,
                'Gprofit%' => 30,
                'N PFT' => 20,
                'Total Ad Spend' => 50,
            ],
        ]);

        // P-Sales = (280 / 7) * 30 = 1200; P NPFT $ = 1200 * 0.30 - 50 = 310
        $this->assertSame(310.0, $out['p_npft_amt']);
        $this->assertSame(25.83, $out['p_npft_pct']);
    }

    public function test_p_npft_amt_skips_channels_without_l7_sales(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'L7 Sales' => 280,
                'Gprofit%' => 30,
                'N PFT' => 20,
                'Total Ad Spend' => 50,
            ],
            [
                'Channel ' => 'Ebay',
                'L30 Sales' => 500,
                'L7 Sales' => 0,
                'Gprofit%' => 20,
                'N PFT' => 10,
                'Total Ad Spend' => 80,
            ],
        ]);

        $this->assertSame(310.0, $out['p_npft_amt']);
    }

    public function test_p_nroi_pct_is_projected_net_profit_over_projected_cogs(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'L7 Sales' => 280,
                'Gprofit%' => 50,
                'N PFT' => 40,
                'Total Ad Spend' => 100,
                'cogs' => 500,
            ],
            [
                'Channel ' => 'Ebay',
                'L30 Sales' => 400,
                'L7 Sales' => 0,
                'Gprofit%' => 20,
                'N PFT' => 10,
                'Total Ad Spend' => 80,
                'cogs' => 200,
            ],
        ]);

        // P-Sales = (280 / 7) * 30 = 1200. Ebay L7 is 0, so it is excluded.
        // P net = 1200 * 0.50 - 100 = 500. Projected COGS = 500 * (1200 / 1000) = 600.
        // P-NROI% = 500 / 600 * 100 = 83.33. Same COGS basis as P GROI%.
        $this->assertSame(83.33, $out['p_nroi_pct']);
    }

    public function test_y_nroi_pct_is_yesterday_net_profit_over_yesterday_cogs(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'Y Sales' => 200,
                'Gprofit%' => 40,
                'N PFT' => 25,
                'cogs' => 600,
            ],
            [
                'Channel ' => 'Ebay',
                'L30 Sales' => 500,
                'Y Sales' => 100,
                'Gprofit%' => 30,
                'N PFT' => 10,
                'cogs' => 350,
            ],
        ]);

        // Y COGS = 600*(200/1000) + 350*(100/500) = 190
        // Y net = 200*0.25 + 100*0.10 = 60
        // YNROI% = 60 / 190 * 100 = 31.58
        $this->assertSame(31.58, $out['y_nroi_pct']);
    }

    public function test_n_roi_is_cogs_weighted_average_of_channel_nroi(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'Gprofit%' => 40,
                'Total Ad Spend' => 100,
                'cogs' => 200,
                'N ROI' => 50,
            ],
            [
                'Channel ' => 'Temu',
                'L30 Sales' => 500,
                'Gprofit%' => 20,
                'Total Ad Spend' => 40,
                'cogs' => 100,
                'N ROI' => 20,
            ],
        ]);

        // (50 × 200 + 20 × 100) / 300 = 40. Not (PFT − spend) / COGS.
        $this->assertSame(40.0, $out['n_roi']);
    }
}
