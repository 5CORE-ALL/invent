<?php

namespace Tests\Unit;

use App\Http\Controllers\Sales\AmazonSalesController;
use PHPUnit\Framework\TestCase;

class AmazonSalesLineFinancialsTest extends TestCase
{
    public function test_single_unit_subtracts_cogs_and_cogs_ship_once(): void
    {
        $fin = AmazonSalesController::lineFinancials(1, 50.0, 20.0, 5.0, 2.0);

        $this->assertSame(50.0, $fin['unit_price']);
        $this->assertSame(5.0, $fin['ship_cost']);
        $this->assertSame(20.0, $fin['cogs']);
        $this->assertEqualsWithDelta(15.0, $fin['pft'], 0.001); // (50 * 0.80) - 20 - 5
        $this->assertEqualsWithDelta(75.0, $fin['roi'], 0.001);
        $this->assertEqualsWithDelta(30.0, $fin['pft_each_pct'], 0.001);
    }

    public function test_multi_qty_subtracts_cogs_ship_once(): void
    {
        $fin = AmazonSalesController::lineFinancials(2, 80.0, 10.0, 6.0, 3.0);

        $this->assertSame(40.0, $fin['unit_price']);
        $this->assertSame(6.0, $fin['ship_cost']);
        $this->assertSame(20.0, $fin['cogs']);
        $this->assertEqualsWithDelta(38.0, $fin['pft'], 0.001); // (80 * 0.80) - 20 - 6
        $this->assertEqualsWithDelta(190.0, $fin['roi'], 0.001);
    }

    public function test_heavy_order_still_subtracts_cogs_ship_once(): void
    {
        $fin = AmazonSalesController::lineFinancials(2, 80.0, 10.0, 6.0, 12.0);

        $this->assertSame(6.0, $fin['ship_cost']);
        $this->assertEqualsWithDelta(38.0, $fin['pft'], 0.001); // (80 * 0.80) - 20 - 6
    }

    public function test_zero_qty_is_safe(): void
    {
        $fin = AmazonSalesController::lineFinancials(0, 50.0, 20.0, 5.0, 2.0);

        $this->assertSame(0.0, $fin['unit_price']);
        $this->assertSame(0.0, $fin['cogs']);
        $this->assertSame(0.0, $fin['pft']);
        $this->assertSame(0.0, $fin['roi']);
    }
}
