<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\ProductMaster;
use App\Models\WayfairDataView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WayfairController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function wayfairView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('wayfair_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Wayfair')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.Wayfair', [
            'mode' => $mode,
            'demo' => $demo,
            'wayfairPercentage' => $percentage

        ]);
    }

    public function getAllData()
    {
        $amazonDatas = $this->apiController->fetchExternalData2();
        return response()->json($amazonDatas);
    }

    public function getViewWayfairData(Request $request)
    {
        $response = $this->apiController->fetchDataFromWayfairMasterGoogleSheet();

        if ($response->getStatusCode() === 200) {
            $data = $response->getData();

            // Get JungleScout data with proper price handling
            $jungleScoutData = JungleScoutProductData::all()
                ->groupBy('parent')
                ->map(function ($group) {
                    // Get all valid numeric prices > 0
                    $validPrices = $group->filter(function ($item) {
                        $price = $item->data['price'] ?? null;
                        return is_numeric($price) && $price > 0;
                    })->pluck('data.price');

                    return [
                        'scout_parent' => $group->first()->parent,
                        'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                        'product_count' => $group->count(),
                        'all_data' => $group->map(function ($item) {
                            // Ensure price is properly formatted
                            $data = $item->data;
                            if (isset($data['price'])) {
                                $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                            }
                            return $data;
                        })->toArray()
                    ];
                });

            $skus = collect($data->data)
                ->filter(function ($item) {
                    $childSku = $item->{'(Child) sku'} ?? '';
                    return !empty($childSku) && stripos($childSku, 'PARENT') === false;
                })
                ->pluck('(Child) sku')
                ->unique()
                ->toArray();

            $shopifyData = ShopifySku::whereIn('sku', $skus)
                ->get()
                ->keyBy('sku');

            // Fetch NR values before processing data
            $nrValues = WayfairDataView::pluck('value', 'sku');

            $filteredData = array_filter($data->data, function ($item) {
                $parent = $item->Parent ?? '';
                $childSku = $item->{'(Child) sku'} ?? '';
                return !(empty(trim($parent)) && empty(trim($childSku)));
            });

            $processedData = array_map(function ($item) use ($shopifyData, $jungleScoutData, $nrValues) {
                $childSku = $item->{'(Child) sku'} ?? '';
                $parentAsin = $item->Parent ?? '';

                // Add JungleScout data if parent ASIN matches
                if (!empty($parentAsin) && $jungleScoutData->has($parentAsin)) {
                    $scoutData = $jungleScoutData[$parentAsin];
                    $item->scout_data = [
                        'scout_parent' => $scoutData['scout_parent'],
                        'min_price' => $scoutData['min_price'],
                        'product_count' => $scoutData['product_count'],
                        'all_data' => $scoutData['all_data']
                    ];
                }

                if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
                    if ($shopifyData->has($childSku)) {
                        $item->INV = $shopifyData[$childSku]->inv;
                        $item->L30 = $shopifyData[$childSku]->quantity;
                    } else {
                        $item->INV = 0;
                        $item->L30 = 0;
                    }

                    // NR value
                    $item->NR = false;
                    $item->Listed = false;
                    $item->Live = false;

                    if ($childSku && isset($nrValues[$childSku])) {
                        $val = $nrValues[$childSku];
                        if (is_array($val)) {
                            $item->NR = $val['NR'] ?? '';
                            $item->Listed = !empty($val['Listed']) ? (int)$val['Listed'] : false;
                            $item->Live = !empty($val['Live']) ? (int)$val['Live'] : false;
                        } else {
                            $decoded = json_decode($val, true);
                            $item->NR = $decoded['NR'] ?? '';
                            $item->Listed = !empty($decoded['Listed']) ? (int)$decoded['Listed'] : false;
                            $item->Live = !empty($decoded['Live']) ? (int)$decoded['Live'] : false;
                        }
                    }
                }

                return $item;
            }, $filteredData);

            $processedData = array_values($processedData);

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $processedData,
                'status' => 200,
                'debug' => [
                    'jungle_scout_parents' => $jungleScoutData->keys()->take(5),
                    'matched_parents' => collect($processedData)
                        ->filter(fn($item) => isset($item->scout_data))
                        ->pluck('Parent')
                        ->unique()
                        ->values()
                ]
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }
    }


    public function updateAllWayfairSkus(Request $request)
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
                ['marketplace' => 'Wayfair'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('wayfair_marketplace_percentage', $percent, now()->addDays(30));

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
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = WayfairDataView::firstOrNew(['sku' => $sku]);
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
        $product = WayfairDataView::firstOrCreate(
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

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Wayfair')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function importWayfairAnalytics(Request $request)
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
                WayfairDataView::updateOrCreate(
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

    public function exportWayfairAnalytics()
    {
        $wayfairData = WayfairDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($wayfairData as $data) {
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
        $fileName = 'Wayfair_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Wayfair_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
