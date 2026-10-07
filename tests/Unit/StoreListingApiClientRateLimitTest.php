<?php

namespace Tests\Unit;

use App\Services\StoreListingApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreListingApiClientRateLimitTest extends TestCase
{
    public function test_price_fetch_retries_429_then_succeeds(): void
    {
        config([
            'services.store.url' => 'https://business5core.com',
            'services.store.api_key' => 'test-key',
        ]);

        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '0']);
            }

            return Http::response([
                'data' => [['sku' => 'ABC', 'id' => 9]],
                'meta' => ['last_page' => 1],
            ], 200);
        });

        $page = app(StoreListingApiClient::class)->fetchPricePage(1, 10, 'ABC');

        $this->assertSame(2, $calls);
        $this->assertSame('ABC', $page['data'][0]['sku']);
    }
}
