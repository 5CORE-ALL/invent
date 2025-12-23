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

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

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
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
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
            
            // Get budget from the latest campaign record for this campaign_id (to ensure consistency with command)
            // Budget should be same across dates, but get latest to be safe
            $campaignId = $matchedCampaign ? $matchedCampaign->campaign_id : null;
            $latestCampaign = $campaignId ? $googleCampaigns->where('campaign_id', $campaignId)
                ->sortByDesc('date')
                ->first() : null;
            $row['campaignBudgetAmount'] = $latestCampaign && $latestCampaign->budget_amount_micros 
                ? $latestCampaign->budget_amount_micros / 1000000 
                : null;
            $row['status'] = $matchedCampaign ? ($matchedCampaign->campaign_status ?? null) : null;

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

            // Calculate UB7 to filter only over-utilized campaigns (UB7 > 90%)
            // This matches the frontend filter logic in google-shopping-over-utilize.blade.php
            $budget = $row['campaignBudgetAmount'] ?? 0;
            $spend_L7 = $row['spend_L7'] ?? 0;
            $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;

            // Fixed: Use !empty() instead of != '' to properly handle null values
            // Only include campaigns with UB7 > 90% (over-utilized) for this page
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
            if ($filterType === 'over_utilize' && $ub7 > 90) {
                $filteredCampaignIds[] = $campaignId;
            } elseif ($filterType === 'under_utilize' && $ub7 < 70) {
                $filteredCampaignIds[] = $campaignId;
            }
        }

        return array_unique($filteredCampaignIds);
    }
}
