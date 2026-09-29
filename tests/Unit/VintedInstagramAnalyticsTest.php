<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\InstagramAnalyticsController;
use App\Http\Controllers\MarketPlace\VintedAnalyticsController;
use App\Models\ProductMaster;
use Tests\TestCase;

class VintedInstagramAnalyticsTest extends TestCase
{
    public function test_vinted_unit_profit_subtracts_ship(): void
    {
        $this->assertEqualsWithDelta(20.80, VintedAnalyticsController::unitProfit(40, 10, 0.87, 4), 0.001);
        $this->assertSame(52, VintedAnalyticsController::gpftPercent(40, 10, 0.87, 4));
        $this->assertSame(208, VintedAnalyticsController::groiPercent(40, 10, 0.87, 4));
        $this->assertSame(0.0, VintedAnalyticsController::unitProfit(0, 10, 0.87, 4));
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

    public function test_vinted_op_metrics_subtract_ship_and_stay_separate_from_sprice(): void
    {
        $metrics = VintedAnalyticsController::opProfitMetrics(40.0, 10.0, 0.87, 5.0, 4.0);

        $this->assertSame(52.0, $metrics['sgpft']);
        $this->assertSame(208.0, $metrics['sgroi']);
        $this->assertSame(47.0, $metrics['spft']);
        $this->assertSame(188.0, $metrics['snroi']);
        $this->assertSame(19.5, VintedAnalyticsController::normalizeOpSprice('19.499'));
        $this->assertNull(VintedAnalyticsController::normalizeOpSprice(0));
        $this->assertNull(VintedAnalyticsController::normalizeOpSprice(''));
    }

    public function test_extract_lp_reads_product_master_values(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 12.5, 'ship' => 9.99];

        $this->assertSame(12.5, VintedAnalyticsController::extractLp($pm));
        $this->assertSame(9.99, VintedAnalyticsController::extractShip($pm));
        $this->assertSame(12.5, InstagramAnalyticsController::extractLp($pm));
        $this->assertSame(0.0, VintedAnalyticsController::extractLp(null));
        $this->assertSame(0.0, VintedAnalyticsController::extractShip(null));
    }
}
