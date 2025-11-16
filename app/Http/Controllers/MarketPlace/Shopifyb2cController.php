<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\Shopifyb2cDataView;
use App\Models\ShopifySku;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        // LOG::info('Saving Shopify pricing data', $request->all());
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $shopifyDataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
        // Decode value column safely
        $existing = is_array($shopifyDataView->value)
            ? $shopifyDataView->value
            : (json_decode($shopifyDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $shopifyDataView->value = $merged;
        $shopifyDataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
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
}
