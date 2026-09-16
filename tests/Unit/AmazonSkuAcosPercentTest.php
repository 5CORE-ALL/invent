<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\OverallAmazonController;
use Tests\TestCase;

class AmazonSkuAcosPercentTest extends TestCase
{
    public function test_acos_is_spend_over_sales_percent(): void
    {
        $this->assertSame(25.0, OverallAmazonController::skuAcosPercent(50, 200));
        $this->assertSame(12.5, OverallAmazonController::skuAcosPercent(25, 200));
    }

    public function test_spend_with_zero_sales_is_one_hundred(): void
    {
        $this->assertSame(100.0, OverallAmazonController::skuAcosPercent(40, 0));
        $this->assertSame(100.0, OverallAmazonController::skuAcosPercent('18.5', null));
    }

    public function test_no_spend_and_no_sales_is_zero(): void
    {
        $this->assertSame(0.0, OverallAmazonController::skuAcosPercent(0, 0));
        $this->assertSame(0.0, OverallAmazonController::skuAcosPercent(null, null));
        $this->assertSame(0.0, OverallAmazonController::skuAcosPercent('x', 'y'));
    }
}
