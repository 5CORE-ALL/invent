<?php

namespace App\Http\Controllers\Channels;

use App\Console\Commands\TiktokSheetData;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAliexpressController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAmazonController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAppscenicController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAutoDSController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBestbuyUSAController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBusiness5CoreController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingDobaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayThreeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayTwoController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFaireController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBMarketplaceController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingInstagramShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMacysController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWoShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2BController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingOfferupController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPlsController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPoshmarkController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingReverbController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSheinController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyWholesaleController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSpocketController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSWGearExchangeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSynceeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTemuController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiendamiaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWalmartController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWayfairController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingYamibuyController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingZendropController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Models\AliExpressSheetData;
use App\Models\AliexpressListingStatus;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\AmazonSpCampaignReport;
use App\Models\ApiCentralWalmartApiData;
use App\Models\ApiCentralWalmartMetric;
use App\Models\BestbuyUsaProduct;
use App\Models\BusinessFiveCoreSheetdata;
use App\Models\ChannelMaster;
use App\Models\DobaListingStatus;
use App\Models\DobaMetric;
use App\Models\DobaSheetdata;
use App\Models\Ebay2Metric;
use App\Models\EbayTwoListingStatus;
use App\Models\Ebay3Metric;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\EbayOrder;
use App\Models\EbayOrderItem;
use App\Models\EbayPriorityReport;
use App\Models\FaireProductSheet;
use App\Models\FaireListingStatus;
use App\Models\FbMarketplaceSheetdata;
use App\Models\FBMarketplaceListingStatus;
use App\Models\FbShopSheetdata;
use App\Models\FBShopListingStatus;
use App\Models\Business5CoreListingStatus;
use App\Models\InstagramShopSheetdata;
use App\Models\InstagramShopListingStatus;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\MarketplaceDailyMetric;
use App\Models\MarketplacePercentage;
use App\Models\MercariWoShipSheetdata;
use App\Models\MercariWShipSheetdata;
use App\Models\MercariWShipListingStatus;
use App\Models\MercariWoShipListingStatus;
use App\Models\PLSProduct;
use App\Models\PlsListingStatus;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\ReverbProduct;
use App\Models\ReverbListingStatus;
use App\Models\SheinSheetData;
use App\Models\SheinDailyData;
use App\Models\SheinListingStatus;
use App\Models\ShopifySku;
use App\Models\TemuDailyData;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use App\Models\TiendamiaProduct;
use App\Models\TiendamiaListingStatus;
use App\Models\TiktokSheet;
use App\Models\TiktokShopListingStatus;
use App\Models\TopDawgSheetdata;
use App\Models\WaifairProductSheet;
use App\Models\WayfairListingStatus;
use App\Models\WalmartListingStatus;
use App\Models\WalmartMetrics;
use App\Models\AmazonListingStatus;
use App\Models\EbayListingStatus;
use App\Models\TemuListingStatus;
use App\Models\BestbuyUSAListingStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\FlareClient\Api;

class ChannelMasterController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Handle dynamic route parameters and return a view.
     */
    public function channel_master_index(Request $request, $first = null, $second = null)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        if ($first === "assets") {
            return redirect('home');
        }

        // return view($first, compact('mode', 'demo', 'second', 'channels'));
        return view($first . '.' . $second, [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }


    public function getViewChannelData(Request $request)
    {
        // Fetch both channel and sheet_link from ChannelMaster
        $columns = ['channel', 'sheet_link', 'channel_percentage'];
        
        // Check if 'base' and 'target' columns exist before adding them
        if (Schema::hasColumn('channel_master', 'base')) {
            $columns[] = 'base';
        }
        if (Schema::hasColumn('channel_master', 'target')) {
            $columns[] = 'target';
        }
        
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('id', 'asc')
            ->get($columns);

        if ($channels->isEmpty()) {
            return response()->json(['status' => 404, 'message' => 'No active channel found']);
        }

        $finalData = [];

        // Map lowercase channel key => controller method
        $controllerMap = [
            'amazon'    => 'getAmazonChannelData',
            'ebay'      => 'getEbayChannelData',
            'ebaytwo'   => 'getEbaytwoChannelData',
            'ebaythree' => 'getEbaythreeChannelData',
            'macys'     => 'getMacysChannelData',
            'tiendamia' => 'getTiendamiaChannelData',
            'bestbuyusa'=> 'getBestbuyUsaChannelData',
            'reverb'    => 'getReverbChannelData',
            'doba'      => 'getDobaChannelData',
            'temu'      => 'getTemuChannelData',
            'walmart'   => 'getWalmartChannelData',
            'pls'       => 'getPlsChannelData',
            'wayfair'   => 'getWayfairChannelData',
            'faire'     => 'getFaireChannelData',
            'shein'     => 'getSheinChannelData',
            'tiktokshop'=> 'getTiktokChannelData',
            'instagramshop' => 'getInstagramChannelData',
            'aliexpress' => 'getAliexpressChannelData',
            'mercariwship' => 'getMercariWShipChannelData',
            'mercariwoship' => 'getMercariWoShipChannelData',
            'fbmarketplace' => 'getFbMarketplaceChannelData',
            'fbshop'    => 'getFbShopChannelData',
            'business5core'    => 'getBusiness5CoreChannelData',
            'topdawg'    => 'getTopDawgChannelData',
            'shopifyb2c' => 'getShopifyB2CChannelData',
            'shopifyb2b' => 'getShopifyB2BChannelData',
            // 'walmart' => 'getWalmartChannelData',
            // 'shopify' => 'getShopifyChannelData',
        ];

        foreach ($channels as $channelRow) {
            $channel = $channelRow->channel;

            // Base row
            $row = [
                'Channel '       => ucfirst($channel),
                'Link'           => null,
                'sheet_link'     => $channelRow->sheet_link,
                'L-60 Sales'     => 0,
                'L30 Sales'      => 0,
                'Growth'         => 0,
                'L60 Orders'     => 0,
                'L30 Orders'     => 0,
                'Gprofit%'       => 'N/A',
                'gprofitL60'     => 'N/A',
                'G Roi%'         => 'N/A',
                'G RoiL60'       => 'N/A',
                'red_margin'     => 0,
                'NR'             => 0,
                'type'           => '',
                'listed_count'   => 0,
                'W/Ads'          => 0,
                'channel_percentage' => $channelRow->channel_percentage ?? '',
                'base' => $channelRow->base ?? 0,
                'target' => $channelRow->target ?? 0,
                // '0 Sold SKU Count' => 0,
                // 'Sold SKU Count'   => 0,
                // 'Brand Registry'   => '',
                'Update'         => 0,
                'Account health' => null,
            ];

            // Normalize channel name for lookup
            $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));

            if (isset($controllerMap[$key]) && method_exists($this, $controllerMap[$key])) {
                $method = $controllerMap[$key];
                $data = $this->$method($request)->getData(true); // call respective function
                if (!empty($data['data'])) {
                    $row = array_merge($row, $data['data'][0]);
                }
            }

            $finalData[] = $row;
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Channel data fetched successfully',
            'data'    => $finalData,
        ]);
    }

    // get total inventory l30 values
    public function getViewChannelData1(Request $request)
{
    $channels = ChannelMaster::where('status', 'Active')
        ->orderBy('id', 'asc')
        ->get(['channel', 'sheet_link', 'channel_percentage']);

    if ($channels->isEmpty()) {
        return response()->json(['status' => 404, 'message' => 'No active channel found']);
    }

    $finalData = [];

    $controllerMap = [
        'amazon'    => 'getAmazonChannelData',
        'ebay'      => 'getEbayChannelData',
        'ebaytwo'   => 'getEbaytwoChannelData',
        'ebaythree' => 'getEbaythreeChannelData',
        'macys'     => 'getMacysChannelData',
        'tiendamia' => 'getTiendamiaChannelData',
        'bestbuyusa'=> 'getBestbuyUsaChannelData',
        'reverb'    => 'getReverbChannelData',
        'doba'      => 'getDobaChannelData',
        'temu'      => 'getTemuChannelData',
        'walmart'   => 'getWalmartChannelData',
        'pls'       => 'getPlsChannelData',
        'wayfair'   => 'getWayfairChannelData',
        'faire'     => 'getFaireChannelData',
        'shein'     => 'getSheinChannelData',
        'tiktokshop'=> 'getTiktokChannelData',
        'instagramshop' => 'getInstagramChannelData',
        'aliexpress' => 'getAliexpressChannelData',
        'mercariwship' => 'getMercariWShipChannelData',
        'mercariwoship' => 'getMercariWoShipChannelData',
        'fbmarketplace' => 'getFbMarketplaceChannelData',
        'fbshop'    => 'getFbShopChannelData',
        'business5core'    => 'getBusiness5CoreChannelData',
        'topdawg'    => 'getTopDawgChannelData',
        'shopifyb2c' => 'getShopifyB2CChannelData',
        'shopifyb2b' => 'getShopifyB2BChannelData',
    ];

    foreach ($channels as $channelRow) {
        $channel = $channelRow->channel;

        $row = [
            'Channel '       => ucfirst($channel),
            'Link'           => null,
            'sheet_link'     => $channelRow->sheet_link,
            'L-60 Sales'     => 0,
            'L30 Sales'      => 0,
            'Growth'         => 0,
            'L60 Orders'     => 0,
            'L30 Orders'     => 0,
            'Gprofit%'       => 'N/A',
            'gprofitL60'     => 'N/A',
            'G Roi%'         => 'N/A',
            'G RoiL60'       => 'N/A',
            'red_margin'     => 0,
            'NR'             => 0,
            'type'           => '',
            'listed_count'   => 0,
            'W/Ads'          => 0,
            'channel_percentage' => $channelRow->channel_percentage ?? '',
            'Update'         => 0,
            'Account health' => null,
        ];

        $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));

        if (isset($controllerMap[$key]) && method_exists($this, $controllerMap[$key])) {
            $method = $controllerMap[$key];
            $data = $this->$method($request)->getData(true);
            if (!empty($data['data'])) {
                $row = array_merge($row, $data['data'][0]);
            }
        }

        $finalData[] = $row;
    }

    // ✅ Calculate total L30 Sales
    $totalL30Sales = collect($finalData)->sum(function ($item) {
        return (float) str_replace(',', '', $item['L30 Sales']);
    });

    return response()->json([
        'status'  => 200,
        'message' => 'Channel sales data fetched successfully',
        'data'    => $totalL30Sales,
    ]);
}



    public function getAmazonChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Amazon')->latest('date')->first();
        
        // Get L60 data from orders for comparison
        $l60OrdersQuery = AmazonOrder::where('period', 'l60')
            ->where('status', '!=', 'Canceled');
        $l60Orders = $l60OrdersQuery->count();
        
        // Calculate L60 Sales
        $sixtyDaysAgo = now()->subDays(60);
        $thirtyDaysAgo = now()->subDays(30);
        $l60OrderItems = AmazonOrder::where('order_date', '>=', $sixtyDaysAgo)
            ->where('order_date', '<', $thirtyDaysAgo)
            ->where('status', '!=', 'Canceled')
            ->join('amazon_order_items', 'amazon_orders.id', '=', 'amazon_order_items.amazon_order_id')
            ->select('amazon_order_items.price', 'amazon_order_items.quantity')
            ->get();
        
        $l60Sales = 0;
        foreach ($l60OrderItems as $item) {
            $quantity = (float) $item->quantity;
            if ($quantity > 0) {
                $l60Sales += (float) $item->price;
            }
        }

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? 0;
        $nRoi = $metrics->n_roi ?? 0;
        $kwSpent = $metrics->kw_spent ?? 0;
        $ptSpent = $metrics->pmt_spent ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage (still needs calculation if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? (($kwSpent + $ptSpent) / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Amazon')->first();

        // Calculate Missing Listing count (SKUs with INV > 0 that are not listed on Amazon, excluding NRL)
        $missingListingCount = $this->getAmazonMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['amazon'] ?? 0;

        $result[] = [
            'Channel '   => 'Amazon',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Amazon channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for Amazon
     * This counts SKUs with INV > 0 that are not listed on Amazon and are not marked as NRL
     */
    private function getAmazonMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonListed = \App\Models\ProductStockMapping::pluck('inventory_amazon_product', 'sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $mappingValue = $amazonListed[$sku] ?? null;
            $listed = null;
            if ($mappingValue !== null) {
                $normalized = strtolower(trim($mappingValue));
                $listed = $normalized !== '' && $normalized !== 'not listed' ? 'Listed' : 'Not Listed';
            } else {
                $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Not Listed';
                } else {
                    $listed = $listedValue;
                }
            }

            // Read NRL field from amazon_data_view - "REQ" means RL, "NRL" means NRL
            $nrlValue = $status['NRL'] ?? 'REQ';
            $nrReq = ($nrlValue === 'NRL') ? 'NR' : 'REQ';

            // Count as pending (missing) if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }

    /**
     * Calculate Missing Listing count for eBay
     * This counts SKUs with INV > 0 that are not listed on eBay and are not marked as NRL
     */
    private function getEbayMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = \App\Models\EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic - read from NRL field
            $nrlValue = $status['NRL'] ?? null;
            $nrReq = 'REQ'; // Default
            if ($nrlValue === 'NRL') {
                $nrReq = 'NRL';
            }

            // Read listed field
            $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
            $listed = null;
            if (is_bool($listedValue)) {
                $listed = $listedValue ? 'Listed' : 'Pending';
            } else if (is_string($listedValue)) {
                $listed = $listedValue;
            }

            // Count as pending (missing) if nr_req is not NRL AND listed is not Listed
            if ($nrReq !== 'NRL' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }


    public function getEbayChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'eBay')->latest('date')->first();
        
        // Get L60 data from orders for comparison
        $l60OrdersQuery = EbayOrder::where('period', 'l60');
        $l60Orders = $l60OrdersQuery->count();
        
        // Calculate L60 Sales
        $sixtyDaysAgo = now()->subDays(60);
        $thirtyDaysAgo = now()->subDays(30);
        $l60OrderItems = EbayOrder::where('order_date', '>=', $sixtyDaysAgo)
            ->where('order_date', '<', $thirtyDaysAgo)
            ->join('ebay_order_items', 'ebay_orders.id', '=', 'ebay_order_items.ebay_order_id')
            ->select('ebay_order_items.price', 'ebay_order_items.quantity')
            ->get();
        
        $l60Sales = 0;
        foreach ($l60OrderItems as $item) {
            $quantity = (float) $item->quantity;
            if ($quantity > 0) {
                $l60Sales += (float) $item->price;
            }
        }

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? 0;
        $nRoi = $metrics->n_roi ?? 0;
        $kwSpent = $metrics->kw_spent ?? 0;
        $pmtSpent = $metrics->pmt_spent ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage (still needs calculation if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? (($kwSpent + $pmtSpent) / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'eBay')->first();

        // Calculate Missing Listing count for eBay
        $missingListingCount = $this->getEbayMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['ebay1'] ?? 0;

        $result[] = [
            'Channel '   => 'eBay',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay channel data fetched successfully',
            'data' => $result,
        ]);
    }


    public function getEbaytwoChannelData(Request $request)
    {
        $result = [];

        // $query = Ebay2Metric::where('sku', 'not like', '%Parent%');

        $query = DB::connection('apicentral')
            ->table('ebay2_metrics')
            ->where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('ebay_l30');
        $l60Orders = $query->sum('ebay_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(ebay_l30 * ebay_price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(ebay_l60 * ebay_price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'EbayTwo')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'ebay_price', 'ebay_l30','ebay_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->ebay_price;
            $unitsL30  = (int) $row->ebay_l30;
            $unitsL60  = (int) $row->ebay_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'EbayTwo')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getEbayTwoMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['ebay2'] ?? 0;

        $result[] = [
            'Channel '   => 'EbayTwo',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay2 channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get eBay Two Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NR
     */
    private function getEbayTwoMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = EbayTwoListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // eBay Two uses nr_req (REQ/NR)
            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getEbaythreeChannelData(Request $request)
    {
        $result = [];

        $query = Ebay3Metric::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('ebay_l30');
        $l60Orders = $query->sum('ebay_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(ebay_l30 * ebay_price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(ebay_l60 * ebay_price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'EbayThree')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'ebay_price', 'ebay_l30','ebay_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->ebay_price;
            $unitsL30  = (int) $row->ebay_l30;
            $unitsL60  = (int) $row->ebay_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'EbayThree')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getEbayThreeMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['ebay3'] ?? 0;

        $result[] = [
            'Channel '   => 'EbayThree',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay three channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get eBay Three Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NR
     */
    private function getEbayThreeMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = EbayThreeListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // eBay Three uses nr_req (REQ/NR)
            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getMacysChannelData(Request $request)
    {
        $result = [];

        $query = MacyProduct::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('m_l30');
        $l60Orders = $query->sum('m_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(m_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(m_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Macys')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'm_l30','m_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->m_l30;
            $unitsL60  = (int) $row->m_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60       = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Macys')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getMacysMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['macy'] ?? 0;

        $result[] = [
            'Channel '   => 'Macys',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Macys channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getMacysMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = MacysListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            // Macy's uses rl_nrl instead of nr_req
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getReverbChannelData(Request $request)
    {
        $result = [];

        $query = ReverbProduct::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('r_l30');
        $l60Orders = $query->sum('r_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(r_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(r_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Reverb')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'r_l30','r_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->r_l30;
            $unitsL60  = (int) $row->r_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Reverb')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getReverbMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['reverb'] ?? 0;

        $result[] = [
            'Channel '   => 'Reverb',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Reverb channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Reverb Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NRL
     */
    private function getReverbMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = ReverbListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // Reverb uses rl_nrl (RL/NRL)
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getDobaChannelData(Request $request)
    {
        $result = [];

        $query = DobaSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Doba')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Doba')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getDobaMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['doba'] ?? 0;

        $result[] = [
            'Channel '   => 'Doba',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Doba channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getDobaMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = DobaListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getTemuChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Temu')->latest('date')->first();
        
        // L60 will be 0 until we have historical data with proper dates
        $l60Orders = 0;
        $l60Sales = 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Temu')->first();

        // Calculate Missing Listing count for Temu
        $missingListingCount = $this->getTemuMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['temu'] ?? 0;

        $result[] = [
            'Channel '   => 'Temu',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Temu channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for Temu
     * This counts SKUs with INV > 0 that are not listed and are not marked as NR
     */
    private function getTemuMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = \App\Models\TemuListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as pending (missing) if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }


    public function getWalmartChannelData(Request $request)
    {
        $result = [];

        $query = WalmartMetrics::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get Walmart marketing percentage
        $percentage = ChannelMaster::where('channel', 'Walmart')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Walmart')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getWalmartMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['walmart'] ?? 0;

        $result[] = [
            'Channel '   => 'Walmart',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];
        
        return response()->json([
            'status' => 200,
            'message' => 'Walmart channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getWalmartMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = WalmartListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            // Walmart uses rl_nrl instead of nr_req
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getTiendamiaChannelData(Request $request)
    {
        $result = [];

        $query = TiendamiaProduct::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('m_l30');
        $l60Orders = $query->sum('m_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(m_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(m_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Tiendamia')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'm_l30','m_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->m_l30;
            $unitsL60  = (int) $row->m_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Tiendamia')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getTiendamiaMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['tiendamia'] ?? 0;

        $result[] = [
            'Channel '   => 'Tiendamia',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Tiendamia channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Tiendamia
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getTiendamiaMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = TiendamiaListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getBestbuyUsaChannelData(Request $request)
    {
        $result = [];

        $query = BestbuyUsaProduct::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('m_l30');
        $l60Orders = $query->sum('m_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(m_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(m_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'BestBuy USA')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'm_l30','m_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->m_l30;
            $unitsL60  = (int) $row->m_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'BestBuy USA')->first();

        // Calculate Missing Listing count for BestBuy USA
        $missingListingCount = $this->getBestbuyUsaMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['bestbuy'] ?? 0;

        $result[] = [
            'Channel '   => 'BestBuy USA',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Bestbuy USA channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for BestBuy USA
     * This counts SKUs with INV > 0 that are not listed and are not marked as NR
     */
    private function getBestbuyUsaMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = \App\Models\BestbuyUSAListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as pending (missing) if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }

    public function getPlsChannelData(Request $request)
    {
        $result = [];

        $query = PLSProduct::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('p_l30');
        $l60Orders = $query->sum('p_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(p_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(p_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'PLS')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'p_l30','p_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->p_l30;
            $unitsL60  = (int) $row->p_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'PLS')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getPlsMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['pls'] ?? 0;

        $result[] = [
            'Channel '   => 'PLS',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'PLS channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for PLS
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getPlsMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = PlsListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getWayfairChannelData(Request $request)
    {
        $result = [];

        $query = WaifairProductSheet::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Wayfair')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Wayfair')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getWayfairMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['wayfair'] ?? 0;

        $result[] = [
            'Channel '   => 'Wayfair',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getWayfairMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = WayfairListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            // Wayfair uses rl_nrl instead of nr_req
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getFaireChannelData(Request $request)
    {
        $result = [];

        $query = FaireProductSheet::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('f_l30');
        $l60Orders = $query->sum('f_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(f_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(f_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Faire')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'f_l30','f_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->f_l30;
            $unitsL60  = (int) $row->f_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Faire')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getFaireMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['faire'] ?? 0;

        $result[] = [
            'Channel '   => 'Faire',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getSheinChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Shein')->latest('date')->first();
        
        // L60 will be 0 until we have historical data with proper dates
        $l60Orders = 0;
        $l60Sales = 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shein')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getSheinMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['shein'] ?? 0;

        $result[] = [
            'Channel '   => 'Shein',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Shein channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Shein Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NR
     */
    private function getSheinMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = SheinListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // Shein uses nr_req (REQ/NR)
            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getTiktokChannelData(Request $request)
    {
        $result = [];

        $query = TiktokSheet::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('shopify_tiktokl30');
        $l60Orders = $query->sum('shopify_tiktokl60');

        $l30Sales  = (clone $query)->selectRaw('SUM(shopify_tiktokl30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(shopify_tiktokl60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Tiktok Shop')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'shopify_tiktokl30','shopify_tiktokl60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->shopify_tiktokl30;
            $unitsL60  = (int) $row->shopify_tiktokl60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Tiktok Shop')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getTiktokShopMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['tiktok'] ?? 0;

        $result[] = [
            'Channel '   => 'Tiktok Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Tiktok channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getTiktokShopMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = TiktokShopListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getInstagramChannelData(Request $request)
    {
        $result = [];

        $query = InstagramShopSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('i_l30');
        $l60Orders = $query->sum('i_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(i_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(i_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Instagram Shop')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'i_l30','i_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->i_l30;
            $unitsL60  = (int) $row->i_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Instagram Shop')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getInstagramShopMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['instagram'] ?? 0;

        $result[] = [
            'Channel '   => 'Instagram Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Instagram Shop
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getInstagramShopMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = InstagramShopListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getAliexpressChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'AliExpress')->latest('date')->first();

        // Get L60 data from sheet data for comparison
        $query = AliExpressSheetData::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('aliexpress_l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(aliexpress_l60 * price) as total')->value('total') ?? 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Aliexpress')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getAliexpressMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['aliexpress'] ?? 0;

        $result[] = [
            'Channel '   => 'Aliexpress',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Aliexpress channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get AliExpress Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NRL
     */
    private function getAliexpressMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = AliexpressListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // AliExpress uses rl_nrl (RL/NRL)
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getMercariWShipChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Mercari With Ship')->latest('date')->first();

        // Get L60 data from sheet data for comparison
        $query = MercariWShipSheetdata::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Mercari w ship')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getMercariWShipMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['mercari_w_ship'] ?? 0;

        $result[] = [
            'Channel '   => 'Mercari w ship',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Mercari w ship channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Mercari w Ship
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getMercariWShipMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = MercariWShipListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getMercariWoShipChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Mercari Without Ship')->latest('date')->first();

        // Get L60 data from sheet data for comparison
        $query = MercariWoShipSheetdata::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Mercari wo ship')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getMercariWoShipMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['mercari_wo_ship'] ?? 0;

        $result[] = [
            'Channel '   => 'Mercari wo ship',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Mercari wo ship channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Mercari w/o Ship
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getMercariWoShipMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = MercariWoShipListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getFbMarketplaceChannelData(Request $request)
    {
        $result = [];

        $query = FbMarketplaceSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'FB Marketplace')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'FB Marketplace')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getFBMarketplaceMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['fb_marketplace'] ?? 0;

        $result[] = [
            'Channel '   => 'FB Marketplace',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for FB Marketplace
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getFBMarketplaceMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = FBMarketplaceListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getFbShopChannelData(Request $request)
    {
        $result = [];

        $query = FbShopSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'FB Shop')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'FB Shop')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getFBShopMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['fb_shop'] ?? 0;

        $result[] = [
            'Channel '   => 'FB Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getBusiness5CoreChannelData(Request $request)
    {
        $result = [];

        $query = BusinessFiveCoreSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Business 5Core')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Business 5Core')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getBusiness5CoreMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['business5core'] ?? 0;

        $result[] = [
            'Channel '   => 'Business 5Core',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getTopDawgChannelData(Request $request)
    {
        $result = [];

        $query = TopDawgSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'TopDawg')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'TopDawg')->first();

        // Get Missing Listing count
        $missingListingCount = 0; // TopDawg doesn't have listing status tracking

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['topdawg'] ?? 0;

        $result[] = [
            'Channel '   => 'TopDawg',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getShopifyB2CChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Shopify B2C')->latest('date')->first();
        
        // L60 will be 0 until we have historical data with proper dates
        $l60Orders = 0;
        $l60Sales = 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shopify B2C')->first();

        // Calculate Missing Listing count for Shopify B2C
        $missingListingCount = $this->getShopifyB2CMissingListingCount();

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['shopify_b2c'] ?? 0;

        $result[] = [
            'Channel '   => 'Shopify B2C',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Shopify B2C channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for Shopify B2C
     * This counts SKUs with INV > 0 that are not listed and are not marked as NRL
     */
    private function getShopifyB2CMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = \App\Models\ShopifyB2CListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // RL/NRL logic - support both new 'rl_nrl' and old 'nr_req'
            $rlNrl = $status['rl_nrl'] ?? null;
            if (empty($rlNrl) && isset($status['nr_req'])) {
                $rlNrl = ($status['nr_req'] === 'REQ') ? 'RL' : 'NRL';
            }
            if (empty($rlNrl)) $rlNrl = 'RL'; // default

            $listed = $status['listed'] ?? null;

            // Count as pending (missing) if rl_nrl is not NRL AND listed is not Listed
            if ($rlNrl !== 'NRL' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }

    public function getShopifyB2BChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Shopify B2B')->latest('date')->first();
        
        // L60 will be 0 until we have historical data with proper dates
        $l60Orders = 0;
        $l60Sales = 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shopify B2B')->first();

        // Get Missing Listing count
        $missingListingCount = 0; // Shopify B2B doesn't have listing status tracking

        // Get Stock Mapping not matching count
        $stockMappingStats = $this->getStockMappingStats();
        $stockMappingCount = $stockMappingStats['shopify_b2b'] ?? 0;

        $result[] = [
            'Channel '   => 'Shopify B2B',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Missing Listing' => $missingListingCount,
            'Stock Mapping' => $stockMappingCount,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Shopify B2B channel data fetched successfully',
            'data' => $result,
        ]);
    }
    


    /**
     * Store a newly created channel in storage.
     */
    public function store(Request $request)
    {
        // Validate Request Data
        $validatedData = $request->validate([
            'channel' => 'required|string',
            'sheet_link' => 'nullable|url',
            'type' => 'nullable|string',
            'channel_percentage' => 'nullable|numeric',
            // 'status' => 'required|in:Active,In Active,To Onboard,In Progress',
            // 'executive' => 'nullable|string',
            // 'b_link' => 'nullable|string',
            // 's_link' => 'nullable|string',
            // 'user_id' => 'nullable|string',
            // 'action_req' => 'nullable|string',
        ]);
        // Save Data to Database
        try {
            $channel = ChannelMaster::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'channel saved successfully',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving channel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save channel. Please try again.'
            ], 500);
        }
    }

    /**
     * Store a update channel in storage.
     */
    public function update(Request $request)
    {
        $originalChannel = $request->input('original_channel');
        $updatedChannel = $request->input('channel');
        $sheetUrl = $request->input('sheet_url');
        $type = $request->input('type');
        $channelPercentage = $request->input('channel_percentage');
        $base = $request->input('base');
        $target = $request->input('target');

        $channel = ChannelMaster::where('channel', $originalChannel)->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found']);
        }

        $channel->channel = $updatedChannel;
        $channel->sheet_link = $sheetUrl;
        $channel->type = $type;
        $channel->channel_percentage = $channelPercentage;
        $channel->base = $base;
        $channel->target = $target;
        $channel->save();

        MarketplacePercentage::updateOrCreate(
            ['marketplace' => $updatedChannel],
            ['percentage' => number_format((float)$channelPercentage, 2, '.', '')]
        );

        return response()->json(['success' => true]);
    }


    public function getChannelCounts()
    {
        // Fetch counts from the database
        $totalChannels = DB::table('channel_master')->count();
        $activeChannels = DB::table('channel_master')->where('status', 'Active')->count();
        $inactiveChannels = DB::table('channel_master')->where('status', 'In Active')->count();
    
        return response()->json([
            'success' => true,
            'totalChannels' => $totalChannels,
            'activeChannels' => $activeChannels,
            'inactiveChannels' => $inactiveChannels,
        ]);
    }

    public function destroy(Request $request)
    {
        // Delete channel from database
    }

    public function sendToGoogleSheet(Request $request)
    {

        $channel = $request->input('channel');
        $checked = $request->input('checked');

        Log::info('Received update-checkbox request', [
            'channel' => $channel,
            'checked' => $checked,
        ]);

        // Log for debugging
        Log::info("Updating GSheet for channel: $channel, checked: " . ($checked ? 'true' : 'false'));

        $url = 'https://script.google.com/macros/s/AKfycbzhlu7KV3dx3PS-9XPFBI9FMgI0JZIAgsuZY48Lchr_60gkSmx1hNAukKwFGZXgPwid/exec'; 

        $response = Http::post($url, [
            'channel' => $channel,
            'checked' => $checked
        ]);

        if ($response->successful()) {
            Log::info("Google Sheet updated successfully");
            return response()->json(['success' => true, 'message' => 'Updated GSheet']);
        } else {
            Log::error('Failed to send to GSheet:', [$response->body()]);
            return response()->json(['success' => false, 'message' => 'Failed to update GSheet'], 500);
        }
    }

    public function updateExecutive(Request $request)
    {
        $channel = trim($request->input('channel'));
        $exec = trim($request->input('exec'));

        $spreadsheetId = '13ZjGtJvSkiLHin2VnkBD-hrGimSRD7duVjILfkoJ2TA';
        $url = 'https://script.google.com/macros/s/AKfycbzYct_htZ_z89S36bPMDdjdDy6s1Nrzm79No6N2PqPriyrwXF1plIschk1c4cDnPYQ5/exec'; // Your Apps Script doPost URL

        $payload = [
            'channel' => $channel,
            'exec' => $exec,
            'action' => 'update_exec'
        ];

        $response = Http::post($url, $payload);

        if ($response->successful()) {
            return response()->json(['message' => 'Executive updated successfully.']);
        } else {
            return response()->json(['message' => 'Failed to update.'], 500);
        }
    }


    public function updateSheetLink(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'sheet_link' => 'nullable|url',
        ]);

        ChannelMaster::updateOrCreate(
            ['channel' => $request->channel], // search by channel
            ['sheet_link' => $request->sheet_link] // update or insert
        );

        return response()->json(['status' => 'success']);
    }

    public function toggleCheckboxFlag(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'field' => 'required|in:nr,w_ads,update',
            'value' => 'required|boolean'
        ]);

        $channelName = trim($request->channel);
        $field = $request->field;
        $value = $request->value;

        $channel = ChannelMaster::whereRaw('LOWER(channel) = ?', [strtolower($channelName)])->first();

        if ($channel) {
            $channel->$field = $value;
            $channel->save();
            return response()->json(['success' => true, 'message' => 'Channel updated.']);
        }

        // Channel not found — insert new row
        $newChannel = new ChannelMaster();
        $newChannel->channel = $channelName;
        $newChannel->$field = $value;
        $newChannel->save();

        return response()->json(['success' => true, 'message' => 'New channel inserted and updated.']);
    }


    public function updateType(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'type' => 'nullable|string'
        ]);

        $channelName = trim($request->input('channel'));
        $type = $request->input('type');

        $channel = ChannelMaster::where('channel', $channelName)->first();

        if (!$channel) {
            // If not found, create new
            $channel = new ChannelMaster();
            $channel->channel = $channelName;
        }

        $channel->type = $type;
        $channel->save();

        return response()->json([
            'success' => true,
            'message' => 'Type updated successfully.'
        ]);
    }

    public function updatePercentage(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'channel_percentage' => 'nullable|numeric'
        ]);

        $channelName = trim($request->input('channel'));
        $channelPercentage = $request->input('channel_percentage');

        $channel = ChannelMaster::where('channel', $channelName)->first();

        if (!$channel) {
            // If not found, create new
            $channel = new ChannelMaster();
            $channel->channel = $channelName;
        }

        $channel->channel_percentage = $channelPercentage;
        $channel->save();

        return response()->json([
            'success' => true,
            'message' => 'Channel percentage updated successfully.'
        ]);
    }


    private function getListedCount($channel)
    {
        $channel = strtolower(trim($channel));

        try {
            switch ($channel) {
                case 'amazon':
                    return app(ListingAmazonController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'ebay':
                    return app(ListingEbayController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'temu':
                    return app(ListingTemuController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'doba':
                    return app(ListingDobaController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'macys':
                    return app(ListingMacysController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'walmart':
                    return app(ListingWalmartController::class)->getNrReqCount()['Listed'] ?? 0;
                
                case 'wayfair':
                    return app(ListingWayfairController::class)->getNrReqCount()['Listed'] ?? 0;
                
                case 'ebay 3':
                    return app(ListingEbayThreeController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'shopify b2c':
                    return app(ListingShopifyB2CController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'reverb':
                    return app(ListingReverbController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'aliexpress':
                    return app(ListingAliexpressController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'shein':
                    return app(ListingSheinController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'tiktok shop':
                    return app(ListingTiktokShopController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'shopify wholesale/ds':
                    return app(ListingShopifyWholesaleController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'faire':
                    return app(ListingFaireController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'ebay 2':
                    return app(ListingEbayTwoController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'mercari w ship':
                    return app(ListingMercariWShipController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'newegg b2c':
                    return app(ListingNeweggB2CController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'fb marketplace':
                    return app(ListingFBMarketplaceController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'syncee':
                    return app(ListingSynceeController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'auto ds':
                    return app(ListingAutoDSController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'mercari w/o ship':
                    return app(ListingMercariWoShipController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'business 5core':
                    return app(ListingBusiness5CoreController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'zendrop':
                    return app(ListingZendropController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'poshmark':
                    return app(ListingPoshmarkController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'appscenic':
                    return app(ListingAppscenicController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'tiendamia':
                    return app(ListingTiendamiaController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'spocket':
                    return app(ListingSpocketController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'offerup':
                    return app(ListingOfferupController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'newegg b2b':
                    return app(ListingNeweggB2BController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'fb shop':
                    return app(ListingFBShopController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'instagram shop':
                    return app(ListingInstagramShopController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'Yamibuy':
                    return app(ListingYamibuyController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'bestbuy usa':
                    return app(ListingBestbuyUSAController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'sw gear exchange':
                    return app(ListingSWGearExchangeController::class)->getNrReqCount()['Listed'] ?? 0;

                default:
                    return 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }
    }


    // public function getSalesTrendData()
    // {
    //     $today = now();
    //     $l30Start = $today->copy()->subDays(30);
    //     $l60Start = $today->copy()->subDays(60);

    //     // Get daily sales for last 60 days
    //     $salesData = DB::connection('apicentral')
    //         ->table('shopify_order_items')
    //         ->select(
    //             DB::raw('DATE(order_date) as date'),
    //             DB::raw('SUM(quantity * price) as total_sales')
    //         )
    //         ->where('order_date', '>=', $l60Start)
    //         ->groupBy(DB::raw('DATE(order_date)'))
    //         ->orderBy('date', 'asc')
    //         ->get();

    //     // Split into two datasets (L30 & L60)
    //     $l30Data = [];
    //     $l60Data = [];

    //     foreach ($salesData as $row) {
    //         $date = Carbon::parse($row->date)->format('Y-m-d');
    //         if ($row->date >= $l30Start->toDateString()) {
    //             $l30Data[$date] = $row->total_sales;
    //         } else {
    //             $l60Data[$date] = $row->total_sales;
    //         }
    //     }

    //     // Prepare consistent date series
    //     $period = new \DatePeriod(
    //         $l60Start,
    //         new \DateInterval('P1D'),
    //         $today
    //     );

    //     $chartData = [];
    //     foreach ($period as $date) {
    //         $formatted = $date->format('Y-m-d');
    //         $chartData[] = [
    //             'date' => $formatted,
    //             'l30_sales' => $l30Data[$formatted] ?? 0,
    //             'l60_sales' => $l60Data[$formatted] ?? 0,
    //         ];
    //     }

    //      // Calculate GPROFIT using Shopify order items + Product Master
    //     $orderItems = DB::connection('apicentral')
    //         ->table('shopify_order_items')
    //         ->select('sku', 'quantity', 'price', 'order_date')
    //         ->where('order_date', '>=', $l60Start)
    //         ->get();

    //     if ($orderItems->isEmpty()) {
    //         foreach ($chartData as &$row) {
    //             $row['gprofit'] = 0;
    //         }
    //         return response()->json(['chartData' => $chartData]);
    //     }

    //     // Load product_master LP & SHIP
    //     $productMasters = ProductMaster::all()->keyBy(fn($item) => strtoupper($item->sku));

    //     $totalSalesL30 = 0;
    //     $totalProfitL30 = 0;

    //     foreach ($orderItems as $item) {
    //         $sku = strtoupper(trim($item->sku));
    //         $price = (float) $item->price;
    //         $qty = (int) $item->quantity;

    //         // Only count L30 for profit (recent 30 days)
    //         if ($item->order_date < $l30Start->toDateString()) {
    //             continue;
    //         }

    //         $lp = 0;
    //         $ship = 0;

    //         if (isset($productMasters[$sku])) {
    //             $pm = $productMasters[$sku];
    //             $values = is_array($pm->Values)
    //                 ? $pm->Values
    //                 : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

    //             $lp = $values['lp'] ?? $pm->lp ?? 0;
    //             $ship = $values['ship'] ?? $pm->ship ?? 0;
    //         }

    //         $sales = $qty * $price;
    //         $profit = ($price - $lp - $ship) * $qty;

    //         $totalSalesL30 += $sales;
    //         $totalProfitL30 += $profit;
    //     }

    //     $gProfitPct = $totalSalesL30 > 0 ? ($totalProfitL30 / $totalSalesL30) * 100 : 0;

    //     // Add GProfit% (flat line or future extension: date-wise)
    //     foreach ($chartData as &$row) {
    //         $row['gprofit'] = round($gProfitPct, 2);
    //     }

    //     return response()->json([
    //         'chartData' => $chartData,
    //         'summary' => [
    //             'total_sales_l30' => round($totalSalesL30, 2),
    //             'total_profit_l30' => round($totalProfitL30, 2),
    //             'gprofit' => round($gProfitPct, 2),
    //         ],
    //     ]);

    //     // return response()->json(['chartData' => $chartData]);
    // }


    public function getSalesTrendData()
    {
        $today = now();
        $l30Start = $today->copy()->subDays(30);
        $l60Start = $today->copy()->subDays(60);

        // Get daily sales for last 60 days
        $salesData = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->select(
                DB::raw('DATE(order_date) as date'),
                DB::raw('SUM(quantity * price) as total_sales')
            )
            ->where('order_date', '>=', $l60Start)
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date', 'asc')
            ->get();

        // Split into two datasets (L30 & L60)
        $l30Data = [];
        $l60Data = [];
        foreach ($salesData as $row) {
            $date = Carbon::parse($row->date)->format('Y-m-d');
            if ($row->date >= $l30Start->toDateString()) {
                $l30Data[$date] = $row->total_sales;
            } else {
                $l60Data[$date] = $row->total_sales;
            }
        }

        // Prepare consistent date series
        $period = new \DatePeriod(
            $l60Start,
            new \DateInterval('P1D'),
            $today
        );

        $chartData = [];
        foreach ($period as $date) {
            $formatted = $date->format('Y-m-d');
            $chartData[$formatted] = [
                'date' => $formatted,
                'l30_sales' => $l30Data[$formatted] ?? 0,
                'l60_sales' => $l60Data[$formatted] ?? 0,
                'gprofit' => 0, // initialize
            ];
        }

        // Load product_master LP & SHIP
        $productMasters = ProductMaster::all()->keyBy(fn($item) => strtoupper($item->sku));

        // Get order items for last 30 days (L30)
        $orderItems = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->select('sku', 'quantity', 'price', 'order_date')
            ->where('order_date', '>=', $l30Start)
            ->get();

        if ($orderItems->isNotEmpty()) {
            $dailySales = [];
            $dailyProfit = [];

            foreach ($orderItems as $item) {
                $sku = strtoupper(trim($item->sku));
                $date = Carbon::parse($item->order_date)->format('Y-m-d');
                $qty = (int) $item->quantity;
                $price = (float) $item->price;

                $lp = 0;
                $ship = 0;
                if (isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $lp = $values['lp'] ?? $pm->lp ?? 0;
                    $ship = $values['ship'] ?? $pm->ship ?? 0;
                }

                $sales = $qty * $price;
                $profit = ($price - $lp - $ship) * $qty;

                $dailySales[$date] = ($dailySales[$date] ?? 0) + $sales;
                $dailyProfit[$date] = ($dailyProfit[$date] ?? 0) + $profit;
            }

            // Assign GProfit per day
            foreach ($chartData as $date => &$row) {
                $sales = $dailySales[$date] ?? 0;
                $profit = $dailyProfit[$date] ?? 0;
                $row['gprofit'] = $sales > 0 ? round(($profit / $sales) * 100, 2) : 0;
            }
        }

        // Convert chartData to indexed array for JSON
        $chartData = array_values($chartData);

        // Optional: summary for total L30
        $totalSalesL30 = array_sum($dailySales ?? []);
        $totalProfitL30 = array_sum($dailyProfit ?? []);
        $totalGProfit = $totalSalesL30 > 0 ? ($totalProfitL30 / $totalSalesL30) * 100 : 0;

        return response()->json([
            'chartData' => $chartData,
            'summary' => [
                'total_sales_l30' => round($totalSalesL30, 2),
                'total_profit_l30' => round($totalProfitL30, 2),
                'gprofit' => round($totalGProfit, 2),
            ],
        ]);
    }

    /**
     * Get Dashboard Metrics: Total Sales, Profit Margin, and ROI
     * Uses the same data source and calculation as channel-masters page
     */
    public function getDashboardMetrics(Request $request)
    {
        try {
            // Get the same channel data that channel-masters page uses
            $response = $this->getViewChannelData($request);
            $data = $response->getData(true);

            if ($data['status'] !== 200 || empty($data['data'])) {
                Log::warning('getDashboardMetrics: No channel data found');
                return $this->getFallbackMetrics();
            }

            $channelData = $data['data'];
            
            // Parse numbers helper
            $parseNumber = function($value) {
                if (is_null($value)) return 0;
                if (is_numeric($value)) return floatval($value);
                // Remove commas and other formatting
                $cleaned = str_replace([',', '$', '%'], '', (string)$value);
                return floatval($cleaned) ?: 0;
            };

            // Calculate totals same way as updateAllTotals in channel-masters.blade.php
            $totalL30Sales = 0;
            $totalPft = 0;
            $totalCogs = 0;
            $dataFound = false;

            foreach ($channelData as $row) {
                $l30Sales = $parseNumber($row['L30 Sales'] ?? $row['l30_sales'] ?? 0);
                $gprofitPercent = $parseNumber($row['Gprofit%'] ?? $row['gprofit_percentage'] ?? 0);
                $cogs = $parseNumber($row['cogs'] ?? 0);

                // Convert % → absolute profit amount for this row
                $profitAmount = ($gprofitPercent / 100) * $l30Sales;

                $totalPft += $profitAmount;
                $totalL30Sales += $l30Sales;
                $totalCogs += $cogs;
                
                if ($l30Sales > 0) {
                    $dataFound = true;
                }
            }

            // Calculate metrics same way
            $gProfit = $totalL30Sales !== 0 ? ($totalPft / $totalL30Sales) * 100 : 0;
            $gRoi = $totalCogs !== 0 ? ($totalPft / $totalCogs) * 100 : 0;

            // Ensure valid numbers
            if (!is_finite($gProfit)) $gProfit = 0;
            if (!is_finite($gRoi)) $gRoi = 0;

            Log::info('Dashboard Metrics calculated from channel data', [
                'channels_count' => count($channelData),
                'total_sales' => $totalL30Sales,
                'profit_amount' => $totalPft,
                'profit_margin' => $gProfit,
                'roi' => $gRoi,
                'data_found' => $dataFound,
            ]);

            // Always return the actual calculated data from channels (even if zeros)
            // Don't use fallback data - return what we actually calculated
            return response()->json([
                'status' => 200,
                'data' => [
                    'total_sales' => round($totalL30Sales, 2),
                    'profit_margin' => round($gProfit, 2),
                    'roi' => round($gRoi, 2),
                    'total_profit' => round($totalPft, 2),
                    'total_cogs' => round($totalCogs, 2),
                    'currency' => 'USD',
                    'period' => 'L30 (Last 30 Days)',
                    'data_found' => $dataFound,
                    'channels_count' => count($channelData),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('getDashboardMetrics error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->getFallbackMetrics();
        }
    }

    /**
     * Get Faire Missing Listing count
     */
    private function getFaireMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = FaireListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingCount++;
            }
        }

        return $missingCount;
    }

    /**
     * Get FB Shop Missing Listing count
     */
    private function getFBShopMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = FBShopListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingCount++;
            }
        }

        return $missingCount;
    }

    /**
     * Get Business 5Core Missing Listing count
     */
    private function getBusiness5CoreMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = Business5CoreListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingCount++;
            }
        }

        return $missingCount;
    }

    /**
     * Get Stock Mapping not matching stats for all channels
     * Returns array with channel key => not matching count
     */
    private function getStockMappingStats()
    {
        static $cachedStats = null;
        
        if ($cachedStats !== null) {
            return $cachedStats;
        }

        $data = collect();
        ProductStockMapping::chunk(500, function ($items) use (&$data) {
            foreach ($items as $item) {
                if (!$data->has($item->sku)) {
                    $item->inventory_pls = null;
                    $item->inventory_business5core = null;
                    $data->put($item->sku, $item);
                }
            }
        });

        $skus = $data->pluck('sku')->filter()->unique()->toArray();

        $marketplaceModels = [
            'amazon' => AmazonListingStatus::class,
            'walmart' => WalmartListingStatus::class,
            'reverb' => ReverbListingStatus::class,
            'shein' => SheinListingStatus::class,
            'doba' => DobaListingStatus::class,
            'temu' => TemuListingStatus::class,
            'macy' => MacysListingStatus::class,
            'ebay1' => EbayListingStatus::class,
            'ebay2' => EbayTwoListingStatus::class,
            'ebay3' => EbayThreeListingStatus::class,
            'bestbuy' => BestbuyUSAListingStatus::class,
            'tiendamia' => TiendamiaListingStatus::class,
            'pls' => PlsListingStatus::class,
            'business5core' => Business5CoreListingStatus::class,
        ];

        foreach ($marketplaceModels as $key => $model) {
            $inventoryField = 'inventory_' . $key;
            $model::whereIn('sku', $skus)->chunk(500, function ($allListings) use (&$data, $inventoryField) {
                foreach ($allListings as $listing) {
                    $sku = $listing->sku ?? '';
                    $sku = str_replace("\u{00A0}", ' ', $sku);
                    $sku = trim(preg_replace('/\s+/', ' ', $sku));
                    if (isset($data[$sku])) {
                        $inventory = \Illuminate\Support\Arr::get($listing->value ?? [], 'inventory', null);
                        if ($inventory === 'Not Listed' || $inventory === 'NRL') {
                            $data[$sku]->$inventoryField = $inventory;
                        } elseif (is_numeric($inventory)) {
                            $data[$sku]->$inventoryField = (int)$inventory;
                        }
                    }
                }
            });
        }

        foreach ($data as $item) {
            if (!isset($item->inventory_pls) || $item->inventory_pls === null) {
                $item->inventory_pls = $item->inventory_shopify ?? 0;
            }
            if (!isset($item->inventory_business5core) || $item->inventory_business5core === null) {
                $item->inventory_business5core = $item->inventory_shopify ?? 0;
            }
        }

        $platforms = ['amazon', 'walmart', 'reverb', 'shein', 'doba', 'temu', 'macy', 'ebay1', 'ebay2', 'ebay3', 'bestbuy', 'tiendamia', 'pls', 'business5core'];

        $info = [];
        foreach ($platforms as $platform) {
            $info[$platform] = 0;
        }

        foreach ($data as $item) {
            $shopifyInventoryRaw = $item->inventory_shopify ?? 0;
            $shopifyInventory = is_numeric($shopifyInventoryRaw) ? (int)$shopifyInventoryRaw : 0;
            if ($shopifyInventory < 0) $shopifyInventory = 0;

            foreach ($platforms as $platform) {
                $fieldName = 'inventory_' . $platform;
                $platformInventoryRaw = $item->$fieldName ?? null;
                $platformInventory = is_numeric($platformInventoryRaw) ? (int)$platformInventoryRaw : 0;

                if (in_array($platformInventoryRaw, ['Not Listed', 'NRL'], true)) continue;
                if ($platformInventory === 0 && $shopifyInventory === 0) continue;

                $difference = abs($platformInventory - $shopifyInventory);
                if ($difference > 3) {
                    $info[$platform]++;
                }
            }
        }

        $cachedStats = $info;
        return $cachedStats;
    }

    /**
     * Return fallback/sample data
     */
    private function getFallbackMetrics()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'total_sales' => 56200,
                'profit_margin' => 75.65,
                'roi' => 68.5,
                'total_profit' => 42500,
                'total_cogs' => 13700,
                'currency' => 'USD',
                'period' => 'L30 (Last 30 Days)',
                'data_found' => false,
                'channels_count' => 0,
            ],
            'message' => 'Using sample data',
        ], 200);
    }




}
