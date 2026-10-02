<?php

namespace Tests\Unit;

use App\Http\Controllers\InventoryManagement\StockBalanceController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class StockBalanceShopifyTransferTest extends TestCase
{
    private StockBalanceController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.api_key' => 'key',
            'services.shopify.password' => 'token',
            'services.shopify.access_token' => 'token',
            'services.shopify.inventory_location_id' => '777',
        ]);
        $this->controller = $this->app->make(StockBalanceController::class);
    }

    public function test_variant_lookup_uses_graphql_when_rest_is_rate_limited(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/variants/')) {
                return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '2']);
            }
            if (str_contains($request->url(), 'graphql.json') && str_contains((string) ($request->data()['query'] ?? ''), 'productVariant')) {
                return Http::response([
                    'data' => [
                        'productVariant' => [
                            'inventoryItem' => [
                                'id' => 'gid://shopify/InventoryItem/555',
                                'inventoryLevel' => [
                                    'quantities' => [
                                        ['name' => 'available', 'quantity' => 8],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => 'unexpected '.$request->url()], 500);
        });

        $info = $this->invoke('inventoryInfoViaGraphQl', ['88421', 'WF 8"-890 2PC']);

        $this->assertTrue($info['success']);
        $this->assertSame('555', $info['inventory_item_id']);
        $this->assertSame('777', $info['location_id']);
        $this->assertSame(8, $info['available']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/variants/'));
    }

    public function test_adjustment_uses_graphql_and_does_not_call_rest_adjust(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'inventory_levels/adjust.json')) {
                return Http::response(['errors' => 'Exceeded 2 calls per second'], 429, ['Retry-After' => '2']);
            }
            if (str_contains($request->url(), 'graphql.json')) {
                return Http::response([
                    'data' => [
                        'inventoryAdjustQuantities' => [
                            'userErrors' => [],
                            'inventoryAdjustmentGroup' => ['reason' => 'correction'],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => 'unexpected'], 500);
        });

        $result = $this->invoke('adjustShopifyAvailable', ['555', '777', -2]);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'graphql.json')) {
                return false;
            }
            $input = $request->data()['variables']['input'] ?? [];
            $change = $input['changes'][0] ?? [];

            return ($input['name'] ?? null) === 'available'
                && ($change['delta'] ?? null) === -2
                && ($change['inventoryItemId'] ?? null) === 'gid://shopify/InventoryItem/555'
                && ($change['locationId'] ?? null) === 'gid://shopify/Location/777';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'inventory_levels/adjust.json'));
    }

    public function test_adjustment_falls_back_to_rest_when_graphql_mutation_is_missing(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'graphql.json')) {
                return Http::response([
                    'errors' => [[
                        'message' => "Field 'inventoryAdjustQuantities' doesn't exist on type 'Mutation'",
                    ]],
                ], 200);
            }
            if (str_contains($request->url(), 'inventory_levels/adjust.json')) {
                return Http::response(['inventory_level' => ['available' => 6]], 200);
            }

            return Http::response(['errors' => 'unexpected'], 500);
        });

        $result = $this->invoke('adjustShopifyAvailable', ['555', '777', 3]);

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'inventory_levels/adjust.json'));
    }

    public function test_rest_call_retries_429_then_succeeds(): void
    {
        Http::fake([
            'https://example.myshopify.com/admin/api/2025-01/variants/1.json' => Http::sequence()
                ->push(['errors' => 'Exceeded'], 429, ['Retry-After' => '0'])
                ->push(['variant' => ['inventory_item_id' => 555]], 200),
        ]);

        $response = $this->invoke('shopifyApiCall', [
            'GET',
            'https://example.myshopify.com/admin/api/2025-01/variants/1.json',
            [],
            2,
        ]);

        $this->assertTrue($response->successful());
        $this->assertSame(555, $response->json('variant.inventory_item_id'));
        Http::assertSentCount(2);
    }

    private function invoke(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->controller, $method);

        return $reflection->invokeArgs($this->controller, $args);
    }
}
