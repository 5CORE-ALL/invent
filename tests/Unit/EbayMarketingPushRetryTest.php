<?php

namespace Tests\Unit;

use App\Support\EbayMarketingPushRetry;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EbayMarketingPushRetryTest extends TestCase
{
    public function test_token_rejection_refreshes_the_token_and_retries(): void
    {
        $seen = [];
        Http::fake(function ($request) use (&$seen) {
            $seen[] = $request->header('Authorization')[0] ?? '';
            if (count($seen) === 1) {
                return Http::response(['errors' => [['message' => 'Invalid access token']]], 401);
            }

            return Http::response([], 200);
        });

        $api = new class
        {
            public int $forgotten = 0;

            public function forgetBearerToken(): void
            {
                $this->forgotten++;
            }

            public function generateBearerToken(): string
            {
                return $this->forgotten > 0 ? 'fresh-token' : 'stale-token';
            }
        };

        $http = new EbayMarketingPushRetry($api);
        $http->sleeper = static function (): void {};
        $http->acquireToken();
        $out = $http->post('https://api.ebay.com/sell/marketing/v1/ad_campaign/1/bulk_update_ads_bid_by_listing_id', [
            'requests' => [],
        ]);

        $this->assertTrue($out['ok']);
        $this->assertSame(2, $out['attempts']);
        $this->assertSame(1, $api->forgotten);
        $this->assertSame(['Bearer stale-token', 'Bearer fresh-token'], $seen);
    }

    public function test_transient_failure_retries_five_times(): void
    {
        $calls = 0;
        $slept = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['errors' => [['message' => 'Internal error']]], 503);
        });

        $api = new class
        {
            public function generateBearerToken(): string
            {
                return 'token';
            }
        };

        $http = new EbayMarketingPushRetry($api);
        $http->sleeper = static function () use (&$slept): void {
            $slept++;
        };
        $http->acquireToken();
        $out = $http->post('https://api.ebay.com/sell/marketing/v1/ad_campaign/1/bulk_update_ads_bid_by_listing_id', [
            'requests' => [],
        ]);

        $this->assertFalse($out['ok']);
        $this->assertSame(5, $calls);
        $this->assertSame(4, $slept);
        $this->assertStringContainsString('after 5 attempts', (string) $out['error']);
    }

    public function test_business_error_is_not_retried(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['errors' => [['message' => 'Seller level standard']]], 409);
        });

        $api = new class
        {
            public int $forgotten = 0;

            public function forgetBearerToken(): void
            {
                $this->forgotten++;
            }

            public function generateBearerToken(): string
            {
                return 'token';
            }
        };

        $http = new EbayMarketingPushRetry($api);
        $http->sleeper = static function (): void {};
        $http->acquireToken();
        $out = $http->post('https://api.ebay.com/sell/marketing/v1/ad_campaign/1/bulk_update_ads_bid_by_listing_id', [
            'requests' => [],
        ]);

        $this->assertFalse($out['ok']);
        $this->assertSame(1, $calls);
        $this->assertSame(0, $api->forgotten);
        $this->assertStringNotContainsString('after', (string) $out['error']);
    }

    public function test_dead_refresh_token_stops_without_another_push(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['errors' => [['message' => 'Invalid access token']]], 401);
        });

        $api = new class
        {
            public function forgetBearerToken(): void {}

            public function generateBearerToken(): string
            {
                static $n = 0;
                $n++;
                if ($n === 1) {
                    return 'stale-token';
                }
                throw new \RuntimeException('Refresh token expired. Please generate a new one.');
            }
        };

        $http = new EbayMarketingPushRetry($api);
        $http->sleeper = static function (): void {};
        $http->acquireToken();
        $out = $http->post('https://api.ebay.com/sell/marketing/v1/ad_campaign/1/bulk_update_ads_bid_by_listing_id', [
            'requests' => [],
        ]);

        $this->assertFalse($out['ok']);
        $this->assertSame(1, $calls);
        $this->assertStringContainsString('Refresh token expired', (string) $out['error']);
    }
}
