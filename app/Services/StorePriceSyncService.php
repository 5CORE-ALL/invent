<?php

namespace App\Services;

use App\Models\Business5CoreProduct;
use App\Models\BusinessFiveCoreSheetdata;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class StorePriceSyncService
{
    public function __construct(protected StoreListingApiClient $client)
    {
    }

    /**
     * Fetch FleetCart listings + prices + product detail and persist every row.
     *
     * @return array{
     *     fetched:int,
     *     stored:int,
     *     matched:int,
     *     unmatched:list<string>,
     *     failed:list<array{sku:string,error:string}>,
     *     pages:int,
     *     with_views:int,
     *     with_sold:int,
     *     with_qty:int
     * }
     */
    public function sync(?string $sku = null, ?callable $onPage = null): array
    {
        $sku = $sku !== null ? trim($sku) : null;
        if ($sku === '') {
            $sku = null;
        }

        $merged = $this->fetchMergedListings($sku, $onPage);
        $pmByNorm = $this->productMasterByNormalizedSku();
        $skuBySlug = $this->skuBySlug($pmByNorm);

        $result = [
            'fetched' => count($merged),
            'stored' => 0,
            'matched' => 0,
            'unmatched' => [],
            'failed' => [],
            'pages' => 0,
            'with_views' => 0,
            'with_sold' => 0,
            'with_qty' => 0,
        ];

        foreach ($merged as $bundle) {
            foreach ($this->expandListingRows($bundle) as $row) {
                try {
                    $this->persistRow($row, $pmByNorm, $skuBySlug, $result);
                } catch (\Throwable $e) {
                    $failSku = (string) ($row['sku'] ?? '');
                    $result['failed'][] = [
                        'sku' => $failSku,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Store listing sync row failed', [
                        'sku' => $failSku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $result['unmatched'] = array_values(array_unique($result['unmatched']));

        return $result;
    }

    /**
     * @return array<int, array{listing:?array, price:?array, detail:?array}>
     */
    protected function fetchMergedListings(?string $sku, ?callable $onPage): array
    {
        $priceItems = $this->client->fetchAllPrices($sku, $onPage ? function ($page, $last, $count, $payload) use ($onPage) {
            $onPage('prices', $page, $last, $count, $payload);
        } : null);

        $catalogItems = [];
        if ($priceItems === []) {
            $catalogItems = $this->client->fetchAllProducts($sku, $onPage ? function ($page, $last, $count, $payload) use ($onPage) {
                $onPage('products', $page, $last, $count, $payload);
            } : null);
        }

        $listingItems = [];
        try {
            if ($sku === null) {
                $listingItems = $this->client->fetchAllListings(null, $onPage ? function ($page, $last, $count, $payload) use ($onPage) {
                    $onPage('listings', $page, $last, $count, $payload);
                } : null);
            } else {
                $probe = $this->client->fetchListings(1, 5, $sku);
                $total = (int) ($probe['meta']['total'] ?? 0);
                if ($total > 0 && $total <= 20) {
                    $listingItems = $this->client->fetchAllListings($sku);
                } elseif (is_array($probe['data'] ?? null)) {
                    foreach ($probe['data'] as $item) {
                        if (is_array($item) && $this->skuMatches($item['sku'] ?? '', $sku)) {
                            $listingItems[] = $item;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        $byId = [];
        foreach ($listingItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byId[$id] = ['listing' => $item, 'price' => null, 'detail' => null];
        }
        foreach ($priceItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (! isset($byId[$id])) {
                $byId[$id] = ['listing' => null, 'price' => $item, 'detail' => null];
            } else {
                $byId[$id]['price'] = $item;
            }
        }
        foreach ($catalogItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (! isset($byId[$id])) {
                $byId[$id] = ['listing' => $item, 'price' => $item, 'detail' => null];
            } elseif ($byId[$id]['listing'] === null) {
                $byId[$id]['listing'] = $item;
            }
        }

        $slugs = [];
        foreach ($byId as $bundle) {
            $slug = $bundle['listing']['slug'] ?? $bundle['price']['slug'] ?? null;
            if (is_string($slug) && $slug !== '') {
                $slugs[] = $slug;
            }
        }

        if ($onPage) {
            $onPage('details', 0, 1, count($slugs), []);
        }
        $details = $this->client->fetchDetailsBySlugs($slugs);
        foreach ($byId as $id => $bundle) {
            $slug = $bundle['listing']['slug'] ?? $bundle['price']['slug'] ?? null;
            if (is_string($slug) && isset($details[$slug])) {
                $byId[$id]['detail'] = $details[$slug];
            }
        }

        return array_values($byId);
    }

    /**
     * @return array<string, ProductMaster>
     */
    protected function productMasterByNormalizedSku(): array
    {
        $map = [];
        ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->select(['id', 'sku'])
            ->chunkById(2000, function ($products) use (&$map) {
                foreach ($products as $product) {
                    $key = ShopifySku::normalizeSkuForShopifyLookup((string) $product->sku);
                    if ($key !== '' && ! isset($map[$key])) {
                        $map[$key] = $product;
                    }
                }
            });

        return $map;
    }

    /**
     * slug => SKU for listings that have no sku on /products.
     *
     * @param  array<string, ProductMaster>  $pmByNorm
     * @return array<string, string>
     */
    protected function skuBySlug(array $pmByNorm): array
    {
        $map = [];
        if (Schema::hasTable('store_listing_prices')) {
            foreach (StoreListingPrice::query()->whereNotNull('slug')->where('slug', '!=', '')->get(['sku', 'slug']) as $row) {
                $slug = strtolower(trim((string) $row->slug));
                $sku = trim((string) $row->sku);
                if ($slug !== '' && $sku !== '' && ! isset($map[$slug])) {
                    $map[$slug] = $sku;
                }
            }
        }

        foreach ($pmByNorm as $product) {
            $values = is_array($product->Values ?? null)
                ? $product->Values
                : (is_string($product->Values ?? null) ? (json_decode((string) $product->Values, true) ?: []) : []);
            $slug = strtolower(trim((string) ($values['website_slug'] ?? $values['slug'] ?? '')));
            $sku = trim((string) $product->sku);
            if ($slug !== '' && $sku !== '' && ! isset($map[$slug])) {
                $map[$slug] = $sku;
            }
        }

        return $map;
    }

    /**
     * @param  array{listing:?array, price:?array, detail:?array}  $bundle
     * @return list<array<string, mixed>>
     */
    protected function expandListingRows(array $bundle): array
    {
        $listing = is_array($bundle['listing'] ?? null) ? $bundle['listing'] : [];
        $price = is_array($bundle['price'] ?? null) ? $bundle['price'] : [];
        $detailPayload = is_array($bundle['detail'] ?? null) ? $bundle['detail'] : [];
        $product = is_array($detailPayload['product'] ?? null) ? $detailPayload['product'] : [];

        $base = $price !== [] ? $price : ($listing !== [] ? $listing : $product);
        $parentSku = trim((string) ($base['sku'] ?? $listing['sku'] ?? $product['sku'] ?? ''));
        $storeProductId = (int) ($base['id'] ?? $listing['id'] ?? $product['id'] ?? 0);

        $rows = [];
        $rows[] = $this->mapListing($listing, $price, $product, $detailPayload, [
            'sku' => $parentSku,
            'parent_sku' => null,
            'is_variant' => false,
            'is_default_variant' => null,
            'store_variant_id' => null,
            'variant_uid' => null,
            'listing_key' => $storeProductId.':0',
        ]);

        $variants = [];
        if (is_array($price['variants'] ?? null)) {
            $variants = $price['variants'];
        } elseif (is_array($product['variants'] ?? null)) {
            $variants = $product['variants'];
        }

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $variantSku = trim((string) ($variant['sku'] ?? ''));
            if ($variantSku === '' || ($parentSku !== '' && $this->skuMatches($variantSku, $parentSku))) {
                continue;
            }

            $variantId = (int) ($variant['id'] ?? 0);
            $rows[] = $this->mapListing($listing, $price, $product, $detailPayload, [
                'sku' => $variantSku,
                'parent_sku' => $parentSku !== '' ? $parentSku : null,
                'name' => $variant['name'] ?? ($base['name'] ?? null),
                'price' => $this->amount($variant['price'] ?? null) ?? $this->amount($base['price'] ?? null),
                'special_price' => $this->amount($variant['special_price'] ?? null),
                'selling_price' => $this->amount($variant['selling_price'] ?? null) ?? $this->amount($base['selling_price'] ?? null),
                'currency' => $this->currency($variant['selling_price'] ?? $variant['price'] ?? null) ?? $this->currency($base['selling_price'] ?? $base['price'] ?? null),
                'sold' => $this->intField([$variant, $product, $listing], ['sold', 'sold_count', 'total_sold', 'orders_count', 'qty_sold', 'quantity_sold', 'sales']),
                'is_variant' => true,
                'is_default_variant' => (bool) ($variant['is_default'] ?? false),
                'store_variant_id' => $variantId > 0 ? $variantId : null,
                'variant_uid' => isset($variant['uid']) ? (string) $variant['uid'] : null,
                'listing_key' => $storeProductId.':'.($variantId > 0 ? $variantId : 'v-'.$variantSku),
                'raw_json' => [
                    'listing' => $listing,
                    'price' => $price,
                    'detail' => $detailPayload,
                    'variant' => $variant,
                ],
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $listing
     * @param  array<string, mixed>  $price
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $detailPayload
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mapListing(array $listing, array $price, array $product, array $detailPayload, array $overrides): array
    {
        $sources = [$price, $listing, $product];
        $first = $price !== [] ? $price : ($listing !== [] ? $listing : $product);

        $images = [];
        $baseImage = $listing['base_image']['path'] ?? $product['base_image']['path'] ?? null;
        if (is_array($listing['additional_images'] ?? null)) {
            $images = $listing['additional_images'];
        } elseif (is_array($product['additional_images'] ?? null)) {
            $images = $product['additional_images'];
        }

        $brand = $listing['brand']['name'] ?? $product['brand']['name'] ?? (is_string($listing['brand'] ?? null) ? $listing['brand'] : null);

        return array_merge([
            'store_product_id' => (int) ($first['id'] ?? 0),
            'sku' => trim((string) ($first['sku'] ?? '')),
            'parent_sku' => null,
            'slug' => isset($first['slug']) ? (string) $first['slug'] : null,
            'name' => isset($first['name']) ? (string) $first['name'] : null,
            'price' => $this->amount($this->firstPresent($sources, 'price')),
            'special_price' => $this->amount($this->firstPresent($sources, 'special_price')),
            'selling_price' => $this->amount($this->firstPresent($sources, 'selling_price')),
            'formatted_price' => $this->stringField($sources, 'formatted_price'),
            'special_price_type' => $this->stringField($sources, 'special_price_type'),
            'special_price_start' => $this->nullableDate($this->firstPresent($sources, 'special_price_start')),
            'special_price_end' => $this->nullableDate($this->firstPresent($sources, 'special_price_end')),
            'currency' => $this->currency($this->firstPresent($sources, 'selling_price') ?? $this->firstPresent($sources, 'price')),
            'views' => $this->intField($sources, ['views']),
            'sold' => $this->intField($sources, ['sold', 'sold_count', 'total_sold', 'orders_count', 'qty_sold', 'quantity_sold', 'sales']),
            'qty' => $this->intField([$product, $listing], ['qty', 'quantity', 'stock']),
            'is_in_stock' => $this->boolField($sources, 'is_in_stock'),
            'url' => $this->stringField($sources, 'url'),
            'brand' => is_string($brand) ? $brand : null,
            'rating_percent' => $this->numericField($sources, 'rating_percent'),
            'base_image' => is_string($baseImage) ? $baseImage : null,
            'categories_json' => $listing['categories'] ?? $product['categories'] ?? null,
            'tags_json' => $listing['tags'] ?? $product['tags'] ?? null,
            'images_json' => $images !== [] ? $images : null,
            'is_variant' => false,
            'is_default_variant' => null,
            'store_variant_id' => null,
            'variant_uid' => null,
            'raw_json' => [
                'listing' => $listing,
                'price' => $price,
                'detail' => $detailPayload,
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ProductMaster>  $pmByNorm
     * @param  array<string, string>  $skuBySlug
     * @param  array{fetched:int,stored:int,matched:int,unmatched:list<string>,failed:list<array{sku:string,error:string}>,pages:int,with_views:int,with_sold:int,with_qty:int}  $result
     */
    protected function persistRow(array $row, array $pmByNorm, array $skuBySlug, array &$result): void
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        if ($sku === '' && $slug !== '') {
            $sku = trim((string) ($skuBySlug[$slug] ?? ''));
            if ($sku !== '') {
                $row['sku'] = $sku;
            }
        }
        if ($sku === '') {
            $label = $slug !== '' ? $slug : ('id:'.($row['store_product_id'] ?? '?'));
            $result['unmatched'][] = $label;

            return;
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $product = $norm !== '' ? ($pmByNorm[$norm] ?? null) : null;
        $matched = $product !== null;

        StoreListingPrice::updateOrCreate(
            ['listing_key' => (string) $row['listing_key']],
            [
                'store_product_id' => $row['store_product_id'],
                'sku' => $sku,
                'parent_sku' => $row['parent_sku'],
                'slug' => $row['slug'],
                'name' => $row['name'],
                'price' => $row['price'],
                'special_price' => $row['special_price'],
                'selling_price' => $row['selling_price'],
                'formatted_price' => $row['formatted_price'],
                'special_price_type' => $row['special_price_type'],
                'special_price_start' => $row['special_price_start'],
                'special_price_end' => $row['special_price_end'],
                'currency' => $row['currency'],
                'views' => $row['views'],
                'sold' => $row['sold'],
                'qty' => $row['qty'],
                'is_in_stock' => $row['is_in_stock'],
                'url' => $row['url'],
                'brand' => $row['brand'],
                'rating_percent' => $row['rating_percent'],
                'base_image' => $row['base_image'],
                'categories_json' => $row['categories_json'],
                'tags_json' => $row['tags_json'],
                'images_json' => $row['images_json'],
                'is_variant' => (bool) $row['is_variant'],
                'is_default_variant' => $row['is_default_variant'],
                'store_variant_id' => $row['store_variant_id'],
                'variant_uid' => $row['variant_uid'],
                'product_master_id' => $product?->id,
                'matched' => $matched,
                'raw_json' => $row['raw_json'],
                'synced_at' => now(),
            ]
        );

        $result['stored']++;
        if ($row['views'] !== null) {
            $result['with_views']++;
        }
        if ($row['sold'] !== null) {
            $result['with_sold']++;
        }
        if ($row['qty'] !== null) {
            $result['with_qty']++;
        }

        if ($matched) {
            $result['matched']++;
        } else {
            $result['unmatched'][] = $sku;
        }

        if (Schema::hasTable('business_5core_products') && $row['selling_price'] !== null) {
            $b5c = ['price' => $row['selling_price']];
            if ($row['sold'] !== null) {
                $b5c['b5c_l30'] = $row['sold'];
            }
            Business5CoreProduct::updateOrCreate(['sku' => $sku], $b5c);
        }

        if (Schema::hasTable('business_five_core_sheet_data')) {
            $sheet = [];
            if ($row['selling_price'] !== null) {
                $sheet['price'] = $row['selling_price'];
            }
            if ($row['views'] !== null) {
                $sheet['views'] = $row['views'];
            }
            if ($row['sold'] !== null) {
                $sheet['l30'] = $row['sold'];
            }
            if ($sheet !== []) {
                BusinessFiveCoreSheetdata::updateOrCreate(['sku' => $sku], $sheet);
            }
        }
    }

    protected function skuMatches(string $left, string $right): bool
    {
        $a = ShopifySku::normalizeSkuForShopifyLookup($left);
        $b = ShopifySku::normalizeSkuForShopifyLookup($right);

        return $a !== '' && $a === $b;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    protected function firstPresent(array $sources, string $key)
    {
        foreach ($sources as $source) {
            if (is_array($source) && array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @param  list<string>  $keys
     */
    protected function intField(array $sources, array $keys): ?int
    {
        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            foreach ($keys as $key) {
                if (isset($source[$key]) && is_numeric($source[$key])) {
                    return (int) $source[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    protected function numericField(array $sources, string $key): ?float
    {
        $value = $this->firstPresent($sources, $key);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    protected function stringField(array $sources, string $key): ?string
    {
        $value = $this->firstPresent($sources, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    protected function boolField(array $sources, string $key): ?bool
    {
        foreach ($sources as $source) {
            if (is_array($source) && array_key_exists($key, $source) && $source[$key] !== null) {
                return (bool) $source[$key];
            }
        }

        return null;
    }

    /**
     * @param  mixed  $price
     */
    protected function amount($price): ?float
    {
        if (is_array($price) && isset($price['amount']) && is_numeric($price['amount'])) {
            return round((float) $price['amount'], 2);
        }

        return null;
    }

    /**
     * @param  mixed  $price
     */
    protected function currency($price): ?string
    {
        if (is_array($price) && isset($price['currency']) && $price['currency'] !== '') {
            return (string) $price['currency'];
        }

        return null;
    }

    /**
     * @param  mixed  $value
     */
    protected function nullableDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
