<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\GoogleDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use Illuminate\Http\Request;
use App\Services\GoogleAdsSbidService;
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

    public function googleOverUtilizeView(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        // Get filtered campaign IDs for over-utilize (UB7 > 90%)
        $filteredCampaignIds = $this->getFilteredCampaignIds('over_utilize', $thirtyDaysAgo, $today);

        if (empty($filteredCampaignIds)) {
            // Return empty data if no campaigns match the filter
            $dates = [];
            $clicks = [];
            $spend = [];
            $orders = [];
            $sales = [];

            for ($i = 30; $i >= 0; $i--) {
                $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
                $dates[] = $date;
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        } else {
            $data = DB::table('google_ads_campaigns')
                ->selectRaw('
                    date,
                    SUM(metrics_clicks) as clicks, 
                    SUM(metrics_cost_micros) / 1000000 as spend, 
                    SUM(ga4_sold_units) as orders, 
                    SUM(ga4_ad_sales) as sales
                ')
                ->whereDate('date', '>=', $thirtyDaysAgo)
                ->whereIn('campaign_id', $filteredCampaignIds)
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
        }

        return view('campaign.google-shopping-over-utilize', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleUnderUtilizeView(){
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        // Get filtered campaign IDs for under-utilize (UB7 < 70%)
        $filteredCampaignIds = $this->getFilteredCampaignIds('under_utilize', $thirtyDaysAgo, $today);

        if (empty($filteredCampaignIds)) {
            // Return empty data if no campaigns match the filter
            $dates = [];
            $clicks = [];
            $spend = [];
            $orders = [];
            $sales = [];

            for ($i = 30; $i >= 0; $i--) {
                $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
                $dates[] = $date;
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        } else {
            $data = DB::table('google_ads_campaigns')
                ->selectRaw('
                    date,
                    SUM(metrics_clicks) as clicks, 
                    SUM(metrics_cost_micros) / 1000000 as spend, 
                    SUM(ga4_sold_units) as orders, 
                    SUM(ga4_ad_sales) as sales
                ')
                ->whereDate('date', '>=', $thirtyDaysAgo)
                ->whereIn('campaign_id', $filteredCampaignIds)
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
        }

        return view('campaign.google-shopping-under-utilize', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleShoppingUtilizedView(){
        // Google Dashboard "Last 30 days" typically means: last 30 complete days (excluding today)
        // Because today's data might not be complete yet
        $yesterday = \Carbon\Carbon::now()->subDay();
        $thirtyDaysAgo = $yesterday->copy()->subDays(29)->format('Y-m-d'); // 30 days including yesterday
        $endDate = $yesterday->format('Y-m-d');

        // Get all SHOPPING campaigns for combined view (no filtering by utilization)
        $googleCampaigns = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->whereDate('date', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zeros (for last 30 days ending yesterday)
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = $yesterday->copy()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            if (isset($googleCampaigns[$date])) {
                $clicks[] = (int) $googleCampaigns[$date]->clicks;
                $spend[] = (float) $googleCampaigns[$date]->spend;
                $orders[] = (int) $googleCampaigns[$date]->orders;
                $sales[] = (float) $googleCampaigns[$date]->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
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

        // Calculate date ranges
        $dateRanges = $this->calculateDateRanges();
        $rangesNeeded = ['L1', 'L7', 'L30'];

        // Only fetch SHOPPING campaigns for Shopping Ads
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
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        $result = [];
        $campaignMap = [];

        // Process each SKU (similar to Amazon - loop through SKUs, not campaigns)
        foreach ($productMasters as $pm) {
            // Skip parent SKUs (similar to Amazon KW/PT)
            $sku = strtoupper(trim($pm->sku));
            if (stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Find matching campaign for this SKU
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $campaignCleaned = rtrim(trim($campaign), '.');
                $skuTrimmed = strtoupper(trim($sku));
                
                $parts = array_map('trim', explode(',', $campaignCleaned));
                $parts = array_map(function($part) {
                    return rtrim(trim($part), '.');
                }, $parts);
                $exactMatch = in_array($skuTrimmed, $parts);
                
                if (!$exactMatch) {
                    $exactMatch = $campaignCleaned === $skuTrimmed;
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
            $gpft = null;
            $pft = null;
            $roi = null;
            $sprice = null;
            $spft = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $nrl = $raw['NRL'] ?? 'REQ';
                    $gpft = $raw['GPFT'] ?? null;
                    $pft = $raw['PFT'] ?? null;
                    $roi = $raw['ROI'] ?? null;
                    $sprice = $raw['SPRICE'] ?? null;
                    $spft = $raw['SPFT'] ?? null;
                }
            }

            // Note: Include NRA items in data so they can be counted and filtered in frontend
            // Frontend will handle filtering/hiding NRA items based on user selection

            // Use SKU as key (since we're looping by SKUs, not campaigns)
            $mapKey = 'SKU_' . $pm->sku;

            if (!isset($campaignMap[$mapKey])) {
                $campaignMap[$mapKey] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => $matchedCampaign && $matchedCampaign->budget_amount_micros 
                        ? $matchedCampaign->budget_amount_micros / 1000000 
                        : 0,
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
                    $metrics = $this->aggregateMetricsByRange(
                        $googleCampaigns, 
                        $skuForMetrics, 
                        $dateRanges[$rangeName], 
                        'ENABLED'
                    );
                    
                    $campaignMap[$mapKey]["spend_$rangeName"] = $metrics['spend'];
                    $campaignMap[$mapKey]["clicks_$rangeName"] = $metrics['clicks'];
                    $campaignMap[$mapKey]["cpc_$rangeName"] = $metrics['cpc'];
                    $campaignMap[$mapKey]["ad_sales_$rangeName"] = $metrics['ad_sales'];
                    $campaignMap[$mapKey]["ad_sold_$rangeName"] = $metrics['ad_sold'];
                }
                
                // Get budget from latest campaign record
                $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)
                    ->sortByDesc('date')
                    ->first();
                if ($latestCampaign && $latestCampaign->budget_amount_micros) {
                    $campaignMap[$mapKey]['campaignBudgetAmount'] = $latestCampaign->budget_amount_micros / 1000000;
                }
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

        // Convert campaignMap to result array (all SKUs will be included)
        $result = array_values($campaignMap);

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'total_sku_count' => $totalSkuCount,
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
                
                try {
                    $this->sbidService->updateCampaignSbids($customerId, $campaignId, $newBid);
                    
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'new_bid' => $newBid,
                        'status' => 'success',
                        'message' => 'SBID updated successfully'
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
            $message = "SBID update completed. Success: {$successCount}, Errors: {$errorCount}";

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

    public function googleMissingAdsView(){
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

        return view('campaign.google.google-shopping-missing-ads', [
            'dates' => $dates,
            'clicks' => collect($clicks),
            'spend' => collect($spend),
            'orders' => collect($orders),
            'sales' => collect($sales)
        ]);
    }

    public function googleShoppingAdsMissingAds()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = GoogleDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Calculate date ranges for recent data
        $dateRanges = $this->calculateDateRanges();

        // Only fetch SHOPPING campaigns that are ENABLED and have recent data
        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status'
            )
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', 'ENABLED')
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->distinct()
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Fixed: Use improved matching logic (same as other methods)
            // Only match if SKU is in comma-separated list OR campaign name exactly equals SKU
            // This prevents partial matches like "MX 12CH" matching "MX 12CH XU"
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign name exactly equals SKU
                if (!$exactMatch) {
                    $exactMatch = $campaign === $skuTrimmed;
                }
                
                return $exactMatch;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku'] = $pm->sku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            
            // Fixed: Add null checks before accessing properties to prevent null pointer exceptions
            $row['campaign_id'] = $matchedCampaign ? ($matchedCampaign->campaign_id ?? null) : null;
            $row['campaignName'] = $matchedCampaign ? ($matchedCampaign->campaign_name ?? null) : null;
            $row['campaignStatus'] = $matchedCampaign ? ($matchedCampaign->campaign_status ?? null) : null;

            $row['NR'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                }
            }

            
            $result[] = (object) $row;
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
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
        return $this->getChartData($request);
    }

    public function filterGoogleShoppingRunningChart(Request $request)
    {
        return $this->getChartData($request, ['campaign_status' => 'ENABLED']);
    }

    public function filterGoogleShoppingOverChart(Request $request)
    {
        return $this->getFilteredChartData($request, 'over_utilize');
    }

    public function filterGoogleShoppingUnderChart(Request $request)
    {
        return $this->getFilteredChartData($request, 'under_utilize');
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

        if (!$startDate || !$endDate) {
            $endDate = \Carbon\Carbon::now()->format('Y-m-d');
            $startDate = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        }

        $query = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks, 
                SUM(metrics_cost_micros) / 1000000 as spend, 
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
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

        // Extract SKU from campaign name (matching main table logic)
        $parts = explode(' ', $campaignName);
        $skuTrimmed = '';
        foreach ($parts as $part) {
            if (strlen($part) >= 2) {
                $skuTrimmed = $part;
                break;
            }
        }

        Log::info('SKU Extracted for aggregation', [
            'original_campaign' => $campaignName,
            'extracted_sku' => $skuTrimmed
        ]);

        // Use LIKE matching based on SKU (same as main table aggregation logic)
        $data = DB::table('google_ads_campaigns')
            ->selectRaw('
                date,
                SUM(metrics_clicks) as clicks,
                SUM(metrics_cost_micros) / 1000000 as spend,
                SUM(ga4_sold_units) as orders,
                SUM(ga4_ad_sales) as sales,
                SUM(metrics_impressions) as impressions
            ')
            ->whereNotNull('date')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('campaign_name', 'LIKE', '%' . $skuTrimmed . '%')  // LIKE matching like main table
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

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
            
            $dayData = $data->firstWhere('date', $dateStr);
            
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

        $totalClicks = $data->sum('clicks');
        $totalSpend = $data->sum('spend');
        $totalOrders = $data->sum('orders');
        $totalSales = $data->sum('sales');
        $totalImpressions = $data->sum('impressions');
        
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
                SUM(ga4_sold_units) as orders, 
                SUM(ga4_ad_sales) as sales
            ')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->whereIn('campaign_id', $filteredCampaignIds)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

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
}
