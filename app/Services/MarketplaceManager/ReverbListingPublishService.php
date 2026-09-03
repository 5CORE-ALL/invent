<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductCategory;
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
    public function publishSkus(array $skus, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = '', ?string $categoryUuid = null): array
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
                $one = $this->publishSkus([$sku], false, 'single', $parentHint, $categoryUuid);
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

        $resolved = $this->resolveCategory($product, $sku, $title, $categoryUuid);
        $categoryUuid = (string) ($resolved['id'] ?? '');
        if ($categoryUuid === '') {
            return [
                'success' => false,
                'message' => 'Could not match a Reverb category for the product type of '.$sku.'. Check the title or Product Master category, then try again.',
            ];
        }

        $condition = $this->resolveCondition($product);
        if ($condition['uuid'] === '') {
            return [
                'success' => false,
                'message' => 'Could not resolve a Reverb condition for '.$sku.'. Set REVERB_DEFAULT_CONDITION_UUID or reverb_condition on Reverb Listing Master.',
            ];
        }

        $values = is_array($product->Values) ? $product->Values : [];
        $make = trim((string) ($product->reverb_make ?? $values['brand'] ?? ''));
        if ($make === '' || strcasecmp($make, 'Unknown') === 0) {
            $make = $this->makeFromTitle($title);
        }
        $model = trim((string) ($product->reverb_model ?? ''));
        if ($model === '' || strcasecmp($model, 'Unknown') === 0) {
            $model = $sku;
        }

        $upc = trim((string) ($product->barcode ?? $values['upc'] ?? $values['gtin'] ?? $values['ean'] ?? ''));
        $inv = $this->publishInventory($sku, $condition['name']);
        $shippingProfileId = $this->resolveShippingProfileId($product, $sku);

        $fields = [
            'title' => $title,
            'make' => $make,
            'model' => $model,
            'finish' => trim((string) ($product->reverb_finish ?? '')),
            'year' => trim((string) ($product->reverb_year ?? '')),
            'sku' => $sku,
            'upc' => $upc,
            'upc_does_not_apply' => $upc === '',
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
        ]);

        $res = $this->api->createListing($fields);
        if (empty($res['success'])) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'Reverb create listing failed.',
            ];
        }

        $listingId = trim((string) ($res['listing_id'] ?? ''));
        $this->persistListed($sku, $listingId, $title, $price, $inv, (string) ($res['web_url'] ?? ''));
        $this->forgetListingCaches();

        return [
            'success' => true,
            'message' => 'Published '.$sku.' to Reverb'.($listingId !== '' ? ' (#'.$listingId.')' : '').'.',
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
     * @return array{id: string, path: string}
     */
    public function suggestCategoryForSku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['id' => '', 'path' => ''];
        }
        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first();
        if (! $product) {
            return ['id' => '', 'path' => ''];
        }

        return $this->resolveCategory($product, $sku, $this->resolveTitle($product, $sku));
    }

    /**
     * @return array{id: string, path: string}
     */
    private function resolveCategory(ProductMaster $product, string $sku, string $title, ?string $override = null): array
    {
        $override = trim((string) $override);
        if ($this->looksLikeUuid($override)) {
            return ['id' => $override, 'path' => ''];
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

    private function makeFromTitle(string $title): string
    {
        $first = trim((string) strtok($title, ' '));
        if ($first !== '' && strcasecmp($first, 'Unknown') !== 0 && mb_strlen($first) <= 40) {
            return $first;
        }

        return 'Generic';
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
            $url = $this->absoluteImageUrl($raw);
            if ($url === '' || in_array($url, $urls, true)) {
                return;
            }
            $urls[] = $url;
        };

        $push((string) ($product->main_image ?? ''));
        $push((string) ($product->main_image_brand ?? ''));
        $push((string) ($product->image_url ?? ''));
        for ($i = 1; $i <= 20; $i++) {
            $push((string) ($product->{'image'.$i} ?? ''));
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['hero_image', 'trust_image', 'ugc_image', 'image_path', 'image', 'Image', 'main_image', 'photo'] as $key) {
            $raw = $values[$key] ?? '';
            $push(is_array($raw) ? (string) ($raw[0]['url'] ?? $raw[0] ?? '') : (string) $raw);
        }
        if ($sku !== '') {
            $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
            if ($shopify) {
                $push((string) ($shopify->image_src ?? ''));
            }
        }

        return $urls;
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
