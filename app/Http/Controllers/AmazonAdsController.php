<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Support\AmazonAdsSbidRule;
use App\Support\AmazonAcosSbgtRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AmazonAdsController extends Controller
{
    /**
     * Whitelist: URL segment => database table (raw read-only for DataTables).
     *
     * Maps to Amazon Ads All tabs:
     * - sp_reports: SP campaign reports (utilized KW / yes_sbid lives here)
     * - sb_reports: SB campaign report rows (also used by HL branch of utilized views)
     * - bid_caps: SKU bid caps
     * - sd_reports: SD campaign reports
     * - fbm_targeting: FBM targeting check records
     */
    private const RAW_TABLE_SOURCES = [
        'sp_reports' => 'amazon_sp_campaign_reports',
        'sb_reports' => 'amazon_sb_campaign_reports',
        'bid_caps' => 'amazon_bid_caps',
        'sd_reports' => 'amazon_sd_campaign_reports',
        'fbm_targeting' => 'amazon_fbm_targeting_checks',
    ];

    /**
     * SP and SB campaign raw tables: hide noisy Amazon metric / audit columns on Amazon Ads All (keep ids, cost, CPC block, L-spends, Sold/Prchase, BGT/SBGT, ACOS, SL 30).
     * SB uses the same list; SB display is then restricted to the same column set and order as SP.
     *
     * @var array<int, string>
     */
    private const AMAZON_SP_CAMPAIGN_REPORTS_HIDDEN_DISPLAY_COLUMNS = [
        'note',
        'impressions',
        'clicks',
        'spend',
        'sales1d',
        'sales7d',
        'sales14d',
        'unitsSoldClicks1d',
        'unitsSoldClicks7d',
        'unitsSoldClicks14d',
        'unitsSoldClicks30d',
        'attributedSalesSameSku1d',
        'attributedSalesSameSku7d',
        'attributedSalesSameSku14d',
        'attributedSalesSameSku30d',
        'unitsSoldSameSku1d',
        'unitsSoldSameSku7d',
        'unitsSoldSameSku14d',
        'unitsSoldSameSku30d',
        'clickThroughRate',
        'qualifiedBorrows',
        'purchases1d',
        'purchases7d',
        'purchases14d',
        'purchases30d',
        'purchases',
        'addToList',
        'royaltyQualifiedBorrows',
        'purchasesSameSku1d',
        'purchasesSameSku7d',
        'purchasesSameSku14d',
        'purchasesSameSku30d',
        'kindleEditionNormalizedPagesRead14d',
        'kindleEditionNormalizedPagesRoyalties14d',
        'campaignBiddingStrategy',
        'currentSpBidPrice',
        'apprSbid',
        'currentUnderSpBidPrice',
        'apprUnderSbid',
        'created_at',
        'updated_at',
    ];

    /**
     * Column order: id first (newest-first default), then key + bid columns (yes_sbid, last_sbid, sbid_m), costPerClick before sbid, then the rest.
     * Display order further adjusts: last_sbid + sbid after U1%. `yes_sbid` and `sbid_m` are not shown on All (still present in row JSON for SP push / pick-bid).
     */
    private static function orderedColumnsForTable(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $cols = Schema::getColumnListing($table);

        $priority = [
            'id',
            'campaign_id',
            'campaignName',
            'ad_type',
            'report_date_range',
            'campaignStatus',
            'yes_sbid',
            'last_sbid',
            'sbid_m',
            'costPerClick',
            'sbid',
        ];

        $ordered = [];
        foreach ($priority as $c) {
            if (in_array($c, $cols, true)) {
                $ordered[] = $c;
            }
        }
        foreach ($cols as $c) {
            if (! in_array($c, $ordered, true)) {
                $ordered[] = $c;
            }
        }

        return $ordered;
    }

    /**
     * Columns sent to the Amazon Ads All DataTables, including computed utilization % after `campaignName`
     * (U7%/U2%/U1% from L7 SP / L2 SP / L1 SP vs `campaignBudgetAmount`; so `ad_type` may sit before `campaign_id` without pulling U7/U2/U1 next to it).
     * `bgt` follows `campaignName`; `sbgt` follows `bgt` when the table has campaign budget.
     */
    private static function displayColumnsForTable(string $table): array
    {
        $ordered = self::orderedColumnsForTable($table);
        if ($ordered === []) {
            return [];
        }
        $idx = array_search('ad_type', $ordered, true);
        if ($idx === false) {
            return $ordered;
        }
        $idxCnUb = array_search('campaignName', $ordered, true);
        if ($idxCnUb !== false) {
            array_splice($ordered, $idxCnUb + 1, 0, ['U7%', 'U2%', 'U1%']);
        } else {
            $idxCidUb = array_search('campaign_id', $ordered, true);
            if ($idxCidUb !== false) {
                array_splice($ordered, $idxCidUb + 1, 0, ['U7%', 'U2%', 'U1%']);
            } else {
                array_splice($ordered, $idx + 1, 0, ['U7%', 'U2%', 'U1%']);
            }
        }

        $hadLastSbid = in_array('last_sbid', $ordered, true);
        $hadSbidCol = in_array('sbid', $ordered, true);
        if ($hadLastSbid || $hadSbidCol) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'last_sbid' && $c !== 'sbid'));
            $idxU1Bid = array_search('U1%', $ordered, true);
            if ($idxU1Bid !== false) {
                $insertBid = [];
                if ($hadLastSbid) {
                    $insertBid[] = 'last_sbid';
                }
                if ($hadSbidCol) {
                    $insertBid[] = 'sbid';
                }
                if ($insertBid !== []) {
                    array_splice($ordered, $idxU1Bid + 1, 0, $insertBid);
                }
            }
        }

        // Display "bgt" after campaign name (same value as campaignBudgetAmount; hide duplicate DB column).
        $idxCn = array_search('campaignName', $ordered, true);
        if ($idxCn !== false && in_array('campaignBudgetAmount', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'campaignBudgetAmount'));
            $idxCn = array_search('campaignName', $ordered, true);
            if ($idxCn !== false) {
                array_splice($ordered, $idxCn + 1, 0, ['bgt']);
            }
        }

        // SBGT immediately after BGT (same scope: table has campaign budget).
        if (in_array('campaignBudgetAmount', self::orderedColumnsForTable($table), true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'sbgt'));
            $idxBgtForSbgt = array_search('bgt', $ordered, true);
            if ($idxBgtForSbgt !== false) {
                array_splice($ordered, $idxBgtForSbgt + 1, 0, ['sbgt']);
            }
        }

        $idxBgt = array_search('bgt', $ordered, true);
        if ($idxBgt !== false && in_array('clicks', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'clicks'));
            $idxAfterBgt = array_search('sbgt', $ordered, true);
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('bgt', $ordered, true);
            }
            if ($idxAfterBgt !== false) {
                array_splice($ordered, $idxAfterBgt + 1, 0, ['clicks']);
            }
        }

        $idxClicks = array_search('clicks', $ordered, true);
        if ($idxClicks !== false && in_array('cost', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'cost'));
            $idxClicks = array_search('clicks', $ordered, true);
            if ($idxClicks !== false) {
                array_splice($ordered, $idxClicks + 1, 0, ['cost']);
            }
        }

        $baseDb = self::orderedColumnsForTable($table);
        $supportLSpend = in_array('report_date_range', $baseDb, true)
            && in_array('campaign_id', $baseDb, true)
            && (in_array('spend', $baseDb, true) || in_array('cost', $baseDb, true));
        if ($supportLSpend) {
            $idxCostIns = array_search('cost', $ordered, true);
            if ($idxCostIns !== false) {
                array_splice($ordered, $idxCostIns + 1, 0, ['L7spend', 'L2spend', 'L1spend']);
            } else {
                $idxClkIns = array_search('clicks', $ordered, true);
                if ($idxClkIns !== false) {
                    array_splice($ordered, $idxClkIns + 1, 0, ['L7spend', 'L2spend', 'L1spend']);
                }
            }
        }

        // CPC 3 / 2 / 1 after L1 spend: SP uses `costPerClick`; SB CPC1 uses L1 summary cost ÷ clicks; CPC2/CPC3 use daily row lookups.
        $canCpcBlock = in_array('campaign_id', $ordered, true)
            && in_array('report_date_range', $ordered, true)
            && (
                in_array('costPerClick', $ordered, true)
                || ($table === 'amazon_sb_campaign_reports'
                    && in_array('clicks', $ordered, true)
                    && (in_array('cost', $ordered, true) || in_array('spend', $ordered, true)))
            );
        if ($canCpcBlock) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'costPerClick'));
            $idxL1 = array_search('L1spend', $ordered, true);
            if ($idxL1 !== false) {
                array_splice($ordered, $idxL1 + 1, 0, ['CPC3', 'CPC2', 'costPerClick']);
            } else {
                $idxU1Fallback = array_search('U1%', $ordered, true);
                if ($idxU1Fallback !== false) {
                    array_splice($ordered, $idxU1Fallback + 1, 0, ['CPC3', 'CPC2', 'costPerClick']);
                } else {
                    $ordered[] = 'CPC3';
                    $ordered[] = 'CPC2';
                    $ordered[] = 'costPerClick';
                }
            }
        }

        $baseCols = self::orderedColumnsForTable($table);
        if (in_array('sales30d', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'sales30d'));
            $idxCpc1 = array_search('costPerClick', $ordered, true);
            if ($idxCpc1 !== false) {
                array_splice($ordered, $idxCpc1 + 1, 0, ['sales30d']);
            } else {
                $ordered[] = 'sales30d';
            }
        } elseif ($table === 'amazon_sb_campaign_reports' && in_array('sales', $baseCols, true)) {
            // SB: no `sales30d` column — show L30 summary `sales` under the same grid key / SL 30 header as SP.
            $idxCpc1Sb = array_search('costPerClick', $ordered, true);
            if ($idxCpc1Sb !== false) {
                array_splice($ordered, $idxCpc1Sb + 1, 0, ['sales30d']);
            } else {
                $ordered[] = 'sales30d';
            }
        }

        // ACOS (%) = cost / sales * 100 — display after primary sales column when cost + sales exist on the table.
        $canAcos = in_array('cost', $baseCols, true)
            && (in_array('sales30d', $baseCols, true) || in_array('sales', $baseCols, true));
        if ($canAcos) {
            $idxSales30 = array_search('sales30d', $ordered, true);
            if ($idxSales30 !== false) {
                array_splice($ordered, $idxSales30 + 1, 0, ['ACOS']);
            } else {
                $idxSales = array_search('sales', $ordered, true);
                if ($idxSales !== false) {
                    array_splice($ordered, $idxSales + 1, 0, ['ACOS']);
                }
            }
        }

        // Prchase (Sold): L30 purchases (`purchases30d` or SB `purchases`) — after L1 SP; hide raw purchase columns from the list.
        $purchDbForSold = in_array('purchases30d', $baseCols, true) ? 'purchases30d' : null;
        if ($purchDbForSold === null && $table === 'amazon_sb_campaign_reports' && in_array('purchases', $baseCols, true)) {
            $purchDbForSold = 'purchases';
        }
        if ($purchDbForSold !== null) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'purchases30d' && $c !== 'purchases'));
            $idxL1spForSold = array_search('L1spend', $ordered, true);
            if ($idxL1spForSold !== false) {
                array_splice($ordered, $idxL1spForSold + 1, 0, ['Prchase']);
            } elseif (in_array('costPerClick', $ordered, true)) {
                $idxL1CpcFallback = array_search('costPerClick', $ordered, true);
                if ($idxL1CpcFallback !== false) {
                    array_splice($ordered, $idxL1CpcFallback + 1, 0, ['Prchase']);
                }
            }
        }

        if ($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports') {
            $ordered = array_values(array_filter(
                $ordered,
                static fn (string $c): bool => ! in_array($c, self::AMAZON_SP_CAMPAIGN_REPORTS_HIDDEN_DISPLAY_COLUMNS, true)
            ));
        }

        // Not shown on Amazon Ads All (still in DB / global search; `yes_sbid` + `sbid_m` kept in row JSON for SP push).
        $ordered = array_values(array_filter(
            $ordered,
            static fn (string $c): bool => ! in_array($c, ['pink_dil_paused_at', 'campaignBudgetCurrencyCode', 'yes_sbid', 'sbid_m', 'unitsSoldSameSku30d'], true)
        ));

        // SB All: only columns that SP All shows, in the same order (no SB-only extra schema columns).
        if ($table === 'amazon_sb_campaign_reports') {
            $spOrder = self::displayColumnsForTable('amazon_sp_campaign_reports');
            $have = array_flip($ordered);
            $filtered = [];
            foreach ($spOrder as $c) {
                if (isset($have[$c])) {
                    $filtered[] = $c;
                }
            }
            // SB-only: inventory from product_master → shopify_skus.inv, keyed by campaign name (HL-style SKU match).
            $idxCnSb = array_search('campaignName', $filtered, true);
            if ($idxCnSb !== false) {
                array_splice($filtered, $idxCnSb + 1, 0, ['INV']);
            } else {
                $filtered[] = 'INV';
            }
            $idxL1Sb = array_search('L1spend', $filtered, true);
            if ($idxL1Sb !== false) {
                array_splice($filtered, $idxL1Sb + 1, 0, ['L1cost', 'L1clicks']);
            }

            return $filtered;
        }

        return $ordered;
    }

    /**
     * Suggested daily budget tier (1 / 2 / 4 / 8 / 12) from L30 ACOS (%), same as {@see AmazonAcosSbgtRule}
     * and the ACOS column: cost ÷ sales, pink ≤10 → 12 … red ≥40 → 1.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function computedSbgtFromReportRow(array $rowArr, array $dbColumns): ?int
    {
        $acos = self::computedAcosPercentFromReportRow($rowArr, $dbColumns);
        if ($acos === null) {
            return null;
        }

        return AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos);
    }

    /**
     * ACOS (%) from the same row: (cost / sales) × 100. Sales prefers `sales30d`, else `sales`.
     * When cost > 0 and sales = 0, ACOS is defined as 100% (same convention as budget tooling).
     * For the grid, pass a row whose `cost` / `sales30d` already match SP L30 / SL30 overlays when applicable.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function computedAcosPercentFromReportRow(array $rowArr, array $dbColumns): ?float
    {
        if (! in_array('cost', $dbColumns, true)) {
            return null;
        }
        $salesKey = null;
        if (in_array('sales30d', $dbColumns, true)) {
            $salesKey = 'sales30d';
        } elseif (in_array('sales', $dbColumns, true)) {
            $salesKey = 'sales';
        }
        if ($salesKey === null) {
            return null;
        }
        $sales = (float) ($rowArr[$salesKey] ?? 0);
        $cost = $rowArr['cost'] ?? null;
        if ($cost === null || $cost === '') {
            return null;
        }
        $c = (float) $cost;
        if (! is_finite($c) || ! is_finite($sales)) {
            return null;
        }
        if ($sales > 0) {
            $v = ($c / $sales) * 100;

            return is_finite($v) ? (float) round($v, 0) : null;
        }
        if ($c > 0) {
            return 100.0;
        }

        return 0.0;
    }

    /**
     * SQL scalar for ORDER BY ACOS, matching {@see computedAcosPercentFromReportRow} on L30 overlay inputs when available.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function sqlExpressionForAcosSort(string $table, array $dbColumns): ?string
    {
        if (! in_array('cost', $dbColumns, true)) {
            return null;
        }
        $costEff = 'COALESCE(cost, 0)';
        $sumSpend = self::correlatedL30SummarySpendScalarSubquerySql($table, $dbColumns);
        if ($sumSpend !== null) {
            $costEff = 'COALESCE(('.$sumSpend.'), cost, 0)';
        }
        if (in_array('sales30d', $dbColumns, true)) {
            $salesEff = 'sales30d';
            $sumSales = self::correlatedL30SummarySales30dScalarSubquerySql($table, $dbColumns);
            if ($sumSales !== null) {
                $salesEff = 'COALESCE(('.$sumSales.'), sales30d)';
            }

            return 'CASE WHEN COALESCE('.$salesEff.', 0) > 0 THEN '.$costEff.' / NULLIF('.$salesEff.', 0) * 100 WHEN '.$costEff.' > 0 THEN 100 ELSE 0 END';
        }
        if (in_array('sales', $dbColumns, true)) {
            $salesEff = 'sales';
            $sumSales = self::correlatedL30SummarySales30dScalarSubquerySql($table, $dbColumns);
            if ($sumSales !== null) {
                $salesEff = 'COALESCE(('.$sumSales.'), sales)';
            }

            return 'CASE WHEN COALESCE('.$salesEff.', 0) > 0 THEN '.$costEff.' / NULLIF('.$salesEff.', 0) * 100 WHEN '.$costEff.' > 0 THEN 100 ELSE 0 END';
        }

        return null;
    }

    /**
     * SQL scalar for ORDER BY SBGT tier (1–12), from the same L30 ACOS % as {@see sqlExpressionForAcosSort}
     * and {@see computedSbgtFromReportRow}.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function sqlExpressionForSbgtSort(string $table, array $dbColumns): ?string
    {
        $acosExpr = self::sqlExpressionForAcosSort($table, $dbColumns);
        if ($acosExpr === null) {
            return null;
        }

        return AmazonAcosSbgtRule::sqlSortCaseExpression($acosExpr);
    }

    /**
     * Legacy hook: totals (cost, sales, L-spends, etc.) are sent at full numeric precision; the grid formats them.
     *
     * @param  array<string, mixed>  $arr
     * @param  array<int, string>  $displayColumns
     */
    private static function roundAmazonAdsDisplayNumericFields(array &$arr, array $displayColumns): void
    {
        // Intentionally empty: money/totals are sent at full numeric precision; the grid formats them.
    }

    /**
     * CP master / ADVMasters-style: SUM(shopify_skus.inv) for rows where sku is not PARENT and parent matches key.
     *
     * @param  Collection<int, ProductMaster>  $productMasterRows
     * @return array<string, float>
     */
    private static function buildInventorySumByParentKeyFromProductMasterRows(Collection $productMasterRows): array
    {
        $childSkus = [];
        foreach ($productMasterRows as $pm) {
            $s = trim((string) ($pm->sku ?? ''));
            if ($s === '' || str_starts_with(strtoupper($s), 'PARENT')) {
                continue;
            }
            $childSkus[] = $s;
        }
        $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));
        $totals = [];
        foreach ($productMasterRows as $pm) {
            $s = trim((string) ($pm->sku ?? ''));
            if ($s === '' || str_starts_with(strtoupper($s), 'PARENT')) {
                continue;
            }
            $pKey = strtoupper(trim((string) ($pm->parent ?? '')));
            if ($pKey === '') {
                continue;
            }
            $rec = $shopifyByPmSku->get($s);
            $totals[$pKey] = ($totals[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);
        }

        return $totals;
    }

    /**
     * Memoized resolver: SB `campaignName` → INV aligned with CP master.
     * Campaign ↔ SKU match: HL-style exact / HEAD / longest substring.
     * `PARENT …` SKUs use sum of children’s Shopify inv by `product_master.parent` (not the synthetic parent’s shopify row).
     *
     * @return \Closure(?string): ?int
     */
    private static function buildSbInvByCampaignNameResolver(): \Closure
    {
        $allPm = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'parent']);
        $pmSkus = $allPm->pluck('sku')->map(static fn ($s) => (string) $s)->unique()->values()->all();
        $shopifyByPmSku = ShopifySku::mapByProductSkus($pmSkus);
        $inventoryByParent = self::buildInventorySumByParentKeyFromProductMasterRows($allPm);
        $parentSkuToFamilyKey = [];
        foreach ($allPm as $pm) {
            $s = trim((string) ($pm->sku ?? ''));
            if ($s === '' || ! str_starts_with(strtoupper($s), 'PARENT')) {
                continue;
            }
            $normSku = preg_replace('/\s+/', ' ', strtoupper($s));
            $parentCol = trim((string) ($pm->parent ?? ''));
            if ($parentCol !== '') {
                $parentSkuToFamilyKey[$normSku] = preg_replace('/\s+/', ' ', strtoupper($parentCol));
            } else {
                $rest = trim(preg_replace('/^PARENT\s+/i', '', $s) ?? '');
                $parentSkuToFamilyKey[$normSku] = $rest === ''
                    ? $normSku
                    : preg_replace('/\s+/', ' ', strtoupper($rest));
            }
        }
        usort($pmSkus, static fn (string $a, string $b): int => strlen((string) $b) <=> strlen((string) $a));
        $memo = [];

        return static function (?string $campaignName) use ($pmSkus, $shopifyByPmSku, $inventoryByParent, $parentSkuToFamilyKey, &$memo): ?int {
            if ($campaignName === null || trim($campaignName) === '') {
                return null;
            }
            $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($campaignName)));
            if ($cleanName === '') {
                return null;
            }
            if (array_key_exists($cleanName, $memo)) {
                return $memo[$cleanName];
            }
            $matchedSkus = [];
            foreach ($pmSkus as $sku) {
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper((string) $sku));
                if ($cleanSku === '') {
                    continue;
                }
                $expected1 = $cleanSku;
                $expected2 = $cleanSku.' HEAD';
                if ($cleanName === $expected1 || $cleanName === $expected2) {
                    $matchedSkus[] = (string) $sku;
                }
            }
            $skusToSum = [];
            if ($matchedSkus !== []) {
                $skusToSum = array_values(array_unique($matchedSkus));
            } else {
                $bestLen = 0;
                $bestSkus = [];
                foreach ($pmSkus as $sku) {
                    $cleanSku = preg_replace('/\s+/', ' ', strtoupper((string) $sku));
                    if ($cleanSku === '' || ! str_contains($cleanName, $cleanSku)) {
                        continue;
                    }
                    $len = strlen($cleanSku);
                    if ($len > $bestLen) {
                        $bestLen = $len;
                        $bestSkus = [(string) $sku];
                    } elseif ($len === $bestLen && $len > 0) {
                        $bestSkus[] = (string) $sku;
                    }
                }
                $skusToSum = array_values(array_unique($bestSkus));
            }
            if ($skusToSum === []) {
                $memo[$cleanName] = null;

                return null;
            }
            $sum = 0;
            $any = false;
            foreach ($skusToSum as $sku) {
                $trimSku = trim($sku);
                if ($trimSku === '') {
                    continue;
                }
                if (str_starts_with(strtoupper($trimSku), 'PARENT')) {
                    $normParentSku = preg_replace('/\s+/', ' ', strtoupper($trimSku));
                    $fam = $parentSkuToFamilyKey[$normParentSku] ?? null;
                    if ($fam !== null) {
                        $sum += (int) round($inventoryByParent[$fam] ?? 0);
                        $any = true;

                        continue;
                    }
                }
                $row = $shopifyByPmSku->get($sku);
                if ($row === null) {
                    continue;
                }
                $inv = $row->inv;
                if ($inv === null || $inv === '') {
                    continue;
                }
                $n = (int) $inv;
                $sum += $n;
                $any = true;
            }
            $out = $any ? $sum : null;
            $memo[$cleanName] = $out;

            return $out;
        };
    }

    /**
     * SQL fragment: per-row spend for one table row (alias), preferring `spend` then `cost`.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function spendCoalesceExprForTableAlias(string $alias, array $dbColumns): ?string
    {
        if (in_array('spend', $dbColumns, true) && in_array('cost', $dbColumns, true)) {
            return 'COALESCE('.$alias.'.spend, '.$alias.'.cost, 0)';
        }
        if (in_array('spend', $dbColumns, true)) {
            return 'COALESCE('.$alias.'.spend, 0)';
        }
        if (in_array('cost', $dbColumns, true)) {
            return 'COALESCE('.$alias.'.cost, 0)';
        }

        return null;
    }

    /**
     * SQL fragment: per-row spend for one table row (alias), preferring `cost` then `spend` (Amazon L30 summary rows
     * may carry both; UI should match stored `cost` when present).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function costPreferCoalesceExprForTableAlias(string $alias, array $dbColumns): ?string
    {
        if (in_array('cost', $dbColumns, true) && in_array('spend', $dbColumns, true)) {
            return 'COALESCE('.$alias.'.cost, '.$alias.'.spend, 0)';
        }
        if (in_array('cost', $dbColumns, true)) {
            return 'COALESCE('.$alias.'.cost, 0)';
        }
        if (in_array('spend', $dbColumns, true)) {
            return 'COALESCE('.$alias.'.spend, 0)';
        }

        return null;
    }

    /**
     * Numeric spend for display from one stored row: prefer `cost`, then `spend` (aligned with {@see costPreferCoalesceExprForTableAlias}).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l30DisplaySpendFromRowArray(array $r, array $dbColumns): ?float
    {
        foreach (['cost', 'spend'] as $k) {
            if (($k === 'cost' && ! in_array('cost', $dbColumns, true)) || ($k === 'spend' && ! in_array('spend', $dbColumns, true))) {
                continue;
            }
            $v = $r[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (is_finite($n)) {
                return $n;
            }
        }

        return null;
    }

    /**
     * L30 summary purchases column: `purchases30d` on SP, `purchases` on SB.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l30SummaryPurchasesDbColumn(array $dbColumns): ?string
    {
        if (in_array('purchases30d', $dbColumns, true)) {
            return 'purchases30d';
        }
        if (in_array('purchases', $dbColumns, true)) {
            return 'purchases';
        }

        return null;
    }

    /**
     * Latest L30 summary purchases (Sold / Prchase overlay).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function correlatedL30SummaryPurchases30dScalarSubquerySql(string $table, array $dbColumns): ?string
    {
        $purchCol = self::l30SummaryPurchasesDbColumn($dbColumns);
        if ($purchCol === null || ! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true) || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $t = str_replace('`', '``', $table);
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $adClause = $hasAdType ? ' AND l30.ad_type <=> `'.$t.'`.ad_type ' : '';

        return 'SELECT l30.`'.$purchCol.'` FROM `'.$t.'` AS l30 WHERE l30.campaign_id = `'.$t.'`.campaign_id'.$adClause
            ." AND UPPER(TRIM(l30.report_date_range)) = 'L30' ORDER BY l30.id DESC LIMIT 1";
    }

    /**
     * Latest L30 summary sales column: `sales30d` on SP/SD, `sales` on SB (`amazon_sb_campaign_reports`).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l30SummarySalesDbColumn(array $dbColumns): ?string
    {
        if (in_array('sales30d', $dbColumns, true)) {
            return 'sales30d';
        }
        if (in_array('sales', $dbColumns, true)) {
            return 'sales';
        }

        return null;
    }

    /**
     * Scalar subquery: sales metric on the latest `report_date_range = L30` row (same as grid SL 30 / `sales30d` overlay).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function correlatedL30SummarySales30dScalarSubquerySql(string $table, array $dbColumns): ?string
    {
        $salesCol = self::l30SummarySalesDbColumn($dbColumns);
        if ($salesCol === null || ! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true) || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $t = str_replace('`', '``', $table);
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $adClause = $hasAdType ? ' AND l30.ad_type <=> `'.$t.'`.ad_type ' : '';

        return 'SELECT l30.`'.$salesCol.'` FROM `'.$t.'` AS l30 WHERE l30.campaign_id = `'.$t.'`.campaign_id'.$adClause
            ." AND UPPER(TRIM(l30.report_date_range)) = 'L30' ORDER BY l30.id DESC LIMIT 1";
    }

    /**
     * Correlated scalar: latest `report_date_range = L30` row for this campaign (+ ad_type), spend prefers `cost` then `spend`.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function correlatedL30SummarySpendScalarSubquerySql(string $table, array $dbColumns): ?string
    {
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true) || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $expr = self::costPreferCoalesceExprForTableAlias('l30', $dbColumns);
        if ($expr === null) {
            return null;
        }
        $t = str_replace('`', '``', $table);
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $adClause = $hasAdType ? ' AND l30.ad_type <=> `'.$t.'`.ad_type ' : '';

        return 'SELECT '.$expr.' FROM `'.$t.'` AS l30 WHERE l30.campaign_id = `'.$t.'`.campaign_id'.$adClause
            ." AND UPPER(TRIM(l30.report_date_range)) = 'L30' ORDER BY l30.id DESC LIMIT 1";
    }

    /**
     * Correlated scalar subquery: sum of daily spend for the outer row's campaign (+ ad_type) over 30 calendar days
     * ending at {@see latestDailyReportYmdInTable} (ISO `report_date_range` prefix rows only).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function correlatedL30DailySpendSumSubquerySql(string $table, array $dbColumns): ?string
    {
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true)) {
            return null;
        }
        $rowExpr = self::spendCoalesceExprForTableAlias('s30', $dbColumns);
        if ($rowExpr === null) {
            return null;
        }
        $anchor = self::latestDailyReportYmdInTable($table);
        if ($anchor === null || $anchor === '') {
            return null;
        }
        try {
            $from = Carbon::parse($anchor, config('app.timezone'))->subDays(29)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
        $t = str_replace('`', '``', $table);
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $adClause = $hasAdType ? ' AND s30.ad_type <=> `'.$t.'`.ad_type ' : '';

        return 'SELECT SUM('.$rowExpr.') FROM `'.$t.'` AS s30 WHERE s30.campaign_id = `'.$t.'`.campaign_id'.$adClause
            .' AND CHAR_LENGTH(TRIM(s30.report_date_range)) >= 10 '
            ."AND LEFT(TRIM(s30.report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' "
            .'AND LEFT(TRIM(s30.report_date_range), 10) BETWEEN \''.$from.'\' AND \''.$anchor.'\'';
    }

    /**
     * Spend column on an SQL alias for L7/L2/L1 slice subqueries — same rule as {@see spendColumnForLRange}.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function lRangeSpendSelectForAlias(string $alias, array $dbColumns): ?string
    {
        $col = self::spendColumnForLRange($dbColumns);
        if ($col === null) {
            return null;
        }

        return $alias.'.'.$col;
    }

    private static function quotedTableIdentifier(string $table): string
    {
        return '`'.str_replace('`', '``', $table).'`';
    }

    /**
     * One row per campaign (+ ad_type): latest L7 summary spend (MAX(id) within L7 rows), same row as {@see fetchL7L2L1SpendMap}.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l7SliceSpendKeyedDerivedSql(string $table, array $dbColumns): ?string
    {
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $spendSel = self::lRangeSpendSelectForAlias('t', $dbColumns);
        if ($spendSel === null) {
            return null;
        }
        $t = self::quotedTableIdentifier($table);
        if (in_array('ad_type', $dbColumns, true)) {
            return 'SELECT t.campaign_id AS u_cid, t.ad_type AS u_ad, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
                .' SELECT campaign_id, ad_type, MAX(id) AS mid FROM '.$t
                ." WHERE UPPER(TRIM(report_date_range)) = 'L7' GROUP BY campaign_id, ad_type"
                .' ) z ON z.mid = t.id';
        }

        return 'SELECT t.campaign_id AS u_cid, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
            .' SELECT campaign_id, MAX(id) AS mid FROM '.$t
            ." WHERE UPPER(TRIM(report_date_range)) = 'L7' GROUP BY campaign_id"
            .' ) z ON z.mid = t.id';
    }

    /**
     * One row per campaign (+ ad_type): latest L1 summary spend.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l1SliceSpendKeyedDerivedSql(string $table, array $dbColumns): ?string
    {
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $spendSel = self::lRangeSpendSelectForAlias('t', $dbColumns);
        if ($spendSel === null) {
            return null;
        }
        $t = self::quotedTableIdentifier($table);
        if (in_array('ad_type', $dbColumns, true)) {
            return 'SELECT t.campaign_id AS u_cid, t.ad_type AS u_ad, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
                .' SELECT campaign_id, ad_type, MAX(id) AS mid FROM '.$t
                ." WHERE UPPER(TRIM(report_date_range)) = 'L1' GROUP BY campaign_id, ad_type"
                .' ) z ON z.mid = t.id';
        }

        return 'SELECT t.campaign_id AS u_cid, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
            .' SELECT campaign_id, MAX(id) AS mid FROM '.$t
            ." WHERE UPPER(TRIM(report_date_range)) = 'L1' GROUP BY campaign_id"
            .' ) z ON z.mid = t.id';
    }

    /**
     * One row per campaign (+ ad_type): L2 spend on {@see l2SpendDailyReportYmd} (latest id that day).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l2SliceSpendKeyedDerivedSql(string $table, array $dbColumns): ?string
    {
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $spendSel = self::lRangeSpendSelectForAlias('t', $dbColumns);
        if ($spendSel === null) {
            return null;
        }
        $l2Day = self::l2SpendDailyReportYmd($table);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $l2Day)) {
            return null;
        }
        $t = self::quotedTableIdentifier($table);
        if (in_array('ad_type', $dbColumns, true)) {
            return 'SELECT t.campaign_id AS u_cid, t.ad_type AS u_ad, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
                .' SELECT campaign_id, ad_type, MAX(id) AS mid FROM '.$t
                .' WHERE CHAR_LENGTH(TRIM(report_date_range)) >= 10 '
                ."AND LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' "
                ."AND LEFT(TRIM(report_date_range), 10) = '".$l2Day."' "
                .'GROUP BY campaign_id, ad_type'
                .' ) z ON z.mid = t.id';
        }

        return 'SELECT t.campaign_id AS u_cid, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
            .' SELECT campaign_id, MAX(id) AS mid FROM '.$t
            .' WHERE CHAR_LENGTH(TRIM(report_date_range)) >= 10 '
            ."AND LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' "
            ."AND LEFT(TRIM(report_date_range), 10) = '".$l2Day."' "
            .'GROUP BY campaign_id'
            .' ) z ON z.mid = t.id';
    }

    /**
     * U% bucket filter using EXISTS + keyed slice derived table (fast on large tables vs correlated scalar subqueries).
     */
    private static function applyUtilizationSliceExistsFilter(
        Builder $query,
        string $table,
        string $keyedSliceDerivedSql,
        int $days,
        ?string $bucket,
        bool $hasAdType
    ): void {
        if ($bucket === null || $keyedSliceDerivedSql === '') {
            return;
        }
        $days = max(1, $days);
        $t = self::quotedTableIdentifier($table);
        $adClause = $hasAdType ? ' AND u_.u_ad <=> '.$t.'.ad_type ' : '';
        $pct = '((u_.u_sp / ('.$t.'.campaignBudgetAmount * '.$days.')) * 100)';
        $base = '('.$t.'.campaignBudgetAmount IS NOT NULL AND '.$t.'.campaignBudgetAmount > 0 AND u_.u_sp IS NOT NULL)';
        $existsHead = 'EXISTS (SELECT 1 FROM ('.$keyedSliceDerivedSql.') AS u_ WHERE u_.u_cid = '.$t.'.campaign_id'.$adClause.' AND '.$base;
        if ($bucket === 'lt66') {
            $query->whereRaw($existsHead.' AND ('.$pct.') < 66)');
        } elseif ($bucket === '66_99') {
            $query->whereRaw($existsHead.' AND ('.$pct.') >= 66 AND ('.$pct.') <= 99)');
        } elseif ($bucket === 'gt99') {
            $query->whereRaw($existsHead.' AND ('.$pct.') > 99)');
        }
    }

    /**
     * Per campaign (+ ad_type): sum of daily spend over 30 calendar days ending at latest daily `report_date_range` in the table.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, float|null>
     */
    private static function fetchL30DailySpendSumMap(string $table, array $dbColumns, iterable $pageRows): array
    {
        $rowExpr = self::spendCoalesceExprForTableAlias('s30', $dbColumns);
        if ($rowExpr === null || ! in_array('report_date_range', $dbColumns, true) || ! in_array('campaign_id', $dbColumns, true)) {
            return [];
        }
        $anchor = self::latestDailyReportYmdInTable($table);
        if ($anchor === null || $anchor === '') {
            return [];
        }
        try {
            $from = Carbon::parse($anchor, config('app.timezone'))->subDays(29)->format('Y-m-d');
        } catch (\Throwable) {
            return [];
        }
        $cids = [];
        foreach ($pageRows as $row) {
            $r = (array) $row;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $q = DB::table($table.' as s30')
            ->whereIn('s30.campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(TRIM(s30.report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(s30.report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->whereRaw('LEFT(TRIM(s30.report_date_range), 10) BETWEEN ? AND ?', [$from, $anchor]);
        if ($hasAdType) {
            $q->selectRaw('s30.campaign_id AS cid, s30.ad_type AS ad, SUM('.$rowExpr.') AS spend_l30')
                ->groupBy('s30.campaign_id', 's30.ad_type');
        } else {
            $q->selectRaw('s30.campaign_id AS cid, SUM('.$rowExpr.') AS spend_l30')
                ->groupBy('s30.campaign_id');
        }
        $map = [];
        foreach ($q->get() as $fr) {
            $cid = isset($fr->cid) ? trim((string) $fr->cid) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($fr->ad ?? '')) : '';
            $key = $cid."\0".$ad;
            $raw = $fr->spend_l30 ?? null;
            if ($raw === null || $raw === '') {
                $map[$key] = null;
            } else {
                $n = (float) $raw;
                $map[$key] = is_finite($n) ? $n : null;
            }
        }

        return $map;
    }

    /**
     * L30 summary purchases on one row: `purchases30d` when present, else `purchases` (SB).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l30Purchases30dFromRowArray(array $r, array $dbColumns): ?int
    {
        $key = self::l30SummaryPurchasesDbColumn($dbColumns);
        if ($key === null) {
            return null;
        }
        $pv = $r[$key] ?? null;
        if ($pv === null || $pv === '') {
            return null;
        }
        $pn = (float) $pv;
        if (! is_finite($pn)) {
            return null;
        }

        return (int) $pn;
    }

    /**
     * L30 summary sales on one row: `sales30d` when present, else `sales` (SB).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l30Sales30dFromRowArray(array $r, array $dbColumns): ?float
    {
        $key = self::l30SummarySalesDbColumn($dbColumns);
        if ($key === null) {
            return null;
        }
        $sv = $r[$key] ?? null;
        if ($sv === null || $sv === '') {
            return null;
        }
        $n = (float) $sv;
        if (! is_finite($n)) {
            return null;
        }

        return $n;
    }

    /**
     * Per campaign (+ ad_type): latest `report_date_range = L30` row — spend (cost then spend), Sold (`purchases30d` or `purchases`), L30 sales (`sales30d` or `sales`).
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, array{spend: ?float, purchases30d: ?int, sales30d: ?float}>
     */
    private static function fetchL30SummarySliceMap(string $table, array $dbColumns, iterable $pageRows): array
    {
        if (! in_array('id', $dbColumns, true) || ! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true)) {
            return [];
        }
        $cids = [];
        foreach ($pageRows as $row) {
            $r = (array) $row;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $sub = DB::table($table)
            ->whereRaw("UPPER(TRIM(report_date_range)) = 'L30'")
            ->whereIn('campaign_id', $cidList);
        if ($hasAdType) {
            $sub->selectRaw('campaign_id AS cid, ad_type AS ad, MAX(id) AS max_id')
                ->groupBy('campaign_id', 'ad_type');
        } else {
            $sub->selectRaw('campaign_id AS cid, MAX(id) AS max_id')
                ->groupBy('campaign_id');
        }
        $maxRows = $sub->get();
        if ($maxRows->isEmpty()) {
            return [];
        }
        $ids = [];
        foreach ($maxRows as $mr) {
            $ids[] = (int) ($mr->max_id ?? 0);
        }
        $ids = array_values(array_filter($ids, static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            return [];
        }
        $fullRows = DB::table($table)->whereIn('id', $ids)->get();
        $map = [];
        foreach ($fullRows as $fr) {
            $r = (array) $fr;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $key = $cid."\0".$ad;
            $spend = self::l30DisplaySpendFromRowArray($r, $dbColumns);
            $spendOut = ($spend !== null && is_finite($spend)) ? $spend : null;
            $map[$key] = [
                'spend' => $spendOut,
                'purchases30d' => self::l30Purchases30dFromRowArray($r, $dbColumns),
                'sales30d' => self::l30Sales30dFromRowArray($r, $dbColumns),
            ];
        }

        return $map;
    }

    /**
     * Per distinct (campaign_id [, ad_type]): L30 SPL30 spend and L30 sales sums for the filtered grid,
     * matching {@see rawData} overlays (no double-count across duplicate report rows).
     *
     * @param  array<int, string>  $dbColumns
     * @param  array<int, string>  $columns   Display column keys
     * @return array{cost_sum: float, sales_sum: float}|null
     */
    private static function aggregateL30CostAndSalesDistinctForFilteredAmazonAdsRows(Builder $filteredBaseQuery, string $table, array $dbColumns, array $columns): ?array
    {
        if ($table !== 'amazon_sp_campaign_reports' && $table !== 'amazon_sb_campaign_reports') {
            return null;
        }
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('cost', $dbColumns, true)) {
            return null;
        }
        $subQ = $filteredBaseQuery->clone()->reorder();
        $hasAd = in_array('ad_type', $dbColumns, true);
        $pairsQ = DB::query()->fromSub($subQ, 'r');
        if ($hasAd) {
            $pairs = $pairsQ->select('r.campaign_id', 'r.ad_type')->distinct()->get();
        } else {
            $pairs = $pairsQ->select('r.campaign_id')->distinct()->get();
        }
        if ($pairs->isEmpty()) {
            return ['cost_sum' => 0.0, 'sales_sum' => 0.0];
        }
        $stubRows = [];
        foreach ($pairs as $p) {
            $o = new \stdClass;
            $o->campaign_id = $p->campaign_id;
            if ($hasAd) {
                $o->ad_type = $p->ad_type ?? null;
            }
            $stubRows[] = $o;
        }
        $needL30ForAcosSbgt = in_array('cost', $columns, true)
            || in_array('ACOS', $columns, true)
            || in_array('sbgt', $columns, true);
        $needL30Slice = ($needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true)))
            || (in_array('Prchase', $columns, true) && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true)))
            || (in_array('sales30d', $columns, true) && (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true)))
            || (($needL30ForAcosSbgt) && in_array('sales30d', $dbColumns, true));
        $l30SliceMap = $needL30Slice ? self::fetchL30SummarySliceMap($table, $dbColumns, $stubRows) : [];
        $l30SpendMap = $needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true))
            ? self::fetchL30DailySpendSumMap($table, $dbColumns, $stubRows)
            : [];
        $rawByKey = [];
        $rawSalesByKey = [];
        $coalesce = self::costPreferCoalesceExprForTableAlias('r', $dbColumns);
        $salesColRaw = self::l30SummarySalesDbColumn($dbColumns);
        $gq = DB::query()->fromSub($filteredBaseQuery->clone()->reorder(), 'r');
        $selectChunks = [];
        if ($hasAd) {
            $selectChunks[] = 'TRIM(r.campaign_id) AS lk_cid';
            $selectChunks[] = 'TRIM(IFNULL(r.ad_type, \'\')) AS lk_ad';
        } else {
            $selectChunks[] = 'TRIM(r.campaign_id) AS lk_cid';
        }
        if ($coalesce !== null) {
            $selectChunks[] = 'MAX('.$coalesce.') AS mx_spend';
        }
        if ($salesColRaw !== null) {
            $selectChunks[] = 'MAX(r.`'.$salesColRaw.'`) AS mx_sales';
        }
        if (count($selectChunks) > ($hasAd ? 2 : 1)) {
            $gq->selectRaw(implode(', ', $selectChunks));
            if ($hasAd) {
                $gq->groupBy('lk_cid', 'lk_ad');
            } else {
                $gq->groupBy('lk_cid');
            }
            foreach ($gq->get() as $rw) {
                $kc = trim((string) ($rw->lk_cid ?? ''));
                if ($kc === '') {
                    continue;
                }
                $ka = $hasAd ? trim((string) ($rw->lk_ad ?? '')) : '';
                $key = $kc."\0".$ka;
                if ($coalesce !== null) {
                    $mx = $rw->mx_spend ?? null;
                    if ($mx === null || $mx === '') {
                        $rawByKey[$key] = null;
                    } else {
                        $n = (float) $mx;
                        $rawByKey[$key] = is_finite($n) ? $n : null;
                    }
                }
                if ($salesColRaw !== null && property_exists($rw, 'mx_sales')) {
                    $ms = $rw->mx_sales ?? null;
                    if ($ms === null || $ms === '') {
                        $rawSalesByKey[$key] = null;
                    } else {
                        $sn = (float) $ms;
                        $rawSalesByKey[$key] = is_finite($sn) ? $sn : null;
                    }
                }
            }
        }
        $costSum = 0.0;
        $salesSum = 0.0;
        foreach ($pairs as $p) {
            $cid = isset($p->campaign_id) ? trim((string) $p->campaign_id) : '';
            if ($cid === '') {
                continue;
            }
            $adTypeStr = $hasAd ? trim((string) ($p->ad_type ?? '')) : '';
            $adKeyL30 = $hasAd ? $adTypeStr : '';
            $lkL30 = $cid."\0".trim((string) $adKeyL30);
            $costVal = null;
            if ($l30SliceMap !== [] && array_key_exists($lkL30, $l30SliceMap) && $l30SliceMap[$lkL30]['spend'] !== null) {
                $sv = (float) $l30SliceMap[$lkL30]['spend'];
                $costVal = is_finite($sv) ? $sv : null;
            } elseif ($l30SpendMap !== [] && array_key_exists($lkL30, $l30SpendMap)) {
                $l30v = $l30SpendMap[$lkL30];
                if ($l30v !== null && is_finite((float) $l30v)) {
                    $costVal = (float) $l30v;
                }
            } elseif (array_key_exists($lkL30, $rawByKey) && $rawByKey[$lkL30] !== null && is_finite((float) $rawByKey[$lkL30])) {
                $costVal = (float) $rawByKey[$lkL30];
            }
            if ($costVal !== null) {
                $costSum += $costVal;
            }
            $salesVal = null;
            if ($l30SliceMap !== [] && array_key_exists($lkL30, $l30SliceMap)) {
                $s30 = $l30SliceMap[$lkL30]['sales30d'];
                if ($s30 !== null && is_finite((float) $s30)) {
                    $salesVal = (float) $s30;
                }
            }
            if ($salesVal === null && array_key_exists($lkL30, $rawSalesByKey) && $rawSalesByKey[$lkL30] !== null && is_finite((float) $rawSalesByKey[$lkL30])) {
                $salesVal = (float) $rawSalesByKey[$lkL30];
            }
            if ($salesVal !== null) {
                $salesSum += $salesVal;
            }
        }

        return ['cost_sum' => $costSum, 'sales_sum' => $salesSum];
    }

    /**
     * Portfolio ACOS (%) from summed L30 cost and sales — same edge cases as {@see computedAcosPercentFromReportRow}.
     */
    private static function overallAcosPercentFromAggregatedSums(float $costSum, float $salesSum): float
    {
        if ($salesSum > 0) {
            $v = ($costSum / $salesSum) * 100;

            return is_finite($v) ? (float) round($v, 0) : 0.0;
        }
        if ($costSum > 0) {
            return 100.0;
        }

        return 0.0;
    }

    /**
     * Sum of SPL30 (`cost` after L30 overlays) for distinct (campaign_id [, ad_type]) in the filtered set,
     * matching per-row logic in {@see rawData} (avoids double-counting duplicate report rows per campaign).
     *
     * @param  array<int, string>  $dbColumns
     * @param  array<int, string>  $columns   Display column keys
     */
    private static function sumSpl30DistinctForFilteredAmazonAdsRows(Builder $filteredBaseQuery, string $table, array $dbColumns, array $columns): ?float
    {
        if (! in_array('cost', $columns, true)) {
            return null;
        }
        $agg = self::aggregateL30CostAndSalesDistinctForFilteredAmazonAdsRows($filteredBaseQuery, $table, $dbColumns, $columns);
        if ($agg === null) {
            return null;
        }

        return round($agg['cost_sum'], 2);
    }

    /**
     * Server-side ORDER BY: display column keys may differ from DB (computed / renamed columns).
     *
     * @param  array<int, string>  $dbColumns
     * @param  array<int, string>  $displayColumns
     */
    private static function applyRawDataOrder(Builder $query, string $table, array $dbColumns, array $displayColumns, int $orderColumnIndex, string $orderDir): void
    {
        $dir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';
        if ($orderColumnIndex < 0 || $orderColumnIndex >= count($displayColumns)) {
            $orderColumnIndex = 0;
        }
        $requested = $displayColumns[$orderColumnIndex];

        if ($requested === 'bgt' && in_array('campaignBudgetAmount', $dbColumns, true)) {
            $query->orderBy('campaignBudgetAmount', $dir === 'ASC' ? 'asc' : 'desc');
        } elseif ($requested === 'Prchase' && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true))) {
            $purchSub = self::correlatedL30SummaryPurchases30dScalarSubquerySql($table, $dbColumns);
            if ($purchSub !== null) {
                if (in_array('purchases30d', $dbColumns, true)) {
                    $query->orderByRaw('COALESCE(('.$purchSub.'), purchases30d) '.$dir);
                } else {
                    $query->orderByRaw('COALESCE(('.$purchSub.'), purchases) '.$dir);
                }
            } elseif (in_array('purchases30d', $dbColumns, true)) {
                $query->orderBy('purchases30d', $dir === 'ASC' ? 'asc' : 'desc');
            } else {
                $query->orderBy('purchases', $dir === 'ASC' ? 'asc' : 'desc');
            }
        } elseif ($requested === 'sales30d' && (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true))) {
            $salesSub = self::correlatedL30SummarySales30dScalarSubquerySql($table, $dbColumns);
            if ($salesSub !== null) {
                if (in_array('sales30d', $dbColumns, true)) {
                    $query->orderByRaw('COALESCE(('.$salesSub.'), sales30d) '.$dir);
                } else {
                    $query->orderByRaw('COALESCE(('.$salesSub.'), sales) '.$dir);
                }
            } elseif (in_array('sales30d', $dbColumns, true)) {
                $query->orderBy('sales30d', $dir === 'ASC' ? 'asc' : 'desc');
            } else {
                $query->orderBy('sales', $dir === 'ASC' ? 'asc' : 'desc');
            }
        } elseif ($requested === 'ACOS') {
            $expr = self::sqlExpressionForAcosSort($table, $dbColumns);
            if ($expr !== null) {
                $query->orderByRaw('('.$expr.') '.$dir);
            } elseif (in_array('id', $dbColumns, true)) {
                $query->orderBy('id', 'desc');
            }
        } elseif ($requested === 'sbgt') {
            $expr = self::sqlExpressionForSbgtSort($table, $dbColumns);
            if ($expr !== null) {
                $query->orderByRaw('('.$expr.') '.$dir);
            } elseif (in_array('id', $dbColumns, true)) {
                $query->orderBy('id', 'desc');
            }
        } elseif ($requested === 'sbid' && in_array('last_sbid', $dbColumns, true) && in_array('sbid', $dbColumns, true)) {
            $query->orderByRaw('COALESCE(last_sbid, sbid, 0) '.$dir);
        } elseif ($requested === 'costPerClick'
            && $table === 'amazon_sb_campaign_reports'
            && ! in_array('costPerClick', $dbColumns, true)
            && in_array('clicks', $dbColumns, true)
            && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true))) {
            $spendExpr = in_array('spend', $dbColumns, true)
                ? 'COALESCE(cost, spend, 0)'
                : 'COALESCE(cost, 0)';
            $query->orderByRaw('CASE WHEN COALESCE(clicks, 0) > 0 THEN '.$spendExpr.' / NULLIF(clicks, 0) ELSE NULL END '.$dir);
        } elseif ($requested === 'cost') {
            $dailySub = self::correlatedL30DailySpendSumSubquerySql($table, $dbColumns);
            $summarySub = self::correlatedL30SummarySpendScalarSubquerySql($table, $dbColumns);
            if ($summarySub !== null && $dailySub !== null) {
                $query->orderByRaw('COALESCE(('.$summarySub.'), ('.$dailySub.')) '.$dir);
            } elseif ($summarySub !== null) {
                $query->orderByRaw('(('.$summarySub.')) '.$dir);
            } elseif ($dailySub !== null) {
                $query->orderByRaw('(('.$dailySub.')) '.$dir);
            } elseif (in_array('cost', $dbColumns, true)) {
                $query->orderBy('cost', $dir === 'ASC' ? 'asc' : 'desc');
            } elseif (in_array('id', $dbColumns, true)) {
                $query->orderBy('id', 'desc');
            }
        } elseif (in_array($requested, $dbColumns, true)) {
            $query->orderBy($requested, $dir === 'ASC' ? 'asc' : 'desc');
        } elseif (in_array('id', $dbColumns, true)) {
            $query->orderBy('id', 'desc');
        } elseif ($dbColumns !== []) {
            $query->orderBy($dbColumns[0], 'desc');
        }

        if ($requested !== 'id' && in_array('id', $dbColumns, true)) {
            $query->orderBy('id', 'desc');
        }
    }

    /**
     * Spend column for L-range rows: prefer `spend`, else `cost`.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function spendColumnForLRange(array $dbColumns): ?string
    {
        if (in_array('spend', $dbColumns, true)) {
            return 'spend';
        }
        if (in_array('cost', $dbColumns, true)) {
            return 'cost';
        }

        return null;
    }

    /**
     * Latest calendar day stored in `report_date_range` (YYYY-MM-DD prefix only).
     */
    private static function latestDailyReportYmdInTable(string $table): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }
        $max = DB::table($table)
            ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max(DB::raw('LEFT(TRIM(report_date_range), 10)'));

        if ($max === null || $max === '') {
            return null;
        }

        return (string) $max;
    }

    /**
     * Calendar day for L2 spend: one day before the newest daily row in the table (same anchor as AutoUpdateAmazonKwBids L2).
     * If there are no daily rows, uses app "today" minus two days like that command's fallback.
     */
    private static function l2SpendDailyReportYmd(string $table): string
    {
        $latest = self::latestDailyReportYmdInTable($table);
        if ($latest !== null && $latest !== '') {
            try {
                return Carbon::parse($latest, config('app.timezone'))->subDay()->format('Y-m-d');
            } catch (\Throwable) {
                // fall through
            }
        }

        return Carbon::now(config('app.timezone'))->subDays(2)->format('Y-m-d');
    }

    /**
     * For each campaign (+ ad_type): L7/L1 from summary rows (report_date_range L7 / L1); L2 from the daily row
     * whose ISO date is the calendar day before the table's latest daily report_date_range (aligned with L1 window in bid jobs).
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, array{L7: float|null, L2: float|null, L1: float|null}>
     */
    private static function fetchL7L2L1SpendMap(string $table, array $dbColumns, iterable $pageRows): array
    {
        $spendCol = self::spendColumnForLRange($dbColumns);
        if ($spendCol === null || ! in_array('report_date_range', $dbColumns, true) || ! in_array('campaign_id', $dbColumns, true)) {
            return [];
        }
        $cids = [];
        foreach ($pageRows as $row) {
            $r = (array) $row;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }

        $hasAdType = in_array('ad_type', $dbColumns, true);
        $select = ['id', 'campaign_id', 'report_date_range', $spendCol];
        if ($hasAdType) {
            $select[] = 'ad_type';
        }

        $map = [];

        $summaryRows = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('UPPER(TRIM(report_date_range)) IN (?, ?)', ['L7', 'L1'])
            ->orderBy('id', 'desc')
            ->get();

        foreach ($summaryRows as $fr) {
            $frArr = (array) $fr;
            $tag = strtoupper(trim((string) ($frArr['report_date_range'] ?? '')));
            if ($tag !== 'L7' && $tag !== 'L1') {
                continue;
            }
            $cid = isset($frArr['campaign_id']) ? trim((string) $frArr['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($frArr['ad_type'] ?? '')) : '';
            $key = $cid."\0".$ad;
            if (! isset($map[$key])) {
                $map[$key] = ['L7' => null, 'L2' => null, 'L1' => null];
            }
            if ($map[$key][$tag] !== null) {
                continue;
            }
            $raw = $frArr[$spendCol] ?? null;
            if ($raw === null || $raw === '') {
                $map[$key][$tag] = null;
            } else {
                $n = (float) $raw;
                $map[$key][$tag] = is_finite($n) ? round($n, 2) : null;
            }
        }

        $l2Day = self::l2SpendDailyReportYmd($table);
        $dailyL2 = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(report_date_range), 10) = ?", [$l2Day])
            ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->orderBy('id', 'desc')
            ->get();

        foreach ($dailyL2 as $fr) {
            $frArr = (array) $fr;
            $cid = isset($frArr['campaign_id']) ? trim((string) $frArr['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($frArr['ad_type'] ?? '')) : '';
            $key = $cid."\0".$ad;
            if (! isset($map[$key])) {
                $map[$key] = ['L7' => null, 'L2' => null, 'L1' => null];
            }
            if ($map[$key]['L2'] !== null) {
                continue;
            }
            $raw = $frArr[$spendCol] ?? null;
            if ($raw === null || $raw === '') {
                $map[$key]['L2'] = null;
            } else {
                $n = (float) $raw;
                $map[$key]['L2'] = is_finite($n) ? round($n, 2) : null;
            }
        }

        return $map;
    }

    /**
     * Latest summary row per campaign with `report_date_range = L1`: cost/spend + clicks (SB CPC1 and L1 cost/click columns).
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, array{cost: float|null, clicks: float|null}>
     */
    private static function fetchL1SummaryClicksCostMap(string $table, array $dbColumns, iterable $pageRows): array
    {
        if (! in_array('report_date_range', $dbColumns, true)
            || ! in_array('campaign_id', $dbColumns, true)
            || ! in_array('clicks', $dbColumns, true)) {
            return [];
        }
        $hasCost = in_array('cost', $dbColumns, true);
        $hasSpend = in_array('spend', $dbColumns, true);
        if (! $hasCost && ! $hasSpend) {
            return [];
        }
        $cids = [];
        foreach ($pageRows as $row) {
            $r = (array) $row;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $select = ['id', 'campaign_id', 'report_date_range', 'clicks'];
        if ($hasCost) {
            $select[] = 'cost';
        }
        if ($hasSpend) {
            $select[] = 'spend';
        }
        if ($hasAdType) {
            $select[] = 'ad_type';
        }
        $map = [];
        $summaryRows = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw("UPPER(TRIM(report_date_range)) = ?", ['L1'])
            ->orderBy('id', 'desc')
            ->get();
        foreach ($summaryRows as $fr) {
            $frArr = (array) $fr;
            $cid = isset($frArr['campaign_id']) ? trim((string) $frArr['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($frArr['ad_type'] ?? '')) : '';
            $key = $cid."\0".$ad;
            if (isset($map[$key])) {
                continue;
            }
            $spendCols = [];
            if ($hasCost) {
                $spendCols[] = 'cost';
            }
            if ($hasSpend) {
                $spendCols[] = 'spend';
            }
            $costVal = null;
            foreach ($spendCols as $col) {
                $v = $frArr[$col] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                $n = (float) $v;
                if (is_finite($n) && $n > 0) {
                    $costVal = $n;
                    break;
                }
            }
            if ($costVal === null) {
                foreach ($spendCols as $col) {
                    $v = $frArr[$col] ?? null;
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $n = (float) $v;
                    if (is_finite($n)) {
                        $costVal = $n;
                        break;
                    }
                }
            }
            $clk = $frArr['clicks'] ?? null;
            $clicksVal = null;
            if ($clk !== null && $clk !== '') {
                $cn = (float) $clk;
                $clicksVal = is_finite($cn) ? $cn : null;
            }
            $map[$key] = [
                'cost' => $costVal,
                'clicks' => $clicksVal,
            ];
        }

        return $map;
    }

    /**
     * Calendar day N days before the "CPC 1" anchor for this row (daily `report_date_range`, summary `L1`, or `date`).
     * N=1 → CPC 2; N=2 → CPC 3 (two days before CPC 1's day).
     * For `report_date_range = L1`, the anchor is the newest ISO daily key in that table ({@see latestDailyReportYmdInTable}), not app "today"
     * (e.g. latest 2026-04-22 → N=1 → 2026-04-21). If there are no daily rows, falls back to yesterday.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function calendarDayOffsetFromCpc1Anchor(array $rowArr, array $dbColumns, string $table, int $daysBefore): ?string
    {
        if ($daysBefore < 1) {
            return null;
        }
        $rdr = isset($rowArr['report_date_range']) ? trim((string) $rowArr['report_date_range']) : '';
        if ($rdr !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $rdr, $m)) {
            try {
                return Carbon::parse($m[1], config('app.timezone'))->subDays($daysBefore)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        if (strtoupper($rdr) === 'L1') {
            $latest = self::latestDailyReportYmdInTable($table);
            if ($latest === null || $latest === '') {
                try {
                    $anchor = Carbon::now(config('app.timezone'))->subDay()->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            } else {
                $anchor = $latest;
            }
            try {
                return Carbon::parse($anchor, config('app.timezone'))->subDays($daysBefore)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        if (in_array('date', $dbColumns, true) && ! empty($rowArr['date'])) {
            try {
                return Carbon::parse($rowArr['date'], config('app.timezone'))->subDays($daysBefore)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * HL-style CPC from a report row: (cost or spend) ÷ clicks, same convention as HL bid tooling.
     *
     * @param  array<string, mixed>  $r
     */
    private static function hlStyleCpcFromReportRowArray(array $r): ?float
    {
        $clicks = $r['clicks'] ?? null;
        if ($clicks === null || $clicks === '') {
            return null;
        }
        $c = (float) $clicks;
        if (! is_finite($c) || $c <= 0) {
            return null;
        }
        $cost = null;
        foreach (['cost', 'spend'] as $k) {
            if (! array_key_exists($k, $r)) {
                continue;
            }
            $v = $r[$k];
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (is_finite($n)) {
                $cost = $n;
                break;
            }
        }
        if ($cost === null || ! is_finite($cost) || $cost <= 0) {
            return null;
        }
        $cpc = $cost / $c;

        return is_finite($cpc) && $cpc > 0 ? round($cpc, 4) : null;
    }

    /**
     * CPC for one calendar `report_date_range` day: `costPerClick` on SP/SD; SB uses {@see hlStyleCpcFromReportRowArray}.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function fetchCostPerClickOnReportDay(
        string $table,
        array $dbColumns,
        string $campaignId,
        ?string $adType,
        string $reportDayYmd,
        array &$cache
    ): ?float {
        $adKey = ($adType !== null && $adType !== '') ? (string) $adType : '-';
        $key = $campaignId."\0".$adKey."\0".$reportDayYmd;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $q = DB::table($table)->where('campaign_id', $campaignId)
            ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(report_date_range), 10) = ?", [$reportDayYmd])
            ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
        if ($adType !== null && $adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $q->where('ad_type', $adType);
        }
        $orderCol = in_array('id', $dbColumns, true) ? 'id' : 'campaign_id';
        $q->orderBy($orderCol, 'desc');

        if (in_array('costPerClick', $dbColumns, true)) {
            $found = $q->first(['costPerClick']);
            $cpc = null;
            if ($found && isset($found->costPerClick)) {
                $n = (float) $found->costPerClick;
                $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
            }
            $cache[$key] = $cpc;

            return $cpc;
        }

        if ($table === 'amazon_sb_campaign_reports'
            && in_array('cost', $dbColumns, true)
            && in_array('clicks', $dbColumns, true)) {
            $select = ['cost', 'clicks'];
            if (in_array('spend', $dbColumns, true)) {
                $select[] = 'spend';
            }
            $found = $q->first($select);
            $cpc = ($found !== null) ? self::hlStyleCpcFromReportRowArray((array) $found) : null;
            $cache[$key] = $cpc;

            return $cpc;
        }

        $cache[$key] = null;

        return null;
    }

    private static function rowSpendForUtilization(array $row): ?float
    {
        foreach (['spend', 'cost'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (is_finite($n)) {
                return $n;
            }
        }

        return null;
    }

    private static function rowBudgetForUtilization(array $row): ?float
    {
        if (! array_key_exists('campaignBudgetAmount', $row)) {
            return null;
        }
        $v = $row['campaignBudgetAmount'];
        if ($v === null || $v === '') {
            return null;
        }
        $n = (float) $v;
        if (! is_finite($n) || $n <= 0) {
            return null;
        }

        return $n;
    }

    /**
     * U7% / U2% / U1% from the same L7 SP, L2 SP, L1 SP values as the grid ({@see fetchL7L2L1SpendMap}).
     * U7 = L7spend / (budget × 7) × 100, U2 = L2spend / (budget × 2) × 100, U1 = L1spend / (budget × 1) × 100.
     *
     * @param  array{L7: float|null, L2: float|null, L1: float|null}  $lSlice
     * @return array{U7: float|null, U2: float|null, U1: float|null}
     */
    private static function utilizationPercentValuesFromLSlice(array $rowForBudget, array $lSlice): array
    {
        $budget = self::rowBudgetForUtilization($rowForBudget);
        if ($budget === null || ! is_finite($budget) || $budget <= 0) {
            return ['U7' => null, 'U2' => null, 'U1' => null];
        }

        $l7 = $lSlice['L7'] ?? null;
        $l2 = $lSlice['L2'] ?? null;
        $l1 = $lSlice['L1'] ?? null;

        $one = static function (mixed $v, float $budget, int $days): ?float {
            if ($v === null || $v === '') {
                return null;
            }
            $n = (float) $v;
            if (! is_finite($n)) {
                return null;
            }
            $d = max(1, $days);
            $x = ($n / ($budget * $d)) * 100;

            return is_finite($x) ? $x : null;
        };

        return [
            'U7' => $one($l7, $budget, 7),
            'U2' => $one($l2, $budget, 2),
            'U1' => $one($l1, $budget, 1),
        ];
    }

    /**
     * Rounded whole-number percent for JSON (null when not computable). Display colors are applied in the blade.
     */
    private static function formatUtilPercent(?float $pct): ?int
    {
        if ($pct === null || ! is_finite($pct)) {
            return null;
        }

        return (int) round($pct);
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function rowPositiveFloatFromKeys(array $row, array $keys): float
    {
        foreach ($keys as $k) {
            if (! array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (is_finite($n) && $n > 0) {
                return $n;
            }
        }

        return 0.0;
    }

    /**
     * Grid SBID from U2%/U1% + L1/L2/L7 CPC (or CPC1 / `costPerClick` fallback), aligned with auto-update commands.
     * Outside red+red / pink+pink bands, sbid is forced to null so the UI shows "--".
     */
    private static function applyGridSbidFromUb2Ub1AndCpc(array &$arr, array $u, array $rowArr, array $dbColumns, string $table): void
    {
        if (! in_array('sbid', $dbColumns, true)) {
            return;
        }
        $u2 = $u['U2'];
        $u1 = $u['U1'];
        if ($u2 === null || $u1 === null) {
            $arr['sbid'] = null;

            return;
        }

        $l1 = self::rowPositiveFloatFromKeys($rowArr, ['l1_cpc', 'L1_cpc', 'l1Cpc']);
        $l2 = self::rowPositiveFloatFromKeys($rowArr, ['l2_cpc', 'L2_cpc', 'l2Cpc']);
        $l7 = self::rowPositiveFloatFromKeys($rowArr, ['l7_cpc', 'L7_cpc', 'l7Cpc']);
        $cpcFb = self::rowPositiveFloatFromKeys($rowArr, ['costPerClick']);
        if ($cpcFb <= 0 && isset($arr['costPerClick']) && $arr['costPerClick'] !== null && $arr['costPerClick'] !== '') {
            $cpcFb = self::rowPositiveFloatFromKeys($arr, ['costPerClick']);
        }
        if ($cpcFb <= 0 && $table === 'amazon_sb_campaign_reports' && in_array('clicks', $dbColumns, true)
            && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true))) {
            $hl = self::hlStyleCpcFromReportRowArray($rowArr);
            $cpcFb = ($hl !== null && $hl > 0) ? $hl : 0.0;
        }
        $fallback = $cpcFb > 0 ? $cpcFb : null;

        $out = AmazonBidUtilizationService::sbidFromUb2Ub1Cpc(
            (float) $u2,
            (float) $u1,
            $l1,
            $l2,
            $l7,
            $fallback
        );

        $arr['sbid'] = $out['sbid'];
    }

    private static function normalizeDateInput(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Normalize stored summary keys like L30, L7, L1 (report_date_range column).
     */
    private static function normalizeSummaryReportRange(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim($value);
        if ($v === '' || strlen($v) > 64) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $v)) {
            return null;
        }

        return $v;
    }

    private static function yesterdayDateString(): string
    {
        return Carbon::now(config('app.timezone'))->subDay()->format('Y-m-d');
    }

    /**
     * Restrict to rows whose `report_date_range` is a calendar day (YYYY-MM-DD prefix), not L7/L30/L1 labels.
     */
    private static function whereReportDateRangeDailyYmdInRange(Builder $query, ?string $from, ?string $to): void
    {
        if ($from === null && $to === null) {
            return;
        }
        $query->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
        if ($from !== null) {
            $query->whereRaw('LEFT(TRIM(report_date_range), 10) >= ?', [$from]);
        }
        if ($to !== null) {
            $query->whereRaw('LEFT(TRIM(report_date_range), 10) <= ?', [$to]);
        }
    }

    /**
     * Calendar overlap on campaign report tables: daily `report_date_range` prefix, optional `date` column,
     * or summary `startDate`/`endDate` vs inclusive bounds.
     *
     * @param  bool  $includeSummaryRowOverlap  When false, omit overlap via startDate/endDate for short labels (L7, …).
     */
    private static function applyReportRangeCalendarOverlap(Builder $query, array $cols, ?string $from, ?string $to, bool $includeSummaryRowOverlap = true): void
    {
        if ($from === null && $to === null) {
            return;
        }
        if (! in_array('report_date_range', $cols, true)) {
            return;
        }

        $fromBound = $from ?? '1970-01-01';
        $toBound = $to ?? '9999-12-31';

        $query->where(function (Builder $outer) use ($from, $to, $fromBound, $toBound, $cols, $includeSummaryRowOverlap) {
            // 1) Daily rows: report_date_range begins with YYYY-MM-DD (10 chars)
            $outer->where(function (Builder $q) use ($from, $to) {
                $q->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
                    ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                if ($from !== null) {
                    $q->whereRaw('LEFT(TRIM(report_date_range), 10) >= ?', [$from]);
                }
                if ($to !== null) {
                    $q->whereRaw('LEFT(TRIM(report_date_range), 10) <= ?', [$to]);
                }
            });

            if (in_array('date', $cols, true)) {
                $outer->orWhere(function (Builder $q) use ($from, $to) {
                    if ($from !== null) {
                        $q->whereDate('date', '>=', $from);
                    }
                    if ($to !== null) {
                        $q->whereDate('date', '<=', $to);
                    }
                });
            }

            if ($includeSummaryRowOverlap && in_array('startDate', $cols, true) && in_array('endDate', $cols, true)) {
                $outer->orWhere(function (Builder $q) use ($fromBound, $toBound) {
                    $q->whereRaw('(CHAR_LENGTH(TRIM(report_date_range)) < 10 OR LEFT(TRIM(report_date_range), 10) NOT REGEXP ?)', ['^[0-9]{4}-[0-9]{2}-[0-9]{2}$'])
                        ->whereNotNull('startDate')
                        ->whereNotNull('endDate')
                        ->whereDate('startDate', '<=', $toBound)
                        ->whereDate('endDate', '>=', $fromBound);
                });
            }
        });
    }

    /**
     * Campaign report tables: calendar filters (— below —) match only `report_date_range` values whose prefix is YYYY-MM-DD
     * (excludes L7, L30, L1, …). Explicit Report range L7/L30 still uses `WHERE report_date_range = …`.
     * L1 preset uses yesterday on that same ISO-date rule. Tables without `report_date_range`: filter on `date` / `created_at`.
     */
    private static function applyDateFilters(Builder $query, string $table, Request $request): void
    {
        $cols = Schema::getColumnListing($table);
        $hasReportRange = in_array('report_date_range', $cols, true);

        $summaryRange = self::normalizeSummaryReportRange($request->input('summary_report_range'));
        $from = self::normalizeDateInput((string) $request->input('date_from'));
        $to = self::normalizeDateInput((string) $request->input('date_to'));

        if (! $hasReportRange) {
            $dateCol = null;
            if (in_array('date', $cols, true)) {
                $dateCol = 'date';
            } elseif (in_array('created_at', $cols, true)) {
                $dateCol = 'created_at';
            }
            if ($dateCol === null) {
                return;
            }
            if ($from !== null) {
                $query->whereDate($dateCol, '>=', $from);
            }
            if ($to !== null) {
                $query->whereDate($dateCol, '<=', $to);
            }

            return;
        }

        // L1 = yesterday only (not WHERE report_date_range = 'L1'). Intersect with manual dates when both are used.
        if ($summaryRange === 'L1') {
            $yesterday = self::yesterdayDateString();
            $effFrom = $yesterday;
            $effTo = $yesterday;
            if ($from !== null) {
                $effFrom = max($effFrom, $from);
            }
            if ($to !== null) {
                $effTo = min($effTo, $to);
            }
            if ($effFrom > $effTo) {
                $query->whereRaw('1 = 0');

                return;
            }
            self::whereReportDateRangeDailyYmdInRange($query, $effFrom, $effTo);

            return;
        }

        // L7, L14, L30, …: stored label, optionally narrowed by Date from / Date to
        if ($summaryRange !== null) {
            $query->where('report_date_range', $summaryRange);
            self::applyReportRangeCalendarOverlap($query, $cols, $from, $to, true);

            return;
        }

        if ($from === null && $to === null) {
            return;
        }
        // Calendar mode: only rows where `report_date_range` is an ISO date (exclude L7, L30, L1, …).
        self::whereReportDateRangeDailyYmdInRange($query, $from, $to);
    }

    /**
     * Outer-row spend expression for legacy U% filters only — prefer {@see spendColumnForLRange} (not COALESCE of both).
     *
     * @param  array<int, string>  $cols
     */
    private static function sqlSpendExpressionForUtilFilters(array $cols): ?string
    {
        $col = self::spendColumnForLRange($cols);

        return $col;
    }

    private static function normalizeUtilRangeBucket(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }

        return in_array($v, ['lt66', '66_99', 'gt99'], true) ? $v : null;
    }

    private static function applyOneUtilizationPercentRangeFilter(Builder $query, string $spendExpr, int $days, ?string $bucket): void
    {
        if ($bucket === null) {
            return;
        }
        $days = max(1, $days);
        $pct = "(({$spendExpr}) / (campaignBudgetAmount * {$days})) * 100";
        $base = "(campaignBudgetAmount IS NOT NULL AND campaignBudgetAmount > 0 AND ({$spendExpr}) IS NOT NULL)";
        if ($bucket === 'lt66') {
            $query->whereRaw("({$base} AND ({$pct}) < 66)");
        } elseif ($bucket === '66_99') {
            $query->whereRaw("({$base} AND ({$pct}) >= 66 AND ({$pct}) <= 99)");
        } elseif ($bucket === 'gt99') {
            $query->whereRaw("({$base} AND ({$pct}) > 99)");
        }
    }

    /**
     * Server-side filters for U7%/U2%/U1% buckets (SP/SB/SD campaign tables only).
     * Prefer L7 / L2-daily / L1 slice spend (same sources as the grid {@see fetchL7L2L1SpendMap}), applied with
     * EXISTS + derived tables so COUNT/DataTables queries stay fast on large tables.
     * Falls back to the outer row's spend only when slice SQL cannot be built.
     *
     * @param  bool  $includeU7Filter  When false, U7% filter is ignored (e.g. U7 distribution chart while a U7 bucket is selected).
     */
    private static function applyUtilizationPercentRangeFilters(Builder $query, string $table, Request $request, bool $includeU7Filter = true): void
    {
        $cols = Schema::getColumnListing($table);
        if (! in_array('campaignBudgetAmount', $cols, true)) {
            return;
        }

        $u7 = $includeU7Filter ? self::normalizeUtilRangeBucket($request->input('filter_u7')) : null;
        $u2 = self::normalizeUtilRangeBucket($request->input('filter_u2'));
        $u1 = self::normalizeUtilRangeBucket($request->input('filter_u1'));
        if ($u7 === null && $u2 === null && $u1 === null) {
            return;
        }

        $l7Sql = self::l7SliceSpendKeyedDerivedSql($table, $cols);
        $l2Sql = self::l2SliceSpendKeyedDerivedSql($table, $cols);
        $l1Sql = self::l1SliceSpendKeyedDerivedSql($table, $cols);
        if ($l7Sql !== null && $l2Sql !== null && $l1Sql !== null) {
            $hasAd = in_array('ad_type', $cols, true);
            self::applyUtilizationSliceExistsFilter($query, $table, $l7Sql, 7, $u7, $hasAd);
            self::applyUtilizationSliceExistsFilter($query, $table, $l2Sql, 2, $u2, $hasAd);
            self::applyUtilizationSliceExistsFilter($query, $table, $l1Sql, 1, $u1, $hasAd);

            return;
        }

        if (! in_array('ad_type', $cols, true)) {
            return;
        }
        $spendExpr = self::sqlSpendExpressionForUtilFilters($cols);
        if ($spendExpr === null) {
            return;
        }
        self::applyOneUtilizationPercentRangeFilter($query, $spendExpr, 7, $u7);
        self::applyOneUtilizationPercentRangeFilter($query, $spendExpr, 2, $u2);
        self::applyOneUtilizationPercentRangeFilter($query, $spendExpr, 1, $u1);
    }

    private static function normalizeCampaignStatusFilter(?string $raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        $v = strtoupper(trim((string) $raw));

        return in_array($v, ['ENABLED', 'PAUSED', 'ARCHIVED'], true) ? $v : null;
    }

    /**
     * Filter by campaignStatus when the column exists (SP/SB/SD reports). "All" = no constraint.
     */
    private static function applyCampaignStatusFilter(Builder $query, string $table, Request $request): void
    {
        $cols = Schema::getColumnListing($table);
        if (! in_array('campaignStatus', $cols, true)) {
            return;
        }
        $status = self::normalizeCampaignStatusFilter($request->input('filter_campaign_status'));
        if ($status === null) {
            return;
        }
        $query->where('campaignStatus', $status);
    }

    /**
     * Latest calendar day for default Date from / Date to (single-day window).
     * When `report_date_range` exists, uses only ISO date prefixes in that column (never L7/L30/L1 labels).
     * Other tables: MAX(`date`) or MAX(DATE(created_at)). Capped at today (app timezone).
     */
    private static function latestAvailableReportDayYmd(string $table): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }
        $cols = Schema::getColumnListing($table);
        $best = null;
        try {
            if (in_array('report_date_range', $cols, true)) {
                $row = DB::table($table)
                    ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
                    ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
                    ->selectRaw('MAX(LEFT(TRIM(report_date_range), 10)) AS d')
                    ->first();
                $d = $row->d ?? null;
                if (is_string($d) && $d !== '') {
                    $s = substr($d, 0, 10);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                        $best = $s;
                    }
                }

                return self::clampReportDayToTodayOrNull($best);
            }
            if (in_array('date', $cols, true)) {
                $v = DB::table($table)->whereNotNull('date')->selectRaw('MAX(DATE(`date`)) AS d')->value('d');
                if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    $best = $best === null ? $v : max($best, $v);
                }
            }
            if (in_array('created_at', $cols, true)) {
                $v = DB::table($table)->whereNotNull('created_at')->selectRaw('MAX(DATE(created_at)) AS d')->value('d');
                if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    $best = $best === null ? $v : max($best, $v);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return self::clampReportDayToTodayOrNull($best);
    }

    private static function clampReportDayToTodayOrNull(?string $day): ?string
    {
        if ($day === null || $day === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }
        $today = Carbon::now(config('app.timezone'))->format('Y-m-d');

        return $day > $today ? $today : $day;
    }

    public function index()
    {
        $rawSources = [];
        $defaultReportRangeDates = [];
        foreach (self::RAW_TABLE_SOURCES as $param => $table) {
            $rawSources[$param] = [
                'table' => $table,
                'columns' => self::displayColumnsForTable($table),
            ];
            $defaultReportRangeDates[$param] = self::latestAvailableReportDayYmd($table);
        }

        return view('amazon_ads.all', [
            'rawSources' => $rawSources,
            'defaultReportRangeDates' => $defaultReportRangeDates,
            'amazonAdsBgtRule' => AmazonAcosSbgtRule::resolvedRule(),
            'amazonAdsSbidRule' => AmazonAdsSbidRule::resolvedRule(),
        ]);
    }

    /**
     * Current ACOS → SBGT rule (for BGT RULE modal and client-side tier colors).
     */
    public function getBgtRule(): JsonResponse
    {
        return response()->json([
            'rule' => AmazonAcosSbgtRule::resolvedRule(),
        ]);
    }

    /**
     * Persist ACOS boundary / SBGT tier rule; clears rule cache so grids and pushes use the new mapping.
     */
    public function saveBgtRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAcosSbgtRule::normalizeRule($request->all());
            AmazonAcosSbgtRule::persistRule($normalized);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'BGT rule saved. SBGT on the grid will use the new ACOS → tier mapping after reload.',
            'rule' => AmazonAcosSbgtRule::resolvedRule(),
            'status' => 200,
        ]);
    }

    /**
     * Current U2%/U1% → SBID rule (Amazon Ads SBID RULE modal).
     */
    public function getSbidRule(): JsonResponse
    {
        return response()->json([
            'rule' => AmazonAdsSbidRule::resolvedRule(),
        ]);
    }

    /**
     * Persist SBID utilization thresholds and CPC multipliers; clears cache so grid and bid jobs use the new rule.
     */
    public function saveSbidRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAdsSbidRule::normalizeRule($request->all());
            AmazonAdsSbidRule::persistRule($normalized);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save SBID rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'SBID rule saved. Suggested SBID on the grid and in bid updates will use the new thresholds after reload.',
            'rule' => AmazonAdsSbidRule::resolvedRule(),
            'status' => 200,
        ]);
    }

    /**
     * SQL CASE for U7% bucket labels (must match grid / {@see utilizationPercentValuesFromLSlice} thresholds 66 / 99).
     */
    private static function sqlU7BucketCaseExpression(): string
    {
        return 'CASE WHEN base_m.campaignBudgetAmount IS NULL OR base_m.campaignBudgetAmount <= 0 OR u7s.u_sp IS NULL THEN \'na\' '
            .'WHEN (u7s.u_sp / (base_m.campaignBudgetAmount * 7) * 100) < 66 THEN \'lt66\' '
            .'WHEN (u7s.u_sp / (base_m.campaignBudgetAmount * 7) * 100) <= 99 THEN \'66_99\' '
            .'ELSE \'gt99\' END';
    }

    /**
     * @return array{buckets: array{lt66: int, 66_99: int, gt99: int, na: int}, total: int}
     */
    private static function aggregateU7BucketsForFilteredRows(
        string $table,
        Request $request,
        array $dbColumns,
        string $l7Sql,
        bool $hasAd
    ): array {
        $inner = DB::table($table);
        self::applyDateFilters($inner, $table, $request);
        self::applyUtilizationPercentRangeFilters($inner, $table, $request, false);
        self::applyCampaignStatusFilter($inner, $table, $request);

        $bucketExpr = self::sqlU7BucketCaseExpression();

        $outer = DB::query()->fromSub($inner, 'base_m');
        $outer->leftJoin(DB::raw('('.$l7Sql.') AS u7s'), function ($join) use ($hasAd) {
            $join->on('u7s.u_cid', '=', 'base_m.campaign_id');
            if ($hasAd) {
                $join->whereRaw('u7s.u_ad <=> base_m.ad_type');
            }
        });

        $rows = $outer->selectRaw($bucketExpr.' AS bucket, COUNT(*) AS cnt')
            ->groupBy(DB::raw($bucketExpr))
            ->get();

        $buckets = ['lt66' => 0, '66_99' => 0, 'gt99' => 0, 'na' => 0];
        $total = 0;
        foreach ($rows as $row) {
            $k = (string) ($row->bucket ?? '');
            $c = (int) ($row->cnt ?? 0);
            $total += $c;
            if (array_key_exists($k, $buckets)) {
                $buckets[$k] = $c;
            } else {
                $buckets['na'] += $c;
            }
        }

        return ['buckets' => $buckets, 'total' => $total];
    }

    /**
     * Row counts by U7% band for the current filters (same as the grid except the U7% filter is ignored so the
     * chart still shows a mix when a U7 bucket is selected). SP/SB/SD campaign tables only when L7 slice SQL exists.
     */
    public function u7Distribution(Request $request, string $source): JsonResponse
    {
        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            return response()->json(['ok' => false, 'message' => 'Unknown source'], 404);
        }

        $table = self::RAW_TABLE_SOURCES[$source];
        $empty = [
            'ok' => false,
            'buckets' => ['lt66' => 0, '66_99' => 0, 'gt99' => 0, 'na' => 0],
            'total' => 0,
        ];

        if (! Schema::hasTable($table)) {
            return response()->json($empty + ['reason' => 'missing_table']);
        }

        $dbColumns = Schema::getColumnListing($table);
        if (! in_array('campaignBudgetAmount', $dbColumns, true)) {
            return response()->json($empty + ['reason' => 'no_budget']);
        }

        $l7Sql = self::l7SliceSpendKeyedDerivedSql($table, $dbColumns);
        if ($l7Sql === null) {
            return response()->json($empty + ['reason' => 'no_l7_slice']);
        }

        $hasAd = in_array('ad_type', $dbColumns, true);

        try {
            $out = self::aggregateU7BucketsForFilteredRows($table, $request, $dbColumns, $l7Sql, $hasAd);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'buckets' => ['lt66' => 0, '66_99' => 0, 'gt99' => 0, 'na' => 0],
                'total' => 0,
                'reason' => 'query_error',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'buckets' => $out['buckets'],
            'total' => $out['total'],
            'source' => $source,
        ]);
    }

    /**
     * Per-calendar-day U7% bucket row counts for the last N days (default 30), one day at a time.
     * Uses the same U7/L7 join as the pie; respects U2/U1/Status filters; ignores the grid date range and U7 filter,
     * and uses calendar daily rows only via {@see applyDateFilters} for each single day.
     */
    public function u7DistributionHistory(Request $request, string $source): JsonResponse
    {
        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            return response()->json(['ok' => false, 'message' => 'Unknown source'], 404);
        }

        $table = self::RAW_TABLE_SOURCES[$source];
        if (! Schema::hasTable($table)) {
            return response()->json(['ok' => false, 'days' => [], 'reason' => 'missing_table']);
        }

        $dbColumns = Schema::getColumnListing($table);
        if (! in_array('campaignBudgetAmount', $dbColumns, true)) {
            return response()->json(['ok' => false, 'days' => [], 'reason' => 'no_budget']);
        }

        $l7Sql = self::l7SliceSpendKeyedDerivedSql($table, $dbColumns);
        if ($l7Sql === null) {
            return response()->json(['ok' => false, 'days' => [], 'reason' => 'no_l7_slice']);
        }

        $hasAd = in_array('ad_type', $dbColumns, true);
        $days = (int) $request->input('days', 30);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 90) {
            $days = 90;
        }

        $tz = config('app.timezone');
        $daysOut = [];
        $bucketKey = self::normalizeU7HistoryBucketKey($request->input('bucket'));

        try {
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = Carbon::now($tz)->subDays($i)->format('Y-m-d');
                $sub = new Request(array_merge($request->all(), [
                    'date_from' => $d,
                    'date_to' => $d,
                    'summary_report_range' => '',
                    'filter_u7' => '',
                ]));
                $agg = self::aggregateU7BucketsForFilteredRows($table, $sub, $dbColumns, $l7Sql, $hasAd);
                $row = ['date' => $d, 'lt66' => $agg['buckets']['lt66'], '66_99' => $agg['buckets']['66_99'], 'gt99' => $agg['buckets']['gt99'], 'na' => $agg['buckets']['na'], 'total' => $agg['total']];
                if ($bucketKey !== null) {
                    $row['selected'] = $agg['buckets'][$bucketKey] ?? 0;
                }
                $daysOut[] = $row;
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'days' => [],
                'reason' => 'query_error',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'days' => $daysOut,
            'days_count' => $days,
            'bucket' => $bucketKey,
            'source' => $source,
        ]);
    }

    private static function normalizeU7HistoryBucketKey(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $k = trim((string) $raw);

        return in_array($k, ['lt66', '66_99', 'gt99', 'na'], true) ? $k : null;
    }

    /**
     * Server-side JSON for DataTables (GET). Source must match RAW_TABLE_SOURCES key.
     */
    public function rawData(Request $request, string $source)
    {
        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            abort(404);
        }

        $table = self::RAW_TABLE_SOURCES[$source];

        if (! Schema::hasTable($table)) {
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Table does not exist: '.$table,
            ]);
        }

        $dbColumns = self::orderedColumnsForTable($table);
        $columns = self::displayColumnsForTable($table);
        if ($columns === [] || $dbColumns === []) {
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $draw = (int) $request->input('draw', 1);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        $length = min($length, 500);

        $search = trim((string) $request->input('search.value', ''));

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        // Newest → oldest by default (id desc when id exists and first column).
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $recordsTotal = (int) DB::table($table)->count();

        $query = DB::table($table);
        self::applyDateFilters($query, $table, $request);
        self::applyUtilizationPercentRangeFilters($query, $table, $request, true);
        self::applyCampaignStatusFilter($query, $table, $request);

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $term = '%'.$escaped.'%';
            $query->where(function ($q) use ($dbColumns, $term) {
                foreach ($dbColumns as $col) {
                    $q->orWhere($col, 'LIKE', $term);
                }
            });
        }

        $recordsFiltered = (int) $query->clone()->count();

        $queryForAggregates = $query->clone();

        $distinctCampaignCount = null;
        if (in_array('campaign_id', $dbColumns, true)) {
            $distinctCampaignCount = (int) DB::query()
                ->fromSub($queryForAggregates->clone(), 'r')
                ->selectRaw('COUNT(DISTINCT r.campaign_id) AS c')
                ->value('c');
        }

        $l30AggDistinct = null;
        if (($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports')
            && in_array('campaign_id', $dbColumns, true)
            && in_array('cost', $dbColumns, true)) {
            $l30AggDistinct = self::aggregateL30CostAndSalesDistinctForFilteredAmazonAdsRows($queryForAggregates, $table, $dbColumns, $columns);
        }

        $spl30Total = null;
        if (in_array('cost', $columns, true) && $l30AggDistinct !== null) {
            $spl30Total = round($l30AggDistinct['cost_sum'], 2);
        }

        $overallAcosPercent = null;
        $hasSalesDbForAgg = in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true);
        if ($l30AggDistinct !== null && $hasSalesDbForAgg) {
            $overallAcosPercent = self::overallAcosPercentFromAggregatedSums($l30AggDistinct['cost_sum'], $l30AggDistinct['sales_sum']);
        }

        self::applyRawDataOrder($query, $table, $dbColumns, $columns, $orderColumnIndex, $orderDir);

        $rows = $query->offset($start)
            ->limit($length)
            ->get();

        $hasLSpendCols = in_array('L7spend', $columns, true);
        $hasUtilCols = in_array('U7%', $columns, true);
        $needLSpendMap = $hasLSpendCols || $hasUtilCols;
        $lSpendMap = $needLSpendMap ? self::fetchL7L2L1SpendMap($table, $dbColumns, $rows) : [];
        $needL30ForAcosSbgt = in_array('cost', $columns, true)
            || in_array('ACOS', $columns, true)
            || in_array('sbgt', $columns, true);
        $l30SpendMap = $needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true))
            ? self::fetchL30DailySpendSumMap($table, $dbColumns, $rows)
            : [];
        $needL30Slice = ($needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true)))
            || (in_array('Prchase', $columns, true) && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true)))
            || (in_array('sales30d', $columns, true) && (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true)))
            || (($needL30ForAcosSbgt) && in_array('sales30d', $dbColumns, true));
        $l30SliceMap = $needL30Slice ? self::fetchL30SummarySliceMap($table, $dbColumns, $rows) : [];

        $hasCpc2 = in_array('CPC2', $columns, true);
        $hasCpc3 = in_array('CPC3', $columns, true);
        $needSbInv = $table === 'amazon_sb_campaign_reports' && in_array('INV', $columns, true);
        $sbInvByName = $needSbInv ? self::buildSbInvByCampaignNameResolver() : null;
        $sbHasSpendOrCost = in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true);
        $needSbL1Cpc = $table === 'amazon_sb_campaign_reports'
            && in_array('clicks', $dbColumns, true)
            && $sbHasSpendOrCost
            && (
                in_array('L1cost', $columns, true)
                || in_array('L1clicks', $columns, true)
                || (in_array('costPerClick', $columns, true) && ! in_array('costPerClick', $dbColumns, true))
            );
        $l1ClicksCostMap = $needSbL1Cpc ? self::fetchL1SummaryClicksCostMap($table, $dbColumns, $rows) : [];
        $empty = array_fill_keys($columns, null);
        $cpcDayCache = [];
        $data = [];
        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $arr = array_merge($empty, $rowArr);
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            $adType = in_array('ad_type', $dbColumns, true) ? ($rowArr['ad_type'] ?? null) : null;
            $adTypeStr = is_string($adType) ? $adType : null;
            $lkSalesRow = '';
            if ($cid !== '') {
                $adKeySales0 = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lkSalesRow = $cid."\0".trim((string) $adKeySales0);
            }
            if ($needSbL1Cpc && $cid !== '') {
                $adKeyL1 = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lkL1 = $cid."\0".trim((string) $adKeyL1);
                $l1m = $l1ClicksCostMap[$lkL1] ?? null;
                if (in_array('L1cost', $columns, true)) {
                    $arr['L1cost'] = ($l1m !== null && $l1m['cost'] !== null && is_finite((float) $l1m['cost']))
                        ? round((float) $l1m['cost'], 2)
                        : null;
                }
                if (in_array('L1clicks', $columns, true)) {
                    $arr['L1clicks'] = ($l1m !== null && $l1m['clicks'] !== null && is_finite((float) $l1m['clicks']))
                        ? (int) round((float) $l1m['clicks'])
                        : null;
                }
                if (in_array('costPerClick', $columns, true) && ! in_array('costPerClick', $dbColumns, true)) {
                    if ($l1m !== null) {
                        $pseudo = ['clicks' => $l1m['clicks']];
                        if ($l1m['cost'] !== null && is_finite((float) $l1m['cost'])) {
                            $pseudo['cost'] = (float) $l1m['cost'];
                        }
                        $arr['costPerClick'] = self::hlStyleCpcFromReportRowArray($pseudo);
                    } else {
                        $arr['costPerClick'] = null;
                    }
                }
            }
            $l30SalesFromMap = null;
            if ($l30SliceMap !== [] && $lkSalesRow !== '' && array_key_exists($lkSalesRow, $l30SliceMap)) {
                $l30SalesFromMap = $l30SliceMap[$lkSalesRow]['sales30d'];
            }
            $lSlice = ['L7' => null, 'L2' => null, 'L1' => null];
            if ($lSpendMap !== [] && $cid !== '') {
                $adKeyUtil = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lkUtil = $cid."\0".trim((string) $adKeyUtil);
                $lSlice = $lSpendMap[$lkUtil] ?? $lSlice;
            }
            $u = self::utilizationPercentValuesFromLSlice($rowArr, $lSlice);
            if ($hasUtilCols) {
                $arr['U7%'] = self::formatUtilPercent($u['U7']);
                $arr['U2%'] = self::formatUtilPercent($u['U2']);
                $arr['U1%'] = self::formatUtilPercent($u['U1']);
            }
            if ($hasCpc3) {
                $day3 = self::calendarDayOffsetFromCpc1Anchor($rowArr, $dbColumns, $table, 2);
                if ($day3 !== null && $cid !== '') {
                    $arr['CPC3'] = self::fetchCostPerClickOnReportDay($table, $dbColumns, $cid, $adTypeStr, $day3, $cpcDayCache);
                } else {
                    $arr['CPC3'] = null;
                }
            }
            if ($hasCpc2) {
                $day2 = self::calendarDayOffsetFromCpc1Anchor($rowArr, $dbColumns, $table, 1);
                if ($day2 !== null && $cid !== '') {
                    $arr['CPC2'] = self::fetchCostPerClickOnReportDay($table, $dbColumns, $cid, $adTypeStr, $day2, $cpcDayCache);
                } else {
                    $arr['CPC2'] = null;
                }
            }
            self::applyGridSbidFromUb2Ub1AndCpc($arr, $u, $rowArr, $dbColumns, $table);
            if (in_array('bgt', $columns, true)) {
                $bgtVal = $rowArr['campaignBudgetAmount'] ?? null;
                if ($bgtVal === null || $bgtVal === '') {
                    $arr['bgt'] = null;
                } else {
                    $bn = (float) $bgtVal;
                    $arr['bgt'] = is_finite($bn) ? $bn : null;
                }
                unset($arr['campaignBudgetAmount']);
            }
            if ($sbInvByName !== null) {
                $cnInv = $rowArr['campaignName'] ?? null;
                $arr['INV'] = $sbInvByName(is_string($cnInv) ? $cnInv : null);
            }
            if (in_array('Prchase', $columns, true)
                && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true))) {
                $lkPr = $cid !== '' && in_array('ad_type', $dbColumns, true)
                    ? $cid."\0".trim((string) ($adTypeStr ?? ''))
                    : ($cid !== '' ? $cid."\0" : '');
                if ($l30SliceMap !== [] && $lkPr !== '' && array_key_exists($lkPr, $l30SliceMap)) {
                    $arr['Prchase'] = $l30SliceMap[$lkPr]['purchases30d'];
                } else {
                    $pv = $rowArr['purchases30d'] ?? $rowArr['purchases'] ?? null;
                    if ($pv === null || $pv === '') {
                        $arr['Prchase'] = null;
                    } else {
                        $pn = (float) $pv;
                        $arr['Prchase'] = is_finite($pn) ? (int) $pn : null;
                    }
                }
                unset($arr['purchases30d'], $arr['purchases']);
            }
            if ((in_array('sales30d', $columns, true) || in_array('ACOS', $columns, true) || in_array('sbgt', $columns, true))
                && $cid !== ''
                && (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true))) {
                if (in_array('sales30d', $columns, true) && $l30SliceMap !== [] && $lkSalesRow !== '' && array_key_exists($lkSalesRow, $l30SliceMap)) {
                    $arr['sales30d'] = $l30SliceMap[$lkSalesRow]['sales30d'];
                }
            }
            if ($hasLSpendCols && $cid !== '') {
                $adKey = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lk = $cid."\0".trim((string) $adKey);
                $slice = $lSpendMap[$lk] ?? ['L7' => null, 'L2' => null, 'L1' => null];
                $arr['L7spend'] = $slice['L7'];
                $arr['L2spend'] = $slice['L2'];
                $arr['L1spend'] = $slice['L1'];
            }
            if ($needL30ForAcosSbgt && $cid !== '') {
                $adKeyL30 = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lkL30 = $cid."\0".trim((string) $adKeyL30);
                if ($l30SliceMap !== [] && array_key_exists($lkL30, $l30SliceMap) && $l30SliceMap[$lkL30]['spend'] !== null) {
                    $arr['cost'] = $l30SliceMap[$lkL30]['spend'];
                } elseif ($l30SpendMap !== [] && array_key_exists($lkL30, $l30SpendMap)) {
                    $l30v = $l30SpendMap[$lkL30];
                    if ($l30v !== null && is_finite($l30v)) {
                        $arr['cost'] = $l30v;
                    }
                }
            }
            if (in_array('ACOS', $columns, true)) {
                $acosRow = $rowArr;
                if (in_array('cost', $dbColumns, true) && array_key_exists('cost', $arr)) {
                    $acosRow['cost'] = $arr['cost'];
                }
                if (in_array('sales30d', $dbColumns, true)) {
                    if (array_key_exists('sales30d', $arr)) {
                        $acosRow['sales30d'] = $arr['sales30d'];
                    }
                } elseif (in_array('sales', $dbColumns, true)) {
                    if ($l30SalesFromMap !== null) {
                        $acosRow['sales'] = $l30SalesFromMap;
                    } elseif (array_key_exists('sales', $arr)) {
                        $acosRow['sales'] = $arr['sales'];
                    }
                }
                $arr['ACOS'] = self::computedAcosPercentFromReportRow($acosRow, $dbColumns);
            }
            if (in_array('sbgt', $columns, true)) {
                $sbgtRow = $rowArr;
                if (in_array('cost', $dbColumns, true) && array_key_exists('cost', $arr)) {
                    $sbgtRow['cost'] = $arr['cost'];
                }
                if (in_array('sales30d', $dbColumns, true)) {
                    if (array_key_exists('sales30d', $arr)) {
                        $sbgtRow['sales30d'] = $arr['sales30d'];
                    }
                } elseif (in_array('sales', $dbColumns, true)) {
                    if ($l30SalesFromMap !== null) {
                        $sbgtRow['sales'] = $l30SalesFromMap;
                    } elseif (array_key_exists('sales', $arr)) {
                        $sbgtRow['sales'] = $arr['sales'];
                    }
                }
                $arr['sbgt'] = self::computedSbgtFromReportRow($sbgtRow, $dbColumns);
            }
            self::roundAmazonAdsDisplayNumericFields($arr, $columns);
            unset($arr['pink_dil_paused_at'], $arr['campaignBudgetCurrencyCode']);
            $data[] = $arr;
        }

        $payload = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
        if ($distinctCampaignCount !== null) {
            $payload['distinctCampaignCount'] = $distinctCampaignCount;
        }
        if ($spl30Total !== null) {
            $payload['spl30Total'] = $spl30Total;
        }
        if ($overallAcosPercent !== null) {
            $payload['overallAcosPercent'] = $overallAcosPercent;
        }

        return response()->json($payload);
    }

    /**
     * Whether an SP campaign name is treated as product targeting (PT) vs keyword (KW),
     * aligned with filters that use "NOT LIKE '% PT'" for KW sets.
     */
    private static function isSpProductTargetingCampaignName(?string $campaignName): bool
    {
        if ($campaignName === null) {
            return false;
        }
        $n = str_replace(["\xC2\xA0", "\xe2\x80\x80", "\xe2\x80\x81", "\xe2\x80\x82", "\xe2\x80\x83"], ' ', (string) $campaignName);
        $n = preg_replace('/\s+/u', ' ', trim($n));
        if ($n === '') {
            return false;
        }
        $u = strtoupper($n);

        return str_contains($u, ' PT') || preg_match('/PT\.?\s*$/u', $u) === 1;
    }

    private static function normalizePositiveBid(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (float) $value;
        if (! is_finite($n) || $n <= 0) {
            return null;
        }

        return round($n, 2);
    }

    /**
     * Push SBID bids for SP campaigns to Amazon (keywords API for KW, targets API for PT),
     * using the same logic as AmazonSpBudgetController utilized bid updates.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "bid", "campaignName"? }, ... ] } (max 100 unique campaigns).
     */
    public function pushSpSbids(Request $request): JsonResponse
    {
        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and bid.',
                'status' => 400,
            ], 400);
        }

        $kwMap = [];
        $ptMap = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $bid = self::normalizePositiveBid($row['bid'] ?? null);
            if ($bid === null) {
                continue;
            }
            $name = $row['campaignName'] ?? $row['campaign_name'] ?? null;
            if (self::isSpProductTargetingCampaignName(is_string($name) ? $name : null)) {
                $ptMap[$cid] = $bid;
            } else {
                $kwMap[$cid] = $bid;
            }
        }

        $totalUnique = count($kwMap) + count($ptMap);
        if ($totalUnique === 0) {
            return response()->json([
                'message' => 'No valid campaign_id / positive bid pairs after classification.',
                'status' => 422,
            ], 422);
        }
        if ($totalUnique > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
            ], 422);
        }

        /** @var AmazonSpBudgetController $sp */
        $sp = app(AmazonSpBudgetController::class);

        $payload = [
            'keywords' => null,
            'targets' => null,
            'keyword_http_status' => null,
            'target_http_status' => null,
        ];

        if ($kwMap !== []) {
            $sub = Request::create('/update-keywords-bid-price', 'PUT', [
                'campaign_ids' => array_keys($kwMap),
                'bids' => array_values($kwMap),
            ]);
            $respKw = $sp->updateCampaignKeywordsBid($sub);
            $payload['keyword_http_status'] = $respKw->getStatusCode();
            $payload['keywords'] = json_decode($respKw->getContent(), true);
        }

        if ($ptMap !== []) {
            $subPt = Request::create('/update-amazon-sp-targets-bid-price', 'PUT', [
                'campaign_ids' => array_keys($ptMap),
                'bids' => array_values($ptMap),
            ]);
            $respPt = $sp->updateCampaignTargetsBid($subPt);
            $payload['target_http_status'] = $respPt->getStatusCode();
            $payload['targets'] = json_decode($respPt->getContent(), true);
        }

        $kwOk = $payload['keyword_http_status'] === null || ($payload['keyword_http_status'] >= 200 && $payload['keyword_http_status'] < 300);
        $ptOk = $payload['target_http_status'] === null || ($payload['target_http_status'] >= 200 && $payload['target_http_status'] < 300);
        $payload['ok'] = $kwOk && $ptOk;
        $payload['message'] = $payload['ok']
            ? 'SBID push finished for Amazon SP (keywords and/or targets).'
            : 'SBID push finished with one or more non-success responses; see keywords/targets and HTTP status fields.';

        return response()->json($payload);
    }

    /**
     * Push SBID bids for Sponsored Brands campaigns to Amazon (SB keywords API), same shape as SP push rows.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "bid", "campaignName"? }, ... ] } (max 100 unique campaigns).
     */
    public function pushSbSbids(Request $request): JsonResponse
    {
        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and bid.',
                'status' => 400,
            ], 400);
        }

        /** @var array<string, float> $bidByCampaignId */
        $bidByCampaignId = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $bid = self::normalizePositiveBid($row['bid'] ?? null);
            if ($bid === null) {
                continue;
            }
            $bidByCampaignId[$cid] = $bid;
        }

        if ($bidByCampaignId === []) {
            return response()->json([
                'message' => 'No valid campaign_id / positive bid pairs.',
                'status' => 422,
            ], 422);
        }
        if (count($bidByCampaignId) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
            ], 422);
        }

        /** @var AmazonSbBudgetController $sb */
        $sb = app(AmazonSbBudgetController::class);

        $sub = Request::create('/amazon-ads/push-sb-sbids', 'PUT', [
            'campaign_ids' => array_keys($bidByCampaignId),
            'bids' => array_values($bidByCampaignId),
        ]);

        $resp = $sb->updateCampaignKeywordsBid($sub);
        $http = $resp->getStatusCode();
        $decoded = json_decode($resp->getContent(), true);
        $ok = $http >= 200 && $http < 300;
        $msg = is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])
            ? $decoded['message']
            : ($ok ? 'SBID push finished for Amazon SB (keywords).' : 'SB keyword bid update returned an error.');

        return response()->json([
            'ok' => $ok,
            'message' => $msg,
            'keyword_http_status' => $http,
            'keywords' => $decoded,
            'target_http_status' => null,
            'targets' => null,
        ]);
    }

    /**
     * Push SBGT tier as SP daily budget ($) to Amazon; allowed tier values match the active {@see AmazonAcosSbgtRule}.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "sbgt" }, ... ] } (max 100 unique campaigns; last row wins per campaign_id).
     */
    public function pushSpSbgts(Request $request): JsonResponse
    {
        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and a valid SBGT tier for the current BGT rule.',
                'status' => 400,
            ], 400);
        }

        $allowedTiers = AmazonAcosSbgtRule::allowedSbgtTierValues();
        /** @var array<string, float> $tierByCampaignId last row on page wins */
        $tierByCampaignId = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $raw = $row['sbgt'] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $tier = (int) $raw;
            if (! in_array($tier, $allowedTiers, true)) {
                continue;
            }
            $tierByCampaignId[$cid] = (float) $tier;
        }

        if ($tierByCampaignId === []) {
            return response()->json([
                'message' => 'No valid campaign_id / SBGT tier pairs (tier must be one of the configured rule values: '.implode(', ', $allowedTiers).').',
                'status' => 422,
            ], 422);
        }
        if (count($tierByCampaignId) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
            ], 422);
        }

        $campaignIds = array_keys($tierByCampaignId);
        $bgts = array_values($tierByCampaignId);

        $sub = Request::create('/update-amazon-campaign-bgt-price', 'PUT', [
            'campaign_ids' => $campaignIds,
            'bgts' => $bgts,
        ]);

        /** @var AmazonACOSController $acos */
        $acos = app(AmazonACOSController::class);

        return $acos->updateAmazonCampaignBgt($sub);
    }

    /**
     * Push SBGT tier as SB daily budget ($) to Amazon; same tier → dollar mapping as {@see pushSpSbgts}.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "sbgt" }, ... ] } (max 100 unique campaigns; last row wins per campaign_id).
     */
    public function pushSbSbgts(Request $request): JsonResponse
    {
        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and a valid SBGT tier for the current BGT rule.',
                'status' => 400,
            ], 400);
        }

        $allowedTiers = AmazonAcosSbgtRule::allowedSbgtTierValues();
        /** @var array<string, float> $tierByCampaignId */
        $tierByCampaignId = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $raw = $row['sbgt'] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $tier = (int) $raw;
            if (! in_array($tier, $allowedTiers, true)) {
                continue;
            }
            $tierByCampaignId[$cid] = (float) $tier;
        }

        if ($tierByCampaignId === []) {
            return response()->json([
                'message' => 'No valid campaign_id / SBGT tier pairs (tier must be one of the configured rule values: '.implode(', ', $allowedTiers).').',
                'status' => 422,
            ], 422);
        }
        if (count($tierByCampaignId) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
            ], 422);
        }

        $campaignIds = array_keys($tierByCampaignId);
        $bgts = array_values($tierByCampaignId);

        $sub = Request::create('/amazon-ads/push-sb-sbgts', 'PUT', [
            'campaign_ids' => $campaignIds,
            'bgts' => $bgts,
        ]);

        /** @var AmazonACOSController $acos */
        $acos = app(AmazonACOSController::class);

        return $acos->updateAmazonSbCampaignBgt($sub);
    }
}
