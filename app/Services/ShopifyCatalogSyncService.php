<?php

namespace App\Services;

use App\Models\ShopifyCatalogProduct;
use App\Models\ShopifyCatalogVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyCatalogSyncService
{
    /**
     * @param  'main'|'pls'  $store
     * @return array{products: int, variants: int}
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

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,title,handle,status,body_html,vendor,product_type,variants',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = $requestBase->timeout(120)->retry(2, 500)->get(
                "https://{$domain}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                Log::error('ShopifyCatalogSyncService: page failed', [
                    'store' => $store,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 2000),
                ]);
                break;
            }

            $products = $response->json()['products'] ?? [];
            $now = now();

            foreach ($products as $product) {
                $pid = (int) ($product['id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

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
                }
            }

            $pageInfo = $this->nextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(500000);
            }
        }

        return ['products' => $productCount, 'variants' => $variantCount];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function credentials(string $store): array
    {
        if ($store === 'pls') {
            $domain = config('services.prolightsounds.domain')
                ?? config('services.prolightsounds.store_url');
            $token = config('services.prolightsounds.password')
                ?? config('services.prolightsounds.access_token');

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
