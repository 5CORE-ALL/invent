<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\RefundRecord;
use App\Models\RefundRecordEditHistory;
use App\Models\RefundReason;
use App\Models\Supplier;
use App\Models\ChannelMaster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class RefundController extends Controller
{
    public const PERSON_RESPONSIBLE_OPTIONS = [
        'Shivaji', 'Shreya', 'Suman', 'Hritiksha', 'Srimanta',
        'USPS', 'UPS', 'Fedex', 'Platform', 'QC', 'Packaging', 'Pricing',
    ];

    public function index()
    {
        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku')
            ->orderBy('product_master.sku')
            ->get();

        $reasons = RefundReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        $suppliers = Supplier::where('type', 'Supplier')->orderBy('name')->get(['id', 'name']);
        $personResponsibleOptions = self::PERSON_RESPONSIBLE_OPTIONS;
        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('channel')
            ->get(['id', 'channel', 'type']);

        return view('inventory-management.refunds-view', compact(
            'skus',
            'reasons',
            'suppliers',
            'personResponsibleOptions',
            'channels'
        ));
    }

    public function importCsv(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file     = $request->file('file');
        $handle   = fopen($file->getRealPath(), 'r');
        $headers  = null;
        $inserted = 0;
        $skipped  = 0;
        $errors   = [];

        $user      = Auth::user();
        $createdBy = trim((string) ($user?->name ?? 'N/A')) ?: 'N/A';

        $map = [
            'sku'                => ['sku'],
            'qty'                => ['qty', 'quantity'],
            'refund_amt'         => ['refund_amt', 'refund amt', 'refund amount', 'amount'],
            'reason'             => ['reason'],
            'comment'            => ['comment', 'corrective action required', 'corrective_action'],
            'person_responsible' => ['person_responsible', 'person responsible'],
            'order_id'           => ['order_id', 'order id'],
            'channel_name'       => ['channel_name', 'channel name', 'channel'],
            'supplier_name'      => ['supplier_name', 'supplier name', 'supplier'],
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
            if (!$sku) { $skipped++; $errors[] = 'Row skipped: SKU empty.'; continue; }

            $qty = $get('qty');
            if ($qty === null || !is_numeric($qty) || (int)$qty < 1) {
                $skipped++;
                $errors[] = "Row skipped (SKU={$sku}): QTY must be >= 1.";
                continue;
            }

            $refundAmt = $get('refund_amt');
            if ($refundAmt === null || !is_numeric($refundAmt) || (float)$refundAmt < 0) {
                $skipped++;
                $errors[] = "Row skipped (SKU={$sku}): Refund Amt must be >= 0.";
                continue;
            }

            $reason = $get('reason');
            if (!$reason) { $skipped++; $errors[] = "Row skipped (SKU={$sku}): Reason is required."; continue; }

            $personResponsible = $get('person_responsible');
            if (!$personResponsible) { $skipped++; $errors[] = "Row skipped (SKU={$sku}): Person Responsible is required."; continue; }

            // Resolve supplier by name
            $supplierId = null;
            if ($supplierName = $get('supplier_name')) {
                $sup = Supplier::where('type', 'Supplier')
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($supplierName)])
                    ->first();
                if ($sup) {
                    $supplierId = $sup->id;
                }
            }

            // Resolve channel by name
            $channelMasterId = null;
            if ($channelName = $get('channel_name')) {
                $ch = ChannelMaster::whereRaw('LOWER(TRIM(COALESCE(status,""))) = ?', ['active'])
                    ->whereRaw('LOWER(TRIM(channel)) = ?', [strtolower($channelName)])
                    ->first();
                if ($ch) {
                    $channelMasterId = $ch->id;
                }
            }

            $orderId = $get('order_id');
            if ($orderId !== null) {
                $orderId = substr($orderId, 0, 30);
            }

            try {
                RefundRecord::create([
                    'sku'                => $sku,
                    'qty'                => (int) $qty,
                    'refund_amt'         => round((float) $refundAmt, 2),
                    'reason'             => $reason,
                    'comment'            => $get('comment'),
                    'person_responsible' => $personResponsible,
                    'supplier_id'        => $supplierId,
                    'order_id'           => $orderId ?: null,
                    'channel_master_id'  => $channelMasterId,
                    'created_by'         => $createdBy,
                    'is_archived'        => false,
                ]);
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

    public function getReasons()
    {
        $reasons = RefundReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        return response()->json(['reasons' => $reasons]);
    }

    public function storeReason(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $name = trim($request->name);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Reason name is required.'], 422);
        }
        if (RefundReason::where('name', $name)->exists()) {
            return response()->json(['success' => false, 'message' => 'This reason already exists.'], 422);
        }
        $maxOrder = RefundReason::max('sort_order') ?? 0;
        RefundReason::create([
            'name' => $name,
            'sort_order' => $maxOrder + 1,
        ]);
        return response()->json(['success' => true, 'reasons' => RefundReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray()]);
    }

    public function store(Request $request)
    {
        // Backward compatibility: some clients may still send `order_jd` by mistake.
        $this->normalizeOrderIdAlias($request);

        $request->validate([
            'sku' => 'required|array',
            'sku.*' => 'required|string',
            'qty' => 'required|array',
            'qty.*' => 'required|integer|min:1',
            'refund_amt' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'comment' => 'nullable|string|max:80',
            'person_responsible' => ['required', Rule::in(self::PERSON_RESPONSIBLE_OPTIONS)],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('type', 'Supplier'),
            ],
            'order_id' => 'nullable|string|max:30',
            'channel_master_id' => [
                'nullable',
                'integer',
                Rule::exists('channel_master', 'id')->where(function ($q) {
                    $q->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active']);
                }),
            ],
        ]);

        $skus = $request->sku;
        $count = count($skus);
        $reason = $request->reason;
        $comment = $request->filled('comment') ? trim($request->comment) : null;
        $user = Auth::user()->name ?? 'N/A';
        $refundAmt = round((float) $request->input('refund_amt'), 2);
        $personResponsible = $request->person_responsible;
        $supplierId = $request->filled('supplier_id') ? (int) $request->supplier_id : null;
        $orderId = $request->filled('order_id') ? substr(trim((string) $request->order_id), 0, 30) : null;
        if ($orderId === '') {
            $orderId = null;
        }
        $channelMasterId = $request->filled('channel_master_id') ? (int) $request->channel_master_id : null;

        for ($i = 0; $i < $count; $i++) {
            $sku = trim($skus[$i]);
            $qty = (int) $request->qty[$i];

            try {
                RefundRecord::create([
                    'sku' => $sku,
                    'qty' => $qty,
                    'refund_amt' => $refundAmt,
                    'reason' => $reason,
                    'comment' => $comment,
                    'person_responsible' => $personResponsible,
                    'supplier_id' => $supplierId,
                    'order_id' => $orderId,
                    'channel_master_id' => $channelMasterId,
                    'created_by' => $user,
                    'is_archived' => false,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to save record (row ' . ($i + 1) . '): ' . $e->getMessage()], 500);
            }
        }

        $msg = $count === 1
            ? 'Refund record saved. Inventory and Shopify were not changed.'
            : $count . ' refund records saved. Inventory and Shopify were not changed.';

        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function updateReasonAndComment(Request $request)
    {
        // Backward compatibility: some clients may still send `order_jd` by mistake.
        $this->normalizeOrderIdAlias($request);

        $request->validate([
            'id' => 'required|integer|exists:refund_records,id',
            'reason' => 'required|string|max:255',
            'comment' => 'nullable|string|max:80',
            'person_responsible' => ['required', Rule::in(self::PERSON_RESPONSIBLE_OPTIONS)],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('type', 'Supplier'),
            ],
            'order_id' => 'nullable|string|max:30',
            'channel_master_id' => [
                'nullable',
                'integer',
                Rule::exists('channel_master', 'id')->where(function ($q) {
                    $q->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active']);
                }),
            ],
        ]);

        $rec = RefundRecord::with(['supplier', 'channelMaster'])->find($request->id);
        if (!$rec) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $reason = trim($request->reason);
        $comment = $request->filled('comment') ? trim($request->comment) : null;
        $personResponsible = $request->person_responsible;
        $supplierId = $request->filled('supplier_id') ? (int) $request->supplier_id : null;
        $orderId = $request->filled('order_id') ? substr(trim((string) $request->order_id), 0, 30) : null;
        if ($orderId === '') {
            $orderId = null;
        }
        $channelMasterId = $request->filled('channel_master_id') ? (int) $request->channel_master_id : null;
        $user = Auth::user()->name ?? 'N/A';
        $now = Carbon::now('America/New_York');

        if ($rec->reason !== $reason) {
            $this->logHistory($rec, 'reason', $rec->reason, $reason, $user, $now);
            $rec->reason = $reason;
        }

        if (($rec->comment ?? '') !== ($comment ?? '')) {
            $this->logHistory($rec, 'comment', $rec->comment, $comment, $user, $now);
            $rec->comment = $comment;
        }

        if (($rec->person_responsible ?? '') !== $personResponsible) {
            $this->logHistory($rec, 'person_responsible', $rec->person_responsible, $personResponsible, $user, $now);
            $rec->person_responsible = $personResponsible;
        }

        $oldSupplierName = $rec->supplier?->name ?? '';
        $newSupplier = $supplierId ? Supplier::find($supplierId) : null;
        $newSupplierName = $newSupplier?->name ?? '';
        if ((string) $oldSupplierName !== (string) $newSupplierName || (int) ($rec->supplier_id ?? 0) !== (int) ($supplierId ?? 0)) {
            $this->logHistory($rec, 'supplier', $oldSupplierName ?: null, $newSupplierName ?: null, $user, $now);
            $rec->supplier_id = $supplierId;
        }

        if (($rec->order_id ?? '') !== ($orderId ?? '')) {
            $this->logHistory($rec, 'order_id', $rec->order_id, $orderId, $user, $now);
            $rec->order_id = $orderId;
        }

        $oldChannelName = $rec->channelMaster?->channel ?? '';
        $newChannel = $channelMasterId ? ChannelMaster::find($channelMasterId) : null;
        $newChannelName = $newChannel?->channel ?? '';
        if ((int) ($rec->channel_master_id ?? 0) !== (int) ($channelMasterId ?? 0)) {
            $this->logHistory($rec, 'channel', $oldChannelName ?: null, $newChannelName ?: null, $user, $now);
            $rec->channel_master_id = $channelMasterId;
        }

        $rec->save();
        $rec->load(['supplier', 'channelMaster']);

        return response()->json([
            'success' => true,
            'message' => 'Updated.',
            'record' => [
                'id' => $rec->id,
                'sku' => $rec->sku,
                'reason' => $rec->reason,
                'remarks' => $rec->comment,
                'person_responsible' => $rec->person_responsible,
                'supplier_id' => $rec->supplier_id,
                'supplier_name' => $rec->supplier?->name ?? '',
                'order_id' => $rec->order_id,
                'channel_master_id' => $rec->channel_master_id,
                'channel_name' => $rec->channelMaster?->channel ?? '',
            ],
        ]);
    }

    private function logHistory(RefundRecord $rec, string $field, $old, $new, string $user, $now): void
    {
        RefundRecordEditHistory::create([
            'refund_record_id' => $rec->id,
            'sku' => $rec->sku,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'updated_by' => $user,
            'updated_at' => $now,
        ]);
    }

    private function normalizeOrderIdAlias(Request $request): void
    {
        if ($request->filled('order_jd') && !$request->has('order_id')) {
            $request->merge([
                'order_id' => $request->input('order_jd'),
            ]);
        }
    }

    public function getHistory(Request $request, $id)
    {
        $id = (int) $id;
        $rec = RefundRecord::find($id);
        if (!$rec) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $labels = [
            'reason' => 'Reason',
            'comment' => 'Corrective action required',
            'person_responsible' => 'Person responsible',
            'supplier' => 'Supplier',
            'order_id' => 'Order ID',
            'channel' => 'Channel',
        ];

        $history = RefundRecordEditHistory::where('refund_record_id', $id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($h) use ($labels) {
                return [
                    'field' => $h->field,
                    'field_label' => $labels[$h->field] ?? $h->field,
                    'old_value' => $h->old_value,
                    'new_value' => $h->new_value,
                    'updated_by' => $h->updated_by,
                    'updated_at' => Carbon::parse($h->updated_at)->timezone('America/New_York')->format('m-d-Y H:i'),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'sku' => $rec->sku,
            'history' => $history,
        ]);
    }

    public function list(Request $request)
    {
        $query = RefundRecord::query()->with(['supplier', 'channelMaster']);

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }
        if ($request->filled('person')) {
            $query->where('created_by', $request->person);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }
        if ($request->filled('filter_pr')) {
            $term = trim($request->filter_pr);
            $query->where('person_responsible', 'like', '%' . addcslashes($term, '%_\\') . '%');
        }
        if ($request->filled('filter_supplier')) {
            $term = trim($request->filter_supplier);
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('supplier', function ($sq) use ($like) {
                    $sq->where('name', 'like', $like);
                });
            });
        }
        if ($request->filled('filter_order_id')) {
            $term = trim($request->filter_order_id);
            $query->where('order_id', 'like', '%' . addcslashes($term, '%_\\') . '%');
        }
        if ($request->filled('filter_channel')) {
            $term = trim($request->filter_channel);
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $query->whereHas('channelMaster', function ($q) use ($like) {
                $q->where('channel', 'like', $like);
            });
        }

        $items = $query->latest('created_at')->get();

        $data = $items->map(function ($item) {
            $qty = (int) $item->qty;
            $refundAmt = (float) $item->refund_amt;
            $archived = (bool) $item->is_archived;
            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'verified_stock' => $qty,
                'refund_amt' => $refundAmt,
                'reason' => $item->reason,
                'remarks' => $item->comment,
                'person_responsible' => $item->person_responsible ?? '',
                'supplier_id' => $item->supplier_id,
                'supplier_name' => $item->supplier?->name ?? '',
                'order_id' => $item->order_id ?? '',
                'channel_master_id' => $item->channel_master_id,
                'channel_name' => $item->channelMaster?->channel ?? '',
                'approved_by' => $item->created_by,
                'approved_at' => $item->created_at
                    ? Carbon::parse($item->created_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
                'is_archived' => $archived,
            ];
        })->values()->all();

        $reasons = RefundReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        $persons = RefundRecord::query()->distinct()->pluck('created_by')->filter()->values()->all();

        return response()->json([
            'data' => $data,
            'reasons' => $reasons,
            'persons' => $persons,
        ]);
    }

    /**
     * Resolve supplier for a SKU using the same logic as Forecast Analysis:
     * 1) mfrg_progress.supplier ("Current Supplier")
     * 2) Supplier Tag from suppliers linked to product parent ("Supply All")
     */
    public function supplierForSku(Request $request)
    {
        $normalizeSku = function ($sku) {
            if ($sku === null || $sku === '') {
                return '';
            }
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            return trim($sku);
        };

        $norm = $normalizeSku($request->input('sku', ''));
        if ($norm === '') {
            return response()->json(['supplier_id' => null, 'supplier_name' => null]);
        }

        $resolveNameToSupplier = function (string $name): ?Supplier {
            $name = trim($name);
            if ($name === '') {
                return null;
            }
            $sup = Supplier::where('type', 'Supplier')
                ->whereRaw('LOWER(TRIM(name)) = LOWER(?)', [$name])
                ->first();
            if ($sup) {
                return $sup;
            }
            return Supplier::where('type', 'Supplier')
                ->where('name', 'like', '%' . addcslashes($name, '%_\\') . '%')
                ->orderByRaw('LENGTH(name)')
                ->first();
        };

        foreach (DB::table('mfrg_progress')->whereNull('deleted_at')->get() as $row) {
            if ($normalizeSku($row->sku ?? '') !== $norm) {
                continue;
            }
            $mfrgSupplier = trim((string) ($row->supplier ?? ''));
            if ($mfrgSupplier !== '') {
                $sup = $resolveNameToSupplier($mfrgSupplier);
                if ($sup) {
                    return response()->json([
                        'supplier_id' => $sup->id,
                        'supplier_name' => $sup->name,
                        'source' => 'mfrg_progress',
                    ]);
                }
            }
        }

        $product = ProductMaster::whereNull('deleted_at')->get()->first(function ($p) use ($normalizeSku, $norm) {
            return $normalizeSku($p->sku ?? '') === $norm;
        });
        if (!$product) {
            return response()->json(['supplier_id' => null, 'supplier_name' => null]);
        }

        $supplierMapByParent = [];
        foreach (Supplier::where('type', 'Supplier')->get() as $row) {
            foreach (array_map('trim', explode(',', strtoupper($row->parent ?? ''))) as $parent) {
                if ($parent !== '') {
                    $supplierMapByParent[$parent][] = $row->name;
                }
            }
        }

        $parentNorm = $normalizeSku($product->parent ?? '');
        $tagNames = [];
        if ($parentNorm !== '' && isset($supplierMapByParent[$parentNorm])) {
            $tagNames = $supplierMapByParent[$parentNorm];
        } else {
            foreach ($supplierMapByParent as $key => $names) {
                if ($normalizeSku($key) === $parentNorm) {
                    $tagNames = $names;
                    break;
                }
            }
        }

        if (empty($tagNames)) {
            return response()->json(['supplier_id' => null, 'supplier_name' => null]);
        }

        $firstName = $tagNames[0];
        $sup = Supplier::where('type', 'Supplier')->where('name', $firstName)->first()
            ?? $resolveNameToSupplier($firstName);

        if ($sup) {
            return response()->json([
                'supplier_id' => $sup->id,
                'supplier_name' => $sup->name,
                'source' => 'supplier_tag',
            ]);
        }

        return response()->json(['supplier_id' => null, 'supplier_name' => null]);
    }

    /**
     * Last 30 days (America/New_York): total refund_amt and per-day totals for chart.
     */
    public function refundStatsLast30Days()
    {
        $tz = 'America/New_York';
        $byDay = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::now($tz)->subDays($i)->format('Y-m-d');
            $byDay[$d] = 0.0;
        }

        $startUtc = Carbon::now($tz)->subDays(29)->startOfDay()->utc();
        $endUtc = Carbon::now($tz)->endOfDay()->utc();

        $records = RefundRecord::query()
            ->where('is_archived', false)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['refund_amt', 'created_at']);

        foreach ($records as $r) {
            $d = Carbon::parse($r->created_at)->timezone($tz)->format('Y-m-d');
            if (isset($byDay[$d])) {
                $byDay[$d] += (float) $r->refund_amt;
            }
        }

        $daily = [];
        $total = 0.0;
        foreach ($byDay as $date => $amt) {
            $amt = round($amt, 2);
            $total += $amt;
            $daily[] = [
                'date' => $date,
                'label' => Carbon::parse($date, $tz)->format('M j'),
                'total' => $amt,
            ];
        }

        return response()->json([
            'total_30d' => round($total, 2),
            'daily' => $daily,
        ]);
    }

    public function archive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:refund_records,id',
        ]);

        $updated = RefundRecord::whereIn('id', $request->ids)
            ->update(['is_archived' => true]);

        return response()->json([
            'success' => true,
            'message' => $updated . ' row(s) archived.',
        ]);
    }
}
