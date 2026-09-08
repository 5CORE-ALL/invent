<?php

namespace App\Services\MarketplaceManager;

use App\Models\NeweggB2BListingStatus;
use App\Models\NeweggB2CListingStatus;
use App\Models\NeweggB2BDataView;
use App\Models\Neweegb2cDataView;
use App\Models\NeweggMetric;
use App\Models\NeweggPricing;
use App\Models\NeweggPricingPrice;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\NeweggApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to Newegg B2C / B2B (one SKU = one listing).
 */
class NeweggListingPublishService
{
    public function __construct(private NeweggApiService $api)
    {
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(
        array $skus,
        string $channel = 'neweggb2c',
        bool $expandSiblings = true,
        string $mode = 'variation',
        string $parentHint = '',
        ?int $categoryId = null
    ): array {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $channel = $this->normalizeChannel($channel);
        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Newegg API credentials missing. Set NEWEGG_SELLER_ID, NEWEGG_API_KEY, and NEWEGG_SECRET_KEY.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        $publishSkus = ($expandSiblings && $mode === 'variation')
            ? $this->expandToPublishableSiblings($skus, $channel)
            : $this->filterPublishable($skus, $channel);

        if ($publishSkus === []) {
            return ['success' => false, 'message' => $this->publishBlockReason($skus, $channel)];
        }

        if (count($publishSkus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastId = null;
            foreach ($publishSkus as $sku) {
                $one = $this->publishSkus([$sku], $channel, false, 'single', $parentHint, $categoryId);
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
                'message' => 'No price found for '.$sku.'. Set Newegg pricing or Shopify price.',
            ];
        }

        $images = $this->productImages($product, $sku);
        if ($images === []) {
            return ['success' => false, 'message' => 'No public image URL for '.$sku.'. Add an https image on CP Master (or Image Master).'];
        }

        $inv = $this->shopifyInv($sku);
        $dims = $this->resolveDimensions($product);
        $subcategoryId = $categoryId !== null && $categoryId > 0
            ? (string) $categoryId
            : trim((string) config('services.newegg.default_subcategory_id', ''));

        $res = $this->api->createListing([
            'sku' => $sku,
            'title' => $title,
            'manufacturer' => $this->resolveManufacturer($product),
            'mpn' => $sku,
            'upc' => $this->resolveUpc($product),
            'description' => $this->resolveDescription($product, $title),
            'bullets' => $this->resolveBullets($product),
            'images' => $images,
            'price' => $price,
            'inventory' => $inv,
            'subcategory_id' => $subcategoryId,
            'length' => $dims['length'],
            'width' => $dims['width'],
            'height' => $dims['height'],
            'weight' => $dims['weight'],
            'platform' => $channel === 'neweggb2b' ? 'b2b' : 'b2c',
        ]);

        if (empty($res['success'])) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'Newegg create listing failed.',
            ];
        }

        $itemNumber = trim((string) ($res['item_number'] ?? ''));
        try {
            $this->api->updateItemPrice($sku, $price);
            $this->api->updateItemInventory($sku, $inv);
        } catch (\Throwable $e) {
            Log::warning('Newegg publish: price/inventory follow-up failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        $this->persistListed($sku, $itemNumber, $title, $price, $inv, $channel);
        $this->forgetListingCaches($channel);

        return [
            'success' => true,
            'message' => $res['message'] ?? ('Published '.$sku.' to Newegg.'),
            'goods_id' => $itemNumber !== '' ? $itemNumber : null,
            'sku_id' => $itemNumber !== '' ? $itemNumber : null,
            'skus' => [$sku],
        ];
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
        $dataView = $cfg['dataView'] ?? ($channel === 'neweggb2b' ? NeweggB2BDataView::class : Neweegb2cDataView::class);
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
            $product = $products->get(strtolower($sku)) ?: $this->findProduct($sku);
            if (! $product || $this->productImages($product, $sku) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
    }

    private function publishBlockReason(array $skus, string $channel): string
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

    private function persistListed(string $sku, string $itemNumber, string $title, float $price, int $inv, string $channel): void
    {
        try {
            if ($itemNumber !== '' && Schema::hasTable('newegg_metric')) {
                NeweggMetric::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $itemNumber,
                        'product_name' => $title,
                        'price' => $price,
                    ]
                );
            }

            if (Schema::hasTable('newegg_pricing')) {
                $payload = [
                    'selling_price' => $price,
                    'available_quantity' => max(0, $inv),
                    'currency' => 'USD',
                    'country_code' => 'USA',
                    'active' => 1,
                ];
                if ($itemNumber !== '' && ! str_starts_with($itemNumber, 'NE-')) {
                    $payload['newegg_item_number'] = $itemNumber;
                }
                $existing = NeweggPricing::query()
                    ->where('seller_part_number', $sku)
                    ->where('country_code', 'USA')
                    ->first()
                    ?: NeweggPricing::query()->whereRaw('UPPER(TRIM(seller_part_number)) = ?', [strtoupper($sku)])->first();
                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    NeweggPricing::create(array_merge(['seller_part_number' => $sku], $payload));
                }
            }

            if (Schema::hasTable('newegg_pricing_prices')) {
                NeweggPricingPrice::updateOrCreate(
                    ['sku' => strtoupper($sku)],
                    [
                        'price' => $price,
                        'ne_stock' => max(0, $inv),
                    ]
                );
            }

            $statusClass = $channel === 'neweggb2b' ? NeweggB2BListingStatus::class : NeweggB2CListingStatus::class;
            if (class_exists($statusClass) && Schema::hasTable((new $statusClass)->getTable())) {
                $status = $statusClass::query()->where('sku', $sku)->first();
                $value = $status && is_array($status->value) ? $status->value : [];
                $value['listed'] = 'Listed';
                $value['listing_id'] = $itemNumber;
                $value['product_id'] = $itemNumber;
                $statusClass::updateOrCreate(['sku' => $sku], ['value' => $value]);
            }
        } catch (\Throwable $e) {
            Log::warning('Newegg persist listed failed', [
                'sku' => $sku,
                'item_number' => $itemNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function forgetListingCaches(string $channel): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            Cache::forget('listing_channel_counts_v1:inv:'.$channel);
            Cache::forget('listing_channel_counts_v1:cp:'.$channel);
            Cache::forget(NeweggLiveListingsService::CACHE_KEY);
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
        if (Schema::hasTable('newegg_pricing_prices')) {
            $row = NeweggPricingPrice::query()->where('sku', $sku)->first()
                ?: NeweggPricingPrice::query()->where('sku', strtoupper($sku))->first()
                ?: NeweggPricingPrice::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            if ($row && is_numeric($row->price) && (float) $row->price > 0) {
                return round((float) $row->price, 2);
            }
        }

        if (Schema::hasTable('newegg_pricing')) {
            $row = NeweggPricing::query()->where('seller_part_number', $sku)->first()
                ?: NeweggPricing::query()->whereRaw('UPPER(TRIM(seller_part_number)) = ?', [strtoupper($sku)])->first();
            if ($row && is_numeric($row->selling_price) && (float) $row->selling_price > 0) {
                return round((float) $row->selling_price, 2);
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

    private function resolveManufacturer(ProductMaster $product): string
    {
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['brand', 'Brand', 'manufacturer', 'Manufacturer'] as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        $brand = trim((string) ($product->description_v2_brand ?? ''));
        if ($brand !== '') {
            return $brand;
        }

        $configured = trim((string) config('services.newegg.default_manufacturer', '5 Core Inc.'));

        return $configured !== '' ? $configured : '5 Core Inc.';
    }

    private function resolveUpc(ProductMaster $product): string
    {
        foreach (['upc', 'barcode'] as $field) {
            if (! Schema::hasColumn($product->getTable(), $field)) {
                continue;
            }
            $raw = trim((string) ($product->{$field} ?? ''));
            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            if (strlen($digits) >= 8 && strlen($digits) <= 14) {
                return $digits;
            }
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['upc', 'UPC', 'gtin', 'GTIN', 'ean', 'EAN', 'barcode'] as $key) {
            $digits = preg_replace('/\D+/', '', (string) ($values[$key] ?? '')) ?? '';
            if (strlen($digits) >= 8 && strlen($digits) <= 14) {
                return $digits;
            }
        }

        return '';
    }

    /**
     * @return array{length: float, width: float, height: float, weight: float}
     */
    private function resolveDimensions(ProductMaster $product): array
    {
        $values = is_array($product->Values) ? $product->Values : [];
        $num = static function (array $bag, array $keys, float $fallback): float {
            foreach ($keys as $key) {
                if (isset($bag[$key]) && is_numeric($bag[$key]) && (float) $bag[$key] > 0) {
                    return (float) $bag[$key];
                }
            }

            return $fallback;
        };

        return [
            'length' => $num($values, ['length', 'Length', 'item_length', 'L'], 1.0),
            'width' => $num($values, ['width', 'Width', 'item_width', 'W'], 1.0),
            'height' => $num($values, ['height', 'Height', 'item_height', 'H'], 1.0),
            'weight' => $num($values, ['weight', 'Weight', 'item_weight', 'wt', 'lb'], 1.0),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveBullets(ProductMaster $product): array
    {
        $bullets = [];
        for ($i = 1; $i <= 5; $i++) {
            $b = trim((string) ($product->{'bullet'.$i} ?? $product->{'feature'.$i} ?? ''));
            if ($b !== '') {
                $bullets[] = $b;
            }
        }

        return $bullets;
    }

    private function resolveDescription(ProductMaster $product, string $title): string
    {
        foreach (['product_description', 'description_800', 'description_600', 'description_1000', 'description_1500'] as $col) {
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

    private function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return in_array($channel, ['neweggb2b', 'newegg-b2b', 'newegg_b2b'], true)
            ? 'neweggb2b'
            : 'neweggb2c';
    }
}
