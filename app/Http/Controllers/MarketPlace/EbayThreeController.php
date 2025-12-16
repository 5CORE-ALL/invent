<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\EbayThreeDataView;
use App\Models\EbayThreeListingStatus;
use App\Models\ADVMastersData;
use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayPriorityReport;
use App\Models\EbayGeneralReport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class EbayThreeController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function overallthreeEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();

        $ebayPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $ebayAdUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.ebayThreeAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayPercentage' => $ebayPercentage,
            'ebayAdUpdates' => $ebayAdUpdates
        ]);
    }

    public function getEbay3TotalSaleSaveData(Request $request)
    {
        return ADVMastersData::getEbay3TotalSaleSaveDataProceed($request);
    }

    public function ebayThreePricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Ebay3', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.ebayThreePricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewEbay3Data(Request $request)
    {
        // Get percentage and ad_updates from cache or database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        $ebayMetrics = Ebay3Metric::select('sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views', 'item_id')->whereIn('sku', $skus)->get()->keyBy('sku');


        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from EbayThreeDataView
        $ebayDataViews = EbayThreeDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Fetch NRL (nr_req) values from EbayThreeListingStatus
        $ebayListingStatuses = EbayThreeListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];
        $spriceValues = [];
        $spftValues = [];
        $sroiValues = [];
        $sgpftValues = [];
        $nrReqValues = [];
        $hideValues = [];

        foreach ($ebayDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? null;
            $listedValues[$sku] = isset($value['Listed']) ? (int) $value['Listed'] : false;
            $liveValues[$sku] = isset($value['Live']) ? (int) $value['Live'] : false;
            $spriceValues[$sku] = isset($value['SPRICE']) ? floatval($value['SPRICE']) : null;
            $spftValues[$sku] = isset($value['SPFT']) ? floatval($value['SPFT']) : null;
            $sroiValues[$sku] = isset($value['SROI']) ? floatval($value['SROI']) : null;
            $sgpftValues[$sku] = isset($value['SGPFT']) ? floatval($value['SGPFT']) : null;
            $hideValues[$sku] = isset($value['Hide']) ? filter_var($value['Hide'], FILTER_VALIDATE_BOOLEAN) : false;
        }
        
        // Fetch nr_req from EbayThreeListingStatus
        foreach ($ebayListingStatuses as $sku => $listingStatus) {
            $statusValue = is_array($listingStatus->value) ? $listingStatus->value : (json_decode($listingStatus->value, true) ?: []);
            $nrReqValues[$sku] = $statusValue['nr_req'] ?? 'REQ';
        }
        
        // Set default 'REQ' for SKUs not in EbayThreeListingStatus
        foreach ($skus as $sku) {
            if (!isset($nrReqValues[$sku])) {
                $nrReqValues[$sku] = 'REQ';
            }
        }

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            $ebayMetric = $ebayMetrics[$productMaster->sku] ?? null;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                '(Child) sku' => $sku,
                'Sku' => $sku, // Keep both for compatibility
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            //Start Ebay3 Data
            $processedItem['eBay L30'] = $ebayMetric->ebay_l30 ?? 0;
            $processedItem['eBay Price'] = $ebayMetric->ebay_price ?? 0;
            $processedItem['views'] = $ebayMetric->views ?? 0;

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

            // Add image_path - check shopify first, then product master values, then product master image_path
            $processedItem['image_path'] = null;
            if (isset($shopifyData[$sku]) && isset($shopifyData[$sku]->image_src)) {
                $processedItem['image_path'] = $shopifyData[$sku]->image_src;
            } elseif (isset($values['image_path'])) {
                $processedItem['image_path'] = $values['image_path'];
            } elseif (isset($productMaster->image_path)) {
                $processedItem['image_path'] = $productMaster->image_path;
            }

            // Fetch NR value if available
            $processedItem['NR'] = $nrValues[$sku] ?? null;
            $processedItem['nr_req'] = $nrReqValues[$sku] ?? 'REQ';
            $processedItem['Listed'] = $listedValues[$sku] ?? false;
            $processedItem['Live'] = $liveValues[$sku] ?? false;
            $processedItem['Hide'] = $hideValues[$sku] ?? false;

            // Fetch SPRICE, SPFT, SROI, SGPFT from database if available
            $processedItem['SPRICE'] = $spriceValues[$sku] ?? null;
            $processedItem['SPFT'] = $spftValues[$sku] ?? null;
            $processedItem['SROI'] = $sroiValues[$sku] ?? null;
            $processedItem['SGPFT'] = $sgpftValues[$sku] ?? null;

            // Calculate AD% and other metrics
            $price = floatval($processedItem['eBay Price'] ?? 0);
            $ebayL30 = floatval($processedItem['eBay L30'] ?? 0);
            $lp = floatval($processedItem['LP'] ?? 0);
            $ship = floatval($processedItem['Ship'] ?? 0);
            $views = floatval($processedItem['views'] ?? 0);
            
            // Get AD spend from reports if available
            $adSpendL30 = 0;
            $kw_spend_l30 = 0;
            $pmt_spend_l30 = 0;
            if ($ebayMetric && $ebayMetric->item_id) {
                // Try to get from EbayPriorityReport (keyword campaigns)
                $matchedCampaignL30 = Ebay3PriorityReport::where('report_range', 'L30')
                    ->where('campaign_name', 'LIKE', '%' . $sku . '%')
                    ->first();
                
                // Try to get from EbayGeneralReport (promoted listings)
                $matchedGeneralL30 = Ebay3GeneralReport::where('report_range', 'L30')
                    ->where('listing_id', $ebayMetric->item_id)
                    ->first();
                
                $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
                $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
                $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;
            }
            
            // Add AD_Spend_L30 to processedItem for frontend
            $processedItem['AD_Spend_L30'] = round($adSpendL30, 2);
            $processedItem['spend_l30'] = round($adSpendL30, 2);
            $processedItem['kw_spend_L30'] = round($kw_spend_l30, 2);
            $processedItem['pmt_spend_L30'] = round($pmt_spend_l30, 2);
            
            // Calculate AD% = (AD Spend L30 / (Price * eBay L30)) * 100
            $totalRevenue = $price * $ebayL30;
            $processedItem['AD%'] = $totalRevenue > 0 ? round(($adSpendL30 / $totalRevenue) * 100, 4) : 0;
            
            // Calculate Profit and Sales L30
            $processedItem['Total_pft'] = round(($price * $percentageValue - $lp - $ship) * $ebayL30, 2);
            $processedItem['Profit'] = $processedItem['Total_pft'];
            $processedItem['T_Sale_l30'] = round($price * $ebayL30, 2);
            $processedItem['Sales L30'] = $processedItem['T_Sale_l30'];
            
            // Calculate TacosL30 = AD Spend L30 / Total Sales L30
            $processedItem['TacosL30'] = $processedItem['T_Sale_l30'] > 0 ? round($adSpendL30 / $processedItem['T_Sale_l30'], 4) : 0;
            
            // Calculate GPFT% = ((Price * 0.86 - Ship - LP) / Price) * 100
            $gpft = $price > 0 ? (($price * 0.86 - $ship - $lp) / $price) * 100 : 0;
            $processedItem['GPFT%'] = round($gpft, 2);
            
            // Calculate PFT% = GPFT% - AD%
            $processedItem['PFT %'] = round($gpft - $processedItem['AD%'], 2);
            
            // Calculate ROI% = ((Price * percentage - LP - Ship) / LP) * 100
            $processedItem['ROI%'] = round(
                $lp > 0 ? (($price * $percentageValue - $lp - $ship) / $lp) * 100 : 0,
                2
            );
            
            // Calculate SCVR = (eBay L30 / views) * 100
            $processedItem['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
            
            // Calculate E Dil% = (L30 / INV) if INV > 0
            $inv = floatval($processedItem['INV'] ?? 0);
            $l30 = floatval($processedItem['L30'] ?? 0);
            $processedItem['E Dil%'] = $inv > 0 ? round($l30 / $inv, 4) : 0;

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['price'] = 0;
            $processedItem['TOTAL PFT'] = $processedItem['Total_pft'];
            $processedItem['T Sales L30'] = $processedItem['T_Sale_l30'];
            $processedItem['Roi'] = $processedItem['ROI%'];
            $processedItem['percentage'] = $percentageValue;
            $processedItem['ad_updates'] = $adUpdates;
            $processedItem['LP_productmaster'] = $lp;
            $processedItem['Ship_productmaster'] = $ship;

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function updateAllEbay3Skus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Validate inputs
            if (empty($type)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Type parameter is required.'
                ], 400);
            }

            if ($value === null || $value === '') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Value parameter is required.'
                ], 400);
            }

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Ebay3')->first();

            $percent = $marketplace ? $marketplace->percentage : 100;
            $adUpdates = $marketplace ? ($marketplace->ad_updates ?? 0) : 0;

            // Handle percentage update
            if ($type === 'percentage' || $request->has('percent')) {
                $percentValue = $request->input('percent') ?? $value;
                if (!is_numeric($percentValue) || $percentValue < 0 || $percentValue > 100) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid percentage value. Must be between 0 and 100.'
                    ], 400);
                }
                $percent = (float) $percentValue;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid ad_updates value. Must be a positive number.'
                    ], 400);
                }
                $adUpdates = (float) $value;
            }

            // Save both fields - check for existing record including soft-deleted ones
            $marketplace = MarketplacePercentage::withTrashed()->where('marketplace', 'Ebay3')->first();
            
            if ($marketplace) {
                // If soft-deleted, restore it first
                if ($marketplace->trashed()) {
                    $marketplace->restore();
                }
                // Update existing record
                $marketplace->percentage = $percent;
                $marketplace->ad_updates = $adUpdates;
                $marketplace->save();
            } else {
                // Create new record
                $marketplace = MarketplacePercentage::create([
                    'marketplace' => 'Ebay3',
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates,
                ]);
            }

            // Refresh the model to get the latest values
            $marketplace->refresh();

            // Store in cache
            Cache::put('Ebay3', $percent, now()->addDays(30));
            Cache::put('Ebay3_ad_updates', $adUpdates, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type ?? 'percentage') . ' updated successfully',
                'data' => [
                    'marketplace' => 'Ebay3',
                    'percentage' => (float) $marketplace->percentage,
                    'ad_updates' => (float) ($marketplace->ad_updates ?? 0)
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in updateAllEbay3Skus: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Error updating Ebay3 marketplace values',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $skus = $request->input("skus");
        $hideValues = $request->input("hideValues");
        $sku = $request->input("sku");
        $nr = $request->input("nr");
        $hide = $request->input("hide");

        // Decode hideValues if it's a JSON string
        if (is_string($hideValues)) {
            $hideValues = json_decode($hideValues, true);
        }

        // Bulk update with individual hide values
        if (is_array($skus) && is_array($hideValues)) {
            foreach ($skus as $skuItem) {
                $ebayDataView = EbayThreeDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?: []);
                // Use the value from hideValues for each SKU
                $value["Hide"] = filter_var(
                    $hideValues[$skuItem] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                $ebayDataView->value = $value;
                $ebayDataView->save();
            }
            return response()->json([
                "success" => true,
                "updated" => count($skus),
            ]);
        }

        // Bulk update if 'skus' is present and 'hide' is a single value (legacy)
        if (is_array($skus) && $hide !== null) {
            foreach ($skus as $skuItem) {
                $ebayDataView = EbayThreeDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?: []);
                $value["Hide"] = filter_var($hide, FILTER_VALIDATE_BOOLEAN);
                $ebayDataView->value = $value;
                $ebayDataView->save();
            }
            return response()->json([
                "success" => true,
                "updated" => count($skus),
            ]);
        }

        // Single update (existing logic)
        if (!$sku || ($nr === null && $hide === null)) {
            return response()->json(
                ["error" => "SKU and at least one of NR or Hide is required."],
                400
            );
        }

        $ebayDataView = EbayThreeDataView::firstOrNew(["sku" => $sku]);
        $value = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        if ($nr !== null) {
            $value["NR"] = $nr;
        }

        if ($hide !== null) {
            $value["Hide"] = filter_var($hide, FILTER_VALIDATE_BOOLEAN);
        }

        $ebayDataView->value = $value;
        $ebayDataView->save();

        // Create a user-friendly message based on what was updated
        $message = "Data updated successfully";
        if ($nr !== null) {
            $message = $nr === 'NRL' ? "NRL updated" : ($nr === 'REQ' ? "REQ updated" : "NR updated to {$nr}");
        } elseif ($hide !== null) {
            $message = "Hide status updated";
        }

        return response()->json(["success" => true, "data" => $ebayDataView, "message" => $message]);
    }


    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayThreeDataView::firstOrCreate(
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

    public function importEbayThreeAnalytics(Request $request)
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
                EbayThreeDataView::updateOrCreate(
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

    public function exportEbayThreeAnalytics()
    {
        $ebayData = EbayThreeDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($ebayData as $data) {
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
        $fileName = 'Ebay_Three_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Ebay_Three_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving eBay3 pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');
        $spft_percent = $request->input('spft_percent');
        $sroi_percent = $request->input('sroi_percent');

        if (!$sku || !$sprice) {
            Log::error('SKU or sprice missing', ['sku' => $sku, 'sprice' => $sprice]);
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get current marketplace percentage for Ebay3
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        Log::info('Using percentage', ['percentage' => $percentage]);

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            Log::error('SKU not found in ProductMaster', ['sku' => $sku]);
            return response()->json(['error' => 'SKU not found in ProductMaster.'], 404);
        }

        // Extract lp and ship
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
        Log::info('LP and Ship', ['lp' => $lp, 'ship' => $ship]);

        // Calculate profit
        $spriceFloat = floatval($sprice);
        $profit = ($spriceFloat * $percentage - $lp - $ship);

        // Calculate SGPFT first
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * 0.86 - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // Get AD% from the product (using Ebay3Metric)
        $adPercent = 0;
        $ebay3Metric = Ebay3Metric::where('sku', $sku)->first();
        
        // For Ebay3, we'll calculate AD% if we have the metric data
        // You may need to adjust this based on your actual data structure
        if ($ebay3Metric) {
            // Calculate AD% based on available data
            // This is a simplified version - adjust based on your actual requirements
            $adPercent = 0; // Default to 0 if no specific calculation available
        }
        
        // Use provided SPFT and SROI if available, otherwise calculate
        $spft = $spft_percent !== null ? floatval($spft_percent) : round($sgpft - $adPercent, 2);
        
        // SROI = ((SPRICE * (0.86 - AD%/100) - ship - lp) / lp) * 100
        $adDecimal = $adPercent / 100;
        $sroi = $sroi_percent !== null ? floatval($sroi_percent) : round(
            $lp > 0 ? (($spriceFloat * (0.86 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
            2
        );
        
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'ad_percent' => $adPercent, 'spft' => $spft, 'sroi' => $sroi]);

        $ebayThreeDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($ebayThreeDataView->value)
            ? $ebayThreeDataView->value
            : (json_decode($ebayThreeDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $ebayThreeDataView->value = $merged;
        $ebayThreeDataView->save();
        Log::info('Data saved successfully to EbayThreeDataView', ['sku' => $sku]);

        return response()->json([
            'success' => true,
            'message' => 'SPRICE saved successfully for Ebay3',
            'data' => [
                'sku' => $sku,
                'sprice' => $spriceFloat,
                'spft' => $spft,
                'sroi' => $sroi,
                'sgpft' => $sgpft
            ]
        ]);
    }
}
