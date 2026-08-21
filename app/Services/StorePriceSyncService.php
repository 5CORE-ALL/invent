<?php

namespace App\Services;

use App\Models\Business5CoreProduct;
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
     * Fetch FleetCart listing prices and persist every row.
     * Matches inventory products by SKU (parent + variant.sku).
     *
     * @return array{
     *     fetched:int,
     *     stored:int,
     *     matched:int,
     *     unmatched:list<string>,
     *     failed:list<array{sku:string,error:string}>,
     *     pages:int
     * }
     */
    public function sync(?string $sku = null, ?callable $onPage = null): array
    {
        $sku = $sku !== null ? trim($sku) : null;
        if ($sku === '') {
            $sku = null;
        }

        $items = $this->client->fetchAllPrices($sku, $onPage);
        $pmByNorm = $this->productMasterByNormalizedSku();

        $result = [
            'fetched' => count($items),
            'stored' => 0,
            'matched' => 0,
            'unmatched' => [],
            'failed' => [],
            'pages' => 0,
        ];

        foreach ($items as $item) {
            foreach ($this->expandListingRows($item) as $row) {
                try {
                    $this->persistRow($row, $pmByNorm, $result);
                } catch (\Throwable $e) {
                    $failSku = (string) ($row['sku'] ?? '');
                    $result['failed'][] = [
                        'sku' => $failSku,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Store price sync row failed', [
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
     * Parent listing plus each variant with its own SKU.
     *
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    protected function expandListingRows(array $item): array
    {
        $parentSku = trim((string) ($item['sku'] ?? ''));
        $storeProductId = (int) ($item['id'] ?? 0);
        $rows = [];

        $rows[] = $this->mapListing($item, [
            'sku' => $parentSku,
            'parent_sku' => null,
            'is_variant' => false,
            'is_default_variant' => null,
            'store_variant_id' => null,
            'variant_uid' => null,
            'listing_key' => $storeProductId.':0',
            'raw_json' => $item,
        ]);

        foreach (is_array($item['variants'] ?? null) ? $item['variants'] : [] as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $variantSku = trim((string) ($variant['sku'] ?? ''));
            if ($variantSku === '' || ($parentSku !== '' && ShopifySku::normalizeSkuForShopifyLookup($variantSku) === ShopifySku::normalizeSkuForShopifyLookup($parentSku))) {
                continue;
            }

            $variantId = (int) ($variant['id'] ?? 0);
            $rows[] = $this->mapListing($item, [
                'sku' => $variantSku,
                'parent_sku' => $parentSku !== '' ? $parentSku : null,
                'name' => $variant['name'] ?? ($item['name'] ?? null),
                'price' => $this->amount($variant['price'] ?? null) ?? $this->amount($item['price'] ?? null),
                'special_price' => $this->amount($variant['special_price'] ?? null),
                'selling_price' => $this->amount($variant['selling_price'] ?? null) ?? $this->amount($item['selling_price'] ?? null),
                'currency' => $this->currency($variant['selling_price'] ?? $variant['price'] ?? null) ?? $this->currency($item['selling_price'] ?? $item['price'] ?? null),
                'is_variant' => true,
                'is_default_variant' => (bool) ($variant['is_default'] ?? false),
                'store_variant_id' => $variantId > 0 ? $variantId : null,
                'variant_uid' => isset($variant['uid']) ? (string) $variant['uid'] : null,
                'listing_key' => $storeProductId.':'.($variantId > 0 ? $variantId : 'v-'.$variantSku),
                'raw_json' => $variant,
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mapListing(array $item, array $overrides): array
    {
        $price = $this->amount($item['price'] ?? null);
        $special = $this->amount($item['special_price'] ?? null);
        $selling = $this->amount($item['selling_price'] ?? null);

        return array_merge([
            'store_product_id' => (int) ($item['id'] ?? 0),
            'sku' => trim((string) ($item['sku'] ?? '')),
            'parent_sku' => null,
            'slug' => isset($item['slug']) ? (string) $item['slug'] : null,
            'name' => isset($item['name']) ? (string) $item['name'] : null,
            'price' => $price,
            'special_price' => $special,
            'selling_price' => $selling,
            'formatted_price' => isset($item['formatted_price']) ? (string) $item['formatted_price'] : null,
            'special_price_type' => isset($item['special_price_type']) ? (string) $item['special_price_type'] : null,
            'special_price_start' => $this->nullableDate($item['special_price_start'] ?? null),
            'special_price_end' => $this->nullableDate($item['special_price_end'] ?? null),
            'currency' => $this->currency($item['selling_price'] ?? $item['price'] ?? null),
            'is_variant' => false,
            'is_default_variant' => null,
            'store_variant_id' => null,
            'variant_uid' => null,
            'raw_json' => $item,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ProductMaster>  $pmByNorm
     * @param  array{fetched:int,stored:int,matched:int,unmatched:list<string>,failed:list<array{sku:string,error:string}>,pages:int}  $result
     */
    protected function persistRow(array $row, array $pmByNorm, array &$result): void
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            $result['failed'][] = [
                'sku' => '',
                'error' => 'Store listing has no SKU (id '.($row['store_product_id'] ?? '?').')',
            ];

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

        if ($matched) {
            $result['matched']++;
        } else {
            $result['unmatched'][] = $sku;
        }

        if (Schema::hasTable('business_5core_products') && $row['selling_price'] !== null) {
            Business5CoreProduct::updateOrCreate(
                ['sku' => $sku],
                ['price' => $row['selling_price']]
            );
        }
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
