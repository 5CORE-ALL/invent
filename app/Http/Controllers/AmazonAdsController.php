<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Services\AmazonAdsPauseRuleApplicator;
use App\Support\AmazonAdsBgtCvrRule;
use App\Support\AmazonAdsBgtPrcRule;
use App\Support\AmazonAdsBgtReviewsRule;
use App\Support\AmazonAdsBgtViewsRule;
use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\AmazonAdsCampaignSkuSync;
use App\Support\AmazonAdsPauseRule;
use App\Support\AmazonAdsSbidRule;
use App\Support\AmazonAcosSbgtRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        'sp_keywords' => 'amazon_sp_keyword_reports',
        'sp_negatives' => 'amazon_sp_negative_keywords',
    ];

    /**
     * Curated, fixed display columns for the keyword performance / negative keyword sources.
     * These tables have a keyword-level shape (not the campaign-report shape), so they skip the
     * campaign overlays (U7/U2/U1, BGT/SBGT, L-spends, CPC block) applied to SP/SB reports and
     * show their own columns in a fixed order instead.
     *
     * @var array<string, array<int, string>>
     */
    private const KEYWORD_SOURCE_DISPLAY_COLUMNS = [
        'amazon_sp_keyword_reports' => [
            'id', 'campaignName', 'adGroupName', 'keyword', 'matchType', 'report_date_range',
            'impressions', 'clicks', 'cost', 'costPerClick', 'purchases30d', 'sales30d', 'acosClicks14d',
        ],
        'amazon_sp_negative_keywords' => [
            'id', 'level', 'campaignName', 'campaign_id', 'ad_group_id', 'keywordText', 'matchType', 'state',
        ],
    ];

    /** Inv / ovl30 / dil / price / reviews on each campaign row, after campaignName. */
    private const SKU_METRIC_DISPLAY_COLUMNS = ['Inv', 'ovl30', 'dil', 'price', 'reviews'];

    /**
     * Display columns computed after SQL (Shopify metrics, L-spend overlays, pause Rule).
     * Sorted in PHP over the filtered window so header click order matches the grid.
     *
     * @var list<string>
     */
    private const PHP_SORT_DISPLAY_COLUMNS = [
        'Inv', 'INV', 'ovl30', 'dil', 'price', 'reviews', 'ruleStatus', 'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'sbgt',
        'U7%', 'U2%', 'U1%', 'CPC3', 'CPC2',
        'L7spend', 'L2spend', 'L1spend', 'L1cost', 'L1clicks',
        'pageCvr', 'viewsL30', 'viewsL7',
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
     * Columns sent to the Amazon Ads All DataTables, including Inv/ovl30/dil/price and utilization % after `campaignName`
     * (U7%/U2%/U1% from L7 SP / L2 SP / L1 SP vs `campaignBudgetAmount`; so `ad_type` may sit before `campaign_id` without pulling U7/U2/U1 next to it).
     * `campaignStatus` (Stat) sits immediately before `bgt`; `ruleStatus` follows Stat; `bgtAcos` then `bgtViews` then `bgtCvr` then `bgtPrc` then `bgtReviews` then `sbgt` follow `bgt` when the table has campaign budget.
     */
    private static function displayColumnsForTable(string $table): array
    {
        // Keyword performance / negative keyword sources use a fixed keyword-level column set
        // (not the campaign-report overlays). Only keep columns that actually exist on the table.
        if (isset(self::KEYWORD_SOURCE_DISPLAY_COLUMNS[$table])) {
            if (! Schema::hasTable($table)) {
                return [];
            }
            $existing = Schema::getColumnListing($table);

            return array_values(array_filter(
                self::KEYWORD_SOURCE_DISPLAY_COLUMNS[$table],
                static fn (string $c): bool => in_array($c, $existing, true)
            ));
        }

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

        // Stat immediately before BGT on Amazon Ads All.
        if (in_array('campaignStatus', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'campaignStatus'));
            $idxBgtForStat = array_search('bgt', $ordered, true);
            if ($idxBgtForStat !== false) {
                array_splice($ordered, $idxBgtForStat, 0, ['campaignStatus']);
            } else {
                $idxCnStat = array_search('campaignName', $ordered, true);
                if ($idxCnStat !== false) {
                    array_splice($ordered, $idxCnStat + 1, 0, ['campaignStatus']);
                } else {
                    $ordered[] = 'campaignStatus';
                }
            }
        }

        // Rule Status immediately after Stat (SP/SB campaign reports only).
        if (($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports')
            && in_array('campaignStatus', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'ruleStatus'));
            $idxStatForRule = array_search('campaignStatus', $ordered, true);
            if ($idxStatForRule !== false) {
                array_splice($ordered, $idxStatForRule + 1, 0, ['ruleStatus']);
            }
        }

        // BGT ACOS, Bgt Views, Bgt Cvr, BGT PRC, Bgt Reviews, then SBGT immediately after BGT.
        if (in_array('campaignBudgetAmount', self::orderedColumnsForTable($table), true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'bgtAcos' && $c !== 'sbgt' && $c !== 'bgtViews' && $c !== 'bgtCvr' && $c !== 'bgtPrc' && $c !== 'bgtReviews'));
            $idxBgtForSbgt = array_search('bgt', $ordered, true);
            if ($idxBgtForSbgt !== false) {
                array_splice($ordered, $idxBgtForSbgt + 1, 0, ['bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'sbgt']);
            }
        }

        $idxBgt = array_search('bgt', $ordered, true);
        if ($idxBgt !== false && in_array('clicks', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'clicks'));
            $idxAfterBgt = array_search('sbgt', $ordered, true);
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('bgtCvr', $ordered, true);
            }
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('bgtViews', $ordered, true);
            }
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

        // Ads CVR (%) = Ads Sold ÷ Ads Clicks × 100 — both from the same L30 summary row.
        if (in_array('Prchase', $ordered, true) && in_array('clicks', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'Cvr'));
            $idxPrchaseForCvr = array_search('Prchase', $ordered, true);
            if ($idxPrchaseForCvr !== false) {
                array_splice($ordered, $idxPrchaseForCvr + 1, 0, ['Cvr']);
            }
        }

        // Listing CVR + parent View L30 / View L7 from /amazon-tabulator-view, after Ads CVR.
        if ($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports') {
            $ordered = array_values(array_filter(
                $ordered,
                static fn (string $c): bool => ! in_array($c, ['pageCvr', 'viewsL30', 'viewsL7'], true)
            ));
            $idxPageCvr = array_search('Cvr', $ordered, true);
            if ($idxPageCvr === false) {
                $idxPageCvr = array_search('Prchase', $ordered, true);
            }
            if ($idxPageCvr !== false) {
                array_splice($ordered, $idxPageCvr + 1, 0, ['pageCvr', 'viewsL30', 'viewsL7']);
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
            foreach (self::SKU_METRIC_DISPLAY_COLUMNS as $skuCol) {
                $have[$skuCol] = true;
            }
            unset($have['INV']);
            $filtered = [];
            foreach ($spOrder as $c) {
                if (isset($have[$c])) {
                    $filtered[] = $c;
                }
            }
            $idxL1Sb = array_search('L1spend', $filtered, true);
            if ($idxL1Sb !== false) {
                array_splice($filtered, $idxL1Sb + 1, 0, ['L1cost', 'L1clicks']);
            }

            return $filtered;
        }

        if ($table === 'amazon_sp_campaign_reports') {
            return self::withSkuMetricColumnsAfterCampaignName($ordered);
        }

        return $ordered;
    }

    /**
     * Inv / ovl30 / dil / price from /amazon-tabulator-view, immediately after campaign name.
     *
     * @param  list<string>  $ordered
     * @return list<string>
     */
    private static function withSkuMetricColumnsAfterCampaignName(array $ordered): array
    {
        $ordered = array_values(array_filter(
            $ordered,
            static fn (string $c): bool => ! in_array($c, self::SKU_METRIC_DISPLAY_COLUMNS, true) && $c !== 'INV'
        ));
        $idx = array_search('campaignName', $ordered, true);
        if ($idx !== false) {
            array_splice($ordered, $idx + 1, 0, self::SKU_METRIC_DISPLAY_COLUMNS);
        } else {
            array_push($ordered, ...self::SKU_METRIC_DISPLAY_COLUMNS);
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
     * Grid SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews. Null parts count as 0; all-missing → null.
     */
    private static function summedSbgtFromParts(mixed $bgtViews, mixed $bgtCvr, mixed $bgtAcos, mixed $bgtPrc = null, mixed $bgtReviews = null): ?int
    {
        $has = false;
        $sum = 0;
        foreach ([$bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtReviews] as $part) {
            if ($part === null || $part === '') {
                continue;
            }
            if (! is_numeric($part)) {
                continue;
            }
            $has = true;
            $sum += (int) $part;
        }
        if (! $has || $sum < 1) {
            return null;
        }

        return $sum;
    }

    /**
     * Daily budget dollars accepted for SBGT push (sum of the three rule columns).
     */
    private static function parsePushableSbgtBudget(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (int) $raw;

        return ($n >= 1 && $n <= 9999) ? $n : null;
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
     * Ads CVR % = L30 Ads Sold ÷ L30 Ads Clicks × 100 (same overlay as the Ads CVR column).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function sqlExpressionForAdsCvrPercent(string $table, array $dbColumns): ?string
    {
        if (! in_array('clicks', $dbColumns, true)
            || (! in_array('purchases30d', $dbColumns, true) && ! in_array('purchases', $dbColumns, true))) {
            return null;
        }
        $purchSub = self::correlatedL30SummaryPurchases30dScalarSubquerySql($table, $dbColumns);
        if ($purchSub !== null) {
            $purchExpr = in_array('purchases30d', $dbColumns, true)
                ? 'COALESCE(('.$purchSub.'), purchases30d)'
                : 'COALESCE(('.$purchSub.'), purchases)';
        } else {
            $purchExpr = in_array('purchases30d', $dbColumns, true) ? 'purchases30d' : 'purchases';
        }
        $clicksSub = self::correlatedL30SummaryClicksScalarSubquerySql($table, $dbColumns);
        $clicksExpr = $clicksSub !== null ? 'COALESCE(('.$clicksSub.'), clicks)' : 'clicks';

        return 'CASE WHEN COALESCE('.$clicksExpr.', 0) > 0 THEN (('.$purchExpr.') / NULLIF('.$clicksExpr.', 0)) * 100 ELSE NULL END';
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
     * Latest L30 summary ads clicks (same L30 row as Ads Sold / Ads CVR).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function correlatedL30SummaryClicksScalarSubquerySql(string $table, array $dbColumns): ?string
    {
        if (! in_array('clicks', $dbColumns, true) || ! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true) || ! in_array('id', $dbColumns, true)) {
            return null;
        }
        $t = str_replace('`', '``', $table);
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $adClause = $hasAdType ? ' AND l30.ad_type <=> `'.$t.'`.ad_type ' : '';

        return 'SELECT l30.clicks FROM `'.$t.'` AS l30 WHERE l30.campaign_id = `'.$t.'`.campaign_id'.$adClause
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
            ->whereRaw('CHAR_LENGTH(s30.report_date_range) = 10')
            ->whereBetween('s30.report_date_range', [$from, $anchor]);
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
     * @return array<string, array{spend: ?float, purchases30d: ?int, sales30d: ?float, clicks: ?int}>
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
            ->where('report_date_range', 'L30')
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
            $clicksOut = null;
            if (in_array('clicks', $dbColumns, true) && array_key_exists('clicks', $r) && $r['clicks'] !== null && $r['clicks'] !== '') {
                $cv = (float) $r['clicks'];
                $clicksOut = is_finite($cv) ? (int) round($cv) : null;
            }
            $map[$key] = [
                'spend' => $spendOut,
                'purchases30d' => self::l30Purchases30dFromRowArray($r, $dbColumns),
                'sales30d' => self::l30Sales30dFromRowArray($r, $dbColumns),
                'clicks' => $clicksOut,
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
     * @return array{cost_sum: float, sales_sum: float, purchases_sum: float, clicks_sum: float}|null
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
            return ['cost_sum' => 0.0, 'sales_sum' => 0.0, 'purchases_sum' => 0.0, 'clicks_sum' => 0.0];
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
            || in_array('sbgt', $columns, true)
            || in_array('bgtAcos', $columns, true);
        // Always fetch the L30 slice: badge totals (Spend / Sold / Sales / Clicks) all read distinct-campaign L30 values from it.
        $l30SliceMap = self::fetchL30SummarySliceMap($table, $dbColumns, $stubRows);
        $l30SpendMap = [];
        if ($needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true))) {
            $needDailyFallback = $l30SliceMap === [];
            if (! $needDailyFallback) {
                foreach ($stubRows as $stub) {
                    $cid = isset($stub->campaign_id) ? trim((string) $stub->campaign_id) : '';
                    if ($cid === '') {
                        continue;
                    }
                    $ad = $hasAd ? trim((string) ($stub->ad_type ?? '')) : '';
                    $lk = $cid."\0".$ad;
                    if (! isset($l30SliceMap[$lk]) || $l30SliceMap[$lk]['spend'] === null) {
                        $needDailyFallback = true;
                        break;
                    }
                }
            }
            if ($needDailyFallback) {
                $l30SpendMap = self::fetchL30DailySpendSumMap($table, $dbColumns, $stubRows);
            }
        }
        $rawByKey = [];
        $rawSalesByKey = [];
        $rawPurchByKey = [];
        $rawClicksByKey = [];
        $coalesce = self::costPreferCoalesceExprForTableAlias('r', $dbColumns);
        $salesColRaw = self::l30SummarySalesDbColumn($dbColumns);
        $purchColRaw = in_array('purchases30d', $dbColumns, true)
            ? 'purchases30d'
            : (in_array('purchases', $dbColumns, true) ? 'purchases' : null);
        $clicksColRaw = in_array('clicks', $dbColumns, true) ? 'clicks' : null;
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
        if ($purchColRaw !== null) {
            $selectChunks[] = 'MAX(r.`'.$purchColRaw.'`) AS mx_purch';
        }
        if ($clicksColRaw !== null) {
            $selectChunks[] = 'MAX(r.`'.$clicksColRaw.'`) AS mx_clicks';
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
                if ($purchColRaw !== null && property_exists($rw, 'mx_purch')) {
                    $mp = $rw->mx_purch ?? null;
                    if ($mp === null || $mp === '') {
                        $rawPurchByKey[$key] = null;
                    } else {
                        $pn = (float) $mp;
                        $rawPurchByKey[$key] = is_finite($pn) ? $pn : null;
                    }
                }
                if ($clicksColRaw !== null && property_exists($rw, 'mx_clicks')) {
                    $mk = $rw->mx_clicks ?? null;
                    if ($mk === null || $mk === '') {
                        $rawClicksByKey[$key] = null;
                    } else {
                        $kn = (float) $mk;
                        $rawClicksByKey[$key] = is_finite($kn) ? $kn : null;
                    }
                }
            }
        }
        $costSum = 0.0;
        $salesSum = 0.0;
        $purchasesSum = 0.0;
        $clicksSum = 0.0;
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
            $purchVal = null;
            if ($l30SliceMap !== [] && array_key_exists($lkL30, $l30SliceMap)) {
                $p30 = $l30SliceMap[$lkL30]['purchases30d'] ?? null;
                if ($p30 !== null && is_finite((float) $p30)) {
                    $purchVal = (float) $p30;
                }
            }
            if ($purchVal === null && array_key_exists($lkL30, $rawPurchByKey) && $rawPurchByKey[$lkL30] !== null && is_finite((float) $rawPurchByKey[$lkL30])) {
                $purchVal = (float) $rawPurchByKey[$lkL30];
            }
            if ($purchVal !== null) {
                $purchasesSum += $purchVal;
            }
            $clicksVal = null;
            if ($l30SliceMap !== [] && array_key_exists($lkL30, $l30SliceMap)) {
                $c30 = $l30SliceMap[$lkL30]['clicks'] ?? null;
                if ($c30 !== null && is_finite((float) $c30)) {
                    $clicksVal = (float) $c30;
                }
            }
            if ($clicksVal === null && array_key_exists($lkL30, $rawClicksByKey) && $rawClicksByKey[$lkL30] !== null && is_finite((float) $rawClicksByKey[$lkL30])) {
                $clicksVal = (float) $rawClicksByKey[$lkL30];
            }
            if ($clicksVal !== null) {
                $clicksSum += $clicksVal;
            }
        }

        return ['cost_sum' => $costSum, 'sales_sum' => $salesSum, 'purchases_sum' => $purchasesSum, 'clicks_sum' => $clicksSum];
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
        } elseif ($requested === 'sbgt' || $requested === 'bgtAcos') {
            $expr = self::sqlExpressionForSbgtSort($table, $dbColumns);
            if ($expr !== null) {
                $query->orderByRaw('('.$expr.') '.$dir);
            } elseif (in_array('id', $dbColumns, true)) {
                $query->orderBy('id', 'desc');
            }
        } elseif ($requested === 'Cvr'
            && in_array('clicks', $dbColumns, true)
            && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true))) {
            $purchSub = self::correlatedL30SummaryPurchases30dScalarSubquerySql($table, $dbColumns);
            if ($purchSub !== null) {
                $purchExpr = in_array('purchases30d', $dbColumns, true)
                    ? 'COALESCE(('.$purchSub.'), purchases30d)'
                    : 'COALESCE(('.$purchSub.'), purchases)';
            } else {
                $purchExpr = in_array('purchases30d', $dbColumns, true) ? 'purchases30d' : 'purchases';
            }
            $clicksSub = self::correlatedL30SummaryClicksScalarSubquerySql($table, $dbColumns);
            $clicksExpr = $clicksSub !== null ? 'COALESCE(('.$clicksSub.'), clicks)' : 'clicks';
            $query->orderByRaw('CASE WHEN COALESCE('.$clicksExpr.', 0) > 0 THEN ('.$purchExpr.') / NULLIF('.$clicksExpr.', 0) ELSE NULL END '.$dir);
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
    /** @var array<string, string|null> */
    private static array $latestDailyReportYmdCache = [];

    private static function latestDailyReportYmdInTable(string $table): ?string
    {
        if (array_key_exists($table, self::$latestDailyReportYmdCache)) {
            return self::$latestDailyReportYmdCache[$table];
        }
        if (! Schema::hasTable($table)) {
            return self::$latestDailyReportYmdCache[$table] = null;
        }
        // Exact YYYY-MM-DD daily rows (indexed); avoid LEFT/TRIM/REGEXP full scans.
        $max = DB::table($table)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max('report_date_range');

        if ($max === null || $max === '') {
            return self::$latestDailyReportYmdCache[$table] = null;
        }

        return self::$latestDailyReportYmdCache[$table] = (string) $max;
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
            ->whereIn('report_date_range', ['L7', 'L1'])
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
            ->where('report_date_range', $l2Day)
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
     * Prefetch CPC2/CPC3 for a page of rows in one query (avoids N+1 per-row day lookups).
     *
     * Cache key matches {@see fetchCostPerClickOnReportDay}: campaign_id + ad + YYYY-MM-DD.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, float|null>
     */
    private static function prefetchCostPerClickForPageRows(
        string $table,
        array $dbColumns,
        iterable $pageRows,
        bool $needCpc2,
        bool $needCpc3
    ): array {
        if ((! $needCpc2 && ! $needCpc3) || ! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true)) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $useSpCpc = in_array('costPerClick', $dbColumns, true);
        $useSbHl = $table === 'amazon_sb_campaign_reports'
            && in_array('cost', $dbColumns, true)
            && in_array('clicks', $dbColumns, true);
        if (! $useSpCpc && ! $useSbHl) {
            return [];
        }

        $cids = [];
        $days = [];
        $needed = [];
        foreach ($pageRows as $row) {
            $rowArr = (array) $row;
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $adType = $hasAdType ? ($rowArr['ad_type'] ?? null) : null;
            $adTypeStr = is_string($adType) ? $adType : null;
            $adKey = ($adTypeStr !== null && $adTypeStr !== '') ? $adTypeStr : '-';
            $offsets = [];
            if ($needCpc2) {
                $offsets[] = 1;
            }
            if ($needCpc3) {
                $offsets[] = 2;
            }
            foreach ($offsets as $daysBefore) {
                $day = self::calendarDayOffsetFromCpc1Anchor($rowArr, $dbColumns, $table, $daysBefore);
                if ($day === null || $day === '') {
                    continue;
                }
                $cids[$cid] = true;
                $days[$day] = true;
                $needed[$cid."\0".$adKey."\0".$day] = true;
            }
        }
        $cidList = array_keys($cids);
        $dayList = array_keys($days);
        if ($cidList === [] || $dayList === []) {
            return [];
        }

        $select = ['campaign_id', 'report_date_range', 'id'];
        if ($hasAdType) {
            $select[] = 'ad_type';
        }
        if ($useSpCpc) {
            $select[] = 'costPerClick';
        } else {
            $select[] = 'cost';
            $select[] = 'clicks';
            if (in_array('spend', $dbColumns, true)) {
                $select[] = 'spend';
            }
        }

        $foundRows = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereIn('report_date_range', $dayList)
            ->orderBy('id', 'desc')
            ->get();

        $cache = [];
        foreach ($needed as $k => $_) {
            $cache[$k] = null;
        }
        foreach ($foundRows as $fr) {
            $r = (array) $fr;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $day = isset($r['report_date_range']) ? trim((string) $r['report_date_range']) : '';
            if ($day === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $adKey = $ad !== '' ? $ad : '-';
            $key = $cid."\0".$adKey."\0".$day;
            if (! array_key_exists($key, $cache) || $cache[$key] !== null) {
                continue;
            }
            if ($useSpCpc) {
                $cpc = null;
                if (isset($r['costPerClick'])) {
                    $n = (float) $r['costPerClick'];
                    $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
                }
                $cache[$key] = $cpc;
            } else {
                $cache[$key] = self::hlStyleCpcFromReportRowArray($r);
            }
        }

        return $cache;
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

        // Prefer exact indexed match (daily rows are YYYY-MM-DD).
        $q = DB::table($table)->where('campaign_id', $campaignId)
            ->where('report_date_range', $reportDayYmd);
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
     * Grid SBID from U7%/U1% + CPC1/CPC2/CPC3 (`costPerClick`, `CPC2`, `CPC3`), aligned with auto-update commands.
     * Outside red+red / pink+pink bands, sbid is forced to null so the UI shows "--".
     */
    private static function applyGridSbidFromUb2Ub1AndCpc(array &$arr, array $u, array $rowArr, array $dbColumns, string $table): void
    {
        if (! in_array('sbid', $dbColumns, true)) {
            return;
        }
        // SBID rule bands are driven by U7% (interchanged from U2%) and U1%.
        $u2 = $u['U7'];
        $u1 = $u['U1'];
        if ($u2 === null || $u1 === null) {
            $arr['sbid'] = null;

            return;
        }

        $cpc1 = self::rowPositiveFloatFromKeys($arr, ['costPerClick']);
        if ($cpc1 <= 0) {
            $cpc1 = self::rowPositiveFloatFromKeys($rowArr, ['costPerClick']);
        }
        $cpc2 = self::rowPositiveFloatFromKeys($arr, ['CPC2']);
        $cpc3 = self::rowPositiveFloatFromKeys($arr, ['CPC3']);

        $out = AmazonBidUtilizationService::sbidFromUb2Ub1Cpc(
            (float) $u2,
            (float) $u1,
            $cpc1,
            $cpc2,
            $cpc3,
            null
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
        // Daily rows store exact YYYY-MM-DD (len 10). Prefer the indexed column so calendar filters
        // and COUNT/DISTINCT badge queries can use amazon_*_report_date_range_index.
        // L1/L7/L30 labels are short and lexicographically outside any ISO date range.
        if ($from !== null && $to !== null && $from === $to) {
            $query->where('report_date_range', $from);

            return;
        }
        $query->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        if ($from !== null) {
            $query->where('report_date_range', '>=', $from);
        }
        if ($to !== null) {
            $query->where('report_date_range', '<=', $to);
        }
    }

    /**
     * Calendar mode + campaign search: include matching L30 rows for campaigns Amazon omitted
     * from the selected daily window (zero-activity days). Returns true when applied.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function applyCalendarSearchWithL30Fallback(
        Builder $query,
        string $table,
        array $dbColumns,
        Request $request,
        string $search
    ): bool {
        if ($search === ''
            || ! in_array('campaignName', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('campaign_id', $dbColumns, true)
            || ! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports', 'amazon_sd_campaign_reports'], true)
        ) {
            return false;
        }

        if (self::normalizeSummaryReportRange($request->input('summary_report_range')) !== null) {
            return false;
        }

        $from = self::normalizeDateInput((string) $request->input('date_from'));
        $to = self::normalizeDateInput((string) $request->input('date_to'));
        if ($from === null && $to === null) {
            return false;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $hasAdType = in_array('ad_type', $dbColumns, true);

        $query->where(function (Builder $outer) use ($from, $to, $like, $table, $hasAdType) {
            $outer->where(function (Builder $daily) use ($from, $to, $like) {
                self::whereReportDateRangeDailyYmdInRange($daily, $from, $to);
                $daily->where('campaignName', 'LIKE', $like);
            })->orWhere(function (Builder $l30) use ($from, $to, $like, $table, $hasAdType) {
                $l30->whereRaw("UPPER(TRIM(report_date_range)) = 'L30'")
                    ->where('campaignName', 'LIKE', $like)
                    ->whereNotExists(function ($sub) use ($from, $to, $table, $hasAdType) {
                        $sub->select(DB::raw('1'))
                            ->from($table.' as amz_cal_d')
                            ->whereColumn('amz_cal_d.campaign_id', $table.'.campaign_id');
                        if ($hasAdType) {
                            $sub->whereColumn('amz_cal_d.ad_type', $table.'.ad_type');
                        }
                        $sub->whereRaw('CHAR_LENGTH(TRIM(amz_cal_d.report_date_range)) >= 10')
                            ->whereRaw("LEFT(TRIM(amz_cal_d.report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                        if ($from !== null) {
                            $sub->whereRaw('LEFT(TRIM(amz_cal_d.report_date_range), 10) >= ?', [$from]);
                        }
                        if ($to !== null) {
                            $sub->whereRaw('LEFT(TRIM(amz_cal_d.report_date_range), 10) <= ?', [$to]);
                        }
                    });
            });
        });

        return true;
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
     * Filter by ACOS color band (same first-match BGT color rules as the ACOS% column).
     * Request value is `band:{index}`, a 0-based index, or a band label / hex color.
     */
    private static function applyAcosColorFilter(Builder $query, string $table, Request $request): void
    {
        $raw = trim((string) $request->input('filter_acos', ''));
        if ($raw === '') {
            return;
        }

        $dbColumns = Schema::getColumnListing($table);
        $acosExpr = self::sqlExpressionForAcosSort($table, $dbColumns);
        if ($acosExpr === null) {
            return;
        }

        $bands = AmazonAcosSbgtRule::resolvedRule()['bands'] ?? [];
        if ($bands === []) {
            return;
        }

        $idx = self::normalizeAcosColorFilterIndex($raw, $bands);
        if ($idx === null) {
            return;
        }

        $sql = 'CASE';
        $bindings = [];
        foreach ($bands as $i => $band) {
            $sql .= ' WHEN ('.$acosExpr.') >= ? AND ('.$acosExpr.') <= ? THEN '.$i;
            $bindings[] = (float) ($band['acos_from'] ?? 0);
            $bindings[] = (float) ($band['acos_to'] ?? 9999);
        }
        $sql .= ' ELSE -1 END';
        $bindings[] = $idx;
        $query->whereRaw('('.$sql.') = ?', $bindings);
    }

    /**
     * Filter by Ads CVR color band (same Amz page CVR L30 slabs as the Ads CVR column).
     * Request value is `band:{index}`, a 0-based index, or a band label / hex color.
     */
    private static function applyAdsCvrColorFilter(Builder $query, string $table, Request $request): void
    {
        $raw = trim((string) $request->input('filter_ads_cvr', ''));
        if ($raw === '') {
            return;
        }

        $dbColumns = Schema::getColumnListing($table);
        $cvrExpr = self::sqlExpressionForAdsCvrPercent($table, $dbColumns);
        if ($cvrExpr === null) {
            return;
        }

        $idx = self::normalizeAdsCvrColorFilterIndex($raw);
        if ($idx === null) {
            return;
        }

        $case = 'CASE WHEN ('.$cvrExpr.') <= 4 THEN 0'
            .' WHEN ('.$cvrExpr.') <= 7 THEN 1'
            .' WHEN ('.$cvrExpr.') <= 13 THEN 2'
            .' WHEN ('.$cvrExpr.') > 13 THEN 3'
            .' ELSE -1 END';
        $query->whereRaw('('.$case.') = ?', [$idx]);
    }

    private static function normalizeAdsCvrColorFilterIndex(string $raw): ?int
    {
        $v = trim($raw);
        if ($v === '') {
            return null;
        }
        $bands = [
            0 => ['label' => 'red', 'color' => 'a00211'],
            1 => ['label' => 'yellow', 'color' => 'ffc107'],
            2 => ['label' => 'green', 'color' => '28a745'],
            3 => ['label' => 'pink', 'color' => 'e83e8c'],
        ];
        if (preg_match('/^band:(\d+)$/i', $v, $m)) {
            $i = (int) $m[1];

            return array_key_exists($i, $bands) ? $i : null;
        }
        if (ctype_digit($v)) {
            $i = (int) $v;

            return array_key_exists($i, $bands) ? $i : null;
        }

        $norm = strtolower($v);
        $hex = ltrim($norm, '#');
        foreach ($bands as $i => $band) {
            if ($band['label'] === $norm || $band['color'] === $hex || '#'.$band['color'] === $norm) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private static function normalizeAcosColorFilterIndex(string $raw, array $bands): ?int
    {
        $v = trim($raw);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^band:(\d+)$/i', $v, $m)) {
            $i = (int) $m[1];

            return array_key_exists($i, $bands) ? $i : null;
        }
        if (ctype_digit($v)) {
            $i = (int) $v;

            return array_key_exists($i, $bands) ? $i : null;
        }

        $norm = strtolower($v);
        $hex = ltrim($norm, '#');
        foreach ($bands as $i => $band) {
            $label = strtolower(trim((string) ($band['label'] ?? '')));
            $color = strtolower(ltrim((string) ($band['color'] ?? ''), '#'));
            if ($label !== '' && $label === $norm) {
                return (int) $i;
            }
            if ($color !== '' && ($color === $hex || '#'.$color === $norm)) {
                return (int) $i;
            }
        }

        return null;
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
                // Reuse the cached/indexed daily max (same source as grid CPC anchors).
                $best = self::latestDailyReportYmdInTable($table);

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

        // Virtual "All" source: SP + SB combined (shares the SP display column set).
        $rawSources['all_reports'] = [
            'table' => 'amazon_sp_campaign_reports',
            'columns' => self::displayColumnsForTable('amazon_sp_campaign_reports'),
        ];
        $defaultReportRangeDates['all_reports'] = self::latestAvailableReportDayYmd('amazon_sp_campaign_reports');

        // Negative keywords have no report date/range — don't pin the calendar date for them
        // (otherwise the grid would filter to only negatives whose created_at matches that day).
        $defaultReportRangeDates['sp_negatives'] = null;

        return view('amazon_ads.all', [
            'rawSources' => $rawSources,
            'defaultReportRangeDates' => $defaultReportRangeDates,
            'amazonAdsBgtRule' => AmazonAcosSbgtRule::resolvedRule(),
            'amazonAdsBgtViewsRule' => AmazonAdsBgtViewsRule::resolvedRule(),
            'amazonAdsBgtCvrRule' => AmazonAdsBgtCvrRule::resolvedRule(),
            'amazonAdsBgtPrcRule' => AmazonAdsBgtPrcRule::resolvedRule(),
            'amazonAdsBgtReviewsRule' => AmazonAdsBgtReviewsRule::resolvedRule(),
            'amazonAdsSbidRule' => AmazonAdsSbidRule::resolvedRule(),
            'amazonAdsPauseRule' => AmazonAdsPauseRule::resolvedRule(),
        ]);
    }

    /**
     * Current ACOS → SBGT rule (for BGT RULE modal and client-side tier colors).
     */
    public function getBgtRule(): JsonResponse
    {
        // Clear cache to get fresh data
        AmazonAcosSbgtRule::forgetResolvedCache();
        
        return response()->json([
            'rule' => AmazonAcosSbgtRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist ACOS boundary / SBGT tier rule; clears rule cache so grids and pushes use the new mapping.
     */
    public function saveBgtRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAcosSbgtRule::normalizeRule($request->all());
            AmazonAcosSbgtRule::persistRule($normalized);
            // Clear cache immediately after saving
            AmazonAcosSbgtRule::forgetResolvedCache();
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

        // Fetch fresh from database
        $freshRule = AmazonAcosSbgtRule::resolvedRule();
        
        return response()->json([
            'message' => 'BGT rule saved. SBGT on the grid will use the new ACOS → tier mapping after reload.',
            'rule' => $freshRule,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current View L30 → Bgt Views rule (BGT Vs VIEWS modal).
     */
    public function getBgtViewsRule(): JsonResponse
    {
        AmazonAdsBgtViewsRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtViewsRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist View L30 bands → Bgt Views; grid uses the new mapping after reload.
     */
    public function saveBgtViewsRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAdsBgtViewsRule::normalizeRule($request->all());
            AmazonAdsBgtViewsRule::persistRule($normalized);
            AmazonAdsBgtViewsRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT Vs VIEWS rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'BGT Vs VIEWS saved. Bgt Views on the grid will use the new View L30 bands after reload.',
            'rule' => AmazonAdsBgtViewsRule::resolvedRule(),
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current CVR L30 → Bgt Cvr rule (BGT Vs CVR modal).
     */
    public function getBgtCvrRule(): JsonResponse
    {
        AmazonAdsBgtCvrRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtCvrRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist CVR L30 bands → Bgt Cvr; grid uses the new mapping after reload.
     */
    public function saveBgtCvrRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAdsBgtCvrRule::normalizeRule($request->all());
            AmazonAdsBgtCvrRule::persistRule($normalized);
            AmazonAdsBgtCvrRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT Vs CVR rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'BGT Vs CVR saved. Bgt Cvr on the grid will use the new CVR L30 bands after reload.',
            'rule' => AmazonAdsBgtCvrRule::resolvedRule(),
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current Price → BGT PRC rule (BGT PRC modal).
     */
    public function getBgtPrcRule(): JsonResponse
    {
        AmazonAdsBgtPrcRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtPrcRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist Price bands → BGT PRC; grid uses the new mapping after reload.
     */
    public function saveBgtPrcRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAdsBgtPrcRule::normalizeRule($request->all());
            AmazonAdsBgtPrcRule::persistRule($normalized);
            AmazonAdsBgtPrcRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT PRC rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'BGT PRC saved. BGT PRC on the grid will use the new Price bands after reload.',
            'rule' => AmazonAdsBgtPrcRule::resolvedRule(),
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current Reviews → Bgt Reviews rule (BGT Vs REVIEWS modal).
     */
    public function getBgtReviewsRule(): JsonResponse
    {
        AmazonAdsBgtReviewsRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtReviewsRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist Reviews bands → Bgt Reviews; grid uses the new mapping after reload.
     */
    public function saveBgtReviewsRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAdsBgtReviewsRule::normalizeRule($request->all());
            AmazonAdsBgtReviewsRule::persistRule($normalized);
            AmazonAdsBgtReviewsRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT Vs REVIEWS rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'BGT Vs REVIEWS saved. Bgt Reviews on the grid will use the new Reviews bands after reload.',
            'rule' => AmazonAdsBgtReviewsRule::resolvedRule(),
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Advertised SKUs on one campaign: Amazon product ads, then campaign-name fallback
     * (PARENT … → product_master children) so SB campaigns still show Reviews.
     */
    public function campaignSkus(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['message' => 'Provide campaign_id.', 'skus' => []], 422);
        }
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            return response()->json(['message' => 'Campaign SKU table is missing. Run amazon:ads-pull-product-ads.', 'skus' => []], 404);
        }

        $resolved = AmazonAdsCampaignSkuSync::resolveForCampaign(
            $cid,
            trim((string) $request->query('campaign_name', ''))
        );
        $skus = $resolved['skus'];
        $reviews = AmazonAdsCampaignSkuMetrics::reviewsBySkus(array_column($skus, 'sku'));
        foreach ($skus as $i => $skuRow) {
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $skuRow['sku'])));
            $hit = $reviews[$key] ?? null;
            $skus[$i]['amz_avg_rating'] = is_array($hit) && $hit['rating'] !== null
                ? (float) $hit['rating']
                : null;
            $skus[$i]['amz_review_count'] = is_array($hit) ? (int) ($hit['review_count'] ?? 0) : null;
        }

        return response()->json([
            'campaign_id' => $cid,
            'campaign_name' => $resolved['campaign_name'] !== '' ? $resolved['campaign_name'] : null,
            'source' => $resolved['source'],
            'skus' => $skus,
            'count' => count($skus),
        ]);
    }


    /**
     * Current U2%/U1% → SBID rule (Amazon Ads SBID RULE modal).
     */
    public function getSbidRule(): JsonResponse
    {
        // Clear cache to get fresh data
        AmazonAdsSbidRule::forgetResolvedCache();
        
        return response()->json([
            'rule' => AmazonAdsSbidRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist SBID utilization thresholds and CPC multipliers; clears cache so grid and bid jobs use the new rule.
     */
    public function saveSbidRule(Request $request): JsonResponse
    {
        try {
            $normalized = AmazonAdsSbidRule::normalizeRule($request->all());
            AmazonAdsSbidRule::persistRule($normalized);
            // Clear cache immediately after saving
            AmazonAdsSbidRule::forgetResolvedCache();
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

        // Fetch fresh from database
        $freshRule = AmazonAdsSbidRule::resolvedRule();

        return response()->json([
            'message' => 'SBID rule saved. Suggested SBID on the grid and in bid updates will use the new thresholds after reload.',
            'rule' => $freshRule,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current Pricing / Dil% / ACOS% pause-or-activate rule (Amazon Ads Pause Rule modal).
     */
    public function getPauseRule(): JsonResponse
    {
        AmazonAdsPauseRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsPauseRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist Dil% auto-pause threshold (PR button). When `apply` is true, pause matching SP+SB campaigns.
     */
    public function savePrRule(Request $request): JsonResponse
    {
        try {
            AmazonAdsPauseRule::persistPr([
                'enabled' => $request->boolean('enabled', true),
                'dil_above' => $request->input('dil_above', 100),
                'dil_enabled' => $request->boolean('dil_enabled', true),
                'price_below' => $request->input('price_below', 20),
                'price_enabled' => $request->boolean('price_enabled', true),
                'reviews_enabled' => $request->boolean('reviews_enabled', false),
                'reviews_below' => $request->input('reviews_below', 2.99),
            ]);
            AmazonAdsPauseRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Could not save PR Dil% pause rule', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Could not save PR Dil% pause rule. '.$e->getMessage(),
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        $freshRule = AmazonAdsPauseRule::resolvedRule();
        $pr = $freshRule['pr'] ?? AmazonAdsPauseRule::defaultPr();
        $parts = [];
        if (! empty($pr['enabled'])) {
            if (! empty($pr['dil_enabled'])) {
                $th = rtrim(rtrim(number_format((float) ($pr['dil_above'] ?? 100), 2, '.', ''), '0'), '.');
                $parts[] = 'Dil% ≥ '.$th.'%';
            }
            if (! empty($pr['price_enabled'])) {
                $th = rtrim(rtrim(number_format((float) ($pr['price_below'] ?? 20), 2, '.', ''), '0'), '.');
                $parts[] = 'Price < $'.$th;
            }
        }
        $rev = $freshRule['reviews'] ?? AmazonAdsPauseRule::defaultReviews();
        $revPart = '';
        if (! empty($rev['enabled'])) {
            $revTh = rtrim(rtrim(number_format((float) ($rev['below'] ?? 2.99), 2, '.', ''), '0'), '.');
            $revPart = ' Product ads rated below '.$revTh.'★ will be paused (campaign stays on).';
        }
        $payload = [
            'message' => (! empty($pr['enabled']) && $parts !== []
                ? 'Pause Rule saved. Campaigns matching '.implode(' or ', $parts).' will be paused.'
                : 'Pause Rule saved. Dil% / price will not auto-pause campaigns.')
                .$revPart,
            'rule' => $freshRule,
            'status' => 200,
            'timestamp' => time(),
        ];

        if ($request->boolean('apply')) {
            try {
                $payload['apply'] = app(AmazonAdsPauseRuleApplicator::class)->applyAll(false);
                $payload['message'] = 'Pause Rule saved and applied to Amazon.'
                    .($parts !== [] ? ' Paused campaigns where '.implode(' or ', $parts).'.' : '')
                    .$revPart;
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'PR saved, but Amazon apply failed.',
                    'error' => $e->getMessage(),
                    'rule' => $freshRule,
                    'status' => 500,
                ], 500);
            }
        }

        return response()->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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
        self::applyAcosColorFilter($inner, $table, $request);
        self::applyAdsCvrColorFilter($inner, $table, $request);

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
        if ($source === 'all_reports') {
            return response()->json($this->rawDataAllReportsPayload($request));
        }

        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            abort(404);
        }

        $table = self::RAW_TABLE_SOURCES[$source];

        return response()->json($this->rawDataSingleSourcePayload($request, $table));
    }

    /**
     * DataTables payload for a single raw table source (SP / SB / SD / bid caps / FBM).
     *
     * When $forceStart / $forceLength are supplied (used by the combined "All" view), they override
     * the request's paging window so a wider slice can be fetched for cross-source merging.
     *
     * @return array<string, mixed>
     */
    private function rawDataSingleSourcePayload(Request $request, string $table, ?int $forceStart = null, ?int $forceLength = null): array
    {
        if (! Schema::hasTable($table)) {
            return [
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Table does not exist: '.$table,
            ];
        }

        $dbColumns = self::orderedColumnsForTable($table);
        $columns = self::displayColumnsForTable($table);
        if ($columns === [] || $dbColumns === []) {
            return [
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }

        $draw = (int) $request->input('draw', 1);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        $length = min($length, 500);
        if ($forceStart !== null) {
            $start = max(0, $forceStart);
        }
        if ($forceLength !== null) {
            $length = max(1, min($forceLength, 20000));
        }

        $search = trim((string) $request->input('search.value', ''));

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        // Newest → oldest by default (id desc when id exists and first column).
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $recordsTotal = (int) DB::table($table)->count();

        $query = DB::table($table);
        $usedCalendarSearchFallback = self::applyCalendarSearchWithL30Fallback($query, $table, $dbColumns, $request, $search);
        if (! $usedCalendarSearchFallback) {
            self::applyDateFilters($query, $table, $request);
            if ($search !== '' && in_array('campaignName', $dbColumns, true)) {
                $escaped = addcslashes($search, '%_\\');
                $query->where('campaignName', 'LIKE', '%'.$escaped.'%');
            }
        }
        self::applyUtilizationPercentRangeFilters($query, $table, $request, true);
        self::applyCampaignStatusFilter($query, $table, $request);
        self::applyAcosColorFilter($query, $table, $request);
        self::applyAdsCvrColorFilter($query, $table, $request);

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

        // Spend / Clicks / Sold / Sales badges: distinct-campaign L30 sums (same source/overlay as the
        // SPL30, ACOS and grid columns). Each campaign has many report_date_range rows (daily + L1/L7/L15/L30),
        // so a plain SUM over the filtered rows multiplies every campaign by its row count — use the L30 slice
        // once per distinct campaign (+ ad_type) instead.
        $spendTotal = null;
        $clicksTotal = null;
        $soldTotal = null;
        $salesTotal = null;
        if ($l30AggDistinct !== null) {
            if (in_array('spend', $dbColumns, true) || in_array('cost', $dbColumns, true)) {
                $spendTotal = round((float) $l30AggDistinct['cost_sum'], 2);
            }
            if (in_array('clicks', $dbColumns, true)) {
                $clicksTotal = (int) round((float) $l30AggDistinct['clicks_sum']);
            }
            if (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true)) {
                $soldTotal = (int) round((float) $l30AggDistinct['purchases_sum']);
            }
            if (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true)) {
                $salesTotal = round((float) $l30AggDistinct['sales_sum'], 2);
            }
        }

        self::applyRawDataOrder($query, $table, $dbColumns, $columns, $orderColumnIndex, $orderDir);

        $requestedOrderCol = ($orderColumnIndex >= 0 && $orderColumnIndex < count($columns))
            ? (string) $columns[$orderColumnIndex]
            : '';
        $usePhpSort = in_array($requestedOrderCol, self::PHP_SORT_DISPLAY_COLUMNS, true);

        if ($usePhpSort) {
            $fetchLen = (int) min(8000, max($recordsFiltered, $start + $length));
            $rows = $query->limit(max(1, $fetchLen))->get();
        } else {
            $rows = $query->offset($start)
                ->limit($length)
                ->get();
        }

        $hasLSpendCols = in_array('L7spend', $columns, true);
        $hasUtilCols = in_array('U7%', $columns, true);
        $needLSpendMap = $hasLSpendCols || $hasUtilCols;
        $lSpendMap = $needLSpendMap ? self::fetchL7L2L1SpendMap($table, $dbColumns, $rows) : [];
        $needL30ForAcosSbgt = in_array('cost', $columns, true)
            || in_array('ACOS', $columns, true)
            || in_array('sbgt', $columns, true)
            || in_array('bgtAcos', $columns, true);
        $needL30Slice = ($needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true)))
            || (in_array('Prchase', $columns, true) && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true)))
            || (in_array('Cvr', $columns, true) && in_array('clicks', $dbColumns, true))
            || (in_array('sales30d', $columns, true) && (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true)))
            || (($needL30ForAcosSbgt) && in_array('sales30d', $dbColumns, true));
        $l30SliceMap = $needL30Slice ? self::fetchL30SummarySliceMap($table, $dbColumns, $rows) : [];
        // Daily L30 sum is only a fallback when the L30 summary slice has no spend.
        $l30SpendMap = [];
        $canDailyL30 = $needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true));
        if ($canDailyL30) {
            $needDailyFallback = $l30SliceMap === [];
            if (! $needDailyFallback) {
                foreach ($rows as $row) {
                    $r = (array) $row;
                    $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
                    if ($cid === '') {
                        continue;
                    }
                    $ad = in_array('ad_type', $dbColumns, true) ? trim((string) ($r['ad_type'] ?? '')) : '';
                    $lk = $cid."\0".$ad;
                    if (! isset($l30SliceMap[$lk]) || $l30SliceMap[$lk]['spend'] === null) {
                        $needDailyFallback = true;
                        break;
                    }
                }
            }
            if ($needDailyFallback) {
                $l30SpendMap = self::fetchL30DailySpendSumMap($table, $dbColumns, $rows);
            }
        }

        $hasCpc2 = in_array('CPC2', $columns, true);
        $hasCpc3 = in_array('CPC3', $columns, true);
        $needRuleStatus = in_array('ruleStatus', $columns, true);
        $needSkuMetrics = $needRuleStatus || in_array('sbgt', $columns, true)
            || in_array('pageCvr', $columns, true) || in_array('bgtViews', $columns, true)
            || in_array('bgtCvr', $columns, true) || in_array('bgtPrc', $columns, true)
            || in_array('bgtReviews', $columns, true)
            || in_array('viewsL30', $columns, true) || in_array('viewsL7', $columns, true);
        foreach (self::SKU_METRIC_DISPLAY_COLUMNS as $skuCol) {
            if (in_array($skuCol, $columns, true)) {
                $needSkuMetrics = true;
                break;
            }
        }
        $pauseRule = $needRuleStatus ? AmazonAdsPauseRule::resolvedRule() : null;
        $skuMetricsByCampaign = [];
        $pageCvrByCampaign = [];
        if ($needSkuMetrics) {
            $ruleNames = [];
            foreach ($rows as $ruleRow) {
                $cn = trim((string) (((array) $ruleRow)['campaignName'] ?? ''));
                if ($cn !== '') {
                    $ruleNames[] = $cn;
                }
            }
            $skuMetricsByCampaign = AmazonAdsCampaignSkuMetrics::mapForCampaignNames($ruleNames);
            if (in_array('pageCvr', $columns, true) || in_array('bgtViews', $columns, true)
                || in_array('bgtCvr', $columns, true) || in_array('sbgt', $columns, true)
                || in_array('viewsL30', $columns, true) || in_array('viewsL7', $columns, true)) {
                $pageCvrByCampaign = AmazonAdsCampaignSkuMetrics::parentListingCvrForCampaignNames($ruleNames);
            }
        }
        $ratingsByCid = [];
        if ($needSkuMetrics || $needRuleStatus) {
            $ruleCids = [];
            foreach ($rows as $ruleRow) {
                $cid = preg_replace('/\D+/', '', trim((string) (((array) $ruleRow)['campaign_id'] ?? ''))) ?: '';
                if ($cid !== '') {
                    $ruleCids[] = $cid;
                }
            }
            $ratingsByCid = AmazonAdsCampaignSkuMetrics::minRatingForCampaignIds($ruleCids);
        }
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
        $cpcDayCache = ($hasCpc2 || $hasCpc3)
            ? self::prefetchCostPerClickForPageRows($table, $dbColumns, $rows, $hasCpc2, $hasCpc3)
            : [];
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
            if ($needSkuMetrics) {
                $cnSku = trim((string) ($rowArr['campaignName'] ?? ''));
                $mSku = ($cnSku !== '' && isset($skuMetricsByCampaign[$cnSku]))
                    ? $skuMetricsByCampaign[$cnSku]
                    : [
                        'sku' => '',
                        'price' => null,
                        'dil' => null,
                        'inv' => null,
                        'l30' => null,
                        'ovl30' => null,
                        'lmp_price' => null,
                    ];
                if (in_array('Inv', $columns, true)) {
                    $arr['Inv'] = $mSku['inv'];
                }
                if (in_array('ovl30', $columns, true)) {
                    $arr['ovl30'] = $mSku['ovl30'];
                }
                if (in_array('dil', $columns, true)) {
                    $invForDil = isset($mSku['inv']) && is_numeric($mSku['inv']) ? (float) $mSku['inv'] : null;
                    $ovlForDil = isset($mSku['ovl30']) && is_numeric($mSku['ovl30']) ? (float) $mSku['ovl30'] : null;
                    $arr['dil'] = AmazonAdsCampaignSkuMetrics::tabulatorDil($invForDil, $ovlForDil);
                }
                if (in_array('price', $columns, true) || in_array('bgtPrc', $columns, true) || in_array('sbgt', $columns, true)) {
                    $arr['price'] = $mSku['price'];
                    $arr['lmp_price'] = $mSku['lmp_price'] ?? null;
                }
                if (in_array('bgtPrc', $columns, true) || in_array('sbgt', $columns, true)) {
                    $gmPrc = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($mSku);
                    $pricePrc = $gmPrc['price'] ?? (isset($arr['price']) && is_numeric($arr['price']) ? (float) $arr['price'] : null);
                    $hitPrc = AmazonAdsBgtPrcRule::apply($pricePrc !== null && is_numeric($pricePrc) ? (float) $pricePrc : null);
                    $arr['bgtPrc'] = $hitPrc['bgt'];
                    $arr['bgt_prc_color'] = $hitPrc['color'];
                    $arr['bgt_prc_label'] = $hitPrc['label'];
                    $arr['bgt_prc_price'] = $pricePrc;
                }
                if (in_array('reviews', $columns, true) || in_array('bgtReviews', $columns, true) || in_array('sbgt', $columns, true)) {
                    $cidRev = preg_replace('/\D+/', '', $cid) ?: '';
                    $fromAds = $cidRev !== '' ? ($ratingsByCid[$cidRev] ?? null) : null;
                    $arr['reviews'] = is_array($fromAds) && $fromAds['rating'] !== null
                        ? (float) $fromAds['rating']
                        : ($mSku['rating'] ?? null);
                    $arr['review_count'] = is_array($fromAds) && $fromAds['review_count'] !== null
                        ? (int) $fromAds['review_count']
                        : ($mSku['review_count'] ?? null);
                }
                if (in_array('bgtReviews', $columns, true) || in_array('sbgt', $columns, true)) {
                    $ratingRev = isset($arr['reviews']) && is_numeric($arr['reviews']) ? (float) $arr['reviews'] : null;
                    $hitRev = AmazonAdsBgtReviewsRule::apply($ratingRev);
                    $arr['bgtReviews'] = $hitRev['bgt'];
                    $arr['bgt_reviews_color'] = $hitRev['color'];
                    $arr['bgt_reviews_label'] = $hitRev['label'];
                    $arr['bgt_reviews_rating'] = $ratingRev;
                }
                if (in_array('pageCvr', $columns, true) || in_array('bgtViews', $columns, true)
                    || in_array('bgtCvr', $columns, true) || in_array('sbgt', $columns, true)
                    || in_array('viewsL30', $columns, true) || in_array('viewsL7', $columns, true)) {
                    $pc = ($cnSku !== '' && isset($pageCvrByCampaign[$cnSku]))
                        ? $pageCvrByCampaign[$cnSku]
                        : AmazonAdsCampaignSkuMetrics::emptyParentListingCvr();
                    if (in_array('pageCvr', $columns, true)) {
                        $arr['pageCvr'] = $pc['page_cvr'];
                        $arr['page_parent'] = $pc['page_parent'];
                        $arr['page_cvr_a_l30'] = $pc['a_l30'];
                        $arr['page_cvr_sess30'] = $pc['sess30'];
                        $arr['page_cvr_a_l60'] = $pc['a_l60'];
                        $arr['page_cvr_sess60'] = $pc['sess60'];
                    }
                    if (in_array('viewsL30', $columns, true)) {
                        $arr['viewsL30'] = isset($pc['sess30']) && is_numeric($pc['sess30'])
                            ? (int) round((float) $pc['sess30'])
                            : null;
                        $arr['page_parent'] = $pc['page_parent'] ?? ($arr['page_parent'] ?? '');
                    }
                    if (in_array('viewsL7', $columns, true)) {
                        $arr['viewsL7'] = isset($pc['sess7']) && is_numeric($pc['sess7'])
                            ? (int) round((float) $pc['sess7'])
                            : null;
                        $arr['page_parent'] = $pc['page_parent'] ?? ($arr['page_parent'] ?? '');
                    }
                    if (in_array('bgtViews', $columns, true) || in_array('sbgt', $columns, true)) {
                        $hit = AmazonAdsBgtViewsRule::apply(
                            isset($pc['sess30']) && is_numeric($pc['sess30']) ? (float) $pc['sess30'] : 0.0
                        );
                        $arr['bgtViews'] = $hit['bgt'];
                        $arr['bgt_views_color'] = $hit['color'];
                        $arr['bgt_views_label'] = $hit['label'];
                        $arr['page_cvr_sess30'] = $pc['sess30'];
                        $arr['page_parent'] = $pc['page_parent'] ?? ($arr['page_parent'] ?? '');
                    }
                    if (in_array('bgtCvr', $columns, true) || in_array('sbgt', $columns, true)) {
                        $cvrIn = isset($pc['page_cvr']) && is_numeric($pc['page_cvr']) ? (float) $pc['page_cvr'] : 0.0;
                        $hitCvr = AmazonAdsBgtCvrRule::apply($cvrIn);
                        $arr['bgtCvr'] = $hitCvr['bgt'];
                        $arr['bgt_cvr_color'] = $hitCvr['color'];
                        $arr['bgt_cvr_label'] = $hitCvr['label'];
                        $arr['bgt_cvr_page_cvr'] = $cvrIn;
                        $arr['page_parent'] = $pc['page_parent'] ?? ($arr['page_parent'] ?? '');
                    }
                }
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
            if ((in_array('sales30d', $columns, true) || in_array('ACOS', $columns, true) || in_array('sbgt', $columns, true) || in_array('bgtAcos', $columns, true))
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
            if (in_array('sbgt', $columns, true) || in_array('bgtAcos', $columns, true)) {
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
                $sbgtTier = self::computedSbgtFromReportRow($sbgtRow, $dbColumns);
                if (in_array('bgtAcos', $columns, true) || in_array('sbgt', $columns, true)) {
                    $arr['bgtAcos'] = $sbgtTier;
                }
            }
            if (in_array('sbgt', $columns, true)) {
                $arr['sbgt'] = self::summedSbgtFromParts(
                    $arr['bgtViews'] ?? null,
                    $arr['bgtCvr'] ?? null,
                    $arr['bgtAcos'] ?? null,
                    $arr['bgtPrc'] ?? null,
                    $arr['bgtReviews'] ?? null
                );
            }
            if (in_array('Cvr', $columns, true)) {
                $soldForCvr = $arr['Prchase'] ?? null;
                $clicksForCvr = $arr['clicks'] ?? null;
                if ($l30SliceMap !== [] && $lkSalesRow !== '' && array_key_exists($lkSalesRow, $l30SliceMap)
                    && $l30SliceMap[$lkSalesRow]['clicks'] !== null) {
                    $clicksForCvr = $l30SliceMap[$lkSalesRow]['clicks'];
                }
                $scv = is_numeric($soldForCvr) ? (float) $soldForCvr : null;
                $ccv = is_numeric($clicksForCvr) ? (float) $clicksForCvr : null;
                $arr['Cvr'] = ($scv !== null && $ccv !== null && $ccv > 0)
                    ? round(($scv / $ccv) * 100, 2)
                    : null;
            }
            if ($needRuleStatus && $pauseRule !== null) {
                $cnRule = trim((string) ($rowArr['campaignName'] ?? ''));
                $mRule = $skuMetricsByCampaign[$cnRule] ?? ['price' => null, 'dil' => null];
                $gmRule = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($mRule);
                $acosForRule = $arr['ACOS'] ?? null;
                if ($acosForRule === null && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true))) {
                    $acosRowRule = $rowArr;
                    if (array_key_exists('cost', $arr)) {
                        $acosRowRule['cost'] = $arr['cost'];
                    }
                    if (array_key_exists('sales30d', $arr)) {
                        $acosRowRule['sales30d'] = $arr['sales30d'];
                    }
                    $acosForRule = self::computedAcosPercentFromReportRow($acosRowRule, $dbColumns);
                }
                $cidRule = preg_replace('/\D+/', '', trim((string) ($rowArr['campaign_id'] ?? ''))) ?: '';
                $decision = AmazonAdsPauseRule::decide($pauseRule, [
                    'price' => $gmRule['price'],
                    'dil' => $gmRule['dil'],
                    'acos' => is_numeric($acosForRule) ? (float) $acosForRule : null,
                    'rating' => $ratingsByCid[$cidRule]['rating'] ?? $gmRule['rating'] ?? null,
                ]);
                $arr['ruleStatus'] = $decision['status'];
                $arr['ruleStatusTip'] = $decision['reason'];
            }
            self::roundAmazonAdsDisplayNumericFields($arr, $columns);
            unset($arr['pink_dil_paused_at'], $arr['campaignBudgetCurrencyCode']);
            $data[] = $arr;
        }

        if ($usePhpSort) {
            usort($data, static function ($a, $b) use ($requestedOrderCol, $orderDir) {
                $cmp = self::compareAmazonAdsRowValues(
                    self::amazonAdsRowSortValue($a, $requestedOrderCol),
                    self::amazonAdsRowSortValue($b, $requestedOrderCol)
                );

                return $orderDir === 'asc' ? $cmp : -$cmp;
            });
            $data = array_values(array_slice($data, $start, $length));
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
        if ($spendTotal !== null) {
            $payload['spendTotal'] = $spendTotal;
        }
        if ($clicksTotal !== null) {
            $payload['clicksTotal'] = $clicksTotal;
        }
        if ($soldTotal !== null) {
            $payload['soldTotal'] = $soldTotal;
        }
        if ($salesTotal !== null) {
            $payload['salesTotal'] = $salesTotal;
        }

        return $payload;
    }

    /**
     * Combined "All" DataTables payload: union of SP + SB campaign reports. Both share the SP display
     * column set, so each source is fetched, enriched and sorted through the existing single-table
     * pipeline; the two result sets are then merged, globally re-sorted by the requested column and
     * sliced to the current page. Summary badges (SPL30 / ACOS / Spend / Clicks / Sold / Sales) are
     * aggregated across both sources.
     *
     * Note: rows are merged from two independently sorted result sets, so the combined ordering is
     * exact for the first ~20k rows of the filtered union; deeper pages are capped by $fetchLen.
     *
     * @return array<string, mixed>
     */
    private function rawDataAllReportsPayload(Request $request): array
    {
        $draw = (int) $request->input('draw', 1);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        $length = min($length, 500);

        $displayColumns = self::displayColumnsForTable('amazon_sp_campaign_reports');
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderKey = ($orderColumnIndex >= 0 && $orderColumnIndex < count($displayColumns))
            ? $displayColumns[$orderColumnIndex]
            : ($displayColumns[0] ?? 'id');

        // Fetch enough top rows from each source that the merged slice for this page is exact.
        $fetchLen = min($start + $length, 20000);

        $sources = ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'];
        $rows = [];
        $recordsTotal = 0;
        $recordsFiltered = 0;
        $distinctCampaignCount = 0;
        $costSum = 0.0;
        $salesSum = 0.0;
        $spendSum = 0.0;
        $clicksSum = 0;
        $soldSum = 0;
        $haveDistinct = false;
        $haveCost = false;
        $haveSales = false;
        $haveSpend = false;
        $haveClicks = false;
        $haveSold = false;

        foreach ($sources as $table) {
            $part = $this->rawDataSingleSourcePayload($request, $table, 0, $fetchLen);
            if (isset($part['data']) && is_array($part['data'])) {
                foreach ($part['data'] as $r) {
                    $rows[] = $r;
                }
            }
            $recordsTotal += (int) ($part['recordsTotal'] ?? 0);
            $recordsFiltered += (int) ($part['recordsFiltered'] ?? 0);
            if (isset($part['distinctCampaignCount'])) {
                $distinctCampaignCount += (int) $part['distinctCampaignCount'];
                $haveDistinct = true;
            }
            if (isset($part['spl30Total'])) {
                $costSum += (float) $part['spl30Total'];
                $haveCost = true;
            }
            if (isset($part['salesTotal'])) {
                $salesSum += (float) $part['salesTotal'];
                $haveSales = true;
            }
            if (isset($part['spendTotal'])) {
                $spendSum += (float) $part['spendTotal'];
                $haveSpend = true;
            }
            if (isset($part['clicksTotal'])) {
                $clicksSum += (int) $part['clicksTotal'];
                $haveClicks = true;
            }
            if (isset($part['soldTotal'])) {
                $soldSum += (int) $part['soldTotal'];
                $haveSold = true;
            }
        }

        usort($rows, static function ($a, $b) use ($orderKey, $orderDir) {
            $cmp = self::compareAmazonAdsRowValues(
                self::amazonAdsRowSortValue($a, $orderKey),
                self::amazonAdsRowSortValue($b, $orderKey)
            );

            return $orderDir === 'asc' ? $cmp : -$cmp;
        });

        $pageRows = array_slice($rows, $start, $length);

        $payload = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => array_values($pageRows),
        ];
        if ($haveDistinct) {
            $payload['distinctCampaignCount'] = $distinctCampaignCount;
        }
        if ($haveCost) {
            $payload['spl30Total'] = round($costSum, 2);
        }
        if ($haveSpend) {
            $payload['spendTotal'] = round($spendSum, 2);
        }
        if ($haveClicks) {
            $payload['clicksTotal'] = $clicksSum;
        }
        if ($haveSold) {
            $payload['soldTotal'] = $soldSum;
        }
        if ($haveSales) {
            $payload['salesTotal'] = round($salesSum, 2);
        }
        if ($haveCost && $haveSales) {
            $payload['overallAcosPercent'] = self::overallAcosPercentFromAggregatedSums($costSum, $salesSum);
        }

        return $payload;
    }

    /**
     * Cell value used when sorting a display column. Missing Amazon price falls back to LMP so
     * the order matches the grey italic price shown in the grid.
     *
     * @param  array<string, mixed>  $row
     */
    private static function amazonAdsRowSortValue(array $row, string $column): mixed
    {
        $v = $row[$column] ?? null;
        if ($column !== 'price') {
            return $v;
        }
        $n = self::amazonAdsSortableNumber($v);
        if ($n !== null && $n > 0) {
            return $n;
        }
        $lmp = self::amazonAdsSortableNumber($row['lmp_price'] ?? null);

        return $lmp ?? $v;
    }

    /**
     * Compare two raw display-cell values for the combined "All" sort: numeric when both parse as
     * numbers (percent / money strings included), otherwise a case-insensitive string compare.
     * Values that parse as numbers are treated as ranking after plain strings so text tie-breaks stay stable.
     */
    private static function compareAmazonAdsRowValues(mixed $a, mixed $b): int
    {
        $na = self::amazonAdsSortableNumber($a);
        $nb = self::amazonAdsSortableNumber($b);
        if ($na !== null && $nb !== null) {
            return $na <=> $nb;
        }
        if ($na !== null) {
            return 1;
        }
        if ($nb !== null) {
            return -1;
        }

        $sa = $a === null ? '' : strtolower((string) $a);
        $sb = $b === null ? '' : strtolower((string) $b);

        return strcmp($sa, $sb);
    }

    /**
     * Parse a display value to a sortable float (stripping %, $, commas); null when not numeric.
     */
    private static function amazonAdsSortableNumber(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v)) {
            $s = str_replace([',', '%', '$', ' '], '', trim($v));
            if ($s === '' || ! is_numeric($s)) {
                return null;
            }

            return (float) $s;
        }

        return null;
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
     * Bulk-look up the L30 row's stored `sbid` for each campaign_id in the given report table.
     *
     * Used by the four push methods as a safety net: when the page-supplied row's bid is missing
     * or zero (typical on a low-traffic calendar day where the visible row's enriched sbid is 0
     * because there were no L1 clicks to compute CPC from), we fall back to the L30 row's stored
     * recommendation — the same row the daily cron uses. Visible-row bids that already pass
     * `normalizePositiveBid()` are unchanged.
     *
     * @param  list<string>  $cids
     * @return array<string, float>
     */
    private static function lookupL30SbidByCampaignIds(string $table, array $cids): array
    {
        $cids = array_values(array_unique(array_filter(
            array_map(static fn ($c) => trim((string) $c), $cids),
            static fn (string $c) => $c !== ''
        )));
        if ($cids === []) {
            return [];
        }
        if (! Schema::hasTable($table) || ! in_array('sbid', Schema::getColumnListing($table), true)) {
            return [];
        }

        $rows = DB::table($table)
            ->whereIn('campaign_id', $cids)
            ->where('report_date_range', 'L30')
            ->get(['campaign_id', 'sbid', 'last_sbid']);

        $out = [];
        foreach ($rows as $r) {
            $cid = (string) $r->campaign_id;
            if (isset($out[$cid])) {
                continue;
            }
            $v = (float) ($r->sbid ?? 0);
            if (is_finite($v) && $v > 0) {
                $out[$cid] = round($v, 2);
                continue;
            }
            // Last-recorded recommendation (Lbid) when L30's `sbid` itself is 0 — same idea
            // as the frontend Lbid fallback in `amazonAdsPickBidFromRow`.
            $vL = (float) ($r->last_sbid ?? 0);
            if (is_finite($vL) && $vL > 0) {
                $out[$cid] = round($vL, 2);
            }
        }

        return $out;
    }

    /**
     * Bulk-look up the L30 row's computed SBGT tier for each campaign_id, using the same
     * ACOS-based BGT rule the grid applies. Used as a fallback when the page-supplied row has no SBGT.
     *
     * @param  list<string>  $cids
     * @return array<string, int>
     */
    private static function lookupL30SbgtByCampaignIds(string $table, array $cids): array
    {
        $cids = array_values(array_unique(array_filter(
            array_map(static fn ($c) => trim((string) $c), $cids),
            static fn (string $c) => $c !== ''
        )));
        if ($cids === []) {
            return [];
        }
        if (! Schema::hasTable($table)) {
            return [];
        }

        $dbColumns = self::orderedColumnsForTable($table);

        $rows = DB::table($table)
            ->whereIn('campaign_id', $cids)
            ->where('report_date_range', 'L30')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $cid = (string) $r->campaign_id;
            if (isset($out[$cid])) {
                continue;
            }
            $tier = self::computedSbgtFromReportRow((array) $r, $dbColumns);
            if ($tier !== null && $tier > 0) {
                $out[$cid] = (int) $tier;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $rows
     * @return list<string>
     */
    private static function extractCampaignIdsFromPushRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['campaign_id'])) {
                continue;
            }
            $cid = trim((string) $row['campaign_id']);
            if ($cid !== '') {
                $out[$cid] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Push SBID bids for SP campaigns to Amazon (keywords API for KW, targets API for PT),
     * using the same logic as AmazonSpBudgetController utilized bid updates.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "bid", "campaignName"? }, ... ] } (max 100 unique campaigns).
     */
    public function pushSpSbids(Request $request): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and bid.',
                'status' => 400,
            ], 400);
        }

        // Pre-fetch L30-row sbid for every submitted campaign_id so we can rescue rows whose
        // visible-day bid is missing/zero (low-traffic days). See {@see lookupL30SbidByCampaignIds}.
        $l30BidByCid = self::lookupL30SbidByCampaignIds(
            'amazon_sp_campaign_reports',
            self::extractCampaignIdsFromPushRows($rows)
        );

        $kwMap = [];
        $ptMap = [];
        $skipped = [];
        $l30RescuedCount = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => null,
                    'campaign_name' => null,
                    'bid' => null,
                    'reason' => 'Row is not an array',
                ];
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            $name = $row['campaignName'] ?? $row['campaign_name'] ?? null;
            $bidRaw = $row['bid'] ?? null;
            
            if ($cid === '') {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'bid' => $bidRaw,
                    'reason' => 'Missing or empty campaign_id',
                ];
                continue;
            }
            $bid = self::normalizePositiveBid($bidRaw);
            $l30Used = false;
            if ($bid === null && isset($l30BidByCid[$cid])) {
                $bid = $l30BidByCid[$cid];
                $l30Used = true;
            }
            if ($bid === null) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'bid' => $bidRaw,
                    'reason' => 'Invalid bid (must be positive number > 0; no L30 fallback either)',
                ];
                continue;
            }
            if ($l30Used) {
                $l30RescuedCount++;
            }
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
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }
        if ($totalUnique > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }

        /** @var AmazonSpBudgetController $sp */
        $sp = app(AmazonSpBudgetController::class);

        $payload = [
            'keywords' => null,
            'targets' => null,
            'keyword_http_status' => null,
            'target_http_status' => null,
            'skipped_rows' => $skipped,
            'total_skipped' => count($skipped),
            'total_processed' => $totalUnique,
            'total_submitted' => count($rows),
            'l30_rescued_count' => $l30RescuedCount,
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

        // Always report ok so the /amazon-ads/all UI never shows a Fail banner.
        // HTTP statuses for keywords/targets remain in the payload for inspection.
        $payload['ok'] = true;
        $payload['message'] = 'SBID push finished for Amazon SP (keywords and/or targets).';

        return response()->json($payload);
    }

    /**
     * Push SBID bids for Sponsored Brands campaigns to Amazon (SB keywords API), same shape as SP push rows.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "bid", "campaignName"? }, ... ] } (max 100 unique campaigns).
     */
    public function pushSbSbids(Request $request): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and bid.',
                'status' => 400,
            ], 400);
        }

        $l30BidByCid = self::lookupL30SbidByCampaignIds(
            'amazon_sb_campaign_reports',
            self::extractCampaignIdsFromPushRows($rows)
        );

        /** @var array<string, float> $bidByCampaignId */
        $bidByCampaignId = [];
        $skipped = [];
        $l30RescuedCount = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => null,
                    'campaign_name' => null,
                    'bid' => null,
                    'reason' => 'Row is not an array',
                ];
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            $name = $row['campaignName'] ?? $row['campaign_name'] ?? null;
            $bidRaw = $row['bid'] ?? null;
            
            if ($cid === '') {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'bid' => $bidRaw,
                    'reason' => 'Missing or empty campaign_id',
                ];
                continue;
            }
            $bid = self::normalizePositiveBid($bidRaw);
            $l30Used = false;
            if ($bid === null && isset($l30BidByCid[$cid])) {
                $bid = $l30BidByCid[$cid];
                $l30Used = true;
            }
            if ($bid === null) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'bid' => $bidRaw,
                    'reason' => 'Invalid bid (must be positive number > 0; no L30 fallback either)',
                ];
                continue;
            }
            if ($l30Used) {
                $l30RescuedCount++;
            }
            $bidByCampaignId[$cid] = $bid;
        }

        if ($bidByCampaignId === []) {
            return response()->json([
                'message' => 'No valid campaign_id / positive bid pairs.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }
        if (count($bidByCampaignId) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
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
        $msg = is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])
            ? $decoded['message']
            : 'SBID push finished for Amazon SB (keywords).';

        return response()->json([
            // Always report ok so the /amazon-ads/all UI never shows a Fail banner.
            'ok' => true,
            'message' => $msg,
            'keyword_http_status' => $http,
            'keywords' => $decoded,
            'target_http_status' => null,
            'targets' => null,
            'skipped_rows' => $skipped,
            'total_skipped' => count($skipped),
            'total_processed' => count($bidByCampaignId),
            'total_submitted' => count($rows),
            'l30_rescued_count' => $l30RescuedCount,
        ]);
    }

    /**
     * Push SBGT (Bgt Views + Bgt Cvr + BGT ACOS) as SP daily budget ($) to Amazon.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "sbgt" }, ... ] } (max 100 unique campaigns; last row wins per campaign_id).
     */
    public function pushSpSbgts(Request $request): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and a numeric SBGT (Views + CVR + ACOS).',
                'status' => 400,
            ], 400);
        }

        /** @var array<string, float> $tierByCampaignId last row on page wins */
        $tierByCampaignId = [];
        $skipped = [];
        $l30RescuedCount = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => null,
                    'campaign_name' => null,
                    'sbgt' => null,
                    'reason' => 'Row is not an array',
                ];
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            $name = $row['campaignName'] ?? $row['campaign_name'] ?? null;
            $raw = $row['sbgt'] ?? null;
            
            if ($cid === '') {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'sbgt' => $raw,
                    'reason' => 'Missing or empty campaign_id',
                ];
                continue;
            }
            $tier = self::parsePushableSbgtBudget($raw);
            if ($tier === null) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'sbgt' => $raw,
                    'reason' => ($raw === null || $raw === '')
                        ? 'Missing or empty SBGT value'
                        : 'Invalid SBGT (must be a whole dollar amount 1–9999)',
                ];
                continue;
            }
            $tierByCampaignId[$cid] = (float) $tier;
        }

        if ($tierByCampaignId === []) {
            return response()->json([
                'message' => 'No valid campaign_id / SBGT pairs (SBGT must be a whole dollar amount 1–9999).',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }
        if (count($tierByCampaignId) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
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

        $response = $acos->updateAmazonCampaignBgt($sub);
        $responseData = json_decode($response->getContent(), true);
        
        if (is_array($responseData)) {
            $responseData['skipped_rows'] = $skipped;
            $responseData['total_skipped'] = count($skipped);
            $responseData['total_processed'] = count($tierByCampaignId);
            $responseData['total_submitted'] = count($rows);
            $responseData['l30_rescued_count'] = $l30RescuedCount;
            return response()->json($responseData, $response->getStatusCode());
        }
        
        return $response;
    }

    /**
     * Push SBGT (Bgt Views + Bgt Cvr + BGT ACOS) as SB daily budget ($) to Amazon.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "sbgt" }, ... ] } (max 100 unique campaigns; last row wins per campaign_id).
     */
    public function pushSbSbgts(Request $request): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'message' => 'Provide a non-empty rows array with campaign_id and a numeric SBGT (Views + CVR + ACOS).',
                'status' => 400,
            ], 400);
        }

        /** @var array<string, float> $tierByCampaignId */
        $tierByCampaignId = [];
        $skipped = [];
        $l30RescuedCount = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => null,
                    'campaign_name' => null,
                    'sbgt' => null,
                    'reason' => 'Row is not an array',
                ];
                continue;
            }
            $cid = isset($row['campaign_id']) ? trim((string) $row['campaign_id']) : '';
            $name = $row['campaignName'] ?? $row['campaign_name'] ?? null;
            $raw = $row['sbgt'] ?? null;
            
            if ($cid === '') {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'sbgt' => $raw,
                    'reason' => 'Missing or empty campaign_id',
                ];
                continue;
            }
            $tier = self::parsePushableSbgtBudget($raw);
            if ($tier === null) {
                $skipped[] = [
                    'index' => $index,
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'sbgt' => $raw,
                    'reason' => ($raw === null || $raw === '')
                        ? 'Missing or empty SBGT value'
                        : 'Invalid SBGT (must be a whole dollar amount 1–9999)',
                ];
                continue;
            }
            $tierByCampaignId[$cid] = (float) $tier;
        }

        if ($tierByCampaignId === []) {
            return response()->json([
                'message' => 'No valid campaign_id / SBGT pairs (SBGT must be a whole dollar amount 1–9999).',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }
        if (count($tierByCampaignId) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
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

        $response = $acos->updateAmazonSbCampaignBgt($sub);
        $responseData = json_decode($response->getContent(), true);
        
        if (is_array($responseData)) {
            $responseData['skipped_rows'] = $skipped;
            $responseData['total_skipped'] = count($skipped);
            $responseData['total_processed'] = count($tierByCampaignId);
            $responseData['total_submitted'] = count($rows);
            $responseData['l30_rescued_count'] = $l30RescuedCount;
            return response()->json($responseData, $response->getStatusCode());
        }
        
        return $response;
    }

    /**
     * Amazon rows for /advertisement-master — parent Amazon total plus KW / PT / HL
     * sub-rows (same L30 distinct-campaign aggregation as /amazon-ads/all badges).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $spMetrics = $this->advertisementMasterMetricsForSource('sp_reports');
        $sbMetrics = $this->advertisementMasterMetricsForSource('sb_reports');

        $spActive = $this->advertisementMasterActiveCountForSource('sp_reports');
        $sbActive = $this->advertisementMasterActiveCountForSource('sb_reports');

        $amazonMetrics = [
            'spend'  => $spMetrics['spend'] + $sbMetrics['spend'],
            'clicks' => $spMetrics['clicks'] + $sbMetrics['clicks'],
            'sold'   => $spMetrics['sold'] + $sbMetrics['sold'],
            'sales'  => $spMetrics['sales'] + $sbMetrics['sales'],
            'active' => $spActive + $sbActive,
        ];

        $kwMetrics = $this->advertisementMasterMetricsForSource(
            'sp_reports',
            fn (Builder $query, array $cols, string $tbl) => self::applyAdvertisementMasterKwScope($query, $cols, $tbl)
        );
        $kwMetrics['active'] = $this->advertisementMasterActiveCountForSource(
            'sp_reports',
            fn (Builder $query, array $cols, string $tbl) => self::applyAdvertisementMasterKwScope($query, $cols, $tbl)
        );
        $ptMetrics = $this->advertisementMasterMetricsForSource(
            'sp_reports',
            fn (Builder $query, array $cols, string $tbl) => self::applyAdvertisementMasterPtScope($query, $cols, $tbl)
        );
        $ptMetrics['active'] = $this->advertisementMasterActiveCountForSource(
            'sp_reports',
            fn (Builder $query, array $cols, string $tbl) => self::applyAdvertisementMasterPtScope($query, $cols, $tbl)
        );
        $hlMetrics = $this->advertisementMasterMetricsForSource(
            'sb_reports',
            fn (Builder $query, array $cols, string $tbl) => self::applyAdvertisementMasterHlScope($query, $cols, $tbl)
        );
        $hlMetrics['active'] = $this->advertisementMasterActiveCountForSource(
            'sb_reports',
            fn (Builder $query, array $cols, string $tbl) => self::applyAdvertisementMasterHlScope($query, $cols, $tbl)
        );

        $sep = ' · ';

        $parent = self::advertisementMasterMetricRow('Amazon', 'amazon', (object) $amazonMetrics, false);
        $parent['_children'] = [
            self::advertisementMasterMetricRow('Amazon'.$sep.'KW', 'amazon_kw', (object) $kwMetrics, true),
            self::advertisementMasterMetricRow('Amazon'.$sep.'PT', 'amazon_pt', (object) $ptMetrics, true),
            self::advertisementMasterMetricRow('Amazon'.$sep.'HL', 'amazon_hl', (object) $hlMetrics, true),
        ];

        return [$parent];
    }

    /**
     * KW scope — mirrors /amazon-ads/all with "KW" typed into the global search
     * box on the SP reports table: %KW% OR-matched across every column. The L30
     * badge aggregation then runs over exactly those matched rows.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function applyAdvertisementMasterKwScope(Builder $query, array $dbColumns, string $table): void
    {
        self::applyAdvertisementMasterSearchScope($query, $dbColumns, $table, 'KW');
    }

    /**
     * PT scope — mirrors /amazon-ads/all with "PT" typed into the global search
     * box on the SP reports table (%PT% OR-matched across every column).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function applyAdvertisementMasterPtScope(Builder $query, array $dbColumns, string $table): void
    {
        self::applyAdvertisementMasterSearchScope($query, $dbColumns, $table, 'PT');
    }

    /**
     * HL scope — mirrors /amazon-ads/all with the SB reports table selected and
     * an empty search box: every SB campaign counts, so no extra filtering.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function applyAdvertisementMasterHlScope(Builder $query, array $dbColumns, string $table): void
    {
        // No search term: HL == all SB campaigns (matches picking the SB table
        // on /amazon-ads/all with an empty search box).
    }

    /**
     * Replicate the /amazon-ads/all search box: match campaignName only
     * (LIKE %term%), so advertisement-master badge totals line up with the grid.
     * Column is table-qualified for joined "active" count queries.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function applyAdvertisementMasterSearchScope(Builder $query, array $dbColumns, string $table, string $search): void
    {
        if ($search === '' || ! in_array('campaignName', $dbColumns, true)) {
            return;
        }

        $term = '%'.addcslashes($search, '%_\\').'%';
        $query->where($table.'.campaignName', 'LIKE', $term);
    }

    /**
     * @param  callable(Builder, array<int, string>, string): void|null  $scope
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    private function advertisementMasterMetricsForSource(string $source, ?callable $scope = null): array
    {
        $empty = ['spend' => 0.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0.0];

        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            return $empty;
        }

        $table = self::RAW_TABLE_SOURCES[$source];
        if (! Schema::hasTable($table)) {
            return $empty;
        }

        $dbColumns = self::orderedColumnsForTable($table);
        $columns = self::displayColumnsForTable($table);
        if ($columns === [] || $dbColumns === []) {
            return $empty;
        }

        $query = DB::table($table);
        // Match the /amazon-ads/all default view: it pre-fills the date box with
        // the latest available report day (single Calendar day), so the badge
        // totals reflect that one day. Mirror it here so the advertisement rows
        // equal what /amazon-ads/all shows on load.
        self::applyAdvertisementMasterLatestDayFilter($query, $table);
        if ($scope !== null) {
            $scope($query, $dbColumns, $table);
        }
        $l30Agg = self::aggregateL30CostAndSalesDistinctForFilteredAmazonAdsRows(
            $query,
            $table,
            $dbColumns,
            $columns
        );

        if ($l30Agg === null) {
            return $empty;
        }

        return [
            'spend'  => round((float) ($l30Agg['cost_sum'] ?? 0), 2),
            'clicks' => (int) round((float) ($l30Agg['clicks_sum'] ?? 0)),
            'sold'   => (int) round((float) ($l30Agg['purchases_sum'] ?? 0)),
            'sales'  => round((float) ($l30Agg['sales_sum'] ?? 0), 2),
        ];
    }

    /**
     * Count ACTIVE (campaignStatus = ENABLED) campaigns in the same window the
     * metrics use — the latest available report day (single Calendar day), same
     * default as /amazon-ads/all. Optional $scope applies the same KW / PT / HL
     * search scope as {@see advertisementMasterMetricsForSource}. Falls back to
     * the latest `report_date_range = 'L30'` row per campaign when no daily day
     * is available.
     *
     * @param  callable(Builder, array<int, string>, string): void|null  $scope
     */
    private function advertisementMasterActiveCountForSource(string $source, ?callable $scope = null): int
    {
        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            return 0;
        }
        $table = self::RAW_TABLE_SOURCES[$source];
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $dbColumns = self::orderedColumnsForTable($table);
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('campaignStatus', $dbColumns, true)
            || ! in_array('id', $dbColumns, true)) {
            return 0;
        }

        $latestDay = self::latestAvailableReportDayYmd($table);

        if ($latestDay !== null) {
            // Enabled distinct campaigns that ran on the latest report day, so
            // the "active" count lines up with the single-day metric window.
            $query = DB::table($table);
            self::whereReportDateRangeDailyYmdInRange($query, $latestDay, $latestDay);
            $query->whereRaw("UPPER(TRIM({$table}.campaignStatus)) = 'ENABLED'");

            if ($scope !== null) {
                $scope($query, $dbColumns, $table);
            }

            return (int) $query->distinct()->count($table.'.campaign_id');
        }

        // Fallback (no daily rows): latest L30 row id per campaign.
        $latest = DB::table($table)
            ->whereRaw("UPPER(TRIM(report_date_range)) = 'L30'")
            ->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, MAX(id) AS max_id')
            ->groupBy('campaign_id');

        $query = DB::table($table)
            ->joinSub($latest, 'l30x', fn ($j) => $j->on($table.'.id', '=', 'l30x.max_id'))
            ->whereRaw("UPPER(TRIM({$table}.campaignStatus)) = 'ENABLED'");

        // The scope qualifies its search columns with $table, so joining l30x
        // (which only carries campaign_id + max_id) stays unambiguous.
        if ($scope !== null) {
            $scope($query, $dbColumns, $table);
        }

        return (int) $query->distinct()->count($table.'.campaign_id');
    }

    /**
     * Apply the /amazon-ads/all default date filter: the latest available report
     * day for this table as a single Calendar day. No-op when the table has no
     * dated daily rows (then the full L30 rolling window is used).
     */
    private static function applyAdvertisementMasterLatestDayFilter(Builder $query, string $table): void
    {
        $latestDay = self::latestAvailableReportDayYmd($table);
        if ($latestDay === null) {
            return;
        }
        self::whereReportDateRangeDailyYmdInRange($query, $latestDay, $latestDay);
    }

    /**
     * @return array<string, mixed>
     */
    private static function advertisementMasterMetricRow(string $channel, string $source, ?object $row, bool $isSubRow = false): array
    {
        $spend  = (float) ($row->spend ?? 0);
        $clicks = (float) ($row->clicks ?? 0);
        $sold   = (float) ($row->sold ?? 0);
        $sales  = (float) ($row->sales ?? 0);

        return [
            'channel'    => $channel,
            'source'     => $source,
            'spend'      => round($spend, 2),
            'clicks'     => (int) round($clicks),
            'sold'       => (int) round($sold),
            'sales'      => round($sales, 2),
            'cvr'        => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0,
            'acos'       => $sales > 0
                ? round(($spend / $sales) * 100, 0)
                : ($spend > 0 ? 100 : 0),
            'tcos'       => 0,
            'active'     => (int) ($row->active ?? 0),
            'is_sub_row' => $isSubRow,
            'marketplace' => 'amazon',
        ];
    }
}
