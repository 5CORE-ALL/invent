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
     * Seller SKUs advertised (or implied) by a campaign name.
     * PARENT … → product_master children; otherwise the stripped SKU key itself.
     *
     * @return list<string>
     */
    public static function advertisedSkusFromCampaignName(?string $campaignName): array
    {
        $key = self::skuKeyFromCampaignName($campaignName);
        if ($key === '') {
            return [];
        }
        if (str_starts_with($key, 'PARENT ')) {
            $fam = trim(substr($key, 7));

            return $fam !== '' ? self::childSkusForParentFamily($fam) : [];
        }

        return [$key];
    }

    /**
     * Child seller SKUs whose product_master.parent matches the family name.
     *
     * @return list<string>
     */
    public static function childSkusForParentFamily(string $family): array
    {
        $fam = strtoupper(trim(str_replace("\xC2\xA0", ' ', $family)));
        if ($fam === '' || ! Schema::hasTable('product_master')) {
            return [];
        }

        $candidates = [$fam];
        if (! str_starts_with($fam, 'PARENT ')) {
            $candidates[] = 'PARENT '.$fam;
        }
        $pmRows = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $c) {
                    $q->orWhereRaw('UPPER(TRIM(parent)) = ?', [$c]);
                }
            })
            ->get(['sku']);

        $out = [];
        foreach ($pmRows as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '' || str_starts_with(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $out[$sku] = true;
        }

        return array_keys($out);
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
     * Empty parent-row listing CVR (Amz page CVR L30) for a campaign with no SKU key.
     *
     * @return array{page_cvr: float|null, page_parent: string, a_l30: float|null, sess30: float|null, sess7: float|null, a_l60: float|null, sess60: float|null}
     */
    public static function emptyParentListingCvr(): array
    {
        return [
            'page_cvr' => null,
            'page_parent' => '',
            'a_l30' => null,
            'sess30' => null,
            'sess7' => null,
            'a_l60' => null,
            'sess60' => null,
        ];
    }

    /**
     * Amz tabulator parent-row CVR L30 (Σ A L30 ÷ Σ Sess30 × 100) for each campaign.
     * Child campaigns resolve to their product_master parent and use that parent summary.
     *
     * @param  list<string>  $campaignNames
     * @return array<string, array{page_cvr: float|null, page_parent: string, a_l30: float|null, sess30: float|null, sess7: float|null, a_l60: float|null, sess60: float|null}>
     */
    public static function parentListingCvrForCampaignNames(array $campaignNames): array
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
        $cvrByKey = self::parentListingCvrForSkuKeys(array_keys($uniqueKeys));
        $empty = self::emptyParentListingCvr();
        $out = [];
        foreach ($keysByName as $name => $key) {
            $out[$name] = $cvrByKey[$key] ?? $empty;
        }

        return $out;
    }

    /**
     * @param  list<string>  $skuKeys
     * @return array<string, array{page_cvr: float|null, page_parent: string, a_l30: float|null, sess30: float|null, sess7: float|null, a_l60: float|null, sess60: float|null}>
     */
    private static function parentListingCvrForSkuKeys(array $skuKeys): array
    {
        $skuKeys = array_values(array_filter(array_map(
            static fn ($k) => is_string($k) ? trim($k) : '',
            $skuKeys
        ), static fn (string $k): bool => $k !== ''));
        if ($skuKeys === []) {
            return [];
        }

        $familyByKey = self::parentFamilyBySkuKeys($skuKeys);
        $families = [];
        foreach ($familyByKey as $fam) {
            if ($fam !== '') {
                $families[$fam] = true;
            }
        }
        $childSkusByFamily = self::childSkusGroupedByParentFamilies(array_keys($families));

        $lookupSkus = $skuKeys;
        foreach ($childSkusByFamily as $kids) {
            foreach ($kids as $sku) {
                $lookupSkus[] = $sku;
            }
        }
        $sheetByCompact = self::datasheetByCompactSku(array_values(array_unique($lookupSkus)));

        $aggByFamily = [];
        foreach (array_keys($families) as $fam) {
            $kids = $childSkusByFamily[$fam] ?? [];
            $aggByFamily[$fam] = self::sumListingCvrFromSheets($kids, $sheetByCompact);
        }

        $out = [];
        foreach ($skuKeys as $key) {
            $fam = $familyByKey[$key] ?? '';
            if ($fam !== '' && isset($aggByFamily[$fam])) {
                $sum = $aggByFamily[$fam];
                $parentLabel = 'PARENT '.$fam;
            } else {
                $sum = self::sumListingCvrFromSheets([$key], $sheetByCompact);
                $parentLabel = $key;
            }
            $sess30 = $sum['sess30'];
            $aL30 = $sum['a_l30'];
            $cvr = ($sess30 !== null && $sess30 > 0 && $aL30 !== null)
                ? round(($aL30 / $sess30) * 100, 2)
                : 0.0;
            $out[$key] = [
                'page_cvr' => $cvr,
                'page_parent' => $parentLabel,
                'a_l30' => $aL30 ?? 0.0,
                'sess30' => $sess30 ?? 0.0,
                'sess7' => $sum['sess7'] ?? 0.0,
                'a_l60' => $sum['a_l60'] ?? 0.0,
                'sess60' => $sum['sess60'] ?? 0.0,
            ];
        }

        return $out;
    }

    /**
     * product_master parent family for each campaign SKU key (PARENT prefix or child → parent).
     *
     * @param  list<string>  $skuKeys
     * @return array<string, string>
     */
    private static function parentFamilyBySkuKeys(array $skuKeys): array
    {
        $familyByKey = [];
        $childKeys = [];
        foreach ($skuKeys as $key) {
            if (str_starts_with($key, 'PARENT ')) {
                $fam = self::normalizeParentFamily(substr($key, 7));
                $familyByKey[$key] = $fam;
            } else {
                $childKeys[] = $key;
            }
        }
        if ($childKeys === [] || ! Schema::hasTable('product_master')) {
            return $familyByKey;
        }

        $spaceKeys = [];
        $compactKeys = [];
        foreach ($childKeys as $key) {
            $space = AmazonDatasheet::normalizeSkuSpaces($key);
            if ($space !== '') {
                $spaceKeys[] = $space;
            }
            $compact = AmazonDatasheet::normalizeSkuForLookup($key);
            if ($compact !== '') {
                $compactKeys[] = $compact;
            }
        }
        $spaceKeys = array_values(array_unique(array_filter($spaceKeys)));
        $compactKeys = array_values(array_unique(array_filter($compactKeys)));
        if ($spaceKeys === [] && $compactKeys === []) {
            return $familyByKey;
        }

        $pmRows = ProductMaster::query()
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
            ->get(['sku', 'parent']);

        $parentByCompact = [];
        $parentBySpace = [];
        foreach ($pmRows as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '' || str_starts_with(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $fam = self::normalizeParentFamily((string) ($pm->parent ?? ''));
            if ($fam === '') {
                continue;
            }
            $parentByCompact[AmazonDatasheet::normalizeSkuForLookup($sku)] = $fam;
            $parentBySpace[AmazonDatasheet::normalizeSkuSpaces($sku)] = $fam;
        }

        foreach ($childKeys as $key) {
            $fam = $parentBySpace[AmazonDatasheet::normalizeSkuSpaces($key)]
                ?? $parentByCompact[AmazonDatasheet::normalizeSkuForLookup($key)]
                ?? '';
            $familyByKey[$key] = $fam;
        }

        return $familyByKey;
    }

    /**
     * @param  list<string>  $families
     * @return array<string, list<string>>
     */
    private static function childSkusGroupedByParentFamilies(array $families): array
    {
        $families = array_values(array_filter(array_map(
            [self::class, 'normalizeParentFamily'],
            $families
        ), static fn (string $f): bool => $f !== ''));
        if ($families === [] || ! Schema::hasTable('product_master')) {
            return [];
        }

        $want = [];
        foreach ($families as $fam) {
            $want[] = $fam;
            $want[] = 'PARENT '.$fam;
        }
        $want = array_values(array_unique($want));
        $ph = implode(',', array_fill(0, count($want), '?'));
        $pmRows = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw('UPPER(TRIM(parent)) IN ('.$ph.')', $want)
            ->get(['sku', 'parent']);

        $out = [];
        foreach ($pmRows as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '' || str_starts_with(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $fam = self::normalizeParentFamily((string) ($pm->parent ?? ''));
            if ($fam === '') {
                continue;
            }
            $out[$fam][] = $sku;
        }

        return $out;
    }

    private static function normalizeParentFamily(?string $parent): string
    {
        $p = AmazonDatasheet::normalizeSkuSpaces($parent);
        if (str_starts_with($p, 'PARENT ')) {
            $p = trim(substr($p, 7));
        }

        return $p;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, AmazonDatasheet>
     */
    private static function datasheetByCompactSku(array $skus): array
    {
        $skus = array_values(array_filter(array_map(
            static fn ($s) => is_string($s) ? trim($s) : '',
            $skus
        ), static fn (string $s): bool => $s !== ''));
        if ($skus === [] || ! Schema::hasTable('amazon_datsheets')) {
            return [];
        }

        $spaceKeys = [];
        $compactKeys = [];
        foreach ($skus as $sku) {
            $space = AmazonDatasheet::normalizeSkuSpaces($sku);
            if ($space !== '') {
                $spaceKeys[] = $space;
            }
            $compact = AmazonDatasheet::normalizeSkuForLookup($sku);
            if ($compact !== '') {
                $compactKeys[] = $compact;
            }
        }
        $spaceKeys = array_values(array_unique(array_filter($spaceKeys)));
        $compactKeys = array_values(array_unique(array_filter($compactKeys)));
        if ($spaceKeys === [] && $compactKeys === []) {
            return [];
        }

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

        $sheetByCompact = [];
        foreach ($rows as $row) {
            $ck = AmazonDatasheet::normalizeSkuForLookup((string) ($row->sku ?? ''));
            if ($ck === '') {
                continue;
            }
            if (! isset($sheetByCompact[$ck])) {
                $sheetByCompact[$ck] = $row;
            } else {
                $picked = AmazonDatasheet::pickBestForProductSku(
                    (string) ($row->sku ?? ''),
                    [$sheetByCompact[$ck], $row]
                );
                if ($picked !== null) {
                    $sheetByCompact[$ck] = $picked;
                }
            }
        }

        return $sheetByCompact;
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, AmazonDatasheet>  $sheetByCompact
     * @return array{a_l30: float, sess30: float, sess7: float, a_l60: float, sess60: float}
     */
    private static function sumListingCvrFromSheets(array $skus, array $sheetByCompact): array
    {
        $aL30 = 0.0;
        $sess30 = 0.0;
        $sess7 = 0.0;
        $aL60 = 0.0;
        $sess60 = 0.0;
        $seen = [];
        foreach ($skus as $sku) {
            $ck = AmazonDatasheet::normalizeSkuForLookup((string) $sku);
            if ($ck === '' || isset($seen[$ck])) {
                continue;
            }
            $seen[$ck] = true;
            $sheet = $sheetByCompact[$ck] ?? null;
            if ($sheet === null) {
                continue;
            }
            $aL30 += (float) ($sheet->units_ordered_l30 ?? 0);
            $sess30 += (float) ($sheet->sessions_l30 ?? 0);
            $sess7 += (float) ($sheet->sessions_l7 ?? 0);
            $aL60 += (float) ($sheet->units_ordered_l60 ?? 0);
            $sess60 += (float) ($sheet->sessions_l60 ?? 0);
        }

        return [
            'a_l30' => $aL30,
            'sess30' => $sess30,
            'sess7' => $sess7,
            'a_l60' => $aL60,
            'sess60' => $sess60,
        ];
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
            ->get(['campaign_id', 'sku', 'ad_id']);

        $realByCid = [];
        $nameByCid = [];
        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
            if ($sku === '' || $cid === '') {
                continue;
            }
            $adId = (string) ($row->ad_id ?? '');
            if (str_starts_with($adId, 'name:')) {
                $nameByCid[$cid][] = $sku;
            } else {
                $realByCid[$cid][] = $sku;
            }
            $skus[] = $sku;
        }
        $byCid = [];
        foreach (array_keys($cids) as $cid) {
            $list = $realByCid[$cid] ?? $nameByCid[$cid] ?? [];
            if ($list !== []) {
                $byCid[$cid] = $list;
            }
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
