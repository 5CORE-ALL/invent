<?php

namespace App\Support;

use App\Models\AmazonDatasheet;
use App\Models\AmazonSkuCompetitor;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Schema;

/**
 * SKU metrics for an Amazon Ads campaign name, aligned with /amazon-tabulator-view.
 *
 * SKU is taken from the campaign name by stripping KW / PT / HEAD / HL suffixes
 * (same naming used on /amazon-ads/all).
 *
 * - Inv = shopify_skus.inv
 * - ovl30 = shopify_skus.quantity (OV L30)
 * - Dil (grid) = ovl30 ÷ Inv × 100
 * - Price = amazon_datsheets.price
 * - dil (pause rule) = Amazon L30 units ÷ Shopify INV × 100
 */
final class AmazonAdsCampaignSkuMetrics
{
    /** @var list<string> */
    private const SUFFIXES = [' HEAD', ' KW', ' PT.', ' PT', ' HL', ' AUTO', ' MANUAL'];

    public static function skuKeyFromCampaignName(?string $campaignName): string
    {
        if ($campaignName === null) {
            return '';
        }
        $n = preg_replace('/\s+/u', ' ', strtoupper(trim(str_replace("\xC2\xA0", ' ', $campaignName)))) ?? '';
        if ($n === '') {
            return '';
        }
        foreach (self::SUFFIXES as $suf) {
            if (str_ends_with($n, $suf)) {
                return trim(substr($n, 0, -strlen($suf)));
            }
        }

        return $n;
    }

    /**
     * Tabulator Dil: OV L30 ÷ INV × 100. INV = 0 → 0 (gray 0% on the grid).
     */
    public static function tabulatorDil(?float $inv, ?float $ovl30): ?float
    {
        if ($inv === null && $ovl30 === null) {
            return null;
        }
        $invF = (float) ($inv ?? 0);
        if ($invF <= 0) {
            return 0.0;
        }

        return round(((float) ($ovl30 ?? 0)) / $invF * 100, 2);
    }

    /**
     * @return array{sku: string, price: ?float, dil: ?float, inv: ?float, l30: ?float, ovl30: ?float, lmp_price: ?float}
     */
    private static function emptyMetrics(string $sku): array
    {
        return [
            'sku' => $sku,
            'price' => null,
            'dil' => null,
            'inv' => null,
            'l30' => null,
            'ovl30' => null,
            'lmp_price' => null,
        ];
    }

    /**
     * @param  list<string>  $campaignNames
     * @return array<string, array{sku: string, price: ?float, dil: ?float, inv: ?float, l30: ?float, ovl30: ?float, lmp_price: ?float}>
     */
    public static function mapForCampaignNames(array $campaignNames): array
    {
        $keysByName = [];
        $uniqueKeys = [];
        foreach ($campaignNames as $name) {
            $name = is_string($name) ? $name : '';
            $key = self::skuKeyFromCampaignName($name);
            $keysByName[$name] = $key;
            if ($key !== '') {
                $uniqueKeys[$key] = true;
            }
        }
        $metricsByKey = self::metricsForSkuKeys(array_keys($uniqueKeys));
        $out = [];
        foreach ($keysByName as $name => $key) {
            $out[$name] = $metricsByKey[$key] ?? self::emptyMetrics($key);
        }

        return $out;
    }

    /**
     * @param  list<string>  $skuKeys
     * @return array<string, array{sku: string, price: ?float, dil: ?float, inv: ?float, l30: ?float, ovl30: ?float, lmp_price: ?float}>
     */
    public static function metricsForSkuKeys(array $skuKeys): array
    {
        $skuKeys = array_values(array_filter(array_map(
            static fn ($k) => is_string($k) ? trim($k) : '',
            $skuKeys
        ), static fn (string $k): bool => $k !== ''));
        if ($skuKeys === []) {
            return [];
        }

        $spaceKeys = [];
        $compactKeys = [];
        foreach ($skuKeys as $key) {
            $spaceKeys[] = AmazonDatasheet::normalizeSkuSpaces($key);
            $compact = AmazonDatasheet::normalizeSkuForLookup($key);
            if ($compact !== '') {
                $compactKeys[] = $compact;
            }
        }
        $spaceKeys = array_values(array_unique(array_filter($spaceKeys)));
        $compactKeys = array_values(array_unique(array_filter($compactKeys)));

        $sheetByCompact = [];
        if (Schema::hasTable('amazon_datsheets') && ($spaceKeys !== [] || $compactKeys !== [])) {
            $rows = AmazonDatasheet::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where(function ($q) use ($spaceKeys, $compactKeys) {
                    if ($spaceKeys !== []) {
                        $ph = implode(',', array_fill(0, count($spaceKeys), '?'));
                        $q->whereRaw('UPPER(TRIM(sku)) IN ('.$ph.')', $spaceKeys);
                    }
                    if ($compactKeys !== []) {
                        $ph = implode(',', array_fill(0, count($compactKeys), '?'));
                        $expr = 'UPPER(REPLACE(REPLACE(TRIM(sku), " ", ""), CHAR(9), "")) IN ('.$ph.')';
                        if ($spaceKeys !== []) {
                            $q->orWhereRaw($expr, $compactKeys);
                        } else {
                            $q->whereRaw($expr, $compactKeys);
                        }
                    }
                })
                ->get();
            foreach ($rows as $row) {
                $ck = AmazonDatasheet::normalizeSkuForLookup((string) ($row->sku ?? ''));
                if ($ck === '') {
                    continue;
                }
                if (! isset($sheetByCompact[$ck])) {
                    $sheetByCompact[$ck] = $row;
                } else {
                    $sheetByCompact[$ck] = AmazonDatasheet::pickBestForProductSku(
                        (string) ($row->sku ?? ''),
                        [$sheetByCompact[$ck], $row]
                    );
                }
            }
        }

        $parentFamilyKeys = [];
        foreach ($skuKeys as $key) {
            if (str_starts_with($key, 'PARENT ')) {
                $rest = trim(substr($key, 7));
                if ($rest !== '') {
                    $parentFamilyKeys[$key] = $rest;
                }
            }
        }
        $childSkusByFamily = [];
        if ($parentFamilyKeys !== [] && Schema::hasTable('product_master')) {
            $families = array_values(array_unique(array_values($parentFamilyKeys)));
            $pmRows = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where(function ($q) use ($families) {
                    foreach ($families as $fam) {
                        $q->orWhereRaw('UPPER(TRIM(parent)) = ?', [strtoupper($fam)]);
                    }
                })
                ->get(['sku', 'parent']);
            foreach ($pmRows as $pm) {
                $sku = trim((string) ($pm->sku ?? ''));
                if ($sku === '' || str_starts_with(strtoupper($sku), 'PARENT')) {
                    continue;
                }
                $fam = strtoupper(trim((string) ($pm->parent ?? '')));
                if ($fam === '') {
                    continue;
                }
                $childSkusByFamily[$fam][] = $sku;
            }
        }

        $lookupSkus = $skuKeys;
        foreach ($childSkusByFamily as $kids) {
            foreach ($kids as $sku) {
                $lookupSkus[] = $sku;
            }
        }
        $shopifyByPm = Schema::hasTable('shopify_skus')
            ? ShopifySku::mapByProductSkus(array_values(array_unique($lookupSkus)))
            : collect();
        $lmpByCompact = self::lmpLandedBySkuKeys(array_values(array_unique($lookupSkus)));

        $out = [];
        foreach ($skuKeys as $key) {
            if (isset($parentFamilyKeys[$key])) {
                $fam = strtoupper($parentFamilyKeys[$key]);
                $kids = $childSkusByFamily[$fam] ?? [];
                $inv = 0.0;
                $ovl30 = 0.0;
                $l30 = 0.0;
                $price = null;
                $lmpPrice = null;
                foreach ($kids as $sku) {
                    $sh = $shopifyByPm->get($sku);
                    $inv += (float) ($sh?->inv ?? 0);
                    $ovl30 += (float) ($sh?->quantity ?? 0);
                    $sheet = $sheetByCompact[AmazonDatasheet::normalizeSkuForLookup($sku)] ?? null;
                    if ($sheet) {
                        $l30 += (float) ($sheet->units_ordered_l30 ?? 0);
                        $p = (float) ($sheet->price ?? 0);
                        if ($p > 0 && ($price === null || $p < $price)) {
                            $price = $p;
                        }
                    }
                    $childLmp = $lmpByCompact[AmazonDatasheet::normalizeSkuForLookup($sku)] ?? null;
                    if ($childLmp !== null && $childLmp > 0 && ($lmpPrice === null || $childLmp < $lmpPrice)) {
                        $lmpPrice = $childLmp;
                    }
                }
                $out[$key] = [
                    'sku' => $key,
                    'price' => $price,
                    'dil' => $inv > 0 ? round(($l30 / $inv) * 100, 2) : ($l30 > 0 ? 100.0 : 0.0),
                    'inv' => $inv,
                    'l30' => $l30,
                    'ovl30' => $ovl30,
                    'lmp_price' => $lmpPrice,
                ];
                continue;
            }

            $sheet = $sheetByCompact[AmazonDatasheet::normalizeSkuForLookup($key)] ?? null;
            $sh = $shopifyByPm->get($key);
            if ($sh === null) {
                foreach ($shopifyByPm as $pmSku => $row) {
                    if (AmazonDatasheet::normalizeSkuForLookup((string) $pmSku) === AmazonDatasheet::normalizeSkuForLookup($key)) {
                        $sh = $row;
                        break;
                    }
                }
            }
            $priceRaw = $sheet !== null ? $sheet->price : null;
            $price = ($priceRaw !== null && $priceRaw !== '' && is_finite((float) $priceRaw))
                ? (float) $priceRaw
                : null;
            $inv = $sh !== null ? (float) ($sh->inv ?? 0) : null;
            $ovl30 = $sh !== null ? (float) ($sh->quantity ?? 0) : null;
            $l30 = $sheet !== null ? (float) ($sheet->units_ordered_l30 ?? 0) : null;
            $dil = null;
            if ($inv !== null && $inv > 0 && $l30 !== null) {
                $dil = round(($l30 / $inv) * 100, 2);
            } elseif ($inv !== null && $inv <= 0) {
                $dil = ($l30 !== null && $l30 > 0) ? 100.0 : 0.0;
            }

            $out[$key] = [
                'sku' => $key,
                'price' => $price,
                'dil' => $dil,
                'inv' => $inv,
                'l30' => $l30,
                'ovl30' => $ovl30,
                'lmp_price' => $lmpByCompact[AmazonDatasheet::normalizeSkuForLookup($key)] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Lowest landed LMP (same source as /amazon-tabulator-view) keyed by compact SKU.
     *
     * @param  list<string>  $skuKeys
     * @return array<string, float>
     */
    private static function lmpLandedBySkuKeys(array $skuKeys): array
    {
        if ($skuKeys === [] || ! Schema::hasTable('amazon_sku_competitors')) {
            return [];
        }

        $spaceKeys = [];
        $compactKeys = [];
        foreach ($skuKeys as $key) {
            $space = AmazonDatasheet::normalizeSkuSpaces($key);
            if ($space !== '') {
                $spaceKeys[] = $space;
            }
            $compact = AmazonDatasheet::normalizeSkuForLookup($key);
            if ($compact !== '') {
                $compactKeys[] = $compact;
            }
        }
        $spaceKeys = array_values(array_unique($spaceKeys));
        $compactKeys = array_values(array_unique($compactKeys));
        if ($spaceKeys === [] && $compactKeys === []) {
            return [];
        }

        $q = AmazonSkuCompetitor::query()
            ->forMarketplace('amazon')
            ->wherePositivePrice()
            ->where(function ($w) use ($spaceKeys, $compactKeys) {
                if ($spaceKeys !== []) {
                    $ph = implode(',', array_fill(0, count($spaceKeys), '?'));
                    $w->whereRaw('UPPER(TRIM(sku)) IN ('.$ph.')', $spaceKeys);
                }
                if ($compactKeys !== []) {
                    $ph = implode(',', array_fill(0, count($compactKeys), '?'));
                    $expr = 'UPPER(REPLACE(REPLACE(TRIM(sku), " ", ""), CHAR(9), "")) IN ('.$ph.')';
                    if ($spaceKeys !== []) {
                        $w->orWhereRaw($expr, $compactKeys);
                    } else {
                        $w->whereRaw($expr, $compactKeys);
                    }
                }
            });

        $grouped = [];
        foreach ($q->get() as $row) {
            $ck = AmazonDatasheet::normalizeSkuForLookup((string) ($row->sku ?? ''));
            if ($ck === '') {
                continue;
            }
            $grouped[$ck][] = $row;
        }

        $out = [];
        foreach ($grouped as $ck => $items) {
            $lowest = AmazonSkuCompetitor::lowestFromCollection($items);
            $landed = $lowest !== null ? AmazonSkuCompetitor::landedPrice($lowest) : null;
            if ($landed !== null && $landed > 0) {
                $out[$ck] = $landed;
            }
        }

        return $out;
    }
}
