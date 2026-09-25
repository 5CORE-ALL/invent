<?php

namespace Tests\Unit;

use App\Models\B5cB2bOrder;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use PHPUnit\Framework\TestCase;

class B5cB2bOrderNumberTest extends TestCase
{
    public function test_numeric_store_id_displays_as_business_5_core_code(): void
    {
        $order = new B5cB2bOrder([
            'store_order_id' => 9,
            'payload' => [],
        ]);

        $this->assertSame('B5-0009', $order->channelOrderNumber());
        $this->assertSame('B5-0002', (new B5cB2bOrder(['store_order_id' => 2]))->channelOrderNumber());
    }

    public function test_payload_order_number_is_kept(): void
    {
        $order = new B5cB2bOrder([
            'store_order_id' => 9,
            'payload' => ['order_number' => 'b5-0009'],
        ]);

        $this->assertSame('B5-0009', $order->channelOrderNumber());
    }

    public function test_search_accepts_the_public_code_and_the_numeric_id(): void
    {
        $this->assertSame(9, B5cB2bOrder::storeIdFromSearch('B5-0009'));
        $this->assertSame(9, B5cB2bOrder::storeIdFromSearch('#B5-0009'));
        $this->assertSame(9, B5cB2bOrder::storeIdFromSearch('9'));
        $this->assertNull(B5cB2bOrder::storeIdFromSearch('Rick Rudolph'));
    }

    public function test_line_sku_is_read_from_product_sku_and_nested_product(): void
    {
        $fromProductSku = (new B5cB2bOrder([
            'store_order_id' => 9,
            'payload' => [
                'products' => [[
                    'product_sku' => 'KS 2X HAND',
                    'name' => 'Keyboard stand',
                    'qty' => 5,
                    'unit_price' => 12.58,
                ]],
            ],
        ]))->normalizedLines();

        $this->assertSame('KS 2X HAND', $fromProductSku[0]['sku']);
        $this->assertSame(5, $fromProductSku[0]['qty']);

        $nested = (new B5cB2bOrder([
            'store_order_id' => 9,
            'payload' => [
                'items' => [[
                    'product' => ['sku' => 'KS 2X HAND', 'name' => 'Keyboard stand'],
                    'quantity' => 5,
                    'price' => 12.58,
                ]],
            ],
        ]))->normalizedLines();

        $this->assertSame('KS 2X HAND', $nested[0]['sku']);
        $this->assertSame('Keyboard stand', $nested[0]['name']);
        $this->assertSame(5, $nested[0]['qty']);
    }

    public function test_pending_fulfillment_is_still_paid_for_shopify_import(): void
    {
        $order = new B5cB2bOrder([
            'status' => 'pending',
            'payload' => [],
        ]);

        $this->assertTrue(MarketplaceOrderPaidFilter::isPaid('b5cb2b', $order));
        $this->assertFalse(MarketplaceOrderPaidFilter::isPaid('b5cb2b', new B5cB2bOrder([
            'status' => 'unpaid',
        ])));
    }
}
