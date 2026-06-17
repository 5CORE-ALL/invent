<?php

namespace App\Http\Controllers;

use App\Models\ArrivedContainer;
use App\Models\ArrivedContainerHistory;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;
use App\Models\TransitContainerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\OnSeaTransit;

class ArrivedContainerController extends Controller
{
    protected function logHistory(
        string $actionType,
        ?int $arrivedContainerId = null,
        ?string $fromTab = null,
        ?string $toTab = null,
        ?string $ourSku = null,
        $details = null
    ): void {
        ArrivedContainerHistory::create([
            'action_type' => $actionType,
            'arrived_container_id' => $arrivedContainerId,
            'from_tab' => $fromTab,
            'to_tab' => $toTab,
            'our_sku' => $ourSku,
            'details' => is_array($details) || is_object($details) ? json_encode($details) : $details,
            'user_id' => Auth::id(),
        ]);
    }

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

        $allRecords->transform(function ($record) use ($skuParentMap, $parentSupplierMap, $shopifyImages, $productValuesMap) {
            $sku = strtoupper(trim(preg_replace('/\s+/', ' ', $record->our_sku ?? '')));

            $parent = $skuParentMap[$sku] ?? null;

            if (empty($record->parent) && $parent) {
                $record->parent = $parent;
            }

            $parentKey = strtoupper(trim(preg_replace('/\s+/', ' ', $record->parent ?? '')));
            $record->supplier_names = $parentSupplierMap[$parentKey] ?? [];

            $record->image_src = $shopifyImages[$sku] ?? null;
            $record->Values = $productValuesMap[$sku] ?? null;
            $record->created_by_name = $record->user->name ?? '—';

            return $record;
        });

        $groupedData = $allRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (!isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        return view('purchase-master.transit_container.arrived-conatiner', [
            'tabs' => $tabs,
            'groupedData' => $groupedData
        ]);
    }

    public function pushArrivedContainer(Request $request)
    {
        $tabName = $request->input('tab_name');
        $rows = $request->input('data', []);

        $userId = auth()->id();

        foreach ($rows as $row) {
            $transitId = $row['id'] ?? null;
            $model = ArrivedContainer::updateOrCreate(
                [
                    'transit_container_id' => $transitId,
                    'tab_name'          => $row['tab_name'] ?? $tabName,
                ],
                [
                    'tab_name'          => $row['tab_name'] ?? null,
                    'our_sku'          => $row['our_sku'] ?? null,
                    'supplier_name'    => $row['supplier_name'] ?? null,
                    'company_name'     => $row['company_name'] ?? null,
                    'parent'           => $row['parent'] ?? null,
                    'no_of_units'      => !empty($row['no_of_units']) ? (int) $row['no_of_units'] : null,
                    'total_ctn'       => !empty($row['total_ctn']) ? (int) $row['total_ctn'] : null,
                    'rate'              => !empty($row['rate']) ? (float) $row['rate'] : null,
                    'unit'              => $row['unit'] ?? null,
                    'changes'           => $row['changes'] ?? null,
                    'package_size'      => $row['package_size'] ?? null,
                    'product_size_link' => $row['product_size_link'] ?? null,
                    'comparison_link'   => $row['comparison_link'] ?? null,
                    'order_link'        => $row['order_link'] ?? null,
                    'image_src'         => $row['image_src'] ?? null,
                    'photos'            => $row['photos'] ?? null,
                    'specification'     => $row['specification'] ?? null,
                    'created_by'        => $userId,
                ]
            );

            $this->logHistory('pushed_from_transit', $model->id, null, $model->tab_name, $model->our_sku, [
                'transit_container_id' => $transitId,
                'tab_name' => $model->tab_name,
                'sku' => $model->our_sku,
            ]);

            if (!empty($row['id'])) {
                TransitContainerDetail::where('id', $row['id'])->update([
                    'status' => 'inactive',
                ]);
            }

            // End-of-cycle handling for the SKU we just Arrived.
            //
            // User intent ("the cycle ends here, save in archived"):
            //   1. The SKU must no longer appear on the MIP In Progress page.
            //   2. Its data should remain available via the MIP page's "Show archived"
            //      toggle (i.e. soft-deleted, restorable — same behavior as the existing
            //      Archive button on MIP), not hard-deleted.
            //   3. If a stage is set on the row in forecast_analysis we clear it back to
            //      "Select stage" (empty), so the forecast page applies its own rule
            //      format from there instead of leaving a misleading stage like 'mip' /
            //      'transit' / 'r2s' hanging around. We don't put 'all_good' there — that
            //      would just be another active stage instead of the empty "Select" state.
            $rawSku = (string) ($row['our_sku'] ?? '');
            $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', $rawSku)));

            if ($normSku !== '') {
                $rawParent = (string) ($row['parent'] ?? '');
                $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', $rawParent)));

                // (1+2) Archive any matching mfrg_progress row(s) — soft delete via the
                // SoftDeletes trait. Mirrors what /mfrg-progresses/delete already does
                // when a user clicks the MIP "Archive" button, so the row shows up under
                // the same "Show archived" filter and can be restored from there.
                MfrgProgress::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                    ->delete();

                // Same for ready_to_ship rows still on R2S (transit_inv_status = 0) — if
                // the SKU was an RTS row showing on MIP it must also drop off.
                ReadyToShip::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                    ->where('transit_inv_status', 0)
                    ->delete();

                // (3) Clear the stage in forecast_analysis so it goes back to the
                // "Select stage" / empty state and the forecast page applies its rules.
                // Parent-scoped match first so SKUs reused across parents don't bleed.
                $cleared = 0;
                if ($normParent !== '') {
                    $cleared = (int) DB::table('forecast_analysis')
                        ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                        ->whereRaw('TRIM(UPPER(COALESCE(parent, ?))) = ?', ['', $normParent])
                        ->update(['stage' => '', 'updated_at' => now()]);
                }
                if ($cleared === 0) {
                    DB::table('forecast_analysis')
                        ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                        ->update(['stage' => '', 'updated_at' => now()]);
                }
                // No forecast row? Do NOT insert one — the user said "if stage is
                // required" it should go to Select; if there isn't one, no stage is
                // required and we shouldn't fabricate a row.
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Inventory pushed successfully',
            'count'   => count($rows),
        ]);
    }

    public function containerSummary(Request $request)
    {
        $containers = ArrivedContainer::where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->get();
        //  OnSeaTransit::all();
        return view('purchase-master.transit_container.container-summary', ['onSeaTransitData' => [], 'chinaLoadMap' => []]);
    }

    /**
     * Save / update a row in Arrived Container (Tabulator cell edits).
     */
    public function saveArrivedRow(Request $request)
    {
        $data = $request->all();
        $tabName = $data['tab_name'] ?? null;
        if (empty($tabName)) {
            return response()->json(['success' => false, 'message' => 'Tab name is missing.'], 422);
        }

        $payload = [
            'tab_name' => $tabName,
            'our_sku' => $data['our_sku'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'parent' => $data['parent'] ?? null,
            'no_of_units' => isset($data['no_of_units']) && $data['no_of_units'] !== '' ? (int) $data['no_of_units'] : null,
            'total_ctn' => isset($data['total_ctn']) && $data['total_ctn'] !== '' ? (int) $data['total_ctn'] : null,
            'rate' => isset($data['rate']) && $data['rate'] !== '' ? (float) $data['rate'] : null,
            'unit' => $data['unit'] ?? null,
            'changes' => $data['changes'] ?? null,
            'package_size' => $data['package_size'] ?? null,
            'product_size_link' => $data['product_size_link'] ?? null,
            'comparison_link' => $data['comparison_link'] ?? null,
            'order_link' => $data['order_link'] ?? null,
            'image_src' => $data['image_src'] ?? null,
            'photos' => $data['photos'] ?? null,
            'specification' => $data['specification'] ?? null,
        ];

        if (!empty($data['transit_container_id'])) {
            $payload['transit_container_id'] = (int) $data['transit_container_id'];
        }

        if (!empty($data['id'])) {
            $row = ArrivedContainer::find($data['id']);
            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Row not found.'], 404);
            }
            $fromTab = $row->tab_name;
            $toTab = $tabName;
            $fieldDiff = [];
            foreach ($payload as $key => $newVal) {
                if ($key === 'tab_name') {
                    continue;
                }
                $oldVal = $row->getAttribute($key);
                $oldNorm = $oldVal === null ? '' : (string) $oldVal;
                $newNorm = $newVal === null ? '' : (string) $newVal;
                if ($oldNorm !== $newNorm) {
                    $fieldDiff[$key] = ['from' => $oldVal, 'to' => $newVal];
                }
            }
            $row->fill($payload);
            $row->save();

            if ($fromTab !== $toTab) {
                $this->logHistory('row_moved', $row->id, $fromTab, $toTab, $row->our_sku, [
                    'sku' => $row->our_sku,
                    'from' => $fromTab,
                    'to' => $toTab,
                ]);
            }
            if (!empty($fieldDiff)) {
                $this->logHistory('row_updated', $row->id, null, $toTab, $row->our_sku, $fieldDiff);
            }
        } else {
            $payload['created_by'] = Auth::id();
            $row = ArrivedContainer::create($payload);
            $this->logHistory('row_created', $row->id, null, $row->tab_name, $row->our_sku, null);
        }

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    /**
     * History for Arrived Container (same filters as transit container history).
     */
    public function getHistory(Request $request)
    {
        $query = ArrivedContainerHistory::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('tab_name')) {
            $tab = trim($request->tab_name);
            $query->where(function ($q) use ($tab) {
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