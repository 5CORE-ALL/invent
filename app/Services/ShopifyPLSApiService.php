<?php

namespace App\Services;

use App\Models\ShopifySku;
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

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($graphqlUrl, $payload);

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
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or variant_id / product_id) and bullet points are required.'];
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
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();

            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if ($shopifySku && $shopifySku->variant_id) {
                $variantResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get("https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json");

                if ($variantResponse->successful()) {
                    $productId = $variantResponse->json('variant.product_id');
                }
            }

            if (! $productId && ctype_digit($trim)) {
                $productProbe = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get("https://{$domain}/admin/api/2024-01/products/{$trim}.json");
                if ($productProbe->successful() && $productProbe->json('product.id')) {
                    $productId = (int) $productProbe->json('product.id');
                }
            }

            if (! $productId) {
                $found = $this->findProductBySkuViaGraphQL($domain, $token, $trim);
                if ($found) {
                    $productId = $found['product_id'];
                }
            }

            if (! $productId) {
                return ['success' => false, 'message' => 'PLS product not found for SKU, variant_id, or product_id.'];
            }

            $html = '<ul>';
            foreach (preg_split('/\r\n|\r|\n/', $bulletPoints) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
            }
            $html .= '</ul>';

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $getProduct = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get($productUrl);
            $title = $getProduct->json('product.title') ?? '';

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->put($productUrl, [
                'product' => [
                    'id' => $productId,
                    'title' => $title,
                    'body_html' => $html,
                ],
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS product bullets updated.'];
            }

            return ['success' => false, 'message' => 'PLS update failed: '.$response->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
