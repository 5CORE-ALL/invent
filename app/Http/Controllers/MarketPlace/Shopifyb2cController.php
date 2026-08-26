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
use App\Models\AmazonDataView;
use App\Models\GoogleSkuCompetitor;
use App\Services\ChannelPromoPricingService;
use App\Services\LmpSkuGroupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonChannelSummary;

class Shopifyb2cController extends Controller
{
    public const BADGE_CHANNEL = 'shopify_b2c';

    /** Keys stored in amazon_channel_summary_data.summary_data for /shopify-b2c-pricing badges. */
    public const BADGE_METRICS = [
        'total_sales', 'total_orders', 'total_qty', 'total_pft', 'total_cogs', 'total_spend',
        'gpft_percent', 'groi_percent', 'nroi_percent', 'npft_percent', 'tcos_percent',
        'total_l30', 'total_views', 'cvr_percent', 'total_b2b_l30',
        'zero_sold_count', 'sold_count', 'missing_count', 'less_amz_count', 'more_amz_count',
        'blue_triangle_count', 'purple_triangle_count',
        'lmp_missing_count', 'prc_gt_lmp_count', 'price_lt80_lmp_count',
        'avg_price', 'total_inv',
    ];

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
            return $marketplaceData ? $marketplaceData->percentage : 95; // Default to 100 if not set
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
        // Check if bulk updates or single update
        $updates = $request->input('updates');
        
        Log::info('saveSpriceToDatabase called', [
            'has_updates' => !empty($updates),
            'updates_count' => is_array($updates) ? count($updates) : 0,
            'raw_input' => $request->all()
        ]);
        
        if ($updates && is_array($updates)) {
            // Bulk update mode
            return $this->saveBulkSpriceUpdates($updates);
        }
        
        // Single update mode
        $sku = $request->input('sku');
        $sprice = $request->input('sprice');

        if (!$sku || $sprice === null) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $result = $this->calculateAndSaveSprice($sku, $sprice, [
            'amz_sugg' => $request->boolean('amz_sugg'),
        ]);
        
        if ($result['success']) {
            return response()->json([
                'message' => 'Data saved successfully.',
                'sgpft_percent' => $result['sgpft'],
                'snpft_percent' => $result['snpft'],
                'sroi_percent' => $result['sroi'],
                'snroi_percent' => $result['snroi'],
            ]);
        } else {
            return response()->json(['error' => $result['error']], 400);
        }
    }

    private function saveBulkSpriceUpdates($updates)
    {
        Log::info('Bulk SPRICE update started', ['count' => count($updates)]);
        
        $successCount = 0;
        $errors = [];

        foreach ($updates as $update) {
            $sku = $update['sku'] ?? null;
            $sprice = $update['sprice'] ?? null;

            if (!$sku || $sprice === null) {
                $errors[] = ['sku' => $sku, 'error' => 'SKU or sprice missing'];
                continue;
            }

            $result = $this->calculateAndSaveSprice($sku, $sprice, [
                'amz_sugg' => !empty($update['amz_sugg']),
            ]);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errors[] = ['sku' => $sku, 'error' => $result['error']];
            }
        }

        Log::info('Bulk SPRICE update completed', [
            'success' => $successCount,
            'errors' => count($errors)
        ]);

        return response()->json([
            'success' => true,
            'updated' => $successCount,
            'errors' => $errors,
            'message' => "Updated $successCount SKU(s)"
        ]);
    }

    private function calculateAndSaveSprice($sku, $sprice, array $extra = [])
    {
        // Get product master data for LP and Ship
        $productMaster = ProductMaster::where('sku', $sku)->first();
        if (!$productMaster) {
            return ['success' => false, 'error' => 'Product not found'];
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

        // Channel Ads% (TCOS badge) — same as Amazon AMAZON_CHANNEL_ADS_PCT for PFT / SNROI
        $channelAdsPct = 0.0;
        try {
            $snapshot = app(\App\Http\Controllers\Channels\ChannelMasterController::class)
                ->getShopifyDirectL30Snapshot();
            $channelAdsPct = (float) ($snapshot['tcos_pct'] ?? 0);
        } catch (\Throwable $e) {
            $channelAdsPct = 0;
        }
        $rowAds = $salesL30 > 0 ? ($googleSpent / $salesL30) * 100 : 0;
        $ads = $channelAdsPct > 0 ? $channelAdsPct : $rowAds;

        // Calculate net values (SNPFT = SGPFT − channel Ads%)
        $snpft = $sgpft - $ads;

        // SNROI — same shape as Amazon net SROI / NROI badge:
        // (suggested gross $ − SPRICE × Ads%/100) / LP × 100
        $adSpendUnit = $sprice * ($ads / 100);
        $snroi = floatval($lp) > 0 ? (($grossProfit - $adSpendUnit) / floatval($lp)) * 100 : 0;

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
            'AMZ_SUGG_APPLIED' => !empty($extra['amz_sugg']),
        ]);

        $shopifyDataView->value = $merged;
        $saved = $shopifyDataView->save();
        
        Log::info('SPRICE saved to shopifyb2c_data_view', [
            'sku' => $sku,
            'sprice' => $sprice,
            'saved' => $saved,
            'id' => $shopifyDataView->id,
            'value' => $merged
        ]);

        return [
            'success' => true,
            'sgpft' => $sgpft,
            'snpft' => $snpft,
            'sroi' => $sroi,
            'snroi' => $snroi,
        ];
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
        // Pull L30 sales + distinct-order count + units sold from the same source
        // `/shopify` and the /all-marketplace-master Shopify row use
        // (shopify_raw_orders with the marketplace exclusions). Lets this page's
        // Total Sales / Orders / Qty badges agree with that page byte-for-byte.
        $shopifySnapshot = app(\App\Http\Controllers\Channels\ChannelMasterController::class)
            ->getShopifyDirectL30Snapshot();

        return view('market-places.shopify_b2c_tabulator_view', [
            'shopifyDirectL30Sales'    => (float) ($shopifySnapshot['l30_sales']      ?? 0),
            'shopifyDirectL30Orders'   => (int)   ($shopifySnapshot['l30_orders']     ?? 0),
            'shopifyDirectL30Qty'      => (int)   ($shopifySnapshot['qty']            ?? 0),
            // Profit / cost / ad-spend + the derived percentages — same numbers
            // /all-marketplace-master shows for the Shopify row, so the GPFT /
            // TCOS / NPFT badges on this page agree with that page byte-for-byte.
            'shopifyDirectTotalPft'    => (float) ($shopifySnapshot['total_pft']      ?? 0),
            'shopifyDirectTotalCogs'   => (float) ($shopifySnapshot['total_cogs']     ?? 0),
            'shopifyDirectTotalSpend'  => (float) ($shopifySnapshot['total_ad_spend'] ?? 0),
            'shopifyDirectGpftPct'     => (float) ($shopifySnapshot['gpft_pct']       ?? 0),
            'shopifyDirectGroiPct'     => (float) ($shopifySnapshot['groi_pct']       ?? 0),
            'shopifyDirectTcosPct'     => (float) ($shopifySnapshot['tcos_pct']       ?? 0),
            'shopifyDirectNpftPct'     => (float) ($shopifySnapshot['npft_pct']       ?? 0),
            'shopifyDirectNroiPct'     => (float) ($shopifySnapshot['nroi_pct']       ?? 0),
        ]);
    }

    public function shopifyB2cDataJson()
    {
        $data = $this->getViewShopifyB2cTabularData();

        // Save snapshot after JSON is sent so the table is not blocked (same as eBay).
        $rows = is_array($data) ? $data : [];
        dispatch(function () use ($rows) {
            $level = ob_get_level();
            ob_start();
            try {
                app(self::class)->snapshotDailyBadgeSummary($rows);
            } catch (\Throwable $e) {
                Log::error('Error saving daily Shopify B2C summary: ' . $e->getMessage());
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        })->afterResponse();

        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29);

        $totalGoogleSpend = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('date', '<=', $yesterday->format('Y-m-d'))
            ->where('advertising_channel_type', 'SHOPPING')
            ->sum('metrics_cost_micros') / 1000000;

        return response()->json([
            'data' => $data,
            'campaign_totals' => [
                'google_spend_L30' => $totalGoogleSpend
            ]
        ]);
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
        $shopifyData = ShopifySku::mapByProductSkus($skus);

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

        // Std Prc — amazon_data_view.STANDARD_PRICE (same shared store as /amazon-tabulator-view)
        $amazonStandardPrices = [];
        foreach (AmazonDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $adv) {
            $val = is_array($adv->value)
                ? $adv->value
                : (json_decode((string) ($adv->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (is_numeric($std) && (float) $std > 0) {
                $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
            }
        }

        // PRMT%/CPN%/DSC%/Appr/Push Prc — shopify_b2c_promo_pricing (site-specific)
        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('shopify_b2c', $skus);

        // Fetch listing status data
        $listingStatusData = ShopifyB2CListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        // Fetch SPRICE data from shopifyb2c_data_view
        $shopifyB2cViewData = Shopifyb2cDataView::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Google LMP from /repricer/google-search → google_sku_competitors
        $googleLmpDetails = collect();
        try {
            $googleLmpLookups = GoogleSkuCompetitor::buildGroupedLookup('google');
            $googleLmpDetails = $googleLmpLookups['details'];
        } catch (\Throwable $e) {
            Log::warning('Shopify B2C Google LMP lookup failed: ' . $e->getMessage());
        }

        // Sku Link LMP — shared lmp_sku_links groups (same as Amazon / Newegg / Shein)
        $lmpGroupService = new LmpSkuGroupService();
        try {
            $lmpGroupService->prepareForSkus(array_values(array_filter(array_map(
                static fn ($s) => trim((string) $s),
                $skus
            ))));
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepare failed (Shopify B2C): ' . $e->getMessage());
        }

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

        // Channel Ads% (TCOS badge) — used for SNROI when row ADS% is 0, same as NROI badge
        $channelAdsPct = 0.0;
        try {
            $snapshot = app(\App\Http\Controllers\Channels\ChannelMasterController::class)
                ->getShopifyDirectL30Snapshot();
            $channelAdsPct = (float) ($snapshot['tcos_pct'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Shopify B2C SNROI channel ads fetch failed: ' . $e->getMessage());
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
            $processedItem["B Link"] = '';
            $processedItem["S Link"] = '';
            
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

                // Buyer / Seller links from Listing Shopify B2C
                $processedItem["B Link"] = $statusValue['buyer_link'] ?? '';
                $processedItem["S Link"] = $statusValue['seller_link'] ?? '';
            }

            // Calculate profit metrics with 95% margin
            // All profit calculations use B2B L30 (actually B2C L30 from shopify_b2c_daily_data)
            $price = $processedItem["Price"];
            $b2cL30 = $processedItem["B2B L30"]; // Rename for clarity: it's from shopify_b2c_daily_data
            $ovL30 = $processedItem["L30"];
            
            // Calculate GPFT% and ROI% based on price (even if no sales)
            if ($price > 0) {
                $grossProfit = ($price * $percentageValue) - $lp - $ship;
                $processedItem["GPFT%"] = ($grossProfit / $price) * 100;
                $processedItem["ROI%"] = $lp > 0 ? ($grossProfit / $lp) * 100 : 0;
                
                // Total profit and sales only when there are actual sales
                if ($b2cL30 > 0) {
                    $processedItem["Profit"] = $grossProfit * $b2cL30;
                    $processedItem["Sales L30"] = $price * $b2cL30;
                } else {
                    $processedItem["Profit"] = 0;
                    $processedItem["Sales L30"] = 0;
                }
            } else {
                $processedItem["GPFT%"] = 0;
                $processedItem["ROI%"] = 0;
                $processedItem["Profit"] = 0;
                $processedItem["Sales L30"] = 0;
            }

            // Calculate DIL% = (OV L30 / INV) * 100 (overall inventory turnover)
            $inv = $processedItem["INV"];
            $processedItem["DIL%"] = $inv > 0 ? ($ovL30 / $inv) * 100 : 0;

            // Calculate CVR% = (B2C L30 / Views) * 100 (B2C conversion rate)
            $views = $processedItem["Views"];
            $processedItem["CVR%"] = $views > 0 ? ($b2cL30 / $views) * 100 : 0;

            // Add Google Ads Spend for this SKU
            $adSpend = (float) ($googleSpentBySku[$sku] ?? 0);
            $processedItem["googleSpent"] = $adSpend;

            // Calculate ADS% = (googleSpent / Sales L30) * 100
            $salesL30 = $processedItem["Sales L30"];
            $processedItem["ADS%"] = $salesL30 > 0 ? ($adSpend / $salesL30) * 100 : 0;

            // NROI% — Amazon unit formula (same shape as SNROI / GROI with channel Ads%):
            //   ((Price × 0.95 − Ship − LP − Price × Ads%/100) / LP) × 100
            // Not gated on B2C L30 qty (old COGS = LP × qty made NROI always 0 with no sales).
            if ($price > 0 && floatval($lp) > 0) {
                $unitGross = ($price * $percentageValue) - floatval($lp) - floatval($ship);
                $adSpendUnit = $price * ($channelAdsPct / 100);
                $processedItem["NROI%"] = (($unitGross - $adSpendUnit) / floatval($lp)) * 100;
            } else {
                $processedItem["NROI%"] = 0;
            }

            // Get SPRICE from shopifyb2c_data_view
            $processedItem["SPRICE"] = 0;
            $processedItem["SGPFT"] = 0;
            $processedItem["SNPFT"] = 0;
            $processedItem["SROI"] = 0;
            $processedItem["SNROI"] = 0;
            $processedItem["SPRICE_STATUS"] = null;
            $processedItem["has_custom_sprice"] = false;
            $processedItem["AMZ_SUGG_APPLIED"] = false;

            if (isset($shopifyB2cViewData[$sku])) {
                $viewData = $shopifyB2cViewData[$sku];
                $valuesArr = is_array($viewData->value)
                    ? $viewData->value
                    : (json_decode($viewData->value, true) ?: []);
                
                $processedItem["SPRICE"] = isset($valuesArr["SPRICE"]) ? floatval($valuesArr["SPRICE"]) : 0;
                $processedItem["has_custom_sprice"] = $processedItem["SPRICE"] > 0;
                $processedItem["AMZ_SUGG_APPLIED"] = !empty($valuesArr["AMZ_SUGG_APPLIED"]);
                $processedItem["SGPFT"] = isset($valuesArr["SGPFT"]) ? floatval($valuesArr["SGPFT"]) : 0;
                $processedItem["SNPFT"] = isset($valuesArr["SNPFT"]) ? floatval($valuesArr["SNPFT"]) : 0;
                $processedItem["SROI"] = isset($valuesArr["SROI"]) ? floatval($valuesArr["SROI"]) : 0;
                // Push status (same shopifyb2c_data_view field CVR /push-shopify-b2c-price updates)
                $processedItem["SPRICE_STATUS"] = $valuesArr["SPRICE_STATUS"] ?? null;

                // SNROI — same shape as Amazon net SROI / NROI badge:
                //   (suggested gross $ − SPRICE × channel Ads%/100) / LP × 100
                $sprice = (float) $processedItem["SPRICE"];
                if ($sprice > 0 && floatval($lp) > 0) {
                    $sGrossUnit = ($sprice * $percentageValue) - floatval($lp) - floatval($ship);
                    $adSpendUnit = $sprice * ($channelAdsPct / 100);
                    $processedItem["SNROI"] = (($sGrossUnit - $adSpendUnit) / floatval($lp)) * 100;
                    // SNPFT = SGPFT − channel Ads% (same as Amazon SNPFT)
                    if (isset($processedItem["SGPFT"])) {
                        $processedItem["SNPFT"] = (float) $processedItem["SGPFT"] - $channelAdsPct;
                    }
                } elseif (isset($valuesArr["SNROI"])) {
                    // Fall back to stored value when we can't recompute (no SPRICE / LP)
                    $processedItem["SNROI"] = floatval($valuesArr["SNROI"]);
                }
            }

            // Google LMP merged across Sku Link LMP group (same as Amazon / Newegg)
            $linkedLmpSkus = $this->shopifyB2cLinkedLmpSkusFor($lmpGroupService, (string) $sku);
            $processedItem['linked_lmp_skus'] = $linkedLmpSkus;

            // Std Prc — shared amazon_data_view.STANDARD_PRICE; inherit from Sku Link LMP siblings
            $stdPrc = $amazonStandardPrices[strtoupper(trim((string) $sku))] ?? null;
            if ($stdPrc === null) {
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedKey = strtoupper(trim((string) $linkedSku));
                    if ($linkedKey !== '' && isset($amazonStandardPrices[$linkedKey])) {
                        $stdPrc = $amazonStandardPrices[$linkedKey];
                        break;
                    }
                }
            }
            $processedItem['STANDARD_PRICE'] = $stdPrc;
            $processedItem = app(ChannelPromoPricingService::class)->applyToRow($processedItem, $promoMap, (string) $sku);

            $mergedLmpEntries = collect();
            $seenLmp = [];
            $skusForLmp = $linkedLmpSkus !== [] ? $linkedLmpSkus : [$sku];
            foreach ($skusForLmp as $linkedSku) {
                $linkedKey = GoogleSkuCompetitor::normalizeSkuKey((string) $linkedSku);
                $groupEntries = $googleLmpDetails->get($linkedKey);
                if (! $groupEntries instanceof \Illuminate\Support\Collection) {
                    continue;
                }
                foreach ($groupEntries as $comp) {
                    $dedupeKey = ((string) ($comp->id ?? '')).'|'
                        .((string) ($comp->product_id ?? '')).'|'
                        .strtoupper(trim((string) ($comp->source ?? ''))).'|'
                        .strtoupper(trim((string) ($comp->product_link ?? '')));
                    if (isset($seenLmp[$dedupeKey])) {
                        continue;
                    }
                    $seenLmp[$dedupeKey] = true;
                    $mergedLmpEntries->push($comp);
                }
            }
            $mergedLmpEntries = GoogleSkuCompetitor::sortCollectionByNumericPrice($mergedLmpEntries);
            $lowestLmp = GoogleSkuCompetitor::lowestFromCollection($mergedLmpEntries);

            $processedItem['lmp_price'] = ($lowestLmp && is_numeric($lowestLmp->price))
                ? round((float) $lowestLmp->price, 2)
                : null;
            $processedItem['lmp_link'] = $lowestLmp->product_link ?? null;
            $processedItem['lmp_source'] = $lowestLmp->source ?? null;
            $processedItem['lmp_title'] = $lowestLmp->product_title ?? null;
            $processedItem['lmp_entries'] = $mergedLmpEntries->map(static function ($comp) {
                return [
                    'id' => $comp->id,
                    'product_id' => $comp->product_id,
                    'source' => $comp->source,
                    'price' => isset($comp->price) ? round((float) $comp->price, 2) : null,
                    'link' => $comp->product_link,
                    'product_link' => $comp->product_link,
                    'title' => $comp->product_title,
                    'product_title' => $comp->product_title,
                    'image' => $comp->image,
                    'rating' => $comp->rating !== null ? (float) $comp->rating : null,
                    'reviews' => $comp->reviews !== null ? (int) $comp->reviews : null,
                ];
            })->values()->all();
            $processedItem['lmp_entries_total'] = $mergedLmpEntries->count();

            $processedItem['is_parent_summary'] = false;
            $processedItems[] = $processedItem;
        }

        // Amazon-style parent summary rows: aggregate children by Parent group.
        // PARENT product_master SKUs themselves have 0 Shopify INV, so we build
        // synthetic "PARENT {group}" rows from child totals so they appear under
        // the default INV > 0 / REQ filters.
        $groupedByParent = collect($processedItems)->groupBy(function ($row) {
            return trim((string) ($row['Parent'] ?? ''));
        });

        $finalItems = [];
        foreach ($groupedByParent as $parent => $rows) {
            foreach ($rows as $row) {
                $finalItems[] = $row;
            }

            if ($parent === '') {
                continue;
            }

            $inv = (float) $rows->sum(fn ($r) => floatval($r['INV'] ?? 0));
            $ovL30 = (float) $rows->sum(fn ($r) => floatval($r['L30'] ?? 0));
            $b2cL30 = (float) $rows->sum(fn ($r) => floatval($r['B2B L30'] ?? 0));
            $views = (float) $rows->sum(fn ($r) => floatval($r['Views'] ?? 0));
            $profit = (float) $rows->sum(fn ($r) => floatval($r['Profit'] ?? 0));
            $sales = (float) $rows->sum(fn ($r) => floatval($r['Sales L30'] ?? 0));
            $adSpend = (float) $rows->sum(fn ($r) => floatval($r['googleSpent'] ?? 0));

            $childPrices = $rows->pluck('Price')->filter(fn ($p) => is_numeric($p) && $p > 0);
            $childAmzPrices = $rows->pluck('A Price')->filter(fn ($p) => is_numeric($p) && $p > 0);
            $gpftVals = $rows->pluck('GPFT%')->filter(fn ($v) => is_numeric($v));
            $roiVals = $rows->pluck('ROI%')->filter(fn ($v) => is_numeric($v));
            $nroiVals = $rows->pluck('NROI%')->filter(fn ($v) => is_numeric($v));

            // REQ if any child is REQ (so parents of listed children stay visible
            // under the default REQ filter); otherwise NR.
            $hasReqChild = $rows->contains(fn ($r) => ($r['nr_req'] ?? '') === 'REQ');
            $imageRow = $rows->first(fn ($r) => !empty($r['image_path']));
            $imagePath = is_array($imageRow) ? ($imageRow['image_path'] ?? null) : null;

            $finalItems[] = [
                '(Child) sku' => 'PARENT ' . $parent,
                'Parent' => $parent,
                'is_parent_summary' => true,
                'LP_productmaster' => '',
                'Ship_productmaster' => '',
                'INV' => $inv,
                'L30' => $ovL30,
                'B2B L30' => $b2cL30,
                'Views' => $views,
                'Price' => $childPrices->count() > 0 ? round($childPrices->avg(), 2) : 0,
                'A Price' => $childAmzPrices->count() > 0 ? round($childAmzPrices->avg(), 2) : 0,
                'image_path' => $imagePath,
                'Missing' => '',
                'nr_req' => $hasReqChild ? 'REQ' : 'NR',
                'B Link' => '',
                'S Link' => '',
                'GPFT%' => $gpftVals->count() > 0 ? round($gpftVals->avg(), 2) : 0,
                'ROI%' => $roiVals->count() > 0 ? round($roiVals->avg(), 2) : 0,
                'NROI%' => $nroiVals->count() > 0 ? round($nroiVals->avg(), 2) : 0,
                'Profit' => round($profit, 2),
                'Sales L30' => round($sales, 2),
                'DIL%' => $inv > 0 ? ($ovL30 / $inv) * 100 : 0,
                'CVR%' => $views > 0 ? ($b2cL30 / $views) * 100 : 0,
                'googleSpent' => $adSpend,
                'ADS%' => $sales > 0 ? ($adSpend / $sales) * 100 : 0,
                'SPRICE' => 0,
                'SGPFT' => 0,
                'SNPFT' => 0,
                'SROI' => 0,
                'SNROI' => 0,
                'SPRICE_STATUS' => null,
                'linked_lmp_skus' => [],
                'lmp_price' => null,
                'lmp_link' => null,
                'lmp_source' => null,
                'lmp_title' => null,
                'lmp_entries' => [],
                'lmp_entries_total' => 0,
            ];
        }

        return $finalItems;
    }

    /**
     * Sku Link LMP group for a Shopify B2C row — shared lmp_sku_links service.
     *
     * @return list<string>
     */
    private function shopifyB2cLinkedLmpSkusFor(LmpSkuGroupService $lmpGroupService, string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        try {
            $group = $lmpGroupService->groupContaining($sku);
        } catch (\Throwable $e) {
            $group = [];
        }

        $members = $group !== [] ? $group : [$sku];
        $seen = [];
        $out = [];
        foreach ($members as $member) {
            $display = trim((string) $member);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $display;
        }

        return $out;
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

    /**
     * Auto-save daily Shopify B2C summary snapshot when the pricing page loads.
     */
    private function saveDailySummaryIfNeeded($products)
    {
        $this->snapshotDailyBadgeSummary(is_array($products) ? $products : []);
    }

    /**
     * Store today's /shopify-b2c-pricing badge values in amazon_channel_summary_data
     * (same store as eBay / Amazon tabulator pages and the Active Channel history).
     *
     * @param  array<int, array<string, mixed>>|null  $products
     * @param  array<string, float|int>  $overlay  Live badge values from the page (triangles / LMP).
     * @return array<string, mixed>
     */
    public function snapshotDailyBadgeSummary(?array $products = null, array $overlay = []): array
    {
        try {
            $loadDirectSnapshot = $products === null || $overlay !== [];
            if ($products === null) {
                $products = $this->getViewShopifyB2cTabularData() ?? [];
            }

            $today = now('America/Los_Angeles')->toDateString();
            $direct = [];
            // Page-load / afterResponse already has row data. Skip the expensive
            // /shopify L30 snapshot here — cron + the live badge POST fill those.
            if ($loadDirectSnapshot) {
                try {
                    $direct = app(\App\Http\Controllers\Channels\ChannelMasterController::class)
                        ->getShopifyDirectL30Snapshot();
                } catch (\Throwable $e) {
                    Log::warning('Shopify B2C badge snapshot: direct L30 failed: ' . $e->getMessage());
                }
            }

            $children = collect($products)->filter(function ($p) {
                if (! empty($p['is_parent_summary'])) {
                    return false;
                }
                $sku = strtoupper(trim((string) ($p['(Child) sku'] ?? '')));

                return $sku === '' || ! str_contains($sku, 'PARENT');
            });

            $filteredData = $children->filter(function ($p) {
                return floatval($p['INV'] ?? 0) > 0 && ($p['nr_req'] ?? '') === 'REQ';
            });

            $totalSkuCount = $filteredData->count();
            $totalPft = 0;
            $totalSales = 0;
            $totalPrice = 0;
            $priceCount = 0;
            $totalInv = 0;
            $totalL30 = 0;
            $totalViews = 0;
            $totalB2BL30 = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $totalDil = 0;
            $dilCount = 0;
            $totalCogs = 0;
            $totalRoi = 0;
            $roiCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            $missingCount = 0;

            foreach ($filteredData as $row) {
                $totalPft += floatval($row['Profit'] ?? 0);
                $totalSales += floatval($row['Sales L30'] ?? 0);

                $price = floatval($row['Price'] ?? 0);
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                }

                $totalInv += floatval($row['INV'] ?? 0);
                $totalL30 += floatval($row['L30'] ?? 0);
                $totalViews += floatval($row['Views'] ?? 0);
                $totalB2BL30 += floatval($row['B2B L30'] ?? 0);

                $b2bL30 = floatval($row['B2B L30'] ?? 0);
                if ($b2bL30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }

                $dil = floatval($row['DIL%'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }

                $lp = floatval($row['LP_productmaster'] ?? 0);
                $totalCogs += $lp * $b2bL30;

                $roi = floatval($row['ROI%'] ?? 0);
                if ($roi != 0) {
                    $totalRoi += $roi;
                    $roiCount++;
                }

                $amzPrice = floatval($row['A Price'] ?? 0);
                if ($amzPrice > 0 && $price > 0) {
                    if ($price < $amzPrice) {
                        $lessAmzCount++;
                    } elseif ($price > $amzPrice) {
                        $moreAmzCount++;
                    }
                }

                if (($row['Missing'] ?? '') === 'M') {
                    $missingCount++;
                }
            }

            $lmpMissing = 0;
            $prcGtLmp = 0;
            $priceLt80 = 0;
            $blueTriangle = 0;
            $purpleTriangle = 0;
            foreach ($children as $row) {
                $price = floatval($row['Price'] ?? 0);
                $lmp = floatval($row['lmp_price'] ?? ($row['LMP'] ?? 0));
                if ($lmp <= 0) {
                    $lmpMissing++;
                } elseif ($price > 0) {
                    if ($price > $lmp) {
                        $prcGtLmp++;
                    } elseif ($price < ($lmp * 0.8)) {
                        $priceLt80++;
                    }
                }

                $sprice = floatval($row['SPRICE'] ?? 0);
                $amz = floatval($row['A Price'] ?? 0);
                if ($sprice > 0 && $price > 0 && round($sprice, 2) !== round($price, 2)) {
                    $blueTriangle++;
                }
                if ($sprice > 0 && $amz > 0 && $sprice < $amz) {
                    $purpleTriangle++;
                }
            }

            $avgPrice = $priceCount > 0 ? $totalPrice / $priceCount : 0;
            $avgGpftFromRows = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            $avgRoi = $roiCount > 0 ? $totalRoi / $roiCount : 0;
            $directQty = (int) ($direct['qty'] ?? 0);
            $cvr = $totalViews > 0 && $directQty > 0
                ? ($directQty / $totalViews) * 100
                : 0;

            $summaryData = [
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                'blue_triangle_count' => $blueTriangle,
                'purple_triangle_count' => $purpleTriangle,
                'lmp_missing_count' => $lmpMissing,
                'prc_gt_lmp_count' => $prcGtLmp,
                'price_lt80_lmp_count' => $priceLt80,

                'total_pft' => round((float) ($direct['total_pft'] ?? $totalPft), 2),
                'total_sales' => round((float) ($direct['l30_sales'] ?? $totalSales), 2),
                'total_orders' => (int) ($direct['l30_orders'] ?? 0),
                'total_qty' => $directQty,
                'total_cogs' => round((float) ($direct['total_cogs'] ?? $totalCogs), 2),
                'total_spend' => round((float) ($direct['total_ad_spend'] ?? 0), 2),

                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                'total_b2b_l30' => round($totalB2BL30, 2),
                'total_views' => (int) round($totalViews),
                'cvr_percent' => round($cvr, 2),

                'gpft_percent' => round((float) ($direct['gpft_pct'] ?? $avgGpftFromRows), 2),
                'groi_percent' => round((float) ($direct['groi_pct'] ?? ($totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : $avgRoi)), 2),
                'nroi_percent' => round((float) ($direct['nroi_pct'] ?? 0), 2),
                'npft_percent' => round((float) ($direct['npft_pct'] ?? 0), 2),
                'tcos_percent' => round((float) ($direct['tcos_pct'] ?? 0), 2),
                'avg_gpft' => round((float) ($direct['gpft_pct'] ?? $avgGpftFromRows), 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round((float) ($direct['groi_pct'] ?? $avgRoi), 2),
                'avg_price' => round($avgPrice, 2),

                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                'filters_applied' => [
                    'inventory' => 'more',
                    'nrl' => 'REQ',
                ],
            ];

            foreach ($overlay as $key => $value) {
                if (in_array($key, self::BADGE_METRICS, true) && is_numeric($value)) {
                    $summaryData[$key] = is_int($value) || ctype_digit((string) $value)
                        ? (int) $value
                        : round((float) $value, 2);
                }
            }

            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => self::BADGE_CHANNEL,
                    'snapshot_date' => $today,
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Daily badge snapshot (INV > 0, REQ) — /shopify-b2c-pricing',
                ]
            );

            Log::info("Daily Shopify B2C summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);

            return $summaryData;
        } catch (\Exception $e) {
            Log::error('Error saving daily Shopify B2C summary: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Merge live badge values from the pricing page into today's snapshot.
     */
    public function saveShopifyB2cBadgeStats(Request $request)
    {
        try {
            $today = now('America/Los_Angeles')->toDateString();
            $overlay = [];
            foreach (self::BADGE_METRICS as $key) {
                if ($request->has($key) && is_numeric($request->input($key))) {
                    $overlay[$key] = (float) $request->input($key);
                }
            }

            $existing = AmazonChannelSummary::where('channel', self::BADGE_CHANNEL)
                ->where('snapshot_date', $today)
                ->first();
            $summary = ($existing && is_array($existing->summary_data))
                ? $existing->summary_data
                : [];
            $summary = array_merge($summary, $overlay);
            $summary['calculated_at'] = now()->toDateTimeString();

            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => self::BADGE_CHANNEL,
                    'snapshot_date' => $today,
                ],
                [
                    'summary_data' => $summary,
                    'notes' => 'Daily badge snapshot (live page)',
                ]
            );

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('saveShopifyB2cBadgeStats error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error saving badge stats'], 500);
        }
    }

    /**
     * Daily snapshot series for Shopify B2C summary badges.
     */
    public function getShopifyB2cBadgeChartData(Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'total_sales');
            $days = intval($request->input('days', 30));

            if (! in_array($metric, self::BADGE_METRICS, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $cacheKey = 'shopify_b2c_badge_chart:' . $metric . ':' . $days;
            $chartData = Cache::remember($cacheKey, 60, function () use ($metric, $days) {
                $tz = 'America/Los_Angeles';
                $query = AmazonChannelSummary::where('channel', self::BADGE_CHANNEL)
                    ->orderBy('snapshot_date', 'asc');
                if ($days > 0) {
                    $query->where('snapshot_date', '>=', now($tz)->subDays(max(0, $days - 1))->toDateString());
                }

                $out = [];
                foreach ($query->get(['snapshot_date', 'summary_data']) as $row) {
                    $summary = is_array($row->summary_data)
                        ? $row->summary_data
                        : (json_decode($row->summary_data ?? '{}', true) ?: []);
                    $raw = $this->shopifyB2cMetricFromSummary($summary, $metric);
                    if ($raw === null) {
                        continue;
                    }
                    $d = Carbon::parse($row->snapshot_date)->toDateString();
                    $out[] = [
                        'date' => Carbon::parse($d)->format('M d'),
                        'full_date' => $d,
                        'value' => floatval($raw),
                    ];
                }

                return $out;
            });

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'metric' => $metric,
            ]);
        } catch (\Throwable $e) {
            Log::error('getShopifyB2cBadgeChartData error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Previous-day Shopify B2C badge metrics for 3-color trend dots.
     */
    public function getShopifyB2cBadgePrevDay(Request $request)
    {
        try {
            $today = now('America/Los_Angeles')->toDateString();
            $payload = Cache::remember('shopify_b2c_badge_prev_day:' . $today, 60, function () use ($today) {
                $row = AmazonChannelSummary::where('channel', self::BADGE_CHANNEL)
                    ->where('snapshot_date', '<', $today)
                    ->orderBy('snapshot_date', 'desc')
                    ->first();

                if (! $row) {
                    return ['date' => null, 'metrics' => null];
                }

                $s = is_array($row->summary_data)
                    ? $row->summary_data
                    : (json_decode($row->summary_data ?? '{}', true) ?: []);

                $metrics = [];
                foreach (self::BADGE_METRICS as $key) {
                    $raw = $this->shopifyB2cMetricFromSummary($s, $key);
                    $metrics[$key] = $raw === null ? null : floatval($raw);
                }

                return [
                    'date' => Carbon::parse($row->snapshot_date)->toDateString(),
                    'metrics' => $metrics,
                ];
            });

            return response()->json([
                'success' => true,
                'date' => $payload['date'] ?? null,
                'metrics' => $payload['metrics'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('getShopifyB2cBadgePrevDay error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching previous day'], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function shopifyB2cMetricFromSummary(array $summary, string $metric): ?float
    {
        $aliases = [
            'gpft_percent' => ['gpft_percent', 'avg_gpft'],
            'groi_percent' => ['groi_percent', 'avg_roi'],
            'total_sales' => ['total_sales', 'total_sales_amt'],
            'total_qty' => ['total_qty'],
            'total_orders' => ['total_orders'],
            'tcos_percent' => ['tcos_percent'],
        ];
        $keys = $aliases[$metric] ?? [$metric];
        foreach ($keys as $key) {
            if (array_key_exists($key, $summary) && $summary[$key] !== null && $summary[$key] !== '') {
                return floatval($summary[$key]);
            }
        }

        return null;
    }
}
