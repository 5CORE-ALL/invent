<?php

namespace App\Services\MarketplaceManager;

use App\Models\FaireDataView;
use App\Models\FaireListingStatus;
use App\Models\FaireMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\FaireApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Publish Missing L SKUs to Faire as parent variation listings.
 */
class FaireListingPublishService
{
    public function __construct(private FaireApiService $api)
    {
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, bool $expandSiblings = true, string $mode = 'variation'): array
    {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Faire API credentials missing. Set FAIRE_ACCESS_TOKEN (or FAIRE_APP_ID / FAIRE_APP_SECRET).',
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

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $publishSkus)
            ->get()
            ->keyBy(fn ($row) => (string) $row->sku);

        $parents = [];
        foreach ($publishSkus as $sku) {
            $product = $products->get($sku);
            if (! $product) {
                return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
            }
            $parents[$this->groupKey($product)] = true;
        }
        if (count($parents) > 1) {
            return [
                'success' => false,
                'message' => 'Selected SKUs belong to more than one parent. Use Publish selected so each parent is listed as its own variation.',
            ];
        }

        $primarySku = $publishSkus[0];
        $primary = $products->get($primarySku);
        $title = $this->resolveTitle($primary, $primarySku);
        if ($title === '') {
            return ['success' => false, 'message' => 'No title found (product_master title80/title100/title150 or Shopify product_title).'];
        }

        $prepared = [];
        foreach ($publishSkus as $sku) {
            $product = $products->get($sku);
            $price = $this->resolveWholesalePrice($sku, $product);
            if ($price === null || $price <= 0) {
                return [
                    'success' => false,
                    'message' => 'No Faire wholesale price (SPRICE) for '.$sku.'. Set SPRICE on Faire pricing first.',
                ];
            }
            $images = $this->productImages($product);
            if ($images === []) {
                return ['success' => false, 'message' => 'No images on product master for '.$sku.'.'];
            }
            $prepared[] = [
                'sku' => $sku,
                'product' => $product,
                'price' => $price,
                'images' => $images,
                'inv' => $this->shopifyInv($sku),
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        $existingProductId = $mode === 'variation' ? $this->existingParentProductId($primary) : '';
        if ($existingProductId !== '') {
            $result = $this->addVariantsToProduct($existingProductId, $prepared, $title, $primary);
        } else {
            $result = $this->createProduct($title, $primary, $prepared);
        }

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $productId = (string) ($result['goods_id'] ?? '');
        try {
            $this->persistListed($prepared, $productId, $title);
        } catch (\Throwable $e) {
            // Product is already on Faire; local Missing L refresh can catch up on next sync.
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? ('Published '.count($prepared).' variation(s) to Faire.'),
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
        $cfg = ChannelListingRegistry::get('faire');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $nrValues = ListingCountsEngine::loadNrValues(FaireDataView::class, $skus);
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
            if (! $product || $this->productImages($product) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @param  list<array{sku: string, product: ProductMaster, price: float, images: list<string>, inv: int}>  $prepared
     * @return array{success: bool, message: string, goods_id?: string}
     */
    private function createProduct(string $title, ProductMaster $primary, array $prepared): array
    {
        $taxonomy = $this->resolveTaxonomyType();
        if ($taxonomy === null) {
            return [
                'success' => false,
                'message' => 'Faire taxonomy type is missing. Set FAIRE_TAXONOMY_TYPE_ID or sync at least one existing Faire product.',
            ];
        }

        $values = [];
        $gallery = [];
        $variants = [];
        foreach ($prepared as $row) {
            $values[] = $row['sku'];
            foreach ($row['images'] as $url) {
                if (! in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
            }
            $variants[] = $this->variantPayload($row);
        }

        $description = trim((string) ($primary->product_description ?? $primary->description_800 ?? $primary->description_600 ?? ''));
        if (strlen($description) > 1000) {
            $description = substr($description, 0, 997).'...';
        }
        $short = trim((string) ($primary->title60 ?? ''));
        if ($short === '') {
            $short = substr($title, 0, 75);
        }

        $body = [
            'idempotence_token' => (string) Str::uuid(),
            'name' => $title,
            'unit_multiplier' => 1,
            'minimum_order_quantity' => 1,
            'taxonomy_type' => $taxonomy,
            'variant_option_sets' => [
                ['name' => 'Variation', 'values' => $values],
            ],
            'variants' => $variants,
            'made_in_country' => 'CHN',
        ];
        if ($description !== '') {
            $body['description'] = $description;
        }
        if ($short !== '') {
            $body['short_description'] = substr($short, 0, 75);
        }
        if ($gallery !== []) {
            $body['images'] = array_map(static fn (string $url) => ['url' => $url], array_slice($gallery, 0, 8));
        }

        $res = $this->api->createProduct($body);
        if (empty($res['success'])) {
            if (isset($body['images'])) {
                unset($body['images']);
                $body['idempotence_token'] = (string) Str::uuid();
                $res = $this->api->createProduct($body);
            }
        }

        if (empty($res['success'])) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'Faire create product failed.',
            ];
        }

        $productId = (string) ($res['product_id'] ?? '');

        return [
            'success' => true,
            'message' => 'Published '.count($prepared).' variation(s) to Faire.',
            'goods_id' => $productId,
        ];
    }

    /**
     * @param  list<array{sku: string, product: ProductMaster, price: float, images: list<string>, inv: int}>  $prepared
     * @return array{success: bool, message: string, goods_id?: string}
     */
    private function addVariantsToProduct(string $productId, array $prepared, string $title, ProductMaster $primary): array
    {
        $info = $this->api->getProductInfo($productId);
        $data = (! empty($info['success']) && is_array($info['data'] ?? null)) ? $info['data'] : [];
        $sets = $this->optionSetsFromProduct($data);
        if ($sets === []) {
            return $this->createProduct($title, $primary, $prepared);
        }

        $created = 0;
        foreach ($prepared as $row) {
            $payload = $this->variantPayload($row, $sets, $data);
            $payload['idempotence_token'] = (string) Str::uuid();
            $res = $this->api->createVariant($productId, $payload);
            if (empty($res['success']) && $this->isMissingOptionsError((string) ($res['message'] ?? ''))) {
                $retry = $payload;
                unset($retry['options']);
                $retry['options'] = array_map(static function (array $opt) {
                    return array_filter([
                        'name' => $opt['name'] ?? null,
                        'value' => $opt['value'] ?? null,
                    ], fn ($v) => $v !== null && $v !== '');
                }, $payload['options']);
                $retry['idempotence_token'] = (string) Str::uuid();
                $res = $this->api->createVariant($productId, $retry);
            }
            if (empty($res['success'])) {
                return [
                    'success' => false,
                    'message' => ($res['message'] ?? 'Faire add variant failed').' ('.$row['sku'].')',
                    'goods_id' => $productId,
                ];
            }
            $created++;
        }

        return [
            'success' => true,
            'message' => 'Added '.$created.' variation(s) to the existing Faire listing.',
            'goods_id' => $productId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name: string, id?: string}>
     */
    private function optionSetsFromProduct(array $data): array
    {
        $sets = [];
        foreach ([$data['variant_option_sets'] ?? [], $data['options'] ?? []] as $bag) {
            if (! is_array($bag)) {
                continue;
            }
            foreach ($bag as $key => $row) {
                if (is_string($row) && ! is_numeric($key)) {
                    $name = trim((string) $key);
                    if ($name !== '' && ! $this->hasOptionSet($sets, $name)) {
                        $sets[] = ['name' => $name];
                    }
                    continue;
                }
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? $row['option_name'] ?? $row['display_name'] ?? ''));
                if ($name === '' && ! is_numeric($key)) {
                    $name = trim((string) $key);
                }
                if ($name === '') {
                    continue;
                }
                $set = ['name' => $name];
                $id = trim((string) ($row['id'] ?? $row['option_id'] ?? $row['variant_option_set_id'] ?? ''));
                if ($id !== '') {
                    $set['id'] = $id;
                }
                if (! $this->hasOptionSet($sets, $name)) {
                    $sets[] = $set;
                }
            }
        }

        $variants = $data['variants'] ?? $data['product_variants'] ?? [];
        if (is_array($variants)) {
            foreach ($variants as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                foreach ($variant['options'] ?? [] as $opt) {
                    if (! is_array($opt)) {
                        continue;
                    }
                    $name = trim((string) ($opt['name'] ?? $opt['option_name'] ?? ''));
                    if ($name === '' || $this->hasOptionSet($sets, $name)) {
                        continue;
                    }
                    $set = ['name' => $name];
                    $id = trim((string) ($opt['id'] ?? $opt['option_id'] ?? ''));
                    if ($id !== '') {
                        $set['id'] = $id;
                    }
                    $sets[] = $set;
                }
            }
        }

        return $sets;
    }

    /**
     * @param  list<array{name: string, id?: string}>  $sets
     */
    private function hasOptionSet(array $sets, string $name): bool
    {
        foreach ($sets as $set) {
            if (strcasecmp((string) ($set['name'] ?? ''), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function firstVariantOptionValues(array $data): array
    {
        $variants = $data['variants'] ?? $data['product_variants'] ?? [];
        if (! is_array($variants)) {
            return [];
        }
        foreach ($variants as $variant) {
            if (! is_array($variant) || ! is_array($variant['options'] ?? null)) {
                continue;
            }
            $out = [];
            foreach ($variant['options'] as $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $name = trim((string) ($opt['name'] ?? $opt['option_name'] ?? ''));
                $value = trim((string) ($opt['value'] ?? $opt['option_value'] ?? ''));
                if ($name !== '' && $value !== '') {
                    $out[$name] = $value;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        return [];
    }

    /**
     * @param  list<array{name: string, id?: string}>  $sets
     * @return list<string>
     */
    private function existingOptionValuesForSet(array $data, string $name): array
    {
        $out = [];
        foreach ($data['variant_option_sets'] ?? [] as $row) {
            if (! is_array($row) || strcasecmp((string) ($row['name'] ?? ''), $name) !== 0) {
                continue;
            }
            foreach ($row['values'] ?? [] as $value) {
                $value = is_array($value) ? trim((string) ($value['value'] ?? $value['name'] ?? '')) : trim((string) $value);
                if ($value !== '') {
                    $out[] = $value;
                }
            }
        }
        foreach ($data['variants'] ?? $data['product_variants'] ?? [] as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            foreach ($variant['options'] ?? [] as $opt) {
                if (! is_array($opt) || strcasecmp((string) ($opt['name'] ?? ''), $name) !== 0) {
                    continue;
                }
                $value = trim((string) ($opt['value'] ?? ''));
                if ($value !== '' && ! in_array($value, $out, true)) {
                    $out[] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{sku: string, product: ProductMaster, price: float, images: list<string>, inv: int}  $row
     * @param  list<array{name: string, id?: string}>|string  $optionSets
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    private function variantPayload(array $row, array|string $optionSets = 'Variation', array $productData = []): array
    {
        $wholesaleMinor = (int) round($row['price'] * 100);
        $retailMinor = (int) round($this->resolveRetailPrice($row['sku'], $row['product'], $row['price']) * 100);
        $sets = is_string($optionSets)
            ? [['name' => $optionSets]]
            : $optionSets;
        if ($sets === []) {
            $sets = [['name' => 'Variation']];
        }
        $copied = $this->firstVariantOptionValues($productData);
        $primaryName = (string) ($sets[0]['name'] ?? 'Variation');
        $used = $this->existingOptionValuesForSet($productData, $primaryName);
        $primaryValue = $this->optionValueForSku($row['sku'], $used);

        $options = [];
        foreach ($sets as $index => $set) {
            $name = trim((string) ($set['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $index === 0
                ? $primaryValue
                : (string) ($copied[$name] ?? $primaryValue);
            $opt = ['name' => $name, 'value' => $value];
            if (trim((string) ($set['id'] ?? '')) !== '') {
                $opt['id'] = trim((string) $set['id']);
            }
            $options[] = $opt;
        }

        return [
            'sku' => $row['sku'],
            'options' => $options,
            'available_quantity' => max(0, (int) $row['inv']),
            'prices' => [[
                'geo_constraint' => ['country' => 'USA'],
                'wholesale_price' => ['amount_minor' => $wholesaleMinor, 'currency' => 'USD'],
                'retail_price' => ['amount_minor' => $retailMinor, 'currency' => 'USD'],
            ]],
        ];
    }

    /**
     * @param  list<string>  $used
     */
    private function optionValueForSku(string $sku, array $used = []): string
    {
        $sku = trim($sku);
        $looksLikeSku = false;
        foreach ($used as $value) {
            if (stripos($value, 'KS ') !== false || preg_match('/\s/', $value)) {
                $looksLikeSku = true;
                break;
            }
        }
        if ($looksLikeSku || $used === []) {
            $candidate = $sku;
        } else {
            $parts = preg_split('/\s+/', $sku) ?: [];
            $tail = (string) end($parts);
            $map = [
                'GEAR' => 'Gear', 'PINK' => 'Pink', 'WH' => 'White', 'WHT' => 'White',
                'GLD' => 'Gold', 'GOLD' => 'Gold', 'BLK' => 'Black', 'RED' => 'Red',
                'BLU' => 'Blue', 'BLUE' => 'Blue',
            ];
            $candidate = $map[strtoupper($tail)] ?? (ucwords(strtolower($tail)) ?: $sku);
        }
        $base = $candidate;
        $n = 2;
        while (in_array($candidate, $used, true)) {
            $candidate = $base.' '.$n;
            $n++;
        }

        return $candidate;
    }

    private function isMissingOptionsError(string $message): bool
    {
        return str_contains(strtolower($message), 'must have options')
            || str_contains(strtolower($message), 'options');
    }

    /**
     * @param  list<array{sku: string, product: ProductMaster, price: float, images: list<string>, inv: int}>  $prepared
     */
    private function persistListed(array $prepared, string $productId, string $title): void
    {
        if ($productId === '') {
            return;
        }

        foreach ($prepared as $row) {
            $sku = $row['sku'];
            $metric = FaireMetric::query()->where('sku', $sku)->first() ?: new FaireMetric;
            $metric->sku = $sku;
            $metric->product_id = $productId;
            if ($title !== '' && Schema::hasColumn($metric->getTable(), 'product_name')) {
                $metric->product_name = $title;
            }
            if (($row['price'] ?? 0) > 0 && Schema::hasColumn($metric->getTable(), 'price')) {
                $metric->price = $row['price'];
            }
            if (Schema::hasColumn($metric->getTable(), 'inventory')) {
                $metric->inventory = (int) ($row['inv'] ?? 0);
            }
            $metric->save();

            $status = FaireListingStatus::where('sku', $sku)->first();
            $value = $status ? ($status->value ?? []) : [];
            $value['listed'] = 'Listed';
            $value['buyer_link'] = 'https://www.faire.com/product/'.$productId;
            $value['seller_link'] = 'https://www.faire.com/brand-portal/my-shop/products';
            FaireListingStatus::updateOrCreate(['sku' => $sku], ['value' => $value]);
        }
    }

    private function existingParentProductId(ProductMaster $primary): string
    {
        $parent = $this->groupKey($primary);
        $siblingSkus = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parent)
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->pluck('sku')
            ->all();

        if ($siblingSkus === []) {
            return '';
        }

        $metric = FaireMetric::query()
            ->whereIn('sku', $siblingSkus)
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->orderByDesc('id')
            ->first();

        return $metric ? trim((string) $metric->product_id) : '';
    }

    /**
     * @return array{id: string}|null
     */
    private function resolveTaxonomyType(): ?array
    {
        $configured = trim((string) config('services.faire.taxonomy_type_id', ''));
        if ($configured !== '') {
            return ['id' => $configured];
        }

        $existingId = FaireMetric::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->orderByDesc('id')
            ->value('product_id');
        if ($existingId) {
            $info = $this->api->getProductInfo((string) $existingId);
            $taxonomy = $info['data']['taxonomy_type'] ?? null;
            if (is_array($taxonomy) && trim((string) ($taxonomy['id'] ?? '')) !== '') {
                return ['id' => trim((string) $taxonomy['id'])];
            }
        }

        $res = $this->api->getTaxonomyTypes();
        $types = $res['types'] ?? [];
        if (isset($types[0]['id']) && trim((string) $types[0]['id']) !== '') {
            return ['id' => trim((string) $types[0]['id'])];
        }

        return null;
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

    private function resolveWholesalePrice(string $sku, ProductMaster $product): ?float
    {
        $view = FaireDataView::query()->where('sku', $sku)->first();
        $meta = is_array($view?->value) ? $view->value : [];
        foreach (['SPRICE', 'sprice'] as $key) {
            if (isset($meta[$key]) && is_numeric($meta[$key]) && (float) $meta[$key] > 0) {
                return round((float) $meta[$key], 2);
            }
        }

        $metric = FaireMetric::query()->where('sku', $sku)->first();
        if ($metric && is_numeric($metric->price) && (float) $metric->price > 0) {
            return round((float) $metric->price, 2);
        }

        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['SPRICE', 'sprice', 'lp'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                return round((float) $values[$key], 2);
            }
        }

        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        $price = (float) ($shopify->price ?? 0);

        return $price > 0 ? round($price, 2) : null;
    }

    private function resolveRetailPrice(string $sku, ProductMaster $product, float $wholesale): float
    {
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['msrp', 'MSRP', 'retail'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > $wholesale) {
                return round((float) $values[$key], 2);
            }
        }

        return round($wholesale * 2, 2);
    }

    private function shopifyInv(string $sku): int
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return (int) ($shopify->available_to_sell ?? $shopify->inv ?? 0);
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
