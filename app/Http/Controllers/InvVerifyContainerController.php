<?php

namespace App\Http\Controllers;

use App\Models\ArrivedContainer;
use App\Models\InvVerifyContainerAudit;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Inv Verify Container — same arrived_containers source as /arrived/container.
 */
class InvVerifyContainerController extends Controller
{
    public function index()
    {
        $allRecords = ArrivedContainer::with('user')->where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->get();

        $tabs = ArrivedContainer::with('user')->where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')->toArray();

        if (empty($tabs)) {
            $tabs = ['Container 1'];
        }

        usort($tabs, function ($a, $b) {
            preg_match('/(\d+)/', (string) $a, $mA);
            preg_match('/(\d+)/', (string) $b, $mB);

            return ((int) ($mB[1] ?? 0)) <=> ((int) ($mA[1] ?? 0));
        });

        $normalizeSku = static function ($value) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
        };

        $skuParentMap = ProductMaster::pluck('parent', 'sku')
            ->mapWithKeys(function ($parent, $sku) use ($normalizeSku) {
                return [$normalizeSku($sku) => $normalizeSku($parent)];
            })->toArray();

        $supplierRows = Supplier::where('type', 'Supplier')->get(['name', 'parent']);
        $parentSupplierMap = [];
        foreach ($supplierRows as $supplier) {
            $parentList = array_map('trim', explode(',', strtoupper($supplier->parent ?? '')));
            foreach ($parentList as $parent) {
                if ($parent === '') {
                    continue;
                }
                $parentSupplierMap[$parent][] = $supplier->name;
            }
        }

        $toOrderSupplierBySku = [];
        $toOrderRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier_name']);
        foreach ($toOrderRows as $r) {
            $k = $normalizeSku($r->sku);
            if ($k === '' || array_key_exists($k, $toOrderSupplierBySku)) {
                continue;
            }
            if ($r->supplier_name !== null) {
                $toOrderSupplierBySku[$k] = (string) $r->supplier_name;
            }
        }

        $mfrgSupplierBySku = [];
        $mfrgRows = DB::table('mfrg_progress')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier']);
        foreach ($mfrgRows as $r) {
            $k = $normalizeSku($r->sku);
            if ($k === '' || array_key_exists($k, $mfrgSupplierBySku)) {
                continue;
            }
            $mfrgSupplierBySku[$k] = trim((string) ($r->supplier ?? ''));
        }

        $shopifyImages = ShopifySku::pluck('image_src', 'sku')->mapWithKeys(function ($value, $key) use ($normalizeSku) {
            return [$normalizeSku($key) => $value];
        })->toArray();

        $productValuesMap = ProductMaster::pluck('Values', 'sku')->mapWithKeys(function ($value, $key) use ($normalizeSku) {
            return [$normalizeSku($key) => $value];
        })->toArray();

        $allRecords->transform(function ($record) use (
            $skuParentMap,
            $parentSupplierMap,
            $shopifyImages,
            $productValuesMap,
            $toOrderSupplierBySku,
            $mfrgSupplierBySku,
            $normalizeSku
        ) {
            $sku = $normalizeSku($record->our_sku ?? '');

            $parent = $skuParentMap[$sku] ?? null;
            if (empty($record->parent) && $parent) {
                $record->parent = $parent;
            }

            $parentKey = $normalizeSku($record->parent ?? '');
            $record->supplier_names = isset($parentSupplierMap[$parentKey])
                ? array_values(array_unique($parentSupplierMap[$parentKey]))
                : [];

            if (array_key_exists($sku, $toOrderSupplierBySku)) {
                $record->supplier_name = $toOrderSupplierBySku[$sku];
            } else {
                $record->supplier_name = $mfrgSupplierBySku[$sku] ?? '';
            }

            $record->image_src = $shopifyImages[$sku] ?? null;
            $values = $productValuesMap[$sku] ?? null;
            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = is_array($decoded) ? $decoded : null;
            }
            $record->Values = $values;

            $cartons = $record->inv_verify_cartons;
            if (is_string($cartons)) {
                $decoded = json_decode($cartons, true);
                $cartons = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($cartons)) {
                $cartons = [];
            }
            $record->inv_verify_cartons = array_values(array_map(static function ($row) {
                return [
                    'qty' => is_numeric($row['qty'] ?? null) ? (float) $row['qty'] : null,
                ];
            }, $cartons));
            $record->inv_verify_carton_count = count($record->inv_verify_cartons);
            $record->inv_verify_total_qty = collect($record->inv_verify_cartons)
                ->sum(fn ($r) => (float) ($r['qty'] ?? 0));
            $record->has_inv_verify = $record->inv_verify_carton_count > 0;

            $expectedQty = ((float) ($record->no_of_units ?? 0)) * ((float) ($record->total_ctn ?? 0));
            $record->inv_verify_expected_qty = $expectedQty;
            $record->inv_verify_qty_match = $record->has_inv_verify
                && abs(((float) $record->inv_verify_total_qty) - $expectedQty) < 0.0001;
            $record->inv_verify_discrepancy = trim((string) ($record->inv_verify_discrepancy ?? '')) ?: null;

            return $record;
        });

        $auditByArrivedId = $allRecords->isEmpty()
            ? collect()
            : InvVerifyContainerAudit::query()
                ->whereIn('arrived_container_id', $allRecords->pluck('id')->filter()->all())
                ->get(['arrived_container_id', 'action_history'])
                ->keyBy('arrived_container_id');

        $allRecords->transform(function ($record) use ($auditByArrivedId) {
            $audit = $auditByArrivedId->get($record->id);
            $history = is_array($audit?->action_history) ? $audit->action_history : [];
            $record->action_history = $history;

            return $record;
        });

        $groupedData = $allRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (! isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        $groupedData = collect($tabs)->mapWithKeys(fn ($tab) => [$tab => $groupedData[$tab]]);

        return view('purchase-master.transit_container.inv-verify-container', [
            'tabs' => $tabs,
            'groupedData' => $groupedData,
        ]);
    }

    public function saveCartons(Request $request)
    {
        $arrivedId = (int) $request->input('id', 0);
        $cartonsIn = $request->input('cartons', []);

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Row id is required.'], 400);
        }

        $row = ArrivedContainer::query()->find($arrivedId);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        if (! is_array($cartonsIn)) {
            return response()->json(['success' => false, 'message' => 'Cartons must be an array.'], 422);
        }

        $cartons = [];
        foreach ($cartonsIn as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qtyRaw = $item['qty'] ?? null;
            if ($qtyRaw === null || $qtyRaw === '') {
                continue;
            }
            if (! is_numeric($qtyRaw) || (float) $qtyRaw < 0) {
                return response()->json(['success' => false, 'message' => 'Each carton qty must be a non-negative number.'], 422);
            }
            $cartons[] = ['qty' => round((float) $qtyRaw, 2)];
        }

        $row->inv_verify_cartons = $cartons;

        $totalQty = (float) collect($cartons)->sum('qty');
        $expectedQty = ((float) ($row->no_of_units ?? 0)) * ((float) ($row->total_ctn ?? 0));
        $matches = count($cartons) > 0 && abs($totalQty - $expectedQty) < 0.0001;

        // Clear discrepancy when qty matches again.
        if ($matches) {
            $row->inv_verify_discrepancy = null;
        }

        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Carton quantities saved.',
            'inv_verify_cartons' => $cartons,
            'inv_verify_carton_count' => count($cartons),
            'inv_verify_total_qty' => $totalQty,
            'inv_verify_expected_qty' => $expectedQty,
            'inv_verify_qty_match' => $matches,
            'has_inv_verify' => count($cartons) > 0,
            'inv_verify_discrepancy' => $row->inv_verify_discrepancy,
            'needs_discrepancy' => count($cartons) > 0 && ! $matches,
        ]);
    }

    public function saveDiscrepancy(Request $request)
    {
        $arrivedId = (int) $request->input('id', 0);
        $note = trim((string) $request->input('discrepancy', ''));

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Row id is required.'], 400);
        }

        if ($note === '') {
            return response()->json(['success' => false, 'message' => 'Discrepancy note is required.'], 422);
        }

        if (mb_strlen($note) > 500) {
            return response()->json(['success' => false, 'message' => 'Discrepancy note max 500 characters.'], 422);
        }

        $row = ArrivedContainer::query()->find($arrivedId);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        $cartons = $row->inv_verify_cartons;
        if (is_string($cartons)) {
            $decoded = json_decode($cartons, true);
            $cartons = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($cartons) || count($cartons) === 0) {
            return response()->json(['success' => false, 'message' => 'Enter carton quantities first.'], 422);
        }

        $totalQty = (float) collect($cartons)->sum(fn ($c) => (float) ($c['qty'] ?? 0));
        $expectedQty = ((float) ($row->no_of_units ?? 0)) * ((float) ($row->total_ctn ?? 0));
        if (abs($totalQty - $expectedQty) < 0.0001) {
            return response()->json(['success' => false, 'message' => 'Qty matches — no discrepancy to record.'], 422);
        }

        $row->inv_verify_discrepancy = $note;
        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Discrepancy recorded.',
            'inv_verify_discrepancy' => $row->inv_verify_discrepancy,
            'inv_verify_qty_match' => false,
            'has_inv_verify' => true,
        ]);
    }

    public function addAction(Request $request)
    {
        $arrivedId = (int) $request->input('arrived_id', 0);
        $note = trim((string) $request->input('note', ''));
        $sku = trim((string) $request->input('sku', ''));
        $supplierName = trim((string) $request->input('supplier_name', ''));

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Arrived container id is required.'], 400);
        }

        if ($note === '') {
            return response()->json(['success' => false, 'message' => 'Action / communication note is required.'], 422);
        }

        $arrived = ArrivedContainer::query()->find($arrivedId);
        if (! $arrived) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        if ($sku === '') {
            $sku = trim((string) ($arrived->our_sku ?? ''));
        }
        if ($supplierName === '') {
            $supplierName = trim((string) ($arrived->supplier_name ?? ''));
        }

        $audit = InvVerifyContainerAudit::firstOrNew(['arrived_container_id' => $arrivedId]);
        $history = is_array($audit->action_history) ? $audit->action_history : [];
        $history[] = [
            'action' => 'Action / Communication',
            'note' => $note,
            'user' => Auth::user()->name ?? 'System',
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $audit->our_sku = $sku !== '' ? $sku : $audit->our_sku;
        $audit->supplier_name = $supplierName !== '' ? $supplierName : $audit->supplier_name;
        $audit->action_history = $history;
        $audit->save();

        return response()->json([
            'success' => true,
            'message' => 'Action added successfully.',
            'action_history' => $audit->action_history,
        ]);
    }
}
