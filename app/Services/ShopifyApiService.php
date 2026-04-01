<?php

namespace App\Services;

use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\Support\Concerns\ShopifyAdminRateLimitRetry;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\ShopifyBulletPointsFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShopifyApiService
{
    use ShopifyAdminRateLimitRetry;

    protected $shopifyApiKey;

    protected $shopifyPassword;

    protected $shopifyStoreUrl;

    protected $shopifyStoreUrlName;

    protected $shopifyAccessToken;

    public function __construct()
    {
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
        $this->shopifyStoreUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $this->shopifyStoreUrlName = config('services.shopify.store');
        $this->shopifyAccessToken = config('services.shopify.access_token') ?: config('services.shopify.password');
    }

    /**
     * Update product title for the given SKU on Main Shopify store (5-core.myshopify.com).
     */
    public function updateTitle(string $sku, string $title): bool
    {
        Log::info('🖱️ Push to Main Shopify', ['sku' => $sku]);

        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');

            if (! $domain || ! $token) {
                Log::warning('Main Shopify credentials not configured', ['sku' => $sku]);

                return false;
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $shopifySku = ShopifySku::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();

            if (! $shopifySku || ! $shopifySku->variant_id) {
                Log::warning('Main Shopify: variant mapping not found for SKU', ['sku' => $sku]);

                return false;
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                Log::error('❌ Push to Main Shopify - Variant lookup failed', [
                    'sku' => $sku,
                    'variant_id' => $shopifySku->variant_id,
                    'status' => $variantRes->status(),
                    'body' => $variantRes->body(),
                ]);

                return false;
            }

            $productId = $variantRes->json('variant.product_id');
            if (! $productId) {
                Log::error('❌ Push to Main Shopify - Product ID missing in variant response', ['sku' => $sku]);

                return false;
            }

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                    ],
                ]);
            });

            if ($updateRes->successful()) {
                Log::info('✅ Push to Main Shopify - Success', ['sku' => $sku, 'product_id' => $productId]);

                return true;
            }

            Log::error('❌ Push to Main Shopify - Failed', [
                'sku' => $sku,
                'product_id' => $productId,
                'status' => $updateRes->status(),
                'body' => $updateRes->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('❌ Push to Main Shopify - Exception', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Phase 1: Overwrite product `body_html` with Key Features bullets only (see ShopifyBulletPointsFormatter).
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        if (trim($identifier) === '' || trim($bulletPoints) === '') {
            return ['success' => false, 'message' => 'SKU (or variant_id) and bullet points are required.'];
        }

        if (! ShopifyBulletPointsFormatter::hasAnyBulletLine($bulletPoints)) {
            return ['success' => false, 'message' => 'At least one bullet line is required.'];
        }

        $formattedHtml = ShopifyBulletPointsFormatter::formatBodyHtml($bulletPoints);
        $firstBullet = '';
        foreach (preg_split('/\r\n|\r|\n/', $bulletPoints) as $line) {
            $line = trim((string) $line, " \t\n\r\0\x0B-•*");
            if ($line !== '') {
                $firstBullet = $line;
                break;
            }
        }

        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');
            Log::info('Shopify updateBulletPoints start', [
                'identifier' => $identifier,
                'domain' => $domain,
                'formatted_html_len' => mb_strlen($formattedHtml),
                'formatted_html_md5' => md5($formattedHtml),
            ]);

            $trim = trim($identifier);
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();
            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if (! $shopifySku || ! $shopifySku->variant_id) {
                Log::warning('Shopify updateBulletPoints mapping missing', ['identifier' => $identifier]);
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                Log::warning('Shopify updateBulletPoints variant lookup failed', [
                    'identifier' => $identifier,
                    'variant_id' => $shopifySku->variant_id,
                    'status' => $variantRes->status(),
                    'body_preview' => mb_substr($variantRes->body(), 0, 500),
                ]);
                $msg = $variantRes->status() === 429
                    ? 'Variant lookup rate limited after retries.'
                    : 'Variant lookup failed: '.$variantRes->body();

                return ['success' => false, 'message' => $msg];
            }

            $productId = $variantRes->json('variant.product_id');
            if (! $productId) {
                Log::warning('Shopify updateBulletPoints missing product_id from variant lookup', [
                    'identifier' => $identifier,
                    'variant_id' => $shopifySku->variant_id,
                    'variant_response_preview' => mb_substr($variantRes->body(), 0, 500),
                ]);
                return ['success' => false, 'message' => 'Product ID missing.'];
            }
            Log::info('Shopify updateBulletPoints resolved mapping', [
                'identifier' => $identifier,
                'mapped_sku' => $shopifySku->sku,
                'variant_id' => $shopifySku->variant_id,
                'product_id' => $productId,
            ]);

            usleep(500000);

            $title = $this->getProductTitle($domain, $token, $productId);
            if ($title === '') {
                return ['success' => false, 'message' => 'Could not load product title (Shopify rate limit or API error).'];
            }

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            Log::info('Shopify updateBulletPoints request payload', [
                'identifier' => $identifier,
                'variant_id' => $shopifySku->variant_id,
                'product_id' => $productId,
                'title_len' => mb_strlen($title),
                'body_html_len' => mb_strlen($formattedHtml),
                'body_html_md5' => md5($formattedHtml),
            ]);
            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $formattedHtml, $title) {
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
            Log::info('Shopify updateBulletPoints API response', [
                'identifier' => $identifier,
                'variant_id' => $shopifySku->variant_id,
                'product_id' => $productId,
                'status' => $updateRes->status(),
                'x_request_id' => $updateRes->header('x-request-id'),
                'body_preview' => mb_substr($updateRes->body(), 0, 800),
            ]);

            if ($updateRes->successful()) {
                $verificationDelaysMs = [400, 1000, 2000];
                $verified = false;
                $verifiedBodyLen = 0;
                $verifiedContainsBullet = false;
                foreach ($verificationDelaysMs as $idx => $delayMs) {
                    usleep($delayMs * 1000);
                    $verifyRes = $this->retryOnRateLimit(function () use ($token, $productUrl) {
                        return Http::withHeaders([
                            'X-Shopify-Access-Token' => $token,
                            'Content-Type' => 'application/json',
                        ])->timeout(30)->get($productUrl);
                    });
                    if (! $verifyRes->successful()) {
                        Log::warning('Shopify updateBulletPoints verify fetch failed', [
                            'identifier' => $identifier,
                            'product_id' => $productId,
                            'attempt' => $idx + 1,
                            'status' => $verifyRes->status(),
                            'body_preview' => mb_substr($verifyRes->body(), 0, 500),
                        ]);
                        continue;
                    }

                    $actualBody = (string) ($verifyRes->json('product.body_html') ?? '');
                    $verifiedBodyLen = mb_strlen($actualBody);
                    $actualBodyPlain = mb_strtolower(trim(strip_tags(html_entity_decode($actualBody))));
                    $expectedBulletPlain = mb_strtolower(trim(strip_tags(html_entity_decode($firstBullet))));
                    $verifiedContainsBullet = $expectedBulletPlain === '' || str_contains($actualBodyPlain, $expectedBulletPlain);
                    $verified = $actualBody !== '' && $verifiedContainsBullet;

                    Log::info('Shopify updateBulletPoints verify attempt', [
                        'identifier' => $identifier,
                        'product_id' => $productId,
                        'attempt' => $idx + 1,
                        'actual_body_len' => $verifiedBodyLen,
                        'contains_first_bullet' => $verifiedContainsBullet,
                        'actual_body_preview' => mb_substr($actualBody, 0, 400),
                    ]);

                    if ($verified) {
                        break;
                    }
                }

                if (! $verified) {
                    Log::warning('Shopify updateBulletPoints success but verification mismatch', [
                        'identifier' => $identifier,
                        'variant_id' => $shopifySku->variant_id,
                        'product_id' => $productId,
                        'expected_body_md5' => md5($formattedHtml),
                        'verified_body_len' => $verifiedBodyLen,
                        'contains_first_bullet' => $verifiedContainsBullet,
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Shopify API returned success, but verification could not confirm persisted body_html yet. Please retry fetch/check in a few seconds.',
                    ];
                }

                return ['success' => true, 'message' => 'Shopify product bullets updated and verified.'];
            }

            $errBody = $updateRes->status() === 429
                ? 'Shopify update rate limited after retries.'
                : $updateRes->body();

            return ['success' => false, 'message' => 'Shopify update failed: '.$errBody];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Description Master (Phase 2): append long-form description below existing `body_html` (e.g. Key Features from Phase 1).
     * Does not replace bullets; fetches current product HTML and appends formatted description.
     *
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description): array
    {
        if (trim($identifier) === '' || trim($description) === '') {
            return ['success' => false, 'message' => 'SKU (or variant_id) and description are required.'];
        }

        $descriptionPlain = trim($description);
        if ($descriptionPlain === '') {
            return ['success' => false, 'message' => 'Description is empty.'];
        }
        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();
            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if (! $shopifySku || ! $shopifySku->variant_id) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                $msg = $variantRes->status() === 429
                    ? 'Variant lookup rate limited after retries.'
                    : 'Variant lookup failed: '.$variantRes->body();

                return ['success' => false, 'message' => $msg];
            }

            $productId = $variantRes->json('variant.product_id');
            if (! $productId) {
                return ['success' => false, 'message' => 'Product ID missing.'];
            }

            usleep(500000);

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $getProduct = $this->retryOnRateLimit(function () use ($token, $productUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($productUrl);
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
                return ['success' => false, 'message' => 'Product title missing from Shopify.'];
            }

            $descriptionHtml = DescriptionWithImagesFormatter::buildHtmlWithImages(
                $descriptionPlain,
                $trim,
                $trim,
                $title,
                12
            )['html'];

            $combined = $this->appendUniqueHtmlByPlainText($currentBody, $descriptionHtml, $descriptionPlain);

            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $combined, $title) {
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

            if ($updateRes->successful()) {
                return ['success' => true, 'message' => 'Shopify product description appended.'];
            }

            $errBody = $updateRes->status() === 429
                ? 'Shopify update rate limited after retries.'
                : $updateRes->body();

            return ['success' => false, 'message' => 'Shopify update failed: '.$errBody];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Description Master: set body_html to Key Features (from bullet lines) + long description, preserving title.
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
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();
            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if (! $shopifySku || ! $shopifySku->variant_id) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                $msg = $variantRes->status() === 429
                    ? 'Variant lookup rate limited after retries.'
                    : 'Variant lookup failed: '.$variantRes->body();

                return ['success' => false, 'message' => $msg];
            }

            $productId = $variantRes->json('variant.product_id');
            if (! $productId) {
                return ['success' => false, 'message' => 'Product ID missing.'];
            }

            usleep(500000);

            $title = $this->getProductTitle($domain, $token, $productId);
            if ($title === '') {
                return ['success' => false, 'message' => 'Could not load product title (Shopify rate limit or API error).'];
            }

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $combined, $title) {
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

            if ($updateRes->successful()) {
                return ['success' => true, 'message' => 'Shopify product description (bullets + copy) updated.'];
            }

            $errBody = $updateRes->status() === 429
                ? 'Shopify update rate limited after retries.'
                : $updateRes->body();

            return ['success' => false, 'message' => 'Shopify update failed: '.$errBody];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Replace product images (Admin REST) using public image URLs.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 20);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU (or variant_id) and image URLs are required.'];
        }

        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $shopifySku = ShopifySku::where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();
            if (! $shopifySku) {
                $shopifySku = ShopifySku::where('variant_id', $trim)->first();
            }

            if (! $shopifySku || ! $shopifySku->variant_id) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$shopifySku->variant_id}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                return ['success' => false, 'message' => 'Variant lookup failed: '.$variantRes->body()];
            }

            $productId = $variantRes->json('variant.product_id');
            if (! $productId) {
                return ['success' => false, 'message' => 'Product ID missing.'];
            }

            $title = $this->getProductTitle($domain, $token, $productId);
            if ($title === '') {
                return ['success' => false, 'message' => 'Could not load product title.'];
            }

            $images = [];
            foreach ($urls as $i => $src) {
                $images[] = ['src' => $src, 'position' => $i + 1];
            }

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $title, $images) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(90)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title,
                        'images' => $images,
                    ],
                ]);
            });

            if ($updateRes->successful()) {
                return ['success' => true, 'message' => 'Shopify product images updated.'];
            }

            return ['success' => false, 'message' => 'Shopify image update failed: '.$updateRes->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Image Master compatibility method: push images then persist in shopify_catalog_products.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string}
     */
    public function updateImages(string $identifier, array $images): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 20);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $res = $this->updateListingImages($identifier, $images);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $saved = $this->saveImageUrlsToShopifyCatalog('main', $identifier, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Shopify product images updated.').' Metrics save failed.';
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
            if (! Schema::hasColumn('shopify_catalog_products', 'id')) {
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
            Log::warning('Shopify saveImageUrlsToShopifyCatalog failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Cached product title to reduce sequential API calls per store.
     */
    private function getProductTitle(string $domain, string $token, int|string $productId): string
    {
        $cacheKey = 'shopify_product_title_'.$productId;
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
                Log::warning('Shopify main store: product title fetch rate limited after retries', [
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

    public function getInventory()
    {

        $inventoryData = [];
        $parentVariants = [];
        $pageInfo = null;
        $hasMore = true;
        $pageCount = 0;
        $totalProducts = 0;
        $totalVariants = 0;
        $validSkus = ProductMaster::query()
            ->selectRaw('DISTINCT TRIM(sku) as sku')
            ->whereNotNull('sku')
            ->whereNull('deleted_at')
            ->whereRaw("TRIM(sku) != ''")
            ->whereRaw("LOWER(sku) NOT LIKE '%parent%'")
            ->orderBy('sku')
            ->pluck('sku')
            ->map(fn ($sku) => trim($sku))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $validSkuLookup = array_flip($validSkus);
        while ($hasMore) {
            $pageCount++;
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,title,variants,image,images',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ]);

            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }

            $response = $request->timeout(120)->retry(3, 500)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                Log::error("Failed to fetch products (Page {$pageCount}): ".$response->body());
                break;
            }

            $products = $response->json()['products'] ?? [];
            $productCount = count($products);
            $totalProducts += $productCount;

            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    $totalVariants++;

                    $sku = $variant['sku'] ?? '';
                    $isParent = count($product['variants']) > 1 ? 1 : 0;
                    $imageUrl = $this->sanitizeImageUrl(
                        $product['image']['src'] ?? (! empty($product['images']) ? $product['images'][0]['src'] : null),
                        $sku
                    );

                    // Ensure SKU is properly formatted but preserve original case
                    $sku = trim((string) $sku);

                    // Skip empty SKUs or SKUs containing 'PARENT'
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }

                    $inventoryData[$sku] = [
                        'variant_id' => $variant['id'],
                        'product_id' => $product['id'],
                        'inventory' => (int) ($variant['inventory_quantity'] ?? 0),
                        'product_title' => $product['title'] ?? '',
                        'sku' => $sku,
                        'variant_title' => $variant['title'] ?? '',
                        'inventory_item_id' => $variant['inventory_item_id'],
                        'on_hand' => (int) ($variant['old_inventory_quantity'] ?? 0),
                        'available_to_sell' => (int) ($variant['inventory_quantity'] ?? 0),
                        'price' => $variant['price'],
                        'image_src' => $imageUrl,
                        'is_parent' => $isParent,
                    ];
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;

            if ($hasMore) {
                Log::info('Waiting 0.5s before next page...');
                usleep(500000);
            }
        }

        foreach ($inventoryData as $sku => $data) {
            // Check if SKU exists in our valid SKUs list
            if (! isset($validSkuLookup[$sku])) {
                Log::info("Skipping SKU not in ProductMaster or contains 'PARENT': $sku");

                continue;
            }

            // Ensure inventory values are integers
            $inventory = (int) $data['inventory'];

            ProductStockMapping::updateOrCreate(
                ['sku' => $sku],  // Use exact SKU from ProductMaster
                [
                    'image' => $data['image_src'],
                    'inventory_shopify' => $inventory,
                    'inventory_amazon' => 'Not Listed',
                    'inventory_walmart' => 'Not Listed',
                    'inventory_reverb' => 'Not Listed',
                    'inventory_shein' => 'Not Listed',
                    'inventory_doba' => 'Not Listed',
                    'inventory_temu' => 'Not Listed',
                    'inventory_macy' => 'Not Listed',
                    'inventory_ebay1' => 'Not Listed',
                    'inventory_ebay2' => 'Not Listed',
                    'inventory_ebay3' => 'Not Listed',
                    'inventory_bestbuy' => 'Not Listed',
                    'tiendamia' => 'Not Listed',
                ]
            );
        }

        return $inventoryData;
    }

    protected function sanitizeImageUrl(?string $url, $sku): ?string
    {
        if (empty($url)) {
            return null;
        }
        // Remove line breaks and spaces
        $cleanUrl = trim(preg_replace('/\s+/', '', $url));
        // Remove ?v= query string (Shopify versioning param)
        $cleanUrl = strtok($cleanUrl, '?');

        return $cleanUrl;
    }

    protected function getNextPageInfo($response): ?string
    {
        if ($response->hasHeader('Link') && str_contains($response->header('Link'), 'rel="next"')) {
            $links = explode(',', $response->header('Link'));
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    preg_match('/<(.*)>; rel="next"/', $link, $matches);
                    parse_str(parse_url($matches[1], PHP_URL_QUERY), $query);

                    return $query['page_info'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Build map of SKU => { title, description, image_src } from Shopify products (for Reverb sync product list).
     */
    public function getProductDetailsBySkuMap(array $limitToSkus = []): array
    {
        $map = [];
        $pageInfo = null;
        $hasMore = true;
        $limitToLookup = $limitToSkus ? array_flip(array_map('trim', $limitToSkus)) : null;

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,title,body_html,vendor,product_type,variants,image,images',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ]);
            if (env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }

            $response = $request->timeout(120)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                break;
            }

            $products = $response->json()['products'] ?? [];
            foreach ($products as $product) {
                $imageUrl = $this->sanitizeImageUrl(
                    $product['image']['src'] ?? (! empty($product['images']) ? $product['images'][0]['src'] : null),
                    ''
                );
                $title = $product['title'] ?? '';
                $description = isset($product['body_html']) ? strip_tags($product['body_html']) : '';
                $description = strlen($description) > 500 ? substr($description, 0, 497).'...' : $description;
                $brand = $product['vendor'] ?? '';
                $productType = $product['product_type'] ?? '';

                foreach ($product['variants'] ?? [] as $variant) {
                    $sku = trim((string) ($variant['sku'] ?? ''));
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    if ($limitToLookup !== null && ! isset($limitToLookup[$sku])) {
                        continue;
                    }
                    $variantTitle = trim((string) ($variant['title'] ?? ''));
                    $model = ($variantTitle !== '' && $variantTitle !== 'Default Title') ? $variantTitle : $productType;
                    $map[$sku] = [
                        'title' => $title,
                        'description' => $description,
                        'image_src' => $imageUrl,
                        'upc' => $variant['barcode'] ?? '',
                        'brand' => $brand,
                        'model' => $model,
                    ];
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(300000);
            }
        }

        return $map;
    }

    /**
     * Return map SKU => inventory quantity from Shopify (no DB writes). Used for Reverb inventory sync.
     */
    public function getInventoryQuantitiesBySku(array $limitToSkus = []): array
    {
        $map = [];
        $pageInfo = null;
        $hasMore = true;
        $limitToLookup = $limitToSkus ? array_flip(array_map('trim', $limitToSkus)) : null;

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,variants',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ]);
            if (env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }

            $response = $request->timeout(120)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                break;
            }

            $products = $response->json()['products'] ?? [];
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    $sku = trim((string) ($variant['sku'] ?? ''));
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    if ($limitToLookup !== null && ! isset($limitToLookup[$sku])) {
                        continue;
                    }
                    $map[$sku] = (int) ($variant['inventory_quantity'] ?? 0);
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(300000);
            }
        }

        return $map;
    }
}
