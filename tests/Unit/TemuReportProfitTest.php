<?php

namespace Tests\Unit;

use App\Services\TemuShopifySalesService;
use PHPUnit\Framework\TestCase;

class TemuReportProfitTest extends TestCase
{
    public function test_sales_amount_adds_shipping_only_under_30(): void
    {
        $this->assertSame(9.54, TemuShopifySalesService::reportSalesUnit(6.55, 9.54));
        $this->assertSame(9.54, TemuShopifySalesService::reportSalesUnit(6.55, 0.0));
        $this->assertSame(30.99, TemuShopifySalesService::reportSalesUnit(28.0, 28.0));
        $this->assertSame(40.0, TemuShopifySalesService::reportSalesUnit(40.0, 42.99));
        $this->assertSame(30.0, TemuShopifySalesService::reportSalesUnit(30.0, 32.99));
    }

    public function test_gpft_uses_margin_and_nroi_uses_net_profit_over_cogs(): void
    {
        $line = TemuShopifySalesService::reportLine([
            'quantity_purchased' => 2,
            'listing_base_price' => 10,
            'base_price_total' => 10,
            'line_sales' => 0,
            'lp' => 4,
            'temu_ship' => 3,
        ]);

        // Sales unit = 10 + 2.99. GPFT$ = (12.99 × 0.95 − 4 − 3) × 2.
        $this->assertEqualsWithDelta(25.98, $line['sales'], 0.001);
        $this->assertEqualsWithDelta(10.681, $line['pft'], 0.001);
        $this->assertEqualsWithDelta(8.0, $line['cogs'], 0.001);

        $spend = 1.0;
        $adsPct = ($spend / $line['sales']) * 100;
        $gpftPct = ($line['pft'] / $line['sales']) * 100;
        $groi = ($line['pft'] / $line['cogs']) * 100;
        $nroi = TemuShopifySalesService::nroiPercent($line['pft'], $spend, $line['cogs']);

        $this->assertEqualsWithDelta($gpftPct - $adsPct, (($line['pft'] - $spend) / $line['sales']) * 100, 0.001);
        $this->assertEqualsWithDelta((($line['pft'] - $spend) / $line['cogs']) * 100, $nroi, 0.001);
        $this->assertNotEqualsWithDelta($groi - $adsPct, $nroi, 0.5);
    }

    public function test_api_line_is_not_charged_shipping_twice(): void
    {
        $line = TemuShopifySalesService::reportLine([
            'quantity_purchased' => 1,
            'listing_base_price' => 0,
            'base_price_total' => 9.54,
            'line_sales' => 9.54,
            'lp' => 2.8,
            'temu_ship' => 4.83,
        ]);

        $this->assertEqualsWithDelta(9.54, $line['sales'], 0.001);
        $this->assertEqualsWithDelta(9.54 * 0.95 - 2.8 - 4.83, $line['pft'], 0.001);
    }

    public function test_stale_catalog_price_does_not_replace_the_order_line(): void
    {
        $line = TemuShopifySalesService::reportLine([
            'quantity_purchased' => 1,
            'listing_base_price' => 40,
            'base_price_total' => 9.54,
            'line_sales' => 9.54,
            'lp' => 2.8,
            'temu_ship' => 4.83,
        ]);

        $this->assertEqualsWithDelta(9.54, $line['sales'], 0.001);
    }
}
