<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Support\AmazonAcosSbgtRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * SP campaign raw table: hide noisy Amazon metric / audit columns on Amazon Ads All (keep bids, ids, cost, CPC block, L-spends, BGT/SBGT, ACOS, Prchase).
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
        'clickThroughRate',
        'qualifiedBorrows',
        'purchases1d',
        'purchases7d',
        'purchases14d',
        'purchases30d',
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
     * Display order further adjusts: last_sbid + sbid after U1%; yes_sbid / sbid_m stay in priority positions unless moved elsewhere.
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
     * (so `ad_type` may sit before `campaign_id` without pulling U7/U2/U1 next to it).
     * `bgt` follows `campaignName`; `sbgt` follows `sbid` when present (else after `last_sbid`, else after `bgt`).
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

        // SBGT after `sbid` (same scope as BGT: table has campaign budget). Fallback if no sbid column.
        if (in_array('campaignBudgetAmount', self::orderedColumnsForTable($table), true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'sbgt'));
            $idxSbidForSbgt = array_search('sbid', $ordered, true);
            if ($idxSbidForSbgt !== false) {
                array_splice($ordered, $idxSbidForSbgt + 1, 0, ['sbgt']);
            } else {
                $idxLastForSbgt = array_search('last_sbid', $ordered, true);
                if ($idxLastForSbgt !== false) {
                    array_splice($ordered, $idxLastForSbgt + 1, 0, ['sbgt']);
                } else {
                    $idxBgtForSbgt = array_search('bgt', $ordered, true);
                    if ($idxBgtForSbgt !== false) {
                        array_splice($ordered, $idxBgtForSbgt + 1, 0, ['sbgt']);
                    }
                }
            }
        }

        $idxBgt = array_search('bgt', $ordered, true);
        if ($idxBgt !== false && in_array('clicks', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'clicks'));
            $idxBgt = array_search('bgt', $ordered, true);
            if ($idxBgt !== false) {
                array_splice($ordered, $idxBgt + 1, 0, ['clicks']);
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

        // CPC 3 / 2 / 1 after L1 spend (same gates as before: needs costPerClick + campaign_id + report_date_range).
        $canCpcBlock = in_array('costPerClick', $ordered, true)
            && in_array('campaign_id', $ordered, true)
            && in_array('report_date_range', $ordered, true);
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

        if (in_array('sales30d', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'sales30d'));
            $idxCpc1 = array_search('costPerClick', $ordered, true);
            if ($idxCpc1 !== false) {
                array_splice($ordered, $idxCpc1 + 1, 0, ['sales30d']);
            } else {
                $ordered[] = 'sales30d';
            }
        }

        // ACOS (%) = cost / sales * 100 — display after primary sales column when cost + sales exist on the table.
        $baseCols = self::orderedColumnsForTable($table);
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

        // Prchase: `purchases30d` shown under renamed header, placed after L1 CPC (`costPerClick`).
        if (in_array('purchases30d', $baseCols, true) && in_array('costPerClick', $ordered, true)) {
            $ordered = array_values(array_filter($ordered, static fn (string $c): bool => $c !== 'purchases30d'));
            $idxL1Cpc = array_search('costPerClick', $ordered, true);
            if ($idxL1Cpc !== false) {
                array_splice($ordered, $idxL1Cpc + 1, 0, ['Prchase']);
            }
        }

        if ($table === 'amazon_sp_campaign_reports') {
            $ordered = array_values(array_filter(
                $ordered,
                static fn (string $c): bool => ! in_array($c, self::AMAZON_SP_CAMPAIGN_REPORTS_HIDDEN_DISPLAY_COLUMNS, true)
            ));
        }

        // Not shown on Amazon Ads All (still in DB / global search).
        $ordered = array_values(array_filter(
            $ordered,
            static fn (string $c): bool => ! in_array($c, ['pink_dil_paused_at', 'campaignBudgetCurrencyCode'], true)
        ));

        return $ordered;
    }

    /**
     * Suggested daily budget tier (1–12) from row ACOS, same breakpoints as {@see AmazonAcosSbgtRule}.
     * Uses `sales30d` when present, else `sales`. Spend prefers `spend`, else `cost`.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function computedSbgtFromReportRow(array $rowArr, array $dbColumns): ?int
    {
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

        $spend = 0.0;
        if (in_array('spend', $dbColumns, true) && array_key_exists('spend', $rowArr) && $rowArr['spend'] !== null && $rowArr['spend'] !== '') {
            $spend = (float) $rowArr['spend'];
        }
        if ($spend <= 0 && in_array('cost', $dbColumns, true) && array_key_exists('cost', $rowArr) && $rowArr['cost'] !== null && $rowArr['cost'] !== '') {
            $spend = (float) $rowArr['cost'];
        }

        $acos = 0.0;
        if ($spend > 0 && $sales > 0) {
            $acos = ($spend / $sales) * 100;
        } elseif ($spend > 0 && $sales <= 0) {
            $acos = 100.0;
        }

        return AmazonAcosSbgtRule::sbgtFromAcosL30($acos);
    }

    /**
     * ACOS (%) from the same row: (cost / sales) × 100. Sales prefers `sales30d`, else `sales`.
     * When cost > 0 and sales = 0, ACOS is defined as 100% (same convention as budget tooling).
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
     * SQL scalar for ORDER BY ACOS (cost / sales × 100), matching {@see computedAcosPercentFromReportRow}.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function sqlExpressionForAcosSort(array $dbColumns): ?string
    {
        if (! in_array('cost', $dbColumns, true)) {
            return null;
        }
        if (in_array('sales30d', $dbColumns, true) && in_array('sales', $dbColumns, true)) {
            return 'CASE WHEN COALESCE(sales30d, 0) > 0 THEN COALESCE(cost, 0) / sales30d * 100 WHEN COALESCE(sales, 0) > 0 THEN COALESCE(cost, 0) / sales * 100 WHEN COALESCE(cost, 0) > 0 THEN 100 ELSE 0 END';
        }
        if (in_array('sales30d', $dbColumns, true)) {
            return 'CASE WHEN COALESCE(sales30d, 0) > 0 THEN COALESCE(cost, 0) / sales30d * 100 WHEN COALESCE(cost, 0) > 0 THEN 100 ELSE 0 END';
        }
        if (in_array('sales', $dbColumns, true)) {
            return 'CASE WHEN COALESCE(sales, 0) > 0 THEN COALESCE(cost, 0) / sales * 100 WHEN COALESCE(cost, 0) > 0 THEN 100 ELSE 0 END';
        }

        return null;
    }

    /**
     * SQL scalar for ORDER BY SBGT tier input (spend-based ACOS %), aligned with {@see computedSbgtFromReportRow}.
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function sqlExpressionForSbgtSort(array $dbColumns): ?string
    {
        if (in_array('spend', $dbColumns, true) && in_array('cost', $dbColumns, true)) {
            $spendExpr = 'COALESCE(spend, cost, 0)';
        } elseif (in_array('spend', $dbColumns, true)) {
            $spendExpr = 'COALESCE(spend, 0)';
        } elseif (in_array('cost', $dbColumns, true)) {
            $spendExpr = 'COALESCE(cost, 0)';
        } else {
            return null;
        }
        if (in_array('sales30d', $dbColumns, true) && in_array('sales', $dbColumns, true)) {
            return 'CASE WHEN '.$spendExpr.' > 0 AND COALESCE(sales30d, 0) > 0 THEN '.$spendExpr.' / sales30d * 100 WHEN '.$spendExpr.' > 0 AND COALESCE(sales, 0) > 0 THEN '.$spendExpr.' / sales * 100 WHEN '.$spendExpr.' > 0 THEN 100 ELSE 0 END';
        }
        if (in_array('sales30d', $dbColumns, true)) {
            return 'CASE WHEN '.$spendExpr.' > 0 AND COALESCE(sales30d, 0) > 0 THEN '.$spendExpr.' / sales30d * 100 WHEN '.$spendExpr.' > 0 THEN 100 ELSE 0 END';
        }
        if (in_array('sales', $dbColumns, true)) {
            return 'CASE WHEN '.$spendExpr.' > 0 AND COALESCE(sales, 0) > 0 THEN '.$spendExpr.' / sales * 100 WHEN '.$spendExpr.' > 0 THEN 100 ELSE 0 END';
        }

        return null;
    }

    /**
     * Round displayed money / percent fields to whole numbers for JSON (cost, sales, L-spends, BGT, ACOS).
     *
     * @param  array<string, mixed>  $arr
     * @param  array<int, string>  $displayColumns
     */
    private static function roundAmazonAdsDisplayNumericFields(array &$arr, array $displayColumns): void
    {
        foreach (['cost', 'sales30d', 'sales', 'L7spend', 'L2spend', 'L1spend', 'bgt', 'ACOS'] as $key) {
            if (! in_array($key, $displayColumns, true) || ! array_key_exists($key, $arr)) {
                continue;
            }
            $v = $arr[$key];
            if ($v === null || $v === '') {
                continue;
            }
            if (! is_numeric($v)) {
                continue;
            }
            $n = (float) $v;
            if (! is_finite($n)) {
                continue;
            }
            $arr[$key] = (float) round($n, 0);
        }
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
        } elseif ($requested === 'Prchase' && in_array('purchases30d', $dbColumns, true)) {
            $query->orderBy('purchases30d', $dir === 'ASC' ? 'asc' : 'desc');
        } elseif ($requested === 'ACOS') {
            $expr = self::sqlExpressionForAcosSort($dbColumns);
            if ($expr !== null) {
                $query->orderByRaw('('.$expr.') '.$dir);
            } elseif (in_array('id', $dbColumns, true)) {
                $query->orderBy('id', 'desc');
            }
        } elseif ($requested === 'sbgt') {
            $expr = self::sqlExpressionForSbgtSort($dbColumns);
            if ($expr !== null) {
                $query->orderByRaw('('.$expr.') '.$dir);
            } elseif (in_array('id', $dbColumns, true)) {
                $query->orderBy('id', 'desc');
            }
        } elseif ($requested === 'sbid' && in_array('last_sbid', $dbColumns, true) && in_array('sbid', $dbColumns, true)) {
            $query->orderByRaw('COALESCE(last_sbid, sbid, 0) '.$dir);
        } elseif ($requested === 'cost' && ($l30Sub = self::correlatedL30DailySpendSumSubquerySql($table, $dbColumns)) !== null) {
            $query->orderByRaw('(('.$l30Sub.')) '.$dir);
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
     * Calendar day N days before the "CPC 1" anchor for this row (daily `report_date_range`, summary `L1`, or `date`).
     * N=1 → CPC 2; N=2 → CPC 3 (two days before CPC 1's day).
     *
     * @param  array<int, string>  $dbColumns
     */
    private static function calendarDayOffsetFromCpc1Anchor(array $rowArr, array $dbColumns, int $daysBefore): ?string
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
            // CPC 1 ≈ yesterday; CPC 2 = yesterday−1; CPC 3 = yesterday−2.
            return Carbon::now(config('app.timezone'))->subDays($daysBefore + 1)->format('Y-m-d');
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

        if (! in_array('costPerClick', $dbColumns, true)) {
            $cache[$key] = null;

            return null;
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
        $found = $q->first(['costPerClick']);
        $cpc = null;
        if ($found && isset($found->costPerClick)) {
            $n = (float) $found->costPerClick;
            $cpc = is_finite($n) && $n > 0 ? round($n, 4) : null;
        }
        $cache[$key] = $cpc;

        return $cpc;
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
     * @return array{U7: float|null, U2: float|null, U1: float|null}
     */
    private static function utilizationPercentValues(array $row): array
    {
        $spend = self::rowSpendForUtilization($row);
        $budget = self::rowBudgetForUtilization($row);
        if ($spend === null || $budget === null) {
            return ['U7' => null, 'U2' => null, 'U1' => null];
        }

        return [
            'U7' => ($spend / ($budget * 7)) * 100,
            'U2' => ($spend / ($budget * 2)) * 100,
            'U1' => ($spend / ($budget * 1)) * 100,
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
     * Grid SBID from U2%/U1% + L1/L2/L7 CPC (or costPerClick fallback), aligned with auto-update commands.
     * Outside red+red / pink+pink bands, sbid is forced to null so the UI shows "--".
     */
    private static function applyGridSbidFromUb2Ub1AndCpc(array &$arr, array $u, array $rowArr, array $dbColumns): void
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

    private static function sqlSpendExpressionForUtilFilters(array $cols): ?string
    {
        $hasSpend = in_array('spend', $cols, true);
        $hasCost = in_array('cost', $cols, true);
        if ($hasSpend && $hasCost) {
            return 'COALESCE(spend, cost)';
        }
        if ($hasSpend) {
            return 'spend';
        }
        if ($hasCost) {
            return 'cost';
        }

        return null;
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
     * Server-side filters for U7%/U2%/U1% buckets (SP/SB/SD campaign tables only; matches spend ÷ (budget × days) × 100).
     */
    private static function applyUtilizationPercentRangeFilters(Builder $query, string $table, Request $request): void
    {
        $cols = Schema::getColumnListing($table);
        if (! in_array('ad_type', $cols, true) || ! in_array('campaignBudgetAmount', $cols, true)) {
            return;
        }
        $spendExpr = self::sqlSpendExpressionForUtilFilters($cols);
        if ($spendExpr === null) {
            return;
        }
        $u7 = self::normalizeUtilRangeBucket($request->input('filter_u7'));
        $u2 = self::normalizeUtilRangeBucket($request->input('filter_u2'));
        $u1 = self::normalizeUtilRangeBucket($request->input('filter_u1'));
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
        ]);
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
        self::applyUtilizationPercentRangeFilters($query, $table, $request);
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

        $distinctCampaignCount = null;
        if (in_array('campaign_id', $dbColumns, true)) {
            $distinctCampaignCount = (int) DB::query()
                ->fromSub($query->clone(), 'r')
                ->selectRaw('COUNT(DISTINCT r.campaign_id) AS c')
                ->value('c');
        }

        self::applyRawDataOrder($query, $table, $dbColumns, $columns, $orderColumnIndex, $orderDir);

        $rows = $query->offset($start)
            ->limit($length)
            ->get();

        $hasLSpendCols = in_array('L7spend', $columns, true);
        $lSpendMap = $hasLSpendCols ? self::fetchL7L2L1SpendMap($table, $dbColumns, $rows) : [];
        $l30SpendMap = in_array('cost', $columns, true)
            ? self::fetchL30DailySpendSumMap($table, $dbColumns, $rows)
            : [];

        $hasUtilCols = in_array('U7%', $columns, true);
        $hasCpc2 = in_array('CPC2', $columns, true);
        $hasCpc3 = in_array('CPC3', $columns, true);
        $empty = array_fill_keys($columns, null);
        $cpcDayCache = [];
        $data = [];
        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $arr = array_merge($empty, $rowArr);
            $u = self::utilizationPercentValues($rowArr);
            if ($hasUtilCols) {
                $arr['U7%'] = self::formatUtilPercent($u['U7']);
                $arr['U2%'] = self::formatUtilPercent($u['U2']);
                $arr['U1%'] = self::formatUtilPercent($u['U1']);
            }
            $cid = isset($rowArr['campaign_id']) ? trim((string) $rowArr['campaign_id']) : '';
            $adType = in_array('ad_type', $dbColumns, true) ? ($rowArr['ad_type'] ?? null) : null;
            $adTypeStr = is_string($adType) ? $adType : null;
            if ($hasCpc3) {
                $day3 = self::calendarDayOffsetFromCpc1Anchor($rowArr, $dbColumns, 2);
                if ($day3 !== null && $cid !== '') {
                    $arr['CPC3'] = self::fetchCostPerClickOnReportDay($table, $dbColumns, $cid, $adTypeStr, $day3, $cpcDayCache);
                } else {
                    $arr['CPC3'] = null;
                }
            }
            if ($hasCpc2) {
                $day2 = self::calendarDayOffsetFromCpc1Anchor($rowArr, $dbColumns, 1);
                if ($day2 !== null && $cid !== '') {
                    $arr['CPC2'] = self::fetchCostPerClickOnReportDay($table, $dbColumns, $cid, $adTypeStr, $day2, $cpcDayCache);
                } else {
                    $arr['CPC2'] = null;
                }
            }
            self::applyGridSbidFromUb2Ub1AndCpc($arr, $u, $rowArr, $dbColumns);
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
            if (in_array('sbgt', $columns, true)) {
                $arr['sbgt'] = self::computedSbgtFromReportRow($rowArr, $dbColumns);
            }
            if (in_array('ACOS', $columns, true)) {
                $arr['ACOS'] = self::computedAcosPercentFromReportRow($rowArr, $dbColumns);
            }
            if (in_array('Prchase', $columns, true) && in_array('purchases30d', $dbColumns, true)) {
                $pv = $rowArr['purchases30d'] ?? null;
                if ($pv === null || $pv === '') {
                    $arr['Prchase'] = null;
                } else {
                    $pn = (float) $pv;
                    $arr['Prchase'] = is_finite($pn) ? (int) $pn : null;
                }
                unset($arr['purchases30d']);
            }
            if ($hasLSpendCols && $cid !== '') {
                $adKey = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lk = $cid."\0".trim((string) $adKey);
                $slice = $lSpendMap[$lk] ?? ['L7' => null, 'L2' => null, 'L1' => null];
                $arr['L7spend'] = $slice['L7'];
                $arr['L2spend'] = $slice['L2'];
                $arr['L1spend'] = $slice['L1'];
            }
            if (in_array('cost', $columns, true) && $cid !== '' && $l30SpendMap !== []) {
                $adKeyL30 = in_array('ad_type', $dbColumns, true) ? ($adTypeStr ?? '') : '';
                $lkL30 = $cid."\0".trim((string) $adKeyL30);
                if (array_key_exists($lkL30, $l30SpendMap)) {
                    $l30v = $l30SpendMap[$lkL30];
                    if ($l30v !== null && is_finite($l30v)) {
                        $arr['cost'] = $l30v;
                    }
                }
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
}
