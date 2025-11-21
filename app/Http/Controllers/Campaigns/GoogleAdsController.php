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
    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }


    public function index(){
        return view('campaign.google-shopping-ads');
    }

    public function googleShoppingAdsRunning(){
        return view('campaign.google-shopping-ads-running');
    }

    public function googleOverUtilizeView(){
        return view('campaign.google-shopping-over-utilize');
    }

    public function googleUnderUtilizeView(){
        return view('campaign.google-shopping-under-utilize');
    }

    public function googleShoppingAdsReport(){
        return view('campaign.google.google-shopping-ads-report');
    }

    public function getAdvShopifyGShoppingSaveData(Request $request)
    {
        return ADVMastersData::getAdvShopifyGShoppingSaveDataProceed($request);
    }

    public function googleSerpView(){
        return view('campaign.google-shopping-ads-serp');
    }

    public function googleSerpReportView(){
        return view('campaign.google.google-serp-ads-report');
    }

    public function googlePmaxView(){
        return view('campaign.google-shopping-ads-pmax');
    }

    public function getGoogleSearchAdsData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $googleCampaigns = DB::connection('apicentral')
            ->table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'range_type',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereIn('range_type', ['L1', 'L7', 'L30']) 
            ->get();

        $ranges = ['L1', 'L7', 'L30'];

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;

            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                if (!str_ends_with($campaign, 'SEARCH.')) {
                    return false;
                }
                
                $contains = strpos($campaign, $skuTrimmed) !== false;
                
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                return ($contains || $exactMatch) && $c->campaign_status === 'ENABLED';
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

            foreach ($ranges as $range) {
                $campaignRange = $googleCampaigns->first(function ($c) use ($sku, $range) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    $contains = strpos($campaign, $skuTrimmed) !== false;
                    
                    $parts = array_map('trim', explode(',', $campaign));
                    $exactMatch = in_array($skuTrimmed, $parts);
                    
                    return ($contains || $exactMatch) && $c->range_type === $range && $c->campaign_status === 'ENABLED';
                });


                $row["spend_$range"] = isset($campaignRange->metrics_cost_micros)
                    ? $campaignRange->metrics_cost_micros / 1000000
                    : 0;

                $row["clicks_$range"] = $campaignRange->metrics_clicks ?? 0;
                $row["impressions_$range"] = $campaignRange->metrics_impressions ?? 0;
                $row["cpc_$range"] = $row["clicks_$range"] ? $row["spend_$range"] / $row["clicks_$range"] : 0;
                $row["ad_sales_$range"] = $campaignRange->ga4_ad_sales ?? 0;
                $row["ad_sold_$range"] = $campaignRange->ga4_sold_units ?? 0;
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

        $googleCampaigns = DB::connection('apicentral')
            ->table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'range_type',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereIn('range_type', ['L7', 'L15', 'L30', 'L60'])
            ->get();

        $ranges = ['L7', 'L15', 'L30', 'L60'];

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;

            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                if (!str_ends_with($campaign, 'SEARCH.')) {
                    return false;
                }
                
                $contains = strpos($campaign, $skuTrimmed) !== false;
                
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                return ($contains || $exactMatch) && $c->campaign_status === 'ENABLED';
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

            foreach ($ranges as $range) {
                $campaignRange = $googleCampaigns->first(function ($c) use ($sku, $range) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    $contains = strpos($campaign, $skuTrimmed) !== false;
                    
                    $parts = array_map('trim', explode(',', $campaign));
                    $exactMatch = in_array($skuTrimmed, $parts);
                    
                    return ($contains || $exactMatch) && $c->range_type === $range && $c->campaign_status === 'ENABLED';
                });


                $row["spend_$range"] = isset($campaignRange->metrics_cost_micros)
                    ? $campaignRange->metrics_cost_micros / 1000000
                    : 0;

                $row["clicks_$range"] = $campaignRange->metrics_clicks ?? 0;
                $row["impressions_$range"] = $campaignRange->metrics_impressions ?? 0;
                $row["cpc_$range"] = $row["clicks_$range"] ? $row["spend_$range"] / $row["clicks_$range"] : 0;
                $row["ad_sales_$range"] = $campaignRange->ga4_ad_sales ?? 0;
                $row["ad_sold_$range"] = $campaignRange->ga4_sold_units ?? 0;
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

        $googleCampaigns = DB::connection('apicentral')
            ->table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'range_type',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereIn('range_type', ['L1', 'L7', 'L30']) 
            ->get();

        $ranges = ['L1', 'L7', 'L30'];

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

            foreach ($ranges as $range) {
                $campaignRange = $googleCampaigns->first(function ($c) use ($sku, $range) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    $contains = strpos($campaign, $skuTrimmed) !== false;
                    
                    $parts = array_map('trim', explode(',', $campaign));
                    $exactMatch = in_array($skuTrimmed, $parts);
                    return ($contains || $exactMatch) && $c->range_type === $range && $c->campaign_status === 'ENABLED';
                });


                $row["spend_$range"] = isset($campaignRange->metrics_cost_micros)
                    ? $campaignRange->metrics_cost_micros / 1000000
                    : 0;

                $row["clicks_$range"] = $campaignRange->metrics_clicks ?? 0;
                $row["impressions_$range"] = $campaignRange->metrics_impressions ?? 0;
                $row["cpc_$range"] = $row["clicks_$range"] ? $row["spend_$range"] / $row["clicks_$range"] : 0;
                $row["ad_sales_$range"] = $campaignRange->ga4_ad_sales ?? 0;
                $row["ad_sold_$range"] = $campaignRange->ga4_sold_units ?? 0;
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

        $googleCampaigns = DB::connection('apicentral')
            ->table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'range_type',
                'metrics_cost_micros',
                'metrics_clicks',
                'metrics_impressions',
                'ga4_sold_units',
                'ga4_ad_sales'
            )
            ->whereIn('range_type', ['L7', 'L15', 'L30', 'L60'])
            ->get();

        $ranges = ['L7', 'L15', 'L30', 'L60'];

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

            foreach ($ranges as $range) {
                $campaignRange = $googleCampaigns->first(function ($c) use ($sku, $range) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    $contains = strpos($campaign, $skuTrimmed) !== false;
                    
                    $parts = array_map('trim', explode(',', $campaign));
                    $exactMatch = in_array($skuTrimmed, $parts);
                    
                    return ($contains || $exactMatch) && $c->range_type === $range;
                });


                $row["spend_$range"] = isset($campaignRange->metrics_cost_micros)
                    ? $campaignRange->metrics_cost_micros / 1000000
                    : 0;

                $row["clicks_$range"] = $campaignRange->metrics_clicks ?? 0;
                $row["impressions_$range"] = $campaignRange->metrics_impressions ?? 0;
                $row["cpc_$range"] = $row["clicks_$range"] ? $row["spend_$range"] / $row["clicks_$range"] : 0;
                $row["ad_sales_$range"] = $campaignRange->ga4_ad_sales ?? 0;
                $row["ad_sold_$range"] = $campaignRange->ga4_sold_units ?? 0;
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
        return view('campaign.google.google-shopping-missing-ads');
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

        $googleCampaigns = DB::connection('apicentral')
            ->table('google_ads_campaigns')
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
}
