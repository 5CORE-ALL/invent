<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Support\CustomerCareIssueFanout;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class QcIssuesSupplierWiseController extends Controller
{
    private const BOARD_DEPARTMENTS = ['QC', 'Packaging'];

    private const NO_SUPPLIER = '(No Supplier)';

    /** Same Action choices as the QC / Packaging page. */
    public const ACTION_OPTIONS = [
        'Offer Customer Alterntive / Updgrade', 'Upgraded + Stock Alternate', 'Alternate Sent + Stock Alternate',
        'Sent Wrong Item + Stock Outgoing', 'Cancelled', 'Refund', 'RTS', 'NA', 'Other',
    ];

    /** Period filter values: lifetime (all) or the last N days. */
    public const PERIODS = ['lifetime' => null, '365' => 365, '180' => 180, '90' => 90, '30' => 30];

    /** `?period=` from the request, as a number of days (null = lifetime). Unknown values mean lifetime. */
    public static function periodKey(Request $request): string
    {
        $p = (string) $request->input('period', 'lifetime');

        return array_key_exists($p, self::PERIODS) ? $p : 'lifetime';
    }

    /** `?supplier=NAME` opens the single-supplier detail view (Issues, Details, Action). */
    public function index(Request $request)
    {
        $detailSupplier = trim((string) $request->query('supplier', ''));

        return view('customer-care.qc_issues_supplier_wise', [
            'detailSupplier' => $detailSupplier !== '' ? $detailSupplier : null,
            'period' => self::periodKey($request),
        ]);
    }

    /**
     * One row per supplier: { supplier, supplier_active, issues: [...], issue_count }.
     * Issues are the non-archived QC / Packaging rows, grouped by the supplier of each SKU.
     */
    public function data(Request $request): JsonResponse
    {
        $groups = $this->groupsBySupplier(self::PERIODS[self::periodKey($request)]);

        $only = trim((string) $request->query('supplier', ''));
        if ($only !== '') {
            $key = mb_strtoupper($only);
            $groups = isset($groups[$key]) ? [$key => $groups[$key]] : [];
        }

        $statuses = [];
        if (Schema::hasTable('qc_supplier_statuses')) {
            $statuses = DB::table('qc_supplier_statuses')->pluck('is_active', 'supplier_key')->all();
        }
        $data = [];
        foreach ($groups as $key => $group) {
            $data[] = [
                'supplier' => $group['supplier'],
                'supplier_key' => $key,
                'supplier_active' => ! isset($statuses[$key]) || (bool) $statuses[$key] ? 'Yes' : 'No',
                // Issues marked "Ignore - Not supplier fault" are listed but not counted.
                'issue_count' => count(array_filter($group['issues'], static fn ($i) => ! $i['ignored'])),
                'issues' => $group['issues'],
            ];
        }

        usort($data, function ($a, $b) {
            // Real suppliers first, most issues first, "(No Supplier)" last.
            $aNo = $a['supplier'] === self::NO_SUPPLIER;
            $bNo = $b['supplier'] === self::NO_SUPPLIER;
            if ($aNo !== $bNo) {
                return $aNo <=> $bNo;
            }

            return [$b['issue_count'], $a['supplier']] <=> [$a['issue_count'], $b['supplier']];
        });

        return response()->json(['data' => $data]);
    }

    public function setActive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier' => 'required|string|max:191',
            'active' => 'required|in:Yes,No',
        ]);

        if (! Schema::hasTable('qc_supplier_statuses')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first.'], 500);
        }

        $name = trim($validated['supplier']);
        DB::table('qc_supplier_statuses')->updateOrInsert(
            ['supplier_key' => mb_strtoupper($name)],
            [
                'supplier_name' => $name,
                'is_active' => $validated['active'] === 'Yes',
                'updated_by' => $request->user()?->email,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /** Mark / unmark one issue as "Ignore - Not supplier fault". Ignored issues are not counted. */
    public function ignoreIssue(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['ignore' => 'required|boolean']);

        if (! Schema::hasTable('qc_issue_ignores')) {
            return response()->json(['message' => 'Run php artisan migrate first.'], 500);
        }

        $row = DB::table('qc_and_packing_issues')->where('id', $id)->first();
        if (! $row || $row->is_archived) {
            return response()->json(['message' => 'Issue not found.'], 404);
        }

        if ($validated['ignore']) {
            $user = $request->user();
            DB::table('qc_issue_ignores')->updateOrInsert(
                ['issue_id' => $id],
                [
                    'ignored_by' => trim((string) ($user?->name ?? '')) ?: 'System',
                    'ignored_by_user_id' => $user?->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } else {
            DB::table('qc_issue_ignores')->where('issue_id', $id)->delete();
        }

        return response()->json(['success' => true, 'ignored' => (bool) $validated['ignore']]);
    }

    /** Edit one issue (not the supplier). */
    public function updateIssue(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'what_happened' => 'nullable|string|max:100',
            'issue' => 'required|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => ['nullable', 'string', 'max:255', Rule::in(self::ACTION_OPTIONS)],
            'action_1_remark' => 'nullable|string|max:255',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
        ]);

        $row = DB::table('qc_and_packing_issues')->where('id', $id)->first();
        if (! $row || $row->is_archived) {
            return response()->json(['message' => 'Issue not found.'], 404);
        }

        if (($validated['action_1'] ?? null) === 'Other' && trim((string) ($validated['action_1_remark'] ?? '')) === '') {
            return response()->json(['message' => 'Action remark is required when Action is Other.'], 422);
        }
        if (($validated['c_action_1'] ?? null) === 'Other' && trim((string) ($validated['c_action_1_remark'] ?? '')) === '') {
            return response()->json(['message' => 'Root Cause Fixed remark is required when Root Cause Fixed is Other.'], 422);
        }

        $payload = [];
        foreach (['what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark', 'c_action_1', 'c_action_1_remark'] as $f) {
            $v = isset($validated[$f]) ? trim((string) $validated[$f]) : null;
            $payload[$f] = $v === '' ? null : $v;
        }
        $payload['updated_at'] = now();

        DB::transaction(function () use ($id, $payload, $request) {
            DB::table('qc_and_packing_issues')->where('id', $id)->update($payload);
            $this->logHistory($id, 'updated', $request);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Archive one issue. Records whether corrective action was taken and the results (Yes)
     * or the reason for archiving without it (No).
     */
    public function archiveIssue(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'corrective_action_taken' => 'required|in:Yes,No',
            'details' => 'required|string|max:1000',
        ]);
        $details = trim($validated['details']);
        if ($details === '') {
            $msg = $validated['corrective_action_taken'] === 'Yes' ? 'Enter the results obtained.' : 'Enter the reason for archiving this issue.';

            return response()->json(['message' => $msg], 422);
        }

        if (! Schema::hasTable('qc_issue_archive_resolutions')) {
            return response()->json(['message' => 'Run php artisan migrate first.'], 500);
        }

        $row = DB::table('qc_and_packing_issues')->where('id', $id)->first();
        if (! $row || $row->is_archived) {
            return response()->json(['message' => 'Issue not found or already archived.'], 404);
        }

        $user = $request->user();
        $actor = trim((string) ($user?->name ?? '')) ?: 'System';

        DB::transaction(function () use ($id, $validated, $details, $request, $user, $actor) {
            $now = now();
            DB::table('qc_and_packing_issues')->where('id', $id)->update([
                'is_archived' => true,
                'archived_at' => $now,
                'archived_by' => $actor,
                'updated_at' => $now,
            ]);
            DB::table('qc_issue_archive_resolutions')->insert([
                'issue_id' => $id,
                'corrective_action_taken' => $validated['corrective_action_taken'] === 'Yes',
                'details' => $details,
                'archived_by' => $actor,
                'archived_by_user_id' => $user?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->logHistory($id, 'archived', $request);
        });

        return response()->json(['success' => true]);
    }

    /** Ignore ("Not supplier fault") or un-ignore several issues at once, e.g. every issue of a summary row. */
    public function ignoreBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'ignore' => 'required|boolean',
        ]);

        if (! Schema::hasTable('qc_issue_ignores')) {
            return response()->json(['message' => 'Run php artisan migrate first.'], 500);
        }

        $ids = $this->openIssueIds($validated['ids']);
        if ($ids === []) {
            return response()->json(['message' => 'No open issues found.'], 404);
        }

        $user = $request->user();
        DB::transaction(function () use ($ids, $validated, $user) {
            foreach ($ids as $id) {
                if ($validated['ignore']) {
                    DB::table('qc_issue_ignores')->updateOrInsert(
                        ['issue_id' => $id],
                        [
                            'ignored_by' => trim((string) ($user?->name ?? '')) ?: 'System',
                            'ignored_by_user_id' => $user?->id,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                } else {
                    DB::table('qc_issue_ignores')->where('issue_id', $id)->delete();
                }
            }
        });

        return response()->json(['success' => true, 'count' => count($ids)]);
    }

    /** Archive several issues at once with one corrective-action answer (every issue of a summary row). */
    public function archiveBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'corrective_action_taken' => 'required|in:Yes,No',
            'details' => 'required|string|max:1000',
        ]);
        $details = trim($validated['details']);
        if ($details === '') {
            $msg = $validated['corrective_action_taken'] === 'Yes' ? 'Enter the results obtained.' : 'Enter the reason for archiving these issues.';

            return response()->json(['message' => $msg], 422);
        }

        if (! Schema::hasTable('qc_issue_archive_resolutions')) {
            return response()->json(['message' => 'Run php artisan migrate first.'], 500);
        }

        $ids = $this->openIssueIds($validated['ids']);
        if ($ids === []) {
            return response()->json(['message' => 'No open issues found.'], 404);
        }

        $user = $request->user();
        $actor = trim((string) ($user?->name ?? '')) ?: 'System';

        DB::transaction(function () use ($ids, $validated, $details, $request, $user, $actor) {
            $now = now();
            foreach ($ids as $id) {
                DB::table('qc_and_packing_issues')->where('id', $id)->update([
                    'is_archived' => true,
                    'archived_at' => $now,
                    'archived_by' => $actor,
                    'updated_at' => $now,
                ]);
                DB::table('qc_issue_archive_resolutions')->insert([
                    'issue_id' => $id,
                    'corrective_action_taken' => $validated['corrective_action_taken'] === 'Yes',
                    'details' => $details,
                    'archived_by' => $actor,
                    'archived_by_user_id' => $user?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->logHistory($id, 'archived', $request);
            }
        });

        return response()->json(['success' => true, 'count' => count($ids)]);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function openIssueIds(array $ids): array
    {
        return DB::table('qc_and_packing_issues')
            ->whereIn('id', array_map('intval', $ids))
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** All archived QC / Packaging issues with their resolutions. */
    public function archived(): JsonResponse
    {
        $rows = DB::table('qc_and_packing_issues')
            ->where('is_archived', true)
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->limit(2000)
            ->get(['id', 'sku', 'parent', 'what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark',
                'c_action_1', 'c_action_1_remark', 'department', 'created_at', 'archived_at', 'archived_by']);

        $resolutions = [];
        if (Schema::hasTable('qc_issue_archive_resolutions')) {
            foreach (DB::table('qc_issue_archive_resolutions')->orderBy('id')->get() as $r) {
                $resolutions[(int) $r->issue_id] = $r; // latest wins
            }
        }

        $tz = config('app.timezone');
        $data = [];
        foreach ($rows as $row) {
            if (array_intersect($this->departmentValues($row->department), self::BOARD_DEPARTMENTS) === []) {
                continue;
            }
            $res = $resolutions[(int) $row->id] ?? null;
            $data[] = [
                'id' => (int) $row->id,
                'sku' => trim((string) $row->sku),
                'what_happened' => trim((string) ($row->what_happened ?? '')),
                'issue' => trim((string) ($row->issue ?? '')),
                'issue_remark' => trim((string) ($row->issue_remark ?? '')),
                'action' => trim((string) ($row->action_1 ?? '')),
                'date' => $row->created_at ? Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y') : '',
                'archived_at' => $row->archived_at ? Carbon::parse($row->archived_at)->timezone($tz)->format('d-m-Y H:i') : '',
                'archived_by' => trim((string) ($row->archived_by ?? '')),
                'corrective_action_taken' => $res ? ((bool) $res->corrective_action_taken ? 'Yes' : 'No') : '',
                'resolution' => $res ? trim((string) $res->details) : '',
            ];
        }

        $data = app(QcAndPackingController::class)->attachSuppliers($data);

        return response()->json(['data' => $data]);
    }

    /** Same history trail the QC / Packaging page writes on every change. */
    private function logHistory(int $id, string $event, Request $request): void
    {
        $row = DB::table('qc_and_packing_issues')->where('id', $id)->first();
        if (! $row) {
            return;
        }
        $user = $request->user();
        $now = now();
        $revision = ((int) DB::table('qc_and_packing_issue_histories')->where('orders_on_hold_issue_id', $id)->max('revision_no')) + 1;

        DB::table('qc_and_packing_issue_histories')->insert([
            'orders_on_hold_issue_id' => $id,
            'event_type' => $event,
            'revision_no' => $revision,
            'sku' => $row->sku,
            'qty' => (float) $row->qty,
            'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
            'parent' => $row->parent,
            'marketplace_1' => $row->marketplace_1 ?? null,
            'marketplace_2' => $row->marketplace_2 ?? null,
            'what_happened' => $row->what_happened ?? null,
            'issue' => $row->issue,
            'issue_remark' => $row->issue_remark ?? null,
            'action_1' => $row->action_1 ?? null,
            'action_1_remark' => $row->action_1_remark ?? null,
            'replacement_tracking' => $row->replacement_tracking ?? null,
            'c_action_1' => $row->c_action_1 ?? null,
            'c_action_1_remark' => $row->c_action_1_remark ?? null,
            'close_note' => $row->close_note ?? null,
            'department' => $row->department ?? null,
            'created_by' => trim((string) ($user?->name ?? '')) ?: 'System',
            'created_by_user_id' => $user?->id,
            'logged_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Board issues (QC / Packaging, not archived) grouped by supplier, newest first.
     *
     * @return array<string, array{supplier: string, issues: list<array<string, mixed>>}>
     */
    public function groupsBySupplier(?int $days = null): array
    {
        // Packaging / QC issues logged on All Issues are copied onto qc_and_packing_issues lazily;
        // do it here too so none are missing (same step the QC / Packaging page runs).
        CustomerCareIssueFanout::ensureForDepartments(self::BOARD_DEPARTMENTS);

        $rows = DB::table('qc_and_packing_issues')
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'sku', 'parent', 'what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark',
                'c_action_1', 'c_action_1_remark', 'department', 'created_at']);

        $tz = config('app.timezone');
        $issues = [];
        foreach ($rows as $row) {
            $depts = array_values(array_intersect($this->departmentValues($row->department), self::BOARD_DEPARTMENTS));
            if ($depts === []) {
                continue;
            }
            $issues[] = [
                'id' => (int) $row->id,
                'sku' => trim((string) $row->sku),
                'parent' => trim((string) ($row->parent ?? '')),
                'what_happened' => trim((string) ($row->what_happened ?? '')),
                'issue' => trim((string) ($row->issue ?? '')),
                'issue_remark' => trim((string) ($row->issue_remark ?? '')),
                'action' => trim((string) ($row->action_1 ?? '')),
                'action_remark' => trim((string) ($row->action_1_remark ?? '')),
                'root_cause_fixed' => trim((string) ($row->c_action_1 ?? '')),
                'root_cause_fixed_remark' => trim((string) ($row->c_action_1_remark ?? '')),
                'department' => implode(', ', $depts),
                'date' => $row->created_at ? Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y') : '',
                'date_iso' => $row->created_at ? Carbon::parse($row->created_at)->timezone($tz)->format('Y-m-d') : '',
            ];
        }

        $ignored = Schema::hasTable('qc_issue_ignores')
            ? array_flip(DB::table('qc_issue_ignores')->pluck('issue_id')->map(fn ($id) => (int) $id)->all())
            : [];
        foreach ($issues as $k => $i) {
            $issues[$k]['ignored'] = isset($ignored[$i['id']]);
        }

        if ($days !== null) {
            // Issues reported in the last $days days (today counts as day 0).
            $cutoff = Carbon::now($tz)->subDays($days)->format('Y-m-d');
            $issues = array_values(array_filter($issues, static fn ($i) => $i['date_iso'] !== '' && $i['date_iso'] >= $cutoff));
        }

        $issues = app(QcAndPackingController::class)->attachSuppliers($issues);

        $groups = [];
        foreach ($issues as $issue) {
            $name = trim((string) ($issue['supplier'] ?? ''));
            if ($name === '') {
                $name = self::NO_SUPPLIER;
            }
            $key = mb_strtoupper($name);
            unset($issue['supplier']);
            if (! isset($groups[$key])) {
                $groups[$key] = ['supplier' => $name, 'issues' => []];
            }
            $groups[$key]['issues'][] = $issue;
        }

        return $groups;
    }

    /** Department column is a JSON array or a "|" / "," separated list. */
    private function departmentValues(mixed $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[|,]/', $raw) ?: [])));
    }
}
