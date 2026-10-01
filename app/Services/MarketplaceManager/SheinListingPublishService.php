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

        // Shein's "Merchant item number" (SPU-level seller code): the variation group for a
        // multi-SKU listing, otherwise the SKU itself. Required or publishOrEdit returns
        // "basic_info/supplier_code: Merchant item number cannot be empty".
        $supplierCode = trim($parentHint);
        if ($supplierCode === '' && count($skuRows) > 1) {
            $supplierCode = trim((string) ($product->parent ?? ''));
        }
        if ($supplierCode === '' || stripos($supplierCode, 'PARENT') === 0) {
            $supplierCode = $primarySku;
        }

        $existingSpu = $this->existingSpuName($primarySku);
        $payload = [
            'brand_code' => $brandCode,
            'category_id' => (int) $category['id'],
            'edit_type' => $existingSpu !== '' ? 1 : 0,
            'product_type_id' => (int) $category['product_type_id'],
            'supplier_code' => mb_substr($supplierCode, 0, 50),
            'suit_flag' => 0,
            'source_system' => 'openapi',
            'multi_language_name_list' => [['language' => 'en', 'name' => $title]],
            'multi_language_desc_list' => [['language' => 'en', 'name' => $description]],
            'site_list' => [[
                'main_site' => 'shein',
                'sub_site_list' => [$subSite],
            ]],
            'skc_list' => $skcList,
        ];
        if ($existingSpu !== '') {
            $payload['spu_name'] = $existingSpu;
        }
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
     * Edit title / description / images of a SKU that is already on Shein.
     *
     * Shein has no field-level update call: content changes go through publishOrEdit with
     * edit_type=1 and the live spu_name / skc_name / sku_code, so the rest of the product
     * (category, price, stock, attributes) is re-sent from the live full-detail row.
     *
     * @param  array{title?: string, description?: string, bullets?: list<string>, images?: list<string>}  $changes
     * @return array{success: bool, message: string, spu_name?: string, sku_code?: string}
     */
    public function editListedSku(string $sku, array $changes): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Shein is not connected. Set SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY.'];
        }

        $skuCode = trim($this->api->resolveSheinSkuCode($sku));
        $metric = null;
        try {
            if (Schema::hasTable('shein_metrics')) {
                $metric = SheinMetric::query()->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($sku)])->first();
                if ($skuCode === '') {
                    $skuCode = trim((string) ($metric?->shein_sku_code ?? ''));
                }
            }
        } catch (\Throwable) {
            $metric = null;
        }
        if ($skuCode === '') {
            return ['success' => false, 'message' => 'This SKU has no Shein skuCode locally; sync Shein listings first.'];
        }

        $raw = [];
        try {
            $details = $this->api->getProductDetails($skuCode);
            $raw = is_array($details['raw_data'] ?? null) ? $details['raw_data'] : (is_array($details) ? $details : []);
        } catch (\Throwable $e) {
            Log::warning('Shein edit: full-detail lookup failed', ['sku' => $sku, 'sku_code' => $skuCode, 'error' => $e->getMessage()]);
        }
        if ($raw === [] && $metric && is_array($metric->raw_data ?? null)) {
            $raw = $metric->raw_data;
        }

        $spu = $this->rawString($raw, ['spuName', 'spu_name', 'spuCode', 'spu_code']);
        if ($spu === '') {
            $spu = trim((string) ($metric?->spu_name ?? ''));
        }
        if ($spu === '') {
            return ['success' => false, 'message' => 'Shein SPU for '.$sku.' is unknown, so the product cannot be edited through the API. Sync Shein listings first.'];
        }
        $skcName = $this->rawString($raw, ['skcName', 'skc_name', 'skc']);
        $supplierSku = $this->rawString($raw, ['sellerSku', 'seller_sku', 'supplierSku', 'supplier_sku', 'productNumber']) ?: $sku;
        $supplierCode = $this->rawString($raw, ['supplierCode', 'supplier_code', 'merchantItemNo']) ?: $supplierSku;

        $product = $this->findProduct($sku);
        $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
        $details = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], 'shein');

        // Category / brand / product type: live row first, then whatever the create path would pick.
        $categoryId = (int) $this->rawString($raw, ['categoryId', 'category_id', 'productCategoryId', 'product_category_id']);
        $productTypeId = (int) $this->rawString($raw, ['productTypeId', 'product_type_id']);
        $brandCode = $this->rawString($raw, ['brandCode', 'brand_code']);
        if ($categoryId <= 0 || $productTypeId <= 0) {
            $resolved = $this->resolveCategory([$sku], $categoryId > 0 ? $categoryId : null, null);
            if ($categoryId <= 0 && $resolved['id'] !== '') {
                $categoryId = (int) $resolved['id'];
            }
            if ($productTypeId <= 0) {
                $productTypeId = (int) ($resolved['product_type_id'] ?? 0);
                if ($productTypeId <= 0 && $categoryId > 0) {
                    $leaf = $this->api->findListingCategory($categoryId);
                    $productTypeId = (int) ($leaf['product_type_id'] ?? 0);
                }
            }
            if ($brandCode === '') {
                $brandCode = trim((string) ($resolved['brand_code'] ?? ''));
            }
        }
        if ($brandCode === '') {
            $brandCode = $this->api->listingBrandCode();
        }
        if ($categoryId <= 0 || $productTypeId <= 0) {
            return ['success' => false, 'message' => 'Shein did not return the category of '.$sku.' (needed for an edit). Open the product in Seller Hub to change its content.'];
        }
        if ($brandCode === '') {
            return ['success' => false, 'message' => 'Shein brand_code is missing. Set SHEIN_BRAND_CODE or confirm query-brand-list is authorized.'];
        }

        $warehouseId = $this->api->listingWarehouseId();
        if ($warehouseId === null || $warehouseId === '') {
            return ['success' => false, 'message' => 'Shein warehouse is missing. Set SHEIN_WAREHOUSE_CODE or confirm the seller account has a default warehouse.'];
        }

        // Title / description: requested change, else the live text.
        $title = trim((string) ($changes['title'] ?? ''));
        if ($title === '') {
            $title = $this->rawString($raw, ['productName', 'product_name']) ?: trim((string) ($metric?->product_name ?? ''));
        }
        if ($title === '' && $product) {
            $title = $this->resolveTitle($product, $sku, $hydrated);
        }
        $title = $this->clipTitle($title);
        if ($title === '') {
            return ['success' => false, 'message' => 'Shein title for '.$sku.' is empty.'];
        }

        $liveDescription = $this->rawString($raw, ['productDesc', 'product_desc', 'description']) ?: trim((string) ($metric?->description ?? ''));
        $description = array_key_exists('description', $changes)
            ? self::sheinDescriptionText((string) $changes['description'])
            : $liveDescription;
        if (! empty($changes['bullets']) && is_array($changes['bullets'])) {
            $description = self::mergeBulletsIntoDescription($description, $changes['bullets']);
        } elseif (array_key_exists('description', $changes)) {
            $keep = self::bulletsFromDescription($liveDescription);
            if ($keep !== [] && self::bulletsFromDescription($description) === []) {
                $description = self::mergeBulletsIntoDescription($description, $keep);
            }
        }
        $description = trim($description) !== '' ? trim($description) : $title;
        if (mb_strlen($description) > 5000) {
            $description = rtrim(mb_substr($description, 0, 5000), " \t-–,.");
        }

        // Images: new list is re-hosted on Shein's CDN; otherwise re-send the live gallery as-is.
        $hostedImages = [];
        if (! empty($changes['images']) && is_array($changes['images'])) {
            $wanted = array_values(array_filter(array_map('trim', $changes['images']), fn ($u) => preg_match('#^https?://#i', $u)));
            $hostedImages = $wanted === [] ? [] : $this->api->uploadListingImages(array_slice($wanted, 0, 12));
            if ($hostedImages === []) {
                return ['success' => false, 'message' => 'Shein image upload failed for '.$sku.'. Images must be public https URLs.'];
            }
        } else {
            $hostedImages = $this->liveImageInfoList($raw);
            if ($hostedImages === [] && $product) {
                $fallback = $this->publicImages($details['images'] ?? $hydrated['images'] ?? [], $product, $sku);
                $hostedImages = $fallback === [] ? [] : $this->api->uploadListingImages($fallback);
            }
            if ($hostedImages === []) {
                return ['success' => false, 'message' => 'Shein returned no images for '.$sku.' and none are available locally; an edit must include at least one image.'];
            }
        }

        // Price / stock: keep what Shein has; fall back to our local values.
        $currency = trim((string) config('services.shein.currency', 'USD')) ?: 'USD';
        $subSite = trim((string) config('services.shein.sub_site', 'shein-us')) ?: 'shein-us';
        $priceRow = is_array($raw['currentPrices'][0] ?? null) ? $raw['currentPrices'][0] : [];
        $salePrice = (float) ($priceRow['salePrice'] ?? $priceRow['specialPrice'] ?? 0);
        $shopPrice = (float) ($priceRow['shopPrice'] ?? $priceRow['suggestedRetailPrice'] ?? 0);
        if (! empty($priceRow['currency']) && is_string($priceRow['currency'])) {
            $currency = strtoupper(trim($priceRow['currency'])) ?: $currency;
        }
        if ($salePrice <= 0 && $shopPrice <= 0) {
            $salePrice = (float) ($this->resolvePrice($sku, $hydrated) ?? 0);
        }
        if ($salePrice <= 0 && $shopPrice <= 0) {
            return ['success' => false, 'message' => 'No price found for '.$sku.' (Shein did not return one and none is set locally).'];
        }
        $basePrice = $shopPrice > 0 ? $shopPrice : $salePrice;
        $priceInfo = ['base_price' => round($basePrice, 2), 'currency' => $currency, 'sub_site' => $subSite];
        if ($salePrice > 0 && $shopPrice > 0 && $salePrice < $shopPrice) {
            $priceInfo['special_price'] = round($salePrice, 2);
        }
        $qty = isset($raw['goodsInventory']['inventoryQuantity']) && is_numeric($raw['goodsInventory']['inventoryQuantity'])
            ? max(0, (int) $raw['goodsInventory']['inventoryQuantity'])
            : $this->resolveQuantity($sku, $hydrated);

        $weightGrams = $this->resolveWeightGrams($details, $hydrated, null);
        $dims = $this->resolveDimensionsCm($details, $hydrated);
        $template = $this->api->listingAttributeTemplate($productTypeId);

        $skuPayload = [
            'sku_code' => $skuCode,
            'supplier_sku' => $supplierSku,
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
            'price_info_list' => [$priceInfo],
        ];
        $skc = [
            'image_info' => ['image_info_list' => $hostedImages],
            'sku_list' => [$skuPayload],
        ];
        if ($skcName !== '') {
            $skc['skc_name'] = $skcName;
        }
        $liveSale = $this->liveSaleAttribute($raw);
        if ($liveSale !== null) {
            $skc['sale_attribute'] = $liveSale;
        } else {
            $mainSale = $this->pickSaleAttribute($template['sale'] ?? [], true);
            $mainValues = $this->attributeValues($mainSale);
            if ($mainSale && $mainValues !== []) {
                $skc['sale_attribute'] = [
                    'attribute_id' => (int) ($mainSale['attribute_id'] ?? $mainSale['attributeId'] ?? 0),
                    'attribute_value_id' => (int) ($mainValues[0]['id'] ?? 0),
                ];
            }
        }

        $payload = [
            'brand_code' => $brandCode,
            'category_id' => $categoryId,
            'edit_type' => 1,
            'product_type_id' => $productTypeId,
            'spu_name' => $spu,
            'supplier_code' => mb_substr($supplierCode, 0, 50),
            'suit_flag' => 0,
            'source_system' => 'openapi',
            'multi_language_name_list' => [['language' => 'en', 'name' => $title]],
            'multi_language_desc_list' => [['language' => 'en', 'name' => $description, 'description' => $description]],
            'site_list' => [[
                'main_site' => 'shein',
                'sub_site_list' => [$subSite],
            ]],
            'skc_list' => [$skc],
        ];
        $productAttrs = $this->productAttributePayload($template['product'] ?? []);
        if ($productAttrs !== []) {
            $payload['product_attribute_list'] = $productAttrs;
        }

        Log::info('Shein listing edit: publishOrEdit', [
            'sku' => $sku,
            'spu' => $spu,
            'sku_code' => $skuCode,
            'skc_name' => $skcName,
            'changes' => array_keys($changes),
            'image_count' => count($hostedImages),
        ]);

        $result = $this->api->publishOrEditProduct($payload);
        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Shein rejected the edit.'];
        }

        if ($metric) {
            try {
                $metric->product_name = $title;
                if (Schema::hasColumn('shein_metrics', 'description')) {
                    $metric->description = $description;
                }
                if (! empty($changes['images'][0]) && Schema::hasColumn('shein_metrics', 'image_url')) {
                    $metric->image_url = (string) $changes['images'][0];
                }
                $metric->save();
            } catch (\Throwable) {
                // local mirror only
            }
        }
        $this->forgetListingCaches();

        $what = [];
        if (isset($changes['title'])) {
            $what[] = 'title';
        }
        if (! empty($changes['bullets'])) {
            $what[] = 'bullet points (written into the description)';
        }
        if (array_key_exists('description', $changes)) {
            $what[] = 'description';
        }
        if (! empty($changes['images'])) {
            $what[] = 'images';
        }

        return [
            'success' => true,
            'message' => 'Shein accepted the '.($what !== [] ? implode(', ', $what) : 'content').' update for SPU '.$spu
                .'. Shein reviews edits before they show on the listing.',
            'spu_name' => $spu,
            'sku_code' => $skuCode,
        ];
    }

    /** Shein descriptions are text with <br> line breaks; flatten editor HTML to that. */
    public static function sheinDescriptionText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li[^>]*>#i', '• ', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]*\n[ \t]*/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim(str_replace("\n", '<br>', trim($text)));
    }

    /**
     * Shein has no bullet field: bullets sit as "• …" lines at the top of the description and
     * replace an earlier bullet block.
     *
     * @param  list<string>  $lines
     */
    public static function mergeBulletsIntoDescription(string $description, array $lines): string
    {
        $rest = self::stripBulletLines($description);
        $lines = array_values(array_filter(array_map(
            static fn ($l) => trim(ltrim(trim(html_entity_decode(strip_tags((string) $l), ENT_QUOTES, 'UTF-8')), "•-* ")),
            $lines
        ), static fn ($l) => $l !== ''));
        if ($lines === []) {
            return $rest;
        }
        $block = implode('<br>', array_map(static fn ($l) => '• '.$l, $lines));

        return $rest !== '' ? $block.'<br><br>'.$rest : $block;
    }

    /** @return list<string> */
    public static function bulletsFromDescription(string $description): array
    {
        $out = [];
        foreach (self::descriptionLines($description) as $line) {
            if (! str_starts_with($line, '• ')) {
                break;
            }
            $out[] = trim(mb_substr($line, 2));
        }

        return array_values(array_filter($out, static fn ($l) => $l !== ''));
    }

    private static function stripBulletLines(string $description): string
    {
        $lines = self::descriptionLines($description);
        while ($lines !== [] && (str_starts_with($lines[0], '• ') || trim($lines[0]) === '')) {
            array_shift($lines);
        }

        return implode('<br>', $lines);
    }

    /** @return list<string> */
    private static function descriptionLines(string $description): array
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $description) ?? $description;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return array_map('trim', explode("\n", trim($text)));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    private function rawString(array $raw, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($raw[$key]) && ! is_array($raw[$key])) {
                $value = trim((string) $raw[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        foreach (['skcList', 'skc_list', 'skuList', 'sku_list', 'skuInfo', 'spuInfo', 'productInfo'] as $nested) {
            $node = $raw[$nested] ?? null;
            if (is_array($node)) {
                $node = isset($node[0]) && is_array($node[0]) ? $node[0] : $node;
                $hit = $this->rawString($node, $keys);
                if ($hit !== '') {
                    return $hit;
                }
            }
        }

        return '';
    }

    /**
     * Live gallery from full-detail imageList → publishOrEdit image_info_list.
     *
     * @param  array<string, mixed>  $raw
     * @return list<array{image_sort: int, image_type: string, image_url: string}>
     */
    private function liveImageInfoList(array $raw): array
    {
        $list = $raw['imageList'] ?? $raw['image_list'] ?? $raw['imageInfoList'] ?? [];
        if (! is_array($list)) {
            return [];
        }
        $main = [];
        $others = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['imageUrl'] ?? $row['image_url'] ?? $row['url'] ?? ''));
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $type = strtoupper(trim((string) ($row['imageType'] ?? $row['image_type'] ?? '')));
            if (in_array($type, ['MAIN', '1'], true)) {
                $main[] = $url;
            } else {
                $others[] = $url;
            }
        }
        $urls = array_values(array_unique(array_merge($main, $others)));
        $out = [];
        foreach ($urls as $i => $url) {
            $out[] = [
                'image_sort' => $i + 1,
                'image_type' => $i === 0 ? '1' : '2',
                'image_url' => $url,
            ];
        }
        if (count($out) === 1) {
            $out[] = ['image_sort' => 2, 'image_type' => '2', 'image_url' => $out[0]['image_url']];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{attribute_id: int, attribute_value_id: int}|null
     */
    private function liveSaleAttribute(array $raw): ?array
    {
        foreach (['saleAttribute', 'sale_attribute', 'saleAttr'] as $key) {
            $node = $raw[$key] ?? null;
            if (is_array($node) && isset($node[0]) && is_array($node[0])) {
                $node = $node[0];
            }
            if (is_array($node)) {
                $id = (int) ($node['attributeId'] ?? $node['attribute_id'] ?? 0);
                $valueId = (int) ($node['attributeValueId'] ?? $node['attribute_value_id'] ?? 0);
                if ($id > 0 && $valueId > 0) {
                    return ['attribute_id' => $id, 'attribute_value_id' => $valueId];
                }
            }
        }
        foreach (['saleAttributeList', 'sale_attribute_list', 'skcList', 'skc_list'] as $key) {
            $list = $raw[$key] ?? null;
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $hit = $this->liveSaleAttribute($row);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
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
    private function existingSpuName(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('shein_metrics') || ! Schema::hasColumn('shein_metrics', 'spu_name')) {
            return '';
        }
        try {
            return trim((string) SheinMetric::query()->where('sku', $sku)->value('spu_name'));
        } catch (\Throwable) {
            return '';
        }
    }

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
