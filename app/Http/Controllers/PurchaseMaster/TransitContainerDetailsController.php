<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\InventoryWarehouse;
use App\Models\TransitContainerDetail;
use App\Models\TransitContainerHistory;
use App\Models\Supplier;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ReadyToShip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransitContainerDetailsController extends Controller
{
    protected function logHistory(string $actionType, ?int $detailId = null, ?string $fromTab = null, ?string $toTab = null, ?string $ourSku = null, $details = null): void
    {
        TransitContainerHistory::create([
            'action_type' => $actionType,
            'transit_container_detail_id' => $detailId,
            'from_tab' => $fromTab,
            'to_tab' => $toTab,
            'our_sku' => $ourSku,
            'details' => is_array($details) || is_object($details) ? json_encode($details) : $details,
            'user_id' => Auth::id(),
        ]);
    }

    public function index()
    {
        $allRecords = TransitContainerDetail::with('user')->where(function($q){
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->get();

        $tabs = TransitContainerDetail::where(function($q){
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')->toArray();

        if (empty($tabs)) {
            $tabs = ['Container 1'];
        }

        $skuParentMap = ProductMaster::pluck('parent', 'sku')
            ->mapWithKeys(function ($parent, $sku) {
                $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', $sku)));
                return [$normSku => strtoupper(trim($parent))];
            })->toArray();

        $supplierData = Supplier::select('name', 'parent')->get();
        $parentSupplierMap = [];
        foreach ($supplierData as $supplier) {
            $parentList = array_map('trim', explode(',', $supplier->parent));
            foreach ($parentList as $parent) {
                $key = strtoupper(trim(preg_replace('/\s+/', ' ', $parent)));
                $parentSupplierMap[$key][] = $supplier->name;
            }
        }

        $shopifyImages = ShopifySku::pluck('image_src', 'sku')->mapWithKeys(function ($value, $key) {
            $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', $key)));
            return [$normSku => $value];
        })->toArray();

        $productValuesMap = ProductMaster::pluck('Values', 'sku')->mapWithKeys(function ($value, $key) {
            $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', $key)));
            return [$normSku => $value];
        })->toArray();

        $pushedMap = InventoryWarehouse::select('tab_name', 'transit_container_id', 'our_sku', 'pushed', 'push_status', 'created_at','created_by')
        ->whereNotNull('transit_container_id')
        ->whereNotNull('tab_name')
        ->orderBy('created_at', 'desc')
        ->get()
        ->unique(function ($item) {
            return strtoupper(trim($item->tab_name)) . '|' . (int)$item->transit_container_id;
        })
        ->mapWithKeys(function ($item) {
            $normTab = strtoupper(trim(preg_replace('/\s+/', ' ', $item->tab_name)));
            $rowId = (int)$item->transit_container_id;
            return ["{$normTab}|{$rowId}" => [
                'pushed' => (int) $item->pushed,
                'push_status' => $item->push_status ?? 'pending'
            ]];
        })
        ->toArray();


        // Transform TransitContainerDetail Records
        $allRecords->transform(function ($record) use ($skuParentMap, $parentSupplierMap, $shopifyImages, $productValuesMap, $pushedMap) {
            $sku = strtoupper(trim(preg_replace('/\s+/', ' ', $record->our_sku ?? '')));
            $tabKey = strtoupper(trim(preg_replace('/\s+/', ' ', $record->tab_name ?? '')));
            // $rowId = $record->id; 

            $key = "{$tabKey}|{$record->id}";

            $parent = $skuParentMap[$sku] ?? null;

            if (empty($record->parent) && $parent) {
                $record->parent = $parent;
            }

            $parentKey = strtoupper(trim(preg_replace('/\s+/', ' ', $record->parent ?? '')));
            $record->supplier_names = $parentSupplierMap[$parentKey] ?? [];

            $record->image_src = $shopifyImages[$sku] ?? null;
            $record->Values = $productValuesMap[$sku] ?? null;

            if (isset($pushedMap[$key])) {
                $record->pushed = (int) $pushedMap[$key]['pushed'];
                $record->push_status = $pushedMap[$key]['push_status'] ?? 'pending';
            } else {
                $record->pushed = 0;
                $record->push_status = 'pending';
            }
            // $record->pushed = isset($pushedMap[$sku]) ? (int) $pushedMap[$sku] : 0;
            $record->created_by_name = $record->user->name ?? '—';
            
            return $record;
        });

        $groupedData = $allRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (!isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        $suppliers = Supplier::select('id', 'name')->get();

        $skus = ProductMaster::pluck('sku')->toArray();

        return view('purchase-master.transit_container.index', [
            'tabs' => $tabs,
            'groupedData' => $groupedData,
            'suppliers' => $suppliers,
            'skus'=> $skus,
            'productValuesMap' => json_encode($productValuesMap)
        ]);
    }


    public function addTab(Request $request)
    {
        $tabName = trim($request->tab_name);

        if (!$tabName) {
            return response()->json(['success' => false, 'message' => 'Tab name is required.'], 400);
        }

        $exists = TransitContainerDetail::where('tab_name', $tabName)->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Tab name already exists.'], 400);
        }

        TransitContainerDetail::create([
            'tab_name' => $tabName,
        ]);

        $this->logHistory('tab_added', null, null, $tabName, null, ['tab_name' => $tabName]);

        return response()->json(['success' => true]);
    }

    public function saveRow(Request $request)
    {
        $data = $request->all();

        if (empty($data['tab_name'])) {
            return response()->json(['success' => false, 'message' => 'Tab name is missing.'], 422);
        }

        if (!empty($data['id'])) {
            $row = TransitContainerDetail::find($data['id']);
            if ($row) {
                $fromTab = $row->tab_name;
                $toTab = $data['tab_name'] ?? $fromTab;
                $row->update($data);
                if ($fromTab !== $toTab) {
                    $this->logHistory('row_moved', $row->id, $fromTab, $toTab, $row->our_sku, ['sku' => $row->our_sku, 'from' => $fromTab, 'to' => $toTab]);
                } else {
                    $this->logHistory('row_updated', $row->id, null, $toTab, $row->our_sku, null);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Row not found.']);
            }
        } else {
            $row = TransitContainerDetail::create($data);
            $this->logHistory('row_created', $row->id, null, $row->tab_name, $row->our_sku, null);
        }

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function uploadImage(Request $request)
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = public_path('uploads/transit/');
            $file->move($path, $filename);

            return response()->json([
                'success' => true,
                'url' => url('uploads/transit/' . $filename),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No file uploaded',
        ]);
    }

    //save transit conatiner items
    public function transitContainerStoreItems(Request $request)
    {
        $request->validate([
            'tab_name'       => 'required|string|max:255',
            'our_sku.*'       => 'required|string|max:255',
            'supplier_name.*' => 'required|string|max:255',
            'no_of_units.*'   => 'nullable|numeric',
            'total_ctn.*'     => 'nullable|numeric',
            'pcs_qty.*'       => 'nullable|numeric',
            'rate.*'          => 'nullable|numeric',
            'unit.*'          => 'nullable|string',
            'cbm.*'          => 'nullable|numeric',
            'changes.*'       => 'nullable|string',
            'specification.*' => 'nullable|string',
        ]);

        foreach ($request->our_sku as $index => $sku) {
            $data = [
                'tab_name'      => $request->tab_name,
                'our_sku'       => $sku,
                'supplier_name' => $request->supplier_name[$index] ?? null,
                'no_of_units'   => $request->no_of_units[$index] ?? null,
                'total_ctn'     => $request->total_ctn[$index] ?? null,
                'pcs_qty'       => $request->pcs_qty[$index] ?? null,
                'rate'          => $request->rate[$index] ?? null,
                'unit'          => $request->unit[$index] ?? null,
                'cbm'          => $request->cbm[$index] ?? null,
                'changes'       => $request->changes[$index] ?? null,
                'specification' => $request->specification[$index] ?? null,
                'created_by'    => auth()->id(),
            ];

            TransitContainerDetail::updateOrCreate(
                [
                    'tab_name' => $request->tab_name,
                    'our_sku'  => null,
                ],
                $data
            );
        }
        $this->logHistory('purchase_added', null, null, $request->tab_name, null, [
            'tab_name' => $request->tab_name,
            'count' => count($request->our_sku),
            'skus' => $request->our_sku,
        ]);
        return back()->with('success', 'Items saved successfully!');
    }

    /**
     * When a transit line is removed, put that SKU back on Ready to Ship (undo mistaken add / wrong container).
     */
    protected function restoreReadyToShipAfterTransitLineDeleted(TransitContainerDetail $row): void
    {
        $normalizeSku = function ($s) {
            if ($s === null || $s === '') {
                return '';
            }

            return strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $s)));
        };
        $skuNorm = $normalizeSku($row->our_sku);
        if ($skuNorm === '') {
            return;
        }

        $qty = (float) ($row->total_ctn ?? 0);
        if ($qty <= 0) {
            $noUnits = (float) ($row->no_of_units ?? 0);
            $pcs = (float) ($row->pcs_qty ?? 0);
            if ($noUnits > 0 && $pcs > 0) {
                $qty = $noUnits > 0 ? round($pcs / $noUnits, 4) : $pcs;
            }
            if ($qty <= 0) {
                $qty = 1;
            }
        }

        $authName = auth()->check() ? auth()->user()->name : 'system';

        $candidates = ReadyToShip::whereNull('deleted_at')
            ->get()
            ->filter(fn ($r) => $normalizeSku($r->sku) === $skuNorm);

        $rtsInTransit = $candidates->where('transit_inv_status', 1)->sortByDesc('id')->first();

        if ($rtsInTransit) {
            $rtsInTransit->update([
                'rec_qty' => $qty,
                'qty' => (int) max(1, round($qty)),
                'transit_inv_status' => 0,
                'rate' => $rtsInTransit->rate ?? $row->rate,
                'cbm' => $rtsInTransit->cbm ?? $row->cbm,
                'parent' => $rtsInTransit->parent ?? $row->parent,
                'supplier' => $rtsInTransit->supplier ?? $row->supplier_name,
                'updated_at' => now(),
            ]);

            return;
        }

        $rtsOpen = $candidates->where('transit_inv_status', 0)->sortByDesc('updated_at')->first();
        if ($rtsOpen) {
            $prev = (float) ($rtsOpen->rec_qty ?? 0);
            if ($prev <= 0 && $rtsOpen->qty !== null && $rtsOpen->qty !== '') {
                $prev = (float) $rtsOpen->qty;
            }
            $newQty = $prev + $qty;
            $rtsOpen->update([
                'rec_qty' => $newQty,
                'qty' => (int) max(1, round($newQty)),
                'updated_at' => now(),
            ]);

            return;
        }

        ReadyToShip::create([
            'sku' => trim((string) $row->our_sku),
            'parent' => $row->parent,
            'rec_qty' => $qty,
            'qty' => (int) max(1, round($qty)),
            'rate' => $row->rate,
            'cbm' => $row->cbm,
            'supplier' => $row->supplier_name,
            'transit_inv_status' => 0,
            'auth_user' => $authName,
        ]);
    }

    public function deleteTransitItem(Request $request)
    {
        try {
            $ids = $request->ids;
            if (! is_array($ids)) {
                $ids = $ids !== null && $ids !== '' ? [(int) $ids] : [];
            }
            $ids = array_values(array_filter(array_map('intval', $ids), fn ($id) => $id > 0));

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rows selected.',
                ], 422);
            }

            $authUser = auth()->check() ? auth()->user()->name : 'system';

            $rows = TransitContainerDetail::whereIn('id', $ids)->get();

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $this->restoreReadyToShipAfterTransitLineDeleted($row);
                    $this->logHistory('row_deleted', $row->id, $row->tab_name, null, $row->our_sku, [
                        'tab' => $row->tab_name,
                        'sku' => $row->our_sku,
                        'restored_ready_to_ship' => true,
                    ]);
                }

                TransitContainerDetail::whereIn('id', $ids)->update([
                    'auth_user' => $authUser,
                ]);

                TransitContainerDetail::whereIn('id', $ids)->delete();
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Removed from transit. SKU(s) restored on Ready to Ship (where applicable).',
            ]);
        } catch (\Exception $e) {
            Log::error('deleteTransitItem', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: '.$e->getMessage(),
            ], 500);
        }
    }

    //transit container changes
    public function transitContainerChanges(){

        $allRecords = TransitContainerDetail::all();

        $tabs = TransitContainerDetail::select('tab_name')->distinct()->pluck('tab_name')->toArray();
        if (empty($tabs)) {
            $tabs = ['Container 1'];
        }

        $skuParentMap = ProductMaster::pluck('parent', 'sku')->toArray();

        $supplierData = Supplier::select('name', 'parent')->get();
        $parentSupplierMap = [];
        foreach ($supplierData as $supplier) {
            $parentList = array_map('trim', explode(',', $supplier->parent));
            foreach ($parentList as $parent) {
                $key = strtolower($parent);
                if (!isset($parentSupplierMap[$key])) {
                    $parentSupplierMap[$key] = [];
                }
                $parentSupplierMap[$key][] = $supplier->name;
            }
        }

        $shopifyImages = ShopifySku::pluck('image_src', 'sku')->mapWithKeys(function($value, $key) {
            return [strtoupper(trim($key)) => $value];
        })->toArray();

        $productValuesMap = ProductMaster::pluck('Values', 'sku')->mapWithKeys(function($value, $key) {
            return [strtoupper(trim($key)) => $value];
        })->toArray();

        // First enrich all records
        $allRecords->transform(function ($record) use ($skuParentMap, $parentSupplierMap, $shopifyImages, $productValuesMap) {
            $sku = strtoupper(trim($record->our_sku ?? ''));

            if (empty($record->parent) && isset($skuParentMap[$sku])) {
                $record->parent = $skuParentMap[$sku];
            }

            $parentKey = strtolower(trim($record->parent ?? ''));
            $record->supplier_names = $parentSupplierMap[$parentKey] ?? [];

            $record->image_src = $shopifyImages[$sku] ?? null;
            $record->Values = $productValuesMap[$sku] ?? null;

            return $record;
        });

        // Then filter out 'Sourcing' parents (after parent field is enriched)
        $filteredRecords = $allRecords->filter(function ($record) {
            return strtolower(trim($record->parent)) !== 'sourcing';
        });

        $groupedData = $filteredRecords->groupBy('tab_name');

        foreach ($tabs as $tab) {
            if (!isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }
        return view('purchase-master.transit_container.changes', compact('tabs', 'groupedData'));
    }

    //transit container new
    public function transitContainerNew()
    {
        $allRecords = TransitContainerDetail::all();

        $tabs = TransitContainerDetail::select('tab_name')->distinct()->pluck('tab_name')->toArray();
        if (empty($tabs)) {
            $tabs = ['Container 1'];
        }

        $skuParentMap = ProductMaster::pluck('parent', 'sku')->toArray();

        $supplierData = Supplier::select('name', 'parent')->get();
        $parentSupplierMap = [];
        foreach ($supplierData as $supplier) {
            $parentList = array_map('trim', explode(',', $supplier->parent));
            foreach ($parentList as $parent) {
                $key = strtolower($parent);
                if (!isset($parentSupplierMap[$key])) {
                    $parentSupplierMap[$key] = [];
                }
                $parentSupplierMap[$key][] = $supplier->name;
            }
        }

        $shopifyImages = ShopifySku::pluck('image_src', 'sku')->mapWithKeys(function ($value, $key) {
            return [strtoupper(trim($key)) => $value];
        })->toArray();

        $productValuesMap = ProductMaster::pluck('Values', 'sku')->mapWithKeys(function ($value, $key) {
            return [strtoupper(trim($key)) => $value];
        })->toArray();

        $allRecords->transform(function ($record) use ($skuParentMap, $parentSupplierMap, $shopifyImages, $productValuesMap) {
            $sku = strtoupper(trim($record->our_sku ?? ''));

            if (empty($record->parent) && isset($skuParentMap[$sku])) {
                $record->parent = $skuParentMap[$sku];
            }

            $parentKey = strtolower(trim($record->parent ?? ''));
            $record->supplier_names = $parentSupplierMap[$parentKey] ?? [];

            $record->image_src = $shopifyImages[$sku] ?? null;
            $record->Values = $productValuesMap[$sku] ?? null;

            return $record;
        });

        // Filter to include ONLY 'Sourcing' parent
        $filteredRecords = $allRecords->filter(function ($record) {
            return strtolower(trim($record->parent)) === 'sourcing';
        });

        $groupedData = $filteredRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (!isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        return view('purchase-master.transit_container.new_transit', [
            'tabs' => $tabs,
            'groupedData' => $groupedData
        ]);
    }

    /**
     * Get history of all transit container actions (moves, adds, deletes, purchase, etc.)
     */
    public function getHistory(Request $request)
    {
        $query = TransitContainerHistory::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('tab_name')) {
            $query->where(function ($q) use ($request) {
                $tab = trim($request->tab_name);
                $q->where('to_tab', $tab)->orWhere('from_tab', $tab);
            });
        }
        if ($request->filled('sku')) {
            $query->where('our_sku', 'like', '%' . trim($request->sku) . '%');
        }
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        $limit = min((int) $request->get('limit', 100), 500);
        $items = $query->limit($limit)->get()->map(function ($h) {
            return [
                'id' => $h->id,
                'action_type' => $h->action_type,
                'from_tab' => $h->from_tab,
                'to_tab' => $h->to_tab,
                'our_sku' => $h->our_sku,
                'details' => $h->details,
                'user_name' => $h->user->name ?? '—',
                'created_at' => $h->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json(['data' => $items]);
    }
}
