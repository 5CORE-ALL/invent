<?php

namespace App\Services;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\Support\Concerns\ShopifyAdminRateLimitRetry;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\ShopifyBulletPointsFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Shopify ProLightSounds (PLS) Store API Service
 * Pushes Title 100 to 5core-wholesale.myshopify.com
 */
class ShopifyPLSApiService
{
    use ShopifyAdminRateLimitRetry;

    /**
     * Update product title for the given SKU on PLS Shopify store.
     * Tries shopify_skus mapping first; on 404, falls back to GraphQL SKU search on PLS store.
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
                $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
                Log::info('ShopifyPLS: trying shopify_skus variant', ['variant_id' => $shopifySku->variant_id, 'url' => $variantUrl]);

                $variantResponse = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                    return Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                    ])->timeout(60)->connectTimeout(25)->get($variantUrl);
                });

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

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                    ],
                ]);
            });

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
     * Find product/variant on PLS store by SKU.
     * Tries GraphQL first; on HTTP 403 (token lacks GraphQL scope) immediately
     * switches to a REST product-scan fallback so the push still succeeds.
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
            foreach (['sku:"'.str_replace('"', '\\"', $q).'"', 'sku:'.$q] as $queryStr) {
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
                    ])->timeout(60)->connectTimeout(25)->post($graphqlUrl, $payload);
                });

                // 403 = token lacks GraphQL scope — switch to REST scan immediately
                if ($response->status() === 403) {
                    Log::info('ShopifyPLS: GraphQL returned 403 (token lacks scope), falling back to REST product scan', ['sku' => $sku]);

                    return $this->findProductBySkuViaREST($domain, $token, $sku);
                }

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
     * Fallback: page through all products on the PLS store via REST and find the
     * variant whose SKU matches.  Results are cached for 1 hour per SKU so the
     * full scan only runs once.
     *
     * @return array{product_id: int, variant_id: string}|null
     */
    private function findProductBySkuViaREST(string $domain, string $token, string $sku): ?array
    {
        $trimSku   = trim($sku);
        $lowerSku  = strtolower($trimSku);
        $cacheKey  = 'shopify_pls_sku_rest_' . md5($domain . '|' . $lowerSku);

        /** @var array{product_id:int,variant_id:string}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info('ShopifyPLS: REST lookup served from cache', ['sku' => $sku]);

            return $cached;
        }

        // Candidate SKU spellings to match against (case-insensitive compare below)
        $candidates = array_unique(array_filter([
            $trimSku,
            strtoupper($trimSku),
            $lowerSku,
            str_replace(' ', '', $trimSku),
        ]));

        $baseUrl  = "https://{$domain}/admin/api/2024-01/products.json";
        $pageInfo = null;
        $maxPages = 25; // scan up to 6 250 = 6 250 products maximum

        for ($page = 0; $page < $maxPages; $page++) {
            // cursor-based pagination: page_info MUST be the only filter when set
            $params = $pageInfo
                ? ['page_info' => $pageInfo, 'limit' => 250]
                : ['fields' => 'id,variants', 'limit' => 250];

            $response = $this->retryOnRateLimit(function () use ($token, $baseUrl, $params) {
                return Http::withHeaders(['X-Shopify-Access-Token' => $token])
                    ->timeout(30)->connectTimeout(15)
                    ->get($baseUrl, $params);
            }, 4, 1.0);

            if (! $response->successful()) {
                Log::warning('ShopifyPLS: REST product scan request failed', [
                    'sku'    => $sku,
                    'page'   => $page,
                    'status' => $response->status(),
                ]);
                break;
            }

            $products = $response->json()['products'] ?? [];

            foreach ($products as $product) {
                foreach (($product['variants'] ?? []) as $variant) {
                    $vSku = trim((string) ($variant['sku'] ?? ''));
                    if ($vSku === '' || strtolower($vSku) !== $lowerSku) {
                        continue;
                    }

                    $result = [
                        'product_id' => (int) $product['id'],
                        'variant_id' => (string) $variant['id'],
                    ];

                    Log::info('ShopifyPLS: found product via REST scan', ['sku' => $sku, 'page' => $page, ...$result]);

                    // Cache for 1 hour to avoid repeated full scans
                    Cache::put($cacheKey, $result, now()->addHour());

                    return $result;
                }
            }

            // Stop if this was the last page
            if (count($products) < 250) {
                break;
            }

            // Follow Shopify cursor pagination via Link header
            $link     = $response->header('Link') ?? '';
            $pageInfo = null;
            if (preg_match('/<([^>]+)>;\s*rel="next"/', $link, $m)) {
                parse_str((string) parse_url($m[1], PHP_URL_QUERY), $qp);
                $pageInfo = $qp['page_info'] ?? null;
            }

            if (! $pageInfo) {
                break;
            }
        }

        Log::warning('ShopifyPLS: SKU not found via REST scan', ['sku' => $sku]);

        return null;
    }

    /**
     * Bullet Points Master: replace `body_html` with unified layout (same as Main store).
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
                    ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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
                    ])->timeout(60)->connectTimeout(25)->get($probeUrl);
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

            $descriptionPlain = $this->resolveProductMasterLongDescription($trim);
            $featureGrid = $this->loadShopifyFeatureGridForSku($trim);
            $formattedHtml = DescriptionWithImagesFormatter::buildHtmlWithImages(
                $descriptionPlain,
                $trim,
                $trim,
                $title,
                12,
                [],
                $bulletPoints,
                $featureGrid,
                true
            )['html'];

            Log::info('Shopify PLS updateBulletPoints unified layout', [
                'sku' => $trim,
                'product_id' => $productId,
                'description_from_pm_chars' => strlen($descriptionPlain),
                'feature_box_count' => count($featureGrid),
            ]);

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $formattedHtml) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'body_html' => $formattedHtml,
                    ],
                ]);
            });

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS product description updated (unified layout).'];
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
     * HTML Editor: set PLS product `body_html` to raw HTML (full replace).
     *
     * @return array{success: bool, message: string}
     */
    public function updateBodyHtml(string $identifier, string $bodyHtml): array
    {
        $bodyHtml = trim((string) $bodyHtml);
        if ($bodyHtml === '') {
            return ['success' => false, 'message' => 'HTML body is empty.'];
        }
        if (strlen($bodyHtml) > 500000) {
            return ['success' => false, 'message' => 'HTML exceeds maximum length (500,000 characters).'];
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
                    ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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
                    ])->timeout(60)->connectTimeout(25)->get($probeUrl);
                });
                $hadHttp = true;
                if ($productProbe->successful() && $productProbe->json('product.id')) {
                    $productId = (int) $productProbe->json('product.id');
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

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $getProduct = $this->retryOnRateLimit(function () use ($token, $productUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($productUrl);
            });

            if (! $getProduct->successful()) {
                return ['success' => false, 'message' => 'Could not load product: '.$getProduct->body()];
            }

            $title = (string) ($getProduct->json('product.title') ?? '');
            if ($title === '') {
                return ['success' => false, 'message' => 'Product title missing from Shopify PLS.'];
            }

            Log::info('Shopify PLS updateBodyHtml', ['sku' => $trim, 'product_id' => $productId, 'len' => strlen($bodyHtml)]);

            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $bodyHtml) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'body_html' => $bodyHtml,
                    ],
                ]);
            });

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS body HTML updated.'];
            }

            return ['success' => false, 'message' => 'PLS update failed: '.$response->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Description Master: sets `body_html` to unified layout (About Item → Product Description → Features → Images).
     *
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        if (trim($identifier) === '' || trim($description) === '') {
            return ['success' => false, 'message' => 'SKU (or variant_id / product_id) and description are required.'];
        }

        $descriptionPlain = trim($description);
        if ($descriptionPlain === '') {
            return ['success' => false, 'message' => 'Description is empty.'];
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
                    ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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
                    ])->timeout(60)->connectTimeout(25)->get($probeUrl);
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

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $getProduct = $this->retryOnRateLimit(function () use ($token, $productUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($productUrl);
            });

            if (! $getProduct->successful()) {
                $msg = $getProduct->status() === 429
                    ? 'Product fetch rate limited after retries.'
                    : 'Could not load product: '.$getProduct->body();

                return ['success' => false, 'message' => $msg];
            }

            $currentBody = (string) ($getProduct->json('product.body_html') ?? '');
            $title = (string) ($getProduct->json('product.title') ?? '');
            if ($title === '') {
                return ['success' => false, 'message' => 'Product title missing from Shopify PLS.'];
            }

            $aboutBullets = $this->loadPlsMetricsBulletPoints($trim);
            $featureGrid = $this->loadShopifyFeatureGridForSku($trim);

            $descriptionHtml = DescriptionWithImagesFormatter::buildHtmlWithImages(
                $descriptionPlain,
                $trim,
                $trim,
                $title,
                12,
                $imageUrls,
                $aboutBullets,
                $featureGrid,
                true
            )['html'];

            $combined = $descriptionHtml;

            Log::info('Shopify PLS updateDescription: body_html set to unified rich layout', [
                'sku' => $trim,
                'product_id' => $productId,
                'about_bullets_chars' => strlen($aboutBullets),
                'feature_box_count' => count($featureGrid),
                'previous_body_html_chars' => strlen($currentBody),
                'new_body_html_chars' => strlen($combined),
            ]);

            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $combined) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'body_html' => $combined,
                    ],
                ]);
            });

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS product description updated (unified layout).'];
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
     * Same unified body layout as Main store (no legacy Key Features list).
     *
     * @return array{success: bool, message: string}
     */
    public function updateProductDescriptionWithBullets(string $identifier, string $bulletPointsPlain, string $descriptionPlain): array
    {
        $bulletTrim = trim($bulletPointsPlain);
        $descTrim = trim($descriptionPlain);
        if ($bulletTrim === '' && $descTrim === '') {
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
                    ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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
                    ])->timeout(60)->connectTimeout(25)->get($probeUrl);
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

            $featureGrid = $this->loadShopifyFeatureGridForSku($trim);
            $combined = DescriptionWithImagesFormatter::buildHtmlWithImages(
                $descTrim,
                $trim,
                $trim,
                $title,
                12,
                [],
                $bulletTrim,
                $featureGrid,
                true
            )['html'];

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $response = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $combined) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'body_html' => $combined,
                    ],
                ]);
            });

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Shopify PLS product description (unified layout) updated.'];
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
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls, string $mode = 'replace'): array
    {
        // Preserve caller's order — only trim whitespace, do NOT sort
        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 20);

        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU / identifier is required.'];
        }

        if ($urls === [] && $mode !== 'replace') {
            return ['success' => true, 'message' => 'No images to add; skipped.'];
        }

        try {
            $domain = config('services.prolightsounds.domain') ?? config('services.prolightsounds.store_url');
            $token  = config('services.prolightsounds.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify PLS credentials not configured.'];
            }

            $domain = rtrim(preg_replace('#^https?://#', '', $domain), '/');
            $trim   = trim($identifier);

            // ── Resolve product ID ─────────────────────────────────────────
            $productId = null;
            $hadHttp   = false;
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();

            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if ($shopifySku && $shopifySku->variant_id) {
                $variantUrl      = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
                $variantResponse = $this->retryOnRateLimit(fn () => Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type'           => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($variantUrl));
                $hadHttp = true;
                if ($variantResponse->successful()) {
                    $productId = $variantResponse->json('variant.product_id');
                }
            }

            if (! $productId && ctype_digit($trim)) {
                if ($hadHttp) {
                    usleep(500000);
                }
                $probeUrl     = "https://{$domain}/admin/api/2024-01/products/{$trim}.json";
                $productProbe = $this->retryOnRateLimit(fn () => Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type'           => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($probeUrl));
                $hadHttp = true;
                if ($productProbe->successful() && $productProbe->json('product.id')) {
                    $productId = (int) $productProbe->json('product.id');
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

            $imagesBase = "https://{$domain}/admin/api/2024-01/products/{$productId}/images";
            $headers    = ['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'];

            // ── Always fetch existing IDs for replace mode ─────────────────
            $existingIds = [];
            if ($mode === 'replace') {
                $listRes = $this->retryOnRateLimit(fn () =>
                    Http::withHeaders($headers)->timeout(15)->get("{$imagesBase}.json")
                );
                if ($listRes->successful()) {
                    $existingIds = array_column($listRes->json('images', []), 'id');
                }
            }

            // ── CLEAR ALL: empty replace → delete all existing images ───────
            if ($urls === []) {
                $deletedCount = 0;
                foreach ($existingIds as $oldId) {
                    $delRes = $this->retryOnRateLimit(fn () =>
                        Http::withHeaders($headers)->timeout(15)
                            ->delete("{$imagesBase}/{$oldId}.json"),
                        3, 0.4
                    );
                    if ($delRes->successful() || $delRes->status() === 404) {
                        $deletedCount++;
                    }
                }
                return ['success' => true, 'message' => "All images removed from Shopify PLS ({$deletedCount} deleted)."];
            }

            // ── Upload each new image in sequence order (position 1, 2, 3…) ─
            // For REPLACE: explicit position so card-1 → pos-1, card-2 → pos-2, etc.
            // For ADD:     no position — Shopify appends in the order we send them.
            $uploadedCount = 0;
            $position      = 0;
            foreach ($urls as $src) {
                $imageData  = [];
                $attachment = $this->readLocalStorageImageAsBase64($src);

                if ($attachment !== null) {
                    // ── Local file: always use base64 attachment ───────────────
                    if ($mode === 'replace') {
                        $imageData['position'] = ++$position;
                    }
                    $imageData['attachment'] = $attachment;
                    $imageData['filename']   = rawurldecode(
                        basename((string) parse_url($src, PHP_URL_PATH))
                    );
                    unset($attachment);
                } elseif ($this->isLocalStorageUrl($src)) {
                    // ── Local URL but file unreadable → SKIP ───────────────────
                    // Sending localhost as 'src' to Shopify creates a tiny broken
                    // placeholder — this was the cause of "low resolution" images.
                    Log::warning('Shopify PLS image upload: local file not readable, skipping', ['src' => $src]);
                    continue;
                } else {
                    // ── Remote URL → let Shopify fetch directly ────────────────
                    if ($mode === 'replace') {
                        $imageData['position'] = ++$position;
                    }
                    $imageData['src'] = $src;
                }

                // 0.4s spacing keeps upload order intact and is well within Shopify rate limits
                $postRes = $this->retryOnRateLimit(fn () =>
                    Http::withHeaders($headers)->timeout(30)->connectTimeout(15)
                        ->post("{$imagesBase}.json", ['image' => $imageData]),
                    3, 0.4
                );
                if ($postRes->successful()) {
                    $uploadedCount++;
                }
            }

            if ($uploadedCount === 0) {
                return ['success' => false, 'message' => 'No images could be uploaded to Shopify PLS.'];
            }

            // ── REPLACE: delete old images now that new ones are live ───────
            $deletedCount = 0;
            $deleteErrors = 0;
            foreach ($existingIds as $oldId) {
                $delRes = $this->retryOnRateLimit(fn () =>
                    Http::withHeaders($headers)->timeout(15)
                        ->delete("{$imagesBase}/{$oldId}.json"),
                    3, 0.4
                );
                if ($delRes->successful() || $delRes->status() === 404) {
                    $deletedCount++;
                } else {
                    $deleteErrors++;
                    Log::warning('Shopify PLS image DELETE failed', [
                        'image_id' => $oldId,
                        'status'   => $delRes->status(),
                        'body'     => mb_substr($delRes->body(), 0, 300),
                    ]);
                }
            }

            $action = $mode === 'add' ? 'Added' : 'Replaced with';
            $deleteNote = ($deleteErrors > 0) ? " ({$deleteErrors} old image(s) could not be deleted — retry Replace to clean up)" : '';

            return ['success' => true, 'message' => "{$action} {$uploadedCount} image(s) on Shopify PLS in sequence order.{$deleteNote}"];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Image Master compatibility method: push images then persist in shopify_catalog_products (store=pls).
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        // Preserve order — unique + trim only (no sort)
        $seen = []; $images = array_values(array_filter(array_map('trim', $images), function($v) use (&$seen) {
            if ($v === '' || isset($seen[$v])) return false; $seen[$v] = true; return true;
        }));
        $images = array_slice($images, 0, 20);
        if ($images === [] && $mode !== 'replace') {
            return ['success' => true, 'message' => 'No images to add; skipped.'];
        }

        $res = $this->updateListingImages($identifier, $images, $mode);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $saved = $this->saveImageUrlsToShopifyCatalog('pls', $identifier, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Shopify PLS product images updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToShopifyCatalog(string $store, string $identifier, array $images): bool
    {
        try {
            if (! Schema::hasTable('shopify_catalog_products') || ! Schema::hasTable('shopify_catalog_variants')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $variant = DB::table('shopify_catalog_variants')
                ->where('store', $store)
                ->where(function ($q) use ($identifier) {
                    $q->where('sku', $identifier)
                        ->orWhere('sku', strtoupper($identifier))
                        ->orWhere('sku', strtolower($identifier));
                })
                ->first();
            if (! $variant) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn('shopify_catalog_products', 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if (Schema::hasColumn('shopify_catalog_products', 'images')) {
                $update['images'] = $payload;
            }
            if (Schema::hasColumn('shopify_catalog_products', 'image_src')) {
                $update['image_src'] = (string) ($images[0] ?? '');
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('shopify_catalog_products', 'updated_at')) {
                $update['updated_at'] = now();
            }

            return DB::table('shopify_catalog_products')
                ->where('id', $variant->shopify_catalog_product_id)
                ->update($update) > 0;
        } catch (\Throwable $e) {
            Log::warning('Shopify PLS saveImageUrlsToShopifyCatalog failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return false;
        }
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
            ])->timeout(60)->connectTimeout(25)->get($productUrl);
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

    private function appendUniqueHtmlByPlainText(string $currentHtml, string $incomingHtml, string $incomingPlain): string
    {
        $currentHtml = trim($currentHtml);
        $incomingPlain = trim($incomingPlain);
        if ($currentHtml === '') {
            return $incomingHtml;
        }
        if ($incomingPlain !== '') {
            $currentPlain = trim(strip_tags($currentHtml));
            if (str_contains(mb_strtolower($currentPlain), mb_strtolower($incomingPlain))) {
                return $currentHtml;
            }
        }

        return $currentHtml."\n\n".$incomingHtml;
    }

    /**
     * Longest tier description from Product Master (for bullet-only Shopify push).
     */
    private function resolveProductMasterLongDescription(string $sku): string
    {
        try {
            $pm = ProductMaster::query()
                ->where(function ($q) use ($sku) {
                    $t = trim($sku);
                    $q->where('sku', $t)
                        ->orWhere('sku', strtoupper($t))
                        ->orWhere('sku', strtolower($t));
                })
                ->first();
            if (! $pm) {
                return '';
            }
            foreach (['description_1500', 'description_1000', 'description_800', 'description_600', 'product_description'] as $col) {
                if (! Schema::hasColumn('product_master', $col)) {
                    continue;
                }
                $v = trim((string) ($pm->{$col} ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ShopifyPLSApiService: resolveProductMasterLongDescription failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * @return array<int, array{title: string, body: string}>
     */
    private function loadShopifyFeatureGridForSku(string $sku): array
    {
        try {
            $pm = ProductMaster::query()
                ->where(function ($q) use ($sku) {
                    $t = trim($sku);
                    $q->where('sku', $t)
                        ->orWhere('sku', strtoupper($t))
                        ->orWhere('sku', strtolower($t));
                })
                ->first();
            if (! $pm || ! is_array($pm->Values)) {
                return [];
            }
            $raw = $pm->Values['shopify_feature_grid'] ?? null;
            if (! is_array($raw)) {
                return [];
            }
            $out = [];
            foreach (array_slice($raw, 0, 4) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $out[] = [
                    'title' => trim((string) ($item['title'] ?? '')),
                    'body' => trim((string) ($item['body'] ?? '')),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('ShopifyPLSApiService: loadShopifyFeatureGridForSku failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function loadPlsMetricsBulletPoints(string $sku): string
    {
        try {
            if (! Schema::hasTable('shopify_pls_metrics') || ! Schema::hasColumn('shopify_pls_metrics', 'bullet_points')) {
                return '';
            }
            $t = trim($sku);
            $row = DB::table('shopify_pls_metrics')
                ->where(function ($q) use ($t) {
                    $q->where('sku', $t)
                        ->orWhere('sku', strtoupper($t))
                        ->orWhere('sku', strtolower($t));
                })
                ->first();

            return $row ? trim((string) ($row->bullet_points ?? '')) : '';
        } catch (\Throwable $e) {
            Log::warning('ShopifyPLSApiService: loadPlsMetricsBulletPoints failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * If $url points to a file on our own public storage disk, return its base64 content.
     * Returns null for external URLs so the caller can fall back to `src`.
     */
    /** Returns true if the URL points to this application's local storage. */
    private function isLocalStorageUrl(string $url): bool
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        return ($appUrl !== '' && str_starts_with($url, $appUrl))
            || (bool) preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?/#i', $url);
    }

    /**
     * Read a local storage image and return its base64-encoded content.
     * Returns null if the URL is not local OR if the file cannot be read.
     */
    /**
     * Read a local storage image and return its base64-encoded content.
     * Uses fopen('rb') to guarantee binary-mode reading on Windows (XAMPP).
     */
    private function readLocalStorageImageAsBase64(string $url): ?string
    {
        try {
            if (! $this->isLocalStorageUrl($url)) {
                return null;
            }

            $urlPath = (string) parse_url($url, PHP_URL_PATH);
            if (! preg_match('#/storage/(.+)$#', $urlPath, $m)) {
                return null;
            }

            // Try both raw-space and %20-encoded variants so SKUs like "138 RU" work
            $candidates = array_unique([
                rawurldecode($m[1]),
                urldecode($m[1]),
                $m[1],
            ]);

            $absolutePath = null;
            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                $abs = Storage::disk('public')->path($candidate);
                if (is_file($abs) && filesize($abs) > 0) {
                    $absolutePath = $abs;
                    break;
                }
            }

            if ($absolutePath === null) {
                return null;
            }

            // 'rb' = explicit binary mode — guarantees every byte of JPEG/PNG
            // data is read exactly as stored, with no Windows CR/LF translation.
            $fh = @fopen($absolutePath, 'rb');
            if ($fh === false) {
                return null;
            }

            $content = stream_get_contents($fh);
            fclose($fh);

            if ($content === false || $content === '') {
                return null;
            }

            // Shopify product images must be square (1:1) to fill the storefront
            // image container without white side-bars.  Pad to square here.
            $content = $this->padImageToSquare($content, $absolutePath);

            return base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Pad an image to 1:1 square with a white background (centred).
     * Returns the original binary string unchanged if GD is unavailable,
     * the image is already square, or processing fails for any reason.
     */
    private function padImageToSquare(string $content, string $absolutePath): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $content; // GD not available — skip
        }

        try {
            $img = @imagecreatefromstring($content);
            if ($img === false) {
                return $content;
            }

            $w = imagesx($img);
            $h = imagesy($img);

            if ($w === $h) {
                imagedestroy($img);
                return $content; // already square — nothing to do
            }

            $size   = max($w, $h);
            $canvas = imagecreatetruecolor($size, $size);
            if ($canvas === false) {
                imagedestroy($img);
                return $content;
            }

            // Fill canvas with white
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);

            // Centre the original image on the canvas
            $offsetX = (int) (($size - $w) / 2);
            $offsetY = (int) (($size - $h) / 2);
            imagecopy($canvas, $img, $offsetX, $offsetY, 0, 0, $w, $h);
            imagedestroy($img);

            // Re-encode in the same format as the source file
            ob_start();
            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            match ($ext) {
                'png'  => imagepng($canvas, null, 6),
                'webp' => imagewebp($canvas, null, 90),
                default => imagejpeg($canvas, null, 95), // jpg / jpeg
            };
            $padded = ob_get_clean();
            imagedestroy($canvas);

            return ($padded !== false && $padded !== '') ? $padded : $content;
        } catch (\Throwable) {
            return $content; // fall back to original on any error
        }
    }
}
