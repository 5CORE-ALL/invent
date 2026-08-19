<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuDataView;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use App\Models\TemuDailyData;
use App\Models\TemuDailyDataL60;
use App\Models\TemuDailyDataL7;
use App\Models\Temu2DailyData;
use App\Models\Temu2DailyDataL60;
use App\Models\Temu2DailyDataL7;
use App\Models\TemuPricing;
use App\Models\Temu2Pricing;
use App\Models\Temu2Metric;
use App\Models\Temu2DataView;
use App\Models\TemuViewData;
use App\Models\TemuAdsView;
use App\Models\TemuViewDataL7;
use App\Models\TemuViewDataL7ToL14;
use App\Models\Temu2ViewData;
use App\Models\TemuAdsApiReport;
use App\Models\TemuAdData;
use App\Models\TemuLmp;
use App\Models\TemuListingStatus;
use App\Models\TemuCampaignReport;
use App\Models\Temu2CampaignReport;
use App\Services\TemuShopifySalesService;
use App\Services\TemuApiService;
use App\Services\Temu2ApiService;
use App\Services\TemuSellerViewScraperService;
use App\Models\TemuBadgeDailyData;
use App\Models\EbayMetric;
use App\Models\Ebay2Metric;
use App\Models\MarketplaceDailyMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use App\Models\AmazonChannelSummary;
use App\Support\ProductMasterTemuShip;
use App\Support\TemuGoodsIdHelper;
use App\Services\ChannelPromoPricingService;
use App\Services\LmpSkuGroupService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class TemuController extends Controller
{
    protected $apiController;

    public function __construct(
        ApiController $apiController,
        private LmpSkuGroupService $lmpSkuGroupService
    ) {
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

        // Get percentage from ChannelMaster
        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

        return view('market-places.temu-cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewTemuData(Request $request)
    {
        try {
            $days = $request->input('days', 30);
            $dateFrom = Carbon::now()->subDays($days)->startOfDay();
            
            // Daily rollup from temu_orders (sheet temu_daily_data dropped)
            $dailyData = collect();
            if (Schema::hasTable('temu_orders')) {
                $dailyData = DB::table('temu_orders')
                    ->where('purchase_date', '>=', $dateFrom)
                    ->select(
                        'contribution_sku',
                        DB::raw('COUNT(DISTINCT order_id) as total_orders'),
                        DB::raw('SUM(quantity_purchased) as total_quantity_purchased'),
                        DB::raw('SUM(quantity_shipped) as total_quantity_shipped'),
                        DB::raw('SUM(quantity_to_ship) as total_quantity_to_ship'),
                        DB::raw('SUM(base_price_total) as total_revenue'),
                        DB::raw('AVG(base_price_total) as avg_price'),
                        DB::raw('MAX(purchase_date) as last_order_date'),
                        DB::raw('MIN(purchase_date) as first_order_date')
                    )
                    ->groupBy('contribution_sku')
                    ->get()
                    ->keyBy('contribution_sku');
            }

            // Fetch all product master records
            $productMasterRows = ProductMaster::all()->keyBy('sku');

            // Get all unique SKUs from product master
            $skus = $productMasterRows->pluck('sku')->toArray();

            // Fetch shopify data for these SKUs
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            // Fetch NR values from temu_data_view
            $temuDataViews = TemuDataView::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Get Amazon pricing data
            $amazonData = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');

            // Get marketplace percentage from marketplace_percentages table
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            $percentage = $marketplaceData ? $marketplaceData->percentage : 87;
            $percentageValue = $percentage / 100;

            // Process data
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
                    'R&A' => false,
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

                // Add data from daily data if available
                if (isset($dailyData[$sku])) {
                    $daily = $dailyData[$sku];
                    $processedItem['total_orders'] = $daily->total_orders ?? 0;
                    $processedItem['sales_l30'] = $daily->total_quantity_purchased ?? 0;
                    $processedItem['quantity_shipped'] = $daily->total_quantity_shipped ?? 0;
                    $processedItem['quantity_to_ship'] = $daily->total_quantity_to_ship ?? 0;
                    $processedItem['total_revenue'] = $daily->total_revenue ?? 0;
                    $processedItem['price'] = $daily->avg_price ?? 0;
                    $processedItem['last_order_date'] = $daily->last_order_date;
                    
                    // Calculate views and clicks (you may want to adjust these)
                    $processedItem['views_l30'] = 0;
                    $processedItem['clicks_l30'] = 0;
                    
                    // Calculate CVR if you have clicks data
                    $clicks = $processedItem['clicks_l30'];
                    $sales = $processedItem['sales_l30'];
                    $processedItem['CVR'] = ($clicks > 0) ? ($sales / $clicks) : 0;
                } else {
                    $processedItem['total_orders'] = 0;
                    $processedItem['sales_l30'] = 0;
                    $processedItem['quantity_shipped'] = 0;
                    $processedItem['quantity_to_ship'] = 0;
                    $processedItem['total_revenue'] = 0;
                    $processedItem['price'] = 0;
                    $processedItem['last_order_date'] = null;
                    $processedItem['views_l30'] = 0;
                    $processedItem['clicks_l30'] = 0;
                    $processedItem['CVR'] = 0;
                }

                $processedItem['SOLD'] = $processedItem['sales_l30'];

                // Add NR, Listed and Live values from temu_data_view if available
                if (isset($temuDataViews[$sku])) {
                    $viewData = $temuDataViews[$sku];
                    $valuesArr = is_array($viewData->value) ? $viewData->value : (json_decode($viewData->value, true) ?: []);
                    $processedItem['NR'] = $valuesArr['NR'] ?? 'REQ';
                    $processedItem['Listed'] = isset($valuesArr['Listed']) ? (bool)$valuesArr['Listed'] : false;
                    $processedItem['Live'] = isset($valuesArr['Live']) ? (bool)$valuesArr['Live'] : false;
                    $processedItem['SPRICE'] = isset($valuesArr['SPRICE']) ? (float)$valuesArr['SPRICE'] : 0;
                    $processedItem['SPFT'] = isset($valuesArr['SPFT']) ? (float)$valuesArr['SPFT'] : 0;
                    $processedItem['SROI'] = isset($valuesArr['SROI']) ? (float)$valuesArr['SROI'] : 0;
                    $processedItem['SHIP'] = isset($valuesArr['SHIP']) ? (float)$valuesArr['SHIP'] : 0;
                } else {
                    $processedItem['NR'] = 'REQ';
                    $processedItem['Listed'] = false;
                    $processedItem['Live'] = false;
                    $processedItem['SPRICE'] = 0;
                    $processedItem['SPFT'] = 0;
                    $processedItem['SROI'] = 0;
                    $processedItem['SHIP'] = 0;
                }

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
        } catch (\Exception $e) {
            Log::error('Error fetching Temu data: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
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

            $channelData = ChannelMaster::where('channel', 'Temu')->first();
            $percent = $channelData ? $channelData->channel_percentage : 100;
            $adUpdates = $channelData ? $channelData->ad_updates : 100;

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
            $channel = ChannelMaster::updateOrCreate(
                ['channel' => 'Temu'],
                [
                    'channel_percentage' => $percent,
                    'ad_updates' => $adUpdates
                ]
            );

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully',
                'data' => [
                    'channel' => 'Temu',
                    'percentage' => $channel->channel_percentage,
                    'ad_updates' => $channel->ad_updates
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
    
    public function saveListingStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string|in:REQ,NRL,NR',
            'listed' => 'nullable|string|in:Listed,Pending',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = TemuListingStatus::where('sku', $sku)->first();

        $rawValue = $status ? $status->getRawOriginal('value') : null;
        $existing = $status ? $status->value : [];
        if (!is_array($existing)) {
            $existing = is_string($rawValue) && $rawValue !== ''
                ? (json_decode($rawValue, true) ?: [])
                : [];
        }

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                // Normalize NR to NRL for consistency
                if ($field === 'nr_req' && isset($validated[$field]) && $validated[$field] === 'NR') {
                    $existing[$field] = 'NRL';
                } else {
                    $existing[$field] = $validated[$field];
                }
            }
        }

        TemuListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'NR/REQ updated successfully',
            'nr_req' => $existing['nr_req'] ?? null,
        ]);
    }

    /** Save Buyer (B) / Seller (S) links for a SKU into temu_listing_statuses.value JSON (preserves nr_req etc.). */
    public function saveTemuDecreaseLinks(Request $request)
    {
        $validated = $request->validate([
            'sku'         => 'required|string',
            'buyer_link'  => 'nullable|string|max:1000',
            'seller_link' => 'nullable|string|max:1000',
        ]);

        $sku = trim($validated['sku']);

        $buyerLink  = isset($validated['buyer_link']) ? trim((string) $validated['buyer_link']) : '';
        $sellerLink = isset($validated['seller_link']) ? trim((string) $validated['seller_link']) : '';

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $label => $link) {
            if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $label)) . ' must be a valid URL.',
                ], 422);
            }
        }

        $status   = TemuListingStatus::where('sku', $sku)->first();
        $rawValue = $status ? $status->getRawOriginal('value') : null;
        $existing = $status ? $status->value : [];
        if (!is_array($existing)) {
            $existing = is_string($rawValue) && $rawValue !== ''
                ? (json_decode($rawValue, true) ?: [])
                : [];
        }

        $existing['buyer_link']  = $buyerLink;
        $existing['seller_link'] = $sellerLink;

        TemuListingStatus::updateOrCreate(
            ['sku' => $sku],
            ['value' => $existing]
        );

        return response()->json([
            'success'     => true,
            'message'     => 'Links saved.',
            'buyer_link'  => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    /**
     * Temu 2: persist nr_req / listed / links inside temu2_data_view.value (same JSON as SPRICE).
     */
    public function saveTemu2ListingFieldsToDataView(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string|in:REQ,NRL,NR',
            'listed' => 'nullable|string|in:Listed,Pending',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = trim((string) $validated['sku']);

        $row = Temu2DataView::firstOrNew(['sku' => $sku]);
        $row->sku = $sku;

        $existing = is_array($row->value)
            ? $row->value
            : (is_string($row->value) ? json_decode($row->value, true) : []);
        if (!is_array($existing)) {
            $raw = $row->getRawOriginal('value');
            $existing = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        }
        if (!is_array($existing)) {
            $existing = [];
        }

        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                if ($field === 'nr_req' && isset($validated[$field]) && $validated[$field] === 'NR') {
                    $existing[$field] = 'NRL';
                } else {
                    $existing[$field] = $validated[$field];
                }
            }
        }

        $row->value = $existing;
        $row->save();

        return response()->json([
            'status' => 'success',
            'message' => 'NR/REQ updated (temu2_data_view)',
            'nr_req' => $existing['nr_req'] ?? null,
        ]);
    }

    /** Temu 2: save Buyer (B) / Seller (S) links into temu2_data_view.value JSON (preserves nr_req etc.). */
    public function saveTemu2DecreaseLinks(Request $request)
    {
        $validated = $request->validate([
            'sku'         => 'required|string',
            'buyer_link'  => 'nullable|string|max:1000',
            'seller_link' => 'nullable|string|max:1000',
        ]);

        $sku = trim((string) $validated['sku']);

        $buyerLink  = isset($validated['buyer_link']) ? trim((string) $validated['buyer_link']) : '';
        $sellerLink = isset($validated['seller_link']) ? trim((string) $validated['seller_link']) : '';

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $label => $link) {
            if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $label)) . ' must be a valid URL.',
                ], 422);
            }
        }

        $row = Temu2DataView::firstOrNew(['sku' => $sku]);
        $row->sku = $sku;

        $existing = is_array($row->value)
            ? $row->value
            : (is_string($row->value) ? json_decode($row->value, true) : []);
        if (!is_array($existing)) {
            $raw = $row->getRawOriginal('value');
            $existing = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        }
        if (!is_array($existing)) {
            $existing = [];
        }

        $existing['buyer_link']  = $buyerLink;
        $existing['seller_link'] = $sellerLink;

        $row->value = $existing;
        $row->save();

        return response()->json([
            'success'     => true,
            'message'     => 'Links saved.',
            'buyer_link'  => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
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

        // Get percentage from ChannelMaster
        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

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

        // Get percentage from ChannelMaster
        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

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

    public function uploadDailyDataChunk(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Temu daily data sheet upload has been removed. Use temu_orders (API).',
        ], 410);
    }

    /**
     * Upload L60 sales daily data (same format as L30, stored in temu_daily_data_l60).
     */
    public function uploadDailyDataL60Chunk(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Temu L60 daily data sheet upload has been removed. Use temu_orders (API).',
        ], 410);
    }

    /**
     * Upload L7 sales daily data (same format as L30, stored in temu_daily_data_l7).
     */
    public function uploadDailyDataL7Chunk(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Temu L7 daily data sheet upload has been removed. Use temu_orders (API).',
        ], 410);
    }

    /**
     * Upload Temu 2 L30 daily data (same format as Temu, stored in temu2_daily_data).
     */
    public function uploadDailyDataTemu2Chunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);
            $file = $request->file('file');
            $chunk = $request->input('chunk');
            $totalChunks = $request->input('totalChunks');
            $uploadId = $request->input('uploadId', uniqid('temu2_'));
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }
            $fileName = $uploadId . '_' . $file->getClientOriginalName();
            $filePath = $tempPath . '/' . $fileName;
            if ($chunk == 0) {
                $file->move($tempPath, $fileName);
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                Temu2DailyData::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                Log::info('Temu 2 daily data table truncated before import');
            }
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            $rawHeaders = $rows[0];
            $headers = [];
            foreach ($rawHeaders as $index => $header) {
                $headers[] = $this->normalizeHeader($header);
            }
            unset($rows[0]);
            $totalRows = count($rows);
            $chunkSize = ceil($totalRows / $totalChunks);
            $startRow = $chunk * $chunkSize;
            $endRow = min(($chunk + 1) * $chunkSize, $totalRows);
            $chunkRows = array_slice($rows, $startRow, $endRow - $startRow, true);
            $imported = 0;
            $skipped = 0;
            DB::beginTransaction();
            try {
                foreach ($chunkRows as $index => $row) {
                    if (empty($row[0])) {
                        $skipped++;
                        continue;
                    }
                    $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                    $data = array_combine($headers, $rowData);
                    $insertData = [
                        'order_id' => isset($data['order_id']) && $data['order_id'] !== '' ? trim($data['order_id']) : null,
                        'order_status' => isset($data['order_status']) && $data['order_status'] !== '' ? trim($data['order_status']) : null,
                        'fulfillment_mode' => isset($data['fulfillment_mode']) && $data['fulfillment_mode'] !== '' ? trim($data['fulfillment_mode']) : null,
                        'logistics_service_suggestion' => isset($data['logistics_service_suggestion']) && $data['logistics_service_suggestion'] !== '' ? trim($data['logistics_service_suggestion']) : null,
                        'order_item_id' => isset($data['order_item_id']) && $data['order_item_id'] !== '' ? trim($data['order_item_id']) : null,
                        'order_item_status' => isset($data['order_item_status']) && $data['order_item_status'] !== '' ? trim($data['order_item_status']) : null,
                        'product_name_by_customer_order' => isset($data['product_name_by_customer_order']) && $data['product_name_by_customer_order'] !== '' ? trim($data['product_name_by_customer_order']) : null,
                        'product_name' => isset($data['product_name']) && $data['product_name'] !== '' ? trim($data['product_name']) : null,
                        'variation' => isset($data['variation']) && $data['variation'] !== '' ? trim($data['variation']) : null,
                        'contribution_sku' => isset($data['contribution_sku']) && $data['contribution_sku'] !== '' ? trim($data['contribution_sku']) : null,
                        'sku_id' => isset($data['sku_id']) && $data['sku_id'] !== '' ? trim($data['sku_id']) : null,
                        'quantity_purchased' => isset($data['quantity_purchased']) && $data['quantity_purchased'] !== '' ? (int)$data['quantity_purchased'] : null,
                        'quantity_shipped' => isset($data['quantity_shipped']) && $data['quantity_shipped'] !== '' ? (int)$data['quantity_shipped'] : null,
                        'quantity_to_ship' => isset($data['quantity_to_ship']) && $data['quantity_to_ship'] !== '' ? (int)$data['quantity_to_ship'] : null,
                        'recipient_name' => isset($data['recipient_name']) && $data['recipient_name'] !== '' ? trim($data['recipient_name']) : null,
                        'recipient_first_name' => isset($data['recipient_first_name']) && $data['recipient_first_name'] !== '' ? trim($data['recipient_first_name']) : null,
                        'recipient_last_name' => isset($data['recipient_last_name']) && $data['recipient_last_name'] !== '' ? trim($data['recipient_last_name']) : null,
                        'recipient_phone_number' => isset($data['recipient_phone_number']) && $data['recipient_phone_number'] !== '' ? trim($data['recipient_phone_number']) : null,
                        'ship_address_1' => isset($data['ship_address_1']) && $data['ship_address_1'] !== '' ? trim($data['ship_address_1']) : null,
                        'ship_address_2' => isset($data['ship_address_2']) && $data['ship_address_2'] !== '' ? trim($data['ship_address_2']) : null,
                        'ship_address_3' => isset($data['ship_address_3']) && $data['ship_address_3'] !== '' ? trim($data['ship_address_3']) : null,
                        'district' => isset($data['district']) && $data['district'] !== '' ? trim($data['district']) : null,
                        'ship_city' => isset($data['ship_city']) && $data['ship_city'] !== '' ? trim($data['ship_city']) : null,
                        'ship_state' => isset($data['ship_state']) && $data['ship_state'] !== '' ? trim($data['ship_state']) : null,
                        'ship_postal_code' => isset($data['ship_postal_code']) && $data['ship_postal_code'] !== '' ? trim($data['ship_postal_code']) : null,
                        'ship_country' => isset($data['ship_country']) && $data['ship_country'] !== '' ? trim($data['ship_country']) : null,
                        'purchase_date' => isset($data['purchase_date']) ? $this->parseDate($data['purchase_date']) : null,
                        'latest_shipping_time' => isset($data['latest_shipping_time']) ? $this->parseDate($data['latest_shipping_time']) : null,
                        'latest_delivery_time' => isset($data['latest_delivery_time']) ? $this->parseDate($data['latest_delivery_time']) : null,
                        'iphone_serial_number' => isset($data['iphone_serial_number']) && $data['iphone_serial_number'] !== '' ? trim($data['iphone_serial_number']) : null,
                        'virtual_email' => isset($data['virtual_email']) && $data['virtual_email'] !== '' ? trim($data['virtual_email']) : null,
                        'activity_goods_base_price' => isset($data['activity_goods_base_price']) ? $this->sanitizePrice($data['activity_goods_base_price']) : null,
                        'base_price_total' => isset($data['base_price_total']) ? $this->sanitizePrice($data['base_price_total']) : null,
                        'tracking_number' => isset($data['tracking_number']) && $data['tracking_number'] !== '' ? trim($data['tracking_number']) : null,
                        'carrier' => isset($data['carrier']) && $data['carrier'] !== '' ? trim($data['carrier']) : null,
                        'order_settlement_status' => isset($data['order_settlement_status']) && $data['order_settlement_status'] !== '' ? trim($data['order_settlement_status']) : null,
                        'keep_proof_of_shipment_before_delivery' => isset($data['keep_proof_of_shipment_before_delivery']) && $data['keep_proof_of_shipment_before_delivery'] !== '' ? trim($data['keep_proof_of_shipment_before_delivery']) : null,
                    ];
                    Temu2DailyData::create($insertData);
                    $imported++;
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            if ($chunk == $totalChunks - 1 && file_exists($filePath)) {
                unlink($filePath);
            }
            if ($chunk == $totalChunks - 1) {
                $this->refreshTemuMetricsAfterDailyUpload(true);
            }
            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk processed successfully",
                'chunk' => $chunk,
                'totalChunks' => $totalChunks,
                'imported' => $imported,
                'skipped' => $skipped,
                'progress' => round((($chunk + 1) / $totalChunks) * 100, 2)
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading Temu 2 daily data chunk: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload Temu 2 L60 sales daily data (same format, stored in temu2_daily_data_l60).
     */
    public function uploadDailyDataTemu2L60Chunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);
            $file = $request->file('file');
            $chunk = (int) $request->input('chunk');
            $totalChunks = (int) $request->input('totalChunks');
            $uploadId = $request->input('uploadId', uniqid('temu2_l60_'));
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }
            $fileName = $uploadId . '_' . $file->getClientOriginalName();
            $filePath = $tempPath . '/' . $fileName;
            if ($chunk == 0) {
                $file->move($tempPath, $fileName);
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                Temu2DailyDataL60::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                Log::info('Temu 2 L60 daily data table truncated before import');
            }
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            $rawHeaders = $rows[0];
            $headers = [];
            foreach ($rawHeaders as $index => $header) {
                $headers[] = $this->normalizeHeader($header);
            }
            unset($rows[0]);
            $totalRows = count($rows);
            $chunkSize = ceil($totalRows / $totalChunks);
            $startRow = $chunk * $chunkSize;
            $endRow = min(($chunk + 1) * $chunkSize, $totalRows);
            $chunkRows = array_slice($rows, $startRow, $endRow - $startRow, true);
            $imported = 0;
            $skipped = 0;
            DB::beginTransaction();
            try {
                foreach ($chunkRows as $index => $row) {
                    if (empty($row[0])) {
                        $skipped++;
                        continue;
                    }
                    $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                    $data = array_combine($headers, $rowData);
                    $insertData = [
                        'order_id' => isset($data['order_id']) && $data['order_id'] !== '' ? trim($data['order_id']) : null,
                        'order_status' => isset($data['order_status']) && $data['order_status'] !== '' ? trim($data['order_status']) : null,
                        'fulfillment_mode' => isset($data['fulfillment_mode']) && $data['fulfillment_mode'] !== '' ? trim($data['fulfillment_mode']) : null,
                        'logistics_service_suggestion' => isset($data['logistics_service_suggestion']) && $data['logistics_service_suggestion'] !== '' ? trim($data['logistics_service_suggestion']) : null,
                        'order_item_id' => isset($data['order_item_id']) && $data['order_item_id'] !== '' ? trim($data['order_item_id']) : null,
                        'order_item_status' => isset($data['order_item_status']) && $data['order_item_status'] !== '' ? trim($data['order_item_status']) : null,
                        'product_name_by_customer_order' => isset($data['product_name_by_customer_order']) && $data['product_name_by_customer_order'] !== '' ? trim($data['product_name_by_customer_order']) : null,
                        'product_name' => isset($data['product_name']) && $data['product_name'] !== '' ? trim($data['product_name']) : null,
                        'variation' => isset($data['variation']) && $data['variation'] !== '' ? trim($data['variation']) : null,
                        'contribution_sku' => isset($data['contribution_sku']) && $data['contribution_sku'] !== '' ? trim($data['contribution_sku']) : null,
                        'sku_id' => isset($data['sku_id']) && $data['sku_id'] !== '' ? trim($data['sku_id']) : null,
                        'quantity_purchased' => isset($data['quantity_purchased']) && $data['quantity_purchased'] !== '' ? (int)$data['quantity_purchased'] : null,
                        'quantity_shipped' => isset($data['quantity_shipped']) && $data['quantity_shipped'] !== '' ? (int)$data['quantity_shipped'] : null,
                        'quantity_to_ship' => isset($data['quantity_to_ship']) && $data['quantity_to_ship'] !== '' ? (int)$data['quantity_to_ship'] : null,
                        'recipient_name' => isset($data['recipient_name']) && $data['recipient_name'] !== '' ? trim($data['recipient_name']) : null,
                        'recipient_first_name' => isset($data['recipient_first_name']) && $data['recipient_first_name'] !== '' ? trim($data['recipient_first_name']) : null,
                        'recipient_last_name' => isset($data['recipient_last_name']) && $data['recipient_last_name'] !== '' ? trim($data['recipient_last_name']) : null,
                        'recipient_phone_number' => isset($data['recipient_phone_number']) && $data['recipient_phone_number'] !== '' ? trim($data['recipient_phone_number']) : null,
                        'ship_address_1' => isset($data['ship_address_1']) && $data['ship_address_1'] !== '' ? trim($data['ship_address_1']) : null,
                        'ship_address_2' => isset($data['ship_address_2']) && $data['ship_address_2'] !== '' ? trim($data['ship_address_2']) : null,
                        'ship_address_3' => isset($data['ship_address_3']) && $data['ship_address_3'] !== '' ? trim($data['ship_address_3']) : null,
                        'district' => isset($data['district']) && $data['district'] !== '' ? trim($data['district']) : null,
                        'ship_city' => isset($data['ship_city']) && $data['ship_city'] !== '' ? trim($data['ship_city']) : null,
                        'ship_state' => isset($data['ship_state']) && $data['ship_state'] !== '' ? trim($data['ship_state']) : null,
                        'ship_postal_code' => isset($data['ship_postal_code']) && $data['ship_postal_code'] !== '' ? trim($data['ship_postal_code']) : null,
                        'ship_country' => isset($data['ship_country']) && $data['ship_country'] !== '' ? trim($data['ship_country']) : null,
                        'purchase_date' => isset($data['purchase_date']) ? $this->parseDate($data['purchase_date']) : null,
                        'latest_shipping_time' => isset($data['latest_shipping_time']) ? $this->parseDate($data['latest_shipping_time']) : null,
                        'latest_delivery_time' => isset($data['latest_delivery_time']) ? $this->parseDate($data['latest_delivery_time']) : null,
                        'iphone_serial_number' => isset($data['iphone_serial_number']) && $data['iphone_serial_number'] !== '' ? trim($data['iphone_serial_number']) : null,
                        'virtual_email' => isset($data['virtual_email']) && $data['virtual_email'] !== '' ? trim($data['virtual_email']) : null,
                        'activity_goods_base_price' => isset($data['activity_goods_base_price']) ? $this->sanitizePrice($data['activity_goods_base_price']) : null,
                        'base_price_total' => isset($data['base_price_total']) ? $this->sanitizePrice($data['base_price_total']) : null,
                        'tracking_number' => isset($data['tracking_number']) && $data['tracking_number'] !== '' ? trim($data['tracking_number']) : null,
                        'carrier' => isset($data['carrier']) && $data['carrier'] !== '' ? trim($data['carrier']) : null,
                        'order_settlement_status' => isset($data['order_settlement_status']) && $data['order_settlement_status'] !== '' ? trim($data['order_settlement_status']) : null,
                        'keep_proof_of_shipment_before_delivery' => isset($data['keep_proof_of_shipment_before_delivery']) && $data['keep_proof_of_shipment_before_delivery'] !== '' ? trim($data['keep_proof_of_shipment_before_delivery']) : null,
                    ];
                    Temu2DailyDataL60::create($insertData);
                    $imported++;
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            if ($chunk == $totalChunks - 1 && file_exists($filePath)) {
                unlink($filePath);
            }
            if ($chunk == $totalChunks - 1) {
                $this->refreshTemuMetricsAfterDailyUpload(true);
            }
            return response()->json([
                'success' => true,
                'message' => "L60 chunk $chunk processed successfully",
                'chunk' => $chunk,
                'totalChunks' => $totalChunks,
                'imported' => $imported,
                'skipped' => $skipped,
                'progress' => round((($chunk + 1) / $totalChunks) * 100, 2)
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading Temu 2 L60 daily data chunk: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function normalizeHeader($header)
    {
        // Store original for logging
        $original = $header;
        
        // Convert header to snake_case
        $headerLower = strtolower(trim($header));
        
        // Remove special characters and convert to underscores
        $headerNormalized = preg_replace('/[^a-z0-9_]/', '_', $headerLower);
        $headerNormalized = preg_replace('/_+/', '_', $headerNormalized);
        $headerNormalized = trim($headerNormalized, '_');
        
        // Map specific headers (including common variations)
        $mapping = [
            // Order fields
            'order_id' => 'order_id',
            'order_status' => 'order_status',
            'fulfillment_mode' => 'fulfillment_mode',
            'logistics_service_suggestion' => 'logistics_service_suggestion',
            
            // Order item fields
            'order_item_id' => 'order_item_id',
            'order_item_status' => 'order_item_status',
            
            // Product fields
            'product_name_by_customer_order' => 'product_name_by_customer_order',
            'product_name' => 'product_name',
            'variation' => 'variation',
            
            // SKU fields
            'contribution_sku' => 'contribution_sku',
            'sku_id' => 'sku_id',
            
            // Quantity fields
            'quantity_purchased' => 'quantity_purchased',
            'quantity_shipped' => 'quantity_shipped',
            'quantity_to_ship' => 'quantity_to_ship',
            
            // Recipient fields
            'recipient_name' => 'recipient_name',
            'recipient_first_name' => 'recipient_first_name',
            'recipient_last_name' => 'recipient_last_name',
            'recipient_phone_number' => 'recipient_phone_number',
            
            // Address fields
            'ship_address_1' => 'ship_address_1',
            'ship_address_2' => 'ship_address_2',
            'ship_address_3' => 'ship_address_3',
            'district' => 'district',
            'ship_city' => 'ship_city',
            'ship_state' => 'ship_state',
            'ship_postal_code' => 'ship_postal_code',
            'ship_postal_code_must_be_shipped_to_the_following_zip_code_' => 'ship_postal_code',
            'ship_country' => 'ship_country',
            
            // Date fields (including UTC variations)
            'purchase_date' => 'purchase_date',
            'purchase_date_utc_0_' => 'purchase_date',
            'purchase_date_utc_8_' => 'purchase_date',
            'latest_shipping_time' => 'latest_shipping_time',
            'latest_shipping_time_utc_0_' => 'latest_shipping_time',
            'latest_shipping_time_utc_8_' => 'latest_shipping_time',
            'latest_delivery_time' => 'latest_delivery_time',
            'latest_delivery_time_utc_0_' => 'latest_delivery_time',
            'latest_delivery_time_utc_8_' => 'latest_delivery_time',
            
            // Other fields
            'iphone_serial_number' => 'iphone_serial_number',
            'virtual_email' => 'virtual_email',
            'activity_goods_base_price' => 'activity_goods_base_price',
            'goods_base_price' => 'base_price_total',  // "goods base price" column maps to base_price_total
            'base_price_total' => 'base_price_total',
            'tracking_number' => 'tracking_number',
            'carrier' => 'carrier',
            'order_settlement_status' => 'order_settlement_status',
            'keep_proof_of_shipment_before_delivery' => 'keep_proof_of_shipment_before_delivery',
        ];

        $result = $mapping[$headerNormalized] ?? $headerNormalized;
        
        // Log if header is not in mapping (for debugging new CSV formats)
        if (!isset($mapping[$headerNormalized])) {
            Log::info("Temu Upload - Header normalized: '$original' -> '$headerNormalized' -> '$result'");
        }
        
        return $result;
    }

    /**
     * Sanitize price values by removing currency symbols and converting to decimal
     */
    private function sanitizePrice($value)
    {
        if ($value === null || $value === '' || $value === '?') {
            return null;
        }

        // Remove currency symbols, commas, and whitespace
        $cleaned = preg_replace('/[$,\s]/', '', (string) $value);

        // Return as float or null if not numeric
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function parseDate($dateString)
    {
        if (empty($dateString) || $dateString === null || $dateString === '') {
            return null;
        }

        try {
            // Clean up the date string - remove timezone info like IST(UTC+5)
            $dateString = trim($dateString);
            $dateString = preg_replace('/\s+IST\(UTC[+-]\d+\)\s*$/', '', $dateString);
            $dateString = preg_replace('/\s+UTC[+-]\d+\s*$/', '', $dateString);
            $dateString = trim($dateString);
            
            // Check if it's an Excel numeric date (like 45321.5)
            if (is_numeric($dateString)) {
                // Excel dates are days since 1900-01-01
                $excelEpoch = Carbon::create(1900, 1, 1)->subDays(2); // Excel has a bug, needs -2 adjustment
                return $excelEpoch->copy()->addDays($dateString);
            }

            // Try parsing various date formats (including Temu format: Dec 9, 2025, 4:20 am)
            $formats = [
                'M j, Y, g:i a',      // Dec 9, 2025, 4:20 am (Temu format)
                'M d, Y, g:i a',      // Dec 09, 2025, 4:20 am
                'M j, Y g:i a',       // Dec 9, 2025 4:20 am
                'Y-m-d H:i:s',
                'Y-m-d',
                'm/d/Y H:i:s',
                'm/d/Y',
                'd/m/Y H:i:s',
                'd/m/Y',
                'M d, Y H:i:s',
                'M d, Y',
                'Y/m/d H:i:s',
                'Y/m/d',
                'd-m-Y H:i:s',
                'd-m-Y',
            ];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, trim($dateString));
                    if ($date !== false && !$date->hasErrors()) {
                        return $date;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // If all else fails, try Carbon's parse method
            $parsed = Carbon::parse($dateString);
            return $parsed;
        } catch (\Exception $e) {
            Log::warning("Could not parse date: '$dateString' - Error: " . $e->getMessage());
            return null;
        }
    }

    public function downloadDailyDataSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row - All columns from migration
        $headers = [
            'Order ID', 'Order Status', 'Fulfillment Mode', 'Logistics Service Suggestion',
            'Order Item ID', 'Order Item Status', 'Product Name by Customer Order', 'Product Name',
            'Variation', 'Contribution SKU', 'SKU ID', 'Quantity Purchased', 'Quantity Shipped',
            'Quantity to Ship', 'Recipient Name', 'Recipient First Name', 'Recipient Last Name',
            'Recipient Phone Number', 'Ship Address 1', 'Ship Address 2', 'Ship Address 3',
            'District', 'Ship City', 'Ship State', 'Ship Postal Code', 'Ship Country',
            'Purchase Date', 'Latest Shipping Time', 'Latest Delivery Time', 'iPhone Serial Number',
            'Virtual Email', 'Activity Goods Base Price', 'Base Price Total', 'Tracking Number',
            'Carrier', 'Order Settlement Status', 'Keep Proof of Shipment Before Delivery'
        ];
        
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data (3 rows)
        $sampleData = [
            [
                'ORD001', 'Shipped', 'Standard', 'USPS Priority',
                'ITEM001', 'Delivered', 'Blue Widget', 'Widget Pro',
                'Color: Blue', 'SKU-WIDGET-001', 'WID001', 2, 2,
                0, 'John Doe', 'John', 'Doe',
                '+1234567890', '123 Main St', 'Apt 4B', '',
                'Downtown', 'New York', 'NY', '10001', 'USA',
                '2025-01-15 10:30:00', '2025-01-16 17:00:00', '2025-01-20 17:00:00', '',
                'john@example.com', '19.99', '39.98', 'TRACK123456',
                'USPS', 'Paid', 'Signature Required'
            ],
            [
                'ORD002', 'Processing', 'Express', 'FedEx Overnight',
                'ITEM002', 'Processing', 'Red Gadget', 'Gadget Max',
                'Color: Red', 'SKU-GADGET-002', 'GAD002', 1, 0,
                1, 'Jane Smith', 'Jane', 'Smith',
                '+0987654321', '456 Oak Ave', '', '',
                'Westside', 'Los Angeles', 'CA', '90001', 'USA',
                '2025-01-15 14:20:00', '2025-01-15 23:59:00', '2025-01-17 17:00:00', '',
                'jane@example.com', '49.99', '49.99', '',
                'FedEx', 'Pending', 'No Signature Required'
            ],
            [
                'ORD003', 'Cancelled', 'Standard', '',
                'ITEM003', 'Cancelled', 'Green Tool', 'Tool Plus',
                'Size: Large', 'SKU-TOOL-003', 'TOL003', 3, 0,
                0, 'Bob Johnson', 'Bob', 'Johnson',
                '+1122334455', '789 Elm St', 'Suite 200', 'Floor 2',
                'Midtown', 'Chicago', 'IL', '60601', 'USA',
                '2025-01-14 09:15:00', '2025-01-15 17:00:00', '2025-01-18 17:00:00', '',
                'bob@example.com', '29.99', '89.97', '',
                '', 'Refunded', ''
            ]
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths for better readability
        foreach (range('A', 'AK') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:AK1')->applyFromArray($headerStyle);

        // Output Download
        $fileName = 'Temu_Daily_Data_Sample_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Get daily data for Temu tabulator (sales page).
     * Source: apicentral.shopify_order_items — same Temu identification as /shopify-orders.
     */
    public function getDailyData(Request $request)
    {
        try {
            // Source: temu_orders table (Temu API order-wise data).
            // Use the canonical Pacific-aligned 30 inclusive-day window (same as
            // /all-marketplace-master L30) so /temu-tabulator "Total Revenue" matches
            // Temu Seller Central's "30 days" base-price sales tile. The old
            // Carbon::now()->subDays(30) window used the app tz (Asia/Kolkata) and spanned
            // 31 inclusive days, inflating revenue above Temu's reported figure.
            [$start, $end] = TemuShopifySalesService::channelMasterL30Window();
            $result = TemuShopifySalesService::getOrdersTableRows($start, $end);

            Log::info('Temu daily data fetched from temu_orders', [
                'result_count' => count($result),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching Temu daily data from temu_orders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get L60 daily data for Temu tabulator — prior 30-day shopify_order_items window.
     */
    public function getDailyDataL60(Request $request)
    {
        try {
            // Source: temu_orders table, prior 30-day window (days 31–60).
            [$start, $end] = TemuShopifySalesService::channelMasterL60Window();
            $result = TemuShopifySalesService::getOrdersTableRows($start, $end);

            Log::info('Temu L60 daily data fetched from temu_orders', [
                'result_count' => count($result),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching Temu L60 daily data from temu_orders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    public function getTemu2DailyData(Request $request)
    {
        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                $sku = preg_replace('/\s+/', ' ', $sku);
                return $sku;
            };

            $productMasterSkus = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->pluck('sku')
                ->filter(function ($sku) {
                    return stripos($sku, 'PARENT') === false;
                })
                ->unique()
                ->values()
                ->all();

            $normalizedPmSet = collect($productMasterSkus)->mapWithKeys(function ($s) use ($normalizeSku) {
                return [$normalizeSku($s) => true];
            })->all();

            if (! Schema::hasTable('temu2_daily_data')) {
                return response()->json(['data' => []]);
            }

            $allowedRawSkus = Temu2DailyData::select('contribution_sku')->distinct()
                ->get()
                ->filter(function ($r) use ($normalizeSku, $normalizedPmSet) {
                    return isset($normalizedPmSet[$normalizeSku($r->contribution_sku ?? '')]);
                })
                ->pluck('contribution_sku')
                ->unique()
                ->values()
                ->all();

            $allTemuData = Temu2DailyData::whereIn('contribution_sku', $allowedRawSkus)
                ->orderBy('purchase_date', 'desc')
                ->orderBy('order_id', 'desc')
                ->get();

            $productMasters = ProductMaster::whereIn('sku', $productMasterSkus)->get();
            $pmByNormalized = $productMasters->keyBy(function ($pm) use ($normalizeSku) {
                return $normalizeSku($pm->sku);
            });

            // Same margin as /temu-tabulator (marketplace_percentages.Temu) — no hardcode
            $margin = TemuShopifySalesService::temuMarginDecimal();

            $result = [];
            foreach ($allTemuData as $item) {
                $sku = $item->contribution_sku;
                $pm = $pmByNormalized[$normalizeSku($sku ?? '')] ?? null;
                $parent = $pm ? $pm->parent : '';
                $lp = 0;
                $temuShip = 0;
                if ($pm) {
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    $temuShip = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $pm);
                }
                $basePrice = $item->base_price_total !== null ? (float)$item->base_price_total : 0;
                $quantity = $item->quantity_purchased !== null ? (int)$item->quantity_purchased : 0;
                // FB Prc: +$2.99 when per-unit base price ≤ $26.99 (matches /temu-decrease).
                $fbPrice = $basePrice <= 26.99 ? ($basePrice + 2.99) : $basePrice;
                $pft = ($fbPrice * $margin - $lp - $temuShip) * $quantity;
                $result[] = [
                    'Parent' => $parent,
                    'contribution_sku' => $item->contribution_sku ?? '',
                    'order_id' => $item->order_id ?? '',
                    'product_name_by_customer_order' => $item->product_name_by_customer_order ?? '',
                    'variation' => $item->variation ?? '',
                    'quantity_purchased' => $quantity,
                    'quantity_shipped' => (int)($item->quantity_shipped ?? 0),
                    'quantity_to_ship' => (int)($item->quantity_to_ship ?? 0),
                    'base_price_total' => $basePrice,
                    'fb_price' => round($fbPrice, 2),
                    'lp' => $lp,
                    'temu_ship' => $temuShip,
                    'pft' => round($pft, 2),
                    'order_status' => $item->order_status ?? '',
                    'fulfillment_mode' => $item->fulfillment_mode ?? '',
                    'tracking_number' => $item->tracking_number ?? '',
                    'carrier' => $item->carrier ?? '',
                    'created_at' => $item->purchase_date ? $item->purchase_date->format('Y-m-d H:i:s') : null,
                ];
            }
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching Temu 2 daily data: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get L7 daily data for Temu 2 tabulator export. Same structure as getTemu2DailyData but uses temu2_daily_data_l7.
     */
    public function getTemu2DailyDataL7(Request $request)
    {
        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                $sku = preg_replace('/\s+/', ' ', $sku);
                return $sku;
            };

            $productMasterSkus = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->pluck('sku')
                ->filter(function ($sku) {
                    return stripos($sku, 'PARENT') === false;
                })
                ->unique()
                ->values()
                ->all();

            $normalizedPmSet = collect($productMasterSkus)->mapWithKeys(function ($s) use ($normalizeSku) {
                return [$normalizeSku($s) => true];
            })->all();

            if (! Schema::hasTable('temu2_daily_data_l7')) {
                return response()->json(['data' => []]);
            }

            $allowedRawSkus = Temu2DailyDataL7::select('contribution_sku')->distinct()
                ->get()
                ->filter(function ($r) use ($normalizeSku, $normalizedPmSet) {
                    return isset($normalizedPmSet[$normalizeSku($r->contribution_sku ?? '')]);
                })
                ->pluck('contribution_sku')
                ->unique()
                ->values()
                ->all();

            $allTemuData = Temu2DailyDataL7::whereIn('contribution_sku', $allowedRawSkus)
                ->orderBy('purchase_date', 'desc')
                ->orderBy('order_id', 'desc')
                ->get();

            $productMasters = ProductMaster::whereIn('sku', $productMasterSkus)->get();
            $pmByNormalized = $productMasters->keyBy(function ($pm) use ($normalizeSku) {
                return $normalizeSku($pm->sku);
            });

            // Same margin as /temu-tabulator (marketplace_percentages.Temu) — no hardcode
            $margin = TemuShopifySalesService::temuMarginDecimal();

            $result = [];
            foreach ($allTemuData as $item) {
                $sku = $item->contribution_sku;
                $pm = $pmByNormalized[$normalizeSku($sku ?? '')] ?? null;
                $parent = $pm ? $pm->parent : '';
                $lp = 0;
                $temuShip = 0;
                if ($pm) {
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    $temuShip = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $pm);
                }
                $basePrice = $item->base_price_total !== null ? (float)$item->base_price_total : 0;
                $quantity = $item->quantity_purchased !== null ? (int)$item->quantity_purchased : 0;
                // FB Prc: +$2.99 when per-unit base price ≤ $26.99 (matches /temu-decrease).
                $fbPrice = $basePrice <= 26.99 ? ($basePrice + 2.99) : $basePrice;
                $pft = ($fbPrice * $margin - $lp - $temuShip) * $quantity;
                $result[] = [
                    'Parent' => $parent,
                    'contribution_sku' => $item->contribution_sku ?? '',
                    'order_id' => $item->order_id ?? '',
                    'product_name_by_customer_order' => $item->product_name_by_customer_order ?? '',
                    'variation' => $item->variation ?? '',
                    'quantity_purchased' => $quantity,
                    'quantity_shipped' => (int)($item->quantity_shipped ?? 0),
                    'quantity_to_ship' => (int)($item->quantity_to_ship ?? 0),
                    'base_price_total' => $basePrice,
                    'fb_price' => round($fbPrice, 2),
                    'lp' => $lp,
                    'temu_ship' => $temuShip,
                    'pft' => round($pft, 2),
                    'order_status' => $item->order_status ?? '',
                    'fulfillment_mode' => $item->fulfillment_mode ?? '',
                    'tracking_number' => $item->tracking_number ?? '',
                    'carrier' => $item->carrier ?? '',
                    'created_at' => $item->purchase_date ? $item->purchase_date->format('Y-m-d H:i:s') : null,
                ];
            }
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching Temu 2 L7 daily data: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show Temu Tabulator View
     */
    public function temuTabulatorView()
    {
        // Y Sales — same source/definition as the Temu row on /all-marketplace-master:
        // freight-inclusive (FB price) revenue for wall-clock yesterday (Pacific).
        $temuYSales = TemuShopifySalesService::computeYSalesFromOrders();

        // Margin from marketplace_percentages (Temu), same source getOrdersTableRows /
        // getTemuChannelData use — so /temu-tabulator GPFT%/ROI match /all-marketplace-master.
        $temuMargin = TemuShopifySalesService::temuMarginDecimal();

        return view('market-places.temu_tabulator_view', [
            'temuYSales' => $temuYSales,
            'temuMargin' => $temuMargin,
        ]);
    }

    /**
     * Save Temu column visibility preferences
     */
    public function saveTemuColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu_tabulator_column_visibility_{$userId}";
            
            $visibility = $request->input('visibility', []);
            
            // Store in cache (matching eBay pattern)
            Cache::put($key, $visibility, now()->addDays(365));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    /**
     * Get Temu column visibility preferences
     */
    public function getTemuColumnVisibility()
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu_tabulator_column_visibility_{$userId}";

            $visibility = Cache::get($key, []);
            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting Temu column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Show Temu 2 Tabulator View (same upload, same badges, same DB tables: temu_daily_data / temu_daily_data_l60)
     */
    public function temu2TabulatorView()
    {
        // Same margin source/formula as /temu-tabulator (marketplace_percentages.Temu).
        $temuMargin = TemuShopifySalesService::temuMarginDecimal();

        // Y Sales — base-price sales for the day before the latest uploaded purchase_date
        // (the last complete day). Also expose that date so the badge shows which day it
        // reflects — makes it obvious when the Temu 2 upload is behind Seller Central.
        $temu2YSales = $this->computeTemu2YSales();
        $latestUpload = (Schema::hasTable('temu2_daily_data') ? Temu2DailyData::whereNotNull('purchase_date')->max('purchase_date') : null);
        $temu2YDate = $latestUpload ? Carbon::parse($latestUpload)->subDay()->toDateString() : null;

        return view('market-places.temu2_tabulator_view', compact('temuMargin', 'temu2YSales', 'temu2YDate'));
    }

    /**
     * Temu 2 Y Sales: yesterday's BASE-price sales from temu2_daily_data — matches Temu
     * Seller Central's "Base price sales" daily chart. Anchored to the day before the
     * latest uploaded purchase_date (a Temu export always includes a partial "today", so
     * max − 1 day = the last complete day = "yesterday" when uploads are current).
     *
     * purchase_date is stored as the Temu export's own (Pacific) date, so it is used as-is
     * WITHOUT a timezone conversion — converting it shifted the day back incorrectly.
     */
    private function computeTemu2YSales(): ?float
    {
        try {
            $latest = (Schema::hasTable('temu2_daily_data') ? Temu2DailyData::whereNotNull('purchase_date')->max('purchase_date') : null);
            if (! $latest) {
                return null;
            }

            $yesterday = Carbon::parse($latest)->subDay();
            $yStart = $yesterday->copy()->startOfDay();
            $yEnd = $yesterday->copy()->endOfDay();

            $rows = Temu2DailyData::where('purchase_date', '>=', $yStart)
                ->where('purchase_date', '<=', $yEnd)
                ->get(['contribution_sku', 'quantity_purchased', 'base_price_total']);

            $total = 0.0;
            foreach ($rows as $row) {
                if (trim((string) ($row->contribution_sku ?? '')) === '') {
                    continue;
                }
                $quantity = (int) ($row->quantity_purchased ?? 0);
                $basePrice = (float) ($row->base_price_total ?? 0);
                if ($quantity <= 0 || $basePrice <= 0) {
                    continue;
                }
                // Base price × qty (no FB freight uplift) to mirror Temu's "Base price sales".
                $total += $basePrice * $quantity;
            }

            return round($total, 2);
        } catch (\Throwable $e) {
            Log::warning('computeTemu2YSales failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Save Temu 2 column visibility preferences
     */
    public function saveTemu2ColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu2_tabulator_column_visibility_{$userId}";

            $visibility = $request->input('visibility', []);
            Cache::put($key, $visibility, now()->addDays(365));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu 2 column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    /**
     * Get Temu 2 column visibility preferences
     */
    public function getTemu2ColumnVisibility()
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu2_tabulator_column_visibility_{$userId}";

            $visibility = Cache::get($key, []);
            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting Temu 2 column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Upload Temu Pricing Data
     */
    public function uploadTemuPricing(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Temu pricing sheet upload has been removed. Use temu_metrics (API).',
        ], 410);
    }

    /**
     * Download Temu Pricing Sample File
     */
    public function downloadTemuPricingSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = [
            'Category',
            'Category id',
            'Product name',
            'Contribution Goods',
            'SKU',
            'Goods ID',
            'SKU ID',
            'Variation',
            'Quantity',
            'Base price',
            'External Product ID Type',
            'External product ID',
            'Status',
            'Detail status',
            'Date created',
            'Incomplete product information'
        ];
        
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            [
                'Musical Instruments/Electronic Music',
                '18434',
                '5Core Speaker Stand 2Pc Heavy Duty',
                'SS SQ WH',
                'SS SQ WH',
                '603239688828956',
                '47514283725096',
                'White',
                '100',
                '249.99',
                '',
                '',
                'Active',
                'Active',
                '24/12/2025 03:44:26',
                ''
            ],
            [
                'Musical Instruments/Electronic Music',
                '18434',
                '5Core Speaker Stand 2Pc Heavy Duty',
                'SS SQ BLK',
                'SS SQ BLK',
                '603239688833129',
                '43116237163596',
                'Black',
                '200',
                '249.99',
                '',
                '',
                'Active',
                'Active',
                '24/12/2025 03:33:55',
                ''
            ]
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']]
        ];
        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

        // Output Download
        $fileName = 'Temu_Pricing_Sample_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload Temu 2 listing/pricing sheet (Seller export).
     * Format columns: Category, Category id, Product name, Contribution Goods, SKU,
     * Goods ID, SKU ID, Variation, Quantity, Base price, …
     * Matches by Goods ID (+ SKU ID / SKU) and updates Base price on temu2_pricing
     * and temu2_metrics so /temu2-decrease Price column reflects the upload.
     *
     * Fast path: bulk insert + in-memory metric match (no per-row ORM queries).
     */
    public function uploadTemu2Pricing(Request $request)
    {
        @set_time_limit(120);
        $request->validate([
            'pricing_file' => 'required|file|max:20480',
        ]);

        $file = $request->file('pricing_file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['xlsx', 'xls', 'csv', 'tsv', 'txt'];
        if (! in_array($ext, $allowed, true)) {
            $msg = 'Invalid file type. Upload .xlsx, .xls, .csv, or .tsv (Temu listing export).';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        try {
            $rows = $this->readTemu2PricingUploadRows($file->getRealPath(), $ext);
            if (count($rows) < 2) {
                throw new \RuntimeException('File has no data rows.');
            }

            $rawHeaders = array_shift($rows);
            $headerMap = []; // normalized => 0-based index
            foreach ($rawHeaders as $idx => $header) {
                if ($header instanceof RichText) {
                    $header = $header->getPlainText();
                }
                $key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $header)));
                if ($key !== '') {
                    $headerMap[$key] = (int) $idx;
                }
            }

            $col = static function (array $aliases) use ($headerMap) {
                foreach ($aliases as $alias) {
                    $k = strtolower(trim(preg_replace('/\s+/', ' ', $alias)));
                    if (array_key_exists($k, $headerMap)) {
                        return $headerMap[$k];
                    }
                }

                return null;
            };

            $goodsIdCol = $col(['Goods ID', 'GoodsID', 'goods_id']);
            $basePriceCol = $col(['Base price', 'Base Price', 'base_price', 'Price']);
            $skuCol = $col(['SKU', 'Contribution SKU']);
            $skuIdCol = $col(['SKU ID', 'sku_id']);
            $qtyCol = $col(['Quantity']);
            $categoryCol = $col(['Category']);
            $categoryIdCol = $col(['Category id', 'Category ID']);
            $productNameCol = $col(['Product name', 'Product Name']);
            $contribCol = $col(['Contribution Goods']);
            $variationCol = $col(['Variation']);
            $statusCol = $col(['Status']);
            $detailStatusCol = $col(['Detail status', 'Detail Status']);
            $extTypeCol = $col(['External Product ID Type']);
            $extIdCol = $col(['External product ID', 'External Product ID']);
            $incompleteCol = $col(['Incomplete product information']);

            if ($goodsIdCol === null || $basePriceCol === null) {
                throw new \RuntimeException('Missing required columns: Goods ID and Base price.');
            }

            $val = static function (array $row, $idx) {
                if ($idx === null || ! array_key_exists($idx, $row)) {
                    return null;
                }
                $v = $row[$idx];
                if ($v instanceof RichText) {
                    $v = $v->getPlainText();
                }

                return is_string($v) ? trim($v) : $v;
            };

            $normId = static function ($v) {
                if ($v === null || $v === '') {
                    return '';
                }
                if (is_float($v) || (is_numeric($v) && preg_match('/[eE]/', (string) $v))) {
                    return TemuGoodsIdHelper::normalizeKey(number_format((float) $v, 0, '.', '')) ?? '';
                }

                return TemuGoodsIdHelper::normalizeKey($v) ?? '';
            };

            $now = now()->format('Y-m-d H:i:s');
            $pricingRows = [];
            $metricUpdates = []; // id => [base_price, goods_id, sku_id?, quantity?]
            $metricCreates = [];
            $skipped = 0;

            // Normalize SKU the same way /temu2-decrease matches to CP Master
            $normalizeSku = static function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);

                return preg_replace('/\s+/', ' ', $sku);
            };

            // Preload metrics once for O(1) match (prefer SKU / SKU ID over Goods ID)
            $bySkuId = [];
            $bySkuNorm = [];
            $byGoodsId = [];
            if (Schema::hasTable('temu2_metrics')) {
                foreach (DB::table('temu2_metrics')->select(['id', 'sku', 'sku_id', 'goods_id'])->get() as $m) {
                    $id = (int) $m->id;
                    $sid = TemuGoodsIdHelper::normalizeKey($m->sku_id) ?? trim((string) $m->sku_id);
                    $sku = trim((string) $m->sku);
                    $skuN = $normalizeSku($sku);
                    $gid = TemuGoodsIdHelper::normalizeKey($m->goods_id) ?? trim((string) $m->goods_id);
                    if ($sid !== '') {
                        $bySkuId[$sid] = $id;
                    }
                    if ($skuN !== '') {
                        $bySkuNorm[$skuN] = $id;
                    }
                    if ($gid !== '') {
                        if (! isset($byGoodsId[$gid])) {
                            $byGoodsId[$gid] = [];
                        }
                        $byGoodsId[$gid][$skuN !== '' ? $skuN : ('_'.$id)] = $id;
                    }
                }
            }

            // Pricing table is fully replaced via truncate below — start ids at 1.
            $nextPricingId = 1;
            $nextMetricId = Schema::hasTable('temu2_metrics')
                ? ((int) (DB::table('temu2_metrics')->max('id') ?? 0) + 1)
                : 1;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;
                    continue;
                }

                $goodsId = $normId($val($row, $goodsIdCol));
                $baseRaw = $val($row, $basePriceCol);
                $basePrice = is_numeric($baseRaw)
                    ? (float) $baseRaw
                    : (float) preg_replace('/[^0-9.\-]/', '', (string) $baseRaw);
                $sku = trim((string) ($val($row, $skuCol) ?? ''));
                $skuId = $normId($val($row, $skuIdCol));
                $qty = (int) ($val($row, $qtyCol) ?? 0);

                if ($goodsId === '' || $basePrice <= 0 || ($sku === '' && $skuId === '')) {
                    $skipped++;
                    continue;
                }

                $skuOut = $sku !== '' ? $sku : $skuId;
                $pricingRows[] = [
                    'id' => $nextPricingId++,
                    'category' => (string) ($val($row, $categoryCol) ?? ''),
                    'category_id' => (string) ($val($row, $categoryIdCol) ?? ''),
                    'product_name' => (string) ($val($row, $productNameCol) ?? ''),
                    'contribution_goods' => (string) ($val($row, $contribCol) ?? ''),
                    'sku' => $skuOut,
                    'goods_id' => $goodsId,
                    'sku_id' => $skuId,
                    'variation' => (string) ($val($row, $variationCol) ?? ''),
                    'quantity' => $qty,
                    'base_price' => round($basePrice, 2),
                    'external_product_id_type' => (string) ($val($row, $extTypeCol) ?? ''),
                    'external_product_id' => (string) ($val($row, $extIdCol) ?? ''),
                    'status' => (string) ($val($row, $statusCol) ?? ''),
                    'detail_status' => (string) ($val($row, $detailStatusCol) ?? ''),
                    'date_created' => null,
                    'incomplete_product_information' => (string) ($val($row, $incompleteCol) ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $skuN = $normalizeSku($skuOut);
                $metricId = null;
                // Match order: SKU ID → SKU (CP Master style normalize) → Goods ID + SKU → Goods ID
                if ($skuId !== '' && isset($bySkuId[$skuId])) {
                    $metricId = $bySkuId[$skuId];
                } elseif ($skuN !== '' && isset($bySkuNorm[$skuN])) {
                    $metricId = $bySkuNorm[$skuN];
                } elseif ($goodsId !== '' && isset($byGoodsId[$goodsId])) {
                    $metricId = $byGoodsId[$goodsId][$skuN] ?? reset($byGoodsId[$goodsId]) ?: null;
                }

                if ($metricId) {
                    $metricUpdates[(int) $metricId] = [
                        'base_price' => round($basePrice, 2),
                        'goods_id' => $goodsId,
                        'sku_id' => $skuId !== '' ? $skuId : null,
                        'quantity' => $qty > 0 ? $qty : null,
                        'updated_at' => $now,
                    ];
                } else {
                    $newId = $nextMetricId++;
                    $metricCreates[] = [
                        'id' => $newId,
                        'sku' => $skuOut,
                        'sku_id' => $skuId !== '' ? $skuId : null,
                        'goods_id' => $goodsId,
                        'base_price' => round($basePrice, 2),
                        'quantity' => $qty,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($skuId !== '') {
                        $bySkuId[$skuId] = $newId;
                    }
                    if ($skuN !== '') {
                        $bySkuNorm[$skuN] = $newId;
                    }
                }
            }

            $imported = count($pricingRows);
            $metricsUpdated = count($metricUpdates);
            $metricsCreated = count($metricCreates);

            // TRUNCATE auto-commits on MySQL — run outside the transaction.
            if (Schema::hasTable('temu2_pricing')) {
                DB::table('temu2_pricing')->truncate();
            }

            DB::beginTransaction();
            try {
                if (Schema::hasTable('temu2_pricing')) {
                    foreach (array_chunk($pricingRows, 500) as $chunk) {
                        DB::table('temu2_pricing')->insert($chunk);
                    }
                }

                // Per-id updates (reliable; ~700 rows is still fast)
                foreach ($metricUpdates as $id => $u) {
                    $data = [
                        'base_price' => $u['base_price'],
                        'goods_id' => $u['goods_id'],
                        'updated_at' => $now,
                    ];
                    if (! empty($u['sku_id'])) {
                        $data['sku_id'] = $u['sku_id'];
                    }
                    if ($u['quantity'] !== null) {
                        $data['quantity'] = $u['quantity'];
                    }
                    DB::table('temu2_metrics')->where('id', (int) $id)->update($data);
                }

                foreach (array_chunk($metricCreates, 500) as $chunk) {
                    DB::table('temu2_metrics')->insert($chunk);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $message = "Imported {$imported} pricing row(s). Updated {$metricsUpdated} metric price(s)"
                .($metricsCreated > 0 ? ", created {$metricsCreated} metric(s)" : '')
                .($skipped > 0 ? ", skipped {$skipped}" : '')
                .'.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                    'metrics_updated' => $metricsUpdated,
                    'metrics_created' => $metricsCreated,
                    'skipped' => $skipped,
                ]);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Temu 2 pricing upload failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $msg = 'Error uploading Temu 2 pricing: '.$e->getMessage();

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => $msg], 500)
                : back()->with('error', $msg);
        }
    }

    /**
     * Read Temu 2 pricing upload into a plain row array (header + data).
     * TSV/CSV use native PHP (fast); Excel uses PhpSpreadsheet data-only load.
     */
    private function readTemu2PricingUploadRows(string $path, string $ext): array
    {
        if (in_array($ext, ['tsv', 'txt', 'csv'], true)) {
            $delimiter = $ext === 'csv' ? ',' : "\t";
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                throw new \RuntimeException('Could not open upload file.');
            }
            $out = [];
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                // Skip fully empty trailing rows
                if ($row === [null] || $row === false) {
                    continue;
                }
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) {
                    continue;
                }
                $out[] = $row;
            }
            fclose($fh);

            return $out;
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return array_values(array_filter($rows, static function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Sync Temu 2 SKUs / goods / prices / stock from Open API into temu2_metrics.
     */
    public function syncTemu2MetricsFromApi(Request $request)
    {
        try {
            $only = strtolower(trim((string) $request->input('only', '')));
            $allowed = ['', 'skus', 'goods', 'qty', 'price', 'ads', 'stock'];
            if (! in_array($only, $allowed, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid only value'], 422);
            }

            $params = $only === '' ? [] : ['--only' => $only];
            $exit = \Illuminate\Support\Facades\Artisan::call('app:fetch-temu2-metrics', $params);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            $count = Schema::hasTable('temu2_metrics')
                ? (int) Temu2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0;
            $withPrice = Schema::hasTable('temu2_metrics') && Schema::hasColumn('temu2_metrics', 'base_price')
                ? (int) Temu2Metric::query()->whereNotNull('base_price')->where('base_price', '>', 0)->count()
                : 0;

            return response()->json([
                'success' => $exit === 0,
                'message' => $exit === 0
                    ? "Temu 2 API sync done. {$count} SKU(s), {$withPrice} with price."
                    : ('Temu 2 API sync failed. '.$output),
                'count' => $count,
                'with_price' => $withPrice,
                'output' => $output,
            ], $exit === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error('Temu 2 API sync failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Temu 2 API sync failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download Temu 2 Pricing sample (same columns as Temu pricing sample).
     */
    public function downloadTemu2PricingSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Category',
            'Category id',
            'Product name',
            'Contribution Goods',
            'SKU',
            'Goods ID',
            'SKU ID',
            'Variation',
            'Quantity',
            'Base price',
            'External Product ID Type',
            'External product ID',
            'Status',
            'Detail status',
            'Date created',
            'Incomplete product information',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sampleData = [
            [
                'Musical Instruments/Electronic Music',
                '18434',
                '5Core Speaker Stand 2Pc Heavy Duty',
                'SS SQ WH',
                'SS SQ WH',
                '603239688828956',
                '47514283725096',
                'White',
                '100',
                '249.99',
                '',
                '',
                'Active',
                'Active',
                '24/12/2025 03:44:26',
                '',
            ],
            [
                'Musical Instruments/Electronic Music',
                '18434',
                '5Core Speaker Stand 2Pc Heavy Duty',
                'SS SQ BLK',
                'SS SQ BLK',
                '603239688833129',
                '43116237163596',
                'Black',
                '200',
                '249.99',
                '',
                '',
                'Active',
                'Active',
                '24/12/2025 03:33:55',
                '',
            ],
        ];

        $sheet->fromArray($sampleData, null, 'A2');

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']],
        ];
        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

        $fileName = 'Temu2_Pricing_Sample_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Update Temu 2 base price in temu2_metrics (API-backed).
     */
    public function updateTemu2Price(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'base_price' => 'required|numeric|min:0',
            ]);

            $metric = Temu2Metric::where('sku', $request->sku)->first();

            if (! $metric) {
                return response()->json(['error' => 'SKU not found in temu2_metrics'], 404);
            }

            $metric->base_price = $request->base_price;
            $metric->save();

            if (Schema::hasTable('temu2_pricing')) {
                Temu2Pricing::where('sku', $request->sku)->update(['base_price' => $request->base_price]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully',
                'data' => $metric,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Temu 2 price: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to update price'], 500);
        }
    }

    /**
     * Save Temu 2 SPRICE (temu2_data_view).
     */
    public function saveTemu2Sprice(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'sprice' => 'required|numeric|min:0',
            ]);

            $sku = trim((string) $request->sku);
            $sprice = floatval($request->sprice);

            $productMaster = ProductMaster::where('sku', $sku)->first()
                ?? ProductMaster::whereRaw('TRIM(sku) = ?', [$sku])->first();

            $lp = 0;
            $temuShip = 0;

            if ($productMaster) {
                $values = is_array($productMaster->Values)
                    ? $productMaster->Values
                    : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($productMaster->lp)) {
                    $lp = floatval($productMaster->lp);
                }

                $temuShip = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $productMaster);
            }

            // SGPRFT on Full Sprice; SROI on S Recovery (Sprice × 0.88)
            // Same margin as /temu-decrease (marketplace_percentages.Temu)
            $margin = TemuShopifySalesService::temuMarginDecimal();
            $sRecovery = $sprice * 0.88;
            $profitRoi = $sRecovery * $margin - $lp - $temuShip;
            $profitPft = $sprice * $margin - $lp - $temuShip;
            $sgprftPercent = $sprice > 0 ? ($profitPft / $sprice) * 100 : 0;
            // SROI = S Profit (recovery) / LP
            $sroiPercent = $lp > 0 ? ($profitRoi / $lp) * 100 : 0;

            $this->writeTemuChannelSprice($sku, $sprice, $sgprftPercent, $sroiPercent, true);
            // Auto-apply same suggested price to Temu 1
            $this->writeTemuChannelSprice($sku, $sprice, $sgprftPercent, $sroiPercent, false);

            return response()->json([
                'success' => true,
                'message' => 'SPRICE saved successfully',
                'sprice' => $sprice,
                'sgprft_percent' => round($sgprftPercent, 2),
                'sroi_percent' => round($sroiPercent, 2),
                'temu_cross_applied' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu 2 SPRICE: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to save SPRICE'], 500);
        }
    }

    /**
     * Persist SPRICE (+ metrics) to Temu or Temu2 data_view for one SKU.
     */
    private function writeTemuChannelSprice(string $sku, float $sprice, float $sgprftPercent, float $sroiPercent, bool $isTemu2): void
    {
        if ($isTemu2 && !Schema::hasTable('temu2_data_view')) {
            return;
        }

        $modelClass = $isTemu2 ? Temu2DataView::class : TemuDataView::class;
        $dataView = $modelClass::firstOrNew(['sku' => $sku]);
        $dataView->sku = $sku;
        $existingValue = is_array($dataView->value)
            ? $dataView->value
            : (is_string($dataView->value) ? json_decode($dataView->value, true) : []);
        if (!is_array($existingValue)) {
            $existingValue = [];
        }

        if ($sprice > 0) {
            $existingValue['sprice'] = $sprice;
            $existingValue['SPRICE'] = $sprice;
            $existingValue['sgprft_percent'] = round($sgprftPercent, 2);
            $existingValue['sroi_percent'] = round($sroiPercent, 2);
            $existingValue['SGPFT'] = round($sgprftPercent, 2);
            $existingValue['SROI'] = round($sroiPercent, 2);
        } else {
            unset(
                $existingValue['sprice'], $existingValue['SPRICE'],
                $existingValue['sgprft_percent'], $existingValue['sroi_percent'],
                $existingValue['SGPFT'], $existingValue['SROI'],
                $existingValue['SPFT'], $existingValue['spft']
            );
        }

        $dataView->value = $existingValue;

        // Some environments lost AUTO_INCREMENT on id — assign next id for new rows.
        if (! $dataView->exists && empty($dataView->id)) {
            $nextId = (int) ($modelClass::query()->max('id') ?? 0) + 1;
            if ($nextId < 1) {
                $nextId = 1;
            }
            $dataView->id = $nextId;
        }

        $dataView->save();
    }

    /**
     * Copy temu_data_view rows into temu2_data_view (same JSON value).
     * Use after splitting Temu / Temu 2 so old SPRICE rows still appear on Temu 2 Pricing.
     * Optional: pass sku (string) or skus (array); omit both to sync all rows.
     */
    public function syncTemu2DataViewFromTemuDataView(Request $request)
    {
        try {
            if (!Schema::hasTable('temu2_data_view') || !Schema::hasTable('temu_data_view')) {
                return response()->json(['success' => false, 'message' => 'Required tables are missing.'], 500);
            }

            $sku = $request->input('sku');
            $skus = $request->input('skus');

            $query = TemuDataView::query()->orderBy('id');
            if (is_array($skus) && count($skus) > 0) {
                $query->whereIn('sku', array_map('trim', $skus));
            } elseif ($sku !== null && $sku !== '') {
                $t = trim((string) $sku);
                $rawSku = $sku;
                $query->where(function ($q) use ($t, $rawSku) {
                    $q->where('sku', $t)->orWhere('sku', $rawSku);
                });
            }

            $synced = 0;
            $query->chunkById(500, function ($rows) use (&$synced) {
                foreach ($rows as $row) {
                    $rowSku = trim((string) ($row->sku ?? ''));
                    if ($rowSku === '') {
                        continue;
                    }
                    $v = $row->value;
                    if (!is_array($v)) {
                        $raw = $row->getAttributes()['value'] ?? null;
                        $v = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
                    }
                    if (!is_array($v)) {
                        $v = [];
                    }
                    Temu2DataView::updateOrCreate(
                        ['sku' => $rowSku],
                        ['value' => $v]
                    );
                    $synced++;
                }
            });

            return response()->json([
                'success' => true,
                'synced' => $synced,
                'message' => $synced === 0
                    ? 'No matching rows in temu_data_view.'
                    : "Copied {$synced} row(s) from temu_data_view to temu2_data_view.",
            ]);
        } catch (\Exception $e) {
            Log::error('syncTemu2DataViewFromTemuDataView: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Clear SPRICE for selected SKUs (temu2_data_view only).
     */
    public function clearAllTemu2Sprice(Request $request)
    {
        try {
            DB::beginTransaction();

            $cleared = 0;
            $skus = $request->input('skus', []);

            if (empty($skus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SKUs selected',
                ], 400);
            }

            $dataViewRecords = Temu2DataView::whereIn('sku', $skus)->get();

            foreach ($dataViewRecords as $record) {
                $value = $record->value ?? [];

                $fieldsToRemove = [
                    'sprice',
                    'spft_percent',
                    'sroi_percent',
                    'ship',
                    'amazon_price_applied_at',
                    'r_price_applied_at',
                    'sprice_status',
                ];

                $wasModified = false;
                foreach ($fieldsToRemove as $field) {
                    if (isset($value[$field])) {
                        unset($value[$field]);
                        $wasModified = true;
                    }
                }

                if ($wasModified) {
                    if (empty($value)) {
                        $record->delete();
                    } else {
                        $record->update([
                            'value' => $value,
                            'updated_at' => now(),
                        ]);
                    }
                    $cleared++;
                }
            }

            DB::commit();

            Log::info("Cleared Temu 2 SPRICE data for {$cleared} selected SKUs");

            return response()->json([
                'success' => true,
                'cleared' => $cleared,
                'message' => "Successfully cleared SPRICE for {$cleared} SKU(s)",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing Temu 2 SPRICE: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to clear SPRICE data'], 500);
        }
    }

    /**
     * Show Temu Decrease View
     */
    public function temuDecreaseView()
    {
        // Margin from marketplace_percentages (Temu) — same source as GROI/GPFT backend
        $temuMargin = TemuShopifySalesService::temuMarginDecimal();

        return view('market-places.temu_decrease', [
            'temuMargin' => $temuMargin,
        ]);
    }

    public function temu2DecreaseView()
    {
        // Same margin source/name as /temu-decrease (marketplace_percentages.Temu)
        $temuMargin = TemuShopifySalesService::temuMarginDecimal();
        return view('market-places.temu2_decrease', compact('temuMargin'));
    }

    /**
     * Get Temu badge daily history for the history table (JSON).
     * For "today" we use live sales summary (same as badge) so chart and badge match.
     */
    public function getTemuBadgeHistory(Request $request)
    {
        $days = (int) $request->input('days', 60);
        $days = max(7, min(365, $days));
        $rows = TemuBadgeDailyData::lastDays($days)->get();
        $todayStr = \Carbon\Carbon::today()->toDateString();
        $liveToday = $this->getTemuSalesSummaryForBadge();

        $data = $rows->map(function ($row) use ($todayStr, $liveToday) {
            $dateStr = $row->record_date->format('Y-m-d');
            $isToday = ($dateStr === $todayStr);
            return [
                'record_date' => $dateStr,
                'total_sales' => $isToday && $liveToday ? round((float) $liveToday['total_revenue'], 2) : round((float) $row->total_sales, 2),
                'total_orders' => $isToday && $liveToday ? (int) $liveToday['total_orders'] : (int) $row->total_orders,
                'total_quantity' => $isToday && $liveToday ? (int) $liveToday['total_quantity'] : (int) $row->total_quantity,
                'sku_count' => (int) $row->sku_count,
                'total_views' => (int) $row->total_views,
                'avg_views' => round((float) $row->avg_views, 2),
                'total_spend' => round((float) $row->total_spend, 2),
                'avg_cvr_pct' => round((float) $row->avg_cvr_pct, 2),
            ];
        })->values()->all();

        // If today is in range but not in DB, add one row with live data so chart matches badge
        if ($liveToday && !collect($data)->contains('record_date', $todayStr)) {
            $data[] = [
                'record_date' => $todayStr,
                'total_sales' => round((float) $liveToday['total_revenue'], 2),
                'total_orders' => (int) $liveToday['total_orders'],
                'total_quantity' => (int) $liveToday['total_quantity'],
                'sku_count' => 0,
                'total_views' => 0,
                'avg_views' => 0,
                'total_spend' => 0,
                'avg_cvr_pct' => 0,
            ];
        }

        // Serial order: oldest date first (left), newest last (right) for chart
        $data = collect($data)->sortBy('record_date')->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Same sales summary as badge on Temu decrease page (ProductMaster skus, all matching orders).
     */
    private function getTemuSalesSummaryForBadge(): ?array
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();
        $productMasters = $productMasters->filter(function ($item) {
            return stripos($item->sku, 'PARENT') === false;
        })->values();
        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        if (empty($skus)) {
            return null;
        }
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);
            return $sku;
        };
        $normalizedPmSet = collect($skus)->mapWithKeys(function ($s) use ($normalizeSku) {
            return [$normalizeSku($s) => true];
        })->all();
        if (Schema::hasTable('temu_orders')) {
            $allowedRawSkus = DB::table('temu_orders')->select('contribution_sku')->distinct()->get()
                ->filter(function ($r) use ($normalizeSku, $normalizedPmSet) {
                    return isset($normalizedPmSet[$normalizeSku($r->contribution_sku ?? '')]);
                })
                ->pluck('contribution_sku')
                ->unique()
                ->values()
                ->all();
            $salesOrderRows = DB::table('temu_orders')->whereIn('contribution_sku', $allowedRawSkus)
                ->get(['contribution_sku', 'order_id', 'quantity_purchased', 'base_price_total']);
        } else {
            $salesOrderRows = collect();
        }
        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        foreach ($salesOrderRows as $row) {
            if (trim((string)($row->contribution_sku ?? '')) === '' || trim((string)($row->order_id ?? '')) === '') {
                continue;
            }
            $totalOrders++;
            $qty = (int)($row->quantity_purchased ?? 0);
            $base = (float)($row->base_price_total ?? 0);
            $totalQuantity += $qty;
            // Full Temu Price = (base × 1.1364); +$2.99 if that ≤ $26.99
            $totalRevenue += TemuShopifySalesService::computeFullTemuPrice($base) * $qty;
        }
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => round($totalRevenue, 2),
        ];
    }

    /**
     * Get Temu Decrease Data (JSON). Optional L7 mode: when $purchaseDateFrom is set, sales are filtered to last 7 days.
     */
    public function getTemuDecreaseData(Request $request)
    {
        return $this->buildTemuDecreaseDataResponse($request, false);
    }

    /**
     * Temu 2 pricing table: same structure as Temu Decrease, but order aggregates use temu2_daily_data (and L7/L60 Temu 2 tables); no ads, Amazon, or eBay fields.
     */
    public function getTemu2DecreaseData(Request $request)
    {
        return $this->buildTemuDecreaseDataResponse($request, true);
    }

    public function getTemu2DecreaseDataL7(Request $request)
    {
        $request->query->set('period', 'L7');

        return $this->buildTemuDecreaseDataResponse($request, true);
    }

    private function buildTemuDecreaseDataResponse(Request $request, bool $isTemu2Pricing)
    {
        try {
            $selectedPeriod = strtoupper((string) $request->query('period', 'L30'));
            if (!in_array($selectedPeriod, ['L30', 'L7'], true)) {
                $selectedPeriod = 'L30';
            }
            $isL7Period = $selectedPeriod === 'L7';

            // Margin from marketplace_percentages.Temu for both Temu and Temu 2 decrease
            // (same TEMU_MARGIN / formula as /temu-decrease).
            $percentage = TemuShopifySalesService::temuMarginDecimal();
            
            // 1. Start from ProductMaster (like eBay does)
            $productMasters = ProductMaster::orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->get();

            // Keep PARENT SKUs for both Temu 1 and Temu 2 (All Rows / Parents / SKUs filter).

            // 2. Get all SKUs from product master
            $skus = $productMasters->pluck("sku")
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->lmpSkuGroupService->prepareForSkus($skus);

            // Helper function to normalize SKU for matching
            $normalizeSku = function($sku) {
                $sku = strtoupper(trim($sku));
                // Normalize common variations: "2 PCS" -> "2PCS", "2 PC" -> "2PC"
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                // Remove extra spaces
                $sku = preg_replace('/\s+/', ' ', $sku);
                return $sku;
            };

            // Loose normalization: alphanumeric only, uppercase. Used as a final
            // fallback so SKUs that differ only in punctuation (dashes, slashes,
            // underscores) or whitespace still match. Examples that all collapse
            // to the same loose key: "ABC-123", "ABC 123", "abc_123", "abc123".
            $normalizeSkuLoose = function ($sku) {
                $s = strtoupper(trim((string) $sku));
                if ($s === '') {
                    return '';
                }
                return preg_replace('/[^A-Z0-9]/', '', $s);
            };

            // Create normalized SKU lookup for ProductMaster
            $normalizedSkuMap = [];
            foreach ($skus as $sku) {
                $normalizedSkuMap[$normalizeSku($sku)] = $sku;
            }

            // 3. Listing / price / stock source:
            //    Temu 1 → temu_metrics (API)
            //    Temu 2 → temu2_metrics (API), then overlay Base price from temu2_pricing
            //             matched by SKU to CP Master (product_master).
            $mapMetricToPricingRow = static function ($m) {
                return (object) [
                    'sku' => $m->sku,
                    'product_name' => '',
                    'category' => '',
                    'variation' => '',
                    'quantity' => (int) ($m->quantity ?? 0),
                    'base_price' => $m->base_price,
                    'status' => '',
                    'detail_status' => '',
                    'goods_id' => $m->goods_id,
                    'sku_id' => $m->sku_id,
                    'date_created' => '',
                    'recommended_base_price' => $m->recommended_base_price ?? null,
                    'product_clicks_l30' => $m->product_clicks_l30 ?? null,
                ];
            };

            if ($isTemu2Pricing) {
                $allPricingData = Schema::hasTable('temu2_metrics')
                    ? Temu2Metric::query()
                        ->select(['sku', 'sku_id', 'goods_id', 'base_price', 'quantity', 'recommended_base_price', 'product_clicks_l30'])
                        ->get()
                        ->map($mapMetricToPricingRow)
                    : collect();
            } else {
                $allPricingData = TemuMetric::query()
                    ->select(['sku', 'sku_id', 'goods_id', 'base_price', 'quantity', 'recommended_base_price', 'product_clicks_l30'])
                    ->get()
                    ->map($mapMetricToPricingRow);
            }

            // Build pricing data with normalized matching to CP Master SKUs
            $pricingData = collect();
            // Build normalized SKU map for missing column check (same as pricing logic)
            $temuPricingSkusNormalized = collect();
            
            foreach ($allPricingData as $pricing) {
                $normalizedPricingSku = $normalizeSku($pricing->sku);
                $temuPricingSkusNormalized->push($normalizedPricingSku);
                
                if (isset($normalizedSkuMap[$normalizedPricingSku])) {
                    $originalSku = $normalizedSkuMap[$normalizedPricingSku];
                    $pricingData[$originalSku] = $pricing;
                }
            }

            // Temu 2: Base Price + Goods ID from temu2_pricing matched by SKU → CP Master.
            // Goods ID from this sheet is the join key for temu2_view_data Views (product_clicks).
            $temu2PricingGoodsIdBySku = []; // original CP Master sku => normalized goods_id
            if ($isTemu2Pricing && Schema::hasTable('temu2_pricing')) {
                $sheetRows = Temu2Pricing::query()
                    ->select(['sku', 'sku_id', 'goods_id', 'base_price', 'quantity', 'product_name', 'category', 'variation', 'status', 'detail_status'])
                    ->get();
                foreach ($sheetRows as $sheet) {
                    $normalizedSheetSku = $normalizeSku($sheet->sku);
                    if ($normalizedSheetSku === '' || ! isset($normalizedSkuMap[$normalizedSheetSku])) {
                        continue;
                    }
                    $originalSku = $normalizedSkuMap[$normalizedSheetSku];
                    $sheetGoodsId = TemuGoodsIdHelper::normalizeKey($sheet->goods_id);
                    $temuPricingSkusNormalized->push($normalizedSheetSku);
                    if ($sheetGoodsId) {
                        $temu2PricingGoodsIdBySku[$originalSku] = $sheetGoodsId;
                    }
                    $existing = $pricingData->get($originalSku);
                    if ($existing) {
                        if ($sheet->base_price !== null && (float) $sheet->base_price > 0) {
                            $existing->base_price = $sheet->base_price;
                        }
                        if ($sheetGoodsId) {
                            $existing->goods_id = $sheetGoodsId;
                        }
                        if (! empty($sheet->sku_id)) {
                            $existing->sku_id = $sheet->sku_id;
                        }
                        if ((int) ($sheet->quantity ?? 0) > 0) {
                            $existing->quantity = (int) $sheet->quantity;
                        }
                        $pricingData[$originalSku] = $existing;
                    } else {
                        $pricingData[$originalSku] = (object) [
                            'sku' => $originalSku,
                            'product_name' => (string) ($sheet->product_name ?? ''),
                            'category' => (string) ($sheet->category ?? ''),
                            'variation' => (string) ($sheet->variation ?? ''),
                            'quantity' => (int) ($sheet->quantity ?? 0),
                            'base_price' => $sheet->base_price,
                            'status' => (string) ($sheet->status ?? ''),
                            'detail_status' => (string) ($sheet->detail_status ?? ''),
                            'goods_id' => $sheetGoodsId,
                            'sku_id' => $sheet->sku_id,
                            'date_created' => '',
                            'recommended_base_price' => null,
                            'product_clicks_l30' => null,
                        ];
                    }
                }
            }
            
            // Flip for quick lookup
            $temuPricingSkusNormalized = $temuPricingSkusNormalized->flip();

            // Side lookup: Temu 2 page "Temu 1 Price" from API metrics (not sheet)
            $temu1PricingBySku = [];
            if ($isTemu2Pricing) {
                $temu1All = TemuMetric::select(['sku', 'base_price'])->get();
                foreach ($temu1All as $row) {
                    $n = $normalizeSku($row->sku);
                    if (isset($normalizedSkuMap[$n])) {
                        $temu1PricingBySku[$normalizedSkuMap[$n]] = $row;
                    }
                }
            }
            
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            $normalizedPmSkus = collect($skus)->mapWithKeys(function ($sku) use ($normalizeSku) {
                return [$normalizeSku($sku) => $sku];
            })->all();
            $l30ByNormalizedSku = array_fill_keys(array_keys($normalizedPmSkus), 0);
            $noSpaceToNormalized = [];
            foreach (array_keys($normalizedPmSkus) as $nk) {
                $noSpace = str_replace(' ', '', $nk);
                if ($noSpace !== '') {
                    $noSpaceToNormalized[$noSpace] = $nk;
                }
            }
            
            if ($isTemu2Pricing) {
                $hasL7Rows = $isL7Period
                    && Schema::hasTable('temu2_daily_data_l7')
                    && Temu2DailyDataL7::query()->exists();

                if ($hasL7Rows) {
                    $orderRows = Temu2DailyDataL7::select('contribution_sku', 'quantity_purchased')->get();
                } elseif (Schema::hasTable('temu2_daily_data')) {
                    $orderRowsQuery = Temu2DailyData::select('contribution_sku', 'quantity_purchased');
                    if ($isL7Period) {
                        $orderRowsQuery->where('purchase_date', '>=', Carbon::now()->subDays(7));
                    }
                    $orderRows = $orderRowsQuery->get();
                } else {
                    $orderRows = collect();
                }
            } else {
                $hasL7Rows = $isL7Period;
               
                if ($isL7Period) {
                    $todayPst = Carbon::now(TemuShopifySalesService::PST);
                    $apiStart = $todayPst->copy()->subDays(6)->startOfDay();
                    $apiEnd = $todayPst->copy()->endOfDay();
                } else {
                    [$apiStart, $apiEnd] = TemuShopifySalesService::channelMasterL30Window();
                }
                $orderRows = collect(TemuShopifySalesService::getOrdersTableRows($apiStart, $apiEnd))
                    ->map(fn ($r) => (object) $r);
            }
            foreach ($orderRows as $row) {
                $raw = trim((string) ($row->contribution_sku ?? ''));
                if ($raw === '') {
                    continue;
                }
                $n = $normalizeSku($raw);
                $qty = (int) ($row->quantity_purchased ?? 0);
                if (isset($l30ByNormalizedSku[$n])) {
                    $l30ByNormalizedSku[$n] += $qty;
                } else {
                    $nNoSpace = str_replace(' ', '', $n);
                    if (isset($noSpaceToNormalized[$nNoSpace])) {
                        $l30ByNormalizedSku[$noSpaceToNormalized[$nNoSpace]] += $qty;
                    }
                }
            }
            $temuSalesData = collect($skus)->mapWithKeys(function ($sku) use ($l30ByNormalizedSku, $normalizeSku) {
                $temuL30 = (int) ($l30ByNormalizedSku[$normalizeSku($sku)] ?? 0);
                return [$sku => (object) ['sku' => $sku, 'temu_l30' => $temuL30]];
            });

            // L60 = prior 30-day window. Temu 1: temu_orders (days 31–60); Temu 2: temu2_daily_data_l60.
            $l60ByNormalizedSku = array_fill_keys(array_keys($normalizedPmSkus), 0);
            if ($isTemu2Pricing) {
                $orderRowsL60 = Schema::hasTable('temu2_daily_data_l60')
                    ? Temu2DailyDataL60::select('contribution_sku', 'quantity_purchased')->get()
                    : collect();
            } else {
                [$l60Start, $l60End] = TemuShopifySalesService::channelMasterL60Window();
                $orderRowsL60 = collect(TemuShopifySalesService::getOrdersTableRows($l60Start, $l60End))
                    ->map(fn ($r) => (object) $r);
            }
            foreach ($orderRowsL60 as $row) {
                $raw = trim((string) ($row->contribution_sku ?? ''));
                if ($raw === '') {
                    continue;
                }
                $n = $normalizeSku($raw);
                $qty = (int) ($row->quantity_purchased ?? 0);
                if (isset($l60ByNormalizedSku[$n])) {
                    $l60ByNormalizedSku[$n] += $qty;
                } else {
                    $nNoSpace = str_replace(' ', '', $n);
                    if (isset($noSpaceToNormalized[$nNoSpace])) {
                        $l60ByNormalizedSku[$noSpaceToNormalized[$nNoSpace]] += $qty;
                    }
                }
            }

            $normalizedPmSet = collect($skus)->mapWithKeys(function ($s) use ($normalizeSku) {
                return [$normalizeSku($s) => true];
            })->all();
            if ($isTemu2Pricing) {
                if ($hasL7Rows) {
                    $salesOrderRows = Temu2DailyDataL7::query()
                        ->get(['contribution_sku', 'order_id', 'quantity_purchased', 'base_price_total']);
                } elseif (Schema::hasTable('temu2_daily_data')) {
                    $salesSource = Temu2DailyData::query();
                    if ($isL7Period) {
                        $salesSource->where('purchase_date', '>=', Carbon::now()->subDays(7));
                    }
                    $salesOrderRows = $salesSource->get(['contribution_sku', 'order_id', 'quantity_purchased', 'base_price_total']);
                } else {
                    $salesOrderRows = collect();
                }
            } else {
                // Reuse the same temu_orders window already loaded for the L30/L7 table rows.
                $salesOrderRows = $orderRows;
            }
            // ProductMaster lookups for order-level GPFT/GROI (Temu 2 matches /temu2-tabulator).
            $pmBySku = $productMasters->keyBy('sku');
            $pmByNormalized = $productMasters->keyBy(function ($pm) use ($normalizeSku) {
                return $normalizeSku($pm->sku ?? '');
            });
            $pmByNoSpace = $productMasters->keyBy(function ($pm) use ($normalizeSku) {
                return str_replace(' ', '', $normalizeSku($pm->sku ?? ''));
            });

            $salesTotalOrders = 0;
            $salesTotalQuantity = 0;
            $salesTotalRevenue = 0.0;
            $salesTotalPftFull = 0.0;
            $salesTotalPft = 0.0;
            $salesTotalCogs = 0.0;
            foreach ($salesOrderRows as $row) {
                $rawSku = trim((string) ($row->contribution_sku ?? ''));
                $orderId = trim((string) ($row->order_id ?? ''));
                if ($rawSku === '' || $orderId === '') {
                    continue;
                }

                // Keep summary SKU matching identical to row-level L30/L7 mapping
                // so badge totals stay in sync with the table.
                $normalizedRowSku = $normalizeSku($rawSku);
                $normalizedRowSkuNoSpace = str_replace(' ', '', $normalizedRowSku);
                $matchesPmSku = isset($normalizedPmSet[$normalizedRowSku]) || isset($noSpaceToNormalized[$normalizedRowSkuNoSpace]);
                if (!$matchesPmSku) {
                    continue;
                }

                $salesTotalOrders++;
                $qty = (int)($row->quantity_purchased ?? 0);
                $base = (float)($row->base_price_total ?? 0);
                $salesTotalQuantity += $qty;

                // Full Temu Price = (base × 1.1364); +$2.99 if that ≤ $26.99 — Sales / GPFT
                $fullPrice = TemuShopifySalesService::computeFullTemuPrice($base);
                $salesTotalRevenue += $fullPrice * $qty;

                if ($isTemu2Pricing && $qty > 0 && $base > 0) {
                    $pm = $pmBySku[$rawSku]
                        ?? $pmByNormalized[$normalizedRowSku]
                        ?? $pmByNoSpace[$normalizedRowSkuNoSpace]
                        ?? null;
                    $orderLp = 0.0;
                    $orderTemuShip = 0.0;
                    if ($pm) {
                        $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                        if (!is_array($values)) {
                            $values = [];
                        }
                        foreach ($values as $k => $v) {
                            if (strtolower((string) $k) === 'lp') {
                                $orderLp = (float) $v;
                                break;
                            }
                        }
                        if ($orderLp === 0.0 && isset($pm->lp)) {
                            $orderLp = (float) $pm->lp;
                        }
                        $orderTemuShip = ProductMasterTemuShip::forPricing($values, $pm);
                    }
                    // Same as /temu-decrease: GPFT $ on Full Price; GROI $ on Temu R Price
                    $rPrice = $base <= 26.99 ? ($base + 2.99) : $base;
                    $salesTotalPftFull += ($fullPrice * $percentage - $orderLp - $orderTemuShip) * $qty;
                    $salesTotalPft += TemuShopifySalesService::computeGroiProfit($rPrice, $percentage, $orderLp, $orderTemuShip) * $qty;
                    $salesTotalCogs += $orderLp * $qty;
                }
            }
            $salesGpftPercent = $salesTotalRevenue > 0 ? ($salesTotalPftFull / $salesTotalRevenue) * 100 : 0.0;
            $salesGroiPercent = $salesTotalCogs > 0 ? ($salesTotalPft / $salesTotalCogs) * 100 : 0.0;
            $salesSummary = [
                'total_orders' => $salesTotalOrders,
                'total_quantity' => $salesTotalQuantity,
                'total_revenue' => round($salesTotalRevenue, 2),
                'total_pft' => round($salesTotalPft, 2),
                'total_cogs' => round($salesTotalCogs, 2),
                'gpft_percent' => round($salesGpftPercent, 1),
                'groi_percent' => round($salesGroiPercent, 1),
            ];

            // Views (Temu 1): Seller Center sheet → temu_view_data.product_clicks (by goods_id).
            // Temu OpenAPI has no product-page views; ads clkCntAll is ad-only (often 0 with organic sales).
            // Fallback: temu_metrics.product_clicks_l30 (Ads API) when sheet has no row for that goods_id.
            // Temu 2: temu2_view_data sheet.
            if ($isTemu2Pricing) {
                $viewData = Schema::hasTable('temu2_view_data')
                    ? Temu2ViewData::selectRaw('goods_id, SUM(product_impressions) as product_impressions, SUM(visitor_impressions) as visitor_impressions, SUM(product_clicks) as product_clicks, SUM(visitor_clicks) as visitor_clicks, AVG(ctr) as ctr')
                        ->groupBy('goods_id')
                        ->get()
                        ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                    : collect();

                $viewDataL7 = collect();
                $viewDataL7ToL14 = collect();
            } else {
                $viewData = Schema::hasTable('temu_view_data')
                    ? TemuViewData::selectRaw('goods_id, SUM(product_impressions) as product_impressions, SUM(visitor_impressions) as visitor_impressions, SUM(product_clicks) as product_clicks, SUM(visitor_clicks) as visitor_clicks, AVG(ctr) as ctr')
                        ->groupBy('goods_id')
                        ->get()
                        ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                    : collect();

                // View 7: Ads API L7 report clicks (ad clicks only; no L7 sheet table restored yet)
                $viewL7ByGoods = [];
                if (Schema::hasTable('temu_ads_api_reports')) {
                    TemuAdsApiReport::query()
                        ->activeAds()
                        ->where('period', 'L7')
                        ->whereNotNull('goods_id')
                        ->get(['goods_id', 'clicks'])
                        ->each(function ($row) use (&$viewL7ByGoods) {
                            $key = TemuGoodsIdHelper::normalizeKey($row->goods_id);
                            if ($key === '' || $key === null) {
                                return;
                            }
                            $viewL7ByGoods[$key] = (object) [
                                'product_clicks' => (int) ($row->clicks ?? 0),
                            ];
                        });
                }
                $viewDataL7 = collect($viewL7ByGoods);

                // Views 14 (L7–L14): no Partner API window — always empty for Temu 1
                $viewDataL7ToL14 = collect();
            }

            $goodsIds = $pricingData->pluck('goods_id')->filter()->unique()->values()->all();
            $campaignRange = $isL7Period ? 'L7' : 'L30';

            if ($isTemu2Pricing) {
                // Temu 2 still uses /temu2/ads campaign-report uploads.
                $adsViewsData = Schema::hasTable('temu_ads_views')
                    ? TemuAdsView::selectRaw('goods_id, SUM(clicks) as ads_views')
                        ->groupBy('goods_id')
                        ->get()
                        ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                    : collect();

                [
                    $campaignReportL30,
                    $campaignReportL30BySku,
                    $campaignReportL30BySkuLoose,
                    $campaignReportL60,
                    $campaignReportL60BySku,
                    $campaignReportL60BySkuLoose,
                    $campaignReportL7,
                    $campaignReportL7BySku,
                    $campaignReportL7BySkuLoose,
                ] = $this->temuCampaignReportSheetIndexes($campaignRange, $normalizeSku, $normalizeSkuLoose);
            } else {
                // Temu 1 ads: temu_ads_api_reports only (no sheet / upload).
                $adsViewsData = $this->temuAdsApiViewsByGoods($campaignRange);
                [
                    $campaignReportL30,
                    $campaignReportL30BySku,
                    $campaignReportL30BySkuLoose,
                    $campaignReportL60,
                    $campaignReportL60BySku,
                    $campaignReportL60BySkuLoose,
                    $campaignReportL7,
                    $campaignReportL7BySku,
                    $campaignReportL7BySkuLoose,
                ] = $this->temuAdsApiReportIndexes($campaignRange, $normalizeSku, $normalizeSkuLoose);
            }

            // Fetch saved SPRICE values (Temu 2 uses temu2_data_view)
            $temuDataViewData = ($isTemu2Pricing ? Temu2DataView::query() : TemuDataView::query())
                ->whereIn('sku', $skus)
                ->select('sku', 'value')
                ->get()
                ->keyBy('sku');

            // R Prc fallback indexes (Temu 1 already has recommended on $pricingData items)
            $recommendedBySkuId = [];
            $recommendedBySku = [];
            if ($isTemu2Pricing) {
                TemuMetric::query()
                    ->select('sku', 'sku_id', 'recommended_base_price')
                    ->whereNotNull('recommended_base_price')
                    ->get()
                    ->each(function ($row) use (&$recommendedBySkuId, &$recommendedBySku, $normalizeSku) {
                        if ($row->sku_id !== null && $row->sku_id !== '') {
                            $recommendedBySkuId[(string) $row->sku_id] = $row->recommended_base_price;
                        }
                        if ($row->sku !== null && $row->sku !== '') {
                            $recommendedBySku[$normalizeSku($row->sku)] = $row->recommended_base_price;
                        }
                    });
            } else {
                foreach ($allPricingData as $row) {
                    if (!isset($row->recommended_base_price) || $row->recommended_base_price === null) {
                        continue;
                    }
                    if ($row->sku_id !== null && $row->sku_id !== '') {
                        $recommendedBySkuId[(string) $row->sku_id] = $row->recommended_base_price;
                    }
                    if ($row->sku !== null && $row->sku !== '') {
                        $recommendedBySku[$normalizeSku($row->sku)] = $row->recommended_base_price;
                    }
                }
            }

            $amazonData = $isTemu2Pricing
                ? collect()
                : AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');

            // Std Prc — amazon_data_view.STANDARD_PRICE (same shared store as /amazon-tabulator-view).
            // Include Sku Link LMP siblings so a Temu SKU inherits the Amazon SP when the
            // value is stored on a linked sibling (same as /amazon-tabulator-view).
            $stdLookupSkus = $skus;
            foreach ($skus as $pageSku) {
                foreach ($this->lmpSkuGroupService->groupContaining((string) $pageSku) as $memberSku) {
                    $stdLookupSkus[] = $memberSku;
                }
            }
            $stdLookupSkus = array_values(array_unique(array_filter(array_map(
                static fn ($s) => trim((string) $s),
                $stdLookupSkus
            ))));

            $amazonStandardPrices = [];
            $indexAmazonStdPrc = function ($skuKey, $std) use (&$amazonStandardPrices, $normalizeSku) {
                if (! is_numeric($std) || (float) $std <= 0) {
                    return;
                }
                $rounded = round((float) $std, 2);
                $raw = trim((string) $skuKey);
                if ($raw === '') {
                    return;
                }
                $amazonStandardPrices[$raw] = $rounded;
                $amazonStandardPrices[strtoupper($raw)] = $rounded;
                $norm = $normalizeSku($raw);
                if ($norm !== '') {
                    $amazonStandardPrices[$norm] = $rounded;
                }
            };

            foreach (AmazonDataView::whereIn('sku', $stdLookupSkus)->get(['sku', 'value']) as $adv) {
                $val = is_array($adv->value)
                    ? $adv->value
                    : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                $indexAmazonStdPrc($adv->sku, $val['STANDARD_PRICE'] ?? null);
            }

            // Same as /amazon-tabulator-view: also match rows whose SKU only differs by
            // spacing / PCS suffix so Temu shows the same Std Prc Amazon does.
            $normalizedStdSet = [];
            foreach ($stdLookupSkus as $s) {
                $n = $normalizeSku($s);
                if ($n !== '') {
                    $normalizedStdSet[$n] = true;
                }
            }
            if ($normalizedStdSet !== []) {
                foreach (AmazonDataView::query()->select(['sku', 'value'])->get() as $adv) {
                    $n = $normalizeSku($adv->sku);
                    if ($n === '' || ! isset($normalizedStdSet[$n])) {
                        continue;
                    }
                    $val = is_array($adv->value)
                        ? $adv->value
                        : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                    $indexAmazonStdPrc($adv->sku, $val['STANDARD_PRICE'] ?? null);
                    $indexAmazonStdPrc($n, $val['STANDARD_PRICE'] ?? null);
                }
            }

            $lookupStdPrc = function ($candidate) use ($amazonStandardPrices, $normalizeSku) {
                $raw = trim((string) $candidate);
                if ($raw === '') {
                    return null;
                }

                return $amazonStandardPrices[$raw]
                    ?? $amazonStandardPrices[strtoupper($raw)]
                    ?? $amazonStandardPrices[$normalizeSku($raw)]
                    ?? null;
            };

            // PRMT%/CPN%/DSC%/Appr/Push Prc — temu / temu2_promo_pricing (site-specific)
            $promoChannel = $isTemu2Pricing ? 'temu2' : 'temu';
            $promoMap = app(ChannelPromoPricingService::class)->mapForSkus($promoChannel, $skus);

            $ebayData = $isTemu2Pricing
                ? collect()
                : EbayMetric::whereIn('sku', $skus)->select('sku', 'ebay_price')->get()->keyBy('sku');

            // eBay 2 listing price (from ebay_2_metrics.ebay_price). Same shape as $ebayData so
            // the per-row lookup mirrors the eBay 1 path; loaded for Temu 1 only (Temu 2 pricing
            // intentionally hides marketplace comparison columns, same as a_price / e_price).
            $ebay2Data = $isTemu2Pricing
                ? collect()
                : Ebay2Metric::whereIn('sku', $skus)->select('sku', 'ebay_price')->get()->keyBy('sku');

            // Temu 1: temu_listing_statuses. Temu 2: same keys live in temu2_data_view JSON (value).
            $statusData = $isTemu2Pricing
                ? collect()
                : TemuListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

            // Fetch Temu LMP data (shared temu_lmp — Temu 1 & Temu 2 decrease)
            $allTemuLmp = Schema::hasTable('temu_lmp') ? TemuLmp::all() : collect();
            $temuLmpByNormalizedSku = [];
            foreach ($allTemuLmp as $row) {
                $nk = $normalizeSku($row->sku);
                if (!isset($temuLmpByNormalizedSku[$nk])) {
                    $temuLmpByNormalizedSku[$nk] = $row;
                }
            }

            // NRP (forecast_analysis.nr) per SKU — same source the /forecast.analysis page
            // shows in its NRP column. Values: REQ (default) / NR (= 2BDC on screen) / LATER.
            // Keyed by normalized SKU so spacing variants ("DP 200 1 Pcs" vs "DP200 1PC") match.
            // When multiple rows exist for the same SKU we prefer one with a non-empty `nr`,
            // mirroring the precedence rule in ForecastAnalysisController::forecastAnalysis().
            $nrByNormalizedSku = [];
            DB::table('forecast_analysis')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->select('sku', 'nr')
                ->get()
                ->each(function ($row) use (&$nrByNormalizedSku, $normalizeSku) {
                    $nk = $normalizeSku($row->sku);
                    if ($nk === '') return;
                    $val = $row->nr !== null ? strtoupper(trim((string) $row->nr)) : '';
                    if (!in_array($val, ['REQ', 'NR', 'LATER'], true)) {
                        $val = '';
                    }
                    // Prefer a populated value over an empty one (matches ForecastAnalysisController)
                    if (!isset($nrByNormalizedSku[$nk]) || ($nrByNormalizedSku[$nk] === '' && $val !== '')) {
                        $nrByNormalizedSku[$nk] = $val;
                    }
                });

            // 4. Process data - iterate through ALL product masters
            $processedData = $productMasters->map(function($productMaster) use ($pricingData, $shopifyData, $temuSalesData, $l60ByNormalizedSku, $normalizeSku, $normalizeSkuLoose, $viewData, $viewDataL7, $viewDataL7ToL14, $adsViewsData, $temuDataViewData, $amazonData, $ebayData, $ebay2Data, $recommendedBySkuId, $recommendedBySku, $percentage, $temuPricingSkusNormalized, $statusData, $campaignReportL30, $campaignReportL30BySku, $campaignReportL30BySkuLoose, $campaignReportL60, $campaignReportL60BySku, $campaignReportL60BySkuLoose, $campaignReportL7, $campaignReportL7BySku, $campaignReportL7BySkuLoose, $temuLmpByNormalizedSku, $nrByNormalizedSku, $isTemu2Pricing, $temu1PricingBySku, $temu2PricingGoodsIdBySku, $promoMap, $lookupStdPrc) {
                $sku = $productMaster->sku;
                
                // Get related data (may be null if not in Temu)
                $item = $pricingData->get($sku);
                $shopify = $shopifyData->get($sku);
                $temuSales = $temuSalesData->get($sku);
                
                // Temu Stock: Temu 1 = API temu_metrics.quantity; Temu 2 = sheet quantity
                $temuStock = $item ? ($item->quantity ?? 0) : 0;
                
                // Get values from product master - check Values JSON first, then direct properties
                $lp = 0;
                $temuShip = 0;
                
                if ($productMaster) {
                    // Check Values JSON first (like eBay does)
                    $values = is_array($productMaster->Values) 
                        ? $productMaster->Values 
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    
                    // Get LP from Values or direct property
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($productMaster->lp)) {
                        $lp = floatval($productMaster->lp);
                    }
                    if ($lp === 0 && isset($productMaster->LP)) {
                        $lp = floatval($productMaster->LP);
                    }
                    
                    // Temu ship: use stored per-SKU value if it already exists; otherwise regular ship.
                    $temuShip = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $productMaster);
                }
                
                // Get image_path (like eBay does)
                $imagePath = $shopify->image_src ?? ($productMaster ? ($productMaster->Values['image_path'] ?? ($productMaster->image_path ?? null)) : null);
                
                // Get inventory from ShopifySku (like eBay does)
                $inventory = $shopify->inv ?? 0;
                $l30 = $shopify->quantity ?? 0;
                
                // Get Temu L30 (last 30 days sales from Temu daily data)
                $temuL30 = $temuSales ? (int) ($temuSales->temu_l30 ?? 0) : 0;
                
                // Views / O Clicks by goods_id:
                // Temu 2 → Goods ID from temu2_pricing, then SUM(product_clicks) from temu2_view_data
                //           across every uploaded file/date for that Goods ID (no Ads API fallback).
                // Temu 1 → temu_view_data sheet; Ads API fallback when sheet has no row.
                if ($isTemu2Pricing) {
                    $goodsId = $temu2PricingGoodsIdBySku[$sku]
                        ?? TemuGoodsIdHelper::normalizeKey($item->goods_id ?? null);
                } else {
                    $goodsId = $item ? TemuGoodsIdHelper::normalizeKey($item->goods_id) : null;
                }
                $goodsIdKeyForViews = $goodsId ? TemuGoodsIdHelper::normalizeKey($goodsId) : null;
                $viewDataItem = $goodsIdKeyForViews ? $viewData->get($goodsIdKeyForViews) : null;
                // Sheet SUM(product_clicks) for this Goods ID (all uploaded dates/files).
                $sheetProductClicks = $viewDataItem ? (int) $viewDataItem->product_clicks : 0;
                $oClicks = $sheetProductClicks;
                if ($isTemu2Pricing) {
                    // Views column = uploaded sheet sum only, matched via temu2_pricing.goods_id
                    $productClicks = $sheetProductClicks;
                } else {
                    $productClicks = $viewDataItem
                        ? $sheetProductClicks
                        : (int) ($item->product_clicks_l30 ?? 0);
                }
                $ctr = $viewDataItem ? ($viewDataItem->ctr ?? 0) : 0;

                // View 7: Temu 1 from ads API L7; Temu 2 unused (0)
                $viewDataL7Item = $goodsIdKeyForViews ? $viewDataL7->get($goodsIdKeyForViews) : null;
                $productClicksL7 = $viewDataL7Item ? (int) $viewDataL7Item->product_clicks : 0;
                $l7VsL30Pct = ($productClicks > 0 && $productClicksL7 > 0)
                    ? round((($productClicksL7 / 7) / ($productClicks / 30)) * 100, 2)
                    : 0;

                // Views 14: no API for Temu 1 L7–L14 window
                $viewDataL7ToL14Item = $goodsIdKeyForViews ? $viewDataL7ToL14->get($goodsIdKeyForViews) : null;
                $productClicksL7ToL14 = $viewDataL7ToL14Item ? (int) $viewDataL7ToL14Item->product_clicks : 0;
                $adsViewsItem = $goodsIdKeyForViews ? $adsViewsData->get($goodsIdKeyForViews) : null;
                $adsViews = $adsViewsItem ? (int) ($adsViewsItem->ads_views ?? 0) : 0;
                
                // Join keys: normalize goods_id so campaign reports match temu_pricing (Excel float issues)
                $goodsIdKey = $goodsId ? TemuGoodsIdHelper::normalizeKey($goodsId) : null;

                // Ads match chain (most specific → most lenient):
                //   1. goods_id (Temu 1: Ads API; Temu 2: campaign-report upload)
                //   2. SKU under strict normalization (uppercase, trim, "PCS"/"PIECES" → "PC")
                //   3. SKU under loose normalization (alphanumeric only).
                $campaignReportItem = ($goodsIdKey ? $campaignReportL30->get($goodsIdKey) : null)
                    ?? $campaignReportL30BySku->get($normalizeSku($sku))
                    ?? $campaignReportL30BySkuLoose->get($normalizeSkuLoose($sku));

                $spend         = $campaignReportItem ? round((float) ($campaignReportItem->spend_l30 ?? 0), 2)        : 0.0;
                $adClicks      = $campaignReportItem ? (int) ($campaignReportItem->clicks_l30 ?? 0)                   : 0;
                $l7CampaignItem = ($goodsIdKey ? $campaignReportL7->get($goodsIdKey) : null)
                    ?? $campaignReportL7BySku->get($normalizeSku($sku))
                    ?? $campaignReportL7BySkuLoose->get($normalizeSkuLoose($sku));
                $adClicksL7 = $l7CampaignItem ? (int) ($l7CampaignItem->clicks_l7 ?? 0) : 0;
                $acosAd        = $campaignReportItem ? round((float) ($campaignReportItem->acos_ad_l30 ?? 0), 2)      : 0.0;
                $netRoas       = $campaignReportItem ? round((float) ($campaignReportItem->net_roas_l30 ?? $campaignReportItem->roas_l30 ?? 0), 2) : 0.0;
                $impressionsVal= $campaignReportItem ? (int) ($campaignReportItem->impressions_l30 ?? 0)              : 0;
                $addToCartVal  = $campaignReportItem ? (int) ($campaignReportItem->add_to_cart_l30 ?? 0)              : 0;
                $target        = $campaignReportItem ? (float) ($campaignReportItem->target_l30 ?? 0)                 : 0.0;

                $inRoasL30  = $campaignReportItem ? round((float) $campaignReportItem->in_roas_l30, 2) : 0;
                $outRoasL30 = $campaignReportItem ? round((float) $campaignReportItem->roas_l30, 2)    : 0;
                $spendL30   = $spend;
                $clicksL30  = $adClicks;
                $adSalesL30 = $campaignReportItem ? round((float) ($campaignReportItem->ad_sales_l30 ?? 0), 2) : 0;
                $adSoldL30  = $campaignReportItem ? (int) ($campaignReportItem->ad_sold_l30 ?? 0)              : 0;
                $campaignStatus = null;
                // Get campaign report data (L60) for spend, ad sold, ad sales —
                // same goods_id → strict SKU → loose SKU fallback chain as L30.
                $l60Item = ($goodsIdKey ? $campaignReportL60->get($goodsIdKey) : null)
                    ?? $campaignReportL60BySku->get($normalizeSku($sku))
                    ?? $campaignReportL60BySkuLoose->get($normalizeSkuLoose($sku));
                $spendL60 = $l60Item ? round((float)$l60Item->spend_l60, 2) : 0;
                $adSoldL60 = $l60Item ? (int)($l60Item->ad_sold_l60 ?? 0) : 0;
                $adSalesL60 = $l60Item ? round((float)($l60Item->ad_sales_l60 ?? 0), 2) : 0;
                $l60Acos = ($adSalesL60 > 0) ? round(($spendL60 / $adSalesL60) * 100, 2) : null;
                $l60VsL30 = ($l60Acos !== null && $l60Acos != 0) ? round((($acosAd - $l60Acos) / $l60Acos) * 100, 2) : null;
                // Temu L60 sales from temu_daily_data_l60 table (L60 Sales upload); same aggregation as L30
                $temuL60FromSales = (int) ($l60ByNormalizedSku[$normalizeSku($sku)] ?? 0);
                if ($campaignReportItem && isset($campaignReportItem->status_l30)
                    && trim((string) $campaignReportItem->status_l30) !== ''
                    && $campaignReportItem->status_l30 !== 'NULL') {
                    // Temu 1: same Status as /temu/ads (Active / Inactive / No ad / Not sync / Deleted)
                    $campaignStatus = trim((string) $campaignReportItem->status_l30);
                } else {
                    $hasCampaign = $isTemu2Pricing && $goodsId && ($spend > 0 || $adClicks > 0 || $campaignReportItem);
                    $campaignStatus = $hasCampaign ? 'Active' : 'Not Created';
                }
                
                // Calculate OVL30 and Dil%
                $ovl30 = $l30;
                $dilPercent = ($l30 && $inventory > 0) ? round(($l30 / $inventory) * 100, 2) : 0;
                
                // Calculate profit - only if item exists in Temu
                // Empty catalog base_price → recommended_base_price (e.g. CS 04 2W)
                $catalogBase = $item ? (float) ($item->base_price ?? 0) : 0;
                $pricingSkuIdEarly = $item && $item->sku_id !== null && $item->sku_id !== ''
                    ? (string) $item->sku_id
                    : null;
                $recommendedForBase = ($item !== null ? ($item->recommended_base_price ?? null) : null)
                    ?? ($pricingSkuIdEarly !== null && isset($recommendedBySkuId[$pricingSkuIdEarly])
                        ? $recommendedBySkuId[$pricingSkuIdEarly]
                        : ($recommendedBySku[$normalizeSku($sku)] ?? null));
                $basePrice = TemuShopifySalesService::resolveListingBasePrice($catalogBase, $recommendedForBase);
                
                // Calculate Temu Price (Base Price + 2.99 if <= 26.99) - only if item exists in Temu
                if ($item && $basePrice > 0) {
                    $temuPrice = $basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice; // Temu R Price
                } else {
                    $temuPrice = 0;
                }
                // Full Temu Price = (base × 1.1364); +$2.99 if ≤ $26.99 — GPFT / NPFT; GROI stays on R Price
                $temuFullPrice = TemuShopifySalesService::computeFullTemuPrice((float) $basePrice);

                // Temu 1 base/listing price for the same SKU. Only populated on the Temu 2
                // endpoint (see $temu1PricingBySku build above). Same +$2.99 adjustment as
                // the Temu Price column so both numbers are directly comparable.
                $temu1Item = $temu1PricingBySku[$sku] ?? null;
                $temu1BasePrice = $temu1Item ? (float) ($temu1Item->base_price ?? 0) : 0;
                $temu1Price = $temu1BasePrice > 0
                    ? ($temu1BasePrice <= 26.99 ? $temu1BasePrice + 2.99 : $temu1BasePrice)
                    : 0;
                
                // Dollar profit / GROI on Temu R Price (no 0.88)
                $profit = TemuShopifySalesService::computeGroiProfit((float) $temuPrice, (float) $percentage, (float) $lp, (float) $temuShip);
                $profitPercent = $temuFullPrice > 0
                    ? (($temuFullPrice * $percentage - $lp - $temuShip) / $temuFullPrice) * 100
                    : 0;
                $roiPercent = TemuShopifySalesService::computeGroiPercent((float) $temuPrice, (float) $percentage, (float) $lp, (float) $temuShip);
                
                // CVR% = Temu L30 / Views × 100.
                // Views = Seller Center clicks + Ads Views (same number as the Views column).
                $tClicks = (int) $productClicks + (int) $adClicks;
                $tClicksL7 = (int) $productClicksL7 + (int) $adClicksL7;
                // T Click Growth % = ((T7 daily pace / T30 daily pace) − 1) × 100
                // 0 = flat, >0 growing, <0 declining. Needs both windows > 0.
                $tClicksGrowth = ($tClicks > 0 && $tClicksL7 > 0)
                    ? round(((($tClicksL7 / 7) / ($tClicks / 30)) - 1) * 100, 1)
                    : null;
                $cvrDenom = (int) $productClicks + (int) $adsViews;
                $cvrPercent = $cvrDenom > 0 ? ($temuL30 / $cvrDenom) * 100 : 0;
                // Temu L60 = from L60 sales upload table; Temu 2: sales only (no ad fallback)
                $temuL60 = $temuL60FromSales > 0
                    ? $temuL60FromSales
                    : ($isTemu2Pricing ? 0 : $adSoldL60);
                $temuL45 = round(($temuL30 + $temuL60) / 2, 2);
                $cvr45 = $cvrDenom > 0 ? round(($temuL45 / $cvrDenom) * 100, 2) : 0;
                $cvr60 = $cvrDenom > 0 ? round(($temuL60 / $cvrDenom) * 100, 2) : 0;

                // ADS%: Spend / Full Temu Price revenue (aligned with GPFT/NPFT)
                // If spend > 0 but no sales (temuL30 = 0), show 100%
                // Same formula for Temu and Temu 2 (matches /temu-decrease).
                $revenue = $temuFullPrice * $temuL30;
                if ($spend > 0 && $temuL30 == 0) {
                    $adsPercent = 100;
                } else {
                    $adsPercent = $revenue > 0 ? ($spend / $revenue) * 100 : 0;
                }

                // NPFT% = GPFT% (Full Temu Price) − ADS%
                // If ADS% is 100% (spent but no sales), don't subtract it
                if ($adsPercent == 100) {
                    $npftPercent = $profitPercent;
                } else {
                    $npftPercent = $profitPercent - $adsPercent;
                }

                // NROI% = GROI% (R Price) − ADS%
                // If ADS% is 100%, don't subtract it
                if ($adsPercent == 100) {
                    $nroiPercent = $roiPercent;
                } else {
                    $nroiPercent = $roiPercent - $adsPercent;
                }
                
                // Saved SPRICE / starget / (Temu 2) listing fields: temu_data_view or temu2_data_view JSON
                $temuDataViewItem = $temuDataViewData->get($sku);
                $temuDataViewValue = [];
                if ($temuDataViewItem) {
                    $decoded = is_array($temuDataViewItem->value)
                        ? $temuDataViewItem->value
                        : (is_string($temuDataViewItem->value) ? json_decode($temuDataViewItem->value, true) : []);
                    $temuDataViewValue = is_array($decoded) ? $decoded : [];
                }
                $sprice = $temuDataViewValue['sprice'] ?? null;
                $starget = $temuDataViewValue['starget'] ?? null;
                
                // Get Amazon price from AmazonDatasheet
                $amazon = $amazonData->get($sku);
                $amazonPrice = $amazon ? floatval($amazon->price ?? 0) : 0;

                // Get eBay price from EbayMetric (same as EbayController / ebay tabulator Prc column)
                $ebayMetric = $ebayData->get($sku);
                $ebayPrice = $ebayMetric ? floatval($ebayMetric->ebay_price ?? 0) : 0;

                // Get eBay 2 price from Ebay2Metric (mirrors eBay 1 lookup; same `ebay_price` column).
                $ebay2Metric = $ebay2Data->get($sku);
                $ebay2Price = $ebay2Metric ? floatval($ebay2Metric->ebay_price ?? 0) : 0;
                
                $normalizedCurrentSku = $normalizeSku($sku);

                // Recommended base price from API metrics (prefer value already on listing row)
                $pricingSkuId = $item && $item->sku_id !== null && $item->sku_id !== ''
                    ? (string) $item->sku_id
                    : null;
                $recommendedBasePrice = ($item !== null ? ($item->recommended_base_price ?? null) : null)
                    ?? ($pricingSkuId !== null && isset($recommendedBySkuId[$pricingSkuId])
                        ? $recommendedBySkuId[$pricingSkuId]
                        : ($recommendedBySku[$normalizedCurrentSku] ?? null));
                
                // nr_req / listed / links: Temu 2 → temu2_data_view JSON; Temu 1 → temu_listing_statuses
                if ($isTemu2Pricing) {
                    $nr_req = $temuDataViewValue['nr_req'] ?? ($inventory > 0 ? 'REQ' : 'NRL');
                    $listed = $temuDataViewValue['listed'] ?? ($inventory > 0 ? 'Pending' : 'Listed');
                    $buyer_link = $temuDataViewValue['buyer_link'] ?? null;
                    $seller_link = $temuDataViewValue['seller_link'] ?? null;
                } else {
                    $status = $statusData->get($sku);
                    $statusValue = [];
                    if ($status) {
                        $decoded = is_array($status->value)
                            ? $status->value
                            : (is_string($status->value) ? json_decode($status->value, true) : []);
                        $statusValue = is_array($decoded) ? $decoded : [];
                    }
                    $nr_req = $statusValue['nr_req'] ?? ($inventory > 0 ? 'REQ' : 'NRL');
                    $listed = $statusValue['listed'] ?? ($inventory > 0 ? 'Pending' : 'Listed');
                    $buyer_link = $statusValue['buyer_link'] ?? null;
                    $seller_link = $statusValue['seller_link'] ?? null;
                }

                // Missing listing: not in Temu API metrics (Temu 1) / temu2_pricing (Temu 2),
                // or listed with INV>0 and base price 0. Never when INV=0+base>0, or nr_req=NR.
                $inPricing = isset($temuPricingSkusNormalized[$normalizedCurrentSku]);
                $basePriceVal = (float) $basePrice;
                $invVal = (float) $inventory;

                $missing = $inPricing ? '' : 'M';
                if ($inPricing && $invVal > 0 && $basePriceVal <= 0) {
                    $missing = 'M';
                }
                if ($inPricing && $invVal <= 0 && $basePriceVal > 0) {
                    $missing = '';
                }
                if (strtoupper(trim((string) $nr_req)) === 'NR') {
                    $missing = '';
                }

                // LMP entries merged across Sku Link LMP group — tag source_sku so edit/delete
                // write back to the same temu_lmp row they came from.
                $linkedLmpSkus = $this->linkedLmpSkusForProduct($sku);
                $lmpEntries = [];
                foreach ($linkedLmpSkus as $linkedSku) {
                    $temuLmpRow = $temuLmpByNormalizedSku[$normalizeSku($linkedSku)] ?? null;
                    if (! $temuLmpRow) {
                        continue;
                    }
                    foreach ($this->extractTemuLmpEntries($temuLmpRow) as $e) {
                        if (! is_array($e)) {
                            continue;
                        }
                        $e['source_sku'] = (string) ($temuLmpRow->sku ?? $linkedSku);
                        $lmpEntries[] = $e;
                    }
                }
                $lmpEntries = $this->dedupeTemuLmpEntries($lmpEntries);
                $temuLmpRow = $temuLmpByNormalizedSku[$normalizeSku($sku)] ?? null;
                // L1 = lowest non-ignored entry (Price + Delivery); ignored stay in the list
                $activeEntries = array_values(array_filter($lmpEntries, function ($e) {
                    return empty($e['ignored']);
                }));
                $prices = [];
                foreach ($activeEntries as $e) {
                    $eff = $this->temuLmpEntryEffectivePrice($e);
                    if ($eff !== null) {
                        $prices[] = $eff;
                    }
                }
                $lmp = count($prices) > 0 ? min($prices) : null;
                $lmp_link = null;
                if ($lmp !== null) {
                    foreach ($activeEntries as $e) {
                        $eff = $this->temuLmpEntryEffectivePrice($e);
                        if ($eff !== null && abs($eff - (float) $lmp) < 0.00001) {
                            $lmp_link = $e['link'] ?? null;
                            break;
                        }
                    }
                }
                // Legacy fallback only when there are no JSON entries at all
                if ($lmp === null && empty($lmpEntries) && $temuLmpRow) {
                    $lmp = $temuLmpRow->lmp;
                    $lmp_link = $temuLmpRow->lmp_link;
                }

                // Temu 2 (same as /pricing-master-cvr): expose Temu Recovery as LMP
                // ≤$27 → (Price × 0.85) + 2.99; >$27 → Price × 0.85. Keep raw for modal / LMP-15%.
                $lmpRaw = $lmp !== null && $lmp !== '' && is_numeric($lmp) ? (float) $lmp : null;
                if ($isTemu2Pricing) {
                    $lmp = $this->temuLmpRecoveryPrice($lmpRaw);
                }

                // Std Prc — shared amazon_data_view.STANDARD_PRICE; inherit from Sku Link LMP siblings
                $stdPrc = $lookupStdPrc($sku);
                if ($stdPrc === null && ! empty($linkedLmpSkus)) {
                    foreach ($linkedLmpSkus as $linkedSku) {
                        $stdPrc = $lookupStdPrc($linkedSku);
                        if ($stdPrc !== null) {
                            break;
                        }
                    }
                }

                $isParentRow = stripos((string) $sku, 'PARENT') !== false;

                $row = [
                    'sku' => $sku,
                    'parent' => $productMaster->parent ?? '',
                    'is_parent' => $isParentRow,
                    'missing' => $missing,
                    'image_path' => $imagePath,
                    'product_name' => $item ? $item->product_name : '',
                    'category' => $item ? $item->category : '',
                    'variation' => $item ? $item->variation : '',
                    'quantity' => $item ? $item->quantity : 0,
                    'temu_stock' => $temuStock,
                    'base_price' => $basePrice,
                    'status' => $item ? $item->status : '',
                    'detail_status' => $item ? $item->detail_status : '',
                    'goods_id' => $item && $item->goods_id !== null && $item->goods_id !== ''
                        ? (string) TemuGoodsIdHelper::normalizeKey($item->goods_id)
                        : '',
                    'sku_id' => $item ? $item->sku_id : '',
                    'date_created' => $item ? $item->date_created : '',
                    'lp' => $lp,
                    'inventory' => $inventory,
                    'ovl30' => $ovl30,
                    'temu_l30' => $temuL30,
                    'temu_l45' => $temuL45,
                    'temu_l60' => $temuL60,
                    'dil_percent' => $dilPercent,
                    'temu_ship' => $temuShip,
                    'temu_price' => round($temuPrice, 2),
                    // Temu 1 reference price (populated only on the Temu 2 endpoint).
                    'temu1_base_price' => round((float) $temu1BasePrice, 2),
                    'temu1_price' => round((float) $temu1Price, 2),
                    'a_price' => $amazonPrice,
                    'e_price' => $ebayPrice,
                    'e2_price' => $ebay2Price,
                    // Pass the live marketplace take-home % (decimal, e.g. 0.95) on every row
                    // so the front-end SROI formatter can use the SAME margin the backend
                    // GROI calc uses. Prevents GROI / SROI from disagreeing on the rate.
                    'percentage' => (float) $percentage,
                    // NRP — mirrors the same column on /forecast.analysis. Falls back to ''
                    // (front-end formatter defaults to 'REQ' for display) when this SKU has
                    // no row in forecast_analysis. Looked up by normalized SKU so spacing
                    // variants match the same way the LMP join above does.
                    'nrp' => $nrByNormalizedSku[$normalizeSku($sku)] ?? '',
                    'profit' => round($profit, 2),
                    'profit_percent' => round($profitPercent, 2),
                    'roi_percent' => round($roiPercent, 2),
                    'product_clicks' => (int) $productClicks + (int) $adsViews,
                    'ads_views' => (int) $adsViews,
                    'o_clicks' => (int) $oClicks,
                    'ctr' => round($ctr, 2),
                    'product_clicks_l7' => $productClicksL7,
                    'product_clicks_l7_to_l14' => $productClicksL7ToL14,
                    'l7_vs_l30_pct' => $l7VsL30Pct,
                    'cvr_percent' => round($cvrPercent, 2),
                    'cvr_30' => round($cvrPercent, 2),
                    'cvr_45' => $cvr45,
                    'cvr_60' => $cvr60,
                    'spend' => round($spend, 2),
                    'net_roas' => round($netRoas, 2),
                    'acos_ad' => round($acosAd, 2),
                    'ad_clicks' => (int) $adClicks,
                    'clicks_l7' => (int) $adClicksL7,
                    't_clicks' => $tClicks,
                    't_clicks_l7' => $tClicksL7,
                    't_clicks_growth' => $tClicksGrowth,
                    'impressions' => (int) $impressionsVal,
                    'add_to_cart_number' => (int) $addToCartVal,
                    'target' => round($target, 2),
                    'ads_percent' => round($adsPercent, 2),
                    'npft_percent' => round($npftPercent, 2),
                    'nroi_percent' => round($nroiPercent, 2),
                    'sprice' => $sprice,
                    'starget' => $starget,
                    'recommended_base_price' => $recommendedBasePrice,
                    'nr_req' => $nr_req,
                    'listed' => $listed,
                    'buyer_link' => $buyer_link,
                    'seller_link' => $seller_link,
                    // Ads L30/L7/L60: Temu 1 from /temu/ads (temu_ads_api_reports); Temu 2 from campaign-report sheets
                    'in_roas_l30' => $inRoasL30,
                    'out_roas_l30' => $outRoasL30,
                    'spend_l30' => $spendL30,
                    'clicks_l30' => $clicksL30,
                    'ad_sales_l30' => $adSalesL30,
                    'ad_sold_l30' => $adSoldL30,
                    'campaign_status' => $campaignStatus,
                    'spend_l60' => $spendL60,
                    'ad_sold_l60' => $adSoldL60,
                    'ad_sales_l60' => $adSalesL60,
                    'l60_vs_l30' => $l60VsL30,
                    'lmp' => $lmp,
                    'lmp_raw' => $lmpRaw,
                    'lmp_link' => $lmp_link,
                    'lmp_entries' => $lmpEntries,
                    'linked_lmp_skus' => $linkedLmpSkus,
                    'STANDARD_PRICE' => $stdPrc,
                ];

                return app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $sku);
            });

            // Temu 2: O/Ad/T clicks, Spend, and Impressions are goods_id-level (already listing totals).
            // Split those totals evenly across child SKUs that share the goods_id so
            // each child isn't given the full amount. Parent rows then show the
            // goods_id total once (not a sum of children).
            if ($isTemu2Pricing) {
                $normalizeParentKey = static function ($value): string {
                    return strtoupper(trim((string) $value));
                };

                // Capture undivided goods_id totals (first row per goods_id).
                $goodsIdMetricTotals = [];
                $skuIndexesByGoodsId = [];
                foreach ($processedData as $idx => $row) {
                    if (! empty($row['is_parent'])) {
                        continue;
                    }
                    $gid = TemuGoodsIdHelper::normalizeKey($row['goods_id'] ?? null) ?? '';
                    if ($gid === '') {
                        continue;
                    }
                    // Keep normalized goods_id on the row for consistent view matching
                    $row['goods_id'] = $gid;
                    $processedData[$idx] = $row;
                    if (! isset($goodsIdMetricTotals[$gid])) {
                        $goodsIdMetricTotals[$gid] = [
                            'product_clicks' => (int) ($row['product_clicks'] ?? 0),
                            'ads_views' => (int) ($row['ads_views'] ?? 0),
                            'o_clicks' => (int) ($row['o_clicks'] ?? 0),
                            'ad_clicks' => (int) ($row['ad_clicks'] ?? 0),
                            'impressions' => (int) ($row['impressions'] ?? 0),
                            'spend' => round((float) ($row['spend'] ?? 0), 2),
                            'spend_l30' => round((float) ($row['spend_l30'] ?? 0), 2),
                            't_clicks_l7' => (int) ($row['t_clicks_l7'] ?? 0),
                            't_clicks_growth' => $row['t_clicks_growth'] ?? null,
                        ];
                    }
                    $skuIndexesByGoodsId[$gid][] = $idx;
                }

                // Views / O Clicks / Ad / T Clicks stay as full goods_id totals on every child
                // (SUM of uploaded product_clicks matched via temu2_pricing.goods_id).
                // Only spend is split across child SKUs so money totals don't multiply.
                foreach ($skuIndexesByGoodsId as $gid => $indexes) {
                    $n = count($indexes);
                    if ($n <= 0) {
                        continue;
                    }
                    $fullO = (int) ($goodsIdMetricTotals[$gid]['product_clicks'] ?? 0);
                    $fullAdsViews = (int) ($goodsIdMetricTotals[$gid]['ads_views'] ?? 0);
                    $fullSheetO = (int) ($goodsIdMetricTotals[$gid]['o_clicks'] ?? 0);
                    $fullAd = (int) ($goodsIdMetricTotals[$gid]['ad_clicks'] ?? 0);
                    $fullImp = (int) ($goodsIdMetricTotals[$gid]['impressions'] ?? 0);
                    $fullT = $fullSheetO + $fullAd;
                    $spendCents = (int) round(((float) ($goodsIdMetricTotals[$gid]['spend'] ?? 0)) * 100);
                    $spendL30Cents = (int) round(((float) ($goodsIdMetricTotals[$gid]['spend_l30'] ?? 0)) * 100);

                    $baseSpend = intdiv($spendCents, $n);
                    $remSpend = $spendCents % $n;
                    $baseSpendL30 = intdiv($spendL30Cents, $n);
                    $remSpendL30 = $spendL30Cents % $n;

                    foreach ($indexes as $i => $idx) {
                        $row = $processedData[$idx];
                        $spendShare = ($baseSpend + ($i < $remSpend ? 1 : 0)) / 100;
                        $spendL30Share = ($baseSpendL30 + ($i < $remSpendL30 ? 1 : 0)) / 100;
                        // Full SUM(product_clicks) for this Goods ID from uploaded view files
                        $row['product_clicks'] = $fullO;
                        $row['ads_views'] = $fullAdsViews;
                        $row['o_clicks'] = $fullSheetO;
                        $row['ad_clicks'] = $fullAd;
                        $row['t_clicks'] = $fullT;
                        $row['impressions'] = $fullImp;
                        $row['spend'] = round($spendShare, 2);
                        $row['spend_l30'] = round($spendL30Share, 2);
                        $sold = (int) ($row['temu_l30'] ?? 0);
                        $row['cvr_percent'] = $fullO > 0 ? round(($sold / $fullO) * 100, 2) : 0;
                        $row['cvr_30'] = $row['cvr_percent'];
                        $processedData[$idx] = $row;
                    }
                }

                $childrenByParent = [];
                foreach ($processedData as $row) {
                    if (! empty($row['is_parent'])) {
                        continue;
                    }
                    $pk = $normalizeParentKey($row['parent'] ?? '');
                    if ($pk === '') {
                        continue;
                    }
                    $childrenByParent[$pk][] = $row;
                }

                $processedData = $processedData->map(function ($row) use ($childrenByParent, $normalizeParentKey, $goodsIdMetricTotals) {
                    if (empty($row['is_parent'])) {
                        return $row;
                    }

                    $pk = $normalizeParentKey($row['parent'] ?? '');
                    if ($pk === '') {
                        $pk = $normalizeParentKey(preg_replace('/^PARENT\s+/i', '', (string) ($row['sku'] ?? '')));
                    }
                    $children = $childrenByParent[$pk] ?? [];
                    if ($children === []) {
                        return $row;
                    }

                    $inv = 0.0;
                    $ovl30 = 0.0;
                    $temuL30 = 0;
                    $temuL60 = 0;
                    $hasReq = false;
                    $childGoodsIds = [];
                    foreach ($children as $c) {
                        $inv += (float) ($c['inventory'] ?? 0);
                        $ovl30 += (float) ($c['ovl30'] ?? 0);
                        $temuL30 += (int) ($c['temu_l30'] ?? 0);
                        $temuL60 += (int) ($c['temu_l60'] ?? 0);
                        $nr = strtoupper(trim((string) ($c['nr_req'] ?? 'REQ')));
                        if ($nr !== 'NR' && $nr !== 'NRL') {
                            $hasReq = true;
                        }
                        $gid = trim((string) ($c['goods_id'] ?? ''));
                        if ($gid !== '') {
                            $childGoodsIds[$gid] = true;
                        }
                    }

                    $uniqueChildGoodsIds = array_keys($childGoodsIds);
                    if (count($uniqueChildGoodsIds) === 1) {
                        $row['goods_id'] = $uniqueChildGoodsIds[0];
                        $row['goods_id_mismatch'] = false;
                        $row['child_goods_ids'] = $uniqueChildGoodsIds;
                    } elseif (count($uniqueChildGoodsIds) > 1) {
                        // Children disagree — keep blank goods_id; UI shows red triangle
                        $row['goods_id'] = '';
                        $row['goods_id_mismatch'] = true;
                        $row['child_goods_ids'] = $uniqueChildGoodsIds;
                    } else {
                        $row['goods_id_mismatch'] = false;
                        $row['child_goods_ids'] = [];
                    }

                    // Parent clicks/spend/impressions = goods_id totals (once per unique goods_id), not sum of children
                    $parentO = 0;
                    $parentAdsViews = 0;
                    $parentSheetO = 0;
                    $parentAd = 0;
                    $parentImp = 0;
                    $parentT7 = 0;
                    $parentSpend = 0.0;
                    $parentSpendL30 = 0.0;
                    $parentGrowth = null;
                    foreach ($uniqueChildGoodsIds as $gid) {
                        $parentO += (int) ($goodsIdMetricTotals[$gid]['product_clicks'] ?? 0);
                        $parentAdsViews += (int) ($goodsIdMetricTotals[$gid]['ads_views'] ?? 0);
                        $parentSheetO += (int) ($goodsIdMetricTotals[$gid]['o_clicks'] ?? 0);
                        $parentAd += (int) ($goodsIdMetricTotals[$gid]['ad_clicks'] ?? 0);
                        $parentImp += (int) ($goodsIdMetricTotals[$gid]['impressions'] ?? 0);
                        $parentT7 += (int) ($goodsIdMetricTotals[$gid]['t_clicks_l7'] ?? 0);
                        $parentSpend += (float) ($goodsIdMetricTotals[$gid]['spend'] ?? 0);
                        $parentSpendL30 += (float) ($goodsIdMetricTotals[$gid]['spend_l30'] ?? 0);
                        if ($parentGrowth === null && array_key_exists('t_clicks_growth', $goodsIdMetricTotals[$gid] ?? [])) {
                            $parentGrowth = $goodsIdMetricTotals[$gid]['t_clicks_growth'];
                        }
                    }
                    $parentT = $parentSheetO + $parentAd;
                    if (count($uniqueChildGoodsIds) === 1) {
                        // keep single-goods_id growth from child
                    } elseif ($parentT > 0 && $parentT7 > 0) {
                        $parentGrowth = round(((($parentT7 / 7) / ($parentT / 30)) - 1) * 100, 1);
                    } else {
                        $parentGrowth = null;
                    }

                    $row['inventory'] = $inv;
                    $row['ovl30'] = $ovl30;
                    $row['temu_l30'] = $temuL30;
                    $row['temu_l60'] = $temuL60;
                    $row['product_clicks'] = $parentO;
                    $row['ads_views'] = $parentAdsViews;
                    $row['o_clicks'] = $parentSheetO;
                    $row['ad_clicks'] = $parentAd;
                    $row['t_clicks'] = $parentT;
                    $row['t_clicks_l7'] = $parentT7;
                    $row['t_clicks_growth'] = $parentGrowth;
                    $row['impressions'] = $parentImp;
                    $row['spend'] = round($parentSpend, 2);
                    $row['spend_l30'] = round($parentSpendL30, 2);
                    $row['dil_percent'] = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0;
                    // Temu 2 parent CVR = Temu L30 / Views (Views includes Ads Views)
                    $row['cvr_percent'] = $parentO > 0 ? round(($temuL30 / $parentO) * 100, 2) : 0;
                    $row['cvr_30'] = $row['cvr_percent'];
                    $row['nr_req'] = $hasReq ? 'REQ' : 'NR';

                    return $row;
                })->values();
            } else {
                // Temu 1: roll child INV / sold / views onto PARENT rows (same Parents filter as Temu 2).
                $normalizeParentKey = static function ($value): string {
                    return strtoupper(trim((string) $value));
                };

                $childrenByParent = [];
                foreach ($processedData as $row) {
                    if (! empty($row['is_parent'])) {
                        continue;
                    }
                    $pk = $normalizeParentKey($row['parent'] ?? '');
                    if ($pk === '') {
                        continue;
                    }
                    $childrenByParent[$pk][] = $row;
                }

                $processedData = $processedData->map(function ($row) use ($childrenByParent, $normalizeParentKey) {
                    if (empty($row['is_parent'])) {
                        return $row;
                    }

                    $pk = $normalizeParentKey($row['parent'] ?? '');
                    if ($pk === '') {
                        $pk = $normalizeParentKey(preg_replace('/^PARENT\s+/i', '', (string) ($row['sku'] ?? '')));
                    }
                    $children = $childrenByParent[$pk] ?? [];
                    if ($children === []) {
                        return $row;
                    }

                    $inv = 0.0;
                    $ovl30 = 0.0;
                    $temuL30 = 0;
                    $temuL45 = 0;
                    $temuL60 = 0;
                    $productClicks = 0;
                    $adsViews = 0;
                    $oClicks = 0;
                    $productClicksL7 = 0;
                    $productClicksL7ToL14 = 0;
                    $hasReq = false;
                    foreach ($children as $c) {
                        $inv += (float) ($c['inventory'] ?? 0);
                        $ovl30 += (float) ($c['ovl30'] ?? 0);
                        $temuL30 += (int) ($c['temu_l30'] ?? 0);
                        $temuL45 += (int) ($c['temu_l45'] ?? 0);
                        $temuL60 += (int) ($c['temu_l60'] ?? 0);
                        $productClicks += (int) ($c['product_clicks'] ?? 0);
                        $adsViews += (int) ($c['ads_views'] ?? 0);
                        $oClicks += (int) ($c['o_clicks'] ?? 0);
                        $productClicksL7 += (int) ($c['product_clicks_l7'] ?? 0);
                        $productClicksL7ToL14 += (int) ($c['product_clicks_l7_to_l14'] ?? 0);
                        $nr = strtoupper(trim((string) ($c['nr_req'] ?? 'REQ')));
                        if ($nr !== 'NR' && $nr !== 'NRL') {
                            $hasReq = true;
                        }
                    }

                    $row['inventory'] = $inv;
                    $row['ovl30'] = $ovl30;
                    $row['temu_l30'] = $temuL30;
                    $row['temu_l45'] = $temuL45;
                    $row['temu_l60'] = $temuL60;
                    $row['product_clicks'] = $productClicks;
                    $row['ads_views'] = $adsViews;
                    $row['o_clicks'] = $oClicks;
                    $row['product_clicks_l7'] = $productClicksL7;
                    $row['product_clicks_l7_to_l14'] = $productClicksL7ToL14;
                    $row['dil_percent'] = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0;
                    $row['cvr_percent'] = $productClicks > 0 ? round(($temuL30 / $productClicks) * 100, 2) : 0;
                    $row['cvr_30'] = $row['cvr_percent'];
                    $row['nr_req'] = $hasReq ? 'REQ' : 'NR';

                    return $row;
                })->values();
            }

            // Auto-save daily summary in background (L30 only, Temu channel table only)
            if (!$isL7Period && !$isTemu2Pricing) {
                $this->saveDailySummaryIfNeeded($processedData->toArray());
            }

            // Campaign / Ads totals — Temu 1 from Ads API; Temu 2 from campaign-report upload.
            if ($isTemu2Pricing) {
                $totalCampaignCount = Temu2CampaignReport::distinct('goods_id')
                    ->pluck('goods_id')
                    ->filter()
                    ->unique()
                    ->count();
                $totalAdSpend = round((float) (Temu2CampaignReport::where('report_range', $campaignRange)
                    ->selectRaw('SUM(spend) as total_spend')
                    ->value('total_spend') ?? 0), 2);
            } else {
                $apiAds = Schema::hasTable('temu_ads_api_reports')
                    ? TemuAdsApiReport::query()->where('period', $campaignRange)
                    : null;
                $totalCampaignCount = $apiAds
                    ? (int) TemuAdsApiReport::query()
                        ->activeAds()
                        ->where('period', $campaignRange)
                        ->distinct()
                        ->count('goods_id')
                    : 0;
                // Same spend total as /temu/ads (all ads for the period, not Active-only).
                $totalAdSpend = $apiAds
                    ? round((float) TemuAdsApiReport::query()
                        ->where('period', $campaignRange)
                        ->sum('ad_spend'), 2)
                    : 0.0;
            }

            // Get exact total_sales from marketplace_daily_metrics (same as all-marketplace-master uses)
            $metrics = MarketplaceDailyMetric::where('channel', $isTemu2Pricing ? 'Temu 2' : 'Temu')->latest('date')->first();
            $totalSalesFromMetrics = $metrics ? ($metrics->total_sales ?? 0) : 0;

            // Ads% = Spend / Sales. Temu 2 prefers order Full-Price sales_summary revenue
            // (same basis as GPFT/Sales badge); else marketplace_daily_metrics.
            $salesForAds = $isTemu2Pricing
                ? ((float) ($salesSummary['total_revenue'] ?? 0) > 0
                    ? (float) $salesSummary['total_revenue']
                    : (float) $totalSalesFromMetrics)
                : (float) $totalSalesFromMetrics;
            $aggregateAdsPercent = $salesForAds > 0 ? ($totalAdSpend / $salesForAds) * 100 : 0.0;

            // Recalculate NPFT% and NROI% for all rows using aggregate Ads%
            // Formula: NPFT% = GPFT% - Aggregate ADS%; NROI% = GROI% - Aggregate ADS%
            $processedData = $processedData->map(function($row) use ($aggregateAdsPercent) {
                $profitPercent = (float) ($row['profit_percent'] ?? 0);
                $roiPercent = (float) ($row['roi_percent'] ?? 0);
                
                // Use aggregate Ads% for NPFT calculation (matches all-marketplace-master)
                // Only exception: if per-row ads_percent is 100% (spent but no sales), keep original
                $rowAdsPercent = (float) ($row['ads_percent'] ?? 0);
                if ($rowAdsPercent == 100) {
                    // Keep original calculation if per-row ads is 100%
                    $row['npft_percent'] = $row['npft_percent'] ?? ($profitPercent - $rowAdsPercent);
                    $row['nroi_percent'] = $row['nroi_percent'] ?? ($roiPercent - $rowAdsPercent);
                } else {
                    // Use aggregate Ads% for all other rows
                    $row['npft_percent'] = round($profitPercent - $aggregateAdsPercent, 2);
                    $row['nroi_percent'] = round($roiPercent - $aggregateAdsPercent, 2);
                }
                
                return $row;
            });

            // Attach today's badge snapshot (if present in temu_badge_daily_data) so the
            // page can render summary badges from the SAME row the chart's "today" point
            // reads — keeps badge and chart byte-for-byte identical. JS falls back to its
            // locally-computed value when no snapshot exists yet (e.g. before today's cron).
            $todayBadge = null;
            try {
                $todayBadgeRow = TemuBadgeDailyData::where('record_date', Carbon::today('America/Los_Angeles')->toDateString())->first();
                if ($todayBadgeRow) {
                    $todayBadge = [
                        'record_date'    => (string) $todayBadgeRow->record_date,
                        'total_sales'    => round((float) $todayBadgeRow->total_sales, 2),
                        'total_orders'   => (int) $todayBadgeRow->total_orders,
                        'total_quantity' => (int) $todayBadgeRow->total_quantity,
                        'sku_count'      => (int) $todayBadgeRow->sku_count,
                        'total_views'    => (int) $todayBadgeRow->total_views,
                        'avg_views'      => round((float) $todayBadgeRow->avg_views, 2),
                        'total_spend'    => round((float) $todayBadgeRow->total_spend, 2),
                        'avg_cvr_pct'    => round((float) $todayBadgeRow->avg_cvr_pct, 2),
                    ];
                }
            } catch (\Throwable $e) {
                // Don't fail the whole response if the snapshot lookup throws — badge
                // simply falls back to live-computed values like before.
                Log::warning('Temu decrease: today badge snapshot lookup failed: ' . $e->getMessage());
            }

            $adTotals = [
                'spend'              => 0.0,
                'clicks'             => 0,
                'sub_orders'         => 0,
                'base_price_sales'   => 0.0,
                'impressions'        => 0,
                'add_to_cart_number' => 0,
                'row_count'          => 0,
            ];
            if ($isTemu2Pricing) {
                $tot = Temu2CampaignReport::where('report_range', $campaignRange)
                    ->selectRaw('
                        COUNT(*) AS row_count,
                        COALESCE(SUM(spend), 0) AS spend
                    ')->first();
                if ($tot) {
                    $adTotals['spend'] = round((float) $tot->spend, 2);
                    $adTotals['row_count'] = (int) $tot->row_count;
                }
            } elseif (Schema::hasTable('temu_ads_api_reports')) {
                // Match /temu/ads spend_sum / clicks_sum: every report row for the period.
                $tot = TemuAdsApiReport::query()
                    ->where('period', $campaignRange)
                    ->selectRaw("
                        COUNT(*) AS row_count,
                        COALESCE(SUM(ad_spend), 0) AS spend,
                        COALESCE(SUM(clicks), 0) AS clicks,
                        COALESCE(SUM(order_pay_cnt), 0) AS sub_orders,
                        COALESCE(SUM(order_pay_amt), 0) AS base_price_sales,
                        COALESCE(SUM(impressions), 0) AS impressions,
                        COALESCE(SUM(cart_cnt), 0) AS add_to_cart_number
                    ")->first();
                if ($tot) {
                    $adTotals = [
                        'spend'              => round((float) $tot->spend, 2),
                        'clicks'             => (int) $tot->clicks,
                        'sub_orders'         => (int) $tot->sub_orders,
                        'base_price_sales'   => round((float) $tot->base_price_sales, 2),
                        'impressions'        => (int) $tot->impressions,
                        'add_to_cart_number' => (int) $tot->add_to_cart_number,
                        'row_count'          => (int) $tot->row_count,
                    ];
                }
            }

            return response()->json([
                'data' => $processedData,
                'period' => $selectedPeriod,
                'total_campaign_count' => $totalCampaignCount,
                'sales_summary' => $salesSummary,
                'aggregate_ads_percent' => $aggregateAdsPercent, // Exact Ads% from marketplace_daily_metrics (matches all-marketplace-master)
                'today_badge_snapshot' => $todayBadge,
                'ad_totals' => $adTotals,
            ]);
        } catch (\Throwable $e) {
            Log::error('Temu decrease data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => config('app.debug') ? $e->getMessage() : 'Failed to fetch data', 'data' => []], 500);
        }
    }

    /**
     * Ads Views for Temu 1 from temu_ads_api_reports — Active ads only.
     *
     * @return \Illuminate\Support\Collection<string, object{ads_views: int}>
     */
    private function temuAdsApiViewsByGoods(string $period)
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return collect();
        }

        return TemuAdsApiReport::query()
            ->activeAds()
            ->where('period', $period)
            ->whereNotNull('goods_id')
            ->get(['goods_id', 'clicks'])
            ->filter(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
            ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
            ->map(fn ($r) => (object) ['ads_views' => (int) ($r->clicks ?? 0)]);
    }

    /**
     * Temu 1 ads indexes from Ads API. Spend / clicks / ACOS match /temu/ads (all statuses).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection, 4: \Illuminate\Support\Collection, 5: \Illuminate\Support\Collection, 6: \Illuminate\Support\Collection, 7: \Illuminate\Support\Collection, 8: \Illuminate\Support\Collection}
     */
    private function temuAdsApiReportIndexes(string $campaignRange, callable $normalizeSku, callable $normalizeSkuLoose): array
    {
        $mapPeriod = function (string $period) use ($normalizeSku, $normalizeSkuLoose) {
            $raw = collect();
            if (Schema::hasTable('temu_ads_api_reports')) {
                $raw = TemuAdsApiReport::query()
                    ->where('period', $period)
                    ->whereNotNull('goods_id')
                    ->get()
                    ->map(function (TemuAdsApiReport $r) {
                        return (object) [
                            'goods_id' => $r->goods_id,
                            'sku' => $r->sku,
                            'spend_l30' => (float) ($r->ad_spend ?? 0),
                            'clicks_l30' => (int) ($r->clicks ?? 0),
                            'clicks_l7' => (int) ($r->clicks ?? 0),
                            'spend_l60' => (float) ($r->ad_spend ?? 0),
                            'ad_sold_l60' => (int) ($r->order_pay_cnt ?? 0),
                            'ad_sales_l60' => (float) ($r->order_pay_amt ?? 0),
                            'roas_l30' => (float) ($r->roas ?? 0),
                            'net_roas_l30' => (float) ($r->roas ?? 0),
                            'in_roas_l30' => 0.0,
                            'acos_ad_l30' => (float) ($r->acos ?? 0),
                            'status_l30' => $r->displayAdStatus(),
                            'ad_sales_l30' => (float) ($r->order_pay_amt ?? 0),
                            'ad_sold_l30' => (int) ($r->order_pay_cnt ?? 0),
                            'impressions_l30' => (int) ($r->impressions ?? 0),
                            'add_to_cart_l30' => (int) ($r->cart_cnt ?? 0),
                            'target_l30' => 0.0,
                        ];
                    });
            }

            return $this->indexTemuAdMetricRows($raw, $normalizeSku, $normalizeSkuLoose);
        };

        [$l30, $l30Sku, $l30Loose] = $mapPeriod($campaignRange);
        [$l60, $l60Sku, $l60Loose] = $mapPeriod('L60');
        [$l7, $l7Sku, $l7Loose] = $mapPeriod('L7');

        return [$l30, $l30Sku, $l30Loose, $l60, $l60Sku, $l60Loose, $l7, $l7Sku, $l7Loose];
    }

    /**
     * Temu 2 ads indexes from uploaded campaign-report sheets.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection, 4: \Illuminate\Support\Collection, 5: \Illuminate\Support\Collection, 6: \Illuminate\Support\Collection, 7: \Illuminate\Support\Collection, 8: \Illuminate\Support\Collection}
     */
    private function temuCampaignReportSheetIndexes(string $campaignRange, callable $normalizeSku, callable $normalizeSkuLoose): array
    {
        $l30Raw = Temu2CampaignReport::where('report_range', $campaignRange)
            ->selectRaw('goods_id, sku,
                SUM(spend) as spend_l30,
                SUM(clicks) as clicks_l30,
                AVG(roas) as roas_l30,
                AVG(net_roas) as net_roas_l30,
                AVG(in_roas) as in_roas_l30,
                AVG(acos_ad) as acos_ad_l30,
                MAX(status) as status_l30,
                SUM(COALESCE(base_price_sales, 0)) as ad_sales_l30,
                SUM(COALESCE(sub_orders, 0)) as ad_sold_l30,
                SUM(COALESCE(impressions, 0)) as impressions_l30,
                SUM(COALESCE(add_to_cart_number, 0)) as add_to_cart_l30,
                AVG(target) as target_l30')
            ->groupBy('goods_id', 'sku')
            ->get();
        [$l30, $l30Sku, $l30Loose] = $this->indexTemuAdMetricRows($l30Raw, $normalizeSku, $normalizeSkuLoose);

        $l60Raw = Temu2CampaignReport::where('report_range', 'L60')
            ->selectRaw('goods_id, sku,
                SUM(spend) as spend_l60,
                SUM(COALESCE(sub_orders, 0)) as ad_sold_l60,
                SUM(COALESCE(NULLIF(base_price_sales, 0), net_declared_sales, 0)) as ad_sales_l60')
            ->groupBy('goods_id', 'sku')
            ->get();
        [$l60, $l60Sku, $l60Loose] = $this->indexTemuAdMetricRows($l60Raw, $normalizeSku, $normalizeSkuLoose);

        $l7Raw = Temu2CampaignReport::where('report_range', 'L7')
            ->selectRaw('goods_id, sku, SUM(clicks) as clicks_l7')
            ->groupBy('goods_id', 'sku')
            ->get();
        [$l7, $l7Sku, $l7Loose] = $this->indexTemuAdMetricRows($l7Raw, $normalizeSku, $normalizeSkuLoose);

        return [$l30, $l30Sku, $l30Loose, $l60, $l60Sku, $l60Loose, $l7, $l7Sku, $l7Loose];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function indexTemuAdMetricRows($raw, callable $normalizeSku, callable $normalizeSkuLoose): array
    {
        $rows = collect($raw);

        return [
            $rows->filter(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id ?? null))
                ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id)),
            $rows->filter(fn ($r) => ! empty(trim((string) ($r->sku ?? ''))))
                ->keyBy(fn ($r) => $normalizeSku($r->sku)),
            $rows->filter(fn ($r) => $normalizeSkuLoose($r->sku ?? '') !== '')
                ->keyBy(fn ($r) => $normalizeSkuLoose($r->sku)),
        ];
    }

    /**
     * L7 endpoint with same structure as L30 endpoint.
     */
    public function getTemuDecreaseDataL7(Request $request)
    {
        // GET requests read period from query only; merge() does not affect query() / input() for GET.
        $request->query->set('period', 'L7');

        return $this->getTemuDecreaseData($request);
    }

    /**
     * Update Temu base price in temu_metrics (API source — not sheet).
     */
    public function updateTemuPrice(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'base_price' => 'required|numeric|min:0'
            ]);

            $sku = $request->sku;
            $metric = TemuMetric::where('sku', $sku)->first();

            if (!$metric) {
                $normalizeSku = static function ($s) {
                    $s = strtoupper(trim((string) $s));
                    return preg_replace('/[^A-Z0-9]/', '', $s);
                };
                $target = $normalizeSku($sku);
                $metric = TemuMetric::query()
                    ->whereNotNull('sku')
                    ->get(['id', 'sku', 'base_price', 'sku_id', 'goods_id'])
                    ->first(function ($row) use ($normalizeSku, $target) {
                        return $normalizeSku($row->sku) === $target;
                    });
            }

            if (!$metric) {
                return response()->json(['error' => 'SKU not found in temu_metrics'], 404);
            }

            $metric->base_price = $request->base_price;
            $metric->save();

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully',
                'data' => $metric
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Temu price: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update price'], 500);
        }
    }

    /**
     * Push SPRICE / base price to Temu via priceorder.change.sku.price API.
     */
    public function pushTemuPrice(Request $request, TemuApiService $temuApi)
    {
        $request->validate([
            'sku' => 'required|string',
            'price' => 'required|numeric|min:0.01',
            'goods_id' => 'nullable|string',
            'sku_id' => 'nullable|string',
        ]);

        $sku = trim((string) $request->input('sku'));
        $price = (float) $request->input('price');
        $goodsId = $request->input('goods_id');
        $skuId = $request->input('sku_id');

        try {
            $result = $temuApi->updateSkuBasePrice(
                $sku,
                $price,
                $goodsId !== null && $goodsId !== '' ? (string) $goodsId : null,
                $skuId !== null && $skuId !== '' ? (string) $skuId : null
            );

            if (! ($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Temu price push failed',
                    'errors' => [['message' => $result['message'] ?? 'Temu price push failed']],
                ], 400);
            }

            // API-only: do not update local temu_metrics / app price
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Price pushed to Temu',
                'data' => [
                    'sku' => $sku,
                    'price' => $price,
                    'goods_id' => $result['goods_id'] ?? $goodsId,
                    'sku_id' => $result['sku_id'] ?? $skuId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Temu pushTemuPrice failed', [
                'sku' => $sku,
                'price' => $price,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push price to Temu',
                'errors' => [['message' => $e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Push SPRICE / base price to Temu2 via priceorder.change.sku.price API.
     */
    public function pushTemu2Price(Request $request, Temu2ApiService $temu2Api)
    {
        $request->validate([
            'sku' => 'required|string',
            'price' => 'required|numeric|min:0.01',
            'goods_id' => 'nullable|string',
            'sku_id' => 'nullable|string',
        ]);

        $sku = trim((string) $request->input('sku'));
        $price = (float) $request->input('price');
        $goodsId = $request->input('goods_id');
        $skuId = $request->input('sku_id');

        try {
            $result = $temu2Api->updateSkuBasePrice(
                $sku,
                $price,
                $goodsId !== null && $goodsId !== '' ? (string) $goodsId : null,
                $skuId !== null && $skuId !== '' ? (string) $skuId : null
            );

            if (! ($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Temu2 price push failed',
                    'errors' => [['message' => $result['message'] ?? 'Temu2 price push failed']],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Price pushed to Temu2',
                'data' => [
                    'sku' => $sku,
                    'price' => $price,
                    'goods_id' => $result['goods_id'] ?? $goodsId,
                    'sku_id' => $result['sku_id'] ?? $skuId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Temu2 pushTemu2Price failed', [
                'sku' => $sku,
                'price' => $price,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push price to Temu2',
                'errors' => [['message' => $e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Save Temu SPRICE (Suggested Price)
     */
    public function saveTemuSprice(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'sprice' => 'required|numeric|min:0'
            ]);

            $sku = $request->sku;
            $sprice = floatval($request->sprice);

            // Get product master data for LP and temu_ship
            $productMaster = ProductMaster::where('sku', $sku)->first();
            
            $lp = 0;
            $temuShip = 0;
            
            if ($productMaster) {
                $values = is_array($productMaster->Values) 
                    ? $productMaster->Values 
                    : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($productMaster->lp)) {
                    $lp = floatval($productMaster->lp);
                }
                
                $temuShip = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $productMaster);
            }

            // SGPRFT on Full Sprice; SROI on S Recovery (Sprice × 0.88)
            $margin = TemuShopifySalesService::temuMarginDecimal();
            $sRecovery = $sprice * 0.88;
            $profitRoi = $sRecovery * $margin - $lp - $temuShip;
            $profitPft = $sprice * $margin - $lp - $temuShip;
            $sgprftPercent = $sprice > 0 ? ($profitPft / $sprice) * 100 : 0;
            // SROI = S Profit (recovery) / LP
            $sroiPercent = $lp > 0 ? ($profitRoi / $lp) * 100 : 0;

            $this->writeTemuChannelSprice($sku, $sprice, $sgprftPercent, $sroiPercent, false);

            // Auto-apply same suggested price to Temu 2 (non-fatal if Temu 2 write fails)
            $temu2Applied = false;
            try {
                $this->writeTemuChannelSprice($sku, $sprice, $sgprftPercent, $sroiPercent, true);
                $temu2Applied = true;
            } catch (\Throwable $e) {
                Log::warning('Temu SPRICE saved but Temu 2 cross-apply failed: ' . $e->getMessage(), [
                    'sku' => $sku,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'SPRICE saved successfully',
                'sprice' => $sprice,
                'sgprft_percent' => round($sgprftPercent, 2),
                'sroi_percent' => round($sroiPercent, 2),
                'temu_cross_applied' => $temu2Applied,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu SPRICE: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to save SPRICE',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save Temu Decrease column visibility preferences
     */
    public function saveTemuDecreaseColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu_decrease_column_visibility_{$userId}";
            
            $visibility = $request->input('visibility', []);
            Cache::put($key, $visibility, now()->addDays(30));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu Decrease column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    /**
     * Get Temu Decrease column visibility preferences
     */
    public function getTemuDecreaseColumnVisibility()
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu_decrease_column_visibility_{$userId}";
            
            $visibility = Cache::get($key, []);
            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting Temu Decrease column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveTemu2DecreaseColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu2_decrease_column_visibility_{$userId}";

            $visibility = $request->input('visibility', []);
            Cache::put($key, $visibility, now()->addDays(30));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu 2 Decrease column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    public function getTemu2DecreaseColumnVisibility()
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu2_decrease_column_visibility_{$userId}";

            $visibility = Cache::get($key, []);

            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting Temu 2 Decrease column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }


    /**
     * Scrape Temu Seller Center product analytics → temu_view_data (cookie session).
     */
    public function scrapeTemuViewData(Request $request, TemuSellerViewScraperService $scraper)
    {
        $request->validate([
            'cookie' => 'nullable|string|max:20000',
            'days' => 'nullable|integer|min:1|max:60',
            'keep' => 'nullable|boolean',
            'probe' => 'nullable|boolean',
        ]);

        $cookie = $request->input('cookie') ?: null;
        $days = (int) ($request->input('days') ?: 30);

        if ($request->boolean('probe')) {
            $results = $scraper->probe($cookie, $days);

            return response()->json([
                'success' => collect($results)->contains(fn ($r) => $r['ok']),
                'probe' => $results,
                'message' => collect($results)->contains(fn ($r) => $r['ok'])
                    ? 'At least one endpoint returned parsable goods rows'
                    : 'All endpoints failed — refresh cookie or use Import JSON / Excel upload',
            ]);
        }

        $result = $scraper->scrape($cookie, $days, ! $request->boolean('keep'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'deleted' => $result['deleted'],
            'endpoint' => $result['endpoint'] ?? null,
            'samples' => $result['samples'] ?? [],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Import Seller Center Network-tab JSON (fallback when cookie scrape paths change).
     */
    public function importTemuViewDataJson(Request $request, TemuSellerViewScraperService $scraper)
    {
        $request->validate([
            'json' => 'required|string|max:20000000',
            'date' => 'nullable|date',
            'keep' => 'nullable|boolean',
        ]);

        $result = $scraper->importJson(
            $request->input('json'),
            $request->input('date'),
            ! $request->boolean('keep')
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'deleted' => $result['deleted'],
            'samples' => $result['samples'] ?? [],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Upload Temu 1 View Data (Seller Center product analytics export).
     * Writes to temu_view_data — used as /temu-decrease Views (product clicks).
     */
    public function uploadTemuViewData(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            $headers = array_shift($rows);

            $headerMap = [];
            foreach ($headers as $idx => $h) {
                $headerMap[trim((string) $h)] = $idx;
            }
            $goodsIdCol = $headerMap['Goods ID'] ?? 1;

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                $deletedCount = TemuViewData::query()->delete();

                foreach ($rows as $rowIndex => $row) {
                    if (empty($row[0]) && empty($row[1])) {
                        $skipped++;
                        continue;
                    }

                    $rowData = @array_combine($headers, $row);
                    if (! is_array($rowData)) {
                        $skipped++;
                        continue;
                    }

                    $date = null;
                    if (! empty($rowData['Date'])) {
                        try {
                            $date = Carbon::parse($rowData['Date'])->format('Y-m-d');
                        } catch (\Exception $e) {
                            Log::warning('Could not parse date: '.$rowData['Date']);
                        }
                    }

                    $ctr = 0;
                    if (! empty($rowData['CTR'])) {
                        $ctr = (float) str_replace('%', '', (string) $rowData['CTR']);
                    }

                    // Excel row is 1-based header + 1-based data → +2
                    $excelRow = $rowIndex + 2;
                    $goodsIdCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdCol + 1).$excelRow);
                    $goodsId = TemuGoodsIdHelper::fromSpreadsheetCell($goodsIdCell)
                        ?? TemuGoodsIdHelper::normalizeKey($rowData['Goods ID'] ?? null);

                    if ($goodsId === null || $goodsId === '') {
                        $skipped++;
                        continue;
                    }

                    TemuViewData::updateOrCreate(
                        ['date' => $date, 'goods_id' => $goodsId],
                        [
                            'goods_name' => $rowData['Goods Name'] ?? null,
                            'product_impressions' => ! empty($rowData['Product impressions']) ? (int) $rowData['Product impressions'] : 0,
                            'visitor_impressions' => ! empty($rowData['Number of visitor impressions of the product']) ? (int) $rowData['Number of visitor impressions of the product'] : 0,
                            'product_clicks' => ! empty($rowData['Product clicks']) ? (int) $rowData['Product clicks'] : 0,
                            'visitor_clicks' => ! empty($rowData['Number of visitor clicks on the product']) ? (int) $rowData['Number of visitor clicks on the product'] : 0,
                            'ctr' => $ctr,
                        ]
                    );
                    $imported++;
                }

                DB::commit();

                return back()->with('success', "Successfully imported $imported Temu 1 view records! ($skipped skipped, replaced $deletedCount existing rows)");
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu view data: '.$e->getMessage());

            return back()->with('error', 'Error uploading file: '.$e->getMessage());
        }
    }

    /**
     * Download Temu 1 View Data sample (Seller Center product analytics columns).
     */
    public function downloadTemuViewDataSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Date',
            'Goods ID',
            'Goods Name',
            'Product impressions',
            'Number of visitor impressions of the product',
            'Product clicks',
            'Number of visitor clicks on the product',
            'CTR',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['2026-07-01', '602828001095586', 'Sample Product', 100, 80, 25, 20, '25%'],
        ], null, 'A2');

        $writer = new Xlsx($spreadsheet);
        $filename = 'temu_view_data_sample.xlsx';
        $tempPath = storage_path('app/'.$filename);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Upload Ads Views (Temu Ads report: ads.txt / xlsx). Writes to temu_ads_views.
     * Matched on /temu-decrease by goods_id — same join as Views.
     */
    public function uploadTemuAdsViews(Request $request)
    {
        @set_time_limit(300);
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['xlsx', 'xls', 'csv', 'tsv', 'txt'];
        if (! in_array($ext, $allowed, true)) {
            return back()->with('error', 'Invalid file type. Use .xlsx, .xls, .csv, .tsv, or .txt.');
        }

        try {
            $parsed = $this->parseTemuAdsViewsUploadFile($file);
            $imported = 0;
            $skipped = (int) ($parsed['skipped'] ?? 0);

            DB::beginTransaction();
            try {
                $deletedCount = TemuAdsView::query()->delete();
                $now = now()->format('Y-m-d H:i:s');
                $chunks = array_chunk($parsed['rows'], 200);
                foreach ($chunks as $chunk) {
                    $insert = [];
                    foreach ($chunk as $row) {
                        $insert[] = array_merge($row, [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                    TemuAdsView::insert($insert);
                    $imported += count($insert);
                }
                DB::commit();

                return back()->with('success', "Successfully imported $imported Ads Views records! ($skipped skipped, replaced $deletedCount existing rows)");
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu Ads Views: '.$e->getMessage());

            return back()->with('error', 'Error uploading file: '.$e->getMessage());
        }
    }

    /**
     * Sample Ads Views file matching the Temu Ads report headers (ads.txt).
     */
    public function downloadTemuAdsViewsSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Goods name',
            'Goods ID',
            'SKU',
            'Spend',
            'Net total cost',
            'Base Price Sales (Overall)',
            'ROAS (Overall)',
            'ACOS (Overall)',
            'Cost Per Order (Overall)',
            'Sub Order Count (Overall)',
            'Items (Overall)',
            'Impressions (Overall)',
            'Clicks (Overall)',
            'CTR (Overall)',
            'CVR (Overall)',
            'Add to cart count (Overall)',
            'Net Base Price Sales (Overall)',
            'Net ROAS (Overall)',
            'Net ACOS (Overall)',
            'Net Cost Per Order (Overall)',
            'Net Sub Order Count (Overall)',
            'Net Items (Overall)',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['Sample Product', '610414356172006', 'SKU-1', '$5.08', '$5.08', '$94.98', '18.70', '5.34%', '$2.54', '2', '2', '2,148', '49', '2.28%', '4.08%', '8', '$94.98', '18.70', '5.34%', '$2.54', '2', '2'],
        ], null, 'A2');

        $writer = new Xlsx($spreadsheet);
        $filename = 'temu_ads_views_sample.xlsx';
        $tempPath = storage_path('app/'.$filename);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Parse Temu Ads report (ads.txt TSV / Excel) into temu_ads_views rows.
     *
     * @return array{rows: array<int, array<string, mixed>>, skipped: int}
     */
    private function parseTemuAdsViewsUploadFile($file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $sheet = null;
        $rows = [];

        if (in_array($ext, ['tsv', 'txt', 'csv'], true)) {
            $delimiter = $ext === 'csv' ? ',' : "\t";
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                throw new \RuntimeException('Could not open '.$file->getClientOriginalName());
            }
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if (! $allEmpty) {
                    $rows[] = $row;
                }
            }
            fclose($fh);
        } else {
            $reader = IOFactory::createReaderForFile($path);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, false, false, false);
        }

        if (count($rows) < 2) {
            throw new \RuntimeException($file->getClientOriginalName().' has no data rows.');
        }

        $headers = array_map(static function ($h) {
            if ($h instanceof RichText) {
                $h = $h->getPlainText();
            }

            return trim((string) $h);
        }, array_shift($rows));

        $headerMap = [];
        foreach ($headers as $idx => $h) {
            if ($h !== '') {
                $headerMap[$h] = (int) $idx;
            }
        }
        $goodsIdCol = $headerMap['Goods ID'] ?? $headerMap['Goods id'] ?? 1;

        $col = static function (array $rowData, array $aliases) {
            foreach ($aliases as $a) {
                if (array_key_exists($a, $rowData) && $rowData[$a] !== null && $rowData[$a] !== '') {
                    return $rowData[$a];
                }
            }

            return null;
        };
        $parseMoney = static function ($value): float {
            if ($value instanceof RichText) {
                $value = $value->getPlainText();
            }
            if ($value === null || $value === '' || $value === '∞') {
                return 0.0;
            }

            return (float) str_replace(['$', ',', ' '], '', (string) $value);
        };
        $parsePct = static function ($value): float {
            if ($value instanceof RichText) {
                $value = $value->getPlainText();
            }
            if ($value === null || $value === '' || $value === '∞') {
                return 0.0;
            }

            return (float) str_replace(['%', ',', ' '], '', (string) $value);
        };
        $parseInt = static function ($value): int {
            if ($value instanceof RichText) {
                $value = $value->getPlainText();
            }
            if ($value === null || $value === '' || $value === '∞') {
                return 0;
            }

            return (int) str_replace([',', ' '], '', (string) $value);
        };

        $out = [];
        $skipped = 0;

        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }
            $first = trim((string) ($row[0] ?? ''));
            if ($first !== '' && stripos($first, 'Total') === 0) {
                $skipped++;
                continue;
            }
            if (empty($row[0]) && empty($row[1])) {
                $skipped++;
                continue;
            }

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }

            $rowData = @array_combine($headers, $row);
            if (! is_array($rowData)) {
                $skipped++;
                continue;
            }

            $goodsId = null;
            if ($sheet !== null) {
                $excelRow = $rowIndex + 2;
                $goodsIdCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdCol + 1).$excelRow);
                $goodsId = TemuGoodsIdHelper::fromSpreadsheetCell($goodsIdCell);
            }
            if ($goodsId === null || $goodsId === '') {
                $goodsId = TemuGoodsIdHelper::normalizeKey($col($rowData, ['Goods ID', 'Goods id', 'goods_id']));
            }
            if ($goodsId === null || $goodsId === '') {
                $skipped++;
                continue;
            }

            $skuRaw = $col($rowData, ['SKU', 'Sku', 'sku']);
            $sku = $skuRaw !== null && trim((string) $skuRaw) !== '' ? trim((string) $skuRaw) : null;

            $out[] = [
                'goods_id' => $goodsId,
                'sku' => $sku,
                'goods_name' => $col($rowData, ['Goods name', 'Goods Name', 'goods_name']),
                'spend' => $parseMoney($col($rowData, ['Spend'])),
                'net_total_cost' => $parseMoney($col($rowData, ['Net total cost', 'Net Total Cost'])),
                'base_price_sales' => $parseMoney($col($rowData, ['Base Price Sales (Overall)', 'Base Price Sales'])),
                'roas' => $parsePct($col($rowData, ['ROAS (Overall)', 'ROAS'])),
                'acos' => $parsePct($col($rowData, ['ACOS (Overall)', 'ACOS'])),
                'cost_per_order' => $parseMoney($col($rowData, ['Cost Per Order (Overall)', 'Cost Per Order'])),
                'sub_order_count' => $parseInt($col($rowData, ['Sub Order Count (Overall)', 'Sub Order Count'])),
                'items' => $parseInt($col($rowData, ['Items (Overall)', 'Items'])),
                'impressions' => $parseInt($col($rowData, ['Impressions (Overall)', 'Impressions'])),
                'clicks' => $parseInt($col($rowData, ['Clicks (Overall)', 'Clicks'])),
                'ctr' => $parsePct($col($rowData, ['CTR (Overall)', 'CTR'])),
                'cvr' => $parsePct($col($rowData, ['CVR (Overall)', 'CVR'])),
                'add_to_cart_count' => $parseInt($col($rowData, ['Add to cart count (Overall)', 'Add to cart count'])),
                'net_base_price_sales' => $parseMoney($col($rowData, ['Net Base Price Sales (Overall)', 'Net Base Price Sales'])),
                'net_roas' => $parsePct($col($rowData, ['Net ROAS (Overall)', 'Net ROAS'])),
                'net_acos' => $parsePct($col($rowData, ['Net ACOS (Overall)', 'Net ACOS'])),
                'net_cost_per_order' => $parseMoney($col($rowData, ['Net Cost Per Order (Overall)', 'Net Cost Per Order'])),
                'net_sub_order_count' => $parseInt($col($rowData, ['Net Sub Order Count (Overall)', 'Net Sub Order Count'])),
                'net_items' => $parseInt($col($rowData, ['Net Items (Overall)', 'Net Items'])),
            ];
        }

        return ['rows' => $out, 'skipped' => $skipped];
    }

    /**
     * Upload Temu 2 View Data (single or multiple files).
     * Every upload TRUNCATES temu2_view_data first, then inserts only the new file(s).
     * Within one upload, multi-file rows are merged (same date+goods_id → last wins).
     */
    public function uploadTemu2ViewData(Request $request)
    {
        @set_time_limit(300);
        $request->validate([
            'file' => 'nullable|file|max:10240',
            'files' => 'nullable|array|max:30',
            'files.*' => 'file|max:10240',
        ]);

        $uploadFiles = [];
        if ($request->hasFile('files')) {
            foreach ((array) $request->file('files') as $f) {
                if ($f) {
                    $uploadFiles[] = $f;
                }
            }
        }
        if ($request->hasFile('file')) {
            $uploadFiles[] = $request->file('file');
        }
        if ($uploadFiles === []) {
            return back()->with('error', 'Choose one or more view data files to upload.');
        }

        $allowed = ['xlsx', 'xls', 'csv', 'tsv', 'txt'];
        foreach ($uploadFiles as $f) {
            $ext = strtolower((string) $f->getClientOriginalExtension());
            if (! in_array($ext, $allowed, true)) {
                return back()->with('error', 'Invalid file type for "'.$f->getClientOriginalName().'". Use .xlsx, .xls, .csv, or .tsv.');
            }
        }

        try {
            $now = now()->format('Y-m-d H:i:s');
            // Keyed by date|goods_id so multi-file duplicates in THIS upload collapse (last wins)
            $merged = [];
            $skipped = 0;
            $fileCount = 0;

            foreach ($uploadFiles as $file) {
                $parsed = $this->parseTemuViewDataUploadFile($file);
                $fileCount++;
                $skipped += (int) ($parsed['skipped'] ?? 0);
                foreach ($parsed['rows'] as $row) {
                    $key = ((string) ($row['date'] ?? '')).'|'.((string) $row['goods_id']);
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                    $merged[$key] = $row;
                }
            }

            if ($merged === []) {
                throw new \RuntimeException('No valid view rows found in the uploaded file(s).');
            }

            $nextId = 1;
            $insertRows = [];
            foreach ($merged as $row) {
                $row['id'] = $nextId++;
                $insertRows[] = $row;
            }

            // Always wipe previous temu2_view_data before inserting this upload batch
            $deletedCount = (int) DB::table('temu2_view_data')->count();
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table('temu2_view_data')->truncate();
            } catch (\Throwable $e) {
                DB::table('temu2_view_data')->delete();
            } finally {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            foreach (array_chunk($insertRows, 500) as $chunk) {
                DB::table('temu2_view_data')->insert($chunk);
            }

            $imported = count($insertRows);

            return back()->with(
                'success',
                "Truncated old temu2_view_data ({$deletedCount} rows). Imported {$imported} new record(s) from {$fileCount} file(s). ({$skipped} skipped)"
            );
        } catch (\Exception $e) {
            Log::error('Error uploading Temu 2 view data: '.$e->getMessage());

            return back()->with('error', 'Error uploading file: '.$e->getMessage());
        }
    }

    /**
     * Parse one Temu Seller Center view export into insertable rows (no id/timestamps).
     *
     * @return array{rows: array<int, array<string, mixed>>, skipped: int}
     */
    private function parseTemuViewDataUploadFile($file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $sheet = null;
        $rows = [];

        if (in_array($ext, ['tsv', 'txt', 'csv'], true)) {
            $delimiter = $ext === 'csv' ? ',' : "\t";
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                throw new \RuntimeException('Could not open '.$file->getClientOriginalName());
            }
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if (! $allEmpty) {
                    $rows[] = $row;
                }
            }
            fclose($fh);
        } else {
            $reader = IOFactory::createReaderForFile($path);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, false, false, false);
        }

        if (count($rows) < 2) {
            throw new \RuntimeException($file->getClientOriginalName().' has no data rows.');
        }

        $headers = array_map(static function ($h) {
            if ($h instanceof RichText) {
                $h = $h->getPlainText();
            }

            return trim((string) $h);
        }, array_shift($rows));

        $headerMap = [];
        foreach ($headers as $idx => $h) {
            if ($h !== '') {
                $headerMap[$h] = (int) $idx;
            }
        }
        $goodsIdCol = $headerMap['Goods ID'] ?? 1;

        $out = [];
        $skipped = 0;

        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }
            if (empty($row[0]) && empty($row[1])) {
                $skipped++;
                continue;
            }

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }

            $rowData = @array_combine($headers, $row);
            if (! is_array($rowData)) {
                $skipped++;
                continue;
            }

            $date = null;
            if (! empty($rowData['Date'])) {
                try {
                    $date = Carbon::parse($rowData['Date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    Log::warning('Could not parse Temu 2 view date: '.$rowData['Date']);
                }
            }

            $goodsId = null;
            if ($sheet !== null) {
                $excelRow = $rowIndex + 2;
                $goodsIdCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdCol + 1).$excelRow);
                $goodsId = TemuGoodsIdHelper::fromSpreadsheetCell($goodsIdCell);
            }
            if ($goodsId === null || $goodsId === '') {
                $goodsId = TemuGoodsIdHelper::normalizeKey($rowData['Goods ID'] ?? null);
            }
            if ($goodsId === null || $goodsId === '') {
                $skipped++;
                continue;
            }

            $ctr = 0;
            if (! empty($rowData['CTR'])) {
                $ctr = (float) str_replace('%', '', (string) $rowData['CTR']);
            }

            $out[] = [
                'date' => $date,
                'goods_id' => $goodsId,
                'goods_name' => $rowData['Goods Name'] ?? null,
                'product_impressions' => ! empty($rowData['Product impressions']) ? (int) $rowData['Product impressions'] : 0,
                'visitor_impressions' => ! empty($rowData['Number of visitor impressions of the product']) ? (int) $rowData['Number of visitor impressions of the product'] : 0,
                'product_clicks' => ! empty($rowData['Product clicks']) ? (int) $rowData['Product clicks'] : 0,
                'visitor_clicks' => ! empty($rowData['Number of visitor clicks on the product']) ? (int) $rowData['Number of visitor clicks on the product'] : 0,
                'ctr' => $ctr,
            ];
        }

        return ['rows' => $out, 'skipped' => $skipped];
    }

    /**
     * Download Temu 2 View Data Sample File (same columns as Temu 1).
     */
    public function downloadTemu2ViewDataSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Date',
            'Goods ID',
            'Goods Name',
            'Product impressions',
            'Number of visitor impressions of the product',
            'Product clicks',
            'Number of visitor clicks on the product',
            'CTR'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        $sampleData = [
            [
                '2025-11-01',
                '603163444796046',
                '5Core 6.5 Inch Midrange Car Door Speaker',
                '98493',
                '71393',
                '3188',
                '2825',
                '3.24%'
            ],
            [
                '2025-11-01',
                '603258940684269',
                'Adjustable Heavy Duty Guitar Stand',
                '79303',
                '56745',
                '496',
                '439',
                '0.63%'
            ]
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setWidth(25);
        }

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        $fileName = 'Temu2_View_Data_Sample_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload Temu Ad Data (Truncate then Insert)
     */
    public function uploadTemuAdData(Request $request)
    {
        try {
            $request->validate([
                'ad_data_file' => 'required|file|mimes:xlsx,xls,csv',
                // Optional report range — drives temu_campaign_reports.report_range so the
                // Spend/ACOS/ROAS badges on Temu Decrease (which sum that table by range)
                // refresh after this upload. Defaults to L30 to match the page default.
                'report_range' => 'nullable|in:L7,L30,L60',
            ]);

            $reportRange = strtoupper((string) ($request->input('report_range') ?: 'L30'));
            if (!in_array($reportRange, ['L7', 'L30', 'L60'], true)) {
                $reportRange = 'L30';
            }

            $file = $request->file('ad_data_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $headerRow = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];
            $headers = array_map(function ($h) {
                return is_string($h) ? trim($h) : $h;
            }, $headerRow);

            $goodsIdColIdx = array_search('Goods ID', $headers, true);
            if ($goodsIdColIdx === false) {
                return back()->with('error', 'Excel must contain a column named exactly "Goods ID".');
            }
            // SKU column is optional but useful — temu_campaign_reports has a SKU
            // fallback index that the Decrease page uses when goods_id doesn't match.
            $skuColIdx = array_search('SKU', $headers, true);

            // Coerce RichText / Stringable cell values to plain strings so that
            // downstream numeric casts (floatval / (int) / (float)) don't blow up
            // with "Object of class RichText could not be converted to float".
            $normalizeCellValue = function ($value) {
                if ($value instanceof RichText) {
                    return trim($value->getPlainText());
                }
                if (is_object($value) && method_exists($value, '__toString')) {
                    return trim((string) $value);
                }
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            };
            $parseCurrency = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if (empty($value) || $value === '∞') {
                    return null;
                }

                return floatval(str_replace(['$', ','], '', (string) $value));
            };
            $parsePercent = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if (empty($value) || $value === '∞') {
                    return null;
                }

                return floatval(str_replace('%', '', (string) $value));
            };
            $parseFloat = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return 0.0;
                }

                return floatval(str_replace([',', '$', '%'], '', (string) $value));
            };
            // Read a value by trying the new Temu export header first, then legacy aliases.
            $col = function (array $rowData, array $aliases) {
                foreach ($aliases as $a) {
                    if (array_key_exists($a, $rowData) && $rowData[$a] !== null && $rowData[$a] !== '') {
                        return $rowData[$a];
                    }
                }

                return null;
            };
            $parseInt = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);

                return (int) str_replace(',', '', (string) ($value ?? 0));
            };

            $imported = 0;
            $highestRow = (int) $sheet->getHighestDataRow();
            $numCols = count($headers);

            DB::beginTransaction();
            try {
                // delete() instead of truncate() — truncate() implicitly commits
                // the active transaction in MySQL, which would later make
                // DB::commit/rollBack throw "There is no active transaction".
                TemuAdData::query()->delete();
                // Replace only the chosen range in temu_campaign_reports so the
                // Spend/ACOS/ROAS badges on Temu Decrease refresh; other ranges
                // (e.g. user uploaded L30 today, L7 yesterday) are preserved.
                TemuCampaignReport::where('report_range', $reportRange)->delete();

                $campaignImported = 0;

                for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                    $firstCell = $sheet->getCell(Coordinate::stringFromColumnIndex(1).$rowNum)->getValue();
                    if ($firstCell !== null && $firstCell !== '' && stripos((string) $firstCell, 'Total') !== false) {
                        continue;
                    }

                    $row = [];
                    for ($c = 1; $c <= $numCols; $c++) {
                        $cellValue = $sheet->getCell(Coordinate::stringFromColumnIndex($c).$rowNum)->getValue();
                        // Flatten rich-text cells (e.g. Temu exports with bold/colored
                        // segments in product names) so downstream numeric casts work.
                        if ($cellValue instanceof RichText) {
                            $cellValue = $cellValue->getPlainText();
                        }
                        $row[] = $cellValue;
                    }
                    if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                        continue;
                    }

                    $rowData = @array_combine($headers, array_pad(array_slice($row, 0, $numCols), $numCols, null));
                    if (! is_array($rowData)) {
                        continue;
                    }

                    $goodsCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdColIdx + 1).$rowNum);
                    $goodsIdNormalized = TemuGoodsIdHelper::fromSpreadsheetCell($goodsCell);
                    if (! $goodsIdNormalized) {
                        Log::warning('Temu ad data upload: skipped row '.$rowNum.' — missing Goods ID');

                        continue;
                    }

                    // Map each field to the new Temu export columns (Ad variants), legacy headers as fallback.
                    $adData = [
                        'goods_name' => $normalizeCellValue($col($rowData, ['Goods name'])),
                        'goods_id' => $goodsIdNormalized,
                        'spend' => $parseCurrency($col($rowData, ['Spend'])),
                        'base_price_sales' => $parseCurrency($col($rowData, ['Base Price Sales (Ad)', 'Base price sales'])),
                        'roas' => $parseFloat($col($rowData, ['ROAS (Ad)', 'ROAS'])),
                        'acos_ad' => $parsePercent($col($rowData, ['ACOS (Ad)', 'ACOS(AD)'])),
                        'cost_per_transaction' => $parseCurrency($col($rowData, ['Cost Per Order (Ad)', 'Cost per transaction'])),
                        'sub_orders' => $parseInt($col($rowData, ['Sub Order Count (Ad)', 'Sub-Orders'])),
                        'items' => $parseInt($col($rowData, ['Item Quantity (Ad)', 'Items'])),
                        'net_total_cost' => $parseCurrency($col($rowData, ['Net total cost'])),
                        'net_declared_sales' => $parseCurrency($col($rowData, ['Net Base Price Sales (Ad)', 'Net declared sales'])),
                        'net_roas' => $parseFloat($col($rowData, ['Net ROAS (Ad)', 'Net advertising return on investment (ROAS)'])),
                        'net_acos_ad' => $parsePercent($col($rowData, ['Net ACOS (Ad)', 'Net advertising cost ratio (advertising)'])),
                        'net_cost_per_transaction' => $parseCurrency($col($rowData, ['Net Cost Per Order (Ad)', 'Net cost per transaction'])),
                        'net_orders' => $parseInt($col($rowData, ['Net Sub Order Count (Ad)', 'Net Orders'])),
                        'net_number_pieces' => $parseInt($col($rowData, ['Net Item Quantity (Ad)', 'Net number of pieces'])),
                        'impressions' => $parseInt($col($rowData, ['Impressions (Ad)', 'Impressions'])),
                        'clicks' => $parseInt($col($rowData, ['Clicks (Ad)', 'Clicks'])),
                        'ctr' => $parsePercent($col($rowData, ['Click Through Rate (Ad)', 'CTR'])),
                        'cvr' => $parsePercent($col($rowData, ['Conversion Rate (Ad)', 'Conversion Rate (CVR)'])),
                        'add_to_cart_number' => $parseInt($col($rowData, ['Add To Cart (Ad)', 'Add-to-cart number'])),
                        'weekly_roas' => $parseFloat($col($rowData, ['Natural Week ROAS (Ad)', 'Weekly ROAS'])),
                        'target' => $parseFloat($col($rowData, ['Natural Week Target ROAS (Ad)', 'Target'])),
                    ];

                    TemuAdData::create($adData);
                    $imported++;

                    // Mirror the same row into temu_campaign_reports for the chosen
                    // report_range so getTemuDecreaseData (which aggregates that table
                    // for the Spend/ACOS/ROAS badges) reflects this upload.
                    $skuValue = null;
                    if ($skuColIdx !== false) {
                        $rawSku = $row[$skuColIdx] ?? null;
                        if ($rawSku instanceof RichText) {
                            $rawSku = $rawSku->getPlainText();
                        }
                        $skuValue = strtoupper(trim((string) ($rawSku ?? '')));
                        if ($skuValue === '') {
                            $skuValue = null;
                        }
                    }

                    $campaignData = $adData + [
                        'sku' => $skuValue,
                        'report_range' => $reportRange,
                    ];
                    TemuCampaignReport::create($campaignData);
                    $campaignImported++;
                }

                DB::commit();

                return back()->with(
                    'success',
                    "Successfully imported {$imported} ad records and refreshed {$campaignImported} {$reportRange} campaign rows."
                );
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu ad data: ' . $e->getMessage());
            return back()->with('error', 'Error uploading file: ' . $e->getMessage());
        }
    }


    /**
     * Temu LMP page: table + upload section
     */
    public function temuLmpPage()
    {
        $records = Schema::hasTable('temu_lmp')
            ? TemuLmp::orderBy('sku')->paginate(100)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 100);
        return view('market-places.temu_lmp', compact('records'));
    }

    /**
     * Upload Temu LMP data (Excel/CSV/TSV: SKU, LMP, LMP Link, LMP, LMP Link)
     * Truncate then insert.
     */
    public function uploadTemuLmp(Request $request)
    {
        // Prefer extension over MIME — browser/OS MIME for CSV/XLSX is often wrong and blocked saves.
        $request->validate([
            'lmp_file' => 'required|file|max:20480',
        ]);

        try {
            if (! Schema::hasTable('temu_lmp')) {
                return back()->with('error', 'temu_lmp table is missing. Run migrations first.');
            }

            $file = $request->file('lmp_file');
            $path = $file->getPathname();
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'tsv'], true)) {
                return back()->with('error', 'Invalid file type. Use Excel (.xlsx/.xls) or CSV/TSV (.csv/.txt).');
            }

            $rows = [];
            if (in_array($ext, ['xlsx', 'xls'], true)) {
                $spreadsheet = IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            } else {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $delimiter = (strpos($lines[0] ?? '', "\t") !== false) ? "\t" : ',';
                foreach ($lines as $line) {
                    $rows[] = str_getcsv($line, $delimiter);
                }
            }

            if (count($rows) < 1) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            // Map columns from header when present; fall back to fixed positions.
            $colMap = ['sku' => 0, 'lmp' => 1, 'lmp_link' => 2, 'lmp_2' => 3, 'lmp_link_2' => 4];
            $startRow = 0;
            $header = array_map(static function ($v) {
                return strtolower(trim((string) $v));
            }, $rows[0] ?? []);
            $headerJoined = implode('|', $header);
            if (str_contains($headerJoined, 'sku') || str_contains($headerJoined, 'lmp')) {
                $startRow = 1;
                foreach ($header as $idx => $label) {
                    if ($label === 'sku' || $label === 'seller sku' || $label === 'contribution sku') {
                        $colMap['sku'] = $idx;
                    } elseif (in_array($label, ['lmp', 'lmp 1', 'lmp1', 'price', 'l1'], true)) {
                        $colMap['lmp'] = $idx;
                    } elseif (in_array($label, ['lmp link', 'lmp_link', 'lmp link 1', 'link', 'link 1'], true)) {
                        $colMap['lmp_link'] = $idx;
                    } elseif (in_array($label, ['lmp 2', 'lmp2', 'lmp_2'], true)) {
                        $colMap['lmp_2'] = $idx;
                    } elseif (in_array($label, ['lmp link 2', 'lmp_link_2', 'link 2'], true)) {
                        $colMap['lmp_link_2'] = $idx;
                    }
                }
            }

            if (count($rows) <= $startRow) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            // Build payload first so a bad file never wipes existing temu_lmp data.
            $toInsert = [];
            $errors = [];
            for ($i = $startRow; $i < count($rows); $i++) {
                $row = $rows[$i];
                $sku = isset($row[$colMap['sku']]) ? trim((string) $row[$colMap['sku']]) : '';
                if ($sku === '' || strcasecmp($sku, 'SKU') === 0) {
                    continue;
                }

                $lmpRaw = $row[$colMap['lmp']] ?? null;
                $lmpLinkRaw = $row[$colMap['lmp_link']] ?? null;
                $lmp2Raw = $row[$colMap['lmp_2']] ?? null;
                $lmpLink2Raw = $row[$colMap['lmp_link_2']] ?? null;

                $lmp = ($lmpRaw !== null && $lmpRaw !== '') ? $this->sanitizePrice($lmpRaw) : null;
                $lmpLink = ($lmpLinkRaw !== null && trim((string) $lmpLinkRaw) !== '') ? trim((string) $lmpLinkRaw) : null;
                $lmp2 = ($lmp2Raw !== null && $lmp2Raw !== '') ? $this->sanitizePrice($lmp2Raw) : null;
                $lmpLink2 = ($lmpLink2Raw !== null && trim((string) $lmpLink2Raw) !== '') ? trim((string) $lmpLink2Raw) : null;

                $lmpEntries = [];
                if ($lmp !== null || $lmpLink !== null) {
                    $lmpEntries[] = ['price' => $lmp, 'link' => $lmpLink, 'ignored' => false];
                }
                if ($lmp2 !== null || $lmpLink2 !== null) {
                    $lmpEntries[] = ['price' => $lmp2, 'link' => $lmpLink2, 'ignored' => false];
                }

                $toInsert[] = [
                    'sku' => $sku,
                    'lmp' => $lmp,
                    'lmp_link' => $lmpLink,
                    'lmp_2' => $lmp2,
                    'lmp_link_2' => $lmpLink2,
                    'lmp_entries' => $lmpEntries !== [] ? $lmpEntries : null,
                    '_row' => $i + 1,
                ];
            }

            if ($toInsert === []) {
                return back()->with('error', 'No rows imported. Check that column 1 is SKU (or a header row with SKU / LMP).');
            }

            $imported = 0;
            DB::transaction(function () use ($toInsert, &$imported, &$errors) {
                // delete() is transactional (unlike TRUNCATE on MySQL).
                TemuLmp::query()->delete();

                foreach ($toInsert as $payload) {
                    $rowNum = $payload['_row'];
                    unset($payload['_row']);
                    try {
                        TemuLmp::create($payload);
                        $imported++;
                    } catch (\Exception $e) {
                        $errors[] = 'Row ' . $rowNum . ': ' . $e->getMessage();
                    }
                }

                if ($imported === 0) {
                    throw new \RuntimeException('All rows failed to import.');
                }
            });

            $msg = "Successfully imported $imported Temu LMP records.";
            if (! empty($errors)) {
                $msg .= ' ' . count($errors) . ' row(s) had errors.';
            }

            return back()->with('success', $msg)->with('upload_errors', $errors);
        } catch (\Exception $e) {
            Log::error('Temu LMP upload error: ' . $e->getMessage());
            return back()->with('error', 'Error uploading file: ' . $e->getMessage());
        }
    }

    /**
     * Save LMP entries from Temu Decrease / price-increase (match by normalized SKU, or create).
     * lmp_entries = array of {price, delivery, link, ignored, source_sku?}.
     * Entries are written back to each source_sku's temu_lmp row (Sku Link LMP group),
     * so edit/delete on /price-increase and /temu-decrease hit the same source.
     */
    public function saveTemuLmp(Request $request)
    {
        try {
            if (! Schema::hasTable('temu_lmp')) {
                return response()->json(['success' => false, 'message' => 'temu_lmp table is missing'], 500);
            }

            $sku = trim((string) $request->input('sku', ''));
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
            }

            $rawEntries = $request->input('lmp_entries', []);
            if (! is_array($rawEntries)) {
                return response()->json(['success' => false, 'message' => 'lmp_entries must be an array'], 422);
            }

            $normalizeSku = static function ($s) {
                $s = strtoupper(trim((string) $s));
                $s = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $s);
                $s = preg_replace('/\s+/', ' ', $s);

                return $s;
            };

            $bySource = [];
            foreach ($rawEntries as $e) {
                if (! is_array($e)) {
                    continue;
                }
                $price = array_key_exists('price', $e) && $e['price'] !== '' && $e['price'] !== null
                    ? $this->sanitizePrice($e['price'])
                    : null;
                $deliveryRaw = array_key_exists('delivery', $e) && $e['delivery'] !== '' && $e['delivery'] !== null
                    ? $this->sanitizePrice($e['delivery'])
                    : null;
                $delivery = ($deliveryRaw !== null && (float) $deliveryRaw > 0) ? (float) $deliveryRaw : 0.0;
                $link = isset($e['link']) && trim((string) $e['link']) !== '' ? trim((string) $e['link']) : null;
                if ($link !== null && strlen($link) > 10000) {
                    $link = substr($link, 0, 10000);
                }
                $ignored = filter_var($e['ignored'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($price === null && $link === null && $delivery <= 0) {
                    continue;
                }
                $sourceSku = trim((string) ($e['source_sku'] ?? $sku));
                if ($sourceSku === '') {
                    $sourceSku = $sku;
                }
                $sourceKey = $normalizeSku($sourceSku);
                if (! isset($bySource[$sourceKey])) {
                    $bySource[$sourceKey] = ['sku' => $sourceSku, 'entries' => []];
                }
                $bySource[$sourceKey]['entries'][] = [
                    'price' => $price,
                    'delivery' => $delivery,
                    'link' => $link,
                    'ignored' => $ignored,
                ];
            }

            // Full replace across Sku Link LMP group — sources with no remaining entries are cleared
            // so deletes from a linked SKU do not reappear after refresh.
            $groupSkus = $this->linkedLmpSkusForProduct($sku);
            if ($groupSkus === []) {
                $groupSkus = [$sku];
            }
            $writeKeys = [];
            foreach ($groupSkus as $memberSku) {
                $writeKeys[$normalizeSku($memberSku)] = $memberSku;
            }
            foreach ($bySource as $key => $bucket) {
                $writeKeys[$key] = $bucket['sku'];
            }

            $totalCount = 0;
            foreach ($writeKeys as $key => $displaySku) {
                $entries = $bySource[$key]['entries'] ?? [];
                $this->upsertTemuLmpEntriesForSku($displaySku, $entries, $normalizeSku);
                $totalCount += count($entries);
            }

            $merged = [];
            foreach ($bySource as $bucket) {
                $merged = array_merge($merged, $bucket['entries']);
            }
            $activeEntries = array_values(array_filter($merged, static function ($e) {
                return empty($e['ignored']);
            }));
            $effectivePrices = [];
            foreach ($activeEntries as $e) {
                $eff = $this->temuLmpEntryEffectivePrice($e);
                if ($eff !== null) {
                    $effectivePrices[] = $eff;
                }
            }
            $firstPrice = count($effectivePrices) > 0 ? min($effectivePrices) : null;

            return response()->json([
                'success' => true,
                'message' => 'LMP saved successfully',
                'lmp' => $firstPrice,
                'count' => $totalCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Temu LMP save error: ' . $e->getMessage(), [
                'sku' => $request->input('sku'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save LMP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upsert one SKU's temu_lmp row (entries only for that source).
     *
     * @param  list<array{price: mixed, delivery?: float, link: mixed, ignored?: bool}>  $lmpEntries
     * @param  callable(string): string  $normalizeSku
     */
    private function upsertTemuLmpEntriesForSku(string $sku, array $lmpEntries, callable $normalizeSku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        $activeEntries = array_values(array_filter($lmpEntries, static function ($e) {
            return empty($e['ignored']);
        }));
        $effectivePrices = [];
        foreach ($activeEntries as $e) {
            $eff = $this->temuLmpEntryEffectivePrice($e);
            if ($eff !== null) {
                $effectivePrices[] = $eff;
            }
        }
        $firstPrice = count($effectivePrices) > 0 ? min($effectivePrices) : null;
        $firstLink = null;
        if ($firstPrice !== null) {
            foreach ($activeEntries as $e) {
                $eff = $this->temuLmpEntryEffectivePrice($e);
                if ($eff !== null && abs($eff - (float) $firstPrice) < 0.00001) {
                    $firstLink = $e['link'] ?? null;
                    break;
                }
            }
        }

        $targetNormalized = $normalizeSku($sku);
        $existing = TemuLmp::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();
        if (! $existing) {
            $existing = TemuLmp::query()
                ->select(['id', 'sku', 'lmp', 'lmp_link', 'lmp_2', 'lmp_link_2', 'lmp_entries'])
                ->whereRaw(
                    'UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(sku), CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?',
                    [$targetNormalized]
                )
                ->first();
        }

        if ($existing && count($lmpEntries) === 0) {
            // Full delete — removes row so linked-SKU merge cannot resurrect stale LMP
            $existing->delete();

            return;
        }

        $payload = [
            'sku' => $existing ? (string) $existing->sku : $sku,
            'lmp' => $firstPrice,
            'lmp_link' => $firstLink,
            // Always persist array (including []) so extract does not fall back to legacy columns
            'lmp_entries' => array_values($lmpEntries),
            'lmp_2' => null,
            'lmp_link_2' => null,
        ];

        if ($existing) {
            $existing->update($payload);
        } elseif (count($lmpEntries) > 0) {
            TemuLmp::create(array_merge($payload, ['sku' => $sku]));
        }
    }


    /**
     * Update Temu Cell Data (like APRICE - Amazon Price)
     */
    public function updateTemuCellData(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $field = $request->input('field');
            $value = $request->input('value');
            
            if (!$sku || !$field) {
                return response()->json(['error' => 'SKU and field are required'], 400);
            }
            
            // Allowed fields to update
            $allowedFields = ['aprice']; // Amazon Price
            
            if (!in_array($field, $allowedFields)) {
                return response()->json(['error' => 'Field not allowed for update'], 400);
            }
            
            // Find or create temu_data_view record
            $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
            
            // Get existing value array or create new one
            $valueArray = is_array($dataView->value) ? $dataView->value : [];
            
            // Update the specific field
            $valueArray[$field] = floatval($value);
            
            // Save
            $dataView->value = $valueArray;
            $dataView->save();
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($field) . ' updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating Temu cell data: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving data'], 500);
        }
    }

    /**
     * Save Amazon Price Updates in Bulk (from Suggest Amazon Price button)
     */
    public function saveTemuAmazonPriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }

            $dataViewClass = $request->boolean('temu2') ? Temu2DataView::class : TemuDataView::class;

            DB::beginTransaction();
            
            $updated = 0;
            $errors = [];
            
            foreach ($updates as $update) {
                $sku = strtoupper(trim($update['sku'] ?? ''));
                $amazonPrice = floatval($update['amazon_price'] ?? 0);
                
                if (empty($sku) || $amazonPrice <= 0) {
                    $errors[] = "Invalid data for SKU: {$sku}";
                    continue;
                }
                
                $dataViewRecord = $dataViewClass::where('sku', $sku)->first();
                
                if ($dataViewRecord) {
                    $existingValue = $dataViewRecord->value ?? [];
                    $existingValue['amazon_price_applied_at'] = now()->toDateTimeString();
                    $existingValue['sprice'] = $amazonPrice;
                    
                    $dataViewRecord->update([
                        'value' => $existingValue,
                        'updated_at' => now()
                    ]);
                } else {
                    $dataViewClass::create([
                        'sku' => $sku,
                        'value' => [
                            'amazon_price_applied_at' => now()->toDateTimeString(),
                            'sprice' => $amazonPrice
                        ]
                    ]);
                }
                $updated++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'updated' => $updated,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Temu Amazon price updates: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save updates'], 500);
        }
    }

    /**
     * Save R Price (Recommended Price) Updates in Bulk
     */
    public function saveTemuRPriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }

            $dataViewClass = $request->boolean('temu2') ? Temu2DataView::class : TemuDataView::class;

            DB::beginTransaction();
            
            $updated = 0;
            $errors = [];
            
            foreach ($updates as $update) {
                $sku = strtoupper(trim($update['sku'] ?? ''));
                $rPrice = floatval($update['r_price'] ?? 0);
                
                if (empty($sku) || $rPrice <= 0) {
                    $errors[] = "Invalid data for SKU: {$sku}";
                    continue;
                }
                
                $dataViewRecord = $dataViewClass::where('sku', $sku)->first();
                
                if ($dataViewRecord) {
                    $existingValue = $dataViewRecord->value ?? [];
                    $existingValue['r_price_applied_at'] = now()->toDateTimeString();
                    $existingValue['sprice'] = $rPrice;
                    
                    $dataViewRecord->update([
                        'value' => $existingValue,
                        'updated_at' => now()
                    ]);
                } else {
                    $dataViewClass::create([
                        'sku' => $sku,
                        'value' => [
                            'r_price_applied_at' => now()->toDateTimeString(),
                            'sprice' => $rPrice
                        ]
                    ]);
                }
                $updated++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'updated' => $updated,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Temu R price updates: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save updates'], 500);
        }
    }

    /**
     * Clear All SPRICE Data from Temu
     */
    public function clearAllTemuSprice(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $cleared = 0;
            $skus = $request->input('skus', []); // Get selected SKUs array
            
            // If no SKUs provided, return error
            if (empty($skus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SKUs selected'
                ], 400);
            }
            
            // Get temu_data_view records for selected SKUs only
            $dataViewRecords = TemuDataView::whereIn('sku', $skus)->get();
            
            foreach ($dataViewRecords as $record) {
                $value = $record->value ?? [];
                
                // Remove sprice and related calculated fields from value array
                $fieldsToRemove = [
                    'sprice',
                    'spft_percent',
                    'sroi_percent',
                    'ship',
                    'amazon_price_applied_at',
                    'r_price_applied_at',
                    'sprice_status'
                ];
                
                $wasModified = false;
                foreach ($fieldsToRemove as $field) {
                    if (isset($value[$field])) {
                        unset($value[$field]);
                        $wasModified = true;
                    }
                }
                
                // Update or delete the record
                if ($wasModified) {
                    if (empty($value)) {
                        // If value array is empty, delete the record
                        $record->delete();
                    } else {
                        // Otherwise, update with cleaned value
                        $record->update([
                            'value' => $value,
                            'updated_at' => now()
                        ]);
                    }
                    $cleared++;
                }
            }

            DB::commit();

            Log::info("Cleared SPRICE data for {$cleared} selected SKUs in Temu");

            return response()->json([
                'success' => true,
                'cleared' => $cleared,
                'message' => "Successfully cleared SPRICE for {$cleared} SKU(s)"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing Temu SPRICE: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to clear SPRICE data'], 500);
        }
    }

    /**
     * Get Temu SKU Metrics History for Chart
     * Includes computed profit_percent, ads_percent, roi_percent, npft_percent, nroi_percent for dot columns.
     */
    public function getTemuMetricsHistory(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $days = (int) $request->input('days', 30);
            if ($days <= 0) {
                $days = 30;
            }

            if (!$sku) {
                return response()->json(['error' => 'SKU is required'], 400);
            }

            // Check if table exists
            if (!DB::getSchemaBuilder()->hasTable('temu_sku_daily_data')) {
                Log::warning('temu_sku_daily_data table does not exist. Please run migration.');
                return response()->json([]); // Return empty array to show "No data" message
            }

            // Get lp and temu_ship for this SKU from ProductMaster (same as decrease page)
            $normalizeSku = function ($s) {
                $s = strtoupper(trim((string) $s));
                $s = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $s);
                $s = preg_replace('/\s+/', ' ', $s);
                return $s;
            };
            $targetNormalized = $normalizeSku($sku);
            $productMaster = ProductMaster::whereNull('deleted_at')->get()->first(function ($row) use ($normalizeSku, $targetNormalized) {
                return $normalizeSku($row->sku ?? '') === $targetNormalized;
            });
            $lp = 0;
            $temuShip = 0;
            if ($productMaster) {
                $values = is_array($productMaster->Values) ? $productMaster->Values : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                $values = $values ?? [];
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($productMaster->lp)) {
                    $lp = floatval($productMaster->lp);
                }
                if ($lp === 0 && isset($productMaster->LP)) {
                    $lp = floatval($productMaster->LP);
                }
                $temuShip = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $productMaster);
            }

            // Margin from marketplace_percentages.Temu — same as /temu-decrease TEMU_MARGIN
            $percentage = TemuShopifySalesService::temuMarginDecimal();

            // Last N stored snapshot days for this SKU (not "today"). Collection often
            // lags, so a today-based window would drop older daily rows and show 1 point.
            $skuExact = strtoupper(trim((string) $sku));
            $metricsQuery = function (string $skuKey) use ($days) {
                return DB::table('temu_sku_daily_data')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuKey])
                    ->orderBy('record_date', 'desc')
                    ->limit($days)
                    ->get()
                    ->sortBy('record_date')
                    ->values();
            };

            $metricsData = $metricsQuery($skuExact);
            if ($metricsData->isEmpty() && $targetNormalized !== '' && $targetNormalized !== $skuExact) {
                $metricsData = $metricsQuery($targetNormalized);
            }

            // Format data for chart from stored snapshots (prefer daily_data JSON)
            $chartData = $metricsData->map(function ($record) use ($lp, $temuShip, $percentage) {
                $json = [];
                if (isset($record->daily_data) && $record->daily_data) {
                    $json = is_array($record->daily_data)
                        ? $record->daily_data
                        : (json_decode((string) $record->daily_data, true) ?: []);
                }
                $basePrice = floatval($json['base_price'] ?? $json['price'] ?? $record->base_price ?? 0);
                $spend = floatval($json['spend'] ?? $record->spend ?? 0);
                $temuL30 = intval($json['temu_l30'] ?? $record->temu_l30 ?? 0);
                $temuRPrice = $basePrice > 0 ? ($basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice) : 0;
                $temuFullPrice = TemuShopifySalesService::computeFullTemuPrice((float) $basePrice);
                $revenue = $temuFullPrice * $temuL30;

                $profitPercent = $temuFullPrice > 0
                    ? (($temuFullPrice * $percentage - $lp - $temuShip) / $temuFullPrice) * 100
                    : 0;
                $roiPercent = TemuShopifySalesService::computeGroiPercent((float) $temuRPrice, (float) $percentage, (float) $lp, (float) $temuShip);
                $adsPercent = ($spend > 0 && $temuL30 == 0) ? 100 : ($revenue > 0 ? ($spend / $revenue) * 100 : 0);
                $npftPercent = $adsPercent == 100 ? $profitPercent : $profitPercent - $adsPercent;
                $nroiPercent = $adsPercent == 100 ? $roiPercent : $roiPercent - $adsPercent;

                $productClicks = intval($json['product_clicks'] ?? $json['views'] ?? $record->product_clicks ?? 0);
                // ad_clicks may be absent on older temu_sku_daily_data rows
                $adClicksHist = intval($json['ad_clicks'] ?? ($record->ad_clicks ?? 0));
                $recordDate = Carbon::parse($record->record_date)->toDateString();

                return [
                    'date' => $recordDate,
                    'full_date' => $recordDate,
                    'date_formatted' => Carbon::parse($record->record_date)->format('M d'),
                    'price' => $basePrice,
                    'views' => $productClicks,
                    'ad_clicks' => $adClicksHist,
                    't_clicks' => $productClicks + $adClicksHist,
                    'cvr_percent' => floatval($json['cvr_percent'] ?? $record->cvr_percent ?? 0),
                    'temu_l30' => $temuL30,
                    'spend' => $spend,
                    'profit_percent' => round($profitPercent, 2),
                    'ads_percent' => round($adsPercent, 2),
                    'roi_percent' => round($roiPercent, 2),
                    'npft_percent' => round($npftPercent, 2),
                    'nroi_percent' => round($nroiPercent, 2),
                ];
            });

            return response()->json($chartData->values());
        } catch (\Exception $e) {
            Log::error('Error fetching Temu metrics history: ' . $e->getMessage());
            // Return empty array instead of 500 error to show "No data" message
            return response()->json([]);
        }
    }

    /**
     * Store daily average views
     */
    public function storeDailyAvgViews(Request $request)
    {
        try {
            $date = $request->input('date', now()->format('Y-m-d'));
            $avgViews = $request->input('avg_views');
            $totalProducts = $request->input('total_products');
            $totalViews = $request->input('total_views');

            \App\Models\TemuDailyAvgViews::updateOrCreate(
                ['date' => $date],
                [
                    'avg_views' => $avgViews,
                    'total_products' => $totalProducts,
                    'total_views' => $totalViews
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Daily average views stored successfully'
            ]);
        } catch (\Exception $e) {
            // Table doesn't exist - return success but don't actually save
            // This prevents 500 errors in console
            Log::error('Error storing daily average views: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Table not available'
            ], 200); // Return 200 instead of 500 to prevent console errors
        }
    }

    /**
     * Get average views history for chart
     */
    public function getAvgViewsHistory(Request $request)
    {
        try {
            $days = $request->input('days', 30);
            
            $history = \App\Models\TemuDailyAvgViews::orderBy('date', 'desc')
                ->take($days)
                ->get()
                ->reverse()
                ->values();

            return response()->json($history);
        } catch (\Exception $e) {
            // Table doesn't exist - return empty array
            Log::error('Error fetching average views history: ' . $e->getMessage());
            return response()->json([], 200); // Return 200 with empty array
        }
    }

    /**
     * Get latest average views
     */
    public function getLatestAvgViews()
    {
        try {
            // Check if table exists by trying to query it
            $latest = \App\Models\TemuDailyAvgViews::orderBy('date', 'desc')->first();
            return response()->json($latest ?: ['avg_views' => 0]);
        } catch (\Exception $e) {
            // Table doesn't exist or other error - return empty data instead of error
            Log::error('Error fetching latest average views: ' . $e->getMessage());
            return response()->json(['avg_views' => 0], 200); // Return 200 with empty data
        }
    }

    /**
     * Save S Target (Suggested Target)
     */
    public function saveStarget(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'starget' => 'required|numeric|min:0'
            ]);

            $sku = $request->sku;
            $starget = $request->starget;

            // Get or create TemuDataView record
            $temuData = TemuDataView::where('sku', $sku)->first();
            
            if (!$temuData) {
                $temuData = new TemuDataView();
                $temuData->sku = $sku;
            }

            // Get current value or initialize as empty array
            $value = is_array($temuData->value) 
                ? $temuData->value 
                : (is_string($temuData->value) ? json_decode($temuData->value, true) : []);
            
            if (!is_array($value)) {
                $value = [];
            }

            // Update starget
            $value['starget'] = $starget;
            
            $temuData->value = $value;
            $temuData->save();

            return response()->json([
                'success' => true,
                'message' => 'S Target saved successfully',
                'data' => $temuData
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving S Target: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save S Target'], 500);
        }
    }

    /**
     * Auto-save daily Temu summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Filter: inventory > 0 && nr_req === 'REQ' (EXACT JavaScript logic)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['inventory'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                
                return $invCheck && $reqCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalProducts = $filteredData->count();
            $totalQuantity = 0;
            $totalPriceWeighted = 0;
            $totalQty = 0;
            $totalRevenue = 0;
            $totalProfit = 0;
            $totalLp = 0;
            $totalGprft = 0;
            $totalGroi = 0;
            $totalAds = 0;
            $totalNpft = 0;
            $totalNroi = 0;
            $totalCvr = 0;
            $totalDil = 0;
            $totalSpend = 0;
            $totalViews = 0;
            $totalTemuL30 = 0;
            $totalInv = 0;
            $cvrCount = 0;
            $dilCount = 0;
            $zeroSoldCount = 0;
            $missingCount = 0;
            $mappedCount = 0;
            $notMappedCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            // Use temu_l30 for Total Quantity to match tabulator view (quantity sold, not stock)
            foreach ($filteredData as $row) {
                $temuL30 = intval($row['temu_l30'] ?? 0);
                $price = floatval($row['base_price'] ?? 0);
                $totalQuantity += $temuL30;
                $totalPriceWeighted += $price * $temuL30;
                $totalQty += $temuL30;
                
                // Revenue = Temu Price × Temu L30
                $temuPrice = floatval($row['temu_price'] ?? 0);
                $totalRevenue += $temuPrice * $temuL30;
                
                // Profit from row data
                $totalProfit += floatval($row['profit'] ?? 0);
                
                // LP (Landing Price / COGS)
                $totalLp += floatval($row['lp'] ?? 0);
                
                // Percentage metrics (for averaging)
                $totalGprft += floatval($row['profit_percent'] ?? 0);
                $totalGroi += floatval($row['roi_percent'] ?? 0);
                $totalAds += floatval($row['ads_percent'] ?? 0);
                $totalNpft += floatval($row['npft_percent'] ?? 0);
                $totalNroi += floatval($row['nroi_percent'] ?? 0);
                
                // CVR% (only count non-zero values)
                $cvr = floatval($row['cvr_percent'] ?? 0);
                if ($cvr > 0) {
                    $totalCvr += $cvr;
                    $cvrCount++;
                }
                
                // DIL% (only count non-zero values)
                $dil = floatval($row['dil_percent'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                // Ad spend and views
                $totalSpend += floatval($row['spend'] ?? 0);
                $totalViews += intval($row['product_clicks'] ?? 0);
                $totalTemuL30 += $temuL30;
                
                // Inventory and counts
                $inventory = floatval($row['inventory'] ?? 0);
                $totalInv += $inventory;
                
                // Zero sold count
                if ($temuL30 == 0) {
                    $zeroSoldCount++;
                }
                
                // Missing (align with Temu decrease badge: INV>0)
                if (($row['missing'] ?? '') === 'M' && floatval($row['inventory'] ?? 0) > 0) {
                    $missingCount++;
                }
                
                // Mapped/Not Mapped (|INV - Temu Stock| <= 3 = mapped, not Missing M)
                $goodsId = $row['goods_id'] ?? '';
                $temuStock = floatval($row['temu_stock'] ?? 0);
                $isMissingL = ($row['missing'] ?? '') === 'M';
                if ($goodsId && $inventory > 0 && ! $isMissingL) {
                    $invTemuDiff = abs($inventory - $temuStock);
                    if ($temuStock > 0) {
                        if ((float) $inventory == (float) $temuStock || $invTemuDiff <= 3) {
                            $mappedCount++;
                        } else {
                            $notMappedCount++;
                        }
                    } elseif ($temuStock == 0) {
                        if ($invTemuDiff > 3) {
                            $notMappedCount++;
                        } else {
                            $mappedCount++;
                        }
                    }
                }
                
                // Compare Temu Price with Amazon Price
                $amazonPrice = floatval($row['a_price'] ?? 0);
                if ($amazonPrice > 0 && $temuPrice > 0) {
                    if ($temuPrice < $amazonPrice) {
                        $lessAmzCount++;
                    } elseif ($temuPrice > $amazonPrice) {
                        $moreAmzCount++;
                    }
                }
            }
            
            // Calculate averages (EXACT JavaScript logic)
            $avgPrice = $totalQty > 0 ? $totalPriceWeighted / $totalQty : 0;
            $avgGprft = $totalProducts > 0 ? $totalGprft / $totalProducts : 0;
            $avgGroi = $totalProducts > 0 ? $totalGroi / $totalProducts : 0;
            $avgAds = $totalProducts > 0 ? $totalAds / $totalProducts : 0;
            $avgNpft = $totalProducts > 0 ? $totalNpft / $totalProducts : 0;
            $avgNroi = $totalProducts > 0 ? $totalNroi / $totalProducts : 0;
            $avgCvr = $cvrCount > 0 ? $totalCvr / $cvrCount : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_products' => $totalProducts,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'mapped_count' => $mappedCount,
                'not_mapped_count' => $notMappedCount,
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                
                // Totals
                'total_quantity' => $totalQuantity,
                'total_revenue' => round($totalRevenue, 2),
                'total_profit' => round($totalProfit, 2),
                'total_lp' => round($totalLp, 2),
                'total_spend' => round($totalSpend, 2),
                'total_views' => $totalViews,
                'total_temu_l30' => $totalTemuL30,
                'total_inv' => round($totalInv, 2),
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                'avg_gprft' => round($avgGprft, 2),
                'avg_groi' => round($avgGroi, 2),
                'avg_ads' => round($avgAds, 2),
                'avg_npft' => round($avgNpft, 2),
                'avg_nroi' => round($avgNroi, 2),
                'avg_cvr' => round($avgCvr, 2),
                'avg_dil' => round($avgDil, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'gt0',  // INV > 0
                    'nr_req' => 'REQ',     // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'temu',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily Temu summary snapshot saved for {$today}", [
                'product_count' => $totalProducts,
                'zero_sold_count' => $zeroSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily Temu summary: ' . $e->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private function linkedLmpSkusForProduct(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        // Must prepare before groupContaining — otherwise the union graph is empty and
        // delete/save only clears the current SKU while linked siblings still supply LMP on reload.
        try {
            $this->lmpSkuGroupService->prepareForSkus([$sku]);
        } catch (\Throwable $e) {
            // fall through to single-SKU write
        }

        $group = $this->lmpSkuGroupService->groupContaining($sku);

        return $this->normalizeLinkedSkuGroup($group !== [] ? $group : [$sku]);
    }

    /**
     * @param  list<string>  $group
     * @return list<string>
     */
    private function normalizeLinkedSkuGroup(array $group): array
    {
        $seen = [];
        $normalized = [];

        foreach ($group as $memberSku) {
            $display = trim((string) $memberSku);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $normalized[] = $display;
        }

        return $normalized;
    }

    /**
     * Effective competitor LMP for one entry = Price + Delivery.
     *
     * @param  array{price?: mixed, delivery?: mixed}|null  $entry
     */
    private function temuLmpEntryEffectivePrice(?array $entry): ?float
    {
        if (! is_array($entry)) {
            return null;
        }
        $price = $entry['price'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0) && $p !== 0.0) {
            return null;
        }
        $delivery = $entry['delivery'] ?? 0;
        $d = (is_numeric($delivery) && (float) $delivery > 0) ? (float) $delivery : 0.0;
        // Temu LMP: default +$2.99 delivery when Price is below $27 (manual Delivery overrides)
        if ($d <= 0 && $p < 27) {
            $d = 2.99;
        }

        return round($p + $d, 2);
    }

    /**
     * Temu LMP Recovery (same rule as /pricing-master-cvr Temu 2 LMP):
     * price ≤ $27 → (Price × 0.85) + 2.99
     * price > $27 → Price × 0.85
     */
    private function temuLmpRecoveryPrice($price): ?float
    {
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0)) {
            return null;
        }
        if ($p <= 27) {
            return round(($p * 0.85) + 2.99, 2);
        }

        return round($p * 0.85, 2);
    }

    /**
     * @return list<array{price: mixed, link: mixed}>
     */
    private function extractTemuLmpEntries(?TemuLmp $temuLmpRow): array
    {
        if (!$temuLmpRow) {
            return [];
        }

        $entries = $temuLmpRow->lmp_entries;
        // Trust lmp_entries when present (including []) so deletes are not undone by legacy lmp/lmp_2.
        if (is_array($entries)) {
            return array_values($entries);
        }

        $lmpEntries = [];
        if ($temuLmpRow->lmp !== null || $temuLmpRow->lmp_link) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp, 'link' => $temuLmpRow->lmp_link];
        }
        if ($temuLmpRow->lmp_2 !== null || $temuLmpRow->lmp_link_2) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp_2, 'link' => $temuLmpRow->lmp_link_2];
        }

        return $lmpEntries;
    }

    /**
     * @param  list<array{price: mixed, link: mixed}>  $entries
     * @return list<array{price: mixed, link: mixed}>
     */
    private function dedupeTemuLmpEntries(array $entries): array
    {
        $seen = [];
        $out = [];

        foreach ($entries as $entry) {
            $price = $entry['price'] ?? null;
            $link = strtoupper(trim((string) ($entry['link'] ?? '')));
            $key = (string) $price . '|' . $link;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }

        usort($out, function ($a, $b) {
            $pa = ($a['price'] ?? null) !== null && $a['price'] !== '' ? (float) $a['price'] : PHP_FLOAT_MAX;
            $pb = ($b['price'] ?? null) !== null && $b['price'] !== '' ? (float) $b['price'] : PHP_FLOAT_MAX;

            return $pa <=> $pb;
        });

        return $out;
    }

    /**
     * After Temu / Temu 2 daily sales upload, refresh marketplace_daily_metrics and
     * channel_master_calculated_data so /all-marketplace-master stays in sync with tabulator.
     */
    private function refreshTemuMetricsAfterDailyUpload(bool $isTemu2): void
    {
        try {
            $php = PHP_BINARY;
            $artisan = base_path('artisan');
            $metricsCmd = escapeshellarg($php).' '.escapeshellarg($artisan).' app:update-marketplace-daily-metrics';
            $channelCmd = escapeshellarg($php).' '.escapeshellarg($artisan).' channel:calculate-data --force';

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen('start /B '.$metricsCmd, 'r'));
                pclose(popen('start /B '.$channelCmd, 'r'));
            } else {
                exec($metricsCmd.' > /dev/null 2>&1 &');
                exec($channelCmd.' > /dev/null 2>&1 &');
            }

            Log::info('Queued Temu channel master refresh after daily upload', ['temu2' => $isTemu2]);
        } catch (\Throwable $e) {
            Log::warning('Temu channel master refresh after upload failed: '.$e->getMessage(), [
                'temu2' => $isTemu2,
            ]);
        }
    }

    public function uploadCampaignReport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'report_range' => 'required|in:L7,L30,L60'
            ]);

            $file = $request->file('file');
            $reportRange = $request->input('report_range');
            $ext = strtolower($file->getClientOriginalExtension());

            // ── Parse rows ────────────────────────────────────────────────────────
            // Accept Excel (.xlsx/.xls), CSV, AND tab-separated text (.txt/.tsv).
            // Temu exports its ads report as a tab-delimited .txt file; PhpSpreadsheet
            // treats the whole row as one cell for that format, so we parse it manually.
            $isTsv = in_array($ext, ['txt', 'tsv', ''])
                || $this->detectTsv($file->getPathname());

            if ($isTsv) {
                [$headers, $dataRows] = $this->parseTsvFile($file->getPathname());
            } else {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rawHeaders = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];
                $headers = array_map(fn ($h) => is_string($h) ? trim($h) : $h, $rawHeaders);
                $dataRows = null; // will iterate via $sheet
            }

            $goodsIdColIdx = array_search('Goods ID', $headers, true);
            if ($goodsIdColIdx === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'File must contain a column named exactly "Goods ID".',
                ], 422);
            }
            $skuColIdx = array_search('SKU', $headers, true);

            $normalizeCellValue = function ($value) {
                if ($value instanceof RichText) {
                    return trim($value->getPlainText());
                }
                if (is_object($value) && method_exists($value, '__toString')) {
                    return trim((string) $value);
                }
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            };
            $parseCurrency = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if (empty($value) || $value === '∞') {
                    return null;
                }

                return floatval(str_replace(['$', ','], '', $value));
            };
            $parsePercent = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if (empty($value) || $value === '∞') {
                    return null;
                }

                return floatval(str_replace('%', '', $value));
            };
            $parseNumber = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return 0;
                }

                return floatval(str_replace([',', '%', '$'], '', (string) $value));
            };

            // Read a value by trying multiple header aliases. The new Temu export uses
            // suffixed column names ("(Ad)" / "(Overall)"); older exports used the bare
            // names. Prefer (Ad), fall back to (Overall), then to the legacy bare name.
            $col = function (array $rowData, array $aliases) {
                foreach ($aliases as $a) {
                    if (array_key_exists($a, $rowData) && $rowData[$a] !== null && $rowData[$a] !== '') {
                        return $rowData[$a];
                    }
                }
                return null;
            };

            $imported = 0;
            $skipped = 0;
            $rowErrors = 0;
            $firstRowError = null;
            $numCols = count($headers);

            // Build the iterable list of raw rows regardless of source format
            $highestRow = 0;
            if ($isTsv) {
                $allRows = $dataRows; // already an array of string arrays (0-indexed, no header row)
            } else {
                $highestRow = (int) $sheet->getHighestDataRow();
                $allRows = null; // will iterate $sheet directly
            }

            DB::beginTransaction();
            try {
                TemuCampaignReport::where('report_range', $reportRange)->delete();

                $iterateFn = function () use ($isTsv, $allRows, &$sheet, $highestRow, $normalizeCellValue, $numCols) {
                    if ($isTsv) {
                        foreach ($allRows as $row) {
                            yield $row;
                        }
                    } else {
                        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                            $raw = [];
                            for ($c = 1; $c <= $numCols; $c++) {
                                $raw[] = $normalizeCellValue($sheet->getCell(Coordinate::stringFromColumnIndex($c).$rowNum)->getValue());
                            }
                            yield ['_rowNum' => $rowNum, '_raw' => $raw];
                        }
                    }
                };

                foreach ($iterateFn() as $entry) {
                    // Normalise to a flat string array
                    if ($isTsv) {
                        $row = $entry;
                        // Skip "Total …" summary rows
                        if (stripos((string) ($row[0] ?? ''), 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                    } else {
                        $rowNum = $entry['_rowNum'];
                        $row = $entry['_raw'];
                        $firstCell = $row[0] ?? null;
                        if ($firstCell !== null && $firstCell !== '' && stripos((string) $firstCell, 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                    }

                    if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                        $skipped++;
                        continue;
                    }

                    $rowData = @array_combine($headers, array_pad(array_slice($row, 0, $numCols), $numCols, null));
                    if (! is_array($rowData)) {
                        $skipped++;
                        continue;
                    }

                    // Extract Goods ID — for TSV it's plain text, for Excel use the cell helper
                    if ($isTsv) {
                        $rawGoodsId = trim((string) ($row[$goodsIdColIdx] ?? ''));
                        $goodsIdNormalized = $rawGoodsId !== '' ? TemuGoodsIdHelper::normalizeKey($rawGoodsId) : null;
                    } else {
                        $goodsCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdColIdx + 1).$rowNum);
                        $goodsIdNormalized = TemuGoodsIdHelper::fromSpreadsheetCell($goodsCell);
                    }

                    if (! $goodsIdNormalized) {
                        $skipped++;
                        Log::warning("Temu campaign report upload ({$reportRange}): skipped row — missing Goods ID");
                        continue;
                    }

                    // SKU from column "SKU" (col index 2 in the Temu export, col name "SKU")
                    $skuValue = $skuColIdx !== false
                        ? strtoupper(trim((string) ($row[$skuColIdx] ?? '')))
                        : null;

                    try {
                        $campaignData = [
                            'goods_name'   => $rowData['Goods name'] ?? null,
                            'goods_id'     => $goodsIdNormalized,
                            'sku'          => $skuValue ?: null,
                            'report_range' => $reportRange,
                            'spend'        => $parseCurrency($col($rowData, ['Spend'])),
                            'base_price_sales' => $parseCurrency($col($rowData, ['Base Price Sales (Ad)', 'Base Price Sales (Overall)', 'Base price sales'])),
                            'roas'         => $parseNumber($col($rowData, ['ROAS (Ad)', 'ROAS (Overall)', 'ROAS']) ?? 0),
                            'acos_ad'      => $parsePercent($col($rowData, ['ACOS (Ad)', 'ACOS (Overall)', 'ACOS(AD)'])),
                            'cost_per_transaction' => $parseCurrency($col($rowData, ['Cost Per Order (Ad)', 'Cost Per Order (Overall)', 'Cost per transaction'])),
                            'sub_orders'   => (int) str_replace(',', '', (string) ($col($rowData, ['Sub Order Count (Ad)', 'Sub Order Count (Overall)', 'Sub-Orders']) ?? 0)),
                            'items'        => (int) str_replace(',', '', (string) ($col($rowData, ['Item Quantity (Ad)', 'Items (Overall)', 'Items']) ?? 0)),
                            'net_total_cost' => $parseCurrency($col($rowData, ['Net total cost'])),
                            'net_declared_sales' => $parseCurrency($col($rowData, ['Net Base Price Sales (Ad)', 'Net declared sales'])),
                            'net_roas'     => $parseNumber($col($rowData, ['Net ROAS (Ad)', 'Net advertising return on investment (ROAS)']) ?? 0),
                            'net_acos_ad'  => $parsePercent($col($rowData, ['Net ACOS (Ad)', 'Net advertising cost ratio (advertising)'])),
                            'net_cost_per_transaction' => $parseCurrency($col($rowData, ['Net Cost Per Order (Ad)', 'Net cost per transaction'])),
                            'net_orders'   => (int) str_replace(',', '', (string) ($col($rowData, ['Net Sub Order Count (Ad)', 'Net Orders']) ?? 0)),
                            'net_number_pieces' => (int) str_replace(',', '', (string) ($col($rowData, ['Net Item Quantity (Ad)', 'Net number of pieces']) ?? 0)),
                            'impressions'  => (int) str_replace(',', '', (string) ($col($rowData, ['Impressions (Ad)', 'Impressions (Overall)', 'Impressions']) ?? 0)),
                            'clicks'       => (int) str_replace(',', '', (string) ($col($rowData, ['Clicks (Ad)', 'Clicks (Overall)', 'Clicks']) ?? 0)),
                            'ctr'          => $parsePercent($col($rowData, ['Click Through Rate (Ad)', 'CTR (Overall)', 'CTR'])),
                            'cvr'          => $parsePercent($col($rowData, ['Conversion Rate (Ad)', 'CVR (Overall)', 'Conversion Rate (CVR)'])),
                            'add_to_cart_number' => (int) str_replace(',', '', (string) ($col($rowData, ['Add To Cart (Ad)', 'Add to cart count (Overall)', 'Add-to-cart number']) ?? 0)),
                            'weekly_roas'  => $parseNumber($col($rowData, ['Natural Week ROAS (Ad)', 'Weekly ROAS']) ?? 0),
                            'target'       => $parseNumber($col($rowData, ['Natural Week Target ROAS (Ad)', 'Target']) ?? 0),
                        ];

                        TemuCampaignReport::create($campaignData);
                        $imported++;
                    } catch (\Exception $e) {
                        $skipped++;
                        $rowErrors++;
                        if ($firstRowError === null) {
                            $firstRowError = $e->getMessage();
                        }
                        Log::warning("Failed to import campaign row: ".$e->getMessage());
                        continue;
                    }
                }

                // Guard: never wipe this range with a zero-import commit.
                if ($imported === 0) {
                    DB::rollBack();
                    $msg = "Imported 0 rows for {$reportRange}. Existing {$reportRange} campaign data was kept.";
                    if ($firstRowError) {
                        $msg .= " First row error: {$firstRowError}";
                    } else {
                        $msg .= " All rows were skipped (check file format/headers).";
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'imported' => 0,
                        'skipped' => $skipped,
                        'row_errors' => $rowErrors,
                    ], 422);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported $imported records for $reportRange",
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'row_errors' => $rowErrors,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu campaign report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return true if the file looks like a tab-delimited text file.
     * We check: extension is .txt/.tsv OR the first line contains tabs.
     */
    private function detectTsv(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (!$handle) return false;
        $line = fgets($handle);
        fclose($handle);
        return $line !== false && substr_count($line, "\t") >= 3;
    }

    /**
     * Parse a tab-delimited text file into [$headers, $dataRows].
     * Skips the first "Total …" summary row that Temu includes as row 2.
     */
    private function parseTsvFile(string $path): array
    {
        $headers  = [];
        $dataRows = [];
        $handle   = fopen($path, 'r');
        if (!$handle) return [[], []];

        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            $cols = explode("\t", $line);
            $cols = array_map('trim', $cols);

            if ($lineNum === 0) {
                $headers = $cols;
            } else {
                // Skip the "Total N item(s)" summary row Temu adds as row 2
                if (stripos($cols[0] ?? '', 'Total') !== false && $lineNum === 1) {
                    $lineNum++;
                    continue;
                }
                $dataRows[] = $cols;
            }
            $lineNum++;
        }
        fclose($handle);
        return [$headers, $dataRows];
    }

    /**
     * N Map SKU rows from temu-decrease / temu2-decrease tabular payload
     * (same Map / N Map rules as the page badges and MappingChannelCounts).
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array{sku: string, channel_sku: string, inv: float, channel_inv: float, diff: float}>
     */
    public static function nmapSkuRowsFromDecrease(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row)) {
                continue;
            }

            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $inventory = (float) ($row['inventory'] ?? 0);
            $temuStock = (float) ($row['temu_stock'] ?? 0);
            $missing = (string) ($row['missing'] ?? '');
            $temuPrice = (float) ($row['temu_price'] ?? 0);
            $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? 'REQ')));

            if ($inventory <= 0 || $nrReq !== 'REQ' || $missing === 'M' || $temuPrice <= 0 || $temuStock <= 0) {
                continue;
            }

            $diff = abs($inventory - $temuStock);
            $isNotMap = ($inventory * 0.03 < 3)
                ? ($diff > 3)
                : (round(($diff / $inventory) * 100) > 3);

            if (! $isNotMap) {
                continue;
            }

            $out[] = [
                'sku' => $sku,
                'channel_sku' => $sku,
                'inv' => $inventory,
                'channel_inv' => $temuStock,
                'diff' => $diff,
            ];
        }

        return $out;
    }
}

