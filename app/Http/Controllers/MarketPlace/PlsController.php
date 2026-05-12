<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\PLSDataView;
use App\Models\PLSProduct;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PlsController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

     public function overallPls(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = ChannelMaster::where('channel', 'PLS')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.plsAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function plsPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Walmart', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.walmartPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewPlsData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('Pls', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Pls')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Fetch NR values for these SKUs from walmartDataView
        $walmartDataViews = PLSDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];

        foreach ($walmartDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? false;
            $listedValues[$sku] = isset($value['Listed']) ? (int) $value['Listed'] : false;
            $liveValues[$sku] = isset($value['Live']) ? (int) $value['Live'] : false;
        }

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem['LP'] = $values['lp'] ?? 0;
            $processedItem['Ship'] = $values['ship'] ?? 0;
            $processedItem['COGS'] = $values['cogs'] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            // Fetch NR value if available
            $processedItem['NR'] = $nrValues[$sku] ?? false;
            $processedItem['Listed'] = $listedValues[$sku] ?? false;
            $processedItem['Live'] = $liveValues[$sku] ?? false;

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['price'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['PFT %'] = 0;
            $processedItem['Roi'] = 0;
            $processedItem['percentage'] = $percentageValue;

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function updateAllPlsSkus(Request $request)
    {
        try {
            $percent = $request->input('percent');

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid percentage value. Must be between 0 and 100.'
                ], 400);
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Pls'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('Pls', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Pls',
                    'percentage' => $percent
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating percentage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = PLSDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        $value['NR'] = filter_var($nr, FILTER_VALIDATE_BOOLEAN);
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = PLSDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function importPlsAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                PLSDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportPlsAnalytics()
    {
        $plsData = PLSDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($plsData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Pls_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Pls_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * PLS Sales View - Shows last 30 days of sales
     */
    public function salesView(Request $request)
    {
        return view('market-places.pls_sales_view');
    }

    /**
     * PLS Sales Data JSON - Returns last 30 days of sales data
     */
    public function salesDataJson(Request $request)
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $sales = \App\Models\PlsSale::where('order_date', '>=', $thirtyDaysAgo)
            ->orderBy('order_date', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'order_date' => $sale->order_date ? $sale->order_date->format('Y-m-d') : '',
                    'order_number' => $sale->order_number,
                    'order_name' => $sale->order_name,
                    'sku' => $sale->sku,
                    'product_title' => $sale->product_title,
                    'variant_title' => $sale->variant_title,
                    'quantity' => $sale->quantity,
                    'price' => $sale->price,
                    'total_amount' => $sale->total_amount,
                    'discount_amount' => $sale->discount_amount,
                    'tax_amount' => $sale->tax_amount,
                    'financial_status' => $sale->financial_status,
                    'fulfillment_status' => $sale->fulfillment_status,
                    'customer_email' => $sale->customer_email,
                    'customer_name' => $sale->customer_name,
                    'currency' => $sale->currency,
                ];
            });

        return response()->json($sales);
    }

    /**
     * PLS Pricing View - Shows pricing and inventory data
     */
    public function pricingView(Request $request)
    {
        return view('market-places.pls_pricing_view');
    }

    /**
     * PLS Pricing Data JSON - Returns combined pricing and inventory data
     */
    public function pricingDataJson(Request $request)
    {
        // 1. Base ProductMaster fetch - Sort by SKU only (ascending order)
        $productMasters = ProductMaster::orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        // Filter out PARENT items
        $productMasters = $productMasters->filter(function ($item) {
            return stripos($item->sku, 'PARENT') === false;
        })->values();

        // 2. Get SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Get PLS marketplace percentage from marketplace_percentages table
        $plsPercentage = MarketplacePercentage::where('marketplace', 'LIKE', '%PLS%')->value('percentage') ?? 100;
        $plsPercentage = $plsPercentage / 100; // convert to fraction

        // 3. Get inventory and L30 from shopify_skus table (like Purchasing Power page)
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // 4. Get PLS products data (price, L30, L60)
        $plsProducts = PLSProduct::whereIn('sku', $skus)
            ->get()
            ->keyBy(function($item) {
                return strtoupper($item->sku);
            });

        // 5. Get PLS inventory from shopify_catalog_variants
        $catalogVariants = \DB::table('shopify_catalog_variants')
            ->where('store', 'pls')
            ->whereIn('sku', $skus)
            ->select('sku', 'inventory_quantity')
            ->get()
            ->groupBy(function($item) {
                return strtoupper($item->sku);
            });

        // 6. Get SPRICE, SGPFT, SROI from pls_data_views
        $plsDataViews = PLSDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // 6b. Get PLS_STATUS from amazon_data_views
        $amazonDataViews = \App\Models\AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // 7. Build Result
        $data = [];

        foreach ($productMasters as $pm) {
            $skuUpper = strtoupper($pm->sku);
            
            // Get related data
            $shopify = $shopifyData->get($pm->sku);
            $plsProduct = $plsProducts->get($skuUpper);
            $plsCatalogVariants = $catalogVariants->get($skuUpper);
            
            // Basic info
            $row = [];
            $row['parent'] = $pm->parent ?? '';
            $row['sku'] = $pm->sku;
            $row['title'] = $pm->title ?? '';
            
            // Parse Values JSON for LP, Ship, and Image Path
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === "lp") {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            
            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            
            // Get image_path from Values JSON or ProductMaster direct property (same as Temu2)
            $imagePath = $values['image_path'] ?? ($pm->image_path ?? null);
            
            // Get inventory and OV L30 from shopify_skus (like Purchasing Power page)
            $inventory = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $ovl30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
            
            // Get PLS inventory from shopify_catalog_variants
            $plsInventory = $plsCatalogVariants ? $plsCatalogVariants->sum('inventory_quantity') : 0;
            
            // Get SPRICE, SGPFT, SROI from pls_data_views
            $plsDataView = $plsDataViews->get($pm->sku);
            $sprice = null;
            $sgpft = null;
            $sroi = null;
            $hasCustomSprice = false;
            
            if ($plsDataView) {
                $dataValue = is_array($plsDataView->value) 
                    ? $plsDataView->value 
                    : (is_string($plsDataView->value) ? json_decode($plsDataView->value, true) : []);
                
                if (is_array($dataValue)) {
                    $sprice = isset($dataValue['sprice']) ? floatval($dataValue['sprice']) : null;
                    $sgpft = isset($dataValue['sgpft']) ? floatval($dataValue['sgpft']) : null;
                    $sroi = isset($dataValue['sroi']) ? floatval($dataValue['sroi']) : null;
                    $hasCustomSprice = $sprice !== null && $sprice > 0;
                }
            }
            
            // Sales data from pls_products (price, L30 for MC L30 column, L60)
            $price = $plsProduct ? floatval($plsProduct->price) : 0;
            $plsL30 = $plsProduct ? intval($plsProduct->p_l30) : 0;
            $l60 = $plsProduct ? intval($plsProduct->p_l60) : 0;
            
            $row['image_path'] = $imagePath;
            $row['price'] = $price;
            $row['lp'] = $lp;
            $row['ship'] = $ship;
            $row['inventory'] = $inventory;
            $row['pls_inventory'] = $plsInventory;  // PLS marketplace inventory
            $row['l30'] = $ovl30;  // OV L30 - Our Velocity from Shopify
            $row['l60'] = $l60;    // PLS L60 from marketplace
            $row['pls_l30'] = $plsL30;  // PLS marketplace L30 sold
            $row['pls_l60'] = $l60;     // PLS marketplace L60 sold
            
            // Calculate GPFT (with marketplace percentage)
            $gpft = 0;
            $gpftPct = 0;
            $roiPct = 0;
            
            if ($price > 0) {
                $gpft = ($price * $plsPercentage) - $lp - $ship;
                $gpftPct = ($gpft / $price) * 100;
            }
            
            if ($lp > 0) {
                $roiPct = ((($price * $plsPercentage) - $lp - $ship) / $lp) * 100;
            }
            
            $row['gpft'] = round($gpft, 2);
            $row['gpft_pct'] = round($gpftPct, 2);
            $row['roi_pct'] = round($roiPct, 2);
            
            // Add SPRICE, SGPFT%, SROI% from pls_data_views
            $row['sprice'] = $sprice;
            $row['sgpft'] = $sgpft;
            $row['sroi'] = $sroi;
            $row['has_custom_sprice'] = $hasCustomSprice;
            
            // Get PLS_STATUS from amazon_data_views
            $amazonDataView = $amazonDataViews->get($pm->sku);
            $plsStatus = null;
            
            if ($amazonDataView) {
                $amazonValue = is_array($amazonDataView->value) 
                    ? $amazonDataView->value 
                    : (is_string($amazonDataView->value) ? json_decode($amazonDataView->value, true) : []);
                
                if (is_array($amazonValue)) {
                    $plsStatus = isset($amazonValue['PLS_STATUS']) ? $amazonValue['PLS_STATUS'] : null;
                }
            }
            
            $row['pls_status'] = $plsStatus;
            
            // Total profit based on OV L30 (our sales)
            $row['total_pft_l30'] = round($gpft * $ovl30, 2);
            
            // Sales value L30 (our sales)
            $row['sales_l30'] = round($price * $ovl30, 2);
            
            // DIL% (Days of Inventory Left based on OV L30 sales rate)
            $row['dil_pct'] = ($ovl30 > 0 && $inventory > 0) 
                ? round(($ovl30 / $inventory) * 100, 2) 
                : 0;
            
            // Calculate Missing status (same logic as Temu2)
            // Missing = Not in pls_products OR (in pls_products but INV > 0 and price <= 0)
            $inPricing = $plsProduct !== null && $price > 0;
            $missing = $inPricing ? '' : 'M';
            if ($inPricing && $inventory > 0 && $price <= 0) {
                $missing = 'M';
            }
            if ($inPricing && $inventory <= 0 && $price > 0) {
                $missing = '';
            }
            
            $row['missing'] = $missing;
            
            $data[] = $row;
        }
        
        return response()->json($data);
    }

    /**
     * Save PLS SPRICE and calculate SGPFT% and SROI%
     */
    public function savePlsSprice(Request $request)
    {
        $sku = $request->input('sku');
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and SPRICE are required'], 400);
        }

        // Get product master data for LP and Ship
        $productMaster = ProductMaster::where('sku', $sku)->first();
        
        if (!$productMaster) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Parse Values JSON for LP and Ship
        $values = is_array($productMaster->Values) 
            ? $productMaster->Values 
            : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
        
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === "lp") {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($productMaster->lp)) {
            $lp = floatval($productMaster->lp);
        }
        
        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($productMaster->ship) ? floatval($productMaster->ship) : 0);

        // Calculate SGPFT% = ((SPRICE - LP - Ship) / SPRICE) * 100
        $sgpft_percent = 0;
        if ($sprice > 0) {
            $sgpft_percent = (($sprice - $lp - $ship) / $sprice) * 100;
        }

        // Calculate SROI% = ((SPRICE - LP - Ship) / LP) * 100
        $sroi_percent = 0;
        if ($lp > 0) {
            $sroi_percent = (($sprice - $lp - $ship) / $lp) * 100;
        }

        // Save to pls_data_views table (create or update)
        $dataView = PLSDataView::firstOrNew(['sku' => $sku]);
        
        $currentValue = $dataView->value;
        if (is_string($currentValue)) {
            $currentValue = json_decode($currentValue, true) ?: [];
        } elseif (!is_array($currentValue)) {
            $currentValue = [];
        }
        
        $currentValue['sprice'] = floatval($sprice);
        $currentValue['sgpft'] = round($sgpft_percent, 2);
        $currentValue['sroi'] = round($sroi_percent, 2);
        
        $dataView->value = $currentValue;
        $dataView->save();

        return response()->json([
            'success' => true,
            'data' => floatval($sprice),
            'sgpft_percent' => round($sgpft_percent, 2),
            'sroi_percent' => round($sroi_percent, 2),
            'has_custom_sprice' => true
        ]);
    }
}


