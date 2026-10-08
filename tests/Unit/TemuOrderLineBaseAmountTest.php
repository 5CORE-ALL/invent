<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\TemuOrderAmountParser;
use PHPUnit\Framework\TestCase;

/**
 * lineSalesAmount() is goods + the $2.99 Temu pays on top. Pages that add that freight
 * themselves need the goods part on its own, or the freight is counted twice.
 */
class TemuOrderLineBaseAmountTest extends TestCase
{
    private function order(array $entry, string $orderSn = 'PO-1'): object
    {
        return (object) [
            'order_sn' => $orderSn,
            'amount_raw_json' => json_encode([
                'orderList' => [array_merge(['orderSn' => $orderSn], $entry)],
            ]),
        ];
    }

    public function test_line_base_is_goods_without_freight(): void
    {
        // HW 1 SKY BLU: $5.40 goods + $2.99 freight = $8.39 collected.
        $order = $this->order([
            'basePrice' => ['amount' => 540, 'currency' => 'USD'],
            'shipAmountTotal' => ['amount' => 299, 'currency' => 'USD'],
        ]);

        $this->assertSame(5.4, TemuOrderAmountParser::lineBaseAmount($order));
        $this->assertSame(8.39, TemuOrderAmountParser::lineSalesAmount($order));
    }

    public function test_line_base_equals_sales_when_there_is_no_freight(): void
    {
        $order = $this->order(['basePrice' => ['amount' => 4941, 'currency' => 'USD']]);

        $this->assertSame(49.41, TemuOrderAmountParser::lineBaseAmount($order));
        $this->assertSame(49.41, TemuOrderAmountParser::lineSalesAmount($order));
    }

    public function test_null_without_a_payload_or_a_matching_order(): void
    {
        $this->assertNull(TemuOrderAmountParser::lineBaseAmount((object) ['order_sn' => 'PO-1']));

        $other = $this->order(['basePrice' => ['amount' => 540, 'currency' => 'USD']], 'PO-2');
        $other->order_sn = 'PO-1';
        $this->assertNull(TemuOrderAmountParser::lineBaseAmount($other));
    }
}
