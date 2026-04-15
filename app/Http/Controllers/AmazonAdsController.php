<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
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

    /**
     * Campaign report tables: daily rows store the calendar day in `report_date_range` (YYYY-MM-DD).
     * Summary rows use labels like L30, L7. Optional `date` column is a fallback when set.
     * Other tables: `created_at` when no report_date_range.
     */
    private static function applyDateFilters(Builder $query, string $table, Request $request): void
    {
        $cols = Schema::getColumnListing($table);
        $hasReportRange = in_array('report_date_range', $cols, true);

        $summaryRange = self::normalizeSummaryReportRange($request->input('summary_report_range'));
        if ($summaryRange !== null && $hasReportRange) {
            $query->where('report_date_range', $summaryRange);

            return;
        }

        $from = self::normalizeDateInput((string) $request->input('date_from'));
        $to = self::normalizeDateInput((string) $request->input('date_to'));
        if ($from === null && $to === null) {
            return;
        }

        if ($hasReportRange) {
            $fromBound = $from ?? '1970-01-01';
            $toBound = $to ?? '9999-12-31';

            $query->where(function (Builder $outer) use ($from, $to, $fromBound, $toBound, $cols) {
                // 1) Daily rows: report_date_range begins with YYYY-MM-DD (10 chars), optional extra text after
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

                // 2) Fallback: explicit date column when populated
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

                // 3) Summary rows (L30, L7, …): overlap user range with startDate/endDate from the report
                if (in_array('startDate', $cols, true) && in_array('endDate', $cols, true)) {
                    $outer->orWhere(function (Builder $q) use ($fromBound, $toBound) {
                        $q->whereRaw('(CHAR_LENGTH(TRIM(report_date_range)) < 10 OR LEFT(TRIM(report_date_range), 10) NOT REGEXP ?)', ['^[0-9]{4}-[0-9]{2}-[0-9]{2}$'])
                            ->whereNotNull('startDate')
                            ->whereNotNull('endDate')
                            ->whereDate('startDate', '<=', $toBound)
                            ->whereDate('endDate', '>=', $fromBound);
                    });
                }
            });

            return;
        }

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
    }

    public function index()
    {
        $rawSources = [];
        foreach (self::RAW_TABLE_SOURCES as $param => $table) {
            $rawSources[$param] = [
                'table' => $table,
                'columns' => self::orderedColumnsForTable($table),
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

        $columns = self::orderedColumnsForTable($table);
        if ($columns === []) {
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
        $orderColumn = $columns[$orderColumnIndex];

        $recordsTotal = (int) DB::table($table)->count();

        $query = DB::table($table);
        self::applyDateFilters($query, $table, $request);

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $term = '%'.$escaped.'%';
            $query->where(function ($q) use ($columns, $term) {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'LIKE', $term);
                }
            });
        }

        $recordsFiltered = (int) $query->clone()->count();

        $query->orderBy($orderColumn, $orderDir);
        if ($orderColumn !== 'id' && in_array('id', $columns, true)) {
            $query->orderBy('id', 'desc');
        }

        $rows = $query->offset($start)
            ->limit($length)
            ->get();

        $empty = array_fill_keys($columns, null);
        $data = [];
        foreach ($rows as $row) {
            $data[] = array_merge($empty, (array) $row);
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
