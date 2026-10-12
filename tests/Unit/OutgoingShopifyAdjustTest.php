<?php

namespace Tests\Unit;

use App\Http\Controllers\InventoryManagement\OutgoingController;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class OutgoingShopifyAdjustTest extends TestCase
{
    private OutgoingController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.api_key' => 'key',
            'services.shopify.password' => 'legacy-password',
            'services.shopify.access_token' => 'shpat-token',
            'services.shopify.inventory_location_id' => '777',
        ]);
        $this->controller = $this->app->make(OutgoingController::class);
    }

    public function test_outgoing_adjust_uses_access_token_at_the_configured_location(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('shpat-token', $request->header('X-Shopify-Access-Token')[0] ?? null);
            $this->assertFalse($request->hasHeader('Authorization'));

            return Http::response([
                'data' => [
                    'inventoryAdjustQuantities' => [
                        'userErrors' => [],
                        'inventoryAdjustmentGroup' => [
                            'reason' => 'correction',
                            'changes' => [[
                                'name' => 'available',
                                'delta' => -2,
                                'quantityAfterChange' => 8,
                            ]],
                        ],
                    ],
                ],
            ]);
        });

        $result = $this->push('555', '777', -2);

        $this->assertTrue($result['ok']);
        $this->assertSame(8, $result['available']);
        Http::assertSent(function ($request) {
            $change = $request->data()['variables']['input']['changes'][0] ?? [];

            return str_contains($request->url(), '/graphql.json')
                && ($change['delta'] ?? null) === -2
                && ($change['locationId'] ?? null) === 'gid://shopify/Location/777'
                && ($change['inventoryItemId'] ?? null) === 'gid://shopify/InventoryItem/555';
        });
    }

    public function test_outgoing_connects_the_sku_when_it_is_not_stocked_at_main_warehouse(): void
    {
        $graphqlCalls = 0;
        Http::fake(function ($request) use (&$graphqlCalls) {
            if (str_contains($request->url(), 'inventory_levels/connect.json')) {
                return Http::response(['inventory_level' => ['available' => 0]], 200);
            }

            $graphqlCalls++;
            if ($graphqlCalls === 1) {
                return Http::response([
                    'data' => [
                        'inventoryAdjustQuantities' => [
                            'userErrors' => [[
                                'field' => ['inventoryItemId'],
                                'message' => 'The specified inventory item is not stocked at the location.',
                            ]],
                            'inventoryAdjustmentGroup' => null,
                        ],
                    ],
                ]);
            }

            return Http::response([
                'data' => [
                    'inventoryAdjustQuantities' => [
                        'userErrors' => [],
                        'inventoryAdjustmentGroup' => [
                            'changes' => [[
                                'name' => 'available',
                                'delta' => -1,
                                'quantityAfterChange' => 4,
                            ]],
                        ],
                    ],
                ],
            ]);
        });

        $result = $this->push('555', '777', -1);

        $this->assertTrue($result['ok']);
        $this->assertSame(4, $result['available']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/connect.json')
                && ($request->data()['location_id'] ?? null) === '777'
                && ($request->data()['inventory_item_id'] ?? null) === '555';
        });
    }

    /**
     * @return array{ok: bool, error: ?string, status: int, available: ?int}
     */
    private function push(string $inventoryItemId, string $locationId, int $adjustment): array
    {
        $method = new ReflectionMethod(OutgoingController::class, 'pushShopifyAvailableAdjustment');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $inventoryItemId, $locationId, $adjustment);
    }
}
