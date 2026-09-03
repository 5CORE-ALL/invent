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

        $products = $this->findProductsBySkus($publishSkus);

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
            $images = $this->productImages($product, $sku);
            if ($images === []) {
                return ['success' => false, 'message' => 'No images on Image Master for '.$sku.'. Add photos on Image Master, then publish again.'];
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

        $upload = $this->api->uploadImagesToPhotobank($gallery);
        if (empty($upload['success']) || ($upload['urls'] ?? []) === []) {
            return [
                'success' => false,
                'message' => $upload['message'] ?? 'Could not upload Image Master photos to AliExpress photobank.',
            ];
        }
        $hostedBySource = [];
        foreach (array_values($gallery) as $i => $source) {
            if (isset($upload['urls'][$i])) {
                $hostedBySource[$source] = $upload['urls'][$i];
            }
        }
        $gallery = array_values($upload['urls']);
        foreach ($prepared as $i => $row) {
            $mapped = [];
            foreach ($row['images'] as $src) {
                $mapped[] = $hostedBySource[$src] ?? ($gallery[0] ?? $src);
            }
            $prepared[$i]['images'] = $mapped !== [] ? $mapped : $gallery;
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

        $pkg = $this->packageSize($primary, $primarySku);
        if (empty($pkg['has_weight'])) {
            foreach ($prepared as $row) {
                $try = $this->packageSize($row['product'], $row['sku']);
                if (! empty($try['has_weight'])) {
                    $pkg = $try;
                    break;
                }
            }
        }
        if (empty($pkg['has_weight'])) {
            return [
                'success' => false,
                'message' => $primarySku.': Package weight is missing on Dim/Wt Master. Add Itm wt GW (or kg) on /dim-wt-master, then publish again.',
            ];
        }
        $subject = mb_substr($title, 0, 128);
        $description = $this->resolveDescription($primary, $subject);
        $variation = count($prepared) > 1;
        $skuInfoList = [];
        foreach ($prepared as $row) {
            $skuRow = array_merge([
                'sku_code' => $row['sku'],
                'price' => number_format((float) $row['price'], 2, '.', ''),
                'inventory' => max(1, (int) $row['inv']),
            ], $this->skuPackageFields($pkg));
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
            'package_length' => (int) $pkg['length'],
            'package_width' => (int) $pkg['width'],
            'package_height' => (int) $pkg['height'],
            'freight_template_id' => (int) $freightId,
            'service_policy_id' => (int) config('services.aliexpress.service_policy_id', 0),
        ];
        $request = array_merge($request, $this->productPackageFields($pkg));

        Log::info('AliExpress publish: sending product.post', [
            'parent' => $parentKey,
            'skus' => $publishSkus,
            'category_id' => $categoryId,
            'category_path' => (string) ($resolved['path'] ?? ''),
            'freight_template_id' => $freightId,
            'mode' => $mode,
            'weight_kg' => $pkg['weight'],
            'package_cm' => $pkg['length'].'x'.$pkg['width'].'x'.$pkg['height'],
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
            if (! $product || $this->productImages($product, $sku) === []) {
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
        $images = $this->productImages($product, $sku);

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
        return '5 Core Inc.';
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
     * Official package fields plus the US schema name aeLogisticsWeight.
     *
     * @param  array<string, mixed>  $pkg
     * @return array<string, mixed>
     */
    private function productPackageFields(array $pkg): array
    {
        $kg = (string) $pkg['weight'];
        $kgNum = (float) $kg;

        return [
            'weight' => $kg,
            'weight_lb' => (string) ($pkg['weight_lb'] ?? ''),
            'package_weight' => $kgNum,
            'gross_weight' => $kg,
            'aeLogisticsWeight' => $kgNum,
            'usLogisticsWeight' => $kgNum,
            'usl' => ['logisticsWeight' => $kgNum],
            'usl.logisticsWeight' => $kgNum,
            'category_attributes' => [
                'aeLogisticsWeight' => ['value' => $kgNum],
                'Package weight' => ['value' => $kgNum],
            ],
            'attribute_list' => [
                [
                    'aliexpress_attribute_name_id' => 2,
                    'attribute_name' => 'Brand Name',
                    'attribute_value' => $this->resolveBrand(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $pkg
     * @return array<string, mixed>
     */
    private function skuPackageFields(array $pkg): array
    {
        $kg = (string) $pkg['weight'];

        return [
            'weight' => $kg,
            'package_weight' => (float) $kg,
            'gross_weight' => $kg,
            'aeLogisticsWeight' => (float) $kg,
            'usLogisticsWeight' => (float) $kg,
        ];
    }

    private function packageSize(ProductMaster $product, string $sku = ''): array
    {
        $sku = trim($sku !== '' ? $sku : (string) $product->sku);
        $pkg = $this->dimWtMasterPackageForSku($sku, $product);

        $weightKg = null;
        if (($pkg['weight_kg'] ?? null) !== null) {
            $weightKg = (float) $pkg['weight_kg'];
        } elseif (($pkg['weight_lb'] ?? null) !== null) {
            $weightKg = (float) $pkg['weight_lb'] * 0.45359237;
        }

        $toCm = static function (?float $cm, ?float $inches, int $fallback): int {
            if ($cm !== null && $cm > 0) {
                return (int) max(1, round($cm));
            }
            if ($inches !== null && $inches > 0) {
                return (int) max(1, round($inches * 2.54));
            }

            return $fallback;
        };

        $hasWeight = $weightKg !== null && $weightKg > 0;
        $weightLb = $hasWeight
            ? (($pkg['weight_lb'] ?? null) !== null
                ? (float) $pkg['weight_lb']
                : $weightKg / 0.45359237)
            : null;

        return [
            'length' => $toCm($pkg['length_cm'] ?? null, $pkg['length_in'] ?? null, 10),
            'width' => $toCm($pkg['width_cm'] ?? null, $pkg['width_in'] ?? null, 10),
            'height' => $toCm($pkg['height_cm'] ?? null, $pkg['height_in'] ?? null, 10),
            'weight' => $hasWeight
                ? number_format(max(0.001, min(500, $weightKg)), 3, '.', '')
                : '',
            'weight_lb' => $weightLb !== null
                ? number_format(max(0.001, $weightLb), 3, '.', '')
                : '',
            'has_weight' => $hasWeight,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dimWtMasterValues(?ProductMaster $product): array
    {
        if (! $product) {
            return [];
        }
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        return is_array($values) ? $values : [];
    }

    private function dimWtMasterNumber(array $values, string ...$keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                continue;
            }
            $raw = $values[$key];
            if (is_string($raw)) {
                $raw = trim(str_replace(',', '', $raw));
            }
            if (! is_numeric($raw)) {
                continue;
            }
            $n = (float) $raw;
            if ($n > 0) {
                return $n;
            }
        }

        return null;
    }

    /**
     * @return array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}
     */
    private function dimWtMasterItemPackage(array $values): array
    {
        return [
            // /dim-wt-master Item L/W/H IN, then Decl
            'length_in' => $this->dimWtMasterNumber($values, 'l', 'l_decl'),
            'width_in' => $this->dimWtMasterNumber($values, 'w', 'w_decl'),
            'height_in' => $this->dimWtMasterNumber($values, 'h', 'h_decl'),
            // /dim-wt-master "Itm wt GW" (lb), then Decl
            'weight_lb' => $this->dimWtMasterNumber($values, 'wt_act', 'itm_wt_gw', 'wt_decl'),
            // /dim-wt-master "Wt ACT (Kg)" when filled
            'weight_kg' => $this->dimWtMasterNumber($values, 'wt_act_kg'),
            // /dim-wt-master Item L/W/H CM
            'length_cm' => $this->dimWtMasterNumber($values, 'l_cm'),
            'width_cm' => $this->dimWtMasterNumber($values, 'w_cm'),
            'height_cm' => $this->dimWtMasterNumber($values, 'h_cm'),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $fill
     * @return array<string, mixed>
     */
    private function mergeDimWtMasterPackage(array $base, array $fill): array
    {
        foreach ($base as $key => $value) {
            if ($value === null && ($fill[$key] ?? null) !== null) {
                $base[$key] = $fill[$key];
            }
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $pkg
     */
    private function dimWtMasterHasWeight(array $pkg): bool
    {
        return ($pkg['weight_lb'] ?? null) !== null || ($pkg['weight_kg'] ?? null) !== null;
    }

    /**
     * Load /dim-wt-master item package for this SKU, then fill blanks from the parent row.
     *
     * @return array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}
     */
    private function dimWtMasterPackageForSku(string $sku, ?ProductMaster $hint = null): array
    {
        $sku = trim($sku);
        $row = $hint;
        if (! $row || $this->normalizeSkuKey((string) $row->sku) !== $this->normalizeSkuKey($sku)) {
            $row = $this->findProductLoose($sku) ?? $hint;
        }
        $pkg = $this->dimWtMasterItemPackage($this->dimWtMasterValues($row));
        if ($this->dimWtMasterHasWeight($pkg) || ! $row) {
            return $pkg;
        }
        $parent = $this->parentProductFor($row, $sku);
        if ($parent) {
            $pkg = $this->mergeDimWtMasterPackage(
                $pkg,
                $this->dimWtMasterItemPackage($this->dimWtMasterValues($parent))
            );
        }

        return $pkg;
    }

    private function normalizeSkuKey(string $sku): string
    {
        $sku = strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return trim($sku);
    }

    /**
     * @param  list<string>  $skus
     * @return \Illuminate\Support\Collection<string, ProductMaster>
     */
    private function findProductsBySkus(array $skus): \Illuminate\Support\Collection
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $key = $this->normalizeSkuKey((string) $sku);
            if ($key !== '') {
                $wanted[$key] = (string) $sku;
            }
        }
        if ($wanted === []) {
            return collect();
        }

        $rows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($wanted) {
                $query->whereIn('sku', array_values($wanted));
                foreach (array_keys($wanted) as $key) {
                    $query->orWhereRaw('UPPER(TRIM(sku)) = ?', [$key]);
                }
            })
            ->get();

        $byNorm = [];
        foreach ($rows as $row) {
            $byNorm[$this->normalizeSkuKey((string) $row->sku)] = $row;
        }

        $out = collect();
        foreach ($skus as $sku) {
            $hit = $byNorm[$this->normalizeSkuKey((string) $sku)] ?? null;
            if ($hit) {
                $out[(string) $sku] = $hit;
            }
        }

        return $out;
    }

    private function findProductLoose(string $sku): ?ProductMaster
    {
        return $this->findProductsBySkus([$sku])->get($sku);
    }

    private function parentProductFor(ProductMaster $product, string $sku): ?ProductMaster
    {
        $parentKey = $this->groupKey($product);
        if ($parentKey === '' || strcasecmp($parentKey, trim($sku)) === 0) {
            return null;
        }

        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parentKey)
            ->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['PARENT%'])
            ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('sku', $parentKey)
                ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($parentKey)])
                ->first();
    }

    /**
     * Image Master gallery: image1–image20, then parent, then saved AliExpress image_master_json.
     *
     * @return list<string>
     */
    private function productImages(ProductMaster $product, string $sku = ''): array
    {
        $urls = [];
        $push = function (string $raw) use (&$urls): void {
            foreach ($this->splitImageValues($raw) as $one) {
                $url = $this->absoluteImageUrl($one);
                if ($url === '' || in_array($url, $urls, true)) {
                    continue;
                }
                $urls[] = $url;
            }
        };

        $this->pushImageMasterGallery($product, $push);

        $childSku = $sku !== '' ? $sku : trim((string) $product->sku);
        $parentSku = trim((string) ($product->parent ?? ''));
        if ($parentSku !== '' && strcasecmp($parentSku, $childSku) !== 0) {
            $parent = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('sku', $parentSku)
                ->first()
                ?: ProductMaster::query()
                    ->whereNull('deleted_at')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($parentSku)])
                    ->first();
            if ($parent) {
                $this->pushImageMasterGallery($parent, $push);
            }
        }

        if ($childSku !== '' && Schema::hasTable('aliexpress_metric') && Schema::hasColumn('aliexpress_metric', 'image_master_json')) {
            $raw = AliexpressMetric::query()
                ->where('sku', $childSku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($childSku)])
                ->value('image_master_json');
            foreach ($this->decodeImageList($raw) as $url) {
                $push($url);
            }
        }

        return array_slice($urls, 0, 6);
    }

    /**
     * @param  callable(string): void  $push
     */
    private function pushImageMasterGallery(ProductMaster $product, callable $push): void
    {
        $fromSlots = false;
        for ($i = 1; $i <= 20; $i++) {
            $value = trim((string) ($product->{'image'.$i} ?? ''));
            if ($value !== '') {
                $fromSlots = true;
                $push($value);
            }
        }
        if (! $fromSlots) {
            $push((string) ($product->main_image ?? ''));
        }
    }

    /**
     * @return list<string>
     */
    private function splitImageValues(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if (str_starts_with($raw, '[') || str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->decodeImageList($decoded);
            }
        }

        return [$raw];
    }

    /**
     * @return list<string>
     */
    private function decodeImageList(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $text = trim((string) $raw);
            if ($text === '') {
                return [];
            }
            $decoded = json_decode($text, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = $item['url'] ?? $item['src'] ?? $item['image'] ?? '';
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function absoluteImageUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, '//')) {
            return 'https:'.$raw;
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            return '';
        }

        return str_starts_with($raw, '/') ? $base.$raw : $base.'/'.ltrim($raw, '/');
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
