<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror All Issues (`dispatch_issue_issues`) rows onto the department boards
 * that still use their own tables (Listing, Label, QC PKG, C-care, Other).
 */
class CustomerCareIssueFanout
{
    /** @var array<string, list<string>> */
    private static array $columnCache = [];

    private const SOURCE_TABLE = 'dispatch_issue_issues';

    private const DEPT_TO_ISSUES = [
        'Listing' => 'listing_issue_issues',
        'Label' => 'label_issue_issues',
        'QC' => 'qc_and_packing_issues',
        'Packaging' => 'qc_and_packing_issues',
        'Customer Care' => 'c_care_issue_issues',
        'Other' => 'other_issue_issues',
    ];

    private const ISSUES_TO_HISTORY = [
        'listing_issue_issues' => 'listing_issue_issue_histories',
        'label_issue_issues' => 'label_issue_issue_histories',
        'qc_and_packing_issues' => 'qc_and_packing_issue_histories',
        'c_care_issue_issues' => 'c_care_issue_issue_histories',
        'other_issue_issues' => 'other_issue_issue_histories',
    ];

    /**
     * @return list<string>
     */
    public static function departmentsForTable(string $issuesTable): array
    {
        $out = [];
        foreach (self::DEPT_TO_ISSUES as $dept => $table) {
            if ($table === $issuesTable) {
                $out[] = $dept;
            }
        }

        return array_values(array_unique($out));
    }

    public static function syncFromDispatchId(int $dispatchId): void
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return;
        }

        $row = DB::table(self::SOURCE_TABLE)->where('id', $dispatchId)->first();
        if (! $row) {
            self::deleteCopies($dispatchId);

            return;
        }

        $depts = CustomerCareDepartments::decode($row->department ?? null);
        $wantedTables = [];
        foreach ($depts as $dept) {
            $canonical = CustomerCareDepartments::canonicalDepartment($dept);
            $table = self::DEPT_TO_ISSUES[$canonical] ?? self::DEPT_TO_ISSUES[$dept] ?? null;
            if ($table) {
                $wantedTables[$table] = true;
                self::upsertCopy($row, $table);
            }
        }

        foreach (array_keys(self::ISSUES_TO_HISTORY) as $table) {
            if (! isset($wantedTables[$table])) {
                self::archiveCopy($dispatchId, $table);
            }
        }
    }

    /**
     * @param  list<int>  $dispatchIds
     */
    public static function syncMany(array $dispatchIds): void
    {
        foreach (array_unique(array_filter(array_map('intval', $dispatchIds))) as $id) {
            self::syncFromDispatchId($id);
        }
    }

    public static function archiveFromDispatchId(int $dispatchId): void
    {
        foreach (array_keys(self::ISSUES_TO_HISTORY) as $table) {
            self::archiveCopy($dispatchId, $table);
        }
    }

    public static function deleteFromDispatchId(int $dispatchId): void
    {
        self::deleteCopies($dispatchId);
    }

    /**
     * Copy any missing All Issues rows for these departments onto the board tables.
     *
     * @param  list<string>  $departments
     */
    public static function ensureForDepartments(array $departments): void
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return;
        }

        $departments = CustomerCareDepartments::normalizeStringList($departments);
        if ($departments === []) {
            return;
        }

        $query = DB::table(self::SOURCE_TABLE)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('id')
            ->limit(2000);

        $query->where(function ($outer) use ($departments) {
            foreach ($departments as $dept) {
                $outer->orWhere(function ($q) use ($dept) {
                    CustomerCareDepartments::applyWhereDepartmentMatches($q, 'department', $dept);
                });
            }
        });

        $alreadyCopied = [];
        foreach ($departments as $dept) {
            $table = self::DEPT_TO_ISSUES[$dept] ?? null;
            if (! $table || ! Schema::hasTable($table) || ! in_array('source_dispatch_issue_id', self::columns($table), true)) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull('source_dispatch_issue_id')->pluck('source_dispatch_issue_id') as $id) {
                $alreadyCopied[(int) $id] = true;
            }
        }

        foreach ($query->get(['id']) as $row) {
            $id = (int) $row->id;
            if (isset($alreadyCopied[$id])) {
                continue;
            }
            self::syncFromDispatchId($id);
        }
    }

    private static function upsertCopy(object $source, string $issuesTable): void
    {
        if (! Schema::hasTable($issuesTable)) {
            return;
        }

        try {
            $payload = self::payloadForTable($source, $issuesTable);
            if ($payload === []) {
                return;
            }

            $now = now();
            $linkCol = Schema::hasColumn($issuesTable, 'source_dispatch_issue_id');
            $existing = null;
            if ($linkCol) {
                $payload['source_dispatch_issue_id'] = (int) $source->id;
                $existing = DB::table($issuesTable)
                    ->where('source_dispatch_issue_id', (int) $source->id)
                    ->first();
            }

            if ($existing) {
                $payload['updated_at'] = $now;
                unset($payload['created_at'], $payload['created_by'], $payload['created_by_user_id']);
                DB::table($issuesTable)->where('id', $existing->id)->update($payload);

                return;
            }

            $payload['created_at'] = $payload['created_at'] ?? $source->created_at ?? $now;
            $payload['updated_at'] = $now;
            if (empty($payload['id'])) {
                $payload['id'] = ((int) DB::table($issuesTable)->max('id')) + 1;
            }
            $copyId = (int) (DB::table($issuesTable)->insertGetId($payload) ?: ($payload['id'] ?? 0));

            $historyTable = self::ISSUES_TO_HISTORY[$issuesTable] ?? null;
            if ($historyTable && Schema::hasTable($historyTable)) {
                $history = self::payloadForTable($source, $historyTable);
                $history['orders_on_hold_issue_id'] = $copyId;
                $history['event_type'] = 'created';
                $history['revision_no'] = 0;
                $history['logged_at'] = $now;
                $history['created_at'] = $now;
                $history['updated_at'] = $now;
                if (empty($history['id'])) {
                    $history['id'] = ((int) DB::table($historyTable)->max('id')) + 1;
                }
                DB::table($historyTable)->insert($history);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('All Issues fan-out failed', [
                'dispatch_id' => $source->id ?? null,
                'table' => $issuesTable,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function archiveCopy(int $dispatchId, string $issuesTable): void
    {
        if (! Schema::hasTable($issuesTable) || ! Schema::hasColumn($issuesTable, 'source_dispatch_issue_id')) {
            return;
        }

        $existing = DB::table($issuesTable)
            ->where('source_dispatch_issue_id', $dispatchId)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->first();
        if (! $existing) {
            return;
        }

        $now = now();
        DB::table($issuesTable)->where('id', $existing->id)->update([
            'is_archived' => true,
            'archived_at' => $now,
            'archived_by' => $existing->archived_by ?? 'All Issues',
            'updated_at' => $now,
        ]);
    }

    private static function deleteCopies(int $dispatchId): void
    {
        foreach (self::ISSUES_TO_HISTORY as $issuesTable => $historyTable) {
            if (! Schema::hasTable($issuesTable) || ! Schema::hasColumn($issuesTable, 'source_dispatch_issue_id')) {
                continue;
            }
            $ids = DB::table($issuesTable)
                ->where('source_dispatch_issue_id', $dispatchId)
                ->pluck('id')
                ->all();
            if ($ids === []) {
                continue;
            }
            if (Schema::hasTable($historyTable)) {
                DB::table($historyTable)->whereIn('orders_on_hold_issue_id', $ids)->delete();
            }
            DB::table($issuesTable)->whereIn('id', $ids)->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadForTable(object $source, string $table): array
    {
        $skip = ['id', 'source_dispatch_issue_id'];
        $columns = array_flip(self::columns($table));
        $payload = [];
        foreach ((array) $source as $key => $value) {
            if (in_array($key, $skip, true) || ! isset($columns[$key])) {
                continue;
            }
            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private static function columns(string $table): array
    {
        if (! isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        }

        return self::$columnCache[$table];
    }
}
