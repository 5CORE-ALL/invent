<?php

namespace Tests\Unit;

use App\Services\ShopifyVariantPriceUpdater;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyVariantPriceUpdaterTest extends TestCase
{
    public function test_graphql_price_update_verifies_the_returned_price(): void
    {
        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.password' => 'test-token',
        ]);

        Http::fake([
            'https://example.myshopify.com/*' => Http::sequence()
                ->push([
                    'data' => [
                        'productVariant' => [
                            'product' => ['id' => 'gid://shopify/Product/8104866873581'],
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        'productVariantsBulkUpdate' => [
                            'productVariants' => [[
                                'id' => 'gid://shopify/ProductVariant/48429790036205',
                                'price' => '23.90',
                            ]],
                            'userErrors' => [],
                        ],
                    ],
                ]),
        ]);

        $result = app(ShopifyVariantPriceUpdater::class)->update('48429790036205', 23.9, 'b2c');

        $this->assertSame('success', $result['status']);
        $this->assertSame(23.9, $result['verified_price']);
        Http::assertSentCount(2);
    }

    public function test_shopify_rejection_is_not_retried_on_the_rest_bucket(): void
    {
        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.password' => 'test-token',
        ]);

        Http::fake([
            'https://example.myshopify.com/*' => Http::sequence()
                ->push([
                    'data' => [
                        'productVariant' => [
                            'product' => ['id' => 'gid://shopify/Product/1'],
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        'productVariantsBulkUpdate' => [
                            'productVariants' => [],
                            'userErrors' => [['field' => ['price'], 'message' => 'Price is invalid']],
                        ],
                    ],
                ]),
        ]);

        $result = app(ShopifyVariantPriceUpdater::class)->update('99', 23.9, 'b2c');

        $this->assertSame('error', $result['status']);
        $this->assertSame('Price is invalid', $result['message']);
        Http::assertSentCount(2);
    }
}
