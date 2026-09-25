<?php

namespace Tests\Unit;

use App\Models\B5cB2bOrder;
use App\Services\MarketplaceManager\B5cB2bOrderPushService;
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

    public function test_shopify_tag_uses_the_channel_name_instead_of_the_slug(): void
    {
        $tags = B5cB2bOrderPushService::shopifyTags('B5-0004', ['b5cb2b', '5Core Inventory']);

        $this->assertSame(['Business 5 Core (B2B)', 'B5-0004', '5Core Inventory'], $tags);

        $rewritten = B5cB2bOrderPushService::rewriteTagList('5Core Inventory, B5-0004, b5cb2b');
        $this->assertTrue($rewritten['changed']);
        $this->assertSame('5Core Inventory, B5-0004, Business 5 Core (B2B)', $rewritten['tags']);

        $already = B5cB2bOrderPushService::rewriteTagList('5Core Inventory, B5-0004, Business 5 Core (B2B)');
        $this->assertFalse($already['changed']);
    }

    public function test_a_saved_shopify_id_counts_only_when_the_order_is_this_channel_order(): void
    {
        $this->assertTrue(B5cB2bOrderPushService::shopifyOrderRecordMatchesChannel([
            'name' => '#344071',
            'tags' => 'B5-0004, b5cb2b',
        ], 'B5-0004', 4));

        $this->assertTrue(B5cB2bOrderPushService::shopifyOrderRecordMatchesChannel([
            'name' => '#B5-0005',
            'tags' => '',
        ], 'B5-0005', 5));

        $this->assertFalse(B5cB2bOrderPushService::shopifyOrderRecordMatchesChannel([
            'name' => '#341560',
            'tags' => 'Best Buy USA',
            'note_attributes' => [],
        ], 'B5-0005', 5));

        $this->assertFalse(B5cB2bOrderPushService::shopifyOrderRecordMatchesChannel([
            'name' => '#341560',
            'tags' => 'b5cb2b',
        ], 'B5-0002', 2));
    }

    public function test_shipping_address_is_read_from_business_5_core_fields(): void
    {
        $fromString = B5cB2bOrderPushService::shopifyAddressFromPayload([
            'shipping_address' => '707 Waterford Dr',
            'shipping_city' => 'Grayslake',
            'shipping_state' => 'IL',
            'shipping_zip' => '60030',
            'shipping_country' => 'United States',
            'phone' => '8472121718',
        ], 'Rick', 'Rudolph');

        $this->assertSame('707 Waterford Dr', $fromString['address1']);
        $this->assertSame('Grayslake', $fromString['city']);
        $this->assertSame('IL', $fromString['province_code']);
        $this->assertSame('60030', $fromString['zip']);
        $this->assertSame('US', $fromString['country_code']);
        $this->assertSame('8472121718', $fromString['phone']);

        $fromNested = B5cB2bOrderPushService::shopifyAddressFromPayload([
            'shipping' => [
                'address_line_1' => '10 Main St',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
            ],
        ], 'Avi', 'harrison');

        $this->assertSame('10 Main St', $fromNested['address1']);
        $this->assertSame('Chicago', $fromNested['city']);
        $this->assertSame('60601', $fromNested['zip']);
    }
}
