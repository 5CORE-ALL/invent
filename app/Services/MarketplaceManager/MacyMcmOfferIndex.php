<?php

namespace App\Services\MarketplaceManager;

use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Every offer on Macy's MCM (OF21 with inactive offers), keyed by shop_sku,
 * product_sku and UPC. Used as the source of truth for Macy's Listed / Missing L.
 */
class MacyMcmOfferIndex
{
    public const CACHE_KEY = 'macy.mcm.offer_index.v1';

    private const FRESH_MINUTES = 30;

    /**
     * @return array{fetched_at: string, offers: list<array{shop_sku: string, product_sku: string, upc: string, active: bool, state: string}>}|null
     */
    public function get(bool $refreshIfStale = true): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        $fresh = is_array($cached)
            && isset($cached['fetched_at'])
            && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($cached['fetched_at'])) < self::FRESH_MINUTES;
        if ($fresh || ! $refreshIfStale) {
            return is_array($cached) ? $cached : null;
        }

        return $this->refresh() ?? (is_array($cached) ? $cached : null);
    }

    /**
     * @return array{fetched_at: string, offers: list<array<string, mixed>>}|null  null when MCM could not be read completely
     */
    public function refresh(): ?array
    {
        $apiKey = trim((string) config('services.macy.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
        if ($apiKey === '' || $baseUrl === '') {
            return null;
        }

        $shopId = config('services.macy.shop_id');
        $offers = [];
        $offset = 0;
        $max = 100;

        try {
            do {
                $params = ['max' => $max, 'offset' => $offset, 'include_inactive_offers' => 'true'];
                if ($shopId !== null && $shopId !== '') {
                    $params['shop_id'] = (int) $shopId;
                }

                $response = null;
                for ($attempt = 1; $attempt <= 5; $attempt++) {
                    $response = Http::withoutVerifying()
                        ->withHeaders(['Authorization' => $apiKey, 'Accept' => 'application/json'])
                        ->timeout(60)
                        ->get($baseUrl.'/api/offers', $params);
                    if ($response->status() !== 429) {
                        break;
                    }
                    sleep(min(30, 3 * $attempt));
                }
                if (! $response || ! $response->successful()) {
                    Log::warning('MacyMcmOfferIndex: OF21 failed', ['status' => $response?->status(), 'offset' => $offset]);

                    return null;
                }

                $page = $response->json('offers') ?? [];
                $total = (int) ($response->json('total_count') ?? 0);
                foreach (is_array($page) ? $page : [] as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }
                    $upc = '';
                    foreach ($offer['product_references'] ?? [] as $ref) {
                        if (is_array($ref) && strcasecmp((string) ($ref['reference_type'] ?? ''), 'UPC') === 0) {
                            $upc = trim((string) ($ref['reference'] ?? ''));
                            break;
                        }
                    }
                    $offers[] = [
                        'shop_sku' => trim((string) ($offer['shop_sku'] ?? '')),
                        'product_sku' => trim((string) ($offer['product_sku'] ?? '')),
                        'upc' => $upc,
                        'active' => filter_var($offer['active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'state' => trim((string) ($offer['state_code'] ?? '')),
                    ];
                }

                $fetched = is_array($page) ? count($page) : 0;
                $offset += $max;
                $hasMore = $fetched >= $max && ($total === 0 || $offset < $total);
                if ($hasMore) {
                    usleep(500000);
                }
            } while ($hasMore);
        } catch (\Throwable $e) {
            Log::warning('MacyMcmOfferIndex: exception', ['error' => $e->getMessage()]);

            return null;
        }

        if ($offers === []) {
            return null;
        }

        $index = ['fetched_at' => now()->toIso8601String(), 'offers' => $offers];
        Cache::put(self::CACHE_KEY, $index, now()->addDays(7));

        return $index;
    }

    /**
     * Match keys of every offer (shop SKU and Mirakl product SKU) => shop_sku.
     *
     * @param  array{offers: list<array<string, mixed>>}  $index
     * @return array<string, string>
     */
    public static function skuKeyMap(array $index): array
    {
        $map = [];
        foreach ($index['offers'] ?? [] as $offer) {
            $shopSku = (string) ($offer['shop_sku'] ?? '');
            foreach ([$shopSku, (string) ($offer['product_sku'] ?? '')] as $sku) {
                if ($sku === '') {
                    continue;
                }
                foreach (ListingCountsEngine::skuLookupKeys($sku) as $key) {
                    $map[$key] ??= $shopSku !== '' ? $shopSku : $sku;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array{offers: list<array<string, mixed>>}  $index
     * @return array<string, string>  digits-only UPC => shop_sku
     */
    public static function upcMap(array $index): array
    {
        $map = [];
        foreach ($index['offers'] ?? [] as $offer) {
            $upc = ltrim(preg_replace('/\D/', '', (string) ($offer['upc'] ?? '')) ?? '', '0');
            if ($upc !== '') {
                $map[$upc] ??= (string) ($offer['shop_sku'] ?? '');
            }
        }

        return $map;
    }
}
