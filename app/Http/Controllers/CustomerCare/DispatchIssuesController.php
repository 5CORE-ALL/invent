<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\InventoryManagement\OutgoingController;
use App\Support\CustomerCareDepartments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DispatchIssuesController extends IssueBoardControllerBase
{
    /**
     * Per-request memoization of expensive Schema::hasTable / hasColumn lookups.
     * Each Schema call hits INFORMATION_SCHEMA (~30-150 ms), so calling them
     * inside the per-row mapping loop costs hundreds of redundant queries.
     * @var array<string, bool>|null
     */
    private ?array $schemaFlags = null;

    private function schemaFlags(): array
    {
        if ($this->schemaFlags !== null) {
            return $this->schemaFlags;
        }
        $issues  = $this->issuesTable();
        $history = $this->historyTable();
        return $this->schemaFlags = [
            'issues_has_image'   => Schema::hasTable($issues)  && Schema::hasColumn($issues,  'image_1_path'),
            'issues_has_claim'   => Schema::hasTable($issues)  && Schema::hasColumn($issues,  'claim_filed'),
            'history_has_image'  => Schema::hasTable($history) && Schema::hasColumn($history, 'image_1_path'),
            'history_has_claim'  => Schema::hasTable($history) && Schema::hasColumn($history, 'claim_filed'),
        ];
    }

    protected function viewName(): string
    {
        return 'customer-care.all_issues';
    }

    /**
     * Augment the shared view data with the list of warehouses so the All
     * Issues modal can render a warehouse picker for the "Outgoing needed?"
     * flow without an extra fetch.
     */
    protected function issueBoardIndexData(): array
    {
        $data = parent::issueBoardIndexData();
        $data['outgoingWarehouses'] = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        return $data;
    }

    /** Second entry point: same datatable and APIs as All Issues, alternate shell/titles. */
    public function dispatchIssueBoard()
    {
        return view('customer-care.dispatch', $this->issueBoardIndexData());
    }

    /** Same datatable and APIs as All Issues, filtered to the "Dispatch" department only. */
    public function dispatchOnlyBoard()
    {
        return view('customer-care.dispatch_issues_only', $this->issueBoardIndexData());
    }

    /** Same data and APIs as All Issues (`dispatch_issue_issues`), alternate titles. */
    public function carrierAndClaimBoard()
    {
        return view('customer-care.carrier_and_claim', $this->issueBoardIndexData());
    }

    /** Same board as Carrier and Claim, filtered to department "Carrier Issue" (`dispatch_issue_issues`). */
    public function carrierIssueBoard()
    {
        return view('customer-care.carrier_issue', $this->issueBoardIndexData());
    }

    /** Tabulator board: same data and APIs as All Issues (`dispatch_issue_issues`), filtered to the "Chargeback" department. */
    public function chargebackBoard()
    {
        return view('customer-care.chargeback_issues', $this->issueBoardIndexData());
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
            'order_number'           => 'required|string|max:255',
            'refund_amount'          => 'nullable|numeric|min:0',
            'total_loss'             => 'nullable|numeric',
            'tracking_number'        => 'nullable|string|max:50',
            'issue_link'             => 'nullable|string|max:500',
            // Action sub-fields (see all_issues modal)
            'refund_type'            => 'nullable|in:partial,full',
            'replacement_sku'        => 'nullable|string|max:128',
            'replacement_qty_sending' => 'nullable|numeric|min:0',
            'outgoing_needed'        => 'nullable|boolean',
            'outgoing_warehouse_id'  => 'nullable|integer|exists:warehouses,id',
            // Replacement / Alternate Sent tracking input is capped at 30 chars in the UI;
            // we reuse `replacement_tracking` (varchar 50 in DB) but enforce 30 here.
            'replacement_tracking'   => 'nullable|string|max:30',
            // Issue? sub-fields (driven by what_happened)
            'wrong_sent_sku'         => 'nullable|string|max:128',
            'issue_notes'            => 'nullable|string|max:200',
            'qty_mismatch_type'      => 'nullable|in:less,more',
            'qty_sent'               => 'nullable|numeric|min:0',
            'qty_ordered'            => 'nullable|numeric|min:0',
        ];
    }

    protected function buildExtraPayload(array $validated): array
    {
        $tn = isset($validated['tracking_number']) ? trim((string) $validated['tracking_number']) : '';
        $il = isset($validated['issue_link']) ? trim((string) $validated['issue_link']) : '';
        $rsku = isset($validated['replacement_sku']) ? trim((string) $validated['replacement_sku']) : '';
        $rtype = isset($validated['refund_type']) ? trim((string) $validated['refund_type']) : '';

        // Only persist sub-fields when the matching Action is selected; otherwise
        // null them out so a previous Action's data doesn't linger after a switch.
        $action = isset($validated['action_1']) ? trim((string) $validated['action_1']) : '';
        $isRefund      = strcasecmp($action, 'Refund') === 0;
        $isReplacement = strcasecmp($action, 'Replacement') === 0 || strcasecmp($action, 'Alternate Sent') === 0;

        // Same idea for the "Issue?" sub-fields (column `what_happened`).
        $whatHappened = isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : '';
        $isWrongItem  = strcasecmp($whatHappened, 'Wrong Item Sent') === 0;
        $isWrongQty   = strcasecmp($whatHappened, 'Wrong Quantity Sent') === 0;
        $wrongSku     = isset($validated['wrong_sent_sku']) ? trim((string) $validated['wrong_sent_sku']) : '';
        $issueNotes   = isset($validated['issue_notes']) ? trim((string) $validated['issue_notes']) : '';
        $qtyType      = isset($validated['qty_mismatch_type']) ? trim((string) $validated['qty_mismatch_type']) : '';

        return [
            'order_number'            => isset($validated['order_number']) ? trim((string) $validated['order_number']) : null,
            'refund_amount'           => $isRefund && isset($validated['refund_amount']) ? (float) $validated['refund_amount'] : null,
            'refund_type'             => $isRefund && in_array($rtype, ['partial', 'full'], true) ? $rtype : null,
            'total_loss'              => isset($validated['total_loss']) ? (float) $validated['total_loss'] : null,
            'tracking_number'         => $tn !== '' ? $tn : null,
            'issue_link'              => $il !== '' ? $il : null,
            'replacement_sku'         => $isReplacement && $rsku !== '' ? $rsku : null,
            'replacement_qty_sending' => $isReplacement && isset($validated['replacement_qty_sending']) ? (float) $validated['replacement_qty_sending'] : null,
            'outgoing_needed'         => $isReplacement ? (bool) ($validated['outgoing_needed'] ?? false) : false,
            'outgoing_warehouse_id'   => $isReplacement && (bool) ($validated['outgoing_needed'] ?? false) && isset($validated['outgoing_warehouse_id'])
                ? (int) $validated['outgoing_warehouse_id']
                : null,
            // Issue? sub-fields:
            'wrong_sent_sku'          => $isWrongItem && $wrongSku !== '' ? $wrongSku : null,
            'issue_notes'             => $isWrongItem && $issueNotes !== '' ? $issueNotes : null,
            'qty_mismatch_type'       => $isWrongQty && in_array($qtyType, ['less', 'more'], true) ? $qtyType : null,
            'qty_sent'                => $isWrongQty && isset($validated['qty_sent']) && $validated['qty_sent'] !== '' ? (float) $validated['qty_sent'] : null,
            'qty_ordered'             => $isWrongQty && isset($validated['qty_ordered']) && $validated['qty_ordered'] !== '' ? (float) $validated['qty_ordered'] : null,
        ];
    }

    protected function extraRowFields(object $row): array
    {
        return array_merge(
            $this->dispatchImageSliceIfPresent($row, $this->issuesTable()),
            [
                'order_number'            => $row->order_number ?? null,
                'refund_amount'           => $row->refund_amount !== null ? (float) $row->refund_amount : null,
                'refund_type'             => $row->refund_type ?? null,
                'total_loss'              => $row->total_loss !== null ? (float) $row->total_loss : null,
                'tracking_number'         => $row->tracking_number ?? null,
                'issue_link'              => $row->issue_link ?? null,
                'group_id'                => $row->group_id ?? null,
                'replacement_sku'         => $row->replacement_sku ?? null,
                'replacement_qty_sending' => isset($row->replacement_qty_sending) && $row->replacement_qty_sending !== null ? (float) $row->replacement_qty_sending : null,
                'outgoing_needed'         => (bool) ($row->outgoing_needed ?? false),
                'outgoing_warehouse_id'   => isset($row->outgoing_warehouse_id) && $row->outgoing_warehouse_id !== null ? (int) $row->outgoing_warehouse_id : null,
                'outgoing_processed_at'   => $row->outgoing_processed_at ?? null,
                'outgoing_inventory_id'   => isset($row->outgoing_inventory_id) && $row->outgoing_inventory_id !== null ? (int) $row->outgoing_inventory_id : null,
                // Issue? sub-fields:
                'wrong_sent_sku'          => $row->wrong_sent_sku ?? null,
                'issue_notes'             => $row->issue_notes ?? null,
                'qty_mismatch_type'       => $row->qty_mismatch_type ?? null,
                'qty_sent'                => isset($row->qty_sent) && $row->qty_sent !== null ? (float) $row->qty_sent : null,
                'qty_ordered'             => isset($row->qty_ordered) && $row->qty_ordered !== null ? (float) $row->qty_ordered : null,
            ],
            $this->dispatchClaimCarrierRowSlice($row, $this->issuesTable())
        );
    }

    protected function extraHistoryRowFields(object $row): array
    {
        return array_merge(
            $this->dispatchImageSliceIfPresent($row, $this->historyTable()),
            [
                'tracking_number' => $row->tracking_number ?? null,
                'issue_link'      => $row->issue_link ?? null,
            ],
            $this->dispatchClaimCarrierRowSlice($row, $this->historyTable())
        );
    }

    protected function validateIssueAttachments(Request $request): void
    {
        $request->validate([
            'image_1' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'image_2' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);
    }

    protected function afterIssueInsert(int $issueId, Request $request): void
    {
        $paths = $this->persistDispatchImagesToDir($request, 'issues/'.$issueId, null);
        if ($paths === []) {
            return;
        }

        DB::table($this->issuesTable())->where('id', $issueId)->update($paths);
        $hid = DB::table($this->historyTable())
            ->where('orders_on_hold_issue_id', $issueId)
            ->where('event_type', 'created')
            ->orderByDesc('id')
            ->value('id');
        if ($hid) {
            DB::table($this->historyTable())->where('id', $hid)->update($paths);
        }
    }

    protected function afterIssueUpdate(int $issueId, Request $request, object $existingIssueRow): void
    {
        $paths = $this->persistDispatchImagesToDir($request, 'issues/'.$issueId, $existingIssueRow);
        if ($paths === []) {
            return;
        }

        DB::table($this->issuesTable())->where('id', $issueId)->update($paths);
        $hid = DB::table($this->historyTable())->where('orders_on_hold_issue_id', $issueId)->orderByDesc('id')->value('id');
        if ($hid) {
            DB::table($this->historyTable())->where('id', $hid)->update($paths);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function dispatchImageSliceIfPresent(object $row, string $table): array
    {
        $flags = $this->schemaFlags();
        $key = $table === $this->historyTable() ? 'history_has_image' : 'issues_has_image';
        if (empty($flags[$key])) {
            return [];
        }

        $p1 = $row->image_1_path ?? null;
        $p2 = $row->image_2_path ?? null;

        return [
            'image_1_path' => $p1,
            'image_2_path' => $p2,
            'image_1_url'  => $this->publicIssueImageUrl($p1),
            'image_2_url'  => $this->publicIssueImageUrl($p2),
        ];
    }

    private function publicIssueImageUrl(?string $path): ?string
    {
        if ($path === null || trim((string) $path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');

        // Use the current request’s root URL so <img src> works when APP_URL
        // does not match the browser host (e.g. localhost vs 127.0.0.1) or when
        // the app is served from a subpath.
        if (app()->bound('request') && request() && ! app()->runningInConsole()) {
            return rtrim(request()->root(), '/') . '/storage/' . $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array<string, string>
     */
    private function persistDispatchImagesToDir(Request $request, string $relativeDir, ?object $existingRow): array
    {
        if (! Schema::hasColumn($this->issuesTable(), 'image_1_path')) {
            return [];
        }

        $disk = 'public';
        $prefix = 'customer-care/dispatch-issues/'.$relativeDir;
        $out = [];

        foreach ([
            'image_1' => 'image_1_path',
            'image_2' => 'image_2_path',
        ] as $field => $column) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $old = $existingRow ? ($existingRow->{$column} ?? null) : null;
            if (is_string($old) && $old !== '' && Storage::disk($disk)->exists($old)) {
                Storage::disk($disk)->delete($old);
            }
            $out[$column] = $request->file($field)->store($prefix, $disk);
        }

        return $out;
    }

    protected function csvImportColumnMap(): array
    {
        return [
            'tracking_number' => ['tracking_number', 'tracking', 'tracking number'],
            'issue_link'      => ['issue_link', 'link', 'url'],
        ];
    }

    protected function csvImportExtraPayload(callable $get): array
    {
        $v = $get('tracking_number');

        $link = $get('issue_link');

        return [
            'tracking_number' => $v !== null && $v !== '' ? $v : null,
            'issue_link'      => $link !== null && $link !== '' ? $link : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchClaimCarrierRowSlice(object $row, string $table): array
    {
        $flags = $this->schemaFlags();
        $key = $table === $this->historyTable() ? 'history_has_claim' : 'issues_has_claim';
        if (empty($flags[$key])) {
            return [];
        }

        $amp = $row->amp_usd ?? null;

        return [
            'claim_filed' => (bool) ($row->claim_filed ?? false),
            'amp_usd' => $amp !== null && trim((string) $amp) !== '' ? (string) $amp : null,
            'claim_received' => (bool) ($row->claim_received ?? false),
            'issue_carrier' => isset($row->issue_carrier) && $row->issue_carrier !== null && trim((string) $row->issue_carrier) !== ''
                ? trim((string) $row->issue_carrier)
                : null,
        ];
    }

    private static function parseAmpUsdAmount(mixed $raw): float
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return 0.0;
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '.' || $s === '-') {
            return 0.0;
        }
        $v = (float) $s;

        return is_finite($v) ? round($v, 2) : 0.0;
    }

    public function claimsStats(Request $request): JsonResponse
    {
        $empty = [
            'filed' => ['count' => 0, 'amount' => 0.0],
            'pending' => ['count' => 0, 'amount' => 0.0],
            'received' => ['count' => 0, 'amount' => 0.0],
        ];
        if (! Schema::hasColumn($this->issuesTable(), 'claim_filed')) {
            return response()->json($empty);
        }

        $department = trim((string) $request->query('department', 'Carrier'));
        if ($department === '') {
            $department = 'Carrier';
        }

        $query = DB::table($this->issuesTable())
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            });
        CustomerCareDepartments::applyWhereDepartmentMatches($query, 'department', $department);
        $rows = $query->get(['claim_filed', 'claim_received', 'amp_usd']);

        $filedC = 0;
        $filedA = 0.0;
        $pendingC = 0;
        $pendingA = 0.0;
        $receivedC = 0;
        $receivedA = 0.0;
        foreach ($rows as $r) {
            $amt = self::parseAmpUsdAmount($r->amp_usd ?? null);
            $filed = (bool) ($r->claim_filed ?? false);
            $rec = (bool) ($r->claim_received ?? false);
            if ($filed) {
                $filedC++;
                $filedA += $amt;
            }
            if ($filed && ! $rec) {
                $pendingC++;
                $pendingA += $amt;
            }
            if ($rec) {
                $receivedC++;
                $receivedA += $amt;
            }
        }

        return response()->json([
            'filed' => ['count' => $filedC, 'amount' => round($filedA, 2)],
            'pending' => ['count' => $pendingC, 'amount' => round($pendingA, 2)],
            'received' => ['count' => $receivedC, 'amount' => round($receivedA, 2)],
        ]);
    }

    public function updateClaimFiled(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasColumn($this->issuesTable(), 'claim_filed')) {
            return response()->json(['message' => 'Not available.'], 503);
        }
        $validated = $request->validate(['claim_filed' => 'required|boolean']);
        $next = (bool) $validated['claim_filed'];

        $updated = DB::table($this->issuesTable())
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->update(['claim_filed' => $next, 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['message' => 'Updated.', 'claim_filed' => $next]);
    }

    public function updateClaimReceived(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasColumn($this->issuesTable(), 'claim_received')) {
            return response()->json(['message' => 'Not available.'], 503);
        }
        $validated = $request->validate(['claim_received' => 'required|boolean']);
        $next = (bool) $validated['claim_received'];

        $updated = DB::table($this->issuesTable())
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->update(['claim_received' => $next, 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['message' => 'Updated.', 'claim_received' => $next]);
    }

    public function updateAmpUsd(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasColumn($this->issuesTable(), 'amp_usd')) {
            return response()->json(['message' => 'Not available.'], 503);
        }
        $validated = $request->validate(['amp_usd' => 'nullable|string|max:6']);
        $raw = isset($validated['amp_usd']) ? trim((string) $validated['amp_usd']) : '';
        $value = $raw === '' ? null : $raw;

        $updated = DB::table($this->issuesTable())
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->update(['amp_usd' => $value, 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['message' => 'Updated.', 'amp_usd' => $value]);
    }

    public function updateIssueCarrier(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasColumn($this->issuesTable(), 'issue_carrier')) {
            return response()->json(['message' => 'Not available.'], 503);
        }
        $validated = $request->validate(['issue_carrier' => 'nullable|string|max:20']);
        $raw = isset($validated['issue_carrier']) ? trim((string) $validated['issue_carrier']) : '';
        if ($raw === '') {
            $normalized = null;
        } else {
            $normalized = strtoupper($raw);
            if (! in_array($normalized, ['USPS', 'UPS', 'FEDEX', 'GOFO'], true)) {
                return response()->json(['message' => 'Invalid carrier.'], 422);
            }
        }

        $updated = DB::table($this->issuesTable())
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->update(['issue_carrier' => $normalized, 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['message' => 'Updated.', 'issue_carrier' => $normalized]);
    }

    /**
     * Replacement / Alternate-Sent SKU lookup for the Action sub-section.
     * Returns the SKU image (product_master) + the Shopify available qty.
     * This is the one place the All Issues page is allowed to read inventory
     * from `shopify_skus`, by explicit product decision.
     */
    public function replacementSkuDetails(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['found' => false, 'message' => 'SKU is required.'], 422);
        }

        $pm = DB::table('product_master')
            ->select('sku', 'parent', 'main_image', 'image1', 'Values as values_json')
            ->where('sku', $sku)
            ->first();

        $shopify = DB::table('shopify_skus')
            ->select(DB::raw('COALESCE(inv, 0) as qty'), 'image_src')
            ->where('sku', $sku)
            ->first();

        $normalizeImage = static function ($path) {
            $p = trim((string) ($path ?? ''));
            if ($p === '') return null;
            if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) return $p;
            return '/' . ltrim($p, '/');
        };

        $values = [];
        if ($pm && isset($pm->values_json) && is_string($pm->values_json) && trim($pm->values_json) !== '') {
            $decoded = json_decode($pm->values_json, true);
            if (is_array($decoded)) {
                $values = $decoded;
            }
        }

        $imageUrl = $normalizeImage($shopify?->image_src)
            ?? $normalizeImage($values['image_path'] ?? null)
            ?? $normalizeImage($pm?->main_image ?? null)
            ?? $normalizeImage($pm?->image1 ?? null);

        $found = (bool) ($pm || $shopify);

        return response()->json([
            'found'           => $found,
            'sku'             => $sku,
            'parent'          => $pm?->parent,
            'qty_available'   => (float) ($shopify?->qty ?? 0),
            'image_url'       => $imageUrl,
        ]);
    }

    /**
     * Override skuDetails so the All Issues modal autocomplete uses ONLY
     * product_master — no shopify_skus inventory lookup. Returns parent and
     * an image URL from the internal catalogue. qty is left at 0; the user
     * enters Order QTY manually in the modal.
     */
    public function skuDetails(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['found' => false, 'message' => 'SKU is required.'], 422);
        }

        $row = DB::table('product_master')
            ->select('sku', 'parent', 'Values as values_json', 'main_image', 'image1')
            ->where('sku', $sku)
            ->first();

        if (! $row) {
            return response()->json(['found' => false, 'message' => 'SKU not found.']);
        }

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

        $imageUrl = $normalizeImage($values['image_path'] ?? null)
            ?? $normalizeImage($row->main_image ?? null)
            ?? $normalizeImage($row->image1 ?? null);

        return response()->json([
            'found'     => true,
            'sku'       => $row->sku,
            'parent'    => $row->parent,
            'qty'       => 0,
            'image_url' => $imageUrl,
        ]);
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

        // Build image map from product_master only. We deliberately do NOT
        // query shopify_skus here — All Issues uses the internal product
        // catalogue as the single image source. Plain whereIn on the indexed
        // `sku` column keeps this fast.
        $rawSkus = $rows->pluck('sku')
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->values()
            ->all();

        $pmMap = [];
        if ($rawSkus !== []) {
            DB::table('product_master')
                ->whereIn('sku', $rawSkus)
                ->select('sku', 'main_image', 'image1')
                ->get()
                ->each(function ($r) use (&$pmMap) {
                    $pmMap[strtolower(trim((string) $r->sku))] = [
                        'main' => $r->main_image ?? null,
                        'alt'  => $r->image1 ?? null,
                    ];
                });
        }

        $normalizeImage = static function ($path) {
            $p = trim((string) ($path ?? ''));
            if ($p === '') return null;
            if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) return $p;
            return '/' . ltrim($p, '/');
        };

        $imageMap = [];
        foreach ($pmMap as $key => $imgs) {
            $imageMap[$key] = $normalizeImage($imgs['main'] ?? null)
                ?? $normalizeImage($imgs['alt'] ?? null);
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
     * Override store to support:
     *   1. Multi-SKU entries (one Order Number, several SKUs).
     *   2. Multi-Department entries: when N departments are selected we insert
     *      a separate row per department so other dept-filtered pages each see
     *      their own clean record. All sibling rows share one group_id.
     *
     * Total rows inserted = (number of SKUs) × (number of departments).
     */
    public function store(Request $request): JsonResponse
    {
        $skusPayload = $request->input('skus');

        // Normalise single-SKU mode to the same shape as multi-SKU mode so the
        // dept-split loop below has a single code path.
        if (! is_array($skusPayload) || count($skusPayload) === 0) {
            $skusPayload = [[
                'sku'       => $request->input('sku'),
                'qty'       => $request->input('qty', 0),
                'order_qty' => $request->input('order_qty'),
                'parent'    => $request->input('parent'),
            ]];
        }

        return $this->storeMultiSku($request, $skusPayload);
    }

    private function storeMultiSku(Request $request, array $skusPayload): JsonResponse
    {
        $request->validate([
            'issue'              => 'nullable|string|max:255',
            'order_number'       => 'required|string|max:255',
            'refund_amount'      => 'nullable|numeric|min:0',
            'total_loss'         => 'nullable|numeric',
            'marketplace_1'      => 'nullable|string|max:255',
            'marketplace_2'      => 'nullable|string|max:255',
            'what_happened'      => 'nullable|string|max:100',
            'issue_remark'       => 'nullable|string|max:255',
            'action_1'           => 'nullable|string|max:255',
            'action_1_remark'      => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'tracking_number'    => 'nullable|string|max:50',
            'issue_link'         => 'nullable|string|max:500',
            'c_action_1'         => 'nullable|string|max:255',
            'c_action_1_remark'  => 'nullable|string|max:255',
            'issue_date'         => 'nullable|string|max:100',
            'department'         => 'required|array|min:1',
            'department.*'       => 'required|string|max:100',
        ]);

        $this->validateIssueAttachments($request);

        $depts = CustomerCareDepartments::normalizeStringList($request->input('department', []));
        if (count($depts) === 0) {
            return response()->json([
                'message' => 'Department is required.',
                'errors'  => ['department' => ['Select at least one department.']],
            ], 422);
        }

        $user      = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System')) ?: 'System';
        $groupId   = Str::uuid()->toString();
        $now       = now();
        $tz        = config('app.timezone');

        $imagePaths = $this->persistDispatchImagesToDir($request, 'groups/'.$groupId, null);

        // Shared fields for every (SKU x Dept) row in the group. The `department`
        // column is set per row inside the loop below — one dept per row.
        $sharedPayload = array_merge([
            'group_id'             => $groupId,
            'order_number'         => $request->input('order_number') ? trim($request->input('order_number')) : null,
            'refund_amount'        => $request->input('refund_amount') !== null && $request->input('refund_amount') !== '' ? (float) $request->input('refund_amount') : null,
            'total_loss'           => $request->input('total_loss') !== null && $request->input('total_loss') !== '' ? (float) $request->input('total_loss') : null,
            'marketplace_1'        => $request->input('marketplace_1') ? trim($request->input('marketplace_1')) : null,
            'marketplace_2'        => $request->input('marketplace_2') ? trim($request->input('marketplace_2')) : null,
            'what_happened'        => $request->input('what_happened') ? trim($request->input('what_happened')) : null,
            'issue'                => $request->filled('issue') ? trim((string) $request->input('issue')) : null,
            'issue_remark'         => $request->input('issue_remark') ? trim($request->input('issue_remark')) : null,
            'action_1'             => $request->input('action_1') ? trim($request->input('action_1')) : null,
            'action_1_remark'      => $request->input('action_1_remark') ? trim($request->input('action_1_remark')) : null,
            'replacement_tracking' => $request->input('replacement_tracking') ? trim($request->input('replacement_tracking')) : null,
            'tracking_number'      => $request->input('tracking_number') ? trim($request->input('tracking_number')) : null,
            'issue_link'           => $request->input('issue_link') ? trim($request->input('issue_link')) : null,
            'c_action_1'           => $request->input('c_action_1') ? trim($request->input('c_action_1')) : null,
            'c_action_1_remark'    => $request->input('c_action_1_remark') ? trim($request->input('c_action_1_remark')) : null,
            'close_note'           => null,
            'issue_date'           => $request->input('issue_date') ? trim($request->input('issue_date')) : null,
            'created_by'           => $createdBy,
            'created_by_user_id'   => $user?->id,
            'created_at'           => $now,
            'updated_at'           => $now,
        ], $imagePaths);

        $insertedRows = [];

        DB::transaction(function () use ($skusPayload, $depts, $sharedPayload, $now, $tz, &$insertedRows) {
            foreach ($skusPayload as $skuEntry) {
                $sku = trim((string) ($skuEntry['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }

                $skuFields = [
                    'sku'       => $sku,
                    'qty'       => isset($skuEntry['qty']) && $skuEntry['qty'] !== '' ? (float) $skuEntry['qty'] : 0,
                    'order_qty' => isset($skuEntry['order_qty']) && $skuEntry['order_qty'] !== '' ? (float) $skuEntry['order_qty'] : null,
                    'parent'    => isset($skuEntry['parent']) ? trim((string) $skuEntry['parent']) : null,
                ];

                // One row per (SKU, department) so dept-filtered pages stay clean.
                foreach ($depts as $dept) {
                    $payload = array_merge($sharedPayload, $skuFields, [
                        'department' => CustomerCareDepartments::encode([$dept]),
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
                ] + $this->extraRowFields($row);
                }
            }
        });

        if (empty($insertedRows)) {
            return response()->json(['message' => 'No valid SKUs provided.'], 422);
        }

        // Outgoing trigger: when "Outgoing needed?" is checked on a Replacement /
        // Alternate Sent issue, mirror the row to /outgoing-view by adjusting
        // Shopify inventory and creating an `inventories` row. We pass every
        // sibling row id from this group so they all get marked as processed.
        $outgoingMsg     = null;
        $outgoingWarning = null;
        try {
            $validatedFromRequest = [
                'action_1'                => $request->input('action_1'),
                'outgoing_needed'         => $request->boolean('outgoing_needed'),
                'replacement_sku'         => $request->input('replacement_sku'),
                'replacement_qty_sending' => $request->input('replacement_qty_sending'),
                'outgoing_warehouse_id'   => $request->input('outgoing_warehouse_id'),
                'replacement_tracking'    => $request->input('replacement_tracking'),
            ];
            $rowIds = array_map(fn ($r) => (int) $r['id'], $insertedRows);
            $outgoing = $this->fireOutgoingForIssue(
                $request,
                $validatedFromRequest,
                $rowIds,
                $request->input('order_number')
            );
            if ($outgoing['success']) {
                $outgoingMsg = 'Shopify inventory adjusted by -' . (int) $request->input('replacement_qty_sending') .
                    ' and a row was added to /outgoing-view.';
            } elseif ($outgoing['error'] && $request->boolean('outgoing_needed')) {
                $outgoingWarning = 'Issue saved, but outgoing could NOT be processed: ' . $outgoing['error'];
            }
        } catch (\Throwable $e) {
            $outgoingWarning = 'Issue saved, but outgoing failed: ' . $e->getMessage();
        }

        return response()->json([
            'message'          => count($insertedRows) . ' record(s) saved as 1 error group.'
                . ($outgoingMsg ? ' ' . $outgoingMsg : ''),
            'rows'             => $insertedRows,
            'group_id'         => $groupId,
            'outgoing_warning' => $outgoingWarning,
        ], 201);
    }

    /**
     * Override update so that changing the department selection on an existing
     * issue is reconciled across the dept-split sibling rows:
     *   - existing rows whose dept is still selected → updated with new field values
     *   - existing rows whose dept was removed       → archived (soft-deleted)
     *   - newly-added depts not yet represented     → inserted as new rows in the
     *                                                 same group, sharing the SKU
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $existing = DB::table($this->issuesTable())->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $validated = $this->validatePayload($request);
        $newDepts  = CustomerCareDepartments::normalizeStringList($validated['department']);
        if (count($newDepts) === 0) {
            return response()->json([
                'message' => 'Department is required.',
                'errors'  => ['department' => ['Select at least one department.']],
            ], 422);
        }

        $user      = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System')) ?: 'System';

        // Sibling set: every active row with the same group_id AND same SKU.
        // If the row predates the dept-split refactor (no group_id) we treat it
        // as a 1-row group so the same logic still applies.
        $sku = trim((string) $validated['sku']);
        $groupId = $existing->group_id ?: \Illuminate\Support\Str::uuid()->toString();
        $siblingsQuery = DB::table($this->issuesTable())
            ->where('sku', $sku)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            });
        if ($existing->group_id) {
            $siblingsQuery->where('group_id', $existing->group_id);
        } else {
            $siblingsQuery->where('id', $id);
        }
        $siblings = $siblingsQuery->get();

        // Build the field-only payload that's identical for every sibling row
        // (excludes sku/qty/order_qty/parent because those are stable per row,
        // and excludes the department which is set per row below).
        $fieldPayload = [
            'qty'                  => (float) $validated['qty'],
            'order_qty'            => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
            'parent'               => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
            'marketplace_1'        => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
            'marketplace_2'        => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
            'what_happened'        => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
            'issue'                => trim($validated['issue']),
            'issue_remark'         => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
            'action_1'             => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
            'action_1_remark'      => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
            'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
            'c_action_1'           => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
            'c_action_1_remark'    => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
            'close_note'           => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
            'issue_date'           => isset($validated['issue_date']) ? trim((string) $validated['issue_date']) : null,
        ];
        $fieldPayload = array_merge($fieldPayload, $this->buildExtraPayload($validated));

        $now = now();

        DB::transaction(function () use ($id, $siblings, $newDepts, $sku, $groupId, $existing, $fieldPayload, $actorName, $user, $request, $now) {
            // Index existing siblings by their (single) department.
            $existingByDept = [];
            foreach ($siblings as $s) {
                $deptList = CustomerCareDepartments::decode($s->department ?? null);
                $deptKey = $deptList[0] ?? '';
                if ($deptKey !== '' && ! isset($existingByDept[$deptKey])) {
                    $existingByDept[$deptKey] = $s;
                }
            }

            $newDeptSet = array_fill_keys($newDepts, true);

            // 1. Update siblings whose dept is still selected; archive the rest.
            foreach ($siblings as $s) {
                $sDept = (CustomerCareDepartments::decode($s->department ?? null)[0] ?? '');
                if ($sDept !== '' && isset($newDeptSet[$sDept])) {
                    $payload = array_merge($fieldPayload, [
                        'sku'        => $sku,
                        'department' => CustomerCareDepartments::encode([$sDept]),
                        'updated_at' => $now,
                    ]);
                    DB::table($this->issuesTable())->where('id', $s->id)->update($payload);

                    $nextRevision = ((int) DB::table($this->historyTable())
                        ->where('orders_on_hold_issue_id', $s->id)
                        ->max('revision_no')) + 1;
                    DB::table($this->historyTable())->insert(array_merge($payload, [
                        'orders_on_hold_issue_id' => $s->id,
                        'event_type'              => 'updated',
                        'revision_no'             => $nextRevision,
                        'created_by'              => $actorName,
                        'created_by_user_id'      => $user?->id,
                        'logged_at'               => $now,
                        'created_at'              => $now,
                    ]));

                    $this->afterIssueUpdate((int) $s->id, $request, $s);
                } else {
                    // Department removed → archive this sibling.
                    DB::table($this->issuesTable())->where('id', $s->id)->update([
                        'is_archived' => true,
                        'archived_at' => $now,
                        'archived_by' => $actorName,
                        'updated_at'  => $now,
                    ]);

                    $nextRevision = ((int) DB::table($this->historyTable())
                        ->where('orders_on_hold_issue_id', $s->id)
                        ->max('revision_no')) + 1;
                    DB::table($this->historyTable())->insert([
                        'orders_on_hold_issue_id' => $s->id,
                        'event_type'              => 'archived',
                        'revision_no'             => $nextRevision,
                        'sku'                     => $s->sku,
                        'qty'                     => (float) $s->qty,
                        'order_qty'               => $s->order_qty !== null ? (float) $s->order_qty : null,
                        'parent'                  => $s->parent,
                        'marketplace_1'           => $s->marketplace_1 ?? null,
                        'marketplace_2'           => $s->marketplace_2 ?? null,
                        'what_happened'           => $s->what_happened ?? null,
                        'issue'                   => $s->issue,
                        'issue_remark'            => $s->issue_remark ?? null,
                        'action_1'                => $s->action_1 ?? null,
                        'action_1_remark'         => $s->action_1_remark ?? null,
                        'replacement_tracking'    => $s->replacement_tracking ?? null,
                        'c_action_1'              => $s->c_action_1 ?? null,
                        'c_action_1_remark'       => $s->c_action_1_remark ?? null,
                        'close_note'              => $s->close_note ?? null,
                        'department'              => $s->department,
                        'created_by'              => $actorName,
                        'created_by_user_id'      => $user?->id,
                        'logged_at'               => $now,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ]);
                }
            }

            // 2. Insert rows for newly-added depts.
            foreach ($newDepts as $newDept) {
                if (isset($existingByDept[$newDept])) {
                    continue;
                }
                $payload = array_merge($fieldPayload, [
                    'sku'                => $sku,
                    'group_id'           => $groupId,
                    'department'         => CustomerCareDepartments::encode([$newDept]),
                    'created_by'         => $actorName,
                    'created_by_user_id' => $user?->id,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
                $newId = DB::table($this->issuesTable())->insertGetId($payload);
                DB::table($this->historyTable())->insert(array_merge($payload, [
                    'orders_on_hold_issue_id' => $newId,
                    'event_type'              => 'created',
                    'revision_no'             => 0,
                    'logged_at'               => $now,
                ]));
            }

            // Backfill group_id on the original row if it was missing.
            if (! $existing->group_id) {
                DB::table($this->issuesTable())->where('id', $id)->update(['group_id' => $groupId]);
            }
        });

        // Outgoing trigger on update — only fires if Outgoing needed is checked
        // and the group hasn't already been processed (idempotent).
        $outgoingMsg     = null;
        $outgoingWarning = null;
        if ($request->boolean('outgoing_needed')) {
            try {
                $rowIds = DB::table($this->issuesTable())
                    ->where('group_id', $groupId)
                    ->where('sku', $sku)
                    ->where(function ($q) {
                        $q->whereNull('is_archived')->orWhere('is_archived', false);
                    })
                    ->pluck('id')
                    ->all();

                $outgoing = $this->fireOutgoingForIssue($request, [
                    'action_1'                => $request->input('action_1'),
                    'outgoing_needed'         => true,
                    'replacement_sku'         => $request->input('replacement_sku'),
                    'replacement_qty_sending' => $request->input('replacement_qty_sending'),
                    'outgoing_warehouse_id'   => $request->input('outgoing_warehouse_id'),
                    'replacement_tracking'    => $request->input('replacement_tracking'),
                ], array_map('intval', $rowIds), $request->input('order_number'));

                if ($outgoing['success']) {
                    $outgoingMsg = 'Shopify inventory adjusted by -' . (int) $request->input('replacement_qty_sending') .
                        ' and a row was added to /outgoing-view.';
                } elseif ($outgoing['error'] && stripos($outgoing['error'], 'already processed') === false) {
                    $outgoingWarning = 'Issue updated, but outgoing could NOT be processed: ' . $outgoing['error'];
                }
            } catch (\Throwable $e) {
                $outgoingWarning = 'Issue updated, but outgoing failed: ' . $e->getMessage();
            }
        }

        return response()->json([
            'message'          => 'Issue updated. Department changes have been reconciled across sibling rows.'
                . ($outgoingMsg ? ' ' . $outgoingMsg : ''),
            'group_id'         => $groupId,
            'outgoing_warning' => $outgoingWarning,
        ]);
    }

    /**
     * Calls /outgoing-view's pipeline once for an issue (or a group of dept-split
     * sibling rows that all share the same SKU/qty). Adjusts Shopify inventory
     * by -$qty and writes an `inventories` row of type=outgoing. Marks every
     * affected issue row with `outgoing_processed_at` + `outgoing_inventory_id`
     * so re-saves are idempotent.
     *
     * @param  int[]  $issueRowIds  Issue row IDs to flag as processed on success.
     * @return array{success: bool, error: string|null, inventory_id: int|null}
     */
    private function fireOutgoingForIssue(
        Request $request,
        array $validated,
        array $issueRowIds,
        ?string $orderNumber = null
    ): array {
        if (empty($issueRowIds)) {
            return ['success' => false, 'error' => 'No issue rows to mark.', 'inventory_id' => null];
        }

        $action  = isset($validated['action_1']) ? trim((string) $validated['action_1']) : '';
        $isReplacement = strcasecmp($action, 'Replacement') === 0 || strcasecmp($action, 'Alternate Sent') === 0;
        $needed  = (bool) ($validated['outgoing_needed'] ?? false);
        if (! $isReplacement || ! $needed) {
            return ['success' => false, 'error' => 'Outgoing not requested.', 'inventory_id' => null];
        }

        $sku  = isset($validated['replacement_sku']) ? trim((string) $validated['replacement_sku']) : '';
        $qty  = isset($validated['replacement_qty_sending']) ? (int) $validated['replacement_qty_sending'] : 0;
        $whId = (int) ($validated['outgoing_warehouse_id'] ?? 0);
        if ($sku === '' || $qty <= 0 || $whId <= 0) {
            return ['success' => false, 'error' => 'Replacement SKU, qty and warehouse are required.', 'inventory_id' => null];
        }

        // Skip if any sibling has already been processed (idempotency guard).
        $alreadyProcessed = DB::table($this->issuesTable())
            ->whereIn('id', $issueRowIds)
            ->whereNotNull('outgoing_processed_at')
            ->exists();
        if ($alreadyProcessed) {
            return ['success' => false, 'error' => 'Outgoing already processed for this issue.', 'inventory_id' => null];
        }

        $tracking = isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null;
        if ($tracking === '') {
            $tracking = null;
        }

        $outgoing = app(OutgoingController::class)->processOutgoingFromIssue($sku, $qty, [
            'warehouse_id'         => $whId,
            'reason'               => 'Replacement (All Issues)',
            'comment'              => 'Auto from All Issues #' . implode(',', $issueRowIds),
            'replacement_tracking' => $tracking,
            'order_id'             => $orderNumber,
        ]);

        if ($outgoing['success']) {
            DB::table($this->issuesTable())
                ->whereIn('id', $issueRowIds)
                ->update([
                    'outgoing_processed_at' => now(),
                    'outgoing_inventory_id' => $outgoing['inventory_id'],
                    'updated_at'            => now(),
                ]);
        }

        return $outgoing;
    }
}
