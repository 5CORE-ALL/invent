<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\WalmartDataView;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartMetrics;
use App\Models\WalmartProductSheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;

class WalmartControllerMarket extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function overallWalmart(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100;
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Walmart')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.walmartAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function walmartPricingCVR(Request $request)
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

    public function getViewWalmartData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('Walmart', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from walmartDataView
        $walmartDataViews = WalmartDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Walmart product sheet data
        $walmartMetrics = WalmartMetrics::whereIn('sku', $skus)->get()->keyBy('sku');

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
                // FIXED: Add A L30 and A Dil% from Shopify if available
                $processedItem['A L30'] = $shopifyItem->a_l30 ?? 0;
                $processedItem['A Dil%'] = $shopifyItem->a_dil ?? 0;
                $processedItem['Sess30'] = $shopifyItem->sess30 ?? 0;
                $processedItem['Tacos30'] = $shopifyItem->tacos30 ?? 0;
                $processedItem['SCVR'] = $shopifyItem->scvr ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
                $processedItem['A L30'] = 0;
                $processedItem['A Dil%'] = 0;
                $processedItem['Sess30'] = 0;
                $processedItem['Tacos30'] = 0;
                $processedItem['SCVR'] = 0;
            }

            // Add data from walmart_product_sheets if available
            if (isset($walmartMetrics[$sku])) {
                $walmartMetric = $walmartMetrics[$sku];
                $processedItem['sheet_price'] = $walmartMetric->price ?? 0;
                $processedItem['sheet_pft'] = $walmartMetric->pft ?? 0;
                $processedItem['sheet_roi'] = $walmartMetric->roi ?? 0;
                $processedItem['sheet_l30'] = $walmartMetric->l30 ?? 0; // Walmart L30
                $processedItem['sheet_dil'] = $walmartMetric->dil ?? 0; // Walmart Dilution
                $processedItem['buy_link'] = $walmartMetric->buy_link ?? '';
            } else {
                $processedItem['sheet_price'] = 0;
                $processedItem['sheet_pft'] = 0;
                $processedItem['sheet_roi'] = 0;
                $processedItem['sheet_l30'] = 0;
                $processedItem['sheet_dil'] = 0;
                $processedItem['buy_link'] = '';
            }

            // Fetch NR value if available
            $processedItem['NR'] = $nrValues[$sku] ?? false;
            $processedItem['Listed'] = $listedValues[$sku] ?? false;
            $processedItem['Live'] = $liveValues[$sku] ?? false;

            // Default values for other fields
            $processedItem['price'] = $processedItem['sheet_price'] ?? 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = $processedItem['sheet_l30'] ?? 0;
            $processedItem['percentage'] = $percentageValue;

            // Calculate profit and ROI percentages
            $price = floatval($processedItem['price']);
            $percentage = floatval($processedItem['percentage']);
            $lp = floatval($processedItem['LP']);
            $ship = floatval($processedItem['Ship']);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage - $lp - $ship) / $price) * 100;
                $processedItem['PFT %'] = round($pft_percentage, 2);
            } else {
                $processedItem['PFT %'] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage - $lp - $ship) / $lp) * 100;
                $processedItem['Roi'] = round($roi_percentage, 2);
            } else {
                $processedItem['Roi'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function updateAllWalmartSkus(Request $request)
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
                ['marketplace' => 'Walmart'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('Walmart', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Wayfair',
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
    // public function saveNrToDatabase(Request $request)
    // {
    //     $sku = $request->input('sku');
    //     $nr = $request->input('nr');

    //     if (!$sku || $nr === null) {
    //         return response()->json(['error' => 'SKU and nr are required.'], 400);
    //     }

    //     $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
    //     $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
    //     $value['NR'] = $nr;
    //     $dataView->value = $value;
    //     $dataView->save();

    //     return response()->json(['success' => true, 'data' => $dataView]);
    // }

    // public function saveNrToDatabase(Request $request)
    // {
    //     $sku = $request->input('sku');
    //     $nr = $request->input('nr');

    //     if (!$sku || $nr === null) {
    //         return response()->json(['error' => 'SKU and nr are required.'], 400);
    //     }

    //     $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
    //     $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
    //     if ($nr !== null) {
    //         $value["NR"] = $nr;
    //     }
    //     $dataView->value = $value;
    //     $dataView->save();

    //     return response()->json(['success' => true, 'data' => $dataView]);
    // }

    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nrValue = $request->input('nr');

        if (empty($sku) || $nrValue === null || $nrValue === '') {
            return response()->json(['error' => 'SKU and NR are required.'], 400);
        }

        $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
        $existingValue = $dataView->value;

        $value = is_array($existingValue)
            ? $existingValue
            : (json_decode($existingValue, true) ?: []);

        $value['NR'] = $nrValue;

        // ✅ assign array directly (no json_encode)
        $dataView->value = $value;
        $dataView->save();

        return response()->json([
            'success' => true,
            'data' => $dataView,
            'stored_value' => $value
        ]);
    }




    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = WalmartDataView::firstOrCreate(
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

    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving Walmart pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            return response()->json(['error' => 'SKU not found in product master.'], 404);
        }

        // Extract lp and ship
        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === 'lp') {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($pm->lp)) {
            $lp = floatval($pm->lp);
        }

        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
        Log::info('LP and Ship', ['lp' => $lp, 'ship' => $ship]);

        // Calculate SPFT% and SROI% (using 0.80 for Walmart like Amazon)
        $spriceFloat = floatval($sprice);
        
        // SPFT% = ((SPRICE * 0.80 - LP - Ship) / SPRICE) * 100
        $spft = $spriceFloat > 0 ? round((($spriceFloat * 0.80 - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // SROI% = ((SPRICE * 0.80 - LP - Ship) / LP) * 100
        $sroi = $lp > 0 ? round((($spriceFloat * 0.80 - $ship - $lp) / $lp) * 100, 2) : 0;
        
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'spft' => $spft, 'sroi' => $sroi]);

        $walmartDataView = WalmartDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($walmartDataView->value)
            ? $walmartDataView->value
            : (json_decode($walmartDataView->value ?? '{}', true) ?? []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
        ]);

        $walmartDataView->value = $merged;
        $walmartDataView->save();
        Log::info('Data saved successfully', ['sku' => $sku]);

        return response()->json([
            'message' => 'Data saved successfully.',
            'data' => $spriceFloat,
            'spft_percent' => $spft,
            'sroi_percent' => $sroi
        ]);
    }

    public function importWalmartAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $fileExtension = $file->getClientOriginalExtension();
            
            $rows = [];
            
            // Handle CSV files (like Amazon import)
            if (strtolower($fileExtension) === 'csv') {
                $content = file_get_contents($file->getRealPath());
                $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                $csvData = array_map('str_getcsv', explode("\n", $content));
                $csvData = array_filter($csvData, function($row) {
                    return count($row) > 0 && !empty(trim(implode('', $row)));
                });
                
                if (empty($csvData)) {
                    return response()->json(['error' => 'CSV file is empty or invalid'], 400);
                }
                
                $rows = $csvData;
            } else {
                // Handle Excel files
                $spreadsheet = IOFactory::load($file->getPathName());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
            }

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = strtoupper(trim($row[0]));
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip(array_map('strtoupper', $existingSkus));

            $importCount = 0;
            $skipped = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                if (empty($row) || empty($row[0])) {
                    $skipped++;
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $rowData = array_map('trim', $rowData);
                $data = array_combine($headers, $rowData);

                $sku = strtoupper(trim($data['sku'] ?? $row[0] ?? ''));
                
                if (empty($sku)) {
                    $skipped++;
                    continue;
                }

                // Only import SKUs that exist in product_masters
                if (!isset($existingSkus[$sku])) {
                    $skipped++;
                    continue;
                }

                // Get or create WalmartDataView record
                $walmartDataView = WalmartDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($walmartDataView->value)
                    ? $walmartDataView->value
                    : (json_decode($walmartDataView->value ?? '{}', true) ?? []);

                // Update buybox_price if provided
                if (isset($data['buybox_price']) || isset($data['buybox']) || isset($data['bb_price'])) {
                    $buyboxPrice = floatval($data['buybox_price'] ?? $data['buybox'] ?? $data['bb_price'] ?? 0);
                    if ($buyboxPrice > 0) {
                        $existing['buybox_price'] = $buyboxPrice;
                    }
                }

                // Update SPRICE if provided
                if (isset($data['sprice']) || isset($data['s_price'])) {
                    $sprice = floatval($data['sprice'] ?? $data['s_price'] ?? 0);
                    if ($sprice > 0) {
                        $existing['SPRICE'] = $sprice;
                        
                        // Recalculate SPFT% and SROI%
                        $pm = ProductMaster::where('sku', $sku)->first();
                        if ($pm) {
                            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                            $lp = 0;
                            foreach ($values as $k => $v) {
                                if (strtolower($k) === 'lp') {
                                    $lp = floatval($v);
                                    break;
                                }
                            }
                            if ($lp === 0 && isset($pm->lp)) {
                                $lp = floatval($pm->lp);
                            }
                            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
                            
                            $spft = $sprice > 0 ? round((($sprice * 0.80 - $ship - $lp) / $sprice) * 100, 2) : 0;
                            $sroi = $lp > 0 ? round((($sprice * 0.80 - $ship - $lp) / $lp) * 100, 2) : 0;
                            
                            $existing['SPFT'] = $spft;
                            $existing['SROI'] = $sroi;
                        }
                    }
                }

                // Save the updated data
                $walmartDataView->value = $existing;
                $walmartDataView->save();

                $importCount++;
            }

            $message = "Successfully imported $importCount records";
            if ($skipped > 0) {
                $message .= ", skipped $skipped invalid rows";
            }

            return response()->json([
                'success' => $message,
                'imported' => $importCount,
                'skipped' => $skipped
            ]);
        } catch (\Exception $e) {
            Log::error('Error importing Walmart data: ' . $e->getMessage());
            return response()->json(['error' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    public function exportWalmartAnalytics()
    {
        $walmartData = WalmartDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($walmartData as $data) {
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
        $fileName = 'Walmart_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="walmart_buybox_price_sample.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['SKU', 'Buybox Price', 'SPRICE']);
            fputcsv($file, ['SAMPLE-SKU-001', '29.99', '']);
            fputcsv($file, ['SAMPLE-SKU-002', '39.99', '35.99']);
            fputcsv($file, ['SAMPLE-SKU-003', '49.99', '']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Tabulator view methods
    public function walmartTabulatorView(Request $request)
    {
        return view('market-places.walmart_tabulator_view');
    }

    public function walmartDataJson(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $nonParentSkus = array_filter($skus, function($sku) {
            return stripos($sku, 'PARENT') === false;
        });

        // Get percentage from database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        // Fetch data from apicentral database
        $walmartLookup = DB::connection('apicentral')
            ->table('walmart_api_data as api')
            ->select(
                'api.sku',
                'api.price',
                DB::raw('COALESCE(m.l30, 0) as l30'),
                DB::raw('COALESCE(m.l60, 0) as l60')
            )
            ->leftJoin('walmart_metrics as m', 'api.sku', '=', 'm.sku')
            ->whereIn('api.sku', $nonParentSkus)
            ->get()
            ->keyBy('sku');

        // Fetch buybox price and views from walmart_insights
        $walmartInsights = DB::connection('apicentral')
            ->table('walmart_insights')
            ->select('sku', 'buy_box_base_price', 'buy_box_total_price', 'views')
            ->whereIn('sku', $nonParentSkus)
            ->get()
            ->keyBy('sku');

        // Fetch page views from walmart_listing_qualities
        $walmartListingQualities = DB::connection('apicentral')
            ->table('walmart_listing_qualities')
            ->select('sku', 'page_views')
            ->whereIn('sku', $nonParentSkus)
            ->get()
            ->keyBy('sku');

        // Fetch shopify data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Walmart data view for NR, Listed, Live
        $walmartDataViews = WalmartDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];
        $slNo = 1;

        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $isParent = stripos($sku, 'PARENT') !== false;
            
            // Skip parent rows (they will be added as summaries)
            if ($isParent) {
                continue;
            }

            $item = [
                'sl_no' => $slNo++,
                'Parent' => $pm->parent ?? '',
                '(Child) sku' => $sku,
                'is_parent_summary' => $isParent,
                'NR' => '',
                'Listed' => false,
                'Live' => false,
                'INV' => 0,
                'L30' => 0,
                'E Dil%' => 0,
                'W_L30' => 0,
                'CVR_L30' => 0,
                'Sess30' => 0,
                'total_review_count' => 0,
                'price' => 0,
                'LP_productmaster' => 0,
                'Ship_productmaster' => 0,
                'GPFT%' => 0,
                'AD%' => 0,
                'PFT%' => 0,
                'ROI_percentage' => 0,
                'buybox_base_price' => 0,
                'buybox_total_price' => 0,
                'insights_views' => 0,
                'page_views' => 0,
                'buybox_price' => 0,
                'SPRICE' => 0,
                'SGPFT' => 0,
                'Spft%' => 0,
                'SROI' => 0,
                'AD_Spend_L30' => 0,
                'kw_spend_L30' => 0,
                'pmt_spend_L30' => 0,
                'image_path' => null,
                'l60' => 0,
                'pft_amt' => 0,
                'sales_amt' => 0,
                'percentage' => $percentage
            ];

            // Get values from product master
            $values = $pm->Values ?: [];
            $lp = $values['lp'] ?? 0;
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            $item['LP_productmaster'] = $lp;
            $item['Ship_productmaster'] = $ship;

            // Get Walmart API data
            if (isset($walmartLookup[$sku])) {
                $walmartData = $walmartLookup[$sku];
                $item['price'] = $walmartData->price ?? 0;
                $item['W_L30'] = $walmartData->l30 ?? 0; // Walmart L30
                $item['l60'] = $walmartData->l60 ?? 0;
            }

            // Get buybox price and views from walmart_insights
            if (isset($walmartInsights[$sku])) {
                $insights = $walmartInsights[$sku];
                $item['buybox_base_price'] = $insights->buy_box_base_price ?? 0;
                $item['buybox_total_price'] = $insights->buy_box_total_price ?? 0;
                $item['insights_views'] = $insights->views ?? 0;
            }

            // Get page views from walmart_listing_qualities
            if (isset($walmartListingQualities[$sku])) {
                $qualities = $walmartListingQualities[$sku];
                $item['page_views'] = $qualities->page_views ?? 0;
            }

            // Get Shopify data
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $item['INV'] = $shopifyItem->inv ?? 0;
                $item['L30'] = $shopifyItem->quantity ?? 0; // Overall L30 from Shopify
                $item['image_path'] = $shopifyItem->image_src ?? ($values['image_path'] ?? null);
            }

            // Get Walmart data view values
            if (isset($walmartDataViews[$sku])) {
                $dataView = $walmartDataViews[$sku];
                $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
                // Use stored NR value if exists, otherwise calculate based on INV
                $nrValue = $value['NR'] ?? (floatval($item['INV']) > 0 ? 'REQ' : 'NR');
                $item['NR'] = $nrValue === 'NR' ? 'NR' : 'REQ';
                $item['Listed'] = isset($value['Listed']) ? (bool)$value['Listed'] : false;
                $item['Live'] = isset($value['Live']) ? (bool)$value['Live'] : false;
                // Load saved SPRICE data
                $item['SPRICE'] = $value['SPRICE'] ?? 0;
                $item['SGPFT'] = $value['SGPFT'] ?? 0;
                $item['Spft%'] = $value['SPFT'] ?? 0;
                $item['SROI'] = $value['SROI'] ?? 0;
                // Load saved buybox price
                $item['buybox_price'] = $value['buybox_price'] ?? 0;
            } else {
                // No saved data - calculate default based on INV
                $item['NR'] = floatval($item['INV']) > 0 ? 'REQ' : 'NR';
            }

            // Calculate formulas matching Amazon (using 0.80 hardcoded like Amazon)
            $price = floatval($item['price']);
            $sprice = floatval($item['SPRICE']);
            $w_l30 = floatval($item['W_L30']);
            $l30 = floatval($item['L30']);
            $inv = floatval($item['INV']);

            // AD% = 0 for now (as requested)
            $item['AD%'] = 0;
            $item['AD_Spend_L30'] = 0;
            $item['kw_spend_L30'] = 0;
            $item['pmt_spend_L30'] = 0;

            // GPFT% Formula = ((price × 0.80 - ship - lp) / price) × 100
            $item['GPFT%'] = 0;
            if ($price > 0) {
                $item['GPFT%'] = round((($price * 0.80 - $ship - $lp) / $price) * 100, 2);
            }

            // PFT% = GPFT% - AD%
            $item['PFT%'] = round(($item['GPFT%'] ?? 0) - $item['AD%'], 2);

            // ROI% = ((price * (0.80 - AD%/100) - ship - lp) / lp) * 100
            $item['ROI_percentage'] = 0;
            $adDecimal = $item['AD%'] / 100;
            if ($lp > 0 && $price > 0) {
                $item['ROI_percentage'] = round((($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100, 2);
            }

            // Dil% = (L30 / INV) * 100
            $item['E Dil%'] = 0;
            if ($inv > 0) {
                $item['E Dil%'] = round(($l30 / $inv) * 100, 2);
            }

            // CVR = (W_L30 / Sess30) * 100 (Sess30 set to 0 for now, so CVR will be 0)
            $sess30 = 0; // Walmart doesn't have views data yet
            $item['Sess30'] = $sess30;
            $item['CVR_L30'] = 0;
            if ($sess30 > 0) {
                $item['CVR_L30'] = round(($w_l30 / $sess30) * 100, 2);
            }

            // SGPFT% = ((SPRICE * 0.80 - ship - lp) / SPRICE) * 100
            // Only calculate if we have a saved value from database, otherwise calculate it
            if ($sprice > 0) {
                // If SGPFT was loaded from database, use it; otherwise calculate
                if (!isset($item['SGPFT']) || $item['SGPFT'] == 0) {
                    $item['SGPFT'] = round((($sprice * 0.80 - $ship - $lp) / $sprice) * 100, 2);
                }
            }

            // SPFT% = SGPFT% - AD%
            $item['Spft%'] = round(($item['SGPFT'] ?? 0) - $item['AD%'], 2);

            // SROI% = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
            // Only calculate if we have a saved value from database, otherwise calculate it
            if (!isset($item['SROI']) || $item['SROI'] == 0) {
                if ($lp > 0 && $sprice > 0) {
                    $item['SROI'] = round((($sprice * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100, 2);
                }
            }

            // If SPRICE is null or empty, use price as default
            if (empty($item['SPRICE']) && $price > 0) {
                $item['SPRICE'] = $price;
                $item['has_custom_sprice'] = false;
                
                // Calculate SGPFT based on default price (only if price > 0)
                if ($price > 0) {
                    $item['SGPFT'] = round((($price * 0.80 - $ship - $lp) / $price) * 100, 2);
                    $item['Spft%'] = round($item['SGPFT'] - $item['AD%'], 2);
                }
                // Calculate SROI only if lp > 0 and price > 0
                if ($lp > 0 && $price > 0) {
                    $item['SROI'] = round((($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100, 2);
                }
            } else if (!empty($item['SPRICE'])) {
                $item['has_custom_sprice'] = true;
            }

            // PFT AMT = (Price * 0.80 - LP - Ship) * L30
            $item['pft_amt'] = ($price * 0.80 - $lp - $ship) * $l30;

            // SALES AMT = Price * L30
            $item['sales_amt'] = $price * $l30;

            $result[] = (object) $item;
        }

        // Parent-wise grouping (similar to Amazon)
        $groupedByParent = collect($result)->groupBy('Parent');
        $finalResult = [];

        foreach ($groupedByParent as $parent => $rows) {
            foreach ($rows as $row) {
                $finalResult[] = $row;
            }

            if (empty($parent)) {
                continue;
            }

            $sumRow = [
                '(Child) sku' => 'PARENT ' . $parent,
                'Parent' => $parent,
                'INV' => $rows->sum('INV'),
                'L30' => $rows->sum('L30'),
                'W_L30' => $rows->sum('W_L30'),
                'price' => '',
                'SPRICE' => '',
                'GPFT%' => $rows->count() > 0 ? round($rows->avg('GPFT%'), 2) : 0,
                'AD%' => 0,
                'PFT%' => $rows->count() > 0 ? round($rows->avg('PFT%'), 2) : 0,
                'ROI_percentage' => '',
                'SGPFT' => '',
                'Spft%' => '',
                'SROI' => '',
                'AD_Spend_L30' => '',
                'buybox_price' => '',
                'Listed' => null,
                'Live' => null,
                'image_path' => '',
                'pft_amt' => round($rows->sum('pft_amt'), 2),
                'sales_amt' => round($rows->sum('sales_amt'), 2),
                'is_parent_summary' => true,
                'percentage' => $percentage
            ];

            $finalResult[] = (object) $sumRow;
        }

        return response()->json($finalResult);
    }

    public function getWalmartColumnVisibility(Request $request)
    {
        $visibility = Cache::get('walmart_column_visibility', '{}');
        return response()->json(['visibility' => $visibility]);
    }

    public function setWalmartColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility');
        Cache::put('walmart_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }

    public function exportWalmartTabulatorData(Request $request)
    {
        try {
            // Get the data from walmartDataJson method
            $response = $this->walmartDataJson($request);
            $data = json_decode($response->getContent(), true);
            $walmartData = collect($data ?? []);

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="Walmart_Pricing_CVR_Export_' . date('Y-m-d_H-i-s') . '.csv"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function () use ($walmartData) {
                $file = fopen('php://output', 'w');

                // Header Row - matching Amazon export structure
                $headerRow = [
                    'Parent', 'SKU', 'INV', 'OV L30', 'Price', 'Buybox Price',
                    'W L30', 'L60', 'NR', 'SPRICE',
                    'SPFT%', 'SROI%', 'Listed', 'Live',
                    'PFT%', 'ROI%', 'Total Profit', 'Total Sales', 'Total COGS'
                ];
                fputcsv($file, $headerRow);

                // Data Rows
                foreach ($walmartData as $item) {
                    // Skip parent summary rows
                    if (isset($item['is_parent_summary']) && $item['is_parent_summary']) {
                        continue;
                    }

                    $price = floatval($item['price'] ?? 0);
                    $sprice = floatval($item['sprice'] ?? 0);
                    $lp = floatval($item['lp'] ?? 0);
                    $ship = floatval($item['ship'] ?? 0);
                    $l30 = floatval($item['l30'] ?? 0);
                    $w_l30 = floatval($item['w_l30'] ?? 0);
                    $cogs = floatval($item['cogs'] ?? 0);

                    // Calculate totals
                    $totalProfit = ($price * 0.80 - $lp - $ship) * $l30;
                    $totalSales = $price * $l30;
                    $totalCogs = $lp * $l30;

                    // Format NR value
                    $nr = $item['nr'] ?? '';
                    if ($nr === 'REQ') {
                        $nr = 'RL';
                    } elseif ($nr === 'NR') {
                        $nr = 'NRL';
                    }

                    $rowData = [
                        $item['parent'] ?? '',
                        $item['sku'] ?? '',
                        $item['inv'] ?? 0,
                        $l30,
                        $price,
                        $item['buybox_price'] ?? 0,
                        $w_l30,
                        $item['l60'] ?? 0,
                        $nr,
                        $sprice,
                        $item['spft_percent'] ?? 0,
                        $item['sroi'] ?? 0,
                        isset($item['listed']) && $item['listed'] ? 'TRUE' : 'FALSE',
                        isset($item['live']) && $item['live'] ? 'TRUE' : 'FALSE',
                        $item['pft_percent'] ?? 0,
                        $item['roi'] ?? 0,
                        round($totalProfit, 2),
                        round($totalSales, 2),
                        round($totalCogs, 2)
                    ];

                    fputcsv($file, $rowData);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Error exporting Walmart pricing CVR data to CSV: ' . $e->getMessage());
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save buybox price and other manual updates to Walmart data view
     * Similar to Amazon's saveManualLink method
     */
    public function saveBuyboxPrice(Request $request)
    {
        $sku = $request->input('sku');
        $buyboxPrice = $request->input('buybox_price');

        if (!$sku) {
            return response()->json(['error' => 'SKU is required.'], 400);
        }

        $walmartDataView = WalmartDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value array
        $existing = is_array($walmartDataView->value)
            ? $walmartDataView->value
            : (json_decode($walmartDataView->value, true) ?: []);

        // Update buybox price
        if ($buyboxPrice !== null) {
            $existing['buybox_price'] = floatval($buyboxPrice);
        }

        $walmartDataView->value = $existing;
        $walmartDataView->save();

        return response()->json([
            'success' => true,
            'message' => 'Buybox price saved successfully.',
            'data' => $walmartDataView
        ]);
    }

    /**
     * Save manual data to Walmart data view
     * Generic method for saving any field (buybox_price, competitor_price, etc.)
     */
    public function saveManualData(Request $request)
    {
        $sku = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        if (!$sku || !$field) {
            return response()->json(['error' => 'SKU and field are required.'], 400);
        }

        $walmartDataView = WalmartDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value array
        $existing = is_array($walmartDataView->value)
            ? $walmartDataView->value
            : (json_decode($walmartDataView->value, true) ?: []);

        // Update the specified field
        $existing[$field] = $value;

        $walmartDataView->value = $existing;
        $walmartDataView->save();

        return response()->json([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $field)) . ' saved successfully.',
            'data' => $walmartDataView
        ]);
    }
}
