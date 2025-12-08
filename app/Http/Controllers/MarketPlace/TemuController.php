<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuDataView;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TemuController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function temuView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100;
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.temu', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function temuPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.temu-cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewTemuData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Temu data for these SKUs
        $temuMetrics = TemuMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values from temu_data_view
        $temuDataViews = TemuDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // NEW: Fetch Temu product sheet data
        $temuProductSheets = TemuProductSheet::whereIn('sku', $skus)->get()->keyBy('sku');

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

            // Add data from temu_metrics if available
            if (isset($temuMetrics[$sku])) {
                $temuMetric = $temuMetrics[$sku];
                $temuPrice = $temuMetric->base_price ?? 0;
                $ship = $values['ship'] ?? 0;

                $processedItem['price'] = $temuPrice > 0 ? $temuPrice + $ship : 0;
                $processedItem['price_wo_ship'] = $temuPrice;
                $processedItem['views_l30'] = $temuMetric->product_impressions_l30 ?? 0;
                $processedItem['views_l60'] = $temuMetric->product_impressions_l60 ?? 0;
                $processedItem['clicks_l30'] = $temuMetric->product_clicks_l30 ?? 0;
                $processedItem['clicks_l60'] = $temuMetric->product_clicks_l60 ?? 0;
                $processedItem['sales_l30'] = $temuMetric->quantity_purchased_l30 ?? 0;
                $processedItem['sales_l60'] = $temuMetric->quantity_purchased_l60 ?? 0;
            } else {
                $processedItem['price'] = 0;
                $processedItem['price_wo_ship'] = 0;
                $processedItem['views_l30'] = 0;
                $processedItem['views_l60'] = 0;
                $processedItem['clicks_l30'] = 0;
                $processedItem['clicks_l60'] = 0;
                $processedItem['sales_l30'] = 0;
                $processedItem['sales_l60'] = 0;
            }

            // NEW: Add data from temu_product_sheets if available
            if (isset($temuProductSheets[$sku])) {
                $temuSheet = $temuProductSheets[$sku];
                $processedItem['sheet_price'] = $temuSheet->price ?? 0;
                $processedItem['sheet_pft'] = $temuSheet->pft ?? 0;
                $processedItem['sheet_roi'] = $temuSheet->roi ?? 0;
                $processedItem['sheet_l30'] = $temuSheet->l30 ?? 0;
                $processedItem['sheet_dil'] = $temuSheet->dil ?? 0;
                $processedItem['buy_link'] = $temuSheet->buy_link ?? '';

                // Calculate T Sales and T DIL
                $inv = $processedItem['INV'] ?? 0;
                $sales_l30 = $processedItem['sales_l30'] ?? 0;
                $sheet_dil = $temuSheet->dil ?? 0;

                $processedItem['T_Sales'] = $temuSheet->dil ?? 0; // T Sales from sheet
                $processedItem['T_DIL'] = $inv > 0 ? ($sheet_dil - ($sales_l30 / $inv)) * 100 : 0; // T DIL formula
            } else {
                $processedItem['sheet_price'] = 0;
                $processedItem['sheet_pft'] = 0;
                $processedItem['sheet_roi'] = 0;
                $processedItem['sheet_l30'] = 0;
                $processedItem['sheet_dil'] = 0;
                $processedItem['buy_link'] = '';
                $processedItem['T_Sales'] = 0;
                $processedItem['T_DIL'] = 0;
            }

            // Calculate CVR
            $clicks_l30 = $processedItem['clicks_l30'] ?? 0;
            $sales_l30 = $processedItem['sales_l30'] ?? 0; // Using sales from Temu API
            $processedItem['CVR'] = ($clicks_l30 > 0) ? ($sales_l30 / $clicks_l30) : 0;
            $processedItem['SOLD'] = $sales_l30; // Add SOLD field for tooltip

            // Add NR, Listed and Live values from temu_data_view if available
            if (isset($temuDataViews[$sku])) {
                $viewData = $temuDataViews[$sku];
                $valuesArr = is_array($viewData->value) ? $viewData->value : (json_decode($viewData->value, true) ?: []);
                $processedItem['NR'] = $valuesArr['NR'] ?? 'REQ';
                $processedItem['Listed'] = isset($valuesArr['Listed']) ? (bool)$valuesArr['Listed'] : false;
                $processedItem['Live'] = isset($valuesArr['Live']) ? (bool)$valuesArr['Live'] : false;
                $processedItem['SPRICE'] = isset($valuesArr['SPRICE']) ? (float)$valuesArr['SPRICE'] : 0;
                $processedItem['SPFT']   = isset($valuesArr['SPFT']) ? (float)$valuesArr['SPFT'] : 0;
                $processedItem['SROI']   = isset($valuesArr['SROI']) ? (float)$valuesArr['SROI'] : 0;
                $processedItem['SHIP']   = isset($valuesArr['SHIP']) ? (float)$valuesArr['SHIP'] : 0;
            } else {
                $processedItem['NR']     = 'REQ';
                $processedItem['Listed'] = false;
                $processedItem['Live']   = false;
                $processedItem['SPRICE'] = 0;
                $processedItem['SPFT']   = 0;
                $processedItem['SROI']   = 0;
                $processedItem['SHIP']   = 0;
            }

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['percentage'] = $percentageValue;

            // Calculate profit and ROI percentages
            $price = floatval($processedItem['price']);
            $percentage = floatval($processedItem['percentage']);
            $lp = floatval($processedItem['LP']);
            $ship = floatval($processedItem['Ship']);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage - $lp - $ship) / $price) * 100;
                $processedItem['PFT_percentage'] = round($pft_percentage, 2);
            } else {
                $processedItem['PFT_percentage'] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage - $lp - $ship) / $lp) * 100;
                $processedItem['ROI_percentage'] = round($roi_percentage, 2);
            } else {
                $processedItem['ROI_percentage'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function updateAllTemuSkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');
            
            // Support legacy 'percent' parameter
            if (!$type && $request->has('percent')) {
                $type = 'percentage';
                $value = $request->input('percent');
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            $percent = $marketplaceData ? $marketplaceData->percentage : 100;
            $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 100;

            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid percentage value. Must be between 0 and 100.'
                    ], 400);
                }
                $percent = $value;
            }

            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid ad_updates value.'
                    ], 400);
                }
                $adUpdates = $value;
            }

            // Update database
            $marketplace = MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Temu'],
                [
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates
                ]
            );

            // Store in cache
            Cache::put('temu_marketplace_percentage', $percent, now()->addDays(30));
            Cache::put('temu_marketplace_ad_updates', $adUpdates, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully',
                'data' => [
                    'marketplace' => 'Temu',
                    'percentage' => $marketplace->percentage,
                    'ad_updates' => $marketplace->ad_updates
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

        $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        if ($nr !== null) {
            $value["NR"] = $nr;
        }
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
        $product = TemuDataView::firstOrCreate(
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
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent', 'ship']);

        if (!$sku || !$spriceData['sprice'] || !isset($spriceData['ship'])) {
            return response()->json(['error' => 'SKU, sprice, and ship are required.'], 400);
        }

        try {
            $temuDataView = TemuDataView::firstOrNew(['sku' => $sku]);

            // Decode existing JSON safely
            $existing = is_array($temuDataView->value)
                ? $temuDataView->value
                : (json_decode($temuDataView->value, true) ?: []);

            // Merge with new values
            $merged = array_merge($existing, [
                'SPRICE' => (float) $spriceData['sprice'],
                'SPFT'   => (float) $spriceData['spft_percent'],
                'SROI'   => (float) $spriceData['sroi_percent'],
                'SHIP'   => (float) $spriceData['ship'],
                'Live'   => true,   // proper boolean
                'Listed' => true    // proper boolean
            ]);

            // Encode JSON with booleans preserved
            $temuDataView->value = $merged;
            $temuDataView->save();

            return response()->json([
                'success' => true,
                'message' => 'Data saved successfully.',
                'data'    => $merged
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving SPRICE for SKU {$sku}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred while saving.'], 500);
        }
    }


    public function temuPricingCVRinc(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.temu_pricing_inc', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function temuPricingCVRdsc(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.temu_pricing_dsc', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function importTemuAnalytics(Request $request)
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
                TemuDataView::updateOrCreate(
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

    public function exportTemuAnalytics()
    {
        $temuData = TemuDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($temuData as $data) {
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
        $fileName = 'Temu_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Temu_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
