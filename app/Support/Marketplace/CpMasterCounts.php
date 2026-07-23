<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CP Master (Product Master) SKU / 0 Inv totals — same rules as /product-master badges.
 *
 * SKU: non-PARENT ProductMaster rows (deleted_at null)
 * 0 Inv: among those, shopify_skus.inv is null / empty / 0 / non-numeric
 */
class CpMasterCounts
{
    /**
     * @return array{SKU: int, ZeroInv: int}
     */
    public static function counts(bool $useCache = true): array
    {
        $empty = ['SKU' => 0, 'ZeroInv' => 0];

        if (! $useCache) {
            try {
                return self::loadCounts() ?: $empty;
            } catch (\Throwable $e) {
                Log::warning('CpMasterCounts load failed: ' . $e->getMessage());

                return $empty;
            }
        }

        try {
            return Cache::remember('cp_master_sku_zero_inv_v1', now()->addMinutes(10), function () use ($empty) {
                return self::loadCounts() ?: $empty;
            });
        } catch (\Throwable $e) {
            Log::warning('CpMasterCounts cache failed: ' . $e->getMessage());

            try {
                return self::loadCounts() ?: $empty;
            } catch (\Throwable $e2) {
                Log::warning('CpMasterCounts load failed: ' . $e2->getMessage());

                return $empty;
            }
        }
    }

    /**
     * @return array{SKU: int, ZeroInv: int}
     */
    private static function loadCounts(): array
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get(['sku']);
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $skuCount = 0;
        $zeroInv = 0;

        foreach ($productMasters as $item) {
            $sku = trim((string) ($item->sku ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $skuCount++;

            $shopify = $shopifyData[$sku] ?? null;
            $shopifyInv = $shopify->inv ?? null;
            // Same as CP Master badge: null / empty / 0 / non-numeric = 0 Inv
            if ($shopifyInv === null || $shopifyInv === '' || ! is_numeric($shopifyInv) || (float) $shopifyInv === 0.0) {
                $zeroInv++;
            }
        }

        return [
            'SKU' => $skuCount,
            'ZeroInv' => $zeroInv,
        ];
    }
}
