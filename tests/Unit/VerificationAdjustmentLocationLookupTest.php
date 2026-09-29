<?php

namespace Tests\Unit;

use App\Http\Controllers\InventoryManagement\VerificationAdjustmentController;
use App\Services\ShopifyOhioLocationResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class VerificationAdjustmentLocationLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.access_token' => 'test-token',
            'services.shopify.password' => null,
            'services.shopify.inventory_location_id' => null,
        ]);
    }

    public function test_ohio_location_retries_http_429_and_caches_the_id(): void
    {
        Http::fake([
            'https://example.myshopify.com/admin/api/2025-01/locations.json' => Http::sequence()
                ->push(['errors' => 'Exceeded'], 429, ['Retry-After' => '0'])
                ->push([
                    'locations' => [
                        ['id' => 4242, 'name' => 'Ohio Warehouse'],
                    ],
                ], 200),
        ]);

        $this->assertSame('4242', ShopifyOhioLocationResolver::preferredLocationId());
        $this->assertSame('4242', ShopifyOhioLocationResolver::preferredLocationId());

        Http::assertSentCount(2);
    }

    public function test_location_lookup_retries_http_429_then_caches_the_level(): void
    {
        config(['services.shopify.inventory_location_id' => '555']);

        Http::fake([
            'https://example.myshopify.com/admin/api/2025-01/inventory_levels.json*' => Http::sequence()
                ->push(['errors' => 'Exceeded'], 429, ['Retry-After' => '0'])
                ->push([
                    'inventory_levels' => [
                        ['location_id' => 555, 'available' => 4],
                    ],
                ], 200),
        ]);

        $controller = $this->app->make(VerificationAdjustmentController::class);
        $method = new ReflectionMethod($controller, 'getLocationIdFast');

        $this->assertSame('555', $method->invoke($controller, '999'));
        $this->assertSame('555', $method->invoke($controller, '999'));

        Http::assertSentCount(2);
    }
}
