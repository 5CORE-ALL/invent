<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Support\GoogleShoppingCampaignsRawRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class GoogleAdsCampaignsRawController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Tabular view of google_ads_campaigns rows only (no SKU matching or transforms).
     */
    public function index()
    {
        return view('campaign.google-ads-campaigns-raw', [
            'gshoppingRawRule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
        ]);
    }

    public function getRawRule(): JsonResponse
    {
        return response()->json([
            'rule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
        ]);
    }

    public function saveRawRule(Request $request): JsonResponse
    {
        try {
            $normalized = GoogleShoppingCampaignsRawRule::normalizeRule($request->all());
            GoogleShoppingCampaignsRawRule::persistRule($normalized);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save G-Shopping raw rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'Rule saved. Refresh the grid to apply SBGT and SBID.',
            'rule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
            'status' => 200,
        ]);
    }

    /**
     * Run `budget:update-shopping` — writes daily Shopping budgets from the persisted SBGT rule (same as cron).
     * Request JSON: `{ "campaign_ids": ["…"] }` — when sent from the raw grid, only those SHOPPING campaigns are updated (max 1000).
     */
    public function pushSbgtShoppingBudgets(Request $request): JsonResponse
    {
        $ids = $this->validatedPushCampaignIds($request);
        if ($ids === []) {
            return $this->pushCampaignIdsMissingResponse('budget:update-shopping');
        }

        return $this->runArtisanPush(
            'budget:update-shopping',
            ['--campaign-ids' => implode(',', $ids)],
            'budget:update-shopping'
        );
    }

    /**
     * Run `sbid:update` — updates Shopping campaign SBIDs from the persisted SBID rule (same as cron).
     * Request JSON: `{ "campaign_ids": ["…"] }` — when sent from the raw grid, only those campaigns are considered (max 1000).
     */
    public function pushSbidShopping(Request $request): JsonResponse
    {
        $ids = $this->validatedPushCampaignIds($request);
        if ($ids === []) {
            return $this->pushCampaignIdsMissingResponse('sbid:update');
        }

        return $this->runArtisanPush(
            'sbid:update',
            ['--campaign-ids' => implode(',', $ids)],
            'sbid:update'
        );
    }

    /**
     * @return list<string>
     */
    private function validatedPushCampaignIds(Request $request): array
    {
        $raw = $request->input('campaign_ids');
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            if (! is_scalar($id)) {
                continue;
            }
            $d = preg_replace('/\D/', '', (string) $id);
            if ($d !== '' && strlen($d) <= 32) {
                $out[$d] = true;
            }
            if (count($out) >= 1000) {
                break;
            }
        }

        return array_keys($out);
    }

    private function pushCampaignIdsMissingResponse(string $command): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'exit_code' => 1,
            'command' => $command,
            'message' => 'No campaign_ids to process. Load a page with data, or select rows with the checkboxes.',
            'output' => '',
        ], 422);
    }

    /**
     * @param  array<string, bool|string>  $options
     */
    private function runArtisanPush(string $command, array $options, string $labelForLog): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        try {
            $exitCode = Artisan::call($command, $options);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => $labelForLog,
                'message' => $e->getMessage(),
                'output' => '',
            ], 500);
        }

        $output = trim(Artisan::output());
        $max = 16000;
        if (strlen($output) > $max) {
            $output = substr($output, 0, $max)."\n… (truncated)";
        }

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'command' => $labelForLog,
            'message' => $exitCode === 0 ? 'Command finished.' : 'Command exited with a non-zero code.',
            'output' => $output,
        ], $exitCode === 0 ? 200 : 422);
    }

    /**
     * Paginated JSON for Tabulator — one row per campaign_id in the date window.
     * Spend = SUM(metrics_cost_micros) / 1e6 for the 30d window; l7/l2/l1_spend = trailing N inclusive days to max date.
     * Adds utilized-style metrics (CPC L30/L7/L2/L1, L30 sales, ACOS, UB%, BGT, SBGT, SBID) using the same formulas as
     * `/google/shopping/utilized`, anchored to this page’s max-date window (not calendar “yesterday”).
     * Other columns from the latest `date` row in the 30d window.
     * Column "spend" (dollars) is ordered immediately after campaign_name for the grid.
     */
    public function data(Request $request)
    {
        $perPage = (int) $request->input('size', 100);
        $perPage = max(10, min(1000, $perPage));
        $page = max(1, (int) $request->input('page', 1));

        $query = $this->buildRawGridBaseQuery();
        $this->applyRawGridDataFilters($query, $request);

        $summaryQuery = clone $query;
        $paginator = $query->orderByDesc('g.id')->paginate($perPage, ['*'], 'page', $page);
        $summary = $this->computeRawGridSummary($summaryQuery);

        $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();

        $rows = $paginator->getCollection()->map(function ($row) use ($rawRule) {
            $arr = json_decode(json_encode($row), true);
            if (isset($arr['spend_window_micros'])) {
                $arr['metrics_cost_micros'] = (int) $arr['spend_window_micros'];
                unset($arr['spend_window_micros']);
            }

            self::enrichRawRowGoogleShoppingStyle($arr, $rawRule);

            return self::prepareRawRowForTabulator($arr);
        })->values();

        $total = (int) $paginator->total();
        $lastPage = max(1, (int) $paginator->lastPage());

        return response()->json([
            'last_page' => $lastPage,
            'last_row' => $total,
            'data' => $rows,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    /**
     * Row counts by U7% band for the current filters (same as the grid except the U7% filter is ignored).
     */
    public function u7Distribution(Request $request): JsonResponse
    {
        $empty = [
            'ok' => false,
            'buckets' => ['lt66' => 0, '66_99' => 0, 'gt99' => 0, 'na' => 0],
            'total' => 0,
        ];

        try {
            $query = $this->buildRawGridBaseQuery();
            $this->applyRawGridDataFilters($query, $request, false);
            $out = $this->aggregateUb7BucketsFromFilteredQuery($query);
        } catch (\Throwable $e) {
            report($e);

            return response()->json($empty + ['reason' => 'query_error'], 500);
        }

        return response()->json([
            'ok' => true,
            'buckets' => $out['buckets'],
            'total' => $out['total'],
        ]);
    }

    /**
     * Per-calendar-day U7% bucket row counts for the last N days (default 30). Each day re-anchors the 30d / L7 / L2 / L1
     * windows to end on that calendar date (parity with Amazon’s per-day re-query, adapted to this page’s window model).
     * Respects U2/U1/Status filters; ignores the U7 filter.
     */
    public function u7DistributionHistory(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 90) {
            $days = 90;
        }

        $tz = config('app.timezone');
        $bucketKey = $this->normalizeU7HistoryBucketKey($request->input('bucket'));
        $daysOut = [];

        try {
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = Carbon::now($tz)->subDays($i)->format('Y-m-d');
                $q = $this->buildRawGridBaseQuery($d);
                $this->applyRawGridDataFilters($q, $request, false);
                $agg = $this->aggregateUb7BucketsFromFilteredQuery($q);
                $row = [
                    'date' => $d,
                    'lt66' => $agg['buckets']['lt66'],
                    '66_99' => $agg['buckets']['66_99'],
                    'gt99' => $agg['buckets']['gt99'],
                    'na' => $agg['buckets']['na'],
                    'total' => $agg['total'],
                ];
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
        ]);
    }

    /**
     * One row per campaign for the raw grid (before U7/U2/U1/Sts filters). Optional $forcedEndYmd (Y-m-d) anchors windows for history.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildRawGridBaseQuery(?string $forcedEndYmd = null)
    {
        $bounds = $this->rawGridDateBoundaries($forcedEndYmd);
        $l7Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 7);
        $l2Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 2);
        $l1Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 1);

        $applyBounds = static function ($q) use ($bounds) {
            if ($bounds !== null) {
                $q->whereNotNull('date')
                    ->whereBetween('date', [$bounds['start'], $bounds['end']]);
            }
        };

        $windowApplier = static function (?array $w) {
            return static function ($q) use ($w) {
                if ($w !== null) {
                    $q->whereNotNull('date')
                        ->whereBetween('date', [$w['start'], $w['end']]);
                }
            };
        };
        $applyL7Bounds = $windowApplier($l7Bounds);
        $applyL2Bounds = $windowApplier($l2Bounds);
        $applyL1Bounds = $windowApplier($l1Bounds);

        $sumSub = DB::table('google_ads_campaigns');
        $applyBounds($sumSub);
        $sumSub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_cost_micros) as sum_micros')
            ->groupBy('campaign_id');

        $sumL7Sub = DB::table('google_ads_campaigns');
        $applyL7Bounds($sumL7Sub);
        $sumL7Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_cost_micros) as sum_micros_l7')
            ->groupBy('campaign_id');

        $sumL2Sub = DB::table('google_ads_campaigns');
        $applyL2Bounds($sumL2Sub);
        $sumL2Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_cost_micros) as sum_micros_l2')
            ->groupBy('campaign_id');

        $sumL1Sub = DB::table('google_ads_campaigns');
        $applyL1Bounds($sumL1Sub);
        $sumL1Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_cost_micros) as sum_micros_l1')
            ->groupBy('campaign_id');

        $clicks30Sub = DB::table('google_ads_campaigns');
        $applyBounds($clicks30Sub);
        $clicks30Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_clicks) as sum_clicks_30')
            ->groupBy('campaign_id');

        $clicksL7Sub = DB::table('google_ads_campaigns');
        $applyL7Bounds($clicksL7Sub);
        $clicksL7Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_clicks) as sum_clicks_l7')
            ->groupBy('campaign_id');

        $clicksL2Sub = DB::table('google_ads_campaigns');
        $applyL2Bounds($clicksL2Sub);
        $clicksL2Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_clicks) as sum_clicks_l2')
            ->groupBy('campaign_id');

        $clicksL1Sub = DB::table('google_ads_campaigns');
        $applyL1Bounds($clicksL1Sub);
        $clicksL1Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_clicks) as sum_clicks_l1')
            ->groupBy('campaign_id');

        $ga30Sub = DB::table('google_ads_campaigns');
        $applyBounds($ga30Sub);
        $ga30Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(ga4_actual_revenue) as sum_ga4_actual, SUM(ga4_ad_sales) as sum_ga4_ads')
            ->groupBy('campaign_id');

        $latestSub = DB::table('google_ads_campaigns');
        $applyBounds($latestSub);
        $latestSub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, MAX(`date`) as max_d')
            ->groupBy('campaign_id');

        $query = DB::table('google_ads_campaigns as g')
            ->joinSub($sumSub, 'cSpend', function ($join) {
                $join->on('g.campaign_id', '=', 'cSpend.campaign_id');
            })
            ->leftJoinSub($sumL7Sub, 'cSpendL7', function ($join) {
                $join->on('g.campaign_id', '=', 'cSpendL7.campaign_id');
            })
            ->leftJoinSub($sumL2Sub, 'cSpendL2', function ($join) {
                $join->on('g.campaign_id', '=', 'cSpendL2.campaign_id');
            })
            ->leftJoinSub($sumL1Sub, 'cSpendL1', function ($join) {
                $join->on('g.campaign_id', '=', 'cSpendL1.campaign_id');
            })
            ->joinSub($clicks30Sub, 'cClicks30', function ($join) {
                $join->on('g.campaign_id', '=', 'cClicks30.campaign_id');
            })
            ->leftJoinSub($clicksL7Sub, 'cClicksL7', function ($join) {
                $join->on('g.campaign_id', '=', 'cClicksL7.campaign_id');
            })
            ->leftJoinSub($clicksL2Sub, 'cClicksL2', function ($join) {
                $join->on('g.campaign_id', '=', 'cClicksL2.campaign_id');
            })
            ->leftJoinSub($clicksL1Sub, 'cClicksL1', function ($join) {
                $join->on('g.campaign_id', '=', 'cClicksL1.campaign_id');
            })
            ->leftJoinSub($ga30Sub, 'cGa30', function ($join) {
                $join->on('g.campaign_id', '=', 'cGa30.campaign_id');
            })
            ->joinSub($latestSub, 'cLatest', function ($join) {
                $join->on('g.campaign_id', '=', 'cLatest.campaign_id')
                    ->on('g.date', '=', 'cLatest.max_d');
            })
            ->whereNotNull('g.campaign_id');
        $applyBounds($query);

        $query->select('g.*')
            ->addSelect(DB::raw('cSpend.sum_micros as spend_window_micros'))
            ->addSelect(DB::raw('cSpend.sum_micros / 1000000 as spend'))
            ->addSelect(DB::raw('COALESCE(cSpendL7.sum_micros_l7, 0) / 1000000 as l7_spend'))
            ->addSelect(DB::raw('COALESCE(cSpendL2.sum_micros_l2, 0) / 1000000 as l2_spend'))
            ->addSelect(DB::raw('COALESCE(cSpendL1.sum_micros_l1, 0) / 1000000 as l1_spend'))
            ->addSelect(DB::raw('COALESCE(cClicks30.sum_clicks_30, 0) as clicks_sum_30'))
            ->addSelect(DB::raw('COALESCE(cClicksL7.sum_clicks_l7, 0) as clicks_sum_l7'))
            ->addSelect(DB::raw('COALESCE(cClicksL2.sum_clicks_l2, 0) as clicks_sum_l2'))
            ->addSelect(DB::raw('COALESCE(cClicksL1.sum_clicks_l1, 0) as clicks_sum_l1'))
            ->addSelect(DB::raw('COALESCE(cGa30.sum_ga4_actual, 0) as sum_ga4_actual'))
            ->addSelect(DB::raw('COALESCE(cGa30.sum_ga4_ads, 0) as sum_ga4_ads'))
            ->addSelect(DB::raw('(CASE WHEN COALESCE(cGa30.sum_ga4_actual, 0) > 0 THEN COALESCE(cGa30.sum_ga4_actual, 0) ELSE COALESCE(cGa30.sum_ga4_ads, 0) END) as sales_l30_agg'));

        return $query;
    }

    /**
     * UB% color bands match the raw grid formatters: green 66–99%, pink &gt;99%, red &lt;66%.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyRawGridDataFilters($query, Request $request, bool $includeUb7 = true): void
    {
        $ub7 = $this->normalizeUbColorFilter($request->input('filter_ub7'));
        $ub2 = $this->normalizeUbColorFilter($request->input('filter_ub2'));
        $ub1 = $this->normalizeUbColorFilter($request->input('filter_ub1'));
        $stat = $this->normalizeStatFilter($request->input('filter_stat'));

        $ub7Expr = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(cSpendL7.sum_micros_l7, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';
        $ub2Expr = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(cSpendL2.sum_micros_l2, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 2.0) * 100.0 ELSE 0 END)';
        $ub1Expr = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(cSpendL1.sum_micros_l1, 0) / 1000000.0) / (g.budget_amount_micros / 1000000.0) * 100.0 ELSE 0 END)';

        if ($includeUb7) {
            $this->whereUbColorBand($query, $ub7Expr, $ub7);
        }
        $this->whereUbColorBand($query, $ub2Expr, $ub2);
        $this->whereUbColorBand($query, $ub1Expr, $ub1);

        if ($stat === 'ENABLED') {
            $query->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) = ?', ['ENABLED']);
        } elseif ($stat === 'NOT_ENABLED') {
            // Every status except ENABLED (includes PAUSED, REMOVED, UNKNOWN, etc.)
            $query->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) <> ?', ['ENABLED']);
        } elseif ($stat !== 'all' && $stat !== '') {
            $query->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) = ?', [strtoupper($stat)]);
        }
    }

    private function normalizeUbColorFilter(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';

        return in_array($v, ['green', 'pink', 'red'], true) ? $v : 'all';
    }

    private function normalizeStatFilter(mixed $value): string
    {
        if (! is_string($value)) {
            return 'all';
        }
        $v = strtoupper(trim($value));
        if ($v === '' || $v === 'ALL') {
            return 'all';
        }
        if ($v === 'NOT_ENABLED') {
            return 'NOT_ENABLED';
        }
        if (in_array($v, ['ENABLED', 'PAUSED', 'REMOVED'], true)) {
            return $v;
        }

        return 'all';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function whereUbColorBand($query, string $ubSqlExpr, string $band): void
    {
        if ($band === 'all') {
            return;
        }
        if ($band === 'green') {
            $query->whereRaw("({$ubSqlExpr}) >= 66 AND ({$ubSqlExpr}) <= 99");

            return;
        }
        if ($band === 'pink') {
            $query->whereRaw("({$ubSqlExpr}) > 99");

            return;
        }
        if ($band === 'red') {
            $query->whereRaw("({$ubSqlExpr}) < 66");
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return array{buckets: array{lt66: int, 66_99: int, gt99: int, na: int}, total: int}
     */
    private function aggregateUb7BucketsFromFilteredQuery($query): array
    {
        $sub = clone $query;
        $sub->reorder();

        $u7 = '(CASE WHEN COALESCE(sq.budget_amount_micros, 0) > 0 THEN sq.l7_spend / ((sq.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';
        $bucket = "(CASE WHEN COALESCE(sq.budget_amount_micros, 0) <= 0 THEN 'na' WHEN ({$u7}) < 66 THEN 'lt66' WHEN ({$u7}) <= 99 THEN '66_99' ELSE 'gt99' END)";

        $outer = DB::query()->fromSub($sub, 'sq');
        $row = $outer->selectRaw(
            'SUM(CASE WHEN ('.$bucket.') = \'lt66\' THEN 1 ELSE 0 END) as c_lt66,'.
            'SUM(CASE WHEN ('.$bucket.') = \'66_99\' THEN 1 ELSE 0 END) as c_mid,'.
            'SUM(CASE WHEN ('.$bucket.') = \'gt99\' THEN 1 ELSE 0 END) as c_gt,'.
            'SUM(CASE WHEN ('.$bucket.') = \'na\' THEN 1 ELSE 0 END) as c_na,'.
            'COUNT(*) as c_tot'
        )->first();

        $lt66 = (int) ($row->c_lt66 ?? 0);
        $mid = (int) ($row->c_mid ?? 0);
        $gt = (int) ($row->c_gt ?? 0);
        $na = (int) ($row->c_na ?? 0);
        $total = (int) ($row->c_tot ?? 0);

        return [
            'buckets' => [
                'lt66' => $lt66,
                '66_99' => $mid,
                'gt99' => $gt,
                'na' => $na,
            ],
            'total' => $total,
        ];
    }

    private function normalizeU7HistoryBucketKey(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $k = trim((string) $raw);

        return in_array($k, ['lt66', '66_99', 'gt99', 'na'], true) ? $k : null;
    }

    /**
     * Weighted totals over the full filtered set (not just the current page).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $summaryQuery
     * @return array{spi30: float|null, acos_pct: int|null, filtered_row_count: int}
     */
    private function computeRawGridSummary($summaryQuery): array
    {
        try {
            $sql = $summaryQuery->toSql();
            $bindings = $summaryQuery->getBindings();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS row_count, COALESCE(SUM(subq.spend), 0) AS sum_spend, COALESCE(SUM(subq.sales_l30_agg), 0) AS sum_sales FROM ('.$sql.') AS subq',
                $bindings
            );
        } catch (\Throwable) {
            return [
                'spi30' => null,
                'acos_pct' => null,
                'filtered_row_count' => 0,
            ];
        }

        $sumSales = (float) ($row->sum_sales ?? 0);
        $sumSpend = (float) ($row->sum_spend ?? 0);
        $acos = 0.0;
        if ($sumSales >= 1.0) {
            $acos = ($sumSpend / $sumSales) * 100.0;
        } elseif ($sumSpend > 0) {
            $acos = 100.0;
        }

        return [
            'spi30' => round($sumSales, 2),
            'acos_pct' => (int) round($acos),
            'filtered_row_count' => (int) ($row->row_count ?? 0),
        ];
    }

    /**
     * 30 inclusive calendar days: default end = latest non-null `date` in the table; optional $forcedEndYmd (Y-m-d) for history.
     * If nothing has a date and no forced end, returns null (no filter — whole table).
     *
     * @return array{start: string, end: string}|null
     */
    private function rawGridDateBoundaries(?string $forcedEndYmd = null): ?array
    {
        if ($forcedEndYmd !== null && trim($forcedEndYmd) !== '') {
            $end = Carbon::parse($forcedEndYmd)->startOfDay();
            $start = $end->copy()->subDays(29);

            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ];
        }

        $maxDateStr = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');
        if ($maxDateStr === null || $maxDateStr === '') {
            return null;
        }

        $end = Carbon::parse($maxDateStr)->startOfDay();
        $start = $end->copy()->subDays(29);

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    /**
     * CPC / ACOS / UB% / SBGT / SBID (SBGT/SBID bands from {@see GoogleShoppingCampaignsRawRule}).
     * Uses raw grid date anchor (max(date) in table) and trailing L7/L2/L1 windows as spend columns.
     *
     * @param  array{sbgt: array<string, float|int>, sbid: array<string, float>}  $rawRule
     */
    private static function enrichRawRowGoogleShoppingStyle(array &$arr, array $rawRule): void
    {
        $spend = (float) ($arr['spend'] ?? 0);
        $l7Spend = (float) ($arr['l7_spend'] ?? 0);
        $l2Spend = (float) ($arr['l2_spend'] ?? 0);
        $l1Spend = (float) ($arr['l1_spend'] ?? 0);

        $clicks30 = (int) ($arr['clicks_sum_30'] ?? 0);
        $clicksL7 = (int) ($arr['clicks_sum_l7'] ?? 0);
        $clicksL2 = (int) ($arr['clicks_sum_l2'] ?? 0);
        $clicksL1 = (int) ($arr['clicks_sum_l1'] ?? 0);
        unset($arr['clicks_sum_30'], $arr['clicks_sum_l7'], $arr['clicks_sum_l2'], $arr['clicks_sum_l1']);

        $arr['cpc_L30'] = $clicks30 > 0 ? round($spend / $clicks30, 6) : 0.0;
        $arr['cpc_L7'] = $clicksL7 > 0 ? round($l7Spend / $clicksL7, 6) : 0.0;
        $arr['cpc_L2'] = $clicksL2 > 0 ? round($l2Spend / $clicksL2, 6) : 0.0;
        $arr['cpc_L1'] = $clicksL1 > 0 ? round($l1Spend / $clicksL1, 6) : 0.0;

        $sumActual = (float) ($arr['sum_ga4_actual'] ?? 0);
        $sumAds = (float) ($arr['sum_ga4_ads'] ?? 0);
        unset($arr['sum_ga4_actual'], $arr['sum_ga4_ads']);

        $salesL30 = $sumActual > 0 ? $sumActual : $sumAds;
        $arr['ad_sales_L30'] = $salesL30;

        $spendR = (int) round($spend);
        $salesR = (int) round($salesL30);
        $acos = 0.0;
        if ($salesR >= 1) {
            $acos = ($spendR / $salesR) * 100.0;
        } elseif ($spendR > 0) {
            $acos = 100.0;
        }
        $arr['acos_l30'] = $acos;

        $arr['sbgt'] = GoogleShoppingCampaignsRawRule::sbgtFromAcos($acos, $rawRule);

        $bgt = 0.0;
        if (! empty($arr['budget_amount_micros'])) {
            $bgt = (float) $arr['budget_amount_micros'] / 1000000.0;
        }
        $arr['bgt'] = $bgt;

        $arr['ub7'] = $bgt > 0 ? ($l7Spend / ($bgt * 7.0)) * 100.0 : 0.0;
        $arr['ub2'] = $bgt > 0 ? ($l2Spend / ($bgt * 2.0)) * 100.0 : 0.0;
        $arr['ub1'] = $bgt > 0 ? ($l1Spend / $bgt) * 100.0 : 0.0;

        $cpcL1 = (float) $arr['cpc_L1'];
        $cpcL7 = (float) $arr['cpc_L7'];
        $ub7 = (float) $arr['ub7'];
        $ub1 = (float) $arr['ub1'];

        $arr['sbid'] = GoogleShoppingCampaignsRawRule::sbidFromUb7Ub1Cpc($ub7, $ub1, $cpcL1, $cpcL7, $rawRule);
    }

    /**
     * Last N inclusive calendar days ending at the same `end` as the 30d grid (max date in table).
     * When $bounds30 is null (no dates in table), returns null so the sum uses the same unbounded semantics as Spend.
     *
     * @param  array{start: string, end: string}|null  $bounds30
     * @return array{start: string, end: string}|null
     */
    private function rawTrailingInclusiveDayBounds(?array $bounds30, int $inclusiveDays): ?array
    {
        if ($bounds30 === null || $inclusiveDays < 1) {
            return null;
        }

        $end = Carbon::parse($bounds30['end'])->startOfDay();
        $start = $end->copy()->subDays($inclusiveDays - 1);

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    /**
     * Key order for Tabulator autoColumns. Only keys in rawTabulatorColumnPriority() are emitted
     * (everything after SBID is omitted from the grid payload).
     */
    private static function prepareRawRowForTabulator(array $row): array
    {
        $ordered = [];
        foreach (self::rawTabulatorColumnPriority() as $key) {
            if (array_key_exists($key, $row)) {
                $ordered[$key] = $row[$key];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function rawTabulatorColumnPriority(): array
    {
        return [
            'id',
            'date',
            'campaign_id',
            'campaign_status',
            'campaign_name',
            'spend',
            'l7_spend',
            'l2_spend',
            'l1_spend',
            'metrics_clicks',
            'cpc_L30',
            'cpc_L7',
            'cpc_L2',
            'cpc_L1',
            'ad_sales_L30',
            'acos_l30',
            'ub7',
            'ub2',
            'ub1',
            'bgt',
            'sbgt',
            'sbid',
        ];
    }
}
