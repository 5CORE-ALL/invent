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
}
