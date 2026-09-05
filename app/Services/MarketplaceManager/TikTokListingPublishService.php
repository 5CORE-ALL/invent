<?php

namespace App\Services\MarketplaceManager;

use App\Models\ListingManagerChannelDraft;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs from listing pages to TikTok Shop / TikTok Shop 2.
 */
class TikTokListingPublishService
{
    /**
     * @return array{id: string, path: string, name: string}
     */
    public function suggestCategoryForSku(string $sku, string $channel = 'tiktokshop2'): array
    {
        return $this->resolveCategory($this->uniqueSkus([$sku]), $this->normalizeChannel($channel), null, null);
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(
        array $skus,
        string $channel = 'tiktokshop2',
        bool $expandSiblings = true,
        string $mode = 'variation',
        string $parentHint = '',
        ?int $categoryId = null,
        ?string $categoryName = null,
        ?float $weightLb = null
    ): array {
        $channel = $this->normalizeChannel($channel);
        $label = $this->channelLabel($channel);
        $api = $this->api($channel);
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $api->isConfigured()) {
            return [
                'success' => false,
                'message' => $label.' is not connected. Open Connect and authorize the shop, then try Publish again.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($expandSiblings && $mode === 'variation') {
            $publishSkus = $this->expandToPublishableSiblings($skus, $channel);
        } else {
            $publishSkus = $this->filterPublishable($skus, $channel);
        }
        if ($publishSkus === []) {
            return ['success' => false, 'message' => $this->publishBlockReason($skus, $channel)];
        }

        if ($mode === 'single' && count($publishSkus) > 1) {
            return $this->publishEachAsSingle($publishSkus, $channel, $parentHint, $categoryId, $categoryName, $weightLb);
        }

        $primarySku = $publishSkus[0];
        $product = $this->findProduct($primarySku);
        if (! $product) {
            return ['success' => false, 'message' => 'SKU not found in product master: '.$primarySku];
        }

        $hydrated = ListingManagerAmazonHydrator::hydrate($primarySku, false);
        $details = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], $channel);
        $title = $this->clipTitle($this->resolveTitle($product, $primarySku, $hydrated));
        if ($title === '') {
            return ['success' => false, 'message' => $primarySku.': Title missing in Title Master'];
        }

        $description = trim((string) ($details['description'] ?? $hydrated['description'] ?? ''));
        if ($description === '') {
            $description = '<p>'.e($title).'</p>';
        }

        $imageSku = $primarySku;
        $imageSources = ListingManagerAmazonHydrator::imageMasterUploadSources($primarySku, (string) ($product->parent ?? ''));
        if ($imageSources === []) {
            foreach ($publishSkus as $sku) {
                $imageSources = ListingManagerAmazonHydrator::imageMasterUploadSources($sku, (string) ($product->parent ?? ''));
                if ($imageSources !== []) {
                    $imageSku = $sku;
                    break;
                }
            }
        }
        if ($imageSources === []) {
            return [
                'success' => false,
                'message' => 'No Image Master photo for '.$primarySku.'. Add images on Image Master, then try Publish again.',
            ];
        }

        $category = $this->resolveCategory($publishSkus, $channel, $categoryId, $categoryName);
        if ($category['id'] === '') {
            return [
                'success' => false,
                'message' => 'TikTok category is required for '.$primarySku.'. Type a category in the publish window, or list a sibling first so we can copy its category.',
            ];
        }

        $warehouseId = $api->listingWarehouseId();
        if ($warehouseId === null || $warehouseId === '') {
            return [
                'success' => false,
                'message' => $label.' warehouse is missing. Set '.strtoupper($api->configKey()).'_WAREHOUSE_ID or confirm the shop has a default warehouse.',
            ];
        }

        $price = $this->resolvePrice($primarySku, $hydrated);
        if ($price === null || $price <= 0) {
            return ['success' => false, 'message' => 'No price found for '.$primarySku.'. Set Shopify / Amazon price first.'];
        }

        $uploaded = $api->uploadImageMasterForListing($imageSku, (string) ($product->parent ?? ''));
        $uris = $uploaded['uris'] ?? [];
        if ($uris === []) {
            return [
                'success' => false,
                'message' => trim((string) ($uploaded['message'] ?? '')) !== ''
                    ? (string) $uploaded['message']
                    : 'TikTok image upload failed for '.$imageSku.'. Check Image Master photos.',
            ];
        }

        $skuRows = $mode === 'variation' && count($publishSkus) > 1
            ? $publishSkus
            : [$primarySku];
        $tiktokSkus = [];
        foreach ($skuRows as $sku) {
            $childHydrated = $sku === $primarySku ? $hydrated : ListingManagerAmazonHydrator::hydrate($sku, false);
            $childPrice = $this->resolvePrice($sku, $childHydrated);
            if ($childPrice === null || $childPrice <= 0) {
                return ['success' => false, 'message' => 'No price found for '.$sku.'.'];
            }
            $qty = max(1, $this->resolveQuantity($sku, $childHydrated));
            $row = [
                'seller_sku' => $sku,
                'price' => ['amount' => number_format($childPrice, 2, '.', ''), 'currency' => 'USD'],
                'inventory' => [['warehouse_id' => $warehouseId, 'quantity' => $qty]],
            ];
            if (count($skuRows) > 1) {
                $row['sales_attributes'] = [[
                    'name' => 'Variation',
                    'value_name' => ListingManagerFamily::variationLabel($sku, $parentHint !== '' ? $parentHint : $this->groupKey($product)),
                ]];
            }
            $tiktokSkus[] = $row;
        }

        $weight = $this->resolveWeightLb($details, $hydrated, $weightLb);
        $dims = $this->resolveDimensions($details, $hydrated);
        $brand = trim((string) config('listing_manager.default_brand', '5 Core Inc.')) ?: '5 Core Inc.';
        $brandId = $api->searchBrandId($category['id'], $brand);
        $attributes = $api->requiredAttributesForCategory($category['id']);

        $payload = [
            'save_mode' => 'LISTING',
            'title' => $title,
            'description' => $description,
            'category_id' => $category['id'],
            'category_version' => $api->listingCategoryVersion(),
            'main_images' => $uris,
            'skus' => $tiktokSkus,
            'package_weight' => ['value' => number_format($weight, 2, '.', ''), 'unit' => 'POUND'],
            'package_dimensions' => [
                'length' => $dims['length'],
                'width' => $dims['width'],
                'height' => $dims['height'],
                'unit' => 'INCH',
            ],
        ];
        if ($brandId !== '') {
            $payload['brand_id'] = $brandId;
        }
        if ($attributes !== []) {
            $payload['product_attributes'] = $attributes;
        }

        Log::info('TikTok listing publish: create product', [
            'channel' => $channel,
            'sku' => $primarySku,
            'mode' => $mode,
            'category_id' => $category['id'],
            'image_count' => count($uris),
            'sku_count' => count($tiktokSkus),
        ]);

        $result = $api->createListingProduct($payload);
        if (empty($result['success'])) {
            return [
                'success' => false,
                'message' => $result['message'] ?? ($label.' rejected create product.'),
            ];
        }

        $productId = trim((string) ($result['product_id'] ?? ''));
        $this->persistListed($channel, $skuRows, $productId, $result['skus'] ?? [], $price);
        $this->forgetListingCaches($channel);

        return [
            'success' => true,
            'message' => $result['message'] ?? ('Published '.$primarySku.' to '.$label.'.'),
            'goods_id' => $productId !== '' ? $productId : null,
            'sku_id' => $result['sku_id'] ?? null,
            'skus' => $skuRows,
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    private function publishEachAsSingle(
        array $skus,
        string $channel,
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
            $one = $this->publishSkus([$sku], $channel, false, 'single', $parentHint, $categoryId, $categoryName, $weightLb);
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
     * @return array{id: string, path: string, name: string}
     */
    private function resolveCategory(array $skus, string $channel, ?int $categoryId, ?string $categoryName): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => ''];
        $explicitId = $categoryId !== null && $categoryId > 0 ? (string) $categoryId : '';
        $name = trim((string) $categoryName);
        if ($explicitId === '' && preg_match('/^\d{4,}$/', $name)) {
            $explicitId = $name;
            $name = '';
        }

        $api = $this->api($channel);
        if ($name !== '') {
            $fromName = $this->categoryFromSearch($api, $name);
            if ($fromName['id'] !== '') {
                return $fromName;
            }
        }
        if ($explicitId !== '') {
            return [
                'id' => $explicitId,
                'path' => $name !== '' ? $name : 'Category '.$explicitId,
                'name' => $name,
            ];
        }

        $fromSibling = $this->categoryFromListedSibling($skus, $channel);
        if ($fromSibling['id'] !== '') {
            return $fromSibling;
        }

        $fromDraft = $this->categoryFromDraft($skus, $channel);
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
            $fromTitle = $this->categoryFromSearch($api, $title);
            if ($fromTitle['id'] !== '') {
                return $fromTitle;
            }
        }

        return $empty;
    }

    /**
     * @return array{id: string, path: string, name: string}
     */
    private function categoryFromSearch(TikTokShopService $api, string $query): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => ''];
        try {
            $result = $api->searchListingCategories($query, $query);
        } catch (\Throwable $e) {
            Log::warning('TikTok listing publish: category search failed', ['q' => $query, 'error' => $e->getMessage()]);

            return $empty;
        }
        $row = $result['categories'][0] ?? null;
        if (! is_array($row)) {
            return $empty;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            return $empty;
        }
        $path = trim((string) ($row['path'] ?? ''));

        return [
            'id' => $id,
            'path' => $path !== '' ? $path : 'Category '.$id,
            'name' => $path !== '' ? (string) preg_replace('/^.*(?: - |>|\/)\s*/', '', $path) : '',
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array{id: string, path: string, name: string}
     */
    private function categoryFromListedSibling(array $skus, string $channel): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => ''];
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
        $cfg = ChannelListingRegistry::get($channel);
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $this->uniqueSkus($candidates)) : [];
        $api = $this->api($channel);
        foreach ($listedMap as $itemId) {
            $itemId = trim((string) $itemId);
            if ($itemId === '') {
                continue;
            }
            $hit = $api->productCategory($itemId);
            if (($hit['id'] ?? '') !== '') {
                return [
                    'id' => $hit['id'],
                    'path' => ($hit['path'] !== '' ? $hit['path'] : 'Category '.$hit['id']).' (from a listed sibling)',
                    'name' => $hit['path'],
                ];
            }
        }

        return $empty;
    }

    /**
     * @param  list<string>  $skus
     * @return array{id: string, path: string, name: string}
     */
    private function categoryFromDraft(array $skus, string $channel): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => ''];
        if (! Schema::hasTable('listing_manager_channel_drafts') || ! Schema::hasTable('channel_master')) {
            return $empty;
        }

        $rows = ListingManagerChannelDraft::query()
            ->join('channel_master', 'channel_master.id', '=', 'listing_manager_channel_drafts.channel_id')
            ->whereIn('listing_manager_channel_drafts.seller_sku', $skus)
            ->get(['listing_manager_channel_drafts.listing_details', 'channel_master.channel']);

        foreach ($rows as $row) {
            $key = ListingChannelCounts::normalize((string) $row->channel);
            if (! in_array($key, $this->channelAliases($channel), true) && $key !== $channel) {
                continue;
            }
            $raw = $row->listing_details;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
            $details = is_array($raw) ? $raw : [];
            $id = trim((string) ($details['primary_category_id'] ?? $details['category_id'] ?? $details['category_uuid'] ?? ''));
            $path = trim((string) ($details['primary_category_path'] ?? $details['category_name'] ?? $details['category'] ?? ''));
            if ($id !== '') {
                return [
                    'id' => $id,
                    'path' => $path !== '' ? $path.' (from Listing Manager)' : 'Category '.$id.' (from Listing Manager)',
                    'name' => $path,
                ];
            }
        }

        return $empty;
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandToPublishableSiblings(array $seedSkus, string $channel): array
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
            $children->map(fn ($p) => trim((string) $p->sku))->filter()->unique()->values()->all(),
            $channel
        );
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function filterPublishable(array $skus, string $channel): array
    {
        $cfg = ChannelListingRegistry::get($channel);
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
    private function resolveWeightLb(array $details, array $hydrated, ?float $weightLb): float
    {
        if ($weightLb !== null && $weightLb > 0) {
            return round($weightLb, 2);
        }
        $lb = (float) ($details['package_weight_lb'] ?? $hydrated['package_weight_lb'] ?? 0);
        $oz = (float) ($details['package_weight_oz'] ?? $hydrated['package_weight_oz'] ?? 0);
        $total = $lb + ($oz / 16);
        if ($total > 0) {
            return round($total, 2);
        }

        return 1.0;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $hydrated
     * @return array{length: string, width: string, height: string}
     */
    private function resolveDimensions(array $details, array $hydrated): array
    {
        $length = (float) ($details['package_length'] ?? $hydrated['package_length'] ?? 0);
        $width = (float) ($details['package_width'] ?? $hydrated['package_width'] ?? 0);
        $height = (float) ($details['package_height'] ?? $hydrated['package_height'] ?? 0);

        return [
            'length' => number_format($length > 0 ? $length : 10, 2, '.', ''),
            'width' => number_format($width > 0 ? $width : 8, 2, '.', ''),
            'height' => number_format($height > 0 ? $height : 6, 2, '.', ''),
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
     * @param  list<array<string, mixed>>  $createdSkus
     */
    private function persistListed(string $channel, array $skus, string $productId, array $createdSkus, mixed $price): void
    {
        if ($productId === '') {
            return;
        }

        $skuIds = [];
        foreach ($createdSkus as $row) {
            if (! is_array($row)) {
                continue;
            }
            $seller = trim((string) ($row['seller_sku'] ?? $row['sellerSku'] ?? ''));
            $id = trim((string) ($row['id'] ?? $row['sku_id'] ?? ''));
            if ($seller !== '' && $id !== '') {
                $skuIds[$seller] = $id;
            }
        }

        $isTwo = $this->isTiktok2($channel);
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $payload = [
                'product_id' => $productId,
                'sku_id' => $skuIds[$sku] ?? null,
                'price' => $price,
                'listing_status' => 'ACTIVATE',
            ];
            if ($isTwo && Schema::hasTable('tiktok_products_two')) {
                TikTokProductTwo::query()->updateOrCreate(['sku' => $sku], $payload);
            } elseif (! $isTwo && Schema::hasTable('tiktok_products')) {
                TikTokProduct::query()->updateOrCreate(['sku' => $sku], $payload);
            }
        }
    }

    private function forgetListingCaches(string $channel): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            if ($this->isTiktok2($channel)) {
                Cache::forget('listing_channel_counts_v1:tiktokshop2');
                Cache::forget('listing_channel_counts_v1:tiktok2');
                app(TikTok2LiveListingsService::class)->clearCache();
            } else {
                Cache::forget('listing_channel_counts_v1:tiktokshop');
                Cache::forget('listing_channel_counts_v1:tiktok');
                app(TikTokLiveListingsService::class)->clearCache();
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param  list<string>  $skus
     */
    private function publishBlockReason(array $skus, string $channel): string
    {
        $cfg = ChannelListingRegistry::get($channel);
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

        return mb_strlen($title) <= 255 ? $title : rtrim(mb_substr($title, 0, 255), " \t-–,.");
    }

    private function api(string $channel): TikTokShopService
    {
        return $this->isTiktok2($channel)
            ? app(TikTok2ShopService::class)
            : app(TikTokShopService::class);
    }

    private function isTiktok2(string $channel): bool
    {
        return in_array($this->normalizeChannel($channel), ['tiktok2', 'tiktokshop2', 'tiktoktwo'], true);
    }

    private function normalizeChannel(string $channel): string
    {
        return ListingChannelCounts::normalize($channel);
    }

    /**
     * @return list<string>
     */
    private function channelAliases(string $channel): array
    {
        return $this->isTiktok2($channel)
            ? ['tiktok2', 'tiktokshop2', 'tiktoktwo']
            : ['tiktok', 'tiktokshop', 'tiktok1'];
    }

    private function channelLabel(string $channel): string
    {
        return $this->isTiktok2($channel) ? 'TikTok Shop 2' : 'TikTok Shop';
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
