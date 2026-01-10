<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\Shopifyb2cDataView;
use App\Models\ShopifySku;
use App\Models\ProductMaster;
use App\Models\ShopifyB2CDailyData;
use App\Models\ShopifyB2CListingStatus;
use App\Models\AmazonDatasheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Shopifyb2cController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function shopifyb2cView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('shopifyb2c_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.shopifyb2c', [
            'mode' => $mode,
            'demo' => $demo,
            'shopifyb2cPercentage' => $percentage
        ]);
    }


    public function shopifyPricingCvr(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('shopifyb2c_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.shopify_pricing_cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'shopifyb2cPercentage' => $percentage
        ]);
    }


    public function shopifyb2cViewPricingIncreaseDecrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('shopifyb2c_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.shopifyb2c_pricing_increase_decrease', [
            'mode' => $mode,
            'demo' => $demo,
            'shopifyb2cPercentage' => $percentage
        ]);
    }
    // public function getViewShopifyB2CData(Request $request)
    // {
    //     $response = $this->apiController->fetchShopifyB2CListingData();

    //     if ($response->getStatusCode() === 200) {
    //         $data = $response->getData();

    //         $skus = collect($data->data)
    //             ->filter(function ($item) {
    //                 $childSku = $item->{'(Child) sku'} ?? '';
    //                 return !empty($childSku) && stripos($childSku, 'PARENT') === false;
    //             })
    //             ->pluck('(Child) sku')
    //             ->unique()
    //             ->toArray();

    //         // Shopify data
    //         $shopifyData = ShopifySku::whereIn('sku', $skus)
    //             ->get()
    //             ->keyBy('sku');

    //         // ProductMaster for LP & Ship
    //         $productMasterData = ProductMaster::whereIn('sku', $skus)
    //             ->get()
    //             ->keyBy('sku');

    //         $nrValues = Shopifyb2cDataView::pluck('value', 'sku');

    //         $filteredData = array_filter($data->data, function ($item) {
    //             $parent = $item->Parent ?? '';
    //             $childSku = $item->{'(Child) sku'} ?? '';
    //             return !(empty(trim($parent)) && empty(trim($childSku)));
    //         });

    //         $processedData = array_map(function ($item) use ($shopifyData, $productMasterData, $nrValues) {
    //             $childSku = $item->{'(Child) sku'} ?? '';

    //             if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
    //                 if ($shopifyData->has($childSku)) {
    //                     $skuData = $shopifyData[$childSku];
    //                     $item->INV = $skuData->inv;
    //                     $item->L30 = $skuData->quantity;

    //                     $item->SPRICE = $skuData->SPRICE ?? null;
    //                     $item->SPFT   = $skuData->SPFT ?? null;
    //                     $item->SROI   = $skuData->SROI ?? null;
    //                     $item->NR     = $skuData->NR ?? null;

    //                     // LP & Ship from ProductMaster
    //                     $pm = $productMasterData[$childSku] ?? null;
    //                     $lp = 0;
    //                     $ship = 0;

    //                     if ($pm) {
    //                         $values = is_array($pm->Values)
    //                             ? $pm->Values
    //                             : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

    //                         foreach ($values as $k => $v) {
    //                             if (strtolower($k) === 'lp') {
    //                                 $lp = floatval($v);
    //                                 break;
    //                             }
    //                         }
    //                         if ($lp === 0 && isset($pm->lp)) {
    //                             $lp = floatval($pm->lp);
    //                         }

    //                         $ship = isset($values['ship'])
    //                             ? floatval($values['ship'])
    //                             : (isset($pm->ship) ? floatval($pm->ship) : 0);
    //                     }

    //                     $item->LP_productmaster = $lp;
    //                     $item->Ship_productmaster = $ship;

    //                     // Profit Calculations
    //                     $price = floatval($item->SPRICE ?? 0);
    //                     $units_ordered_l30 = floatval($item->L30 ?? 0);
    //                     $percentage = 1; // default 100%

    //                     $item->Total_pft = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
    //                     $item->T_Sale_l30 = round($price * $units_ordered_l30, 2);
    //                     $item->PFT_percentage = round(
    //                         $price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0,
    //                         2
    //                     );
    //                     $item->ROI_percentage = round(
    //                         $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
    //                         2
    //                     );
    //                     $item->T_COGS = round($lp * $units_ordered_l30, 2);
    //                 } else {
    //                     $item->INV = 0;
    //                     $item->L30 = 0;
    //                     $item->SPRICE = null;
    //                     $item->SPFT = null;
    //                     $item->SROI = null;
    //                     $item->NR = null;
    //                     $item->LP_productmaster = 0;
    //                     $item->Ship_productmaster = 0;
    //                 }

    //                 // NR Handling
    //                 $item->NR = false;
    //                 $item->Listed = false;
    //                 $item->Live = false;

    //                 if ($childSku && isset($nrValues[$childSku])) {
    //                     $val = $nrValues[$childSku];
    //                     if (is_array($val)) {
    //                         $item->NR = $val['NR'] ?? false;
    //                         $item->Listed = !empty($val['Listed']) ? (int)$val['Listed'] : false;
    //                         $item->Live = !empty($val['Live']) ? (int)$val['Live'] : false;
    //                     } else {
    //                         $decoded = json_decode($val, true);
    //                         $item->NR = $decoded['NR'] ?? false;
    //                         $item->Listed = !empty($decoded['Listed']) ? (int)$decoded['Listed'] : false;
    //                         $item->Live = !empty($decoded['Live']) ? (int)$decoded['Live'] : false;
    //                     }
    //                 }
    //             }

    //             return (array) $item;
    //         }, $filteredData);

    //         $processedData = array_values($processedData);

    //         return response()->json([
    //             'message' => 'Data fetched successfully',
    //             'data' => $processedData,
    //             'status' => 200
    //         ]);
    //     } else {
    //         return response()->json([
    //             'message' => 'Failed to fetch data from Google Sheet',
    //             'status' => $response->getStatusCode()
    //         ], $response->getStatusCode());
    //     }
    // }


    public function getViewShopifyB2CData(Request $request)
    {
        // Fetch all relevant SKUs from ShopifySku and ProductMaster
        $shopifyData = ShopifySku::all()->keyBy('sku');
        $productMasterData = ProductMaster::all()->keyBy('sku');
        $nrValues = Shopifyb2cDataView::pluck('value', 'sku');

        // Collect all unique SKUs
        $skus = $productMasterData->keys();

        $processedData = $skus->map(function ($sku) use ($shopifyData, $productMasterData, $nrValues) {
            $item = new \stdClass();
            $item->{'(Child) sku'} = $sku;

            // Shopify data
            if ($shopifyData->has($sku)) {
                $skuData = $shopifyData[$sku];
                $item->INV = $skuData->inv;
                $item->L30 = $skuData->quantity;
                $item->Price = $skuData->price;
                $item->SPRICE = $skuData->SPRICE ?? null;
                $item->SPFT   = $skuData->SPFT ?? null;
                $item->SROI   = $skuData->SROI ?? null;
            } else {
                $item->INV = 0;
                $item->L30 = 0;
                $item->Price = 0;
                $item->SPRICE = null;
                $item->SPFT = null;
                $item->SROI = null;
            }

            // ProductMaster LP & Ship
            $pm = $productMasterData[$sku] ?? null;
            $lp = 0;
            $ship = 0;
            $item->Parent = null;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = $values['lp'] ?? $pm->lp ?? 0;
                $ship = $values['ship'] ?? $pm->ship ?? 0;
                $item->Parent = $pm->parent ?? null;
            }
            $item->LP_productmaster = floatval($lp);
            $item->Ship_productmaster = floatval($ship);

            // Profit Calculations
            $price = floatval($item->SPRICE ?? 0);
            $units_ordered_l30 = floatval($item->L30 ?? 0);
            $percentage = 1; // default 100%

            $item->Total_pft = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $item->T_Sale_l30 = round($price * $units_ordered_l30, 2);
            $item->PFT_percentage = round($price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0, 2);
            $item->ROI_percentage = round($lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);
            $item->T_COGS = round($lp * $units_ordered_l30, 2);

            // NR Handling
            $item->NR = false;
            $item->Listed = false;
            $item->Live = false;

            if (isset($nrValues[$sku])) {
                $val = $nrValues[$sku];
                if (is_array($val)) {
                    $item->NR = $val['NR'] ?? false;
                    $item->Listed = !empty($val['Listed']) ? (int)$val['Listed'] : false;
                    $item->Live = !empty($val['Live']) ? (int)$val['Live'] : false;
                } else {
                    $decoded = json_decode($val, true);
                    $item->NR = $decoded['NR'] ?? false;
                    $item->Listed = !empty($decoded['Listed']) ? (int)$decoded['Listed'] : false;
                    $item->Live = !empty($decoded['Live']) ? (int)$decoded['Live'] : false;
                }
            }

            return (array) $item;
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData->values(),
            'status' => 200
        ]);
    }


    public function updateAllShopifyB2CSkus(Request $request)
    {
        try {
            $percent = $request->input('percent');

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid percentage value. Must be between 0 and 100.'
                ], 400);
            }

            MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'ShopifyB2C'],
                ['percentage' => $percent]
            );

            Cache::put('shopifyb2c_marketplace_percentage', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'ShopifyB2C',
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

    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $ebayDataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);

        $value = $ebayDataView->value ?? [];
        $value['NR'] = $nr;

        $ebayDataView->value = $value;
        $ebayDataView->save();

        return response()->json([
            'success' => true,
            'data' => $ebayDataView->value // return clean JSON
        ]);
    }



    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get product master data for LP and Ship
        $productMaster = ProductMaster::where('sku', $sku)->first();
        if (!$productMaster) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        $values = is_array($productMaster->Values)
            ? $productMaster->Values
            : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
        
        $lp = $values['lp'] ?? ($productMaster->lp ?? 0);
        $ship = $values['ship'] ?? ($productMaster->ship ?? 0);

        // Calculate metrics with 95% margin
        $percentage = 0.95;
        $grossProfit = ($sprice * $percentage) - $lp - $ship;
        
        $sgpft = $sprice > 0 ? ($grossProfit / $sprice) * 100 : 0;
        $sroi = $lp > 0 ? ($grossProfit / $lp) * 100 : 0;

        // Get ADS% from shopify_b2c_daily_data
        $shopifyB2COrders = \App\Models\ShopifyB2CDailyData::where('sku', $sku)
            ->where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->selectRaw('SUM(quantity) as total_quantity, SUM(price * quantity) as total_sales')
            ->first();

        $salesL30 = $shopifyB2COrders->total_sales ?? 0;

        // Get Google Ads spend
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29);
        
        $googleSpent = \DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('date', '<=', $yesterday->format('Y-m-d'))
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [strtoupper(trim($sku))])
            ->sum('metrics_cost_micros') / 1000000;

        $ads = $salesL30 > 0 ? ($googleSpent / $salesL30) * 100 : 0;

        // Calculate net values
        $snpft = $sgpft - $ads;
        $snroi = $sroi - $ads;

        // Save to database
        $shopifyDataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($shopifyDataView->value)
            ? $shopifyDataView->value
            : (json_decode($shopifyDataView->value, true) ?: []);

        $merged = array_merge($existing, [
            'SPRICE' => $sprice,
            'SGPFT' => $sgpft,
            'SNPFT' => $snpft,
            'SROI' => $sroi,
            'SNROI' => $snroi,
        ]);

        $shopifyDataView->value = $merged;
        $shopifyDataView->save();

        return response()->json([
            'message' => 'Data saved successfully.',
            'sgpft_percent' => $sgpft,
            'snpft_percent' => $snpft,
            'sroi_percent' => $sroi,
            'snroi_percent' => $snroi,
        ]);
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Shopify B2C')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }


    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = Shopifyb2cDataView::firstOrCreate(
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

    public function importShopifyB2CAnalytics(Request $request)
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
                Shopifyb2cDataView::updateOrCreate(
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

    public function exportShopifyB2CAnalytics()
    {
        $shopifyB2CData = Shopifyb2cDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($shopifyB2CData as $data) {
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
        $fileName = 'ShopifyB2C_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'ShopifyB2C_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ========== SHOPIFY B2C TABULATOR VIEW ==========
    
    public function shopifyB2cTabulatorView()
    {
        return view('market-places.shopify_b2c_tabulator_view');
    }

    public function shopifyB2cDataJson()
    {
        $data = $this->getViewShopifyB2cTabularData();
        return response()->json($data);
    }

    public function getViewShopifyB2cTabularData()
    {
        // Hardcoded 95% margin for Shopify B2C
        $percentage = 95;
        $percentageValue = 0.95;

        // Fetch all product master records (excluding parent rows)
        $productMasterRows = ProductMaster::all()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->keyBy("sku");

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch L30 orders from shopify_b2c_daily_data (period = 'l30')
        $shopifyB2COrders = ShopifyB2CDailyData::whereIn('sku', $skus)
            ->where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');

        // Fetch Amazon prices
        $amazonData = AmazonDatasheet::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch listing status data
        $listingStatusData = ShopifyB2CListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        // Fetch Google Ads spend per SKU (L30 - last 30 days)
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29);
        $startDateStr = $startDate->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        $googleSpentData = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $yesterdayStr)
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->selectRaw('UPPER(TRIM(campaign_name)) as sku_key, SUM(metrics_cost_micros) / 1000000 as total_spend')
            ->groupByRaw('UPPER(TRIM(campaign_name))')
            ->pluck('total_spend', 'sku_key')
            ->toArray();

        // Build Google spend lookup by SKU
        $googleSpentBySku = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $googleSpentBySku[$sku] = $googleSpentData[$skuUpper] ?? 0;
        }

        $processedItems = [];

        foreach ($productMasterRows as $sku => $productMaster) {
            $processedItem = [];
            $processedItem["(Child) sku"] = $sku;
            $processedItem["Parent"] = $productMaster->parent ?? null;

            // Get Values field
            $values = is_array($productMaster->Values)
                ? $productMaster->Values
                : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);

            $lp = $values["lp"] ?? ($productMaster->lp ?? 0);
            $ship = $values["ship"] ?? ($productMaster->ship ?? 0);

            $processedItem["LP_productmaster"] = $lp;
            $processedItem["Ship_productmaster"] = $ship;

            // Add shopify SKU data if available
            $shopifyItem = $shopifyData[$sku] ?? null;
            if ($shopifyItem) {
                $processedItem["INV"] = $shopifyItem->inv ?? 0;
                $processedItem["L30"] = $shopifyItem->quantity ?? 0; // OV L30 - Overall sales from shopify_skus
                $processedItem["Price"] = $shopifyItem->price ?? 0;
                $processedItem["Views"] = $shopifyItem->views ?? 0;
                $processedItem["image_path"] = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
            } else {
                $processedItem["INV"] = 0;
                $processedItem["L30"] = 0;
                $processedItem["Price"] = 0;
                $processedItem["Views"] = 0;
                $processedItem["image_path"] = $values["image_path"] ?? ($productMaster->image_path ?? null);
            }

            // Get B2C L30 orders from shopify_b2c_daily_data (B2C sales only)
            $b2cOrder = $shopifyB2COrders[$sku] ?? null;
            $processedItem["L30"] = $processedItem["L30"]; // Keep OV L30 from shopify_skus
            $processedItem["B2B L30"] = $b2cOrder ? $b2cOrder->total_quantity : 0;

            // Check if SKU exists in Shopify (Missing column)
            if ($shopifyItem) {
                $processedItem["Missing"] = ''; // SKU exists
            } else {
                $processedItem["Missing"] = 'M'; // SKU missing
            }

            // Amazon Price
            if (isset($amazonData[$sku])) {
                $processedItem["A Price"] = $amazonData[$sku]->price ?? 0;
            } else {
                $processedItem["A Price"] = 0;
            }

            // Get NR/REQ from shopify_b2c_listing_statuses
            $processedItem["nr_req"] = 'REQ'; // Default value
            
            if (isset($listingStatusData[$sku])) {
                $listingStatus = $listingStatusData[$sku];
                $statusValue = is_array($listingStatus->value) 
                    ? $listingStatus->value 
                    : (json_decode($listingStatus->value, true) ?? []);
                
                $rlNrl = $statusValue['rl_nrl'] ?? null;
                
                if (!$rlNrl && isset($statusValue['nr_req'])) {
                    $rlNrl = ($statusValue['nr_req'] === 'REQ') ? 'RL' : (($statusValue['nr_req'] === 'NR') ? 'NRL' : 'RL');
                }
                
                if ($rlNrl === 'RL') {
                    $processedItem['nr_req'] = 'REQ';
                } elseif ($rlNrl === 'NRL') {
                    $processedItem['nr_req'] = 'NR';
                }
            }

            // Calculate profit metrics with 95% margin
            // All profit calculations use B2B L30 (actually B2C L30 from shopify_b2c_daily_data)
            $price = $processedItem["Price"];
            $b2cL30 = $processedItem["B2B L30"]; // Rename for clarity: it's from shopify_b2c_daily_data
            $ovL30 = $processedItem["L30"];
            
            if ($price > 0 && $b2cL30 > 0) {
                $grossProfit = ($price * $percentageValue) - $lp - $ship;
                $processedItem["GPFT%"] = ($grossProfit / $price) * 100;
                $processedItem["ROI%"] = $lp > 0 ? ($grossProfit / $lp) * 100 : 0;
                $processedItem["Profit"] = $grossProfit * $b2cL30;
                $processedItem["Sales L30"] = $price * $b2cL30;
            } else {
                $processedItem["GPFT%"] = 0;
                $processedItem["ROI%"] = 0;
                $processedItem["Profit"] = 0;
                $processedItem["Sales L30"] = 0;
            }

            // Calculate DIL% = (OV L30 / INV) * 100 (overall inventory turnover)
            $inv = $processedItem["INV"];
            $processedItem["DIL%"] = $inv > 0 ? ($ovL30 / $inv) * 100 : 0;

            // Calculate CVR% = (OV L30 / Views) * 100 (overall conversion rate)
            $views = $processedItem["Views"];
            $processedItem["CVR%"] = $views > 0 ? ($ovL30 / $views) * 100 : 0;

            // Add Google Ads Spend for this SKU
            $processedItem["googleSpent"] = $googleSpentBySku[$sku] ?? 0;

            // Calculate ADS% = (googleSpent / Sales L30) * 100
            $salesL30 = $processedItem["Sales L30"];
            $processedItem["ADS%"] = $salesL30 > 0 ? (($googleSpentBySku[$sku] ?? 0) / $salesL30) * 100 : 0;

            $processedItems[] = $processedItem;
        }

        return $processedItems;
    }

    public function updateShopifyB2cListedLive(Request $request)
    {
        $sku = $request->input('sku');
        $nrReq = $request->input('nr_req');

        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }

        // Convert REQ/NR to RL/NRL for storage
        $rlNrlValue = ($nrReq === 'REQ') ? 'RL' : 'NRL';

        // Get existing listing status
        $listingStatus = ShopifyB2CListingStatus::where('sku', $sku)->first();

        if ($listingStatus) {
            $currentValue = is_array($listingStatus->value) 
                ? $listingStatus->value 
                : (json_decode($listingStatus->value, true) ?? []);
            
            $currentValue['rl_nrl'] = $rlNrlValue;
            
            $listingStatus->value = $currentValue;
            $listingStatus->save();
        } else {
            ShopifyB2CListingStatus::create([
                'sku' => $sku,
                'value' => ['rl_nrl' => $rlNrlValue]
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id();
        $cacheKey = "shopify_b2c_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($cacheKey, []);
        
        return response()->json(['visibility' => $visibility]);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id();
        $visibility = $request->input('visibility', []);
        $cacheKey = "shopify_b2c_tabulator_column_visibility_{$userId}";
        
        Cache::put($cacheKey, $visibility, now()->addDays(30));
        
        return response()->json(['success' => true]);
    }
}
