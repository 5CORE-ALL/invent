<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JungleScoutProductData;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;

class ForecastAnalysisController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    private function buildForecastAnalysisData()
    {
        // Improved normalization to handle multiple spaces and hidden whitespace
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse multiple spaces to single space
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace characters
            return trim($sku);
        };

        $jungleScoutData = JungleScoutProductData::query()
            ->get()
            ->groupBy(fn($item) => $normalizeSku($item->parent))
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : [];
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->pluck('data.price');

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : [];
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });

        $productListData = DB::table('product_master')->whereNull('deleted_at')->get();

        // Load all shopify data and normalize SKUs for matching
        $shopifyData = ShopifySku::all()->keyBy(function($item) use ($normalizeSku) {
            return $normalizeSku($item->sku);
        });


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

        // Key forecastMap by SKU only for easier matching
        // If multiple records exist for same SKU, prefer the one with non-empty stage value, then non-empty nr value
        $forecastMap = DB::table('forecast_analysis')->get()->groupBy(function($item) use ($normalizeSku) {
            return $normalizeSku($item->sku);
        })->map(function($group) {
            // Prefer record with non-empty stage value
            $withStage = $group->firstWhere('stage', '!=', null);
            if ($withStage && !empty(trim($withStage->stage))) {
                return $withStage;
            }
            // Otherwise prefer one with non-empty nr
            $withNr = $group->firstWhere('nr', '!=', null);
            if ($withNr && !empty(trim($withNr->nr))) {
                return $withNr;
            }
            return $group->first();
        });
        $movementMap = DB::table('movement_analysis')->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $readyToShipMap = DB::table('ready_to_ship')->where('transit_inv_status', 0)->whereNull('deleted_at')->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $mfrg = DB::table('mfrg_progress')->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $purchases = DB::table('purchases')->whereNull('deleted_at')
            ->select('items')
            ->get()
            ->flatMap(function ($row) {
                $items = json_decode($row->items);

                if (!is_array($items)) return [];
                
                return collect($items)->mapWithKeys(function ($item) {
                    if (!isset($item->sku)) return [];
                    return [$item->sku => $item];
                });
            });

        // Get container records that have been successfully pushed to warehouse
        $warehousePushedRecords = DB::table('inventory_warehouse')
            ->where('push_status', 'success')
            ->select('our_sku', 'tab_name')
            ->get()
            ->map(function($record) {
                return [
                    'sku' => strtoupper(trim($record->our_sku)),
                    'container' => strtoupper(trim($record->tab_name ?? ''))
                ];
            })
            ->toArray();

        $transitContainer = TransitContainerDetail::whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')
                ->orWhere('status', '');
            })
            ->select('our_sku', 'tab_name', 'no_of_units', 'total_ctn', 'rate')
            ->get()
            ->filter(function ($item) use ($warehousePushedRecords) {
                // Only exclude specific container records that have been pushed to warehouse
                $normalizedSku = strtoupper(trim($item->our_sku));
                $containerName = strtoupper(trim($item->tab_name ?? ''));
                
                // Check if this specific SKU + Container combination has been pushed to warehouse
                foreach ($warehousePushedRecords as $pushedRecord) {
                    if ($pushedRecord['sku'] === $normalizedSku && 
                        (strpos($containerName, $pushedRecord['container']) !== false || 
                         strpos($pushedRecord['container'], $containerName) !== false)) {
                        return false; // Exclude this specific container record
                    }
                }
                return true; // Keep this record
            })
            ->groupBy(fn($item) => strtoupper(trim($item->our_sku)))
            ->map(function ($group) {
                $transitSum = 0;
                $transitValueSum = 0; // Sum of (qty * rate) for each row (like transit-container-details page)
                $rate = 0;
                foreach ($group as $row) {
                    $no_of_units = (float) ($row->no_of_units ?? 0);
                    $total_ctn = (float) ($row->total_ctn ?? 0);
                    $rowRate = (float) ($row->rate ?? 0);
                    $qty = $no_of_units * $total_ctn;
                    $transitSum += $qty;
                    // Calculate value as qty * rate for each row (like transit-container-details page: amount = qty * rate)
                    if ($qty > 0 && $rowRate > 0) {
                        $transitValueSum += ($qty * $rowRate);
                    }
                    if (!empty($row->rate)) {
                        $rate = $rowRate; // Keep last rate (for backward compatibility)
                    }
                }

                return (object)[
                    'tab_name' => $group->pluck('tab_name')->unique()->implode(', '),
                    'transit' => $transitSum,
                    'rate' => $rate,
                    'transit_value' => $transitValueSum, // Total value as sum of (qty * rate) for all rows
                ];
            })
            ->keyBy(fn($item, $key) => $key);



        $processedData = [];

        foreach ($productListData as $prodData) {
            $sheetSku = $normalizeSku($prodData->sku);
            if (empty($sheetSku)) continue;

            $item = new \stdClass();
            $item->SKU = $sheetSku;
            $item->Parent = $normalizeSku($prodData->parent ?? '');
            $item->is_parent = stripos($sheetSku, 'PARENT') !== false;
            $item->{'Supplier Tag'} = isset($supplierMapByParent[$item->Parent]) ? implode(', ', array_unique($supplierMapByParent[$item->Parent])) : '';

            $valuesRaw = $prodData->Values ?? '{}';
            $values = json_decode($valuesRaw, true);

            $item->{'CP'} = $values['cp'] ?? '';
            $item->{'LP'} = $values['lp'] ?? '';
            $item->{'MOQ'} = $values['moq'] ?? '';
            $item->{'SH'} = $values['ship'] ?? '';
            $item->{'Freight'} = $values['frght'] ?? '';
            $item->{'CBM MSL'} = $values['cbm'] ?? '';
            $item->{'GW (LB)'} = $values['wt_act'] ?? '';
            $item->{'GW (KG)'} = is_numeric($values['wt_act'] ?? null) ? round($values['wt_act'] * 0.45, 2) : '';

            $shopify = $shopifyData[$sheetSku] ?? null;
            
            // If exact match fails, try to find by removing all spaces (fallback)
            if (!$shopify && !empty($sheetSku)) {
                $skuNoSpaces = str_replace(' ', '', $sheetSku);
                foreach ($shopifyData as $key => $value) {
                    if (str_replace(' ', '', $key) === $skuNoSpaces) {
                        $shopify = $value;
                        break;
                    }
                }
            }
            
            $imageFromShopify = $shopify ? ($shopify->image_src ?? null) : null;
            $imageFromProductMaster = $values['image_path'] ?? null;
            $item->Image = $imageFromShopify ?: $imageFromProductMaster;

            // Safely get inventory - check if shopify exists first
            $item->INV = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
            $item->L30 = ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0;
            
            // Calculate shopifyb2c_price and inv_value
            $shopifyb2c_price = ($shopify && isset($shopify->price)) ? (float)$shopify->price : 0;
            $item->shopifyb2c_price = $shopifyb2c_price;
            $item->inv_value = $item->INV * $shopifyb2c_price;
            
            // Calculate lp_value (LP * INV)
            $lp = is_numeric($item->{'LP'}) ? (float)$item->{'LP'} : 0;
            $item->lp_value = $lp * $item->INV;

            // if (!empty($item->Parent) && $jungleScoutData->has($item->Parent)) {
            //     $item->scout_data = json_decode(json_encode($jungleScoutData[$item->Parent]), true);
            // }

            // Initialize nr field to empty string first
            $item->nr = '';
            
            // Match forecast record by SKU only
            if ($forecastMap->has($sheetSku)) {
                $forecast = $forecastMap->get($sheetSku);
                $item->{'s-msl'} = $forecast->s_msl ?? 0;
                $item->{'Approved QTY'} = $forecast->approved_qty ?? 0;
                
                // Get and normalize nr value - preserve the value even if it's empty
                $nrValue = $forecast->nr ?? null;
                if ($nrValue !== null && $nrValue !== '') {
                    $item->nr = strtoupper(trim((string)$nrValue));
                    // Ensure it's a valid value
                    if (!in_array($item->nr, ['REQ', 'NR', 'LATER'])) {
                        $item->nr = 'REQ'; // Default to REQ if invalid
                    }
                } else {
                    $item->nr = ''; // Keep as empty string, formatter will default to REQ
                }
                
                $item->req = $forecast->req ?? '';
                $item->hide = $forecast->hide ?? '';
                $item->notes = $forecast->notes ?? '';
                $item->{'Clink'} = $forecast->clink ?? '';
                $item->{'Olink'} = $forecast->olink ?? '';
                $item->rfq_form_link = $forecast->rfq_form_link ?? '';
                $item->rfq_report = $forecast->rfq_report ?? '';
                $item->date_apprvl = $forecast->date_apprvl ?? '';
                // Normalize stage value: trim and convert to lowercase
                $stageValue = $forecast->stage ?? '';
                $item->stage = !empty($stageValue) ? strtolower(trim($stageValue)) : '';
            } else {
                // If key not found in map, try direct database lookup by SKU only
                $forecastRecord = DB::table('forecast_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sheetSku))])
                    ->orderByRaw("CASE WHEN stage IS NOT NULL AND stage != '' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN nr IS NOT NULL AND nr != '' THEN 0 ELSE 1 END")
                    ->first();
                
                if ($forecastRecord) {
                    $item->{'s-msl'} = $forecastRecord->s_msl ?? 0;
                    $item->{'Approved QTY'} = $forecastRecord->approved_qty ?? 0;
                    
                    // Get and normalize nr value
                    $nrValue = $forecastRecord->nr ?? null;
                    if ($nrValue !== null && $nrValue !== '') {
                        $item->nr = strtoupper(trim((string)$nrValue));
                        // Ensure it's a valid value
                        if (!in_array($item->nr, ['REQ', 'NR', 'LATER'])) {
                            $item->nr = 'REQ'; // Default to REQ if invalid
                        }
                    } else {
                        $item->nr = ''; // Keep as empty string, formatter will default to REQ
                    }
                    
                    $item->req = $forecastRecord->req ?? '';
                    $item->hide = $forecastRecord->hide ?? '';
                    $item->notes = $forecastRecord->notes ?? '';
                    $item->{'Clink'} = $forecastRecord->clink ?? '';
                    $item->{'Olink'} = $forecastRecord->olink ?? '';
                    $item->rfq_form_link = $forecastRecord->rfq_form_link ?? '';
                    $item->rfq_report = $forecastRecord->rfq_report ?? '';
                    $item->date_apprvl = $forecastRecord->date_apprvl ?? '';
                    // Normalize stage value: trim and convert to lowercase
                    $stageValue = $forecastRecord->stage ?? '';
                    $item->stage = !empty($stageValue) ? strtolower(trim($stageValue)) : '';
                }
            }

            $item->containerName = $transitContainer[$normalizeSku($prodData->sku)]->tab_name ?? '';
            $item->transit = $transitContainer[$normalizeSku($prodData->sku)]->transit ?? 0;
            $item->transit_rate = (float)($transitContainer[$normalizeSku($prodData->sku)]->rate ?? 0);
            $item->transit_value_calculated = (float)($transitContainer[$normalizeSku($prodData->sku)]->transit_value ?? 0); // Pre-calculated value from transit_container_details


            $readyToShipQty = 0;
            $r2sRate = 0;
            if($readyToShipMap->has($sheetSku)){
                $readyToShipData = $readyToShipMap->get($sheetSku);
                $readyToShipQty = $readyToShipData->qty ?? 0;
                $r2sRate = (float) ($readyToShipData->rate ?? 0);
                $item->readyToShipQty = $readyToShipQty;
            }
            $item->r2s_rate = $r2sRate; // Store rate for R2S Value calculation

            // MIP column should ONLY come from mfrg_progress table, no fallback to purchases
            $order_given = 0;
            $mipRate = 0;
            $mfrgReadyToShip = 'No'; // Default to 'No'
            if($mfrg->has($sheetSku)){
                $mfrgData = $mfrg->get($sheetSku);
                $mfrgReadyToShip = $mfrgData->ready_to_ship ?? 'No';
                if($mfrgReadyToShip === 'No' || $mfrgReadyToShip === ''){
                    $order_given = (float) ($mfrgData->qty ?? 0);
                    $mipRate = (float) ($mfrgData->rate ?? 0);
                }
            }
            // Removed purchases table fallback - MIP should only show mfrg_progress data
            $item->order_given = $order_given;
            $item->mip_rate = $mipRate; // Store rate for MIP Value calculation
            $item->mfrg_ready_to_ship = $mfrgReadyToShip; // Store ready_to_ship status from mfrg_progress table

            if ($movementMap->has($sheetSku)) {
                $months = json_decode($movementMap->get($sheetSku)->months ?? '{}', true);
                $months = is_array($months) ? $months : [];

                $monthNames = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'];
                $totalMonthCount = 0;
                $totalSum = 0;

                foreach ($monthNames as $month) {
                    $value = isset($months[$month]) && is_numeric($months[$month]) ? (int)$months[$month] : 0;
                    $item->{$month} = $value;
                    if ($value !== 0) $totalMonthCount++;
                    $totalSum += $value;
                }

                $item->{'Total'} = $totalSum;
                $item->{'Total month'} = $totalMonthCount;
                
                $msl = $item->{'Total month'} > 0 ? ($item->{'Total'} / $item->{'Total month'}) * 4 : 0;

                $effectiveMsl = (isset($item->{'s-msl'}) && $item->{'s-msl'} > 0) ? $item->{'s-msl'} : $msl;
                
                $lp = is_numeric($item->{'LP'}) ? (float)$item->{'LP'} : 0;
                $item->{'MSL_C'} = round($msl * $lp / 4, 2);

                $mslfour = $msl/4;

                $item->{'MSL_Four'} = round($msl / 4, 2);

                $item->{'MSL_SP'} = floor($shopifyb2c_price * $effectiveMsl / 4);
            }

            $cp = (float)($item->{'CP'} ?? 0);
            $orderQty = (float)($item->order_given ?? 0);
            $readyToShipQty = (float)($item->readyToShipQty ?? 0);
            $transit = (float)($transitContainer[$normalizeSku($prodData->sku)]->transit ?? 0);
            $transitRate = (float)($transitContainer[$normalizeSku($prodData->sku)]->rate ?? 0);
            $transitValueCalculated = (float)($transitContainer[$normalizeSku($prodData->sku)]->transit_value ?? 0);

            // MIP Value: Use qty * rate from mfrg_progress (like mfrg-in-progress page), fallback to CP * order_given if rate not available
            // For items with stage === 'mip', use rate * qty (matching mfrg-in-progress page calculation)
            if ($mipRate > 0 && $orderQty > 0) {
                $item->MIP_Value = round($mipRate * $orderQty, 2);
            } else {
                // Fallback to CP * order_given if rate not available
                $item->MIP_Value = round($cp * $orderQty, 2);
            }
            
            // R2S Value: Use qty * rate from ready_to_ship table (like ready-to-ship page), fallback to CP * readyToShipQty if rate not available
            // For items with stage === 'r2s', use rate * qty (matching ready-to-ship page calculation)
            if ($r2sRate > 0 && $readyToShipQty > 0) {
                $item->R2S_Value = round($r2sRate * $readyToShipQty, 2);
            } else {
                // Fallback to CP * readyToShipQty if rate not available
                $item->R2S_Value = round($cp * $readyToShipQty, 2);
            }
            
            // Transit Value: Use qty * rate from transit_container_details (like transit-container-details page), fallback to CP * transit if rate not available
            // In transit-container-details page: amount = (no_of_units * total_ctn) * rate for each row, then sum all rows
            // We've pre-calculated transit_value as sum of (qty * rate) for all rows with same SKU
            if ($transitValueCalculated > 0) {
                // Use pre-calculated value (sum of all (qty * rate) for this SKU from transit_container_details)
                $item->Transit_Value = round($transitValueCalculated, 2);
            } else if ($transit > 0 && $transitRate > 0) {
                // Fallback: if we have qty and rate, calculate directly
                $item->Transit_Value = round($transit * $transitRate, 2);
            } else {
                // Final fallback: CP * transit (for backward compatibility)
                $item->Transit_Value = round($cp * $transit, 2);
            }

            $processedData[] = $item;
        }

        return $processedData;
    }

    public function getViewForecastAnalysisData()
    {
        try {
            $processedData = $this->buildForecastAnalysisData();

            $totalMslC = collect($processedData)
                ->filter(function ($item) {
                    return !$item->is_parent;
                })
                ->sum(function ($item) {
                    return floatval($item->{'MSL_C'} ?? 0);
                });

            // Calculate total Transit Value from ALL transit_container_details records (like transit-container-details page)
            // This matches the transit-container-details page calculation: sum of (qty * rate) for ALL rows across ALL tabs
            $totalTransitValue = TransitContainerDetail::whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('status')
                    ->orWhere('status', '');
                })
                ->get()
                ->sum(function ($row) {
                    $no_of_units = (float) ($row->no_of_units ?? 0);
                    $total_ctn = (float) ($row->total_ctn ?? 0);
                    $rate = (float) ($row->rate ?? 0);
                    $qty = $no_of_units * $total_ctn;
                    return $qty * $rate; // Calculate qty * rate for each row (like transit-container-details page)
                });

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $processedData,
                'total_msl_c' => round($totalMslC, 2),
                'total_transit_value' => round($totalTransitValue, 2), // Total Transit Value from ALL transit_container_details records
                'status' => 200,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forecastAnalysis(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('purchase-master.forecastAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function approvalRequired(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('purchase-master.approvalRequired', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function transit(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('purchase-master.transit', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function updateForcastSheet(Request $request)
    {        
        $sku = trim($request->input('sku'));
        $parent = trim($request->input('parent'));
        $column = trim($request->input('column'));
        $value = trim($request->input('value')); // Trim the value to remove whitespace

        // Handle MOQ updates separately - save to ProductMaster
        if (strtoupper($column) === 'MOQ') {
            $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
            
            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found']);
            }

            // Get current Values or initialize empty array
            $values = is_array($product->Values) ? $product->Values : (json_decode($product->Values, true) ?? []);
            
            // Update MOQ value
            $values['moq'] = $value;
            
            // Save back to ProductMaster
            $product->Values = $values;
            $product->save();

            return response()->json(['success' => true, 'message' => 'MOQ updated successfully']);
        }

        $columnMap = [
            'S-MSL' => 's_msl',
            'Approved QTY' => 'approved_qty',
            'NR' => 'nr',
            'REQ' => 'req',
            'Hide' => 'hide',
            'Notes' => 'notes',
            'Clink' => 'clink',
            'Olink' => 'olink',
            'rfq_form_link' => 'rfq_form_link',
            'rfq_report' => 'rfq_report',
            'order_given' => 'order_given',
            'Transit' => 'transit',
            'Date of Appr' => 'date_apprvl',
            'Stage' => 'stage',
        ];

        $columnKey = $columnMap[$column] ?? null;

        if (!$columnKey) {
            return response()->json(['success' => false, 'message' => 'Invalid column']);
        }

        // Match by SKU only - prefer record with stage value if multiple exist
        $existing = DB::table('forecast_analysis')
            ->select('*')
            ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
            ->orderByRaw("CASE WHEN stage IS NOT NULL AND stage != '' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN nr IS NOT NULL AND nr != '' THEN 0 ELSE 1 END")
            ->first();

        if ($existing) {
            $currentValue = $existing->{$columnKey} ?? null;
            
            // Normalize NR values to uppercase
            if ($columnKey === 'nr' && !empty($value)) {
                $value = strtoupper(trim($value));
                // Ensure value is one of the valid options
                if (!in_array($value, ['REQ', 'NR', 'LATER'])) {
                    $value = 'REQ'; // Default to REQ if invalid
                }
            }

            // Normalize Stage values to lowercase
            if ($columnKey === 'stage' && !empty($value)) {
                $value = strtolower(trim($value));
                // Ensure value is one of the valid options
                $validStages = ['appr_req', 'mip', 'r2s', 'transit', 'all_good', 'to_order_analysis'];
                if (!in_array($value, $validStages)) {
                    $value = ''; // Default to empty if invalid
                }
            }

            if ((string)$currentValue !== (string)$value) {
                DB::table('forecast_analysis')
                    ->where('id', $existing->id)
                    ->update([$columnKey => $value, 'updated_at' => now()]);
            }

            if (strtolower($column) === 'stage'){
                // Get MOQ from ProductMaster table (not from approved_qty)
                $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
                $moqValue = null;
                if ($product && $product->Values) {
                    $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                    $moqValue = $values['moq'] ?? null;
                }
                
                // Use MOQ value from ProductMaster
                $orderQty = $moqValue ?? null;
                
                if(strtolower($value) === 'to_order_analysis'){
                    DB::table('to_order_analysis')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'approved_qty' => $orderQty,
                            'date_apprvl' => now()->toDateString(),
                            'stage' => '',
                            'auth_user' => Auth::user()->name,
                            'updated_at' => now(),
                            'created_at' => now(),
                            'deleted_at' => null,
                        ]
                    );
                }

                if(strtolower($value) === 'mip'){
                    // Always use MOQ value from ProductMaster
                    $qtyToSet = (int)($orderQty ?? 0);
                    
                    // Check if record exists - match by SKU only
                    $existingMfrg = DB::table('mfrg_progress')
                        ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
                        ->orderByRaw("CASE WHEN ready_to_ship = 'No' THEN 0 ELSE 1 END")
                        ->first();
                    
                    if ($existingMfrg) {
                        // Update existing record - explicitly set qty to MOQ value
                        DB::table('mfrg_progress')
                            ->where('id', $existingMfrg->id)
                            ->update([
                                'qty' => $qtyToSet,
                                'ready_to_ship' => 'No',
                                'parent' => $parent, // Update parent if different
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Insert new record
                        DB::table('mfrg_progress')->insert([
                            'sku' => $sku,
                            'parent' => $parent,
                            'qty' => $qtyToSet,
                            'ready_to_ship' => 'No',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if(strtolower($value) === 'r2s'){
                    DB::table('ready_to_ship')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'qty' => $orderQty,
                            'transit_inv_status' => 0,
                            'auth_user' => Auth::user()->name,
                            'updated_at' => now(),
                            'created_at' => now(),
                            'deleted_at' => null,
                        ]
                    );
                }
            }

            return response()->json(['success' => true, 'message' => 'Updated or already up-to-date']);
        } else {
            // Normalize NR values to uppercase before inserting
            if ($columnKey === 'nr' && !empty($value)) {
                $value = strtoupper(trim($value));
                // Ensure value is one of the valid options
                if (!in_array($value, ['REQ', 'NR', 'LATER'])) {
                    $value = 'REQ'; // Default to REQ if invalid
                }
            }

            // Normalize Stage values to lowercase before inserting
            if ($columnKey === 'stage' && !empty($value)) {
                $value = strtolower(trim($value));
                // Ensure value is one of the valid options
                $validStages = ['appr_req', 'mip', 'r2s', 'transit', 'all_good', 'to_order_analysis'];
                if (!in_array($value, $validStages)) {
                    $value = ''; // Default to empty if invalid
                }
            }
            
            DB::table('forecast_analysis')->insert([
                'sku' => $sku,
                'parent' => $parent,
                $columnKey => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (strtolower($column) === 'stage'){
                // Get MOQ from ProductMaster table (not from approved_qty)
                $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
                $moqValue = null;
                if ($product && $product->Values) {
                    $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                    $moqValue = $values['moq'] ?? null;
                }
                
                // Use MOQ value from ProductMaster
                $orderQty = $moqValue ?? null;
                    
                if(strtolower($value) === 'to_order_analysis'){
                    DB::table('to_order_analysis')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'approved_qty' => $orderQty,
                            'date_apprvl' => now()->toDateString(),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                if(strtolower($value) === 'mip'){
                    // Always use MOQ value from ProductMaster
                    $qtyToSet = (int)($orderQty ?? 0);
                    
                    // Check if record exists - match by SKU only
                    $existingMfrg = DB::table('mfrg_progress')
                        ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
                        ->orderByRaw("CASE WHEN ready_to_ship = 'No' THEN 0 ELSE 1 END")
                        ->first();
                    
                    if ($existingMfrg) {
                        // Update existing record - explicitly set qty to MOQ value
                        DB::table('mfrg_progress')
                            ->where('id', $existingMfrg->id)
                            ->update([
                                'qty' => $qtyToSet,
                                'ready_to_ship' => 'No',
                                'parent' => $parent, // Update parent if different
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Insert new record
                        DB::table('mfrg_progress')->insert([
                            'sku' => $sku,
                            'parent' => $parent,
                            'qty' => $qtyToSet,
                            'ready_to_ship' => 'No',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if(strtolower($value) === 'r2s'){
                    DB::table('ready_to_ship')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'qty' => $orderQty,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            return response()->json(['success' => true, 'message' => 'Inserted new row']);
        }
    }

    public function getSkuQuantity(Request $request)
    {
        try {
            $sku = $request->query('sku');
            $table = $request->query('table');

            if (!$sku) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU is required'
                ], 400);
            }

            // Normalize SKU
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $normalizedSku = $normalizeSku($sku);

            $exists = false;
            $quantity = 0;

            switch ($table) {
                case 'mfrg-progress':
                    $mfrg = MfrgProgress::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
                    if ($mfrg) {
                        $exists = true;
                        $quantity = floatval($mfrg->qty) ?: 0;
                    }
                    break;

                case 'ready-to-ship':
                    $r2s = ReadyToShip::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
                    if ($r2s) {
                        $exists = true;
                        $quantity = floatval($r2s->rec_qty) ?: 0;
                    }
                    break;

                case 'transit':
                    $transit = TransitContainerDetail::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])
                        ->whereNull('deleted_at')
                        ->first();
                    if ($transit) {
                        $exists = true;
                        $quantity = floatval($transit->qty) ?: 0;
                    }
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid table name'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'quantity' => $quantity
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
