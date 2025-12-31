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

        // Improved normalization to handle multiple spaces and hidden whitespace
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse multiple spaces to single space
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace characters
            return trim($sku);
        };

        // Get stage data from forecast_analysis table - match by SKU only, prefer record with stage value
        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->groupBy(function($item) use ($normalizeSku) {
                return $normalizeSku($item->sku);
            })
            ->map(function($group) {
                // Prefer record with non-empty stage value
                $withStage = $group->firstWhere('stage', '!=', null);
                if ($withStage && !empty(trim($withStage->stage))) {
                    return $withStage;
                }
                return $group->first();
            });

        $readyToShipData = ReadyToShip::where('transit_inv_status', 0)->whereNull('deleted_at')->get();

        $readyToShipData = $readyToShipData->filter(function ($item) use ($forecastData, $normalizeSku) {
            $sku = $normalizeSku($item->sku);
            // Only include SKUs where stage is 'r2s' in forecast_analysis
            if ($forecastData->has($sku)) {
                $forecast = $forecastData->get($sku);
                $stage = strtolower(trim($forecast->stage ?? ''));
                return $stage === 'r2s';
            }
            // If no forecast record found, exclude it
            return false;
        });

        $readyToShipData->transform(function ($item) use ($supplierMapByParent, $shopifyImages, $productMaster, $forecastData, $normalizeSku) {
            $sku = $normalizeSku($item->sku);
            $parent = strtoupper(trim($item->parent ?? ''));
            $item->supplier_names = $supplierMapByParent[$parent] ?? [];

            $image = null;
            $cbm = null;

            // Try to get image from shopify_skus first
            if (isset($shopifyImages[$sku]) && !empty($shopifyImages[$sku]->image_src)) {
                $image = $shopifyImages[$sku]->image_src;
            }

            // If still no image, try direct database lookup with flexible matching
            if (empty($image) && !empty($item->sku)) {
                try {
                    // Try exact match first
                    $directImage = DB::table('shopify_skus')
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                        ->whereNotNull('image_src')
                        ->where('image_src', '!=', '')
                        ->value('image_src');
                    
                    // If no exact match, try pattern matching
                    if (empty($directImage)) {
                        $skuPattern = str_replace(' ', '%', $sku);
                        $directImage = DB::table('shopify_skus')
                            ->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['%' . $skuPattern . '%'])
                            ->whereNotNull('image_src')
                            ->where('image_src', '!=', '')
                            ->value('image_src');
                    }
                    
                    // If still no match, try without spaces
                    if (empty($directImage)) {
                        $skuNoSpaces = str_replace(' ', '', $sku);
                        $directImage = DB::table('shopify_skus')
                            ->whereRaw('UPPER(REPLACE(sku, " ", "")) = ?', [$skuNoSpaces])
                            ->whereNotNull('image_src')
                            ->where('image_src', '!=', '')
                            ->value('image_src');
                    }
                    
                    if (!empty($directImage)) {
                        $image = $directImage;
                    }
                } catch (\Exception $e) {
                    // Silently fail if database query has issues
                    Log::warning('Image lookup query failed for SKU: ' . $item->sku, ['error' => $e->getMessage()]);
                }
            }

            // Try to get image from product_master if still not found
            if (empty($image)) {
                // Try multiple SKU variations
                $skuVariations = [
                    $sku,
                    str_replace(' ', '', $sku),
                    str_replace(' ', ' ', $sku),
                ];

                $productRow = null;
                foreach ($skuVariations as $skuVar) {
                    if (isset($productMaster[$skuVar])) {
                        $productRow = $productMaster[$skuVar];
                        break;
                    }
                }
                
                // If still no product master, try direct database lookup
                if (!$productRow && !empty($item->sku)) {
                    try {
                        $directProduct = DB::table('product_master')
                            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                            ->orWhereRaw('UPPER(TRIM(sku)) LIKE ?', ['%' . str_replace(' ', '%', $sku) . '%'])
                            ->first();
                        
                        if ($directProduct) {
                            $productRow = $directProduct;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Product master lookup query failed for SKU: ' . $item->sku, ['error' => $e->getMessage()]);
                    }
                }

                if ($productRow) {
                    $valuesRaw = $productRow->Values ?? '{}';
                    $values = json_decode($valuesRaw, true);

                    if (is_array($values)) {
                        if (!empty($values['image_path']) && empty($image)) {
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
                } else {
                    Log::warning("SKU missing in product_master: [$sku] <- original: [{$item->sku}]");
                }
            } else {
                // Image found from shopify_skus, but still need to get CBM from product_master
                if (isset($productMaster[$sku])) {
                    $valuesRaw = $productMaster[$sku]->Values ?? '{}';
                    $values = json_decode($valuesRaw, true);

                    if (is_array($values)) {
                        if (isset($values['cbm'])) {
                            $cbm = (float) $values['cbm'];
                        } else {
                            Log::warning("CBM missing in values for SKU: $sku");
                        }
                    }
                }
            }

            // Get stage and nr from forecast_analysis
            $stage = '';
            $nr = '';
            if ($forecastData->has($sku)) {
                $forecast = $forecastData->get($sku);
                $stage = $forecast->stage ?? '';
                $nr = strtoupper(trim($forecast->nr ?? ''));
                // Normalize stage value to lowercase
                if (!empty($stage)) {
                    $stage = strtolower(trim($stage));
                }
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
