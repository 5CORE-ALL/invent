<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class Ebay3UtilizedAdsController extends Controller
{
    public function ebay3OverUtilizedAdsView()
    {
        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_3_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        // Create array for all 30 days with data or zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            if (isset($data[$date])) {
                $row = $data[$date];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return view('campaign.ebay-three.over-utilized-ads', compact('dates', 'clicks', 'spend', 'acos', 'cvr'))
            ->with('ad_sales', $adSales)
            ->with('ad_sold', $adSold);
    }

    public function ebay3UnderUtilizedAdsView()
    {
        return view('campaign.ebay-three.under-utilized-ads');
    }

    public function ebay3CorrectlyUtilizedAdsView()
    {
        return view('campaign.ebay-three.correctly-utilized-ads');
    }

    public function getEbay3UtilizedAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];
        $campaignMap = []; // Group by campaign_id to avoid duplicates

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $nrValue = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                }
            }

            // Skip if NR is NRA
            if ($nrValue == 'NRA') {
                continue;
            }

            $matchedReports = $reports->filter(function ($item) use ($sku) {
                $campaignSku = strtoupper(trim($item->campaign_name ?? ''));
                return $campaignSku === $sku;
            });

            if ($matchedReports->isEmpty()) {
                continue;
            }

            // Group reports by campaign_id to combine L7, L1, L30 data
            foreach ($matchedReports as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                
                if (empty($campaignId)) {
                    continue;
                }

                // Create or get existing row for this campaign
                if (!isset($campaignMap[$campaignId])) {
                    $campaignMap[$campaignId] = [
                        'parent' => $parent,
                        'sku' => $pm->sku,
                        'campaign_id' => $campaignId,
                        'campaignName' => $campaign->campaign_name ?? '',
                        'campaignBudgetAmount' => $campaign->campaignBudgetAmount ?? 0,
                        'campaignStatus' => $campaign->campaignStatus ?? '',
                        'INV' => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                        'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                        'l7_spend' => 0,
                        'l7_cpc' => 0,
                        'l1_spend' => 0,
                        'l1_cpc' => 0,
                        'acos' => 0,
                        'adFees' => 0,
                        'sales' => 0,
                        'NR' => $nrValue,
                    ];
                }

                $reportRange = $campaign->report_range ?? '';
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $campaign->cost_per_click ?? '0');

                // Set L7 data
                if ($reportRange == 'L7') {
                    $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    $campaignMap[$campaignId]['l7_cpc'] = $cpc;
                }

                // Set L1 data
                if ($reportRange == 'L1') {
                    $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    $campaignMap[$campaignId]['l1_cpc'] = $cpc;
                }

                // Calculate ACOS from L30 data (or use the latest available)
                if ($reportRange == 'L30') {
                    $campaignMap[$campaignId]['adFees'] = $adFees;
                    $campaignMap[$campaignId]['sales'] = $sales;
                    
                    if ($sales > 0) {
                        $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$campaignId]['acos'] = 100;
                    }
                }
            }
        }

        // Convert map to array
        foreach ($campaignMap as $campaignId => $row) {
            $result[] = (object) $row;
        }

        // Calculate total ACOS from ALL RUNNING campaigns (L30 data)
        $allL30Campaigns = Ebay3PriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpendAll = 0;
        $totalSalesAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
            $totalSpendAll += $adFees;
            $totalSalesAll += $sales;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'total_l30_spend' => round($totalSpendAll, 2),
            'total_l30_sales' => round($totalSalesAll, 2),
            'total_acos' => round($totalACOSAll, 2),
            'status' => 200,
        ]);
    }

    public function updateEbay3NrData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $ebayDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);

        $jsonData = $ebayDataView->value ?? [];

        $jsonData[$field] = $value;

        $ebayDataView->value = $jsonData;
        $ebayDataView->save();

        return response()->json([
            'status' => 200,
            'message' => "Field updated successfully",
            'updated_json' => $jsonData
        ]);
    }

    public function filterOverUtilizedAds(Request $request)
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $data = DB::table('ebay_3_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get();

        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        $totalClicks = 0;
        $totalSpend = 0;
        $totalAdSales = 0;
        $totalAdSold = 0;

        foreach ($data as $row) {
            $dates[] = $row->report_date;
            
            $clicksVal = (int) $row->clicks;
            $spendVal = (float) $row->spend;
            $salesVal = (float) $row->ad_sales;
            $soldVal = (int) $row->ad_sold;

            $clicks[] = $clicksVal;
            $spend[] = $spendVal;
            $adSales[] = $salesVal;
            $adSold[] = $soldVal;

            $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
            $acos[] = round($acosVal, 2);

            $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
            $cvr[] = round($cvrVal, 2);

            $totalClicks += $clicksVal;
            $totalSpend += $spendVal;
            $totalAdSales += $salesVal;
            $totalAdSold += $soldVal;
        }

        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'ad_sales' => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr,
            'totals' => [
                'clicks' => $totalClicks,
                'spend' => $totalSpend,
                'ad_sales' => $totalAdSales,
                'ad_sold' => $totalAdSold,
            ]
        ]);
    }

    public function getCampaignChartData(Request $request)
    {
        $campaignName = $request->input('campaignName');

        $data = DB::table('ebay_3_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('campaign_name', $campaignName)
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', \Carbon\Carbon::now()->subDays(30))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        // Fill all 30 days with data or zeros
        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            if (isset($data[$date])) {
                $row = $data[$date];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'ad_sales' => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr
        ]);
    }

    /**
     * Get eBay3 access token
     */
    private function getEbay3AccessToken()
    {
        if (Cache::has('ebay3_access_token')) {
            return Cache::get('ebay3_access_token');
        }

        $clientId = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');

        $scope = implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly'
        ]);

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('EBAY_3_REFRESH_TOKEN'),
                    'scope' => $scope,
                ]);

            if ($response->successful()) {
                $accessToken = $response->json()['access_token'];
                $expiresIn = $response->json()['expires_in'] ?? 7200;
                
                Cache::put('ebay3_access_token', $accessToken, $expiresIn - 60);
                Log::info('eBay3 token', ['response' => 'Token generated!']);
                
                return $accessToken;
            }

            Log::error('eBay3 token refresh error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('eBay3 token refresh exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get ad groups for a campaign
     */
    private function getAdGroups($campaignId)
    {
        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            Log::error("No access token available for fetching ad groups");
            return ['adGroups' => []];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(120)
                ->retry(3, 5000)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group");

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Successfully fetched ad groups for campaign {$campaignId}", [
                    'ad_groups_count' => count($data['adGroups'] ?? [])
                ]);
                return $data;
            }

            // If token expired, try refreshing
            if ($response->status() === 401) {
                Log::info("Token expired, refreshing for campaign {$campaignId}");
                Cache::forget('ebay3_access_token');
                $accessToken = $this->getEbay3AccessToken();
                if ($accessToken) {
                    $response = Http::withToken($accessToken)
                        ->timeout(120)
                        ->retry(3, 5000)
                        ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group");
                    if ($response->successful()) {
                        $data = $response->json();
                        Log::info("Successfully fetched ad groups after token refresh for campaign {$campaignId}");
                        return $data;
                    }
                }
            }

            Log::error("Failed to fetch ad groups for campaign {$campaignId}", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error("Exception fetching ad groups for campaign {$campaignId}: " . $e->getMessage());
        }

        return ['adGroups' => []];
    }

    /**
     * Get keywords for an ad group
     */
    private function getKeywords($campaignId, $adGroupId)
    {
        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return [];
        }

        $keywords = [];
        $offset = 0;
        $limit = 200;

        do {
            try {
                $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/keyword?ad_group_ids={$adGroupId}&keyword_status=ACTIVE&limit={$limit}&offset={$offset}";
                
                $response = Http::withToken($accessToken)
                    ->timeout(120)
                    ->retry(3, 5000)
                    ->get($endpoint);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['keywords']) && is_array($data['keywords'])) {
                        foreach ($data['keywords'] as $k) {
                            $keywords[] = $k['keywordId'] ?? $k['id'] ?? null;
                        }
                    }

                    $total = $data['total'] ?? count($keywords);
                    $offset += $limit;
                    
                    Log::debug("Fetched keywords for campaign {$campaignId}, ad group {$adGroupId}", [
                        'offset' => $offset - $limit,
                        'count' => count($data['keywords'] ?? []),
                        'total' => $total
                    ]);
                } else {
                    // If token expired, try refreshing
                    if ($response->status() === 401) {
                        Cache::forget('ebay3_access_token');
                        $accessToken = $this->getEbay3AccessToken();
                        if (!$accessToken) {
                            break;
                        }
                        continue;
                    }
                    break;
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching keywords for campaign {$campaignId}, ad group {$adGroupId}: " . $e->getMessage());
                break;
            }
        } while ($offset < ($data['total'] ?? 0));

        return array_filter($keywords);
    }

    /**
     * Update keyword bids for eBay3 campaigns (for automated command)
     */
    public function updateAutoKeywordsBidDynamic(array $campaignIds, array $newBids)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve eBay3 access token',
                'status' => 500
            ]);
        }

        $results = [];
        $hasError = false;

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            Log::info("Processing campaign {$campaignId} with bid {$newBid}");

            $adGroups = $this->getAdGroups($campaignId);
            if (!isset($adGroups['adGroups']) || empty($adGroups['adGroups'])) {
                Log::warning("No ad groups found for campaign {$campaignId}");
                $results[] = [
                    "campaign_id" => $campaignId,
                    "status"      => "error",
                    "message"     => "No ad groups found",
                ];
                continue;
            }

            Log::info("Found " . count($adGroups['adGroups']) . " ad groups for campaign {$campaignId}");

            foreach ($adGroups['adGroups'] as $adGroup) {
                $adGroupId = $adGroup['adGroupId'];
                $keywords = $this->getKeywords($campaignId, $adGroupId);

                if (empty($keywords)) {
                    Log::warning("No keywords found for campaign {$campaignId}, ad group {$adGroupId}");
                    continue;
                }

                Log::info("Found " . count($keywords) . " keywords for campaign {$campaignId}, ad group {$adGroupId}");

                foreach (array_chunk($keywords, 100) as $keywordChunk) {
                    $payload = [
                        "requests" => []
                    ];

                    foreach ($keywordChunk as $keywordId) {
                        $payload["requests"][] = [
                            "bid" => [
                                "currency" => "USD",
                                "value"    => $newBid,
                            ],
                            "keywordId" => $keywordId,
                            "keywordStatus" => "ACTIVE"
                        ];
                    }

                    $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";

                    Log::info("Updating " . count($keywordChunk) . " keywords for campaign {$campaignId}, ad group {$adGroupId} with bid {$newBid}");

                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type'  => 'application/json',
                        ])->post($endpoint, $payload);

                        Log::info("API Response for campaign {$campaignId}: Status " . $response->status(), [
                            'response_body' => $response->body()
                        ]);

                        if ($response->successful()) {
                            $respData = $response->json();
                            
                            // Log the response structure
                            Log::info("Successful response structure", ['response' => $respData]);
                            
                            // Handle different response structures
                            if (isset($respData['responses']) && is_array($respData['responses'])) {
                                // Response has individual keyword responses
                                foreach ($respData['responses'] as $r) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $r['keywordId'] ?? null,
                                        "status"      => $r['status'] ?? "success",
                                        "message"     => $r['message'] ?? "Updated",
                                    ];
                                }
                            } elseif (isset($respData['status']) && $respData['status'] === 'SUCCESS') {
                                // Response indicates success but no individual responses
                                // This means all keywords were updated successfully
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Updated",
                                    ];
                                }
                                Log::info("Bulk update successful for " . count($keywordChunk) . " keywords");
                            } else {
                                // If response structure is different, assume success and log it
                                Log::warning("Unexpected response structure, assuming success", ['response' => $respData]);
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Bulk update completed",
                                    ];
                                }
                            }
                        } else {
                            // If token expired, try refreshing
                            if ($response->status() === 401) {
                                Cache::forget('ebay3_access_token');
                                $accessToken = $this->getEbay3AccessToken();
                                if ($accessToken) {
                                    $response = Http::withHeaders([
                                        'Authorization' => "Bearer {$accessToken}",
                                        'Content-Type'  => 'application/json',
                                    ])->post($endpoint, $payload);
                                    
                                    if ($response->successful()) {
                                        $respData = $response->json();
                                        foreach ($respData['responses'] ?? [] as $r) {
                                            $results[] = [
                                                "campaign_id" => $campaignId,
                                                "ad_group_id" => $adGroupId,
                                                "keyword_id"  => $r['keywordId'] ?? null,
                                                "status"      => $r['status'] ?? "unknown",
                                                "message"     => $r['message'] ?? "Updated",
                                            ];
                                        }
                                        continue;
                                    }
                                }
                            }

                            $hasError = true;
                            $errorBody = $response->json();
                            $errorMessage = "Unknown error";
                            $statusCode = $response->status();
                            
                            if (isset($errorBody['errors']) && is_array($errorBody['errors']) && !empty($errorBody['errors'])) {
                                $errorMessage = $errorBody['errors'][0]['message'] ?? $errorBody['errors'][0]['longMessage'] ?? "Unknown error";
                            } elseif (isset($errorBody['message'])) {
                                $errorMessage = $errorBody['message'];
                            }
                            
                            // Special handling for premium ads campaigns (409 error)
                            if ($statusCode === 409 && str_contains(strtolower($errorMessage), 'premium ads')) {
                                $errorMessage = "Campaign uses Premium Ads (beta feature). Bid updates are not available for this campaign type.";
                                Log::warning("Premium ads campaign detected - bid updates not supported", [
                                    'campaign_id' => $campaignId,
                                    'error' => $errorMessage
                                ]);
                            }
                            
                            Log::error("Failed to update keywords for campaign {$campaignId}", [
                                'status' => $statusCode,
                                'error' => $errorMessage,
                                'response' => $errorBody
                            ]);
                            
                            $results[] = [
                                "campaign_id" => $campaignId,
                                "ad_group_id" => $adGroupId,
                                "status"      => "error",
                                "message"     => $errorMessage,
                                "http_code"   => $statusCode,
                            ];
                        }

                    } catch (\Exception $e) {
                        $hasError = true;
                        Log::error("Exception updating keywords for campaign {$campaignId}", [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $results[] = [
                            "campaign_id" => $campaignId,
                            "ad_group_id" => $adGroupId,
                            "status"      => "error",
                            "message"     => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return response()->json([
            "status" => $hasError ? 207 : 200,
            "message" => $hasError ? "Some keywords failed to update" : "All keyword bids updated successfully",
            "data" => $results
        ]);
    }

    /**
     * Update keyword bids for eBay3 campaigns (for frontend requests)
     */
    public function updateKeywordsBidDynamic(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
        
        $campaignIds = $request->input('campaign_ids', []);
        $newBids = $request->input('bids', []);

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve eBay3 access token',
                'status' => 500
            ]);
        }

        $results = [];
        $hasError = false;

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            Log::info("Processing campaign {$campaignId} with bid {$newBid}");

            $adGroups = $this->getAdGroups($campaignId);
            if (!isset($adGroups['adGroups']) || empty($adGroups['adGroups'])) {
                Log::warning("No ad groups found for campaign {$campaignId}");
                $results[] = [
                    "campaign_id" => $campaignId,
                    "status"      => "error",
                    "message"     => "No ad groups found",
                ];
                continue;
            }

            Log::info("Found " . count($adGroups['adGroups']) . " ad groups for campaign {$campaignId}");

            foreach ($adGroups['adGroups'] as $adGroup) {
                $adGroupId = $adGroup['adGroupId'];
                $keywords = $this->getKeywords($campaignId, $adGroupId);

                if (empty($keywords)) {
                    Log::warning("No keywords found for campaign {$campaignId}, ad group {$adGroupId}");
                    continue;
                }

                Log::info("Found " . count($keywords) . " keywords for campaign {$campaignId}, ad group {$adGroupId}");

                foreach (array_chunk($keywords, 100) as $keywordChunk) {
                    $payload = [
                        "requests" => []
                    ];

                    foreach ($keywordChunk as $keywordId) {
                        $payload["requests"][] = [
                            "bid" => [
                                "currency" => "USD",
                                "value"    => $newBid,
                            ],
                            "keywordId" => $keywordId,
                            "keywordStatus" => "ACTIVE"
                        ];
                    }

                    $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";

                    Log::info("Updating " . count($keywordChunk) . " keywords for campaign {$campaignId}, ad group {$adGroupId} with bid {$newBid}");

                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type'  => 'application/json',
                        ])->post($endpoint, $payload);

                        Log::info("API Response for campaign {$campaignId}: Status " . $response->status(), [
                            'response_body' => $response->body()
                        ]);

                        if ($response->successful()) {
                            $respData = $response->json();
                            
                            // Log the response structure
                            Log::info("Successful response structure", ['response' => $respData]);
                            
                            // Handle different response structures
                            if (isset($respData['responses']) && is_array($respData['responses'])) {
                                // Response has individual keyword responses
                                foreach ($respData['responses'] as $r) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $r['keywordId'] ?? null,
                                        "status"      => $r['status'] ?? "success",
                                        "message"     => $r['message'] ?? "Updated",
                                    ];
                                }
                            } elseif (isset($respData['status']) && $respData['status'] === 'SUCCESS') {
                                // Response indicates success but no individual responses
                                // This means all keywords were updated successfully
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Updated",
                                    ];
                                }
                                Log::info("Bulk update successful for " . count($keywordChunk) . " keywords");
                            } else {
                                // If response structure is different, assume success and log it
                                Log::warning("Unexpected response structure, assuming success", ['response' => $respData]);
                                foreach ($keywordChunk as $keywordId) {
                                    $results[] = [
                                        "campaign_id" => $campaignId,
                                        "ad_group_id" => $adGroupId,
                                        "keyword_id"  => $keywordId,
                                        "status"      => "success",
                                        "message"     => "Bulk update completed",
                                    ];
                                }
                            }
                        } else {
                            // If token expired, try refreshing
                            if ($response->status() === 401) {
                                Cache::forget('ebay3_access_token');
                                $accessToken = $this->getEbay3AccessToken();
                                if ($accessToken) {
                                    $response = Http::withHeaders([
                                        'Authorization' => "Bearer {$accessToken}",
                                        'Content-Type'  => 'application/json',
                                    ])->post($endpoint, $payload);
                                    
                                    if ($response->successful()) {
                                        $respData = $response->json();
                                        if (isset($respData['responses']) && is_array($respData['responses'])) {
                                            foreach ($respData['responses'] as $r) {
                                                $results[] = [
                                                    "campaign_id" => $campaignId,
                                                    "ad_group_id" => $adGroupId,
                                                    "keyword_id"  => $r['keywordId'] ?? null,
                                                    "status"      => $r['status'] ?? "success",
                                                    "message"     => $r['message'] ?? "Updated",
                                                ];
                                            }
                                        } else {
                                            foreach ($keywordChunk as $keywordId) {
                                                $results[] = [
                                                    "campaign_id" => $campaignId,
                                                    "ad_group_id" => $adGroupId,
                                                    "keyword_id"  => $keywordId,
                                                    "status"      => "success",
                                                    "message"     => "Updated",
                                                ];
                                            }
                                        }
                                        continue;
                                    }
                                }
                            }

                            $hasError = true;
                            $errorBody = $response->json();
                            $errorMessage = "Unknown error";
                            $statusCode = $response->status();
                            
                            if (isset($errorBody['errors']) && is_array($errorBody['errors']) && !empty($errorBody['errors'])) {
                                $errorMessage = $errorBody['errors'][0]['message'] ?? $errorBody['errors'][0]['longMessage'] ?? "Unknown error";
                            } elseif (isset($errorBody['message'])) {
                                $errorMessage = $errorBody['message'];
                            }
                            
                            // Special handling for premium ads campaigns (409 error)
                            if ($statusCode === 409 && str_contains(strtolower($errorMessage), 'premium ads')) {
                                $errorMessage = "Campaign uses Premium Ads (beta feature). Bid updates are not available for this campaign type.";
                                Log::warning("Premium ads campaign detected - bid updates not supported", [
                                    'campaign_id' => $campaignId,
                                    'error' => $errorMessage
                                ]);
                            }
                            
                            Log::error("Failed to update keywords for campaign {$campaignId}", [
                                'status' => $statusCode,
                                'error' => $errorMessage,
                                'response' => $errorBody
                            ]);
                            
                            $results[] = [
                                "campaign_id" => $campaignId,
                                "ad_group_id" => $adGroupId,
                                "status"      => "error",
                                "message"     => $errorMessage,
                                "http_code"   => $statusCode,
                            ];
                        }

                    } catch (\Exception $e) {
                        $hasError = true;
                        Log::error("Exception updating keywords for campaign {$campaignId}", [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $results[] = [
                            "campaign_id" => $campaignId,
                            "ad_group_id" => $adGroupId,
                            "status"      => "error",
                            "message"     => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return response()->json([
            "status" => $hasError ? 207 : 200,
            "message" => $hasError ? "Some keywords failed to update" : "All keyword bids updated successfully",
            "data" => $results
        ]);
    }

    /**
     * Test method to check bid update for a specific campaign
     */
    public function testBidUpdate($campaignId, $bid)
    {
        Log::info("Testing bid update for campaign {$campaignId} with bid {$bid}");
        
        // Get access token
        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve eBay3 access token'
            ];
        }
        
        // Get ad groups
        $adGroups = $this->getAdGroups($campaignId);
        Log::info("Ad groups for campaign {$campaignId}:", ['ad_groups' => $adGroups]);
        
        if (!isset($adGroups['adGroups']) || empty($adGroups['adGroups'])) {
            return [
                'success' => false,
                'message' => 'No ad groups found for this campaign',
                'campaign_id' => $campaignId
            ];
        }
        
        $allKeywords = [];
        $adGroupDetails = [];
        
        // Get keywords for each ad group
        foreach ($adGroups['adGroups'] as $adGroup) {
            $adGroupId = $adGroup['adGroupId'];
            $keywords = $this->getKeywords($campaignId, $adGroupId);
            
            $adGroupDetails[] = [
                'ad_group_id' => $adGroupId,
                'ad_group_name' => $adGroup['adGroupName'] ?? 'N/A',
                'keyword_count' => count($keywords)
            ];
            
            $allKeywords = array_merge($allKeywords, $keywords);
        }
        
        Log::info("Total keywords found: " . count($allKeywords));
        
        if (empty($allKeywords)) {
            return [
                'success' => false,
                'message' => 'No keywords found for this campaign',
                'campaign_id' => $campaignId,
                'ad_groups' => $adGroupDetails
            ];
        }
        
        // Test update with first 5 keywords only (for testing)
        $testKeywords = array_slice($allKeywords, 0, 5);
        
        $payload = [
            "requests" => []
        ];
        
        foreach ($testKeywords as $keywordId) {
            $payload["requests"][] = [
                "bid" => [
                    "currency" => "USD",
                    "value"    => floatval($bid),
                ],
                "keywordId" => $keywordId,
                "keywordStatus" => "ACTIVE"
            ];
        }
        
        $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";
        
        Log::info("Testing bid update with payload:", ['endpoint' => $endpoint, 'payload' => $payload]);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ])->post($endpoint, $payload);
            
            $responseData = $response->json();
            $statusCode = $response->status();
            
            Log::info("API Response:", [
                'status_code' => $statusCode,
                'response' => $responseData
            ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Bid update test successful',
                    'campaign_id' => $campaignId,
                    'bid' => $bid,
                    'keywords_tested' => count($testKeywords),
                    'total_keywords' => count($allKeywords),
                    'ad_groups' => $adGroupDetails,
                    'response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Bid update failed',
                    'campaign_id' => $campaignId,
                    'status_code' => $statusCode,
                    'error' => $responseData['errors'][0]['message'] ?? 'Unknown error',
                    'response' => $responseData
                ];
            }
        } catch (\Exception $e) {
            Log::error("Exception during bid update test: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception occurred',
                'error' => $e->getMessage()
            ];
        }
    }

}
