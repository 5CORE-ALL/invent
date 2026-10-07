<?php

namespace Tests\Unit;

use App\Http\Controllers\InventoryManagement\VerificationAdjustmentController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class VerificationShopifyAdjustmentRateLimitTest extends TestCase
{
    private VerificationAdjustmentController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.api_key' => 'key',
            'services.shopify.password' => 'token',
            'services.shopify.access_token' => 'token',
            'services.shopify.inventory_location_id' => '77',
        ]);
        $this->controller = $this->app->make(VerificationAdjustmentController::class);
    }

    public function test_rate_limit_error_is_detected(): void
    {
        $this->assertTrue(VerificationAdjustmentController::isShopifyRateLimitError(
            'HTTP 429 - "Exceeded 2 calls per second for api client"'
        ));
        $this->assertTrue(VerificationAdjustmentController::isShopifyRateLimitError(
            'Shopify is busy (rate limited). Automatic retry scheduled.'
        ));
        $this->assertFalse(VerificationAdjustmentController::isShopifyRateLimitError('SKU not found in Shopify'));
    }

    public function test_adjustment_uses_graphql_and_skips_rest(): void
    {
        Cache::put('va:shopify_iid:06CWV', '555', 86400);
        Cache::put('shopify_main_warehouse_location_id', '77', 3600);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'inventory_levels/adjust.json')) {
                return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '2']);
            }
            if (str_contains($request->url(), 'graphql.json')) {
                return Http::response([
                    'data' => [
                        'inventoryAdjustQuantities' => [
                            'userErrors' => [],
                            'inventoryAdjustmentGroup' => [
                                'reason' => 'correction',
                                'changes' => [
                                    ['name' => 'available', 'delta' => 2, 'quantityAfterChange' => 12],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => 'unexpected '.$request->url()], 500);
        });

        $result = $this->invoke('adjustShopifyInventoryFast', ['06CWV', 2]);

        $this->assertTrue($result['success']);
        $this->assertSame(12, $result['available']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'inventory_levels/adjust.json'));
    }

    public function test_rest_fallback_still_retries_through_shared_gate(): void
    {
        Cache::put('va:shopify_iid:06CWV', '555', 86400);
        Cache::put('shopify_main_warehouse_location_id', '77', 3600);

        $adjustCalls = 0;
        Http::fake(function ($request) use (&$adjustCalls) {
            if (str_contains($request->url(), 'graphql.json')) {
                return Http::response([
                    'errors' => [[
                        'message' => "Field 'inventoryAdjustQuantities' doesn't exist on type 'Mutation'",
                    ]],
                ], 200);
            }
            if (str_contains($request->url(), 'inventory_levels/adjust.json')) {
                $adjustCalls++;
                if ($adjustCalls === 1) {
                    return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '0']);
                }

                return Http::response(['inventory_level' => ['available' => 9]], 200);
            }

            return Http::response(['errors' => 'unexpected '.$request->url()], 500);
        });

        $result = $this->invoke('adjustShopifyInventoryFast', ['06CWV', 1]);

        $this->assertTrue($result['success']);
        $this->assertSame(9, $result['available']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'inventory_levels/adjust.json'));
    }

    public function test_graphql_throttle_retries_graphql_and_skips_rest_gate(): void
    {
        Cache::put('va:shopify_iid:06CWV', '555', 86400);
        Cache::put('shopify_main_warehouse_location_id', '77', 3600);

        $graphqlCalls = 0;
        Http::fake(function ($request) use (&$graphqlCalls) {
            if (str_contains($request->url(), 'inventory_levels/adjust.json') || str_contains($request->url(), 'variants/')) {
                return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '2']);
            }
            if (str_contains($request->url(), 'graphql.json')) {
                $graphqlCalls++;
                if ($graphqlCalls === 1) {
                    return Http::response([
                        'errors' => [[
                            'message' => 'Throttled',
                            'extensions' => ['code' => 'THROTTLED'],
                        ]],
                    ], 200, ['Retry-After' => '0']);
                }

                return Http::response([
                    'data' => [
                        'inventoryAdjustQuantities' => [
                            'userErrors' => [],
                            'inventoryAdjustmentGroup' => [
                                'reason' => 'correction',
                                'changes' => [
                                    ['name' => 'available', 'delta' => 2, 'quantityAfterChange' => 12],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => 'unexpected '.$request->url()], 500);
        });

        $result = $this->invoke('adjustShopifyInventoryFast', ['06CWV', 2]);

        $this->assertTrue($result['success']);
        $this->assertSame(12, $result['available']);
        $this->assertSame(2, $graphqlCalls);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'inventory_levels/adjust.json'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'variants/'));
    }

    public function test_cold_location_uses_graphql_and_skips_locations_json(): void
    {
        Cache::put('va:shopify_iid:06CWV', '555', 86400);

        Http::fake(function ($request) {
            $body = (string) $request->body();
            if (str_contains($request->url(), 'locations.json') || str_contains($request->url(), 'inventory_levels/adjust.json')) {
                return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '2']);
            }
            if (str_contains($body, 'VerificationLocations')) {
                return Http::response([
                    'data' => [
                        'locations' => [
                            'nodes' => [
                                ['id' => 'gid://shopify/Location/10', 'name' => 'Ohio Warehouse', 'isActive' => true],
                                ['id' => 'gid://shopify/Location/77', 'name' => 'Main Warehouse', 'isActive' => true],
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($body, 'inventoryAdjustQuantities')) {
                return Http::response([
                    'data' => [
                        'inventoryAdjustQuantities' => [
                            'userErrors' => [],
                            'inventoryAdjustmentGroup' => [
                                'reason' => 'correction',
                                'changes' => [
                                    ['name' => 'available', 'delta' => 1, 'quantityAfterChange' => 4],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => 'unexpected '.$request->url()], 500);
        });

        $result = $this->invoke('adjustShopifyInventoryFast', ['06CWV', 1]);

        $this->assertTrue($result['success']);
        $this->assertSame(4, $result['available']);
        $this->assertSame('77', Cache::get('shopify_main_warehouse_location_id'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'locations.json'));
    }

    public function test_sku_lookup_uses_graphql_not_rest_variant(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'variants/') || str_contains($request->url(), 'products.json')) {
                return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '2']);
            }

            return Http::response([
                'data' => [
                    'productVariants' => [
                        'nodes' => [
                            ['inventoryItem' => ['id' => 'gid://shopify/InventoryItem/555']],
                        ],
                    ],
                ],
            ], 200);
        });

        $id = $this->invoke('findInventoryItemIdBySkuGraphQl', ['06CWV']);

        $this->assertSame('555', $id);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'variants/'));
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function invoke(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->controller, $method);

        return $reflection->invokeArgs($this->controller, $args);
    }
}
