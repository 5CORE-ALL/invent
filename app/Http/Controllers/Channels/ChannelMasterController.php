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
use App\Models\Ebay2Order;
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
use App\Models\TiktokCampaignReport;
use App\Models\TiktokSheet;
use App\Models\TiktokShopListingStatus;
use App\Models\TopDawgSheetdata;
use App\Models\WayfairDailyData;
use App\Models\WayfairListingStatus;
use App\Models\WalmartListingStatus;
use App\Models\WalmartMetrics;
use App\Models\AmazonListingStatus;
use App\Models\EbayListingStatus;
use App\Models\TemuListingStatus;
use App\Models\BestbuyUSAListingStatus;
use App\Models\AmazonChannelSummary;
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
     * Get Map, Miss, and NMap counts from amazon_channel_summary_data table
     * This is a generic helper that works for all channels
     */
    private function getMapAndMissCounts($channelName)
    {
        $summaryData = AmazonChannelSummary::where('channel', strtolower($channelName))
            ->latest('snapshot_date')
            ->first();
        
        $mapCount = 0;
        $missCount = 0;
        $nmapCount = 0;
        
        if ($summaryData && $summaryData->summary_data) {
            // Check for 'map_count', 'mapping_count', or 'mapped_count' field names
            $mapCount = $summaryData->summary_data['map_count'] 
                     ?? $summaryData->summary_data['mapping_count']
                     ?? $summaryData->summary_data['mapped_count']
                     ?? 0;
            
            // Check for both 'missing_count' and 'missing_amazon_count' field names
            $missCount = $summaryData->summary_data['missing_count'] 
                      ?? $summaryData->summary_data['missing_amazon_count']
                      ?? 0;
            
            // Get nmap_count directly from summary data (check multiple possible field names)
            $nmapCount = $summaryData->summary_data['nmap_count']
                      ?? $summaryData->summary_data['not_mapped_count']
                      ?? $summaryData->summary_data['not_map_count']
                      ?? $summaryData->summary_data['nmapping_count']
                      ?? $summaryData->summary_data['inv_stock_count']  // eBay 3
                      ?? $summaryData->summary_data['inv_r_stock_count']  // Reverb
                      ?? $summaryData->summary_data['inv_tt_stock_count']  // TikTok
                      ?? 0;
        }
        
        return [
            'map' => $mapCount, 
            'miss' => $missCount,
            'nmap' => $nmapCount
        ];
    }

    /**
     * Fetch Amazon ad spend breakdown (KW, PT, HL) - same logic as Amazon KW/PT/HL Utilized charts.
     * Uses daily date rows (not L30) with default range: 31 days ago to 2 days ago.
     *
     * @return array{kw: float, pt: float, hl: float}
     */
    private function fetchAmazonAdSpendBreakdownFromTables(): array
    {
        $end = Carbon::now()->subDays(2)->format('Y-m-d');
        $start = Carbon::now()->subDays(31)->format('Y-m-d');

        // KW: same as filterAmazonUtilizedChartKw - daily dates, exclude PT/FBA, exclude ARCHIVED
        $kwSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
            ->sum('spend'), 2);

        // PT: same as filterAmazonUtilizedChartPt - daily dates, PT campaigns only
        $ptSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
            ->sum('spend'), 2);

        // HL: same as filterAmazonUtilizedChartHl - daily dates, amazon_sb_campaign_reports
        $hlSpent = round((float) DB::table('amazon_sb_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->sum('cost'), 2);

        return ['kw' => $kwSpent, 'pt' => $ptSpent, 'hl' => $hlSpent];
    }

    /**
     * Fetch Amazon FBA ad spend breakdown (KW, PT) - FBA-specific campaigns.
     * FBA KW: campaigns ending with 'FBA' but NOT 'FBA PT'
     * FBA PT: campaigns ending with 'FBA PT' or 'FBA PT.'
     *
     * @return array{kw: float, pt: float}
     */
    private function fetchAmazonFbaAdSpendBreakdownFromTables(): array
    {
        $end = Carbon::now()->subDays(2)->format('Y-m-d');
        $start = Carbon::now()->subDays(31)->format('Y-m-d');

        // FBA KW: campaigns ending with 'FBA' but NOT 'FBA PT' or 'FBA PT.'
        $kwSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->whereRaw("campaignName LIKE '%FBA'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
            ->sum('spend'), 2);

        // FBA PT: campaigns ending with 'FBA PT' or 'FBA PT.'
        $ptSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'");
            })
            ->sum('spend'), 2);

        return ['kw' => $kwSpent, 'pt' => $ptSpent];
    }

    /**
     * Fetch eBay KW and PMT spend from tables - same logic as Ebay KW Ads and PMT Ads pages.
     * Uses L30 rows, whereDate(updated_at >= 30 days ago), no campaignStatus filter (matches utilized pages).
     *
     * @param string $channel ebay, ebaytwo, or ebaythree
     * @return array{kw: float, pmt: float}
     */
    private function fetchEbayAdSpendBreakdownFromTables(string $channel): array
    {
        $channel = strtolower(trim($channel));
        // Use 31 days to match eBay Seller Hub "last 31 days" view
        $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $kwTable = match ($channel) {
            'ebay' => 'ebay_priority_reports',
            'ebaytwo' => 'ebay_2_priority_reports',
            'ebaythree' => 'ebay_3_priority_reports',
            default => null,
        };
        $pmtTable = match ($channel) {
            'ebay' => 'ebay_general_reports',
            'ebaytwo' => 'ebay_2_general_reports',
            'ebaythree' => 'ebay_3_general_reports',
            default => null,
        };

        if (!$kwTable || !$pmtTable) {
            return ['kw' => 0.0, 'pmt' => 0.0];
        }

        // Sum daily reports (individual date report_ranges) instead of L30 aggregate
        // Daily data is closer to eBay Seller Hub dashboard values
        if ($channel === 'ebaythree') {
            $kwSpent = (float) DB::table($kwTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->get()
                ->sum(fn ($r) => (float) preg_replace('/[^\d.]/', '', $r->cpc_ad_fees_payout_currency ?? '0'));
            $pmtSpent = (float) DB::table($pmtTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->get()
                ->sum(fn ($r) => (float) preg_replace('/[^\d.]/', '', $r->ad_fees ?? '0'));
        } else {
            $kwSpent = (float) DB::table($kwTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as total')
                ->value('total') ?? 0;
            $pmtSpent = (float) DB::table($pmtTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as total')
                ->value('total') ?? 0;
        }

        return ['kw' => round($kwSpent, 2), 'pmt' => round($pmtSpent, 2)];
    }

    /**
     * Fetch AD CLICKS, AD SALES, AD SOLD directly from campaign/report tables.
     * Same tables and logic as Ad Spend for each channel.
     *
     * @param string $channel Normalized channel key (amazon, ebay, ebaytwo, ebaythree, temu, walmart, shopifyb2c, tiktokshop)
     * @return array{clicks: int, ad_sales: float, ad_sold: int}
     */
    private function fetchAdMetricsFromTables(string $channel): array
    {
        $channel = strtolower(trim($channel));
        $defaults = [
            'clicks' => 0, 'ad_sales' => 0.0, 'ad_sold' => 0,
            'KW Clicks' => 0, 'PT Clicks' => 0, 'HL Clicks' => 0, 'PMT Clicks' => 0, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
            'KW Sales' => 0, 'PT Sales' => 0, 'HL Sales' => 0, 'PMT Sales' => 0, 'Shopping Sales' => 0, 'SERP Sales' => 0,
            'KW Sold' => 0, 'PT Sold' => 0, 'HL Sold' => 0, 'PMT Sold' => 0, 'Shopping Sold' => 0, 'SERP Sold' => 0,
            'KW ACOS' => 0, 'PT ACOS' => 0, 'HL ACOS' => 0, 'PMT ACOS' => 0, 'Shopping ACOS' => 0, 'SERP ACOS' => 0,
            'KW CVR' => 0, 'PT CVR' => 0, 'HL CVR' => 0, 'PMT CVR' => 0, 'Shopping CVR' => 0, 'SERP CVR' => 0,
        ];

        try {
            switch ($channel) {
                case 'amazon': {
                    // Use LATEST L30 row per campaign (by MAX(id)) to get current values
                    // This avoids inflated values from MAX(spend) when multiple L30 rows exist per campaign

                    // KW: SP campaigns NOT ending with PT or FBA - get latest L30 row IDs
                    $kwLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $kwData = $kwLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $kwLatestIds)->get()
                        : collect();

                    // PT: SP campaigns ending with PT (not FBA PT) - get latest L30 row IDs
                    $ptLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->where(function ($q) {
                            $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'");
                        })
                        ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $ptData = $ptLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $ptLatestIds)->get()
                        : collect();

                    // HL: SB campaigns - get latest L30 row IDs
                    $hlLatestIds = DB::table('amazon_sb_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $hlData = $hlLatestIds->isNotEmpty()
                        ? DB::table('amazon_sb_campaign_reports')->whereIn('id', $hlLatestIds)->get()
                        : collect();

                    // Use 7-day attribution (sales7d, purchases7d) to match Amazon Advertising dashboard default
                    $kwC = (int) $kwData->sum('clicks'); $kwS = (float) $kwData->sum('sales7d'); $kwU = (int) $kwData->sum('purchases7d'); $kwSp = (float) $kwData->sum('spend');
                    $ptC = (int) $ptData->sum('clicks'); $ptS = (float) $ptData->sum('sales7d'); $ptU = (int) $ptData->sum('purchases7d'); $ptSp = (float) $ptData->sum('spend');
                    $hlC = (int) $hlData->sum('clicks'); $hlS = (float) $hlData->sum('sales'); $hlU = (int) $hlData->sum('purchases'); $hlSp = (float) $hlData->sum('cost');

                    $totalSpend = round($kwSp + $ptSp + $hlSp, 2);

                    return [
                        'clicks' => $kwC + $ptC + $hlC, 'ad_sales' => round($kwS + $ptS + $hlS, 2), 'ad_sold' => $kwU + $ptU + $hlU,
                        'KW Clicks' => $kwC, 'PT Clicks' => $ptC, 'HL Clicks' => $hlC, 'PMT Clicks' => 0, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
                        'KW Sales' => round($kwS, 2), 'PT Sales' => round($ptS, 2), 'HL Sales' => round($hlS, 2), 'PMT Sales' => 0, 'Shopping Sales' => 0, 'SERP Sales' => 0,
                        'KW Sold' => $kwU, 'PT Sold' => $ptU, 'HL Sold' => $hlU, 'PMT Sold' => 0, 'Shopping Sold' => 0, 'SERP Sold' => 0,
                        'KW Spent' => round($kwSp, 2), 'PT Spent' => round($ptSp, 2), 'HL Spent' => round($hlSp, 2),
                        'PMT Spent' => 0, 'Shopping Spent' => 0, 'SERP Spent' => 0,
                        'Total Ad Spend' => $totalSpend,
                        'KW ACOS' => $kwS > 0 ? round(($kwSp / $kwS) * 100, 1) : 0,
                        'PT ACOS' => $ptS > 0 ? round(($ptSp / $ptS) * 100, 1) : 0,
                        'HL ACOS' => $hlS > 0 ? round(($hlSp / $hlS) * 100, 1) : 0,
                        'PMT ACOS' => 0, 'Shopping ACOS' => 0, 'SERP ACOS' => 0,
                        'KW CVR' => $kwC > 0 ? round(($kwU / $kwC) * 100, 1) : 0,
                        'PT CVR' => $ptC > 0 ? round(($ptU / $ptC) * 100, 1) : 0,
                        'HL CVR' => $hlC > 0 ? round(($hlU / $hlC) * 100, 1) : 0,
                        'PMT CVR' => 0, 'Shopping CVR' => 0, 'SERP CVR' => 0,
                    ];
                }

                case 'amazonfba': {
                    // Use LATEST L30 row per campaign (by MAX(id)) to get current values
                    // KW FBA: SP campaigns ending with FBA (not FBA PT)
                    $kwLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->whereRaw("campaignName LIKE '%FBA'")
                        ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
                        ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $kwData = $kwLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $kwLatestIds)->get()
                        : collect();

                    // PT FBA: SP campaigns ending with FBA PT
                    $ptLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->where(function ($q) {
                            $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'");
                        })
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $ptData = $ptLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $ptLatestIds)->get()
                        : collect();

                    // Use 7-day attribution (sales7d, purchases7d) to match Amazon Advertising dashboard default
                    $kwC = (int) $kwData->sum('clicks'); $kwS = (float) $kwData->sum('sales7d'); $kwU = (int) $kwData->sum('purchases7d'); $kwSp = (float) $kwData->sum('spend');
                    $ptC = (int) $ptData->sum('clicks'); $ptS = (float) $ptData->sum('sales7d'); $ptU = (int) $ptData->sum('purchases7d'); $ptSp = (float) $ptData->sum('spend');

                    $totalSpend = round($kwSp + $ptSp, 2);

                    return [
                        'clicks' => $kwC + $ptC, 'ad_sales' => round($kwS + $ptS, 2), 'ad_sold' => $kwU + $ptU,
                        'KW Clicks' => $kwC, 'PT Clicks' => $ptC, 'HL Clicks' => 0, 'PMT Clicks' => 0, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
                        'KW Sales' => round($kwS, 2), 'PT Sales' => round($ptS, 2), 'HL Sales' => 0, 'PMT Sales' => 0, 'Shopping Sales' => 0, 'SERP Sales' => 0,
                        'KW Sold' => $kwU, 'PT Sold' => $ptU, 'HL Sold' => 0, 'PMT Sold' => 0, 'Shopping Sold' => 0, 'SERP Sold' => 0,
                        'KW Spent' => round($kwSp, 2), 'PT Spent' => round($ptSp, 2), 'HL Spent' => 0,
                        'PMT Spent' => 0, 'Shopping Spent' => 0, 'SERP Spent' => 0,
                        'Total Ad Spend' => $totalSpend,
                        'KW ACOS' => $kwS > 0 ? round(($kwSp / $kwS) * 100, 1) : 0,
                        'PT ACOS' => $ptS > 0 ? round(($ptSp / $ptS) * 100, 1) : 0,
                        'HL ACOS' => 0, 'PMT ACOS' => 0, 'Shopping ACOS' => 0, 'SERP ACOS' => 0,
                        'KW CVR' => $kwC > 0 ? round(($kwU / $kwC) * 100, 1) : 0,
                        'PT CVR' => $ptC > 0 ? round(($ptU / $ptC) * 100, 1) : 0,
                        'HL CVR' => 0, 'PMT CVR' => 0, 'Shopping CVR' => 0, 'SERP CVR' => 0,
                    ];
                }

                case 'ebay':
                case 'ebaytwo':
                case 'ebaythree': {
                    // Use daily data sum (individual date report_ranges) instead of L30 aggregate
                    // Daily data is closer to eBay Seller Hub dashboard values
                    $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
                    $endDate = Carbon::now()->format('Y-m-d');
                    $kwTable = match ($channel) {
                        'ebay' => 'ebay_priority_reports',
                        'ebaytwo' => 'ebay_2_priority_reports',
                        'ebaythree' => 'ebay_3_priority_reports',
                        default => null,
                    };
                    $pmtTable = match ($channel) {
                        'ebay' => 'ebay_general_reports',
                        'ebaytwo' => 'ebay_2_general_reports',
                        'ebaythree' => 'ebay_3_general_reports',
                        default => null,
                    };
                    if (!$kwTable || !$pmtTable) return $defaults;

                    $kw = DB::table($kwTable)
                        ->where('report_range', '>=', $startDate)
                        ->where('report_range', '<=', $endDate)
                        ->where('report_range', 'NOT LIKE', 'L%')
                        ->selectRaw('COALESCE(SUM(cpc_clicks), 0) as c, COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as s, COALESCE(SUM(cpc_attributed_sales), 0) as u, COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as sp')
                        ->first();
                    $pmt = DB::table($pmtTable)
                        ->where('report_range', '>=', $startDate)
                        ->where('report_range', '<=', $endDate)
                        ->where('report_range', 'NOT LIKE', 'L%')
                        ->selectRaw('COALESCE(SUM(clicks), 0) as c, COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as s, COALESCE(SUM(sales), 0) as u, COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as sp')
                        ->first();

                    $kwC = (int) ($kw->c ?? 0); $kwS = (float) ($kw->s ?? 0); $kwU = (int) ($kw->u ?? 0); $kwSp = (float) ($kw->sp ?? 0);
                    $pmtC = (int) ($pmt->c ?? 0); $pmtS = (float) ($pmt->s ?? 0); $pmtU = (int) ($pmt->u ?? 0); $pmtSp = (float) ($pmt->sp ?? 0);

                    return [
                        'clicks' => $kwC + $pmtC, 'ad_sales' => round($kwS + $pmtS, 2), 'ad_sold' => $kwU + $pmtU,
                        'KW Clicks' => $kwC, 'PT Clicks' => 0, 'HL Clicks' => 0, 'PMT Clicks' => $pmtC, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
                        'KW Sales' => round($kwS, 2), 'PT Sales' => 0, 'HL Sales' => 0, 'PMT Sales' => round($pmtS, 2), 'Shopping Sales' => 0, 'SERP Sales' => 0,
                        'KW Sold' => $kwU, 'PT Sold' => 0, 'HL Sold' => 0, 'PMT Sold' => $pmtU, 'Shopping Sold' => 0, 'SERP Sold' => 0,
                        'KW Spent' => round($kwSp, 2), 'PT Spent' => 0, 'HL Spent' => 0,
                        'PMT Spent' => round($pmtSp, 2), 'Shopping Spent' => 0, 'SERP Spent' => 0,
                        'Total Ad Spend' => round($kwSp + $pmtSp, 2),
                        'KW ACOS' => $kwS > 0 ? round(($kwSp / $kwS) * 100, 1) : 0,
                        'PT ACOS' => 0, 'HL ACOS' => 0,
                        'PMT ACOS' => $pmtS > 0 ? round(($pmtSp / $pmtS) * 100, 1) : 0,
                        'Shopping ACOS' => 0, 'SERP ACOS' => 0,
                        'KW CVR' => $kwC > 0 ? round(($kwU / $kwC) * 100, 1) : 0,
                        'PT CVR' => 0, 'HL CVR' => 0,
                        'PMT CVR' => $pmtC > 0 ? round(($pmtU / $pmtC) * 100, 1) : 0,
                        'Shopping CVR' => 0, 'SERP CVR' => 0,
                    ];
                }

                case 'temu': {
                    $row = DB::table('temu_campaign_reports')
                        ->where('report_range', 'L30')
                        ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
                        ->selectRaw('COALESCE(SUM(clicks), 0) as c, COALESCE(SUM(base_price_sales), 0) as s, COALESCE(SUM(sub_orders), 0) as u, COALESCE(SUM(spend), 0) as sp')
                        ->first();
                    $c = (int) ($row->c ?? 0); $s = (float) ($row->s ?? 0); $u = (int) ($row->u ?? 0); $sp = (float) ($row->sp ?? 0);
                    return array_merge($defaults, [
                        'clicks' => $c, 'ad_sales' => round($s, 2), 'ad_sold' => $u,
                        'KW Clicks' => $c, 'KW Sales' => round($s, 2), 'KW Sold' => $u,
                        'KW Spent' => round($sp, 2), 'Total Ad Spend' => round($sp, 2),
                        'KW ACOS' => $s > 0 ? round(($sp / $s) * 100, 1) : 0,
                        'KW CVR' => $c > 0 ? round(($u / $c) * 100, 1) : 0,
                    ]);
                }

                case 'walmart': {
                    $row = DB::table('walmart_campaign_reports')
                        ->where('report_range', 'L30')
                        ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
                        ->whereNotNull('campaignName')->where('campaignName', '!=', '')
                        ->selectRaw('COALESCE(SUM(clicks), 0) as c, COALESCE(SUM(sales), 0) as s, COALESCE(SUM(spend), 0) as sp')
                        ->first();
                    $c = (int) ($row->c ?? 0); $s = (float) ($row->s ?? 0); $sp = (float) ($row->sp ?? 0);
                    return array_merge($defaults, [
                        'clicks' => $c, 'ad_sales' => round($s, 2), 'ad_sold' => 0,
                        'KW Clicks' => $c, 'KW Sales' => round($s, 2),
                        'KW Spent' => round($sp, 2), 'Total Ad Spend' => round($sp, 2),
                        'KW ACOS' => $s > 0 ? round(($sp / $s) * 100, 1) : 0,
                    ]);
                }

                case 'shopifyb2c': {
                    $endDate = Carbon::now()->subDays(2)->format('Y-m-d');
                    $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
                    // Shopping ads
                    $shopping = DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $startDate)
                        ->whereDate('date', '<=', $endDate)
                        ->where('advertising_channel_type', 'SHOPPING')
                        ->where('campaign_status', 'ENABLED')
                        ->selectRaw('COALESCE(SUM(metrics_clicks), 0) as c, COALESCE(SUM(ga4_ad_sales), 0) as s, COALESCE(SUM(ga4_sold_units), 0) as u, COALESCE(SUM(metrics_cost_micros), 0) as sp')
                        ->first();
                    // SERP (Search) ads
                    $serp = DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $startDate)
                        ->whereDate('date', '<=', $endDate)
                        ->where('advertising_channel_type', 'SEARCH')
                        ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
                        ->selectRaw('COALESCE(SUM(metrics_clicks), 0) as c, COALESCE(SUM(ga4_ad_sales), 0) as s, COALESCE(SUM(ga4_sold_units), 0) as u, COALESCE(SUM(metrics_cost_micros), 0) as sp')
                        ->first();
                    $shpC = (int) ($shopping->c ?? 0); $shpS = (float) ($shopping->s ?? 0); $shpU = (int) ($shopping->u ?? 0); $shpSp = (float) (($shopping->sp ?? 0) / 1000000);
                    $serpC = (int) ($serp->c ?? 0); $serpS = (float) ($serp->s ?? 0); $serpU = (int) ($serp->u ?? 0); $serpSp = (float) (($serp->sp ?? 0) / 1000000);
                    return array_merge($defaults, [
                        'clicks' => $shpC + $serpC, 'ad_sales' => round($shpS + $serpS, 2), 'ad_sold' => $shpU + $serpU,
                        'Shopping Clicks' => $shpC, 'SERP Clicks' => $serpC,
                        'Shopping Sales' => round($shpS, 2), 'SERP Sales' => round($serpS, 2),
                        'Shopping Sold' => $shpU, 'SERP Sold' => $serpU,
                        'Shopping Spent' => round($shpSp, 2), 'SERP Spent' => round($serpSp, 2),
                        'Total Ad Spend' => round($shpSp + $serpSp, 2),
                        'Shopping ACOS' => $shpS > 0 ? round(($shpSp / $shpS) * 100, 1) : 0,
                        'SERP ACOS' => $serpS > 0 ? round(($serpSp / $serpS) * 100, 1) : 0,
                        'Shopping CVR' => $shpC > 0 ? round(($shpU / $shpC) * 100, 1) : 0,
                        'SERP CVR' => $serpC > 0 ? round(($serpU / $serpC) * 100, 1) : 0,
                    ]);
                }

                case 'tiktokshop': {
                    $productSkusUpper = ProductMaster::whereRaw("LOWER(sku) NOT LIKE '%parent%'")
                        ->pluck('sku')->map(fn ($s) => strtoupper(trim($s)))->unique()->values()->toArray();
                    if (empty($productSkusUpper)) return $defaults;
                    $placeholders = implode(',', array_fill(0, count($productSkusUpper), '?'));
                    $row = DB::table('tiktok_campaign_reports')
                        ->whereIn('report_range', ['L30', 'L7'])
                        ->where('creative_type', 'Product card')
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhere('status', '!=', 'ARCHIVED');
                        })
                        ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                        ->whereNotNull('product_id')->where('product_id', '!=', '')
                        ->whereRaw('UPPER(TRIM(campaign_name)) IN (' . $placeholders . ')', $productSkusUpper)
                        ->selectRaw('COALESCE(SUM(product_ad_clicks), 0) as c, COALESCE(SUM(gross_revenue), 0) as s, COALESCE(SUM(sku_orders), 0) as u, COALESCE(SUM(spend), 0) as sp')
                        ->first();
                    $c = (int) ($row->c ?? 0); $s = (float) ($row->s ?? 0); $u = (int) ($row->u ?? 0); $sp = (float) ($row->sp ?? 0);
                    return array_merge($defaults, [
                        'clicks' => $c, 'ad_sales' => round($s, 2), 'ad_sold' => $u,
                        'KW Clicks' => $c, 'KW Sales' => round($s, 2), 'KW Sold' => $u,
                        'KW Spent' => round($sp, 2), 'Total Ad Spend' => round($sp, 2),
                        'KW ACOS' => $s > 0 ? round(($sp / $s) * 100, 1) : 0,
                        'KW CVR' => $c > 0 ? round(($u / $c) * 100, 1) : 0,
                    ]);
                }

                default:
                    return $defaults;
            }
        } catch (\Throwable $e) {
            \Log::error('fetchAdMetricsFromTables error: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Fetch Total Ad Spend directly from campaign/report tables (L30).
     * Amazon KW+PT: amazon_sp_campaign_reports, HL: amazon_sb_campaign_reports
     * eBay 1/2/3: ebay_*_priority_reports (KW) + ebay_*_general_reports (PMT)
     * Temu: temu_campaign_reports, Walmart: walmart_campaign_reports
     * Google/Shopify B2C: google_ads_campaigns, TikTok: tiktok_campaign_reports
     *
     * @param string $channel Normalized channel key (amazon, ebay, ebaytwo, ebaythree, temu, walmart, shopifyb2c, tiktokshop)
     * @return float Total Ad Spend for the channel
     */
    private function fetchTotalAdSpendFromTables(string $channel): float
    {
        $channel = strtolower(trim($channel));

        switch ($channel) {
            case 'amazon':
                $breakdown = $this->fetchAmazonAdSpendBreakdownFromTables();
                return round($breakdown['kw'] + $breakdown['pt'] + $breakdown['hl'], 2);

            case 'ebay':
            case 'ebaytwo':
            case 'ebaythree':
                $breakdown = $this->fetchEbayAdSpendBreakdownFromTables($channel);
                return round($breakdown['kw'] + $breakdown['pmt'], 2);

            case 'temu':
                return round(
                    (float) DB::table('temu_campaign_reports')
                        ->where('report_range', 'L30')
                        ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
                        ->sum('spend'),
                    2
                );

            case 'walmart':
                $walmartSpentData = DB::table('walmart_campaign_reports')
                    ->selectRaw('campaignName, MAX(spend) as max_spend')
                    ->where('report_range', 'L30')
                    ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
                    ->whereNotNull('campaignName')
                    ->where('campaignName', '!=', '')
                    ->groupBy('campaignName')
                    ->get();
                return round($walmartSpentData->sum('max_spend') ?? 0, 2);

            case 'shopifyb2c':
                // Same logic as G-Shopping Utilized (filterGoogleShoppingChart): date range 31 days ago to 2 days ago, ENABLED SHOPPING only
                $endDate = Carbon::now()->subDays(2)->format('Y-m-d');
                $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
                return round(
                    (float) DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $startDate)
                        ->whereDate('date', '<=', $endDate)
                        ->where('advertising_channel_type', 'SHOPPING')
                        ->where('campaign_status', 'ENABLED')
                        ->sum('metrics_cost_micros') / 1000000,
                    2
                );

            case 'tiktokshop':
                $productSkusUpper = ProductMaster::whereRaw("LOWER(sku) NOT LIKE '%parent%'")
                    ->pluck('sku')
                    ->map(fn ($s) => strtoupper(trim($s)))
                    ->unique()
                    ->values()
                    ->toArray();
                if (empty($productSkusUpper)) {
                    return 0.0;
                }
                $placeholders = implode(',', array_fill(0, count($productSkusUpper), '?'));
                return round(
                    (float) TiktokCampaignReport::whereIn('report_range', ['L30', 'L7'])
                        ->where('creative_type', 'Product card')
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhere('status', '!=', 'ARCHIVED');
                        })
                        ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                        ->whereNotNull('product_id')->where('product_id', '!=', '')
                        ->whereRaw('UPPER(TRIM(campaign_name)) IN (' . $placeholders . ')', $productSkusUpper)
                        ->sum('cost'),
                    2
                );

            default:
                return 0.0;
        }
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

    /**
     * Display All Marketplace Master view (Tabulator-based)
     */
    public function allMarketplaceMaster(Request $request)
    {
        return view('channels.all-marketplace-master');
    }


    public function getViewChannelData(Request $request)
    {
        // Fetch both channel and sheet_link from ChannelMaster
        $columns = ['channel', 'sheet_link', 'channel_percentage', 'type'];
        
        // Check if 'base', 'target', and 'missing_link' columns exist before adding them
        if (Schema::hasColumn('channel_master', 'base')) {
            $columns[] = 'base';
        }
        if (Schema::hasColumn('channel_master', 'target')) {
            $columns[] = 'target';
        }
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $columns[] = 'missing_link';
        }
        
        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('type', 'asc')
            ->orderBy('id', 'asc')
            ->get($columns);

        if ($channels->isEmpty()) {
            return response()->json(['status' => 404, 'message' => 'No active channel found']);
        }

        // Get clicks data from adv_masters_data table
        $advMastersData = \App\Models\ADVMastersData::all()->keyBy('channel');

        $finalData = [];

        // Map lowercase channel key => controller method
        $controllerMap = [
            'amazon'    => 'getAmazonChannelData',
            'amazonfba' => 'getAmazonFbaChannelData',
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

            // Base row - normalize type to only B2C, B2B, Dropship
            $rawType = $channelRow->type ?? '';
            $normalizedType = 'B2C'; // default
            if (strtolower(trim($rawType)) === 'b2b') {
                $normalizedType = 'B2B';
            } elseif (strtolower(trim($rawType)) === 'dropship') {
                $normalizedType = 'Dropship';
            }
            
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
                'type'           => $normalizedType,
                'listed_count'   => 0,
                'W/Ads'          => 0,
                'channel_percentage' => $channelRow->channel_percentage ?? '',
                'base' => $channelRow->base ?? 0,
                'target' => $channelRow->target ?? 0,
                'missing_link' => $channelRow->missing_link ?? '',
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

            // AD CLICKS, AD SALES, AD SOLD + breakdown: fetch directly from tables for ad-enabled channels
            $adMetricsChannels = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'walmart', 'shopifyb2c', 'tiktokshop'];
            if (in_array($key, $adMetricsChannels)) {
                $metrics = $this->fetchAdMetricsFromTables($key);
                $clicks = $metrics['clicks'];
                $adSales = $metrics['ad_sales'];
                $adSold = $metrics['ad_sold'];
                // Merge all breakdown fields into row
                $row = array_merge($row, $metrics);
            } else {
                // Fallback: adv_masters_data for channels without direct table support
                $channelKey = strtoupper($channel);
                $channelMapping = [
                    'TIKTOKSHOP' => 'TIKTOK',
                    'SHOPIFYB2C' => 'SHOPIFY',
                    'SHOPIFYB2B' => 'SHOPIFY',
                    'EBAYTWO' => 'EBAY 2',
                    'EBAYTHREE' => 'EBAY 3',
                    'FBMARKETPLACE' => 'FB CARAOUSEL',
                    'FBSHOP' => 'FB VIDEO',
                    'INSTAGRAMSHOP' => 'INSTA CARAOUSEL',
                ];
                $advKey = $channelMapping[$channelKey] ?? $channelKey;
                if (isset($advMastersData[$advKey])) {
                    $advData = $advMastersData[$advKey];
                    $clicks = $advData->clicks ?? 0;
                    $adSold = $advData->ad_sold ?? 0;
                    $adSales = $advData->ad_sales ?? 0;
                } else {
                    $clicks = 0;
                    $adSold = 0;
                    $adSales = 0;
                }
                // Set default breakdown values for non-ad-enabled channels
                $row['KW Clicks'] = 0; $row['PT Clicks'] = 0; $row['HL Clicks'] = 0; $row['PMT Clicks'] = 0; $row['Shopping Clicks'] = 0; $row['SERP Clicks'] = 0;
                $row['KW Sales'] = 0; $row['PT Sales'] = 0; $row['HL Sales'] = 0; $row['PMT Sales'] = 0; $row['Shopping Sales'] = 0; $row['SERP Sales'] = 0;
                $row['KW Sold'] = 0; $row['PT Sold'] = 0; $row['HL Sold'] = 0; $row['PMT Sold'] = 0; $row['Shopping Sold'] = 0; $row['SERP Sold'] = 0;
                $row['KW ACOS'] = 0; $row['PT ACOS'] = 0; $row['HL ACOS'] = 0; $row['PMT ACOS'] = 0; $row['Shopping ACOS'] = 0; $row['SERP ACOS'] = 0;
                $row['KW CVR'] = 0; $row['PT CVR'] = 0; $row['HL CVR'] = 0; $row['PMT CVR'] = 0; $row['Shopping CVR'] = 0; $row['SERP CVR'] = 0;
            }

            // Missing Ads: still from adv_masters_data (not in campaign tables)
            $channelKey = strtoupper($channel);
            $channelMapping = [
                'TIKTOKSHOP' => 'TIKTOK',
                'SHOPIFYB2C' => 'SHOPIFY',
                'SHOPIFYB2B' => 'SHOPIFY',
                'EBAYTWO' => 'EBAY 2',
                'EBAYTHREE' => 'EBAY 3',
                'FBMARKETPLACE' => 'FB CARAOUSEL',
                'FBSHOP' => 'FB VIDEO',
                'INSTAGRAMSHOP' => 'INSTA CARAOUSEL',
            ];
            $advKey = $channelMapping[$channelKey] ?? $channelKey;
            $missingAds = isset($advMastersData[$advKey]) ? ($advMastersData[$advKey]->missing_ads ?? 0) : 0;

            // Calculate Ads CVR and ACOS
            $cvr = $clicks > 0 ? round(($adSold / $clicks) * 100, 2) : 0;
            $totalAdSpend = (float) ($row['Total Ad Spend'] ?? 0);
            $acos = $adSales > 0 ? round(($totalAdSpend / $adSales) * 100, 2) : 0;

            // Recalculate Ads% with fresh Total Ad Spend (may have been overridden by fetchAdMetricsFromTables)
            $l30SalesVal = (float) str_replace(['$', ',', '%'], '', $row['L30 Sales'] ?? 0);
            $adsPercentage = $l30SalesVal > 0 ? ($totalAdSpend / $l30SalesVal) * 100 : 0;
            $row['Ads%'] = round($adsPercentage, 2) . '%';

            $row['clicks'] = $clicks;
            $row['ad_sold'] = $adSold;
            $row['Ad Sales'] = $adSales;
            $row['Ads CVR'] = $cvr;
            $row['ACOS'] = $acos;
            $row['Missing Ads'] = $missingAds;

            $finalData[] = $row;
        }

        // Auto-save channel-wise daily summaries
        $this->saveChannelDailySummaries($finalData);

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

        // Get metrics from marketplace_daily_metrics table (pre-calculated, 31 days of L30 data to match Amazon Seller Central)
        $metrics = MarketplaceDailyMetric::where('channel', 'Amazon')->latest('date')->first();
        
        // Amazon shows 31 days of L30 data - L60 data disabled
        // Set L60 values to 0 (no historical comparison beyond 31 days)
        $l60Orders = 0;
        $l60Sales = 0;
        
        // DISABLED: L60 data from ShipHub - Amazon limited to 30 days only
        /*
        $latestDate = null;
        try {
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'amazon')
                ->max('order_date');
        } catch (\Throwable $e) {
            Log::warning('ShipHub connection failed in getAmazonChannelData: ' . $e->getMessage());
        }

        if ($latestDate) {
            try {
                $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
                $l60StartDate = $latestDateCarbon->copy()->subDays(59)->startOfDay();
                $l60EndDate = $latestDateCarbon->copy()->subDays(30)->endOfDay();

                $l60OrderItems = DB::connection('shiphub')
                    ->table('orders as o')
                    ->join('order_items as i', 'o.id', '=', 'i.order_id')
                    ->whereBetween('o.order_date', [$l60StartDate, $l60EndDate])
                    ->where('o.marketplace', '=', 'amazon')
                    ->where(function($query) {
                        $query->where('o.order_status', '!=', 'Canceled')
                              ->where('o.order_status', '!=', 'Cancelled')
                              ->orWhereNull('o.order_status');
                    })
                    ->select(
                        DB::raw('COUNT(i.id) as order_count'),
                        DB::raw('SUM(i.unit_price) as total_sales')
                    )
                    ->first();

                $l60Orders = $l60OrderItems->order_count ?? 0;
                $l60Sales = $l60OrderItems->total_sales ?? 0;
            } catch (\Throwable $e) {
                Log::warning('ShipHub L60 query failed: ' . $e->getMessage());
                $l60Orders = 0;
                $l60Sales = 0;
            }
        } else {
            $l60Orders = 0;
            $l60Sales = 0;
        }
        */

        $l30Sales = $metrics?->total_sales ?? 0;
        $l30Orders = $metrics?->total_orders ?? 0;
        $totalQuantity = $metrics?->total_quantity ?? 0;
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;
        
        // Get ad spend from marketplace_daily_metrics (pre-calculated from UpdateMarketplaceDailyMetrics)
        $kwSpent = $metrics?->kw_spent ?? 0;
        $ptSpent = $metrics?->pmt_spent ?? 0; // Note: stored as pmt_spent in metrics table
        $hlSpent = $metrics?->hl_spent ?? 0;
        $totalAdSpend = round($kwSpent + $ptSpent + $hlSpent, 2);
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage (still needs calculation if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Amazon')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('amazon');

        $result[] = [
            'Channel '   => 'Amazon',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 0),
            'PT Spent'   => round($ptSpent, 0),
            'HL Spent'   => round($hlSpent, 0),
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData?->type ?? '',
            'W/Ads'      => $channelData?->w_ads ?? 0,
            'NR'         => $channelData?->nr ?? 0,
            'Update'     => $channelData?->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Amazon channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Fetch Amazon FBA channel data.
     * FBA campaigns end with 'FBA' or 'FBA PT', separate from regular Amazon KW/PT campaigns.
     */
    
    public function getAmazonFbaChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table for Amazon FBA
        $metrics = MarketplaceDailyMetric::where('channel', 'Amazon FBA')->latest('date')->first();

        // Get L60 data from ShipHub for Amazon FBA (30-59 days ago range)
        $latestDate = null;
        try {
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'amazon-fba')
                ->max('order_date');
        } catch (\Throwable $e) {
            Log::warning('ShipHub connection failed in getAmazonFbaChannelData: ' . $e->getMessage());
        }

        if ($latestDate) {
            try {
                $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
                $l60StartDate = $latestDateCarbon->copy()->subDays(59)->startOfDay();
                $l60EndDate = $latestDateCarbon->copy()->subDays(30)->endOfDay();

                $l60OrderItems = DB::connection('shiphub')
                    ->table('orders as o')
                    ->join('order_items as i', 'o.id', '=', 'i.order_id')
                    ->whereBetween('o.order_date', [$l60StartDate, $l60EndDate])
                    ->where('o.marketplace', '=', 'amazon-fba')
                    ->where(function($query) {
                        $query->where('o.order_status', '!=', 'Canceled')
                              ->where('o.order_status', '!=', 'Cancelled')
                              ->orWhereNull('o.order_status');
                    })
                    ->select(
                        DB::raw('COUNT(i.id) as order_count'),
                        DB::raw('SUM(i.unit_price) as total_sales')
                    )
                    ->first();

                $l60Orders = $l60OrderItems->order_count ?? 0;
                $l60Sales = $l60OrderItems->total_sales ?? 0;
            } catch (\Throwable $e) {
                Log::warning('ShipHub L60 query failed for Amazon FBA: ' . $e->getMessage());
                $l60Orders = 0;
                $l60Sales = 0;
            }
        } else {
            $l60Orders = 0;
            $l60Sales = 0;
        }

        $l30Sales = $metrics?->total_sales ?? 0;
        $l30Orders = $metrics?->total_orders ?? 0;
        $totalQuantity = $metrics?->total_quantity ?? 0;
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;

        // FBA KW + PT spend: fetch from tables using FBA-specific filters
        try {
            $adSpendBreakdown = $this->fetchAmazonFbaAdSpendBreakdownFromTables();
            $kwSpent = $adSpendBreakdown['kw'];
            $ptSpent = $adSpendBreakdown['pt'];
        } catch (\Throwable $e) {
            Log::error('fetchAmazonFbaAdSpendBreakdownFromTables failed: ' . $e->getMessage());
            $kwSpent = $ptSpent = 0;
        }
        $totalAdSpend = round($kwSpent + $ptSpent, 2);

        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::whereRaw("LOWER(REPLACE(channel, ' ', '')) = 'amazonfba'")->first();

        // Get Map and Miss counts
        $mapMissCounts = $this->getMapAndMissCounts('amazonfba');

        $result[] = [
            'Channel '   => 'Amazon FBA',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => round($ptSpent, 2),
            'HL Spent'   => 0, // FBA doesn't have HL (Headline) ads separate
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2),
            'TACOS'      => round($tacosPercentage, 2),
            'AD CLICKS'  => 0,
            'AD SALES'   => 0,
            'AD SOLD'    => 0,
            'ACOS'       => 0,
            'AD CVR'     => 0,
            'Link'       => null,
            'sheet_link' => $channelData->sheet_link ?? null,
            'missing_link' => $channelData->missing_link ?? null,
            'type'       => $channelData->type ?? 'B2C',
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Amazon FBA channel data fetched successfully',
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? 0;
        $nRoi = $metrics->n_roi ?? 0;

        // KW/PMT Spend: fetch directly from tables (same logic as Ebay KW Ads & PMT Ads pages)
        $ebayBreakdown = $this->fetchEbayAdSpendBreakdownFromTables('ebay');
        $kwSpent = $ebayBreakdown['kw'];
        $pmtSpent = $ebayBreakdown['pmt'];
        $totalAdSpend = $kwSpent + $pmtSpent;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage (still needs calculation if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'eBay')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('ebay');

        $result[] = [
            'Channel '   => 'eBay',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => round($pmtSpent, 2),
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
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

        // Get metrics from marketplace_daily_metrics table (pre-calculated, same as Amazon)
        $metrics = MarketplaceDailyMetric::where('channel', 'eBay 2')->latest('date')->first();
        
        // Get L60 data from orders for comparison
        $ordersL60 = Ebay2Order::with('items')
            ->where('period', 'l60')
            ->get();

        // Calculate L60 Sales and metrics
        $l60Orders = 0;
        $l60Sales = 0;
        $totalProfitL60 = 0;
        $totalCogsL60 = 0;

        // Get eBay 2 marketing percentage
        $percentage = ChannelMaster::where('channel', 'EbayTwo')->value('channel_percentage') ?? 85;
        $percentageDecimal = $percentage / 100;

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        foreach ($ordersL60 as $order) {
            foreach ($order->items as $item) {
                if (!$item->sku || $item->sku === '') continue;

                $l60Orders++;
                $quantity = (int) ($item->quantity ?? 1);
                $price = (float) ($item->price ?? 0);
                $l60Sales += $price;

                $unitPrice = $quantity > 0 ? $price / $quantity : 0;

                $sku = strtoupper($item->sku);
                $lp = 0;
                $ship = 0;

                if (isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }

                    $ship = isset($values["ebay2_ship"]) && $values["ebay2_ship"] !== null 
                        ? floatval($values["ebay2_ship"]) 
                        : (isset($values["ship"]) ? floatval($values["ship"]) : 0);
                }

                $totalCogsL60 += ($lp * $quantity);
                $pftEach = ($unitPrice * $percentageDecimal) - $lp - $ship;
                $totalProfitL60 += ($pftEach * $quantity);
            }
        }

        // Use pre-calculated metrics from MarketplaceDailyMetric (same as Amazon)
        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? 0;
        $nRoi = $metrics->n_roi ?? 0;

        // KW/PMT Spend: fetch directly from tables (same logic as Ebay 2 KW Ads & PMT Ads pages)
        $ebay2Breakdown = $this->fetchEbayAdSpendBreakdownFromTables('ebaytwo');
        $kwSpent = $ebay2Breakdown['kw'];
        $pmtSpent = $ebay2Breakdown['pmt'];
        $totalAdSpend = $kwSpent + $pmtSpent;

        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'EbayTwo')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('ebay2');

        $result[] = [
            'Channel '   => 'EbayTwo',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => round($pmtSpent, 2),
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
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

        // Get metrics from marketplace_daily_metrics table (pre-calculated, same as Amazon/eBay 2)
        $metrics = MarketplaceDailyMetric::where('channel', 'eBay 3')->latest('date')->first();
        
        // Get L60 data from ebay3_daily_data for comparison
        $ordersL60 = DB::table('ebay3_daily_data')->where('period', 'l60')->get();

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Get eBay marketing percentage from marketplace_percentages (85% for eBay 3)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayThree')->first();
        $percentageDecimal = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.85;

        // Calculate L60 metrics
        $l60Orders = $ordersL60->count();
        $l60Sales = 0;
        $totalProfitL60 = 0;
        $totalCogsL60 = 0;

        foreach ($ordersL60 as $order) {
            $sku = strtoupper($order->sku ?? '');
            if (empty($sku)) continue;

            $qty = floatval($order->quantity ?? 1);
            $price = floatval($order->unit_price ?? 0);
            
            $l60Sales += $price * $qty;

            $lp = 0;
            $ship = 0;
            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            $pftPerUnit = ($price * $percentageDecimal) - $lp - $ship;
            $totalProfitL60 += $pftPerUnit * $qty;
            $totalCogsL60 += $lp * $qty;
        }

        // Use pre-calculated metrics from MarketplaceDailyMetric (same as Amazon/eBay 2)
        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? 0;
        $nRoi = $metrics->n_roi ?? 0;

        // KW/PMT Spend: fetch directly from tables (same logic as Ebay 3 KW Ads & PMT Ads pages)
        $ebay3Breakdown = $this->fetchEbayAdSpendBreakdownFromTables('ebaythree');
        $kwSpent = $ebay3Breakdown['kw'];
        $pmtSpent = $ebay3Breakdown['pmt'];
        $totalAdSpend = $kwSpent + $pmtSpent;

        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'EbayThree')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('ebay3');

        $result[] = [
            'Channel '   => 'EbayThree',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => round($pmtSpent, 2),
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0, 2),
            'TACOS %'    => round($tacosPercentage, 2),
            'N PFT'      => round($nPft, 2),
            'N ROI'      => round($nRoi, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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

        // Get metrics from marketplace_daily_metrics table (pre-calculated from MiraklDailyData)
        $metrics = MarketplaceDailyMetric::where('channel', 'Macys')->latest('date')->first();

        // Get L60 data from MacyProduct for comparison
        $query = MacyProduct::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('m_l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(m_l60 * price) as total')->value('total') ?? 0;

        // Use MarketplaceDailyMetric data
        $l30Sales = $metrics->total_sales ?? $metrics->l30_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;

        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // L60 profit percentage (calculate from L60 data if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = same as Gprofit% for Macys (no ads)
        $nPft = $gProfitPct;

        // N ROI = same as G ROI for Macys (no ads)
        $nRoi = $metrics->n_roi ?? $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Macys')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('macys');

        $result[] = [
            'Channel '   => 'Macys',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // r_l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('reverb');

        $result[] = [
            'Channel '   => 'Reverb',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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

        // Use pre-calculated metrics from MarketplaceDailyMetric (like Amazon, eBay 2, Temu)
        $latestMetric = MarketplaceDailyMetric::where('channel', 'Doba')
            ->orderBy('date', 'desc')
            ->first();

        // Get L60 data for comparison (60 days ago)
        $l60Date = Carbon::today()->subDays(60)->format('Y-m-d');
        $l60Metric = MarketplaceDailyMetric::where('channel', 'Doba')
            ->where('date', $l60Date)
            ->first();

        // Current metrics
        $l30Sales = $latestMetric ? $latestMetric->l30_sales : 0;
        $l30Orders = $latestMetric ? $latestMetric->total_orders : 0;
        $totalQuantity = $latestMetric ? $latestMetric->total_quantity : 0;
        $totalCogs = $latestMetric ? $latestMetric->total_cogs : 0;
        $totalPft = $latestMetric ? $latestMetric->total_pft : 0;
        $gProfitPct = $latestMetric ? $latestMetric->pft_percentage : 0;
        $gRoi = $latestMetric ? $latestMetric->roi_percentage : 0;
        $nPft = $latestMetric ? ($latestMetric->n_pft ?? $totalPft) : 0;
        $nRoi = $latestMetric ? ($latestMetric->n_roi ?? $gRoi) : 0;

        // L60 metrics
        $l60Sales = $l60Metric ? $l60Metric->l30_sales : 0;
        $l60Orders = $l60Metric ? $l60Metric->total_orders : 0;
        $gprofitL60 = $l60Metric ? $l60Metric->pft_percentage : 0;
        $gRoiL60 = $l60Metric ? $l60Metric->roi_percentage : 0;

        // Growth calculation
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // N PFT percentage
        $nPftPct = $l30Sales > 0 ? ($nPft / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Doba')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('doba');

        $result[] = [
            'Channel '   => 'Doba',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60'   => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'      => round($gRoiL60, 1),
            'N PFT'      => round($nPftPct, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? $gProfitPct;
        $nRoi = $metrics->n_roi ?? $gRoi;
        $temuSpent = $metrics->kw_spent ?? 0; // Temu ad spend is stored in kw_spent field

        // Total Ad Spend: fetch directly from temu_campaign_reports table
        $totalAdSpend = $this->fetchTotalAdSpendFromTables('temu');
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Temu')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('temu');

        $result[] = [
            'Channel '   => 'Temu',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => $totalAdSpend, // Temu KW ad spend from temu_campaign_reports
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'TACOS %'    => round($tacosPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
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

        // Use walmart_daily_data table (same as Sales page) instead of ShipHub
        $latestDate = \App\Models\WalmartDailyData::max('order_date');

        if (!$latestDate) {
            return response()->json([
                'status' => 200,
                'message' => 'No Walmart data found in walmart_daily_data',
                'data' => [[
                    'Channel ' => 'Walmart',
                    'L-60 Sales' => 0,
                    'L30 Sales' => 0,
                    'Growth' => '0%',
                    'L60 Orders' => 0,
                    'L30 Orders' => 0,
                    'Gprofit%' => '0%',
                    'gprofitL60' => '0%',
                    'G Roi' => 0,
                    'G RoiL60' => 0,
                    'N PFT' => '0%',
                ]]
            ]);
        }

        $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
        // Get 32 days from latest order date
        $l30EndDate = $latestDateCarbon->endOfDay(); // Latest date in DB
        $l30StartDate = $latestDateCarbon->copy()->subDays(30)->startOfDay(); // 32 days before latest
        // L60: 32-day period ending the day before L30 starts
        $l60EndDate = $l30StartDate->copy()->subDay()->endOfDay(); // Day before L30 starts
        $l60StartDate = $l60EndDate->copy()->subDays(30)->startOfDay(); // 31 days before that

        // Get Walmart marketing percentage (default 80%)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
        $margin = $percentage / 100; // convert % to fraction

        // Get L30 order items from walmart_daily_data (same as Sales page)
        $l30Orders = \App\Models\WalmartDailyData::where('period', 'l30')
            ->whereBetween('order_date', [$l30StartDate, $l30EndDate])
            ->where('fulfillment_option', 'DELIVERY')
            ->where('status', '!=', 'Cancelled')
            ->select([
                DB::raw("COALESCE(customer_order_id, purchase_order_id, CONCAT('WM-', id)) as order_id"),
                'sku',
                'quantity',
                'unit_price as price',
            ])
            ->get();

        // Get L60 order items from walmart_daily_data
        $l60Orders = \App\Models\WalmartDailyData::where('period', 'l30')
            ->whereBetween('order_date', [$l60StartDate, $l60EndDate])
            ->where('fulfillment_option', 'DELIVERY')
            ->where('status', '!=', 'Cancelled')
            ->select([
                DB::raw("COALESCE(customer_order_id, purchase_order_id, CONCAT('WM-', id)) as order_id"),
                'sku',
                'quantity',
                'unit_price as price',
            ])
            ->get();

        // Get unique SKUs
        $skus = $l30Orders->pluck('sku')->merge($l60Orders->pluck('sku'))->filter()->unique()->values()->toArray();

        // Load product masters (lp, ship) keyed by SKU (UPPERCASE)
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // Process L30 data
        $l30Sales = 0;
        $l30OrderCount = 0;
        $l30TotalQuantity = 0;
        $totalProfit = 0;
        $totalCogs = 0;
        $l30OrderIds = [];

        foreach ($l30Orders as $order) {
            $sku = strtoupper(trim($order->sku ?? ''));
            $quantity = floatval($order->quantity ?? 1);
            $unitPrice = floatval($order->price ?? 0);
            
            $saleAmount = $unitPrice * $quantity;
            $l30TotalQuantity += $quantity;

            // Get ProductMaster data
            $pm = $productMasters->get($sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        if (strtolower($key) === 'lp') $lp = floatval($value);
                        elseif (strtolower($key) === 'ship') $ship = floatval($value);
                        elseif (strtolower($key) === 'weight_act') $weightAct = floatval($value);
                    }
                }
                
                if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
                if ($ship === 0 && isset($pm->ship)) $ship = floatval($pm->ship);
            }

            // Calculate ship cost
            $tWeight = $weightAct * $quantity;
            $shipCost = ($quantity == 1) ? $ship : (($quantity > 1 && $tWeight < 20) ? ($ship / $quantity) : $ship);

            // Calculate profit
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $profit = $pftEach * $quantity;
            $cogs = $lp * $quantity;

            $l30Sales += $saleAmount;
            $totalProfit += $profit;
            $totalCogs += $cogs;
            $l30OrderIds[$order->order_id] = true;
        }
        $l30OrderCount = count($l30OrderIds);

        // Process L60 data
        $l60Sales = 0;
        $l60OrderCount = 0;
        $totalProfitL60 = 0;
        $totalCogsL60 = 0;
        $l60OrderIds = [];

        foreach ($l60Orders as $order) {
            $sku = strtoupper(trim($order->sku ?? ''));
            $quantity = floatval($order->quantity ?? 1);
            $unitPrice = floatval($order->price ?? 0);
            
            $saleAmount = $unitPrice * $quantity;

            // Get ProductMaster data
            $pm = $productMasters->get($sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        if (strtolower($key) === 'lp') $lp = floatval($value);
                        elseif (strtolower($key) === 'ship') $ship = floatval($value);
                        elseif (strtolower($key) === 'weight_act') $weightAct = floatval($value);
                    }
                }
                
                if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
                if ($ship === 0 && isset($pm->ship)) $ship = floatval($pm->ship);
            }

            // Calculate ship cost
            $tWeight = $weightAct * $quantity;
            $shipCost = ($quantity == 1) ? $ship : (($quantity > 1 && $tWeight < 20) ? ($ship / $quantity) : $ship);

            // Calculate profit
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $profit = $pftEach * $quantity;
            $cogs = $lp * $quantity;

            $l60Sales += $saleAmount;
            $totalProfitL60 += $profit;
            $totalCogsL60 += $cogs;
            $l60OrderIds[$order->order_id] = true;
        }
        $l60OrderCount = count($l60OrderIds);

        // Total Ad Spend: fetch directly from walmart_campaign_reports table
        $walmartSpent = $this->fetchTotalAdSpendFromTables('walmart');
        
        // Calculate percentages
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;
        
        // Calculate TACOS %: (Walmart Spent / Total Sales) * 100
        $tacosPercentage = $l30Sales > 0 ? ($walmartSpent / $l30Sales) * 100 : 0;
        
        // Calculate N PFT: GPFT % - TACOS %
        $nPft = $gProfitPct - $tacosPercentage;
        
        // Calculate N ROI: ROI % - TACOS %
        $nRoi = $gRoi - $tacosPercentage;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Walmart')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('walmart');

        $result[] = [
            'Channel '   => 'Walmart',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60OrderCount,
            'L30 Orders' => $l30OrderCount,
            'Qty'        => intval($l30TotalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'Walmart Spent' => round($walmartSpent, 2),
            'KW Spent'   => round($walmartSpent, 2), // Walmart KW ad spend
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => round($walmartSpent, 2),
            'TACOS %'    => round($tacosPercentage, 2) . '%',
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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

        // Get metrics from marketplace_daily_metrics table (pre-calculated from MiraklDailyData)
        $metrics = MarketplaceDailyMetric::where('channel', 'Tiendamia')->latest('date')->first();

        // Get L60 data from TiendamiaProduct for comparison
        $query = TiendamiaProduct::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('m_l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(m_l60 * price) as total')->value('total') ?? 0;

        // Use MarketplaceDailyMetric data
        $l30Sales = $metrics->total_sales ?? $metrics->l30_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;

        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // L60 profit percentage (calculate from L60 data if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = same as Gprofit% for Tiendamia (no ads)
        $nPft = $gProfitPct;

        // N ROI = same as G ROI for Tiendamia (no ads)
        $nRoi = $metrics->n_roi ?? $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Tiendamia')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('tiendamia');

        $result[] = [
            'Channel '   => 'Tiendamia',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Best Buy USA')->latest('date')->first();

        // Get L60 data from BestbuyUsaProduct for comparison
        $query = BestbuyUsaProduct::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('m_l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(m_l60 * price) as total')->value('total') ?? 0;

        // Use MarketplaceDailyMetric data
        $l30Sales = $metrics->total_sales ?? $metrics->l30_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;

        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // L60 profit percentage (calculate from L60 data if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = same as Gprofit% for Best Buy (no ads)
        $nPft = $gProfitPct;

        // N ROI = same as G ROI for Best Buy (no ads)
        $nRoi = $metrics->n_roi ?? $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'BestBuy USA')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('bestbuy');

        $result[] = [
            'Channel '   => 'BestBuy USA',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // p_l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('pls');

        $result[] = [
            'Channel '   => 'PLS',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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

        // Get Wayfair data from wayfair_daily_data table (period = 'l30')
        $wayfairData = WayfairDailyData::where('period', 'l30')
            ->where('sku', 'not like', '%Parent%')
            ->get();

        // Calculate L30 metrics
        $l30Orders = $wayfairData->count(); // Total number of orders
        $totalQuantity = $wayfairData->sum('quantity');
        $l30Sales = $wayfairData->sum(function($item) {
            return $item->unit_price * $item->quantity;
        });

        // For L60, we don't have data yet, so set to 0
        $l60Orders = 0;
        $l60Sales = 0;

        $growth = $l30Sales > 0 && $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // Get Wayfair marketplace percentage from marketplace_percentages table
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $percentageFraction = $percentage / 100; // convert % to fraction

        // Load product masters (lp only, no ship) keyed by SKU
        $skus = $wayfairData->pluck('sku')->unique()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit (WITHOUT ship cost per new Wayfair formula)
        $totalProfit = 0;
        $totalProfitL60 = 0;
        $totalCogs = 0;
        $totalCogsL60 = 0;

        foreach ($wayfairData as $order) {
            $sku = strtoupper($order->sku);
            $unitPrice = (float) $order->unit_price;
            $quantity = (int) $order->quantity;

            if ($quantity <= 0) {
                continue;
            }

            $lp = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                // Get LP from Values or direct property
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
            }

            // Wayfair Profit Formula: (unit_price * percentage) - lp (NO ship cost)
            $profitPerUnit = ($unitPrice * $percentageFraction) - $lp;
            $profitTotal = $profitPerUnit * $quantity;

            $totalProfit += $profitTotal;
            $totalCogs += ($quantity * $lp);
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // N ROI = same as G ROI for Wayfair (no ads)
        $nRoi = $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Wayfair')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('wayfair');

        $result[] = [
            'Channel '   => 'Wayfair',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // f_l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('faire');

        $result[] = [
            'Channel '   => 'Faire',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shein')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('shein');

        $result[] = [
            'Channel '   => 'Shein',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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

        // Get metrics from marketplace_daily_metrics table (pre-calculated from ShipHub)
        // Try both 'TikTok' and 'Tiktok Shop' channel names
        $metrics = MarketplaceDailyMetric::where('channel', 'TikTok')
            ->orWhere('channel', 'Tiktok Shop')
            ->latest('date')
            ->first();
        
        // Get L60 data from ShipHub (33-66 days ago range)
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'tiktok')
            ->max('order_date');

        if ($latestDate) {
            // Use California timezone for consistent date calculations
            $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
            $l60StartDate = $latestDateCarbon->copy()->subDays(59); // 60 days ago (60 days before today)
            $l60EndDate = $latestDateCarbon->copy()->subDays(30); // 31 days ago (end of L60, start of L30)
            
            // Get L60 order items from ShipHub
            $l60OrderItems = DB::connection('shiphub')
                ->table('orders as o')
                ->join('order_items as i', 'o.id', '=', 'i.order_id')
                ->whereBetween('o.order_date', [$l60StartDate, $l60EndDate])
                ->where('o.marketplace', '=', 'tiktok')
                ->where(function($query) {
                    $query->where('o.order_status', '!=', 'Canceled')
                          ->where('o.order_status', '!=', 'Cancelled')
                          ->orWhereNull('o.order_status');
                })
                ->select(
                    DB::raw('COUNT(i.id) as order_count'),
                    DB::raw('SUM(i.unit_price) as total_sales')
                )
                ->first();
            
            $l60Orders = $l60OrderItems->order_count ?? 0;
            $l60Sales = $l60OrderItems->total_sales ?? 0;
            
            // Get L30 data from ShipHub (last 30 days, California time)
            $l30StartDate = $latestDateCarbon->copy()->subDays(29); // 30 days total (29 previous days + today)
            $l30EndDate = $latestDateCarbon->endOfDay();
            
            $l30OrderItems = DB::connection('shiphub')
                ->table('orders as o')
                ->join('order_items as i', 'o.id', '=', 'i.order_id')
                ->whereBetween('o.order_date', [$l30StartDate, $l30EndDate])
                ->where('o.marketplace', '=', 'tiktok')
                ->where(function($query) {
                    $query->where('o.order_status', '!=', 'Canceled')
                          ->where('o.order_status', '!=', 'Cancelled')
                          ->orWhereNull('o.order_status');
                })
                ->select([
                    'o.marketplace_order_id as order_id',
                    'o.order_total as total_amount',
                    'i.sku',
                    'i.quantity_ordered as quantity',
                ])
                ->get();
            
            // Calculate L30 metrics from ShipHub
            $l30Orders = 0;
            $l30Sales = 0;
            $totalQuantity = 0;
            $totalProfit = 0;
            $totalCogs = 0;
            
            // Load ProductMasters with UPPERCASE keys (EXACT SAME as TikTokSalesController)
            $skus = $l30OrderItems->pluck('sku')->filter()->unique()->values()->toArray();
            $productMasters = \App\Models\ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
            $orderIds = [];
            
            // Group items by order
            $orderGroups = [];
            foreach ($l30OrderItems as $item) {
                $orderId = $item->order_id ?? 'unknown';
                if (!isset($orderGroups[$orderId])) {
                    $orderGroups[$orderId] = [
                        'order_total' => (float) ($item->total_amount ?? 0),
                        'items' => []
                    ];
                }
                $orderGroups[$orderId]['items'][] = $item;
            }
            
            // Process each order
            foreach ($orderGroups as $orderId => $orderData) {
                $orderTotal = $orderData['order_total'];
                $items = $orderData['items'];
                $itemCount = count($items);
                $pricePerItem = $itemCount > 0 ? $orderTotal / $itemCount : $orderTotal;
                
                $orderIds[$orderId] = true;
                $l30Sales += $orderTotal;
                
                foreach ($items as $item) {
                    $sku = trim($item->sku ?? '');
                    $quantity = (float) ($item->quantity ?? 1);
                    // Use UPPERCASE for lookup (EXACT SAME as TikTokSalesController)
                    $pm = $productMasters->get(strtoupper($sku));
                    
                    // EXACT SAME LOGIC as TikTokSalesController for LP, Ship, Weight Act
                    $lp = 0;
                    $ship = 0;
                    $weightAct = 0;
                    
                    if ($sku && $pm) {
                        $values = is_array($pm->Values) ? $pm->Values : 
                                (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                        
                        // Get LP
                        if (is_array($values)) {
                            foreach ($values as $k => $v) {
                                if (strtolower($k) === "lp") {
                                    $lp = floatval($v);
                                    break;
                                }
                            }
                        }
                        if ($lp === 0 && isset($pm->lp)) {
                            $lp = floatval($pm->lp);
                        }
                        
                        // Get Ship
                        if (is_array($values) && isset($values['ship'])) {
                            $ship = floatval($values['ship']);
                        } elseif (isset($pm->ship)) {
                            $ship = floatval($pm->ship);
                        }
                        
                        // Get Weight Act
                        if (is_array($values) && isset($values['wt_act'])) {
                            $weightAct = floatval($values['wt_act']);
                        }
                    }
                    
                    // Ship Cost calculation (EXACT SAME as TikTokSalesController)
                    $tWeight = $weightAct * $quantity;
                    if ($quantity == 1) {
                        $shipCost = $ship;
                    } elseif ($quantity > 1 && $tWeight < 20) {
                        $shipCost = $ship / $quantity;
                    } else {
                        $shipCost = $ship;
                    }
                    
                    $unitPrice = $quantity > 0 ? $pricePerItem / $quantity : 0;
                    $cogs = $lp * $quantity;
                    $pftEach = ($unitPrice * 0.80) - $lp - $shipCost; // 80% margin for TikTok
                    $profit = $pftEach * $quantity;
                    
                    $totalQuantity += $quantity;
                    $totalCogs += $cogs;
                    $totalProfit += $profit;
                }
            }
            
            $l30Orders = count($orderIds);
            
            // Calculate percentages
            $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
            $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
            $nPft = $gProfitPct; // TikTok has no ads
            $nRoi = $gRoi;
            
            // Debug log
            Log::info('TikTok Channel Data Calculation', [
                'l30_sales' => $l30Sales,
                'total_profit' => $totalProfit,
                'total_cogs' => $totalCogs,
                'g_profit_pct' => $gProfitPct,
                'g_roi' => $gRoi,
                'l30_orders' => $l30Orders,
                'order_count' => count($orderGroups)
            ]);
        } else {
            $l60Orders = 0;
            $l60Sales = 0;
            $l30Orders = 0;
            $l30Sales = 0;
            $totalQuantity = 0;
            $totalProfit = 0;
            $totalCogs = 0;
            $gProfitPct = 0;
            $gRoi = 0;
            $nPft = 0;
            $nRoi = 0;
        }
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;

        // L60 profit percentage (calculated if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Tiktok Shop')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('tiktok');

        // Total Ad Spend: fetch directly from tiktok_campaign_reports table
        $tiktokAdSpend = $this->fetchTotalAdSpendFromTables('tiktokshop');
        $adsPct = $l30Sales > 0 ? ($tiktokAdSpend / $l30Sales) * 100 : 0;
        $netProfit = $totalProfit - $tiktokAdSpend;
        $nPftWithAds = $gProfitPct - $adsPct;
        $nRoiWithAds = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

        $result[] = [
            'Channel '   => 'Tiktok Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2),
            'gprofitL60' => round($gprofitL60, 2),
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPftWithAds, 2),
            'N ROI'      => round($nRoiWithAds, 2),
            'Ads%'       => round($adsPct, 2),
            'TikTok Ad Spend' => round($tiktokAdSpend, 2),
            'KW Spent'   => round($tiktokAdSpend, 2), // TikTok KW ad spend
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => round($tiktokAdSpend, 2),
            'type'       => $channelData->type ?? 'B2C',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'base'       => $channelData->base ?? 0,
            'sheet_link' => $channelData->sheet_link ?? '',
            'ra'         => $channelData->ra ?? 0,
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
        $totalQuantity = $l30Orders; // i_l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('instagram');

        $result[] = [
            'Channel '   => 'Instagram Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
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

        // N ROI = same as G ROI for AliExpress (no ads)
        $nRoi = $metrics->n_roi ?? $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Aliexpress')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('aliexpress');

        $result[] = [
            'Channel '   => 'Aliexpress',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Mercari w ship')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('mercari_w_ship');

        $result[] = [
            'Channel '   => 'Mercari w ship',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Mercari wo ship')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('mercari_wo_ship');

        $result[] = [
            'Channel '   => 'Mercari wo ship',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('fb_marketplace');

        $result[] = [
            'Channel '   => 'FB Marketplace',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('fb_shop');

        $result[] = [
            'Channel '   => 'FB Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('business5core');

        $result[] = [
            'Channel '   => 'Business 5Core',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

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

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('topdawg');

        $result[] = [
            'Channel '   => 'TopDawg',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? $gProfitPct;
        $nRoi = $metrics->n_roi ?? $gRoi;
        $googleSpent = $metrics->kw_spent ?? 0; // Google Ads spend is stored in kw_spent field

        // Total Ad Spend: fetch directly from google_ads_campaigns table
        // Shopping ads (advertising_channel_type = 'SHOPPING')
        $shoppingAdSpend = $this->fetchTotalAdSpendFromTables('shopifyb2c');
        
        // SERP/Search ads (advertising_channel_type = 'SEARCH') - include ENABLED and PAUSED campaigns
        $endDate = Carbon::now()->subDays(2)->format('Y-m-d');
        $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
        $serpAdSpend = round(
            (float) DB::table('google_ads_campaigns')
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->where('advertising_channel_type', 'SEARCH')
                ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
                ->sum('metrics_cost_micros') / 1000000,
            2
        );
        
        $totalAdSpend = round($shoppingAdSpend + $serpAdSpend, 2);
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shopify B2C')->first();

        // Calculate Missing Listing count for Shopify B2C
        $missingListingCount = $this->getShopifyB2CMissingListingCount();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('shopify_b2c');

        $result[] = [
            'Channel '   => 'Shopify B2C',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => $shoppingAdSpend, // Google Shopping spend (SHOPPING type)
            'SERP Spent' => $serpAdSpend, // Google Search spend (SEARCH type)
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 1) . '%',
            'TACOS %'    => round($tacosPercentage, 1) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
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
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // Calculate growth
        $growth = $l30Sales > 0 ? (($l30Sales - $l60Sales) / $l30Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shopify B2B')->first();

        // Get Missing Listing count
        $missingListingCount = 0; // Shopify B2B doesn't have listing status tracking

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('shopify_b2b');

        $result[] = [
            'Channel '   => 'Shopify B2B',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
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
            // Set default status to 'Active' if not provided
            if (!isset($validatedData['status'])) {
                $validatedData['status'] = 'Active';
            }
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
        $missingLink = $request->input('missing_link');

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
        
        // Save missing_link if column exists
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $channel->missing_link = $missingLink;
        }
        
        $channel->save();

        MarketplacePercentage::updateOrCreate(
            ['marketplace' => $updatedChannel],
            ['percentage' => number_format((float)$channelPercentage, 2, '.', '')]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Update only channel name and Ad Type (for ADV Masters Edit).
     * Prefer update: lookup by original_channel (case-insensitive). If not found, lookup by new channel (avoid duplicates).
     * Create only when neither exists.
     */
    public function updateNameAndType(Request $request)
    {
        $request->validate([
            'original_channel' => 'required|string',
            'channel' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
        ]);

        $original = trim($request->input('original_channel'));
        $newName = trim($request->input('channel'));
        $type = $request->filled('type') ? trim($request->input('type')) : null;

        $channel = ChannelMaster::whereRaw('LOWER(TRIM(channel)) = ?', [strtolower($original)])->first();

        if (!$channel) {
            $channel = ChannelMaster::whereRaw('LOWER(TRIM(channel)) = ?', [strtolower($newName)])->first();
        }

        if ($channel) {
            $channel->channel = $newName;
            $channel->type = $type;
            $channel->save();
            return response()->json(['success' => true, 'message' => 'Channel updated successfully']);
        }

        ChannelMaster::create([
            'channel' => $newName,
            'type' => $type,
            'status' => 'Active',
        ]);
        return response()->json(['success' => true, 'message' => 'Channel created successfully']);
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

    /**
     * Get list of campaign names currently in Google Sheet (L30 data only)
     * This ensures we only sum data that exists in the current Google Sheet
     */
    private function getCurrentGoogleSheetCampaigns()
    {
        try {
            $url = "https://script.google.com/macros/s/AKfycbxWwC98yCcPDcXjXfKpbE0dMC74L0YfF0fx2HdG_i3G7BzSjuhD8H9X98byGQymFNbx/exec";
            
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
            
            if (!$response->ok()) {
                Log::warning('Failed to fetch current Google Sheet data for totals calculation');
                return [];
            }
            
            $json = $response->json();
            
            // Get L30 data only
            if (!isset($json['L30']['data'])) {
                return [];
            }
            
            // Extract unique campaign names from current Google Sheet
            $campaignNames = [];
            foreach ($json['L30']['data'] as $row) {
                $campaignName = $row['campaign_name'] ?? null;
                if ($campaignName && !empty(trim($campaignName))) {
                    $campaignNames[] = trim($campaignName);
                }
            }
            
            return array_unique($campaignNames);
        } catch (\Exception $e) {
            Log::error('Error fetching current Google Sheet campaigns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get channel history for modal (last 30 days)
     */
    public function getChannelHistory($channel)
    {
        try {
            // Use California/Pacific timezone for date calculations
            $history = \App\Models\ChannelMasterSummary::where('channel', $channel)
                ->where('snapshot_date', '>=', now('America/Los_Angeles')->subDays(30)->toDateString())
                ->orderBy('snapshot_date', 'desc')
                ->get();
            
            return response()->json([
                'status' => 200,
                'message' => 'History fetched successfully',
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching channel history: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching history',
            ], 500);
        }
    }

    /**
     * Get channel metric chart data from ChannelMasterSummary snapshots
     */
    public function getChannelMetricChartData(Request $request)
    {
        try {
            $channel = strtolower(str_replace([' ', '-', '&', '/'], '', trim($request->input('channel', ''))));
            $metric = $request->input('metric', 'l30_sales');
            $days = intval($request->input('days', 30));

            if (!$channel) {
                return response()->json(['success' => false, 'message' => 'Channel is required'], 400);
            }

            // Map frontend metric names to summary_data keys
            $metricMap = [
                'l30_sales' => 'l30_sales',
                'l30_orders' => 'l30_orders',
                'qty' => 'total_quantity',
                'gprofit' => 'gprofit_percent',
                'groi' => 'groi_percent',
                'ads_pct' => 'tcos_percent',
                'pft' => null,       // computed: total profit amount from gprofit%
                'npft' => 'npft_percent',
                'nroi' => null,      // computed: npft / tcos * 100
                'missing_l' => 'miss_count',
                'nmap' => 'nmap_count',
                'ad_spend' => 'total_ad_spend',
                'clicks' => 'clicks',
                'ad_sales' => null,  // uses ad_sales with l30_sales ratio fallback
                'ad_sold' => null,   // uses ad_sold with clicks × cvr ratio fallback
                'acos' => null,      // computed: (ad_spend / ad_sales) * 100
                'ads_cvr' => null,   // computed: (ad_sold / clicks) * 100
            ];

            $metricKey = $metricMap[$metric] ?? $metric;
            $isAll = ($channel === 'all');

            // Metrics that should be averaged (percentages) vs summed (counts/amounts)
            $avgMetrics = ['gprofit', 'groi', 'ads_pct', 'npft', 'nroi', 'acos', 'ads_cvr'];
            $shouldAvg = in_array($metric, $avgMetrics);

            // Determine date range
            $query = \App\Models\ChannelMasterSummary::orderBy('snapshot_date', 'asc');

            if (!$isAll) {
                $query->where('channel', $channel);
            }

            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }

            $history = $query->get();

            if ($history->isEmpty()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Group by snapshot_date for "all" aggregation
            $grouped = $history->groupBy(function ($row) {
                return Carbon::parse($row->snapshot_date)->format('Y-m-d');
            })->sortKeys();

            $chartData = [];
            foreach ($grouped as $dateKey => $rows) {
                $date = Carbon::parse($dateKey)->format('M d');

                if ($isAll) {
                    // Aggregate across all channels for this date
                    $totalVal = 0;
                    $totalSpend = 0;
                    $totalSales = 0;
                    $totalAdSales = 0;
                    $totalAdSold = 0;
                    $totalClicks = 0;
                    $totalCogs = 0;
                    $totalPft = 0;
                    $totalNpft = 0;
                    $totalTcos = 0;
                    $count = 0;

                    foreach ($rows as $row) {
                        $sd = $row->summary_data ?? [];
                        $count++;

                        $channelL30Sales = floatval($sd['l30_sales'] ?? 0);
                        $channelAdSpend = floatval($sd['total_ad_spend'] ?? 0);
                        $channelGprofit = floatval($sd['gprofit_percent'] ?? 0);

                        $channelCogs = floatval($sd['cogs'] ?? 0);
                        // Always derive profit from gprofit% (matches badge calculation exactly)
                        $channelPft = ($channelGprofit / 100) * $channelL30Sales;

                        if ($metric === 'acos' || $metric === 'ad_sales') {
                            $totalSpend += $channelAdSpend;
                            $adSales = floatval($sd['ad_sales'] ?? 0);
                            $totalAdSales += $adSales > 0 ? $adSales : ($channelL30Sales * 0.5);
                        } elseif ($metric === 'ads_cvr' || $metric === 'ad_sold') {
                            $totalAdSold += floatval($sd['ad_sold'] ?? 0);
                            $totalClicks += floatval($sd['clicks'] ?? 0);
                        } elseif ($metric === 'gprofit' || $metric === 'npft' || $metric === 'pft') {
                            $totalPft += $channelPft;
                            $totalSales += $channelL30Sales;
                            $totalSpend += $channelAdSpend;
                        } elseif ($metric === 'groi' || $metric === 'nroi') {
                            $totalPft += $channelPft;
                            $totalCogs += $channelCogs;
                            $totalSpend += $channelAdSpend;
                            $totalSales += $channelL30Sales;
                        } elseif ($metric === 'ads_pct') {
                            $totalSpend += $channelAdSpend;
                            $totalSales += $channelL30Sales;
                        } elseif ($shouldAvg) {
                            $totalVal += floatval($sd[$metricKey] ?? 0);
                        } else {
                            $totalVal += floatval($sd[$metricKey] ?? 0);
                        }
                    }

                    if ($metric === 'acos') {
                        $value = $totalAdSales > 0 ? round(($totalSpend / $totalAdSales) * 100, 1) : 0;
                    } elseif ($metric === 'ad_sales') {
                        $value = round($totalAdSales, 2);
                    } elseif ($metric === 'ads_cvr') {
                        $value = $totalClicks > 0 ? round(($totalAdSold / $totalClicks) * 100, 1) : 0;
                    } elseif ($metric === 'ad_sold') {
                        $value = round($totalAdSold);
                    } elseif ($metric === 'gprofit') {
                        // Weighted avg: total profit / total sales * 100
                        $value = $totalSales > 0 ? round(($totalPft / $totalSales) * 100, 1) : 0;
                    } elseif ($metric === 'pft') {
                        // Total Pft amount (sum of all profit)
                        $value = round($totalPft, 2);
                    } elseif ($metric === 'npft') {
                        // N PFT = G PFT% - Ads%
                        $gpft = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0;
                        $adsPct = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;
                        $value = round($gpft - $adsPct, 1);
                    } elseif ($metric === 'groi') {
                        // G ROI = total profit / total cogs * 100
                        $value = $totalCogs > 0 ? round(($totalPft / $totalCogs) * 100, 1) : 0;
                    } elseif ($metric === 'nroi') {
                        // N ROI = GROI% - TCOS%
                        $groi = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;
                        $tcos = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;
                        $value = round($groi - $tcos, 1);
                    } elseif ($metric === 'ads_pct') {
                        $value = $totalSales > 0 ? round(($totalSpend / $totalSales) * 100, 1) : 0;
                    } elseif ($shouldAvg && $count > 0) {
                        $value = round($totalVal / $count, 1);
                    } else {
                        $value = round($totalVal, 2);
                    }
                } else {
                    // Single channel
                    $row = $rows->first();
                    $summaryData = $row->summary_data ?? [];

                    if ($metric === 'acos') {
                        // Always use ratio approach: ad_spend / (l30_sales × ratio)
                        // This gives consistent trend because ratio is fixed from latest date
                        $spend = floatval($summaryData['total_ad_spend'] ?? 0);
                        $l30Sales = floatval($summaryData['l30_sales'] ?? 0);
                        $adSales = $l30Sales * $this->getAdSalesRatio($channel);
                        $value = $adSales > 0 ? round(($spend / $adSales) * 100, 1) : 0;
                    } elseif ($metric === 'ad_sales') {
                        $adSales = floatval($summaryData['ad_sales'] ?? 0);
                        if ($adSales <= 0) {
                            $l30Sales = floatval($summaryData['l30_sales'] ?? 0);
                            $adSales = $l30Sales * $this->getAdSalesRatio($channel);
                        }
                        $value = round($adSales, 2);
                    } elseif ($metric === 'ad_sold') {
                        $adSold = floatval($summaryData['ad_sold'] ?? 0);
                        if ($adSold <= 0) {
                            $clicks = floatval($summaryData['clicks'] ?? 0);
                            $adSold = $clicks * $this->getAdCvrRatio($channel);
                        }
                        $value = round($adSold);
                    } elseif ($metric === 'ads_cvr') {
                        $adSold = floatval($summaryData['ad_sold'] ?? 0);
                        $clicks = floatval($summaryData['clicks'] ?? 0);
                        if ($adSold <= 0 && $clicks > 0) {
                            $adSold = $clicks * $this->getAdCvrRatio($channel);
                        }
                        $value = $clicks > 0 ? round(($adSold / $clicks) * 100, 1) : 0;
                    } elseif ($metric === 'pft') {
                        // Calculate profit amount from gprofit%
                        $gprofitPercent = floatval($summaryData['gprofit_percent'] ?? 0);
                        $sales = floatval($summaryData['l30_sales'] ?? 0);
                        $value = round(($gprofitPercent / 100) * $sales, 2);
                    } elseif ($metric === 'nroi') {
                        // N ROI = GROI% - TCOS%
                        $groi = floatval($summaryData['groi_percent'] ?? 0);
                        $tcos = floatval($summaryData['tcos_percent'] ?? 0);
                        $value = round($groi - $tcos, 1);
                    } else {
                        $value = floatval($summaryData[$metricKey] ?? 0);
                    }
                }

                $chartData[] = [
                    'date' => $date,
                    'value' => $value,
                ];
            }

            // ==================================================================================
            // SCALE chart data so the latest value matches the table value
            // Table uses marketplace_daily_metrics / fetchAdMetricsFromTables as source
            // Chart uses ChannelMasterSummary which may have different values
            // ==================================================================================
            if (!empty($chartData)) {
                // If the frontend passed the exact cell/badge value, use it directly
                $cellValue = $request->input('badge_value');
                if ($cellValue !== null) {
                    $tableRef = (float) $cellValue;
                } elseif ($isAll) {
                    $tableRef = $this->getAllChannelsTableReference($metric);
                } else {
                    $tableRef = $this->getTableReferenceValue($channel, $metric);
                }
                if ($tableRef !== null && $tableRef != 0) {
                    $chartLatest = (float) end($chartData)['value'];
                    if ($chartLatest != 0 && abs($chartLatest - $tableRef) > 0.01) {
                        $sf = $tableRef / $chartLatest;
                        foreach ($chartData as &$pt) {
                            $pt['value'] = round($pt['value'] * $sf, 2);
                        }
                        unset($pt);
                    }
                }
            }

            // Smooth out consecutive duplicate values
            $chartData = $this->interpolateChartData($chartData);

            return response()->json([
                'success' => true,
                'data' => $chartData,
            ]);

        } catch (\Exception $e) {
            \Log::error('getChannelMetricChartData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Get the "table reference value" for ALL channels combined.
     * Sums the latest marketplace_daily_metrics value across every channel.
     * Used when channel='all' to scale charts to match the badge totals.
     */
    private function getAllChannelsTableReference(string $metric): ?float
    {
        // Metrics that are averaged (percentages) — cannot simply sum
        $avgMetrics = ['gprofit', 'groi', 'ads_pct', 'npft', 'nroi', 'acos', 'ads_cvr'];
        if (in_array($metric, $avgMetrics)) {
            // For averaged metrics, get weighted average across channels
            $allMdm = MarketplaceDailyMetric::selectRaw('channel, MAX(date) as max_date')
                ->groupBy('channel')
                ->get();

            $totalSales = 0;
            $weightedSum = 0;
            $count = 0;
            foreach ($allMdm as $row) {
                $mdm = MarketplaceDailyMetric::where('channel', $row->channel)
                    ->where('date', $row->max_date)->first();
                if (!$mdm) continue;

                $sales = (float) ($mdm->total_sales ?? 0);
                $val = match($metric) {
                    'gprofit' => (float) ($mdm->pft_percentage ?? 0),
                    'groi' => (float) ($mdm->roi_percentage ?? 0),
                    'ads_pct' => (float) ($mdm->tacos_percentage ?? 0),
                    'npft' => (float) ($mdm->n_pft ?? 0),
                    'nroi' => (float) ($mdm->n_roi ?? 0),
                    default => null,
                };
                if ($val === null) continue;
                // Weight by sales for meaningful average
                $totalSales += $sales;
                $weightedSum += $val * $sales;
                $count++;
            }
            if ($metric === 'acos' || $metric === 'ads_cvr') {
                // For ad metrics, compute from totals
                $liveTotal = ['Total Ad Spend' => 0, 'clicks' => 0, 'ad_sales' => 0, 'ad_sold' => 0];
                $adChannels = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'walmart', 'shopifyb2c', 'tiktokshop'];
                foreach ($adChannels as $ch) {
                    $live = $this->fetchAdMetricsFromTables($ch);
                    $liveTotal['Total Ad Spend'] += (float) ($live['Total Ad Spend'] ?? 0);
                    $liveTotal['clicks'] += (float) ($live['clicks'] ?? 0);
                    $liveTotal['ad_sales'] += (float) ($live['ad_sales'] ?? 0);
                    $liveTotal['ad_sold'] += (float) ($live['ad_sold'] ?? 0);
                }
                return match($metric) {
                    'acos' => $liveTotal['ad_sales'] > 0 ? round(($liveTotal['Total Ad Spend'] / $liveTotal['ad_sales']) * 100, 2) : null,
                    'ads_cvr' => $liveTotal['clicks'] > 0 ? round(($liveTotal['ad_sold'] / $liveTotal['clicks']) * 100, 2) : null,
                    default => null,
                };
            }
            return $totalSales > 0 ? round($weightedSum / $totalSales, 2) : ($count > 0 ? round($weightedSum / $count, 2) : null);
        }

        // For summable metrics (counts, amounts): sum across all channels
        $allMdm = MarketplaceDailyMetric::selectRaw('channel, MAX(date) as max_date')
            ->groupBy('channel')
            ->get();

        $total = 0;
        foreach ($allMdm as $row) {
            $mdm = MarketplaceDailyMetric::where('channel', $row->channel)
                ->where('date', $row->max_date)->first();
            if (!$mdm) continue;

            $val = match($metric) {
                'l30_sales' => (float) ($mdm->total_sales ?? 0),
                'l30_orders' => (float) ($mdm->total_orders ?? 0),
                'qty' => (float) ($mdm->total_quantity ?? 0),
                'pft' => (float) ($mdm->total_pft ?? 0),
                default => null,
            };
            if ($val !== null) $total += $val;
        }

        // For ad metrics, sum from live campaign tables
        if (in_array($metric, ['ad_spend', 'clicks', 'ad_sales', 'ad_sold'])) {
            $adChannels = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'walmart', 'shopifyb2c', 'tiktokshop'];
            $total = 0;
            foreach ($adChannels as $ch) {
                $live = $this->fetchAdMetricsFromTables($ch);
                $total += match($metric) {
                    'ad_spend' => (float) ($live['Total Ad Spend'] ?? 0),
                    'clicks' => (float) ($live['clicks'] ?? 0),
                    'ad_sales' => (float) ($live['ad_sales'] ?? 0),
                    'ad_sold' => (float) ($live['ad_sold'] ?? 0),
                    default => 0,
                };
            }
        }

        return $total > 0 ? round($total, 2) : null;
    }

    /**
     * Get the "table reference value" for a given channel and metric.
     * This returns the exact value that the table displays, ensuring charts match.
     * Table sources: marketplace_daily_metrics for most metrics, fetchAdMetricsFromTables for ad metrics.
     */
    private function getTableReferenceValue(string $channel, string $metric): ?float
    {
        static $mdmCache = [];
        static $liveCache = [];

        // Get marketplace_daily_metrics for this channel
        $mdmKey = strtolower($channel);
        if (!isset($mdmCache[$mdmKey])) {
            $channelMap = [
                'amazon' => 'Amazon', 'amazonfba' => 'Amazon FBA',
                'ebay' => 'eBay', 'ebaytwo' => 'eBay 2', 'ebaythree' => 'eBay 3',
                'shopifyb2c' => 'Shopify B2C', 'temu' => 'Temu', 'walmart' => 'Walmart',
                'tiktokshop' => 'TikTok Shop',
            ];
            $mdmChannel = $channelMap[$mdmKey] ?? null;
            $mdmCache[$mdmKey] = $mdmChannel
                ? MarketplaceDailyMetric::where('channel', $mdmChannel)->orderBy('date', 'desc')->first()
                : null;
        }
        $mdm = $mdmCache[$mdmKey];

        // For ad metrics, use fetchAdMetricsFromTables (same as table)
        if (in_array($metric, ['ad_spend', 'clicks', 'ad_sales', 'ad_sold', 'acos', 'ads_cvr'])) {
            if (!isset($liveCache[$mdmKey])) {
                $liveCache[$mdmKey] = $this->fetchAdMetricsFromTables($mdmKey);
            }
            $live = $liveCache[$mdmKey];

            return match($metric) {
                'ad_spend' => (float) ($live['Total Ad Spend'] ?? 0),
                'clicks' => (float) ($live['clicks'] ?? 0),
                'ad_sales' => (float) ($live['ad_sales'] ?? 0),
                'ad_sold' => (float) ($live['ad_sold'] ?? 0),
                'acos' => ($live['ad_sales'] ?? 0) > 0
                    ? round(((float)($live['Total Ad Spend'] ?? 0) / (float)$live['ad_sales']) * 100, 2)
                    : null,
                'ads_cvr' => ($live['clicks'] ?? 0) > 0
                    ? round(((float)($live['ad_sold'] ?? 0) / (float)$live['clicks']) * 100, 2)
                    : null,
                default => null,
            };
        }

        if (!$mdm) return null;

        // Map metric to marketplace_daily_metrics field
        return match($metric) {
            'l30_sales' => (float) $mdm->total_sales,
            'l30_orders' => (float) $mdm->total_orders,
            'qty' => (float) $mdm->total_quantity,
            'gprofit' => (float) $mdm->pft_percentage,
            'groi' => (float) $mdm->roi_percentage,
            'ads_pct' => (float) $mdm->tacos_percentage,
            'npft' => (float) $mdm->n_pft,
            'nroi' => $mdm->n_roi !== null ? (float) $mdm->n_roi : null,
            'pft' => $mdm->total_pft !== null ? (float) $mdm->total_pft : null,
            'missing_l' => null, // not critical for matching
            'nmap' => null,
            default => null,
        };
    }

    /**
     * Smooth out consecutive duplicate values in chart data via linear interpolation.
     * Finds "transition points" where the value actually changes, then linearly
     * interpolates between them so there are no flat plateaus.
     * Preserves the first point, last point, and all transition points exactly.
     */
    private function interpolateChartData(array $chartData): array
    {
        $n = count($chartData);
        if ($n <= 2) return $chartData;

        // Step 1: Find transition indices (where value differs from previous value)
        // Index 0 is always a transition. Any index where value != value[i-1] is a transition.
        $transitions = [0]; // always include first point
        for ($i = 1; $i < $n; $i++) {
            if (abs((float) $chartData[$i]['value'] - (float) $chartData[$i - 1]['value']) > 0.001) {
                $transitions[] = $i;
            }
        }
        // Always include last point if not already there
        if (end($transitions) !== $n - 1) {
            $transitions[] = $n - 1;
        }

        // Handle end-of-series duplicates: if the last transition and the forced last point
        // have the same value, remove the earlier one so interpolation spans from further back
        $tCount = count($transitions);
        if ($tCount >= 3) {
            $lastIdx = $transitions[$tCount - 1];
            $prevIdx = $transitions[$tCount - 2];
            $lastVal = (float) $chartData[$lastIdx]['value'];
            $prevVal = (float) $chartData[$prevIdx]['value'];
            if (abs($lastVal - $prevVal) < 0.001) {
                // Remove the second-to-last transition so interpolation bridges from further back
                array_splice($transitions, $tCount - 2, 1);
            }
        }

        // If transitions cover most points, no smoothing needed
        if (count($transitions) >= $n - 1) return $chartData;

        // Step 2: Linearly interpolate between consecutive transition points
        for ($t = 0; $t < count($transitions) - 1; $t++) {
            $fromIdx = $transitions[$t];
            $toIdx = $transitions[$t + 1];
            $gap = $toIdx - $fromIdx;

            if ($gap <= 1) continue; // adjacent transitions, nothing to fill

            $fromVal = (float) $chartData[$fromIdx]['value'];
            $toVal = (float) $chartData[$toIdx]['value'];

            for ($k = $fromIdx + 1; $k < $toIdx; $k++) {
                $frac = ($k - $fromIdx) / $gap;
                $chartData[$k]['value'] = round($fromVal + ($toVal - $fromVal) * $frac, 2);
            }
        }

        return $chartData;
    }

    /**
     * Get ad_sales / l30_sales ratio from the latest ChannelMasterSummary row with ad_sales data.
     * Used as fallback when ad_sales is not available for historical dates.
     */
    private function getAdSalesRatio($channel)
    {
        static $cache = [];
        if (isset($cache[$channel])) return $cache[$channel];

        $row = \App\Models\ChannelMasterSummary::where('channel', $channel)
            ->orderBy('snapshot_date', 'desc')
            ->first();
        if ($row) {
            $sd = $row->summary_data ?? [];
            $adSales = floatval($sd['ad_sales'] ?? 0);
            $l30Sales = floatval($sd['l30_sales'] ?? 0);
            if ($adSales > 0 && $l30Sales > 0) {
                $cache[$channel] = $adSales / $l30Sales;
                return $cache[$channel];
            }
        }
        $cache[$channel] = 0.5; // default 50% fallback
        return $cache[$channel];
    }

    /**
     * Get ad_sold / clicks ratio (CVR) from the latest ChannelMasterSummary row.
     * Used as fallback when ad_sold is not available for historical dates.
     */
    private function getAdCvrRatio($channel)
    {
        static $cache = [];
        if (isset($cache[$channel])) return $cache[$channel];

        $row = \App\Models\ChannelMasterSummary::where('channel', $channel)
            ->orderBy('snapshot_date', 'desc')
            ->first();
        if ($row) {
            $sd = $row->summary_data ?? [];
            $adSold = floatval($sd['ad_sold'] ?? 0);
            $clicks = floatval($sd['clicks'] ?? 0);
            if ($adSold > 0 && $clicks > 0) {
                $cache[$channel] = $adSold / $clicks;
                return $cache[$channel];
            }
        }
        $cache[$channel] = 0.04; // default 4% CVR fallback
        return $cache[$channel];
    }

    /**
     * Get channel name as it appears in marketplace_daily_metrics table
     */
    private function getChannelNameForMetrics($normalizedChannelName)
    {
        $mapping = [
            'amazon' => 'Amazon',
            'ebay' => 'eBay',
            'ebaytwo' => 'eBay 2',
            'ebaythree' => 'eBay 3',
            'walmart' => 'Walmart',
            'temu' => 'Temu',
            'macys' => 'Macys',
            'tiendamia' => 'Tiendamia',
            'bestbuyusa' => 'Best Buy USA',
            'reverb' => 'Reverb',
            'doba' => 'Doba',
            'pls' => 'PLS',
            'wayfair' => 'Wayfair',
            'faire' => 'Faire',
            'shein' => 'Shein',
            'tiktokshop' => 'TikTok',
            'instagramshop' => 'Instagram Shop',
            'aliexpress' => 'AliExpress',
            'mercariwship' => 'Mercari With Ship',
            'mercariwoship' => 'Mercari Without Ship',
            'fbmarketplace' => 'FB Marketplace',
            'fbshop' => 'FB Shop',
            'business5core' => 'Business 5Core',
            'topdawg' => 'TopDawg',
            'shopifyb2c' => 'Shopify B2C',
            'shopifyb2b' => 'Shopify B2B',
        ];
        
        return $mapping[$normalizedChannelName] ?? ucfirst($normalizedChannelName);
    }

    /**
     * Save channel-wise daily summaries for historical tracking
     */
    private function saveChannelDailySummaries($channelData)
    {
        try {
            // Use California/Pacific timezone for date storage
            $today = now('America/Los_Angeles')->toDateString();
            
            foreach ($channelData as $row) {
                $channelName = strtolower(str_replace([' ', '-', '&', '/'], '', trim($row['Channel '] ?? '')));
                
                if (!$channelName) continue;
                
                // Calculate NPFT% and TCOS%
                $gprofitPercent = floatval($row['Gprofit%'] ?? 0);
                $adSpend = floatval($row['Total Ad Spend'] ?? 0);
                $l30Sales = floatval($row['L30 Sales'] ?? 0);
                $tcosPercent = $l30Sales > 0 ? ($adSpend / $l30Sales * 100) : 0;
                $npftPercent = $gprofitPercent - $tcosPercent;
                
                // Get actual total_quantity from marketplace_daily_metrics (for units sold)
                $channelNameForMetrics = $this->getChannelNameForMetrics($channelName);
                $metrics = \App\Models\MarketplaceDailyMetric::where('channel', $channelNameForMetrics)
                    ->latest('date')
                    ->first();
                $totalQuantity = $metrics ? ($metrics->total_quantity ?? $metrics->total_orders ?? 0) : 0;
                
                // Prepare summary data
                $summaryData = [
                    // Sales & Orders
                    'l60_sales' => floatval($row['L-60 Sales'] ?? 0),
                    'l30_sales' => $l30Sales,
                    'l60_orders' => floatval($row['L60 Orders'] ?? 0),
                    'l30_orders' => floatval($row['L30 Orders'] ?? 0),
                    'total_quantity' => floatval($totalQuantity), // Total quantity (units sold) from marketplace_daily_metrics
                    'growth' => floatval($row['Growth'] ?? 0),
                    'clicks' => intval($row['clicks'] ?? 0),
                    
                    // Profit & ROI Metrics
                    'gprofit_percent' => $gprofitPercent,
                    'gprofit_l60' => floatval($row['gprofitL60'] ?? 0),
                    'groi_percent' => floatval($row['G Roi'] ?? 0),
                    'groi_l60' => floatval($row['G RoiL60'] ?? 0),
                    'npft_percent' => round($npftPercent, 2),
                    'nroi_percent' => floatval($row['N ROI'] ?? 0),
                    'tcos_percent' => round($tcosPercent, 2),
                    'total_ad_spend' => $adSpend,
                    'ad_sales' => floatval($row['Ad Sales'] ?? 0),
                    'ad_sold' => intval($row['ad_sold'] ?? 0),
                    'ads_cvr' => floatval($row['Ads CVR'] ?? 0),
                    'cogs' => floatval($row['cogs'] ?? 0),
                    'total_pft' => floatval($row['Total PFT'] ?? 0),
                    
                    // Counts
                    'missing_listing' => intval($row['Missing Listing'] ?? 0),
                    'stock_mapping' => intval($row['Stock Mapping'] ?? 0),
                    'map_count' => intval($row['Map'] ?? 0),
                    'miss_count' => intval($row['Miss'] ?? 0),
                    'nmap_count' => intval($row['NMap'] ?? 0),
                    'nr_count' => intval($row['NR'] ?? 0),
                    'listed_count' => intval($row['listed_count'] ?? 0),
                    
                    // Config
                    'channel_percentage' => floatval($row['channel_percentage'] ?? 0),
                    'base' => floatval($row['base'] ?? 0),
                    'target' => floatval($row['target'] ?? 0),
                    'type' => $row['type'] ?? 'B2C',
                    'w_ads' => intval($row['W/Ads'] ?? 0),
                    'update' => intval($row['Update'] ?? 0),
                    
                    // Metadata
                    'calculated_at' => now()->toDateTimeString(),
                ];
                
                // Save or update
                \App\Models\ChannelMasterSummary::updateOrCreate(
                    [
                        'channel' => $channelName,
                        'snapshot_date' => $today
                    ],
                    [
                        'summary_data' => $summaryData,
                        'notes' => 'Auto-saved channel master snapshot',
                    ]
                );
            }
            
            Log::info("Channel Master daily summaries saved for {$today}", [
                'channels_count' => count($channelData),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error saving channel master daily summaries: ' . $e->getMessage());
        }
    }

    /**
     * Get clicks breakdown (PT, KW, HL) for a channel
     */
    public function getClicksBreakdown(Request $request)
    {
        try {
            $channel = $request->input('channel');
            
            if (!$channel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Channel name is required'
                ], 400);
            }
            
            // Get channel name and convert to uppercase
            $channelUpper = strtoupper(trim($channel));
            
            \Log::info("Clicks breakdown requested", ['channel' => $channelUpper]);
            
            // Determine related channel patterns based on main channel
            $suffixPatterns = [];
            if ($channelUpper === 'AMAZON') {
                $suffixPatterns = ['AMZ PT', 'AMZ KW', 'AMZ HL'];
            } elseif ($channelUpper === 'EBAY') {
                $suffixPatterns = ['EB KW', 'EB PMT'];
            } elseif ($channelUpper === 'EBAY 2' || $channelUpper === 'EBAYTWO') {
                $suffixPatterns = ['EB PMT2'];
            } elseif ($channelUpper === 'EBAY 3' || $channelUpper === 'EBAYTHREE') {
                $suffixPatterns = ['EB KW3', 'EB PMT3'];
            } elseif ($channelUpper === 'WALMART') {
                $suffixPatterns = [];
            }
            
            \Log::info("Searching for patterns", ['patterns' => $suffixPatterns]);
            
            // Get parent channel's L30 sales for TACOS calculation
            $parentChannel = \App\Models\ADVMastersData::where('channel', $channelUpper)->first();
            $parentL30Sales = $parentChannel ? ($parentChannel->l30_sales ?? 0) : 0;
            
            \Log::info("Parent channel L30 sales", [
                'channel' => $channelUpper,
                'l30_sales' => $parentL30Sales
            ]);
            
            // Find all related channels from adv_masters_datas table
            $breakdown = [];
            $total = 0;
            
            foreach ($suffixPatterns as $pattern) {
                // Try both exact match and case-insensitive match
                $relatedChannel = \App\Models\ADVMastersData::where('channel', $pattern)
                    ->orWhereRaw('UPPER(channel) = ?', [strtoupper($pattern)])
                    ->first();
                
                \Log::info("Pattern search", [
                    'pattern' => $pattern,
                    'found' => $relatedChannel ? 'yes' : 'no',
                    'found_channel' => $relatedChannel ? $relatedChannel->channel : null
                ]);
                
                if ($relatedChannel) {
                    // Extract clean type name from channel pattern
                    $type = $pattern;
                    if (strpos($pattern, 'PMT') !== false) {
                        $type = str_replace(['EB ', 'EB'], '', $pattern);
                    } elseif (strpos($pattern, 'KW') !== false) {
                        $type = str_replace(['EB ', 'EB', 'AMZ ', 'AMZ'], '', $pattern);
                    } elseif (strpos($pattern, 'PT') !== false || strpos($pattern, 'HL') !== false) {
                        $type = str_replace(['AMZ ', 'AMZ'], '', $pattern);
                    }
                    
                    // Get values from database
                    $clicks = $relatedChannel->clicks ?? 0;
                    $adSold = $relatedChannel->ad_sold ?? 0;
                    $adSales = $relatedChannel->ad_sales ?? 0;
                    $spent = $relatedChannel->spent ?? 0;
                    
                    // Calculate metrics using formulas:
                    // CVR = (ads sold / clicks) * 100
                    $cvr = $clicks > 0 ? round(($adSold / $clicks) * 100, 2) : 0;
                    
                    // ACOS = (spent / ad_sales) * 100
                    $acos = $adSales > 0 ? round(($spent / $adSales) * 100, 2) : 0;
                    
                    // TACOS = (spent / parent_total_sales) * 100
                    // Use parent channel's L30 sales for all sub-channels
                    $tacos = $parentL30Sales > 0 ? round(($spent / $parentL30Sales) * 100, 2) : 0;
                    
                    $breakdown[] = [
                        'type' => $type,
                        'channel' => $relatedChannel->channel,
                        'spent' => $spent,
                        'clicks' => $clicks,
                        'ad_sales' => $adSales,
                        'acos' => $acos,
                        'tacos' => $tacos,
                        'ad_sold' => $adSold,
                        'cvr' => $cvr,
                        'missing_ads' => $relatedChannel->missing_ads ?? 0
                    ];
                    $total += $clicks;
                }
            }
            
            \Log::info("Clicks breakdown results", [
                'channel' => $channelUpper,
                'found_count' => count($breakdown),
                'total_clicks' => $total
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $breakdown,
                'total' => $total,
                'parent_l30_sales' => $parentL30Sales
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching clicks breakdown: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching clicks breakdown'
            ], 500);
        }
    }

    /**
     * Get daily ad metrics chart data for breakdown columns (KW, PT, HL, PMT, SHOPPING, SERP)
     * Returns last 30 days of daily data for the specified channel and metric type.
     * Daily data is only available for: Amazon, eBay 1/2/3, Google Ads (Shopify B2C)
     */
    public function getAdBreakdownChartData(Request $request)
    {
        try {
            $channel = strtolower(trim($request->input('channel', '')));
            $adType = strtolower(trim($request->input('ad_type', ''))); // kw, pt, hl, pmt, shopping, serp
            $metric = strtolower(trim($request->input('metric', 'spend'))); // spend, clicks, sales, sold

            if (!$channel || !$adType) {
                return response()->json(['success' => false, 'message' => 'Channel and ad_type are required'], 400);
            }

            // ==================================================================================
            // RATIO-BASED APPROACH: Use ChannelMasterSummary historical totals + today's
            // breakdown ratios from live campaign tables (same source as the table display).
            // This ensures chart values match the table exactly.
            // ==================================================================================
            $metricsChannelMap = [
                'amazon' => 'Amazon', 'amazonfba' => 'Amazon FBA',
                'ebay' => 'eBay', 'ebaytwo' => 'eBay 2', 'ebaythree' => 'eBay 3',
                'shopifyb2c' => 'Shopify B2C', 'temu' => 'Temu', 'walmart' => 'Walmart',
            ];
            $metricsChannel = $metricsChannelMap[$channel] ?? null;
            // ChannelMasterSummary uses the frontend channel name directly (lowercase)
            $summaryChannel = $channel;

            // Fetch live metrics from campaign tables (same source as table display)
            $liveMetrics = $this->fetchAdMetricsFromTables($channel);

            // Map ad_type to column name prefix
            $adTypePrefix = match($adType) {
                'kw' => 'KW', 'pt' => 'PT', 'hl' => 'HL',
                'pmt' => 'PMT', 'shopping' => 'Shopping', 'serp' => 'SERP',
                default => 'KW',
            };

            // Extract live values for THIS ad type
            $adTypeSpend = (float) ($liveMetrics["{$adTypePrefix} Spent"] ?? 0);
            $adTypeClicks = (float) ($liveMetrics["{$adTypePrefix} Clicks"] ?? 0);
            $adTypeSales = (float) ($liveMetrics["{$adTypePrefix} Sales"] ?? 0);
            $adTypeSold = (float) ($liveMetrics["{$adTypePrefix} Sold"] ?? 0);

            // Extract totals from live metrics
            $totalSpend = (float) ($liveMetrics['Total Ad Spend'] ?? 0);
            $totalClicks = (float) ($liveMetrics['clicks'] ?? 0);
            $totalSales = (float) ($liveMetrics['ad_sales'] ?? 0);
            $totalSold = (float) ($liveMetrics['ad_sold'] ?? 0);

            if ($metricsChannel && ($adTypeSpend > 0 || $adTypeClicks > 0 || $adTypeSales > 0)) {
                    // Calculate ratios from live campaign data
                    $ratios = [];
                    $ratios['spend'] = $totalSpend > 0 ? $adTypeSpend / $totalSpend : 0;
                    $ratios['clicks'] = $totalClicks > 0 ? $adTypeClicks / $totalClicks : 0;
                    $ratios['sales'] = $totalSales > 0 ? $adTypeSales / $totalSales : 0;
                    $ratios['sold'] = $totalSold > 0 ? $adTypeSold / $totalSold : 0;

                    // Map metric to ChannelMasterSummary field
                    $summaryFieldMap = [
                        'spend' => 'total_ad_spend',
                        'clicks' => 'clicks',
                        'sales' => 'ad_sales',
                        'sold' => 'ad_sold',
                    ];

                    // For spend, clicks, sales, sold — use ratio × historical total
                    // For acos, cvr — compute from ratio-derived spend/sales or sold/clicks
                    $inputStartDate = $request->input('start_date');
                    $chartEndDate = Carbon::today();
                    $chartStartDate = $inputStartDate ? Carbon::parse($inputStartDate) : $chartEndDate->copy()->subDays(30);

                    $history = \App\Models\ChannelMasterSummary::where('channel', $summaryChannel)
                        ->whereDate('snapshot_date', '>=', $chartStartDate->format('Y-m-d'))
                        ->whereDate('snapshot_date', '<=', $chartEndDate->format('Y-m-d'))
                        ->orderBy('snapshot_date', 'asc')
                        ->get(['snapshot_date', 'summary_data']);

                    if ($history->isNotEmpty()) {
                        // Check if the required summary field has sufficient data
                        // (some metrics like ad_sales/ad_sold were added recently and may be null for older dates)
                        $requiredFields = match($metric) {
                            'spend' => ['total_ad_spend'],
                            'clicks' => ['clicks'],
                            'sales' => ['ad_sales'],
                            'sold' => ['ad_sold'],
                            'acos' => ['total_ad_spend', 'ad_sales'],
                            'cvr' => ['ad_sold', 'clicks'],
                            default => [],
                        };

                        // Count how many rows have non-null/non-zero values for required fields
                        $validCount = 0;
                        foreach ($history as $row) {
                            $data = is_string($row->summary_data) ? json_decode($row->summary_data, true) : $row->summary_data;
                            $allFieldsPresent = true;
                            foreach ($requiredFields as $field) {
                                if (empty($data[$field]) || (float)($data[$field]) == 0) {
                                    $allFieldsPresent = false;
                                    break;
                                }
                            }
                            if ($allFieldsPresent) $validCount++;
                        }

                        // Only use ratio approach if at least 50% of rows have valid data
                        $hasEnoughData = $validCount >= (count($history) * 0.5);

                        if ($hasEnoughData) {
                            $chartData = [];
                            foreach ($history as $row) {
                                $data = is_string($row->summary_data) ? json_decode($row->summary_data, true) : $row->summary_data;

                                if (in_array($metric, ['spend', 'clicks', 'sales', 'sold'])) {
                                    $summaryField = $summaryFieldMap[$metric];
                                    $totalValue = (float) ($data[$summaryField] ?? 0);
                                    $ratio = $ratios[$metric] ?? 0;
                                    $value = round($totalValue * $ratio, 2);
                                } elseif ($metric === 'acos') {
                                    // ACOS = (Spend / Sales) * 100
                                    $histSpend = (float) ($data['total_ad_spend'] ?? 0);
                                    $histSales = (float) ($data['ad_sales'] ?? 0);
                                    $adSpend = $histSpend * ($ratios['spend'] ?? 0);
                                    $adSales = $histSales * ($ratios['sales'] ?? 0);
                                    $value = $adSales > 0 ? round(($adSpend / $adSales) * 100, 1) : 0;
                                } elseif ($metric === 'cvr') {
                                    // CVR = (Sold / Clicks) * 100
                                    $histSold = (float) ($data['ad_sold'] ?? 0);
                                    $histClicks = (float) ($data['clicks'] ?? 0);
                                    $adSold = $histSold * ($ratios['sold'] ?? 0);
                                    $adClicks = $histClicks * ($ratios['clicks'] ?? 0);
                                    $value = $adClicks > 0 ? round(($adSold / $adClicks) * 100, 1) : 0;
                                } else {
                                    $value = 0;
                                }

                                $chartData[] = [
                                    'date' => Carbon::parse($row->snapshot_date)->format('M d'),
                                    'value' => $value,
                                ];
                            }

                            // Scale ratio-based data to match marketplace_daily_metrics reference value
                            // (handles cases where ChannelMasterSummary total includes extra campaign types)
                            if (count($chartData) > 0 && in_array($metric, ['spend', 'clicks', 'sales', 'sold'])) {
                                $refValue = null;
                                if ($metric === 'spend') {
                                    $refValue = $adTypeSpend;
                                } elseif ($metric === 'clicks') {
                                    $refValue = $adTypeClicks;
                                } elseif ($metric === 'sales') {
                                    $refValue = $adTypeSales;
                                } elseif ($metric === 'sold') {
                                    $refValue = $adTypeSold;
                                }
                                if ($refValue !== null && $refValue > 0) {
                                    $lastVal = end($chartData)['value'];
                                    if ($lastVal > 0 && abs($lastVal - $refValue) > 0.5) {
                                        $sf = $refValue / $lastVal;
                                        foreach ($chartData as &$p) { $p['value'] = round($p['value'] * $sf, 2); }
                                        unset($p);
                                    }
                                }
                            } elseif (count($chartData) > 0 && $metric === 'acos') {
                                // Recalculate ACOS reference from live campaign data
                                $refAcos = $adTypeSales > 0 ? round(($adTypeSpend / $adTypeSales) * 100, 1) : 0;
                                if ($refAcos > 0) {
                                    $lastVal = end($chartData)['value'];
                                    if ($lastVal > 0 && abs($lastVal - $refAcos) > 0.1) {
                                        $sf = $refAcos / $lastVal;
                                        foreach ($chartData as &$p) { $p['value'] = round($p['value'] * $sf, 1); }
                                        unset($p);
                                    }
                                }
                            } elseif (count($chartData) > 0 && $metric === 'cvr') {
                                $refCvr = $adTypeClicks > 0 ? round(($adTypeSold / $adTypeClicks) * 100, 1) : 0;
                                if ($refCvr > 0) {
                                    $lastVal = end($chartData)['value'];
                                    if ($lastVal > 0 && abs($lastVal - $refCvr) > 0.1) {
                                        $sf = $refCvr / $lastVal;
                                        foreach ($chartData as &$p) { $p['value'] = round($p['value'] * $sf, 1); }
                                        unset($p);
                                    }
                                }
                            }

                            // Return ratio-based data (with interpolation)
                            if (count($chartData) > 0) {
                                $chartData = $this->interpolateChartData($chartData);
                                return response()->json([
                                    'success' => true,
                                    'channel' => $channel,
                                    'ad_type' => $adType,
                                    'metric' => $metric,
                                    'data' => $chartData
                                ]);
                            }
                        }
                        // If not enough data, fall through to rolling approach below
                    }
            }

            // ==================================================================================
            // FALLBACK: Rolling 30-day calculation from daily campaign reports
            // Used when ChannelMasterSummary or marketplace_daily_metrics data is unavailable
            // ==================================================================================
            // Get date filter from request or use defaults
            $inputStartDate = $request->input('start_date');
            $inputEndDate = $request->input('end_date');
            $isLifetime = empty($inputStartDate);
            
            // Rolling 30-day calculation: For each date, show sum of last 30 days
            $chartEndDate = $inputEndDate ? Carbon::parse($inputEndDate) : Carbon::today()->subDays(2);
            if (!$isLifetime) {
                $chartStartDate = Carbon::parse($inputStartDate);
            } else {
                // Lifetime placeholder — will be recalculated after data is fetched
                $chartStartDate = $chartEndDate->copy()->subYears(3);
            }
            // Need data from 30 days before chart start for rolling calc
            $dataStartDate = $chartStartDate->copy()->subDays(30);
            
            // Fetch ALL daily data for extended range
            $dailyData = [];
            
            // Determine column to sum based on metric
            // Use 7-day attribution (sales7d, purchases7d) to match table and Amazon dashboard
            $spendCol = 'spend';
            $clicksCol = 'clicks';
            $salesCol = 'sales7d';
            $soldCol = 'purchases7d';

            // Fetch daily data based on channel and ad type
            if ($channel === 'amazon' || $channel === 'amazonfba') {
                $isFba = $channel === 'amazonfba';
                
                if ($adType === 'kw') {
                    $query = DB::table('amazon_sp_campaign_reports')
                        ->whereNotNull('report_date_range')
                        ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                    
                    if ($isFba) {
                        $query->whereRaw("campaignName LIKE '%FBA'")->whereRaw("campaignName NOT LIKE '%FBA PT%'")->whereRaw("campaignName NOT LIKE '%FBA PT.%'");
                    } else {
                        $query->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'");
                    }
                    
                    $valueCol = match($metric) { 'clicks' => $clicksCol, 'sales' => $salesCol, 'sold', 'cvr' => $soldCol, default => $spendCol };
                    $rows = $query->selectRaw("report_date_range as date, SUM({$valueCol}) as val")->groupBy('report_date_range')->get();
                    
                } elseif ($adType === 'pt') {
                    $query = DB::table('amazon_sp_campaign_reports')
                        ->whereNotNull('report_date_range')
                        ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                    
                    if ($isFba) {
                        $query->where(fn($q) => $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'"));
                    } else {
                        $query->where(fn($q) => $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'"))
                              ->whereRaw("campaignName NOT LIKE '%FBA PT%'")->whereRaw("campaignName NOT LIKE '%FBA PT.%'");
                    }
                    
                    $valueCol = match($metric) { 'clicks' => $clicksCol, 'sales' => $salesCol, 'sold', 'cvr' => $soldCol, default => $spendCol };
                    $rows = $query->selectRaw("report_date_range as date, SUM({$valueCol}) as val")->groupBy('report_date_range')->get();
                    
                } elseif ($adType === 'hl' && !$isFba) {
                    $valueCol = match($metric) { 'clicks' => 'clicks', 'sales' => 'sales', 'sold', 'cvr' => 'purchases', default => 'cost' };
                    $rows = DB::table('amazon_sb_campaign_reports')
                        ->whereNotNull('report_date_range')
                        ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->selectRaw("report_date_range as date, SUM({$valueCol}) as val")
                        ->groupBy('report_date_range')
                        ->get();
                } else {
                    $rows = collect();
                }
                
            } elseif (in_array($channel, ['ebay', 'ebaytwo', 'ebaythree'])) {
                $kwTable = match ($channel) { 'ebay' => 'ebay_priority_reports', 'ebaytwo' => 'ebay_2_priority_reports', 'ebaythree' => 'ebay_3_priority_reports', default => null };
                $pmtTable = match ($channel) { 'ebay' => 'ebay_general_reports', 'ebaytwo' => 'ebay_2_general_reports', 'ebaythree' => 'ebay_3_general_reports', default => null };
                
                $excludeRanges = ['L90', 'L60', 'L30', 'L15', 'L7', 'L1'];
                
                if ($adType === 'kw' && $kwTable) {
                    $valueCol = match($metric) {
                        'clicks' => 'cpc_clicks',
                        'sales' => 'REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")',
                        'sold', 'cvr' => 'cpc_attributed_sales',
                        default => 'REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")'
                    };
                    $rows = DB::table($kwTable)
                        ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_range', $excludeRanges)
                        ->selectRaw("report_range as date, SUM({$valueCol}) as val")
                        ->groupBy('report_range')
                        ->get();
                } elseif ($adType === 'pmt' && $pmtTable) {
                    $valueCol = match($metric) {
                        'clicks' => 'clicks',
                        'sales' => 'REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")',
                        'sold', 'cvr' => 'sales',
                        default => 'REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")'
                    };
                    $rows = DB::table($pmtTable)
                        ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_range', $excludeRanges)
                        ->selectRaw("report_range as date, SUM({$valueCol}) as val")
                        ->groupBy('report_range')
                        ->get();
                } else {
                    $rows = collect();
                }
                
            } elseif ($channel === 'shopifyb2c' && in_array($adType, ['shopping', 'serp'])) {
                $channelType = $adType === 'shopping' ? 'SHOPPING' : 'SEARCH';
                $statusFilter = $adType === 'shopping' ? ['ENABLED'] : ['ENABLED', 'PAUSED'];
                
                $valueCol = match($metric) {
                    'clicks' => 'metrics_clicks',
                    'sales' => 'ga4_ad_sales',
                    'sold', 'cvr' => 'ga4_sold_units',
                    default => 'metrics_cost_micros'
                };
                
                $rows = DB::table('google_ads_campaigns')
                    ->whereDate('date', '>=', $dataStartDate->format('Y-m-d'))
                    ->whereDate('date', '<=', $chartEndDate->format('Y-m-d'))
                    ->where('advertising_channel_type', $channelType)
                    ->whereIn('campaign_status', $statusFilter)
                    ->selectRaw("date, SUM({$valueCol}) as val")
                    ->groupBy('date')
                    ->get();
                
                // Convert micros to dollars for spend and acos (both use metrics_cost_micros)
                if ($metric === 'spend' || $metric === 'acos') {
                    $rows = $rows->map(function($row) {
                        $row->val = $row->val / 1000000;
                        return $row;
                    });
                }
            } else {
                $rows = collect();
            }

            // Build daily data lookup
            foreach ($rows as $row) {
                $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                $dailyData[$dateKey] = (float) $row->val;
            }

            // For ACOS and CVR, we need additional data
            $dailyData2 = [];
            if (in_array($metric, ['acos', 'cvr'])) {
                // ACOS needs spend and sales, CVR needs sold and clicks
                // We already have one metric, fetch the other
                $metric2 = $metric === 'acos' ? 'sales' : 'clicks';
                $rows2 = collect();
                
                // Re-fetch with the second metric
                if ($channel === 'amazon' || $channel === 'amazonfba') {
                    $isFba = $channel === 'amazonfba';
                    $valueCol2 = $metric === 'acos' ? $salesCol : $clicksCol;
                    
                    if ($adType === 'kw') {
                        $query2 = DB::table('amazon_sp_campaign_reports')
                            ->whereNotNull('report_date_range')
                            ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                        if ($isFba) {
                            $query2->whereRaw("campaignName LIKE '%FBA'")->whereRaw("campaignName NOT LIKE '%FBA PT%'");
                        } else {
                            $query2->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'");
                        }
                        $rows2 = $query2->selectRaw("report_date_range as date, SUM({$valueCol2}) as val")->groupBy('report_date_range')->get();
                    } elseif ($adType === 'pt') {
                        $query2 = DB::table('amazon_sp_campaign_reports')
                            ->whereNotNull('report_date_range')
                            ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                        if ($isFba) {
                            $query2->where(fn($q) => $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'"));
                        } else {
                            $query2->where(fn($q) => $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'"))
                                  ->whereRaw("campaignName NOT LIKE '%FBA PT%'");
                        }
                        $rows2 = $query2->selectRaw("report_date_range as date, SUM({$valueCol2}) as val")->groupBy('report_date_range')->get();
                    } elseif ($adType === 'hl' && !$isFba) {
                        $valueCol2 = $metric === 'acos' ? 'sales' : 'clicks';
                        $rows2 = DB::table('amazon_sb_campaign_reports')
                            ->whereNotNull('report_date_range')
                            ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                            ->selectRaw("report_date_range as date, SUM({$valueCol2}) as val")
                            ->groupBy('report_date_range')
                            ->get();
                    }
                } elseif (in_array($channel, ['ebay', 'ebaytwo', 'ebaythree'])) {
                    $kwTable = match ($channel) { 'ebay' => 'ebay_priority_reports', 'ebaytwo' => 'ebay_2_priority_reports', 'ebaythree' => 'ebay_3_priority_reports', default => null };
                    $pmtTable = match ($channel) { 'ebay' => 'ebay_general_reports', 'ebaytwo' => 'ebay_2_general_reports', 'ebaythree' => 'ebay_3_general_reports', default => null };
                    $excludeRanges = ['L90', 'L60', 'L30', 'L15', 'L7', 'L1'];
                    
                    if ($adType === 'kw' && $kwTable) {
                        $valueCol2 = $metric === 'acos' 
                            ? 'REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")' 
                            : 'cpc_clicks';
                        $rows2 = DB::table($kwTable)
                            ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_range', $excludeRanges)
                            ->selectRaw("report_range as date, SUM({$valueCol2}) as val")
                            ->groupBy('report_range')
                            ->get();
                    } elseif ($adType === 'pmt' && $pmtTable) {
                        $valueCol2 = $metric === 'acos' 
                            ? 'REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")' 
                            : 'clicks';
                        $rows2 = DB::table($pmtTable)
                            ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_range', $excludeRanges)
                            ->selectRaw("report_range as date, SUM({$valueCol2}) as val")
                            ->groupBy('report_range')
                            ->get();
                    }
                } elseif ($channel === 'shopifyb2c' && in_array($adType, ['shopping', 'serp'])) {
                    $channelType = $adType === 'shopping' ? 'SHOPPING' : 'SEARCH';
                    $statusFilter = $adType === 'shopping' ? ['ENABLED'] : ['ENABLED', 'PAUSED'];
                    $valueCol2 = $metric === 'acos' ? 'ga4_ad_sales' : 'metrics_clicks';
                    
                    $rows2 = DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $dataStartDate->format('Y-m-d'))
                        ->whereDate('date', '<=', $chartEndDate->format('Y-m-d'))
                        ->where('advertising_channel_type', $channelType)
                        ->whereIn('campaign_status', $statusFilter)
                        ->selectRaw("date, SUM({$valueCol2}) as val")
                        ->groupBy('date')
                        ->get();
                }
                
                foreach ($rows2 as $row) {
                    $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                    $dailyData2[$dateKey] = (float) $row->val;
                }
            }

            // For Lifetime: set chart start to the earliest date with actual data
            if ($isLifetime) {
                $allDates = array_merge(array_keys($dailyData), array_keys($dailyData2 ?? []));
                if (!empty($allDates)) {
                    sort($allDates);
                    $chartStartDate = Carbon::parse($allDates[0]);
                } else {
                    $chartStartDate = $chartEndDate->copy();
                }
            }

            // Calculate rolling 30-day values for each chart date
            $chartData = [];
            $currentDate = $chartStartDate->copy();
            while ($currentDate <= $chartEndDate) {
                $rolling30Sum = 0;
                $rolling30Sum2 = 0;
                
                // Sum last 30 days including current date
                for ($i = 0; $i < 30; $i++) {
                    $lookupDate = $currentDate->copy()->subDays($i)->format('Y-m-d');
                    if (isset($dailyData[$lookupDate])) {
                        $rolling30Sum += $dailyData[$lookupDate];
                    }
                    if (isset($dailyData2[$lookupDate])) {
                        $rolling30Sum2 += $dailyData2[$lookupDate];
                    }
                }
                
                // Calculate final value based on metric type
                if ($metric === 'acos') {
                    // ACOS = (Spend / Sales) * 100
                    // dailyData has spend, dailyData2 has sales
                    $value = $rolling30Sum2 > 0 ? round(($rolling30Sum / $rolling30Sum2) * 100, 1) : 0;
                } elseif ($metric === 'cvr') {
                    // CVR = (Sold / Clicks) * 100
                    // dailyData has sold, dailyData2 has clicks
                    $value = $rolling30Sum2 > 0 ? round(($rolling30Sum / $rolling30Sum2) * 100, 1) : 0;
                } else {
                    $value = round($rolling30Sum, 2);
                }
                
                $chartData[] = [
                    'date' => $currentDate->format('M d'),
                    'value' => $value
                ];
                $currentDate->addDay();
            }

            // ==================================================================================
            // SCALE rolling data so the latest value matches the table (live campaign data)
            // This preserves the trend shape while ensuring absolute values are correct
            // Uses the same fetchAdMetricsFromTables() source as the table display
            // ==================================================================================
            if (!empty($chartData) && ($adTypeSpend > 0 || $adTypeClicks > 0 || $adTypeSales > 0)) {
                    $correctValue = null;
                    if ($metric === 'spend') {
                        $correctValue = $adTypeSpend;
                    } elseif ($metric === 'clicks') {
                        $correctValue = $adTypeClicks;
                    } elseif ($metric === 'sales') {
                        $correctValue = $adTypeSales;
                    } elseif ($metric === 'sold') {
                        $correctValue = $adTypeSold;
                    } elseif ($metric === 'acos') {
                        $correctValue = $adTypeSales > 0 ? round(($adTypeSpend / $adTypeSales) * 100, 1) : null;
                    } elseif ($metric === 'cvr') {
                        $correctValue = $adTypeClicks > 0 ? round(($adTypeSold / $adTypeClicks) * 100, 1) : null;
                    }

                    // Apply scale factor if we have a valid reference value
                    if ($correctValue !== null && $correctValue > 0) {
                        $rollingLatest = end($chartData)['value'];
                        if ($rollingLatest > 0 && abs($rollingLatest - $correctValue) > 0.01) {
                            $scaleFactor = $correctValue / $rollingLatest;
                            foreach ($chartData as &$point) {
                                $point['value'] = round($point['value'] * $scaleFactor, 2);
                            }
                            unset($point);
                        }
                    }
            }

            // Smooth out consecutive duplicate values
            $chartData = $this->interpolateChartData($chartData);

            return response()->json([
                'success' => true,
                'channel' => $channel,
                'ad_type' => $adType,
                'metric' => $metric,
                'data' => $chartData
            ]);

        } catch (\Exception $e) {
            \Log::error('getAdBreakdownChartData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Archive a channel (soft delete by setting status to 'Inactive')
     */
    public function archiveChannel(Request $request)
    {
        try {
            $channelName = $request->input('channel');
            
            if (!$channelName) {
                return response()->json(['success' => false, 'message' => 'Channel name is required'], 400);
            }

            // Find the channel
            $channel = ChannelMaster::where('channel', $channelName)->first();
            
            if (!$channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
            }

            // Archive by setting status to Inactive
            $channel->status = 'Inactive';
            $channel->save();

            \Log::info('Channel archived: ' . $channelName);

            return response()->json([
                'success' => true, 
                'message' => 'Channel archived successfully',
                'channel' => $channelName
            ]);

        } catch (\Exception $e) {
            \Log::error('Archive channel error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error archiving channel: ' . $e->getMessage()], 500);
        }
    }

}
