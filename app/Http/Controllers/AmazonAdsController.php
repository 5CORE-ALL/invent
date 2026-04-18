<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
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
     * Column order: id first (newest-first default), then key + bid columns (yes_sbid, last_sbid, sbid_m), then the rest.
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
     * Columns sent to the Amazon Ads All DataTables, including computed utilization % after `ad_type`.
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
        array_splice($ordered, $idx + 1, 0, ['U7%', 'U2%', 'U1%']);

        return $ordered;
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

    private static function rowLastSbidNumeric(array $row): ?float
    {
        if (! array_key_exists('last_sbid', $row)) {
            return null;
        }
        $v = $row['last_sbid'];
        if ($v === null || $v === '') {
            return null;
        }
        $n = (float) $v;
        if (! is_finite($n) || $n <= 0) {
            return null;
        }

        return $n;
    }

    /** Parse last_sbid as float when present (includes 0 for low-util rule). */
    private static function rowLastSbidFloatOrNull(array $row): ?float
    {
        if (! array_key_exists('last_sbid', $row)) {
            return null;
        }
        $v = $row['last_sbid'];
        if ($v === null || $v === '') {
            return null;
        }
        $n = (float) $v;

        return is_finite($n) ? $n : null;
    }

    /**
     * Override sbid from utilization vs last_sbid:
     * - Both U2% and U1% (raw) > 99%: sbid = last_sbid × 0.90 (requires last_sbid > 0).
     * - Both U2% and U1% (raw) < 66%: sbid = last_sbid × 1.10, or 0.75 when last_sbid is null or 0.
     */
    private static function maybeApplyComputedSbidFromUtilization(array &$arr, array $u, array $rowArr, array $dbColumns): void
    {
        if (! in_array('sbid', $dbColumns, true) || ! in_array('last_sbid', $dbColumns, true)) {
            return;
        }
        $u2 = $u['U2'];
        $u1 = $u['U1'];
        if ($u2 === null || $u1 === null) {
            return;
        }
        if ($u2 > 99 && $u1 > 99) {
            $last = self::rowLastSbidNumeric($rowArr);
            if ($last === null) {
                return;
            }
            $arr['sbid'] = round($last * 0.90, 2);

            return;
        }
        if ($u2 < 66 && $u1 < 66) {
            $last = self::rowLastSbidFloatOrNull($rowArr);
            if ($last === null || $last <= 0) {
                $arr['sbid'] = 0.75;
            } else {
                $arr['sbid'] = round($last * 1.10, 2);
            }
        }
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
     * Exclude aggregate summary labels (L7, L30, L1, …) stored in report_date_range.
     * Used when filtering by calendar dates so only daily-keyed rows remain.
     */
    private static function excludeAggregateReportDateRangeLabels(Builder $query): void
    {
        $query->whereRaw("(TRIM(UPPER(COALESCE(report_date_range, ''))) NOT REGEXP '^L[0-9]+$')");
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
     * Campaign report tables: daily rows store the calendar day in `report_date_range` (YYYY-MM-DD).
     * Summary rows use labels like L30, L7. L1 is treated as "yesterday" (calendar), intersected with Date from/to when set.
     * Other tables: `created_at` / `date` when no report_date_range.
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
            self::applyReportRangeCalendarOverlap($query, $cols, $effFrom, $effTo, false);
            self::excludeAggregateReportDateRangeLabels($query);

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
        self::applyReportRangeCalendarOverlap($query, $cols, $from, $to, false);
        self::excludeAggregateReportDateRangeLabels($query);
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

    public function index()
    {
        $rawSources = [];
        foreach (self::RAW_TABLE_SOURCES as $param => $table) {
            $rawSources[$param] = [
                'table' => $table,
                'columns' => self::displayColumnsForTable($table),
            ];
        }

        return view('amazon_ads.all', [
            'rawSources' => $rawSources,
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
        if ($orderColumnIndex < 0 || $orderColumnIndex >= count($columns)) {
            $orderColumnIndex = 0;
        }
        $orderColumnRequested = $columns[$orderColumnIndex];
        $orderColumn = in_array($orderColumnRequested, $dbColumns, true)
            ? $orderColumnRequested
            : (in_array('id', $dbColumns, true) ? 'id' : $dbColumns[0]);

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

        $query->orderBy($orderColumn, $orderDir);
        if ($orderColumn !== 'id' && in_array('id', $dbColumns, true)) {
            $query->orderBy('id', 'desc');
        }

        $rows = $query->offset($start)
            ->limit($length)
            ->get();

        $hasUtilCols = in_array('U7%', $columns, true);
        $empty = array_fill_keys($columns, null);
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
            self::maybeApplyComputedSbidFromUtilization($arr, $u, $rowArr, $dbColumns);
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
}
