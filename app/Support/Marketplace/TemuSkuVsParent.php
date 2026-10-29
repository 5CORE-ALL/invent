<?php

namespace App\Support\Marketplace;

use App\Models\ShopifySku;
use Illuminate\Support\Collection;

/**
 * "SKU VS Parent" for the Temu 1 listing page - same rule as the Temu Ads page: for each Product Master
 * parent, how many of its SKUs are listed on Temu and which ones are missing.
 *
 * A SKU counts as listed when its listing row has a Temu goods_id, or when ANY live Temu goods carries a
 * variation with that name (a parent can be split across several goods).
 */
class TemuSkuVsParent
{
    /**
     * Adds sku_parent_listed / sku_parent_total / sku_parent_missing to every row that has a parent.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public static function attach(Collection $rows): Collection
    {
        $parentKey = static fn (string $p): string => mb_strtolower(trim((string) preg_replace('/^PARENT\s+/i', '', trim($p))));
        $norm = static fn (string $sku): string => ShopifySku::normalizeSkuForShopifyLookup($sku);

        // Master SKUs per parent and the SKUs that already carry a goods_id.
        $masterByParent = [];
        $listed = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                continue;
            }
            $n = $norm($sku);
            if ($n === '') {
                continue;
            }
            $key = $parentKey((string) ($row->parent ?? ''));
            if ($key !== '') {
                $masterByParent[$key][$n] = $sku;
            }
            if (trim((string) ($row->goods_id ?? '')) !== '') {
                $listed[$n] = true;
            }
        }
        if ($masterByParent === []) {
            return $rows;
        }

        // Plus every named variation Temu itself holds.
        try {
            $temuMap = \App\Console\Commands\SyncTemuGoodsSkuNames::getSkuNames();
            if (is_array($temuMap)) {
                $live = \App\Console\Commands\SyncTemuGoodsSkuNames::getLiveGoods();
                $live = (is_array($live) && count($live) >= 50) ? $live : null;
                foreach ($temuMap as $gid => $goods) {
                    if ($live !== null && ! array_key_exists((string) $gid, $live)) {
                        continue; // removed from Temu: not listed
                    }
                    foreach ((array) ($goods['skus'] ?? []) as $name) {
                        $n = $norm((string) $name);
                        if ($n !== '') {
                            $listed[$n] = true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // the goods-name cache is optional
        }

        $missingSkus = [];
        foreach ($masterByParent as $skus) {
            foreach ($skus as $n => $sku) {
                if (! isset($listed[$n])) {
                    $missingSkus[] = $sku;
                }
            }
        }
        $shopifyByNorm = $missingSkus !== []
            ? ShopifySku::buildShopifySkuLookupByNormalizedSku(array_values(array_unique($missingSkus)), false)
            : [];

        $summary = [];
        foreach ($masterByParent as $key => $skus) {
            $missing = [];
            foreach ($skus as $n => $sku) {
                if (isset($listed[$n])) {
                    continue;
                }
                $shopify = $shopifyByNorm[$n] ?? null;
                $missing[] = [
                    'sku' => $sku,
                    'sid' => $shopify ? trim((string) ($shopify->variant_id ?? '')) : '',
                    'inv' => $shopify ? (int) ($shopify->inv ?? 0) : 0,
                ];
            }
            usort($missing, fn (array $a, array $b) => strnatcasecmp($a['sku'], $b['sku']));
            $summary[$key] = [
                'listed' => count($skus) - count($missing),
                'total' => count($skus),
                'missing' => $missing,
            ];
        }

        foreach ($rows as $row) {
            $key = $parentKey((string) ($row->parent ?? ''));
            $row->sku_parent_listed = null;
            $row->sku_parent_total = null;
            $row->sku_parent_missing = [];
            if ($key !== '' && isset($summary[$key])) {
                $row->sku_parent_listed = $summary[$key]['listed'];
                $row->sku_parent_total = $summary[$key]['total'];
                $row->sku_parent_missing = $summary[$key]['missing'];
            }
        }

        return $rows;
    }
}
