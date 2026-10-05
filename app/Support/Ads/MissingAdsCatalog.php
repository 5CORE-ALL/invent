<?php

namespace App\Support\Ads;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

class MissingAdsCatalog
{
    /**
     * Fill image_path from product master / Shopify. Keeps rows that already
     * have inv set.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function attachImages(Collection $rows): Collection
    {
        $skus = $rows->pluck('sku')
            ->filter(fn ($sku) => trim((string) $sku) !== '')
            ->map(fn ($sku) => (string) $sku)
            ->unique()
            ->values()
            ->all();

        $shopifyByNorm = $skus === [] ? [] : ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $productMasterByNorm = self::productMasterByNormalizedSku($skus);

        return $rows->map(function (array $row) use ($shopifyByNorm, $productMasterByNorm) {
            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $productMaster = $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;
            $row['image_path'] = self::imagePath($productMaster, $shopify);

            return $row;
        })->values();
    }

    /**
     * Attach Shopify inv / L30 / image and keep inv > 0.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function requireShopifyInventory(Collection $rows, bool $withImages = true): Collection
    {
        $skus = $rows->pluck('sku')
            ->filter(fn ($sku) => trim((string) $sku) !== '')
            ->map(fn ($sku) => (string) $sku)
            ->unique()
            ->values()
            ->all();

        $shopifyByNorm = $skus === [] ? [] : ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $productMasterByNorm = $withImages ? self::productMasterByNormalizedSku($skus) : [];

        return $rows->map(function (array $row) use ($shopifyByNorm, $productMasterByNorm, $withImages) {
            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $productMaster = $withImages && $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;
            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $ovl30 = $shopify ? (int) round((float) ($shopify->quantity ?? $shopify->shopify_l30 ?? 0)) : 0;
            $row['inv'] = $inv;
            $row['ovl30'] = $ovl30;
            $row['dil_percent'] = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0;
            if (! isset($row['price']) || $row['price'] === '' || $row['price'] === null) {
                $row['price'] = $shopify ? round((float) ($shopify->price ?? $shopify->b2c_price ?? 0), 2) : null;
            }
            $row['ad_status'] = 'No ad';
            $row['image_path'] = $withImages ? self::imagePath($productMaster, $shopify) : null;

            return $row;
        })->filter(fn (array $row) => (int) ($row['inv'] ?? 0) > 0)->values();
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, ProductMaster>
     */
    private static function productMasterByNormalizedSku(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($key !== '') {
                $wanted[$key] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $out = [];
        foreach (ProductMaster::query()->whereIn('sku', $skus)->get(['id', 'sku', 'Values', 'main_image']) as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            if ($key !== '' && isset($wanted[$key]) && ! isset($out[$key])) {
                $out[$key] = $pm;
            }
        }

        return $out;
    }

    private static function imagePath(?ProductMaster $productMaster, ?ShopifySku $shopify): ?string
    {
        $values = is_array($productMaster?->Values)
            ? $productMaster->Values
            : (is_string($productMaster?->Values) ? (json_decode((string) $productMaster->Values, true) ?: []) : []);
        $local = trim((string) ($values['image_path'] ?? $productMaster?->main_image ?? ''));
        $shopifyImage = trim((string) ($shopify?->image_src ?? ''));

        if ($local !== '' && (str_contains($local, 'storage/') || str_contains($local, '/storage/'))) {
            return '/'.ltrim($local, '/');
        }
        if ($shopifyImage !== '') {
            return $shopifyImage;
        }
        if ($local === '') {
            return null;
        }
        if (str_starts_with($local, 'http://') || str_starts_with($local, 'https://')) {
            return $local;
        }

        return '/'.ltrim($local, '/');
    }
}
