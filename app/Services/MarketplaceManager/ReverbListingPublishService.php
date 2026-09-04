<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductMaster;
use App\Models\ReverbListingStatus;
use App\Models\ReverbMetric;
use App\Models\ReverbPricingPrice;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use App\Services\ReverbApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to Reverb as independent listings (one SKU = one listing).
 */
class ReverbListingPublishService
{
    public function __construct(private ReverbApiService $api)
    {
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = '', ?string $categoryUuid = null, ?string $categoryName = null): array
    {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Reverb API credentials missing. Set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET (or REVERB_TOKEN).',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($expandSiblings && $mode === 'variation') {
            $publishSkus = $this->expandToPublishableSiblings($skus);
            if ($publishSkus === []) {
                return [
                    'success' => false,
                    'message' => $this->publishBlockReason($skus),
                ];
            }
        } else {
            $publishSkus = $skus;
        }

        if (count($publishSkus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastId = null;
            foreach ($publishSkus as $sku) {
                $one = $this->publishSkus([$sku], false, 'single', $parentHint, $categoryUuid, $categoryName);
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

        $title = $this->resolveTitle($product, $sku);
        if ($title === '') {
            return ['success' => false, 'message' => $sku.': Title missing in Title Master'];
        }

        $price = $this->resolvePrice($sku, $product);
        if ($price === null || $price <= 0) {
            return [
                'success' => false,
                'message' => 'No price found for '.$sku.'. Set Reverb pricing or Shopify price.',
            ];
        }

        $images = $this->productImages($product, $sku);
        if ($images === []) {
            return ['success' => false, 'message' => 'No public image URL for '.$sku.'. Add an https image on CP Master (or Shopify), not a local/filename-only path.'];
        }

        $resolved = $this->resolveCategory($product, $sku, $title, $categoryUuid, $categoryName);
        $categoryUuid = (string) ($resolved['id'] ?? '');
        if ($categoryUuid === '') {
            return [
                'success' => false,
                'message' => 'Could not match a Reverb category for '.$sku.'. Type a Reverb category name in the publish window, or check the Product Master category.',
            ];
        }

        $condition = $this->resolveCondition($product);
        if ($condition['uuid'] === '') {
            return [
                'success' => false,
                'message' => 'Could not resolve a Reverb condition for '.$sku.'. Set REVERB_DEFAULT_CONDITION_UUID or reverb_condition on Reverb Listing Master.',
            ];
        }

        $explicitMake = trim((string) ($product->reverb_make ?? ''));
        $make = ($explicitMake !== '' && strcasecmp($explicitMake, 'Unknown') !== 0 && strcasecmp($explicitMake, 'Generic') !== 0)
            ? $explicitMake
            : $this->defaultListingBrand();
        $model = trim((string) ($product->reverb_model ?? ''));
        if ($model === '' || strcasecmp($model, 'Unknown') === 0) {
            $model = $sku;
        }

        $inv = $this->publishInventory($sku, $condition['name']);
        $shippingProfileId = $this->resolveShippingProfileId($product, $sku);

        $fields = [
            'title' => $title,
            'make' => $make,
            'model' => $model,
            'finish' => trim((string) ($product->reverb_finish ?? '')),
            'year' => trim((string) ($product->reverb_year ?? '')),
            'sku' => $sku,
            'upc' => '',
            'upc_does_not_apply' => true,
            'description' => $this->resolveDescription($product, $title),
            'price_amount' => $price,
            'price_currency' => 'USD',
            'inventory' => $inv,
            'has_inventory' => true,
            'offers_enabled' => true,
            'condition_uuid' => $condition['uuid'],
            'category_uuid' => $categoryUuid,
            'photos' => $images,
            'publish' => true,
        ];
        if ($shippingProfileId !== '') {
            $fields['shipping_profile_id'] = $shippingProfileId;
        }

        Log::info('Reverb publish: creating listing', [
            'sku' => $sku,
            'parent' => trim($parentHint) !== '' ? trim($parentHint) : $this->groupKey($product),
            'category_uuid' => $categoryUuid,
            'category_path' => (string) ($resolved['path'] ?? ''),
            'condition' => $condition['name'],
            'photo_count' => count($images),
            'make' => $make,
        ]);

        $res = $this->api->createListing($fields);
        if (empty($res['success'])) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'Reverb create listing failed.',
            ];
        }

        $listingId = trim((string) ($res['listing_id'] ?? ''));
        if ($listingId !== '' && $images !== []) {
            $imgRes = $this->api->updateListingImages($listingId, $images, 'replace');
            if (empty($imgRes['success'])) {
                Log::warning('Reverb publish: listing created but full image replace failed', [
                    'sku' => $sku,
                    'listing_id' => $listingId,
                    'image_count' => count($images),
                    'message' => $imgRes['message'] ?? '',
                ]);
            }
        }
        $this->persistListed($sku, $listingId, $title, $price, $inv, (string) ($res['web_url'] ?? ''));
        $this->forgetListingCaches();

        return [
            'success' => true,
            'message' => 'Published '.$sku.' to Reverb'.($listingId !== '' ? ' (#'.$listingId.')' : '')
                .' with '.count($images).' photo'.(count($images) === 1 ? '' : 's').'.',
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
    private function filterPublishable(array $skus): array
    {
        $cfg = ChannelListingRegistry::get('reverb');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn ($row) => strtolower(trim((string) $row->sku)));

        $statusRows = [];
        if (Schema::hasTable('reverb_listing_statuses')) {
            foreach (ReverbListingStatus::query()->whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
                $statusRows[strtolower(trim((string) $row->sku))] = is_array($row->value) ? $row->value : [];
            }
        }

        $out = [];
        foreach ($skus as $sku) {
            $sku = trim($sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            if (trim((string) ($listedMap[strtolower($sku)] ?? '')) !== '') {
                continue;
            }
            $status = $statusRows[strtolower($sku)] ?? [];
            $nr = strtoupper(trim((string) ($status['rl_nrl'] ?? $status['NRL'] ?? $status['nr_req'] ?? '')));
            if (in_array($nr, ['NR', 'NRL'], true)) {
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

    /**
     * @return array{id: string, path: string, name: string}
     */
    public function suggestCategoryForSku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['id' => '', 'path' => '', 'name' => ''];
        }
        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first();
        if (! $product) {
            return ['id' => '', 'path' => '', 'name' => ''];
        }

        $title = $this->resolveTitle($product, $sku);
        $resolved = $this->resolveCategory($product, $sku, $title);
        $name = $this->productCategoryLabel($product);
        if ($name === '' && trim((string) ($resolved['path'] ?? '')) !== '') {
            $path = (string) $resolved['path'];
            $name = trim((string) substr($path, (int) strrpos($path, '>') + 1));
        }
        $resolved['name'] = $name;

        return $resolved;
    }

    /**
     * @return array{id: string, path: string}
     */
    private function resolveCategory(ProductMaster $product, string $sku, string $title, ?string $overrideUuid = null, ?string $overrideName = null): array
    {
        $overrideUuid = trim((string) $overrideUuid);
        if ($this->looksLikeUuid($overrideUuid)) {
            return ['id' => $overrideUuid, 'path' => ''];
        }

        $overrideName = trim((string) $overrideName);
        if ($this->looksLikeUuid($overrideName)) {
            return ['id' => $overrideName, 'path' => ''];
        }
        if ($overrideName !== '') {
            $typed = $this->api->resolveCategoryByName($overrideName);
            if ($this->looksLikeUuid((string) ($typed['id'] ?? ''))) {
                return $typed;
            }
        }

        foreach ($this->productTypeHints($product, $sku, $title) as $hint) {
            if ($this->looksLikeSkuCode($hint)) {
                continue;
            }
            $fromProduct = $this->api->resolveCategoryByName($hint);
            if ($this->looksLikeUuid((string) ($fromProduct['id'] ?? ''))) {
                return $fromProduct;
            }
        }

        $suggested = $this->api->suggestListingCategory($title, $this->productTypeHints($product, $sku, $title));
        if ($this->looksLikeUuid((string) ($suggested['id'] ?? ''))) {
            return [
                'id' => (string) $suggested['id'],
                'path' => (string) ($suggested['path'] ?? ''),
            ];
        }

        $siblingId = $this->siblingListingId($product, $sku);
        if ($siblingId !== '') {
            $listing = $this->api->getListing($siblingId);
            $cats = is_array($listing['categories'] ?? null) ? $listing['categories'] : [];
            foreach ($cats as $cat) {
                if (! is_array($cat)) {
                    continue;
                }
                $uuid = trim((string) ($cat['uuid'] ?? $cat['id'] ?? ''));
                if ($this->looksLikeUuid($uuid)) {
                    return [
                        'id' => $uuid,
                        'path' => trim((string) ($cat['full_name'] ?? $cat['name'] ?? 'Same as listed sibling')),
                    ];
                }
            }
        }

        $configured = trim((string) config('services.reverb.default_category_uuid', ''));
        if ($this->looksLikeUuid($configured)) {
            return ['id' => $configured, 'path' => 'Default Reverb category'];
        }

        return ['id' => '', 'path' => ''];
    }

    private function productCategoryLabel(ProductMaster $product): string
    {
        $categoryId = (int) ($product->category_id ?? 0);
        if ($categoryId > 0) {
            $name = trim((string) ProductCategory::query()->where('id', $categoryId)->value('category_name'));
            if ($name !== '') {
                return $name;
            }
        }
        if (Schema::hasColumn('product_master', 'category')) {
            $legacy = trim((string) ($product->getAttribute('category') ?? ''));
            if ($legacy !== '' && ! $this->looksLikeSkuCode($legacy)) {
                return $legacy;
            }
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['type', 'Type', 'product_type', 'category', 'Category'] as $key) {
            $raw = trim((string) ($values[$key] ?? ''));
            if ($raw !== '' && ! $this->looksLikeSkuCode($raw)) {
                return $raw;
            }
        }

        return '';
    }

    private function looksLikeSkuCode(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[a-z]{3,}/', $value)) {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9][A-Z0-9 ._\-]{0,32}$/', $value);
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

    /**
     * @return array{uuid: string, name: string}
     */
    private function resolveCondition(ProductMaster $product): array
    {
        $configured = trim((string) config('services.reverb.default_condition_uuid', ''));
        $wanted = strtolower(trim((string) ($product->reverb_condition ?? '')));
        $conditions = $this->api->listingConditions();

        if ($this->looksLikeUuid($configured)) {
            $name = 'Brand New';
            foreach ($conditions as $row) {
                if (strcasecmp((string) ($row['id'] ?? ''), $configured) === 0) {
                    $name = (string) ($row['name'] ?? $name);
                    break;
                }
            }

            return ['uuid' => $configured, 'name' => $name];
        }

        $preferred = $wanted !== ''
            ? [$wanted]
            : ['brand new', 'new', 'mint', 'b-stock', 'b stock'];

        foreach ($preferred as $needle) {
            foreach ($conditions as $row) {
                $name = strtolower(trim((string) ($row['name'] ?? '')));
                $id = trim((string) ($row['id'] ?? ''));
                if ($name === '' || ! $this->looksLikeUuid($id)) {
                    continue;
                }
                if ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
                    return ['uuid' => $id, 'name' => (string) $row['name']];
                }
            }
        }

        foreach ($conditions as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($this->looksLikeUuid($id)) {
                return ['uuid' => $id, 'name' => (string) ($row['name'] ?? 'Brand New')];
            }
        }

        return ['uuid' => '', 'name' => ''];
    }

    private function resolveShippingProfileId(ProductMaster $product, string $sku): string
    {
        $fromMaster = trim((string) ($product->reverb_shipping_profile_id ?? ''));
        if ($fromMaster !== '') {
            return $fromMaster;
        }

        $siblingId = $this->siblingListingId($product, $sku);
        if ($siblingId !== '') {
            $listing = $this->api->getListing($siblingId);
            $id = trim((string) ($listing['shipping_profile_id'] ?? $listing['shipping']['profile_id'] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return $this->api->firstShippingProfileId();
    }

    private function siblingListingId(ProductMaster $product, string $sku): string
    {
        $parent = $this->groupKey($product);
        $siblings = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parent)
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->pluck('sku')
            ->all();
        $siblings = array_values(array_filter($siblings, fn ($s) => strcasecmp((string) $s, $sku) !== 0));
        if ($siblings === []) {
            return '';
        }

        $cfg = ChannelListingRegistry::get('reverb');
        $map = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $siblings) : [];
        foreach ($siblings as $sib) {
            $id = trim((string) ($map[strtolower((string) $sib)] ?? ''));
            if ($id !== '' && strcasecmp($id, (string) $sib) !== 0) {
                return $id;
            }
        }

        return '';
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
        if (Schema::hasTable('reverb_pricing_prices')) {
            $row = ReverbPricingPrice::query()->where('sku', $sku)->first()
                ?: ReverbPricingPrice::query()->where('sku', strtoupper($sku))->first()
                ?: ReverbPricingPrice::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->first();
            if ($row && is_numeric($row->price) && (float) $row->price > 0) {
                return round((float) $row->price, 2);
            }
        }

        if (Schema::hasTable('reverb_metric')) {
            $metric = ReverbMetric::query()->where('sku', $sku)->first()
                ?: ReverbMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            if ($metric && is_numeric($metric->price) && (float) $metric->price > 0) {
                return round((float) $metric->price, 2);
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
        foreach (['msrp', 'MSRP', 'price'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                return round((float) $values[$key], 2);
            }
        }

        return null;
    }

    private function shopifyInv(string $sku): int
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return max(0, (int) ($shopify->available_to_sell ?? $shopify->inv ?? 0));
    }

    private function publishInventory(string $sku, string $conditionName): int
    {
        $inv = $this->shopifyInv($sku);
        $allowsMulti = in_array(strtolower(trim($conditionName)), ReverbListingValidator::MULTI_QTY_CONDITIONS, true)
            || str_contains(strtolower($conditionName), 'brand new')
            || str_contains(strtolower($conditionName), 'mint');
        if (! $allowsMulti) {
            return min(1, $inv);
        }

        return $inv;
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
                return $text;
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

        return $title;
    }

    private function defaultListingBrand(): string
    {
        $brand = trim((string) config('listing_manager.default_brand', '5 Core Inc.'));

        return $brand !== '' ? $brand : '5 Core Inc.';
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

    private function publishBlockReason(array $skus): string
    {
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
            $reasons[] = $sku.': already listed or NRL';
        }

        return $reasons !== []
            ? implode('; ', $reasons)
            : 'No Missing L child SKUs left to publish (already listed, NRL, or missing images).';
    }

    /**
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

        $this->pushMasterGallery($product, $push);

        $childSku = $sku !== '' ? $sku : trim((string) $product->sku);
        $parentSku = trim((string) ($product->parent ?? ''));
        if ($parentSku !== '' && strcasecmp($parentSku, $childSku) !== 0) {
            $parent = $this->findProduct($parentSku);
            if ($parent) {
                $this->pushMasterGallery($parent, $push);
            }
        }

        if ($childSku !== '') {
            foreach ($this->shopifyCatalogImages($childSku) as $url) {
                $push((string) $url);
            }
            foreach ($this->productImageRows($childSku) as $url) {
                $push((string) $url);
            }
            foreach ($this->reverbImageMasterUrls($childSku) as $url) {
                $push((string) $url);
            }
        }

        return array_slice($urls, 0, 25);
    }

    /**
     * @param  callable(string): void  $push
     */
    private function pushMasterGallery(ProductMaster $product, callable $push): void
    {
        $push((string) ($product->main_image ?? ''));
        for ($i = 1; $i <= 20; $i++) {
            $push((string) ($product->{'image'.$i} ?? ''));
        }
        $push((string) ($product->main_image_brand ?? ''));
        $push((string) ($product->image_url ?? ''));

        $v2 = $product->description_v2_images ?? null;
        if (is_array($v2)) {
            foreach ($v2 as $item) {
                $push(is_array($item) ? (string) ($item['url'] ?? $item['src'] ?? '') : (string) $item);
            }
        } elseif (is_string($v2) && $v2 !== '') {
            $push($v2);
        }

        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['hero_image', 'trust_image', 'ugc_image', 'image_path', 'image', 'Image', 'main_image', 'photo', 'images', 'gallery'] as $key) {
            $raw = $values[$key] ?? '';
            if (is_array($raw)) {
                foreach ($raw as $item) {
                    $push(is_array($item) ? (string) ($item['url'] ?? $item['src'] ?? $item['href'] ?? '') : (string) $item);
                }
            } else {
                $push((string) $raw);
            }
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
                $out = [];
                $walk = function ($item) use (&$out, &$walk): void {
                    if (is_string($item) && trim($item) !== '') {
                        $out[] = trim($item);
                    } elseif (is_array($item)) {
                        $url = $item['url'] ?? $item['src'] ?? $item['href'] ?? null;
                        if (is_string($url) && trim($url) !== '') {
                            $out[] = trim($url);
                        } else {
                            foreach ($item as $nested) {
                                $walk($nested);
                            }
                        }
                    }
                };
                $walk($decoded);

                return $out !== [] ? $out : [];
            }
        }
        if (str_contains($raw, "\n") || str_contains($raw, '|')) {
            $parts = preg_split('/[\r\n|]+/', $raw) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts)));
            if (count($parts) > 1) {
                return $parts;
            }
        }

        return [$raw];
    }

    /**
     * @return list<string>
     */
    private function shopifyCatalogImages(string $sku): array
    {
        $urls = [];
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        if ($shopify) {
            $urls[] = (string) ($shopify->image_src ?? '');
        }
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return $urls;
        }

        $select = ['p.id'];
        foreach (['image_src', 'images', 'image_urls'] as $col) {
            if (Schema::hasColumn('shopify_catalog_products', $col)) {
                $select[] = 'p.'.$col;
            }
        }

        $row = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->whereRaw('UPPER(TRIM(COALESCE(v.sku, \'\'))) = ?', [strtoupper($sku)])
            ->orderByDesc('v.id')
            ->select($select)
            ->first();
        if (! $row) {
            return $urls;
        }

        foreach (['image_urls', 'images'] as $col) {
            if (! isset($row->{$col})) {
                continue;
            }
            $decoded = is_array($row->{$col}) ? $row->{$col} : json_decode((string) $row->{$col}, true);
            if (! is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $item) {
                if (is_string($item)) {
                    $urls[] = $item;
                } elseif (is_array($item)) {
                    $urls[] = (string) ($item['src'] ?? $item['url'] ?? '');
                }
            }
        }
        $urls[] = (string) ($row->image_src ?? '');

        return $urls;
    }

    /**
     * @return list<string>
     */
    private function productImageRows(string $sku): array
    {
        if (! Schema::hasTable('product_images')) {
            return [];
        }

        return ProductImage::query()
            ->where(function ($query) use ($sku): void {
                $query->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            })
            ->get()
            ->map(function (ProductImage $row): string {
                $cdn = trim((string) ($row->cdn_url ?? ''));

                return $cdn !== '' ? $cdn : (string) ($row->image_path ?? '');
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    private function reverbImageMasterUrls(string $sku): array
    {
        if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'image_master_json')) {
            return [];
        }

        $raw = ReverbProduct::query()
            ->where(function ($query) use ($sku): void {
                $query->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            })
            ->value('image_master_json');
        if (is_array($raw)) {
            return array_map(static fn ($item) => is_array($item) ? (string) ($item['url'] ?? $item['src'] ?? '') : (string) $item, $raw);
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded)
            ? array_map(static fn ($item) => is_array($item) ? (string) ($item['url'] ?? $item['src'] ?? '') : (string) $item, $decoded)
            : [];
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

        $base = rtrim((string) (config('services.reverb.sku_image_public_base_url') ?: config('app.url')), '/');
        if ($base === '') {
            return '';
        }
        if (str_starts_with($raw, '/')) {
            return $base.$raw;
        }

        return $base.'/'.ltrim($raw, '/');
    }

    private function persistListed(string $sku, string $listingId, string $title, float $price, int $inv, string $webUrl): void
    {
        try {
            if (Schema::hasTable('reverb_products')) {
                $payload = [
                    'reverb_listing_id' => $listingId !== '' ? $listingId : null,
                    'listing_state' => 'live',
                    'product_title' => $title,
                    'price' => $price,
                    'remaining_inventory' => $inv,
                    'last_synced_at' => now(),
                    'status' => 'live',
                ];
                $existing = ReverbProduct::query()->where('sku', $sku)->first()
                    ?: ReverbProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    ReverbProduct::create(array_merge(['sku' => $sku], $payload));
                }
            }

            if ($listingId !== '' && Schema::hasTable('reverb_metric')) {
                ReverbMetric::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $listingId,
                        'product_name' => $title,
                        'price' => $price,
                    ]
                );
            }

            if (Schema::hasTable('reverb_pricing_prices')) {
                ReverbPricingPrice::updateOrCreate(
                    ['sku' => strtoupper($sku)],
                    [
                        'price' => $price,
                        'rv_stock' => max(0, $inv),
                    ]
                );
            }

            if (Schema::hasTable('reverb_listing_statuses')) {
                $status = ReverbListingStatus::query()->where('sku', $sku)->orderByDesc('updated_at')->first();
                $value = $status && is_array($status->value) ? $status->value : [];
                $value['listed'] = 'Listed';
                $value['listing_id'] = $listingId;
                $value['state'] = 'live';
                $value['buyer_link'] = $webUrl !== '' ? $webUrl : ($listingId !== '' ? 'https://reverb.com/item/'.$listingId : ($value['buyer_link'] ?? ''));
                $value['seller_link'] = $listingId !== ''
                    ? 'https://reverb.com/my/selling/listings/'.$listingId
                    : ($value['seller_link'] ?? '');
                ReverbListingStatus::updateOrCreate(['sku' => $sku], ['value' => $value]);
            }
        } catch (\Throwable $e) {
            Log::warning('Reverb persist listed failed', [
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
            Cache::forget('listing_channel_counts_v1:reverb');
            Cache::forget(ReverbLiveListingsService::CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
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
