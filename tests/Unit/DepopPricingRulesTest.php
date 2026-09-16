<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\DepopController;
use App\Models\ProductMaster;
use Tests\TestCase;

class DepopPricingRulesTest extends TestCase
{
    public function test_unit_profit_uses_margin_and_lp_without_ship(): void
    {
        // $40 × 0.87 − $10 = $24.80. Ship is ignored even if present on PM.
        $this->assertEqualsWithDelta(24.80, DepopController::unitProfit(40, 10, 0.87), 0.001);
        $this->assertEqualsWithDelta(14.80, DepopController::unitProfit(40, 20, 0.87), 0.001);
    }

    public function test_gpft_and_groi_match_aliexpress_rounding_without_ship(): void
    {
        // profit 24.80 / 40 = 62% GPFT; 24.80 / 10 = 248% GROI
        $this->assertSame(62, DepopController::gpftPercent(40, 10, 0.87));
        $this->assertSame(248, DepopController::groiPercent(40, 10, 0.87));
        $this->assertSame(0, DepopController::gpftPercent(0, 10, 0.87));
        $this->assertSame(0, DepopController::groiPercent(40, 0, 0.87));
    }

    public function test_target_sprice_from_roi_does_not_add_ship(): void
    {
        // (10 × 1.50) / 0.87 = 17.241… → 17.24
        $this->assertSame(17.24, DepopController::targetSpriceFromRoi(10, 50, 0.87));
        $this->assertSame(0.0, DepopController::targetSpriceFromRoi(0, 50, 0.87));
    }

    public function test_target_sprice_from_gpft_does_not_add_ship(): void
    {
        // 10 / (0.87 − 0.30) = 17.543… → 17.54
        $this->assertSame(17.54, DepopController::targetSpriceFromGpft(10, 30, 0.87));
        $this->assertSame(0.0, DepopController::targetSpriceFromGpft(10, 90, 0.87));
    }

    public function test_missing_list_price_is_not_negative_lp(): void
    {
        $this->assertSame(0.0, DepopController::unitProfit(0, 10, 0.87));
        $this->assertSame(0, DepopController::groiPercent(0, 10, 0.87));
        $this->assertSame(20.0, DepopController::effectiveSellPrice(0, 40, 2));
        $this->assertSame(25.0, DepopController::effectiveSellPrice(25, 40, 2));
        // $40 sales × 0.87 − $10 × 2 = $14.80
        $this->assertEqualsWithDelta(14.80, DepopController::l30Profit(40, 2, 10, 0.87), 0.001);
        $this->assertSame(0.0, DepopController::l30Profit(0, 2, 10, 0.87));
    }

    public function test_sheet_pft_percent_subtracts_lp_and_is_not_flat_margin(): void
    {
        // $25 × 0.80 − $10 = $10 → 40% GPFT, not 80%.
        $this->assertEqualsWithDelta(10.0, DepopController::unitProfit(25, 10, 0.80), 0.001);
        $this->assertSame(40, DepopController::gpftPercent(25, 10, 0.80));
        $this->assertSame(100, DepopController::groiPercent(25, 10, 0.80));
    }

    public function test_extract_lp_reads_product_master_values_and_ignores_ship(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 12.5, 'ship' => 9.99];

        $this->assertSame(12.5, DepopController::extractLp($pm));
        $this->assertSame(0.0, DepopController::extractLp(null));
    }

    public function test_active_channel_window_matches_sheet_pft_without_ship_or_fake_cogs(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 10, 'ship' => 8];

        $totals = DepopController::aggregateSalesWindow([
            ['item_price' => 40, 'quantity' => 2, 'sku_code' => 'abc'],
            ['item_price' => 25, 'quantity' => 1, 'sku_code' => 'missing'],
            ['item_price' => 0, 'quantity' => 1, 'sku_code' => 'abc'],
        ], ['ABC' => $pm], 0.87);

        // $80 × 0.87 − $20 = $49.60; $25 × 0.87 − $0 = $21.75; $0 sale does not subtract LP
        $this->assertSame(3, $totals['orders']);
        $this->assertEqualsWithDelta(105.0, $totals['sales'], 0.001);
        $this->assertSame(4, $totals['qty']);
        $this->assertEqualsWithDelta(20.0, $totals['cogs'], 0.001);
        $this->assertEqualsWithDelta(71.35, $totals['pft'], 0.001);
        $this->assertEqualsWithDelta((71.35 / 105) * 100, $totals['gpft'], 0.001);
        $this->assertEqualsWithDelta((71.35 / 20) * 100, $totals['groi'], 0.001);
    }
}
