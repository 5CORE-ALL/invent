<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Services\Support\ShopifyBulletPointsFormatter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopify ProLightSounds (PLS) Store API Service
 * Pushes Title 100 to 5core-wholesale.myshopify.com
 */
class ShopifyPLSApiService
{
    /**
     * Update product title for the given SKU on PLS Shopify store.
     * Tries shopify_skus mapping first; on 404, falls back to GraphQL SKU search on PLS store.
     *
     * @param  string  $sku
     * @param  string  $title
     * @return bool
     */
    public function updateTitle(string $sku, string $title): bool
    {
        Log::info('🚀 Push to ShopifyPLS - Started', ['sku' => $sku]);

        try {
            $domain = config('services.prolightsounds.domain') ?? config('services.prolightsounds.store_url');
            $token = config('services.prolightsounds.password');

            if (! $domain || ! $token) {
                Log::warning('ShopifyPLS credentials not configured', ['sku' => $sku]);

                return false;
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $productId = null;
            $variantId = null;

            // 1. Try shopify_skus mapping first
            $shopifySku = ShopifySku::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();

            if ($shopifySku && $shopifySku->variant_id) {
                sleep(3); // Rate limit
                $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
                Log::info('ShopifyPLS: trying shopify_skus variant', ['variant_id' => $shopifySku->variant_id, 'url' => $variantUrl]);

                $variantResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get($variantUrl);

                if ($variantResponse->successful()) {
                    $variantData = $variantResponse->json();
                    $productId = $variantData['variant']['product_id'] ?? null;
                    $variantId = $shopifySku->variant_id;
                } else {
                    Log::warning('ShopifyPLS: shopify_skus variant returned 404, falling back to GraphQL SKU search', [
                        'sku' => $sku,
                        'variant_id' => $shopifySku->variant_id,
                        'status' => $variantResponse->status(),
                    ]);
                }
            } else {
                Log::info('ShopifyPLS: SKU not in shopify_skus, using GraphQL SKU search', ['sku' => $sku]);
            }

            // 2. Fallback: search PLS store by SKU via GraphQL
            if (! $productId) {
                $found = $this->findProductBySkuViaGraphQL($domain, $token, $sku);
                if ($found) {
                    $productId = $found['product_id'];
                    $variantId = $found['variant_id'];
                }
            }

            if (! $productId) {
                Log::error('❌ Push to ShopifyPLS - Failed', [
                    'sku' => $sku,
                    'error' => 'SKU not found in shopify_skus and not found on PLS store via GraphQL. Ensure product exists on 5core-wholesale.myshopify.com.',
                ]);

                return false;
            }

            sleep(3);

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->put($productUrl, [
                'product' => [
                    'id' => $productId,
                    'title' => $title,
                ],
            ]);

            if ($response->successful()) {
                Log::info('✅ Push to ShopifyPLS - Success', ['sku' => $sku, 'product_id' => $productId]);

                return true;
            }

            $err = "Product update failed: {$response->status()} - {$response->body()}";
            Log::error('❌ Push to ShopifyPLS - Failed', ['sku' => $sku, 'product_id' => $productId, 'error' => $err]);

            return false;
        } catch (\Throwable $e) {
            Log::error('❌ Push to ShopifyPLS - Failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Find product/variant on PLS store by SKU via GraphQL productVariants query.
     *
     * @return array{product_id: int, variant_id: string}|null
     */
    private function findProductBySkuViaGraphQL(string $domain, string $token, string $sku): ?array
    {
        $graphqlUrl = "https://{$domain}/admin/api/2024-01/graphql.json";

        $skuValues = array_unique(array_filter([
            $sku,
            strtoupper($sku),
            strtolower($sku),
            str_replace('+', ' ', $sku),
            str_replace(' ', '', $sku),
        ]));

        foreach ($skuValues as $q) {
            foreach (['sku:"' . str_replace('"', '\\"', $q) . '"', 'sku:' . $q] as $queryStr) {
                $payload = [
                    'query' => 'query ($query: String!) {
                        productVariants(first: 1, query: $query) {
                            edges {
                                node {
                                    id
                                    product { id }
                                }
                            }
                        }
                    }',
                    'variables' => ['query' => $queryStr],
                ];

                Log::info('ShopifyPLS: GraphQL SKU search', ['query' => $queryStr, 'url' => $graphqlUrl]);

                $response = $this->retryOnRateLimit(function () use ($token, $graphqlUrl, $payload) {
                    return Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->post($graphqlUrl, $payload);
                });

                if (! $response->successful()) {
                    Log::warning('ShopifyPLS: GraphQL request failed', ['status' => $response->status(), 'query' => $queryStr]);
                    continue;
                }

                $data = $response->json();
                $edges = $data['data']['productVariants']['edges'] ?? [];

                if (empty($edges)) {
                    continue;
                }

                $node = $edges[0]['node'];
                $variantGid = $node['id'] ?? null;
                $productGid = $node['product']['id'] ?? null;

                if (! $variantGid || ! $productGid) {
                    continue;
                }

                $productId = (int) preg_replace('/^gid:\/\/shopify\/Product\//', '', $productGid);
                $variantId = preg_replace('/^gid:\/\/shopify\/ProductVariant\//', '', $variantGid);

                if ($productId > 0) {
                    Log::info('ShopifyPLS: found product via GraphQL', ['sku' => $sku, 'product_id' => $productId, 'variant_id' => $variantId]);

                    return ['product_id' => $productId, 'variant_id' => $variantId];
                }
            }
        }

        Log::warning('ShopifyPLS: SKU not found on PLS store via GraphQL', ['sku' => $sku, 'tried_values' => $skuValues]);

        return null;
    }

    /**
     * Phase 1: Overwrite product `body_html` with Key Features bullets only (see ShopifyBulletPointsFormatter).
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        if (trim($identifier) === '' || trim($bulletPoints) === '') {
            return ['success' => false, 'message' => 'SKU (or variant_id / product_id) and bullet points are required.'];
        }

        if (! ShopifyBulletPointsFormatter::hasAnyBulletLine($bulletPoints)) {
            return ['success' => false, 'message' => 'At least one bullet line is required.'];
        }

        $formattedHtml = ShopifyBulletPointsFormatter::formatBodyHtml($bulletPoints);

        try {
            $domain = config('services.prolightsounds.domain') ?? config('services.prolightsounds.store_url');
            $token = config('services.prolightsounds.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify PLS credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $productId = null;
            $hadHttp = false;
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();

            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if ($shopifySku && $shopifySku->variant_id) {
                $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
                $variantResponse = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                    return Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->get($variantUrl);
                });
                $hadHttp = true;

                if ($variantResponse->successful()) {
                    $productId = $variantResponse->json('variant.product_id');
                }
            }

            if (! $productId && ctype_digit($trim)) {
                if ($hadHttp) {
                    usleep(500000);
                }
                $probeUrl = "https://{$domain}/admin/api/2024-01/products/{$trim}.json";
                $productProbe = $this->retryOnRateLimit(function () use ($token, $probeUrl) {
                    return Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->get($probeUrl);
                });
                $hadHttp = true;
                if ($productProbe->successful() && $productProbe->json('product.id')) {
                    $productId = (int) $productProbe->json('product.id');
                    $probeTitle = (string) ($productProbe->json('product.title') ?? '');
                    if ($productId && $probeTitle !== '') {
                        Cache::put('shopify_pls_product_title_'.$productId, $probeTitle, 3600);
                    }
                }
            }

            if (! $productId) {
                if ($hadHttp) {
                    usleep(500000);
                }
                $found = $this->findProductBySkuViaGraphQL($domain, $token, $trim);
                if ($found) {
                    $productId = $found['product_id'];
                }
            }

            if (! $productId) {
                return ['success' => false, 'message' => 'PLS product not found for SKU, variant_id, or product_id.'];
            }

            usleep(500000);

            $title = $this->getProductTitle($domain, $token, $productId);
            if ($title === '') {
                return ['success' => false, 'message' => 'Could not load product title (Shopify PLS rate limit or API error).'];
            }

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $formattedHtml) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'body_html' => $formattedHtml,
                    ],
                ]);
            });

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS product bullets updated.'];
            }

            $errBody = $response->status() === 429
                ? 'PLS update rate limited after retries.'
                : $response->body();

            return ['success' => false, 'message' => 'PLS update failed: '.$errBody];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Description Master: bullets (Key Features) + long description in body_html.
     *
     * @return array{success: bool, message: string}
     */
    public function updateProductDescriptionWithBullets(string $identifier, string $bulletPointsPlain, string $descriptionPlain): array
    {
        $combined = ShopifyBulletPointsFormatter::combineBulletPointsAndDescription($bulletPointsPlain, $descriptionPlain);
        if (trim($combined) === '') {
            return ['success' => false, 'message' => 'Nothing to push: add bullets (Bullet Points Master) and/or description text.'];
        }

        try {
            $domain = config('services.prolightsounds.domain') ?? config('services.prolightsounds.store_url');
            $token = config('services.prolightsounds.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify PLS credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $productId = null;
            $hadHttp = false;
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();

            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if ($shopifySku && $shopifySku->variant_id) {
                $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
                $variantResponse = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                    return Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->get($variantUrl);
                });
                $hadHttp = true;

                if ($variantResponse->successful()) {
                    $productId = $variantResponse->json('variant.product_id');
                }
            }

            if (! $productId && ctype_digit($trim)) {
                if ($hadHttp) {
                    usleep(500000);
                }
                $probeUrl = "https://{$domain}/admin/api/2024-01/products/{$trim}.json";
                $productProbe = $this->retryOnRateLimit(function () use ($token, $probeUrl) {
                    return Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->get($probeUrl);
                });
                $hadHttp = true;
                if ($productProbe->successful() && $productProbe->json('product.id')) {
                    $productId = (int) $productProbe->json('product.id');
                    $probeTitle = (string) ($productProbe->json('product.title') ?? '');
                    if ($productId && $probeTitle !== '') {
                        Cache::put('shopify_pls_product_title_'.$productId, $probeTitle, 3600);
                    }
                }
            }

            if (! $productId) {
                if ($hadHttp) {
                    usleep(500000);
                }
                $found = $this->findProductBySkuViaGraphQL($domain, $token, $trim);
                if ($found) {
                    $productId = $found['product_id'];
                }
            }

            if (! $productId) {
                return ['success' => false, 'message' => 'PLS product not found for SKU, variant_id, or product_id.'];
            }

            usleep(500000);

            $title = $this->getProductTitle($domain, $token, $productId);
            if ($title === '') {
                return ['success' => false, 'message' => 'Could not load product title (Shopify PLS rate limit or API error).'];
            }

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $combined) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'body_html' => $combined,
                    ],
                ]);
            });

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS product description (bullets + copy) updated.'];
            }

            $errBody = $response->status() === 429
                ? 'PLS update rate limited after retries.'
                : $response->body();

            return ['success' => false, 'message' => 'PLS update failed: '.$errBody];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Retry HTTP calls when Shopify returns 429 Too Many Requests.
     */
    private function retryOnRateLimit(callable $call, int $maxRetries = 3): Response
    {
        $attempt = 0;
        $response = null;

        while ($attempt < $maxRetries) {
            $response = $call();

            if ($response->status() !== 429) {
                return $response;
            }

            $attempt++;
            $wait = (int) (pow(2, $attempt) * 1000000);
            usleep($wait);
        }

        return $response;
    }

    /**
     * Cached product title for PLS store to reduce sequential API calls.
     */
    private function getProductTitle(string $domain, string $token, int|string $productId): string
    {
        $cacheKey = 'shopify_pls_product_title_'.$productId;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
        $getProduct = $this->retryOnRateLimit(function () use ($token, $productUrl) {
            return Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($productUrl);
        });

        if (! $getProduct->successful()) {
            if ($getProduct->status() === 429) {
                Log::warning('Shopify PLS: product title fetch rate limited after retries', [
                    'product_id' => $productId,
                ]);
            }

            return '';
        }

        $title = (string) ($getProduct->json('product.title') ?? '');
        if ($title !== '') {
            Cache::put($cacheKey, $title, 3600);
        }

        return $title;
    }
}
