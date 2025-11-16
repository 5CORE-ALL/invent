<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\MacyDataView;
use App\Models\MacyProduct;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MacyController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function macyView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Macys')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.macys', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }


    public function macyPricingCvr(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.macys_pricing_cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }


    public function macyPricingIncreaseandDecrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.macys_pricing_increase_decrease', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }


    public function getViewMacyData(Request $request)
    {
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->toArray();

        $shopifySkus = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $macyProducts = MacyProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $macyDataViews = MacyDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = $productMasters->map(function ($product) use ($shopifySkus, $macyProducts, $macyDataViews) {
            $sku = $product->sku;

            // 🟢 Shopify + Macy base metrics
            $product->INV = $shopifySkus->has($sku) ? $shopifySkus[$sku]->inv : 0;
            $product->L30 = $shopifySkus->has($sku) ? $shopifySkus[$sku]->quantity : 0;
            $product->m_l30 = $macyProducts->has($sku) ? $macyProducts[$sku]->m_l30 : null;
            $product->m_l60 = $macyProducts->has($sku) ? $macyProducts[$sku]->m_l60 : null;
            $product->price = $macyProducts->has($sku) ? $macyProducts[$sku]->price : null;

            // 🟢 Default NR/flags
            $product->NR = '';
            $product->SPRICE = null;
            $product->SPFT = null;
            $product->SROI = null;
            $product->Listed = null;
            $product->Live = null;
            $product->APlus = null;

            // 🟢 MacyDataView enrichments
            if ($macyDataViews->has($sku)) {
                $value = $macyDataViews[$sku]->value;

                if (!is_array($value)) {
                    $value = json_decode($value, true);
                }

                if (is_array($value)) {
                    $product->NR = $value['NR'] ?? '';
                    $product->SPRICE = $value['SPRICE'] ?? null;
                    $product->SPFT = $value['SPFT'] ?? null;
                    $product->SROI = $value['SROI'] ?? null;
                    $product->Listed = isset($value['Listed']) ? filter_var($value['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $product->Live = isset($value['Live']) ? filter_var($value['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $product->APlus = isset($value['APlus']) ? filter_var($value['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // 🟡 LP and SHIP extraction
            $values = is_array($product->Values)
                ? $product->Values
                : (is_string($product->Values) ? json_decode($product->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($product->lp)) {
                $lp = floatval($product->lp);
            }

            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($product->ship) ? floatval($product->ship) : 0);

            // 🟡 Macy percentage (default 100%)
            $percentage = Cache::remember(
                'macy_marketplace_percentage',
                now()->addDays(30),
                function () {
                    return MarketplacePercentage::where('marketplace', 'Macy')->value('percentage') ?? 100;
                }
            ) / 100;

            $price = floatval($product->price ?? 0);
            $units_ordered_l30 = floatval($product->m_l30 ?? 0);

            // 🟢 Profitability calculations
            $product->Total_pft = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $product->T_Sale_l30 = round($price * $units_ordered_l30, 2);
            $product->PFT_percentage = round(
                $price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0,
                2
            );
            $product->ROI_percentage = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );
            $product->T_COGS = round($lp * $units_ordered_l30, 2);
            $product->LP_productmaster = $lp;
            $product->Ship_productmaster = $ship;
            $product->percentage = $percentage;

            return $product;
        })->values();

        return response()->json([
            'message' => 'Macy data fetched successfully (DB only)',
            'product_master_data' => $processedData,
            'status' => 200
        ]);
    }


    // public function getViewMacyData(Request $request)
    // {
    //     // Fetch data from the Google Sheet using the ApiController method
    //     $response = $this->apiController->fetchMacyListingData();

    //     // Check if the response is successful
    //     if ($response->getStatusCode() === 200) {
    //         $data = $response->getData(); // Get the JSON data from the response

    //         // Get all non-PARENT SKUs from the data to fetch from ShopifySku model
    //         $skus = collect($data->data)
    //             ->filter(function ($item) {
    //                 $childSku = $item->{'(Child) sku'} ?? '';
    //                 return !empty($childSku) && stripos($childSku, 'PARENT') === false;
    //             })
    //             ->pluck('(Child) sku')
    //             ->unique()
    //             ->toArray();

    //         // Fetch Shopify inventory data for non-PARENT SKUs
    //         $shopifyData = ShopifySku::whereIn('sku', $skus)
    //             ->get()
    //             ->keyBy('sku');

    //         // Fetch MacyProduct data for non-PARENT SKUs
    //         $macyProducts = MacyProduct::whereIn('sku', values: $skus)
    //             ->get()
    //             ->keyBy('sku');

    //         // Fetch all products from ProductMaster (parent and sku)
    //         $productMasters = ProductMaster::select('parent', 'sku', 'Values')->get();
    //         $skuToProduct = $productMasters->keyBy('sku');
    //         $parentToProduct = $productMasters->keyBy('parent');

    //         // Filter out rows where both Parent and (Child) sku are empty and process data
    //         $filteredData = array_filter($data->data, function ($item) {
    //             $parent = $item->Parent ?? '';
    //             $childSku = $item->{'(Child) sku'} ?? '';

    //             // Keep the row if either Parent or (Child) sku is not empty
    //             return !(empty(trim($parent)) && empty(trim($childSku)));
    //         });

    //         // Process the data to include Shopify inventory values, ProductMaster info, and MacyProduct "M L30"
    //         $processedData = array_map(function ($item) use ($shopifyData, $skuToProduct, $parentToProduct, $macyProducts) {
    //             $childSku = $item->{'(Child) sku'} ?? '';
    //             $parent = $item->Parent ?? '';

    //             // Only update INV and L30 if this is not a PARENT SKU
    //             if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
    //                 if ($shopifyData->has($childSku)) {
    //                     $item->INV = $shopifyData[$childSku]->inv;
    //                     $item->L30 = $shopifyData[$childSku]->quantity;
    //                 } else {
    //                     $item->INV = 0;
    //                     $item->L30 = 0;
    //                 }
    //             }
    //             // Attach ProductMaster info by SKU if available
    //             if (!empty($childSku) && $skuToProduct->has($childSku)) {
    //                 $item->product_master = $skuToProduct[$childSku];
    //             } elseif (!empty($parent) && $parentToProduct->has($parent)) {
    //                 $item->product_master = $parentToProduct[$parent];
    //             } else {
    //                 $item->product_master = null;
    //             }

    //             // Attach MacyProduct "M L30" value if available
    //             if (!empty($childSku) && $macyProducts->has($childSku)) {
    //                 $item->{'M L30'} = $macyProducts[$childSku]->m_l30;
    //                 $item->{'M L60'} = $macyProducts[$childSku]->m_l60;
    //             } else {
    //                 $item->{'M L30'} = null;
    //                 $item->{'M L60'} = null;
    //             }

    //             return $item;
    //         }, $filteredData);

    //         // Re-index the array after filtering
    //         $processedData = array_values($processedData);

    //         // Return the filtered data
    //         return response()->json([
    //             'message' => 'Data fetched successfully',
    //             'data' => $processedData,
    //             'status' => 200
    //         ]);
    //     } else {
    //         // Handle the error if the request failed
    //         return response()->json([
    //             'message' => 'Failed to fetch data from Google Sheet',
    //             'status' => $response->getStatusCode()
    //         ], $response->getStatusCode());
    //     }
    // }

    public function updateAllMacySkus(Request $request)
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
                ['marketplace' => 'Macys'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('macys_marketplace_percentage', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Macys',
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

        $dataView = MacyDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        if ($nr !== null) {
            $value["NR"] = $nr;
        }
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $macyDataView = MacyDataView::firstOrNew(['sku' => $sku]);
        // Decode value column safely
        $existing = is_array($macyDataView->value)
            ? $macyDataView->value
            : (json_decode($macyDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $macyDataView->value = $merged;
        $macyDataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Macys')->first();

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
        $product = MacyDataView::firstOrCreate(
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

    public function importMacysAnalytics(Request $request)
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
                MacyDataView::updateOrCreate(
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

    public function exportMacysAnalytics()
    {
        $macyData = MacyDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($macyData as $data) {
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
        $fileName = 'Macy_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Macy_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
