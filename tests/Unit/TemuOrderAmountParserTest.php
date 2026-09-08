<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\TemuOrderAmountParser;
use PHPUnit\Framework\TestCase;

class TemuOrderAmountParserTest extends TestCase
{
    public function test_line_sales_amount_is_base_plus_freight_from_amount_api(): void
    {
        $payload = [
            'parentOrderMap' => [
                'estimatedRevenue' => ['amount' => 589, 'currency' => 'USD'],
                'basePriceTotal' => ['amount' => 290, 'currency' => 'USD'],
                'shippingAmountTotal' => ['amount' => 299, 'currency' => 'USD'],
            ],
            'orderList' => [[
                'quantity' => 2,
                'orderSn' => '211-18563365870711960',
                'shipAmountTotal' => ['amount' => 299, 'currency' => 'USD'],
                'unitBasePrice' => ['amount' => 145, 'currency' => 'USD'],
                'basePrice' => ['amount' => 290, 'currency' => 'USD'],
            ]],
        ];

        $order = (object) [
            'order_sn' => '211-18563365870711960',
            'order_base_amount' => 2.90,
            'amount_raw_json' => json_encode($payload),
        ];

        $this->assertSame(5.89, TemuOrderAmountParser::lineSalesAmount($order));
    }

    public function test_line_sales_falls_back_to_stored_base_when_json_missing(): void
    {
        $order = (object) [
            'order_sn' => '211-1',
            'order_base_amount' => 18.50,
            'amount_raw_json' => null,
        ];

        $this->assertSame(18.5, TemuOrderAmountParser::lineSalesAmount($order));
    }
}
