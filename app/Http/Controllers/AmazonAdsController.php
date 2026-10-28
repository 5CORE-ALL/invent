<?php

namespace App\Http\Controllers;

use App\Support\SchemaMemo;
use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Services\AmazonAdsLiveBidBgtSyncService;
use App\Models\AmazonAdsLiveSyncState;
use App\Models\AmazonAdsPauseRuleState;
use App\Models\AmazonDatasheet;
use App\Models\ShopifySku;
use App\Services\AmazonAdsPauseRuleApplicator;
use App\Support\AmazonAdsBgtCountRunner;
use App\Support\AmazonAdsBgtCountStore;
use App\Support\AmazonAdsBgtCvrRule;
use App\Support\AmazonAdsBgtDilRule;
use App\Support\AmazonAdsBgtFlagRule;
use App\Support\AmazonAdsBgtGpftRule;
use App\Support\AmazonAdsBgtInvRule;
use App\Support\AmazonAdsBgtPrcRule;
use App\Support\AmazonAdsBgtReviewsRule;
use App\Support\AmazonAdsBgtSpendRule;
use App\Support\AmazonAdsBgtViewsRule;
use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\AmazonAdsCampaignSkuSync;
use App\Support\AmazonAdsLbidDaily;
use App\Support\AmazonAdsLRangeMetrics;
use App\Support\AmazonAdsYesterdayBadgeHistory;
use App\Support\AmazonAdsPauseRule;
use App\Support\AmazonAdsSbAdEditor;
use App\Support\AmazonAdsSbidRule;
use App\Support\AmazonAdsLiveSyncFollowUp;
use App\Support\AmazonAdsLiveSyncStatus;
use App\Support\AmazonAdsSbgt;
use App\Support\AmazonAdsStoredLiveBid;
use App\Support\AmazonAdsTargetCounts;
use App\Support\AmazonAcosSbgtRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmazonAdsController extends Controller
{
    /** Request attribute (not HTTP input) that lets a sbid_universe call save every campaign's SBID. */
    private const PERSIST_SBID_UNIVERSE_ATTR = 'amazon_ads_persist_sbid_universe';

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

    /** Inv / ovl30 / dil / price / gpft / reviews on each campaign row, after campaignName. */
    private const SKU_METRIC_DISPLAY_COLUMNS = ['Inv', 'ovl30', 'dil', 'price', 'gpft', 'reviews'];

    /**
     * Display columns computed after SQL (Shopify metrics, L-spend overlays, pause Rule).
     * Sorted in PHP over the filtered window so header click order matches the grid.
     *
     * @var list<string>
     */
    private const PHP_SORT_DISPLAY_COLUMNS = [
        'Inv', 'INV', 'ovl30', 'dil', 'price', 'reviews', 'ruleStatus', 'activeAgain', 'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'bgtDil', 'bgtInv', 'gpft', 'bgtGpft', 'bgtFlag', 'sbgt',
        'U7%', 'U2%', 'U1%', 'CPC3', 'CPCAvg', 'CPC2', 'ltCvr',
        'L7spend', 'L2spend', 'L1spend', 'L1cost', 'L1clicks',
        'projectedSpend', 'projectedSales', 'ySpend', 'ySales', 'yAcos',
        'pageCvr', 'viewsL30', 'viewsL7',
        'ACOS', 'acosGpft', 'acosFlag', 'ltAcos', 'ltSpend', 'ltSales', 'ltSold', 'ltCps', 'ltTcps', 'ltTcos', 'ltCpc',
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
        if (! SchemaMemo::hasTable($table)) {
            return [];
        }

        $cols = SchemaMemo::getColumnListing($table);

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
     * `campaignStatus` (Stat) sits immediately before `bgt` (Lbgt); `ruleStatus` follows Stat; `activeAgain` is the last column; `sbgt` and `sbgtAlert` sit beside Lbgt, then `bgtAcos`, `bgtViews`, `bgtCvr`, `bgtPrc`, `bgtReviews`, `bgtDil`, `bgtInv`.
     * `sbidHistory` sits beside SBID and `sbgtHistory` beside SBGT. Both open the daily history chart.
     * Lbid has no column of its own: its history dot sits inside the Lbid cell.
     */
    private static function displayColumnsForTable(string $table): array
    {
        // Keyword performance / negative keyword sources use a fixed keyword-level column set
        // (not the campaign-report overlays). Only keep columns that actually exist on the table.
        if (isset(self::KEYWORD_SOURCE_DISPLAY_COLUMNS[$table])) {
            if (! SchemaMemo::hasTable($table)) {
                return [];
            }
            $existing = SchemaMemo::getColumnListing($table);

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

        // Targets and N Target sit immediately after SBID. Counts come from synced report tables.
        if (in_array('sbid', $ordered, true)
            && in_array('campaign_id', $ordered, true)
            && in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports', 'amazon_sd_campaign_reports'], true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'targets' && $c !== 'nTargets'));
            $idxSbidTargets = array_search('sbid', $ordered, true);
            if ($idxSbidTargets !== false) {
                array_splice($ordered, $idxSbidTargets + 1, 0, ['targets', 'nTargets']);
            }
        }

        // SBID alert sits beside SBID. SBGT has its own alert beside SBGT.
        if (in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)
            && in_array('sbid', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'pushAlert'));
            $idxAlert = array_search('sbid', $ordered, true);
            if ($idxAlert !== false) {
                array_splice($ordered, $idxAlert + 1, 0, ['pushAlert']);
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

        // Rule Status immediately after Stat. Active Again is appended at the end.
        if (($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports')
            && in_array('campaignStatus', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'ruleStatus' && $c !== 'activeAgain'));
            $idxStatForRule = array_search('campaignStatus', $ordered, true);
            if ($idxStatForRule !== false) {
                array_splice($ordered, $idxStatForRule + 1, 0, ['ruleStatus']);
            }
        }

        // Lbgt, SBGT, and the SBGT alert sit together. The budget parts follow that pair.
        if (in_array('campaignBudgetAmount', self::orderedColumnsForTable($table), true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'bgtAcos' && $c !== 'sbgt' && $c !== 'sbgtAlert' && $c !== 'bgtViews' && $c !== 'bgtCvr' && $c !== 'bgtPrc' && $c !== 'bgtReviews' && $c !== 'bgtDil' && $c !== 'bgtInv' && $c !== 'bgtGpft' && $c !== 'bgtFlag'));
            $idxBgtForSbgt = array_search('bgt', $ordered, true);
            if ($idxBgtForSbgt !== false) {
                $budgetTail = ['sbgt', 'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'bgtDil', 'bgtInv', 'bgtGpft', 'bgtFlag'];
                if (in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
                    $budgetTail = ['sbgt', 'sbgtAlert', 'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'bgtDil', 'bgtInv', 'bgtGpft', 'bgtFlag'];
                }
                array_splice($ordered, $idxBgtForSbgt + 1, 0, $budgetTail);
            }
        }

        // History dots sit on the value they chart. They are not database columns.
        if (in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports', 'amazon_sd_campaign_reports'], true)) {
            if (in_array('sbid', $ordered, true)) {
                $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'sbidHistory'));
                $idxSbidHist = array_search('sbid', $ordered, true);
                if ($idxSbidHist !== false) {
                    array_splice($ordered, $idxSbidHist + 1, 0, ['sbidHistory']);
                }
            }
            if (in_array('sbgt', $ordered, true)) {
                $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'sbgtHistory'));
                $idxSbgtHist = array_search('sbgt', $ordered, true);
                if ($idxSbgtHist !== false) {
                    array_splice($ordered, $idxSbgtHist + 1, 0, ['sbgtHistory']);
                }
            }
        }

        $idxBgt = array_search('bgt', $ordered, true);
        if ($idxBgt !== false && in_array('clicks', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'clicks'));
            $idxAfterBgt = array_search('bgtFlag', $ordered, true);
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('bgtGpft', $ordered, true);
            }
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('bgtInv', $ordered, true);
            }
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('bgtDil', $ordered, true);
            }
            if ($idxAfterBgt === false) {
                $idxAfterBgt = array_search('sbgt', $ordered, true);
            }
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

        // CPC 3 / Avg / 2 / 1 after L1 spend. CPC Avg is lifetime cost ÷ clicks from daily API rows. SP uses `costPerClick`; SB CPC1 uses L1 summary cost ÷ clicks; CPC2/CPC3 use daily row lookups.
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
                array_splice($ordered, $idxL1 + 1, 0, ['CPC3', 'CPCAvg', 'CPC2', 'costPerClick']);
            } else {
                $idxU1Fallback = array_search('U1%', $ordered, true);
                if ($idxU1Fallback !== false) {
                    array_splice($ordered, $idxU1Fallback + 1, 0, ['CPC3', 'CPCAvg', 'CPC2', 'costPerClick']);
                } else {
                    $ordered[] = 'CPC3';
                    $ordered[] = 'CPCAvg';
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

        if ($supportLSpend && ($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports')) {
            $ordered = array_values(array_filter(
                $ordered,
                static fn (string $c): bool => ! in_array($c, ['projectedSpend', 'projectedSales', 'ySpend', 'ySales', 'yAcos'], true)
            ));
            $idxAfterSl = array_search('sales30d', $ordered, true);
            if ($idxAfterSl === false) {
                $idxAfterSl = array_search('cost', $ordered, true);
            }
            if ($idxAfterSl !== false) {
                array_splice($ordered, $idxAfterSl + 1, 0, ['projectedSpend', 'projectedSales', 'ySpend', 'ySales', 'yAcos']);
            }
        }

        // ACOS (%) = cost / sales * 100 — spend + Ads Sold 0 is saved as 100% for BGT / SBGT.
        // LT ACOS is lifetime cost ÷ lifetime sales on daily API rows, immediately after ACOS%. (The duplicate LT "ACOS %" column, ltCps, is no longer shown.)
        // Y ACOS sits just before ACOS% so yesterday's spend ÷ sales stays next to Y Sales.
        // acosGpft (GPFT%, the campaign's SKU / parent GPFT) sits right before ACOS%; the Flag column (acosFlag: ACOS% < GPFT%) sits right after it.
        $canAcos = in_array('cost', $baseCols, true)
            && (in_array('sales30d', $baseCols, true) || in_array('sales', $baseCols, true));
        if ($canAcos) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => ! in_array($c, ['acosGpft', 'ACOS', 'acosFlag', 'ltAcos', 'ltSpend', 'ltSales', 'ltSold', 'ltCps', 'ltTcps', 'ltTcos', 'ltCpc'], true)));
            $idxSales30 = array_search('yAcos', $ordered, true);
            if ($idxSales30 === false) {
                $idxSales30 = array_search('ySales', $ordered, true);
            }
            if ($idxSales30 === false) {
                $idxSales30 = array_search('sales30d', $ordered, true);
            }
            if ($idxSales30 !== false) {
                array_splice($ordered, $idxSales30 + 1, 0, ['acosGpft', 'ACOS', 'acosFlag', 'ltAcos', 'ltSpend', 'ltSales', 'ltSold', 'ltTcos', 'ltCpc', 'ltTcps']);
            } else {
                $idxSales = array_search('sales', $ordered, true);
                if ($idxSales !== false) {
                    array_splice($ordered, $idxSales + 1, 0, ['acosGpft', 'ACOS', 'acosFlag', 'ltAcos', 'ltSpend', 'ltSales', 'ltSold', 'ltTcos', 'ltCpc', 'ltTcps']);
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
        // LT CVR is lifetime sold ÷ lifetime clicks on daily API rows, immediately after Ads CVR.
        if (in_array('Prchase', $ordered, true) && in_array('clicks', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'Cvr' && $c !== 'ltCvr'));
            $idxPrchaseForCvr = array_search('Prchase', $ordered, true);
            if ($idxPrchaseForCvr !== false) {
                array_splice($ordered, $idxPrchaseForCvr + 1, 0, ['Cvr', 'ltCvr']);
            }
        }

        // Listing CVR + parent View L30 / View L7 from /amazon-tabulator-view, after LT CVR.
        if ($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports') {
            $ordered = array_values(array_filter(
                $ordered,
                static fn (string $c): bool => ! in_array($c, ['pageCvr', 'viewsL30', 'viewsL7'], true)
            ));
            $idxPageCvr = array_search('ltCvr', $ordered, true);
            if ($idxPageCvr === false) {
                $idxPageCvr = array_search('Cvr', $ordered, true);
            }
            if ($idxPageCvr === false) {
                $idxPageCvr = array_search('Prchase', $ordered, true);
            }
            if ($idxPageCvr !== false) {
                array_splice($ordered, $idxPageCvr + 1, 0, ['pageCvr', 'viewsL30', 'viewsL7']);
            }
            if (in_array('viewsL7', $ordered, true) && in_array('clicks', $ordered, true)) {
                $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'clicks'));
                $idxViewsL7 = array_search('viewsL7', $ordered, true);
                if ($idxViewsL7 !== false) {
                    array_splice($ordered, $idxViewsL7 + 1, 0, ['clicks']);
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

            return self::withActiveAgainLast($filtered);
        }

        if ($table === 'amazon_sp_campaign_reports') {
            return self::withActiveAgainLast(self::withSkuMetricColumnsAfterCampaignName($ordered));
        }

        return $ordered;
    }

    /**
     * Active Again is the last grid column (green dot; full text on hover).
     *
     * @param  list<string>  $ordered
     * @return list<string>
     */
    private static function withActiveAgainLast(array $ordered): array
    {
        $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'activeAgain'));
        $ordered[] = 'activeAgain';

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
    private static function computedSbgtFromReportRow(array $rowArr, array $dbColumns, ?float $acos = null): ?int
    {
        $acos = $acos ?? self::computedAcosPercentFromReportRow($rowArr, $dbColumns);
        if ($acos === null) {
            return null;
        }

        return AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos);
    }

    /**
     * Grid SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews + Bgt Dil + Bgt Inv.
     * A part of 0 is added as zero. The campaign pauses only when the total is 0.
     */
    private static function summedSbgtFromParts(mixed $bgtViews, mixed $bgtCvr, mixed $bgtAcos, mixed $bgtPrc = null, mixed $bgtReviews = null, mixed $bgtDil = null, mixed $bgtInv = null, mixed $bgtGpft = null, mixed $bgtFlag = null): ?int
    {
        return AmazonAdsSbgt::sumFromParts($bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtReviews, $bgtDil, $bgtInv, $bgtGpft, $bgtFlag);
    }

    /**
     * Daily budget dollars accepted for SBGT push (sum of the rule columns). 0 is not pushable.
     */
    private static function parsePushableSbgtBudget(mixed $raw): ?int
    {
        return AmazonAdsSbgt::parsePushableBudget($raw);
    }

    /**
     * ACOS (%) from the same row: (cost / sales) × 100. Sales prefers `sales30d`, else `sales`.
     * When spend > 0 and Ads Sold = 0 (or sales $ = 0), ACOS is saved as 100% for BGT / SBGT / filters.
     * For the grid, pass a row whose `cost` / sold / `sales30d` already match L30 overlays when applicable.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function computedAcosPercentFromReportRow(array $rowArr, array $dbColumns): ?float
    {
        if (! in_array('cost', $dbColumns, true) && ! in_array('spend', $dbColumns, true)) {
            return null;
        }
        $hasSales = in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true);
        $hasSold = in_array('purchases30d', $dbColumns, true)
            || in_array('purchases', $dbColumns, true)
            || array_key_exists('Prchase', $rowArr);
        if (! $hasSales && ! $hasSold) {
            return null;
        }

        return AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow($rowArr);
    }

    /**
     * Row passed into {@see computedAcosPercentFromReportRow} / SBGT: L30 spend, sold, sales,
     * plus L7/L2/L1 spend so "spend exists and sold is 0 → ACOS 100%" matches the grid.
     *
     * @param  array<string, mixed>  $rowArr
     * @param  array<string, mixed>  $arr
     * @param  array<int, string>  $dbColumns
     * @param  array<string, array{spend: ?float, purchases30d: ?int, sales30d: ?float, clicks: ?int}>  $l30SliceMap
     * @return array<string, mixed>
     */
    private static function acosCalculationRowFromGridOverlays(
        array $rowArr,
        array $arr,
        array $dbColumns,
        array $l30SliceMap,
        string $lkL30
    ): array {
        $acosRow = $rowArr;
        if (array_key_exists('cost', $arr)) {
            $acosRow['cost'] = $arr['cost'];
        }
        if (array_key_exists('spend', $arr)) {
            $acosRow['spend'] = $arr['spend'];
        }
        foreach (['L7spend', 'L2spend', 'L1spend'] as $k) {
            if (array_key_exists($k, $arr)) {
                $acosRow[$k] = $arr[$k];
            }
        }
        $hasL30 = $lkL30 !== '' && $l30SliceMap !== [] && array_key_exists($lkL30, $l30SliceMap);
        if ($hasL30) {
            $slice = $l30SliceMap[$lkL30];
            if (($slice['spend'] ?? null) !== null) {
                $acosRow['cost'] = $slice['spend'];
            }
            if (array_key_exists('sales30d', $slice)) {
                $acosRow['sales30d'] = $slice['sales30d'];
                $acosRow['sales'] = $slice['sales30d'];
            }
            if (array_key_exists('purchases30d', $slice)) {
                $sold = $slice['purchases30d'];
                $acosRow['purchases30d'] = $sold === null ? 0 : $sold;
                $acosRow['purchases'] = $acosRow['purchases30d'];
                $acosRow['Prchase'] = $acosRow['purchases30d'];
            }
        } else {
            if (array_key_exists('sales30d', $arr)) {
                $acosRow['sales30d'] = $arr['sales30d'];
            }
            if (array_key_exists('sales', $arr) && ! array_key_exists('sales30d', $dbColumns)) {
                $acosRow['sales'] = $arr['sales'];
            }
            if (array_key_exists('Prchase', $arr)) {
                $sold = $arr['Prchase'];
                $acosRow['purchases30d'] = $sold === null || $sold === '' ? 0 : $sold;
                $acosRow['purchases'] = $acosRow['purchases30d'];
                $acosRow['Prchase'] = $acosRow['purchases30d'];
            }
        }

        return $acosRow;
    }

    /**
     * SQL scalar for ORDER BY ACOS, matching {@see computedAcosPercentFromReportRow} on L30 overlay inputs when available.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function sqlExpressionForAcosSort(string $table, array $dbColumns): ?string
    {
        if (! in_array('cost', $dbColumns, true) && ! in_array('spend', $dbColumns, true)) {
            return null;
        }
        $costParts = [];
        $sumSpend = self::correlatedL30SummarySpendScalarSubquerySql($table, $dbColumns);
        if ($sumSpend !== null) {
            $costParts[] = 'NULLIF(('.$sumSpend.'), 0)';
        }
        if (in_array('cost', $dbColumns, true)) {
            $costParts[] = 'NULLIF(cost, 0)';
        }
        if (in_array('spend', $dbColumns, true)) {
            $costParts[] = 'NULLIF(spend, 0)';
        }
        $costEff = $costParts !== [] ? 'COALESCE('.implode(', ', $costParts).', 0)' : '0';
        $salesEff = null;
        if (in_array('sales30d', $dbColumns, true)) {
            $salesEff = 'sales30d';
            $sumSales = self::correlatedL30SummarySales30dScalarSubquerySql($table, $dbColumns);
            if ($sumSales !== null) {
                $salesEff = 'COALESCE(('.$sumSales.'), sales30d)';
            }
        } elseif (in_array('sales', $dbColumns, true)) {
            $salesEff = 'sales';
            $sumSales = self::correlatedL30SummarySales30dScalarSubquerySql($table, $dbColumns);
            if ($sumSales !== null) {
                $salesEff = 'COALESCE(('.$sumSales.'), sales)';
            }
        }
        $soldEff = null;
        $purchCol = self::l30SummaryPurchasesDbColumn($dbColumns);
        if ($purchCol !== null) {
            $soldEff = $purchCol;
            $sumSold = self::correlatedL30SummaryPurchases30dScalarSubquerySql($table, $dbColumns);
            if ($sumSold !== null) {
                $soldEff = 'COALESCE(('.$sumSold.'), '.$purchCol.')';
            }
        }
        if ($salesEff === null && $soldEff === null) {
            return null;
        }
        $sql = 'CASE';
        if ($soldEff !== null) {
            $sql .= ' WHEN '.$costEff.' > 0 AND COALESCE('.$soldEff.', 0) <= 0 THEN 100';
        }
        if ($salesEff !== null) {
            $sql .= ' WHEN COALESCE('.$salesEff.', 0) > 0 THEN '.$costEff.' / NULLIF('.$salesEff.', 0) * 100';
        }
        $sql .= ' WHEN '.$costEff.' > 0 THEN 100 ELSE 0 END';

        return $sql;
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
            return 'COALESCE(NULLIF('.$alias.'.cost, 0), NULLIF('.$alias.'.spend, 0), 0)';
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
        $zero = null;
        foreach (['cost', 'spend'] as $k) {
            if (($k === 'cost' && ! in_array('cost', $dbColumns, true)) || ($k === 'spend' && ! in_array('spend', $dbColumns, true))) {
                continue;
            }
            $v = $r[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (! is_finite($n)) {
                continue;
            }
            if ($n > 0) {
                return $n;
            }
            $zero = 0.0;
        }

        return $zero;
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
            ." AND l30.report_date_range = 'L30' ORDER BY l30.id DESC LIMIT 1";
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
            ." AND l30.report_date_range = 'L30' ORDER BY l30.id DESC LIMIT 1";
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
            ." AND l30.report_date_range = 'L30' ORDER BY l30.id DESC LIMIT 1";
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
            ." AND l30.report_date_range = 'L30' ORDER BY l30.id DESC LIMIT 1";
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
            .' AND CHAR_LENGTH(s30.report_date_range) = 10 '
            .'AND s30.report_date_range BETWEEN \''.$from.'\' AND \''.$anchor.'\'';
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
                ." WHERE report_date_range = 'L7' GROUP BY campaign_id, ad_type"
                .' ) z ON z.mid = t.id';
        }

        return 'SELECT t.campaign_id AS u_cid, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
            .' SELECT campaign_id, MAX(id) AS mid FROM '.$t
            ." WHERE report_date_range = 'L7' GROUP BY campaign_id"
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
                ." WHERE report_date_range = 'L1' GROUP BY campaign_id, ad_type"
                .' ) z ON z.mid = t.id';
        }

        return 'SELECT t.campaign_id AS u_cid, '.$spendSel.' AS u_sp FROM '.$t.' t INNER JOIN ('
            .' SELECT campaign_id, MAX(id) AS mid FROM '.$t
            ." WHERE report_date_range = 'L1' GROUP BY campaign_id"
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
     * Green-flag rule: the campaign has L30 spend and its L30 ACOS% is below its GPFT%.
     */
    private static function acosBelowGpft(?float $acosPct, ?float $gpft, ?float $spend): bool
    {
        return $acosPct !== null && $gpft !== null && $spend !== null
            && is_finite($acosPct) && is_finite($gpft) && is_finite($spend)
            && $spend > 0 && $acosPct < $gpft;
    }

    /**
     * Flag test for one report row when sorting by the Flag column: the same L30 ACOS% the ACOS column shows.
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, string>  $dbColumns
     * @param  array<string, array{spend: ?float, purchases30d: ?int, sales30d: ?float, clicks: ?int}>  $l30Map
     */
    private static function acosFlaggedForSortRow(array $r, array $dbColumns, array $l30Map, string $lk, ?float $gpft): bool
    {
        return self::flagGapForSortRow($r, $dbColumns, $l30Map, $lk, $gpft) !== null;
    }

    /**
     * L30 ACOS% of one report row (the ACOS% column), for sorting by BGT ACOS / SBGT.
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, string>  $dbColumns
     * @param  array<string, array{spend: ?float, purchases30d: ?int, sales30d: ?float, clicks: ?int}>  $l30Map
     */
    private static function l30AcosForSortRow(array $r, array $dbColumns, array $l30Map, string $lk): ?float
    {
        $arr = [];
        if (isset($l30Map[$lk]) && $l30Map[$lk]['spend'] !== null) {
            $arr['cost'] = $l30Map[$lk]['spend'];
        }
        $acosRow = self::acosCalculationRowFromGridOverlays($r, $arr, $dbColumns, $l30Map, $lk);
        $acos = self::computedAcosPercentFromReportRow($acosRow, $dbColumns);

        return ($acos !== null && is_finite((float) $acos)) ? (float) $acos : null;
    }

    /**
     * GPFT% − ACOS% for a flagged row (null when the row is not flagged), for sorting by Bgt Flag / SBGT.
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, string>  $dbColumns
     * @param  array<string, array{spend: ?float, purchases30d: ?int, sales30d: ?float, clicks: ?int}>  $l30Map
     */
    private static function flagGapForSortRow(array $r, array $dbColumns, array $l30Map, string $lk, ?float $gpft): ?float
    {
        if ($gpft === null || ! isset($l30Map[$lk]) || $l30Map[$lk]['spend'] === null) {
            return null;
        }
        $arr = ['cost' => $l30Map[$lk]['spend']];
        $acosRow = self::acosCalculationRowFromGridOverlays($r, $arr, $dbColumns, $l30Map, $lk);

        return AmazonAdsBgtFlagRule::flagGap(self::computedAcosPercentFromReportRow($acosRow, $dbColumns), $gpft, (float) $l30Map[$lk]['spend']);
    }

    /**
     * Campaign id => ['acos' => L30 ACOS%|null, 'spend' => L30 spend|null] for the cron SBGT resolver,
     * so it applies the same flag rule as the grid.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{acos: ?float, spend: ?float}>
     */
    public static function l30AcosSpendForCampaigns(string $table, array $campaignIds): array
    {
        $dbColumns = SchemaMemo::getColumnListing($table);
        $pairs = [];
        foreach ($campaignIds as $cid) {
            $cid = trim((string) $cid);
            if ($cid !== '') {
                $pairs[] = (object) ['campaign_id' => $cid];
            }
        }
        if ($pairs === []) {
            return [];
        }
        $out = [];
        foreach (self::fetchL30SummarySliceMap($table, $dbColumns, $pairs) as $key => $slice) {
            $cid = explode("\0", (string) $key)[0];
            $spend = $slice['spend'] ?? null;
            $acos = null;
            if ($spend !== null && (float) $spend > 0) {
                $sold = $slice['purchases30d'] ?? null;
                $sales = $slice['sales30d'] ?? null;
                $acos = self::computedAcosPercentFromReportRow([
                    'cost' => $spend,
                    'spend' => $spend,
                    'sales30d' => $sales,
                    'sales' => $sales,
                    'purchases30d' => $sold === null ? 0 : $sold,
                    'purchases' => $sold === null ? 0 : $sold,
                    'Prchase' => $sold === null ? 0 : $sold,
                ], $dbColumns);
            }
            $out[$cid] = ['acos' => $acos, 'spend' => $spend !== null ? (float) $spend : null];
        }

        return $out;
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
        return self::sumLatestSummaryMetricsForFilteredCampaigns($filteredBaseQuery, $table, $dbColumns, 'L30');
    }

    /**
     * One month from a 7-day total: daily average (÷ 7) times 30.
     */
    private static function projectMonthFromLast7(float $last7): float
    {
        return round(($last7 / 7) * 30, 2);
    }

    /**
     * Badge totals: one SQL sum of the latest summary row (L1, L7, or L30) per filtered campaign (+ ad_type).
     *
     * @param  array<int, string>  $dbColumns
     * @return array{cost_sum: float, sales_sum: float, purchases_sum: float, clicks_sum: float}
     */
    private static function sumLatestSummaryMetricsForFilteredCampaigns(Builder $filteredBaseQuery, string $table, array $dbColumns, string $range): array
    {
        if (! in_array($range, ['L1', 'L7', 'L30'], true)) {
            $range = 'L30';
        }
        $hasAd = in_array('ad_type', $dbColumns, true);
        $salesCol = self::l30SummarySalesDbColumn($dbColumns);
        $purchCol = self::l30SummaryPurchasesDbColumn($dbColumns);
        $clicksCol = in_array('clicks', $dbColumns, true) ? 'clicks' : null;
        $costExpr = self::costPreferCoalesceExprForTableAlias('t', $dbColumns);

        $pairs = $filteredBaseQuery->clone()->reorder()->select($table.'.campaign_id');
        if ($hasAd) {
            $pairs->addSelect($table.'.ad_type');
        }
        $pairs->distinct();

        $latest = DB::table($table.' as src')->where('src.report_date_range', $range);
        $latest->joinSub($pairs, 'flt', function ($join) use ($hasAd) {
            $join->on('flt.campaign_id', '=', 'src.campaign_id');
            if ($hasAd) {
                $join->whereRaw('flt.ad_type <=> src.ad_type');
            }
        });
        if ($hasAd) {
            $latest->selectRaw('MAX(src.id) AS max_id')->groupBy('src.campaign_id', 'src.ad_type');
        } else {
            $latest->selectRaw('MAX(src.id) AS max_id')->groupBy('src.campaign_id');
        }

        $selects = [
            $costExpr !== null ? 'COALESCE(SUM('.$costExpr.'), 0) AS cost_sum' : '0 AS cost_sum',
            $salesCol !== null ? 'COALESCE(SUM(t.`'.$salesCol.'`), 0) AS sales_sum' : '0 AS sales_sum',
            $purchCol !== null ? 'COALESCE(SUM(t.`'.$purchCol.'`), 0) AS purchases_sum' : '0 AS purchases_sum',
            $clicksCol !== null ? 'COALESCE(SUM(t.`'.$clicksCol.'`), 0) AS clicks_sum' : '0 AS clicks_sum',
            in_array('campaignBudgetAmount', $dbColumns, true)
                ? 'COALESCE(SUM(t.`campaignBudgetAmount`), 0) AS budget_sum'
                : '0 AS budget_sum',
        ];

        $row = DB::query()
            ->fromSub($latest, 'm')
            ->join($table.' as t', 't.id', '=', 'm.max_id')
            ->selectRaw(implode(', ', $selects))
            ->first();

        return [
            'cost_sum' => (float) ($row->cost_sum ?? 0),
            'sales_sum' => (float) ($row->sales_sum ?? 0),
            'purchases_sum' => (float) ($row->purchases_sum ?? 0),
            'clicks_sum' => (float) ($row->clicks_sum ?? 0),
            'budget_sum' => (float) ($row->budget_sum ?? 0),
        ];
    }

    /**
     * Utilized Y / Utilized L7 blocks: campaigns (distinct campaign_id [+ ad_type]) bucketed by utilisation of the
     * latest L1 or L7 summary row — spend ÷ (campaignBudgetAmount × days), same bands and n/a rule as
     * {@see sqlU7BucketCaseExpression} (< 66, 66–99, > 99).
     *
     * @param  array<int, string>  $dbColumns
     * @return array{lt66: int, 66_99: int, gt99: int, na: int, total: int, spend: float, bd: float}
     */
    private static function utilBucketsLatestSummaryForFilteredCampaigns(Builder $filteredBaseQuery, string $table, array $dbColumns, string $range, int $days): array
    {
        $out = ['lt66' => 0, '66_99' => 0, 'gt99' => 0, 'na' => 0, 'total' => 0, 'spend' => 0.0, 'bd' => 0.0];
        $spendCol = self::spendColumnForLRange($dbColumns);
        if ($spendCol === null || ! in_array('campaignBudgetAmount', $dbColumns, true) || ! in_array($range, ['L1', 'L7'], true)) {
            return $out;
        }
        $hasAd = in_array('ad_type', $dbColumns, true);

        $pairs = $filteredBaseQuery->clone()->reorder()->select($table.'.campaign_id');
        if ($hasAd) {
            $pairs->addSelect($table.'.ad_type');
        }
        $pairs->distinct();

        $latest = DB::table($table.' as src')->where('src.report_date_range', $range);
        $latest->joinSub($pairs, 'flt', function ($join) use ($hasAd) {
            $join->on('flt.campaign_id', '=', 'src.campaign_id');
            if ($hasAd) {
                $join->whereRaw('flt.ad_type <=> src.ad_type');
            }
        });
        if ($hasAd) {
            $latest->selectRaw('MAX(src.id) AS max_id')->groupBy('src.campaign_id', 'src.ad_type');
        } else {
            $latest->selectRaw('MAX(src.id) AS max_id')->groupBy('src.campaign_id');
        }

        $sp = 't.`'.$spendCol.'`';
        $bud = 't.`campaignBudgetAmount`';
        $days = max(1, $days);
        $bucket = 'CASE WHEN '.$bud.' IS NULL OR '.$bud.' <= 0 OR '.$sp.' IS NULL THEN \'na\' '
            .'WHEN ('.$sp.' / ('.$bud.' * '.$days.') * 100) < 66 THEN \'lt66\' '
            .'WHEN ('.$sp.' / ('.$bud.' * '.$days.') * 100) <= 99 THEN \'66_99\' '
            .'ELSE \'gt99\' END';

        $perCampaign = DB::query()
            ->fromSub($latest, 'm')
            ->join($table.' as t', 't.id', '=', 'm.max_id')
            ->selectRaw($bucket.' AS bucket, '
                .'CASE WHEN '.$bud.' > 0 AND '.$sp.' IS NOT NULL THEN '.$sp.' ELSE 0 END AS sp_v, '
                .'CASE WHEN '.$bud.' > 0 AND '.$sp.' IS NOT NULL THEN '.$bud.' * '.$days.' ELSE 0 END AS bd_v');
        $rows = DB::query()
            ->fromSub($perCampaign, 'x')
            ->selectRaw('x.bucket AS bucket, COUNT(*) AS cnt, COALESCE(SUM(x.sp_v), 0) AS sp_sum, COALESCE(SUM(x.bd_v), 0) AS bd_sum')
            ->groupBy('x.bucket')
            ->get();

        foreach ($rows as $r) {
            $k = (string) ($r->bucket ?? 'na');
            $k = array_key_exists($k, $out) && $k !== 'total' && $k !== 'spend' && $k !== 'bd' ? $k : 'na';
            $c = (int) ($r->cnt ?? 0);
            $out[$k] += $c;
            $out['total'] += $c;
            $out['spend'] += (float) ($r->sp_sum ?? 0);
            $out['bd'] += (float) ($r->bd_sum ?? 0);
        }

        return $out;
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
     * Per-campaign yesterday ACOS %, same edge cases as the Yesterday badge:
     * spend ÷ ads sales, 100% when spend exists and sales do not, 0% when both are zero.
     * Null when yesterday spend and sales are both missing.
     */
    private static function yesterdayAcosPercent(mixed $spend, mixed $sales): ?float
    {
        $hasSpend = is_numeric($spend);
        $hasSales = is_numeric($sales);
        if (! $hasSpend && ! $hasSales) {
            return null;
        }

        return self::overallAcosPercentFromAggregatedSums(
            $hasSpend ? (float) $spend : 0.0,
            $hasSales ? (float) $sales : 0.0
        );
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
        if (! SchemaMemo::hasTable($table)) {
            return self::$latestDailyReportYmdCache[$table] = null;
        }
        // Date labels sort before L1/L7/L30, so this range uses the report_date_range index.
        $max = DB::table($table)
            ->where('report_date_range', '>=', '2010-01-01')
            ->where('report_date_range', '<=', '2099-12-31')
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
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
     * Spend prefers `cost` then `spend`. L1 sales prefers `sales1d`; L7 sales prefers `sales7d`.
     * A later positive L1/L7 row replaces a newer zero (stale backfill). Latest daily fills missing L1.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, array{L7: float|null, L2: float|null, L1: float|null, L7sales: float|null, L1sales: float|null}>
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
        $select = self::lRangeMetricSelectColumns($dbColumns, $hasAdType);
        $emptySlice = ['L7' => null, 'L2' => null, 'L1' => null, 'L7sales' => null, 'L2sales' => null, 'L1sales' => null];
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
                $map[$key] = $emptySlice;
            }
            $spend = AmazonAdsLRangeMetrics::spendFromRow($frArr, $dbColumns);
            $map[$key][$tag] = AmazonAdsLRangeMetrics::preferAmount($map[$key][$tag], $spend);
            $salesKey = $tag === 'L7' ? 'L7sales' : 'L1sales';
            $sales = AmazonAdsLRangeMetrics::salesFromRow($frArr, $dbColumns, $tag);
            $map[$key][$salesKey] = AmazonAdsLRangeMetrics::preferAmount($map[$key][$salesKey], $sales);
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
                $map[$key] = $emptySlice;
            }
            $spend = AmazonAdsLRangeMetrics::spendFromRow($frArr, $dbColumns);
            $map[$key]['L2'] = AmazonAdsLRangeMetrics::preferAmount($map[$key]['L2'], $spend);
            $l2Sales = AmazonAdsLRangeMetrics::salesFromRow($frArr, $dbColumns, 'daily');
            $map[$key]['L2sales'] = AmazonAdsLRangeMetrics::preferAmount($map[$key]['L2sales'] ?? null, $l2Sales);
        }

        self::overlayLatestDailyOntoL1Map($table, $dbColumns, $cidList, $hasAdType, $select, $map);
        self::fillL7SalesFromDailySum($table, $dbColumns, $cidList, $hasAdType, $map);

        return $map;
    }

    /**
     * @param  array<int, string>  $dbColumns
     * @return list<string>
     */
    private static function lRangeMetricSelectColumns(array $dbColumns, bool $hasAdType): array
    {
        $select = ['id', 'campaign_id', 'report_date_range'];
        foreach (['cost', 'spend', 'sales1d', 'sales7d', 'sales14d', 'sales30d', 'sales'] as $col) {
            if (in_array($col, $dbColumns, true)) {
                $select[] = $col;
            }
        }
        if ($hasAdType) {
            $select[] = 'ad_type';
        }

        return array_values(array_unique($select));
    }

    /**
     * @param  array<int, string>  $dbColumns
     * @param  list<string>  $cidList
     * @param  list<string>  $select
     * @param  array<string, array{L7: float|null, L2: float|null, L1: float|null, L7sales: float|null, L1sales: float|null}>  $map
     */
    private static function overlayLatestDailyOntoL1Map(
        string $table,
        array $dbColumns,
        array $cidList,
        bool $hasAdType,
        array $select,
        array &$map
    ): void {
        $latest = self::latestDailyReportYmdInTable($table);
        if ($latest === null || $latest === '' || $cidList === []) {
            return;
        }
        $daily = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->where('report_date_range', $latest)
            ->orderBy('id', 'desc')
            ->get();
        foreach ($daily as $fr) {
            $frArr = (array) $fr;
            $cid = isset($frArr['campaign_id']) ? trim((string) $frArr['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($frArr['ad_type'] ?? '')) : '';
            $key = $cid."\0".$ad;
            if (! isset($map[$key])) {
                $map[$key] = ['L7' => null, 'L2' => null, 'L1' => null, 'L7sales' => null, 'L2sales' => null, 'L1sales' => null];
            }
            $spend = AmazonAdsLRangeMetrics::spendFromRow($frArr, $dbColumns);
            $map[$key]['L1'] = AmazonAdsLRangeMetrics::preferAmount($map[$key]['L1'], $spend);
            $sales = AmazonAdsLRangeMetrics::salesFromRow($frArr, $dbColumns, 'daily');
            $map[$key]['L1sales'] = AmazonAdsLRangeMetrics::preferAmount($map[$key]['L1sales'], $sales);
        }
    }

    /**
     * When the L7 summary left ads sales at 0/null, sum daily day-sales over the last 7 calendar days.
     *
     * @param  array<int, string>  $dbColumns
     * @param  list<string>  $cidList
     * @param  array<string, array{L7: float|null, L2: float|null, L1: float|null, L7sales: float|null, L1sales: float|null}>  $map
     */
    private static function fillL7SalesFromDailySum(
        string $table,
        array $dbColumns,
        array $cidList,
        bool $hasAdType,
        array &$map
    ): void {
        $salesCol = null;
        foreach (['sales1d', 'sales', 'sales30d', 'sales7d'] as $c) {
            if (in_array($c, $dbColumns, true)) {
                $salesCol = $c;
                break;
            }
        }
        if ($salesCol === null || $cidList === []) {
            return;
        }
        $anchor = self::latestDailyReportYmdInTable($table);
        if ($anchor === null || $anchor === '') {
            return;
        }
        try {
            $from = Carbon::parse($anchor, config('app.timezone'))->subDays(6)->format('Y-m-d');
        } catch (\Throwable) {
            return;
        }
        $q = DB::table($table)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereBetween('report_date_range', [$from, $anchor]);
        if ($hasAdType) {
            $q->selectRaw('campaign_id, ad_type, COALESCE(SUM(`'.$salesCol.'`), 0) AS s')
                ->groupBy('campaign_id', 'ad_type');
        } else {
            $q->selectRaw('campaign_id, COALESCE(SUM(`'.$salesCol.'`), 0) AS s')
                ->groupBy('campaign_id');
        }
        foreach ($q->get() as $row) {
            $r = (array) $row;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $key = $cid."\0".$ad;
            $sum = is_numeric($r['s'] ?? null) ? round((float) $r['s'], 2) : null;
            if ($sum === null || ! is_finite($sum) || $sum <= 0) {
                continue;
            }
            if (! isset($map[$key])) {
                $map[$key] = ['L7' => null, 'L2' => null, 'L1' => null, 'L7sales' => null, 'L2sales' => null, 'L1sales' => null];
            }
            $map[$key]['L7sales'] = AmazonAdsLRangeMetrics::preferAmount($map[$key]['L7sales'], $sum);
        }
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
            ->where('report_date_range', 'L1')
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
     * Lifetime average CPC for the visible page: total cost ÷ total clicks on daily API rows
     * (`report_date_range` YYYY-MM-DD). Same Amazon report data as CPC1/CPC2/CPC3.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, float|null>
     */
    private static function prefetchLifetimeAvgCpcForPageRows(string $table, array $dbColumns, iterable $pageRows): array
    {
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true)) {
            return [];
        }
        $costCol = in_array('cost', $dbColumns, true) ? 'cost' : (in_array('spend', $dbColumns, true) ? 'spend' : null);
        $hasClicks = in_array('clicks', $dbColumns, true);
        $hasCpc = in_array('costPerClick', $dbColumns, true);
        $useWeighted = $costCol !== null && $hasClicks;
        if (! $useWeighted && ! $hasCpc) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $cids = [];
        foreach ($pageRows as $row) {
            $rowArr = (array) $row;
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }

        $select = ['campaign_id'];
        if ($hasAdType) {
            $select[] = 'ad_type';
        }
        if ($useWeighted) {
            $select[] = DB::raw('SUM(`'.$costCol.'`) as life_cost');
            $select[] = DB::raw('SUM(`clicks`) as life_clicks');
        } else {
            $select[] = DB::raw('AVG(CASE WHEN `costPerClick` > 0 THEN `costPerClick` END) as life_cpc');
        }

        $q = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        $q->groupBy($hasAdType ? ['campaign_id', 'ad_type'] : ['campaign_id']);

        $map = [];
        foreach ($q->get() as $fr) {
            $r = (array) $fr;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $cpc = null;
            if ($useWeighted) {
                $clicks = (float) ($r['life_clicks'] ?? 0);
                $cost = (float) ($r['life_cost'] ?? 0);
                if ($clicks > 0 && $cost > 0) {
                    $n = $cost / $clicks;
                    $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
                }
            } else {
                $n = (float) ($r['life_cpc'] ?? 0);
                $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
            }
            $map[$cid."\0".$ad] = $cpc;
        }

        return $map;
    }

    /**
     * Lifetime CVR % for the visible page: total purchases ÷ total clicks × 100 on daily API rows.
     * SP uses `purchases30d` (same attribution as Ads Sold). SB/SD use `purchases`.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, float|null>
     */
    private static function prefetchLifetimeCvrForPageRows(string $table, array $dbColumns, iterable $pageRows): array
    {
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('clicks', $dbColumns, true)) {
            return [];
        }
        $purchCol = in_array('purchases30d', $dbColumns, true)
            ? 'purchases30d'
            : (in_array('purchases', $dbColumns, true) ? 'purchases' : null);
        if ($purchCol === null) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $cids = [];
        foreach ($pageRows as $row) {
            $rowArr = (array) $row;
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }

        $select = ['campaign_id', DB::raw('SUM(`'.$purchCol.'`) as life_purch'), DB::raw('SUM(`clicks`) as life_clicks')];
        if ($hasAdType) {
            $select[] = 'ad_type';
        }
        $q = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        $q->groupBy($hasAdType ? ['campaign_id', 'ad_type'] : ['campaign_id']);

        $map = [];
        foreach ($q->get() as $fr) {
            $r = (array) $fr;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $clicks = (float) ($r['life_clicks'] ?? 0);
            $purch = (float) ($r['life_purch'] ?? 0);
            $cvr = null;
            if ($clicks > 0 && is_finite($purch)) {
                $n = ($purch / $clicks) * 100;
                $cvr = is_finite($n) ? round($n, 2) : null;
            }
            $map[$cid."\0".$ad] = $cvr;
        }

        return $map;
    }

    /**
     * Lifetime ACOS % for the visible page: total cost ÷ total sales × 100 on daily API rows.
     * Spend with no sales is 100%, matching the ACOS% column.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, float|null>
     */
    private static function prefetchLifetimeAcosForPageRows(string $table, array $dbColumns, iterable $pageRows): array
    {
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true)) {
            return [];
        }
        $costCol = in_array('cost', $dbColumns, true) ? 'cost' : (in_array('spend', $dbColumns, true) ? 'spend' : null);
        $salesCol = in_array('sales30d', $dbColumns, true) ? 'sales30d' : (in_array('sales', $dbColumns, true) ? 'sales' : null);
        if ($costCol === null || $salesCol === null) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $cids = [];
        foreach ($pageRows as $row) {
            $rowArr = (array) $row;
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }

        $select = [
            'campaign_id',
            DB::raw('SUM(`'.$costCol.'`) as life_cost'),
            DB::raw('SUM(`'.$salesCol.'`) as life_sales'),
        ];
        if ($hasAdType) {
            $select[] = 'ad_type';
        }
        $q = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        $q->groupBy($hasAdType ? ['campaign_id', 'ad_type'] : ['campaign_id']);

        $map = [];
        foreach ($q->get() as $fr) {
            $r = (array) $fr;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $cost = (float) ($r['life_cost'] ?? 0);
            $sales = (float) ($r['life_sales'] ?? 0);
            $map[$cid."\0".$ad] = self::lifetimeAcosPercent($cost, $sales);
        }

        return $map;
    }

    /**
     * Lifetime spend and sales for the visible page: total cost / total sales on daily API rows
     * (`report_date_range` YYYY-MM-DD). Same rows and columns as LT ACOS.
     *
     * @param  array<int, string>  $dbColumns
     * @param  iterable<int, object>  $pageRows
     * @return array<string, array{spend: ?float, sales: ?float, sold: ?int, cps: ?float, tcps: ?float, tcos: ?float, cpc: ?float}>
     */
    private static function prefetchLifetimeSpendSalesForPageRows(string $table, array $dbColumns, iterable $pageRows): array
    {
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('report_date_range', $dbColumns, true)) {
            return [];
        }
        $costCol = in_array('cost', $dbColumns, true) ? 'cost' : (in_array('spend', $dbColumns, true) ? 'spend' : null);
        $salesCol = in_array('sales30d', $dbColumns, true) ? 'sales30d' : (in_array('sales', $dbColumns, true) ? 'sales' : null);
        $soldCol = in_array('purchases30d', $dbColumns, true)
            ? 'purchases30d'
            : (in_array('purchases', $dbColumns, true) ? 'purchases' : null);
        if ($costCol === null && $salesCol === null && $soldCol === null) {
            return [];
        }
        $hasAdType = in_array('ad_type', $dbColumns, true);
        $cids = [];
        foreach ($pageRows as $row) {
            $rowArr = (array) $row;
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            if ($cid !== '') {
                $cids[$cid] = true;
            }
        }
        $cidList = array_keys($cids);
        if ($cidList === []) {
            return [];
        }

        $select = ['campaign_id'];
        if ($soldCol !== null) {
            $select[] = DB::raw('SUM(`'.$soldCol.'`) as life_sold');
        }
        if ($costCol !== null) {
            $select[] = DB::raw('SUM(`'.$costCol.'`) as life_cost');
        }
        if ($salesCol !== null) {
            $select[] = DB::raw('SUM(`'.$salesCol.'`) as life_sales');
        }
        if ($hasAdType) {
            $select[] = 'ad_type';
        }
        $q = DB::table($table)
            ->select($select)
            ->whereIn('campaign_id', $cidList)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        $q->groupBy($hasAdType ? ['campaign_id', 'ad_type'] : ['campaign_id']);

        $map = [];
        foreach ($q->get() as $fr) {
            $r = (array) $fr;
            $cid = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($r['ad_type'] ?? '')) : '';
            $cost = isset($r['life_cost']) && $r['life_cost'] !== null ? (float) $r['life_cost'] : null;
            $sales = isset($r['life_sales']) && $r['life_sales'] !== null ? (float) $r['life_sales'] : null;
            $sold = isset($r['life_sold']) && $r['life_sold'] !== null ? (float) $r['life_sold'] : null;
            $map[$cid."\0".$ad] = [
                'spend' => ($cost !== null && is_finite($cost)) ? round($cost, 2) : null,
                'sales' => ($sales !== null && is_finite($sales)) ? round($sales, 2) : null,
                'sold' => ($sold !== null && is_finite($sold)) ? (int) round($sold) : null,
                // CPS = LT Spend ÷ LT Sales (blank when there are no lifetime sales).
                'cps' => ($cost !== null && $sales !== null && is_finite($cost) && is_finite($sales) && $sales > 0)
                    ? round($cost / $sales, 2)
                    : null,
                // TCPS = CPS × 0.60, where CPS = LT Spend ÷ LT Sold.
                'tcps' => ($cost !== null && $sold !== null && is_finite($cost) && is_finite($sold) && $sold > 0)
                    ? round(($cost / $sold) * 0.60, 2)
                    : null,
                // TCOS % = ACOS % × 0.60 (ACOS % = LT Spend ÷ LT Sales × 100).
                'tcos' => ($cost !== null && $sales !== null && is_finite($cost) && is_finite($sales) && $sales > 0)
                    ? round(($cost / $sales) * 100 * 0.60, 2)
                    : null,
                // CPC = LT Spend ÷ LT Sold (blank when there are no lifetime sold units).
                'cpc' => ($cost !== null && $sold !== null && is_finite($cost) && is_finite($sold) && $sold > 0)
                    ? round($cost / $sold, 2)
                    : null,
            ];
        }

        return $map;
    }

    private static function lifetimeAcosPercent(float $cost, float $sales): ?float
    {
        if (! is_finite($cost) || ! is_finite($sales)) {
            return null;
        }
        if ($sales > 0) {
            $n = ($cost / $sales) * 100;

            return is_finite($n) ? round($n, 2) : null;
        }
        if ($cost > 0) {
            return 100.0;
        }

        return null;
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
     * Grid SBID from U7%/U1% + CPC1/CPC2/CPC3 (`costPerClick`, `CPC2`, `CPC3`).
     * When all three are missing and Avg CPC is available, SBID is Avg CPC + the rule's avg_cpc_add.
     * Outside red+red / pink+pink bands, sbid is forced to null so the UI shows "--".
     */
    private static function applyGridSbidFromUb2Ub1AndCpc(array &$arr, array $u, array $rowArr, array $dbColumns, string $table): void
    {
        if (! in_array('sbid', $dbColumns, true)) {
            return;
        }
        // SBID rule bands are driven by U7% (interchanged from U2%) and U1%.
        $utils = self::sbidRuleUtilsFromUtilization($u, $rowArr);
        if ($utils === null) {
            $arr['sbid'] = null;

            return;
        }
        $u2 = $utils['u2'];
        $u1 = $utils['u1'];

        $cpc1 = self::rowPositiveFloatFromKeys($arr, ['costPerClick']);
        if ($cpc1 <= 0) {
            $cpc1 = self::rowPositiveFloatFromKeys($rowArr, ['costPerClick']);
        }
        $cpc2 = self::rowPositiveFloatFromKeys($arr, ['CPC2']);
        $cpc3 = self::rowPositiveFloatFromKeys($arr, ['CPC3']);
        $avgCpc = self::rowPositiveFloatFromKeys($arr, ['CPCAvg']);

        $out = AmazonBidUtilizationService::sbidFromUb2Ub1Cpc(
            (float) $u2,
            (float) $u1,
            $cpc1,
            $cpc2,
            $cpc3,
            null,
            $avgCpc > 0 ? $avgCpc : null
        );

        $arr['sbid'] = $out['sbid'];
    }

    /**
     * Missing L7/L1 spend is 0% util when BGT exists — same as bid crons and the
     * SBID rule fallback (e.g. $0.75 when both below low and CPC is 0).
     *
     * @param  array{U7: mixed, U1: mixed}  $u
     * @param  array<string, mixed>  $rowArr
     * @return array{u2: float, u1: float}|null
     */
    private static function sbidRuleUtilsFromUtilization(array $u, array $rowArr): ?array
    {
        $u2 = $u['U7'] ?? null;
        $u1 = $u['U1'] ?? null;
        if (self::rowBudgetForUtilization($rowArr) !== null) {
            $u2 = $u2 ?? 0.0;
            $u1 = $u1 ?? 0.0;
        }
        if ($u2 === null || $u1 === null || ! is_numeric($u2) || ! is_numeric($u1)) {
            return null;
        }

        return ['u2' => (float) $u2, 'u1' => (float) $u1];
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
     * Calendar mode pins the grid to one daily report_date_range, so Amazon omits
     * paused / zero-activity campaigns that still have L30 spend. Badge totals must
     * use the L30 summary universe (same as Amazon Ads console Last 30 days).
     */
    private static function shouldUseL30SummaryUniverseForBadges(string $table, Request $request): bool
    {
        if ($table !== 'amazon_sp_campaign_reports' && $table !== 'amazon_sb_campaign_reports') {
            return false;
        }

        return self::normalizeSummaryReportRange($request->input('summary_report_range')) === null;
    }

    /**
     * L30 summary rows plus search / Stat / U% / ACOS / Ads CVR filters — no calendar-day pin.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function l30SummaryUniverseQuery(string $table, Request $request, string $search, array $dbColumns): Builder
    {
        $query = DB::table($table);
        if (in_array('report_date_range', $dbColumns, true)) {
            $query->where('report_date_range', 'L30');
        }
        if ($search !== '' && in_array('campaignName', $dbColumns, true)) {
            $escaped = addcslashes($search, '%_\\');
            $query->where('campaignName', 'LIKE', '%'.$escaped.'%');
        }
        self::applyUtilizationPercentRangeFilters($query, $table, $request, true);
        self::applyCampaignStatusFilter($query, $table, $request);
        self::applyAcosColorFilter($query, $table, $request);
        self::applyAdsCvrColorFilter($query, $table, $request);
        self::applyAcosGpftFlagFilter($query, $table, $request);
        self::applyInventoryFilter($query, $table, $request, $dbColumns);
        self::applyTargetsFilter($query, $table, $request);
        if (self::tableSupportsLiveSyncStatus($table, $dbColumns)) {
            self::applyLiveSyncStatusFilters($query, $table, $request);
        }

        return $query;
    }

    /**
     * Calendar mode: keep the selected day's rows, and also L30 rows for campaigns Amazon
     * omitted from that day (zero impressions). Search used to be required, so the default
     * grid hid enabled campaigns the Amazon console still lists. Returns true when applied.
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
        if (! in_array('campaignName', $dbColumns, true)
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
            $latest = self::latestDailyReportYmdInTable($table);
            if ($latest === null || $latest === '') {
                return false;
            }
            $from = $latest;
            $to = $latest;
        }

        $like = $search !== '' ? '%'.addcslashes($search, '%_\\').'%' : null;
        $hasAdType = in_array('ad_type', $dbColumns, true);

        // Two index lookups. OR + NOT EXISTS makes MySQL scan the whole report table.
        $dailyIds = DB::table($table)->select('id');
        self::whereReportDateRangeDailyYmdInRange($dailyIds, $from, $to);
        if ($like !== null) {
            $dailyIds->where('campaignName', 'LIKE', $like);
        }
        $dailyIdList = $dailyIds->pluck('id')->all();

        $present = DB::table($table)->select('campaign_id');
        if ($hasAdType) {
            $present->addSelect('ad_type');
        }
        self::whereReportDateRangeDailyYmdInRange($present, $from, $to);
        $presentKeys = [];
        foreach ($present->distinct()->get() as $row) {
            $cid = trim((string) ($row->campaign_id ?? ''));
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($row->ad_type ?? '')) : '';
            $presentKeys[$cid."\0".$ad] = true;
        }

        $l30IdList = [];
        $l30Query = DB::table($table)
            ->select($hasAdType ? ['id', 'campaign_id', 'ad_type'] : ['id', 'campaign_id'])
            ->where('report_date_range', 'L30');
        if ($like !== null) {
            $l30Query->where('campaignName', 'LIKE', $like);
        }
        foreach ($l30Query->get() as $row) {
            $cid = trim((string) ($row->campaign_id ?? ''));
            if ($cid === '') {
                continue;
            }
            $ad = $hasAdType ? trim((string) ($row->ad_type ?? '')) : '';
            if (isset($presentKeys[$cid."\0".$ad])) {
                continue;
            }
            $l30IdList[] = $row->id;
        }

        $ids = array_values(array_unique(array_merge($dailyIdList, $l30IdList)));
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return true;
        }
        $query->where(function (Builder $w) use ($ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $w->orWhereIn('id', $chunk);
            }
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
        $cols = SchemaMemo::getColumnListing($table);
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
            // Calendar mode with no dates must not scan every historical row.
            if (in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports', 'amazon_sd_campaign_reports'], true)) {
                $latest = self::latestDailyReportYmdInTable($table);
                if ($latest !== null && $latest !== '') {
                    $query->where('report_date_range', $latest);
                }
            }

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
        $cols = SchemaMemo::getColumnListing($table);
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

    /**
     * Target-count bands for the Targets column. 0 is its own band (shown as M).
     */
    private static function normalizeTargetsFilter(mixed $raw): ?string
    {
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['m', 'lt50', 'mid', 'gt100'], true) ? $v : null;
    }

    private static function targetCountMatchesBand(int $count, string $band): bool
    {
        return match ($band) {
            'm' => $count === 0,
            'lt50' => $count > 0 && $count < 50,
            'mid' => $count >= 50 && $count <= 100,
            'gt100' => $count > 100,
            default => false,
        };
    }

    private static function targetCountAdProduct(string $table): ?string
    {
        return match ($table) {
            'amazon_sp_campaign_reports' => 'sp',
            'amazon_sb_campaign_reports' => 'sb',
            'amazon_sd_campaign_reports' => 'sd',
            default => null,
        };
    }

    /**
     * Target counts for the grid. SP uses the L30 targeting report. SB and SD are not in that
     * report (HEAD / HL keywords live on the Sponsored Brands list), so they use the synced
     * amazon_ads_target_counts table. Campaigns with no rows are 0 (shown as M).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, int>
     */
    private static function targetCountsForCampaigns(string $table, array $campaignIds): array
    {
        $adProduct = self::targetCountAdProduct($table);
        if ($adProduct === null) {
            return [];
        }

        $ids = AmazonAdsTargetCounts::normalizeIds($campaignIds);
        if ($ids === []) {
            return [];
        }

        $report = $adProduct === 'sp'
            ? self::targetCountsFromKeywordReports($ids)
            : AmazonAdsTargetCounts::map($adProduct, $ids, 'targets');
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $report[$id] ?? 0;
        }

        return $out;
    }

    /**
     * Distinct L30 targeting-report rows (keyword_id) for the campaigns on this page.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, int>
     */
    private static function targetCountsFromKeywordReports(array $campaignIds): array
    {
        if ($campaignIds === [] || ! SchemaMemo::hasTable('amazon_sp_keyword_reports')) {
            return [];
        }

        $cols = SchemaMemo::getColumnListing('amazon_sp_keyword_reports');
        if (! in_array('campaign_id', $cols, true) || ! in_array('keyword_id', $cols, true) || ! in_array('report_date_range', $cols, true)) {
            return [];
        }

        $map = [];
        $rows = DB::table('amazon_sp_keyword_reports')
            ->select('campaign_id', DB::raw('COUNT(DISTINCT keyword_id) AS c'))
            ->where('report_date_range', 'L30')
            ->whereIn('campaign_id', $campaignIds)
            ->whereNotNull('keyword_id')
            ->where('keyword_id', '!=', '')
            ->groupBy('campaign_id')
            ->get();
        foreach ($rows as $row) {
            $map[trim((string) $row->campaign_id)] = (int) $row->c;
        }

        return $map;
    }

    /**
     * Distinct negative keywords on each campaign from the synced SP negative-keyword list.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, int>
     */
    private static function negativeTargetCountsForCampaigns(array $campaignIds, ?string $table = null): array
    {
        $ids = AmazonAdsTargetCounts::normalizeIds($campaignIds);
        $adProduct = $table === null ? null : self::targetCountAdProduct($table);
        if ($adProduct === 'sb' || $adProduct === 'sd') {
            $stored = AmazonAdsTargetCounts::map($adProduct, $ids, 'n_targets');
            $out = [];
            foreach ($ids as $id) {
                $out[$id] = $stored[$id] ?? 0;
            }

            return $out;
        }
        if ($ids === [] || ! SchemaMemo::hasTable('amazon_sp_negative_keywords')) {
            return array_fill_keys($ids, 0);
        }

        $cols = SchemaMemo::getColumnListing('amazon_sp_negative_keywords');
        if (! in_array('campaign_id', $cols, true) || ! in_array('keyword_id', $cols, true)) {
            return array_fill_keys($ids, 0);
        }

        $map = [];
        $hasState = in_array('state', $cols, true);
        $q = DB::table('amazon_sp_negative_keywords')
            ->select('campaign_id', DB::raw('COUNT(DISTINCT keyword_id) AS c'))
            ->whereIn('campaign_id', $ids)
            ->whereNotNull('keyword_id')
            ->where('keyword_id', '!=', '');
        if ($hasState) {
            $q->where(function ($w) {
                $w->whereNull('state')->orWhereRaw("UPPER(state) <> 'ARCHIVED'");
            });
        }
        foreach ($q->groupBy('campaign_id')->get() as $row) {
            $map[trim((string) $row->campaign_id)] = (int) $row->c;
        }
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $map[$id] ?? 0;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function attachTargetCountsToRows(array $rows, string $table, bool $withTargets, bool $withNegatives): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row['campaign_id'] ?? '';
        }
        $counts = $withTargets ? self::targetCountsForCampaigns($table, $ids) : [];
        $negatives = $withNegatives ? self::negativeTargetCountsForCampaigns($ids, $table) : [];
        foreach ($rows as $i => $row) {
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($withTargets) {
                $rows[$i]['targets'] = ($cid !== '' && array_key_exists($cid, $counts)) ? $counts[$cid] : null;
            }
            if ($withNegatives) {
                $rows[$i]['nTargets'] = ($cid !== '' && array_key_exists($cid, $negatives)) ? $negatives[$cid] : 0;
            }
        }

        return $rows;
    }

    private static function applyTargetsFilter(Builder $query, string $table, Request $request): void
    {
        $band = self::normalizeTargetsFilter($request->input('filter_targets'));
        if ($band === null || self::targetCountAdProduct($table) === null) {
            return;
        }
        $cols = SchemaMemo::getColumnListing($table);
        if (! in_array('campaign_id', $cols, true)) {
            return;
        }

        $ids = $query->clone()->reorder()->select('campaign_id')->distinct()->pluck('campaign_id');
        $idList = [];
        foreach ($ids as $id) {
            $s = trim((string) $id);
            if ($s !== '') {
                $idList[] = $s;
            }
        }
        $counts = self::targetCountsForCampaigns($table, $idList);
        $keep = [];
        foreach ($idList as $id) {
            $n = $counts[$id] ?? null;
            if ($n === null) {
                continue;
            }
            if (self::targetCountMatchesBand((int) $n, $band)) {
                $keep[] = $id;
            }
        }
        if ($keep === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where(function (Builder $w) use ($keep) {
            foreach (array_chunk($keep, 500) as $chunk) {
                $w->orWhereIn('campaign_id', $chunk);
            }
        });
    }

    private static function normalizeInvFilter(mixed $raw): ?string
    {
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['zero', 'gt'], true) ? $v : null;
    }

    /**
     * Keep rows whose campaign Inv matches the grid (0 / blank, or above 0).
     * Resolved once from campaign names, then applied in SQL so paging and counts
     * stay on the matching set instead of scanning thousands of report rows in PHP.
     *
     * @param  list<string>  $dbColumns
     */
    private static function applyInventoryFilter(Builder $query, string $table, Request $request, array $dbColumns): void
    {
        $mode = self::normalizeInvFilter($request->input('filter_inv'));
        if ($mode === null
            || ! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)
            || ! in_array('campaignName', $dbColumns, true)
        ) {
            return;
        }

        $names = $query->clone()
            ->reorder()
            ->select('campaignName')
            ->distinct()
            ->pluck('campaignName');

        $named = [];
        $hasBlank = false;
        foreach ($names as $name) {
            $raw = (string) $name;
            if (trim($raw) === '') {
                $hasBlank = true;
                continue;
            }
            $named[$raw] = true;
        }

        $keep = [];
        if ($named !== []) {
            $metrics = AmazonAdsCampaignSkuMetrics::mapForCampaignNames(array_keys($named));
            foreach (array_keys($named) as $name) {
                if (AmazonAdsCampaignSkuMetrics::invMatchesFilter($metrics[$name]['inv'] ?? null, $mode)) {
                    $keep[] = $name;
                }
            }
        }

        $includeBlank = $mode === 'zero' && $hasBlank;
        if ($keep === [] && ! $includeBlank) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $w) use ($keep, $includeBlank) {
            foreach (array_chunk($keep, 500) as $chunk) {
                $w->orWhereIn('campaignName', $chunk);
            }
            if ($includeBlank) {
                $w->orWhereNull('campaignName')->orWhere('campaignName', '=', '');
            }
        });
    }

    /**
     * Empty or "all" means no status constraint. "__none__" matches nothing.
     * A comma-separated list matches any of ENABLED, PAUSED, ARCHIVED.
     *
     * @return array<int, string>|null
     */
    private static function campaignStatusFilterValues(Request $request): ?array
    {
        $raw = $request->input('filter_campaign_status');
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        $raw = trim((string) $raw);
        if ($raw === '' || strcasecmp($raw, 'all') === 0) {
            return null;
        }
        if ($raw === '__none__') {
            return [];
        }
        $allowed = ['ENABLED' => true, 'PAUSED' => true, 'ARCHIVED' => true];
        $out = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            $v = strtoupper(trim((string) $part));
            if (isset($allowed[$v])) {
                $out[$v] = $v;
            }
        }

        return array_values($out);
    }

    /**
     * Filter by campaignStatus when the column exists (SP/SB/SD reports). "All" = no constraint.
     */
    private static function applyCampaignStatusFilter(Builder $query, string $table, Request $request): void
    {
        $cols = SchemaMemo::getColumnListing($table);
        if (! in_array('campaignStatus', $cols, true)) {
            return;
        }
        $statuses = self::campaignStatusFilterValues($request);
        if ($statuses === null) {
            return;
        }
        if ($statuses === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereIn('campaignStatus', $statuses);
    }

    /**
     * Filter by L30 ACOS band (same first-match ranges as BGT Vs ACOS).
     * Request value is `band:{index}`, a 0-based index, or a band label / hex color.
     */
    private static function applyAcosColorFilter(Builder $query, string $table, Request $request): void
    {
        $raw = trim((string) $request->input('filter_acos', ''));
        if ($raw === '') {
            return;
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
        if (! in_array('campaign_id', $dbColumns, true)) {
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

        $hasAd = in_array('ad_type', $dbColumns, true);
        $select = ['campaign_id'];
        if ($hasAd) {
            $select[] = 'ad_type';
        }
        $pairs = $query->clone()->reorder()->select($select)->distinct()->get();
        $l30 = self::l30AcosSpendForCampaigns($table, array_values(array_unique(array_filter(array_map(
            static fn ($row): string => trim((string) ($row->campaign_id ?? '')),
            $pairs->all()
        )))));
        $map = [];
        foreach ($l30 as $cid => $v) {
            if (isset($v['acos']) && is_numeric($v['acos'])) {
                $map[(string) $cid] = (float) $v['acos'];
            }
        }
        $keep = [];
        foreach ($pairs as $row) {
            $cid = trim((string) ($row->campaign_id ?? ''));
            if ($cid === '') {
                continue;
            }
            $acos = $map[$cid] ?? null;
            if ($acos === null || self::firstAcosBandIndex((float) $acos, $bands) !== $idx) {
                continue;
            }
            $keep[$cid] = $cid;
        }
        if ($keep === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where(function (Builder $w) use ($keep) {
            foreach (array_chunk(array_values($keep), 500) as $chunk) {
                $w->orWhereIn('campaign_id', $chunk);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private static function firstAcosBandIndex(float $acos, array $bands): int
    {
        foreach ($bands as $i => $band) {
            $from = (float) ($band['acos_from'] ?? 0);
            $to = (float) ($band['acos_to'] ?? 9999);
            if ($acos >= $from && $acos <= $to) {
                return (int) $i;
            }
        }

        return -1;
    }

    /**
     * Campaign ids whose L30 ACOS% is below their GPFT% (the GPFT% column: the SKU's GPFT, or the
     * average of a PARENT campaign's child SKUs). A campaign with no L30 spend is never flagged,
     * so the green flag on the ACOS% cell means "ads are costing less than the margin".
     *
     * @return array<string, true> campaign_id => true
     */
    private static function acosBelowGpftCampaignIds(Builder $query, string $table): array
    {
        // The SKU metrics lookup is the slow part (seconds on all SP campaigns), and every grid draw asks for
        // the count, so reuse the result for a couple of minutes per identical filtered query.
        $cacheKey = 'amz_ads_acos_below_gpft:'.md5($table.'|'.$query->toSql().'|'.json_encode($query->getBindings()));

        return Cache::remember($cacheKey, 120, static fn (): array => self::computeAcosBelowGpftCampaignIds($query, $table));
    }

    /**
     * @return array<string, true>
     */
    private static function computeAcosBelowGpftCampaignIds(Builder $query, string $table): array
    {
        $dbColumns = SchemaMemo::getColumnListing($table);
        if (! in_array('campaign_id', $dbColumns, true) || ! in_array('campaignName', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true) || ! in_array('id', $dbColumns, true)) {
            return [];
        }

        $pairs = $query->clone()->reorder()
            ->select('campaign_id', DB::raw('MAX(campaignName) AS campaignName'))
            ->groupBy('campaign_id')
            ->get();
        if ($pairs->isEmpty()) {
            return [];
        }

        $names = [];
        foreach ($pairs as $pair) {
            $cn = trim((string) ($pair->campaignName ?? ''));
            if ($cn !== '') {
                $names[] = $cn;
            }
        }
        $skuByName = AmazonAdsCampaignSkuMetrics::mapForCampaignNames($names);

        $sliceByCid = [];
        foreach (self::fetchL30SummarySliceMap($table, $dbColumns, $pairs) as $key => $slice) {
            $sliceByCid[explode("\0", (string) $key)[0]] = $slice;
        }

        $out = [];
        foreach ($pairs as $pair) {
            $cid = trim((string) ($pair->campaign_id ?? ''));
            $cn = trim((string) ($pair->campaignName ?? ''));
            if ($cid === '' || $cn === '' || ! isset($sliceByCid[$cid])) {
                continue;
            }
            $gpft = $skuByName[$cn]['gpft'] ?? null;
            if (! is_numeric($gpft)) {
                continue;
            }
            $slice = $sliceByCid[$cid];
            $spend = $slice['spend'] ?? null;
            if ($spend === null || ! ((float) $spend > 0)) {
                continue;
            }
            $sold = $slice['purchases30d'] ?? null;
            $sales = $slice['sales30d'] ?? null;
            $acos = self::computedAcosPercentFromReportRow([
                'cost' => $spend,
                'spend' => $spend,
                'sales30d' => $sales,
                'sales' => $sales,
                'purchases30d' => $sold === null ? 0 : $sold,
                'purchases' => $sold === null ? 0 : $sold,
                'Prchase' => $sold === null ? 0 : $sold,
            ], $dbColumns);
            if ($acos !== null && $acos < (float) $gpft) {
                $out[$cid] = true;
            }
        }

        return $out;
    }

    /**
     * "ACOS% green flag" filter: keep only campaigns whose L30 ACOS% is below their GPFT%.
     * Request value `filter_acos_gpft` = 1.
     */
    private static function applyAcosGpftFlagFilter(Builder $query, string $table, Request $request): void
    {
        $raw = $request->input('filter_acos_gpft');
        if (! ($raw === true || $raw === 1 || $raw === '1' || $raw === 'true')) {
            return;
        }
        $keep = array_keys(self::acosBelowGpftCampaignIds($query, $table));
        if ($keep === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where(function (Builder $w) use ($keep) {
            foreach (array_chunk($keep, 500) as $chunk) {
                $w->orWhereIn('campaign_id', array_map('strval', $chunk));
            }
        });
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

        $dbColumns = SchemaMemo::getColumnListing($table);
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

    /**
     * @param  list<string>  $dbColumns
     */
    private static function tableSupportsLiveSyncStatus(string $table, array $dbColumns): bool
    {
        return in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)
            && in_array('campaign_id', $dbColumns, true);
    }

    private static function liveSyncChannelForTable(string $table): string
    {
        return $table === 'amazon_sb_campaign_reports' ? 'sb' : 'sp';
    }

    /**
     * @return array{green: int, yellow: int, red: int}
     */
    private static function emptyLiveSyncCounts(): array
    {
        return ['green' => 0, 'yellow' => 0, 'red' => 0];
    }

    /**
     * Faceted counts for one field: apply the other field's color filter, not this field's.
     *
     * @return array{green: int, yellow: int, red: int}
     */
    private static function liveSyncStatusCounts(Builder $query, string $table, string $field, Request $request): array
    {
        $field = $field === 'bid' ? 'bid' : 'bgt';
        try {
            $q = $query->clone()->reorder();
            $otherField = $field === 'bgt' ? 'bid' : 'bgt';
            $otherInput = $otherField === 'bgt' ? 'filter_bgt_sync' : 'filter_bid_sync';
            self::applyOneLiveSyncStatusFilter($q, $table, $request, $otherField, $otherInput);

            if (! SchemaMemo::hasTable('amazon_ads_live_sync_states')) {
                $total = (int) $q->clone()->count();

                return $total < 1
                    ? self::emptyLiveSyncCounts()
                    : ['green' => 0, 'yellow' => $total, 'red' => 0];
            }

            $inner = $q->clone()->select($table.'.campaign_id');
            if ($field === 'bid') {
                $inner->addSelect([$table.'.last_sbid', $table.'.sbid']);
            } elseif (in_array('campaignBudgetAmount', SchemaMemo::getColumnListing($table), true)) {
                $budgetSelect = [$table.'.campaignBudgetAmount'];
                if (SchemaMemo::hasColumn($table, 'sbgt')) {
                    $budgetSelect[] = $table.'.sbgt';
                }
                $inner->addSelect($budgetSelect);
            }
            $channel = self::liveSyncChannelForTable($table);
            $colorSql = self::liveSyncColorSql('r', $field, SchemaMemo::hasColumn($table, 'sbgt'));
            $row = DB::query()
                ->fromSub($inner, 'r')
                ->leftJoin('amazon_ads_live_sync_states as s', function ($join) use ($channel, $field) {
                    $join->on('s.campaign_id', '=', 'r.campaign_id')
                        ->where('s.channel', '=', $channel)
                        ->where('s.field', '=', $field);
                })
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN ({$colorSql}) = 'green' THEN 1 ELSE 0 END), 0) AS green_n, "
                    ."COALESCE(SUM(CASE WHEN ({$colorSql}) = 'yellow' THEN 1 ELSE 0 END), 0) AS yellow_n, "
                    ."COALESCE(SUM(CASE WHEN ({$colorSql}) = 'red' THEN 1 ELSE 0 END), 0) AS red_n"
                )
                ->first();

            return [
                'green' => (int) ($row->green_n ?? 0),
                'yellow' => (int) ($row->yellow_n ?? 0),
                'red' => (int) ($row->red_n ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('amazon-ads live sync status counts failed', [
                'table' => $table,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return self::emptyLiveSyncCounts();
        }
    }

    private static function applyLiveSyncStatusFilters(Builder $query, string $table, Request $request): void
    {
        self::applyOneLiveSyncStatusFilter($query, $table, $request, 'bgt', 'filter_bgt_sync');
        self::applyOneLiveSyncStatusFilter($query, $table, $request, 'bid', 'filter_bid_sync');
    }

    private static function applyOneLiveSyncStatusFilter(
        Builder $query,
        string $table,
        Request $request,
        string $field,
        string $input
    ): void {
        $color = AmazonAdsLiveSyncStatus::normalizeColor((string) $request->input($input, ''));
        if ($color === null) {
            return;
        }
        if (! SchemaMemo::hasTable('amazon_ads_live_sync_states')) {
            if ($color !== AmazonAdsLiveSyncStatus::YELLOW) {
                $query->whereRaw('0 = 1');
            }

            return;
        }
        self::constrainLiveSyncColor($query, $table, $field, $color);
    }

    /**
     * Same green / yellow / red as the dots on the row.
     * A displayed match is green even if an older sync failed. A real gap stays red only when the numbers still differ.
     */
    private static function liveSyncColorSql(string $alias, string $field, bool $storedSbgtColumn = false): string
    {
        $failed = "s.status = 'failed'";
        $synced = "s.status = 'synced'";
        if ($field === 'bid') {
            $match = self::storedBidMatchesSql($alias);
            $differ = self::storedBidDiffersSql($alias);

            return "CASE WHEN ({$match}) THEN 'green' WHEN {$failed} THEN 'red' WHEN {$synced} AND NOT ({$differ}) THEN 'green' ELSE 'yellow' END";
        }
        if ($field === 'bgt') {
            $match = $storedSbgtColumn
                ? self::storedBudgetMatchesSql($alias)
                : self::storedBudgetDesiredMatchesSql($alias);
            $differ = $storedSbgtColumn
                ? self::storedBudgetDiffersSql($alias)
                : self::storedBudgetDesiredDiffersSql($alias);

            return "CASE WHEN ({$match}) THEN 'green' WHEN {$failed} THEN 'red' WHEN {$synced} AND NOT ({$differ}) THEN 'green' ELSE 'yellow' END";
        }

        return "CASE WHEN {$failed} THEN 'red' WHEN {$synced} THEN 'green' ELSE 'yellow' END";
    }

    private static function constrainLiveSyncColor(Builder $query, string $table, string $field, string $color): void
    {
        $field = $field === 'bid' ? 'bid' : 'bgt';
        $channel = self::liveSyncChannelForTable($table);
        $t = str_replace('`', '', $table);
        $colorSql = self::liveSyncColorSql($t, $field, SchemaMemo::hasColumn($table, 'sbgt'));
        $query->whereRaw(
            '(SELECT '.$colorSql.' FROM (SELECT 1) AS amz_sync_one'
            .' LEFT JOIN amazon_ads_live_sync_states AS s'
            ." ON s.campaign_id = `{$t}`.`campaign_id` AND s.channel = ? AND s.field = ?"
            .' LIMIT 1) = ?',
            [$channel, $field, $color]
        );
    }

    private static function storedBidMatchesSql(string $table): string
    {
        $t = str_replace('`', '', $table);

        return "((`{$t}`.`last_sbid` + 0) > 0 AND (`{$t}`.`sbid` + 0) > 0 AND ROUND((`{$t}`.`last_sbid` + 0), 2) = ROUND((`{$t}`.`sbid` + 0), 2))";
    }

    private static function storedBidDiffersSql(string $table): string
    {
        $t = str_replace('`', '', $table);

        return "((`{$t}`.`last_sbid` + 0) > 0 AND (`{$t}`.`sbid` + 0) > 0 AND ROUND((`{$t}`.`last_sbid` + 0), 2) <> ROUND((`{$t}`.`sbid` + 0), 2))";
    }

    private static function storedBudgetAmountSql(string $table): string
    {
        $t = str_replace('`', '', $table);

        return $t === 'r' ? 'r.campaignBudgetAmount' : "`{$t}`.`campaignBudgetAmount`";
    }

    private static function storedSbgtColumnSql(string $table): string
    {
        $t = str_replace('`', '', $table);

        return $t === 'r' ? 'r.sbgt' : "`{$t}`.`sbgt`";
    }

    private static function storedBudgetMatchesSql(string $table): string
    {
        $budget = self::storedBudgetAmountSql($table);
        $sbgt = self::storedSbgtColumnSql($table);

        return "({$budget} IS NOT NULL AND {$sbgt} IS NOT NULL AND ABS(({$budget} + 0) - ({$sbgt} + 0)) <= 0.015)";
    }

    private static function storedBudgetDiffersSql(string $table): string
    {
        $budget = self::storedBudgetAmountSql($table);
        $sbgt = self::storedSbgtColumnSql($table);

        return "({$budget} IS NOT NULL AND {$sbgt} IS NOT NULL AND ABS(({$budget} + 0) - ({$sbgt} + 0)) > 0.015)";
    }

    private static function storedBudgetDesiredMatchesSql(string $table): string
    {
        $col = self::storedBudgetAmountSql($table);

        return "({$col} IS NOT NULL AND s.desired_value IS NOT NULL AND ABS(({$col} + 0) - (s.desired_value + 0)) <= 0.015)";
    }

    private static function storedBudgetDesiredDiffersSql(string $table): string
    {
        $col = self::storedBudgetAmountSql($table);

        return "({$col} IS NOT NULL AND s.desired_value IS NOT NULL AND ABS(({$col} + 0) - (s.desired_value + 0)) > 0.015)";
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return list<array<string, mixed>>
     */
    private static function attachLiveSyncStatusesToRows(array $data, string $table): array
    {
        if ($data === []) {
            return $data;
        }
        $states = ['bgt' => [], 'bid' => []];
        try {
            if (SchemaMemo::hasTable('amazon_ads_live_sync_states')) {
                $channel = self::liveSyncChannelForTable($table);
                $ids = [];
                foreach ($data as $row) {
                    $cid = trim((string) ($row['campaign_id'] ?? ''));
                    if ($cid !== '') {
                        $ids[] = $cid;
                    }
                    $digits = preg_replace('/\D+/', '', $cid) ?: '';
                    if ($digits !== '' && $digits !== $cid) {
                        $ids[] = $digits;
                    }
                }
                $ids = array_values(array_unique($ids));
                if ($ids !== []) {
                    $found = AmazonAdsLiveSyncState::query()
                        ->where('channel', $channel)
                        ->whereIn('field', ['bgt', 'bid'])
                        ->whereIn('campaign_id', $ids)
                        ->get();
                    foreach ($found as $state) {
                        $field = $state->field === 'bid' ? 'bid' : 'bgt';
                        $states[$field][(string) $state->campaign_id] = $state;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('amazon-ads live sync status attach failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return AmazonAdsLiveSyncStatus::attachToRows($data, $states);
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
        if (! SchemaMemo::hasTable($table)) {
            return null;
        }
        $cols = SchemaMemo::getColumnListing($table);
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

        // The scheduler recounts every 30 minutes and saving rules recounts at once.
        // Opening the page only needs a recount when there are none yet or the scheduler has stopped (>45 min old).
        try {
            $countedAt = AmazonAdsBgtCountStore::updatedAt();
            $fresh = $countedAt !== null
                && AmazonAdsBgtCountStore::read() !== null
                && Carbon::parse($countedAt)->gt(Carbon::now()->subMinutes(45));
            if (! $fresh) {
                AmazonAdsBgtCountRunner::startInBackground();
            }
        } catch (\Throwable) {
        }

        return view('amazon_ads.all', [
            'rawSources' => $rawSources,
            'defaultReportRangeDates' => $defaultReportRangeDates,
            'amazonAdsBgtRule' => AmazonAcosSbgtRule::resolvedRule(),
            'amazonAdsBgtViewsRule' => AmazonAdsBgtViewsRule::resolvedRule(),
            'amazonAdsBgtCvrRule' => AmazonAdsBgtCvrRule::resolvedRule(),
            'amazonAdsBgtPrcRule' => AmazonAdsBgtPrcRule::resolvedRule(),
            'amazonAdsBgtReviewsRule' => AmazonAdsBgtReviewsRule::resolvedRule(),
            'amazonAdsBgtDilRule' => AmazonAdsBgtDilRule::resolvedRule(),
            'amazonAdsBgtInvRule' => AmazonAdsBgtInvRule::resolvedRule(),
            'amazonAdsBgtGpftRule' => AmazonAdsBgtGpftRule::resolvedRule(),
            'amazonAdsBgtFlagRule' => AmazonAdsBgtFlagRule::resolvedRule(),
            'amazonAdsBgtSpendRule' => AmazonAdsBgtSpendRule::resolvedRule(),
            'amazonAdsBgtCounts' => AmazonAdsBgtCountStore::read(),
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
        self::forgetRawDataCache();
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

        return response()->json([
            'message' => 'BGT rule saved. SBGT on the grid will use the new ACOS → tier mapping after reload.',
            'rule' => $normalized,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist the last BGT chart counts so the modal can paint them without a live recount.
     */
    public function saveBgtCounts(Request $request): JsonResponse
    {
        $counts = $request->input('counts');
        if (! is_array($counts)) {
            return response()->json([
                'message' => 'Counts are required.',
                'status' => 422,
            ], 422);
        }

        try {
            AmazonAdsBgtCountStore::write($counts);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT counts.',
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'counts' => AmazonAdsBgtCountStore::read(),
            'status' => 200,
        ]);
    }

    /**
     * Saved BGT chart counts. The page paints these while a background recount runs.
     */
    public function getBgtCounts(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'counts' => AmazonAdsBgtCountStore::read(),
            'updated_at' => AmazonAdsBgtCountStore::updatedAt(),
            'running' => AmazonAdsBgtCountRunner::isRunning(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private const COUNT_HISTORY_CHANNEL = 'amazon_ads_count_hist';

    /**
     * Daily slab counts for the history dots in the SBID and BGT rule modals. One snapshot per PDT day,
     * the same way the Std prc vs dil history works.
     */
    public function getCountHistory(Request $request): JsonResponse
    {
        $days = max(7, min(90, (int) $request->input('days', 30)));
        $row = \App\Models\ChannelTabulatorColumnSetting::query()
            ->where('channel_name', self::COUNT_HISTORY_CHANNEL)
            ->first();
        $hist = is_array($row?->visibility) ? $row->visibility : [];
        $end = Carbon::now('America/Los_Angeles')->startOfDay();
        $start = $end->copy()->subDays($days - 1);
        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $counts = is_array($hist[$key] ?? null) ? $hist[$key] : [];
            $out[] = array_merge(['date' => $key, 'label' => $d->format('m-d')], $counts);
        }

        return response()->json(['success' => true, 'days' => $days, 'data' => $out])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Merge today's counts into the daily snapshot. Each modal sends only its own keys, so they are merged
     * rather than replaced.
     */
    public function saveCountHistory(Request $request): JsonResponse
    {
        $incoming = $request->input('counts');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'counts required'], 422);
        }
        $counts = [];
        foreach ($incoming as $key => $value) {
            $key = (string) $key;
            if (! preg_match('/^[a-z][a-z0-9_]*:[a-z0-9._-]{1,40}$/', $key) || ! is_numeric($value)) {
                continue;
            }
            $counts[$key] = round((float) $value, 2);
            if (count($counts) >= 300) {
                break;
            }
        }
        if ($counts === []) {
            return response()->json(['success' => false, 'message' => 'no valid counts'], 422);
        }

        $today = Carbon::now('America/Los_Angeles')->toDateString();
        $cutoff = Carbon::now('America/Los_Angeles')->subDays(89)->toDateString();
        $row = \App\Models\ChannelTabulatorColumnSetting::query()->firstOrNew([
            'channel_name' => self::COUNT_HISTORY_CHANNEL,
        ]);
        $hist = is_array($row->visibility) ? $row->visibility : [];
        $day = is_array($hist[$today] ?? null) ? $hist[$today] : [];
        // A modal repaints with a new set of slab keys after a rule edit, so drop its previous keys first.
        foreach ($counts as $key => $value) {
            $prefix = explode(':', $key, 2)[0];
            foreach (array_keys($day) as $existing) {
                if (str_starts_with((string) $existing, $prefix.':')) {
                    unset($day[$existing]);
                }
            }
        }
        $hist[$today] = array_merge($day, $counts);
        foreach (array_keys($hist) as $date) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $cutoff) {
                unset($hist[$date]);
            }
        }
        ksort($hist);
        $row->visibility = $hist;
        $row->column_order = ['history'];
        $row->save();

        return response()->json(['success' => true, 'date' => $today]);
    }

    /**
     * Recalculate BGT chart counts in the background.
     */
    public function refreshBgtCounts(): JsonResponse
    {
        try {
            AmazonAdsBgtCountRunner::startInBackground();
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Could not start the count.',
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'running' => true,
        ]);
    }

    /**
     * Current View L7 → Bgt Views rule (BGT Vs VIEWS modal).
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
     * Persist View L7 bands → Bgt Views; grid uses the new mapping after reload.
     */
    public function saveBgtViewsRule(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
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
            'message' => 'BGT Vs VIEWS saved. Bgt Views on the grid will use the new View L7 bands after reload.',
            'rule' => $normalized,
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
        self::forgetRawDataCache();
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
            'rule' => $normalized,
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
        self::forgetRawDataCache();
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
            'rule' => $normalized,
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
        self::forgetRawDataCache();
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
            'rule' => $normalized,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current Dil% → Bgt Dil rule (BGT Vs Dil modal).
     */
    public function getBgtDilRule(): JsonResponse
    {
        AmazonAdsBgtDilRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtDilRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist Dil% bands → Bgt Dil; grid uses the new mapping after reload.
     */
    public function saveBgtDilRule(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
        try {
            $normalized = AmazonAdsBgtDilRule::normalizeRule($request->all());
            AmazonAdsBgtDilRule::persistRule($normalized);
            AmazonAdsBgtDilRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save BGT Vs Dil rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'BGT Vs Dil saved. Bgt Dil on the grid will use the new Dil% bands after reload.',
            'rule' => $normalized,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current L30 spend → Bgt Spend rule (Spend Rule column).
     */
    public function getBgtSpendRule(): JsonResponse
    {
        AmazonAdsBgtSpendRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtSpendRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist L30 spend bands → Bgt Spend.
     */
    public function saveBgtSpendRule(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
        try {
            $normalized = AmazonAdsBgtSpendRule::normalizeRule($request->all());
            AmazonAdsBgtSpendRule::persistRule($normalized);
            AmazonAdsBgtSpendRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save Spend Rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'Spend Rule saved.',
            'rule' => $normalized,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Current on-hand inventory → Bgt Inv rule.
     */
    public function getBgtInvRule(): JsonResponse
    {
        AmazonAdsBgtInvRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtInvRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist inventory slabs → Bgt Inv.
     */
    public function saveBgtInvRule(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
        try {
            $normalized = AmazonAdsBgtInvRule::normalizeRule($request->all());
            AmazonAdsBgtInvRule::persistRule($normalized);
            AmazonAdsBgtInvRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save Inv Rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'Inv Rule saved.',
            'rule' => $normalized,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    public function getBgtGpftRule(): JsonResponse
    {
        AmazonAdsBgtGpftRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtGpftRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist GPFT% slabs → Bgt GPFT.
     */
    public function saveBgtGpftRule(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
        try {
            $normalized = AmazonAdsBgtGpftRule::normalizeRule($request->all());
            AmazonAdsBgtGpftRule::persistRule($normalized);
            AmazonAdsBgtGpftRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save GPFT Rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'GPFT Rule saved.',
            'rule' => $normalized,
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    public function getBgtFlagRule(): JsonResponse
    {
        AmazonAdsBgtFlagRule::forgetResolvedCache();

        return response()->json([
            'rule' => AmazonAdsBgtFlagRule::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Persist FLAG gap slabs (GPFT% − ACOS%, flagged campaigns only) → Bgt Flag.
     */
    public function saveBgtFlagRule(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
        try {
            $normalized = AmazonAdsBgtFlagRule::normalizeRule($request->all());
            AmazonAdsBgtFlagRule::persistRule($normalized);
            AmazonAdsBgtFlagRule::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save FLAG Rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'FLAG Rule saved.',
            'rule' => $normalized,
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
        if (! SchemaMemo::hasTable('amazon_ads_campaign_skus')) {
            return response()->json(['message' => 'Campaign SKU table is missing. Run amazon:ads-pull-product-ads.', 'skus' => []], 404);
        }

        $resolved = AmazonAdsCampaignSkuSync::resolveForCampaign(
            $cid,
            trim((string) $request->query('campaign_name', ''))
        );
        $listed = $resolved['skus'];
        $isSb = AmazonAdsSbAdEditor::campaignIsSb($cid);
        $onAd = [];
        foreach ($listed as $row) {
            if ($isSb && ($row['source'] ?? '') === 'campaign_name') {
                continue;
            }
            $onAd[] = $row;
        }
        $name = $resolved['campaign_name'] !== '' ? $resolved['campaign_name'] : '';
        $parentFamily = AmazonAdsCampaignSkuMetrics::parentFamilyFromCampaignName($name);
        $parentNames = $parentFamily !== ''
            ? AmazonAdsCampaignSkuMetrics::childSkusForParentFamily($parentFamily)
            : [];
        $onAdKeys = [];
        foreach ($onAd as $row) {
            $ck = AmazonDatasheet::normalizeSkuForLookup((string) ($row['sku'] ?? ''));
            if ($ck !== '') {
                $onAdKeys[$ck] = true;
            }
        }
        $parentRows = [];
        foreach ($parentNames as $sku) {
            $ck = AmazonDatasheet::normalizeSkuForLookup($sku);
            if ($ck === '' || isset($onAdKeys[$ck])) {
                continue;
            }
            $parentRows[] = [
                'sku' => $sku,
                'asin' => null,
                'state' => null,
                'source' => 'parent',
            ];
        }
        $asinNeed = [];
        foreach (array_merge($onAd, $parentRows) as $row) {
            if (trim((string) ($row['asin'] ?? '')) === '') {
                $asinNeed[] = (string) ($row['sku'] ?? '');
            }
        }
        $asinBySku = $asinNeed !== [] ? AmazonAdsCampaignSkuSync::asinsBySkus($asinNeed) : [];
        foreach ($onAd as $i => $row) {
            if (trim((string) ($row['asin'] ?? '')) !== '') {
                continue;
            }
            $sku = (string) ($row['sku'] ?? '');
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
            $onAd[$i]['asin'] = $asinBySku[$key] ?? $asinBySku[$sku] ?? null;
        }
        foreach ($parentRows as $i => $row) {
            $sku = (string) ($row['sku'] ?? '');
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
            $parentRows[$i]['asin'] = $asinBySku[$key] ?? $asinBySku[$sku] ?? null;
        }
        $onAd = $this->decorateCampaignSkuRows($onAd);
        $parentRows = $this->decorateCampaignSkuRows($parentRows);

        return response()->json([
            'campaign_id' => $cid,
            'campaign_name' => $name !== '' ? $name : null,
            'source' => $resolved['source'],
            'channel' => $isSb ? 'sb' : 'sp',
            'parent_family' => $parentFamily !== '' ? $parentFamily : null,
            'skus' => $onAd,
            'parent_skus' => $parentRows,
            'count' => count($onAd),
            'parent_count' => count($parentRows),
        ]);
    }

    /**
     * Add products to an existing SB creative. SB only.
     */
    public function addSbAdProducts(Request $request, AmazonAdsSbAdEditor $editor): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->input('campaign_id', ''))) ?: '';
        $result = $editor->add($cid, $this->stringList($request->input('skus')), $this->stringList($request->input('asins')));
        $status = ! empty($result['success']) ? 200 : ((string) ($result['message'] ?? '') === 'Campaign ID is required.' ? 422 : 422);

        return response()->json($result, $status);
    }

    /**
     * Remove products from an SB creative. Deletes the SB ad when the last product is removed.
     */
    public function removeSbAdProducts(Request $request, AmazonAdsSbAdEditor $editor): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->input('campaign_id', ''))) ?: '';
        $result = $editor->remove($cid, $this->stringList($request->input('skus')), $this->stringList($request->input('asins')));
        $status = ! empty($result['success']) ? 200 : 422;

        return response()->json($result, $status);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @return list<array<string, mixed>>
     */
    private function decorateCampaignSkuRows(array $skus): array
    {
        if ($skus === []) {
            return [];
        }
        $names = array_column($skus, 'sku');
        $reviews = AmazonAdsCampaignSkuMetrics::reviewsBySkus($names);
        $priceLmp = AmazonAdsCampaignSkuMetrics::priceAndLmpBySkus($names);
        $shopify = SchemaMemo::hasTable('shopify_skus')
            ? ShopifySku::mapByProductSkus($names)
            : collect();
        foreach ($skus as $i => $skuRow) {
            $sku = (string) ($skuRow['sku'] ?? '');
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
            $hit = $reviews[$key] ?? null;
            $pl = $priceLmp[$key] ?? null;
            if ($pl === null && $key !== '') {
                foreach ($priceLmp as $pk => $row) {
                    if (strcasecmp((string) $pk, $key) === 0) {
                        $pl = $row;
                        break;
                    }
                }
            }
            $sh = $shopify->get($sku);
            if ($sh === null && $sku !== '') {
                foreach ($shopify as $pmSku => $row) {
                    if (strcasecmp(trim((string) $pmSku), trim($sku)) === 0) {
                        $sh = $row;
                        break;
                    }
                }
            }
            $inv = $sh !== null && is_numeric($sh->inv ?? null) ? (int) round((float) $sh->inv) : null;
            $image = $sh !== null ? trim((string) ($sh->image_src ?? '')) : '';
            $skus[$i]['amz_avg_rating'] = is_array($hit) && $hit['rating'] !== null
                ? (float) $hit['rating']
                : null;
            $skus[$i]['amz_review_count'] = is_array($hit) ? (int) ($hit['review_count'] ?? 0) : null;
            $skus[$i]['inv'] = $inv;
            $skus[$i]['image'] = $image !== '' ? $image : null;
            $skus[$i]['price'] = is_array($pl) ? ($pl['price'] ?? null) : null;
            $skus[$i]['lmp'] = is_array($pl) ? ($pl['lmp'] ?? null) : null;
            $skus[$i]['lmp_count'] = is_array($pl) ? (int) ($pl['lmp_count'] ?? 0) : 0;
            $skus[$i]['competitors'] = is_array($pl) ? ($pl['competitors'] ?? []) : [];
        }

        return $skus;
    }

    /**
     * Daily CPC for one campaign (Amazon report rows, YYYY-MM-DD), newest 365 days.
     */
    public function cpcAvgHistory(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }

        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        if ($table === null || ! SchemaMemo::hasTable($table)) {
            return response()->json(['ok' => false, 'message' => 'No daily CPC table for this row.', 'points' => []], 404);
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
        $costCol = in_array('cost', $dbColumns, true) ? 'cost' : (in_array('spend', $dbColumns, true) ? 'spend' : null);
        $hasClicks = in_array('clicks', $dbColumns, true);
        $hasCpc = in_array('costPerClick', $dbColumns, true);
        if (! $hasCpc && ! ($costCol !== null && $hasClicks)) {
            return response()->json(['ok' => true, 'points' => []]);
        }

        $select = ['report_date_range'];
        if ($costCol !== null) {
            $select[] = $costCol;
        }
        if ($hasClicks) {
            $select[] = 'clicks';
        }
        if ($hasCpc) {
            $select[] = 'costPerClick';
        }

        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }

        $q = DB::table($table)
            ->select($select)
            ->where('campaign_id', $cid)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->orderBy('report_date_range', 'desc')
            ->limit($days === 0 ? 2000 : $days);
        $adType = trim((string) $request->query('ad_type', ''));
        if ($adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $q->where('ad_type', $adType);
        }

        $points = [];
        foreach ($q->get()->reverse()->values() as $row) {
            $r = (array) $row;
            $day = trim((string) ($r['report_date_range'] ?? ''));
            if ($day === '') {
                continue;
            }
            $cpc = null;
            if ($costCol !== null && $hasClicks) {
                $clicks = (float) ($r['clicks'] ?? 0);
                $cost = (float) ($r[$costCol] ?? 0);
                if ($clicks > 0 && $cost > 0) {
                    $n = $cost / $clicks;
                    $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
                }
            }
            if ($cpc === null && $hasCpc) {
                $n = (float) ($r['costPerClick'] ?? 0);
                $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
            }
            if ($cpc === null) {
                continue;
            }
            $points[] = ['date' => $day, 'cpc' => $cpc];
        }

        return response()->json(['ok' => true, 'points' => $points]);
    }

    /**
     * Daily L1–L7 ads spend and ads sales for the L1 Spend / L1 Sales icon.
     * L1 is the newest daily report day; L2 is the day before that (same as the L2SP column).
     */
    public function lRangeHistory(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }
        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        if ($table === null || ! SchemaMemo::hasTable($table)) {
            return response()->json(['ok' => false, 'message' => 'No daily report table for this row.', 'points' => []], 404);
        }
        $dbColumns = SchemaMemo::getColumnListing($table);
        $latest = self::latestDailyReportYmdInTable($table);
        if ($latest === null || $latest === '') {
            $latest = Carbon::now(config('app.timezone'))->subDay()->format('Y-m-d');
        }
        $labels = [];
        try {
            $anchor = Carbon::parse($latest, config('app.timezone'));
            $labels['L1'] = $anchor->format('Y-m-d');
            for ($n = 2; $n <= 7; $n++) {
                $labels['L'.$n] = $anchor->copy()->subDays($n - 1)->format('Y-m-d');
            }
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'Could not resolve L1–L7 dates.', 'points' => []], 422);
        }
        $dates = array_values(array_unique(array_values($labels)));
        $select = self::lRangeMetricSelectColumns($dbColumns, false);
        $adType = trim((string) $request->query('ad_type', ''));
        $loadDaily = function (?string $adFilter) use ($table, $select, $cid, $dates, $dbColumns) {
            $q = DB::table($table)
                ->select($select)
                ->where('campaign_id', $cid)
                ->whereIn('report_date_range', $dates)
                ->orderByDesc('id');
            if ($adFilter !== null && $adFilter !== '' && in_array('ad_type', $dbColumns, true)) {
                $q->where('ad_type', $adFilter);
            }

            return $q->get();
        };
        $rows = $loadDaily($adType);
        if ($rows->isEmpty() && $adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $rows = $loadDaily(null);
        }
        $byDate = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $day = trim((string) ($r['report_date_range'] ?? ''));
            if ($day === '' || isset($byDate[$day])) {
                continue;
            }
            $spend = AmazonAdsLRangeMetrics::spendFromRow($r, $dbColumns);
            $sales = AmazonAdsLRangeMetrics::salesFromRow($r, $dbColumns, 'daily');
            $byDate[$day] = [
                'spend' => $spend,
                'sales' => $sales,
            ];
        }
        $points = [];
        foreach ($labels as $label => $day) {
            $hit = $byDate[$day] ?? ['spend' => null, 'sales' => null];
            $points[] = [
                'label' => $label,
                'date' => $day,
                'spend' => $hit['spend'],
                'sales' => $hit['sales'],
            ];
        }

        return response()->json([
            'ok' => true,
            'campaign_id' => $cid,
            'l1_date' => $latest,
            'points' => $points,
        ]);
    }

    /**
     * Daily ads spend history for the Y Spend history dot (same chart modal as CPC / SBID).
     */
    public function ySpendHistory(Request $request): JsonResponse
    {
        return $this->dailyAdsMetricHistory($request, 'spend', 'yspend');
    }

    /**
     * Daily ads sales history for the Y Sales history dot (same chart modal as CPC / SBID).
     */
    public function ySalesHistory(Request $request): JsonResponse
    {
        return $this->dailyAdsMetricHistory($request, 'sales', 'ysales');
    }

    /**
     * Rolling history for the Yesterday Spend / Ads Sales / ACOS badges.
     * Same campaign universe and the same L1 columns as those badges (cost, sales30d).
     * The latest point is the badge total. Older points are that column on each calendar day.
     */
    public function yesterdayBadgeHistory(Request $request): JsonResponse
    {
        $metric = strtolower(trim((string) $request->query('metric', 'yspend')));
        if (! in_array($metric, ['yspend', 'ysales', 'yacos', 'tones'], true)) {
            $metric = 'yspend';
        }
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 2, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }
        $emptyTones = ['yspend' => 'gray', 'ysales' => 'gray', 'yacos' => 'gray'];
        $tables = self::yesterdayBadgeHistoryTables($request->query('source'));
        if ($tables === []) {
            return response()->json([
                'ok' => true,
                'points' => [],
                'tones' => $emptyTones,
                'message' => 'Yesterday history is available for SP and SB.',
            ]);
        }

        $end = null;
        foreach ($tables as $table) {
            $latest = self::latestDailyReportYmdInTable($table);
            if (is_string($latest) && $latest !== '' && ($end === null || $latest > $end)) {
                $end = $latest;
            }
        }
        if ($end === null) {
            return response()->json(['ok' => true, 'points' => [], 'tones' => $emptyTones]);
        }

        $span = $days === 0 ? 364 : max(0, $days - 1);
        $start = Carbon::parse($end)->subDays($span)->toDateString();
        $byDate = [];
        $badgeSpend = 0.0;
        $badgeSales = 0.0;
        $haveBadge = false;
        foreach ($tables as $table) {
            $byDate = AmazonAdsYesterdayBadgeHistory::mergeDaily(
                $byDate,
                self::dailySpendSalesForFilteredCampaigns($request, $table, $start, $end)
            );
            if (! SchemaMemo::hasTable($table)) {
                continue;
            }
            $dbColumns = self::orderedColumnsForTable($table);
            $universe = self::badgeUniverseQuery($request, $table, $dbColumns);
            if ($universe === null) {
                continue;
            }
            $agg = self::sumLatestSummaryMetricsForFilteredCampaigns($universe, $table, $dbColumns, 'L1');
            $badgeSpend += (float) $agg['cost_sum'];
            $badgeSales += (float) $agg['sales_sum'];
            $haveBadge = true;
        }
        if ($haveBadge) {
            // Rightmost point is the Yesterday badge itself (L1 cost + sales30d), not a different daily column.
            $byDate[$end] = [
                'spend' => $badgeSpend,
                'sales' => $badgeSales,
            ];
        }
        if ($byDate === []) {
            return response()->json(['ok' => true, 'points' => [], 'tones' => $emptyTones]);
        }
        ksort($byDate);
        $first = (string) array_key_first($byDate);
        if ($first > $start) {
            $start = $first;
        }
        $series = AmazonAdsYesterdayBadgeHistory::series($byDate, $start, $end);
        $tones = AmazonAdsYesterdayBadgeHistory::tones($series);
        $chartMetric = $metric === 'tones' ? 'yspend' : $metric;

        return response()->json([
            'ok' => true,
            'metric' => $chartMetric,
            'tone' => $tones[$chartMetric] ?? 'gray',
            'tones' => $tones,
            'points' => $metric === 'tones' ? [] : AmazonAdsYesterdayBadgeHistory::chartPoints($series, $chartMetric),
        ]);
    }

    /**
     * History for every KPI tile on the summary cards (Yesterday / L30 / Projected / Utilized Y / Utilized L7).
     * Same campaign universe as the Yesterday badges; one daily row per campaign (+ ad_type) and day, combined here:
     *   y_*   = that day,  l30_* = rolling 30 days ending that day,  p_* = rolling 7 days ending that day (× 30 ÷ 7 for volume),
     *   u1_* / u7_* = campaigns per utilisation band that day (U1% = day spend ÷ budget, U7% = 7-day spend ÷ 7-day budget).
     * metric=tones → last two days for all keys (dots). metric=<key> → daily points for that key (chart).
     */
    public function kpiBadgeHistory(Request $request): JsonResponse
    {
        $keys = self::kpiHistoryKeys();
        $metric = trim((string) $request->query('metric', 'tones'));
        if ($metric !== 'tones' && ! in_array($metric, $keys, true)) {
            $metric = 'tones';
        }
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 2, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }
        if ($metric === 'tones') {
            $days = 2;
        }
        $tables = self::yesterdayBadgeHistoryTables($request->query('source'));
        if ($tables === []) {
            return response()->json(['ok' => true, 'points' => [], 'values' => [], 'prev' => [], 'message' => 'History is available for SP and SB.']);
        }

        $end = null;
        foreach ($tables as $table) {
            $latest = self::latestDailyReportYmdInTable($table);
            if (is_string($latest) && $latest !== '' && ($end === null || $latest > $end)) {
                $end = $latest;
            }
        }
        if ($end === null) {
            return response()->json(['ok' => true, 'points' => [], 'values' => [], 'prev' => []]);
        }

        $span = $days === 0 ? 364 : max(1, $days - 1);
        $start = Carbon::parse($end)->subDays($span)->toDateString();
        $from = Carbon::parse($start)->subDays(29)->toDateString();

        $camp = [];
        foreach ($tables as $table) {
            foreach (self::dailyCampaignRowsForFilteredCampaigns($request, $table, $from, $end) as $r) {
                $camp[$table.'|'.$r['key']][$r['day']] = $r;
            }
        }
        if ($camp === []) {
            return response()->json(['ok' => true, 'points' => [], 'values' => [], 'prev' => []]);
        }

        // Portfolio totals per day.
        $tot = [];
        foreach ($camp as $byDay) {
            foreach ($byDay as $day => $r) {
                if (! isset($tot[$day])) {
                    $tot[$day] = ['spend' => 0.0, 'sales' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'budget' => 0.0];
                }
                foreach (['spend', 'sales', 'clicks', 'sold', 'budget'] as $f) {
                    $tot[$day][$f] += (float) $r[$f];
                }
            }
        }

        $firstData = array_key_first($tot);
        ksort($tot);
        $firstData = (string) array_key_first($tot);
        if ($firstData > $start) {
            $start = $firstData;
        }
        $window = static function (array $tot, string $day, int $len): array {
            $sum = ['spend' => 0.0, 'sales' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'budget' => 0.0];
            $d = new \DateTimeImmutable($day);
            for ($i = 0; $i < $len; $i++) {
                $row = $tot[$d->modify('-'.$i.' day')->format('Y-m-d')] ?? null;
                if ($row === null) {
                    continue;
                }
                foreach ($sum as $f => $_) {
                    $sum[$f] += $row[$f];
                }
            }

            return $sum;
        };
        $derive = static function (array $w, int $len, bool $project): array {
            $mul = $project ? 30 / 7 : 1;
            $spend = $w['spend'];
            $o = [];
            $o['bgt'] = $len > 0 ? $w['budget'] / $len : null;
            $o['util'] = $w['budget'] > 0 ? $spend / $w['budget'] * 100 : null;
            $o['bid'] = $w['clicks'] > 0 ? $spend / $w['clicks'] : null;
            $o['cvr'] = $w['clicks'] > 0 ? $w['sold'] / $w['clicks'] * 100 : null;
            $o['acos'] = AmazonAdsYesterdayBadgeHistory::acos($spend, $w['sales']);
            $o['clicks'] = $w['clicks'] * $mul;
            $o['sold'] = $w['sold'] * $mul;
            $o['sales'] = $w['sales'] * $mul;
            $o['spend'] = $spend * $mul;
            $o['bgt_total'] = $o['bgt'] === null ? null : $o['bgt'] * 30;

            return $o;
        };
        $band = static function (float $spend, float $budget): string {
            if ($budget <= 0) {
                return 'na';
            }
            $u = $spend / $budget * 100;

            return $u < 66 ? 'red' : ($u <= 99 ? 'green' : 'pink');
        };

        $series = [];
        $cursor = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);
        while ($cursor <= $last) {
            $day = $cursor->format('Y-m-d');
            $vals = [];
            foreach ($derive($tot[$day] ?? ['spend' => 0.0, 'sales' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'budget' => 0.0], 1, false) as $k => $v) {
                if ($k !== 'bgt_total') {
                    $vals['y_'.$k] = $v;
                }
            }
            foreach ($derive($window($tot, $day, 30), 30, false) as $k => $v) {
                $vals['l30_'.$k] = $v;
            }
            foreach ($derive($window($tot, $day, 7), 7, true) as $k => $v) {
                $vals['p_'.$k] = $v;
            }
            // Utilisation bands: U1% (day) and U7% (7 days).
            foreach (['u1' => 1, 'u7' => 7] as $uk => $len) {
                $cnt = ['green' => 0, 'red' => 0, 'pink' => 0];
                $spSum = 0.0;
                $bdSum = 0.0;
                foreach ($camp as $byDay) {
                    $sp = 0.0;
                    $bd = 0.0;
                    $seen = false;
                    for ($i = 0; $i < $len; $i++) {
                        $r = $byDay[$cursor->modify('-'.$i.' day')->format('Y-m-d')] ?? null;
                        if ($r === null) {
                            continue;
                        }
                        $seen = true;
                        $sp += $r['spend'];
                        $bd += $r['budget'];
                    }
                    if (! $seen) {
                        continue;
                    }
                    $b = $band($sp, $bd);
                    if ($b !== 'na') {
                        $cnt[$b]++;
                        $spSum += $sp;
                        $bdSum += $bd;
                    }
                }
                $vals[$uk.'_green'] = $cnt['green'];
                $vals[$uk.'_red'] = $cnt['red'];
                $vals[$uk.'_pink'] = $cnt['pink'];
                $vals[$uk.'_avg'] = $bdSum > 0 ? $spSum / $bdSum * 100 : null;
            }
            foreach ($vals as $k => $v) {
                $vals[$k] = $v === null ? null : round((float) $v, 4);
            }
            $series[] = ['date' => $day] + $vals;
            $cursor = $cursor->modify('+1 day');
        }

        // The newest point is the tile itself (same L1 / L7 / L30 summary rows and the same formulas as the cards), so the
        // chart and the dots always end on the number shown. Older points are rebuilt from the daily rows.
        // Dots (metric=tones) compare two days built the same way, so they use the daily series before this swap: the L30 / L7
        // summary rows count sold / sales differently from the sum of daily rows, which would fake a jump on the last day.
        $tileValues = $metric === 'tones' ? null : self::kpiTileValuesForTables($request, $tables);
        if ($tileValues !== null && $series !== []) {
            $lastIdx = count($series) - 1;
            foreach ($tileValues as $k => $v) {
                $series[$lastIdx][$k] = $v === null ? null : round((float) $v, 4);
            }
        }

        $n = count($series);
        if ($metric === 'tones') {
            $cur = $n >= 1 ? $series[$n - 1] : [];
            $prev = $n >= 2 ? $series[$n - 2] : [];
            unset($cur['date'], $prev['date']);

            return response()->json(['ok' => true, 'end_date' => $end, 'values' => $cur, 'prev' => $prev, 'points' => []]);
        }
        $points = [];
        foreach ($series as $row) {
            if ($row[$metric] === null) {
                continue;
            }
            $points[] = ['date' => $row['date'], 'value' => $row[$metric]];
        }

        return response()->json(['ok' => true, 'metric' => $metric, 'points' => $points]);
    }

    /**
     * Tile values exactly as the summary cards show them (payload.kpiBlocks + the existing ACOS / projected totals),
     * summed over the given tables. Null when no table has data.
     *
     * @param  list<string>  $tables
     * @return array<string, float|int|null>|null
     */
    private static function kpiTileValuesForTables(Request $request, array $tables): ?array
    {
        $blocks = null;
        foreach ($tables as $table) {
            if (! SchemaMemo::hasTable($table)) {
                continue;
            }
            $dbColumns = self::orderedColumnsForTable($table);
            if (! in_array('campaign_id', $dbColumns, true) || ! in_array('cost', $dbColumns, true)) {
                continue;
            }
            $universe = self::badgeUniverseQuery($request, $table, $dbColumns);
            if ($universe === null) {
                continue;
            }
            $columns = self::displayColumnsForTable($table);
            $l30 = self::aggregateL30CostAndSalesDistinctForFilteredAmazonAdsRows($universe, $table, $dbColumns, $columns);
            if ($l30 === null) {
                continue;
            }
            $l1 = self::sumLatestSummaryMetricsForFilteredCampaigns($universe, $table, $dbColumns, 'L1');
            $l7 = self::sumLatestSummaryMetricsForFilteredCampaigns($universe, $table, $dbColumns, 'L7');
            $l30b = self::sumLatestSummaryMetricsForFilteredCampaigns($universe, $table, $dbColumns, 'L30');
            $part = [
                'y' => ['spend' => (float) $l1['cost_sum'], 'sales' => (float) $l1['sales_sum'], 'clicks' => (float) $l1['clicks_sum'], 'sold' => (float) $l1['purchases_sum'], 'budget' => (float) $l1['budget_sum']],
                'l7' => ['spend' => (float) $l7['cost_sum'], 'sales' => (float) $l7['sales_sum'], 'clicks' => (float) $l7['clicks_sum'], 'sold' => (float) $l7['purchases_sum'], 'budget' => (float) $l7['budget_sum']],
                'l30' => ['spend' => (float) $l30['cost_sum'], 'sales' => (float) $l30['sales_sum'], 'clicks' => (float) $l30['clicks_sum'], 'sold' => (float) $l30['purchases_sum'], 'budget' => (float) $l30b['budget_sum']],
                'u1' => self::utilBucketsLatestSummaryForFilteredCampaigns($universe, $table, $dbColumns, 'L1', 1),
                'u7' => self::utilBucketsLatestSummaryForFilteredCampaigns($universe, $table, $dbColumns, 'L7', 7),
            ];
            $blocks = self::sumKpiBlocks($blocks, $part);
        }
        if ($blocks === null) {
            return null;
        }

        $derive = static function (array $w, int $days, float $acos): array {
            return [
                'bgt' => $w['budget'],
                'util' => $w['budget'] > 0 ? $w['spend'] / ($w['budget'] * $days) * 100 : null,
                'bid' => $w['clicks'] > 0 ? $w['spend'] / $w['clicks'] : null,
                'cvr' => $w['clicks'] > 0 ? $w['sold'] / $w['clicks'] * 100 : null,
                'acos' => $acos,
                'clicks' => $w['clicks'],
                'sold' => $w['sold'],
                'sales' => $w['sales'],
                'spend' => $w['spend'],
            ];
        };
        $y = $blocks['y'];
        $l30 = $blocks['l30'];
        $l7 = $blocks['l7'];
        $out = [];
        foreach ($derive($y, 1, self::overallAcosPercentFromAggregatedSums($y['spend'], $y['sales'])) as $k => $v) {
            $out['y_'.$k] = $v;
        }
        foreach ($derive($l30, 30, self::overallAcosPercentFromAggregatedSums($l30['spend'], $l30['sales'])) as $k => $v) {
            $out['l30_'.$k] = $v;
        }
        $out['l30_bgt_total'] = $l30['budget'] * 30;

        // Projected: spend / sales = L7 ÷ 7 × 30 (rounded like the Projected badges); clicks / sold the same; ratios from L7.
        $pSpend = self::projectMonthFromLast7($l7['spend']);
        $pSales = self::projectMonthFromLast7($l7['sales']);
        $p = $derive($l7, 7, self::overallAcosPercentFromAggregatedSums($pSpend, $pSales));
        foreach ($p as $k => $v) {
            $out['p_'.$k] = $v;
        }
        $out['p_clicks'] = $l7['clicks'] / 7 * 30;
        $out['p_sold'] = $l7['sold'] / 7 * 30;
        $out['p_sales'] = $pSales;
        $out['p_spend'] = $pSpend;
        $out['p_bgt_total'] = $l7['budget'] * 30;

        foreach (['u1', 'u7'] as $u) {
            $b = $blocks[$u];
            $out[$u.'_green'] = $b['66_99'];
            $out[$u.'_red'] = $b['lt66'];
            $out[$u.'_pink'] = $b['gt99'];
            $out[$u.'_avg'] = $b['bd'] > 0 ? $b['spend'] / $b['bd'] * 100 : null;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function kpiHistoryKeys(): array
    {
        $kinds = ['bgt', 'util', 'bid', 'cvr', 'acos', 'clicks', 'sold', 'sales', 'spend'];
        $keys = [];
        foreach (['y', 'l30', 'p'] as $w) {
            foreach ($kinds as $k) {
                $keys[] = $w.'_'.$k;
            }
        }
        $keys[] = 'l30_bgt_total';
        $keys[] = 'p_bgt_total';
        foreach (['u1', 'u7'] as $u) {
            foreach (['green', 'red', 'pink', 'avg'] as $b) {
                $keys[] = $u.'_'.$b;
            }
        }

        return $keys;
    }

    /**
     * One daily row per campaign (+ ad_type) and day for the Yesterday-badge universe.
     *
     * @return list<array{key: string, day: string, spend: float, sales: float, clicks: float, sold: float, budget: float}>
     */
    private static function dailyCampaignRowsForFilteredCampaigns(Request $request, string $table, string $from, string $to): array
    {
        if (! SchemaMemo::hasTable($table)) {
            return [];
        }
        $dbColumns = self::orderedColumnsForTable($table);
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('id', $dbColumns, true)) {
            return [];
        }
        $costExpr = self::costPreferCoalesceExprForTableAlias('t', $dbColumns);
        $universe = self::badgeUniverseQuery($request, $table, $dbColumns);
        if ($costExpr === null || $universe === null) {
            return [];
        }
        $hasAd = in_array('ad_type', $dbColumns, true);
        $pairs = $universe->clone()->reorder()->select($table.'.campaign_id');
        if ($hasAd) {
            $pairs->addSelect($table.'.ad_type');
        }
        $pairs->whereNotNull($table.'.campaign_id')->distinct();

        $latest = DB::table($table.' as src')
            ->whereRaw('CHAR_LENGTH(TRIM(src.report_date_range)) = 10')
            ->whereBetween('src.report_date_range', [$from, $to]);
        $latest->joinSub($pairs, 'flt', function ($join) use ($hasAd) {
            $join->on('flt.campaign_id', '=', 'src.campaign_id');
            if ($hasAd) {
                $join->whereRaw('flt.ad_type <=> src.ad_type');
            }
        });
        $latest->selectRaw('MAX(src.id) AS max_id');
        if ($hasAd) {
            $latest->groupBy('src.report_date_range', 'src.campaign_id', 'src.ad_type');
        } else {
            $latest->groupBy('src.report_date_range', 'src.campaign_id');
        }

        $salesCol = self::l30SummarySalesDbColumn($dbColumns);
        $purchCol = self::l30SummaryPurchasesDbColumn($dbColumns);
        $q = DB::query()
            ->fromSub($latest, 'm')
            ->join($table.' as t', 't.id', '=', 'm.max_id')
            ->selectRaw('t.report_date_range AS day, t.campaign_id AS cid')
            ->selectRaw($hasAd ? 't.ad_type AS ad' : '\'\' AS ad')
            ->selectRaw('COALESCE('.$costExpr.', 0) AS sp')
            ->selectRaw($salesCol !== null ? 'COALESCE(t.`'.$salesCol.'`, 0) AS sa' : '0 AS sa')
            ->selectRaw(in_array('clicks', $dbColumns, true) ? 'COALESCE(t.`clicks`, 0) AS cl' : '0 AS cl')
            ->selectRaw($purchCol !== null ? 'COALESCE(t.`'.$purchCol.'`, 0) AS so' : '0 AS so')
            ->selectRaw(in_array('campaignBudgetAmount', $dbColumns, true) ? 'COALESCE(t.`campaignBudgetAmount`, 0) AS bd' : '0 AS bd');

        $out = [];
        foreach ($q->get() as $r) {
            $day = trim((string) ($r->day ?? ''));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            $out[] = [
                'key' => (string) $r->cid.'|'.(string) ($r->ad ?? ''),
                'day' => $day,
                'spend' => (float) $r->sp,
                'sales' => (float) $r->sa,
                'clicks' => (float) $r->cl,
                'sold' => (float) $r->so,
                'budget' => (float) $r->bd,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function yesterdayBadgeHistoryTables(mixed $source): array
    {
        return match (trim((string) $source)) {
            'sb_reports' => ['amazon_sb_campaign_reports'],
            'sp_reports' => ['amazon_sp_campaign_reports'],
            '', 'all_reports' => ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'],
            default => [],
        };
    }

    /**
     * Same filters as the Yesterday badges, then one latest daily row per campaign (+ ad type).
     *
     * @return array<string, array{spend: float, sales: float}>
     */
    private static function dailySpendSalesForFilteredCampaigns(Request $request, string $table, string $from, string $to): array
    {
        if (! SchemaMemo::hasTable($table)) {
            return [];
        }
        $dbColumns = self::orderedColumnsForTable($table);
        if (! in_array('campaign_id', $dbColumns, true)
            || ! in_array('report_date_range', $dbColumns, true)
            || ! in_array('id', $dbColumns, true)) {
            return [];
        }
        $costExpr = self::costPreferCoalesceExprForTableAlias('t', $dbColumns);
        if ($costExpr === null) {
            return [];
        }
        $universe = self::badgeUniverseQuery($request, $table, $dbColumns);
        if ($universe === null) {
            return [];
        }
        $hasAd = in_array('ad_type', $dbColumns, true);
        $pairs = $universe->clone()->reorder()->select($table.'.campaign_id');
        if ($hasAd) {
            $pairs->addSelect($table.'.ad_type');
        }
        $pairs->whereNotNull($table.'.campaign_id')->distinct();

        $latest = DB::table($table.' as src')
            ->whereRaw('CHAR_LENGTH(TRIM(src.report_date_range)) = 10')
            ->whereBetween('src.report_date_range', [$from, $to]);
        $latest->joinSub($pairs, 'flt', function ($join) use ($hasAd) {
            $join->on('flt.campaign_id', '=', 'src.campaign_id');
            if ($hasAd) {
                $join->whereRaw('flt.ad_type <=> src.ad_type');
            }
        });
        $latest->selectRaw('MAX(src.id) AS max_id');
        if ($hasAd) {
            $latest->groupBy('src.report_date_range', 'src.campaign_id', 'src.ad_type');
        } else {
            $latest->groupBy('src.report_date_range', 'src.campaign_id');
        }

        $salesCol = self::l30SummarySalesDbColumn($dbColumns);
        $salesExpr = $salesCol !== null ? 'COALESCE(t.`'.$salesCol.'`, 0)' : '0';
        $rows = DB::query()
            ->fromSub($latest, 'm')
            ->join($table.' as t', 't.id', '=', 'm.max_id')
            ->selectRaw('t.report_date_range AS day')
            ->selectRaw('COALESCE(SUM('.$costExpr.'), 0) AS cost_sum')
            ->selectRaw('COALESCE(SUM('.$salesExpr.'), 0) AS sales_sum')
            ->groupBy('t.report_date_range')
            ->orderBy('t.report_date_range')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $day = trim((string) ($row->day ?? ''));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            $out[$day] = [
                'spend' => (float) ($row->cost_sum ?? 0),
                'sales' => (float) ($row->sales_sum ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Campaign set behind the Yesterday badges (L30 summary universe in calendar mode).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function badgeUniverseQuery(Request $request, string $table, array $dbColumns): ?Builder
    {
        $search = $request->input('search');
        if (is_array($search)) {
            $search = (string) ($search['value'] ?? '');
        }
        $search = trim((string) $search);

        if (self::shouldUseL30SummaryUniverseForBadges($table, $request)) {
            return self::l30SummaryUniverseQuery($table, $request, $search, $dbColumns);
        }

        $query = DB::table($table);
        $usedCalendarSearchFallback = self::applyCalendarSearchWithL30Fallback($query, $table, $dbColumns, $request, $search);
        if (! $usedCalendarSearchFallback) {
            self::applyDateFilters($query, $table, $request);
            if ($search !== '' && in_array('campaignName', $dbColumns, true)) {
                $query->where('campaignName', 'LIKE', '%'.addcslashes($search, '%_\\').'%');
            }
        }
        self::applyUtilizationPercentRangeFilters($query, $table, $request, true);
        self::applyCampaignStatusFilter($query, $table, $request);
        self::applyAcosColorFilter($query, $table, $request);
        self::applyAdsCvrColorFilter($query, $table, $request);
        self::applyAcosGpftFlagFilter($query, $table, $request);
        self::applyInventoryFilter($query, $table, $request, $dbColumns);
        self::applyTargetsFilter($query, $table, $request);
        if (self::tableSupportsLiveSyncStatus($table, $dbColumns)) {
            self::applyLiveSyncStatusFilters($query, $table, $request);
        }

        return $query;
    }

    /**
     * Calendar-day ads spend or sales points for the rolling history chart.
     */
    private function dailyAdsMetricHistory(Request $request, string $metric, string $pointKey): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }
        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        if ($table === null || ! SchemaMemo::hasTable($table)) {
            return response()->json(['ok' => false, 'message' => 'No daily report table for this row.', 'points' => []], 404);
        }
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }
        $dbColumns = SchemaMemo::getColumnListing($table);
        $select = self::lRangeMetricSelectColumns($dbColumns, false);
        $adType = trim((string) $request->query('ad_type', ''));
        $q = DB::table($table)
            ->select($select)
            ->where('campaign_id', $cid)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->orderByDesc('report_date_range')
            ->orderByDesc('id');
        if ($adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $q->where('ad_type', $adType);
        }
        $rows = $q->limit(2000)->get();
        if ($rows->isEmpty() && $adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $rows = DB::table($table)
                ->select($select)
                ->where('campaign_id', $cid)
                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
                ->orderByDesc('report_date_range')
                ->orderByDesc('id')
                ->limit(2000)
                ->get();
        }
        $byDate = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $day = trim((string) ($r['report_date_range'] ?? ''));
            if ($day === '' || array_key_exists($day, $byDate)) {
                continue;
            }
            $val = $metric === 'sales'
                ? AmazonAdsLRangeMetrics::salesFromRow($r, $dbColumns, 'daily')
                : AmazonAdsLRangeMetrics::spendFromRow($r, $dbColumns);
            $byDate[$day] = $val;
        }
        if ($byDate === []) {
            return response()->json(['ok' => true, 'points' => []]);
        }
        $known = array_keys($byDate);
        sort($known);
        $end = $known[count($known) - 1];
        $start = $known[0];
        if ($days !== 0) {
            $from = Carbon::parse($end)->subDays($days - 1)->toDateString();
            if ($from > $start) {
                $start = $from;
            }
        }
        $points = [];
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);
        while ($cursor->lte($last)) {
            $day = $cursor->toDateString();
            $raw = $byDate[$day] ?? null;
            $points[] = [
                'date' => $day,
                $pointKey => $raw !== null && is_finite($raw) ? round((float) $raw, 2) : 0.0,
            ];
            $cursor->addDay();
        }

        return response()->json(['ok' => true, 'points' => $points]);
    }

    /**
     * Daily CVR history for the LT CVR dot. Same daily API rows as the lifetime column.
     */
    public function ltCvrHistory(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }

        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        if ($table === null || ! SchemaMemo::hasTable($table)) {
            return response()->json(['ok' => false, 'message' => 'No daily CVR table for this row.', 'points' => []], 404);
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
        $purchCol = in_array('purchases30d', $dbColumns, true)
            ? 'purchases30d'
            : (in_array('purchases', $dbColumns, true) ? 'purchases' : null);
        if ($purchCol === null || ! in_array('clicks', $dbColumns, true)) {
            return response()->json(['ok' => true, 'points' => []]);
        }

        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }

        $q = DB::table($table)
            ->select(['report_date_range', $purchCol, 'clicks'])
            ->where('campaign_id', $cid)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->orderBy('report_date_range', 'desc')
            ->limit($days === 0 ? 2000 : $days);
        $adType = trim((string) $request->query('ad_type', ''));
        if ($adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $q->where('ad_type', $adType);
        }

        $points = [];
        foreach ($q->get()->reverse()->values() as $row) {
            $r = (array) $row;
            $day = trim((string) ($r['report_date_range'] ?? ''));
            $clicks = (float) ($r['clicks'] ?? 0);
            if ($day === '' || $clicks <= 0) {
                continue;
            }
            $purch = (float) ($r[$purchCol] ?? 0);
            $n = ($purch / $clicks) * 100;
            if (! is_finite($n)) {
                continue;
            }
            $points[] = ['date' => $day, 'cvr' => round($n, 2)];
        }

        return response()->json(['ok' => true, 'points' => $points]);
    }

    /**
     * Daily ACOS history for the LT ACOS dot. Same daily API rows as the lifetime column.
     */
    public function ltAcosHistory(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }

        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        if ($table === null || ! SchemaMemo::hasTable($table)) {
            return response()->json(['ok' => false, 'message' => 'No daily ACOS table for this row.', 'points' => []], 404);
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
        $costCol = in_array('cost', $dbColumns, true) ? 'cost' : (in_array('spend', $dbColumns, true) ? 'spend' : null);
        $salesCol = in_array('sales30d', $dbColumns, true) ? 'sales30d' : (in_array('sales', $dbColumns, true) ? 'sales' : null);
        if ($costCol === null || $salesCol === null) {
            return response()->json(['ok' => true, 'points' => []]);
        }

        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }

        $q = DB::table($table)
            ->select(['report_date_range', $costCol, $salesCol])
            ->where('campaign_id', $cid)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->orderBy('report_date_range', 'desc')
            ->limit($days === 0 ? 2000 : $days);
        $adType = trim((string) $request->query('ad_type', ''));
        if ($adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $q->where('ad_type', $adType);
        }

        $points = [];
        foreach ($q->get()->reverse()->values() as $row) {
            $r = (array) $row;
            $day = trim((string) ($r['report_date_range'] ?? ''));
            if ($day === '') {
                continue;
            }
            $acos = self::lifetimeAcosPercent((float) ($r[$costCol] ?? 0), (float) ($r[$salesCol] ?? 0));
            if ($acos === null) {
                continue;
            }
            $points[] = ['date' => $day, 'acos' => $acos];
        }

        return response()->json(['ok' => true, 'points' => $points]);
    }

    /**
     * Daily SBID history for the SBID History dot. Stored `sbid` on each calendar report row.
     */
    public function sbidHistory(Request $request): JsonResponse
    {
        return $this->dailyStoredMoneyHistory($request, 'sbid', 'SBID');
    }

    /**
     * Daily SBGT history for the SBGT History dot. Stored `sbgt` on each calendar report row.
     */
    public function sbgtHistory(Request $request): JsonResponse
    {
        return $this->dailyStoredMoneyHistory($request, 'sbgt', 'SBGT');
    }

    /**
     * Daily Lbid (live Amazon bid) history for the Lbid dot.
     * Only the days saved in amazon_ads_lbid_daily (one value per campaign per California day,
     * starting the day saving began). Old report rows are not used.
     */
    public function lbidHistory(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }

        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        $channel = $table !== null ? AmazonAdsLbidDaily::channelForTable($table) : null;
        if ($channel === null) {
            return response()->json(['ok' => false, 'message' => 'Daily Lbid is saved for SP and SB campaigns only.', 'points' => []], 404);
        }

        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }

        return response()->json([
            'ok' => true,
            'points' => self::filledMoneyHistoryPoints(AmazonAdsLbidDaily::historyByDate($channel, $cid), $days, 'lbid'),
        ]);
    }

    private function dailyStoredMoneyHistory(Request $request, string $column, string $label): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', trim((string) $request->query('campaign_id', ''))) ?: '';
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'Provide campaign_id.', 'points' => []], 422);
        }

        $table = self::cpcHistoryTable($request->query('source'), $request->query('ad_type'));
        if ($table === null || ! SchemaMemo::hasTable($table) || ! SchemaMemo::hasColumn($table, $column)) {
            return response()->json(['ok' => false, 'message' => 'No daily '.$label.' table for this row.', 'points' => []], 404);
        }

        $days = (int) $request->query('days', 30);
        if (! in_array($days, [0, 7, 30, 31, 32, 35, 60, 90], true)) {
            $days = 30;
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
        $q = DB::table($table)
            ->where('campaign_id', $cid)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
        $adType = trim((string) $request->query('ad_type', ''));
        if ($adType !== '' && in_array('ad_type', $dbColumns, true)) {
            $q->where('ad_type', $adType);
        }

        $select = ['report_date_range', $column];
        $q->orderByDesc('report_date_range');
        if (in_array('id', $dbColumns, true)) {
            $select[] = 'id';
            $q->orderByDesc('id');
        }
        $rows = $q->limit(2000)->get($select);

        $byDate = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $day = trim((string) ($r['report_date_range'] ?? ''));
            if ($day === '') {
                continue;
            }
            $parsed = self::moneyHistoryValue($r[$column] ?? null);
            if (! array_key_exists($day, $byDate)) {
                $byDate[$day] = $parsed;
            } elseif ($byDate[$day] === null && $parsed !== null) {
                $byDate[$day] = $parsed;
            }
        }

        foreach (self::moneyHistoryPushesByDate($table, $column, $cid) as $day => $pushed) {
            if (! array_key_exists($day, $byDate) || $byDate[$day] === null) {
                $byDate[$day] = $pushed;
            }
        }

        return response()->json([
            'ok' => true,
            'points' => self::filledMoneyHistoryPoints($byDate, $days, $column),
        ]);
    }

    /**
     * One point per day after the first real saved or pushed amount.
     * Later blank days keep that amount until the next real change.
     * Earlier blank days are left off, so today's value is not copied backward.
     *
     * @param  array<string, float|null>  $byDate
     * @return list<array<string, float|string>>
     */
    private static function filledMoneyHistoryPoints(array $byDate, int $days, string $column): array
    {
        $knownDates = array_keys($byDate);
        sort($knownDates);
        if ($knownDates === []) {
            return [];
        }

        $end = $knownDates[count($knownDates) - 1];
        $start = $knownDates[0];
        if ($days !== 0) {
            $start = Carbon::parse($end)->subDays($days - 1)->toDateString();
            if ($start < $knownDates[0]) {
                $start = $knownDates[0];
            }
        }

        $carry = null;
        foreach ($knownDates as $day) {
            if ($day >= $start) {
                break;
            }
            if ($byDate[$day] !== null) {
                $carry = $byDate[$day];
            }
        }

        $points = [];
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);
        while ($cursor->lte($last)) {
            $day = $cursor->toDateString();
            if (array_key_exists($day, $byDate) && $byDate[$day] !== null) {
                $carry = $byDate[$day];
            }
            if ($carry !== null) {
                $points[] = ['date' => $day, $column => $carry];
            }
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * Last successful push of this bid or budget on each day.
     *
     * @return array<string, float>
     */
    private static function moneyHistoryPushesByDate(string $table, string $column, string $campaignId): array
    {
        $types = self::moneyHistoryPushTypes($table, $column);
        if ($types === [] || ! SchemaMemo::hasTable('amazon_ads_push_logs')) {
            return [];
        }

        $rows = DB::table('amazon_ads_push_logs')
            ->where('campaign_id', $campaignId)
            ->whereIn('push_type', $types)
            ->where('status', 'success')
            ->whereNotNull('value')
            ->orderBy('created_at')
            ->get(['created_at', 'value']);

        $tz = (string) config('app.timezone');
        $byDate = [];
        foreach ($rows as $row) {
            $parsed = self::moneyHistoryValue($row->value ?? null);
            $at = $row->created_at ?? null;
            if ($parsed === null || $at === null || $at === '') {
                continue;
            }
            $day = Carbon::parse((string) $at)->timezone($tz)->toDateString();
            $byDate[$day] = $parsed;
        }

        return $byDate;
    }

    /**
     * @return list<string>
     */
    private static function moneyHistoryPushTypes(string $table, string $column): array
    {
        $brand = $table === 'amazon_sb_campaign_reports';
        if ($column === 'sbid') {
            return [$brand ? 'sb_sbid' : 'sp_sbid'];
        }
        if ($column === 'sbgt') {
            return [$brand ? 'sb_sbgt' : 'sp_sbgt'];
        }

        return [];
    }

    private static function moneyHistoryValue(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }
        $n = round((float) $raw, 2);
        if (! is_finite($n) || $n < 0) {
            return null;
        }

        return $n;
    }

    private static function cpcHistoryTable(mixed $source, mixed $adType): ?string
    {
        $source = is_string($source) ? $source : '';
        if (isset(self::RAW_TABLE_SOURCES[$source])) {
            $table = self::RAW_TABLE_SOURCES[$source];
            if (in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports', 'amazon_sd_campaign_reports'], true)) {
                return $table;
            }
        }

        $ad = strtoupper(trim(is_string($adType) ? $adType : ''));
        if (str_contains($ad, 'BRAND')) {
            return 'amazon_sb_campaign_reports';
        }
        if (str_contains($ad, 'DISPLAY')) {
            return 'amazon_sd_campaign_reports';
        }
        if ($ad !== '' || $source === 'all_reports' || $source === 'sp_reports') {
            return 'amazon_sp_campaign_reports';
        }

        return null;
    }

    /**
     * History-column dots: green when today's amount is above the previous saved day,
     * red when below, gray when unchanged or there is no prior day.
     * Lbid is green (not gray) when today's California value was saved and it still matches the previous day.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function attachMoneyHistoryTrends(array $rows, string $table): array
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports', 'amazon_sd_campaign_reports'], true)) {
            return $rows;
        }
        $ids = [];
        foreach ($rows as $row) {
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($cid !== '') {
                $ids[$cid] = true;
            }
        }
        $prevSbid = self::previousDailyMoneyMap($table, array_keys($ids), 'sbid');
        $prevSbgt = self::previousDailyMoneyMap($table, array_keys($ids), 'sbgt');
        // Lbid trend uses only the days saved in amazon_ads_lbid_daily.
        $lbidChannel = AmazonAdsLbidDaily::channelForTable($table);
        $lbidIds = array_map('strval', array_keys($ids));
        $prevLbid = $lbidChannel !== null
            ? AmazonAdsLbidDaily::previousByCampaign($lbidChannel, $lbidIds)
            : [];
        $todayLbid = $lbidChannel !== null
            ? AmazonAdsLbidDaily::todayByCampaign($lbidChannel, $lbidIds)
            : [];
        foreach ($rows as $i => $row) {
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            $sbidPrev = $prevSbid[$cid] ?? null;
            $sbgtPrev = $prevSbgt[$cid] ?? null;
            $lbidPrev = $prevLbid[$cid] ?? null;
            $rows[$i]['sbid_prev'] = $sbidPrev;
            $rows[$i]['sbgt_prev'] = $sbgtPrev;
            $rows[$i]['lbid_prev'] = $lbidPrev;
            $lbidTrend = self::moneyHistoryTrend($row['last_sbid'] ?? null, $lbidPrev);
            // Live API (or today's snapshot) already stored this California day, and the bid did not move.
            if ($lbidTrend === 'flat' && array_key_exists($cid, $todayLbid)) {
                $lbidTrend = 'same';
            }
            $rows[$i]['lbid_trend'] = $lbidTrend;
            $rows[$i]['sbid_trend'] = self::moneyHistoryTrend($row['sbid'] ?? null, $sbidPrev);
            $rows[$i]['sbgt_trend'] = self::moneyHistoryTrend($row['sbgt'] ?? null, $sbgtPrev);
        }

        return $rows;
    }

    /**
     * All-tab page rows come from SP and SB. Color each dot from its own table.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function attachMoneyHistoryTrendsForMixedPage(array $rows): array
    {
        $groups = [];
        foreach ($rows as $i => $row) {
            $ad = strtoupper(trim((string) ($row['ad_type'] ?? '')));
            $table = str_contains($ad, 'BRAND')
                ? 'amazon_sb_campaign_reports'
                : (str_contains($ad, 'DISPLAY') ? 'amazon_sd_campaign_reports' : 'amazon_sp_campaign_reports');
            $groups[$table][$i] = $row;
        }
        foreach ($groups as $table => $subset) {
            $updated = self::attachMoneyHistoryTrends(array_values($subset), $table);
            $keys = array_keys($subset);
            foreach ($updated as $n => $row) {
                $rows[$keys[$n]] = $row;
            }
        }

        return $rows;
    }

    /**
     * Most recent daily amount strictly before today.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, float>
     */
    private static function previousDailyMoneyMap(string $table, array $campaignIds, string $column): array
    {
        if ($campaignIds === [] || ! SchemaMemo::hasTable($table) || ! SchemaMemo::hasColumn($table, $column)) {
            return [];
        }
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $out = [];
        foreach (array_chunk($campaignIds, 200) as $chunk) {
            $maxes = DB::table($table)
                ->select('campaign_id', DB::raw('MAX(report_date_range) as md'))
                ->whereIn('campaign_id', $chunk)
                ->where('report_date_range', '<', $today)
                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->groupBy('campaign_id')
                ->get();
            if ($maxes->isEmpty()) {
                continue;
            }
            $rows = DB::table($table)
                ->select('campaign_id', 'report_date_range', $column)
                ->where(function ($q) use ($maxes) {
                    foreach ($maxes as $max) {
                        $q->orWhere(function ($w) use ($max) {
                            $w->where('campaign_id', $max->campaign_id)
                                ->where('report_date_range', $max->md);
                        });
                    }
                })
                ->get();
            foreach ($rows as $row) {
                $n = self::moneyHistoryValue($row->{$column} ?? null);
                if ($n !== null) {
                    $out[(string) $row->campaign_id] = $n;
                }
            }
        }

        return $out;
    }

    private static function moneyHistoryTrend(mixed $current, ?float $previous): string
    {
        $now = self::moneyHistoryValue($current);
        if ($now === null || $previous === null) {
            return 'na';
        }
        if (abs($now - $previous) < 0.005) {
            return 'flat';
        }

        return $now > $previous ? 'up' : 'down';
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
        self::forgetRawDataCache();
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
     * Current PARENT Dil% pause-or-activate rule (Amazon Ads Pause Rule modal).
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
        self::forgetRawDataCache();
        try {
            AmazonAdsPauseRule::persistPr([
                'enabled' => $request->boolean('enabled', true),
                'dil_above' => $request->input('dil_above', 100),
                'dil_enabled' => $request->boolean('dil_enabled', true),
                'price_enabled' => false,
                'reviews_enabled' => false,
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
        if (! empty($pr['enabled']) && ! empty($pr['dil_enabled'])) {
            $th = rtrim(rtrim(number_format((float) ($pr['dil_above'] ?? 100), 2, '.', ''), '0'), '.');
            $parts[] = 'Dil% ≥ '.$th.'%';
        }
        $payload = [
            'message' => (! empty($pr['enabled']) && $parts !== []
                ? 'Pause Rule saved. PARENT campaigns matching '.implode(' or ', $parts).' will be paused, and child SKU campaigns in those families will be paused too. Old ads stay off; only recently Dil-paused PARENT campaigns activate again.'
                : 'Pause Rule saved. Dil% will not auto-pause campaigns.'),
            'rule' => $freshRule,
            'status' => 200,
            'timestamp' => time(),
        ];

        if ($request->boolean('apply')) {
            try {
                $payload['apply'] = app(AmazonAdsPauseRuleApplicator::class)->applyAll(false);
                $payload['message'] = 'Pause Rule saved and applied to Amazon.'
                    .($parts !== [] ? ' Paused PARENT and child SKU campaigns where '.implode(' or ', $parts).'. Active Again SKUs are paused again. Old ads stay off; only recently Dil-paused PARENT campaigns can activate again.' : '');
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
        self::applyAcosGpftFlagFilter($inner, $table, $request);

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

        if (! SchemaMemo::hasTable($table)) {
            return response()->json($empty + ['reason' => 'missing_table']);
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
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
        if (! SchemaMemo::hasTable($table)) {
            return response()->json(['ok' => false, 'days' => [], 'reason' => 'missing_table']);
        }

        $dbColumns = SchemaMemo::getColumnListing($table);
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
     * Recalculate the grid SBID for every campaign and save it, without a page load.
     *
     * The grid only saves SBID for the rows it shows, so with nobody on the page the stored sbid went
     * stale and the scheduled sync never saw a Lbid/SBID gap. This runs the same calculation the grid
     * runs (same default window: latest calendar day plus L30 for campaigns that day omitted).
     *
     * @return array{rows: int, written: int}
     */
    public function refreshStoredSbids(string $table): array
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return ['rows' => 0, 'written' => 0];
        }
        @set_time_limit(0);
        $request = Request::create('/', 'POST', [
            'draw' => 1,
            'start' => 0,
            'length' => 20000,
            'sbid_universe' => 1,
        ]);
        $request->attributes->set(self::PERSIST_SBID_UNIVERSE_ATTR, true);
        $payload = $this->rawDataSingleSourcePayload($request, $table, 0, 20000);

        return [
            'rows' => (int) ($payload['sbid_rows_seen'] ?? 0),
            'written' => (int) ($payload['sbid_rows_written'] ?? 0),
        ];
    }

    /**
     * Server-side JSON for DataTables (GET). Source must match RAW_TABLE_SOURCES key.
     */
    public function rawData(Request $request, string $source)
    {
        $universe = $request->boolean('bgt_universe') || $request->boolean('sbid_universe');
        // Opening the page, paging, sorting and the background refresh all ask for the same slice of data many times a
        // minute. The answer is built once (by the first visitor, the scheduled warm-up or the Refresh button) and
        // reused for a short while. fresh=1 rebuilds it.
        if (! in_array($source, ['all_reports', 'sp_reports', 'sb_reports', 'sd_reports'], true)) {
            return $this->rawDataUncached($request, $source);
        }
        $key = self::rawDataCacheKey($request, $source);
        $ttl = 300;
        if (! $request->boolean('fresh')) {
            try {
                $hit = Cache::get($key);
            } catch (\Throwable) {
                $hit = null;
            }
            if (is_array($hit)) {
                if (array_key_exists('draw', $hit)) {
                    $hit['draw'] = (int) $request->input('draw', $hit['draw']);
                }

                return response()->json($hit);
            }
        }
        $response = $this->rawDataUncached($request, $source);
        if ($response instanceof JsonResponse && $response->getStatusCode() === 200) {
            $data = $response->getData(true);
            if (is_array($data)) {
                try {
                    Cache::put($key, $data, $ttl);
                } catch (\Throwable) {
                }
            }
        }

        return $response;
    }

    private const RAW_CACHE_VERSION_KEY = 'amz_ads_raw_ver';

    /**
     * Rebuilds the cached answer for the page as it opens (All, active campaigns, first page, sorted by yesterday's
     * spend), so a visitor gets it from cache. Run by the scheduler; the page itself never has to wait for it.
     *
     * @return array{ok: bool, rows: int, ms: int}
     */
    public function warmDefaultGrid(): array
    {
        $started = microtime(true);
        $day = self::latestAvailableReportDayYmd('amazon_sp_campaign_reports') ?? '';
        $cols = self::displayColumnsForTable('amazon_sp_campaign_reports');
        $idx = array_search('ySpend', $cols, true);
        $params = [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'search' => ['value' => '', 'regex' => 'false'],
            'date_from' => $day,
            'date_to' => $day,
            'summary_report_range' => '',
            'filter_campaign_status' => 'ENABLED',
            'want_acos_gpft_count' => '1',
            'fresh' => 1,
        ];
        if ($idx !== false) {
            $params['order'] = [['column' => (string) $idx, 'dir' => 'desc']];
        }
        $request = Request::create('/amazon-ads/raw-data/all_reports', 'POST', $params);
        $response = $this->rawData($request, 'all_reports');
        $data = $response instanceof JsonResponse ? $response->getData(true) : [];

        return [
            'ok' => $response->getStatusCode() === 200,
            'rows' => is_array($data['data'] ?? null) ? count($data['data']) : 0,
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /** Same request (ignoring draw, token and fresh, and empty filters) -> same key. */
    public static function rawDataCacheKey(Request $request, string $source): string
    {
        $params = $request->except(['draw', '_token', 'fresh', '_']);
        $clean = static function ($v) use (&$clean) {
            if (is_array($v)) {
                $out = [];
                foreach ($v as $k => $x) {
                    $x = $clean($x);
                    if ($x !== null && $x !== '' && $x !== []) {
                        $out[$k] = $x;
                    }
                }
                ksort($out);

                return $out;
            }

            return is_scalar($v) ? (string) $v : null;
        };
        $params = $clean($params);
        $ver = 'v0';
        try {
            $ver = (string) Cache::get(self::RAW_CACHE_VERSION_KEY, 'v0');
        } catch (\Throwable) {
        }

        return 'amz_ads_raw:'.$ver.':'.$source.':'.md5(json_encode($params));
    }

    /** Drop every cached grid answer (after a push or anything else that changes what the grid shows). */
    public static function forgetRawDataCache(): void
    {
        try {
            Cache::forever(self::RAW_CACHE_VERSION_KEY, (string) microtime(true));
        } catch (\Throwable) {
        }
    }

    private function rawDataUncached(Request $request, string $source)
    {
        if ($request->boolean('bgt_universe') || $request->boolean('sbid_universe')) {
            @set_time_limit(app()->runningInConsole() ? 0 : 180);
        }

        if ($source === 'all_reports') {
            return response()->json($this->rawDataAllReportsPayload($request));
        }

        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            abort(404);
        }

        $table = self::RAW_TABLE_SOURCES[$source];

        if ($request->boolean('bgt_universe') || $request->boolean('sbid_universe')) {
            $payload = $this->rawDataSingleSourcePayload($request, $table, 0, 20000);

            return response()->json($this->bgtUniverseJson(
                $this->tagBgtUniverseRows($payload['data'] ?? [], $table),
                isset($payload['distinctCampaignCount']) ? (int) $payload['distinctCampaignCount'] : null
            ));
        }

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
        if (! SchemaMemo::hasTable($table)) {
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
        self::applyAcosGpftFlagFilter($query, $table, $request);
        self::applyInventoryFilter($query, $table, $request, $dbColumns);
        self::applyTargetsFilter($query, $table, $request);

        $forSbidUniverse = $request->boolean('sbid_universe');
        $forBgtUniverse = $request->boolean('bgt_universe') || $forSbidUniverse;
        $bgtSyncCounts = ['green' => 0, 'yellow' => 0, 'red' => 0];
        $bidSyncCounts = ['green' => 0, 'yellow' => 0, 'red' => 0];
        if (self::tableSupportsLiveSyncStatus($table, $dbColumns)) {
            if (! $forBgtUniverse) {
                $bgtSyncCounts = self::liveSyncStatusCounts($query, $table, 'bgt', $request);
                $bidSyncCounts = self::liveSyncStatusCounts($query, $table, 'bid', $request);
            }
            self::applyLiveSyncStatusFilters($query, $table, $request);
        }

        $recordsFiltered = 0;
        $recordsTotal = 0;
        if (! $forBgtUniverse) {
            $recordsFiltered = (int) $query->clone()->count();
            $recordsTotal = $recordsFiltered;
        }

        $queryForAggregates = $query->clone();
        // Count for the ACOS% green-flag badge (ACOS% < GPFT%), taken before paging is applied to $query.
        $acosGpftFlagCount = null;
        if (! $forBgtUniverse && $request->boolean('want_acos_gpft_count')
            && ($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports')) {
            try {
                $acosGpftFlagCount = count(self::acosBelowGpftCampaignIds($query, $table));
            } catch (\Throwable $e) {
                Log::warning('ACOS < GPFT flag count failed: '.$e->getMessage());
            }
        }
        // Calendar grid includes that day's rows plus L30 rows Amazon omitted (no impressions).
        // Spend / Clicks / Sold / Sales / ACOS badges still sum the L30 summary universe.
        if (self::shouldUseL30SummaryUniverseForBadges($table, $request)) {
            $queryForAggregates = self::l30SummaryUniverseQuery($table, $request, $search, $dbColumns);
        }

        $distinctCampaignCount = null;
        if (! $forBgtUniverse && in_array('campaign_id', $dbColumns, true)) {
            $distinctCampaignCount = (int) $query->clone()
                ->reorder()
                ->selectRaw('COUNT(DISTINCT `'.$table.'`.campaign_id) AS c')
                ->value('c');
        }

        $l30AggDistinct = null;
        if (! $forBgtUniverse
            && ($table === 'amazon_sp_campaign_reports' || $table === 'amazon_sb_campaign_reports')
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

        $yesterdaySpend = null;
        $yesterdaySales = null;
        $yesterdayAcosPercent = null;
        $projectedSpend = null;
        $projectedSales = null;
        $projectedAcosPercent = null;
        $kpiBlocks = null;
        if (! $forBgtUniverse && $l30AggDistinct !== null) {
            $l1Agg = self::sumLatestSummaryMetricsForFilteredCampaigns($queryForAggregates, $table, $dbColumns, 'L1');
            $l7Agg = self::sumLatestSummaryMetricsForFilteredCampaigns($queryForAggregates, $table, $dbColumns, 'L7');
            $yesterdaySpend = round((float) $l1Agg['cost_sum'], 2);
            $yesterdaySales = round((float) $l1Agg['sales_sum'], 2);
            $yesterdayAcosPercent = self::overallAcosPercentFromAggregatedSums((float) $l1Agg['cost_sum'], (float) $l1Agg['sales_sum']);
            $projectedSpend = self::projectMonthFromLast7((float) $l7Agg['cost_sum']);
            $projectedSales = self::projectMonthFromLast7((float) $l7Agg['sales_sum']);
            $projectedAcosPercent = self::overallAcosPercentFromAggregatedSums($projectedSpend, $projectedSales);

            // Google-style KPI blocks (Yesterday / L30 / Projected / Utilized Y / Utilized L7): raw sums only, the
            // page derives BGT, UTIL, AVG BID, CVR from them. Existing spend / sales / ACOS above are unchanged.
            $l30Budget = self::sumLatestSummaryMetricsForFilteredCampaigns($queryForAggregates, $table, $dbColumns, 'L30');
            $enabledCount = null;
            if (in_array('campaignStatus', $dbColumns, true)) {
                $enabledCount = (int) $queryForAggregates->clone()
                    ->reorder()
                    ->where($table.'.campaignStatus', 'ENABLED')
                    ->selectRaw('COUNT(DISTINCT `'.$table.'`.campaign_id) AS c')
                    ->value('c');
            }
            $kpiBlocks = [
                'y' => [
                    'spend' => (float) $l1Agg['cost_sum'],
                    'sales' => (float) $l1Agg['sales_sum'],
                    'clicks' => (float) $l1Agg['clicks_sum'],
                    'sold' => (float) $l1Agg['purchases_sum'],
                    'budget' => (float) $l1Agg['budget_sum'],
                ],
                'l7' => [
                    'spend' => (float) $l7Agg['cost_sum'],
                    'sales' => (float) $l7Agg['sales_sum'],
                    'clicks' => (float) $l7Agg['clicks_sum'],
                    'sold' => (float) $l7Agg['purchases_sum'],
                    'budget' => (float) $l7Agg['budget_sum'],
                ],
                'l30' => [
                    'spend' => (float) $l30AggDistinct['cost_sum'],
                    'sales' => (float) $l30AggDistinct['sales_sum'],
                    'clicks' => (float) $l30AggDistinct['clicks_sum'],
                    'sold' => (float) $l30AggDistinct['purchases_sum'],
                    'budget' => (float) $l30Budget['budget_sum'],
                ],
                'u1' => self::utilBucketsLatestSummaryForFilteredCampaigns($queryForAggregates, $table, $dbColumns, 'L1', 1),
                'u7' => self::utilBucketsLatestSummaryForFilteredCampaigns($queryForAggregates, $table, $dbColumns, 'L7', 7),
                'active' => ['enabled' => $enabledCount, 'total' => (int) ($distinctCampaignCount ?? 0)],
            ];
        }

        $requestedOrderCol = ($orderColumnIndex >= 0 && $orderColumnIndex < count($columns))
            ? (string) $columns[$orderColumnIndex]
            : '';
        $lightSort = self::columnUsesLightDisplaySort($requestedOrderCol);
        $usePhpSort = $lightSort || in_array($requestedOrderCol, self::PHP_SORT_DISPLAY_COLUMNS, true);
        $phpSortPaged = false;
        $sortCap = $forBgtUniverse ? 20000 : 3000;

        // Correlated ORDER BY runs a lookup per campaign before the page can return.
        // On a normal day the filtered set fits in one window, so rank it in PHP instead.
        // Chart counts do not need that order.
        if ($forBgtUniverse && in_array('id', $dbColumns, true) && in_array('campaign_id', $dbColumns, true)) {
            $latestIds = $query->clone()
                ->reorder()
                ->select(DB::raw('MAX(`'.$table.'`.`id`) as pick_id'))
                ->groupBy($table.'.campaign_id')
                ->limit(20000)
                ->pluck('pick_id');
            $idList = [];
            foreach ($latestIds as $pickId) {
                if ($pickId !== null && $pickId !== '') {
                    $idList[] = $pickId;
                }
            }
            $rows = collect();
            foreach (array_chunk($idList, 1000) as $idChunk) {
                if ($idChunk === []) {
                    continue;
                }
                $rows = $rows->concat(DB::table($table)->whereIn('id', $idChunk)->get());
            }
        } elseif ($usePhpSort && $recordsFiltered <= $sortCap && in_array('id', $dbColumns, true)) {
            $query->orderBy('id', 'desc');
        } else {
            self::applyRawDataOrder($query, $table, $dbColumns, $columns, $orderColumnIndex, $orderDir);
        }

        if ($forBgtUniverse && isset($rows)) {
            $seenCampaign = [];
            $deduped = [];
            foreach ($rows as $row) {
                $raw = (array) $row;
                $cid = trim((string) ($raw['campaign_id'] ?? ''));
                if ($cid !== '' && isset($seenCampaign[$cid])) {
                    continue;
                }
                if ($cid !== '') {
                    $seenCampaign[$cid] = true;
                }
                $deduped[] = $row;
            }
            $rows = $deduped;
        } elseif ($usePhpSort) {
            $fetchLen = (int) min($sortCap, max($recordsFiltered, $start + $length));
            $window = $query->limit(max(1, $fetchLen))->get();
            $paged = self::pageRowsMatchingDisplaySort(
                $window,
                $table,
                $dbColumns,
                $requestedOrderCol,
                $orderDir,
                $start,
                $length
            );
            if ($paged !== null) {
                $rows = $paged;
                $phpSortPaged = true;
            } else {
                $rows = $window;
            }
        } else {
            $rows = $query->offset($start)
                ->limit($length)
                ->get();
        }

        $hasLSpendCols = in_array('L7spend', $columns, true);
        $hasUtilCols = in_array('U7%', $columns, true);
        $needProjYCols = in_array('projectedSpend', $columns, true)
            || in_array('projectedSales', $columns, true)
            || in_array('ySpend', $columns, true)
            || in_array('ySales', $columns, true)
            || in_array('yAcos', $columns, true);
        $needLSpendMap = $hasLSpendCols || $hasUtilCols || $needProjYCols;
        $lSpendMap = $needLSpendMap ? self::fetchL7L2L1SpendMap($table, $dbColumns, $rows) : [];
        $needL30ForAcosSbgt = in_array('cost', $columns, true)
            || in_array('ACOS', $columns, true)
            || in_array('acosFlag', $columns, true)
            || in_array('bgtFlag', $columns, true)
            || in_array('sbgt', $columns, true)
            || in_array('bgtAcos', $columns, true);
        $needL30Slice = ($needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true)))
            || (in_array('Prchase', $columns, true) && (in_array('purchases30d', $dbColumns, true) || in_array('purchases', $dbColumns, true)))
            || (in_array('Cvr', $columns, true) && in_array('clicks', $dbColumns, true))
            || (in_array('sales30d', $columns, true) && (in_array('sales30d', $dbColumns, true) || in_array('sales', $dbColumns, true)))
            || (($needL30ForAcosSbgt) && in_array('sales30d', $dbColumns, true));
        if ($forSbidUniverse) {
            $needL30Slice = false;
        }
        $l30SliceMap = $needL30Slice ? self::fetchL30SummarySliceMap($table, $dbColumns, $rows) : [];
        // Daily L30 sum is only a fallback when the L30 summary slice has no spend.
        $l30SpendMap = [];
        $canDailyL30 = ! $forSbidUniverse && $needL30ForAcosSbgt && (in_array('cost', $dbColumns, true) || in_array('spend', $dbColumns, true));
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

        $hasCpc2 = in_array('CPC2', $columns, true) && (! $forBgtUniverse || $forSbidUniverse);
        $hasCpc3 = in_array('CPC3', $columns, true) && (! $forBgtUniverse || $forSbidUniverse);
        $hasCpcAvg = in_array('CPCAvg', $columns, true) && (! $forBgtUniverse || $forSbidUniverse);
        $hasLtCvr = ! $forBgtUniverse && in_array('ltCvr', $columns, true);
        $hasLtAcos = ! $forSbidUniverse && in_array('ltAcos', $columns, true);
        $hasLtSpendSales = ! $forSbidUniverse && (in_array('ltSpend', $columns, true) || in_array('ltSales', $columns, true) || in_array('ltSold', $columns, true) || in_array('ltCps', $columns, true) || in_array('ltTcps', $columns, true) || in_array('ltTcos', $columns, true) || in_array('ltCpc', $columns, true));
        $needRuleStatus = in_array('ruleStatus', $columns, true);
        $needActiveAgain = in_array('activeAgain', $columns, true);
        $needSkuMetrics = $needRuleStatus || $needActiveAgain || in_array('sbgt', $columns, true)
            || in_array('pageCvr', $columns, true) || in_array('bgtViews', $columns, true)
            || in_array('bgtCvr', $columns, true) || in_array('bgtPrc', $columns, true)
            || in_array('bgtReviews', $columns, true) || in_array('bgtDil', $columns, true)
            || in_array('bgtInv', $columns, true) || in_array('bgtGpft', $columns, true) || in_array('bgtFlag', $columns, true) || in_array('acosGpft', $columns, true) || in_array('acosFlag', $columns, true)
            || in_array('viewsL30', $columns, true) || in_array('viewsL7', $columns, true);
        foreach (self::SKU_METRIC_DISPLAY_COLUMNS as $skuCol) {
            if (in_array($skuCol, $columns, true)) {
                $needSkuMetrics = true;
                break;
            }
        }
        if ($forSbidUniverse) {
            $needSkuMetrics = false;
            $needRuleStatus = false;
            $needActiveAgain = false;
        }
        $pauseRule = ($needRuleStatus || $needActiveAgain) ? AmazonAdsPauseRule::resolvedRule() : null;
        $skuMetricsByCampaign = [];
        $pageCvrByCampaign = [];
        $familyByName = [];
        $parentDilByFam = [];
        if ($needSkuMetrics) {
            $ruleNames = [];
            foreach ($rows as $ruleRow) {
                $cn = trim((string) (((array) $ruleRow)['campaignName'] ?? ''));
                if ($cn !== '') {
                    $ruleNames[] = $cn;
                }
            }
            $familyByName = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames($ruleNames);
            foreach ($familyByName as $fam) {
                $fam = trim((string) $fam);
                if ($fam !== '') {
                    $ruleNames[] = 'PARENT '.$fam;
                }
            }
            $skuMetricsByCampaign = AmazonAdsCampaignSkuMetrics::mapForCampaignNames($ruleNames);
            $parentDilByFam = AmazonAdsCampaignSkuMetrics::parentDilByFamilyFromMetrics($skuMetricsByCampaign);
            if (in_array('pageCvr', $columns, true) || in_array('bgtViews', $columns, true)
                || in_array('bgtCvr', $columns, true) || in_array('sbgt', $columns, true)
                || in_array('viewsL30', $columns, true) || in_array('viewsL7', $columns, true)) {
                $pageCvrByCampaign = AmazonAdsCampaignSkuMetrics::parentListingCvrForCampaignNames($ruleNames);
            }
        }
        $ratingsByCid = [];
        $statesByCid = [];
        $statesByName = [];
        if ($needSkuMetrics || $needRuleStatus || $needActiveAgain) {
            $ruleCids = [];
            $ruleNames = [];
            foreach ($rows as $ruleRow) {
                $rowState = (array) $ruleRow;
                $cid = preg_replace('/\D+/', '', trim((string) ($rowState['campaign_id'] ?? ''))) ?: '';
                if ($cid !== '') {
                    $ruleCids[] = $cid;
                }
                $cn = trim((string) ($rowState['campaignName'] ?? ''));
                if ($cn !== '') {
                    $ruleNames[] = $cn;
                }
            }
            if ($needSkuMetrics || $needRuleStatus) {
                $ratingsByCid = AmazonAdsCampaignSkuMetrics::minRatingForCampaignIds($ruleCids);
            }
            if ($needActiveAgain) {
                $statesByCid = AmazonAdsPauseRuleState::mapForCampaignIds($ruleCids);
                $statesByName = AmazonAdsPauseRuleState::mapForCampaignNames($ruleNames);
            }
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
        $cpcAvgMap = $hasCpcAvg
            ? self::prefetchLifetimeAvgCpcForPageRows($table, $dbColumns, $rows)
            : [];
        $ltCvrMap = $hasLtCvr
            ? self::prefetchLifetimeCvrForPageRows($table, $dbColumns, $rows)
            : [];
        $ltAcosMap = $hasLtAcos
            ? self::prefetchLifetimeAcosForPageRows($table, $dbColumns, $rows)
            : [];
        $ltSpendSalesMap = $hasLtSpendSales
            ? self::prefetchLifetimeSpendSalesForPageRows($table, $dbColumns, $rows)
            : [];
        $skuInvByCid = [];
        $skuInvCids = [];
        foreach ($rows as $invRow) {
            $invCid = preg_replace('/\D+/', '', trim((string) (((array) $invRow)['campaign_id'] ?? ''))) ?: '';
            if ($invCid !== '') {
                $skuInvCids[] = $invCid;
            }
        }
        if (! $forSbidUniverse && $skuInvCids !== []) {
            $skuInvByCid = AmazonAdsCampaignSkuMetrics::shopifyInvForCampaignIds($skuInvCids);
        }
        $data = [];
        $suggestedSbidByRowId = [];
        $suggestedSbidByCampaign = [];
        $suggestedSbgtByRowId = [];
        $suggestedSbgtByCampaign = [];
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
            if ($hasCpcAvg) {
                $adKeyAvg = in_array('ad_type', $dbColumns, true) ? trim((string) ($adTypeStr ?? '')) : '';
                $arr['CPCAvg'] = ($cid !== '' && array_key_exists($cid."\0".$adKeyAvg, $cpcAvgMap))
                    ? $cpcAvgMap[$cid."\0".$adKeyAvg]
                    : null;
            }
            if ($hasLtCvr) {
                $adKeyLt = in_array('ad_type', $dbColumns, true) ? trim((string) ($adTypeStr ?? '')) : '';
                $arr['ltCvr'] = ($cid !== '' && array_key_exists($cid."\0".$adKeyLt, $ltCvrMap))
                    ? $ltCvrMap[$cid."\0".$adKeyLt]
                    : null;
            }
            if ($hasLtAcos) {
                $adKeyAcos = in_array('ad_type', $dbColumns, true) ? trim((string) ($adTypeStr ?? '')) : '';
                $arr['ltAcos'] = ($cid !== '' && array_key_exists($cid."\0".$adKeyAcos, $ltAcosMap))
                    ? $ltAcosMap[$cid."\0".$adKeyAcos]
                    : null;
            }
            if ($hasLtSpendSales) {
                $adKeyLtSs = in_array('ad_type', $dbColumns, true) ? trim((string) ($adTypeStr ?? '')) : '';
                $ltSs = ($cid !== '') ? ($ltSpendSalesMap[$cid."\0".$adKeyLtSs] ?? null) : null;
                $arr['ltSpend'] = $ltSs['spend'] ?? null;
                $arr['ltSales'] = $ltSs['sales'] ?? null;
                $arr['ltSold'] = $ltSs['sold'] ?? null;
                $arr['ltCps'] = $ltSs['cps'] ?? null;
                $arr['ltTcps'] = $ltSs['tcps'] ?? null;
                $arr['ltTcos'] = $ltSs['tcos'] ?? null;
                $arr['ltCpc'] = $ltSs['cpc'] ?? null;
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
            $suggestedRowId = $rowArr['id'] ?? null;
            if ($suggestedRowId !== null && $suggestedRowId !== ''
                && in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
                $wantSbid = AmazonBidUtilizationService::suggestedSbidStorageValue($arr['sbid'] ?? null);
                $cidSave = trim((string) ($rowArr['campaign_id'] ?? ''));
                if ($cidSave !== '') {
                    $suggestedSbidByCampaign[$cidSave] = $wantSbid;
                }
                if (! AmazonBidUtilizationService::storedSbidMatches($rowArr['sbid'] ?? null, $wantSbid)) {
                    $suggestedSbidByRowId[$suggestedRowId] = $wantSbid;
                }
            }
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
            $flagGpft = null;
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
                $cidInvEarly = preg_replace('/\D+/', '', $cid) ?: '';
                $mSku = self::withCampaignInventory($mSku, $skuInvByCid[$cidInvEarly] ?? null);
                if (in_array('Inv', $columns, true)) {
                    $arr['Inv'] = $mSku['inv'];
                }
                if (in_array('ovl30', $columns, true)) {
                    $arr['ovl30'] = $mSku['ovl30'];
                }
                if (in_array('dil', $columns, true) || in_array('bgtDil', $columns, true) || in_array('sbgt', $columns, true)) {
                    $invForDil = isset($mSku['inv']) && is_numeric($mSku['inv']) ? (float) $mSku['inv'] : null;
                    $ovlForDil = isset($mSku['ovl30']) && is_numeric($mSku['ovl30']) ? (float) $mSku['ovl30'] : null;
                    $dilVal = AmazonAdsCampaignSkuMetrics::tabulatorDil($invForDil, $ovlForDil);
                    if (in_array('dil', $columns, true)) {
                        $arr['dil'] = $dilVal;
                    }
                    if (in_array('bgtDil', $columns, true) || in_array('sbgt', $columns, true)) {
                        $hitDil = AmazonAdsBgtDilRule::apply($dilVal);
                        $arr['bgtDil'] = $hitDil['bgt'];
                        $arr['bgt_dil_color'] = $hitDil['color'];
                        $arr['bgt_dil_label'] = $hitDil['label'];
                        $arr['bgt_dil_value'] = $dilVal;
                    }
                }
                if (in_array('bgtInv', $columns, true) || in_array('sbgt', $columns, true)) {
                    $invForRule = isset($mSku['inv']) && is_numeric($mSku['inv']) ? (float) $mSku['inv'] : null;
                    $hitInv = AmazonAdsBgtInvRule::apply($invForRule);
                    $arr['bgtInv'] = $hitInv['bgt'];
                    $arr['bgt_inv_color'] = $hitInv['color'];
                    $arr['bgt_inv_label'] = $hitInv['label'];
                    $arr['bgt_inv_value'] = $invForRule;
                }
                if (in_array('acosGpft', $columns, true)) {
                    $arr['acosGpft'] = isset($mSku['gpft']) && is_numeric($mSku['gpft']) ? (float) $mSku['gpft'] : null;
                }
                $flagGpft = isset($mSku['gpft']) && is_numeric($mSku['gpft']) ? (float) $mSku['gpft'] : null;
                if (in_array('gpft', $columns, true) || in_array('bgtGpft', $columns, true) || in_array('sbgt', $columns, true)) {
                    $gpftVal = isset($mSku['gpft']) && is_numeric($mSku['gpft']) ? (float) $mSku['gpft'] : null;
                    $arr['gpft'] = $gpftVal;
                    if (in_array('bgtGpft', $columns, true) || in_array('sbgt', $columns, true)) {
                        $hitGpft = AmazonAdsBgtGpftRule::apply($gpftVal);
                        $arr['bgtGpft'] = $hitGpft['bgt'];
                        $arr['bgt_gpft_color'] = $hitGpft['color'];
                        $arr['bgt_gpft_label'] = $hitGpft['label'];
                        $arr['bgt_gpft_value'] = $gpftVal;
                    }
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
                            isset($pc['sess7']) && is_numeric($pc['sess7']) ? (float) $pc['sess7'] : 0.0
                        );
                        $arr['bgtViews'] = $hit['bgt'];
                        $arr['bgt_views_color'] = $hit['color'];
                        $arr['bgt_views_label'] = $hit['label'];
                        $arr['page_cvr_sess7'] = $pc['sess7'];
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
            if (($hasLSpendCols || $needProjYCols) && $cid !== '') {
                $adKey = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lk = $cid."\0".trim((string) $adKey);
                $slice = $lSpendMap[$lk] ?? ['L7' => null, 'L2' => null, 'L1' => null, 'L7sales' => null, 'L2sales' => null, 'L1sales' => null];
                if ($hasLSpendCols) {
                    $arr['L7spend'] = $slice['L7'];
                    $arr['L2spend'] = $slice['L2'];
                    $arr['L1spend'] = $slice['L1'];
                }
                if ($needProjYCols) {
                    $l7 = $slice['L7'] ?? null;
                    $l1 = $slice['L1'] ?? null;
                    $l7Sales = $slice['L7sales'] ?? null;
                    $l1Sales = $slice['L1sales'] ?? null;
                    if (in_array('ySpend', $columns, true)) {
                        $arr['ySpend'] = $l1;
                        $arr['ySpend_prev'] = $slice['L2'] ?? null;
                        $arr['ySpend_trend'] = self::moneyHistoryTrend($l1, is_numeric($slice['L2'] ?? null) ? (float) $slice['L2'] : null);
                    }
                    if (in_array('ySales', $columns, true)) {
                        $arr['ySales'] = $l1Sales;
                        $arr['ySales_prev'] = $slice['L2sales'] ?? null;
                        $arr['ySales_trend'] = self::moneyHistoryTrend($l1Sales, is_numeric($slice['L2sales'] ?? null) ? (float) $slice['L2sales'] : null);
                    }
                    if (in_array('yAcos', $columns, true)) {
                        $arr['yAcos'] = self::yesterdayAcosPercent($l1, $l1Sales);
                    }
                    if (in_array('projectedSpend', $columns, true)) {
                        $arr['projectedSpend'] = is_numeric($l7) ? self::projectMonthFromLast7((float) $l7) : null;
                        $y30Spend = is_numeric($l1) ? round(((float) $l1) * 30, 2) : null;
                        $arr['projectedSpend_prev'] = $y30Spend;
                        $arr['projectedSpend_trend'] = self::moneyHistoryTrend($arr['projectedSpend'], $y30Spend);
                    }
                    if (in_array('projectedSales', $columns, true)) {
                        $arr['projectedSales'] = is_numeric($l7Sales) ? self::projectMonthFromLast7((float) $l7Sales) : null;
                        $y30Sales = is_numeric($l1Sales) ? round(((float) $l1Sales) * 30, 2) : null;
                        $arr['projectedSales_prev'] = $y30Sales;
                        $arr['projectedSales_trend'] = self::moneyHistoryTrend($arr['projectedSales'], $y30Sales);
                    }
                }
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
            $needAcosCalc = in_array('ACOS', $columns, true)
                || in_array('acosFlag', $columns, true)
                || in_array('bgtFlag', $columns, true)
                || in_array('sbgt', $columns, true)
                || in_array('bgtAcos', $columns, true)
                || $needRuleStatus;
            $acosCalcRow = $needAcosCalc
                ? self::acosCalculationRowFromGridOverlays($rowArr, $arr, $dbColumns, $l30SliceMap, $lkSalesRow)
                : $rowArr;
            $acosPct = $needAcosCalc
                ? self::computedAcosPercentFromReportRow($acosCalcRow, $dbColumns)
                : null;
            if (in_array('ACOS', $columns, true) || in_array('sbgt', $columns, true) || in_array('bgtAcos', $columns, true)) {
                $arr['ACOS'] = $acosPct;
            }
            if (in_array('acosFlag', $columns, true)) {
                // Flag column: 1 when L30 ACOS% is below the GPFT% (needs L30 spend), same rule as the ACOS% badge filter.
                $flagSpend = isset($arr['cost']) && is_numeric($arr['cost']) ? (float) $arr['cost'] : null;
                $arr['acosFlag'] = self::acosBelowGpft($acosPct, $flagGpft, $flagSpend) ? 1 : 0;
            }
            if (in_array('bgtFlag', $columns, true) || in_array('sbgt', $columns, true)) {
                // BGT Vs FLAG: flagged campaigns only. Slab input is the gap GPFT% − ACOS%; others get no part.
                $gapSpend = isset($arr['cost']) && is_numeric($arr['cost']) ? (float) $arr['cost'] : null;
                $flagGap = AmazonAdsBgtFlagRule::flagGap($acosPct, $flagGpft, $gapSpend);
                $hitFlag = AmazonAdsBgtFlagRule::apply($flagGap);
                $arr['bgtFlag'] = $hitFlag['bgt'];
                $arr['bgt_flag_color'] = $hitFlag['color'];
                $arr['bgt_flag_label'] = $hitFlag['label'];
                $arr['bgt_flag_value'] = $flagGap;
            }
            if (in_array('sbgt', $columns, true) || in_array('bgtAcos', $columns, true)) {
                // BGT Vs ACOS band is the campaign's L30 ACOS% (the ACOS% column), not lifetime ACOS.
                $arr['bgtAcos'] = ($acosPct === null || ! is_finite((float) $acosPct))
                    ? null
                    : AmazonAcosSbgtRule::bgtAcosDecimal((float) $acosPct);
            }
            if (in_array('sbgt', $columns, true)) {
                $arr['sbgt'] = self::summedSbgtFromParts(
                    $arr['bgtViews'] ?? null,
                    $arr['bgtCvr'] ?? null,
                    $arr['bgtAcos'] ?? null,
                    $arr['bgtPrc'] ?? null,
                    $arr['bgtReviews'] ?? null,
                    $arr['bgtDil'] ?? null,
                    $arr['bgtInv'] ?? null,
                    $arr['bgtGpft'] ?? null,
                    $arr['bgtFlag'] ?? null
                );
                if (in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
                    $wantSbgt = AmazonAdsSbgt::storageValue($arr['sbgt'] ?? null);
                    $cidSbgt = trim((string) ($rowArr['campaign_id'] ?? ''));
                    if ($cidSbgt !== '') {
                        $suggestedSbgtByCampaign[$cidSbgt] = $wantSbgt;
                    }
                    $sbgtRowId = $rowArr['id'] ?? null;
                    if ($sbgtRowId !== null && $sbgtRowId !== ''
                        && ! AmazonAdsSbgt::storedMatches($rowArr['sbgt'] ?? null, $wantSbgt)) {
                        $suggestedSbgtByRowId[$sbgtRowId] = $wantSbgt;
                    }
                }
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
                $decision = AmazonAdsPauseRule::decide($pauseRule, [
                    'dil' => $gmRule['dil'],
                    'parent_dil' => AmazonAdsCampaignSkuMetrics::parentDilForCampaign(
                        $cnRule,
                        $familyByName,
                        $parentDilByFam
                    ),
                ], $cnRule);
                $arr['ruleStatus'] = $decision['status'];
                $arr['ruleStatusTip'] = $decision['reason'];
            }
            if ($needActiveAgain) {
                $cidAgain = preg_replace('/\D+/', '', trim((string) ($rowArr['campaign_id'] ?? ''))) ?: '';
                $nameAgain = AmazonAdsPauseRule::normalizeCampaignName((string) ($rowArr['campaignName'] ?? ''));
                $stateAgain = $statesByCid[$cidAgain]
                    ?? $statesByCid['id:'.$cidAgain]
                    ?? $statesByName[$nameAgain]
                    ?? null;
                $again = AmazonAdsPauseRule::activeAgainDisplay($stateAgain);
                $arr['activeAgain'] = $again['label'];
                $arr['activeAgainReason'] = $again['reason'];
                $arr['activeAgainTip'] = $again['tip'];
            }
            self::roundAmazonAdsDisplayNumericFields($arr, $columns);
            unset($arr['pink_dil_paused_at'], $arr['campaignBudgetCurrencyCode']);
            $cidInv = preg_replace('/\D+/', '', $cid) ?: '';
            $skuInv = $skuInvByCid[$cidInv] ?? null;
            $arr['sku_inv_known'] = is_array($skuInv) && ($skuInv['has_skus'] ?? false);
            $arr['sku_inv_min'] = is_array($skuInv) ? ($skuInv['min'] ?? null) : null;
            $data[] = $arr;
        }

        if ($usePhpSort && ! $phpSortPaged && ! $forBgtUniverse) {
            usort($data, static function ($a, $b) use ($requestedOrderCol, $orderDir) {
                $cmp = self::compareAmazonAdsRowValues(
                    self::amazonAdsRowSortValue($a, $requestedOrderCol),
                    self::amazonAdsRowSortValue($b, $requestedOrderCol)
                );

                return $orderDir === 'asc' ? $cmp : -$cmp;
            });
            $data = array_values(array_slice($data, $start, $length));
        }

        // Header sort used to rewrite SBID/SBGT for every filtered campaign before
        // slicing the page. Keep writes on the rows actually returned.
        [$suggestedSbidByRowId, $suggestedSbidByCampaign] = self::filterSuggestedMapsToPage(
            $suggestedSbidByRowId,
            $suggestedSbidByCampaign,
            $data
        );
        [$suggestedSbgtByRowId, $suggestedSbgtByCampaign] = self::filterSuggestedMapsToPage(
            $suggestedSbgtByRowId,
            $suggestedSbgtByCampaign,
            $data
        );
        // A grid page saves the SBID of the rows it shows. The scheduled sync has no page, so it asks for the
        // whole universe and sets this request attribute (never an HTTP input) to have every SBID saved.
        $persistSbidUniverse = $forSbidUniverse && (bool) $request->attributes->get(self::PERSIST_SBID_UNIVERSE_ATTR, false);
        if (($persistSbidUniverse || ! $forBgtUniverse) && ($suggestedSbidByRowId !== [] || $suggestedSbidByCampaign !== [])) {
            AmazonBidUtilizationService::persistSuggestedSbidByRowId($table, $suggestedSbidByRowId, $suggestedSbidByCampaign);
        }
        if (! $forBgtUniverse && ($suggestedSbgtByRowId !== [] || $suggestedSbgtByCampaign !== [])) {
            AmazonAdsSbgt::persistByRowId($table, $suggestedSbgtByRowId, $suggestedSbgtByCampaign);
        }

        if (! $forBgtUniverse && self::tableSupportsLiveSyncStatus($table, $dbColumns)) {
            $data = AmazonAdsStoredLiveBid::fillRows($table, $data);
            $data = self::attachLiveSyncStatusesToRows($data, $table);
            // Loading or reloading the grid only reads. It must not start a live pull/push; that runs from
            // the schedule or when someone presses Push / turns Auto Push & Pull on.
        }
        if (! $forBgtUniverse && (in_array('targets', $columns, true) || in_array('nTargets', $columns, true))) {
            $data = self::attachTargetCountsToRows(
                $data,
                $table,
                in_array('targets', $columns, true),
                in_array('nTargets', $columns, true)
            );
        }
        if ($forceLength === null
            && (in_array('sbidHistory', $columns, true) || in_array('sbgtHistory', $columns, true) || in_array('last_sbid', $columns, true))) {
            $data = self::attachMoneyHistoryTrends($data, $table);
        }

        $payload = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'bgt_sync_counts' => $bgtSyncCounts,
            'bid_sync_counts' => $bidSyncCounts,
        ];
        if ($persistSbidUniverse) {
            $payload['sbid_rows_written'] = count($suggestedSbidByRowId);
            $payload['sbid_rows_seen'] = count($data);
        }
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
        if ($yesterdaySpend !== null) {
            $payload['yesterdaySpend'] = $yesterdaySpend;
            $payload['yesterdaySales'] = $yesterdaySales;
            $payload['yesterdayAcosPercent'] = $yesterdayAcosPercent;
            $payload['projectedSpend'] = $projectedSpend;
            $payload['projectedSales'] = $projectedSales;
            $payload['projectedAcosPercent'] = $projectedAcosPercent;
        }
        if ($kpiBlocks !== null) {
            $payload['kpiBlocks'] = $kpiBlocks;
        }
        if ($acosGpftFlagCount !== null) {
            $payload['acosGpftFlagCount'] = $acosGpftFlagCount;
        }

        return $payload;
    }

    /**
     * Slim campaign rows for BGT / SBID rule counts. Same filters as the grid, one row per
     * campaign_id within each source (the CAMPAIGN badge is COUNT DISTINCT campaign_id per source).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{ok: bool, distinctCampaignCount: int|null, campaigns: list<array<string, mixed>>}
     */
    private function bgtUniverseJson(array $rows, ?int $distinctCampaignCount): array
    {
        $seen = [];
        $campaigns = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($cid !== '') {
                $key = (string) ($row['_bgt_src'] ?? '')."\0".$cid;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $campaigns[] = self::bgtUniverseCampaign($row);
        }

        return [
            'ok' => true,
            'distinctCampaignCount' => $distinctCampaignCount,
            'campaigns' => $campaigns,
        ];
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function tagBgtUniverseRows(array $rows, string $sourceTable): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $row['_bgt_src'] = $sourceTable;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function bgtUniverseCampaign(array $row): array
    {
        $keys = [
            'campaign_id', 'ad_type', 'ACOS', 'ltAcos', 'viewsL7', 'page_cvr_sess7',
            'bgt_cvr_page_cvr', 'pageCvr', 'bgt_prc_price', 'price',
            'bgt_reviews_rating', 'reviews', 'bgt_dil_value', 'dil', 'Inv', 'ovl30', 'bgt_gpft_value', 'gpft', 'bgt_flag_value',
            'cost', 'spend', 'bgt', 'U7%', 'U1%', 'costPerClick', 'CPC2', 'CPC3', 'CPCAvg',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $row[$key] ?? null;
        }

        return $out;
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
        $universe = $request->boolean('bgt_universe') || $request->boolean('sbid_universe');
        $draw = (int) $request->input('draw', 1);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        if ($universe) {
            $start = 0;
            $length = 20000;
        } else {
            $length = min($length, 500);
        }

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
        $yesterdaySpendSum = 0.0;
        $yesterdaySalesSum = 0.0;
        $projectedSpendSum = 0.0;
        $projectedSalesSum = 0.0;
        $haveYesterday = false;
        $kpiBlocksSum = null;
        $acosGpftFlagSum = 0;
        $haveAcosGpftFlag = false;
        $bgtSyncCounts = ['green' => 0, 'yellow' => 0, 'red' => 0];
        $bidSyncCounts = ['green' => 0, 'yellow' => 0, 'red' => 0];

        foreach ($sources as $table) {
            $part = $this->rawDataSingleSourcePayload($request, $table, 0, $fetchLen);
            if (isset($part['data']) && is_array($part['data'])) {
                foreach ($part['data'] as $r) {
                    if ($universe && is_array($r)) {
                        $r['_bgt_src'] = $table;
                    }
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
            if (isset($part['yesterdaySpend'])) {
                $yesterdaySpendSum += (float) $part['yesterdaySpend'];
                $yesterdaySalesSum += (float) ($part['yesterdaySales'] ?? 0);
                $projectedSpendSum += (float) ($part['projectedSpend'] ?? 0);
                $projectedSalesSum += (float) ($part['projectedSales'] ?? 0);
                $haveYesterday = true;
            }
            if (isset($part['kpiBlocks']) && is_array($part['kpiBlocks'])) {
                $kpiBlocksSum = self::sumKpiBlocks($kpiBlocksSum, $part['kpiBlocks']);
            }
            if (isset($part['acosGpftFlagCount'])) {
                $acosGpftFlagSum += (int) $part['acosGpftFlagCount'];
                $haveAcosGpftFlag = true;
            }
            foreach (['green', 'yellow', 'red'] as $color) {
                $bgtSyncCounts[$color] += (int) ($part['bgt_sync_counts'][$color] ?? 0);
                $bidSyncCounts[$color] += (int) ($part['bid_sync_counts'][$color] ?? 0);
            }
        }

        if ($universe) {
            return $this->bgtUniverseJson($rows, $haveDistinct ? $distinctCampaignCount : null);
        }

        usort($rows, static function ($a, $b) use ($orderKey, $orderDir) {
            $cmp = self::compareAmazonAdsRowValues(
                self::amazonAdsRowSortValue($a, $orderKey),
                self::amazonAdsRowSortValue($b, $orderKey)
            );

            return $orderDir === 'asc' ? $cmp : -$cmp;
        });

        $pageRows = self::attachMoneyHistoryTrendsForMixedPage(array_slice($rows, $start, $length));

        $payload = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => array_values($pageRows),
            'bgt_sync_counts' => $bgtSyncCounts,
            'bid_sync_counts' => $bidSyncCounts,
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
        if ($haveYesterday) {
            $payload['yesterdaySpend'] = round($yesterdaySpendSum, 2);
            $payload['yesterdaySales'] = round($yesterdaySalesSum, 2);
            $payload['yesterdayAcosPercent'] = self::overallAcosPercentFromAggregatedSums($yesterdaySpendSum, $yesterdaySalesSum);
            $payload['projectedSpend'] = round($projectedSpendSum, 2);
            $payload['projectedSales'] = round($projectedSalesSum, 2);
            $payload['projectedAcosPercent'] = self::overallAcosPercentFromAggregatedSums($projectedSpendSum, $projectedSalesSum);
        }
        if ($kpiBlocksSum !== null) {
            $payload['kpiBlocks'] = $kpiBlocksSum;
        }
        if ($haveAcosGpftFlag) {
            $payload['acosGpftFlagCount'] = $acosGpftFlagSum;
        }

        return $payload;
    }

    /**
     * Add two kpiBlocks payloads (SP + SB) key by key; every leaf is a plain sum or count. Null leaves stay null
     * only when both sides are null.
     *
     * @param  array<string, mixed>|null  $acc
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private static function sumKpiBlocks(?array $acc, array $part): array
    {
        if ($acc === null) {
            return $part;
        }
        foreach ($part as $k => $v) {
            if (is_array($v)) {
                $acc[$k] = self::sumKpiBlocks(isset($acc[$k]) && is_array($acc[$k]) ? $acc[$k] : null, $v);
            } elseif ($v === null) {
                $acc[$k] = $acc[$k] ?? null;
            } else {
                $acc[$k] = (isset($acc[$k]) && is_numeric($acc[$k]) ? $acc[$k] : 0) + $v;
            }
        }

        return $acc;
    }

    /**
     * Sort a wide window by the display value, then keep only this page.
     * Returns null when the column still needs the full row builder (SKU / SBGT).
     *
     * @param  iterable<int, object>  $rows
     * @param  array<int, string>  $dbColumns
     * @return \Illuminate\Support\Collection<int, object>|null
     */
    private static function pageRowsMatchingDisplaySort(
        iterable $rows,
        string $table,
        array $dbColumns,
        string $column,
        string $orderDir,
        int $start,
        int $length
    ) {
        $keys = self::displaySortKeysForPageWindow($rows, $table, $dbColumns, $column);
        if ($keys === null) {
            return null;
        }
        $indexed = [];
        $i = 0;
        foreach ($rows as $row) {
            $indexed[] = [$row, $keys[$i] ?? null];
            $i++;
        }
        usort($indexed, static function (array $a, array $b) use ($orderDir): int {
            $cmp = self::compareAmazonAdsRowValues($a[1], $b[1]);

            return $orderDir === 'asc' ? $cmp : -$cmp;
        });
        $slice = array_slice($indexed, $start, $length);
        $out = [];
        foreach ($slice as $pair) {
            $out[] = $pair[0];
        }

        return collect($out);
    }

    private static function columnUsesLightDisplaySort(string $column): bool
    {
        return in_array($column, [
            'ACOS', 'cost', 'clicks', 'sales30d', 'Prchase', 'Cvr',
            'L7spend', 'L2spend', 'L1spend', 'U7%', 'U2%', 'U1%',
            'projectedSpend', 'projectedSales', 'ySpend', 'ySales', 'yAcos',
        ], true);
    }

    /**
     * Sort keys for columns that can be ranked from one lookup, aligned with $rows.
     * Null means the caller must enrich the whole window.
     *
     * @param  iterable<int, object>  $rows
     * @param  array<int, string>  $dbColumns
     * @return list<mixed>|null
     */
    private static function displaySortKeysForPageWindow(iterable $rows, string $table, array $dbColumns, string $column): ?array
    {
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row;
        }
        if ($list === []) {
            return [];
        }

        $hasAd = in_array('ad_type', $dbColumns, true);

        if ($column === 'yAcos') {
            $map = self::fetchL7L2L1SpendMap($table, $dbColumns, $list);
            $keys = [];
            foreach ($list as $row) {
                $r = (array) $row;
                $cid = trim((string) ($r['campaign_id'] ?? ''));
                $ad = $hasAd ? trim((string) ($r['ad_type'] ?? '')) : '';
                $slice = ($cid !== '' && isset($map[$cid."\0".$ad]))
                    ? $map[$cid."\0".$ad]
                    : ['L1' => null, 'L1sales' => null];
                $keys[] = self::yesterdayAcosPercent($slice['L1'] ?? null, $slice['L1sales'] ?? null);
            }

            return $keys;
        }

        $spendCols = ['L7spend' => 'L7', 'L2spend' => 'L2', 'L1spend' => 'L1'];
        $utilCols = ['U7%' => 'U7', 'U2%' => 'U2', 'U1%' => 'U1'];
        $projYCols = ['projectedSpend' => 'L7', 'ySpend' => 'L1', 'projectedSales' => 'L7sales', 'ySales' => 'L1sales'];

        if (isset($spendCols[$column]) || isset($utilCols[$column]) || isset($projYCols[$column])) {
            $map = self::fetchL7L2L1SpendMap($table, $dbColumns, $list);
            $keys = [];
            foreach ($list as $row) {
                $r = (array) $row;
                $cid = trim((string) ($r['campaign_id'] ?? ''));
                $ad = $hasAd ? trim((string) ($r['ad_type'] ?? '')) : '';
                $slice = ($cid !== '' && isset($map[$cid."\0".$ad]))
                    ? $map[$cid."\0".$ad]
                    : ['L7' => null, 'L2' => null, 'L1' => null, 'L7sales' => null, 'L2sales' => null, 'L1sales' => null];
                if (isset($spendCols[$column])) {
                    $keys[] = $slice[$spendCols[$column]] ?? null;
                } elseif (isset($projYCols[$column])) {
                    $raw = $slice[$projYCols[$column]] ?? null;
                    if ($column === 'projectedSpend' || $column === 'projectedSales') {
                        $keys[] = is_numeric($raw) ? self::projectMonthFromLast7((float) $raw) : null;
                    } else {
                        $keys[] = $raw;
                    }
                } else {
                    $u = self::utilizationPercentValuesFromLSlice($r, $slice);
                    $keys[] = self::formatUtilPercent($u[$utilCols[$column]] ?? null);
                }
            }

            return $keys;
        }

        if ($column === 'ACOS') {
            $map = self::fetchL30SummarySliceMap($table, $dbColumns, $list);
            $keys = [];
            foreach ($list as $row) {
                $r = (array) $row;
                $cid = trim((string) ($r['campaign_id'] ?? ''));
                $ad = $hasAd ? trim((string) ($r['ad_type'] ?? '')) : '';
                $lk = $cid."\0".$ad;
                $arr = [];
                if ($cid !== '' && isset($map[$lk]) && $map[$lk]['spend'] !== null) {
                    $arr['cost'] = $map[$lk]['spend'];
                }
                $acosRow = self::acosCalculationRowFromGridOverlays($r, $arr, $dbColumns, $map, $cid !== '' ? $lk : '');
                $keys[] = self::computedAcosPercentFromReportRow($acosRow, $dbColumns);
            }

            return $keys;
        }

        if (in_array($column, ['cost', 'clicks', 'sales30d', 'Prchase', 'Cvr'], true)) {
            $map = self::fetchL30SummarySliceMap($table, $dbColumns, $list);
            $keys = [];
            foreach ($list as $row) {
                $r = (array) $row;
                $cid = trim((string) ($r['campaign_id'] ?? ''));
                $ad = $hasAd ? trim((string) ($r['ad_type'] ?? '')) : '';
                $slice = ($cid !== '' && isset($map[$cid."\0".$ad])) ? $map[$cid."\0".$ad] : null;
                $spend = $slice['spend'] ?? (is_numeric($r['cost'] ?? null) ? (float) $r['cost'] : null);
                $clicks = $slice['clicks'] ?? (is_numeric($r['clicks'] ?? null) ? (float) $r['clicks'] : null);
                $sales = $slice['sales30d'] ?? (is_numeric($r['sales30d'] ?? $r['sales'] ?? null) ? (float) ($r['sales30d'] ?? $r['sales']) : null);
                $sold = $slice['purchases30d'] ?? (is_numeric($r['purchases30d'] ?? $r['purchases'] ?? null) ? (float) ($r['purchases30d'] ?? $r['purchases']) : null);
                if ($column === 'cost') {
                    $keys[] = $spend;
                } elseif ($column === 'clicks') {
                    $keys[] = $clicks;
                } elseif ($column === 'sales30d') {
                    $keys[] = $sales;
                } elseif ($column === 'Prchase') {
                    $keys[] = $sold;
                } else {
                    $keys[] = ($sold !== null && $clicks !== null && (float) $clicks > 0)
                        ? round(((float) $sold / (float) $clicks) * 100, 2)
                        : null;
                }
            }

            return $keys;
        }

        return self::heavyDisplaySortKeys($list, $table, $dbColumns, $column);
    }

    /**
     * Use campaign-SKU inventory when the name-derived Inv is missing or zero.
     * Same replacement the grid applies before Inv / Dil / BGT columns.
     *
     * @param  array<string, mixed>  $mSku
     * @param  array<string, mixed>|null  $skuInvHit
     * @return array<string, mixed>
     */
    private static function withCampaignInventory(array $mSku, ?array $skuInvHit): array
    {
        return AmazonAdsCampaignSkuMetrics::overlayCampaignInventory($mSku, $skuInvHit);
    }

    /**
     * @param  array<int|string, mixed>  $byRowId
     * @param  array<int|string, mixed>  $byCampaign
     * @param  list<array<string, mixed>>  $pageRows
     * @return array{0: array<int|string, mixed>, 1: array<int|string, mixed>}
     */
    private static function filterSuggestedMapsToPage(array $byRowId, array $byCampaign, array $pageRows): array
    {
        $keepRow = [];
        $keepCid = [];
        foreach ($pageRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rid = $row['id'] ?? null;
            if ($rid !== null && $rid !== '') {
                $keepRow[(string) $rid] = true;
            }
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($cid !== '') {
                $keepCid[$cid] = true;
            }
        }
        $byRowId = array_filter(
            $byRowId,
            static fn ($id): bool => isset($keepRow[(string) $id]),
            ARRAY_FILTER_USE_KEY
        );
        $byCampaign = array_filter(
            $byCampaign,
            static fn ($cid): bool => isset($keepCid[(string) $cid]),
            ARRAY_FILTER_USE_KEY
        );

        return [$byRowId, $byCampaign];
    }

    /**
     * Sort keys for columns that are filled in PHP (Inv, SBGT, BGT parts, CPC, Rule).
     * One lookup for the sorted column, then only that page is enriched.
     *
     * @param  list<object|array<string, mixed>>  $list
     * @param  array<int, string>  $dbColumns
     * @return list<mixed>|null
     */
    private static function heavyDisplaySortKeys(array $list, string $table, array $dbColumns, string $column): ?array
    {
        $handled = [
            'Inv', 'INV', 'ovl30', 'dil', 'price', 'reviews', 'ruleStatus', 'activeAgain',
            'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'bgtDil', 'bgtInv', 'gpft', 'acosGpft', 'acosFlag', 'bgtGpft', 'bgtFlag', 'sbgt',
            'CPC3', 'CPCAvg', 'CPC2', 'ltCvr', 'L1cost', 'L1clicks',
            'pageCvr', 'viewsL30', 'viewsL7', 'ltAcos', 'ltSpend', 'ltSales', 'ltSold', 'ltCps', 'ltTcps', 'ltTcos', 'ltCpc',
        ];
        if (! in_array($column, $handled, true)) {
            return null;
        }

        $hasAd = in_array('ad_type', $dbColumns, true);
        $needsSku = in_array($column, ['Inv', 'INV', 'ovl30', 'dil', 'price', 'reviews', 'bgtDil', 'bgtInv', 'gpft', 'acosGpft', 'acosFlag', 'bgtGpft', 'bgtFlag', 'bgtPrc', 'bgtReviews', 'sbgt', 'ruleStatus'], true);
        $needsViews = in_array($column, ['bgtViews', 'bgtCvr', 'pageCvr', 'viewsL30', 'viewsL7', 'sbgt'], true);
        $needsLtAcos = in_array($column, ['ltAcos', 'bgtAcos', 'sbgt'], true);
        $needsRatings = in_array($column, ['reviews', 'bgtReviews', 'sbgt'], true);
        $needsShopifyInv = in_array($column, ['Inv', 'INV', 'ovl30', 'dil', 'bgtDil', 'bgtInv', 'sbgt'], true);
        $needsCpcDay = $column === 'CPC2' || $column === 'CPC3';
        $needsCpcAvg = $column === 'CPCAvg';
        $needsLtCvr = $column === 'ltCvr';
        $needsLtSpendSales = in_array($column, ['ltSpend', 'ltSales', 'ltSold', 'ltCps', 'ltTcps', 'ltTcos', 'ltCpc'], true);
        $needsL1 = $column === 'L1cost' || $column === 'L1clicks';
        $needsAcosFlag = in_array($column, ['acosFlag', 'bgtFlag', 'bgtAcos', 'sbgt'], true);

        $names = [];
        $cids = [];
        foreach ($list as $row) {
            $r = (array) $row;
            $cn = trim((string) ($r['campaignName'] ?? ''));
            if ($cn !== '') {
                $names[] = $cn;
            }
            $cid = preg_replace('/\D+/', '', trim((string) ($r['campaign_id'] ?? ''))) ?: '';
            if ($cid !== '') {
                $cids[] = $cid;
            }
        }

        $skuByName = ($needsSku && $column !== 'ruleStatus')
            ? AmazonAdsCampaignSkuMetrics::mapForCampaignNames($names)
            : [];
        $skuInvByCid = $needsShopifyInv ? AmazonAdsCampaignSkuMetrics::shopifyInvForCampaignIds($cids) : [];
        $ratingsByCid = $needsRatings ? AmazonAdsCampaignSkuMetrics::minRatingForCampaignIds($cids) : [];
        $pageCvrByName = $needsViews ? AmazonAdsCampaignSkuMetrics::parentListingCvrForCampaignNames($names) : [];
        $ltAcosMap = $needsLtAcos ? self::prefetchLifetimeAcosForPageRows($table, $dbColumns, $list) : [];
        $ltCvrMap = $needsLtCvr ? self::prefetchLifetimeCvrForPageRows($table, $dbColumns, $list) : [];
        $cpcAvgMap = $needsCpcAvg ? self::prefetchLifetimeAvgCpcForPageRows($table, $dbColumns, $list) : [];
        $ltSpendSalesMap = $needsLtSpendSales ? self::prefetchLifetimeSpendSalesForPageRows($table, $dbColumns, $list) : [];
        $cpcDayCache = $needsCpcDay
            ? self::prefetchCostPerClickForPageRows($table, $dbColumns, $list, $column === 'CPC2', $column === 'CPC3')
            : [];
        $l1Map = ($needsL1 && $table === 'amazon_sb_campaign_reports')
            ? self::fetchL1SummaryClicksCostMap($table, $dbColumns, $list)
            : [];

        $l30FlagMap = $needsAcosFlag ? self::fetchL30SummarySliceMap($table, $dbColumns, $list) : [];
        $pauseRule = null;
        $familyByName = [];
        $parentDilByFam = [];
        if ($column === 'ruleStatus') {
            $pauseRule = AmazonAdsPauseRule::resolvedRule();
            $familyByName = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames($names);
            $parentNames = $names;
            foreach ($familyByName as $fam) {
                $fam = trim((string) $fam);
                if ($fam !== '') {
                    $parentNames[] = 'PARENT '.$fam;
                }
            }
            $parentMetrics = AmazonAdsCampaignSkuMetrics::mapForCampaignNames($parentNames);
            $parentDilByFam = AmazonAdsCampaignSkuMetrics::parentDilByFamilyFromMetrics($parentMetrics);
            $skuByName = $parentMetrics;
        }

        $statesByCid = [];
        $statesByName = [];
        if ($column === 'activeAgain') {
            $statesByCid = AmazonAdsPauseRuleState::mapForCampaignIds($cids);
            $statesByName = AmazonAdsPauseRuleState::mapForCampaignNames($names);
        }

        $emptySku = [
            'sku' => '',
            'price' => null,
            'dil' => null,
            'inv' => null,
            'l30' => null,
            'ovl30' => null,
            'lmp_price' => null,
        ];
        $keys = [];
        foreach ($list as $row) {
            $r = (array) $row;
            $cn = trim((string) ($r['campaignName'] ?? ''));
            $cidRaw = trim((string) ($r['campaign_id'] ?? ''));
            $cid = preg_replace('/\D+/', '', $cidRaw) ?: '';
            $ad = $hasAd ? trim((string) ($r['ad_type'] ?? '')) : '';
            $lk = $cidRaw."\0".$ad;
            $m = ($cn !== '' && isset($skuByName[$cn])) ? $skuByName[$cn] : $emptySku;
            if ($needsShopifyInv) {
                $m = self::withCampaignInventory($m, $skuInvByCid[$cid] ?? null);
            }
            $inv = isset($m['inv']) && is_numeric($m['inv']) ? (float) $m['inv'] : null;
            $ovl = isset($m['ovl30']) && is_numeric($m['ovl30']) ? (float) $m['ovl30'] : null;
            $dil = AmazonAdsCampaignSkuMetrics::tabulatorDil($inv, $ovl);
            $pc = ($cn !== '' && isset($pageCvrByName[$cn]))
                ? $pageCvrByName[$cn]
                : AmazonAdsCampaignSkuMetrics::emptyParentListingCvr();
            $ltAcos = ($cidRaw !== '' && array_key_exists($lk, $ltAcosMap)) ? $ltAcosMap[$lk] : null;

            if ($column === 'Inv' || $column === 'INV') {
                $keys[] = $inv;
            } elseif ($column === 'ovl30') {
                $keys[] = $ovl;
            } elseif ($column === 'dil' || $column === 'bgtDil') {
                $keys[] = $column === 'dil'
                    ? $dil
                    : AmazonAdsBgtDilRule::apply($dil)['bgt'];
            } elseif ($column === 'price') {
                $keys[] = self::amazonAdsRowSortValue([
                    'price' => $m['price'] ?? null,
                    'lmp_price' => $m['lmp_price'] ?? null,
                ], 'price');
            } elseif ($column === 'bgtPrc') {
                $gm = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($m);
                $pricePrc = $gm['price'] ?? (isset($m['price']) && is_numeric($m['price']) ? (float) $m['price'] : null);
                $keys[] = AmazonAdsBgtPrcRule::apply($pricePrc !== null && is_numeric($pricePrc) ? (float) $pricePrc : null)['bgt'];
            } elseif ($column === 'reviews' || $column === 'bgtReviews') {
                $fromAds = $cid !== '' ? ($ratingsByCid[$cid] ?? null) : null;
                $rating = is_array($fromAds) && $fromAds['rating'] !== null
                    ? (float) $fromAds['rating']
                    : (isset($m['rating']) && is_numeric($m['rating']) ? (float) $m['rating'] : null);
                $keys[] = $column === 'reviews'
                    ? $rating
                    : AmazonAdsBgtReviewsRule::apply($rating)['bgt'];
            } elseif ($column === 'bgtInv') {
                $keys[] = AmazonAdsBgtInvRule::apply($inv)['bgt'];
            } elseif ($column === 'bgtFlag') {
                $keys[] = AmazonAdsBgtFlagRule::apply(self::flagGapForSortRow($r, $dbColumns, $l30FlagMap, $lk, isset($m['gpft']) && is_numeric($m['gpft']) ? (float) $m['gpft'] : null))['bgt'];
            } elseif ($column === 'acosFlag') {
                $flagGpftKey = isset($m['gpft']) && is_numeric($m['gpft']) ? (float) $m['gpft'] : null;
                $keys[] = self::acosFlaggedForSortRow($r, $dbColumns, $l30FlagMap, $lk, $flagGpftKey) ? 1 : 0;
            } elseif ($column === 'gpft' || $column === 'acosGpft' || $column === 'bgtGpft') {
                $gpftKey = isset($m['gpft']) && is_numeric($m['gpft']) ? (float) $m['gpft'] : null;
                $keys[] = $column !== 'bgtGpft' ? $gpftKey : AmazonAdsBgtGpftRule::apply($gpftKey)['bgt'];
            } elseif ($column === 'pageCvr') {
                $keys[] = $pc['page_cvr'] ?? null;
            } elseif ($column === 'viewsL30') {
                $keys[] = isset($pc['sess30']) && is_numeric($pc['sess30']) ? (int) round((float) $pc['sess30']) : null;
            } elseif ($column === 'viewsL7' || $column === 'bgtViews') {
                $views = isset($pc['sess7']) && is_numeric($pc['sess7']) ? (float) $pc['sess7'] : null;
                $keys[] = $column === 'viewsL7'
                    ? ($views !== null ? (int) round($views) : null)
                    : AmazonAdsBgtViewsRule::apply($views !== null ? $views : 0.0)['bgt'];
            } elseif ($column === 'bgtCvr') {
                $cvrIn = isset($pc['page_cvr']) && is_numeric($pc['page_cvr']) ? (float) $pc['page_cvr'] : 0.0;
                $keys[] = AmazonAdsBgtCvrRule::apply($cvrIn)['bgt'];
            } elseif ($column === 'ltAcos') {
                $keys[] = $ltAcos;
            } elseif (in_array($column, ['ltSpend', 'ltSales', 'ltSold', 'ltCps', 'ltTcps', 'ltTcos', 'ltCpc'], true)) {
                $ltField = ['ltSpend' => 'spend', 'ltSales' => 'sales', 'ltSold' => 'sold', 'ltCps' => 'cps', 'ltTcps' => 'tcps', 'ltTcos' => 'tcos', 'ltCpc' => 'cpc'][$column];
                $keys[] = ($cidRaw !== '' && isset($ltSpendSalesMap[$lk]))
                    ? ($ltSpendSalesMap[$lk][$ltField] ?? null)
                    : null;
            } elseif ($column === 'bgtAcos') {
                $l30Acos = self::l30AcosForSortRow($r, $dbColumns, $l30FlagMap, $lk);
                $keys[] = $l30Acos !== null ? AmazonAcosSbgtRule::bgtAcosDecimal($l30Acos) : null;
            } elseif ($column === 'sbgt') {
                $fromAds = $cid !== '' ? ($ratingsByCid[$cid] ?? null) : null;
                $rating = is_array($fromAds) && $fromAds['rating'] !== null
                    ? (float) $fromAds['rating']
                    : (isset($m['rating']) && is_numeric($m['rating']) ? (float) $m['rating'] : null);
                $gm = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($m);
                $pricePrc = $gm['price'] ?? (isset($m['price']) && is_numeric($m['price']) ? (float) $m['price'] : null);
                $sess7 = isset($pc['sess7']) && is_numeric($pc['sess7']) ? (float) $pc['sess7'] : 0.0;
                $cvrIn = isset($pc['page_cvr']) && is_numeric($pc['page_cvr']) ? (float) $pc['page_cvr'] : 0.0;
                $keys[] = self::summedSbgtFromParts(
                    AmazonAdsBgtViewsRule::apply($sess7)['bgt'],
                    AmazonAdsBgtCvrRule::apply($cvrIn)['bgt'],
                    ($l30AcosForSbgt = self::l30AcosForSortRow($r, $dbColumns, $l30FlagMap, $lk)) !== null ? AmazonAcosSbgtRule::bgtAcosDecimal($l30AcosForSbgt) : null,
                    AmazonAdsBgtPrcRule::apply($pricePrc !== null && is_numeric($pricePrc) ? (float) $pricePrc : null)['bgt'],
                    AmazonAdsBgtReviewsRule::apply($rating)['bgt'],
                    AmazonAdsBgtDilRule::apply($dil)['bgt'],
                    AmazonAdsBgtInvRule::apply($inv)['bgt'],
                    AmazonAdsBgtGpftRule::apply(isset($m['gpft']) && is_numeric($m['gpft']) ? (float) $m['gpft'] : null)['bgt'],
                    AmazonAdsBgtFlagRule::apply(self::flagGapForSortRow($r, $dbColumns, $l30FlagMap, $lk, isset($m['gpft']) && is_numeric($m['gpft']) ? (float) $m['gpft'] : null))['bgt']
                );
            } elseif ($column === 'ruleStatus') {
                $gm = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($m);
                $decision = AmazonAdsPauseRule::decide($pauseRule, [
                    'dil' => $gm['dil'],
                    'parent_dil' => AmazonAdsCampaignSkuMetrics::parentDilForCampaign($cn, $familyByName, $parentDilByFam),
                ], $cn);
                $keys[] = $decision['status'];
            } elseif ($column === 'activeAgain') {
                $nameAgain = AmazonAdsPauseRule::normalizeCampaignName((string) ($r['campaignName'] ?? ''));
                $stateAgain = $statesByCid[$cid]
                    ?? $statesByCid['id:'.$cid]
                    ?? $statesByName[$nameAgain]
                    ?? null;
                $keys[] = AmazonAdsPauseRule::activeAgainDisplay($stateAgain)['label'];
            } elseif ($column === 'CPCAvg') {
                $keys[] = ($cidRaw !== '' && array_key_exists($lk, $cpcAvgMap)) ? $cpcAvgMap[$lk] : null;
            } elseif ($column === 'ltCvr') {
                $keys[] = ($cidRaw !== '' && array_key_exists($lk, $ltCvrMap)) ? $ltCvrMap[$lk] : null;
            } elseif ($column === 'CPC2' || $column === 'CPC3') {
                $adTypeStr = $hasAd ? (isset($r['ad_type']) && is_string($r['ad_type']) ? $r['ad_type'] : null) : null;
                $day = self::calendarDayOffsetFromCpc1Anchor($r, $dbColumns, $table, $column === 'CPC2' ? 1 : 2);
                $keys[] = ($day !== null && $cidRaw !== '')
                    ? self::fetchCostPerClickOnReportDay($table, $dbColumns, $cidRaw, $adTypeStr, $day, $cpcDayCache)
                    : null;
            } elseif ($column === 'L1cost' || $column === 'L1clicks') {
                $l1 = $l1Map[$lk] ?? null;
                if ($column === 'L1cost') {
                    $keys[] = (is_array($l1) && isset($l1['cost']) && is_numeric($l1['cost']) && is_finite((float) $l1['cost']))
                        ? round((float) $l1['cost'], 2)
                        : null;
                } else {
                    $keys[] = (is_array($l1) && isset($l1['clicks']) && is_numeric($l1['clicks']) && is_finite((float) $l1['clicks']))
                        ? (int) round((float) $l1['clicks'])
                        : null;
                }
            } else {
                $keys[] = null;
            }
        }

        return $keys;
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
        if (! SchemaMemo::hasTable($table) || ! in_array('sbid', SchemaMemo::getColumnListing($table), true)) {
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
        if (! SchemaMemo::hasTable($table)) {
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
        self::forgetRawDataCache();
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
        self::forgetRawDataCache();
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
     * Split SBGT push rows: $0 pauses (Amazon will not take a $0 daily budget); 1–9999 updates budget.
     *
     * @param  list<mixed>  $rows
     * @return array{budgets: array<string, float>, pause_ids: list<string>, skipped: list<array<string, mixed>>}
     */
    private static function partitionSbgtPushRows(array $rows): array
    {
        /** @var array<string, float> $budgets */
        $budgets = [];
        /** @var array<string, true> $pause */
        $pause = [];
        $skipped = [];

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
            if (AmazonAdsSbgt::isExplicitZero($raw)) {
                unset($budgets[$cid]);
                $pause[$cid] = true;
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
                        : 'Invalid SBGT (must be a whole dollar amount 1–9999, or 0 to pause)',
                ];
                continue;
            }
            unset($pause[$cid]);
            $budgets[$cid] = (float) $tier;
        }

        return [
            'budgets' => $budgets,
            'pause_ids' => array_keys($pause),
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     * @return array{paused: int, failed: int, errors: list<string>}
     */
    private function pauseCampaignsForZeroSbgt(string $channel, array $campaignIds): array
    {
        if ($campaignIds === []) {
            return ['paused' => 0, 'failed' => 0, 'errors' => []];
        }
        $stats = app(AmazonAdsPauseRuleApplicator::class)->pauseCampaigns($channel, $campaignIds);
        Log::info('amazon-ads: paused campaigns with SBGT 0', [
            'channel' => $channel,
            'requested' => count($campaignIds),
            'paused' => $stats['paused'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
        ]);

        return [
            'paused' => (int) ($stats['paused'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
            'errors' => is_array($stats['errors'] ?? null) ? $stats['errors'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{paused: int, failed: int, errors: list<string>}  $pauseStats
     * @param  list<string>  $pauseIds
     * @param  list<array<string, mixed>>  $skipped
     * @return array<string, mixed>
     */
    private static function mergeZeroSbgtPauseIntoPushPayload(
        array $payload,
        array $pauseStats,
        array $pauseIds,
        array $skipped,
        int $budgetCount,
        int $submittedCount
    ): array {
        $paused = (int) ($pauseStats['paused'] ?? 0);
        $pauseFailed = (int) ($pauseStats['failed'] ?? 0);
        $payload['skipped_rows'] = $skipped;
        $payload['total_skipped'] = count($skipped);
        $payload['total_processed'] = $budgetCount;
        $payload['total_submitted'] = $submittedCount;
        $payload['l30_rescued_count'] = 0;
        $payload['paused_zero_sbgt'] = $paused;
        $payload['paused_zero_sbgt_ids'] = $pauseIds;
        $payload['pause_zero_sbgt_failed'] = $pauseFailed;
        $payload['pause_zero_sbgt_errors'] = $pauseStats['errors'] ?? [];
        if ($paused > 0 || $pauseIds !== []) {
            $pauseNote = $paused > 0
                ? 'Paused '.$paused.' campaign(s) with SBGT 0 (cannot push $0 budget).'
                : 'SBGT 0 pause requested for '.count($pauseIds).' campaign(s).';
            $existing = isset($payload['message']) && is_string($payload['message']) ? $payload['message'] : '';
            $payload['message'] = trim($pauseNote.($existing !== '' ? ' '.$existing : ''));
        }

        return $payload;
    }

    /**
     * Pull live Amazon BGT/BID, compare to grid SBGT/SBID, push mismatches, verify live match.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "channel"|"ad_type", "sbgt", "sbid", "campaignName" } ] }
     */
    public function syncLiveBidBgt(Request $request, AmazonAdsLiveBidBgtSyncService $sync): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $rows = $request->input('rows');
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Provide a non-empty rows array with campaign_id and sbgt/sbid.',
                'status' => 400,
            ], 400);
        }
        if (count($rows) > 100) {
            return response()->json([
                'ok' => false,
                'message' => 'At most 100 rows per request.',
                'status' => 422,
            ], 422);
        }

        $out = $sync->syncRows($rows, 'web');
        self::forgetRawDataCache();
        $failed = (int) ($out['failed'] ?? 0);
        $synced = (int) ($out['synced'] ?? 0);

        return response()->json([
            'ok' => $failed === 0,
            'message' => $failed === 0
                ? ('Live sync verified '.$synced.' campaign(s).')
                : ('Live sync finished with '.$failed.' unverified campaign(s).'),
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => (int) ($out['skipped'] ?? 0),
            'in_progress' => (int) ($out['in_progress'] ?? 0),
            'results' => $out['results'] ?? [],
        ], $failed === 0 ? 200 : 207);
    }

    /**
     * Push SBGT (Bgt Views + Bgt Cvr + BGT ACOS) as SP daily budget ($) to Amazon.
     * SBGT 0 pauses the campaign instead of pushing budget.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "sbgt" }, ... ] } (max 100 unique campaigns; last row wins per campaign_id).
     */
    public function pushSpSbgts(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
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

        $split = self::partitionSbgtPushRows($rows);
        $tierByCampaignId = $split['budgets'];
        $pauseIds = $split['pause_ids'];
        $skipped = $split['skipped'];
        $pauseStats = $this->pauseCampaignsForZeroSbgt('sp', $pauseIds);

        if ($tierByCampaignId === [] && $pauseIds === []) {
            return response()->json([
                'message' => 'No valid campaign_id / SBGT pairs (SBGT must be a whole dollar amount 1–9999, or 0 to pause).',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }
        if (count($tierByCampaignId) + count($pauseIds) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }

        if ($tierByCampaignId === []) {
            $ok = $pauseStats['failed'] === 0;
            $payload = self::mergeZeroSbgtPauseIntoPushPayload(
                ['ok' => $ok, 'message' => ''],
                $pauseStats,
                $pauseIds,
                $skipped,
                0,
                count($rows)
            );

            return response()->json($payload, $ok ? 200 : 422);
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
            $merged = self::mergeZeroSbgtPauseIntoPushPayload(
                $responseData,
                $pauseStats,
                $pauseIds,
                $skipped,
                count($tierByCampaignId),
                count($rows)
            );

            return response()->json($merged, $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Push SBGT (Bgt Views + Bgt Cvr + BGT ACOS) as SB daily budget ($) to Amazon.
     * SBGT 0 pauses the campaign instead of pushing budget.
     *
     * Expects JSON: { "rows": [ { "campaign_id", "sbgt" }, ... ] } (max 100 unique campaigns; last row wins per campaign_id).
     */
    public function pushSbSbgts(Request $request): JsonResponse
    {
        self::forgetRawDataCache();
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

        $split = self::partitionSbgtPushRows($rows);
        $tierByCampaignId = $split['budgets'];
        $pauseIds = $split['pause_ids'];
        $skipped = $split['skipped'];
        $pauseStats = $this->pauseCampaignsForZeroSbgt('sb', $pauseIds);

        if ($tierByCampaignId === [] && $pauseIds === []) {
            return response()->json([
                'message' => 'No valid campaign_id / SBGT pairs (SBGT must be a whole dollar amount 1–9999, or 0 to pause).',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }
        if (count($tierByCampaignId) + count($pauseIds) > 100) {
            return response()->json([
                'message' => 'At most 100 distinct campaigns per request.',
                'status' => 422,
                'skipped_rows' => $skipped,
                'total_skipped' => count($skipped),
            ], 422);
        }

        if ($tierByCampaignId === []) {
            $ok = $pauseStats['failed'] === 0;
            $payload = self::mergeZeroSbgtPauseIntoPushPayload(
                ['ok' => $ok, 'message' => ''],
                $pauseStats,
                $pauseIds,
                $skipped,
                0,
                count($rows)
            );

            return response()->json($payload, $ok ? 200 : 422);
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
            $merged = self::mergeZeroSbgtPauseIntoPushPayload(
                $responseData,
                $pauseStats,
                $pauseIds,
                $skipped,
                count($tierByCampaignId),
                count($rows)
            );

            return response()->json($merged, $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Amazon rows for /advertisement-master — parent Amazon total plus KW / PT / HL
     * sub-rows (same L30 summary-universe aggregation as /amazon-ads/all Spend badges).
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
        if (! SchemaMemo::hasTable($table)) {
            return $empty;
        }

        $dbColumns = self::orderedColumnsForTable($table);
        $columns = self::displayColumnsForTable($table);
        if ($columns === [] || $dbColumns === []) {
            return $empty;
        }

        $query = DB::table($table);
        // Same L30 universe as /amazon-ads/all Spend / ACOS badges (Amazon console
        // Last 30 days), not the calendar latest-day grid subset.
        self::applyAdvertisementMasterL30SummaryFilter($query, $table);
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
     * Count ACTIVE (campaignStatus = ENABLED) campaigns on the latest calendar
     * report day (grid window). Spend badges use L30 summary rows instead.
     * Optional $scope applies the same KW / PT / HL search as
     * {@see advertisementMasterMetricsForSource}. Falls back to the latest
     * `report_date_range = 'L30'` row per campaign when no daily day is available.
     *
     * @param  callable(Builder, array<int, string>, string): void|null  $scope
     */
    private function advertisementMasterActiveCountForSource(string $source, ?callable $scope = null): int
    {
        if (! isset(self::RAW_TABLE_SOURCES[$source])) {
            return 0;
        }
        $table = self::RAW_TABLE_SOURCES[$source];
        if (! SchemaMemo::hasTable($table)) {
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
            ->where('report_date_range', 'L30')
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
     * Restrict advertisement-master spend/sales to latest L30 summary rows.
     */
    private static function applyAdvertisementMasterL30SummaryFilter(Builder $query, string $table): void
    {
        $cols = SchemaMemo::getColumnListing($table);
        if (! in_array('report_date_range', $cols, true)) {
            return;
        }
        $query->where('report_date_range', 'L30');
    }

    /**
     * Latest available report day as a single Calendar day (active-count window).
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
            'channel_key' => $channel,
            'source'     => $source,
            'spend'      => round($spend, 2),
            'clicks'     => (int) round($clicks),
            'sold'       => (int) round($sold),
            'sales'      => round($sales, 2),
            'cvr'        => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0,
            'acos'       => ($spend > 0 && $sold <= 0)
                ? 100
                : ($sales > 0
                    ? round(($spend / $sales) * 100, 0)
                    : ($spend > 0 ? 100 : 0)),
            'tcos'       => 0,
            'active'     => (int) ($row->active ?? 0),
            'is_sub_row' => $isSubRow,
            'marketplace' => 'amazon',
        ];
    }
}
