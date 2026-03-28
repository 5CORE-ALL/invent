<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class IssueBoardControllerBase extends Controller
{
    abstract protected function viewName(): string;
    abstract protected function issuesTable(): string;
    abstract protected function historyTable(): string;

    protected function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:128',
            'qty' => 'required|numeric|min:0',
            'order_qty' => 'nullable|numeric|min:0',
            'parent' => 'nullable|string|max:255',
            'marketplace_1' => 'nullable|string|max:255',
            'marketplace_2' => 'nullable|string|max:255',
            'what_happened' => 'nullable|string|max:50',
            'issue' => 'required|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => 'nullable|string|in:Offer Customer Alterntive / Updgrade,Upgraded + Stock Alternate,Alternate Sent + Stock Alternate,Sent Wrong Item + Stock Outgoing,Cancelled,Other|max:255',
            'action_1_remark' => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
            'close_note' => 'nullable|string|max:255',
        ]);

        $allowedRootCauses = [
            'Mapping',
            'Replacement Issued But not Entered',
            'FBA stock Issued But not Entered',
            'Alternate Issued But not Entered',
            'Stock Balance not Entered',
            'Reserve Stock Issue',
            'Other',
        ];

        if (!in_array((string) ($validated['issue'] ?? ''), $allowedRootCauses, true)) {
            abort(response()->json([
                'message' => 'Invalid Root Cause Found selection.',
                'errors' => ['issue' => ['Invalid Root Cause Found selection.']],
            ], 422));
        }

        if (($validated['issue'] ?? null) === 'Other' && trim((string) ($validated['issue_remark'] ?? '')) === '') {
            abort(response()->json([
                'message' => 'Root Cause Found remark is required when Other is selected.',
                'errors' => ['issue_remark' => ['Root Cause Found remark is required when Other is selected.']],
            ], 422));
        }

        if (($validated['action_1'] ?? null) === 'Other' && trim((string) ($validated['action_1_remark'] ?? '')) === '') {
            abort(response()->json([
                'message' => 'Action remark is required when Action is Other.',
                'errors' => ['action_1_remark' => ['Action remark is required when Action is Other.']],
            ], 422));
        }

        if (($validated['c_action_1'] ?? null) === 'Other' && trim((string) ($validated['c_action_1_remark'] ?? '')) === '') {
            abort(response()->json([
                'message' => 'Root Cause Fixed remark is required when Root Cause Fixed is Other.',
                'errors' => ['c_action_1_remark' => ['Root Cause Fixed remark is required when Root Cause Fixed is Other.']],
            ], 422));
        }

        return $validated;
    }

    public function index()
    {
        $marketplaces = DB::table('marketplace_percentages')
            ->whereNotNull('marketplace')
            ->where('marketplace', '!=', '')
            ->orderBy('marketplace')
            ->pluck('marketplace')
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->values();

        return view($this->viewName(), compact('marketplaces'));
    }

    public function skuDetails(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['found' => false, 'message' => 'SKU is required.'], 422);
        }

        $row = DB::table('product_master as pm')
            ->selectRaw('pm.sku, pm.parent, pm.Values as values_json, pm.main_image, pm.image1')
            ->whereRaw('LOWER(TRIM(pm.sku)) = ?', [strtolower($sku)])
            ->first();

        if (! $row) {
            return response()->json(['found' => false, 'message' => 'SKU not found.']);
        }

        $shopify = DB::table('shopify_skus')
            ->selectRaw('COALESCE(inv, 0) as qty, image_src')
            ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower((string) $row->sku)])
            ->first();

        $normalizeImage = static function ($path) {
            $p = trim((string) ($path ?? ''));
            if ($p === '') {
                return null;
            }
            if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) {
                return $p;
            }
            return '/' . ltrim($p, '/');
        };

        $values = [];
        if (isset($row->values_json) && is_string($row->values_json) && trim($row->values_json) !== '') {
            $decoded = json_decode($row->values_json, true);
            if (is_array($decoded)) {
                $values = $decoded;
            }
        }

        $imageUrl = $normalizeImage($shopify?->image_src)
            ?? $normalizeImage($values['image_path'] ?? null)
            ?? $normalizeImage($row->main_image ?? null)
            ?? $normalizeImage($row->image1 ?? null);

        return response()->json([
            'found' => true,
            'sku' => $row->sku,
            'parent' => $row->parent,
            'qty' => (float) ($shopify?->qty ?? 0),
            'image_url' => $imageUrl,
        ]);
    }

    public function issuesIndex(): JsonResponse
    {
        $rows = DB::table($this->issuesTable())
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        $tz = config('app.timezone');
        $data = $rows->map(function ($row) use ($tz) {
            return [
                'id' => (int) $row->id,
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
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'created_at_display' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function historyIndex(): JsonResponse
    {
        $rows = DB::table($this->historyTable())->orderByDesc('id')->limit(1000)->get();
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
                'created_by' => $row->created_by,
                'logged_at' => $row->logged_at,
                'logged_at_display' => $row->logged_at
                    ? \Carbon\Carbon::parse($row->logged_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $user = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System')) ?: 'System';

        $id = DB::transaction(function () use ($validated, $createdBy, $user) {
            $now = now();
            $payload = [
                'sku' => trim($validated['sku']),
                'qty' => (float) $validated['qty'],
                'order_qty' => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
                'parent' => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
                'marketplace_1' => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
                'marketplace_2' => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
                'what_happened' => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
                'issue' => trim($validated['issue']),
                'issue_remark' => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
                'action_1' => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
                'action_1_remark' => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
                'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
                'c_action_1' => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
                'c_action_1_remark' => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
                'close_note' => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
                'created_by' => $createdBy,
                'created_by_user_id' => $user?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $issueId = DB::table($this->issuesTable())->insertGetId($payload);
            DB::table($this->historyTable())->insert(array_merge($payload, [
                'orders_on_hold_issue_id' => $issueId,
                'event_type' => 'created',
                'revision_no' => 0,
                'logged_at' => $now,
            ]));
            return $issueId;
        });

        $row = DB::table($this->issuesTable())->where('id', $id)->first();
        $tz = config('app.timezone');
        return response()->json([
            'message' => 'Hold issue saved successfully.',
            'row' => [
                'id' => (int) $row->id,
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
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'created_at_display' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i') : '',
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $existing = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $user = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System')) ?: 'System';

        DB::transaction(function () use ($id, $validated, $actorName, $user) {
            $now = now();
            $nextRevision = ((int) DB::table($this->historyTable())->where('orders_on_hold_issue_id', $id)->max('revision_no')) + 1;
            $payload = [
                'sku' => trim($validated['sku']),
                'qty' => (float) $validated['qty'],
                'order_qty' => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
                'parent' => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
                'marketplace_1' => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
                'marketplace_2' => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
                'what_happened' => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
                'issue' => trim($validated['issue']),
                'issue_remark' => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
                'action_1' => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
                'action_1_remark' => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
                'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
                'c_action_1' => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
                'c_action_1_remark' => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
                'close_note' => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
                'updated_at' => $now,
            ];
            DB::table($this->issuesTable())->where('id', $id)->update($payload);
            DB::table($this->historyTable())->insert(array_merge($payload, [
                'orders_on_hold_issue_id' => $id,
                'event_type' => 'updated',
                'revision_no' => $nextRevision,
                'created_by' => $actorName,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
            ]));
        });

        return response()->json(['message' => 'Hold issue updated successfully.']);
    }

    public function archive(int $id): JsonResponse
    {
        $row = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $user = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System')) ?: 'System';

        DB::transaction(function () use ($id, $row, $actorName, $user) {
            $now = now();
            $nextRevision = ((int) DB::table($this->historyTable())->where('orders_on_hold_issue_id', $id)->max('revision_no')) + 1;
            DB::table($this->issuesTable())->where('id', $id)->update([
                'is_archived' => true,
                'archived_at' => $now,
                'archived_by' => $actorName,
                'updated_at' => $now,
            ]);
            DB::table($this->historyTable())->insert([
                'orders_on_hold_issue_id' => $id,
                'event_type' => 'archived',
                'revision_no' => $nextRevision,
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
                'created_by' => $actorName,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return response()->json(['message' => 'Hold issue archived successfully.']);
    }
}
