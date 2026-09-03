<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Models\ProductCategory;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AliExpressApiService;
use App\Support\Marketplace\AliexpressListingCounts;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to AliExpress via solution.product.post.
 */
class AliexpressListingPublishService
{
    public function __construct(private AliExpressApiService $api)
    {
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = '', ?int $categoryId = null, ?string $categoryName = null): array
    {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'AliExpress API credentials missing. Set ALIEXPRESS_APP_KEY, ALIEXPRESS_APP_SECRET, and ALIEXPRESS_ACCESS_TOKEN.',
            ];
        }

        $publishSkus = $expandSiblings
            ? $this->expandToPublishableSiblings($skus)
            : $this->filterPublishable($skus);

        if ($publishSkus === []) {
            return [
                'success' => false,
                'message' => 'No Missing L child SKUs left to publish (already listed, NRL, or missing images).',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($mode === 'single' && count($publishSkus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastId = null;
            foreach ($publishSkus as $sku) {
                $one = $this->publishSkus([$sku], false, 'single', $parentHint, $categoryId, $categoryName);
                if ($one['success'] ?? false) {
                    $ok[] = $one['message'] ?? ('Published '.$sku);
                    foreach ($one['skus'] ?? [$sku] as $listedSku) {
                        $listed[] = $listedSku;
                    }
                    if (! empty($one['goods_id'])) {
                        $lastId = $one['goods_id'];
                    }
                } else {
                    $fail[] = $sku.': '.($one['message'] ?? 'Publish failed');
                }
            }

            return [
                'success' => $fail === [],
                'message' => trim(implode(' ', $ok).($fail !== [] ? ' '.implode(' ', $fail) : '')),
                'goods_id' => $lastId,
                'skus' => array_values(array_unique($listed)),
            ];
        }

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $publishSkus)
            ->get()
            ->keyBy(fn ($row) => (string) $row->sku);

        $primarySku = $publishSkus[0];
        $primary = $products->get($primarySku);
        if (! $primary) {
            return ['success' => false, 'message' => 'SKU not found in product master.'];
        }

        $title = $this->resolveTitle($primary, $primarySku);
        if ($title === '') {
            return ['success' => false, 'message' => $primarySku.': Title missing in Title Master'];
        }

        $prepared = [];
        $gallery = [];
        foreach ($publishSkus as $sku) {
            $product = $products->get($sku);
            if (! $product) {
                return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
            }
            $price = $this->resolvePrice($sku, $product);
            if ($price === null || $price <= 0) {
                return [
                    'success' => false,
                    'message' => 'No price found for '.$sku.'. Set Shopify price or AliExpress Std Prc.',
                ];
            }
            $images = $this->productImages($product);
            if ($images === []) {
                return ['success' => false, 'message' => 'No images on product master for '.$sku.'.'];
            }
            foreach ($images as $url) {
                if (! in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
            }
            $prepared[] = [
                'sku' => $sku,
                'product' => $product,
                'price' => $price,
                'inv' => $this->shopifyInv($sku),
                'images' => $images,
            ];
        }

        $parentKey = trim($parentHint) !== '' ? trim($parentHint) : $this->groupKey($primary);
        $resolved = $this->resolveCategory($primary, $primarySku, $title, $gallery[0] ?? '', $categoryId, $categoryName);
        $categoryId = (int) ($resolved['id'] ?? 0);
        if ($categoryId <= 0) {
            return [
                'success' => false,
                'message' => 'Could not match an AliExpress category from the product type. Type a category name (for example Guitar Capos) and try again.',
            ];
        }

        $freightId = $this->resolveFreightTemplateId($primary, $primarySku);
        if ($freightId === '') {
            return [
                'success' => false,
                'message' => 'AliExpress freight template is missing. Set ALIEXPRESS_FREIGHT_TEMPLATE_ID from freighttemplate.aliexpress.com (do not use 1000).',
            ];
        }

        $pkg = $this->packageSize($primary);
        $subject = mb_substr($title, 0, 128);
        $description = $this->resolveDescription($primary, $subject);
        $variation = count($prepared) > 1;
        $skuInfoList = [];
        foreach ($prepared as $row) {
            $skuRow = [
                'sku_code' => $row['sku'],
                'price' => number_format((float) $row['price'], 2, '.', ''),
                'inventory' => max(1, (int) $row['inv']),
            ];
            if ($variation) {
                $skuRow['sku_attributes_list'] = [[
                    'sku_attribute_name' => 'Specification',
                    'sku_attribute_value' => mb_substr((string) $row['sku'], 0, 70),
                    'sku_image_url' => $row['images'][0] ?? ($gallery[0] ?? ''),
                ]];
            }
            $skuInfoList[] = $skuRow;
        }

        $request = [
            'language' => 'en',
            'aliexpress_category_id' => $categoryId,
            'brand_name' => $this->resolveBrand(),
            'multi_language_subject_list' => [
                ['language' => 'en', 'subject' => $subject],
            ],
            'multi_language_description_list' => [$this->descriptionModules($description)],
            'main_image_urls_list' => array_slice($gallery, 0, 6),
            'sku_info_list' => $skuInfoList,
            'product_unit' => (int) config('services.aliexpress.product_unit', 100000015),
            'inventory_deduction_strategy' => 'place_order_withhold',
            'shipping_lead_time' => max(1, (int) config('services.aliexpress.shipping_lead_time', 7)),
            'weight' => (string) $pkg['weight'],
            'package_length' => (int) $pkg['length'],
            'package_width' => (int) $pkg['width'],
            'package_height' => (int) $pkg['height'],
            'freight_template_id' => (int) $freightId,
            'service_policy_id' => (int) config('services.aliexpress.service_policy_id', 0),
        ];

        Log::info('AliExpress publish: sending product.post', [
            'parent' => $parentKey,
            'skus' => $publishSkus,
            'category_id' => $categoryId,
            'category_path' => (string) ($resolved['path'] ?? ''),
            'freight_template_id' => $freightId,
            'mode' => $mode,
        ]);

        $res = $this->api->postProduct($request);
        $productId = trim((string) ($res['product_id'] ?? ''));
        if (empty($res['success']) || $productId === '') {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'AliExpress product post failed. Nothing was created in the seller portal.',
            ];
        }

        $this->persistListed($prepared, $productId);
        $this->forgetListingCaches();

        $count = count($publishSkus);
        $created = $count > 1
            ? 'Submitted AliExpress product #'.$productId.' with '.$count.' variations of '.$parentKey
            : 'Submitted AliExpress product #'.$productId.' for '.$primarySku;

        return [
            'success' => true,
            'message' => $created.' to Under review. After AliExpress accepts it, the listing goes live. Search the seller portal Auditing / Under review tab for product ID '.$productId.'.',
            'goods_id' => $productId,
            'sku_id' => $productId,
            'skus' => $publishSkus,
        ];
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandToPublishableSiblings(array $seedSkus): array
    {
        $seeds = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $seedSkus)
            ->get();

        $parentKeys = [];
        foreach ($seeds as $product) {
            $parentKeys[$this->groupKey($product)] = true;
        }

        $children = collect();
        foreach (array_keys($parentKeys) as $parent) {
            $group = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->orderBy('sku')
                ->get();
            if ($group->isEmpty()) {
                $group = $seeds->filter(function ($product) use ($parent) {
                    return $this->groupKey($product) === $parent
                        && stripos((string) $product->sku, 'PARENT') === false;
                })->values();
            }
            $children = $children->concat($group);
        }

        return $this->filterPublishable(
            $children->map(fn ($p) => trim((string) $p->sku))->filter()->unique()->values()->all()
        );
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function filterPublishable(array $skus): array
    {
        $metrics = AliexpressListingCounts::metricsByNormalizedSku();
        $pricing = AliexpressListingCounts::pricingSkusByNormalizedSku();
        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn ($row) => strtolower(trim((string) $row->sku)));

        $out = [];
        foreach ($skus as $sku) {
            $sku = trim($sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $resolved = AliexpressListingCounts::resolveListed($sku, $metrics, $pricing);
            if ($resolved['listed'] ?? false) {
                continue;
            }
            $product = $products->get(strtolower($sku));
            if (! $product || $this->productImages($product) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @return array{id: int, path: string}
     */
    public function suggestCategoryForSku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['id' => 0, 'path' => ''];
        }
        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first();
        if (! $product) {
            return ['id' => 0, 'path' => ''];
        }
        $title = $this->resolveTitle($product, $sku);
        $images = $this->productImages($product);

        return $this->resolveCategory($product, $sku, $title, $images[0] ?? '', null, null);
    }

    /**
     * @return array{id: int, path: string}
     */
    private function resolveCategory(ProductMaster $primary, string $sku, string $title, string $imageUrl, ?int $overrideId, ?string $overrideName): array
    {
        if ($overrideId !== null && $overrideId > 0) {
            return ['id' => $overrideId, 'path' => ''];
        }

        $overrideName = trim((string) $overrideName);
        if ($overrideName !== '' && preg_match('/^\d{5,}$/', $overrideName)) {
            return ['id' => (int) $overrideName, 'path' => ''];
        }
        if ($overrideName !== '') {
            $hit = $this->api->suggestCategoryMatch($overrideName, $imageUrl, [$overrideName]);
            if ((int) ($hit['id'] ?? 0) > 0) {
                return $hit;
            }
        }

        $hints = $this->productTypeHints($primary, $sku, $title);
        if ($overrideName !== '') {
            array_unshift($hints, $overrideName);
        }
        $hit = $this->api->suggestCategoryMatch($title, $imageUrl, $hints);
        if ((int) ($hit['id'] ?? 0) > 0) {
            return $hit;
        }

        $parent = $this->groupKey($primary);
        $siblings = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parent)
            ->pluck('sku')
            ->all();
        $metrics = AliexpressListingCounts::metricsByNormalizedSku();
        foreach ($siblings as $sib) {
            $productId = AliexpressListingCounts::productIdForSku((string) $sib, $metrics);
            if ($productId === '') {
                continue;
            }
            $info = $this->api->getProductInfo($productId);
            if (empty($info['success'])) {
                continue;
            }
            $fromSibling = $this->api->extractSuggestedCategories($info['data'] ?? []);
            if ($fromSibling !== []) {
                return $fromSibling[0];
            }
            $id = $this->api->extractCategoryId($info['data'] ?? []);
            if ($id !== null) {
                return ['id' => $id, 'path' => 'Same as listed sibling'];
            }
        }

        $configured = (int) preg_replace('/\D+/', '', (string) config('services.aliexpress.default_category_id', ''));
        if ($configured > 0) {
            return ['id' => $configured, 'path' => 'Default AliExpress category'];
        }

        return ['id' => 0, 'path' => ''];
    }

    /**
     * @return list<string>
     */
    private function productTypeHints(ProductMaster $product, string $sku, string $title): array
    {
        $hints = [];
        $parent = $this->groupKey($product);
        if ($parent !== '' && strcasecmp($parent, $sku) !== 0) {
            $hints[] = $parent;
        }

        $categoryId = (int) ($product->category_id ?? 0);
        if ($categoryId > 0) {
            $name = trim((string) ProductCategory::query()->where('id', $categoryId)->value('category_name'));
            if ($name !== '') {
                $hints[] = $name;
            }
        }
        if (Schema::hasColumn('product_master', 'category')) {
            $legacy = trim((string) ($product->getAttribute('category') ?? ''));
            if ($legacy !== '') {
                $hints[] = $legacy;
            }
        }

        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['type', 'Type', 'product_type', 'category', 'Category'] as $key) {
            $raw = trim((string) ($values[$key] ?? ''));
            if ($raw !== '') {
                $hints[] = $raw;
            }
        }
        $hints[] = $title;

        return $hints;
    }

    private function resolveFreightTemplateId(ProductMaster $primary, string $sku): string
    {
        $configured = trim((string) config('services.aliexpress.freight_template_id', ''));
        if ($configured !== '' && $configured !== '1000' && ctype_digit($configured)) {
            return $configured;
        }

        $parent = $this->groupKey($primary);
        $siblings = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parent)
            ->pluck('sku')
            ->all();
        $metrics = AliexpressListingCounts::metricsByNormalizedSku();
        foreach ($siblings as $sib) {
            $productId = AliexpressListingCounts::productIdForSku((string) $sib, $metrics);
            if ($productId === '') {
                continue;
            }
            $info = $this->api->getProductInfo($productId);
            if (empty($info['success'])) {
                continue;
            }
            $id = $this->api->extractFreightTemplateId($info['data'] ?? []);
            if ($id !== '') {
                return $id;
            }
        }

        return $this->api->firstFreightTemplateId();
    }

    private function resolveBrand(): string
    {
        $configured = trim((string) config('services.aliexpress.brand_name', ''));
        $norm = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $configured));
        if ($configured !== '' && $norm !== '5CORE') {
            return mb_substr($configured, 0, 80);
        }
        $brand = trim((string) config('listing_manager.default_brand', '5 Core Inc.'));

        return mb_substr($brand !== '' ? $brand : '5 Core Inc.', 0, 80);
    }

    private function resolveDescription(ProductMaster $product, string $title): string
    {
        $html = trim((string) ($product->description_html ?? ''));
        if ($html !== '') {
            return $html;
        }
        foreach (['description_1500', 'description_1000', 'description_800', 'description_600', 'product_description'] as $col) {
            $text = trim((string) ($product->{$col} ?? ''));
            if ($text !== '') {
                return nl2br(e($text), false);
            }
        }
        $bullets = [];
        for ($i = 1; $i <= 5; $i++) {
            $b = trim((string) ($product->{'bullet'.$i} ?? ''));
            if ($b !== '') {
                $bullets[] = $b;
            }
        }
        if ($bullets !== []) {
            $out = '<p>'.e($title).'</p><ul>';
            foreach ($bullets as $b) {
                $out .= '<li>'.e($b).'</li>';
            }

            return $out.'</ul>';
        }

        return '<p>'.e($title).'</p>';
    }

    /**
     * @return array{language: string, web_detail: string, mobile_detail: string}
     */
    private function descriptionModules(string $html): array
    {
        $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) ?? '');
        if ($plain === '') {
            $plain = 'Product details';
        }

        return [
            'language' => 'en',
            'web_detail' => json_encode([
                'moduleList' => [
                    ['type' => 'html', 'html' => ['content' => $html]],
                ],
                'version' => '2.0.0',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'mobile_detail' => json_encode([
                'moduleList' => [
                    ['type' => 'text', 'texts' => ['content' => mb_substr($plain, 0, 2000), 'class' => 'body']],
                ],
                'version' => '2.0.0',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function resolveTitle(ProductMaster $product, string $sku): string
    {
        foreach (['title80', 'title100', 'title150', 'title60'] as $field) {
            $title = trim((string) ($product->{$field} ?? ''));
            if ($title !== '') {
                return $title;
            }
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return trim((string) ($shopify->product_title ?? $shopify->title ?? $product->parent ?? $sku));
    }

    private function resolvePrice(string $sku, ProductMaster $product): ?float
    {
        if (Schema::hasTable('aliexpress_pricing_prices')) {
            $row = AliexpressPricingPrice::query()->where('sku', $sku)->first()
                ?: AliexpressPricingPrice::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->first();
            if ($row && is_numeric($row->price) && (float) $row->price > 0) {
                return round((float) $row->price, 2);
            }
        }

        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        $price = (float) ($shopify->price ?? $shopify->b2c_price ?? 0);
        if ($price > 0) {
            return round($price, 2);
        }

        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['lp', 'LP', 'sprice', 'SPRICE', 'price'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                return round((float) $values[$key], 2);
            }
        }

        return null;
    }

    private function shopifyInv(string $sku): int
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return (int) ($shopify->available_to_sell ?? $shopify->inv ?? 0);
    }

    /**
     * @return array{length: int, width: int, height: int, weight: string}
     */
    private function packageSize(ProductMaster $product): array
    {
        $num = function ($raw, float $fallback): float {
            if (is_numeric($raw) && (float) $raw > 0) {
                return (float) $raw;
            }

            return $fallback;
        };

        return [
            'length' => (int) max(1, round($num($product->length ?? $product->package_length ?? null, 10))),
            'width' => (int) max(1, round($num($product->width ?? $product->package_width ?? null, 10))),
            'height' => (int) max(1, round($num($product->height ?? $product->package_height ?? null, 10))),
            'weight' => number_format($num($product->weight ?? $product->package_weight ?? null, 0.5), 2, '.', ''),
        ];
    }

    /**
     * @return list<string>
     */
    private function productImages(ProductMaster $product): array
    {
        $urls = [];
        $push = function (string $raw) use (&$urls): void {
            $raw = trim($raw);
            if ($raw === '' || in_array($raw, $urls, true)) {
                return;
            }
            if (! preg_match('#^https?://#i', $raw)) {
                return;
            }
            $urls[] = $raw;
        };

        $push((string) ($product->main_image ?? ''));
        $push((string) ($product->main_image_brand ?? ''));
        for ($i = 1; $i <= 19; $i++) {
            $push((string) ($product->{'image'.$i} ?? ''));
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['image_path', 'image', 'Image', 'main_image', 'photo'] as $key) {
            $raw = $values[$key] ?? '';
            $push(is_array($raw) ? (string) ($raw[0]['url'] ?? $raw[0] ?? '') : (string) $raw);
        }

        return $urls;
    }

    /**
     * @param  list<array{sku: string, price: float, inv: int}>  $prepared
     */
    private function persistListed(array $prepared, string $productId): void
    {
        foreach ($prepared as $row) {
            $sku = trim((string) $row['sku']);
            if ($sku === '') {
                continue;
            }
            try {
                if ($productId !== '' && Schema::hasTable('aliexpress_metric')) {
                    $payload = [
                        'price' => $row['price'],
                    ];
                    if (Schema::hasColumn('aliexpress_metric', 'listing_status')) {
                        $payload['listing_status'] = 'auditing';
                    }
                    AliexpressMetric::updateOrCreate(
                        ['product_id' => $productId, 'sku' => $sku],
                        $payload
                    );
                }
                if (Schema::hasTable('aliexpress_pricing_prices')) {
                    AliexpressPricingPrice::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'price' => $row['price'],
                            'ae_stock' => max(0, (int) $row['inv']),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('AliExpress persist listed failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function forgetListingCaches(): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            Cache::forget('listing_channel_counts_v1:aliexpress');
            Cache::forget(AliexpressLiveListingsService::CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    private function groupKey(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));

        return $parent !== '' ? $parent : trim((string) $product->sku);
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function uniqueSkus(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && ! in_array($sku, $out, true)) {
                $out[] = $sku;
            }
        }

        return $out;
    }
}
