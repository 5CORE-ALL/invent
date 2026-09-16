<?php

namespace Tests\Unit;

use App\Services\Business5CoreB2bApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Business5CoreB2bApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.b5cb2b.url' => 'http://b5cb2b.test',
            'services.b5cb2b.api_key' => 'test-key',
            'services.b5cb2b.timeout' => 10,
        ]);
    }

    public function test_ping_sends_x_api_key(): void
    {
        Http::fake([
            'http://b5cb2b.test/api' => Http::response(['name' => 'Business 5 Core Laravel'], 200),
        ]);

        $json = app(Business5CoreB2bApiService::class)->ping();

        $this->assertSame('Business 5 Core Laravel', $json['name'] ?? null);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://b5cb2b.test/api'
                && $request->hasHeader('X-Api-Key', 'test-key');
        });
    }

    public function test_push_inventory_batch_posts_items(): void
    {
        Http::fake([
            'http://b5cb2b.test/api/inventory' => Http::response(['ok' => true], 200),
        ]);

        app(Business5CoreB2bApiService::class)->pushInventory([
            ['sku' => 'ABC', 'qty' => 3],
            ['sku' => 'DEF', 'qty' => 0],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/api/inventory')
                && isset($body['items'][0]['sku'])
                && $body['items'][0]['sku'] === 'ABC';
        });
    }
}
