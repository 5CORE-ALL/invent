<?php

namespace App\Http\Controllers\Channels;

use App\Console\Commands\TiktokSheetData;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AliExpressSheetData;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Models\BestbuyUsaProduct;
use App\Models\BusinessFiveCoreSheetdata;
use App\Models\ChannelMaster;
use App\Models\DobaMetric;
use App\Models\DobaSheetdata;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\FaireProductSheet;
use App\Models\FbMarketplaceSheetdata;
use App\Models\FbShopSheetdata;
use App\Models\InstagramShopSheetdata;
use App\Models\MacyProduct;
use App\Models\MarketplacePercentage;
use App\Models\MercariWoShipSheetdata;
use App\Models\MercariWShipSheetdata;
use App\Models\PLSProduct;
use App\Models\ProductMaster;
use App\Models\ReverbProduct;
use App\Models\SheinSheetData;
use App\Models\ADVMastersData;
use App\Models\ShopifySku;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use App\Models\TiendamiaProduct;
use App\Models\TiktokSheet;
use App\Models\TopDawgSheetdata;
use App\Models\WaifairProductSheet;
use App\Models\WalmartMetrics;
use App\Models\EbayDataView;
use App\Models\JungleScoutProductData;
use App\Models\EbayGeneralReport;
use App\Models\EbayPriorityReport;
use App\Models\WalmartProductSheet;
use App\Models\WalmartDataView;
use App\Models\EbayThreeDataView;
use App\Models\Ebay3PriorityReport;
use App\Models\Ebay3GeneralReport;
use App\Models\WalmartCampaignReport;
use App\Models\EbayTwoDataView;
use App\Models\Ebay2GeneralReport;
use App\Models\ADVMastersDailyData;
use App\Models\MarketplaceDailyMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Api;
           

class AdsMasterController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }


    public function getAdsMasterData(Request $request)
    {
        // Fetch both channel and sheet_link from ChannelMaster
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('id', 'asc')
            ->get(['channel', 'sheet_link', 'channel_percentage']);

        if ($channels->isEmpty()) {
            return response()->json(['status' => 404, 'message' => 'No active channel found']);
        }

        $finalData = [];

        // Map lowercase channel key => controller method
        $controllerMap = [
            'amazon'    => 'getAmazonAdRunningData',
            'ebay'      => 'getEbayChannelData',
            'ebaytwo'   => 'getEbaytwoChannelData',
            'ebaythree' => 'getEbaythreeChannelData',
            'macys'     => 'getMacysChannelData',
            'tiendamia' => 'getTiendamiaChannelData',
            'bestbuyusa' => 'getBestbuyUsaChannelData',
            'reverb'    => 'getReverbChannelData',
            'doba'      => 'getDobaChannelData',
            'temu'      => 'getTemuChannelData',
            'walmart'   => 'getWalmartChannelData',
            'pls'       => 'getPlsChannelData',
            'wayfair'   => 'getWayfairChannelData',
            'faire'     => 'getFaireChannelData',
            'shein'     => 'getSheinChannelData',
            'tiktokshop' => 'getTiktokChannelData',
            'instagramshop' => 'getInstagramChannelData',
            'aliexpress' => 'getAliexpressChannelData',
            'mercariwship' => 'getMercariWShipChannelData',
            'mercariwoship' => 'getMercariWoShipChannelData',
            'fbmarketplace' => 'getFbMarketplaceChannelData',
            'fbshop'    => 'getFbShopChannelData',
            'business5core'    => 'getBusiness5CoreChannelData',
            'topdawg'    => 'getTopDawgChannelData',
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


    public function getAmazonAdRunningData(Request $request)
    {
        $result = [];

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate L30 and L60 Sales from Amazon datasheets
        $l30Sales = AmazonDatasheet::whereIn('sku', $skus)->sum('units_ordered_l30');
        $l60Sales = AmazonDatasheet::whereIn('sku', $skus)->sum('units_ordered_l60');

        // If you have sales amount data, use that instead of units
        // $l30SalesAmount = AmazonDatasheet::whereIn('sku', $skus)->sum('sales_amount_l30');
        // $l60SalesAmount = AmazonDatasheet::whereIn('sku', $skus)->sum('sales_amount_l60');

        // For now, using units as placeholder - adjust based on your actual data
        $l30SalesAmount = $l30Sales;
        $l60SalesAmount = $l60Sales;

        $growth = $l30SalesAmount > 0 ? (($l30SalesAmount - $l60SalesAmount) / $l30SalesAmount) * 100 : 0;

        // Get ad campaign data
        $amazonKwL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $amazonPtL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonHlL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        // Calculate total ad metrics for each campaign type
        $totalSpendL30 = 0;
        $totalClicksL30 = 0;
        $totalImpressionsL30 = 0;
        $totalSoldL30 = 0;

        // Campaign type specific totals
        $kwSpendL30 = 0;
        $kwClicksL30 = 0;
        $kwImpressionsL30 = 0;
        $kwSoldL30 = 0;
        $kwSalesL30 = 0;

        $ptSpendL30 = 0;
        $ptClicksL30 = 0;
        $ptImpressionsL30 = 0;
        $ptSoldL30 = 0;
        $ptSalesL30 = 0;

        $hlSpendL30 = 0;
        $hlClicksL30 = 0;
        $hlImpressionsL30 = 0;
        $hlSoldL30 = 0;
        $hlSalesL30 = 0;

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $matchedCampaignKwL30 = $amazonKwL30->first(function ($item) use ($sku) {
                return strcasecmp(trim($item->campaignName), $sku) === 0;
            });

            $matchedCampaignPtL30 = $amazonPtL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (
                    (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED'
                );
            });

            $matchedCampaignHlL30 = $amazonHlL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $expected1 = $sku;
                $expected2 = $sku . ' HEAD';
                return ($cleanName === $expected1 || $cleanName === $expected2)
                    && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            // KW Campaign metrics
            $kwSpendL30 += $matchedCampaignKwL30->spend ?? 0;
            $kwClicksL30 += $matchedCampaignKwL30->clicks ?? 0;
            $kwImpressionsL30 += $matchedCampaignKwL30->impressions ?? 0;
            $kwSoldL30 += $matchedCampaignKwL30->unitsSoldClicks30d ?? 0;
            $kwSalesL30 += $matchedCampaignKwL30->sales30d ?? 0;

            // PT Campaign metrics
            $ptSpendL30 += $matchedCampaignPtL30->spend ?? 0;
            $ptClicksL30 += $matchedCampaignPtL30->clicks ?? 0;
            $ptImpressionsL30 += $matchedCampaignPtL30->impressions ?? 0;
            $ptSoldL30 += $matchedCampaignPtL30->unitsSoldClicks30d ?? 0;
            $ptSalesL30 += $matchedCampaignPtL30->sales30d ?? 0;

            // HL Campaign metrics
            $hlSpendL30 += $matchedCampaignHlL30->cost ?? 0;
            $hlClicksL30 += $matchedCampaignHlL30->clicks ?? 0;
            $hlImpressionsL30 += $matchedCampaignHlL30->impressions ?? 0;
            $hlSoldL30 += $matchedCampaignHlL30->unitsSold ?? 0;
            $hlSalesL30 += $matchedCampaignHlL30->sales ?? 0;

            // Add to totals
            $totalSpendL30 += ($matchedCampaignKwL30->spend ?? 0) +
                ($matchedCampaignPtL30->spend ?? 0) +
                ($matchedCampaignHlL30->cost ?? 0);

            $totalClicksL30 += ($matchedCampaignKwL30->clicks ?? 0) +
                ($matchedCampaignPtL30->clicks ?? 0) +
                ($matchedCampaignHlL30->clicks ?? 0);

            $totalImpressionsL30 += ($matchedCampaignKwL30->impressions ?? 0) +
                ($matchedCampaignPtL30->impressions ?? 0) +
                ($matchedCampaignHlL30->impressions ?? 0);

            $totalSoldL30 += ($matchedCampaignKwL30->unitsSoldClicks30d ?? 0) +
                ($matchedCampaignPtL30->unitsSoldClicks30d ?? 0) +
                ($matchedCampaignHlL30->unitsSold ?? 0);
        }

        // Get Amazon marketing percentage
        $percentage = ChannelMaster::where('channel', 'Amazon')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100;

        // Calculate profit (you'll need to adjust this based on your actual profit calculation)
        $totalProfit = $l30SalesAmount * 0.2; // Placeholder - 20% profit margin
        $totalProfitL60 = $l60SalesAmount * 0.2;

        $gProfitPct = $l30SalesAmount > 0 ? ($totalProfit / $l30SalesAmount) * 100 : 0;
        $gprofitL60 = $l60SalesAmount > 0 ? ($totalProfitL60 / $l60SalesAmount) * 100 : 0;

        // Calculate ROI
        $gRoi = $totalSpendL30 > 0 ? ($totalProfit / $totalSpendL30) * 100 : 0;
        $gRoiL60 = $totalSpendL30 > 0 ? ($totalProfitL60 / $totalSpendL30) * 100 : 0;

        // Calculate ACOS for each campaign type
        $kwAcos = $kwSalesL30 > 0 ? ($kwSpendL30 / $kwSalesL30) * 100 : 0;
        $ptAcos = $ptSalesL30 > 0 ? ($ptSpendL30 / $ptSalesL30) * 100 : 0;
        $hlAcos = $hlSalesL30 > 0 ? ($hlSpendL30 / $hlSalesL30) * 100 : 0;
        $totalAcos = $l30SalesAmount > 0 ? ($totalSpendL30 / $l30SalesAmount) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Amazon')->first();

        $result[] = [
            'Channel '   => 'Amazon',
            'Link'       => null,
            'sheet_link' => $channelData->sheet_link ?? '',
            'L-60 Sales' => intval($l60SalesAmount),
            'L30 Sales'  => intval($l30SalesAmount),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Sales, // Using sales units as orders
            'L30 Orders' => $l30Sales, // Using sales units as orders
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi%'     => round($gRoi, 2) . '%',
            'G RoiL60'   => round($gRoiL60, 2) . '%',
            'red_margin' => 0,
            'NR'         => $channelData->nr ?? 0,
            'type'       => $channelData->type ?? '',
            'listed_count' => count($skus),
            'W/Ads'      => $channelData->w_ads ?? 0,
            'channel_percentage' => $channelData->channel_percentage ?? '',
            'Update'     => $channelData->update ?? 0,
            'Account health' => null,

            // Total Ad metrics
            'SPEND_L30'  => round($totalSpendL30, 2),
            'CLICKS_L30' => $totalClicksL30,
            'IMPRESSIONS_L30' => $totalImpressionsL30,
            'SOLD_L30'   => $totalSoldL30,

            // KW Campaign metrics
            'KW_SPEND_L30' => round($kwSpendL30, 2),
            'KW_CLICKS_L30' => $kwClicksL30,
            'KW_IMPRESSIONS_L30' => $kwImpressionsL30,
            'KW_SOLD_L30' => $kwSoldL30,
            'KW_SALES_L30' => round($kwSalesL30, 2),
            'KW_ACOS' => round($kwAcos, 2) . '%',

            // PT Campaign metrics
            'PT_SPEND_L30' => round($ptSpendL30, 2),
            'PT_CLICKS_L30' => $ptClicksL30,
            'PT_IMPRESSIONS_L30' => $ptImpressionsL30,
            'PT_SOLD_L30' => $ptSoldL30,
            'PT_SALES_L30' => round($ptSalesL30, 2),
            'PT_ACOS' => round($ptAcos, 2) . '%',

            // HL Campaign metrics
            'HL_SPEND_L30' => round($hlSpendL30, 2),
            'HL_CLICKS_L30' => $hlClicksL30,
            'HL_IMPRESSIONS_L30' => $hlImpressionsL30,
            'HL_SOLD_L30' => $hlSoldL30,
            'HL_SALES_L30' => round($hlSalesL30, 2),
            'HL_ACOS' => round($hlAcos, 2) . '%',

            // Combined metrics
            'Ad Sales'   => intval($l30SalesAmount),
            'Ad Sold'    => $l30Sales,
            'ACOS'       => round($totalAcos, 2) . '%',
            'Tacos'      => 'N/A',
            'Pft'        => round($totalProfit, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Amazon channel data fetched successfully',
            'data' => $result,
        ]);
    }


    public function getEbayChannelData(Request $request)
    {
        $result = [];

        $query = EbayMetric::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('ebay_l30');
        $l60Orders = $query->sum('ebay_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(ebay_l30 * ebay_price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(ebay_l60 * ebay_price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'eBay')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'ebay_price', 'ebay_l30', 'ebay_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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

        // Channel data
        $channelData = ChannelMaster::where('channel', 'eBay')->first();

        $result[] = [
            'Channel '   => 'eBay',
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
        $ebayRows     = $query->get(['sku', 'ebay_price', 'ebay_l30', 'ebay_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay2 channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'ebay_price', 'ebay_l30', 'ebay_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay three channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'm_l30', 'm_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Macys')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Macys channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'r_l30', 'r_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Reverb')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Reverb channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Doba')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Doba channel data fetched successfully',
            'data' => $result,
        ]);
    }


    public function getTemuChannelData(Request $request)
    {
        $result = [];

        $query = TemuMetric::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('quantity_purchased_l30');
        $l60Orders = $query->sum('quantity_purchased_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(quantity_purchased_l30 * temu_sheet_price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(quantity_purchased_l60 * temu_sheet_price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Temu')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'temu_sheet_price', 'quantity_purchased_l30', 'quantity_purchased_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->temu_sheet_price;
            $unitsL30  = (int) $row->quantity_purchased_l30;
            $unitsL60  = (int) $row->quantity_purchased_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['temu_ship']) ? (float) $values['temu_ship'] : ($pm->temu_ship ?? 0);
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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Temu')->first();

        $result[] = [
            'Channel '   => 'Temu',
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
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Doba channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Walmart')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Walmart channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'm_l30', 'm_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Tiendamia')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Tiendamia channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'm_l30', 'm_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'BestBuy USA')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Bestbuy USA channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'p_l30', 'p_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'PLS')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'PLS channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Wayfair')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'f_l30', 'f_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Faire')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
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

        $query = SheinSheetData::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('shopify_sheinl30');
        $l60Orders = $query->sum('shopify_sheinl60');

        $l30Sales  = (clone $query)->selectRaw('SUM(shopify_sheinl30 * shopify_price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(shopify_sheinl60 * shopify_price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Shein')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'shopify_price', 'shopify_sheinl30', 'shopify_sheinl60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->shopify_price;
            $unitsL30  = (int) $row->shopify_sheinl30;
            $unitsL60  = (int) $row->shopify_sheinl60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Shein')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getTiktokChannelData(Request $request)
    {
        $result = [];

        $query = TiktokSheet::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Tiktok Shop')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Tiktok Shop')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'i_l30', 'i_l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Instagram Shop')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getAliexpressChannelData(Request $request)
    {
        $result = [];

        $query = AliExpressSheetData::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('aliexpress_l30');
        $l60Orders = $query->sum('aliexpress_l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(aliexpress_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(aliexpress_l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Aliexpress')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'aliexpress_l30', 'aliexpress_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->aliexpress_l30;
            $unitsL60  = (int) $row->aliexpress_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Aliexpress')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getMercariWShipChannelData(Request $request)
    {
        $result = [];

        $query = MercariWShipSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Mercari w ship')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Mercari w ship')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getMercariWoShipChannelData(Request $request)
    {
        $result = [];

        $query = MercariWoShipSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Mercari wo ship')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Mercari wo ship')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'FB Marketplace')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'FB Shop')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'Business 5Core')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
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
        $ebayRows     = $query->get(['sku', 'price', 'l30', 'l60']);
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

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
        $channelData = ChannelMaster::where('channel', 'TopDawg')->first();

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
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
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

        $channel = ChannelMaster::where('channel', $originalChannel)->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found']);
        }

        $channel->channel = $updatedChannel;
        $channel->sheet_link = $sheetUrl;
        $channel->type = $type;
        $channel->channel_percentage = $channelPercentage;
        $channel->save();

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

    public function channelAdsMaster(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        // return view($first, compact('mode', 'demo', 'second', 'channels'));
        return view('channels.ads-masters', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function channelAdvMaster(Request $request)
    {
        $advMasterDatas = ADVMastersData::get();
        
        // Calculate L60 date range (60 days ago to 30 days ago)
        $today = \Carbon\Carbon::today();
        $l60StartDate = $today->copy()->subDays(60)->format('Y-m-d');
        $l60EndDate = $today->copy()->subDays(31)->format('Y-m-d'); // 31 days ago to get 30 days of data
        
        // Get L60 spent data from daily data table
        $l60DailyData = ADVMastersDailyData::whereBetween('date', [$l60StartDate, $l60EndDate])
            ->select('channel', DB::raw('SUM(spent) as total_spent'), DB::raw('SUM(clicks) as total_clicks'), DB::raw('SUM(ad_sold) as total_ad_sold'), DB::raw('SUM(ad_sales) as total_ad_sales'))
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');
        
        // Initialize all variables to 0 to handle missing channels
        $amazon_l30_sales = 0;
        $amazon_spent = 0;
        $amazon_clicks = 0;
        $amazon_ad_sales = 0;
        $amazon_ad_sold = 0;
        $amazon_missing_ads = 0;
        
        $amazonkw_l30_sales = 0;
        $amazonkw_spent = 0;
        $amazonkw_clicks = 0;
        $amazonkw_ad_sales = 0;
        $amazonkw_ad_sold = 0;
        $amazonkw_missing_ads = 0;
        
        $amazonpt_l30_sales = 0;
        $amazonpt_spent = 0;
        $amazonpt_clicks = 0;
        $amazonpt_ad_sales = 0;
        $amazonpt_ad_sold = 0;
        $amazonpt_missing_ads = 0;
        
        $amazonhl_l30_sales = 0;
        $amazonhl_spent = 0;
        $amazonhl_clicks = 0;
        $amazonhl_ad_sales = 0;
        $amazonhl_ad_sold = 0;
        $amazonhl_missing_ads = 0;
        
        $ebay_l30_sales = 0;
        $ebay_spent = 0;
        $ebay_clicks = 0;
        $ebay_ad_sales = 0;
        $ebay_ad_sold = 0;
        $ebay_missing_ads = 0;
        
        $ebaykw_l30_sales = 0;
        $ebaykw_spent = 0;
        $ebaykw_clicks = 0;
        $ebaykw_ad_sales = 0;
        $ebaykw_ad_sold = 0;
        $ebaykw_missing_ads = 0;
        
        $ebaypmt_l30_sales = 0;
        $ebaypmt_spent = 0;
        $ebaypmt_clicks = 0;
        $ebaypmt_ad_sales = 0;
        $ebaypmt_ad_sold = 0;
        $ebaypmt_missing_ads = 0;
        
        $ebay2_l30_sales = 0;
        $ebay2_spent = 0;
        $ebay2_clicks = 0;
        $ebay2_ad_sales = 0;
        $ebay2_ad_sold = 0;
        $ebay2_missing_ads = 0;
        
        $ebay2pmt_l30_sales = 0;
        $ebay2pmt_spent = 0;
        $ebay2pmt_clicks = 0;
        $ebay2pmt_ad_sales = 0;
        $ebay2pmt_ad_sold = 0;
        $ebay2pmt_missing_ads = 0;
        
        $ebay3_l30_sales = 0;
        $ebay3_spent = 0;
        $ebay3_clicks = 0;
        $ebay3_ad_sales = 0;
        $ebay3_ad_sold = 0;
        $ebay3_missing_ads = 0;
        
        $ebay3kw_l30_sales = 0;
        $ebay3kw_spent = 0;
        $ebay3kw_clicks = 0;
        $ebay3kw_ad_sales = 0;
        $ebay3kw_ad_sold = 0;
        $ebay3kw_missing_ads = 0;
        
        $ebay3pmt_l30_sales = 0;
        $ebay3pmt_spent = 0;
        $ebay3pmt_clicks = 0;
        $ebay3pmt_ad_sales = 0;
        $ebay3pmt_ad_sold = 0;
        $ebay3pmt_missing_ads = 0;
        
        $walmart_l30_sales = 0;
        $walmart_spent = 0;
        $walmart_clicks = 0;
        $walmart_ad_sales = 0;
        $walmart_ad_sold = 0;
        $walmart_missing_ads = 0;
        
        $gshoping_l30_sales = 0;
        $gshoping_spent = 0;
        $gshoping_clicks = 0;
        $gshoping_ad_sales = 0;
        $gshoping_ad_sold = 0;
        $gshoping_missing_ads = 0;
        
        $total_l30_sales = 0;
        $total_spent = 0;
        $total_l60_spent = 0;
        $total_clicks = 0;
        $total_ad_sales = 0;
        $total_ad_sold = 0;
        $total_missing = 0;
        
        // Initialize L60 spent variables
        $amazon_l60_spent = 0;
        $amazonkw_l60_spent = 0;
        $amazonpt_l60_spent = 0;
        $amazonhl_l60_spent = 0;
        $ebay_l60_spent = 0;
        $ebaykw_l60_spent = 0;
        $ebaypmt_l60_spent = 0;
        $ebay2_l60_spent = 0;
        $ebay2pmt_l60_spent = 0;
        $ebay3_l60_spent = 0;
        $ebay3kw_l60_spent = 0;
        $ebay3pmt_l60_spent = 0;
        $walmart_l60_spent = 0;
        $gshoping_l60_spent = 0;
        
        // Initialize L60 clicks, ad_sold, and ad_sales variables
        $amazonkw_l60_clicks = 0;
        $amazonpt_l60_clicks = 0;
        $amazonhl_l60_clicks = 0;
        $amazonkw_l60_ad_sold = 0;
        $amazonpt_l60_ad_sold = 0;
        $amazonhl_l60_ad_sold = 0;
        $amazonkw_l60_ad_sales = 0;
        $amazonpt_l60_ad_sales = 0;
        $amazonhl_l60_ad_sales = 0;
        
        $ebaykw_l60_clicks = 0;
        $ebaypmt_l60_clicks = 0;
        $ebaykw_l60_ad_sold = 0;
        $ebaypmt_l60_ad_sold = 0;
        $ebaykw_l60_ad_sales = 0;
        $ebaypmt_l60_ad_sales = 0;
        
        $ebay2pmt_l60_clicks = 0;
        $ebay2pmt_l60_ad_sold = 0;
        $ebay2pmt_l60_ad_sales = 0;
        
        $ebay3kw_l60_clicks = 0;
        $ebay3pmt_l60_clicks = 0;
        $ebay3kw_l60_ad_sold = 0;
        $ebay3pmt_l60_ad_sold = 0;
        $ebay3kw_l60_ad_sales = 0;
        $ebay3pmt_l60_ad_sales = 0;
        
        $walmart_l60_clicks = 0;
        $walmart_l60_ad_sold = 0;
        $walmart_l60_ad_sales = 0;
        
        $gshoping_l60_clicks = 0;
        $gshoping_l60_ad_sold = 0;
        $gshoping_l60_ad_sales = 0;

        // Fetch MarketplaceDailyMetric for all channels (same source as all-marketplace-master - correct L30 sales, spend)
        $amazonMetrics = MarketplaceDailyMetric::where('channel', 'Amazon')->latest('date')->first();
        $ebayMetrics = MarketplaceDailyMetric::where('channel', 'eBay')->latest('date')->first();
        $ebay2Metrics = MarketplaceDailyMetric::where('channel', 'eBay 2')->latest('date')->first();
        $ebay3Metrics = MarketplaceDailyMetric::where('channel', 'eBay 3')->latest('date')->first();
        $walmartMetrics = MarketplaceDailyMetric::where('channel', 'Walmart')->latest('date')->first();
        $temuMetrics = MarketplaceDailyMetric::where('channel', 'Temu')->latest('date')->first();
        $sheinMetrics = MarketplaceDailyMetric::where('channel', 'Shein')->latest('date')->first();
        $tiktokMetrics = MarketplaceDailyMetric::where('channel', 'TikTok')->latest('date')->first();
        $shopifyB2cMetrics = MarketplaceDailyMetric::where('channel', 'Shopify B2C')->latest('date')->first();
        $shopifyB2bMetrics = MarketplaceDailyMetric::where('channel', 'Shopify B2B')->latest('date')->first();
        $aliexpressMetrics = MarketplaceDailyMetric::where('channel', 'AliExpress')->latest('date')->first();
        $mercariWsMetrics = MarketplaceDailyMetric::where('channel', 'Mercari With Ship')->latest('date')->first();
        $mercariWosMetrics = MarketplaceDailyMetric::where('channel', 'Mercari Without Ship')->latest('date')->first();
        $macysMetrics = MarketplaceDailyMetric::where('channel', 'Macys')->latest('date')->first();
        $tiendamiaMetrics = MarketplaceDailyMetric::where('channel', 'Tiendamia')->latest('date')->first();
        $bestbuyMetrics = MarketplaceDailyMetric::where('channel', 'Best Buy USA')->latest('date')->first();
        $dobaMetrics = MarketplaceDailyMetric::where('channel', 'Doba')->latest('date')->first();
        
        foreach($advMasterDatas as $data)
        {
            // Handle null values from database by using null coalescing operator
            if($data->channel == 'AMAZON')
            {
                $amazon_l30_sales = $data->l30_sales ?? 0;
                $amazon_spent = $data->spent ?? 0;
                $amazon_clicks = $data->clicks ?? 0;
                if ($amazonMetrics) {
                    $amazon_l30_sales = (float) ($amazonMetrics->total_sales ?? $amazonMetrics->l30_sales ?? 0);
                    $amazon_spent = (float) (($amazonMetrics->kw_spent ?? 0) + ($amazonMetrics->pmt_spent ?? 0) + ($amazonMetrics->hl_spent ?? 0));
                }
                $amazon_ad_sales = $data->ad_sales ?? 0;
                $amazon_ad_sold = $data->ad_sold ?? 0;
                $amazon_missing_ads = $data->missing_ads ?? 0;

            }else if($data->channel == 'AMZ KW'){
                $amazonkw_l30_sales = $data->l30_sales ?? 0;
                $amazonkw_spent = $amazonMetrics ? (float) ($amazonMetrics->kw_spent ?? 0) : ($data->spent ?? 0);
                $amazonkw_clicks = $data->clicks ?? 0;
                $amazonkw_ad_sales = $data->ad_sales ?? 0;
                $amazonkw_ad_sold = $data->ad_sold ?? 0;
                $amazonkw_missing_ads = $data->missing_ads ?? 0;

            }else if($data->channel == 'AMZ PT'){
                $amazonpt_l30_sales = $data->l30_sales ?? 0;
                $amazonpt_spent = $amazonMetrics ? (float) ($amazonMetrics->pmt_spent ?? 0) : ($data->spent ?? 0);
                $amazonpt_clicks = $data->clicks ?? 0;
                $amazonpt_ad_sales = $data->ad_sales ?? 0;
                $amazonpt_ad_sold = $data->ad_sold ?? 0;
                $amazonpt_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'AMZ HL'){
                $amazonhl_l30_sales = $data->l30_sales ?? 0;
                $amazonhl_spent = $amazonMetrics ? (float) ($amazonMetrics->hl_spent ?? 0) : ($data->spent ?? 0);
                $amazonhl_clicks = $data->clicks ?? 0;
                $amazonhl_ad_sales = $data->ad_sales ?? 0;
                $amazonhl_ad_sold = $data->ad_sold ?? 0;
                $amazonhl_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EBAY'){
                $ebay_l30_sales = $data->l30_sales ?? 0;
                $ebay_spent = $data->spent ?? 0;
                $ebay_clicks = $data->clicks ?? 0;
                if ($ebayMetrics) {
                    $ebay_l30_sales = (float) ($ebayMetrics->total_sales ?? $ebayMetrics->l30_sales ?? 0);
                    $ebay_spent = (float) (($ebayMetrics->kw_spent ?? 0) + ($ebayMetrics->pmt_spent ?? 0) + ($ebayMetrics->hl_spent ?? 0));
                }
                $ebay_ad_sales = $data->ad_sales ?? 0;
                $ebay_ad_sold = $data->ad_sold ?? 0;
                $ebay_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EB KW'){
                $ebaykw_l30_sales = $data->l30_sales ?? 0;
                $ebaykw_spent = $ebayMetrics ? (float) ($ebayMetrics->kw_spent ?? 0) : ($data->spent ?? 0);
                $ebaykw_clicks = $data->clicks ?? 0;
                $ebaykw_ad_sales = $data->ad_sales ?? 0;
                $ebaykw_ad_sold = $data->ad_sold ?? 0;
                $ebaykw_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EB PMT'){
                $ebaypmt_l30_sales = $data->l30_sales ?? 0;
                $ebaypmt_spent = $ebayMetrics ? (float) ($ebayMetrics->pmt_spent ?? 0) : ($data->spent ?? 0);
                $ebaypmt_clicks = $data->clicks ?? 0;
                $ebaypmt_ad_sales = $data->ad_sales ?? 0;
                $ebaypmt_ad_sold = $data->ad_sold ?? 0;
                $ebaypmt_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EBAY 2'){
                $ebay2_l30_sales = $data->l30_sales ?? 0;
                $ebay2_spent = $data->spent ?? 0;
                $ebay2_clicks = $data->clicks ?? 0;
                if ($ebay2Metrics) {
                    $ebay2_l30_sales = (float) ($ebay2Metrics->total_sales ?? $ebay2Metrics->l30_sales ?? 0);
                    $ebay2_spent = (float) (($ebay2Metrics->kw_spent ?? 0) + ($ebay2Metrics->pmt_spent ?? 0) + ($ebay2Metrics->hl_spent ?? 0));
                }
                $ebay2_ad_sales = $data->ad_sales ?? 0;
                $ebay2_ad_sold = $data->ad_sold ?? 0;
                $ebay2_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EB PMT2'){
                $ebay2pmt_l30_sales = $data->l30_sales ?? 0;
                $ebay2pmt_spent = $ebay2Metrics ? (float) (($ebay2Metrics->kw_spent ?? 0) + ($ebay2Metrics->pmt_spent ?? 0)) : ($data->spent ?? 0);
                $ebay2pmt_clicks = $data->clicks ?? 0;
                $ebay2pmt_ad_sales = $data->ad_sales ?? 0;
                $ebay2pmt_ad_sold = $data->ad_sold ?? 0;
                $ebay2pmt_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EBAY 3'){
                $ebay3_l30_sales = $data->l30_sales ?? 0;
                $ebay3_spent = $data->spent ?? 0;
                $ebay3_clicks = $data->clicks ?? 0;
                if ($ebay3Metrics) {
                    $ebay3_l30_sales = (float) ($ebay3Metrics->total_sales ?? $ebay3Metrics->l30_sales ?? 0);
                    $ebay3_spent = (float) (($ebay3Metrics->kw_spent ?? 0) + ($ebay3Metrics->pmt_spent ?? 0) + ($ebay3Metrics->hl_spent ?? 0));
                }
                $ebay3_ad_sales = $data->ad_sales ?? 0;
                $ebay3_ad_sold = $data->ad_sold ?? 0;
                $ebay3_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EB KW3'){
                $ebay3kw_l30_sales = $data->l30_sales ?? 0;
                $ebay3kw_spent = $ebay3Metrics ? (float) ($ebay3Metrics->kw_spent ?? 0) : ($data->spent ?? 0);
                $ebay3kw_clicks = $data->clicks ?? 0;
                $ebay3kw_ad_sales = $data->ad_sales ?? 0;
                $ebay3kw_ad_sold = $data->ad_sold ?? 0;
                $ebay3kw_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'EB PMT3'){
                $ebay3pmt_l30_sales = $data->l30_sales ?? 0;
                $ebay3pmt_spent = $ebay3Metrics ? (float) ($ebay3Metrics->pmt_spent ?? 0) : ($data->spent ?? 0);
                $ebay3pmt_clicks = $data->clicks ?? 0;
                $ebay3pmt_ad_sales = $data->ad_sales ?? 0;
                $ebay3pmt_ad_sold = $data->ad_sold ?? 0;
                $ebay3pmt_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'WALMART'){
                $walmart_l30_sales = $data->l30_sales ?? 0;
                $walmart_spent = $data->spent ?? 0;
                $walmart_clicks = $data->clicks ?? 0;
                if ($walmartMetrics) {
                    $walmart_l30_sales = (float) ($walmartMetrics->total_sales ?? $walmartMetrics->l30_sales ?? 0);
                    $walmart_spent = (float) (($walmartMetrics->kw_spent ?? 0) + ($walmartMetrics->pmt_spent ?? 0) + ($walmartMetrics->hl_spent ?? 0));
                }
                $walmart_ad_sales = $data->ad_sales ?? 0;
                $walmart_ad_sold = $data->ad_sold ?? 0;
                $walmart_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'SHOPIFY'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'G SHOPPING'){
                $gshoping_l30_sales = $data->l30_sales ?? 0;
                $gshoping_spent = $data->spent ?? 0;
                $gshoping_clicks = $data->clicks ?? 0;
                $gshoping_ad_sales = $data->ad_sales ?? 0;
                $gshoping_ad_sold = $data->ad_sold ?? 0;
                $gshoping_missing_ads = $data->missing_ads ?? 0;
                
            }else if($data->channel == 'G SERP'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'FB CARAOUSAL'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'FB VIDEO'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'INSTA CARAOUSAL'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'INSTA VIDEO'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'YOUTUBE'){
                // Channel exists but no data assignment needed currently
                
            }else if($data->channel == 'TIKTOK'){
                // Channel exists but no data assignment needed currently
                
            }
        }
        
        // Fetch L60 data from daily data table for each channel
        // Amazon sub-channels
        if(isset($l60DailyData['AMZ KW'])){
            $amazonkw_l60_spent = $l60DailyData['AMZ KW']->total_spent ?? 0;
            $amazonkw_l60_clicks = $l60DailyData['AMZ KW']->total_clicks ?? 0;
            $amazonkw_l60_ad_sold = $l60DailyData['AMZ KW']->total_ad_sold ?? 0;
            $amazonkw_l60_ad_sales = $l60DailyData['AMZ KW']->total_ad_sales ?? 0;
        }
        if(isset($l60DailyData['AMZ PT'])){
            $amazonpt_l60_spent = $l60DailyData['AMZ PT']->total_spent ?? 0;
            $amazonpt_l60_clicks = $l60DailyData['AMZ PT']->total_clicks ?? 0;
            $amazonpt_l60_ad_sold = $l60DailyData['AMZ PT']->total_ad_sold ?? 0;
            $amazonpt_l60_ad_sales = $l60DailyData['AMZ PT']->total_ad_sales ?? 0;
        }
        if(isset($l60DailyData['AMZ HL'])){
            $amazonhl_l60_spent = $l60DailyData['AMZ HL']->total_spent ?? 0;
            $amazonhl_l60_clicks = $l60DailyData['AMZ HL']->total_clicks ?? 0;
            $amazonhl_l60_ad_sold = $l60DailyData['AMZ HL']->total_ad_sold ?? 0;
            $amazonhl_l60_ad_sales = $l60DailyData['AMZ HL']->total_ad_sales ?? 0;
        }
        
        // eBay sub-channels
        if(isset($l60DailyData['EB KW'])){
            $ebaykw_l60_spent = $l60DailyData['EB KW']->total_spent ?? 0;
            $ebaykw_l60_clicks = $l60DailyData['EB KW']->total_clicks ?? 0;
            $ebaykw_l60_ad_sold = $l60DailyData['EB KW']->total_ad_sold ?? 0;
            $ebaykw_l60_ad_sales = $l60DailyData['EB KW']->total_ad_sales ?? 0;
        }
        if(isset($l60DailyData['EB PMT'])){
            $ebaypmt_l60_spent = $l60DailyData['EB PMT']->total_spent ?? 0;
            $ebaypmt_l60_clicks = $l60DailyData['EB PMT']->total_clicks ?? 0;
            $ebaypmt_l60_ad_sold = $l60DailyData['EB PMT']->total_ad_sold ?? 0;
            $ebaypmt_l60_ad_sales = $l60DailyData['EB PMT']->total_ad_sales ?? 0;
        }
        
        // eBay 2 sub-channels
        if(isset($l60DailyData['EB PMT2'])){
            $ebay2pmt_l60_spent = $l60DailyData['EB PMT2']->total_spent ?? 0;
            $ebay2pmt_l60_clicks = $l60DailyData['EB PMT2']->total_clicks ?? 0;
            $ebay2pmt_l60_ad_sold = $l60DailyData['EB PMT2']->total_ad_sold ?? 0;
            $ebay2pmt_l60_ad_sales = $l60DailyData['EB PMT2']->total_ad_sales ?? 0;
        }
        
        // eBay 3 sub-channels
        if(isset($l60DailyData['EB KW3'])){
            $ebay3kw_l60_spent = $l60DailyData['EB KW3']->total_spent ?? 0;
            $ebay3kw_l60_clicks = $l60DailyData['EB KW3']->total_clicks ?? 0;
            $ebay3kw_l60_ad_sold = $l60DailyData['EB KW3']->total_ad_sold ?? 0;
            $ebay3kw_l60_ad_sales = $l60DailyData['EB KW3']->total_ad_sales ?? 0;
        }
        if(isset($l60DailyData['EB PMT3'])){
            $ebay3pmt_l60_spent = $l60DailyData['EB PMT3']->total_spent ?? 0;
            $ebay3pmt_l60_clicks = $l60DailyData['EB PMT3']->total_clicks ?? 0;
            $ebay3pmt_l60_ad_sold = $l60DailyData['EB PMT3']->total_ad_sold ?? 0;
            $ebay3pmt_l60_ad_sales = $l60DailyData['EB PMT3']->total_ad_sales ?? 0;
        }
        
        // Walmart
        if(isset($l60DailyData['WALMART'])){
            $walmart_l60_spent = $l60DailyData['WALMART']->total_spent ?? 0;
            $walmart_l60_clicks = $l60DailyData['WALMART']->total_clicks ?? 0;
            $walmart_l60_ad_sold = $l60DailyData['WALMART']->total_ad_sold ?? 0;
            $walmart_l60_ad_sales = $l60DailyData['WALMART']->total_ad_sales ?? 0;
        }
        
        // G SHOPPING
        if(isset($l60DailyData['G SHOPPING'])){
            $gshoping_l60_spent = $l60DailyData['G SHOPPING']->total_spent ?? 0;
            $gshoping_l60_clicks = $l60DailyData['G SHOPPING']->total_clicks ?? 0;
            $gshoping_l60_ad_sold = $l60DailyData['G SHOPPING']->total_ad_sold ?? 0;
            $gshoping_l60_ad_sales = $l60DailyData['G SHOPPING']->total_ad_sales ?? 0;
        }

        $roundVars = [
            'amazon_l30_sales', 'amazon_spent', 'amazon_clicks', 'amazon_ad_sales', 'amazon_ad_sold', 'amazon_missing_ads', 'amazonkw_l30_sales', 'amazonkw_spent', 'amazonkw_clicks', 'amazonkw_ad_sales', 'amazonkw_ad_sold', 'amazonkw_missing_ads', 'amazonpt_l30_sales', 'amazonpt_spent', 'amazonpt_clicks', 'amazonpt_ad_sales', 'amazonpt_ad_sold', 'amazonpt_missing_ads', 'amazonhl_l30_sales', 'amazonhl_spent', 'amazonhl_clicks', 'amazonhl_ad_sales', 'amazonhl_ad_sold', 'amazonhl_missing_ads', 'ebay_l30_sales', 'ebay_spent', 'ebay_clicks', 'ebay_ad_sales', 'ebay_ad_sold', 'ebay_missing_ads', 'ebaykw_l30_sales', 'ebaykw_spent', 'ebaykw_clicks', 'ebaykw_ad_sales', 'ebaykw_ad_sold', 'ebaykw_missing_ads', 'ebaypmt_l30_sales', 'ebaypmt_spent', 'ebaypmt_clicks', 'ebaypmt_ad_sales', 'ebaypmt_ad_sold', 'ebaypmt_missing_ads', 'ebay2_l30_sales', 'ebay2_spent', 'ebay2_clicks', 'ebay2_ad_sales', 'ebay2_ad_sold', 'ebay2_missing_ads', 'ebay2pmt_l30_sales', 'ebay2pmt_spent', 'ebay2pmt_clicks', 'ebay2pmt_ad_sales', 'ebay2pmt_ad_sold', 'ebay2pmt_missing_ads', 'ebay3_l30_sales', 'ebay3_spent', 'ebay3_clicks', 'ebay3_ad_sales', 'ebay3_ad_sold', 'ebay3_missing_ads', 'ebay3kw_l30_sales', 'ebay3kw_spent', 'ebay3kw_clicks', 'ebay3kw_ad_sales', 'ebay3kw_ad_sold', 'ebay3kw_missing_ads', 'ebay3pmt_l30_sales', 'ebay3pmt_spent', 'ebay3pmt_clicks', 'ebay3pmt_ad_sales', 'ebay3pmt_ad_sold', 'ebay3pmt_missing_ads', 'walmart_l30_sales', 'walmart_spent', 'walmart_clicks', 'walmart_ad_sales', 'walmart_ad_sold', 'walmart_missing_ads', 'gshoping_l30_sales', 'gshoping_spent', 'gshoping_clicks', 'gshoping_ad_sales', 'gshoping_ad_sold', 'gshoping_missing_ads', 'total_l30_sales', 'total_spent', 'total_clicks', 'total_ad_sales', 'total_ad_sold', 'total_missing'
        ];

        // Calculate L30 SPENT total BEFORE rounding to avoid rounding errors
        // Sum of ALL individual rows displayed in table (sub-channels + standalone channels)
        // Exclude main channel summary rows (AMAZON, EBAY, EBAY 2, EBAY 3) as they are calculated totals, not individual rows
        $total_spent = $amazonkw_spent + $amazonpt_spent + $amazonhl_spent
            + $ebaykw_spent + $ebaypmt_spent
            + $ebay2pmt_spent
            + $ebay3kw_spent + $ebay3pmt_spent
            + $walmart_spent + $gshoping_spent;
        
        // Round individual values AFTER calculating total
        foreach ($roundVars as $varName) {
            if (isset($$varName)) {
                $$varName = round((float) $$varName);
            }
        }
        
        // Calculate Amazon total from sub-channels (KW + PT + HL) AFTER rounding to match frontend display
        $amazon_spent_calculated = $amazonkw_spent + $amazonpt_spent + $amazonhl_spent;
        $amazon_clicks_calculated = $amazonkw_clicks + $amazonpt_clicks + $amazonhl_clicks;
        $amazon_ad_sales_calculated = $amazonkw_ad_sales + $amazonpt_ad_sales + $amazonhl_ad_sales;
        $amazon_ad_sold_calculated = $amazonkw_ad_sold + $amazonpt_ad_sold + $amazonhl_ad_sold;
        
        // Use calculated values for Amazon total (sum of rounded sub-channels)
        $amazon_spent = $amazon_spent_calculated;
        $amazon_clicks = $amazon_clicks_calculated;
        $amazon_ad_sales = $amazon_ad_sales_calculated;
        $amazon_ad_sold = $amazon_ad_sold_calculated;
        
        // Calculate eBay totals from sub-channels (after rounding)
        $ebay_spent_calculated = $ebaykw_spent + $ebaypmt_spent;
        $ebay_clicks_calculated = $ebaykw_clicks + $ebaypmt_clicks;
        $ebay_ad_sales_calculated = $ebaykw_ad_sales + $ebaypmt_ad_sales;
        $ebay_ad_sold_calculated = $ebaykw_ad_sold + $ebaypmt_ad_sold;
        
        $ebay_spent = $ebay_spent_calculated;
        $ebay_clicks = $ebay_clicks_calculated;
        $ebay_ad_sales = $ebay_ad_sales_calculated;
        $ebay_ad_sold = $ebay_ad_sold_calculated;
        
        // Calculate eBay 2 and eBay 3 totals from sub-channels (after rounding)
        $ebay2_spent = $ebay2pmt_spent; // eBay 2 only has PMT
        $ebay2_clicks = $ebay2pmt_clicks;
        $ebay2_ad_sales = $ebay2pmt_ad_sales;
        $ebay2_ad_sold = $ebay2pmt_ad_sold;
        
        $ebay3_spent_calculated = $ebay3kw_spent + $ebay3pmt_spent;
        $ebay3_clicks_calculated = $ebay3kw_clicks + $ebay3pmt_clicks;
        $ebay3_ad_sales_calculated = $ebay3kw_ad_sales + $ebay3pmt_ad_sales;
        $ebay3_ad_sold_calculated = $ebay3kw_ad_sold + $ebay3pmt_ad_sold;
        
        $ebay3_spent = $ebay3_spent_calculated;
        $ebay3_clicks = $ebay3_clicks_calculated;
        $ebay3_ad_sales = $ebay3_ad_sales_calculated;
        $ebay3_ad_sold = $ebay3_ad_sold_calculated;

        // Calculate totals - use main channel l30_sales only (not sub-channels, they're duplicates)
        $total_l30_sales = $amazon_l30_sales + $ebay_l30_sales + $ebay2_l30_sales + $ebay3_l30_sales + $walmart_l30_sales + $gshoping_l30_sales;
        
        // Calculate L60 spent totals (sum of sub-channels for main channels)
        $amazon_l60_spent = $amazonkw_l60_spent + $amazonpt_l60_spent + $amazonhl_l60_spent;
        $ebay_l60_spent = $ebaykw_l60_spent + $ebaypmt_l60_spent;
        $ebay2_l60_spent = $ebay2pmt_l60_spent;
        $ebay3_l60_spent = $ebay3kw_l60_spent + $ebay3pmt_l60_spent;
        $total_l60_spent = $amazon_l60_spent + $ebay_l60_spent + $ebay2_l60_spent + $ebay3_l60_spent + $walmart_l60_spent + $gshoping_l60_spent;
        // Calculate totals - use main channel values only (not sub-channels to avoid double counting)
        $total_clicks = $amazon_clicks + $ebay_clicks + $ebay2_clicks + $ebay3_clicks + $walmart_clicks + $gshoping_clicks;
        $total_ad_sales = $amazon_ad_sales + $ebay_ad_sales + $ebay2_ad_sales + $ebay3_ad_sales + $walmart_ad_sales + $gshoping_ad_sales;
        $total_ad_sold = $amazon_ad_sold + $ebay_ad_sold + $ebay2_ad_sold + $ebay3_ad_sold + $walmart_ad_sold + $gshoping_ad_sold;
        $total_missing = $amazon_missing_ads + $amazonkw_missing_ads + $amazonpt_missing_ads + $amazonhl_missing_ads
            + $ebay_missing_ads + $ebaykw_missing_ads + $ebaypmt_missing_ads
            + $ebay2_missing_ads + $ebay2pmt_missing_ads
            + $ebay3_missing_ads + $ebay3kw_missing_ads + $ebay3pmt_missing_ads
            + $walmart_missing_ads + $gshoping_missing_ads;

        // Calculate L60 clicks/ad_sold from daily data
        $total_l60_clicks = $amazonkw_l60_clicks + $amazonpt_l60_clicks + $amazonhl_l60_clicks
            + $ebaykw_l60_clicks + $ebaypmt_l60_clicks
            + $ebay2pmt_l60_clicks
            + $ebay3kw_l60_clicks + $ebay3pmt_l60_clicks
            + $walmart_l60_clicks + $gshoping_l60_clicks;
        
        $total_l60_ad_sold = $amazonkw_l60_ad_sold + $amazonpt_l60_ad_sold + $amazonhl_l60_ad_sold
            + $ebaykw_l60_ad_sold + $ebaypmt_l60_ad_sold
            + $ebay2pmt_l60_ad_sold
            + $ebay3kw_l60_ad_sold + $ebay3pmt_l60_ad_sold
            + $walmart_l60_ad_sold + $gshoping_l60_ad_sold;
        
        $total_l60_ad_sales = $amazonkw_l60_ad_sales + $amazonpt_l60_ad_sales + $amazonhl_l60_ad_sales
            + $ebaykw_l60_ad_sales + $ebaypmt_l60_ad_sales
            + $ebay2pmt_l60_ad_sales
            + $ebay3kw_l60_ad_sales + $ebay3pmt_l60_ad_sales
            + $walmart_l60_ad_sales + $gshoping_l60_ad_sales;

        // Channel-wise totals for "Active channels view / channel-wise" mode
        $channelWiseTotals = [];
        $channelData = [
            'AMAZON' => [
                'l30_sales' => $amazon_l30_sales,
                'spent' => $amazon_spent,
                'l60_spent' => $amazon_l60_spent,
                'clicks' => $amazon_clicks,
                'l60_clicks' => $amazonkw_l60_clicks + $amazonpt_l60_clicks + $amazonhl_l60_clicks,
                'ad_sales' => $amazon_ad_sales,
                'l60_ad_sales' => $amazonkw_l60_ad_sales + $amazonpt_l60_ad_sales + $amazonhl_l60_ad_sales,
                'ad_sold' => $amazon_ad_sold,
                'l60_ad_sold' => $amazonkw_l60_ad_sold + $amazonpt_l60_ad_sold + $amazonhl_l60_ad_sold,
                'missing' => $amazon_missing_ads + $amazonkw_missing_ads + $amazonpt_missing_ads + $amazonhl_missing_ads,
            ],
            'EBAY' => [
                'l30_sales' => $ebay_l30_sales,
                'spent' => $ebay_spent,
                'l60_spent' => $ebay_l60_spent,
                'clicks' => $ebay_clicks,
                'l60_clicks' => $ebaykw_l60_clicks + $ebaypmt_l60_clicks,
                'ad_sales' => $ebay_ad_sales,
                'l60_ad_sales' => $ebaykw_l60_ad_sales + $ebaypmt_l60_ad_sales,
                'ad_sold' => $ebay_ad_sold,
                'l60_ad_sold' => $ebaykw_l60_ad_sold + $ebaypmt_l60_ad_sold,
                'missing' => $ebay_missing_ads + $ebaykw_missing_ads + $ebaypmt_missing_ads,
            ],
            'EBAY 2' => [
                'l30_sales' => $ebay2_l30_sales,
                'spent' => $ebay2_spent,
                'l60_spent' => $ebay2_l60_spent,
                'clicks' => $ebay2_clicks,
                'l60_clicks' => $ebay2pmt_l60_clicks,
                'ad_sales' => $ebay2_ad_sales,
                'l60_ad_sales' => $ebay2pmt_l60_ad_sales,
                'ad_sold' => $ebay2_ad_sold,
                'l60_ad_sold' => $ebay2pmt_l60_ad_sold,
                'missing' => $ebay2_missing_ads + $ebay2pmt_missing_ads,
            ],
            'EBAY 3' => [
                'l30_sales' => $ebay3_l30_sales,
                'spent' => $ebay3_spent,
                'l60_spent' => $ebay3_l60_spent,
                'clicks' => $ebay3_clicks,
                'l60_clicks' => $ebay3kw_l60_clicks + $ebay3pmt_l60_clicks,
                'ad_sales' => $ebay3_ad_sales,
                'l60_ad_sales' => $ebay3kw_l60_ad_sales + $ebay3pmt_l60_ad_sales,
                'ad_sold' => $ebay3_ad_sold,
                'l60_ad_sold' => $ebay3kw_l60_ad_sold + $ebay3pmt_l60_ad_sold,
                'missing' => $ebay3_missing_ads + $ebay3kw_missing_ads + $ebay3pmt_missing_ads,
            ],
            'WALMART' => [
                'l30_sales' => $walmart_l30_sales,
                'spent' => $walmart_spent,
                'l60_spent' => $walmart_l60_spent,
                'clicks' => $walmart_clicks,
                'l60_clicks' => $walmart_l60_clicks,
                'ad_sales' => $walmart_ad_sales,
                'l60_ad_sales' => $walmart_l60_ad_sales,
                'ad_sold' => $walmart_ad_sold,
                'l60_ad_sold' => $walmart_l60_ad_sold,
                'missing' => $walmart_missing_ads,
            ],
            'G SHOPPING' => [
                'l30_sales' => $gshoping_l30_sales,
                'spent' => $gshoping_spent,
                'l60_spent' => $gshoping_l60_spent,
                'clicks' => $gshoping_clicks,
                'l60_clicks' => $gshoping_l60_clicks,
                'ad_sales' => $gshoping_ad_sales,
                'l60_ad_sales' => $gshoping_l60_ad_sales,
                'ad_sold' => $gshoping_ad_sold,
                'l60_ad_sold' => $gshoping_l60_ad_sold,
                'missing' => $gshoping_missing_ads,
            ],
        ];
        foreach ($channelData as $ch => $d) {
            $grw = ($d['l60_spent'] > 0) ? ($d['spent'] / $d['l60_spent']) * 100 : 0;
            $grw_clks = ($d['l60_clicks'] > 0) ? ($d['clicks'] / $d['l60_clicks']) * 100 : 0;
            $l30_acos = ($d['ad_sales'] > 0) ? ($d['spent'] / $d['ad_sales']) * 100 : 0;
            $l60_acos = (($d['l60_ad_sales'] ?? 0) > 0) ? ($d['l60_spent'] / $d['l60_ad_sales']) * 100 : 0;
            $l30_acos_val = ($d['ad_sales'] > 0) ? ($d['spent'] / $d['ad_sales']) * 100 : 0;
            $l60_acos_val = (($d['l60_ad_sales'] ?? 0) > 0) ? ($d['l60_spent'] / $d['l60_ad_sales']) * 100 : 0;
            $ctrl_acos = ($l60_acos_val > 0) ? (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100 : 0;
            $cvr = ($d['clicks'] > 0) ? ($d['ad_sold'] / $d['clicks']) * 100 : 0;
            $cvr_60 = ($d['l60_clicks'] > 0) ? ($d['l60_ad_sold'] / $d['l60_clicks']) * 100 : 0;
            $grw_cvr = ($cvr_60 > 0) ? (($cvr - $cvr_60) / $cvr_60) * 100 : 0;
            $channelWiseTotals[$ch] = [
                'l30_sales' => $d['l30_sales'],
                'spent' => $d['spent'],
                'l60_spent' => $d['l60_spent'],
                'clicks' => $d['clicks'],
                'l60_clicks' => $d['l60_clicks'],
                'ad_sales' => $d['ad_sales'],
                'l60_ad_sales' => $d['l60_ad_sales'] ?? 0,
                'ad_sold' => $d['ad_sold'],
                'l60_ad_sold' => $d['l60_ad_sold'],
                'missing' => $d['missing'],
                'grw' => round($grw, 2),
                'grw_clks' => round($grw_clks, 2),
                'l30_acos' => round($l30_acos, 2),
                'l60_acos' => $l60_acos,
                'ctrl_acos' => $ctrl_acos,
                'cvr' => round($cvr, 2),
                'cvr_60' => round($cvr_60, 2),
                'grw_cvr' => round($grw_cvr, 2),
            ];
        }
        $all_grw = $total_l60_spent > 0 ? ($total_spent / $total_l60_spent) * 100 : 0;
        $all_grw_clks = $total_l60_clicks > 0 ? ($total_clicks / $total_l60_clicks) * 100 : 0;
        $all_l30_acos = $total_ad_sales > 0 ? ($total_spent / $total_ad_sales) * 100 : 0;
        $all_cvr = $total_clicks > 0 ? ($total_ad_sold / $total_clicks) * 100 : 0;
        $all_cvr_60 = $total_l60_clicks > 0 ? ($total_l60_ad_sold / $total_l60_clicks) * 100 : 0;
        $all_grw_cvr = $all_cvr_60 > 0 ? (($all_cvr - $all_cvr_60) / $all_cvr_60) * 100 : 0;
        $channelWiseTotals['all'] = [
            'l30_sales' => $total_l30_sales,
            'spent' => $total_spent,
            'l60_spent' => $total_l60_spent,
            'clicks' => $total_clicks,
            'l60_clicks' => $total_l60_clicks,
            'ad_sales' => $total_ad_sales,
            'ad_sold' => $total_ad_sold,
            'l60_ad_sold' => $total_l60_ad_sold,
            'missing' => $total_missing,
            'grw' => round($all_grw, 2),
            'grw_clks' => round($all_grw_clks, 2),
            'l30_acos' => round($all_l30_acos, 2),
            'l60_acos' => 0,
            'ctrl_acos' => 0,
            'cvr' => round($all_cvr, 2),
            'cvr_60' => round($all_cvr_60, 2),
            'grw_cvr' => round($all_grw_cvr, 2),
        ];

        /** START AMZON GRAPH DATA **/

        $amazonDateArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('date')->map(fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d)->toArray();
        $amazonSpentArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('spent')->map(function ($value) {
        return $value ?? 0;
        })->toArray();
            $amazonclicksArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('clicks')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $amazonadSalesArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('ad_sales')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $amzonadSoldArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('ad_sold')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $amzonCpcArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('cpc')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $amazonCvrArray = ADVMastersDailyData::where('channel', 'AMAZON')->orderBy('date', 'asc')->pluck('cvr')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        /** END AMAZON GRAPH DATA **/


        /** START EBAY GRAPH DATA ***/

        $ebayDateArray = ADVMastersDailyData::where('channel', 'EBAY')->orderBy('date', 'asc')->pluck('date')->map(fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d)->toArray();
        $ebaySpentArray = ADVMastersDailyData::where('channel', 'EBAY')->orderBy('date', 'asc')->pluck('spent')->map(function ($value) {
        return $value ?? 0;
        })->toArray();
            $ebayclicksArray = ADVMastersDailyData::where('channel', 'EBAY')->orderBy('date', 'asc')->pluck('clicks')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $ebayadSalesArray = ADVMastersDailyData::where('channel', 'EBAY')->orderBy('date', 'asc')->pluck('ad_sales')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $ebayadSoldArray = ADVMastersDailyData::where('channel', 'EBAY')->orderBy('date', 'asc')->pluck('ad_sold')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $ebayCvrArray = ADVMastersDailyData::where('channel', 'EBAY')->orderBy('date', 'asc')->pluck('cvr')->map(function ($value) {
            return $value ?? 0;
        })->toArray();


        /** END EBAY GRAPH DATA **/

        // Fetch additional channels from channel_master that aren't in the hardcoded list
        $hardcodedChannels = ['AMAZON', 'AMZ KW', 'AMZ PT', 'AMZ HL', 'EBAY', 'EB KW', 'EB PMT', 'EBAY 2', 'EB PMT2', 'EBAY 3', 'EB KW3', 'EB PMT3', 'WALMART', 'G SHOPPING', 'G SERP', 'SHOPIFY', 'FB CARAOUSAL', 'FB VIDEO', 'INSTA CARAOUSAL', 'INSTA VIDEO', 'YOUTUBE', 'TIKTOK'];
        $additionalChannels = ChannelMaster::where('status', 'Active')
            ->whereNotIn('channel', $hardcodedChannels)
            ->orderBy('channel', 'asc')
            ->get(['channel', 'type', 'sheet_link']);
        
        // Channel name => MarketplaceDailyMetric channel (for override - same source as all-marketplace-master)
        $channelToMarketplaceMap = [
            'TikTok Shop' => $tiktokMetrics ?? null,
            'TikTok' => $tiktokMetrics ?? null,
            'Temu' => $temuMetrics ?? null,
            'Shein' => $sheinMetrics ?? null,
            'Shopify B2C' => $shopifyB2cMetrics ?? null,
            'Shopify B2B' => $shopifyB2bMetrics ?? null,
            'AliExpress' => $aliexpressMetrics ?? null,
            'Mercari With Ship' => $mercariWsMetrics ?? null,
            'Mercari Without Ship' => $mercariWosMetrics ?? null,
            'Macys' => $macysMetrics ?? null,
            'Tiendamia' => $tiendamiaMetrics ?? null,
            'Best Buy USA' => $bestbuyMetrics ?? null,
            'Doba' => $dobaMetrics ?? null,
        ];

        // For each additional channel, try to get data from ADVMastersData, override with MarketplaceDailyMetric when available
        $additionalChannelsData = [];
        foreach ($additionalChannels as $ch) {
            $channelData = ADVMastersData::where('channel', $ch->channel)->first();
            $l30Sales = $channelData ? ($channelData->l30_sales ?? 0) : 0;
            $spent = $channelData ? ($channelData->spent ?? 0) : 0;
            $metrics = $channelToMarketplaceMap[$ch->channel] ?? null;
            if ($metrics) {
                $l30Sales = (float) ($metrics->total_sales ?? $metrics->l30_sales ?? 0);
                $spent = (float) (($metrics->kw_spent ?? 0) + ($metrics->pmt_spent ?? 0) + ($metrics->hl_spent ?? 0));
            }
            $chData = [
                'channel' => $ch->channel,
                'type' => $ch->type ?? '',
                'sheet_link' => $ch->sheet_link ?? '',
                'l30_sales' => $l30Sales,
                'spent' => $spent,
                'l60_spent' => $channelData ? ($channelData->l60_spent ?? 0) : 0,
                'clicks' => $channelData ? ($channelData->clicks ?? 0) : 0,
                'l60_clicks' => $channelData ? ($channelData->l60_clicks ?? 0) : 0,
                'ad_sales' => $channelData ? ($channelData->ad_sales ?? 0) : 0,
                'ad_sold' => $channelData ? ($channelData->ad_sold ?? 0) : 0,
                'l60_ad_sold' => $channelData ? ($channelData->l60_ad_sold ?? 0) : 0,
                'missing_ads' => $channelData ? ($channelData->missing_ads ?? 0) : 0,
            ];
            $additionalChannelsData[] = $chData;
            
            // Add to channelWiseTotals for channel-wise view and graphs
            $grw = ($chData['l60_spent'] > 0) ? ($chData['spent'] / $chData['l60_spent']) * 100 : 0;
            $grw_clks = ($chData['l60_clicks'] > 0) ? ($chData['clicks'] / $chData['l60_clicks']) * 100 : 0;
            $l30_acos = ($chData['ad_sales'] > 0) ? ($chData['spent'] / $chData['ad_sales']) * 100 : 0;
            $l60_acos = 0;
            $ctrl_acos = 0;
            $cvr = ($chData['clicks'] > 0) ? ($chData['ad_sold'] / $chData['clicks']) * 100 : 0;
            $cvr_60 = ($chData['l60_clicks'] > 0) ? ($chData['l60_ad_sold'] / $chData['l60_clicks']) * 100 : 0;
            $grw_cvr = ($cvr_60 > 0) ? (($cvr - $cvr_60) / $cvr_60) * 100 : 0;
            $channelWiseTotals[$ch->channel] = [
                'l30_sales' => $chData['l30_sales'],
                'spent' => $chData['spent'],
                'l60_spent' => $chData['l60_spent'],
                'clicks' => $chData['clicks'],
                'l60_clicks' => $chData['l60_clicks'],
                'ad_sales' => $chData['ad_sales'],
                'ad_sold' => $chData['ad_sold'],
                'l60_ad_sold' => $chData['l60_ad_sold'],
                'missing' => $chData['missing_ads'],
                'grw' => round($grw, 2),
                'grw_clks' => round($grw_clks, 2),
                'l30_acos' => round($l30_acos, 2),
                'l60_acos' => $l60_acos,
                'ctrl_acos' => $ctrl_acos,
                'cvr' => round($cvr, 2),
                'cvr_60' => round($cvr_60, 2),
                'grw_cvr' => round($grw_cvr, 2),
            ];
        }

        // Add additional channels' L30 spent to total so badge/header match table sum
        foreach ($additionalChannelsData as $chData) {
            $total_spent += (float) ($chData['spent'] ?? 0);
        }
        // Sync channelWiseTotals['all'] with updated total_spent
        if (isset($channelWiseTotals['all'])) {
            $channelWiseTotals['all']['spent'] = $total_spent;
        }

        return view('channels.adv-masters', compact('amazon_l30_sales', 'amazon_spent', 'amazon_l60_spent', 'amazon_clicks', 'amazon_ad_sales', 'amazon_ad_sold', 'amazon_missing_ads', 'amazonkw_l30_sales', 'amazonkw_spent', 'amazonkw_l60_spent', 'amazonkw_clicks', 'amazonkw_ad_sales', 'amazonkw_ad_sold', 'amazonkw_missing_ads', 'amazonpt_l30_sales', 'amazonpt_spent', 'amazonpt_l60_spent', 'amazonpt_clicks', 'amazonpt_ad_sales', 'amazonpt_ad_sold', 'amazonpt_missing_ads', 'amazonhl_l30_sales', 'amazonhl_spent', 'amazonhl_l60_spent', 'amazonhl_clicks', 'amazonhl_ad_sales', 'amazonhl_ad_sold', 'amazonhl_missing_ads', 'ebay_l30_sales', 'ebay_spent', 'ebay_l60_spent', 'ebay_clicks', 'ebay_ad_sales', 'ebay_ad_sold', 'ebay_missing_ads', 'ebaykw_l30_sales', 'ebaykw_spent', 'ebaykw_l60_spent', 'ebaykw_clicks', 'ebaykw_ad_sales', 'ebaykw_ad_sold', 'ebaykw_missing_ads', 'ebaypmt_l30_sales', 'ebaypmt_spent', 'ebaypmt_l60_spent', 'ebaypmt_clicks', 'ebaypmt_ad_sales', 'ebaypmt_ad_sold', 'ebaypmt_missing_ads', 'ebay2_l30_sales', 'ebay2_spent', 'ebay2_l60_spent', 'ebay2_clicks', 'ebay2_ad_sales', 'ebay2_ad_sold', 'ebay2_missing_ads', 'ebay2pmt_l30_sales', 'ebay2pmt_spent', 'ebay2pmt_l60_spent', 'ebay2pmt_clicks', 'ebay2pmt_ad_sales', 'ebay2pmt_ad_sold', 'ebay2pmt_missing_ads', 'ebay3_l30_sales', 'ebay3_spent', 'ebay3_l60_spent', 'ebay3_clicks', 'ebay3_ad_sales', 'ebay3_ad_sold', 'ebay3_missing_ads', 'ebay3kw_l30_sales', 'ebay3kw_spent', 'ebay3kw_l60_spent', 'ebay3kw_clicks', 'ebay3kw_ad_sales', 'ebay3kw_ad_sold', 'ebay3kw_missing_ads', 'ebay3pmt_l30_sales', 'ebay3pmt_spent', 'ebay3pmt_l60_spent', 'ebay3pmt_clicks', 'ebay3pmt_ad_sales', 'ebay3pmt_ad_sold', 'ebay3pmt_missing_ads', 'walmart_l30_sales', 'walmart_spent', 'walmart_l60_spent', 'walmart_clicks', 'walmart_ad_sales', 'walmart_ad_sold', 'walmart_missing_ads', 'gshoping_l30_sales', 'gshoping_spent', 'gshoping_l60_spent', 'gshoping_clicks', 'gshoping_ad_sales', 'gshoping_ad_sold', 'gshoping_missing_ads', 'total_l30_sales', 'total_spent', 'total_l60_spent', 'total_clicks', 'total_ad_sales', 'total_ad_sold', 'total_missing', 'total_l60_clicks', 'total_l60_ad_sold', 'total_l60_ad_sales', 'channelWiseTotals', 'amazonDateArray', 'amazonSpentArray', 'amazonclicksArray', 'amazonadSalesArray', 'amzonadSoldArray', 'amzonCpcArray', 'amazonCvrArray', 'ebayDateArray', 'ebaySpentArray', 'ebayclicksArray', 'ebayadSalesArray', 'ebayadSoldArray', 'ebayCvrArray', 'additionalChannelsData'));
    }

    public function getAmazonAdvChartData(Request $request)
    {
        $fromDate = $request->amazonFromDate;
        $toDate = $request->amazonToDate;

        if (empty($fromDate) || empty($toDate)) {
            return response()->json(['error' => 'From date and to date are required'], 400);
        }

        $amazonDateArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('date')->map(fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d)->toArray();

        $amazonSpentArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('spent')->map(function ($value) {
        return $value ?? 0;
        })->toArray();

        $amazonclicksArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('clicks')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        $amazonadSalesArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('ad_sales')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        $amzonadSoldArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('ad_sold')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        $amzonCpcArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('cpc')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        $amazonCvrArray = ADVMastersDailyData::where('channel', 'AMAZON')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('cvr')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        return response()->json([
            'amazonDateArray' => $amazonDateArray,
            'amazonSpentArray' => $amazonSpentArray,
            'amazonclicksArray' => $amazonclicksArray,
            'amazonadSalesArray' => $amazonadSalesArray,
            'amzonadSoldArray' => $amzonadSoldArray,
            'amzonCpcArray' => $amzonCpcArray,
            'amazonCvrArray' => $amazonCvrArray,
        ]);
    }

    public function getEbayAdvChartData(Request $request)
    {
        $fromDate = $request->ebayFromDate;
        $toDate = $request->ebayToDate;

        if (empty($fromDate) || empty($toDate)) {
            return response()->json(['error' => 'From date and to date are required'], 400);
        }

        $ebayDateArray = ADVMastersDailyData::where('channel', 'EBAY')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('date')->map(fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d)->toArray();
        $ebaySpentArray = ADVMastersDailyData::where('channel', 'EBAY')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('spent')->map(function ($value) {
        return $value ?? 0;
        })->toArray();
            $ebayclicksArray = ADVMastersDailyData::where('channel', 'EBAY')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('clicks')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $ebayadSalesArray = ADVMastersDailyData::where('channel', 'EBAY')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('ad_sales')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $ebayadSoldArray = ADVMastersDailyData::where('channel', 'EBAY')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('ad_sold')->map(function ($value) {
            return $value ?? 0;
        })->toArray();
            $ebayCvrArray = ADVMastersDailyData::where('channel', 'EBAY')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->orderBy('date', 'asc')->pluck('cvr')->map(function ($value) {
            return $value ?? 0;
        })->toArray();

        return response()->json([
            'ebayDateArray' => $ebayDateArray,
            'ebaySpentArray' => $ebaySpentArray,
            'ebayclicksArray' => $ebayclicksArray,
            'ebayadSalesArray' => $ebayadSalesArray,
            'ebayadSoldArray' => $ebayadSoldArray,
            'ebayCvrArray' => $ebayCvrArray
        ]);

    }

    public function getChannelAdvChartData(Request $request)
    {
        $channel = $request->channel; // EBAY 2, EBAY 3, WALMART, G SHOPPING
        $fromDate = $request->fromDate;
        $toDate = $request->toDate;

        $validChannels = ['EBAY 2', 'EBAY 3', 'WALMART', 'G SHOPPING'];
        if (!in_array($channel, $validChannels)) {
            return response()->json(['error' => 'Invalid channel'], 400);
        }
        if (empty($fromDate) || empty($toDate)) {
            return response()->json(['error' => 'From date and to date are required'], 400);
        }

        $dateArray = ADVMastersDailyData::where('channel', $channel)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->pluck('date')
            ->map(fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d)
            ->toArray();

        $spentArray = ADVMastersDailyData::where('channel', $channel)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->pluck('spent')
            ->map(fn($v) => $v ?? 0)
            ->toArray();

        $clicksArray = ADVMastersDailyData::where('channel', $channel)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->pluck('clicks')
            ->map(fn($v) => $v ?? 0)
            ->toArray();

        $adSalesArray = ADVMastersDailyData::where('channel', $channel)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->pluck('ad_sales')
            ->map(fn($v) => $v ?? 0)
            ->toArray();

        $adSoldArray = ADVMastersDailyData::where('channel', $channel)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->pluck('ad_sold')
            ->map(fn($v) => $v ?? 0)
            ->toArray();

        $cvrArray = ADVMastersDailyData::where('channel', $channel)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->pluck('cvr')
            ->map(fn($v) => $v ?? 0)
            ->toArray();

        return response()->json([
            'dateArray' => $dateArray,
            'spentArray' => $spentArray,
            'clicksArray' => $clicksArray,
            'adSalesArray' => $adSalesArray,
            'adSoldArray' => $adSoldArray,
            'cvrArray' => $cvrArray,
        ]);
    }

    public function getChannelAdvMasterAmazonCronData(Request $request)
    {
        try {
            $result = ADVMastersData::getChannelAdvMasterAmazonCronDataProceed($request);
            if ($result == 1) {
                return response()->json(['success' => true, 'message' => 'Amazon cron data updated successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to update Amazon cron data. Check logs for details.'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Amazon Cron Controller Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getChannelAdvMasterAmazonCronMissingData(Request $request)
    {
        return ADVMastersData::getChannelAdvMasterAmazonCronMissingDataProceed($request);
    }

    public function getChannelAdvMasterAmazonCronTotalSaleData(Request $request)
    {
        return ADVMastersData::getChannelAdvMasterAmazonCronTotalSaleDataProceed($request); 
    }

    public function getChannelAdvMasterEbayCronData(Request $request)
    {
        try {
            $result = ADVMastersData::getChannelAdvMasterEbayCronDataProceed($request);
            if ($result == 1) {
                return response()->json(['success' => true, 'message' => 'Ebay cron data updated successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to update Ebay cron data. Check logs for details.'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Ebay Cron Controller Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getChannelAdvMasterEbayCronMissingData(Request $request)
    {
        try {
            $result = ADVMastersData::getChannelAdvMasterEbayCronMissingDataProceed($request);
            if ($result == 1) {
                return response()->json(['success' => true, 'message' => 'Ebay missing ads data updated successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to update Ebay missing ads data. Check logs for details.'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Ebay Missing Ads Cron Controller Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getChannelAdvMasterEbayCronTotalSaleData(Request $request)
    {
        try {
            $result = ADVMastersData::getChannelAdvMasterEbayCronTotalSaleDataProceed($request);
            if ($result == 1) {
                return response()->json(['success' => true, 'message' => 'Ebay total sales data updated successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to update Ebay total sales data. Check logs for details.'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Ebay Total Sales Cron Controller Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Consolidated Cron Job - Runs all ADV Masters cron jobs sequentially
     * This method executes all Amazon and eBay cron jobs in the correct order
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function runAllAdvMastersCronJobs(Request $request)
    {
        // Increase memory limit for cron jobs
        ini_set('memory_limit', '512M');
        set_time_limit(600); // 10 minutes timeout
        
        try {
            $startTime = microtime(true);
            $results = [
                'success' => true,
                'message' => 'All cron jobs executed',
                'timestamp' => now()->toDateTimeString(),
                'jobs' => [],
                'summary' => [
                    'total' => 0,
                    'succeeded' => 0,
                    'failed' => 0
                ],
                'execution_time' => 0
            ];

        // Define all cron jobs in execution order
        $cronJobs = [
            [
                'name' => 'Amazon Main Data',
                'method' => 'getChannelAdvMasterAmazonCronDataProceed',
                'type' => 'amazon'
            ],
            [
                'name' => 'Amazon Missing Ads',
                'method' => 'getChannelAdvMasterAmazonCronMissingDataProceed',
                'type' => 'amazon'
            ],
            [
                'name' => 'Amazon Total Sales',
                'method' => 'getChannelAdvMasterAmazonCronTotalSaleDataProceed',
                'type' => 'amazon'
            ],
            [
                'name' => 'eBay Main Data',
                'method' => 'getChannelAdvMasterEbayCronDataProceed',
                'type' => 'ebay'
            ],
            [
                'name' => 'eBay Missing Ads',
                'method' => 'getChannelAdvMasterEbayCronMissingDataProceed',
                'type' => 'ebay'
            ],
            [
                'name' => 'eBay Total Sales',
                'method' => 'getChannelAdvMasterEbayCronTotalSaleDataProceed',
                'type' => 'ebay'
            ],
            [
                'name' => 'eBay 2 Main Data',
                'method' => 'getEbay2AdvRunningAdDataSaveProceed',
                'type' => 'ebay2'
            ],
            [
                'name' => 'eBay 2 Missing Ads',
                'method' => 'getAdvEbay2MissingSaveDataProceed',
                'type' => 'ebay2'
            ],
            [
                'name' => 'eBay 2 Total Sales',
                'method' => 'getEbay2TotsalSaleDataSaveProceed',
                'type' => 'ebay2'
            ],
            [
                'name' => 'eBay 3 Main Data',
                'method' => 'getAdvEbay3AdRunningDataSaveProceed',
                'type' => 'ebay3'
            ],
            [
                'name' => 'eBay 3 Missing Ads',
                'method' => 'getEbay3MissingDataSaveProceed',
                'type' => 'ebay3'
            ],
            [
                'name' => 'eBay 3 Total Sales',
                'method' => 'getEbay3TotalSaleSaveDataProceed',
                'type' => 'ebay3'
            ],
            [
                'name' => 'Walmart Main Data',
                'method' => 'getAdvWalmartRunningSaveDataProceed',
                'type' => 'walmart'
            ],
            [
                'name' => 'G Shopping Main Data',
                'method' => 'getAdvShopifyGShoppingSaveDataProceed',
                'type' => 'gshopping'
            ]
        ];

        \Log::info('Starting consolidated ADV Masters cron job execution', [
            'total_jobs' => count($cronJobs),
            'timestamp' => now()->toDateTimeString()
        ]);

        // Execute each cron job
        foreach ($cronJobs as $job) {
            $jobStartTime = microtime(true);
            $jobResult = [
                'name' => $job['name'],
                'type' => $job['type'],
                'status' => 'pending',
                'message' => '',
                'execution_time' => 0,
                'error' => null
            ];

            try {
                \Log::info("Executing cron job: {$job['name']}");
                
                // Call the static method from ADVMastersData model using call_user_func
                $methodName = $job['method'];
                if (!method_exists(ADVMastersData::class, $methodName)) {
                    throw new \Exception("Method {$methodName} does not exist in ADVMastersData");
                }
                $result = call_user_func([ADVMastersData::class, $methodName], $request);
                
                $jobExecutionTime = round((microtime(true) - $jobStartTime) * 1000, 2); // in milliseconds
                
                if ($result == 1) {
                    $jobResult['status'] = 'success';
                    $jobResult['message'] = "{$job['name']} completed successfully";
                    $results['summary']['succeeded']++;
                    \Log::info("Cron job completed: {$job['name']}", [
                        'execution_time_ms' => $jobExecutionTime
                    ]);
                } else {
                    $jobResult['status'] = 'failed';
                    $jobResult['message'] = "{$job['name']} returned failure status";
                    $results['summary']['failed']++;
                    $results['success'] = false;
                    \Log::warning("Cron job failed: {$job['name']}", [
                        'execution_time_ms' => $jobExecutionTime
                    ]);
                }
                
                $jobResult['execution_time'] = $jobExecutionTime;
                
            } catch (\Exception $e) {
                $jobExecutionTime = round((microtime(true) - $jobStartTime) * 1000, 2);
                $jobResult['status'] = 'error';
                $jobResult['message'] = "Error executing {$job['name']}";
                $jobResult['error'] = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
                $jobResult['execution_time'] = $jobExecutionTime;
                $results['summary']['failed']++;
                $results['success'] = false;
                
                \Log::error("Cron job error: {$job['name']}", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'execution_time_ms' => $jobExecutionTime
                ]);
            }
            
            $results['jobs'][] = $jobResult;
            $results['summary']['total']++;
            
            // Free memory after each job
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Log memory usage
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
            \Log::info("Memory usage after {$job['name']}", [
                'current_mb' => $memoryUsage,
                'peak_mb' => $memoryPeak
            ]);
        }

        $totalExecutionTime = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds
        $results['execution_time'] = $totalExecutionTime;
        $results['message'] = $results['success'] 
            ? "All cron jobs completed successfully in {$totalExecutionTime}ms"
            : "Cron jobs completed with {$results['summary']['failed']} failure(s) in {$totalExecutionTime}ms";

        \Log::info('Consolidated ADV Masters cron job execution completed', [
            'summary' => $results['summary'],
            'total_execution_time_ms' => $totalExecutionTime
        ]);

            $statusCode = $results['success'] ? 200 : 207; // 207 Multi-Status for partial success
            
            return response()->json($results, $statusCode);
        } catch (\Exception $e) {
            \Log::error('Consolidated Cron Job Fatal Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Fatal error executing consolidated cron jobs: ' . $e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                'timestamp' => now()->toDateTimeString()
            ], 500);
        }
    }
}
