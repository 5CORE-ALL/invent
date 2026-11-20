<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Support\CustomerCareIssueFanout;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    /** Most recent issues sent to the AI per supplier (keeps the prompt small). */
    private const SUMMARY_MAX_ISSUES = 120;

    /** `?supplier=NAME` opens the single-supplier detail view (Issues, Details, Summary, Action). */
    public function index(Request $request)
    {
        $detailSupplier = trim((string) $request->query('supplier', ''));

        return view('customer-care.qc_issues_supplier_wise', [
            'detailSupplier' => $detailSupplier !== '' ? $detailSupplier : null,
        ]);
    }

    /**
     * One row per supplier: { supplier, supplier_active, issues: [...], issue_count, summary }.
     * Issues are the non-archived QC / Packaging rows, grouped by the supplier of each SKU.
     */
    public function data(Request $request): JsonResponse
    {
        $groups = $this->groupsBySupplier();

        $only = trim((string) $request->query('supplier', ''));
        if ($only !== '') {
            $key = mb_strtoupper($only);
            $groups = isset($groups[$key]) ? [$key => $groups[$key]] : [];
        }

        $statuses = [];
        if (Schema::hasTable('qc_supplier_statuses')) {
            $statuses = DB::table('qc_supplier_statuses')->pluck('is_active', 'supplier_key')->all();
        }
        $summaries = [];
        if (Schema::hasTable('qc_supplier_summaries')) {
            $summaries = DB::table('qc_supplier_summaries')->get(['supplier_key', 'issues_hash', 'summary'])->keyBy('supplier_key')->all();
        }

        $data = [];
        foreach ($groups as $key => $group) {
            $summary = null;
            $stale = false;
            if (isset($summaries[$key])) {
                $decoded = json_decode((string) $summaries[$key]->summary, true);
                if (is_array($decoded)) {
                    $summary = $decoded;
                    $stale = $summaries[$key]->issues_hash !== $this->issuesHash($group['issues']);
                }
            }
            $data[] = [
                'supplier' => $group['supplier'],
                'supplier_key' => $key,
                'supplier_active' => ! isset($statuses[$key]) || (bool) $statuses[$key] ? 'Yes' : 'No',
                'issue_count' => count($group['issues']),
                'issues' => $group['issues'],
                'summary' => $summary,
                'summary_stale' => $stale,
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

    /**
     * AI summary of one supplier's issues, SKU wise. SKUs that share a parent (siblings) and the
     * same supplier are summarised together. Cached until the supplier's issues change.
     */
    public function summarize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier' => 'required|string|max:191',
            'force' => 'sometimes|boolean',
        ]);

        if (! Schema::hasTable('qc_supplier_summaries')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first.'], 500);
        }

        $key = mb_strtoupper(trim($validated['supplier']));
        $group = $this->groupsBySupplier()[$key] ?? null;
        if (! $group || $group['issues'] === []) {
            return response()->json(['success' => false, 'message' => 'No issues found for this supplier.'], 404);
        }

        $hash = $this->issuesHash($group['issues']);
        $cached = DB::table('qc_supplier_summaries')->where('supplier_key', $key)->first();
        if ($cached && $cached->issues_hash === $hash && empty($validated['force'])) {
            $decoded = json_decode((string) $cached->summary, true);
            if (is_array($decoded)) {
                return response()->json(['success' => true, 'summary' => $decoded, 'cached' => true]);
            }
        }

        $apiKey = (string) (config('services.anthropic.key') ?: config('services.claude.key') ?: '');
        if ($apiKey === '') {
            return response()->json(['success' => false, 'message' => 'CLAUDE_API_KEY / ANTHROPIC_API_KEY is not configured.'], 503);
        }

        $blocks = $this->siblingBlocks($group['issues']);
        $prompt = $this->buildPrompt($group['supplier'], $blocks);
        $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 2500,
                    'temperature' => 0.2,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('QC supplier summary request failed', ['supplier' => $key, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'AI request failed: '.$e->getMessage()], 502);
        }

        if (! $response->successful()) {
            $msg = (string) data_get($response->json(), 'error.message', '');
            Log::warning('QC supplier summary Claude failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
                'model' => $model,
            ]);

            return response()->json([
                'success' => false,
                'message' => $msg !== '' ? $msg : 'Claude request failed (HTTP '.$response->status().').',
            ], 502);
        }

        $summary = $this->parseSummary((string) data_get($response->json(), 'content.0.text', ''), $blocks);

        DB::table('qc_supplier_summaries')->updateOrInsert(
            ['supplier_key' => $key],
            [
                'issues_hash' => $hash,
                'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                'model' => $model,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'summary' => $summary, 'cached' => false]);
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
    private function groupsBySupplier(): array
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
            ];
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

    /** Changes whenever the supplier's issues change, so cached summaries can be flagged outdated. */
    private function issuesHash(array $issues): string
    {
        return md5(json_encode(array_map(static fn ($i) => [
            $i['id'], $i['what_happened'], $i['issue'], $i['issue_remark'], $i['action'], $i['action_remark'],
            $i['root_cause_fixed'], $i['root_cause_fixed_remark'],
        ], $issues)));
    }

    /**
     * Sibling groups: issues of this supplier that share a parent are one block.
     * SKUs without a parent stand alone.
     *
     * @return list<array{group: string, skus: list<string>, lines: list<string>}>
     */
    private function siblingBlocks(array $issues): array
    {
        $blocks = [];
        foreach (array_slice($issues, 0, self::SUMMARY_MAX_ISSUES) as $i) {
            $label = $i['parent'] !== '' ? $i['parent'] : $i['sku'];
            $key = mb_strtoupper($label);
            if (! isset($blocks[$key])) {
                $blocks[$key] = ['group' => $label, 'skus' => [], 'lines' => []];
            }
            if (! in_array($i['sku'], $blocks[$key]['skus'], true)) {
                $blocks[$key]['skus'][] = $i['sku'];
            }
            $parts = array_filter([
                $i['date'],
                'SKU '.$i['sku'],
                $i['department'],
                'issue: '.trim($i['what_happened'].' '.$i['issue']),
                $i['issue_remark'] !== '' ? 'remark: '.$i['issue_remark'] : '',
                $i['action'] !== '' ? 'action: '.$i['action'].($i['action_remark'] !== '' ? ' ('.$i['action_remark'].')' : '') : '',
                $i['root_cause_fixed'] !== '' ? 'root cause fixed: '.$i['root_cause_fixed'] : '',
            ], static fn ($p) => $p !== '');
            $blocks[$key]['lines'][] = '- '.Str::limit(implode(' | ', $parts), 400);
        }

        return array_values($blocks);
    }

    private function buildPrompt(string $supplier, array $blocks): string
    {
        $text = '';
        foreach ($blocks as $b) {
            $text .= 'GROUP: '.$b['group'].' (SKUs: '.implode(', ', $b['skus']).")\n".implode("\n", $b['lines'])."\n\n";
        }
        $count = count($blocks);

        return <<<PROMPT
You are a QC analyst for an e-commerce brand (5 Core / musical gear & accessories).
Below are the QC and Packaging issues recorded for supplier "{$supplier}", already grouped by product family (sibling SKUs share one parent).

{$text}
Write a short summary of the issues faced for EACH group (there are {$count}), SKU wise:
- Mention which SKUs were affected and what went wrong, merging repeated problems across sibling SKUs of the same group.
- Include how often it happened and the date range when there is more than one issue, and say whether the root cause was fixed if that is recorded.
- Max 2 sentences per group. Use only facts from the data above; do not invent anything.

Output ONLY valid JSON (no markdown fences), one entry per group, in the same order:
[{"group":"<GROUP name>","skus":["<sku>", "..."],"summary":"<summary>"}]
PROMPT;
    }

    /**
     * @param  list<array{group: string, skus: list<string>, lines: list<string>}>  $blocks
     * @return list<array{group: string, skus: list<string>, summary: string}>
     */
    private function parseSummary(string $content, array $blocks): array
    {
        $content = trim($content);
        $parsed = null;
        if (preg_match('/\[.*\]/s', $content, $m)) {
            $parsed = json_decode($m[0], true);
        }

        $out = [];
        if (is_array($parsed)) {
            foreach ($parsed as $item) {
                if (! is_array($item) || trim((string) ($item['summary'] ?? '')) === '') {
                    continue;
                }
                $out[] = [
                    'group' => trim((string) ($item['group'] ?? '')),
                    'skus' => array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) ($item['skus'] ?? [])))),
                    'summary' => trim((string) $item['summary']),
                ];
            }
        }

        if ($out === []) {
            // Model did not return JSON: keep its text so the work is not lost.
            $out[] = ['group' => '', 'skus' => [], 'summary' => $content];
        }

        return $out;
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
