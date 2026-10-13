<?php

namespace App\Services\MarketplaceManager;

use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SKUs that exist in Mirakl Connect for Macy's, Best Buy and Purchasing Power.
 * A Connect product is a page created on the marketplace (pending, rejected or
 * inactive included). It is not Missing L and it is not an active listing.
 */
class MiraklConnectProductIndex
{
    /** config key => Mirakl Connect channel_code */
    public const CONNECT_CODES = [
        'macy' => 'macys',
        'bestbuy' => 'bestbuyusa',
        'purchasingpower' => 'purchasingpower',
    ];

    public ?string $lastError = null;

    public static function cacheKey(string $configKey): string
    {
        return 'mirakl.connect.product_index.v1.'.$configKey;
    }

    /**
     * @return array{fetched_at: string, count: int, keys: array<string, true>}|null
     */
    public function get(string $channel, bool $refreshIfMissing = true): ?array
    {
        $key = MiraklMcmOfferIndex::configKey($channel);
        if ($key === null || ! isset(self::CONNECT_CODES[$key])) {
            return null;
        }
        $cached = Cache::get(self::cacheKey($key));
        if (is_array($cached) && ($cached['keys'] ?? []) !== []) {
            return $cached;
        }
        if (! $refreshIfMissing || Cache::get(self::cacheKey($key).'.fail')) {
            return is_array($cached) ? $cached : null;
        }

        return $this->refresh($channel);
    }

    /**
     * @return array{fetched_at: string, count: int, keys: array<string, true>}|null
     */
    public function refresh(string $channel): ?array
    {
        $key = MiraklMcmOfferIndex::configKey($channel);
        $code = $key !== null ? (self::CONNECT_CODES[$key] ?? null) : null;
        if ($key === null || $code === null) {
            return null;
        }
        $this->lastError = null;

        $token = $this->token($key);
        if ($token === null) {
            $this->lastError = 'Mirakl Connect token missing';
            Cache::put(self::cacheKey($key).'.fail', 1, now()->addMinutes(2));

            return null;
        }

        @set_time_limit(180);
        $keys = [];
        $products = 0;
        $pageToken = null;
        $pages = 0;

        try {
            do {
                $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code='.$code;
                if ($pageToken) {
                    $url .= '&page_token='.urlencode($pageToken);
                }
                $response = Http::withoutVerifying()->withToken($token)->timeout(60)->get($url);
                if (! $response->successful()) {
                    $this->lastError = 'Connect HTTP '.$response->status().': '.substr((string) $response->body(), 0, 200);
                    Log::warning('MiraklConnectProductIndex: page failed', ['channel' => $key, 'status' => $response->status()]);
                    break;
                }
                foreach ($response->json('data') ?? [] as $product) {
                    if (! is_array($product)) {
                        continue;
                    }
                    $sku = trim((string) ($product['id'] ?? $product['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $products++;
                    foreach (ListingCountsEngine::skuLookupKeys($sku) as $lookup) {
                        $keys[$lookup] = true;
                    }
                }
                $pageToken = $response->json('next_page_token') ?: null;
                $pages++;
            } while ($pageToken && $pages < 50);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('MiraklConnectProductIndex: exception', ['channel' => $key, 'error' => $e->getMessage()]);
        }

        if ($keys === []) {
            Cache::put(self::cacheKey($key).'.fail', 1, now()->addMinutes(2));

            return null;
        }

        $index = [
            'fetched_at' => now()->toIso8601String(),
            'count' => $products,
            'keys' => $keys,
        ];
        Cache::put(self::cacheKey($key), $index, now()->addDays(7));
        Cache::forget(self::cacheKey($key).'.fail');

        return $index;
    }

    public static function contains(array $index, string $sku): bool
    {
        $keys = $index['keys'] ?? [];
        if ($keys === []) {
            return false;
        }
        foreach (ListingCountsEngine::skuLookupKeys($sku) as $lookup) {
            if (isset($keys[$lookup])) {
                return true;
            }
        }

        return false;
    }

    private function token(string $configKey): ?string
    {
        try {
            $cached = Cache::get('macy_access_token');
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable $e) {
            // continue
        }

        $id = config('services.'.$configKey.'.client_id') ?: config('services.macy.client_id');
        $secret = config('services.'.$configKey.'.client_secret') ?: config('services.macy.client_secret');
        if (! $id || ! $secret) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()->asForm()->timeout(30)->post('https://auth.mirakl.net/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $id,
                'client_secret' => $secret,
            ]);
            $token = $response->successful() ? (string) $response->json('access_token') : '';
            if ($token === '') {
                return null;
            }
            Cache::put('macy_access_token', $token, 3000);

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
