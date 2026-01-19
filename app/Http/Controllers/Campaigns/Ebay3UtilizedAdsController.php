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
use Exception;

class Ebay3UtilizedAdsController extends Controller
{
    /**
     * Get Ebay3 access token
     */
    private function getEbay3AccessToken()
    {
        if (Cache::has('ebay3_access_token')) {
            return Cache::get('ebay3_access_token');
        }

        $clientId = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');
        $refreshToken = env('EBAY_3_REFRESH_TOKEN');
        $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";

        $postFields = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => 'https://api.ebay.com/oauth/api_scope/sell.marketing'
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic " . base64_encode("$clientId:$clientSecret")
            ],
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['access_token'])) {
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 7200;

            Cache::put('ebay3_access_token', $accessToken, $expiresIn - 60);

            return $accessToken;
        }

        throw new Exception("Failed to refresh token: " . json_encode($data));
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
     * Update keyword bids for Ebay3 campaigns (for automated command)
     */
    public function updateAutoKeywordsBidDynamic(array $campaignIds, array $newBids)
    {
        // Set longer timeout for API operations (10 minutes per batch)
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $accessToken = $this->getEbay3AccessToken();
        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve Ebay3 access token',
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
                        $response = Http::timeout(120) // 2 minute timeout per request
                            ->withHeaders([
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
     * Update keyword bids for Ebay3 campaigns (for frontend requests)
     */
    public function updateKeywordsBidDynamic(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
        
        $campaignIds = $request->input('campaign_ids', []);
        $newBids = $request->input('bids', []);

        $accessToken = $this->getEbay3AccessToken();
        $results = [];
        $hasError = false;
        $successfulCampaigns = []; // Track campaigns with successful bid updates

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);
            $campaignSuccess = false;

            $adGroups = $this->getAdGroups($campaignId);
            if (!isset($adGroups['adGroups'])) {
                continue;
            }

            foreach ($adGroups['adGroups'] as $adGroup) {
                $adGroupId = $adGroup['adGroupId'];
                $keywords = $this->getKeywords($campaignId, $adGroupId);

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

                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type'  => 'application/json',
                        ])->post($endpoint, $payload);

                        if ($response->successful()) {
                            $campaignSuccess = true;
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
                        } else {
                            $hasError = true;
                            $results[] = [
                                "campaign_id" => $campaignId,
                                "ad_group_id" => $adGroupId,
                                "status"      => "error",
                                "message"     => $response->json()['errors'][0]['message'] ?? "Unknown error",
                                "http_code"   => $response->status(),
                            ];
                        }

                    } catch (\Exception $e) {
                        $hasError = true;
                        $results[] = [
                            "campaign_id" => $campaignId,
                            "ad_group_id" => $adGroupId,
                            "status"      => "error",
                            "message"     => $e->getMessage(),
                        ];
                    }
                }
            }
            
            // Track successful campaigns for apprSbid update
            if ($campaignSuccess && $newBid > 0) {
                $successfulCampaigns[$campaignId] = $newBid;
            }
        }

        // Save apprSbid for successfully updated campaigns
        if (!empty($successfulCampaigns)) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            foreach ($successfulCampaigns as $campaignId => $bidValue) {
                // Try to update yesterday's records first
                $updated = DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_range', $yesterday)
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'apprSbid' => (string)$bidValue
                    ]);
                
                // If no record found for yesterday, try L1
                if ($updated === 0) {
                    DB::table('ebay_3_priority_reports')
                        ->where('campaign_id', $campaignId)
                        ->where('report_range', 'L1')
                        ->where('campaignStatus', 'RUNNING')
                        ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                        ->where('campaign_name', 'NOT LIKE', 'General - %')
                        ->where('campaign_name', 'NOT LIKE', 'Default%')
                        ->update([
                            'apprSbid' => (string)$bidValue
                        ]);
                }
                
                // Also update L7 and L30 records for consistency
                DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_range', ['L7', 'L30'])
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'apprSbid' => (string)$bidValue
                    ]);
            }
        }

        return response()->json([
            "status" => $hasError ? 207 : 200,
            "message" => $hasError ? "Some keywords failed to update" : "All keyword bids updated successfully",
            "data" => $results
        ]);
    }

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

    public function ebay3UtilizedView()
    {
        return view('campaign.ebay-three.ebay3-utilized');
    }

    public function getEbay3UtilizedAdsData()
    {
        // SKU normalization function to handle spaces and whitespace
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse multiple spaces to single space
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace characters
            return trim($sku);
        };
        
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        
        // Fetch Shopify data with normalized SKU matching
        $shopifyDataRaw = ShopifySku::whereIn('sku', $skus)->get();
        $shopifyData = [];
        foreach ($shopifyDataRaw as $shopify) {
            // Store with normalized key for matching
            $normalizedKey = $normalizeSku($shopify->sku);
            $shopifyData[$normalizedKey] = $shopify;
        }
        
        // Fetch eBay metric data with normalized SKU matching
        $ebayMetricDataRaw = Ebay3Metric::whereIn('sku', $skus)->get();
        $ebayMetricData = [];
        foreach ($ebayMetricDataRaw as $ebay) {
            // Store with normalized key for matching
            $normalizedKey = $normalizeSku($ebay->sku);
            $ebayMetricData[$normalizedKey] = $ebay;
        }
        
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];
        $campaignMap = [];
        $ebaySkuSet = []; // Track unique SKUs in eBay for count

        // Calculate child SKUs INV sum for each PARENT SKU
        $childInvSumByParent = [];
        foreach ($productMasters as $pm) {
            // Skip PARENT SKUs - only process child SKUs
            if (stripos($pm->sku, 'PARENT') !== false) {
                continue;
            }
            
            // Get child SKU's INV value
            $normalizedChildSku = $normalizeSku($pm->sku);
            $childShopify = $shopifyData[$normalizedChildSku] ?? null;
            if (!$childShopify) {
                $childShopify = ShopifySku::where('sku', $pm->sku)->first();
            }
            
            $childInv = ($childShopify && isset($childShopify->inv)) ? (int)$childShopify->inv : 0;
            
            // Sum INV for each parent
            $parentKey = $pm->parent ?? '';
            if (!empty($parentKey)) {
                if (!isset($childInvSumByParent[$parentKey])) {
                    $childInvSumByParent[$parentKey] = 0;
                }
                $childInvSumByParent[$parentKey] += $childInv;
            }
        }

        foreach ($productMasters as $pm) {
            // Only process PARENT SKUs
            if (stripos($pm->sku, 'PARENT') === false) {
                continue;
            }

            // Normalize the SKU for both lookup and campaign matching
            $normalizedSku = $normalizeSku($pm->sku);
            $sku = $normalizedSku; // Use normalized SKU for campaign matching
            $parent = $pm->parent;
            
            // Try to get Shopify data using normalized key
            $shopify = $shopifyData[$normalizedSku] ?? null;
            if (!$shopify) {
                // Fallback: Direct database lookup for edge cases
                $shopify = ShopifySku::where('sku', $pm->sku)->first();
            }
            
            // Try to get eBay metric data using normalized key
            $ebay = $ebayMetricData[$normalizedSku] ?? null;
            if (!$ebay) {
                // Fallback: Direct database lookup for edge cases
                $ebay = Ebay3Metric::where('sku', $pm->sku)->first();
            }

            $nrValue = '';
            $nrlValue = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                    $nrlValue = $raw['NRL'] ?? null;
                }
            }

            $matchedReports = $reports->filter(function ($item) use ($sku, $normalizeSku) {
                $campaignName = $item->campaign_name ?? '';
                $normalizedCampaignName = $normalizeSku($campaignName);
                return $normalizedCampaignName === $sku;
            });

            // Check if campaign exists
            $hasCampaign = false;
            $matchedCampaignL7 = null;
            $matchedCampaignL1 = null;
            $matchedCampaignL30 = null;
            $campaignId = '';
            $campaignName = '';
            $campaignBudgetAmount = 0;
            $campaignStatus = '';

            if (!$matchedReports->isEmpty()) {
                foreach ($matchedReports as $campaign) {
                    $tempCampaignId = $campaign->campaign_id ?? '';
                    // Check if campaign exists (has campaign_id) regardless of status (RUNNING or PAUSED)
                    if (!empty($tempCampaignId)) {
                        $hasCampaign = true;
                        $campaignId = $tempCampaignId;
                        $campaignName = $campaign->campaign_name ?? '';
                        $campaignBudgetAmount = $campaign->campaignBudgetAmount ?? 0;
                        $campaignStatus = $campaign->campaignStatus ?? '';

                        $reportRange = $campaign->report_range ?? '';
                        if ($reportRange == 'L7') {
                            $matchedCampaignL7 = $campaign;
                        }
                        if ($reportRange == 'L1') {
                            $matchedCampaignL1 = $campaign;
                        }
                        if ($reportRange == 'L30') {
                            $matchedCampaignL30 = $campaign;
                        }
                    }
                }
            }

            // Use SKU as key if no campaign, otherwise use campaignId
            $mapKey = !empty($campaignId) ? $campaignId : 'SKU_' . $sku;

            if (!isset($campaignMap[$mapKey])) {
                $price = $ebay->ebay_price ?? 0;
                $ebayL30 = $ebay->ebay_l30 ?? 0;
                $views = $ebay->views ?? 0;
                
                // Track eBay SKU (if has eBay data with price > 0 or campaign)
                if (($ebay && $price > 0) || $hasCampaign) {
                    $ebaySkuSet[$sku] = true;
                }
                
                // Use sum of child SKUs INV for PARENT SKU
                $invValue = $childInvSumByParent[$parent] ?? 0;
                
                $campaignMap[$mapKey] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => $campaignBudgetAmount,
                    'campaignStatus' => $campaignStatus,
                    'INV' => $invValue,
                    'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                    'price' => $price,
                    'ebay_l30' => $ebayL30,
                    'views' => (int)$views,
                    'l7_spend' => 0,
                    'l7_cpc' => 0,
                    'l1_spend' => 0,
                    'l1_cpc' => 0,
                    'acos' => 0,
                    'adFees' => 0,
                    'sales' => 0,
                    'clicks' => 0,
                    'ad_sold' => 0,
                    'cvr' => 0,
                    'NR' => $nrValue,
                    'NRL' => $nrlValue,
                    'hasCampaign' => $hasCampaign,
                ];
            } else {
                // Entry already exists - update inventory with sum of child SKUs INV
                $childInvSum = $childInvSumByParent[$parent] ?? 0;
                $existingInv = (int)($campaignMap[$mapKey]['INV'] ?? 0);
                
                // Use the child SKUs sum (should be same or updated)
                if ($childInvSum != $existingInv) {
                    $campaignMap[$mapKey]['INV'] = $childInvSum;
                }
            }

            // Add campaign data if exists
            if ($matchedCampaignL7) {
                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
                $campaignMap[$mapKey]['l7_spend'] = $adFees;
                $campaignMap[$mapKey]['l7_cpc'] = $cpc;
            }

            if ($matchedCampaignL1) {
                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');
                $campaignMap[$mapKey]['l1_spend'] = $adFees;
                $campaignMap[$mapKey]['l1_cpc'] = $cpc;
            }

            if ($matchedCampaignL30) {
                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
                $clicks = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
                $adSold = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
                $campaignMap[$mapKey]['adFees'] = $adFees;
                $campaignMap[$mapKey]['sales'] = $sales;
                $campaignMap[$mapKey]['clicks'] = $clicks;
                $campaignMap[$mapKey]['ad_sold'] = $adSold;
                
                // Calculate CVR: (attributed_sales / clicks) * 100 (same as Ebay3UtilizedAdsController)
                if ($clicks > 0) {
                    $campaignMap[$mapKey]['cvr'] = round(($adSold / $clicks) * 100, 2);
                } else {
                    $campaignMap[$mapKey]['cvr'] = 0;
                }
                
                if ($sales > 0) {
                    $campaignMap[$mapKey]['acos'] = round(($adFees / $sales) * 100, 2);
                } else if ($adFees > 0 && $sales == 0) {
                    $campaignMap[$mapKey]['acos'] = 100;
                }
            }
        }

        // Process campaigns that don't match ProductMaster SKUs
        $allCampaignIds = $reports->where('campaignStatus', 'RUNNING')->pluck('campaign_id')->unique();
        $processedCampaignIds = array_keys($campaignMap);
        
        // Create a set of all ProductMaster SKUs (normalized) to check for duplicates
        $productMasterSkuSet = [];
        foreach ($productMasters as $pm) {
            $normalizedSku = $normalizeSku($pm->sku);
            $productMasterSkuSet[$normalizedSku] = true;
        }
        
        foreach ($allCampaignIds as $campaignId) {
            if (in_array($campaignId, $processedCampaignIds)) {
                continue;
            }

            $campaignReports = $reports->where('campaign_id', $campaignId)->where('campaignStatus', 'RUNNING');
            if ($campaignReports->isEmpty()) {
                continue;
            }

            $firstCampaign = $campaignReports->first();
            $campaignName = $firstCampaign->campaign_name ?? '';
            
            // Check if this campaign name matches a ProductMaster SKU (avoid duplicates)
            $normalizedCampaignName = $normalizeSku($campaignName);
            if (isset($productMasterSkuSet[$normalizedCampaignName])) {
                // Skip - this campaign is already processed as a ProductMaster SKU
                continue;
            }
            
            $matchedSku = null;
            foreach ($productMasters as $pm) {
                if (strtoupper(trim(rtrim($pm->sku, '.'))) === strtoupper(trim(rtrim($campaignName, '.')))) {
                    $matchedSku = $pm->sku;
                    break;
                }
            }

            $nrValue = '';
            $nrlValue = '';
            if ($matchedSku && isset($nrValues[$matchedSku])) {
                $raw = $nrValues[$matchedSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                    $nrlValue = $raw['NRL'] ?? null;
                }
            }

            // Use normalized SKU for lookups
            $normalizedMatchedSku = $matchedSku ? $normalizeSku($matchedSku) : null;
            $shopify = $normalizedMatchedSku ? ($shopifyData[$normalizedMatchedSku] ?? null) : null;
            $ebay = $normalizedMatchedSku ? ($ebayMetricData[$normalizedMatchedSku] ?? null) : null;
            
            // Try to get price and ebay_l30 from EbayMetric using campaign name as SKU
            $price = 0;
            $ebayL30 = 0;
            $views = 0;
            if ($ebay) {
                $price = $ebay->ebay_price ?? 0;
                $ebayL30 = $ebay->ebay_l30 ?? 0;
                $views = $ebay->views ?? 0;
            } else {
                // Try to find by campaign name
                $ebayMetricByName = Ebay3Metric::where('sku', $campaignName)->first();
                if ($ebayMetricByName) {
                    $price = $ebayMetricByName->ebay_price ?? 0;
                    $ebayL30 = $ebayMetricByName->ebay_l30 ?? 0;
                    $views = $ebayMetricByName->views ?? 0;
                }
            }

            // Track eBay SKU for campaigns not matching ProductMaster SKUs (if has price > 0)
            if ($price > 0) {
                $campaignSkuUpper = strtoupper(trim($campaignName));
                if (!isset($ebaySkuSet[$campaignSkuUpper])) {
                    $ebaySkuSet[$campaignSkuUpper] = true;
                }
            }

            $campaignMap[$campaignId] = [
                'parent' => '',
                'sku' => $campaignName,
                'campaign_id' => $campaignId,
                'campaignName' => $campaignName,
                'campaignBudgetAmount' => $firstCampaign->campaignBudgetAmount ?? 0,
                'campaignStatus' => $firstCampaign->campaignStatus ?? '',
                'INV' => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                'price' => $price,
                'ebay_l30' => $ebayL30,
                'views' => (int)$views,
                'l7_spend' => 0,
                'l7_cpc' => 0,
                'l1_spend' => 0,
                'l1_cpc' => 0,
                'acos' => 0,
                'adFees' => 0,
                'sales' => 0,
                'clicks' => 0,
                'ad_sold' => 0,
                'cvr' => 0,
                'NR' => $nrValue,
                'NRL' => $nrlValue,
                'hasCampaign' => true, // These campaigns always have campaigns
            ];

            foreach ($campaignReports as $campaign) {
                $reportRange = $campaign->report_range ?? '';
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $campaign->cost_per_click ?? '0');

                if ($reportRange == 'L7') {
                    $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    $campaignMap[$campaignId]['l7_cpc'] = $cpc;
                }

                if ($reportRange == 'L1') {
                    $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    $campaignMap[$campaignId]['l1_cpc'] = $cpc;
                }

                if ($reportRange == 'L30') {
                    $clicks = (int) ($campaign->cpc_clicks ?? 0);
                    $adSold = (int) ($campaign->cpc_attributed_sales ?? 0);
                    $campaignMap[$campaignId]['adFees'] = $adFees;
                    $campaignMap[$campaignId]['sales'] = $sales;
                    $campaignMap[$campaignId]['clicks'] = $clicks;
                    $campaignMap[$campaignId]['ad_sold'] = $adSold;
                    
                    // Calculate CVR: (attributed_sales / clicks) * 100 (same as Ebay3UtilizedAdsController)
                    if ($clicks > 0) {
                        $campaignMap[$campaignId]['cvr'] = round(($adSold / $clicks) * 100, 2);
                    } else {
                        $campaignMap[$campaignId]['cvr'] = 0;
                    }
                    
                    if ($sales > 0) {
                        $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$campaignId]['acos'] = 100;
                    }
                }
            }
        }

        // Fetch last 30 days daily data from ebay_3_priority_reports
        // Daily data is stored with report_range as date (format: Y-m-d)
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $today = \Carbon\Carbon::now()->format('Y-m-d');
        
        // Get all unique campaign IDs from campaignMap for efficient filtering
        $allCampaignIds = [];
        foreach ($campaignMap as $row) {
            if (!empty($row['campaign_id'])) {
                $allCampaignIds[] = $row['campaign_id'];
            }
        }
        $allCampaignIds = array_unique($allCampaignIds);
        
        $dailyDataLast30Days = collect();
        if (!empty($allCampaignIds)) {
            $dailyDataLast30Days = DB::table('ebay_3_priority_reports')
                ->select(
                    'campaign_id',
                    'campaign_name',
                    DB::raw('SUM(cpc_clicks) as total_clicks'),
                    DB::raw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend'),
                    DB::raw('SUM(cpc_attributed_sales) as total_ad_sold')
                )
                ->whereRaw("report_range >= ? AND report_range <= ? AND report_range NOT IN ('L7', 'L1', 'L30')", [$thirtyDaysAgo, $today])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->whereIn('campaign_id', $allCampaignIds)
                ->groupBy('campaign_id', 'campaign_name')
                ->get()
                ->keyBy('campaign_id');
        }
        
        // Add last 30 days data to each campaign in campaignMap
        foreach ($campaignMap as $key => $row) {
            $campaignId = $row['campaign_id'] ?? '';
            if (!empty($campaignId) && $dailyDataLast30Days->has($campaignId)) {
                $dailyData = $dailyDataLast30Days[$campaignId];
                $campaignMap[$key]['l30_daily_clicks'] = (int) ($dailyData->total_clicks ?? 0);
                $campaignMap[$key]['l30_daily_spend'] = (float) ($dailyData->total_spend ?? 0);
                $campaignMap[$key]['l30_daily_ad_sold'] = (int) ($dailyData->total_ad_sold ?? 0);
            } else {
                $campaignMap[$key]['l30_daily_clicks'] = 0;
                $campaignMap[$key]['l30_daily_spend'] = 0;
                $campaignMap[$key]['l30_daily_ad_sold'] = 0;
            }
        }

        // Calculate total ACOS from ALL RUNNING campaigns (L30 report_range data)
        $allL30Campaigns = Ebay3PriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpendAll = 0;
        $totalSalesAll = 0;
        $totalClicksAll = 0;
        $totalAdSoldAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
            $clicks = (int) ($campaign->cpc_clicks ?? 0);
            $adSold = (int) ($campaign->cpc_attributed_sales ?? 0);
            $totalSpendAll += $adFees;
            $totalSalesAll += $sales;
            $totalClicksAll += $clicks;
            $totalAdSoldAll += $adSold;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

        // Calculate average ACOS and CVR from campaignMap
        $totalAcos = 0;
        $totalCvr = 0;
        $acosCount = 0;
        $cvrCount = 0;
        
        foreach ($campaignMap as $row) {
            if (isset($row['acos']) && $row['acos'] !== null) {
                $totalAcos += (float) $row['acos'];
                $acosCount++;
            }
            // Only count CVR for campaigns with clicks > 0 (CVR is meaningful only when clicks exist)
            // Check if clicks > 0 to ensure CVR was actually calculated from L30 data
            if (isset($row['clicks']) && $row['clicks'] > 0 && isset($row['cvr'])) {
                $totalCvr += (float) $row['cvr'];
                $cvrCount++;
            }
        }
        
        $avgAcos = $acosCount > 0 ? round($totalAcos / $acosCount, 2) : 0;
        $avgCvr = $cvrCount > 0 ? round($totalCvr / $cvrCount, 2) : 0;

        // Fetch last_sbid from day-before-yesterday's date records
        // This ensures last_sbid shows the PREVIOUS day's calculated SBID, not the current day's
        // Example: On 15-01-2026, we fetch from 13-01-2026 records (which has SBID calculated on 14-01-2026)
        // So last_sbid = previous day's calculated SBID, SBID = current day's calculated SBID
        // This prevents both columns from showing the same value after page refresh
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastSbidMap = [];
        $sbidMMap = [];
        
        $lastSbidReports = Ebay3PriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();
        
        foreach ($lastSbidReports as $report) {
            if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                $lastSbidMap[$report->campaign_id] = $report->last_sbid;
            }
        }

        // Fetch sbid_m from yesterday's records first, then L1 as fallback
        $sbidMReports = Ebay3PriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)
                  ->orWhere('report_range', 'L1');
            })
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                // Prioritize yesterday's records over L1
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        
        foreach ($sbidMReports as $campaignId => $reports) {
            // Get the first report (prioritized by yesterday)
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->sbid_m)) {
                $sbidMMap[$report->campaign_id] = $report->sbid_m;
            }
        }

        // Fetch apprSbid from yesterday's records first, then L1 as fallback
        $apprSbidMap = [];
        $apprSbidReports = Ebay3PriorityReport::where(function($q) use ($yesterday) {
                $q->where('report_range', $yesterday)
                  ->orWhere('report_range', 'L1');
            })
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                // Prioritize yesterday's records over L1
                return $report->report_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        
        foreach ($apprSbidReports as $campaignId => $reports) {
            // Get the first report (prioritized by yesterday)
            $report = $reports->first();
            if (!empty($report->campaign_id) && !empty($report->apprSbid)) {
                $apprSbidMap[$report->campaign_id] = $report->apprSbid;
            }
        }

        // Fetch pink_dil_paused_at from L30 records
        $pinkDilPausedMap = [];
        $pinkDilPausedReports = Ebay3PriorityReport::where('report_range', 'L30')
            ->whereNotNull('pink_dil_paused_at')
            ->get();
        
        foreach ($pinkDilPausedReports as $report) {
            if (!empty($report->campaign_id)) {
                $pinkDilPausedMap[$report->campaign_id] = $report->pink_dil_paused_at ? $report->pink_dil_paused_at->toDateTimeString() : null;
            }
        }

        // Add last_sbid, sbid_m, apprSbid, and pink_dil_paused_at to campaignMap
        foreach ($campaignMap as $key => $row) {
            $campaignId = $row['campaign_id'] ?? '';
            if (!empty($campaignId)) {
                if (isset($lastSbidMap[$campaignId])) {
                    $campaignMap[$key]['last_sbid'] = $lastSbidMap[$campaignId];
                } else {
                    $campaignMap[$key]['last_sbid'] = '';
                }
                
                if (isset($sbidMMap[$campaignId])) {
                    $campaignMap[$key]['sbid_m'] = $sbidMMap[$campaignId];
                } else {
                    $campaignMap[$key]['sbid_m'] = '';
                }
                
                if (isset($apprSbidMap[$campaignId])) {
                    $campaignMap[$key]['apprSbid'] = $apprSbidMap[$campaignId];
                } else {
                    $campaignMap[$key]['apprSbid'] = '';
                }
                
                if (isset($pinkDilPausedMap[$campaignId])) {
                    $campaignMap[$key]['pink_dil_paused_at'] = $pinkDilPausedMap[$campaignId];
                } else {
                    $campaignMap[$key]['pink_dil_paused_at'] = null;
                }
            } else {
                $campaignMap[$key]['last_sbid'] = '';
                $campaignMap[$key]['sbid_m'] = '';
                $campaignMap[$key]['apprSbid'] = '';
                $campaignMap[$key]['pink_dil_paused_at'] = null;
            }
        }

        // Calculate eBay SKU count - count all distinct PARENT SKUs from ebay_3_metrics table
        $ebaySkuCount = Ebay3Metric::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw("UPPER(sku) LIKE 'PARENT %'")
            ->distinct()
            ->count('sku');

        foreach ($campaignMap as $campaignId => $row) {
            $result[] = (object) $row;
        }

        // Calculate total SKU count from actual result data (to match pagination)
        // This should match the number of rows displayed in pagination
        $totalSkuCount = count($result);

        // Calculate and save SBID for yesterday's actual date records (not L1, L7, L30)
        // This is saved for tracking: to compare calculated SBID with what was actually updated on eBay
        // When cron runs and new data comes, page will refresh, so we need to save SBID to database
        try {
            $this->calculateAndSaveSBID($result);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Error saving eBay SBID: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'total_l30_spend' => round($totalSpendAll, 2),
            'total_l30_sales' => round($totalSalesAll, 2),
            'total_l30_clicks' => $totalClicksAll,
            'total_l30_ad_sold' => $totalAdSoldAll,
            'total_acos' => round($totalACOSAll, 2),
            'avg_acos' => $avgAcos,
            'avg_cvr' => $avgCvr,
            'total_sku_count' => $totalSkuCount,
            'ebay_sku_count' => $ebaySkuCount,
            'status' => 200,
        ]);
    }

    public function updateEbay3NrData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $ebayDataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value if it's a JSON string
        $jsonData = is_array($ebayDataView->value) 
            ? $ebayDataView->value 
            : (json_decode($ebayDataView->value ?? '{}', true) ?: []);

        // Save field value
        $jsonData[$field] = $value;

        // If NRL is set to "NRL" or "NR", automatically set NRA to "NRA" (always, regardless of current value)
        // Note: Dropdown sends 'NR' but database stores 'NRL', so handle both
        if ($field === 'NRL' && ($value === 'NRL' || $value === 'NR')) {
            // Always set NRA to "NRA" when NRL is "NRL" or "NR"
            $jsonData['NR'] = 'NRA';
            // Store as 'NRL' in database (normalize 'NR' to 'NRL')
            $jsonData['NRL'] = 'NRL';
        }

        $ebayDataView->value = $jsonData;
        $ebayDataView->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Field updated successfully",
            'updated_json' => $jsonData
        ]);
    }

    public function bulkUpdateEbay3SbidM(Request $request)
    {
        try {
            $campaignIds = $request->input('campaign_ids', []);
            $sbidM = $request->input('sbid_m');

            // Filter out invalid campaign IDs
            $campaignIds = array_filter($campaignIds, function($id) {
                return !empty($id) && $id !== null && $id !== '';
            });
            $campaignIds = array_values($campaignIds); // Re-index array

            if (empty($campaignIds)) {
                Log::warning('bulkUpdateEbay3SbidM: No valid campaign IDs provided after filtering.', ['input_campaign_ids' => $request->input('campaign_ids')]);
                return response()->json([
                    'status' => 400,
                    'message' => 'No valid campaign IDs provided'
                ], 400);
            }

            if (!$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M is required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $sbidMString = (string)$sbidM;

            Log::info('bulkUpdateEbay3SbidM: Attempting to update ' . count($campaignIds) . ' campaigns.', ['campaign_ids' => $campaignIds, 'sbid_m' => $sbidM]);

            // Define common conditions for the queries
            $commonConditions = function ($query) {
                $query->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%');
            };

            // 1. Update for yesterday's date (most common case)
            DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where($commonConditions)
                ->update([
                    'sbid_m' => $sbidMString,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);

            // 2. Get campaign IDs that were successfully updated for yesterday
            $updatedYesterdayCampaignIds = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where($commonConditions)
                ->where('sbid_m', $sbidMString) // Verify it was updated with the new value
                ->pluck('campaign_id')
                ->toArray();

            $remainingCampaignIdsForL1 = array_diff($campaignIds, $updatedYesterdayCampaignIds);

            // 3. Update for L1 (fallback for campaigns not found in yesterday)
            if (!empty($remainingCampaignIdsForL1)) {
                DB::table('ebay_3_priority_reports')
                    ->whereIn('campaign_id', $remainingCampaignIdsForL1)
                    ->where('report_range', 'L1')
                    ->where($commonConditions)
                    ->update([
                        'sbid_m' => $sbidMString,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
            }

            // 4. Update L7 and L30 records for all original selected campaigns (for consistency)
            DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('report_range', ['L7', 'L30'])
                ->where($commonConditions)
                ->update([
                    'sbid_m' => $sbidMString,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);

            // Count total updated campaigns (yesterday + L1)
            $totalUpdatedCount = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('report_range', [$yesterday, 'L1'])
                ->where($commonConditions)
                ->where('sbid_m', $sbidMString)
                ->distinct('campaign_id')
                ->count('campaign_id');

            Log::info('bulkUpdateEbay3SbidM: Successfully updated ' . $totalUpdatedCount . ' out of ' . count($campaignIds) . ' requested campaigns.', ['campaign_ids' => $campaignIds, 'updated_count' => $totalUpdatedCount]);

            return response()->json([
                'status' => 200,
                'message' => "SBID M saved successfully for {$totalUpdatedCount} campaign(s)",
                'updated_count' => $totalUpdatedCount,
                'total_count' => count($campaignIds)
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay3 SBID M bulk: ' . $e->getMessage(), [
                'campaign_ids' => $request->input('campaign_ids'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error saving SBID M: ' . $e->getMessage()
            ], 500);
        }
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

    public function getEbay3UtilizationCounts(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $skuKey = 'EBAY_UTILIZATION_' . $today;

            $record = EbayThreeDataView::where('sku', $skuKey)->first();

            if ($record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                return response()->json([
                    'over_utilized' => $value['over_utilized'] ?? 0,
                    'under_utilized' => $value['under_utilized'] ?? 0,
                    'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                    'status' => 200,
                ]);
            }

            // If not found, calculate on the fly
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');
            $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $ebayCampaignReportsL7 = Ebay3PriorityReport::where('report_range', 'L7')
                ->where('campaignStatus', 'RUNNING')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->get();

            $ebayCampaignReportsL1 = Ebay3PriorityReport::where('report_range', 'L1')
                ->where('campaignStatus', 'RUNNING')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->get();

            $ebayCampaignReportsL30 = Ebay3PriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->get();

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

            $overUtilizedCount = 0;
            $underUtilizedCount = 0;
            $correctlyUtilizedCount = 0;

            foreach ($productMasters as $pm) {
                $sku = strtoupper(trim($pm->sku));
                $shopify = $shopifyData[$pm->sku] ?? null;
                $ebay = $ebayMetricData[$pm->sku] ?? null;

                $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                if (!$matchedCampaignL7 && !$matchedCampaignL1 && !$matchedCampaignL30) {
                    continue;
                }

                $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
                if (!$campaignForDisplay || $campaignForDisplay->campaignStatus !== 'RUNNING') {
                    continue;
                }

                $price = $ebay->ebay_price ?? 0;
                if ($price < 30) {
                    continue;
                }

                $budget = $campaignForDisplay->campaignBudgetAmount ?? 0;
                $l7_spend = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0) : 0;
                $l1_spend = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0) : 0;
                
                $adFees = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0) : 0;
                $sales = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0) : 0;
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
                if ($acos === 0) {
                    $acos = 100;
                }

                $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

                $rowAcos = $acos;
                if ($rowAcos == 0) {
                    $rowAcos = 100;
                }

                $inv = $shopify->inv ?? 0;
                $l30 = $shopify->quantity ?? 0;

                $dilDecimal = (is_numeric($l30) && is_numeric($inv) && $inv !== 0) ? ($l30 / $inv) : 0;
                $dilPercent = $dilDecimal * 100;
                $isPink = ($dilPercent >= 50);

                $categorized = false;
                
                if ($totalACOSAll > 0 && !$isPink) {
                    $condition1 = ($rowAcos > $totalACOSAll && $ub7 > 33);
                    $condition2 = ($rowAcos <= $totalACOSAll && $ub7 > 90);
                    if ($condition1 || $condition2) {
                        $overUtilizedCount++;
                        $categorized = true;
                    }
                }

                if (!$categorized && $ub7 < 70 && $ub1 < 70 && $price >= 30 && $inv > 0 && !$isPink) {
                    $underUtilizedCount++;
                    $categorized = true;
                }

                if (!$categorized && $ub7 >= 70 && $ub7 <= 90 && $ub1 >= 70 && $ub1 <= 90) {
                    $correctlyUtilizedCount++;
                    $categorized = true;
                }
            }

            return response()->json([
                'over_utilized' => $overUtilizedCount,
                'under_utilized' => $underUtilizedCount,
                'correctly_utilized' => $correctlyUtilizedCount,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting eBay utilization counts: " . $e->getMessage());
            return response()->json([
                'over_utilized' => 0,
                'under_utilized' => 0,
                'correctly_utilized' => 0,
                'status' => 500,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getEbay3UtilizationChartData(Request $request)
    {
        try {
            $data = EbayThreeDataView::where('sku', 'LIKE', 'EBAY_UTILIZATION_%')
                ->orderBy('sku', 'desc')
                ->limit(30)
                ->get();
            
            $data = $data->map(function ($item) {
                $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                $date = str_replace('EBAY_UTILIZATION_', '', $item->sku);
                
                return [
                    'date' => $date,
                    'over_utilized' => $value['over_utilized'] ?? 0,
                    'under_utilized' => $value['under_utilized'] ?? 0,
                    'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                ];
            })
            ->reverse()
            ->values();

            $today = \Carbon\Carbon::today();
            $filledData = [];
            $dataByDate = $data->keyBy('date');
            
            for ($i = 29; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i)->format('Y-m-d');
                
                if (isset($dataByDate[$date])) {
                    $filledData[] = $dataByDate[$date];
                } else {
                    $filledData[] = [
                        'date' => $date,
                        'over_utilized' => 0,
                        'under_utilized' => 0,
                        'correctly_utilized' => 0,
                    ];
                }
            }

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $filledData,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting eBay utilization chart data: " . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching data',
                'data' => [],
                'status' => 500,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate and save SBID to last_sbid column for Ebay3 campaigns
     * This matches the frontend SBID calculation logic from Ebay3-utilized.blade.php
     */
    private function calculateAndSaveSBID($result)
    {
        // Save to yesterday's date because we're calculating SBID for yesterday's report data
        // Example: If today is Jan 15, cron downloaded Jan 14 report, we save SBID to Jan 14 records
        // Tomorrow (Jan 16) when checking, last_sbid will be in Jan 14 records
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Prepare batch updates
        $updates = [];
        
        foreach ($result as $row) {
            // Skip if no campaign_id
            if (empty($row->campaign_id)) {
                continue;
            }

            // Check if NRA (🔴) is selected - skip if NRA
            $nraValue = isset($row->NR) ? trim($row->NR) : '';
            if ($nraValue === 'NRA') {
                continue; // Skip update if NRA is selected
            }

            $l1Cpc = floatval($row->l1_cpc ?? 0);
            $l7Cpc = floatval($row->l7_cpc ?? 0);
            $budget = floatval($row->campaignBudgetAmount ?? 0);
            $l7Spend = floatval($row->l7_spend ?? 0);
            $l1Spend = floatval($row->l1_spend ?? 0);
            $price = floatval($row->price ?? 0);
            $inv = floatval($row->INV ?? 0);

            // Calculate UB7 and UB1
            $ub7 = 0;
            $ub1 = 0;
            if ($budget > 0) {
                $ub7 = ($l7Spend / ($budget * 7)) * 100;
                $ub1 = ($l1Spend / $budget) * 100;
            }

            // Calculate SBID using the same logic as blade file
            $sbid = 0;
            
            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
            // Note: Removed special case for ub7 === 0 && ub1 === 0 to allow UB1-based rules to apply (matching view file)
            if ($ub7 > 99 && $ub1 > 99) {
                if ($l1Cpc > 0) {
                    $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                } elseif ($l7Cpc > 0) {
                    $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                } else {
                    $sbid = 0;
                }
            } 
            // For 'all' utilization type, determine individual campaign's utilization status
            else {
                // Determine utilization status (same logic as combinedFilter in blade)
                $isOverUtilized = false;
                $isUnderUtilized = false;
                
                // Check over-utilized first (priority 1)
                if ($ub7 > 99 && $ub1 > 99) {
                    $isOverUtilized = true;
                }
                
                // Check under-utilized (priority 2: only if not over-utilized)
                // Remove price >= 20 check to match backend command logic
                if (!$isOverUtilized && $ub7 < 66 && $ub1 < 66 && $inv > 0) {
                    $isUnderUtilized = true;
                }
                
                // Apply SBID logic based on determined status
                if ($isOverUtilized) {
                    // If L1 CPC > 1.25, then L1CPC * 0.80, else L1CPC * 0.90
                    if ($l1Cpc > 1.25) {
                        $sbid = floor($l1Cpc * 0.80 * 100) / 100;
                    } elseif ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                    } else {
                        $sbid = 0;
                    }
                    
                    // Price cap: If price < $20, cap SBID at 0.20
                    if ($price < 20) {
                        $sbid = min($sbid, 0.20);
                    }
                } elseif ($isUnderUtilized) {
                    // New UB1-based bid increase rules (matching view file logic exactly)
                    // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
                    $lastSbidRaw = isset($row->last_sbid) ? $row->last_sbid : '';
                    $baseBid = 0;
                    
                    // Parse last_sbid, treat empty/0 as 0 (exact match with view file)
                    if (empty($lastSbidRaw) || $lastSbidRaw === '' || $lastSbidRaw === '0' || $lastSbidRaw === 0) {
                        $baseBid = 0;
                    } else {
                        $baseBid = floatval($lastSbidRaw);
                        if (is_nan($baseBid)) {
                            $baseBid = 0;
                        }
                    }
                    
                    // If last_sbid is 0, use L1_CPC or L7_CPC as fallback (exact match with view file)
                    if ($baseBid === 0) {
                        $baseBid = ($l1Cpc && !is_nan($l1Cpc) && $l1Cpc > 0) ? $l1Cpc : (($l7Cpc && !is_nan($l7Cpc) && $l7Cpc > 0) ? $l7Cpc : 0);
                    }
                    
                    if ($baseBid > 0) {
                        // If UB1 < 33%: increase bid by 0.10
                        if ($ub1 < 33) {
                            $sbid = floor(($baseBid + 0.10) * 100) / 100;
                        }
                        // If UB1 is 33% to 66%: increase bid by 10%
                        elseif ($ub1 >= 33 && $ub1 < 66) {
                            $sbid = floor(($baseBid * 1.10) * 100) / 100;
                        } else {
                            // For UB1 >= 66%, use base bid (no increase)
                            $sbid = floor($baseBid * 100) / 100;
                        }
                    } else {
                        $sbid = 0;
                    }
                } else {
                    // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                    } elseif ($l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                    } else {
                        $sbid = 0;
                    }
                }
            }
            
            // Only save if SBID > 0
            if ($sbid > 0) {
                $sbidValue = (string)$sbid;
                $updates[$row->campaign_id] = $sbidValue;
            }
        }

        // Perform efficient bulk updates using WHERE IN
        // Update only yesterday's actual date records (not L1, L7, L30) for tracking purposes
        if (!empty($updates)) {
            // Update in batches of 50 to avoid query size limits
            $chunks = array_chunk($updates, 50, true);
            foreach ($chunks as $chunk) {
                $campaignIds = array_keys($chunk);
                
                // Build CASE statement for bulk update
                $cases = [];
                $bindings = [];
                foreach ($chunk as $campaignId => $sbidValue) {
                    $cases[] = "WHEN ? THEN ?";
                    $bindings[] = $campaignId;
                    $bindings[] = $sbidValue;
                }
                
                $caseSql = implode(' ', $cases);
                $placeholders = str_repeat('?,', count($campaignIds) - 1) . '?';
                
                // Single query to update all records - only for yesterday's date (Y-m-d format)
                // Save to last_sbid column for tracking purposes
                // Always update with new calculated SBID value (removed NULL check to allow recalculation)
                // report_range should be the date in Y-m-d format (not L1, L7, L30)
                DB::statement("
                    UPDATE ebay_3_priority_reports 
                    SET last_sbid = CASE campaign_id {$caseSql} END
                    WHERE campaign_id IN ({$placeholders})
                    AND report_range = ?
                    AND report_range NOT IN ('L7', 'L1', 'L30')
                    AND campaignStatus = 'RUNNING'
                ", array_merge($bindings, $campaignIds, [$yesterday]));
            }
        }
    }

    public function saveEbay3SbidM(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $sbidM = $request->input('sbid_m');

            if (!$campaignId || !$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign ID and SBID M are required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // Update eBay campaigns - try yesterday first, then L1, L7, L30 as fallback
            // Clear apprSbid when sbid_m is updated so new bid can be pushed
            // First try yesterday's date
            $updated = DB::table('ebay_3_priority_reports')
                ->where('campaign_id', $campaignId)
                ->where('report_range', $yesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => (string)$sbidM,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);
            
            // If no record found for yesterday, try L1
            if ($updated === 0) {
                $updated = DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_range', 'L1')
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'sbid_m' => (string)$sbidM,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
            }

            // Also update L7 and L30 records for consistency (don't fail if they don't exist)
            if ($updated > 0) {
                DB::table('ebay_3_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_range', ['L7', 'L30'])
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'sbid_m' => (string)$sbidM,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
                
                return response()->json([
                    'status' => 200,
                    'message' => 'SBID M saved successfully',
                    'sbid_m' => $sbidM
                ]);
            } else {
                // Log for debugging
                Log::error('SBID M save failed', [
                    'campaign_id' => $campaignId,
                    'yesterday' => $yesterday,
                    'sbid_m' => $sbidM
                ]);
                
                return response()->json([
                    'status' => 404,
                    'message' => 'Campaign not found. Please ensure the campaign exists for yesterday\'s date or L1.'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error saving eBay SBID M: ' . $e->getMessage(), [
                'campaign_id' => $request->input('campaign_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error saving SBID M: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Save SBID M for multiple eBay campaigns (bulk update)
     */
    public function saveEbay3SbidMBulk(Request $request)
    {
        try {
            $campaignIds = $request->input('campaign_ids', []);
            $sbidM = $request->input('sbid_m');

            if (empty($campaignIds) || !is_array($campaignIds)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign IDs array is required'
                ], 400);
            }

            // Filter out invalid campaign IDs and ensure they are unique
            $campaignIds = array_filter($campaignIds, function($id) {
                return !empty($id) && $id !== null && $id !== '';
            });
            $campaignIds = array_values(array_unique($campaignIds)); // Re-index array and remove duplicates

            if (empty($campaignIds)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'No valid campaign IDs provided'
                ], 400);
            }

            // Log for debugging - ensure we're only updating selected campaigns
            Log::info('Bulk SBID M update', [
                'campaign_count' => count($campaignIds),
                'campaign_ids' => $campaignIds,
                'sbid_m' => $sbidM
            ]);

            if (!$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M is required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $sbidMString = (string)$sbidM;

            // Batch update for yesterday's date (most common case)
            $updatedYesterday = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => $sbidMString,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);

            // Get campaign IDs that were updated for yesterday
            $updatedCampaignIds = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->where('sbid_m', $sbidMString)
                ->pluck('campaign_id')
                ->toArray();

            $remainingCampaignIds = array_diff($campaignIds, $updatedCampaignIds);

            // Batch update for L1 (fallback for campaigns not found in yesterday)
            if (!empty($remainingCampaignIds)) {
                DB::table('ebay_3_priority_reports')
                    ->whereIn('campaign_id', $remainingCampaignIds)
                    ->where('report_range', 'L1')
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'sbid_m' => $sbidMString,
                        'apprSbid' => '' // Clear apprSbid to allow new bid push
                    ]);
            }

            // Batch update L7 and L30 records for all campaigns (for consistency)
            DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('report_range', ['L7', 'L30'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => $sbidMString,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);

            // Count total updated campaigns (yesterday + L1) - only count campaigns from our selected list
            $totalUpdated = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds) // Strictly limit to selected campaigns
                ->whereIn('report_range', [$yesterday, 'L1'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->where('sbid_m', $sbidMString)
                ->distinct()
                ->count('campaign_id');

            // Ensure we don't count more than we selected
            $updatedCount = min($totalUpdated, count($campaignIds));
            
            // Log the results for debugging
            Log::info('Bulk SBID M update completed', [
                'requested_count' => count($campaignIds),
                'updated_count' => $updatedCount,
                'total_updated_query' => $totalUpdated
            ]);

            return response()->json([
                'status' => 200,
                'message' => "SBID M saved successfully for {$updatedCount} campaign(s)",
                'updated_count' => $updatedCount,
                'total_count' => count($campaignIds)
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay SBID M bulk: ' . $e->getMessage(), [
                'campaign_ids' => $request->input('campaign_ids'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error saving SBID M: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear SBID M for multiple eBay campaigns (bulk clear)
     */
    public function clearEbay3SbidMBulk(Request $request)
    {
        try {
            $campaignIds = $request->input('campaign_ids', []);

            if (empty($campaignIds) || !is_array($campaignIds)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign IDs array is required'
                ], 400);
            }

            // Filter out invalid campaign IDs and ensure they are unique
            $campaignIds = array_filter($campaignIds, function($id) {
                return !empty($id) && $id !== null && $id !== '';
            });
            $campaignIds = array_values(array_unique($campaignIds)); // Re-index array and remove duplicates

            if (empty($campaignIds)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'No valid campaign IDs provided'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // Batch clear for yesterday's date (most common case)
            $updatedYesterday = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => null, // Clear sbid_m
                    'apprSbid' => '' // Clear apprSbid
                ]);

            // Get campaign IDs that were updated for yesterday
            $updatedCampaignIds = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->where('report_range', $yesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->whereNull('sbid_m')
                ->pluck('campaign_id')
                ->toArray();

            $remainingCampaignIds = array_diff($campaignIds, $updatedCampaignIds);

            // Batch clear for L1 (fallback for campaigns not found in yesterday)
            if (!empty($remainingCampaignIds)) {
                DB::table('ebay_3_priority_reports')
                    ->whereIn('campaign_id', $remainingCampaignIds)
                    ->where('report_range', 'L1')
                    ->where('campaignStatus', 'RUNNING')
                    ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                    ->where('campaign_name', 'NOT LIKE', 'General - %')
                    ->where('campaign_name', 'NOT LIKE', 'Default%')
                    ->update([
                        'sbid_m' => null, // Clear sbid_m
                        'apprSbid' => '' // Clear apprSbid
                    ]);
            }

            // Batch clear L7 and L30 records for all campaigns (for consistency)
            DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('report_range', ['L7', 'L30'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => null, // Clear sbid_m
                    'apprSbid' => '' // Clear apprSbid
                ]);

            // Count total cleared campaigns (yesterday + L1) - only count campaigns from our selected list
            $totalCleared = DB::table('ebay_3_priority_reports')
                ->whereIn('campaign_id', $campaignIds) // Strictly limit to selected campaigns
                ->whereIn('report_range', [$yesterday, 'L1'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->whereNull('sbid_m')
                ->distinct()
                ->count('campaign_id');

            // Ensure we don't count more than we selected
            $clearedCount = min($totalCleared, count($campaignIds));

            return response()->json([
                'status' => 200,
                'message' => "SBID M cleared successfully for {$clearedCount} campaign(s)",
                'updated_count' => $clearedCount,
                'total_count' => count($campaignIds)
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing eBay SBID M bulk: ' . $e->getMessage(), [
                'campaign_ids' => $request->input('campaign_ids'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error clearing SBID M: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleCampaignStatus(Request $request)
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

            $accessToken = $this->getEbay3AccessToken();
            if (!$accessToken) {
                return response()->json([
                    'status' => 500,
                    'message' => 'Failed to get access token'
                ], 500);
            }

            // Use campaign-level pause/resume endpoints
            if ($status === 'PAUSED') {
                $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/pause";
            } else {
                $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/resume";
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ])->post($endpoint);

                if ($response->successful()) {
                    // Update campaign status in database
                    $dbStatus = $status === 'ENABLED' ? 'RUNNING' : 'PAUSED';
                    
                    // Update pink_dil_paused_at: set timestamp when PAUSED, null when ENABLED
                    $updateData = ['campaignStatus' => $dbStatus];
                    if ($status === 'PAUSED') {
                        $updateData['pink_dil_paused_at'] = now();
                    } else {
                        $updateData['pink_dil_paused_at'] = null;
                    }
                    
                    Ebay3PriorityReport::where('campaign_id', $campaignId)
                        ->update($updateData);

                    return response()->json([
                        'status' => 200,
                        'message' => "Campaign {$status} successfully.",
                        'campaign_id' => $campaignId,
                        'status' => $status
                    ]);
                } else {
                    Log::error("Failed to {$status} campaign {$campaignId}: " . $response->body());
                    return response()->json([
                        'status' => $response->status(),
                        'message' => "Failed to {$status} campaign: " . ($response->json()['errors'][0]['message'] ?? $response->body())
                    ], $response->status());
                }
            } catch (\Exception $e) {
                Log::error("Exception {$status} campaign {$campaignId}: " . $e->getMessage());
                return response()->json([
                    'status' => 500,
                    'message' => 'Error updating campaign status: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error toggling eBay3 campaign status: ' . $e->getMessage(), [
                'campaign_id' => $request->input('campaign_id'),
                'status' => $request->input('status'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error updating campaign status: ' . $e->getMessage()
            ], 500);
        }
    }

}
