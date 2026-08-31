<?php

namespace App\Support;

use App\Models\AmazonAdsCampaignSku;
use App\Models\AmazonDatasheet;
use App\Models\AmazonProductReview;
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
 * - Dil (grid and pause/PR) = ovl30 ÷ Inv × 100
 * - Price (grid) = amazon_datsheets.price, else grey LMP
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
     * Price and Dil% for pause/PR — same values as the Amazon Ads All grid.
     * Dil = ovl30 ÷ Inv. Price = Amazon list price, or the grey LMP when list price is missing.
     *
     * @param  array{price?: mixed, dil?: mixed, inv?: mixed, ovl30?: mixed, lmp_price?: mixed, rating?: mixed}  $m
     * @return array{price: float|null, dil: float|null, rating: float|null}
     */
    public static function gridMetricsForPause(array $m): array
    {
        $inv = isset($m['inv']) && is_numeric($m['inv']) ? (float) $m['inv'] : null;
        $ovl = isset($m['ovl30']) && is_numeric($m['ovl30']) ? (float) $m['ovl30'] : null;
        $dil = self::tabulatorDil($inv, $ovl);
        if ($dil === null && isset($m['dil']) && is_numeric($m['dil'])) {
            $dil = (float) $m['dil'];
        }

        $amazon = isset($m['price']) && is_numeric($m['price']) ? (float) $m['price'] : null;
        $lmp = isset($m['lmp_price']) && is_numeric($m['lmp_price']) ? (float) $m['lmp_price'] : null;
        $price = ($amazon !== null && is_finite($amazon) && $amazon > 0)
            ? $amazon
            : (($lmp !== null && is_finite($lmp) && $lmp > 0) ? $lmp : null);

        $rating = isset($m['rating']) && is_numeric($m['rating']) ? (float) $m['rating'] : null;
        if ($rating !== null && (! is_finite($rating) || $rating <= 0)) {
            $rating = null;
        }

        return ['price' => $price, 'dil' => $dil, 'rating' => $rating];
    }

    /**
     * Avg rating + review count from amazon_product_reviews, keyed by uppercase SKU.
     *
     * @param  list<string>  $skus
     * @return array<string, array{rating: float|null, review_count: int}>
     */
    public static function reviewsBySkus(array $skus): array
    {
        $want = [];
        foreach ($skus as $sku) {
            $k = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $sku)));
            if ($k !== '') {
                $want[$k] = true;
            }
        }
        if ($want === [] || ! Schema::hasTable('amazon_product_reviews')) {
            return [];
        }

        $rows = AmazonProductReview::query()
            ->where(function ($q) {
                $q->where('channel', 'Amazon')->orWhereNull('channel')->orWhere('channel', '');
            })
            ->whereNotNull('sku')
            ->get(['sku', 'product_rating', 'review_count']);

        $exact = [];
        $compact = [];
        foreach ($rows as $rr) {
            $k = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $rr->sku)));
            if ($k === '') {
                continue;
            }
            $entry = [
                'rating' => $rr->product_rating !== null ? (float) $rr->product_rating : null,
                'review_count' => (int) ($rr->review_count ?? 0),
            ];
            $exact[$k] = $entry;
            $ck = AmazonDatasheet::normalizeSkuForLookup($k);
            if ($ck !== '') {
                $compact[$ck][] = ['sku' => $k, 'entry' => $entry];
            }
        }

        $out = [];
        foreach (array_keys($want) as $sku) {
            if (isset($exact[$sku])) {
                $out[$sku] = $exact[$sku];
                continue;
            }
            $ck = AmazonDatasheet::normalizeSkuForLookup($sku);
            $cands = $compact[$ck] ?? [];
            if ($cands === []) {
                continue;
            }
            $spaces = AmazonDatasheet::normalizeSkuSpaces($sku);
            $picked = null;
            foreach ($cands as $c) {
                if (AmazonDatasheet::normalizeSkuSpaces((string) $c['sku']) === $spaces) {
                    $picked = $c['entry'];
                    break;
                }
            }
            $out[$sku] = $picked ?? $cands[0]['entry'];
        }

        return $out;
    }

    /**
     * Lowest advertised-SKU rating per campaign (amazon_ads_campaign_skus).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{rating: float|null, review_count: int|null, sku: string}>
     */
    public static function minRatingForCampaignIds(array $campaignIds): array
    {
        $cids = [];
        foreach ($campaignIds as $cid) {
            $id = preg_replace('/\D+/', '', trim((string) $cid)) ?: '';
            if ($id !== '') {
                $cids[$id] = true;
            }
        }
        if ($cids === [] || ! Schema::hasTable('amazon_ads_campaign_skus')) {
            return [];
        }

        $rows = AmazonAdsCampaignSku::query()
            ->whereIn('campaign_id', array_keys($cids))
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['campaign_id', 'sku']);

        $byCid = [];
        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
            if ($sku === '' || $cid === '') {
                continue;
            }
            $byCid[$cid][] = $sku;
            $skus[] = $sku;
        }
        $reviews = self::reviewsBySkus($skus);
        $out = [];
        foreach ($byCid as $cid => $list) {
            $min = null;
            $count = null;
            $skuUsed = '';
            foreach ($list as $sku) {
                $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
                $hit = $reviews[$key] ?? null;
                if (! is_array($hit) || $hit['rating'] === null) {
                    continue;
                }
                $rating = (float) $hit['rating'];
                if ($min === null || $rating < $min) {
                    $min = $rating;
                    $count = (int) ($hit['review_count'] ?? 0);
                    $skuUsed = $sku;
                }
            }
            $out[$cid] = [
                'rating' => $min,
                'review_count' => $count,
                'sku' => $skuUsed,
            ];
        }

        return $out;
    }

    /**
     * @return array{sku: string, price: ?float, dil: ?float, inv: ?float, l30: ?float, ovl30: ?float, lmp_price: ?float, rating: ?float, review_count: ?int}
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
            'rating' => null,
            'review_count' => null,
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
        $lookupSkus = array_values(array_unique($lookupSkus));
        $shopifyByPm = Schema::hasTable('shopify_skus')
            ? ShopifySku::mapByProductSkus($lookupSkus)
            : collect();
        $lmpByCompact = self::lmpLandedBySkuKeys($lookupSkus);
        $reviewsBySku = self::reviewsBySkus($lookupSkus);

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
                $rev = self::pickLowestReview($reviewsBySku, $kids);
                $out[$key] = [
                    'sku' => $key,
                    'price' => $price,
                    'dil' => self::tabulatorDil($inv, $ovl30),
                    'inv' => $inv,
                    'l30' => $l30,
                    'ovl30' => $ovl30,
                    'lmp_price' => $lmpPrice,
                    'rating' => $rev['rating'],
                    'review_count' => $rev['review_count'],
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

            $rev = self::pickLowestReview($reviewsBySku, [$key]);
            $out[$key] = [
                'sku' => $key,
                'price' => $price,
                'dil' => self::tabulatorDil($inv, $ovl30),
                'inv' => $inv,
                'l30' => $l30,
                'ovl30' => $ovl30,
                'lmp_price' => $lmpByCompact[AmazonDatasheet::normalizeSkuForLookup($key)] ?? null,
                'rating' => $rev['rating'],
                'review_count' => $rev['review_count'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{rating: float|null, review_count: int}>  $reviewsBySku
     * @param  list<string>  $skus
     * @return array{rating: float|null, review_count: int|null}
     */
    private static function pickLowestReview(array $reviewsBySku, array $skus): array
    {
        $min = null;
        $count = null;
        foreach ($skus as $sku) {
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $sku)));
            $hit = $reviewsBySku[$key] ?? null;
            if (! is_array($hit) || $hit['rating'] === null) {
                continue;
            }
            $rating = (float) $hit['rating'];
            if ($min === null || $rating < $min) {
                $min = $rating;
                $count = (int) ($hit['review_count'] ?? 0);
            }
        }

        return ['rating' => $min, 'review_count' => $count];
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
