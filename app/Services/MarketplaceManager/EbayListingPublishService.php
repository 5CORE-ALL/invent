<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ListingManagerChannelDraft;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs from listing pages via Trading API AddFixedPriceItem.
 */
class EbayListingPublishService
{
    /**
     * @return array{id: string, path: string, name: string}
     */
    public function suggestCategoryForSku(string $sku, string $channel = 'ebaytwo'): array
    {
        return $this->resolveCategory($this->uniqueSkus([$sku]), $this->normalizeChannel($channel), null, null);
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(
        array $skus,
        string $channel = 'ebaytwo',
        bool $expandSiblings = true,
        string $mode = 'variation',
        string $parentHint = '',
        ?int $categoryId = null,
        ?string $categoryName = null
    ): array {
        $channel = $this->normalizeChannel($channel);
        $label = $this->channelLabel($channel);
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->isConfigured($channel)) {
            return [
                'success' => false,
                'message' => $label.' API credentials are not configured.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($expandSiblings && $mode === 'variation') {
            $publishSkus = $this->expandToPublishableSiblings($skus, $channel);
        } else {
            $publishSkus = $this->filterPublishable($skus, $channel);
        }
        if ($publishSkus === []) {
            return [
                'success' => false,
                'message' => $this->publishBlockReason($skus, $channel),
            ];
        }

        if ($mode === 'single' && count($publishSkus) > 1) {
            return $this->publishEachAsSingle($publishSkus, $channel, $parentHint, $categoryId, $categoryName);
        }

        $primarySku = $publishSkus[0];
        $product = $this->findProduct($primarySku);
        if (! $product) {
            return ['success' => false, 'message' => 'SKU not found in product master: '.$primarySku];
        }

        $hydrated = ListingManagerAmazonHydrator::hydrate($primarySku, false);
        $details = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], $channel);
        $title = $this->resolveTitle($product, $primarySku, $hydrated);
        if ($title === '') {
            return ['success' => false, 'message' => $primarySku.': Title missing in Title Master'];
        }
        $title = $this->clipTitle($title);

        $description = trim((string) ($details['description'] ?? $hydrated['description'] ?? ''));
        if ($description === '') {
            $description = '<p>'.e($title).'</p>';
        }

        $images = $this->publicImages($details['images'] ?? $hydrated['images'] ?? [], $product, $primarySku);
        if ($images === []) {
            return [
                'success' => false,
                'message' => 'No public image URL for '.$primarySku.'. Add an https image on CP Master (or Image Master).',
            ];
        }

        $category = $this->resolveCategory($publishSkus, $channel, $categoryId, $categoryName);
        if ($category['id'] === '') {
            return [
                'success' => false,
                'message' => 'eBay category is required for '.$primarySku.'. Type a category in the publish window, or list a sibling first so we can copy its category.',
            ];
        }

        $price = $this->resolvePrice($primarySku, $hydrated);
        $quantity = $this->resolveQuantity($primarySku, $hydrated);
        $variations = [];
        if ($mode === 'variation' && count($publishSkus) > 1) {
            $variations = $this->variationRows($publishSkus, $parentHint !== '' ? $parentHint : $this->groupKey($product));
            if ($variations === []) {
                return [
                    'success' => false,
                    'message' => 'Could not build variations for '.$this->groupKey($product).'. Check price and SKU rows.',
                ];
            }
        } elseif ($price === null || $price <= 0) {
            return [
                'success' => false,
                'message' => 'No price found for '.$primarySku.'. Set Shopify / Amazon price first.',
            ];
        }

        $policies = $this->policyIds($channel);
        $defaults = (array) config('listing_manager.ebay2_defaults', []);
        $payload = array_merge($details, [
            'sku' => $primarySku,
            'title' => $title,
            'description' => $description,
            'price' => $price ?? 0,
            'quantity' => $quantity,
            'images' => $images,
            'primary_category_id' => $category['id'],
            'primary_category_path' => $category['path'],
            'condition_id' => trim((string) ($details['condition_id'] ?? '1000')) ?: '1000',
            'location_city' => trim((string) ($details['location_city'] ?? $defaults['location_city'] ?? 'Bellefontaine')),
            'location_country' => trim((string) ($details['location_country'] ?? $defaults['location_country'] ?? 'US')),
            'location_postal_code' => trim((string) ($details['location_postal_code'] ?? $defaults['location_postal_code'] ?? '43311')),
            'shipping_policy_id' => $policies['shipping'],
            'payment_policy_id' => $policies['payment'],
            'return_policy_id' => $policies['return'],
            'variations' => $variations,
        ]);

        Log::info('Ebay listing publish: AddFixedPriceItem', [
            'channel' => $channel,
            'sku' => $primarySku,
            'mode' => $mode,
            'category_id' => $category['id'],
            'image_count' => count($images),
            'variation_count' => count($variations),
        ]);

        try {
            $result = ListingManagerEbayTradingPublisher::publish($channel, $payload);
        } catch (\Throwable $e) {
            Log::error('Ebay listing publish failed: '.$e->getMessage(), [
                'channel' => $channel,
                'sku' => $primarySku,
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (empty($result['success'])) {
            return [
                'success' => false,
                'message' => $result['message'] ?? ($label.' rejected AddFixedPriceItem.'),
            ];
        }

        $itemId = trim((string) ($result['item_id'] ?? ''));
        $listedSkus = $variations !== []
            ? array_values(array_map(fn ($row) => (string) $row['sku'], $variations))
            : [$primarySku];
        $this->persistMetrics($channel, $listedSkus, $itemId, $title, $price ?? 0, $quantity);
        $this->forgetListingCaches($channel);

        return [
            'success' => true,
            'message' => $result['message'] ?? ('Published '.$primarySku.' to '.$label.'.'),
            'goods_id' => $itemId !== '' ? $itemId : null,
            'sku_id' => $itemId !== '' ? $itemId : null,
            'skus' => $listedSkus,
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
        ?string $categoryName
    ): array {
        $ok = [];
        $fail = [];
        $listed = [];
        $lastId = null;
        foreach ($skus as $sku) {
            $one = $this->publishSkus([$sku], $channel, false, 'single', $parentHint, $categoryId, $categoryName);
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
        if ($explicitId === '' && preg_match('/^\d{3,}$/', $name)) {
            $explicitId = $name;
            $name = '';
        }
        if ($explicitId !== '') {
            return [
                'id' => $explicitId,
                'path' => $name !== '' ? $name.' ('.$explicitId.')' : 'Category '.$explicitId,
                'name' => $name,
            ];
        }

        if ($name !== '') {
            $fromName = $this->categoryFromTaxonomy($name);
            if ($fromName['id'] !== '') {
                return $fromName;
            }
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
            $fromTitle = $this->categoryFromTaxonomy($title);
            if ($fromTitle['id'] !== '') {
                return $fromTitle;
            }
        }

        return $empty;
    }

    /**
     * @return array{id: string, path: string, name: string}
     */
    private function categoryFromTaxonomy(string $query): array
    {
        $empty = ['id' => '', 'path' => '', 'name' => ''];
        $query = trim($query);
        if ($query === '') {
            return $empty;
        }

        try {
            $result = app(Ebay2ApiService::class)->getCategorySuggestions($query);
        } catch (\Throwable $e) {
            Log::warning('Ebay listing publish: category search failed', ['q' => $query, 'error' => $e->getMessage()]);

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
            'name' => $path !== '' ? (string) preg_replace('/^.*[>\/|]\s*/', '', $path) : '',
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
            $parent = $this->groupKey($product);
            foreach (ListingManagerFamily::siblingSkus($parent, $sku) as $sibling) {
                $candidates[] = $sibling;
            }
        }
        $candidates = $this->uniqueSkus($candidates);
        $cfg = ChannelListingRegistry::get($channel);
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $candidates) : [];
        $itemIds = [];
        foreach ($candidates as $sku) {
            $id = trim((string) ($listedMap[strtolower($sku)] ?? ''));
            if ($id !== '' && ! in_array($id, $itemIds, true)) {
                $itemIds[] = $id;
            }
        }
        foreach (array_slice($itemIds, 0, 5) as $itemId) {
            $item = $this->getItem($channel, $itemId);
            if (! is_array($item)) {
                continue;
            }
            $node = $item['Item']['PrimaryCategory'] ?? $item['PrimaryCategory'] ?? [];
            $id = trim((string) ($node['CategoryID'] ?? $node['categoryId'] ?? ''));
            $name = trim((string) ($node['CategoryName'] ?? $node['categoryName'] ?? ''));
            if ($id !== '') {
                return [
                    'id' => $id,
                    'path' => $name !== '' ? $name.' ('.$id.', from a listed sibling)' : 'Category '.$id.' (from a listed sibling)',
                    'name' => $name,
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

        $channelIds = ListingManagerChannelDraft::query()
            ->join('channel_master', 'channel_master.id', '=', 'listing_manager_channel_drafts.channel_id')
            ->whereIn('listing_manager_channel_drafts.seller_sku', $skus)
            ->get(['listing_manager_channel_drafts.listing_details', 'channel_master.channel']);

        foreach ($channelIds as $row) {
            $key = ListingChannelCounts::normalize((string) $row->channel);
            if (! in_array($key, $this->channelAliases($channel), true) && $key !== $channel) {
                continue;
            }
            $rawDetails = $row->listing_details;
            if (is_string($rawDetails) && $rawDetails !== '') {
                $decoded = json_decode($rawDetails, true);
                $rawDetails = is_array($decoded) ? $decoded : [];
            }
            $details = is_array($rawDetails) ? $rawDetails : [];
            $id = trim((string) ($details['primary_category_id'] ?? $details['category_id'] ?? ''));
            $path = trim((string) ($details['primary_category_path'] ?? $details['category'] ?? ''));
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
     * @return array<string, mixed>|null
     */
    private function getItem(string $channel, string $itemId): ?array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return null;
        }

        try {
            $svc = match ($this->registryKey($channel)) {
                'ebay' => app(EbayApiService::class),
                'ebaythree' => app(EbayThreeApiService::class),
                default => app(Ebay2ApiService::class),
            };
            $data = $svc->getItem($itemId);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('Ebay listing publish: GetItem failed', [
                'channel' => $channel,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandToPublishableSiblings(array $seedSkus, string $channel): array
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
     * @param  list<string>  $skus
     * @return list<array{sku: string, price: float, quantity: int, variation_label: string, upc: string}>
     */
    private function variationRows(array $skus, string $parent): array
    {
        $rows = [];
        foreach ($skus as $sku) {
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            $price = $this->resolvePrice($sku, $hydrated);
            if ($price === null || $price <= 0) {
                continue;
            }
            $rows[] = [
                'sku' => $sku,
                'price' => $price,
                'quantity' => $this->resolveQuantity($sku, $hydrated),
                'variation_label' => ListingManagerFamily::variationLabel($sku, $parent),
                'upc' => trim((string) ($hydrated['upc'] ?? '')),
            ];
        }

        return count($rows) > 1 ? $rows : [];
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
        if ($shopifyPrice > 0) {
            return round($shopifyPrice, 2);
        }

        return null;
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
     * @param  mixed  $images
     * @return list<string>
     */
    private function publicImages(mixed $images, ProductMaster $product, string $sku): array
    {
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
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            foreach ($hydrated['images'] ?? [] as $url) {
                $push((string) $url);
            }
        }

        return array_slice($urls, 0, 12);
    }

    /**
     * @param  list<string>  $skus
     */
    private function persistMetrics(
        string $channel,
        array $skus,
        string $itemId,
        string $title,
        mixed $price,
        mixed $qty
    ): void {
        if ($itemId === '') {
            return;
        }

        $key = $this->registryKey($channel);
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $link = 'https://www.ebay.com/itm/'.$itemId;
            $payload = [
                'item_id' => $itemId,
                'ebay_title' => $title,
                'ebay_price' => $price,
                'ebay_stock' => $qty,
                'ebay_link' => $link,
            ];
            if ($key === 'ebaytwo' && Schema::hasTable('ebay_2_metrics')) {
                Ebay2Metric::query()->updateOrCreate(['sku' => $sku], $payload);
            } elseif ($key === 'ebaythree' && Schema::hasTable('ebay_3_metrics')) {
                Ebay3Metric::query()->updateOrCreate(['sku' => $sku], $payload);
            } elseif ($key === 'ebay' && Schema::hasTable('ebay_metrics')) {
                EbayMetric::query()->updateOrCreate(['sku' => $sku], $payload);
            }
        }
    }

    private function forgetListingCaches(string $channel): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            $key = $this->registryKey($channel);
            Cache::forget('listing_channel_counts_v1:'.$key);
            if ($key === 'ebay') {
                Cache::forget('listing_channel_counts_v1:ebay1');
                Cache::forget(Ebay1LiveListingsService::CACHE_KEY);
            } elseif ($key === 'ebaythree') {
                Cache::forget('listing_channel_counts_v1:ebay3');
                Cache::forget(Ebay3LiveListingsService::CACHE_KEY);
            } else {
                Cache::forget('listing_channel_counts_v1:ebay2');
                Cache::forget('listing_channel_counts_v1:ebaytwo');
                Cache::forget(Ebay2LiveListingsService::CACHE_KEY);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @return array{shipping: string, payment: string, return: string}
     */
    private function policyIds(string $channel): array
    {
        $defaults = (array) config('listing_manager.ebay2_defaults', []);
        $ids = [
            'shipping' => trim((string) ($defaults['shipping_policy_id'] ?? '')),
            'payment' => trim((string) ($defaults['payment_policy_id'] ?? '')),
            'return' => trim((string) ($defaults['return_policy_id'] ?? '')),
        ];
        if ($this->registryKey($channel) !== 'ebaytwo') {
            return $ids;
        }

        try {
            $resolved = app(Ebay2ApiService::class)->policyIds();
            foreach (['shipping', 'payment', 'return'] as $key) {
                if ($ids[$key] === '') {
                    $ids[$key] = trim((string) ($resolved[$key] ?? ''));
                }
            }
        } catch (\Throwable) {
        }

        return $ids;
    }

    private function isConfigured(string $channel): bool
    {
        try {
            return match ($this->registryKey($channel)) {
                'ebay' => app(EbayApiService::class)->isConfigured(),
                'ebaythree' => app(EbayThreeApiService::class)->isConfigured(),
                default => app(Ebay2ApiService::class)->isConfigured(),
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function findProduct(string $sku): ?ProductMaster
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();
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

    private function groupKey(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));

        return $parent !== '' ? $parent : trim((string) $product->sku);
    }

    private function clipTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
        if (mb_strlen($title) <= 80) {
            return $title;
        }

        $cut = mb_substr($title, 0, 80);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space >= 50) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t-–,.");
    }

    private function normalizeChannel(string $channel): string
    {
        return ListingChannelCounts::normalize($channel);
    }

    private function registryKey(string $channel): string
    {
        $key = $this->normalizeChannel($channel);

        return match ($key) {
            'ebay', 'ebay1', 'ebayone' => 'ebay',
            'ebay3', 'ebaythree' => 'ebaythree',
            default => 'ebaytwo',
        };
    }

    /**
     * @return list<string>
     */
    private function channelAliases(string $channel): array
    {
        return match ($this->registryKey($channel)) {
            'ebay' => ['ebay', 'ebay1', 'ebayone'],
            'ebaythree' => ['ebay3', 'ebaythree'],
            default => ['ebay2', 'ebaytwo'],
        };
    }

    private function channelLabel(string $channel): string
    {
        return match ($this->registryKey($channel)) {
            'ebay' => 'eBay',
            'ebaythree' => 'eBay 3',
            default => 'eBay 2',
        };
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
