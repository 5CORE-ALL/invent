<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReadyToShipController extends Controller
{
    public function index()
    {
        $supplierRows = Supplier::where('type', 'Supplier')->get();

        $supplierMapByParent = [];
        foreach ($supplierRows as $row) {
            $parents = array_map('trim', explode(',', strtoupper($row->parent ?? '')));
            foreach ($parents as $parent) {
                if (!empty($parent)) {
                    $supplierMapByParent[$parent][] = $row->name;
                }
            }
        }

        foreach ($supplierMapByParent as $parent => $suppliers) {
            $supplierMapByParent[$parent] = array_unique($suppliers);
        }

        $shopifyImages = DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        $productMaster = DB::table('product_master')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        // Get stage data from forecast_analysis table
        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        $readyToShipData = ReadyToShip::where('transit_inv_status', 0)->get();

        $readyToShipData->transform(function ($item) use ($supplierMapByParent, $shopifyImages, $productMaster, $forecastData) {
            $sku = strtoupper(trim($item->sku));
            $parent = strtoupper(trim($item->parent ?? ''));
            $item->supplier_names = $supplierMapByParent[$parent] ?? [];

            $image = null;
            $cbm = null;

            if (isset($shopifyImages[$sku]) && !empty($shopifyImages[$sku]->image_src)) {
                $image = $shopifyImages[$sku]->image_src;
            }

            if (!isset($productMaster[$sku])) {
                Log::warning("SKU missing in product_master: [$sku] <- original: [{$item->sku}]");
            } else {
                $valuesRaw = $productMaster[$sku]->Values ?? '{}';
                $values = json_decode($valuesRaw, true);

                if (is_array($values)) {
                    if (!empty($values['image_path'])) {
                        $image = 'storage/' . ltrim($values['image_path'], '/');
                    }

                    if (isset($values['cbm'])) {
                        $cbm = (float) $values['cbm'];
                    } else {
                        Log::warning("CBM missing in values for SKU: $sku");
                    }
                } else {
                    Log::warning("Values decode failed for SKU: $sku");
                }
            }

            // Get stage and nr from forecast_analysis
            $stage = '';
            $nr = '';
            if (isset($forecastData[$sku])) {
                $stage = $forecastData[$sku]->stage ?? '';
                $nr = strtoupper(trim($forecastData[$sku]->nr ?? ''));
            }
            $item->stage = $stage;
            $item->nr = $nr;
            $item->order_qty = $item->qty; // Add order_qty field for validation

            $item->Image = $image;
            $item->CBM = $cbm;
            return $item;
        });

        // Create supplier to zone mapping
        $supplierZoneMap = Supplier::where('type', 'Supplier')
            ->whereNotNull('zone')
            ->pluck('zone', 'name')
            ->toArray();

        return view('purchase-master.ready-to-ship.index', [
            'readyToShipList' => $readyToShipData,
            'suppliers' => Supplier::pluck('name'),
            'supplierZoneMap' => $supplierZoneMap,
        ]);
    }


    public function inlineUpdateBySku(Request $request)
    {
        $sku = $request->input('sku');
        $column = $request->input('column');
        $value = $request->input('value');
        $item = ReadyToShip::where('sku', $sku)->first();
        $qty = $item->qty;

        if($column === 'rec_qty'){
            $value = is_numeric($value) ? (int)$value : null;
            if($value !== null) {
                $item->qty = $qty - $value;
                $item->save();
            }
        }

        if (!in_array($column, [
            'rec_qty',
            'rate',
            'area',
            'pay_term',
            'payment_confirmation',
            'supplier',
        ])) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        $readyToShip = ReadyToShip::where('sku', $sku)->first();

        if (!$readyToShip) {
            return response()->json(['success' => false, 'message' => 'SKU not found in ready_to_ships']);
        }

        $readyToShip->$column = $value;
        $readyToShip->save();

        return response()->json(['success' => true]);
    }

    public function revertBackMfrg(Request $request)
    {
        $skus = $request->input('skus');

        if (!is_array($skus) || empty($skus)) {
            return response()->json(['success' => false, 'message' => 'No SKUs provided.']);
        }

        try {
            ReadyToShip::whereIn('sku', $skus)->delete();
            MfrgProgress::whereIn('sku', $skus)->update(['ready_to_ship' => 'No']);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
        }
    }

    public function moveToTransit(Request $request)
    {
        $skus = $request->input('skus', []);
        $tabName = trim($request->input('tab_name'));

        if (empty($skus)) {
            return response()->json(['success' => false, 'message' => 'No SKUs provided.']);
        }

        $readyItems = ReadyToShip::whereIn('sku', $skus)->get();

        foreach ($readyItems as $item) {
            $existing = TransitContainerDetail::where('our_sku', $item->sku)->where('tab_name', $tabName)->first();
            if ($existing) {
                $existing->update([
                    'our_sku'       => $item->sku,
                    'tab_name'      => $tabName,
                    'rec_qty'       => $item->rec_qty,
                    'updated_at'    => now(),
                ]);
                $item->update([
                    'rec_qty' => NULL,
                    'updated_at' => now(),
                ]);
            } else {
                TransitContainerDetail::create([
                    'our_sku'       => $item->sku,
                    'tab_name'      => $tabName,
                    'rec_qty'       => $item->rec_qty,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $item->update([
                    'rec_qty' => NULL,
                    'updated_at' => now(),
                ]);
            }

            if($item->qty === 0){
                $item->update([
                    'transit_inv_status' => 1,
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Data moved to TransitContainerDetail.']);
    }

    public function deleteItems(Request $request)
    {
        try {
            $ids = $request->input('skus', []);

            if (!empty($ids)) {
                $user = auth()->check() ? auth()->user()->name : 'System';

                ReadyToShip::whereIn('sku', $ids)->update([
                    'auth_user' => $user,
                    'deleted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Records soft-deleted successfully by ' . $user,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No IDs provided',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage(),
            ], 500);
        }
    }


}
