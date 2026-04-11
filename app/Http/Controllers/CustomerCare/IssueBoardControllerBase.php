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
    abstract protected function moduleKey(): string;

    /** Extra validation rules for page-specific fields (override in subclass) */
    protected function extraValidationRules(): array { return []; }

    /** Build extra payload fields from validated data (override in subclass) */
    protected function buildExtraPayload(array $validated): array { return []; }

    /** Extra fields to include in issuesIndex API response (override in subclass) */
    protected function extraRowFields(object $row): array { return []; }

    /** Extra fields on history API rows (override in subclass) */
    protected function extraHistoryRowFields(object $row): array { return []; }

    /** Merge into CSV import row payload (override in subclass; avoid keys your table lacks) */
    protected function csvImportExtraPayload(callable $get): array { return []; }

    protected function normalizeFieldType(string $fieldType): string
    {
        $value = trim($fieldType);
        abort_unless(in_array($value, ['root_cause_found', 'root_cause_fixed'], true), 422, 'Invalid field type.');
        return $value;
    }

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
            'issue' => 'nullable|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => 'nullable|string|max:255',
            'action_1_remark' => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
            'close_note' => 'nullable|string|max:255',
            'issue_date' => 'nullable|string|max:100',
        ]);

        // Merge page-specific extra field validation
        $extra = $this->extraValidationRules();
        if (!empty($extra)) {
            $validated = array_merge($validated, $request->validate($extra));
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
            ->unique(fn ($m) => strtolower(preg_replace('/\s+/', '', $m)))
            ->values();

        return view($this->viewName(), compact('marketplaces'));
    }

    public function dropdownOptionsIndex(Request $request): JsonResponse
    {
        $fieldType = $this->normalizeFieldType((string) $request->query('field_type', ''));
        $options = DB::table('customer_care_issue_dropdown_options')
            ->where('module_key', $this->moduleKey())
            ->where('field_type', $fieldType)
            ->orderBy('option_value')
            ->pluck('option_value')
            ->values();

        return response()->json(['data' => $options]);
    }

    public function dropdownOptionsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'option_value' => 'required|string|max:255',
        ]);

        $fieldType = $this->normalizeFieldType((string) $validated['field_type']);
        $optionValue = trim((string) $validated['option_value']);
        if ($optionValue === '') {
            return response()->json(['message' => 'Option value is required.'], 422);
        }

        $exists = DB::table('customer_care_issue_dropdown_options')
            ->where('module_key', $this->moduleKey())
            ->where('field_type', $fieldType)
            ->whereRaw('LOWER(option_value) = ?', [strtolower($optionValue)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Option already exists.'], 409);
        }

        DB::table('customer_care_issue_dropdown_options')->insert([
            'module_key' => $this->moduleKey(),
            'field_type' => $fieldType,
            'option_value' => $optionValue,
            'created_by_user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Option added successfully.']);
    }

    public function dropdownOptionsDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'option_value' => 'required|string|max:255',
        ]);

        $fieldType = $this->normalizeFieldType((string) $validated['field_type']);
        $optionValue = trim((string) $validated['option_value']);

        DB::table('customer_care_issue_dropdown_options')
            ->where('module_key', $this->moduleKey())
            ->where('field_type', $fieldType)
            ->whereRaw('LOWER(option_value) = ?', [strtolower($optionValue)])
            ->delete();

        return response()->json(['message' => 'Option deleted successfully.']);
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
                'issue_date' => $row->issue_date ?? null,
                'created_at_display' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ] + $this->extraRowFields($row);
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
            ] + $this->extraHistoryRowFields($row);
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
                'issue_date' => isset($validated['issue_date']) ? trim((string) $validated['issue_date']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $payload = array_merge($payload, $this->buildExtraPayload($validated));
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
                'issue_date' => $row->issue_date ?? null,
                'created_at_display' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i') : '',
            ] + $this->extraRowFields($row),
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
                'issue_date' => isset($validated['issue_date']) ? trim((string) $validated['issue_date']) : null,
            ];
            $payload = array_merge($payload, $this->buildExtraPayload($validated));
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

    public function importCsv(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');
        $headers = null;
        $inserted = 0;
        $skipped  = 0;
        $errors   = [];

        $user      = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System')) ?: 'System';

        $map = [
            'sku'                  => ['sku'],
            'qty'                  => ['qty', 'quantity'],
            'order_qty'            => ['order_qty', 'order qty', 'order_quantity'],
            'parent'               => ['parent'],
            'marketplace_1'        => ['marketplace_1', 'mkt1', 'marketplace1'],
            'marketplace_2'        => ['marketplace_2', 'mkt2', 'marketplace2'],
            'what_happened'        => ['what_happened', 'what?', 'what happened'],
            'action_1'             => ['action_1', 'action', 'action 1'],
            'action_1_remark'      => ['action_1_remark', 'action remark', 'action remark'],
            'replacement_tracking' => ['replacement_tracking', 'replacement tracking'],
            'issue'                => ['issue', 'root_cause_found', 'root cause found'],
            'issue_remark'         => ['issue_remark', 'root cause remark', 'root_cause_remark'],
            'c_action_1'           => ['c_action_1', 'root_cause_fixed', 'root cause fixed'],
            'c_action_1_remark'    => ['c_action_1_remark', 'root cause fixed remark', 'root_cause_fixed_remark'],
            'issue_date'           => ['issue_date', 'issue date', 'date'],
            'department'           => ['department', 'dept'],
            'order_number'         => ['order_number', 'order id', 'order_id', 'order #'],
        ];

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim((string) $h)), $row);
                continue;
            }

            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($headers, array_pad($row, count($headers), ''));

            $get = function (string $field) use ($data, $map): ?string {
                foreach ($map[$field] ?? [$field] as $alias) {
                    if (array_key_exists($alias, $data) && trim((string) $data[$alias]) !== '') {
                        return trim((string) $data[$alias]);
                    }
                }
                return null;
            };

            $sku = $get('sku');
            if (!$sku) {
                $skipped++;
                $errors[] = 'Row skipped: SKU is empty.';
                continue;
            }

            $qty = $get('qty');
            if ($qty === null || !is_numeric($qty)) {
                $skipped++;
                $errors[] = "Row skipped (SKU={$sku}): QTY is missing or not numeric.";
                continue;
            }

            $issue = $get('issue');
            if (!$issue) {
                $skipped++;
                $errors[] = "Row skipped (SKU={$sku}): Root Cause Found / Issue is required.";
                continue;
            }

            try {
                $now     = now();
                $payload = [
                    'sku'                  => $sku,
                    'qty'                  => (float) $qty,
                    'order_qty'            => $get('order_qty') !== null ? (float) $get('order_qty') : null,
                    'parent'               => $get('parent'),
                    'marketplace_1'        => $get('marketplace_1'),
                    'marketplace_2'        => $get('marketplace_2'),
                    'what_happened'        => $get('what_happened'),
                    'issue'                => $issue,
                    'issue_remark'         => $get('issue_remark'),
                    'action_1'             => $get('action_1'),
                    'action_1_remark'      => $get('action_1_remark'),
                    'replacement_tracking' => $get('replacement_tracking'),
                    'c_action_1'           => $get('c_action_1'),
                    'c_action_1_remark'    => $get('c_action_1_remark'),
                    'issue_date'           => $get('issue_date'),
                    'department'           => $get('department'),
                    'created_by'           => $createdBy,
                    'created_by_user_id'   => $user?->id,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
                $payload = array_merge($payload, $this->csvImportExtraPayload($get));

                \Illuminate\Support\Facades\DB::transaction(function () use ($payload, $now) {
                    $id = \Illuminate\Support\Facades\DB::table($this->issuesTable())->insertGetId($payload);
                    \Illuminate\Support\Facades\DB::table($this->historyTable())->insert(array_merge($payload, [
                        'orders_on_hold_issue_id' => $id,
                        'event_type'              => 'created',
                        'revision_no'             => 0,
                        'logged_at'               => $now,
                    ]));
                });

                $inserted++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row failed (SKU={$sku}): " . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'message'  => "{$inserted} record(s) imported, {$skipped} skipped.",
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 20),
        ]);
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
