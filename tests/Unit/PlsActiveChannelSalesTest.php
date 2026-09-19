<?php

namespace Tests\Unit;

use App\Support\Marketplace\PlsActiveChannelSales;
use PHPUnit\Framework\TestCase;

class PlsActiveChannelSalesTest extends TestCase
{
    public function test_apply_to_row_writes_sales_and_growth(): void
    {
        $row = PlsActiveChannelSales::applyToRow([], [
            'l30_sales' => 1250.0,
            'l60_sales' => 1000.0,
            'l30_orders' => 12,
            'l60_orders' => 9,
            'qty' => 20,
            'y_sales' => 40.5,
            'l7_sales' => 210.25,
        ]);

        $this->assertSame('PLS', $row['Channel ']);
        $this->assertSame(1250, $row['L30 Sales']);
        $this->assertSame(1000, $row['L-60 Sales']);
        $this->assertSame(12, $row['L30 Orders']);
        $this->assertSame(20, $row['Qty']);
        $this->assertSame(40.5, $row['Y Sales']);
        $this->assertSame(210.25, $row['L7 Sales']);
        $this->assertSame('25%', $row['Growth']);
    }

    public function test_stub_row_uses_pricing_link_and_zero_ads(): void
    {
        $row = PlsActiveChannelSales::stubRow(
            ['type' => 'B2C', 'missing_link' => '/pls-pricing'],
            [
                'l30_sales' => 10,
                'l60_sales' => 0,
                'l30_orders' => 1,
                'l60_orders' => 0,
                'qty' => 1,
                'y_sales' => 0,
                'l7_sales' => 0,
            ]
        );

        $this->assertSame('/pls-pricing', $row['missing_link']);
        $this->assertSame('B2C', $row['type']);
        $this->assertSame('0%', $row['Ads%']);
        $this->assertSame('0%', $row['Growth']);
    }
}
