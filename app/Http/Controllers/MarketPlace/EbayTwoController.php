<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\EbayTwoDataView;
use App\Services\Ebay2ApiService;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\Ebay2GeneralReport;
use App\Models\ADVMastersData;
use App\Models\Ebay2Metric;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EbayTwoController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function updateEbayPricing(Request $request)
    {

        $service = new Ebay2ApiService();

        $itemID = $request["sku"];
        $newPrice = $request["price"];
        $response = $service->reviseFixedPriceItem(
            itemId: $itemID,
            price: $newPrice,
        );

        return response()->json(['status' => 200, 'data' => $response]);
    }

    public function overallEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database
        $marketplaceData = ChannelMaster::where('channel', 'EbayTwo')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

        return view('market-places.ebayTwoAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayTwoPercentage' => $percentage
        ]);
    }

    public function getEbay2TotsalSaleDataSave(Request $request)
    {
        return ADVMastersData::getEbay2TotsalSaleDataSaveProceed($request);
    }

    public function EbayTwoPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        return view('market-places.EbayTwoPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayTwoPercentage' => $percentage
        ]);
    }

    public function getViewEbayData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();
        
        // 3. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        /*    
        $ebayMetrics = Ebay2Metric::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku"); 
        */
            
        $ebayMetrics = DB::connection('apicentral')
            ->table('ebay2_metrics')
            ->select('sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views', 'item_id')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');  

        $nrValues = EbayTwoDataView::whereIn("sku", $skus)->pluck("value", "sku");

        // Mapping: item_id → sku
        $itemIdToSku = $ebayMetrics->pluck('sku', 'item_id')->toArray();

        // ✅ Fetch L30 Clicks directly from ebay2_general_reports
        $extraClicksData = Ebay2GeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('clicks', 'listing_id')
            ->toArray();

        // 4. Fetch General Reports (listing_id → sku)
        $generalReports = Ebay2GeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->whereIn('report_range', ['L60', 'L30', 'L7'])
            ->get();

        $adMetricsBySku = [];

        // General Reports
        foreach ($generalReports as $report) {
            $sku = $itemIdToSku[$report->listing_id] ?? null;
            if (!$sku) continue;

            $range = strtoupper($report->report_range);

            $adMetricsBySku[$sku][$range]['GENERAL_SPENT'] =
                ($adMetricsBySku[$sku][$range]['GENERAL_SPENT'] ?? 0) + $this->extractNumber($report->ad_fees);

            $adMetricsBySku[$sku][$range]['Imp'] =
                ($adMetricsBySku[$sku][$range]['Imp'] ?? 0) + (int) $report->impressions;

            $adMetricsBySku[$sku][$range]['Clk'] =
                ($adMetricsBySku[$sku][$range]['Clk'] ?? 0) + (int) $report->clicks;

            $adMetricsBySku[$sku][$range]['Ctr'] =
                ($adMetricsBySku[$sku][$range]['Ctr'] ?? 0) + (float) $report->ctr;

            $adMetricsBySku[$sku][$range]['Sls'] =
                ($adMetricsBySku[$sku][$range]['Sls'] ?? 0) + (int) $report->sales;
        }

        // 5. Marketplace percentage (EbayTwo)
        $percentage = (MarketplacePercentage::where("marketplace", "EbayTwo")->value("percentage") ?? 100) / 100;

        // 6. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebayMetric = $ebayMetrics[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            // eBay2 Metrics
            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;

            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            // Ad Metrics (only GENERAL from ebay2_general_reports)
            $pmtData = $adMetricsBySku[$sku] ?? [];
            foreach (['L60', 'L30', 'L7'] as $range) {
                $metrics = $pmtData[$range] ?? [];
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = $metrics[$suffix] ?? 0;
                }
            }

            // ✅ Merge Extra Clicks (L30 only)
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $row["PmtClkL30"] += (int) $extraClicksData[$ebayMetric->item_id];
            }

            // Values: LP & Ship
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

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);
            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);
            $row["PmtClkL30"] = $adMetricsBySku[$sku]['L30']['Clk'] ?? 0;
            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["PFT %"] = round(
                $price > 0 ? (($price * $percentage - $lp - $ship) / $price) : 0,
                2
            );
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) : 0,
                2
            );
            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // NR & Hide
            $row['NR'] = "";
            $row['SPRICE'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['SPFT'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "eBay2 Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }




    public function updateAllEbay2Skus(Request $request)
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
                ['marketplace' => 'EbayTwo'],
                ['percentage' => $percent]
            );

            // No caching needed for instant results
            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'EbayTwo',   // ✅ Fix here
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

        $dataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        $value['NR'] = $nr;
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
        $product = EbayTwoDataView::firstOrCreate(
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
    function extractNumber($value)
    {
        if (is_null($value)) {
            return null;
        }

        // Match only digits
        preg_match('/\d+/', $value, $matches);

        return $matches[0] ?? null;
    }


    public function saveSpriceToDatabase(Request $request)
    {
        // LOG::info('Saving Shopify pricing data', $request->all());
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }


        $ebayDataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $ebayDataView->value = $merged;
        $ebayDataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
    }

    public function importEbayTwoAnalytics(Request $request)
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
                EbayTwoDataView::updateOrCreate(
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

    public function exportEbayTwoAnalytics()
    {
        $ebayData = EbayTwoDataView::all();

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
        $fileName = 'Ebay_Two_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Ebay_Two_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
