<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MFRGInProgressController extends Controller
{
    public function index()
    {
        $mfrgData = MfrgProgress::all();

        $shopifyImages = DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        $productMaster = DB::table('product_master')->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        // Get stage data from forecast_analysis table
        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        // Supplier Table Parent Mapping
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

        // Fetch purchase orders and create SKU to price mapping
        $purchaseOrders = PurchaseOrder::whereNotNull('items')->get();
        $skuToPriceMap = [];
        $normalizeSku = fn($sku) => strtoupper(trim($sku ?? ''));
        
        foreach ($purchaseOrders as $po) {
            $items = json_decode($po->items, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['sku']) && isset($item['price'])) {
                        $normalizedSku = $normalizeSku($item['sku']);
                        // Use latest price if multiple POs have same SKU
                        if (!isset($skuToPriceMap[$normalizedSku]) || $po->created_at > ($skuToPriceMap[$normalizedSku]['date'] ?? '')) {
                            $skuToPriceMap[$normalizedSku] = [
                                'price' => $item['price'],
                                'currency' => $item['currency'] ?? 'USD',
                                'date' => $po->created_at
                            ];
                        }
                    }
                }
            }
        }

        foreach ($mfrgData as $row) {
            $sku = strtoupper(trim($row->sku));
            $image = null;
            $cbm = null;
            $parent = null;
            $supplierNames = [];
            $priceFromPO = null;
            $currencyFromPO = null;

            // Shopify Image
            if (isset($shopifyImages[$sku]) && !empty($shopifyImages[$sku]->image_src)) {
                $image = $shopifyImages[$sku]->image_src;
            }

            // Product Master Data
            if (isset($productMaster[$sku])) {
                $productRow = $productMaster[$sku];
                $values = json_decode($productRow->Values ?? '{}', true);

                if (is_array($values)) {
                    if (!empty($values['image_path'])) {
                        $image = 'storage/' . ltrim($values['image_path'], '/');
                    }
                    if (isset($values['cbm'])) {
                        $cbm = $values['cbm'];
                    }
                }

                $parent = strtoupper(trim($productRow->parent));
            }

            // Supplier from Parent Mapping
            if (!empty($parent) && isset($supplierMapByParent[$parent])) {
                $supplierNames = $supplierMapByParent[$parent];
            }

            if (!empty($row->supplier)) {
                $row->supplier = $row->supplier; // keep manual value
            } else {
                $row->supplier = implode(', ', $supplierNames); // mapping value
            }

            // Get price from purchase_order if available
            if (isset($skuToPriceMap[$sku])) {
                $priceFromPO = $skuToPriceMap[$sku]['price'];
                $currencyFromPO = $skuToPriceMap[$sku]['currency'];
            }

            // Get stage and nr from forecast_analysis
            $stage = '';
            $nr = '';
            if (isset($forecastData[$sku])) {
                $stage = $forecastData[$sku]->stage ?? '';
                $nr = strtoupper(trim($forecastData[$sku]->nr ?? ''));
            }
            $row->stage = $stage;
            $row->nr = $nr;
            $row->order_qty = $row->qty; // Add order_qty field for validation

            $row->Image = $image;
            $row->CBM = $cbm;
            $row->price_from_po = $priceFromPO;
            $row->currency_from_po = $currencyFromPO;
        }


        $suppliers = Supplier::pluck('name');
        
        return view('purchase-master.mfrg-progress.index', [
            'data' => $mfrgData,
            'suppliers' => $suppliers,
        ]);
    }

    public function newMfrgView(){
        return view('purchase-master.mfrg-progress.mfrg-new');
    }

    public function getMfrgProgressData()
    {
        $normalizeSku = fn($sku) => strtoupper(
            preg_replace('/\s+/', ' ',
                trim(
                    str_replace(["\xC2\xA0","\xE2\x80\x8B","\r","\n","\t"], ' ', $sku)
                )
            )
        );

        $mfrgData = MfrgProgress::all();

        $shopifyImages = DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        $productMaster = DB::table('product_master')
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        // Get stage data from forecast_analysis table
        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        $supplierRows = Supplier::where('type', 'Supplier')->get();
        $supplierMapByParent = [];
        foreach ($supplierRows as $row) {
            $parents = array_map('trim', explode(',', $normalizeSku($row->parent ?? '')));
            foreach ($parents as $parent) {
                if (!empty($parent)) {
                    $supplierMapByParent[$parent][] = $row->name;
                }
            }
        }

        // Fetch purchase orders and create SKU to price mapping
        $purchaseOrders = PurchaseOrder::whereNotNull('items')->get();
        $skuToPriceMap = [];
        
        foreach ($purchaseOrders as $po) {
            $items = json_decode($po->items, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['sku']) && isset($item['price'])) {
                        $normalizedSku = $normalizeSku($item['sku']);
                        // Use latest price if multiple POs have same SKU
                        if (!isset($skuToPriceMap[$normalizedSku]) || $po->created_at > ($skuToPriceMap[$normalizedSku]['date'] ?? '')) {
                            $skuToPriceMap[$normalizedSku] = [
                                'price' => $item['price'],
                                'currency' => $item['currency'] ?? 'USD',
                                'date' => $po->created_at
                            ];
                        }
                    }
                }
            }
        }

        $processedData = [];

        foreach ($mfrgData as $row) {
            $sku = $normalizeSku($row->sku);
            $image = null;
            $cbm = null;
            $parent = null;
            $supplierNames = [];
            $priceFromPO = null;
            $currencyFromPO = null;

            if (isset($shopifyImages[$sku]) && !empty($shopifyImages[$sku]->image_src)) {
                $image = $shopifyImages[$sku]->image_src;
            }

            if (isset($productMaster[$sku])) {
                $productRow = $productMaster[$sku];
                $values = json_decode($productRow->Values ?? '{}', true);

                if (is_array($values)) {
                    if (!empty($values['image_path'])) {
                        $image = 'storage/' . ltrim($values['image_path'], '/');
                    }
                    if (isset($values['cbm'])) {
                        $cbm = $values['cbm'];
                    }
                }

                $parent = $normalizeSku($productRow->parent ?? '');
            }

            if (!empty($parent) && isset($supplierMapByParent[$parent])) {
                $supplierNames = $supplierMapByParent[$parent];
            }

            $row->supplier = !empty($row->supplier) ? $row->supplier : implode(', ', $supplierNames);
            
            // Get price from purchase_order if available
            if (isset($skuToPriceMap[$sku])) {
                $priceFromPO = $skuToPriceMap[$sku]['price'];
                $currencyFromPO = $skuToPriceMap[$sku]['currency'];
            }
            
            // Get stage from forecast_analysis
            $stage = '';
            if (isset($forecastData[$sku])) {
                $stage = $forecastData[$sku]->stage ?? '';
            }
            $row->stage = $stage;
            $row->order_qty = $row->qty; // Add order_qty field for validation

            $row->Image = $image;
            $row->CBM = $cbm;
            $row->price_from_po = $priceFromPO;
            $row->currency_from_po = $currencyFromPO;

            $processedData[] = $row;
        }

        return response()->json([
            "data" => $processedData
        ]);
    }


    public function convert(Request $request)
    {
        $amount = $request->query('amount', 1);
        $from = $request->query('from', 'USD');
        $to = $request->query('to', 'CNY');

        try {
            $apiUrl = "https://api.frankfurter.app/latest?amount=$amount&from=$from&to=$to";
            $response = Http::get($apiUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json(['error' => 'Frankfurter API error'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function inlineUpdateBySku(Request $request)
    {
        $sku = $request->input('sku');
        $column = $request->input('column');

        $validColumns = [
            'advance_amt', 'pay_conf_date', 'o_links', 'adv_date', 'del_date', 'total_cbm',
            'barcode_sku', 'artwork_manual_book', 'notes', 'ready_to_ship', 'rate', 'rate_currency',
            'photo_packing', 'photo_int_sale','supplier','created_at'
        ];

        if (!in_array($column, $validColumns)) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        $progress = MfrgProgress::where('sku', $sku)->first();
        if (!$progress) {
            return response()->json(['success' => false, 'message' => 'SKU not found.']);
        }

        if ($request->hasFile('value') && in_array($column, ['photo_packing', 'photo_int_sale', 'barcode_sku'])) {
            $file = $request->file('value');
            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/mfrg_images');
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $file->move($destinationPath, $filename);
            $url = url("uploads/mfrg_images/{$filename}");

            $progress->{$column} = $url;
            $progress->save();

            return response()->json(['success' => true, 'url' => $url]);
        }

        if ($column === 'advance_amt') {
            if (!$progress->supplier) {
                return response()->json(['success' => false, 'message' => 'Supplier not found.']);
            }

            MfrgProgress::where('supplier', $progress->supplier)->update([
                'advance_amt' => $request->input('value')
            ]);

            return response()->json(['success' => true, 'message' => 'Advance updated.']);
        }

        $progress->{$column} = $request->input('value');
        $progress->save();

        return response()->json(['success' => true]);
    }


    public function storeDataReadyToShip(Request $request)
    {
        try {
            $data = [
                'supplier' => $request->supplier,
                'cbm' => $request->totalCbm,
                'qty' => $request->qty,
                'rate' => $request->rate,
                'transit_inv_status' => 0,
            ];

            $readyToShip = ReadyToShip::where('parent', $request->parent)
                ->where('sku', $request->sku)
                ->first();

            if ($readyToShip) {
                $readyToShip->update($data);
            } else {
                ReadyToShip::create(array_merge([
                    'parent' => $request->parent,
                    'sku' => $request->sku,
                ], $data));
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteBySkus(Request $request)
    {
        try {
            $skus = $request->input('skus', []);
            
            if (empty($skus) || !is_array($skus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SKUs provided for deletion.'
                ], 400);
            }

            // Normalize SKUs (uppercase and trim)
            $normalizedSkus = array_map(function($sku) {
                return strtoupper(trim($sku));
            }, $skus);

            // Delete records
            $deletedCount = MfrgProgress::whereIn(DB::raw('UPPER(TRIM(sku))'), $normalizedSkus)->delete();

            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Successfully deleted {$deletedCount} record(s)."
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting MFRG Progress records: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting records: ' . $e->getMessage()
            ], 500);
        }
    }


}
