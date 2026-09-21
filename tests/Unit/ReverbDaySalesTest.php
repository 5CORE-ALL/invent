<?php

namespace Tests\Unit;

use App\Services\ReverbDaySales;
use PHPUnit\Framework\TestCase;

class ReverbDaySalesTest extends TestCase
{
    public function test_order_total_uses_payload_total_not_unit_price(): void
    {
        $raw = [
            'order' => [
                'total' => ['amount' => '42.35'],
                'amount_product' => ['amount' => '19.79'],
                'amount_product_subtotal' => ['amount' => '39.58'],
            ],
        ];

        $this->assertSame(42.35, ReverbDaySales::orderTotalFromPayload($raw, 19.79, 2));
    }

    public function test_order_total_falls_back_to_unit_price_times_qty(): void
    {
        $this->assertSame(39.58, ReverbDaySales::orderTotalFromPayload(null, 19.79, 2));
    }
}
