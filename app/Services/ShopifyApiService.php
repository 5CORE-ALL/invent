<?php

namespace App\Services;

use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Models\ShopifyVariant;
use App\Services\Support\Concerns\ShopifyAdminRateLimitRetry;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\ShopifyBulletPointsFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

            $variantId = $this->resolveMainStoreVariantId($sku);
            if (! $variantId) {
                Log::warning('Main Shopify: variant mapping not found for SKU', ['sku' => $sku]);

                return false;
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                Log::error('❌ Push to Main Shopify - Variant lookup failed', [
                    'sku' => $sku,
                    'variant_id' => $variantId,
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
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
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
     * Resolve main-store Shopify Admin variant ID for API calls (Description Master, bullets, images).
     *
     * Order: (1) main catalog SKU match, case-insensitive — synced Admin data is preferred over stale shopify_skus rows;
     * (2) shopify_skus exact SKU; (3) shopify_skus case-insensitive (newest row); (4) identifier as variant_id on shopify_skus or catalog.
     */
    protected function resolveMainStoreVariantId(string $identifier): ?string
    {
        $trim = trim($identifier);
        if ($trim === '') {
            return null;
        }

        $lowerSku = mb_strtolower($trim);

        if (Schema::hasTable('shopify_catalog_variants')) {
            $cat = ShopifyVariant::query()
                ->whereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
                ->orderByDesc('synced_at')
                ->orderByDesc('id')
                ->first();
            if ($cat && $cat->shopify_variant_id) {
                return (string) $cat->shopify_variant_id;
            }
        }

        $row = ShopifySku::where('sku', $trim)->first();
        if ($row && $row->variant_id) {
            return (string) $row->variant_id;
        }

        $row = ShopifySku::whereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        if ($row && $row->variant_id) {
            return (string) $row->variant_id;
        }

        $byVid = ShopifySku::where('variant_id', $trim)->first();
        if ($byVid && $byVid->variant_id) {
            return (string) $byVid->variant_id;
        }

        if (Schema::hasTable('shopify_catalog_variants')) {
            $catVid = ShopifyVariant::query()->where('shopify_variant_id', $trim)->first();
            if ($catVid && $catVid->shopify_variant_id) {
                return (string) $catVid->shopify_variant_id;
            }
        }

        return null;
    }

    /**
     * Bullet Points Master: update only the bullet/About Item block in the current Shopify `body_html`.
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

        $firstBullet = '';
        foreach (preg_split('/\r\n|\r|\n/', $bulletPoints) as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/^\s*✅\s*/u', '', $line);
            $line = trim($line, " \t\n\r\0\x0B-•*");
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
            $trim = trim($identifier);
            $variantId = $this->resolveMainStoreVariantId($trim);
            if (! $variantId) {
                Log::warning('Shopify updateBulletPoints mapping missing', ['identifier' => $identifier]);

                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $mappedSku = ShopifySku::where('variant_id', $variantId)->value('sku');
            if ($mappedSku === null && Schema::hasTable('shopify_catalog_variants')) {
                $mappedSku = ShopifyVariant::query()->where('shopify_variant_id', $variantId)->value('sku');
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($variantUrl);
            });

            if (! $variantRes->successful()) {
                Log::warning('Shopify updateBulletPoints variant lookup failed', [
                    'identifier' => $identifier,
                    'variant_id' => $variantId,
                    'status' => $variantRes->status(),
                    'x_request_id' => $variantRes->header('x-request-id'),
                    'retry_after' => $variantRes->header('Retry-After'),
                    'call_limit' => $variantRes->header('X-Shopify-Shop-Api-Call-Limit'),
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
                    'variant_id' => $variantId,
                    'variant_response_preview' => mb_substr($variantRes->body(), 0, 500),
                ]);
                return ['success' => false, 'message' => 'Product ID missing.'];
            }
            Log::info('Shopify updateBulletPoints resolved mapping', [
                'identifier' => $identifier,
                'mapped_sku' => $mappedSku ?? $trim,
                'variant_id' => $variantId,
                'product_id' => $productId,
            ]);

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $productRes = $this->retryOnRateLimit(function () use ($token, $productUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($productUrl);
            });

            if (! $productRes->successful()) {
                $msg = $productRes->status() === 429
                    ? 'Product fetch rate limited after retries.'
                    : 'Could not load product before bullet update: '.$productRes->body();

                return ['success' => false, 'message' => $msg];
            }

            $currentBody = (string) ($productRes->json('product.body_html') ?? '');
            $formattedHtml = ShopifyBulletPointsFormatter::replaceAboutItemBlock($currentBody, $bulletPoints);

            $legacyBracketPattern = '/<p\b[^>]*>(?:(?!<\/p>)[\s\S])*?【(?:(?!<\/p>)[\s\S])*?<\/p>/is';
            $legacyBracketListPattern = '/<(?:ol|ul)\b[^>]*>(?=[\s\S]*?【)[\s\S]*?<\/(?:ol|ul)>/is';
            $legacyHighlightedFeaturesPattern = '/<h[1-6]\b[^>]*>(?=[\s\S]*?Highlighted\s+Features)[\s\S]*?<\/h[1-6]>\s*(?:<p\b[^>]*>(?:(?!<\/p>)[\s\S])*?【(?:(?!<\/p>)[\s\S])*?<\/p>\s*){1,8}/is';
            $legacyAboutItemHeadingPattern = '/<p\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/p>\s*/is';
            $emptyLegacyListPattern = '/<(?:ol|ul)\b[^>]*>\s*<\/(?:ol|ul)>\s*/is';
            $safeLegacyHeadingPattern = '/<h[1-6]\b[^>]*>(?=[\s\S]*?(?:Key\s+Benefits|Product\s+Highlights|Main\s+Features|Bullet\s+Points))[\s\S]*?<\/h[1-6]>\s*(?:(?:<p\b[^>]*>(?:(?!<\/p>)[\s\S])*?(?:【|•|\*|-|\d+[.)])(?:(?!<\/p>)[\s\S])*?<\/p>\s*){1,8}|<(?:ul|ol)\b[^>]*>[\s\S]*?<\/(?:ul|ol)>)/is';
            $leadingSymbolParagraphsPattern = '/^\s*(?:<p\b[^>]*>\s*(?:<[^>]+>\s*)*(?:•|\*|-|\d+[.)])[\s\S]*?<\/p>\s*){2,8}/is';
            $leadingListPattern = '/^\s*<(?:ul|ol)\b[^>]*>[\s\S]*?<\/(?:ul|ol)>\s*/is';
            $manualBeforeBulletPattern = '/\A\s*<p\b[^>]*>\s*<a\b[^>]*>[\s\S]*?Download\s+Product\s+Manual[\s\S]*?<\/a>\s*<\/p>\s*<!--\s*bullet-points-master:start\s*-->/is';
            $legacyAplusBulletPattern = '/<div\b[^>]*class=(["\'])(?=[^"\']*\baplus-3p-center-content\b)[^"\']*\1[^>]*>(?=[\s\S]*?About\s+Item:)(?=[\s\S]*?【)[\s\S]*?<\/div>\s*/is';
            $topBoldLabelBulletsPattern = '/(?:\A\s*<p\b[^>]*>\s*<a\b[^>]*>[\s\S]*?Download\s+Product\s+Manual[\s\S]*?<\/a>\s*<\/p>\s*)?\K(?:<p\b[^>]*>\s*(?:<[^>]+>\s*)*<strong\b[^>]*>[^<]{2,120}(?:-|:|–|—)\s*<\/strong>[\s\S]*?<\/p>\s*){2,8}/is';
            Log::info('Shopify updateBulletPoints preserving existing description body_html', [
                'identifier' => $identifier,
                'mapped_sku' => $mappedSku ?? $trim,
                'product_id' => $productId,
                'previous_body_html_chars' => strlen($currentBody),
                'new_body_html_chars' => strlen($formattedHtml),
                'previous_master_marker_count' => substr_count($currentBody, 'bullet-points-master-section'),
                'new_master_marker_count' => substr_count($formattedHtml, 'bullet-points-master-section'),
                'previous_legacy_bracket_bullets' => preg_match_all($legacyBracketPattern, $currentBody),
                'new_legacy_bracket_bullets' => preg_match_all($legacyBracketPattern, $formattedHtml),
                'previous_legacy_bracket_lists' => preg_match_all($legacyBracketListPattern, $currentBody),
                'new_legacy_bracket_lists' => preg_match_all($legacyBracketListPattern, $formattedHtml),
                'previous_legacy_highlighted_features' => preg_match_all($legacyHighlightedFeaturesPattern, $currentBody),
                'new_legacy_highlighted_features' => preg_match_all($legacyHighlightedFeaturesPattern, $formattedHtml),
                'previous_legacy_about_item_headings' => preg_match_all($legacyAboutItemHeadingPattern, $currentBody),
                'new_legacy_about_item_headings' => preg_match_all($legacyAboutItemHeadingPattern, $formattedHtml),
                'previous_empty_legacy_lists' => preg_match_all($emptyLegacyListPattern, $currentBody),
                'new_empty_legacy_lists' => preg_match_all($emptyLegacyListPattern, $formattedHtml),
                'previous_safe_legacy_headings' => preg_match_all($safeLegacyHeadingPattern, $currentBody),
                'new_safe_legacy_headings' => preg_match_all($safeLegacyHeadingPattern, $formattedHtml),
                'previous_leading_symbol_paragraphs' => preg_match_all($leadingSymbolParagraphsPattern, $currentBody),
                'new_leading_symbol_paragraphs' => preg_match_all($leadingSymbolParagraphsPattern, $formattedHtml),
                'previous_leading_lists' => preg_match_all($leadingListPattern, $currentBody),
                'new_leading_lists' => preg_match_all($leadingListPattern, $formattedHtml),
                'new_manual_link_before_bullets' => preg_match($manualBeforeBulletPattern, $formattedHtml) === 1,
                'previous_legacy_aplus_bullet_blocks' => preg_match_all($legacyAplusBulletPattern, $currentBody),
                'new_legacy_aplus_bullet_blocks' => preg_match_all($legacyAplusBulletPattern, $formattedHtml),
                'previous_top_bold_label_bullets' => preg_match_all($topBoldLabelBulletsPattern, $currentBody),
                'new_top_bold_label_bullets' => preg_match_all($topBoldLabelBulletsPattern, $formattedHtml),
            ]);

            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $formattedHtml) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'body_html' => $formattedHtml,
                    ],
                ]);
            });
            Log::info('Shopify updateBulletPoints API response', [
                'identifier' => $identifier,
                'variant_id' => $variantId,
                'product_id' => $productId,
                'status' => $updateRes->status(),
                'x_request_id' => $updateRes->header('x-request-id'),
                'call_limit' => $updateRes->header('X-Shopify-Shop-Api-Call-Limit'),
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
                        ])->timeout(60)->connectTimeout(25)->get($productUrl);
                    });
                    if (! $verifyRes->successful()) {
                        Log::warning('Shopify updateBulletPoints verify fetch failed', [
                            'identifier' => $identifier,
                            'product_id' => $productId,
                            'attempt' => $idx + 1,
                            'status' => $verifyRes->status(),
                            'x_request_id' => $verifyRes->header('x-request-id'),
                            'retry_after' => $verifyRes->header('Retry-After'),
                            'call_limit' => $verifyRes->header('X-Shopify-Shop-Api-Call-Limit'),
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
                        'variant_id' => $variantId,
                        'product_id' => $productId,
                        'expected_body_md5' => md5($formattedHtml),
                        'verified_body_len' => $verifiedBodyLen,
                        'contains_first_bullet' => $verifiedContainsBullet,
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Shopify API returned success, but verification could not confirm persisted body_html yet. Please retry fetch/check in a few seconds.',
                        'variant_id' => (string) $variantId,
                        'product_id' => (string) $productId,
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Shopify product bullets updated and verified.',
                    'variant_id' => (string) $variantId,
                    'product_id' => (string) $productId,
                ];
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
     * Description Master (HTML Editor): set `body_html` to the given HTML string (full replace, no server templating).
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
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $variantId = $this->resolveMainStoreVariantId($trim);
            if (! $variantId) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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

            usleep(1_000_000);

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

            $title = (string) ($getProduct->json('product.title') ?? '');
            if ($title === '') {
                return ['success' => false, 'message' => 'Product title missing from Shopify.'];
            }

            Log::info('Shopify updateBodyHtml: replacing body_html with custom HTML', [
                'sku' => $trim,
                'product_id' => $productId,
                'body_html_chars' => strlen($bodyHtml),
            ]);

            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $bodyHtml, $title) {
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

            if ($updateRes->successful()) {
                return ['success' => true, 'message' => 'Shopify product body HTML updated.'];
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
     * Description Master: sets `body_html` to a unified layout — About Item (from Bullet Points Master / shopify_metrics),
     * Product Description, optional Features 2×2 grid (from product_master.Values.shopify_feature_grid), then Images.
     *
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
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
            $variantId = $this->resolveMainStoreVariantId($trim);
            if (! $variantId) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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

            usleep(1_000_000);

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
                return ['success' => false, 'message' => 'Product title missing from Shopify.'];
            }

            $aboutBullets = $this->loadShopifyMetricsBulletPoints($trim);
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

            Log::info('Shopify updateDescription: body_html set to unified rich layout (About Item → Product Description → Features → Images)', [
                'sku' => $trim,
                'product_id' => $productId,
                'about_bullets_chars' => strlen($aboutBullets),
                'feature_box_count' => count($featureGrid),
                'previous_body_html_chars' => strlen($currentBody),
                'new_body_html_chars' => strlen($combined),
            ]);

            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $combined, $title) {
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

            if ($updateRes->successful()) {
                return ['success' => true, 'message' => 'Shopify product description updated (unified layout).'];
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
     * Set `body_html` to the same unified layout as Description Master / bullet push (no legacy Key Features list).
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
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $trim = trim($identifier);
            $variantId = $this->resolveMainStoreVariantId($trim);
            if (! $variantId) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->retryOnRateLimit(function () use ($token, $variantUrl) {
                return Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(25)->get($variantUrl);
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

            usleep(1_000_000);

            $title = $this->getProductTitle($domain, $token, $productId);
            if ($title === '') {
                return ['success' => false, 'message' => 'Could not load product title (Shopify rate limit or API error).'];
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

            Log::info('Shopify updateProductDescriptionWithBullets: unified layout', [
                'sku' => $trim,
                'product_id' => $productId,
                'feature_box_count' => count($featureGrid),
            ]);

            $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
            $updateRes = $this->retryOnRateLimit(function () use ($token, $productUrl, $productId, $combined, $title) {
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

            if ($updateRes->successful()) {
                return ['success' => true, 'message' => 'Shopify product description (unified layout) updated.'];
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
    public function updateListingImages(string $identifier, array $imageUrls, string $mode = 'replace'): array
    {
        // Preserve caller's order — only trim whitespace, do NOT sort
        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 20);

        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU / identifier is required.'];
        }

        // Empty urls + add mode = nothing to do
        if ($urls === [] && $mode !== 'replace') {
            return ['success' => true, 'message' => 'No images to add; skipped.'];
        }

        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token  = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }

            $domain = rtrim(preg_replace('#^https?://#', '', $domain), '/');
            $trim   = trim($identifier);

            $variantId = $this->resolveMainStoreVariantId($trim);
            if (! $variantId) {
                return ['success' => false, 'message' => 'Shopify variant mapping not found for SKU or variant_id.'];
            }

            $variantRes = $this->retryOnRateLimit(fn () => Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type'           => 'application/json',
            ])->timeout(60)->connectTimeout(25)->get("https://{$domain}/admin/api/2024-01/variants/{$variantId}.json"));

            if (! $variantRes->successful()) {
                return ['success' => false, 'message' => 'Variant lookup failed: '.$variantRes->body()];
            }

            $productId = $variantRes->json('variant.product_id');
            if (! $productId) {
                return ['success' => false, 'message' => 'Product ID missing.'];
            }

            // ── GraphQL fast path: attach ALL images in a single request, avoiding the REST
            //    ~2-calls/second limit that drops images under load. Only when every URL is public
            //    (CDN/remote); local-file URLs fall through to the REST base64 path below.
            if ($urls !== [] && $this->allUrlsArePublic($urls)) {
                $gqlResult = $this->attachProductImagesViaGraphql($domain, $token, (string) $productId, $urls, $mode);
                if ($gqlResult['success'] ?? false) {
                    return $gqlResult;
                }
                Log::warning('Shopify GraphQL image attach failed; falling back to REST', [
                    'sku' => $trim, 'error' => $gqlResult['message'] ?? 'unknown',
                ]);
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
                    // 0.4s spacing — fast enough for bulk deletes, safe for Shopify rate limits
                    $delRes = $this->retryOnRateLimit(fn () =>
                        Http::withHeaders($headers)->timeout(15)
                            ->delete("{$imagesBase}/{$oldId}.json"),
                        3, 0.4
                    );
                    if ($delRes->successful() || $delRes->status() === 404) {
                        $deletedCount++;
                    }
                }
                return ['success' => true, 'message' => "All images removed from Shopify ({$deletedCount} deleted)."];
            }

            // ── Upload each new image in sequence order (position 1, 2, 3…) ─
            // For REPLACE: explicit position so card-1 → pos-1, card-2 → pos-2, etc.
            // For ADD:     no position — Shopify appends in the order we send them.
            $uploadedCount = 0;
            $position      = 0; // track actual upload position (skipped images don't count)
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
                    unset($attachment); // free memory immediately after use
                } elseif ($this->isLocalStorageUrl($src)) {
                    // ── Local URL but file unreadable → SKIP this image ────────
                    // Sending a localhost URL as 'src' to Shopify would produce a
                    // tiny broken placeholder image because Shopify cannot reach
                    // 127.0.0.1 — this was the root cause of "low resolution" images.
                    Log::warning('Shopify image upload: local file not readable, skipping', ['src' => $src]);
                    continue;
                } else {
                    // ── Remote URL (Shopify CDN, Amazon, etc.) → direct fetch ──
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
                } else {
                    Log::warning('Shopify image upload failed', [
                        'sku_or_identifier' => $identifier,
                        'product_id'        => $productId,
                        'status'            => $postRes->status(),
                        'body'              => mb_substr($postRes->body(), 0, 500),
                        'source_is_local'   => $this->isLocalStorageUrl($src),
                    ]);
                }
            }

            if ($uploadedCount === 0) {
                return ['success' => false, 'message' => 'No images could be uploaded to Shopify.'];
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
                    Log::warning('Shopify image DELETE failed', [
                        'image_id' => $oldId,
                        'status'   => $delRes->status(),
                        'body'     => mb_substr($delRes->body(), 0, 300),
                    ]);
                }
            }

            $action = $mode === 'add' ? 'Added' : 'Replaced with';
            $deleteNote = ($deleteErrors > 0) ? " ({$deleteErrors} old image(s) could not be deleted — retry Replace to clean up)" : '';

            return ['success' => true, 'message' => "{$action} {$uploadedCount} image(s) on Shopify in sequence order.{$deleteNote}"];

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

        $saved = $this->saveImageUrlsToShopifyCatalog('main', $identifier, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Shopify product images updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * Upload raw image bytes to the Shopify Files CDN (GraphQL Files API) and return the public
     * cdn.shopify.com URL. Image Master uploads use this so a new image becomes a marketplace-
     * fetchable CDN URL (the same `/s/files/.../files/` bucket as existing product images) instead
     * of a self-hosted /storage URL that Reverb and other URL-fetching marketplaces cannot fetch.
     *
     * @return array{success: bool, url?: string, message: string}
     */
    public function uploadImageToShopifyCdn(string $contents, string $filename, string $mimeType = 'image/jpeg'): array
    {
        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token  = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return ['success' => false, 'message' => 'Shopify credentials not configured.'];
            }
            $domain   = rtrim(preg_replace('#^https?://#', '', $domain), '/');
            $version  = config('services.shopify.api_version', '2025-01');
            $gql      = "https://{$domain}/admin/api/{$version}/graphql.json";
            $headers  = ['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'];

            // Validation: enforce Shopify's 20-megapixel limit (downscale if over) and align the
            // mime/filename to the actual (possibly re-encoded) bytes so the staged upload is valid.
            $contents = $this->downscaleImageBytes($contents);
            $info = @getimagesizefromstring($contents);
            if ($info === false) {
                return ['success' => false, 'message' => 'File is not a valid image.'];
            }
            $mimeType = $info['mime'] ?: ($mimeType ?: 'image/jpeg');
            $filename = $this->sanitizeCdnFilename($filename, $mimeType);

            // 1. Ask Shopify for a staged upload target.
            $stagedQuery = 'mutation($input:[StagedUploadInput!]!){stagedUploadsCreate(input:$input){stagedTargets{url resourceUrl parameters{name value}} userErrors{field message}}}';
            $stagedVars  = ['input' => [[
                'filename'   => $filename,
                'mimeType'   => $mimeType,
                'resource'   => 'IMAGE',
                'httpMethod' => 'POST',
            ]]];
            $sr     = $this->retryOnRateLimit(fn () => Http::withHeaders($headers)->timeout(40)
                ->post($gql, ['query' => $stagedQuery, 'variables' => $stagedVars]));
            $target = $sr->json('data.stagedUploadsCreate.stagedTargets.0');
            $errs   = $sr->json('data.stagedUploadsCreate.userErrors') ?: [];
            if (! is_array($target) || empty($target['url']) || $errs) {
                return ['success' => false, 'message' => 'stagedUploadsCreate failed: '.json_encode($errs ?: $sr->json() ?: $sr->body())];
            }

            // 2. POST the bytes to the staged target — parameters first, file last (S3/GCS order).
            $upload = Http::asMultipart()->timeout(120);
            foreach (($target['parameters'] ?? []) as $param) {
                $upload = $upload->attach((string) $param['name'], (string) $param['value']);
            }
            $upload = $upload->attach('file', $contents, $filename);
            $ur     = $upload->post($target['url']);
            if (! in_array($ur->status(), [200, 201, 204], true)) {
                return ['success' => false, 'message' => 'Staged upload failed (HTTP '.$ur->status().'): '.mb_substr($ur->body(), 0, 300)];
            }

            // 3. Register the staged resource as a Shopify file.
            $createQuery = 'mutation($files:[FileCreateInput!]!){fileCreate(files:$files){files{id fileStatus ... on MediaImage{image{url}}} userErrors{field message}}}';
            $createVars  = ['files' => [['originalSource' => $target['resourceUrl'], 'contentType' => 'IMAGE']]];
            $cr          = $this->retryOnRateLimit(fn () => Http::withHeaders($headers)->timeout(40)
                ->post($gql, ['query' => $createQuery, 'variables' => $createVars]));
            $file        = $cr->json('data.fileCreate.files.0');
            $createErrs  = $cr->json('data.fileCreate.userErrors') ?: [];
            if (! is_array($file) || $createErrs) {
                return ['success' => false, 'message' => 'fileCreate failed: '.json_encode($createErrs ?: $cr->json() ?: $cr->body())];
            }

            $url    = $file['image']['url'] ?? null;
            $fileId = $file['id'] ?? null;

            // 4. Image processing is async — poll the node until the CDN URL is ready (throttle-aware).
            for ($i = 0; $i < 20 && (! is_string($url) || $url === '') && $fileId; $i++) {
                usleep(1_500_000);
                $nodeQuery = 'query($id:ID!){node(id:$id){... on MediaImage{fileStatus image{url}}}}';
                $nr        = $this->retryOnRateLimit(fn () => Http::withHeaders($headers)->timeout(30)
                    ->post($gql, ['query' => $nodeQuery, 'variables' => ['id' => $fileId]]));
                $url       = $nr->json('data.node.image.url');
            }

            if (! is_string($url) || $url === '') {
                return ['success' => false, 'file_id' => $fileId, 'message' => 'Shopify accepted the file but did not return a CDN URL in time (still processing).'];
            }

            return ['success' => true, 'url' => $url, 'file_id' => $fileId, 'message' => 'Uploaded to Shopify CDN.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Make a filename safe for the Shopify Files CDN (no spaces/odd chars). When a mime type is
     * given, the extension is derived from it (so a re-encoded image gets the right extension).
     */
    private function sanitizeCdnFilename(string $name, string $mime = ''): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME) ?? '');
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'image_'.substr(md5($name.microtime()), 0, 8);
        }
        $byMime = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $ext = $byMime[strtolower($mime)] ?? strtolower(preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($name, PATHINFO_EXTENSION) ?? '') ?: 'jpg');

        return $base.'.'.$ext;
    }

    /**
     * Downscale image bytes to fit within Shopify's pixel limit (default 20 MP). Returns re-encoded
     * JPEG bytes when a resize happens, otherwise the original bytes unchanged. No-op without GD.
     */
    public function downscaleImageBytes(string $bytes, float $maxMegapixels = 20.0): string
    {
        try {
            if (! function_exists('imagecreatefromstring')) {
                return $bytes;
            }
            $info = @getimagesizefromstring($bytes);
            if ($info === false) {
                return $bytes;
            }
            [$w, $h] = $info;
            $maxPx = $maxMegapixels * 1_000_000;
            if ($w < 1 || $h < 1 || ($w * $h) <= $maxPx) {
                return $bytes;
            }
            $scale = sqrt($maxPx / ($w * $h)) * 0.98; // small margin under the hard limit
            $nw = max(1, (int) floor($w * $scale));
            $nh = max(1, (int) floor($h * $scale));
            $src = @imagecreatefromstring($bytes);
            if ($src === false) {
                return $bytes;
            }
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 88);
            $out = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);

            return ($out !== false && $out !== '') ? $out : $bytes;
        } catch (\Throwable) {
            return $bytes;
        }
    }

    /**
     * True when every URL is a public http(s) URL (CDN/remote) — i.e. none are self-hosted local
     * storage URLs. Only then can Shopify fetch them via GraphQL productCreateMedia.
     *
     * @param  list<string>  $urls
     */
    private function allUrlsArePublic(array $urls): bool
    {
        foreach ($urls as $u) {
            if ($this->isLocalStorageUrl($u) || ! parse_url($u, PHP_URL_HOST)) {
                return false;
            }
        }

        return $urls !== [];
    }

    /**
     * Attach product images via the GraphQL Admin API in a single request (productCreateMedia),
     * instead of one REST POST per image. GraphQL uses a separate, more generous cost-based rate
     * limit, so a bulk image push no longer competes for the REST ~2-calls/second budget (which
     * was silently dropping images under load). For replace mode, old media are removed in one
     * productDeleteMedia call and the new media are reordered so urls[0] is the main image.
     *
     * @param  list<string>  $urls  public image URLs, main image first
     * @return array{success: bool, message: string, uploaded?: int, deleted?: int}
     */
    private function attachProductImagesViaGraphql(string $domain, string $token, string $productId, array $urls, string $mode): array
    {
        $version = config('services.shopify.api_version', '2025-01');
        $gql     = "https://{$domain}/admin/api/{$version}/graphql.json";
        $headers = ['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'];
        $pgid    = 'gid://shopify/Product/'.$productId;

        $post = fn (string $query, array $vars) => $this->retryOnRateLimit(fn () => Http::withHeaders($headers)
            ->timeout(60)->post($gql, ['query' => $query, 'variables' => $vars]));

        // 1. Existing media ids (to remove on replace).
        $oldMediaIds = [];
        if ($mode === 'replace') {
            $lr = $post('query($id:ID!){product(id:$id){media(first:100){nodes{id}}}}', ['id' => $pgid]);
            $oldMediaIds = array_values(array_filter(array_map(
                fn ($n) => $n['id'] ?? null,
                $lr->json('data.product.media.nodes') ?: []
            )));
        }

        // 2. Create all new media in ONE request (creation order = array order).
        $media = array_map(fn ($u) => ['originalSource' => $u, 'mediaContentType' => 'IMAGE'], $urls);
        $cq = 'mutation($pid:ID!,$media:[CreateMediaInput!]!){productCreateMedia(productId:$pid,media:$media){media{id} mediaUserErrors{field message}}}';
        $cr = $post($cq, ['pid' => $pgid, 'media' => $media]);
        $createdIds = array_values(array_filter(array_map(
            fn ($m) => $m['id'] ?? null,
            $cr->json('data.productCreateMedia.media') ?: []
        )));
        if ($createdIds === []) {
            $errs = $cr->json('data.productCreateMedia.mediaUserErrors') ?: ($cr->json('errors') ?: $cr->body());

            return ['success' => false, 'message' => 'productCreateMedia failed: '.json_encode($errs)];
        }

        // 3. Remove old media in ONE request (replace).
        $deleted = 0;
        if ($mode === 'replace' && $oldMediaIds !== []) {
            $dq = 'mutation($pid:ID!,$ids:[ID!]!){productDeleteMedia(productId:$pid,mediaIds:$ids){deletedMediaIds mediaUserErrors{message}}}';
            $dr = $post($dq, ['pid' => $pgid, 'ids' => $oldMediaIds]);
            $deleted = count($dr->json('data.productDeleteMedia.deletedMediaIds') ?: []);
        }

        // 4. Order the new media so urls[0] is the main image.
        if (count($createdIds) > 1) {
            $moves = [];
            foreach ($createdIds as $i => $mid) {
                $moves[] = ['id' => $mid, 'newPosition' => (string) $i];
            }
            $post(
                'mutation($id:ID!,$moves:[MoveInput!]!){productReorderMedia(id:$id,moves:$moves){job{id} mediaUserErrors{message}}}',
                ['id' => $pgid, 'moves' => $moves]
            );
        }

        $action = $mode === 'replace' ? 'Replaced with' : 'Added';

        return [
            'success'  => true,
            'message'  => "{$action} ".count($createdIds)." image(s) on Shopify in one request".($deleted ? " ({$deleted} old removed)" : '').'.',
            'uploaded' => count($createdIds),
            'deleted'  => $deleted,
        ];
    }

    /**
     * Delete a file from Shopify Files by its GID (gid://shopify/MediaImage/...) or its CDN URL.
     */
    public function deleteCdnFile(string $fileIdOrUrl): bool
    {
        try {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token  = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token || trim($fileIdOrUrl) === '') {
                return false;
            }
            $domain  = rtrim(preg_replace('#^https?://#', '', $domain), '/');
            $version = config('services.shopify.api_version', '2025-01');
            $gql     = "https://{$domain}/admin/api/{$version}/graphql.json";
            $headers = ['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'];

            $fileId = $fileIdOrUrl;
            if (! str_starts_with($fileIdOrUrl, 'gid://')) {
                // Resolve the GID from the CDN URL by matching recent files.
                $q  = '{files(first:100,sortKey:CREATED_AT,reverse:true){edges{node{id ... on MediaImage{image{url}}}}}}';
                $fr = $this->retryOnRateLimit(fn () => Http::withHeaders($headers)->timeout(30)->post($gql, ['query' => $q]));
                $needle = strtok($fileIdOrUrl, '?');
                $fileId = null;
                foreach (($fr->json('data.files.edges') ?: []) as $e) {
                    if (strtok((string) ($e['node']['image']['url'] ?? ''), '?') === $needle) {
                        $fileId = $e['node']['id'];
                        break;
                    }
                }
                if (! $fileId) {
                    return false;
                }
            }

            $dq = 'mutation($ids:[ID!]!){fileDelete(fileIds:$ids){deletedFileIds userErrors{message}}}';
            $dr = $this->retryOnRateLimit(fn () => Http::withHeaders($headers)->timeout(30)
                ->post($gql, ['query' => $dq, 'variables' => ['ids' => [$fileId]]]));

            return ! empty($dr->json('data.fileDelete.deletedFileIds'));
        } catch (\Throwable) {
            return false;
        }
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
            if ($images === []) {
                if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
                    $update['image_urls'] = null;
                }
                if (Schema::hasColumn('shopify_catalog_products', 'image_master_json')) {
                    $update['image_master_json'] = null;
                }
                if (Schema::hasColumn('shopify_catalog_products', 'images')) {
                    $update['images'] = null;
                }
                if (Schema::hasColumn('shopify_catalog_products', 'image_src')) {
                    $update['image_src'] = null;
                }
            } else {
                $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    return false;
                }
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
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('shopify_catalog_products', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('shopify_catalog_products')
                ->where('id', $variant->shopify_catalog_product_id)
                ->update($update);

            return true;
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
            ])->timeout(60)->connectTimeout(25)->get($productUrl);
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
     * Description Master pull: fetch a product's full rich-HTML description (body_html) + product image URLs by SKU.
     * Read-only (GETs only). Tries the catalog/variant resolver first (exact, synced on production), then falls back
     * to a GraphQL SKU search so it still works when local Shopify mapping tables are not synced.
     *
     * @return array{success: bool, message: string, html?: string, images?: array<int,string>, title?: string, product_id?: string|int|null, source?: string}
     */
    public function fetchProductDescriptionHtml(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        if (! $domain || ! $token) {
            return ['success' => false, 'message' => 'Shopify credentials not configured.'];
        }
        $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
        $verify = env('FILESYSTEM_DRIVER') === 'local';

        $headers = function () use ($token, $verify) {
            $req = Http::withHeaders(['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json']);

            return $verify ? $req->withoutVerifying() : $req;
        };

        // 1) Preferred path: resolve via local catalog/variant mapping (exact; synced on production).
        try {
            $variantId = $this->resolveMainStoreVariantId($sku);
            if ($variantId) {
                $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
                $variantRes = $this->retryOnRateLimit(fn () => $headers()->timeout(60)->connectTimeout(25)->get($variantUrl));
                $productId = $variantRes->successful() ? $variantRes->json('variant.product_id') : null;
                if ($productId) {
                    usleep(400000);
                    $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
                    $getProduct = $this->retryOnRateLimit(fn () => $headers()->timeout(60)->connectTimeout(25)->get($productUrl));
                    if ($getProduct->successful()) {
                        $images = array_values(array_filter(array_map(
                            fn ($i) => $i['src'] ?? null,
                            $getProduct->json('product.images') ?? []
                        )));

                        return [
                            'success' => true,
                            'message' => 'Fetched from Shopify.',
                            'html' => (string) ($getProduct->json('product.body_html') ?? ''),
                            'images' => $images,
                            'title' => (string) ($getProduct->json('product.title') ?? ''),
                            'product_id' => $productId,
                            'source' => 'rest',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Shopify fetchProductDescriptionHtml: resolver path failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        // 2) Fallback: GraphQL search by SKU (no local mapping needed). Prefer an exact SKU match among results.
        try {
            $query = 'query($q: String!) { productVariants(first: 10, query: $q) { edges { node { sku product { id title descriptionHtml images(first: 30) { edges { node { url } } } } } } } }';
            $resp = $this->retryOnRateLimit(fn () => $headers()->timeout(60)->connectTimeout(25)->post(
                "https://{$domain}/admin/api/2024-01/graphql.json",
                ['query' => $query, 'variables' => ['q' => 'sku:'.$sku]]
            ));
            $edges = $resp->json('data.productVariants.edges') ?? [];
            $chosen = null;
            foreach ($edges as $edge) {
                $node = $edge['node'] ?? null;
                if (! is_array($node) || ! isset($node['product'])) {
                    continue;
                }
                if ($chosen === null) {
                    $chosen = $node;
                }
                if (strcasecmp(trim((string) ($node['sku'] ?? '')), $sku) === 0) {
                    $chosen = $node;
                    break;
                }
            }
            if (is_array($chosen)) {
                $prod = $chosen['product'];
                $images = array_values(array_filter(array_map(
                    fn ($e) => $e['node']['url'] ?? null,
                    $prod['images']['edges'] ?? []
                )));
                $exact = strcasecmp(trim((string) ($chosen['sku'] ?? '')), $sku) === 0;

                return [
                    'success' => true,
                    'message' => $exact ? 'Fetched from Shopify (search).' : 'Fetched from Shopify (closest match: '.($chosen['sku'] ?? '?').').',
                    'html' => (string) ($prod['descriptionHtml'] ?? ''),
                    'images' => $images,
                    'title' => (string) ($prod['title'] ?? ''),
                    'product_id' => $prod['id'] ?? null,
                    'source' => 'graphql',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Shopify fetchProductDescriptionHtml: GraphQL fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return ['success' => false, 'message' => 'No Shopify product found for this SKU.'];
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

    /**
     * Longest available tier description from Product Master (used when building Shopify body from bullets only).
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
                Log::debug('ShopifyApiService: resolveProductMasterLongDescription no PM row', ['sku' => $sku]);

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
            Log::warning('ShopifyApiService: resolveProductMasterLongDescription failed', ['sku' => $sku, 'error' => $e->getMessage()]);
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
            Log::warning('ShopifyApiService: loadShopifyFeatureGridForSku failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function loadShopifyMetricsBulletPoints(string $sku): string
    {
        try {
            if (! Schema::hasTable('shopify_metrics') || ! Schema::hasColumn('shopify_metrics', 'bullet_points')) {
                return '';
            }
            $t = trim($sku);
            $row = DB::table('shopify_metrics')
                ->where(function ($q) use ($t) {
                    $q->where('sku', $t)
                        ->orWhere('sku', strtoupper($t))
                        ->orWhere('sku', strtolower($t));
                })
                ->first();

            return $row ? trim((string) ($row->bullet_points ?? '')) : '';
        } catch (\Throwable $e) {
            Log::warning('ShopifyApiService: loadShopifyMetricsBulletPoints failed', ['sku' => $sku, 'error' => $e->getMessage()]);

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
     *
     * Uses fopen('rb') + stream_get_contents() instead of Storage::get() to
     * guarantee true binary-mode reading on Windows (XAMPP).  Storage::get()
     * can silently truncate JPEG/PNG files at certain byte sequences on Windows,
     * causing Shopify to receive partial data and store a reduced-resolution image.
     */
    private function readLocalStorageImageAsBase64(string $url): ?string
    {
        try {
            if (! $this->isLocalStorageUrl($url)) {
                return null;
            }

            // Extract the storage-relative path from the URL path component
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
