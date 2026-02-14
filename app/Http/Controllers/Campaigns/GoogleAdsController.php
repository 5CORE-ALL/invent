<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\GoogleDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use App\Models\Shopifyb2cDataView;
use Illuminate\Http\Request;
use App\Services\GoogleAdsSbidService;
use App\Services\GA4ApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoogleAdsController extends Controller
{
    use GoogleAdsDateRangeTrait;
    
    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }


    public function index(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($data[$date])) {
                $clicks[] = (int) $data[$date]->clicks;
                $spend[] = (float) $data[$date]->spend;
                $orders[] = (int) $data[$date]->orders;
                $sales[] = (float) $data[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return view('campaign.google-shopping-ads', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleShoppingAdsRunning(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->where('campaign_status', 'ENABLED')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($data[$date])) {
                $clicks[] = (int) $data[$date]->clicks;
                $spend[] = (float) $data[$date]->spend;
                $orders[] = (int) $data[$date]->orders;
                $sales[] = (float) $data[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return view('campaign.google-shopping-ads-running', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleShoppingUtilizedView(){
        // Match Google Ads/GA4 range: last 30 days ending 2 days ago (e.g. Jan 5 – Feb 3) so we have complete data
        $endDate = \Carbon\Carbon::now()->subDays(2)->format('Y-m-d');
        $startDate = \Carbon\Carbon::now()->subDays(31)->format('Y-m-d');

        // Spend & Clicks: ENABLED + PAUSED SHOPPING (exclude ARCHIVED only)
        $dataSpend = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks,
                SUM(metrics_cost_micros) / 1000000 as spend
            ')
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Sales & Orders: ENABLED + PAUSED SHOPPING (exclude ARCHIVED). Prefer GA4 actual; fallback to Google Ads
        $dataSales = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(ga4_actual_revenue) as ga4_sales,
                SUM(ga4_actual_sold_units) as ga4_orders,
                SUM(ga4_ad_sales) as ad_sales,
                SUM(ga4_sold_units) as ad_orders
            ')
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $useGA4 = $dataSales->sum('ga4_sales') > 0 || $dataSales->sum('ga4_orders') > 0;

        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->format('Y-m-d');
            $dates[] = $date;

            $rowSpend = $dataSpend[$date] ?? null;
            $rowSales = $dataSales[$date] ?? null;

            $clicks[] = $rowSpend ? (int) $rowSpend->clicks : 0;
            $spend[] = $rowSpend ? (float) $rowSpend->spend : 0.0;

            if ($rowSales) {
                $orders[] = (int) ($useGA4 ? $rowSales->ga4_orders : $rowSales->ad_orders);
                $sales[] = (float) ($useGA4 ? $rowSales->ga4_sales : $rowSales->ad_sales);
            } else {
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        // If GA4 total is available for this range, scale sales/orders so card matches GA4 report
        $ga4Total = app(GA4ApiService::class)->getTotalPurchaseMetrics($startDate, $endDate);
        if ($ga4Total && ($ga4Total['revenue'] > 0 || $ga4Total['purchases'] > 0)) {
            $salesSum = array_sum($sales);
            $ordersSum = array_sum($orders);
            if ($salesSum > 0 && $ga4Total['revenue'] > 0) {
                $ratio = $ga4Total['revenue'] / $salesSum;
                $sales = array_map(fn($v) => round($v * $ratio, 2), $sales);
            }
            if ($ordersSum > 0 && $ga4Total['purchases'] > 0) {
                $ratio = $ga4Total['purchases'] / $ordersSum;
                $orders = array_map(fn($v) => (int) round($v * $ratio), $orders);
            }
        }

        return view('campaign.google-shopping-utilized', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleShoppingAdsReport(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($data[$date])) {
                $clicks[] = (int) $data[$date]->clicks;
                $spend[] = (float) $data[$date]->spend;
                $orders[] = (int) $data[$date]->orders;
                $sales[] = (float) $data[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return view('campaign.google.google-shopping-ads-report', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function getAdvShopifyGShoppingSaveData(Request $request)
    {
        return ADVMastersData::getAdvShopifyGShoppingSaveDataProceed($request);
    }

    public function googleSerpView(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->where('advertising_channel_type', 'SEARCH')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($data[$date])) {
                $clicks[] = (int) $data[$date]->clicks;
                $spend[] = (float) $data[$date]->spend;
                $orders[] = (int) $data[$date]->orders;
                $sales[] = (float) $data[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return view('campaign.google-shopping-ads-serp', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleSerpReportView(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->where('advertising_channel_type', 'SEARCH')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($data[$date])) {
                $clicks[] = (int) $data[$date]->clicks;
                $spend[] = (float) $data[$date]->spend;
                $orders[] = (int) $data[$date]->orders;
                $sales[] = (float) $data[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return view('campaign.google.google-serp-ads-report', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googlePmaxView(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->where('advertising_channel_type', 'PERFORMANCE_MAX')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($data[$date])) {
                $clicks[] = (int) $data[$date]->clicks;
                $spend[] = (float) $data[$date]->spend;
                $orders[] = (int) $data[$date]->orders;
                $sales[] = (float) $data[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return view('campaign.google-shopping-ads-pmax', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function getGoogleSearchAdsData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Calculate date ranges
        $dateRanges = $this->calculateDateRanges();
        $rangesNeeded = ['L1', 'L7', 'L30'];
        
        // Only fetch SEARCH campaigns for SERP page
        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->where('advertising_channel_type', 'SEARCH')
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;
            
            // Fixed: Use original SKU for shopifyData lookup (not uppercase)
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Fixed: Use improved matching logic for SEARCH campaigns
            // SEARCH campaigns end with " SEARCH." so we need special handling
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                // Check if campaign ends with ' SEARCH.'
                if (!str_ends_with($campaign, ' SEARCH.')) {
                    return false;
                }
                
                // Remove ' SEARCH.' suffix for matching
                $campaignBase = str_replace(' SEARCH.', '', $campaign);
                
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaignBase));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign base exactly equals SKU
                if (!$exactMatch) {
                    $exactMatch = $campaignBase === $skuTrimmed;
                }
                
                return $exactMatch && $c->campaign_status === 'ENABLED';
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            
            // Fixed: Add null checks before accessing properties to prevent null pointer exceptions
            $row['campaign_id'] = $matchedCampaign ? ($matchedCampaign->campaign_id ?? null) : null;
            $row['campaignName'] = $matchedCampaign ? ($matchedCampaign->campaign_name ?? null) : null;
            $row['campaignBudgetAmount'] = $matchedCampaign ? ($matchedCampaign->budget_amount_micros ?? null) : null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['status'] = $matchedCampaign ? ($matchedCampaign->campaign_status ?? null) : null;

            // Aggregate metrics for each date range
            // Note: $googleCampaigns already filtered to SEARCH campaigns only
            foreach ($rangesNeeded as $rangeName) {
                $metrics = $this->aggregateMetricsByRange(
                    $googleCampaigns, 
                    $sku, 
                    $dateRanges[$rangeName], 
                    'ENABLED'
                );
                
                $row["spend_$rangeName"] = $metrics['spend'];
                $row["clicks_$rangeName"] = $metrics['clicks'];
                $row["impressions_$rangeName"] = $metrics['impressions'];
                $row["cpc_$rangeName"] = $metrics['cpc'];
                $row["ad_sales_$rangeName"] = $metrics['ad_sales'];
                $row["ad_sold_$rangeName"] = $metrics['ad_sold'];
            }

            // Fixed: Use !empty() instead of != '' to properly handle null values
            if(!empty($row['campaignName'])) {
                $result[] = (object) $row;
            }
        }
        
        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function getGoogleSearchAdsReportData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Calculate date ranges
        $dateRanges = $this->calculateDateRanges();
        $rangesNeeded = ['L7', 'L15', 'L30', 'L60'];

        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereBetween('date', [$dateRanges['L60']['start'], $dateRanges['L60']['end']])
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            // Fixed: Use original SKU for shopifyData lookup (not uppercase)
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaign = $googleCampaigns->filter(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                if (!str_ends_with($campaign, 'SEARCH.')) {
                    return false;
                }
                
                $contains = strpos($campaign, $skuTrimmed) !== false;
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                return ($contains || $exactMatch) && $c->campaign_status === 'ENABLED';
            })->sortByDesc('date')->first();

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            
            // Fixed: Add null checks before accessing properties to prevent null pointer exceptions
            $row['campaign_id'] = $matchedCampaign ? ($matchedCampaign->campaign_id ?? null) : null;
            $row['campaignName'] = $matchedCampaign ? ($matchedCampaign->campaign_name ?? null) : null;
            $row['campaignBudgetAmount'] = $matchedCampaign ? ($matchedCampaign->budget_amount_micros ?? null) : null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['campaignStatus'] = $matchedCampaign ? ($matchedCampaign->campaign_status ?? null) : null;

            foreach ($rangesNeeded as $rangeName) {
                $metrics = $this->aggregateMetricsByRange(
                    $googleCampaigns, 
                    $sku, 
                    $dateRanges[$rangeName], 
                    'ENABLED'
                );
                
                $row["spend_$rangeName"] = $metrics['spend'];
                $row["clicks_$rangeName"] = $metrics['clicks'];
                $row["impressions_$rangeName"] = $metrics['impressions'];
                $row["cpc_$rangeName"] = $metrics['cpc'];
                $row["ad_sales_$rangeName"] = $metrics['ad_sales'];
                $row["ad_sold_$rangeName"] = $metrics['ad_sold'];
            }

            // Fixed: Use !empty() instead of != '' to properly handle null values
            if(!empty($row['campaignName'])) {
                $result[] = (object) $row;
            }

        }
        
        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function getGoogleShoppingAdsData(){

        // Get all product masters excluding soft deleted ones (similar to Amazon)
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Count total SKUs (non-parent SKUs only, similar to Amazon KW/PT)
        $totalSkuCount = ProductMaster::whereNull('deleted_at')
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->count();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Get NRL, NRA from GoogleDataView (similar to AmazonDataView)
        $nrValues = GoogleDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        
        // Get GPFT, PFT, ROI, SPRICE, SPFT from shopify-b2c-pricing data
        // Fetch pricing data using Shopifyb2cController method
        $shopifyB2cController = new \App\Http\Controllers\MarketPlace\Shopifyb2cController(new \App\Http\Controllers\ApiController());
        $shopifyB2cPricingData = $shopifyB2cController->getViewShopifyB2cTabularData();
        $shopifyB2cPricingMap = [];
        foreach ($shopifyB2cPricingData as $item) {
            $itemSku = $item['(Child) sku'] ?? null;
            if ($itemSku) {
                $shopifyB2cPricingMap[$itemSku] = $item;
            }
        }
        
        // Also get SPRICE, SPFT from shopifyb2c_data_view (for SPFT)
        $shopifyB2cData = Shopifyb2cDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // Calculate date ranges
        $dateRanges = $this->calculateDateRanges();
        $rangesNeeded = ['L1', 'L7', 'L30'];

        // Only fetch SHOPPING campaigns for Shopping Ads (L30 date range for metrics)
        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'mbid',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales',
                'ga4_actual_sold_units',
                'ga4_actual_revenue'
            )
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        // Fetch ALL SHOPPING campaigns (latest row per campaign, no date filter)
        // so parent campaigns without L30 data can still be matched
        $allCampaignsLatest = DB::table('google_ads_campaigns')
            ->select('campaign_id', 'campaign_name', 'campaign_status', 'budget_amount_micros', 'mbid', 'date')
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->orderBy('date', 'desc')
            ->get()
            ->unique('campaign_id');

        $result = [];
        $campaignMap = [];

        // Process each SKU (similar to Amazon - loop through SKUs, not campaigns)
        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;
            $imageSrc = ($shopify && isset($shopify->image_src)) ? $shopify->image_src : '';

            // Include parent SKUs - also match their own campaign from DB
            if (stripos($sku, 'PARENT') !== false) {
                $mapKey = 'PARENT_' . $pm->sku;

                // Match campaign for parent SKU using ALL campaigns (no date range limit)
                $matchedParentCampaign = $allCampaignsLatest->first(function ($c) use ($sku) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $campaignCleaned = rtrim(trim($campaign), '.');
                    $skuCleaned = rtrim(trim($sku), '.');
                    $parts = array_map(function($part) { return rtrim(trim($part), '.'); }, array_map('trim', explode(',', $campaignCleaned)));
                    return in_array($skuCleaned, $parts) || $campaignCleaned === $skuCleaned;
                });

                $parentHasCampaign = !empty($matchedParentCampaign);
                $parentCampaignId = $matchedParentCampaign ? $matchedParentCampaign->campaign_id : null;
                $parentCampaignName = $matchedParentCampaign ? $matchedParentCampaign->campaign_name : null;

                // BGT and mbid from the matched row (already latest)
                $parentBudget = 0;
                $parentMbid = null;
                if ($matchedParentCampaign) {
                    if ($matchedParentCampaign->budget_amount_micros) {
                        $parentBudget = $matchedParentCampaign->budget_amount_micros / 1000000;
                    }
                    if (isset($matchedParentCampaign->mbid) && $matchedParentCampaign->mbid !== null) {
                        $parentMbid = (float) $matchedParentCampaign->mbid;
                    }
                }

                $campaignMap[$mapKey] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'is_parent' => true,
                    'image_src' => $imageSrc,
                    'INV' => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                    'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                    'campaign_id' => $parentCampaignId,
                    'campaignName' => $parentCampaignName,
                    'campaignBudgetAmount' => $parentBudget,
                    'mbid' => $parentMbid,
                    'status' => $matchedParentCampaign ? $matchedParentCampaign->campaign_status : null,
                    'campaignStatus' => $matchedParentCampaign ? $matchedParentCampaign->campaign_status : null,
                    'price' => ($shopify && isset($shopify->price)) ? (float)$shopify->price : 0,
                    'NRL' => '',
                    'NRA' => '',
                    'hasCampaign' => $parentHasCampaign,
                    'GPFT' => null, 'PFT' => null, 'roi' => null,
                    'SPRICE' => null, 'SPFT' => null,
                    'spend_L1' => 0, 'spend_L7' => 0, 'spend_L30' => 0,
                    'clicks_L1' => 0, 'clicks_L7' => 0, 'clicks_L30' => 0,
                    'cpc_L1' => 0, 'cpc_L7' => 0,
                    'ad_sales_L1' => 0, 'ad_sales_L7' => 0, 'ad_sales_L30' => 0,
                    'ad_sold_L1' => 0, 'ad_sold_L7' => 0, 'ad_sold_L30' => 0,
                    'sbid' => 0, 'ub7' => 0, 'ub1' => 0,
                ];

                // Calculate metrics for matched parent campaign (match by SKU/campaign name)
                if ($parentHasCampaign) {
                    $skuForMetrics = strtoupper(trim($pm->sku));
                    foreach ($rangesNeeded as $rangeName) {
                        $metrics = $this->aggregateMetricsByRange(
                            $googleCampaigns, $skuForMetrics, $dateRanges[$rangeName], null
                        );
                        $campaignMap[$mapKey]["spend_$rangeName"] = $metrics['spend'];
                        $campaignMap[$mapKey]["clicks_$rangeName"] = $metrics['clicks'];
                        $campaignMap[$mapKey]["cpc_$rangeName"] = $metrics['cpc'];
                        $campaignMap[$mapKey]["ad_sales_$rangeName"] = $metrics['ad_sales'];
                        $campaignMap[$mapKey]["ad_sold_$rangeName"] = $metrics['ad_sold'];
                    }

                    // SBID calculation
                    $budget = $parentBudget;
                    $spend_L7 = $campaignMap[$mapKey]['spend_L7'] ?? 0;
                    $spend_L1 = $campaignMap[$mapKey]['spend_L1'] ?? 0;
                    $cpc_L7 = $campaignMap[$mapKey]['cpc_L7'] ?? 0;
                    $cpc_L1 = $campaignMap[$mapKey]['cpc_L1'] ?? 0;
                    $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;
                    $ub1 = $budget > 0 ? ($spend_L1 / $budget) * 100 : 0;
                    $sbid = 0;
                    if ($ub7 > 99 && $ub1 > 99) {
                        $sbid = $cpc_L7 == 0 ? 0.75 : floor($cpc_L7 * 0.90 * 100) / 100;
                    } elseif ($ub7 < 66 && $ub1 < 66) {
                        if ($cpc_L1 == 0 && $cpc_L7 == 0) $sbid = 0.75;
                        elseif ($ub7 < 10 || $cpc_L7 == 0) $sbid = 0.75;
                        elseif ($cpc_L7 > 0 && $cpc_L7 < 0.30) $sbid = round($cpc_L7 + 0.20, 2);
                        else $sbid = floor($cpc_L7 * 1.10 * 100) / 100;
                    }
                    $campaignMap[$mapKey]['sbid'] = $sbid;
                    $campaignMap[$mapKey]['ub7'] = $ub7;
                    $campaignMap[$mapKey]['ub1'] = $ub1;
                }

                continue;
            }

            // Find matching campaign for this SKU
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $campaignCleaned = rtrim(trim($campaign), '.');
                $skuTrimmed = strtoupper(trim($sku));
                $skuCleaned = rtrim(trim($skuTrimmed), '.'); // Remove period from SKU too
                
                $parts = array_map('trim', explode(',', $campaignCleaned));
                $parts = array_map(function($part) {
                    return rtrim(trim($part), '.');
                }, $parts);
                $exactMatch = in_array($skuCleaned, $parts);
                
                if (!$exactMatch) {
                    $exactMatch = $campaignCleaned === $skuCleaned;
                }
                
                return $exactMatch;
            });

            // Check if campaign exists
            $hasCampaign = !empty($matchedCampaign);
            
            $campaignId = $matchedCampaign ? $matchedCampaign->campaign_id : null;
            $campaignName = $matchedCampaign ? $matchedCampaign->campaign_name : null;
            
            // Get NRL, NRA from GoogleDataView
            $nra = '';
            $nrl = 'REQ'; // Default value
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $nrl = $raw['NRL'] ?? 'REQ';
                }
            }
            
            // Get GPFT, PFT, ROI, SPRICE, SPFT from shopify-b2c-pricing data
            $gpft = null;
            $pft = null;
            $roi = null;
            $sprice = null;
            $spft = null;
            
            // Get from shopify-b2c-pricing route data
            if (isset($shopifyB2cPricingMap[$pm->sku])) {
                $pricingItem = $shopifyB2cPricingMap[$pm->sku];
                // GPFT% from pricing data (convert to GPFT)
                $gpft = isset($pricingItem['GPFT%']) ? floatval($pricingItem['GPFT%']) : null;
                // ROI% from pricing data (convert to ROI)
                $roi = isset($pricingItem['ROI%']) ? floatval($pricingItem['ROI%']) : null;
                // PFT = GPFT - ADS% (if ADS% available)
                $adsPercent = isset($pricingItem['ADS%']) ? floatval($pricingItem['ADS%']) : 0;
                $pft = $gpft !== null ? ($gpft - $adsPercent) : null;
                // SPRICE from pricing data
                $sprice = isset($pricingItem['SPRICE']) ? floatval($pricingItem['SPRICE']) : null;
            }
            
            // Get SPFT from shopifyb2c_data_view (SGPFT)
            if (isset($shopifyB2cData[$pm->sku])) {
                $b2cData = $shopifyB2cData[$pm->sku];
                $b2cValues = is_array($b2cData->value) 
                    ? $b2cData->value 
                    : (json_decode($b2cData->value, true) ?: []);
                
                // SPFT = SGPFT (from shopifyb2c_data_view)
                $spft = isset($b2cValues['SGPFT']) ? floatval($b2cValues['SGPFT']) : ($spft ?? null);
                // If SPRICE not found in pricing data, get from shopifyb2c_data_view
                if ($sprice === null) {
                    $sprice = isset($b2cValues['SPRICE']) ? floatval($b2cValues['SPRICE']) : null;
                }
            }

            // Note: Include NRA items in data so they can be counted and filtered in frontend
            // Frontend will handle filtering/hiding NRA items based on user selection

            // Use SKU as key (since we're looping by SKUs, not campaigns)
            $mapKey = 'SKU_' . $pm->sku;

            // BGT and mbid (manual bid) from latest-by-date row only
            $initialBudget = 0;
            $mbid = null;
            if ($campaignId) {
                $latestForBgt = $googleCampaigns->where('campaign_id', $campaignId)->sortByDesc('date')->first();
                if ($latestForBgt) {
                    if ($latestForBgt->budget_amount_micros) {
                        $initialBudget = $latestForBgt->budget_amount_micros / 1000000;
                    }
                    if (isset($latestForBgt->mbid) && $latestForBgt->mbid !== null) {
                        $mbid = (float) $latestForBgt->mbid;
                    }
                }
            }

            if (!isset($campaignMap[$mapKey])) {
                $campaignMap[$mapKey] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'is_parent' => false,
                    'image_src' => $imageSrc,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => $initialBudget,
                    'mbid' => $mbid,
                    'status' => $matchedCampaign ? $matchedCampaign->campaign_status : null,
                    'campaignStatus' => $matchedCampaign ? $matchedCampaign->campaign_status : null,
                    'price' => ($shopify && isset($shopify->price)) ? (float)$shopify->price : 0,
                    'INV' => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                    'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                    'NRL' => $nrl,
                    'NRA' => $nra,
                    'hasCampaign' => $hasCampaign,
                    'GPFT' => $gpft,
                    'PFT' => $pft,
                    'roi' => $roi,
                    'SPRICE' => $sprice,
                    'SPFT' => $spft,
                    'GPFT' => $gpft,
                    'PFT' => $pft,
                    'roi' => $roi,
                    'SPRICE' => $sprice,
                    'SPFT' => $spft,
                    'spend_L1' => 0,
                    'spend_L7' => 0,
                    'spend_L30' => 0,
                    'clicks_L1' => 0,
                    'clicks_L7' => 0,
                    'clicks_L30' => 0,
                    'cpc_L1' => 0,
                    'cpc_L7' => 0,
                    'ad_sales_L1' => 0,
                    'ad_sales_L7' => 0,
                    'ad_sales_L30' => 0,
                    'ad_sold_L1' => 0,
                    'ad_sold_L7' => 0,
                    'ad_sold_L30' => 0,
                ];
            }

            // Calculate metrics for matched campaign
            if ($matchedCampaign && $hasCampaign) {
                $skuForMetrics = strtoupper(trim($pm->sku));
                
                foreach ($rangesNeeded as $rangeName) {
                    // null = include all statuses (ENABLED + PAUSED) for historical metrics;
                    // PAUSED campaigns can have non-zero clicks/spend/sales in the date range
                    $metrics = $this->aggregateMetricsByRange(
                        $googleCampaigns, 
                        $skuForMetrics, 
                        $dateRanges[$rangeName], 
                        null
                    );
                    
                    $campaignMap[$mapKey]["spend_$rangeName"] = $metrics['spend'];
                    $campaignMap[$mapKey]["clicks_$rangeName"] = $metrics['clicks'];
                    $campaignMap[$mapKey]["cpc_$rangeName"] = $metrics['cpc'];
                    $campaignMap[$mapKey]["ad_sales_$rangeName"] = $metrics['ad_sales'];
                    $campaignMap[$mapKey]["ad_sold_$rangeName"] = $metrics['ad_sold'];
                }
                
                // targetBudget = dollar value from ACOS (same as budget:update-shopping); when it differs
                // from BGT, the cron will update Google; BGT column can show "2 → 1" until Fetch runs
                $spendL30 = $campaignMap[$mapKey]['spend_L30'] ?? 0;
                $salesL30 = $campaignMap[$mapKey]['ad_sales_L30'] ?? 0;
                $acos = 0;
                if ($salesL30 > 0) {
                    $acos = ($spendL30 / $salesL30) * 100;
                } elseif ($spendL30 > 0) {
                    $acos = 100;
                }
                $targetBudget = 1;
                if ($acos < 10) {
                    $targetBudget = 5;
                } elseif ($acos < 30) {
                    $targetBudget = 4;
                } elseif ($acos < 40) {
                    $targetBudget = 3;
                } elseif ($acos < 50) {
                    $targetBudget = 2;
                }
                $campaignMap[$mapKey]['targetBudget'] = $targetBudget;
                
                $campaignMap[$mapKey]['status'] = $matchedCampaign->campaign_status ?? null;
                $campaignMap[$mapKey]['campaignStatus'] = $matchedCampaign->campaign_status ?? null;
            }
            
            // Calculate SBID for this SKU/campaign
            $budget = $campaignMap[$mapKey]['campaignBudgetAmount'] ?? 0;
            $spend_L7 = $campaignMap[$mapKey]['spend_L7'] ?? 0;
            $spend_L1 = $campaignMap[$mapKey]['spend_L1'] ?? 0;
            $cpc_L7 = $campaignMap[$mapKey]['cpc_L7'] ?? 0;
            $cpc_L1 = $campaignMap[$mapKey]['cpc_L1'] ?? 0;
            
            $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($spend_L1 / $budget) * 100 : 0;
            
            $sbid = 0;
            
            // Determine utilization type
            if ($ub7 > 99 && $ub1 > 99) {
                // Over-utilized: decrease bid
                if ($cpc_L7 == 0) {
                    $sbid = 0.75;
                } else {
                    $sbid = floor($cpc_L7 * 0.90 * 100) / 100;
                }
            } elseif ($ub7 < 66 && $ub1 < 66) {
                // Under-utilized: increase bid
                if ($cpc_L1 == 0 && $cpc_L7 == 0) {
                    $sbid = 0.75;
                } elseif ($ub7 < 10 || $cpc_L7 == 0) {
                    $sbid = 0.75;
                } elseif ($cpc_L7 > 0 && $cpc_L7 < 0.30) {
                    $sbid = round($cpc_L7 + 0.20, 2);
                } else {
                    $sbid = floor($cpc_L7 * 1.10 * 100) / 100;
                }
            }
            
            $campaignMap[$mapKey]['sbid'] = $sbid;
            $campaignMap[$mapKey]['ub7'] = $ub7;
            $campaignMap[$mapKey]['ub1'] = $ub1;
        }

        // Sum child INV and L30 into parent rows (parent keeps its own campaign data from DB)
        $parentSums = [];
        foreach ($campaignMap as $key => $row) {
            if (!($row['is_parent'] ?? false) && !empty($row['parent'])) {
                $p = $row['parent'];
                if (!isset($parentSums[$p])) {
                    $parentSums[$p] = ['INV' => 0, 'L30' => 0];
                }
                $parentSums[$p]['INV'] += (int)($row['INV'] ?? 0);
                $parentSums[$p]['L30'] += (int)($row['L30'] ?? 0);
            }
        }
        foreach ($campaignMap as $key => &$row) {
            if (($row['is_parent'] ?? false) && !empty($row['parent']) && isset($parentSums[$row['parent']])) {
                $row['INV'] = $parentSums[$row['parent']]['INV'];
                $row['L30'] = $parentSums[$row['parent']]['L30'];
            }
        }
        unset($row);

        // Convert campaignMap to result array (all SKUs will be included)
        $result = array_values($campaignMap);

        // Count unique parents (from non-parent rows only)
        $totalParentCount = collect($result)->where('is_parent', false)->pluck('parent')->unique()->count();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'total_sku_count' => $totalSkuCount,
            'total_parent_count' => $totalParentCount,
            'status'  => 200,
        ]);
    }

    public function getGoogleShoppingAdsReportData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Calculate date ranges
        $dateRanges = $this->calculateDateRanges();
        $rangesNeeded = ['L7', 'L15', 'L30', 'L60'];

        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereBetween('date', [$dateRanges['L60']['start'], $dateRanges['L60']['end']])
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            // Fixed: Use original SKU for shopifyData lookup (not uppercase)
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Fixed: Use consistent matching logic (same as aggregateMetricsByRange)
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // Allow single-campaign names to match only when fully equal (prevents partial substring matches)
                if (!$exactMatch) {
                    $exactMatch = $campaign === $skuTrimmed;
                }
                
                return $exactMatch;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            
            // Fixed: Add null checks before accessing properties to prevent null pointer exceptions
            $row['campaign_id'] = $matchedCampaign ? ($matchedCampaign->campaign_id ?? null) : null;
            $row['campaignName'] = $matchedCampaign ? ($matchedCampaign->campaign_name ?? null) : null;
            $row['campaignBudgetAmount'] = $matchedCampaign ? ($matchedCampaign->budget_amount_micros ?? null) : null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['campaignStatus'] = $matchedCampaign ? ($matchedCampaign->campaign_status ?? null) : null;

            foreach ($rangesNeeded as $rangeName) {
                $metrics = $this->aggregateMetricsByRange(
                    $googleCampaigns, 
                    $sku, 
                    $dateRanges[$rangeName], 
                    null
                );
                
                $row["spend_$rangeName"] = $metrics['spend'];
                $row["clicks_$rangeName"] = $metrics['clicks'];
                $row["impressions_$rangeName"] = $metrics['impressions'];
                $row["cpc_$rangeName"] = $metrics['cpc'];
                $row["ad_sales_$rangeName"] = $metrics['ad_sales'];
                $row["ad_sold_$rangeName"] = $metrics['ad_sold'];
            }

            // Fixed: Use !empty() instead of != '' to properly handle null values
            if(!empty($row['campaignName'])) {
                $result[] = (object) $row;
            }

        }
        
        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function updateGoogleAdsCampaignSbid(Request $request){

        try {
            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');
            
            try {
                $validator = Validator::make($request->all(), [
                    'campaign_ids' => 'required|array|min:1',
                    'bids' => 'required|array|min:1',
                    'campaign_ids.*' => 'required|string',
                    'bids.*' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        "status" => 422,
                        "message" => "Validation failed",
                        "errors" => $validator->errors(),
                        "data" => []
                    ], 422);
                }
            } catch (\Exception $e) {
                return response()->json([
                    "status" => 500,
                    "message" => "Validation error: " . $e->getMessage(),
                    "data" => []
                ], 500);
            }

            $campaignIds = $request->input('campaign_ids', []);
            $newBids = $request->input('bids', []);

            $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');

            if (!$customerId) {
                return response()->json([
                    "status" => 500,
                    "message" => "Google Ads configuration missing",
                    "data" => []
                ], 500);
            }

            if (count($campaignIds) !== count($newBids)) {
                return response()->json([
                    "status" => 422,
                    "message" => "Campaign IDs and bids arrays must have the same length",
                    "data" => []
                ], 422);
            }

            $results = [];
            $hasError = false;
            $successCount = 0;
            $errorCount = 0;

            foreach ($campaignIds as $index => $campaignId) {
                $newBid = $newBids[$index] ?? null;
                
                if (empty($campaignId) || !is_numeric($newBid) || $newBid <= 0) {
                    $hasError = true;
                    $errorCount++;
                    
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'new_bid' => $newBid,
                        'status' => 'error',
                        'message' => 'Invalid campaign ID or bid amount'
                    ];
                    continue;
                }
                
                $saveMbidOnly = $request->boolean('save_mbid_only');

                try {
                    if (!$saveMbidOnly) {
                        $this->sbidService->updateCampaignSbids($customerId, $campaignId, $newBid);
                    }

                    DB::table('google_ads_campaigns')
                        ->where('campaign_id', $campaignId)
                        ->update(['mbid' => (float) $newBid]);
                    
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'new_bid' => $newBid,
                        'status' => 'success',
                        'message' => $saveMbidOnly ? 'mbid saved (no Google push)' : 'SBID updated successfully'
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    $hasError = true;
                    $errorCount++;
                    
                    $errorMessage = $e->getMessage();

                    $results[] = [
                        'campaign_id' => $campaignId,
                        'new_bid' => $newBid,
                        'status' => 'error',
                        'message' => $errorMessage
                    ];
                }
            }

            $statusCode = $hasError ? ($successCount > 0 ? 207 : 400) : 200;
            $saveMbidOnly = $request->boolean('save_mbid_only');
            $message = $saveMbidOnly
                ? "mbid saved (no Google push). Success: {$successCount}, Errors: {$errorCount}"
                : "SBID update completed. Success: {$successCount}, Errors: {$errorCount}";

            return response()->json([
                "status" => $statusCode,
                "message" => $message,
                "data" => $results,
                "summary" => [
                    "total_campaigns" => count($campaignIds),
                    "successful_updates" => $successCount,
                    "failed_updates" => $errorCount
                ]
            ], $statusCode);

        } catch (\Exception $e) {

            return response()->json([
                "status" => 500,
                "message" => "An unexpected error occurred: " . $e->getMessage(),
                "data" => []
            ], 500);
        }
    }

    public function updateGoogleNrData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $googleDataView = GoogleDataView::firstOrNew(['sku' => $sku]);

        $jsonData = $googleDataView->value ?? [];

        $jsonData[$field] = $value;

        $googleDataView->value = $jsonData;
        $googleDataView->save();

        return response()->json([
            'status' => 200,
            'message' => "Field updated successfully",
            'updated_json' => $jsonData
        ]);
    }

    public function bulkUpdateGoogleNrData(Request $request)
    {
        $skus  = $request->input('skus', []);
        $field = $request->input('field');
        $value = $request->input('value');

        if (empty($skus) || !in_array($field, ['NRA', 'NRL'])) {
            return response()->json([
                'status' => 422,
                'message' => "Invalid request. SKUs array and valid field (NRA/NRL) required."
            ], 422);
        }

        $updated = 0;
        $errors = [];

        foreach ($skus as $sku) {
            try {
                $googleDataView = GoogleDataView::firstOrNew(['sku' => $sku]);
                $jsonData = $googleDataView->value ?? [];
                $jsonData[$field] = $value;
                $googleDataView->value = $jsonData;
                $googleDataView->save();
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Error updating SKU {$sku}: " . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 200,
            'message' => "Bulk update completed. {$updated} SKU(s) updated.",
            'updated_count' => $updated,
            'errors' => $errors
        ]);
    }

    // Chart filter methods
    public function filterGoogleShoppingChart(Request $request)
    {
        // Spend & Clicks: ENABLED SHOPPING only (match Google Ads Cost). Sales & Orders: all SHOPPING (match GA4).
        $responseSpend = $this->getChartData($request, ['advertising_channel_type' => 'SHOPPING', 'campaign_status' => 'ENABLED']);
        $responseSales = $this->getChartData($request, ['advertising_channel_type' => 'SHOPPING']);
        $dataSpend = json_decode($responseSpend->getContent(), true);
        $dataSales = json_decode($responseSales->getContent(), true);
        return response()->json([
            'dates' => $dataSpend['dates'],
            'clicks' => $dataSpend['clicks'],
            'spend' => $dataSpend['spend'],
            'orders' => $dataSales['orders'],
            'sales' => $dataSales['sales'],
            'totals' => [
                'clicks' => $dataSpend['totals']['clicks'],
                'spend' => $dataSpend['totals']['spend'],
                'orders' => $dataSales['totals']['orders'],
                'sales' => $dataSales['totals']['sales'],
            ]
        ]);
    }

    public function filterGoogleShoppingRunningChart(Request $request)
    {
        return $this->getChartData($request, ['campaign_status' => 'ENABLED']);
    }

    public function filterGoogleShoppingReportChart(Request $request)
    {
        return $this->getChartData($request);
    }

    public function filterGoogleSerpChart(Request $request)
    {
        return $this->getChartData($request, ['advertising_channel_type' => 'SEARCH']);
    }

    public function filterGoogleSerpReportChart(Request $request)
    {
        return $this->getChartData($request, ['advertising_channel_type' => 'SEARCH']);
    }

    public function filterGooglePmaxChart(Request $request)
    {
        return $this->getChartData($request, ['advertising_channel_type' => 'PERFORMANCE_MAX']);
    }

    private function getChartData(Request $request, array $additionalFilters = [])
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        // Default: last 30 days ending 2 days ago (match Google Jan 5 – Feb 3, data complete)
        if (!$startDate || !$endDate) {
            $endDate = \Carbon\Carbon::now()->subDays(2)->format('Y-m-d');
            $startDate = \Carbon\Carbon::now()->subDays(31)->format('Y-m-d');
        }

        $query = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_actual_revenue) as ga4_sales,
                SUM(ga4_actual_sold_units) as ga4_orders,
                SUM(ga4_ad_sales) as ad_sales,
                SUM(ga4_sold_units) as ad_orders
            ')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate);

        foreach ($additionalFilters as $column => $value) {
            $query->where($column, $value);
        }

        $data = $query->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $useGA4 = $data->sum('ga4_sales') > 0 || $data->sum('ga4_orders') > 0;

        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $dates[] = $dateStr;
            
            if (isset($data[$dateStr])) {
                $row = $data[$dateStr];
                $clicks[] = (int) $row->clicks;
                $spend[] = (float) $row->spend;
                $orders[] = (int) ($useGA4 ? $row->ga4_orders : $row->ad_orders);
                $sales[] = (float) ($useGA4 ? $row->ga4_sales : $row->ad_sales);
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        // If GA4 total is available, scale sales/orders so totals match GA4 report
        $ga4Total = app(GA4ApiService::class)->getTotalPurchaseMetrics($startDate, $endDate);
        if ($ga4Total && ($ga4Total['revenue'] > 0 || $ga4Total['purchases'] > 0)) {
            $salesSum = array_sum($sales);
            $ordersSum = array_sum($orders);
            if ($salesSum > 0 && $ga4Total['revenue'] > 0) {
                $ratio = $ga4Total['revenue'] / $salesSum;
                $sales = array_map(fn($v) => round($v * $ratio, 2), $sales);
            }
            if ($ordersSum > 0 && $ga4Total['purchases'] > 0) {
                $ratio = $ga4Total['purchases'] / $ordersSum;
                $orders = array_map(fn($v) => (int) round($v * $ratio), $orders);
            }
        }

        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'orders' => $orders,
            'sales' => $sales,
            'totals' => [
                'clicks' => array_sum($clicks),
                'spend' => array_sum($spend),
                'orders' => array_sum($orders),
                'sales' => array_sum($sales),
            ]
        ]);
    }

    public function getGoogleShoppingCampaignChartData(Request $request)
    {
        $campaignName = $request->campaignName;

        // Default to last 30 days if no date range provided
        $startDate = $request->startDate ?? \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $endDate = $request->endDate ?? \Carbon\Carbon::now()->format('Y-m-d');

        if (!$campaignName) {
            return response()->json([
                'error' => 'Campaign name is required'
            ], 400);
        }

        // Log for debugging
        Log::info('Campaign Chart Request', [
            'campaign_name' => $campaignName,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Use exact campaign name matching (same as main table logic)
        $campaignNameUpper = strtoupper(trim($campaignName));
        $campaignNameCleaned = rtrim(trim($campaignNameUpper), '.');

        Log::info('Campaign Chart Request - Matching', [
            'original_campaign' => $campaignName,
            'campaign_upper' => $campaignNameUpper,
            'campaign_cleaned' => $campaignNameCleaned
        ]);

        // Fetch data matching campaign name with GA4 actual data preference
        // Exclude ARCHIVED campaigns to match table data logic
        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(metrics_impressions) as impressions,
                SUM(ga4_actual_sold_units) as ga4_actual_orders,
                SUM(ga4_actual_revenue) as ga4_actual_sales,
                SUM(ga4_sold_units) as ga4_orders,
                SUM(ga4_ad_sales) as ga4_sales
            ')
            ->whereNotNull('date')
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function($query) use ($campaignNameUpper, $campaignNameCleaned, $campaignName) {
                $query->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameUpper])
                      ->orWhereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameCleaned])
                      ->orWhere('campaign_name', 'LIKE', '%' . trim($campaignName) . '%');
            })
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Check if GA4 actual data exists for any day in the range
        $totalGA4ActualSales = $data->sum('ga4_actual_sales');
        $totalGA4ActualOrders = $data->sum('ga4_actual_orders');
        $useGA4Actual = ($totalGA4ActualSales > 0 || $totalGA4ActualOrders > 0);

        // Process data: Use GA4 actual data if available for ANY day, otherwise fallback to Google Ads data
        $processedData = $data->map(function($item) use ($useGA4Actual) {
            return (object) [
                'date' => $item->date,
                'clicks' => $item->clicks,
                'spend' => $item->spend,
                'impressions' => $item->impressions,
                'orders' => $useGA4Actual ? $item->ga4_actual_orders : $item->ga4_orders,
                'sales' => $useGA4Actual ? $item->ga4_actual_sales : $item->ga4_sales,
            ];
        });

        // Fill in missing dates with zeros
        $allDates = [];
        $allClicks = [];
        $allSpend = [];
        $allOrders = [];
        $allSales = [];

        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $allDates[] = $date->format('M d');
            
            $dayData = $processedData->firstWhere('date', $dateStr);
            
            if ($dayData) {
                $allClicks[] = (int) $dayData->clicks;
                $allSpend[] = (float) $dayData->spend;
                $allOrders[] = (int) $dayData->orders;
                $allSales[] = (float) $dayData->sales;
            } else {
                $allClicks[] = 0;
                $allSpend[] = 0;
                $allOrders[] = 0;
                $allSales[] = 0;
            }
        }

        // Calculate totals from processed data (after GA4 actual data preference)
        $totalClicks = array_sum($allClicks);
        $totalSpend = array_sum($allSpend);
        $totalOrders = array_sum($allOrders);
        $totalSales = array_sum($allSales);
        $totalImpressions = $processedData->sum('impressions');
        
        $ctr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;

        return response()->json([
            'chartData' => [
                'labels' => $allDates,
                'clicks' => $allClicks,
                'spend' => $allSpend,
                'orders' => $allOrders,
                'sales' => $allSales,
            ],
            'totals' => [
                'clicks' => (int) $totalClicks,
                'spend' => (float) $totalSpend,
                'orders' => (int) $totalOrders,
                'sales' => (float) $totalSales,
                'impressions' => (int) $totalImpressions,
                'ctr' => $ctr,
            ]
        ]);
    }

    /**
     * Daily ACOS history for a campaign (line chart, period selector, min/max).
     * Used by ACOS "eye" icon in google-shopping-utilized.
     */
    public function getGoogleShoppingCampaignAcosChartData(Request $request)
    {
        $campaignName = $request->campaignName;
        $startDate = $request->startDate ?? \Carbon\Carbon::now()->subDays(29)->format('Y-m-d');
        $endDate = $request->endDate ?? \Carbon\Carbon::now()->format('Y-m-d');

        if (!$campaignName) {
            return response()->json(['error' => 'Campaign name is required'], 400);
        }

        $campaignNameUpper = strtoupper(trim($campaignName));
        $campaignNameCleaned = rtrim(trim($campaignNameUpper), '.');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_actual_revenue) as ga4_actual_sales,
                SUM(ga4_ad_sales) as ga4_sales
            ')
            ->whereNotNull('date')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) use ($campaignNameUpper, $campaignNameCleaned, $campaignName) {
                $q->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameUpper])
                  ->orWhereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameCleaned])
                  ->orWhere('campaign_name', 'LIKE', '%' . trim($campaignName) . '%');
            })
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $useGA4Actual = $data->sum('ga4_actual_sales') > 0;

        $byDate = [];
        foreach ($data as $row) {
            $spend = (float) $row->spend;
            $sales = $useGA4Actual ? (float) $row->ga4_actual_sales : (float) $row->ga4_sales;
            $acos = 0;
            if ($sales >= 1) {
                $acos = ($spend / $sales) * 100;
            } elseif ($spend > 0) {
                $acos = 100;
            }
            $byDate[$row->date] = round($acos, 2);
        }

        $labels = [];
        $dates = [];
        $acosValues = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $labels[] = $d->format('M d');
            $dates[] = $dateStr;
            $acosValues[] = $byDate[$dateStr] ?? 0;
        }

        $min = $acosValues ? min($acosValues) : 0;
        $max = $acosValues ? max($acosValues) : 0;

        return response()->json([
            'labels' => $labels,
            'dates' => $dates,
            'acos' => $acosValues,
            'min' => round($min, 2),
            'max' => round($max, 2),
        ]);
    }

    /**
     * Daily CVR and Price (CPC) history per campaign for CVR column eye icon.
     * CVR = (orders / clicks) * 100; Price = CPC = spend / clicks.
     */
    public function getGoogleShoppingCampaignCvrPriceChartData(Request $request)
    {
        $campaignName = $request->campaignName;
        $startDate = $request->startDate ?? \Carbon\Carbon::now()->subDays(29)->format('Y-m-d');
        $endDate = $request->endDate ?? \Carbon\Carbon::now()->format('Y-m-d');

        if (!$campaignName) {
            return response()->json(['error' => 'Campaign name is required'], 400);
        }

        $campaignNameUpper = strtoupper(trim($campaignName));
        $campaignNameCleaned = rtrim(trim($campaignNameUpper), '.');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_actual_sold_units) as ga4_actual_orders,
                SUM(ga4_sold_units) as ga4_orders
            ')
            ->whereNotNull('date')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) use ($campaignNameUpper, $campaignNameCleaned, $campaignName) {
                $q->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameUpper])
                  ->orWhereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameCleaned])
                  ->orWhere('campaign_name', 'LIKE', '%' . trim($campaignName) . '%');
            })
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $useGA4Actual = $data->sum('ga4_actual_orders') > 0;
        $byDate = [];
        foreach ($data as $row) {
            $clicks = (float) $row->clicks;
            $spend = (float) $row->spend;
            $orders = $useGA4Actual ? (float) $row->ga4_actual_orders : (float) $row->ga4_orders;
            $cvr = ($clicks >= 1 && $orders > 0) ? round(($orders / $clicks) * 100, 2) : 0;
            $price = ($clicks >= 1) ? round($spend / $clicks, 4) : 0;
            $byDate[$row->date] = ['cvr' => $cvr, 'price' => $price];
        }

        $labels = [];
        $dates = [];
        $cvrValues = [];
        $priceValues = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $labels[] = $d->format('M d');
            $dates[] = $dateStr;
            $entry = $byDate[$dateStr] ?? ['cvr' => 0, 'price' => 0];
            $cvrValues[] = $entry['cvr'];
            $priceValues[] = $entry['price'];
        }

        $cvrMin = $cvrValues ? min($cvrValues) : 0;
        $cvrMax = $cvrValues ? max($cvrValues) : 0;
        $priceMin = $priceValues ? min($priceValues) : 0;
        $priceMax = $priceValues ? max($priceValues) : 0;

        return response()->json([
            'labels' => $labels,
            'dates' => $dates,
            'cvr' => $cvrValues,
            'price' => $priceValues,
            'cvr_min' => round($cvrMin, 2),
            'cvr_max' => round($cvrMax, 2),
            'price_min' => round($priceMin, 4),
            'price_max' => round($priceMax, 4),
        ]);
    }

    /**
     * Overall (aggregate) daily ACOS history for all SHOPPING campaigns.
     * Used by overall ACOS badge eye icon.
     */
    public function getGoogleShoppingOverallAcosChartData(Request $request)
    {
        $startDate = $request->startDate ?? \Carbon\Carbon::now()->subDays(29)->format('Y-m-d');
        $endDate = $request->endDate ?? \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_actual_revenue) as ga4_actual_sales,
                SUM(ga4_ad_sales) as ga4_sales
            ')
            ->whereNotNull('date')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $useGA4Actual = $data->sum('ga4_actual_sales') > 0;
        $byDate = [];
        foreach ($data as $row) {
            $spend = (float) $row->spend;
            $sales = $useGA4Actual ? (float) $row->ga4_actual_sales : (float) $row->ga4_sales;
            $acos = 0;
            if ($sales >= 1) {
                $acos = ($spend / $sales) * 100;
            } elseif ($spend > 0) {
                $acos = 100;
            }
            $byDate[$row->date] = round($acos, 2);
        }

        $labels = [];
        $dates = [];
        $acosValues = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $labels[] = $d->format('M d');
            $dates[] = $dateStr;
            $acosValues[] = $byDate[$dateStr] ?? 0;
        }

        $min = $acosValues ? min($acosValues) : 0;
        $max = $acosValues ? max($acosValues) : 0;

        return response()->json([
            'labels' => $labels,
            'dates' => $dates,
            'acos' => $acosValues,
            'min' => round($min, 2),
            'max' => round($max, 2),
        ]);
    }

    /**
     * Overall (aggregate) daily CVR and Price (CPC) history for all SHOPPING campaigns.
     * Used by overall CVR badge eye icon.
     */
    public function getGoogleShoppingOverallCvrPriceChartData(Request $request)
    {
        $startDate = $request->startDate ?? \Carbon\Carbon::now()->subDays(29)->format('Y-m-d');
        $endDate = $request->endDate ?? \Carbon\Carbon::now()->format('Y-m-d');

        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_actual_sold_units) as ga4_actual_orders,
                SUM(ga4_sold_units) as ga4_orders
            ')
            ->whereNotNull('date')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $useGA4Actual = $data->sum('ga4_actual_orders') > 0;
        $byDate = [];
        foreach ($data as $row) {
            $clicks = (float) $row->clicks;
            $spend = (float) $row->spend;
            $orders = $useGA4Actual ? (float) $row->ga4_actual_orders : (float) $row->ga4_orders;
            $cvr = ($clicks >= 1 && $orders > 0) ? round(($orders / $clicks) * 100, 2) : 0;
            $price = ($clicks >= 1) ? round($spend / $clicks, 4) : 0;
            $byDate[$row->date] = ['cvr' => $cvr, 'price' => $price];
        }

        $labels = [];
        $dates = [];
        $cvrValues = [];
        $priceValues = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $labels[] = $d->format('M d');
            $dates[] = $dateStr;
            $entry = $byDate[$dateStr] ?? ['cvr' => 0, 'price' => 0];
            $cvrValues[] = $entry['cvr'];
            $priceValues[] = $entry['price'];
        }

        $cvrMin = $cvrValues ? min($cvrValues) : 0;
        $cvrMax = $cvrValues ? max($cvrValues) : 0;
        $priceMin = $priceValues ? min($priceValues) : 0;
        $priceMax = $priceValues ? max($priceValues) : 0;

        return response()->json([
            'labels' => $labels,
            'dates' => $dates,
            'cvr' => $cvrValues,
            'price' => $priceValues,
            'cvr_min' => round($cvrMin, 2),
            'cvr_max' => round($cvrMax, 2),
            'price_min' => round($priceMin, 4),
            'price_max' => round($priceMax, 4),
        ]);
    }

    private function getFilteredChartData(Request $request, $filterType = null)
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        if (!$startDate || !$endDate) {
            $endDate = \Carbon\Carbon::now()->format('Y-m-d');
            $startDate = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        }

        // Get campaign IDs that meet the filter criteria
        $filteredCampaignIds = $this->getFilteredCampaignIds($filterType, $startDate, $endDate);

        if (empty($filteredCampaignIds)) {
            // Return empty data if no campaigns match the filter
            $dates = [];
            $clicks = [];
            $spend = [];
            $orders = [];
            $sales = [];

            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            for ($date = $start; $date->lte($end); $date->addDay()) {
                $dates[] = $date->format('Y-m-d');
                $clicks[] = 0;
                $spend[] = 0;
                $orders[] = 0;
                $sales[] = 0;
            }

            return response()->json([
                'dates' => $dates,
                'clicks' => $clicks,
                'spend' => $spend,
                'orders' => $orders,
                'sales' => $sales,
                'totals' => [
                    'clicks' => 0,
                    'spend' => 0,
                    'orders' => 0,
                    'sales' => 0,
                ]
            ]);
        }

        // Get chart data only for filtered campaigns
        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_actual_sold_units) as ga4_actual_orders,
                SUM(ga4_actual_revenue) as ga4_actual_sales,
                SUM(ga4_sold_units) as ga4_orders, 
                SUM(ga4_ad_sales) as ga4_sales
            ')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->whereIn('campaign_id', $filteredCampaignIds)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Check if GA4 actual data exists for any day in the range
        $totalGA4ActualSales = $data->sum('ga4_actual_sales');
        $totalGA4ActualOrders = $data->sum('ga4_actual_orders');
        $useGA4Actual = ($totalGA4ActualSales > 0 || $totalGA4ActualOrders > 0);

        // Process data: Use GA4 actual data if available for ANY day, otherwise fallback to Google Ads data
        $data = $data->map(function($item) use ($useGA4Actual) {
            return (object) [
                'date' => $item->date,
                'clicks' => $item->clicks,
                'spend' => $item->spend,
                'orders' => $useGA4Actual ? $item->ga4_actual_orders : $item->ga4_orders,
                'sales' => $useGA4Actual ? $item->ga4_actual_sales : $item->ga4_sales,
            ];
        })->keyBy('date');

        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $dates[] = $dateStr;
            
            if (isset($data[$dateStr])) {
                $clicks[] = (int) $data[$dateStr]->clicks;
                $spend[] = (float) $data[$dateStr]->spend;
                $orders[] = (int) $data[$dateStr]->orders;
                $sales[] = (float) $data[$dateStr]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'orders' => $orders,
            'sales' => $sales,
            'totals' => [
                'clicks' => array_sum($clicks),
                'spend' => array_sum($spend),
                'orders' => array_sum($orders),
                'sales' => array_sum($sales),
            ]
        ]);
    }

    private function getFilteredCampaignIds($filterType, $startDate, $endDate)
    {
        if (!in_array($filterType, ['over_utilize', 'under_utilize'])) {
            return [];
        }

        // Calculate date ranges for L1 and L7
        $dateRanges = $this->calculateDateRanges();

        // Get all product masters with their Shopify data
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get Google campaigns data
        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereBetween('date', [$dateRanges['L7']['start'], $dateRanges['L7']['end']])
            ->get();

        $filteredCampaignIds = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Find matching campaign
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                return $exactMatch;
            });

            if (!$matchedCampaign) {
                continue;
            }

            $campaignId = $matchedCampaign->campaign_id;
            $budget = $matchedCampaign->budget_amount_micros ? $matchedCampaign->budget_amount_micros / 1000000 : 0;

            // Calculate L7 metrics using aggregateMetricsByRange method
            $metricsL7 = $this->aggregateMetricsByRange(
                $googleCampaigns, 
                $sku, 
                $dateRanges['L7'], 
                'ENABLED'
            );

            $spendL7 = $metricsL7['spend'];

            // Calculate UB7 percentage
            $ub7 = $budget > 0 ? ($spendL7 / ($budget * 7)) * 100 : 0;

            // Apply filter based on type
            // Need to calculate ub1 as well for the new thresholds
            $metricsL1 = $this->aggregateMetricsByRange(
                $googleCampaigns, 
                $sku, 
                $dateRanges['L1'], 
                'ENABLED'
            );
            $spendL1 = $metricsL1['spend'];
            $ub1 = $budget > 0 ? ($spendL1 / $budget) * 100 : 0;
            
            if ($filterType === 'over_utilize' && $ub7 > 99 && $ub1 > 99) {
                $filteredCampaignIds[] = $campaignId;
            } elseif ($filterType === 'under_utilize' && $ub7 < 66 && $ub1 < 66) {
                $filteredCampaignIds[] = $campaignId;
            }
        }

        return array_unique($filteredCampaignIds);
    }

    public function getGoogleShoppingUtilizationCounts(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $skuKey = 'GOOGLE_SHOPPING_UTILIZATION_' . $today;
            
            $record = GoogleDataView::where('sku', $skuKey)->first();
            
            // Check if record exists and has valid data (not blank/zero data)
            $isValidRecord = false;
            if ($record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                // Check if any count is greater than 0 (valid data)
                $totalCount = ($value['over_utilized_7ub'] ?? 0) + 
                             ($value['under_utilized_7ub'] ?? 0) +
                             ($value['over_utilized_7ub_1ub'] ?? 0) + 
                             ($value['under_utilized_7ub_1ub'] ?? 0);
                $isValidRecord = $totalCount > 0;
            }
            
            // If valid record exists, return stored data
            if ($isValidRecord && $record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                return response()->json([
                    'over_utilized_7ub' => $value['over_utilized_7ub'] ?? 0,
                    'under_utilized_7ub' => $value['under_utilized_7ub'] ?? 0,
                    'over_utilized_7ub_1ub' => $value['over_utilized_7ub_1ub'] ?? 0,
                    'under_utilized_7ub_1ub' => $value['under_utilized_7ub_1ub'] ?? 0,
                    'status' => 200,
                ]);
            }
            
            // If no valid data, calculate from current data (same logic as command)
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            $dateRanges = $this->calculateDateRanges();
            
            $googleCampaigns = DB::table('google_ads_campaigns')
                ->select(
                    'campaign_id',
                    'campaign_name',
                    'campaign_status',
                    'budget_amount_micros',
                    'date',
                    'metrics_cost_micros',
                    'metrics_clicks'
                )
                ->where('advertising_channel_type', 'SHOPPING')
                ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
                ->get();

            $result = [];
            $uniqueCampaignIds = $googleCampaigns->pluck('campaign_id')->unique();
            $campaignMap = $googleCampaigns->groupBy('campaign_id')->map(function ($campaigns) {
                return $campaigns->first();
            });

            foreach ($uniqueCampaignIds as $campaignId) {
                $campaign = $campaignMap[$campaignId];
                $campaignName = $campaign->campaign_name;

                $matchedPm = null;
                foreach ($productMasters as $pm) {
                    $sku = strtoupper(trim($pm->sku));
                    $campaignUpper = strtoupper(trim($campaignName));
                    $campaignUpperCleaned = rtrim($campaignUpper, '.');

                    $parts = array_map(function($part) { return rtrim(trim($part), '.'); }, explode(',', $campaignUpperCleaned));
                    $skuTrimmed = strtoupper(trim($sku));
                    $exactMatch = in_array($skuTrimmed, $parts);

                    if (!$exactMatch) {
                        $exactMatch = $campaignUpperCleaned === $skuTrimmed;
                    }

                    if ($exactMatch) {
                        $matchedPm = $pm;
                        break;
                    }
                }

                $inv = 0;
                if ($matchedPm) {
                    $shopify = $shopifyData[$matchedPm->sku] ?? null;
                    $inv = $shopify->inv ?? 0;
                }

                if (floatval($inv) <= 0) {
                    continue;
                }

                $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)
                    ->sortByDesc('date')
                    ->first();
                $budget = $latestCampaign && $latestCampaign->budget_amount_micros
                    ? $latestCampaign->budget_amount_micros / 1000000
                    : 0;

                $spend_L7 = $googleCampaigns
                    ->where('campaign_id', $campaignId)
                    ->whereBetween('date', [$dateRanges['L7']['start'], $dateRanges['L7']['end']])
                    ->where('campaign_status', 'ENABLED')
                    ->sum('metrics_cost_micros') / 1000000;

                $spend_L1 = $googleCampaigns
                    ->where('campaign_id', $campaignId)
                    ->whereBetween('date', [$dateRanges['L1']['start'], $dateRanges['L1']['end']])
                    ->where('campaign_status', 'ENABLED')
                    ->sum('metrics_cost_micros') / 1000000;

                $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($spend_L1 / ($budget * 1)) * 100 : 0;

                if (!isset($result[$campaignId])) {
                    $result[$campaignId] = [
                        'ub7' => $ub7,
                        'ub1' => $ub1,
                    ];
                }
            }

            $overUtilizedCount7ub = 0;
            $underUtilizedCount7ub = 0;
            $overUtilizedCount7ub1ub = 0;
            $underUtilizedCount7ub1ub = 0;

            foreach ($result as $campaignData) {
                $ub7 = $campaignData['ub7'];
                $ub1 = $campaignData['ub1'];
                
                if ($ub7 > 99) {
                    $overUtilizedCount7ub++;
                } elseif ($ub7 < 66) {
                    $underUtilizedCount7ub++;
                }
                
                if ($ub7 > 99 && $ub1 > 99) {
                    $overUtilizedCount7ub1ub++;
                } elseif ($ub7 < 66 && $ub1 < 66) {
                    $underUtilizedCount7ub1ub++;
                }
            }

            return response()->json([
                'over_utilized_7ub' => $overUtilizedCount7ub,
                'under_utilized_7ub' => $underUtilizedCount7ub,
                'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
                'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getGoogleShoppingUtilizationCounts: ' . $e->getMessage());
            return response()->json([
                'over_utilized_7ub' => 0,
                'under_utilized_7ub' => 0,
                'over_utilized_7ub_1ub' => 0,
                'under_utilized_7ub_1ub' => 0,
                'status' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getGoogleShoppingUtilizationChartData(Request $request)
    {
        try {
            $condition = $request->get('condition', '7ub'); // Default to 7ub, can be '7ub-1ub'
            
            $data = GoogleDataView::where('sku', 'LIKE', 'GOOGLE_SHOPPING_UTILIZATION_%')
                ->orderBy('sku', 'desc')
                ->limit(30)
                ->get();
            
            $data = $data->map(function ($item) use ($condition) {
                $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                
                $date = str_replace('GOOGLE_SHOPPING_UTILIZATION_', '', $item->sku);
                
                if ($condition === '7ub') {
                    return [
                        'date' => $date,
                        'over_utilized_7ub' => $value['over_utilized_7ub'] ?? 0,
                        'under_utilized_7ub' => $value['under_utilized_7ub'] ?? 0,
                    ];
                } else {
                    return [
                        'date' => $date,
                        'over_utilized_7ub_1ub' => $value['over_utilized_7ub_1ub'] ?? 0,
                        'under_utilized_7ub_1ub' => $value['under_utilized_7ub_1ub'] ?? 0,
                    ];
                }
            })
            ->reverse()
            ->values();

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $data,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getGoogleShoppingUtilizationChartData: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching chart data: ' . $e->getMessage(),
                'data' => [],
                'status' => 500,
            ], 500);
        }
    }

    public function toggleGoogleShoppingCampaignStatus(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $status = $request->input('status'); // 'ENABLED' or 'PAUSED'

            if (!$campaignId || !$status) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign ID and status are required'
                ], 400);
            }

            if (!in_array($status, ['ENABLED', 'PAUSED'])) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Status must be ENABLED or PAUSED'
                ], 400);
            }

            $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');
            if (empty($customerId)) {
                return response()->json([
                    'status' => 500,
                    'message' => 'GOOGLE_ADS_LOGIN_CUSTOMER_ID is not configured'
                ], 500);
            }

            $campaignResourceName = "customers/{$customerId}/campaigns/{$campaignId}";

            try {
                if ($status === 'PAUSED') {
                    $this->sbidService->pauseCampaign($customerId, $campaignResourceName);
                } else {
                    $this->sbidService->enableCampaign($customerId, $campaignResourceName);
                }

                // Update database
                DB::table('google_ads_campaigns')
                    ->where('campaign_id', $campaignId)
                    ->update(['campaign_status' => $status]);

                return response()->json([
                    'status' => 200,
                    'message' => "Campaign {$status} successfully",
                    'campaign_id' => $campaignId,
                    'campaign_status' => $status
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to {$status} Google Shopping campaign {$campaignId}: " . $e->getMessage());
                return response()->json([
                    'status' => 500,
                    'message' => "Failed to {$status} campaign: " . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Error in toggleGoogleShoppingCampaignStatus: " . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error toggling campaign status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable or pause multiple Google Shopping campaigns.
     */
    public function toggleBulkGoogleShoppingCampaignStatus(Request $request)
    {
        try {
            $campaignIds = $request->input('campaign_ids');
            $status = $request->input('status'); // 'ENABLED' or 'PAUSED'

            if (!is_array($campaignIds) || empty($campaignIds) || !$status) {
                return response()->json([
                    'status' => 400,
                    'message' => 'campaign_ids (array) and status are required'
                ], 400);
            }

            if (!in_array($status, ['ENABLED', 'PAUSED'])) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Status must be ENABLED or PAUSED'
                ], 400);
            }

            $campaignIds = array_values(array_filter(array_unique($campaignIds)));

            $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');
            if (empty($customerId)) {
                return response()->json([
                    'status' => 500,
                    'message' => 'GOOGLE_ADS_LOGIN_CUSTOMER_ID is not configured'
                ], 500);
            }

            $updated = 0;
            $errors = [];

            foreach ($campaignIds as $campaignId) {
                if (empty($campaignId)) {
                    continue;
                }
                $campaignResourceName = "customers/{$customerId}/campaigns/{$campaignId}";
                try {
                    if ($status === 'PAUSED') {
                        $this->sbidService->pauseCampaign($customerId, $campaignResourceName);
                    } else {
                        $this->sbidService->enableCampaign($customerId, $campaignResourceName);
                    }
                    DB::table('google_ads_campaigns')
                        ->where('campaign_id', $campaignId)
                        ->update(['campaign_status' => $status]);
                    $updated++;
                } catch (\Exception $e) {
                    Log::error("Bulk toggle: failed to {$status} campaign {$campaignId}: " . $e->getMessage());
                    $errors[] = ['campaign_id' => $campaignId, 'message' => $e->getMessage()];
                }
            }

            $failed = count($errors);
            $msg = "{$updated} campaign(s) {$status}.";
            if ($failed > 0) {
                $msg .= " {$failed} failed.";
            }

            return response()->json([
                'status' => 200,
                'message' => $msg,
                'updated' => $updated,
                'failed' => $failed,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            Log::error("Error in toggleBulkGoogleShoppingCampaignStatus: " . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error in bulk toggle: ' . $e->getMessage()
            ], 500);
        }
    }
}
