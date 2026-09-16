<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\PurchasingPowerController;
use Tests\TestCase;

class PurchasingPowerL30SoldRollupTest extends TestCase
{
    public function test_rollup_matches_sales_page_qty_and_revenue(): void
    {
        $lines = [
            (object) [
                'sku' => "WF\xC2\xA08120 4 OHM 2PCS",
                'quantity' => 2,
                'unit_price' => 20.00,
                'amount' => 40.00,
                'status' => 'SHIPPING',
            ],
            (object) [
                'sku' => 'WF 8120 4 OHM 2PCS',
                'quantity' => 1,
                'unit_price' => 22.00,
                'amount' => 22.00,
                'status' => 'CANCELED',
            ],
            (object) [
                'sku' => 'OTHER SKU',
                'quantity' => 0,
                'unit_price' => 10.00,
                'amount' => 0,
            ],
        ];

        $rollup = PurchasingPowerController::rollupL30SoldFromLines($lines);
        $sold = PurchasingPowerController::lookupL30Sold($rollup, 'WF 8120 4 OHM 2PCS');

        $this->assertSame(3, $sold['qty']);
        $this->assertEqualsWithDelta(62.00, $sold['sales'], 0.001);
    }

    public function test_lookup_matches_nbsp_product_master_sku(): void
    {
        $rollup = PurchasingPowerController::rollupL30SoldFromLines([
            (object) [
                'sku' => 'WF 8120 4 OHM 2PCS',
                'quantity' => 4,
                'unit_price' => 12.50,
                'amount' => 50.00,
            ],
        ]);

        $sold = PurchasingPowerController::lookupL30Sold($rollup, "WF\xC2\xA08120\xC2\xA04\xC2\xA0OHM 2PCS");

        $this->assertSame(4, $sold['qty']);
        $this->assertEqualsWithDelta(50.00, $sold['sales'], 0.001);
    }

    public function test_lp_and_ship_bb_uses_shipping_master_ship_bb(): void
    {
        $pm = (object) [
            'sku' => 'WF 8120 4 OHM 2PCS',
            'Values' => [
                'lp' => 10,
                'ship' => 6,
                'ship_bb_base' => 8,
                'handling_charge' => 1,
                'o_size_charge' => 0.5,
            ],
        ];

        $cost = PurchasingPowerController::lpAndShipBb($pm);

        $this->assertEqualsWithDelta(10.0, $cost['lp'], 0.001);
        $this->assertEqualsWithDelta(9.5, $cost['ship'], 0.001);
    }
}
