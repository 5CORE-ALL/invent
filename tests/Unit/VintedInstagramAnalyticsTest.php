<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\InstagramAnalyticsController;
use App\Http\Controllers\MarketPlace\VintedAnalyticsController;
use App\Models\ProductMaster;
use Tests\TestCase;

class VintedInstagramAnalyticsTest extends TestCase
{
    public function test_vinted_unit_profit_ignores_ship_like_depop(): void
    {
        $this->assertEqualsWithDelta(24.80, VintedAnalyticsController::unitProfit(40, 10, 0.87), 0.001);
        $this->assertSame(62, VintedAnalyticsController::gpftPercent(40, 10, 0.87));
        $this->assertSame(248, VintedAnalyticsController::groiPercent(40, 10, 0.87));
        $this->assertSame(0.0, VintedAnalyticsController::unitProfit(0, 10, 0.87));
    }

    public function test_instagram_unit_profit_uses_full_take_home_when_margin_is_one(): void
    {
        $this->assertEqualsWithDelta(30.0, InstagramAnalyticsController::unitProfit(40, 10, 1.0), 0.001);
        $this->assertSame(75, InstagramAnalyticsController::gpftPercent(40, 10, 1.0));
        $this->assertSame(300, InstagramAnalyticsController::groiPercent(40, 10, 1.0));
    }

    public function test_missing_list_price_falls_back_to_sheet_average(): void
    {
        $this->assertSame(20.0, VintedAnalyticsController::effectiveSellPrice(0, 40, 2));
        $this->assertSame(25.0, InstagramAnalyticsController::effectiveSellPrice(25, 40, 2));
    }

    public function test_extract_lp_reads_product_master_values(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 12.5, 'ship' => 9.99];

        $this->assertSame(12.5, VintedAnalyticsController::extractLp($pm));
        $this->assertSame(12.5, InstagramAnalyticsController::extractLp($pm));
        $this->assertSame(0.0, VintedAnalyticsController::extractLp(null));
    }
}
