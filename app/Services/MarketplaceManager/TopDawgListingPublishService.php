<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TopDawgDataView;
use App\Models\TopDawgListingStatus;
use App\Models\TopDawgProduct;
use App\Services\TopDawgApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to TopDawg (one SKU = one listing).
 */
class TopDawgListingPublishService
{
    public function __construct(private TopDawgApiService $api)
    {
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
        ?string $categoryUuid = null,
        ?string $categoryName = null,
        array $overrides = []
    ): array
    {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'TopDawg API token missing. Set TOPDAWG_API_TOKEN in .env.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        $publishSkus = ($expandSiblings && $mode === 'variation')
            ? $this->expandToPublishableSiblings($skus)
            : $this->filterPublishable($skus, ! $expandSiblings);

        if ($publishSkus === []) {
            return ['success' => false, 'message' => $this->publishBlockReason($skus)];
        }

        if (count($publishSkus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastId = null;
            foreach ($publishSkus as $sku) {
                $one = $this->publishSkus([$sku], false, 'single', $parentHint, $categoryUuid, $categoryName, $overrides);
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

        $sku = $publishSkus[0];
        $product = $this->findProduct($sku);
        if (! $product) {
            return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
        }

        $title = trim((string) ($overrides['title'] ?? '')) ?: $this->resolveTitle($product, $sku);
        if ($title === '') {
            return ['success' => false, 'message' => $sku.': Title missing in Title Master'];
        }

        $price = isset($overrides['price']) && is_numeric($overrides['price']) && (float) $overrides['price'] > 0
            ? round((float) $overrides['price'], 2)
            : $this->resolvePrice($sku, $product);
        if ($price === null || $price <= 0) {
            return [
                'success' => false,
                'message' => 'No price found for '.$sku.'. Set TopDawg SPRICE or Shopify price.',
            ];
        }

        $images = is_array($overrides['images'] ?? null) ? array_values(array_filter(array_map(
            static fn ($url) => trim((string) $url),
            $overrides['images']
        ))) : [];
        if ($images === []) {
            $images = $this->productImages($product, $sku);
        }
        if ($images === []) {
            return ['success' => false, 'message' => 'No public image URL for '.$sku.'. Add an https image on CP Master (or Image Master).'];
        }

        $pkg = $this->packageInches($product, $sku, $overrides);
        if ($pkg['weight'] === null || $pkg['length'] === null || $pkg['width'] === null || $pkg['height'] === null) {
            return [
                'success' => false,
                'message' => $sku.': add weight and L/W/H (inches) on Package or /dim-wt-master before publishing to TopDawg.',
            ];
        }

        $inv = isset($overrides['quantity']) && is_numeric($overrides['quantity'])
            ? max(0, (int) $overrides['quantity'])
            : $this->shopifyInv($sku);
        $category = self::resolveCategory(
            $categoryUuid ?? ($overrides['category_id'] ?? null),
            $categoryName ?? ($overrides['category_name'] ?? null)
        );
        $madeIn = $this->madeInCountry((string) ($overrides['country_of_origin'] ?? $overrides['product_made_in'] ?? 'CN'));
        $cost = isset($overrides['cost']) && is_numeric($overrides['cost']) && (float) $overrides['cost'] > 0
            ? round((float) $overrides['cost'], 2)
            : $price;
        $msrp = isset($overrides['msrp']) && is_numeric($overrides['msrp']) && (float) $overrides['msrp'] > 0
            ? round((float) $overrides['msrp'], 2)
            : $this->resolveMsrp($sku, $product, $price);
        $description = trim((string) ($overrides['description'] ?? '')) ?: $this->resolveDescription($product, $title);
        $res = $this->api->createProduct([
            'product_code' => $sku,
            'product_name' => $title,
            'description' => $description,
            'price' => $price,
            'cost' => $cost,
            'msrp' => $msrp >= $cost ? $msrp : $cost,
            'qty_available' => $inv,
            'images' => $images,
            'brand_name' => trim((string) ($overrides['brand'] ?? '5 Core')) ?: '5 Core',
            'manufacturer' => trim((string) ($overrides['manufacturer'] ?? '5 Core')) ?: '5 Core',
            'upc' => trim((string) ($overrides['upc'] ?? '')),
            'dept' => $category['dept'],
            'section' => $category['section'],
            'category' => $category['category'],
            'gender' => trim((string) ($overrides['gender'] ?? 'Unisex')) ?: 'Unisex',
            'age_group' => trim((string) ($overrides['age_group'] ?? 'Adults')) ?: 'Adults',
            'condition' => trim((string) ($overrides['condition'] ?? 'New')) ?: 'New',
            'pack_of' => isset($overrides['pack_of']) && is_numeric($overrides['pack_of'])
                ? max(1, (int) $overrides['pack_of'])
                : 1,
            'product_weight' => $pkg['weight'],
            'ship_length' => $pkg['length'],
            'ship_width' => $pkg['width'],
            'ship_height' => $pkg['height'],
            'product_made_in' => $madeIn,
        ]);

        if (empty($res['success'])) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'TopDawg create listing failed.',
            ];
        }

        $listingId = trim((string) ($res['listing_id'] ?? ''));
        $this->persistListed($sku, $listingId, trim((string) ($res['tdid'] ?? '')), $title, $price, $inv);
        $this->forgetListingCaches();

        return [
            'success' => true,
            'message' => $res['message'] ?? ('Published '.$sku.' to TopDawg.'),
            'goods_id' => $listingId !== '' ? $listingId : null,
            'sku_id' => $listingId !== '' ? $listingId : null,
            'skus' => [$sku],
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
    private function filterPublishable(array $skus, bool $fromListingManager = false): array
    {
        $cfg = ChannelListingRegistry::get('topdawg');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $nrValues = ListingCountsEngine::loadNrValues(TopDawgDataView::class, $skus);
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
            $listedId = trim((string) ($listedMap[strtolower($sku)] ?? ''));
            if ($listedId !== '' && $this->isLiveOnTopDawg($sku, $listedId)) {
                continue;
            }
            if (! $fromListingManager && ListingCountsEngine::nrReqFromDataView($nrValues->get(strtoupper($sku))) === 'NR') {
                continue;
            }
            $product = $products->get(strtolower($sku)) ?: $this->findProduct($sku);
            if (! $product || $this->productImages($product, $sku) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
    }

    private function isLiveOnTopDawg(string $sku, string $listedId = ''): bool
    {
        if (! ChannelListingRegistry::isLiveTopDawgListingId($listedId, $sku)) {
            $listedId = '';
        }
        try {
            $live = $this->api->lookupLiveCatalogProduct($sku, false);
        } catch (\Throwable $e) {
            Log::warning('TopDawg live listed check failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return $listedId !== '';
        }
        if (! is_array($live)) {
            return false;
        }
        $id = trim((string) ($live['id'] ?? $live['listing_id'] ?? $live['tdid'] ?? $live['TDID'] ?? ''));

        return ChannelListingRegistry::isLiveTopDawgListingId($id, $sku) || $listedId !== '';
    }

    private function publishBlockReason(array $skus): string
    {
        $cfg = ChannelListingRegistry::get('topdawg');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $nrValues = ListingCountsEngine::loadNrValues(TopDawgDataView::class, $skus);
        $reasons = [];
        foreach ($this->uniqueSkus($skus) as $sku) {
            $product = $this->findProduct($sku);
            if (! $product) {
                $reasons[] = $sku.': not in product master';
                continue;
            }
            if ($this->productImages($product, $sku) === []) {
                $reasons[] = $sku.': no public https image';
                continue;
            }
            $listedId = trim((string) ($listedMap[strtolower($sku)] ?? ''));
            if ($listedId !== '' && $this->isLiveOnTopDawg($sku, $listedId)) {
                $reasons[] = $sku.': already listed on TopDawg';
                continue;
            }
            if (ListingCountsEngine::nrReqFromDataView($nrValues->get(strtoupper($sku))) === 'NR') {
                $reasons[] = $sku.': NRL';
                continue;
            }
            $reasons[] = $sku.': already listed or NRL';
        }

        return $reasons !== []
            ? implode('; ', $reasons)
            : 'No Missing L child SKUs left to publish (already listed, NRL, or missing images).';
    }

    private function persistListed(string $sku, string $listingId, string $tdid, string $title, float $price, int $inv): void
    {
        $liveId = ChannelListingRegistry::isLiveTopDawgListingId($listingId, $sku)
            ? $listingId
            : (ChannelListingRegistry::isLiveTopDawgListingId($tdid, $sku) ? $tdid : '');
        if ($liveId === '') {
            return;
        }
        try {
            if (Schema::hasTable('topdawg_products')) {
                $payload = [
                    'topdawg_listing_id' => $liveId,
                    'product_title' => $title,
                    'price' => $price,
                    'msrp' => $price,
                    'remaining_inventory' => max(0, $inv),
                    'listing_state' => 'yes',
                ];
                if ($tdid !== '') {
                    $payload['tdid'] = $tdid;
                }
                $existing = TopDawgProduct::query()->where('sku', $sku)->first()
                    ?: TopDawgProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    TopDawgProduct::create(array_merge(['sku' => $sku], $payload));
                }
            }

            if (Schema::hasTable('topdawg_listing_statuses')) {
                $status = TopDawgListingStatus::query()->where('sku', $sku)->first();
                $value = $status && is_array($status->value) ? $status->value : [];
                $value['listed'] = 'Listed';
                $value['listing_id'] = $liveId;
                $value['topdawg_listing_id'] = $liveId;
                TopDawgListingStatus::updateOrCreate(['sku' => $sku], ['value' => $value]);
            }
        } catch (\Throwable $e) {
            Log::warning('TopDawg persist listed failed', [
                'sku' => $sku,
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function forgetListingCaches(): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            Cache::forget('listing_channel_counts_v1:inv:topdawg');
            Cache::forget('listing_channel_counts_v1:cp:topdawg');
        } catch (\Throwable) {
        }
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
        if (Schema::hasTable('topdawg_data_views')) {
            $view = TopDawgDataView::query()->where('sku', $sku)->first()
                ?: TopDawgDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            $meta = is_array($view?->value) ? $view->value : [];
            foreach (['sprice', 'SPRICE'] as $key) {
                if (isset($meta[$key]) && is_numeric($meta[$key]) && (float) $meta[$key] > 0) {
                    return round((float) $meta[$key], 2);
                }
            }
        }

        if (Schema::hasTable('topdawg_products')) {
            $row = TopDawgProduct::query()->where('sku', $sku)->first()
                ?: TopDawgProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
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
        $lp = isset($values['lp']) && is_numeric($values['lp']) ? (float) $values['lp'] : 0.0;
        $ship = isset($values['ship']) && is_numeric($values['ship']) ? (float) $values['ship'] : 0.0;
        if ($lp > 0) {
            return round($lp + $ship, 2);
        }

        return null;
    }

    private function resolveMsrp(string $sku, ProductMaster $product, float $price): float
    {
        $map = $this->valuesNumber($this->productValues($product), 'map', 'MAP', 'msrp', 'MSRP');
        if ($map !== null && $map >= $price) {
            return round($map, 2);
        }

        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        foreach (['compare_at_price', 'compare_at', 'msrp'] as $field) {
            $raw = $shopify->{$field} ?? null;
            if (is_numeric($raw) && (float) $raw >= $price) {
                return round((float) $raw, 2);
            }
        }

        return round($price, 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{weight: ?float, length: ?float, width: ?float, height: ?float}
     */
    private function packageInches(ProductMaster $product, string $sku, array $overrides = []): array
    {
        $fromDraft = [
            'length' => is_numeric($overrides['package_length'] ?? null) ? (float) $overrides['package_length'] : null,
            'width' => is_numeric($overrides['package_width'] ?? null) ? (float) $overrides['package_width'] : null,
            'height' => is_numeric($overrides['package_height'] ?? null) ? (float) $overrides['package_height'] : null,
            'weight' => $this->weightLbFromOverrides($overrides),
        ];
        if ($fromDraft['weight'] !== null && $fromDraft['length'] !== null && $fromDraft['width'] !== null && $fromDraft['height'] !== null) {
            return $fromDraft;
        }

        $values = $this->productValues($product);
        $pkg = [
            'weight' => $fromDraft['weight'] ?? $this->valuesNumber($values, 'wt_act', 'itm_wt_gw', 'wt_decl'),
            'length' => $fromDraft['length'] ?? $this->valuesNumber($values, 'l', 'l_decl'),
            'width' => $fromDraft['width'] ?? $this->valuesNumber($values, 'w', 'w_decl'),
            'height' => $fromDraft['height'] ?? $this->valuesNumber($values, 'h', 'h_decl'),
        ];
        if ($pkg['weight'] !== null && $pkg['length'] !== null && $pkg['width'] !== null && $pkg['height'] !== null) {
            return $pkg;
        }

        $parentSku = trim((string) ($product->parent ?? ''));
        if ($parentSku === '' || strcasecmp($parentSku, $sku) === 0) {
            return $pkg;
        }
        $parent = $this->findProduct($parentSku);
        if (! $parent) {
            return $pkg;
        }
        $parentValues = $this->productValues($parent);
        if ($pkg['weight'] === null) {
            $pkg['weight'] = $this->valuesNumber($parentValues, 'wt_act', 'itm_wt_gw', 'wt_decl');
        }
        if ($pkg['length'] === null) {
            $pkg['length'] = $this->valuesNumber($parentValues, 'l', 'l_decl');
        }
        if ($pkg['width'] === null) {
            $pkg['width'] = $this->valuesNumber($parentValues, 'w', 'w_decl');
        }
        if ($pkg['height'] === null) {
            $pkg['height'] = $this->valuesNumber($parentValues, 'h', 'h_decl');
        }

        return $pkg;
    }

    /**
     * @return array<string, mixed>
     */
    private function productValues(ProductMaster $product): array
    {
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        return is_array($values) ? $values : [];
    }

    private function valuesNumber(array $values, string ...$keys): ?float
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
     * @param  array<string, mixed>  $overrides
     */
    private function weightLbFromOverrides(array $overrides): ?float
    {
        $lb = is_numeric($overrides['package_weight_lb'] ?? null) ? (float) $overrides['package_weight_lb'] : 0.0;
        $oz = is_numeric($overrides['package_weight_oz'] ?? null) ? (float) $overrides['package_weight_oz'] : 0.0;
        $total = $lb + ($oz / 16);
        if (isset($overrides['product_weight']) && is_numeric($overrides['product_weight']) && (float) $overrides['product_weight'] > 0) {
            $total = max($total, (float) $overrides['product_weight']);
        }

        return $total > 0 ? round($total, 3) : null;
    }

    private function madeInCountry(string $raw): string
    {
        $raw = trim($raw);
        $map = [
            'CN' => 'China',
            'US' => 'United States',
            'IN' => 'India',
            'VN' => 'Vietnam',
            'TW' => 'Taiwan',
            'MX' => 'Mexico',
            'CA' => 'Canada',
        ];
        $upper = strtoupper($raw);
        if (isset($map[$upper])) {
            return $map[$upper];
        }

        return $raw !== '' ? $raw : 'China';
    }

    private function resolveDescription(ProductMaster $product, string $title): string
    {
        foreach (['product_description', 'description_800', 'description_600', 'description_1000'] as $col) {
            $text = trim(strip_tags((string) ($product->{$col} ?? '')));
            if ($text !== '') {
                return mb_substr($text, 0, 4000);
            }
        }

        return $title;
    }

    /**
     * @return list<string>
     */
    private function productImages(ProductMaster $product, string $sku): array
    {
        return ListingManagerAmazonHydrator::publishImageUrls($sku, trim((string) ($product->parent ?? '')), 8);
    }

    private function shopifyInv(string $sku): int
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return max(0, (int) ($shopify->available_to_sell ?? $shopify->inv ?? 0));
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

    /**
     * @return array{success: bool, categories: list<array{id: string, name: string, path: string}>}
     */
    public static function searchListingCategories(string $q, string $title = ''): array
    {
        $needle = mb_strtolower(trim($q));
        $out = [];
        foreach (self::categoryCatalog() as $row) {
            $hay = mb_strtolower($row['id'].' '.$row['path']);
            if ($needle !== '' && ! str_contains($hay, $needle)) {
                continue;
            }
            $out[] = [
                'id' => $row['id'],
                'name' => $row['category'],
                'path' => $row['path'],
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return ['success' => true, 'categories' => $out];
    }

    /**
     * @return array{dept: string, section: string, category: string}
     */
    public static function resolveCategory(?string $id, ?string $name): array
    {
        $id = trim((string) $id);
        $name = trim((string) $name);

        if ($id !== '') {
            foreach (self::categoryCatalog() as $row) {
                if (strcasecmp($row['id'], $id) === 0) {
                    return [
                        'dept' => $row['dept'],
                        'section' => $row['section'],
                        'category' => $row['category'],
                    ];
                }
            }
            if (str_contains($id, '|')) {
                return self::partsToCategory(explode('|', $id));
            }
        }

        if ($name !== '') {
            foreach (self::categoryCatalog() as $row) {
                if (strcasecmp($row['path'], $name) === 0 || strcasecmp($row['category'], $name) === 0) {
                    return [
                        'dept' => $row['dept'],
                        'section' => $row['section'],
                        'category' => $row['category'],
                    ];
                }
            }
            if (str_contains($name, '>')) {
                return self::partsToCategory(explode('>', $name));
            }

            return [
                'dept' => 'Electronics',
                'section' => 'Music',
                'category' => $name,
            ];
        }

        return [
            'dept' => 'Electronics',
            'section' => 'Music',
            'category' => 'Music Accessories',
        ];
    }

    /**
     * @param  list<string>  $parts
     * @return array{dept: string, section: string, category: string}
     */
    private static function partsToCategory(array $parts): array
    {
        $parts = array_values(array_filter(array_map('trim', $parts), static fn ($part) => $part !== ''));

        return [
            'dept' => $parts[0] ?? 'Electronics',
            'section' => $parts[1] ?? 'Music',
            'category' => $parts[2] ?? ($parts[1] ?? ($parts[0] ?? 'Music Accessories')),
        ];
    }

    /**
     * @return list<array{id: string, dept: string, section: string, category: string, path: string}>
     */
    public static function categoryCatalog(): array
    {
        $rows = [
            ['Electronics', 'Music', 'Music Accessories'],
            ['Electronics', 'Music', 'Microphones'],
            ['Electronics', 'Music', 'Microphone Accessories'],
            ['Electronics', 'Music', 'Wireless Microphones'],
            ['Electronics', 'Music', 'Speakers'],
            ['Electronics', 'Music', 'PA Speakers'],
            ['Electronics', 'Music', 'Headphones'],
            ['Electronics', 'Music', 'Cables'],
            ['Electronics', 'Music', 'Audio Cables'],
            ['Electronics', 'Music', 'Stands'],
            ['Electronics', 'Music', 'Microphone Stands'],
            ['Electronics', 'Music', 'Speaker Stands'],
            ['Electronics', 'Music', 'Keyboard Stands'],
            ['Electronics', 'Music', 'Mixers'],
            ['Electronics', 'Music', 'Amplifiers'],
            ['Electronics', 'Music', 'DJ Equipment'],
            ['Electronics', 'Music', 'DJ Controllers'],
            ['Electronics', 'Music', 'Lighting'],
            ['Electronics', 'Music', 'Stage Lighting'],
            ['Electronics', 'Music', 'Light Stands'],
            ['Electronics', 'Music', 'Tripods'],
            ['Electronics', 'Music', 'Wireless Systems'],
            ['Electronics', 'Audio', 'Accessories'],
            ['Electronics', 'Audio', 'Cables & Connectors'],
            ['Electronics', 'Audio', 'Headphones & Earphones'],
            ['Electronics', 'Lighting', 'Stage Lights'],
            ['Electronics', 'Lighting', 'Lighting Stands'],
            ['Electronics', 'Lighting', 'Lighting Accessories'],
            ['Musical Instruments', 'Accessories', 'Stands'],
            ['Musical Instruments', 'Accessories', 'Cables'],
            ['Musical Instruments', 'Accessories', 'Microphones'],
        ];

        $out = [];
        foreach ($rows as [$dept, $section, $category]) {
            $out[] = [
                'id' => $dept.'|'.$section.'|'.$category,
                'dept' => $dept,
                'section' => $section,
                'category' => $category,
                'path' => $dept.' > '.$section.' > '.$category,
            ];
        }

        return $out;
    }
}
