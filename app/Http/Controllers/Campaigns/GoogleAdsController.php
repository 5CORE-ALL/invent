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
            ->where('campaign_advertising_channel_type', 'SEARCH')
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
            ->where('campaign_advertising_channel_type', 'SEARCH')
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
            ->where('campaign_advertising_channel_type', 'PERFORMANCE_MAX')
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
        
        // Fetch campaigns data within the maximum date range needed (L30)
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
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;
            $shopify = $shopifyData[$sku] ?? null;

            // Find the latest campaign for this SKU
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
            
            $row['campaign_id'] = $matchedCampaign->campaign_id ?? null;
            $row['campaignName'] = $matchedCampaign->campaign_name ?? null;
            $row['campaignBudgetAmount'] = $matchedCampaign->budget_amount_micros ?? null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['status'] = $matchedCampaign->campaign_status ?? null;

            // Aggregate metrics for each date range
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

            if($row['campaignName'] != '') {
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

            $shopify = $shopifyData[$sku] ?? null;

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
            
            $row['campaign_id'] = $matchedCampaign->campaign_id ?? null;
            $row['campaignName'] = $matchedCampaign->campaign_name ?? null;
            $row['campaignBudgetAmount'] = $matchedCampaign->budget_amount_micros ?? null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['campaignStatus'] = $matchedCampaign->campaign_status ?? null;

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

            if($row['campaignName'] != '') {
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
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;

            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                return $exactMatch;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            
            $row['campaign_id'] = $matchedCampaign->campaign_id ?? null;
            $row['campaignName'] = $matchedCampaign->campaign_name ?? null;
            $row['campaignBudgetAmount'] = $matchedCampaign->budget_amount_micros ?? null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['status'] = $matchedCampaign->campaign_status ?? null;

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

            if($row['campaignName'] != '') {
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

            $shopify = $shopifyData[$sku] ?? null;

            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                return $exactMatch;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaign->campaign_id ?? null;
            $row['campaignName'] = $matchedCampaign->campaign_name ?? null;
            $row['campaignBudgetAmount'] = $matchedCampaign->budget_amount_micros ?? null;
            $row['campaignBudgetAmount'] = $row['campaignBudgetAmount'] ? $row['campaignBudgetAmount'] / 1000000 : null;
            $row['campaignStatus'] = $matchedCampaign->campaign_status ?? null;

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

            if($row['campaignName'] != '') {
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

        $googleCampaigns = DB::table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
            )
            ->get();

        $googleCampaignsKeyed = $googleCampaigns->keyBy(function ($item) {
            return strtoupper(trim($item->campaign_name));
        });

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaign = $googleCampaignsKeyed[$sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku'] = $pm->sku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaign->campaign_id ?? null;
            $row['campaignName'] = $matchedCampaign->campaign_name ?? null;
            $row['campaignStatus'] = $matchedCampaign->campaign_status ?? null;

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
        return $this->getChartData($request);
    }

    public function filterGoogleShoppingUnderChart(Request $request)
    {
        return $this->getChartData($request);
    }

    public function filterGoogleShoppingReportChart(Request $request)
    {
        return $this->getChartData($request);
    }

    public function filterGoogleSerpChart(Request $request)
    {
        return $this->getChartData($request, ['campaign_advertising_channel_type' => 'SEARCH']);
    }

    public function filterGoogleSerpReportChart(Request $request)
    {
        return $this->getChartData($request, ['campaign_advertising_channel_type' => 'SEARCH']);
    }

    public function filterGooglePmaxChart(Request $request)
    {
        return $this->getChartData($request, ['campaign_advertising_channel_type' => 'PERFORMANCE_MAX']);
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
}
