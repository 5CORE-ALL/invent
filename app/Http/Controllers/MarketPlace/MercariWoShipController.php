<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\MercariWoShipDataView;
use App\Models\MercariWoShipPriceSoldData;
use App\Models\MercariWoShipListingStatus;
use App\Models\MercariDailyData;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MercariWoShipController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function mercariWoShipTabulatorView(Request $request)
    {
        return view('market-places.mercari_without_ship_tabulator_view');
    }

    public function getMercariWoShipTabulatorData(Request $request)
    {
        $productMasterRows = ProductMaster::all();
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch Shopify data (inventory + image) for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Fetch Mercari without-ship price & sold data keyed by SKU
        $priceSoldData = MercariWoShipPriceSoldData::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch listing statuses (buyer/seller links) keyed by SKU
        $listingStatusData = MercariWoShipListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        // Build ProductMaster lookup map for SKU matching against Mercari order titles
        $productMastersBySku = $productMasterRows->mapWithKeys(function ($pm) {
            $skuUpper = strtoupper(trim($pm->sku));
            $skuNoSpaces = str_replace([' ', '-', '_'], '', $skuUpper);
            return [
                $skuUpper => $pm,
                $skuNoSpaces => $pm,
            ];
        });

        // Get L30 sold counts from MercariDailyData (mercari-without-ship daily sales)
        $l30Data = $this->getL30OrderCounts($skus, $productMastersBySku);
        $orderCounts = $l30Data['orderCounts'];

        // MercariWoShip percentage (profit factor) from marketplace_percentages
        $percentage = MarketplacePercentage::where('marketplace', 'MercariWoShip')->value('percentage');
        $factor = ($percentage !== null ? (float) $percentage : 100) / 100;

        $data = [];
        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;

            // Skip parent rows
            if (stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $values = is_array($productMaster->Values)
                ? $productMaster->Values
                : (json_decode($productMaster->Values, true) ?: []);
            $shopifyItem = $shopifyData[$sku] ?? null;
            $priceSold = $priceSoldData[$sku] ?? null;
            $soldL30 = $orderCounts[$sku] ?? 0;

            // Buyer/Seller links from listing status
            $statusValue = $listingStatusData[$sku]->value ?? [];
            if (is_string($statusValue)) {
                $statusValue = json_decode($statusValue, true) ?: [];
            }

            $price = (float) ($priceSold->price ?? 0);
            $lp = (float) ($values['lp'] ?? 0);
            $ship = (float) ($values['ship'] ?? 0);
            $inv = (float) ($shopifyItem->inv ?? 0);

            // NR/REQ: default to REQ when INV > 0, else NR
            $nrReq = $statusValue['nr_req'] ?? ($inv > 0 ? 'REQ' : 'NR');

            // PFT% and ROI% calculations (without-ship: shipping not applied)
            $pft = $price > 0 ? (($price * $factor - $lp) / $price) * 100 : 0;
            $roi = $lp > 0 ? (($price * $factor - $lp) / $lp) * 100 : 0;

            // S Price (manual, saved in listing status) and its SPFT/SROI
            $sprice = isset($statusValue['sprice']) && $statusValue['sprice'] !== '' && $statusValue['sprice'] !== null
                ? (float) $statusValue['sprice']
                : null;
            $spft = ($sprice !== null && $sprice > 0) ? (($sprice * $factor - $lp) / $sprice) * 100 : 0;
            $sroi = ($sprice !== null && $lp > 0) ? (($sprice * $factor - $lp) / $lp) * 100 : 0;

            $data[] = [
                'Parent' => $productMaster->parent ?? null,
                'image_path' => $shopifyItem->image_src ?? ($values['image_path'] ?? null),
                'sku' => $sku,
                'INV' => $shopifyItem->inv ?? 0,
                'L30' => $shopifyItem->quantity ?? 0,
                'price' => $price,
                'sold' => $soldL30,
                'PFT' => round($pft, 2),
                'ROI' => round($roi, 2),
                'sprice' => $sprice,
                'SPFT' => round($spft, 2),
                'SROI' => round($sroi, 2),
                'nr_req' => $nrReq,
                'lp' => $lp,
                'ship' => $ship,
                'factor' => $factor,
                'buyer_link' => $statusValue['buyer_link'] ?? null,
                'seller_link' => $statusValue['seller_link'] ?? null,
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function saveMercariWoShipStatus(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        $sku = $request->input('sku');

        $status = MercariWoShipListingStatus::firstOrNew(['sku' => $sku]);
        $value = is_array($status->value)
            ? $status->value
            : (json_decode($status->value, true) ?: []);

        // Only update fields present in the request
        foreach (['sprice', 'nr_req'] as $field) {
            if ($request->has($field)) {
                $value[$field] = $request->input($field);
            }
        }

        $status->value = $value;
        $status->save();

        return response()->json(['success' => true]);
    }

    /**
     * Extract potential SKUs from item title and match with ProductMaster.
     */
    private function extractAndMatchSkuFromTitle($itemTitle, $productMastersBySku)
    {
        if (empty($itemTitle)) {
            return null;
        }

        $variations = [];

        if (preg_match('/\b([A-Za-z0-9\s\-]{3,})\s*$/', $itemTitle, $matches)) {
            $lastPart = trim($matches[1]);
            $variations[] = $lastPart;
            $variations[] = strtoupper($lastPart);
            $variations[] = str_replace(' ', '', $lastPart);
            $variations[] = str_replace(' ', '', strtoupper($lastPart));
            $variations[] = str_replace([' ', '-'], '', strtoupper($lastPart));

            $words = explode(' ', $lastPart);
            if (count($words) > 1 && strlen($words[0]) <= 3) {
                $withoutPrefix = trim(implode(' ', array_slice($words, 1)));
                if (strlen($withoutPrefix) >= 3) {
                    $variations[] = $withoutPrefix;
                    $variations[] = strtoupper($withoutPrefix);
                    $variations[] = str_replace(' ', '', $withoutPrefix);
                    $variations[] = str_replace(' ', '', strtoupper($withoutPrefix));
                    $variations[] = str_replace([' ', '-'], '', strtoupper($withoutPrefix));
                }
            }
        }

        if (preg_match_all('/\b([A-Za-z]{1,}[a-z]*\s*[A-Z0-9]{1,}(?:\s+[A-Za-z0-9]+){0,3})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed;
                    $variations[] = strtoupper($trimmed);
                    $variations[] = str_replace(' ', '', $trimmed);
                    $variations[] = str_replace(' ', '', strtoupper($trimmed));
                }
            }
        }

        if (preg_match_all('/\b(\d+[A-Za-z]+\s+[A-Za-z0-9]+(?:\s+[A-Za-z0-9]+){0,2})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed;
                    $variations[] = strtoupper($trimmed);
                    $variations[] = str_replace(' ', '', $trimmed);
                    $variations[] = str_replace(' ', '', strtoupper($trimmed));
                }
            }
        }

        if (preg_match_all('/\b([A-Z]{2,}\s+[A-Z0-9]{1,}(?:\s+[A-Z0-9]+){0,4})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 4) {
                    $variations[] = $trimmed;
                    $variations[] = str_replace(' ', '', $trimmed);
                }
            }
        }

        if (preg_match_all('/\b([A-Za-z0-9\-]{4,})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                $variations[] = $trimmed;
                $variations[] = strtoupper($trimmed);
            }
        }

        $variations = array_values(array_unique(array_filter($variations)));

        foreach ($variations as $variation) {
            $normalized = strtoupper(trim($variation));
            $normalizedNoSpaces = str_replace([' ', '-', '_'], '', $normalized);

            if (isset($productMastersBySku[$normalized])) {
                return $productMastersBySku[$normalized]->sku;
            }
            if (isset($productMastersBySku[$normalizedNoSpaces])) {
                return $productMastersBySku[$normalizedNoSpaces]->sku;
            }

            foreach ($productMastersBySku as $pmSku => $pm) {
                $pmSkuUpper = strtoupper(trim($pmSku));
                $pmSkuNoSpaces = str_replace([' ', '-', '_'], '', $pmSkuUpper);

                if ($normalized === $pmSkuUpper || $normalizedNoSpaces === $pmSkuNoSpaces) {
                    return $pm->sku;
                }

                if (strlen($normalized) >= 3) {
                    if (stripos($pmSkuUpper, $normalized) !== false ||
                        stripos($normalized, $pmSkuUpper) !== false ||
                        stripos($pmSkuNoSpaces, $normalizedNoSpaces) !== false ||
                        stripos($normalizedNoSpaces, $pmSkuNoSpaces) !== false) {
                        return $pm->sku;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get L30 order counts from MercariDailyData for without-ship sales (buyer_shipping_fee > 0).
     */
    private function getL30OrderCounts($skus, $productMastersBySku)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Without-ship: buyer pays shipping (buyer_shipping_fee > 0)
        $mercariOrders = MercariDailyData::where('buyer_shipping_fee', '>', 0)
            ->where('sold_date', '>=', $thirtyDaysAgo)
            ->whereNull('canceled_date')
            ->where(function ($query) {
                $query->whereNull('order_status')
                      ->orWhere('order_status', 'not like', '%cancelled%')
                      ->orWhere('order_status', 'not like', '%canceled%');
            })
            ->get();

        $orderCounts = array_fill_keys($skus, 0);
        $matchedSkus = [];

        foreach ($mercariOrders as $order) {
            if (empty($order->item_title)) {
                continue;
            }

            $matchedSku = $this->extractAndMatchSkuFromTitle($order->item_title, $productMastersBySku);

            if ($matchedSku && isset($orderCounts[$matchedSku])) {
                $orderCounts[$matchedSku]++;
                $matchedSkus[$matchedSku] = $matchedSku;
            }
        }

        return [
            'orderCounts' => $orderCounts,
            'matchedSkus' => $matchedSkus,
        ];
    }

    public function importMercariWoShipPriceSold(Request $request)
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

            $existingSkus = array_flip(
                ProductMaster::whereIn('sku', $allSkus)->pluck('sku')->toArray()
            );

            $importCount = 0;
            foreach ($rows as $row) {
                if (empty($row[0])) {
                    continue;
                }

                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (empty($data['sku']) || !isset($existingSkus[$data['sku']])) {
                    continue;
                }

                MercariWoShipPriceSoldData::updateOrCreate(
                    ['sku' => $data['sku']],
                    [
                        'price' => isset($data['price']) && $data['price'] !== null && $data['price'] !== ''
                            ? (float) preg_replace('/[^0-9.\-]/', '', (string) $data['price'])
                            : null,
                        'sold' => isset($data['sold']) && $data['sold'] !== null && $data['sold'] !== ''
                            ? (int) preg_replace('/[^0-9\-]/', '', (string) $data['sold'])
                            : null,
                    ]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount price & sold records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function downloadMercariWoShipPriceSoldSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['SKU', 'Price'];
        $sheet->fromArray($headers, NULL, 'A1');

        $sampleData = [
            ['SKU001', 19.99],
            ['SKU002', 24.50],
            ['SKU003', 9.99],
        ];
        $sheet->fromArray($sampleData, NULL, 'A2');

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);

        $fileName = 'MercariWoShip_PriceSold_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function MercariWoShipPricingCVR(Request $request)
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

    public function getViewMercariWoShipData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('MercariWoShip', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'MercariWoShip')->first();
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
        $walmartDataViews = MercariWoShipDataView::whereIn('sku', $skus)->get()->keyBy('sku');
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

    public function updateAllMercariWoShipSkus(Request $request)
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
                ['marketplace' => 'MercariWoShip'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('MercariWoShip', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'MercariWoShip',
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

        // Flatten properly
        $nrValue = is_array($nr) && isset($nr['NR']) ? $nr['NR'] : $nr;

        $dataView = MercariWoShipDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?: []);

        // Save correctly
        $value['NR'] = $nrValue;

        $dataView->value = $value;
        $dataView->save();

        return response()->json([
            'success' => true,
            'data' => $dataView
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
        $product = MercariWoShipDataView::firstOrCreate(
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

    public function importMercariWoShipAnalytics(Request $request)
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
                MercariWoShipDataView::updateOrCreate(
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

    public function exportMercariWoShipAnalytics()
    {
        $mercariWoShipData = MercariWoShipDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($mercariWoShipData as $data) {
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
        $fileName = 'MercariWoShip_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'MercariWoShip_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
