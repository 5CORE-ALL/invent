<?php

namespace App\Http\Controllers\CustomerCare;

use App\Support\CustomerCareDepartments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchIssuesController extends IssueBoardControllerBase
{
    protected function viewName(): string
    {
        return 'customer-care.dispatch_issues';
    }

    /** Second entry point: same datatable and APIs as All Issues, alternate shell/titles. */
    public function dispatchIssueBoard()
    {
        return view('customer-care.dispatch', $this->issueBoardIndexData());
    }

    protected function issuesTable(): string
    {
        return 'dispatch_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'dispatch_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'dispatch_issues';
    }

    protected function extraValidationRules(): array
    {
        return [
            'order_number'  => 'nullable|string|max:255',
            'refund_amount' => 'nullable|numeric|min:0',
            'total_loss'    => 'nullable|numeric',
        ];
    }

    protected function buildExtraPayload(array $validated): array
    {
        return [
            'order_number'  => isset($validated['order_number'])  ? trim((string) $validated['order_number'])  : null,
            'refund_amount' => isset($validated['refund_amount'])  ? (float) $validated['refund_amount']         : null,
            'total_loss'    => isset($validated['total_loss'])     ? (float) $validated['total_loss']            : null,
        ];
    }

    protected function extraRowFields(object $row): array
    {
        return [
            'order_number'  => $row->order_number  ?? null,
            'refund_amount' => $row->refund_amount  !== null ? (float) $row->refund_amount : null,
            'total_loss'    => $row->total_loss     !== null ? (float) $row->total_loss    : null,
            'group_id'      => $row->group_id       ?? null,
        ];
    }

    public function issuesIndex(): JsonResponse
    {
        $department = trim((string) request()->query('department', ''));
        $query = DB::table($this->issuesTable())
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            });
        if ($department !== '') {
            CustomerCareDepartments::applyWhereDepartmentMatches($query, 'department', $department);
        }
        $rows = $query->orderByDesc('id')
            ->limit(1000)
            ->get();

        // Build image map: sku (lowercase) => image_url
        $skus = $rows->pluck('sku')->map(fn ($s) => strtolower(trim((string) $s)))->unique()->values()->all();
        $shopifyImages = DB::table('shopify_skus')
            ->selectRaw('LOWER(TRIM(sku)) as sku_key, image_src')
            ->whereRaw('LOWER(TRIM(sku)) IN (' . implode(',', array_fill(0, count($skus), '?')) . ')', $skus)
            ->get()
            ->keyBy('sku_key');

        $pmImages = DB::table('product_master')
            ->selectRaw('LOWER(TRIM(sku)) as sku_key, main_image, image1')
            ->whereRaw('LOWER(TRIM(sku)) IN (' . implode(',', array_fill(0, count($skus), '?')) . ')', $skus)
            ->get()
            ->keyBy('sku_key');

        $normalizeImage = static function ($path) {
            $p = trim((string) ($path ?? ''));
            if ($p === '') return null;
            if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) return $p;
            return '/' . ltrim($p, '/');
        };

        $imageMap = [];
        foreach ($skus as $key) {
            $imageMap[$key] = $normalizeImage($shopifyImages[$key]->image_src ?? null)
                ?? $normalizeImage($pmImages[$key]->main_image ?? null)
                ?? $normalizeImage($pmImages[$key]->image1 ?? null);
        }

        $tz = config('app.timezone');
        $data = $rows->map(function ($row) use ($tz, $imageMap) {
            $skuKey = strtolower(trim((string) $row->sku));
            return [
                'id'                   => (int) $row->id,
                'sku'                  => $row->sku,
                'image_url'            => $imageMap[$skuKey] ?? null,
                'qty'                  => (float) $row->qty,
                'order_qty'            => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent'               => $row->parent,
                'marketplace_1'        => $row->marketplace_1,
                'marketplace_2'        => $row->marketplace_2,
                'what_happened'        => $row->what_happened,
                'issue'                => $row->issue,
                'issue_remark'         => $row->issue_remark,
                'action_1'             => $row->action_1,
                'action_1_remark'      => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1'           => $row->c_action_1,
                'c_action_1_remark'    => $row->c_action_1_remark,
                'close_note'           => $row->close_note,
                'department'           => CustomerCareDepartments::label($row->department ?? null),
                'departments'          => CustomerCareDepartments::decode($row->department ?? null),
                'created_by'           => $row->created_by,
                'created_at'           => $row->created_at,
                'created_at_display'   => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ] + $this->extraRowFields($row);
        })->values();

        return response()->json(['data' => $data]);
    }

    public function historyIndex(): JsonResponse
    {
        $department = trim((string) request()->query('department', ''));
        $query = DB::table($this->historyTable())->orderByDesc('id')->limit(1000);
        if ($department !== '') {
            CustomerCareDepartments::applyWhereDepartmentMatches($query, 'department', $department);
        }
        $rows = $query->get();
        $tz = config('app.timezone');

        $data = $rows->map(function ($row) use ($tz) {
            return [
                'id' => (int) $row->id,
                'orders_on_hold_issue_id' => $row->orders_on_hold_issue_id ? (int) $row->orders_on_hold_issue_id : null,
                'event_type' => $row->event_type,
                'revision_no' => $row->revision_no !== null ? (int) $row->revision_no : null,
                'issue_ref' => ($row->orders_on_hold_issue_id
                    ? ((((int) ($row->revision_no ?? 0) > 0)
                        ? ((string) $row->orders_on_hold_issue_id . '.' . (string) ((int) $row->revision_no))
                        : (string) $row->orders_on_hold_issue_id))
                    : null),
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'department' => CustomerCareDepartments::label($row->department ?? null),
                'departments' => CustomerCareDepartments::decode($row->department ?? null),
                'created_by' => $row->created_by,
                'logged_at' => $row->logged_at,
                'logged_at_display' => $row->logged_at
                    ? \Carbon\Carbon::parse($row->logged_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ] + $this->extraHistoryRowFields($row);
        })->values();

        return response()->json(['data' => $data]);
    }

    public function archive(int $id): JsonResponse
    {
        if (auth()->user()?->email !== 'president@5core.com') {
            return response()->json(['message' => 'Unauthorised.'], 403);
        }

        return parent::archive($id);
    }

    public function l30Issues(\Illuminate\Http\Request $request): JsonResponse
    {
        $tz         = config('app.timezone');
        $days       = max(1, (int) $request->query('days', 30));
        $department = $request->query('department', '');
        $today      = \Carbon\Carbon::now($tz)->toDateString();
        $from       = \Carbon\Carbon::now($tz)->subDays($days - 1)->toDateString();

        $query = DB::table($this->issuesTable())
            ->selectRaw("DATE(created_at) as day, COUNT(*) as issue_count")
            ->whereRaw("DATE(created_at) BETWEEN ? AND ?", [$from, $today]);

        if ($department !== '') {
            CustomerCareDepartments::applyWhereDepartmentMatches($query, 'department', $department);
        }

        $rows = $query->groupByRaw("DATE(created_at)")
            ->orderByRaw("DATE(created_at)")
            ->get();

        return response()->json([
            'total' => (int) $rows->sum('issue_count'),
            'from'  => $from,
            'to'    => $today,
            'days'  => $days,
            'daily' => $rows->map(fn ($r) => [
                'date'  => $r->day,
                'count' => (int) $r->issue_count,
            ])->values(),
        ]);
    }

    public function l30Loss(\Illuminate\Http\Request $request): JsonResponse
    {
        $tz         = config('app.timezone');
        $days       = max(1, (int) $request->query('days', 30));
        $department = $request->query('department', '');
        $today      = \Carbon\Carbon::now($tz)->toDateString();
        $from       = \Carbon\Carbon::now($tz)->subDays($days - 1)->toDateString();

        $query = DB::table($this->issuesTable())
            ->selectRaw("DATE(created_at) as day, SUM(total_loss) as daily_loss, COUNT(*) as issue_count")
            ->whereRaw("DATE(created_at) BETWEEN ? AND ?", [$from, $today])
            ->whereNotNull('total_loss');

        if ($department !== '') {
            CustomerCareDepartments::applyWhereDepartmentMatches($query, 'department', $department);
        }

        $rows = $query->groupByRaw("DATE(created_at)")
            ->orderByRaw("DATE(created_at)")
            ->get();

        return response()->json([
            'total' => round((float) $rows->sum('daily_loss'), 2),
            'from'  => $from,
            'to'    => $today,
            'daily' => $rows->map(fn ($r) => [
                'date'  => $r->day,
                'loss'  => round((float) $r->daily_loss, 2),
                'count' => (int) $r->issue_count,
            ])->values(),
        ]);
    }

    /**
     * Override store to support multi-SKU entries (single Order ID, multiple SKUs = 1 error group).
     */
    public function store(Request $request): JsonResponse
    {
        $skusPayload = $request->input('skus');

        // Multi-SKU mode: array of {sku, qty, order_qty, parent}
        if (is_array($skusPayload) && count($skusPayload) >= 1) {
            return $this->storeMultiSku($request, $skusPayload);
        }

        // Single-SKU mode – use base behaviour
        return parent::store($request);
    }

    private function storeMultiSku(Request $request, array $skusPayload): JsonResponse
    {
        $request->validate([
            'issue'              => 'nullable|string|max:255',
            'order_number'       => 'nullable|string|max:255',
            'refund_amount'      => 'nullable|numeric|min:0',
            'total_loss'         => 'nullable|numeric',
            'marketplace_1'      => 'nullable|string|max:255',
            'marketplace_2'      => 'nullable|string|max:255',
            'what_happened'      => 'nullable|string|max:50',
            'issue_remark'       => 'nullable|string|max:255',
            'action_1'           => 'nullable|string|max:255',
            'action_1_remark'    => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1'         => 'nullable|string|max:255',
            'c_action_1_remark'  => 'nullable|string|max:255',
            'issue_date'         => 'nullable|string|max:100',
            'department'         => 'required|array|min:1',
            'department.*'       => 'required|string|max:100',
        ]);

        $depts = CustomerCareDepartments::normalizeStringList($request->input('department', []));
        if (count($depts) === 0) {
            return response()->json([
                'message' => 'Department is required.',
                'errors'  => ['department' => ['Select at least one department.']],
            ], 422);
        }
        $departmentEncoded = CustomerCareDepartments::encode($depts);

        $user      = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System')) ?: 'System';
        $groupId   = Str::uuid()->toString();
        $now       = now();
        $tz        = config('app.timezone');

        $sharedPayload = [
            'group_id'             => $groupId,
            'order_number'         => $request->input('order_number') ? trim($request->input('order_number')) : null,
            'refund_amount'        => $request->input('refund_amount') !== null && $request->input('refund_amount') !== '' ? (float) $request->input('refund_amount') : null,
            'total_loss'           => $request->input('total_loss') !== null && $request->input('total_loss') !== '' ? (float) $request->input('total_loss') : null,
            'marketplace_1'        => $request->input('marketplace_1') ? trim($request->input('marketplace_1')) : null,
            'marketplace_2'        => $request->input('marketplace_2') ? trim($request->input('marketplace_2')) : null,
            'what_happened'        => $request->input('what_happened') ? trim($request->input('what_happened')) : null,
            'issue'                => trim($request->input('issue')),
            'issue_remark'         => $request->input('issue_remark') ? trim($request->input('issue_remark')) : null,
            'action_1'             => $request->input('action_1') ? trim($request->input('action_1')) : null,
            'action_1_remark'      => $request->input('action_1_remark') ? trim($request->input('action_1_remark')) : null,
            'replacement_tracking' => $request->input('replacement_tracking') ? trim($request->input('replacement_tracking')) : null,
            'c_action_1'           => $request->input('c_action_1') ? trim($request->input('c_action_1')) : null,
            'c_action_1_remark'    => $request->input('c_action_1_remark') ? trim($request->input('c_action_1_remark')) : null,
            'close_note'           => null,
            'issue_date'           => $request->input('issue_date') ? trim($request->input('issue_date')) : null,
            'department'           => $departmentEncoded,
            'created_by'           => $createdBy,
            'created_by_user_id'   => $user?->id,
            'created_at'           => $now,
            'updated_at'           => $now,
        ];

        $insertedRows = [];

        DB::transaction(function () use ($skusPayload, $sharedPayload, $now, $tz, &$insertedRows) {
            foreach ($skusPayload as $skuEntry) {
                $sku = trim((string) ($skuEntry['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }

                $payload = array_merge($sharedPayload, [
                    'sku'       => $sku,
                    'qty'       => isset($skuEntry['qty']) && $skuEntry['qty'] !== '' ? (float) $skuEntry['qty'] : 0,
                    'order_qty' => isset($skuEntry['order_qty']) && $skuEntry['order_qty'] !== '' ? (float) $skuEntry['order_qty'] : null,
                    'parent'    => isset($skuEntry['parent']) ? trim((string) $skuEntry['parent']) : null,
                ]);

                $issueId = DB::table($this->issuesTable())->insertGetId($payload);
                DB::table($this->historyTable())->insert(array_merge($payload, [
                    'orders_on_hold_issue_id' => $issueId,
                    'event_type'              => 'created',
                    'revision_no'             => 0,
                    'logged_at'               => $now,
                ]));

                $row = DB::table($this->issuesTable())->where('id', $issueId)->first();
                $insertedRows[] = [
                    'id'                   => (int) $row->id,
                    'sku'                  => $row->sku,
                    'qty'                  => (float) $row->qty,
                    'order_qty'            => $row->order_qty !== null ? (float) $row->order_qty : null,
                    'parent'               => $row->parent,
                    'group_id'             => $row->group_id,
                    'marketplace_1'        => $row->marketplace_1,
                    'marketplace_2'        => $row->marketplace_2,
                    'what_happened'        => $row->what_happened,
                    'issue'                => $row->issue,
                    'issue_remark'         => $row->issue_remark,
                    'action_1'             => $row->action_1,
                    'action_1_remark'      => $row->action_1_remark,
                    'replacement_tracking' => $row->replacement_tracking,
                    'c_action_1'           => $row->c_action_1,
                    'c_action_1_remark'    => $row->c_action_1_remark,
                    'close_note'           => $row->close_note,
                    'created_by'           => $row->created_by,
                    'created_at'           => $row->created_at,
                    'issue_date'           => $row->issue_date ?? null,
                    'order_number'         => $row->order_number ?? null,
                    'refund_amount'        => $row->refund_amount !== null ? (float) $row->refund_amount : null,
                    'total_loss'           => $row->total_loss !== null ? (float) $row->total_loss : null,
                    'department'           => CustomerCareDepartments::label($row->department ?? null),
                    'departments'          => CustomerCareDepartments::decode($row->department ?? null),
                    'created_at_display'   => $row->created_at
                        ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                        : '',
                ];
            }
        });

        if (empty($insertedRows)) {
            return response()->json(['message' => 'No valid SKUs provided.'], 422);
        }

        return response()->json([
            'message'  => count($insertedRows) . ' SKU(s) saved as 1 error group.',
            'rows'     => $insertedRows,
            'group_id' => $groupId,
        ], 201);
    }
}
