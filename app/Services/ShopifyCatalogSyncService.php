<?php

namespace App\Services;

use App\Models\ShopifyCatalogProduct;
use App\Models\ShopifyCatalogVariant;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShopifyCatalogSyncService
{
    /**
     * @param  'main'|'pls'  $store
     * @return array{products: int, variants: int, pruned_products?: int, pruned_variants?: int, completed?: bool}
     */
    public function syncCatalog(string $store): array
    {
        $store = $store === 'pls' ? 'pls' : 'main';
        [$domain, $token] = $this->credentials($store);
        if (! $domain || ! $token) {
            Log::warning('ShopifyCatalogSyncService: missing credentials', ['store' => $store]);

            return ['products' => 0, 'variants' => 0];
        }

        $domain = preg_replace('#^https?://#', '', (string) $domain);
        $domain = rtrim($domain, '/');

        $requestBase = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ]);

        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $requestBase = $requestBase->withoutVerifying();
        }

        $pageInfo = null;
        $hasMore = true;
        $productCount = 0;
        $variantCount = 0;
        $seenProductIds = [];
        $seenVariantIds = [];
        $completedFully = true;

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,title,handle,status,body_html,vendor,product_type,variants',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = null;
            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $response = $requestBase->timeout(120)->get(
                    "https://{$domain}/admin/api/2025-01/products.json",
                    $queryParams
                );
                if ($response->status() === 429) {
                    $wait = max(2, (int) ($response->header('Retry-After') ?: ($attempt * 2)));
                    Log::warning('ShopifyCatalogSyncService: rate limited, backing off', [
                        'store' => $store,
                        'attempt' => $attempt,
                        'wait_seconds' => $wait,
                    ]);
                    sleep($wait);
                    continue;
                }
                break;
            }

            if (! $response || ! $response->successful()) {
                Log::error('ShopifyCatalogSyncService: page failed', [
                    'store' => $store,
                    'status' => $response ? $response->status() : null,
                    'body' => $response ? substr($response->body(), 0, 2000) : null,
                ]);
                $completedFully = false;
                break;
            }

            $products = $response->json()['products'] ?? [];
            $now = now();

            foreach ($products as $product) {
                $pid = (int) ($product['id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                $seenProductIds[$pid] = true;

                $productRow = ShopifyCatalogProduct::updateOrCreate(
                    [
                        'store' => $store,
                        'shopify_id' => $pid,
                    ],
                    [
                        'title' => $product['title'] ?? null,
                        'handle' => $product['handle'] ?? null,
                        'status' => $product['status'] ?? null,
                        'body_html' => $product['body_html'] ?? null,
                        'vendor' => $product['vendor'] ?? null,
                        'product_type' => $product['product_type'] ?? null,
                        'synced_at' => $now,
                    ]
                );
                $productCount++;

                foreach ($product['variants'] ?? [] as $variant) {
                    $vid = (int) ($variant['id'] ?? 0);
                    if ($vid <= 0) {
                        continue;
                    }

                    $seenVariantIds[$vid] = true;

                    ShopifyCatalogVariant::updateOrCreate(
                        [
                            'store' => $store,
                            'shopify_variant_id' => $vid,
                        ],
                        [
                            'shopify_catalog_product_id' => $productRow->id,
                            'shopify_product_id' => $pid,
                            'sku' => isset($variant['sku']) ? trim((string) $variant['sku']) : null,
                            'variant_title' => $variant['title'] ?? null,
                            'price' => isset($variant['price']) ? (float) $variant['price'] : null,
                            'position' => isset($variant['position']) ? (int) $variant['position'] : null,
                            'inventory_quantity' => isset($variant['inventory_quantity']) ? (int) $variant['inventory_quantity'] : null,
                            'synced_at' => $now,
                        ]
                    );
                    $variantCount++;

                    // Shared live store: keep shopify_skus qty in sync for marketplace listings (one sync for all MPs).
                    $sku = isset($variant['sku']) ? trim((string) $variant['sku']) : '';
                    if ($store === 'main' && $sku !== '' && array_key_exists('inventory_quantity', $variant)) {
                        $qty = (int) $variant['inventory_quantity'];
                        ShopifySku::query()->updateOrCreate(
                            ['sku' => $sku],
                            [
                                'variant_id' => (string) $vid,
                                'available_to_sell' => $qty,
                                'inv' => $qty,
                                'on_hand' => $qty,
                                'product_title' => $product['title'] ?? null,
                                'variant_title' => $variant['title'] ?? null,
                                'price' => isset($variant['price']) ? (float) $variant['price'] : null,
                                'updated_at' => $now,
                            ]
                        );
                    }
                }
            }

            $pageInfo = $this->nextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(700000); // stay under Shopify REST 2 req/s bucket
            }
        }

        $prunedProducts = 0;
        $prunedVariants = 0;
        if ($completedFully && $seenProductIds !== []) {
            $staleProductIds = ShopifyCatalogProduct::query()
                ->where('store', $store)
                ->whereNotIn('shopify_id', array_keys($seenProductIds))
                ->pluck('id');
            if ($staleProductIds->isNotEmpty()) {
                $prunedVariants += ShopifyCatalogVariant::query()
                    ->whereIn('shopify_catalog_product_id', $staleProductIds)
                    ->delete();
                $prunedProducts = ShopifyCatalogProduct::query()
                    ->whereIn('id', $staleProductIds)
                    ->delete();
            }
            $prunedVariants += ShopifyCatalogVariant::query()
                ->where('store', $store)
                ->whereNotIn('shopify_variant_id', array_keys($seenVariantIds))
                ->delete();
        }

        return [
            'products' => $productCount,
            'variants' => $variantCount,
            'pruned_products' => $prunedProducts,
            'pruned_variants' => $prunedVariants,
            'completed' => $completedFully,
        ];
    }

    /**
     * Live Shopify inventory keyed by normalized SKU (PLS or main). Cached so
     * /map-issues and /pls-pricing do not page the Admin API on every request.
     *
     * @return array<string, int>
     */
    public function cachedInventoryByNormalizedSku(string $store, int $ttlSeconds = 900): array
    {
        $store = $store === 'pls' ? 'pls' : 'main';
        $key = 'mm.shopify.'.$store.'.inv.by_norm_sku.v1';

        try {
            $cached = Cache::get($key);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // fall through to live pull
        }

        $map = $this->pullInventoryByNormalizedSku($store);
        if ($map !== []) {
            try {
                Cache::put($key, $map, now()->addSeconds(max(60, $ttlSeconds)));
            } catch (\Throwable $e) {
                // ignore cache write
            }
        }

        return $map;
    }

    /**
     * Page Admin API products and return normalized SKU => available qty.
     * Also writes inventory_quantity back onto shopify_catalog_variants when the variant exists.
     *
     * @return array<string, int>
     */
    public function pullInventoryByNormalizedSku(string $store): array
    {
        $store = $store === 'pls' ? 'pls' : 'main';
        [$domain, $token] = $this->credentials($store);
        if (! $domain || ! $token) {
            return [];
        }

        $domain = preg_replace('#^https?://#', '', (string) $domain);
        $domain = rtrim($domain, '/');

        $requestBase = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ]);
        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $requestBase = $requestBase->withoutVerifying();
        }

        $pageInfo = null;
        $hasMore = true;
        $qtyByNorm = [];
        $qtyByVariantId = [];

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,variants',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = null;
            for ($attempt = 1; $attempt <= 6; $attempt++) {
                $response = $requestBase->timeout(90)->get(
                    "https://{$domain}/admin/api/2025-01/products.json",
                    $queryParams
                );
                if ($response->status() === 429) {
                    sleep(max(2, (int) ($response->header('Retry-After') ?: ($attempt * 2))));
                    continue;
                }
                break;
            }

            if (! $response || ! $response->successful()) {
                Log::warning('ShopifyCatalogSyncService: inventory page failed', [
                    'store' => $store,
                    'status' => $response ? $response->status() : null,
                ]);
                break;
            }

            foreach ($response->json()['products'] ?? [] as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    $vid = (int) ($variant['id'] ?? 0);
                    $qty = (int) ($variant['inventory_quantity'] ?? 0);
                    if ($vid > 0) {
                        $qtyByVariantId[$vid] = $qty;
                    }
                    $sku = trim((string) ($variant['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                    if ($norm === '') {
                        continue;
                    }
                    $qtyByNorm[$norm] = ($qtyByNorm[$norm] ?? 0) + $qty;
                }
            }

            $pageInfo = $this->nextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(250000);
            }
        }

        if ($qtyByVariantId !== [] && Schema::hasTable('shopify_catalog_variants')) {
            $now = now();
            foreach (array_chunk($qtyByVariantId, 200, true) as $chunk) {
                foreach ($chunk as $vid => $qty) {
                    DB::table('shopify_catalog_variants')
                        ->where('store', $store)
                        ->where('shopify_variant_id', $vid)
                        ->update([
                            'inventory_quantity' => $qty,
                            'updated_at' => $now,
                            'synced_at' => $now,
                        ]);
                }
            }
        }

        return $qtyByNorm;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function credentials(string $store): array
    {
        if ($store === 'pls') {
            $domain = config('services.prolightsounds.domain')
                ?? config('services.prolightsounds.store_url');
            $token = app(ShopifyPlsTokenService::class)->getAccessToken();

            return [$domain, $token];
        }

        $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');

        return [$domain, $token];
    }

    private function nextPageInfo(\Illuminate\Http\Client\Response $response): ?string
    {
        if ($response->hasHeader('Link') && str_contains($response->header('Link'), 'rel="next"')) {
            $links = explode(',', $response->header('Link'));
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    preg_match('/<(.*)>; rel="next"/', $link, $matches);
                    if (! empty($matches[1])) {
                        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);

                        return $query['page_info'] ?? null;
                    }
                }
            }
        }

        return null;
    }
}
