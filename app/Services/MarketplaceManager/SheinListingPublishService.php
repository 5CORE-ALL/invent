<?php

namespace App\Services\MarketplaceManager;

use App\Models\ListingManagerChannelDraft;
use App\Models\ProductMaster;
use App\Models\SheinMetric;
use App\Models\ShopifySku;
use App\Services\SheinApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs from the Shein listing page via publishOrEdit.
 */
class SheinListingPublishService
{
    public function __construct(private SheinApiService $api)
    {
    }

    /**
     * @return array{id: string, path: string, name: string}
     */
    public function suggestCategoryForSku(string $sku): array
    {
        return $this->resolveCategory($this->uniqueSkus([$sku]), null, null);
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(
        array $skus,
        bool $expandSiblings = true,
        string $mode = 'variation',
        string $parentHint = '',
        ?int $categoryId = null,
        ?string $categoryName = null,
        ?float $weightLb = null
    ): array {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Shein is not connected. Set SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY, then try Publish again.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($expandSiblings && $mode === 'variation') {
            $publishSkus = $this->expandToPublishableSiblings($skus);
        } else {
            $publishSkus = $this->filterPublishable($skus);
        }
        if ($publishSkus === []) {
            return ['success' => false, 'message' => $this->publishBlockReason($skus)];
        }

        if ($mode === 'single' && count($publishSkus) > 1) {
            return $this->publishEachAsSingle($publishSkus, $parentHint, $categoryId, $categoryName, $weightLb);
        }

        $primarySku = $publishSkus[0];
        $product = $this->findProduct($primarySku);
        if (! $product) {
            return ['success' => false, 'message' => 'SKU not found in product master: '.$primarySku];
        }

        $hydrated = ListingManagerAmazonHydrator::hydrate($primarySku, false);
        $details = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], 'shein');
        $title = $this->clipTitle($this->resolveTitle($product, $primarySku, $hydrated));
        if ($title === '') {
            return ['success' => false, 'message' => $primarySku.': Title missing in Title Master'];
        }

        $description = trim(strip_tags((string) ($details['description'] ?? $hydrated['description'] ?? '')));
        if ($description === '') {
            $description = $title;
        }
        if (mb_strlen($description) > 500) {
            $description = rtrim(mb_substr($description, 0, 500), " \t-–,.");
        }

        $images = $this->publicImages($details['images'] ?? $hydrated['images'] ?? [], $product, $primarySku);
        if ($images === []) {
            return [
                'success' => false,
                'message' => 'No public image URL for '.$primarySku.'. Add an https image on CP Master (or Image Master).',
            ];
        }

        $category = $this->resolveCategory($publishSkus, $categoryId, $categoryName);
        if ($category['id'] === '' || (int) ($category['product_type_id'] ?? 0) <= 0) {
            return [
                'success' => false,
                'message' => 'Shein category is required for '.$primarySku.'. Type a leaf category in the publish window, or list a sibling first so we can copy its category.',
            ];
        }

        $warehouseId = $this->api->listingWarehouseId();
        if ($warehouseId === null || $warehouseId === '') {
            return [
                'success' => false,
                'message' => 'Shein warehouse is missing. Set SHEIN_WAREHOUSE_CODE or confirm the seller account has a default warehouse.',
            ];
        }

        $brandCode = trim((string) ($category['brand_code'] ?? ''));
        if ($brandCode === '') {
            $brandCode = $this->api->listingBrandCode();
        }
        if ($brandCode === '') {
            return [
                'success' => false,
                'message' => 'Shein brand_code is missing. Set SHEIN_BRAND_CODE or confirm query-brand-list is authorized.',
            ];
        }

        $price = $this->resolvePrice($primarySku, $hydrated);
        if ($price === null || $price <= 0) {
            return ['success' => false, 'message' => 'No price found for '.$primarySku.'. Set Shopify / Amazon price first.'];
        }

        $hostedImages = $this->api->uploadListingImages($images);
        if ($hostedImages === []) {
            return [
                'success' => false,
                'message' => 'Shein image upload failed for '.$primarySku.'. Check that CP Master images are public https URLs.',
            ];
        }

        $skuRows = $mode === 'variation' && count($publishSkus) > 1
            ? $publishSkus
            : [$primarySku];

        $weightGrams = $this->resolveWeightGrams($details, $hydrated, $weightLb);
        $dims = $this->resolveDimensionsCm($details, $hydrated);
        $subSite = trim((string) config('services.shein.sub_site', 'shein-us')) ?: 'shein-us';
        $currency = trim((string) config('services.shein.currency', 'USD')) ?: 'USD';

        $template = $this->api->listingAttributeTemplate((int) $category['product_type_id']);
        $productAttrs = $this->productAttributePayload($template['product'] ?? []);
        $skcList = $this->buildSkcList(
            $skuRows,
            $hostedImages,
            $warehouseId,
            $weightGrams,
            $dims,
            $subSite,
            $currency,
            $template['sale'] ?? []
        );
        if ($skcList === []) {
            return [
                'success' => false,
                'message' => 'Could not build Shein SKU rows for '.$primarySku.'. The category may need sale attributes we cannot fill.',
            ];
        }

        $payload = [
            'brand_code' => $brandCode,
            'category_id' => (int) $category['id'],
            'edit_type' => 0,
            'product_type_id' => (int) $category['product_type_id'],
            'multi_language_name_list' => [['language' => 'en', 'name' => $title]],
            'multi_language_desc_list' => [['language' => 'en', 'name' => $description]],
            'site_list' => [[
                'main_site' => 'shein',
                'sub_site_list' => [$subSite],
            ]],
            'skc_list' => $skcList,
        ];
        if ($productAttrs !== []) {
            $payload['product_attribute_list'] = $productAttrs;
        }

        Log::info('Shein listing publish: publishOrEdit', [
            'sku' => $primarySku,
            'mode' => $mode,
            'category_id' => $category['id'],
            'product_type_id' => $category['product_type_id'],
            'image_count' => count($hostedImages),
            'sku_count' => count($skuRows),
        ]);

        $result = $this->api->publishOrEditProduct($payload);
        if (empty($result['success'])) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Shein rejected publishOrEdit.',
            ];
        }

        $spu = trim((string) ($result['spu_name'] ?? ''));
        $skuCode = trim((string) ($result['sku_code'] ?? ''));
        $this->persistListed($skuRows, $spu, $skuCode, $price, $title, $category['path'] ?? '');
        $this->forgetListingCaches();

        return [
            'success' => true,
            'message' => $result['message'] ?? ('Published '.$primarySku.' to Shein.'),
            'goods_id' => $spu !== '' ? $spu : ($skuCode !== '' ? $skuCode : null),
            'sku_id' => $skuCode !== '' ? $skuCode : null,
            'skus' => $skuRows,
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    private function publishEachAsSingle(
        array $skus,
        string $parentHint,
        ?int $categoryId,
        ?string $categoryName,
        ?float $weightLb
    ): array {
        $ok = [];
        $fail = [];
        $listed = [];
        $lastId = null;
        foreach ($skus as $sku) {
            $one = $this->publishSkus([$sku], false, 'single', $parentHint, $categoryId, $categoryName, $weightLb);
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
            'sku_id' => $lastId,
            'skus' => array_values(array_unique($listed)),
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array{id: string, path: string, name: string, product_type_id?: int, brand_code?: string}
     */
    private function resolveCategory(array $skus, ?int $categoryId, ?string $categoryName): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => '', 'product_type_id' => 0, 'brand_code' => ''];
        $explicitId = $categoryId !== null && $categoryId > 0 ? $categoryId : 0;
        $name = trim((string) $categoryName);
        if ($explicitId <= 0 && preg_match('/^\d{3,}$/', $name)) {
            $explicitId = (int) $name;
            $name = '';
        }

        if ($explicitId > 0) {
            $leaf = $this->api->findListingCategory($explicitId);
            if ($leaf) {
                return [
                    'id' => $leaf['id'],
                    'path' => $name !== '' ? $name : $leaf['path'],
                    'name' => $leaf['name'],
                    'product_type_id' => (int) ($leaf['product_type_id'] ?? 0),
                    'brand_code' => '',
                ];
            }
        }

        if ($name !== '') {
            $fromName = $this->categoryFromSearch($name);
            if ($fromName['id'] !== '') {
                return $fromName;
            }
        }

        $fromSibling = $this->categoryFromListedSibling($skus);
        if ($fromSibling['id'] !== '') {
            return $fromSibling;
        }

        $fromDraft = $this->categoryFromDraft($skus);
        if ($fromDraft['id'] !== '') {
            return $fromDraft;
        }

        $title = '';
        foreach ($skus as $sku) {
            $product = $this->findProduct($sku);
            if (! $product) {
                continue;
            }
            $title = $this->resolveTitle($product, $sku, ListingManagerAmazonHydrator::hydrate($sku, false));
            if ($title !== '') {
                break;
            }
        }
        if ($title !== '') {
            $fromTitle = $this->categoryFromSearch($title);
            if ($fromTitle['id'] !== '') {
                return $fromTitle;
            }
        }

        return $empty;
    }

    /**
     * @return array{id: string, path: string, name: string, product_type_id: int, brand_code: string}
     */
    private function categoryFromSearch(string $query): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => '', 'product_type_id' => 0, 'brand_code' => ''];
        try {
            $result = $this->api->searchListingCategories($query, $query);
        } catch (\Throwable $e) {
            Log::warning('Shein listing publish: category search failed', ['q' => $query, 'error' => $e->getMessage()]);

            return $empty;
        }
        $row = $result['categories'][0] ?? null;
        if (! is_array($row) || trim((string) ($row['id'] ?? '')) === '') {
            return $empty;
        }
        $path = trim((string) ($row['path'] ?? ''));

        return [
            'id' => (string) $row['id'],
            'path' => $path !== '' ? $path : 'Category '.$row['id'],
            'name' => $path !== '' ? (string) preg_replace('/^.*(?: - |>|\/)\s*/', '', $path) : '',
            'product_type_id' => (int) ($row['product_type_id'] ?? 0),
            'brand_code' => '',
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array{id: string, path: string, name: string, product_type_id: int, brand_code: string}
     */
    private function categoryFromListedSibling(array $skus): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => '', 'product_type_id' => 0, 'brand_code' => ''];
        $candidates = $skus;
        foreach ($skus as $sku) {
            $product = $this->findProduct($sku);
            if (! $product) {
                continue;
            }
            foreach (ListingManagerFamily::siblingSkus($this->groupKey($product), $sku) as $sibling) {
                $candidates[] = $sibling;
            }
        }
        $candidates = $this->uniqueSkus($candidates);
        if ($candidates === [] || ! Schema::hasTable('shein_metrics')) {
            return $empty;
        }

        $rows = SheinMetric::query()
            ->whereIn('sku', $candidates)
            ->where(function ($q) {
                $q->where('price', '>', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('shein_sku_code')->where('shein_sku_code', '!=', '');
                    });
            })
            ->get(['sku', 'shein_sku_code', 'category', 'raw_data']);

        foreach ($rows as $row) {
            $code = trim((string) ($row->shein_sku_code ?? ''));
            $lookup = $code !== '' ? $code : trim((string) $row->sku);
            $tax = $this->api->taxonomyFromListedProduct($lookup);
            $categoryId = (int) ($tax['category_id'] ?? 0);
            $productTypeId = (int) ($tax['product_type_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            if ($productTypeId <= 0) {
                $leaf = $this->api->findListingCategory($categoryId);
                $productTypeId = (int) ($leaf['product_type_id'] ?? 0);
            }
            $path = trim((string) ($tax['path'] ?: $row->category ?: ''));

            return [
                'id' => (string) $categoryId,
                'path' => ($path !== '' ? $path : 'Category '.$categoryId).' (from a listed sibling)',
                'name' => $path,
                'product_type_id' => $productTypeId,
                'brand_code' => trim((string) ($tax['brand_code'] ?? '')),
            ];
        }

        return $empty;
    }

    /**
     * @param  list<string>  $skus
     * @return array{id: string, path: string, name: string, product_type_id: int, brand_code: string}
     */
    private function categoryFromDraft(array $skus): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => '', 'product_type_id' => 0, 'brand_code' => ''];
        if (! Schema::hasTable('listing_manager_channel_drafts') || ! Schema::hasTable('channel_master')) {
            return $empty;
        }

        $rows = ListingManagerChannelDraft::query()
            ->join('channel_master', 'channel_master.id', '=', 'listing_manager_channel_drafts.channel_id')
            ->whereIn('listing_manager_channel_drafts.seller_sku', $skus)
            ->get(['listing_manager_channel_drafts.listing_details', 'channel_master.channel']);

        foreach ($rows as $row) {
            $key = ListingChannelCounts::normalize((string) $row->channel);
            if ($key !== 'shein') {
                continue;
            }
            $raw = $row->listing_details;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
            $details = is_array($raw) ? $raw : [];
            $id = (int) preg_replace('/\D+/', '', (string) ($details['primary_category_id'] ?? $details['category_id'] ?? ''));
            $path = trim((string) ($details['primary_category_path'] ?? $details['category_name'] ?? $details['category'] ?? ''));
            $productTypeId = (int) ($details['product_type_id'] ?? $details['productTypeId'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($productTypeId <= 0) {
                $leaf = $this->api->findListingCategory($id);
                $productTypeId = (int) ($leaf['product_type_id'] ?? 0);
                if ($path === '') {
                    $path = (string) ($leaf['path'] ?? '');
                }
            }

            return [
                'id' => (string) $id,
                'path' => $path !== '' ? $path.' (from Listing Manager)' : 'Category '.$id.' (from Listing Manager)',
                'name' => $path,
                'product_type_id' => $productTypeId,
                'brand_code' => trim((string) ($details['brand_code'] ?? '')),
            ];
        }

        return $empty;
    }

    /**
     * @param  list<string>  $skuRows
     * @param  list<array{image_sort: int, image_type: string, image_url: string}>  $hostedImages
     * @param  array{length: string, width: string, height: string}  $dims
     * @param  list<array<string, mixed>>  $saleAttrs
     * @return list<array<string, mixed>>
     */
    private function buildSkcList(
        array $skuRows,
        array $hostedImages,
        string $warehouseId,
        int $weightGrams,
        array $dims,
        string $subSite,
        string $currency,
        array $saleAttrs
    ): array {
        $mainSale = $this->pickSaleAttribute($saleAttrs, true);
        $subSale = $this->pickSaleAttribute($saleAttrs, false);
        $mainValues = $this->attributeValues($mainSale);
        $subValues = $this->attributeValues($subSale);

        $out = [];
        foreach ($skuRows as $i => $sku) {
            $childHydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            $childPrice = $this->resolvePrice($sku, $childHydrated);
            if ($childPrice === null || $childPrice <= 0) {
                continue;
            }
            $qty = max(1, $this->resolveQuantity($sku, $childHydrated));
            $skuPayload = [
                'supplier_sku' => $sku,
                'height' => $dims['height'],
                'length' => $dims['length'],
                'width' => $dims['width'],
                'weight' => (string) $weightGrams,
                'mall_state' => 1,
                'stop_purchase' => 1,
                'stock_info_list' => [[
                    'inventory_num' => $qty,
                    'supplier_warehouse_id' => $warehouseId,
                ]],
                'price_info_list' => [[
                    'base_price' => $childPrice,
                    'currency' => $currency,
                    'sub_site' => $subSite,
                ]],
            ];
            if ($subSale && $subValues !== []) {
                $skuPayload['sale_attribute_list'] = [[
                    'attribute_id' => (int) ($subSale['attribute_id'] ?? $subSale['attributeId'] ?? 0),
                    'attribute_value_id' => (int) ($subValues[min($i, count($subValues) - 1)]['id'] ?? 0),
                ]];
            }

            $skc = [
                'image_info' => ['image_info_list' => $hostedImages],
                'sku_list' => [$skuPayload],
            ];
            if ($mainSale && $mainValues !== []) {
                $skc['sale_attribute'] = [
                    'attribute_id' => (int) ($mainSale['attribute_id'] ?? $mainSale['attributeId'] ?? 0),
                    'attribute_value_id' => (int) ($mainValues[min($i, count($mainValues) - 1)]['id'] ?? 0),
                ];
            }
            $out[] = $skc;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $saleAttrs
     * @return array<string, mixed>|null
     */
    private function pickSaleAttribute(array $saleAttrs, bool $main): ?array
    {
        $mainHits = [];
        $other = [];
        foreach ($saleAttrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $label = (int) ($attr['attribute_label'] ?? $attr['attributeLabel'] ?? 0);
            if ($label === 1) {
                $mainHits[] = $attr;
            } else {
                $other[] = $attr;
            }
        }
        if ($main) {
            return $mainHits[0] ?? $other[0] ?? null;
        }

        return $other[0] ?? ($mainHits[1] ?? null);
    }

    /**
     * @param  array<string, mixed>|null  $attr
     * @return list<array{id: int, name: string}>
     */
    private function attributeValues(?array $attr): array
    {
        if (! is_array($attr)) {
            return [];
        }
        $list = $attr['attribute_value_info_list'] ?? $attr['attributeValueInfoList'] ?? [];
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['attribute_value_id'] ?? $row['attributeValueId'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => trim((string) ($row['attribute_value_en'] ?? $row['attribute_value'] ?? $row['attributeValue'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $attrs
     * @return list<array{attribute_id: int, attribute_value_id: int}>
     */
    private function productAttributePayload(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $attr) {
            $values = $this->attributeValues($attr);
            $attrId = (int) ($attr['attribute_id'] ?? $attr['attributeId'] ?? 0);
            $valueId = (int) ($values[0]['id'] ?? 0);
            if ($attrId <= 0 || $valueId <= 0) {
                continue;
            }
            $out[] = [
                'attribute_id' => $attrId,
                'attribute_value_id' => $valueId,
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandToPublishableSiblings(array $seedSkus): array
    {
        $seeds = ProductMaster::query()->whereNull('deleted_at')->whereIn('sku', $seedSkus)->get();
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
        $cfg = ChannelListingRegistry::get('shein');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $dataView = $cfg['dataView'] ?? null;
        $nrValues = ($dataView && class_exists($dataView))
            ? ListingCountsEngine::loadNrValues($dataView, $skus)
            : collect();
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
            if (trim((string) ($listedMap[strtolower($sku)] ?? '')) !== '') {
                continue;
            }
            if (ListingCountsEngine::nrReqFromDataView($nrValues->get(strtoupper($sku))) === 'NR') {
                continue;
            }
            $product = $products->get(strtolower($sku));
            if (! $product) {
                continue;
            }
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            if ($this->publicImages($hydrated['images'] ?? [], $product, $sku) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $hydrated
     */
    private function resolveTitle(ProductMaster $product, string $sku, array $hydrated = []): string
    {
        foreach (['title80', 'title100', 'title150', 'title60'] as $field) {
            $title = trim((string) ($product->{$field} ?? ''));
            if ($title !== '') {
                return $title;
            }
        }
        $fromHydrate = trim((string) ($hydrated['title'] ?? ''));
        if ($fromHydrate !== '') {
            return $fromHydrate;
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return trim((string) ($shopify->product_title ?? $shopify->title ?? $product->parent ?? $sku));
    }

    /**
     * @param  array<string, mixed>  $hydrated
     */
    private function resolvePrice(string $sku, array $hydrated = []): ?float
    {
        $price = isset($hydrated['price']) ? (float) $hydrated['price'] : 0.0;
        if ($price > 0) {
            return round($price, 2);
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        $shopifyPrice = (float) ($shopify->price ?? $shopify->b2c_price ?? 0);

        return $shopifyPrice > 0 ? round($shopifyPrice, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $hydrated
     */
    private function resolveQuantity(string $sku, array $hydrated = []): int
    {
        $live = ListingManagerAmazonHydrator::shopifyQuantity($sku, false);
        if ($live !== null) {
            return max(0, $live);
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return max(0, (int) ($shopify->available_to_sell ?? $shopify->inv ?? $hydrated['quantity'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $hydrated
     */
    private function resolveWeightGrams(array $details, array $hydrated, ?float $weightLb): int
    {
        $lb = $weightLb !== null && $weightLb > 0 ? $weightLb : 0.0;
        if ($lb <= 0) {
            $lb = (float) ($details['package_weight_lb'] ?? $hydrated['package_weight_lb'] ?? 0);
            $oz = (float) ($details['package_weight_oz'] ?? $hydrated['package_weight_oz'] ?? 0);
            $lb += $oz / 16;
        }
        if ($lb <= 0) {
            $lb = 1.0;
        }

        return max(1, (int) round($lb * 453.592));
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $hydrated
     * @return array{length: string, width: string, height: string}
     */
    private function resolveDimensionsCm(array $details, array $hydrated): array
    {
        $toCm = static function (float $inches): string {
            $cm = $inches > 0 ? $inches * 2.54 : 0;

            return (string) max(1, (int) round($cm > 0 ? $cm : 25));
        };

        return [
            'length' => $toCm((float) ($details['package_length'] ?? $hydrated['package_length'] ?? 10)),
            'width' => $toCm((float) ($details['package_width'] ?? $hydrated['package_width'] ?? 8)),
            'height' => $toCm((float) ($details['package_height'] ?? $hydrated['package_height'] ?? 6)),
        ];
    }

    /**
     * @param  mixed  $images
     * @return list<string>
     */
    private function publicImages(mixed $images, ProductMaster $product, string $sku): array
    {
        $fromMaster = ListingManagerAmazonHydrator::publishImageUrls($sku, (string) ($product->parent ?? ''));
        if ($fromMaster !== []) {
            return $fromMaster;
        }

        $urls = [];
        $push = function (string $raw) use (&$urls): void {
            $raw = trim($raw);
            if ($raw === '' || ! preg_match('#^https?://#i', $raw) || in_array($raw, $urls, true)) {
                return;
            }
            $urls[] = $raw;
        };
        if (is_array($images)) {
            foreach ($images as $url) {
                $push((string) $url);
            }
        }
        foreach ([$product->main_image ?? '', $product->main_image_brand ?? ''] as $url) {
            $push((string) $url);
        }
        for ($i = 1; $i <= 19; $i++) {
            $push((string) ($product->{'image'.$i} ?? ''));
        }
        if ($urls === []) {
            foreach ((ListingManagerAmazonHydrator::hydrate($sku, false)['images'] ?? []) as $url) {
                $push((string) $url);
            }
        }

        return array_slice($urls, 0, 9);
    }

    /**
     * @param  list<string>  $skus
     */
    private function persistListed(array $skus, string $spu, string $skuCode, mixed $price, string $title, string $category): void
    {
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            try {
                $this->api->persistSheinMetricRow([
                    'sku' => $sku,
                    'shein_sku_code' => $skuCode !== '' ? $skuCode : null,
                    'spu_name' => $spu !== '' ? $spu : null,
                    'price' => $price,
                    'quantity' => $this->resolveQuantity($sku),
                    'product_name' => $title,
                    'category' => $category !== '' ? $category : null,
                    'status' => 'active',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Shein listing publish: persist failed', [
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
            Cache::forget('listing_channel_counts_v1:shein');
            app(SheinLiveListingsService::class)->clearCache();
        } catch (\Throwable) {
        }
    }

    /**
     * @param  list<string>  $skus
     */
    private function publishBlockReason(array $skus): string
    {
        $cfg = ChannelListingRegistry::get('shein');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $reasons = [];
        foreach ($this->uniqueSkus($skus) as $sku) {
            $product = $this->findProduct($sku);
            if (! $product) {
                $reasons[] = $sku.': not in product master';
                continue;
            }
            if (trim((string) ($listedMap[strtolower($sku)] ?? '')) !== '') {
                $reasons[] = $sku.': already listed';
                continue;
            }
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            if ($this->publicImages($hydrated['images'] ?? [], $product, $sku) === []) {
                $reasons[] = $sku.': no public https image';
                continue;
            }
            $reasons[] = $sku.': already listed or NRL';
        }

        return $reasons !== []
            ? implode('; ', $reasons)
            : 'No Missing L child SKUs left to publish (already listed, NRL, or missing images).';
    }

    private function findProduct(string $sku): ?ProductMaster
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return ProductMaster::query()->whereNull('deleted_at')->where('sku', $sku)->first()
            ?: ProductMaster::query()->whereNull('deleted_at')->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
    }

    private function groupKey(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));

        return $parent !== '' ? $parent : trim((string) $product->sku);
    }

    private function clipTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
        $max = max(20, (int) config('services.shein.title_max_length', 80));

        return mb_strlen($title) <= $max ? $title : rtrim(mb_substr($title, 0, $max), " \t-–,.");
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
            if ($sku === '' || isset($out[strtoupper($sku)])) {
                continue;
            }
            $out[strtoupper($sku)] = $sku;
        }

        return array_values($out);
    }
}
