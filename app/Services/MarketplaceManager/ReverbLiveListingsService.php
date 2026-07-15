<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\PushLinkedSkuInventoryFromShopify;
use App\Models\ShopifySku;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live inventory helpers for Reverb listings UI.
 *
 * Listings pages must paginate Shopify (or metric) first — never load all Reverb listings
 * into a single request. Live Reverb qty is fetched only for the current page's listing IDs.
 */
final class ReverbLiveListingsService
{
    public const CACHE_KEY = 'mm.reverb.live_listings.v2';

    public const CACHE_TTL_SECONDS = 300;

    /**
     * Full catalog (expensive). Use only from background jobs / explicit refresh — not page renders.
     *
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int, title: ?string, price: ?float}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            try {
                Cache::forget(self::CACHE_KEY);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return $this->fetchFromApi();
            });
        } catch (\Throwable $e) {
            Log::warning('ReverbLiveListingsService: cache unavailable, fetching uncached', [
                'error' => $e->getMessage(),
            ]);

            return $this->fetchFromApi();
        }
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            return is_array($cached) ? $cached : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Live Reverb details for a page of listing IDs only (parallel GETs).
     *
     * @param  array<int, string>  $listingIds
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int, title: ?string, price: ?float}>
     */
    public function liveDetailsByListingIds(array $listingIds): array
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $listingIds
        ), static fn ($id) => $id !== '')));

        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($ids, 25) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $token) {
                foreach ($chunk as $id) {
                    $pool->as((string) $id)
                        ->withoutVerifying()
                        ->withHeaders([
                            'Authorization' => 'Bearer '.$token,
                            'Accept' => 'application/hal+json',
                            'Accept-Version' => '3.0',
                        ])
                        ->timeout(25)
                        ->get('https://api.reverb.com/api/listings/'.$id);
                }
            });

            foreach ($chunk as $id) {
                $response = $responses[(string) $id] ?? null;
                if (! $response || ! method_exists($response, 'successful') || ! $response->successful()) {
                    continue;
                }
                $item = $response->json() ?? [];
                $parsed = $this->parseListingItem($item);
                if ($parsed !== null) {
                    $out[$parsed['product_id']] = $parsed;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, int> UPPER(sku) => live Shopify inventory_quantity
     */
    public function liveShopifyQtyBySkus(array $skus): array
    {
        $store = preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        if ($store === '' || $token === '' || $skus === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($skus, 15) as $chunk) {
            foreach (MarketplaceListingStockResolver::liveShopifyQtyBySkuListGraphqlPublic($store, $token, $chunk) as $upper => $qty) {
                $map[$upper] = $qty;
            }
        }

        $missing = [];
        foreach ($skus as $sku) {
            $upper = strtoupper($sku);
            if (! array_key_exists($upper, $map)) {
                $missing[] = $sku;
            }
        }
        foreach ($missing as $sku) {
            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(20)->get("https://{$store}/admin/api/2025-01/variants.json", [
                    'sku' => $sku,
                    'limit' => 1,
                ]);
                if ($response->successful()) {
                    $variants = $response->json('variants') ?? [];
                    if (is_array($variants) && $variants !== []) {
                        $map[strtoupper($sku)] = (int) ($variants[0]['inventory_quantity'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                // continue
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{sku: string, inventory: int, state?: string, product_id?: string}>  $liveRows
     * @param  array<string, int>  $liveShopifyByUpper
     */
    public function queueSyncForMismatches(array $liveRows, array $liveShopifyByUpper): int
    {
        $skus = [];
        foreach ($liveRows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $state = (string) ($row['state'] ?? '');
            if (! MarketplaceLiveInventoryRules::reverbMayUpdateInventory($state)) {
                continue;
            }
            $upper = strtoupper($sku);
            if (! array_key_exists($upper, $liveShopifyByUpper)) {
                continue;
            }
            $shopifyQty = (int) $liveShopifyByUpper[$upper];
            $reverbQty = (int) ($row['inventory'] ?? 0);
            $want = MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyQty);
            if ($want === $reverbQty) {
                continue;
            }
            $skus[] = $sku;
        }

        $skus = array_values(array_unique($skus));
        if ($skus === []) {
            return 0;
        }

        // One batched job (not 1 job per SKU) — keeps marketplace-manager free for full syncs.
        try {
            PushLinkedSkuInventoryFromShopify::dispatch($skus, null, null);

            return count($skus);
        } catch (\Throwable $e) {
            Log::warning('ReverbLiveListingsService: could not queue inventory sync (cache lock / storage)', [
                'sku_count' => count($skus),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return array<string, true>
     */
    public function shopifyCatalogKeys(): array
    {
        $keys = [];
        if (! Schema::hasTable('shopify_skus')) {
            return $keys;
        }
        ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$keys) {
                foreach ($rows as $row) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($norm !== '') {
                        $keys[$norm] = true;
                    }
                }
            });

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{product_id: string, sku: string, state: string, inventory: int, title: ?string, price: ?float}|null
     */
    protected function parseListingItem(array $item): ?array
    {
        $productId = trim((string) ($item['id'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? $item['manufacturer_sku'] ?? ''));
        $stateRaw = $item['state'] ?? null;
        $state = is_array($stateRaw)
            ? strtolower(trim((string) ($stateRaw['slug'] ?? $stateRaw['description'] ?? '')))
            : strtolower(trim((string) $stateRaw));
        $inv = (int) ($item['inventory'] ?? $item['quantity'] ?? 0);
        $price = null;
        if (isset($item['price']['amount'])) {
            $price = (float) $item['price']['amount'];
        } elseif (isset($item['price']) && is_numeric($item['price'])) {
            $price = (float) $item['price'];
        }
        if ($productId === '') {
            return null;
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $state,
            'inventory' => $inv,
            'title' => isset($item['title']) ? (string) $item['title'] : null,
            'price' => $price,
        ];
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int, title: ?string, price: ?float}>
     */
    protected function fetchFromApi(): array
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            return [];
        }

        $out = [];
        $url = 'https://api.reverb.com/api/my/listings?state=all&per_page=100';
        $guard = 0;

        while ($url && $guard < 200) {
            $guard++;
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->timeout(60)
                ->get($url);

            if ($response->status() === 429) {
                sleep(2);
                continue;
            }
            if (! $response->successful()) {
                break;
            }

            $data = $response->json() ?? [];
            $listings = $data['listings'] ?? $data['_embedded']['listings'] ?? [];
            if (! is_array($listings) || $listings === []) {
                break;
            }

            foreach ($listings as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $parsed = $this->parseListingItem($item);
                if ($parsed !== null && $parsed['sku'] !== '') {
                    $out[] = $parsed;
                }
            }

            $next = $data['_links']['next']['href'] ?? null;
            $url = is_string($next) && $next !== '' ? $next : null;
            if ($url) {
                usleep(150000);
            }
        }

        return $out;
    }
}
