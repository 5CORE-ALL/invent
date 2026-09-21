<?php

namespace Tests\Unit;

use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class ReverbPricePushListingLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.reverb.token' => 'test-token',
            'services.reverb.client_id' => '',
            'services.reverb.client_secret' => '',
            'services.reverb.api_url' => 'https://api.reverb.com/api',
        ]);
        Cache::forget('reverb_oauth_access_token');
    }

    public function test_sku_filter_hit_does_not_page_the_whole_catalog(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'sku=')) {
                return Http::response([
                    'listings' => [
                        ['id' => '111', 'sku' => 'ABC 1'],
                    ],
                    '_links' => [
                        'next' => ['href' => 'https://api.reverb.com/api/my/listings?state=all&page=2'],
                    ],
                ], 200);
            }

            return Http::response([
                'listings' => [
                    ['id' => '999', 'sku' => 'ABC 1'],
                ],
            ], 200);
        });

        $this->assertSame(['111'], $this->fetchIds('ABC 1'));
        Http::assertSentCount(1);
    }

    public function test_catalog_scan_stops_at_the_first_matching_page(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'sku=')) {
                return Http::response(['listings' => []], 200);
            }
            if (str_contains($url, 'page=2')) {
                return Http::response([
                    'listings' => [
                        ['id' => '222', 'sku' => 'ABC 1'],
                    ],
                    '_links' => [
                        'next' => ['href' => 'https://api.reverb.com/api/my/listings?state=all&per_page=100&page=3'],
                    ],
                ], 200);
            }

            return Http::response([
                'listings' => [
                    ['id' => '1', 'sku' => 'OTHER'],
                ],
                '_links' => [
                    'next' => ['href' => 'https://api.reverb.com/api/my/listings?state=all&per_page=100&page=2'],
                ],
            ], 200);
        });

        $this->assertSame(['222'], $this->fetchIds('ABC 1', true));
        Http::assertSentCount(3);
    }

    /**
     * @return list<string>
     */
    private function fetchIds(string $sku, bool $stopAtFirst = false): array
    {
        $service = new ReverbApiService();
        $method = new ReflectionMethod($service, 'fetchListingIdsFromReverbApiBySku');
        $method->setAccessible(true);

        return $method->invoke($service, $sku, $stopAtFirst);
    }
}
