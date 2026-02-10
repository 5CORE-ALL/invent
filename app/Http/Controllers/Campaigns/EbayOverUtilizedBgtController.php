<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class EbayOverUtilizedBgtController extends Controller
{

    function getEbayAccessToken()
    {
        if (Cache::has('ebay_access_token')) {
            return Cache::get('ebay_access_token');
        }

        $clientId = env('EBAY_APP_ID');
        $clientSecret = env('EBAY_CERT_ID');
        $refreshToken = env('EBAY_REFRESH_TOKEN');
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

            Cache::put('ebay_access_token', $accessToken, $expiresIn - 60);

            return $accessToken;
        }

        throw new Exception("Failed to refresh token: " . json_encode($data));
    }

    function getAdGroups($campaignId)
    {
        $accessToken = $this->getEbayAccessToken();
        $url = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json",
                "Accept: application/json",
            ],
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }

    function getKeywords($campaignId, $adGroupId)
    {
        $accessToken = $this->getEbayAccessToken();
        $keywords = [];
        $offset = 0;
        $limit = 200;

        do {
            $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/keyword"."?ad_group_ids={$adGroupId}&keyword_status=ACTIVE&limit={$limit}&offset={$offset}";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$accessToken}",
                    "Content-Type: application/json",
                ],
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
            curl_close($ch);

            $data = json_decode($response, true);

            if (isset($data['keywords']) && is_array($data['keywords'])) {
                foreach ($data['keywords'] as $k) {
                    $keywords[] = $k['keywordId'] ?? $k['id'] ?? null;
                }
            }

            $total = $data['total'] ?? count($keywords);
            $offset += $limit;

        } while ($offset < $total);

        return array_filter($keywords);
    }

    public function updateAutoKeywordsBidDynamic(array $campaignIds, array $newBids)
    {
        // Set longer timeout for API operations (10 minutes per batch)
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        if (empty($campaignIds) || empty($newBids)) {
            return [
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ];
        }

        $accessToken = $this->getEbayAccessToken();
        $results = [];
        $hasError = false;

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

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
                        $response = Http::timeout(120) // 2 minute timeout per request
                            ->withHeaders([
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
        }

        return response()->json([
            "status" => $hasError ? 207 : 200,
            "message" => $hasError ? "Some keywords failed to update" : "All keyword bids updated successfully",
            "data" => $results
        ]);
    }

    public function updateKeywordsBidDynamic(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
        
        $campaignIds = $request->input('campaign_ids', []);
        $newBids = $request->input('bids', []);

        $accessToken = $this->getEbayAccessToken();
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
                $updated = DB::table('ebay_priority_reports')
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
                    DB::table('ebay_priority_reports')
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
                DB::table('ebay_priority_reports')
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

    public function ebayOverUtilisation(){
        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_priority_reports')
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

        return view('campaign.ebay-over-utilization', compact('dates', 'clicks', 'spend', 'acos', 'cvr'))
            ->with('ad_sales', $adSales)
            ->with('ad_sold', $adSold);
    }

    public function getEbayOverUtiData()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Get product master SKUs for filtering additional campaigns
        $productMasterSkus = $productMasters->pluck('sku')->map(function($sku) {
            return strtoupper(trim($sku));
        })->filter()->unique()->values()->all();

        // Fetch additional RUNNING campaigns not in product_masters from both L7 and L30
        $additionalL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->pluck('campaign_name')
            ->map(function($name) { return strtoupper(trim($name)); });

        $additionalL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->pluck('campaign_name')
            ->map(function($name) { return strtoupper(trim($name)); });

        $additionalRunningCampaigns = $additionalL7->merge($additionalL30)
            ->unique()
            ->filter(function($campaignSku) use ($productMasterSkus) {
                return !in_array($campaignSku, $productMasterSkus);
            })
            ->values()
            ->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        // Fallback: Get all campaigns (any report_range) for PAUSED campaigns that might not have L7/L30 data
        // Group by campaign_id instead of campaign_name to handle multiple report ranges properly
        $allCampaignReports = EbayPriorityReport::whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get()
            ->groupBy('campaign_id')
            ->map(function ($group) {
                // For each campaign_id, get the one with the most recent data or RUNNING status
                return $group->sortByDesc(function ($item) {
                    // Prefer RUNNING over PAUSED, then prefer L30 > L7 > L1 > date ranges
                    $statusPriority = $item->campaignStatus === 'RUNNING' ? 2 : 1;
                    $rangePriority = 0;
                    if ($item->report_range === 'L30') $rangePriority = 5;
                    elseif ($item->report_range === 'L7') $rangePriority = 4;
                    elseif ($item->report_range === 'L1') $rangePriority = 3;
                    else $rangePriority = 1; // Date ranges
                    return $statusPriority * 10 + $rangePriority;
                })->first();
            });

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebay = $ebayMetricData[$pm->sku] ?? null;

            $matchedCampaignsL7 = $ebayCampaignReportsL7->filter(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });
            $matchedCampaignL7 = $matchedCampaignsL7->first();

            $matchedCampaignsL1 = $ebayCampaignReportsL1->filter(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });
            $matchedCampaignL1 = $matchedCampaignsL1->first();

            $matchedCampaignsL30 = $ebayCampaignReportsL30->filter(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });
            $matchedCampaignL30 = $matchedCampaignsL30->first();

            // Use L7 if available, otherwise fall back to L30, then L1
            // If still not found, try fallback query (for PAUSED campaigns without report data)
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30 ?? $matchedCampaignL1;
            
            // Fallback: Check all campaigns if not found in L7/L30/L1
            if (!$campaignForDisplay) {
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                foreach ($allCampaignReports as $campaignId => $campaign) {
                    $normalizedCampaignName = strtoupper(trim(rtrim($campaign->campaign_name, '.')));
                    if ($normalizedCampaignName === $cleanSku) {
                        $campaignForDisplay = $campaign;
                        break;
                    }
                }
            }
            
            // Include both RUNNING and PAUSED campaigns (exclude ENDED)
            if (!$campaignForDisplay || !in_array($campaignForDisplay->campaignStatus, ['RUNNING', 'PAUSED'])) {
                continue;
            }
            
            // Ensure campaign_id is always set (critical for toggle to work)
            if (empty($campaignForDisplay->campaign_id)) {
                continue; // Skip if no campaign_id
            }

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $campaignForDisplay->campaign_id ?? '';
            $row['campaignName'] = $campaignForDisplay->campaign_name ?? '';
            $row['campaignStatus'] = $campaignForDisplay->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $campaignForDisplay->campaignBudgetAmount ?? '';

            // Use L30 data if available, otherwise use L7 or empty values
            $dataSource = $matchedCampaignL30 ?? $matchedCampaignL7 ?? $matchedCampaignL1 ?? null;
            
            $adFees   = $dataSource ? (float) str_replace('USD ', '', $dataSource->cpc_ad_fees_payout_currency ?? 0) : 0;
            $sales    = $dataSource ? (float) str_replace('USD ', '', $dataSource->cpc_sale_amount_payout_currency ?? 0) : 0;
            $clicks = $dataSource ? (int) ($dataSource->cpc_clicks ?? 0) : 0;
            $attributedSales = $dataSource ? (int) ($dataSource->cpc_attributed_sales ?? 0) : 0;

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            // If acos is 0 (no sales or no ad fees), set it to 100 for display
            if($acos === 0){
                $row['acos'] = 100;
            }else{
                $row['acos'] = $acos;
            }

            // Add L30 spend and sales for totals calculation
            $row['spend_l30'] = $adFees;
            $row['ad_sales_l30'] = $sales;

            // Calculate CVR: (attributed_sales / clicks) * 100 (same as Ebay3UtilizedAdsController)
            if ($clicks > 0) {
                $row['cvr'] = round(($attributedSales / $clicks) * 100, 2);
            } else {
                $row['cvr'] = 0;
            }
            $row['clicks'] = $clicks;
            $row['adFees'] = $adFees;
            $row['ad_sold'] = $attributedSales;

            $row['l7_spend'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l7_cpc'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0) : 0;
            $row['l1_spend'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l1_cpc'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0) : 0;

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

            // Only show items with price >= 30 and RUNNING status
            if($row['price'] >= 30 && $row['campaignStatus'] === 'RUNNING'){
                $result[] = (object) $row;
            }
        }

        // Process additional RUNNING campaigns not in product_masters
        $allL7Reports = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->get();
        $allL1Reports = EbayPriorityReport::where('report_range', 'L1')
            ->where('campaignStatus', 'RUNNING')
            ->get();
        $allL30Reports = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->get();

        foreach ($additionalRunningCampaigns as $campaignSku) {
            $matchedCampaignsL7 = $allL7Reports->filter(function ($item) use ($campaignSku) {
                return strtoupper(trim($item->campaign_name)) === $campaignSku;
            });
            $matchedCampaignL7 = $matchedCampaignsL7->first();

            $matchedCampaignsL1 = $allL1Reports->filter(function ($item) use ($campaignSku) {
                return strtoupper(trim($item->campaign_name)) === $campaignSku;
            });
            $matchedCampaignL1 = $matchedCampaignsL1->first();

            $matchedCampaignsL30 = $allL30Reports->filter(function ($item) use ($campaignSku) {
                return strtoupper(trim($item->campaign_name)) === $campaignSku;
            });
            $matchedCampaignL30 = $matchedCampaignsL30->first();

            // Only show RUNNING campaigns - use L7 if available, otherwise L30
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            if (!$campaignForDisplay || $campaignForDisplay->campaignStatus !== 'RUNNING') {
                continue;
            }

            $row = [];
            $row['parent'] = '';
            $row['sku'] = $campaignSku;
            $row['INV'] = 0;
            $row['L30'] = 0;
            
            // Try to get price from EbayMetric using campaign name as SKU
            $ebayMetric = EbayMetric::where('sku', $campaignSku)->first();
            $row['price'] = $ebayMetric->ebay_price ?? 0;
            
            $row['campaign_id'] = $campaignForDisplay->campaign_id ?? '';
            $row['campaignName'] = $campaignForDisplay->campaign_name ?? '';
            $row['campaignStatus'] = $campaignForDisplay->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $campaignForDisplay->campaignBudgetAmount ?? '';

            // Use L30 data if available, otherwise use L7 data
            $matchedCampaignL30 = $matchedCampaignL30 ?? $matchedCampaignL7;
            $adFees = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $sales = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $clicks = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
            $attributedSales = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            // If acos is 0 (no sales or no ad fees), set it to 100 for display
            if ($acos === 0) {
                $row['acos'] = 100;
            } else {
                $row['acos'] = $acos;
            }

            // Add L30 spend and sales for totals calculation
            $row['spend_l30'] = $adFees;
            $row['ad_sales_l30'] = $sales;

            // Calculate CVR: (attributed_sales / clicks) * 100 (same as Ebay3UtilizedAdsController)
            if ($clicks > 0) {
                $row['cvr'] = round(($attributedSales / $clicks) * 100, 2);
            } else {
                $row['cvr'] = 0;
            }
            $row['clicks'] = $clicks;
            $row['adFees'] = $adFees;
            $row['ad_sold'] = $attributedSales;

            $row['l7_spend'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l7_cpc'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0) : 0;
            $row['l1_spend'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l1_cpc'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0) : 0;
            $row['NR'] = '';

            // Only show items with price >= 30 and RUNNING status
            if($row['price'] >= 30 && $row['campaignStatus'] === 'RUNNING'){
                $result[] = (object) $row;
            }
        }

        // Calculate totals from ALL RUNNING campaigns (L30 data)
        $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpend = 0;
        $totalSales = 0;
        
        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
            $sales = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);
            $totalSpend += $adFees;
            $totalSales += $sales;
        }

        $totalACOS = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'total_acos' => round($totalACOS, 2),
            'total_l30_spend' => round($totalSpend, 2),
            'total_l30_sales' => round($totalSales, 2),
            'status'  => 200,
        ]);
    }

    public function updateNrData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value if it's a JSON string
        $jsonData = is_array($ebayDataView->value) 
            ? $ebayDataView->value 
            : (json_decode($ebayDataView->value ?? '{}', true) ?: []);

        // Save field value
        $jsonData[$field] = $value;

        $ebayDataView->value = $jsonData;
        $ebayDataView->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Field updated successfully",
            'updated_json' => $jsonData
        ]);
    }

    public function ebayUnderUtilized(){
        // Generate chart data for last 30 days
        $dates = [];
        $clicks = [];
        $spend = [];
        $ad_sales = [];
        $ad_sold = [];
        $acos = [];
        $cvr = [];
        
        // Generate date-wise data for last 30 days using ebay_metrics table
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            // Get data from ebay_metrics for this date (views as proxy for impressions)
            $dayData = DB::table('ebay_metrics')
                ->where('report_date', $date)
                ->selectRaw('
                    SUM(views) as total_views,
                    SUM(organic_clicks) as total_clicks,
                    COUNT(*) as total_items,
                    AVG(ebay_price) as avg_price
                ')
                ->first();
            
            // Get data from ebay_general_reports for this period (using L1 as daily proxy)
            $reportData = DB::table('ebay_general_reports')
                ->where('report_range', 'L1')
                ->selectRaw('
                    SUM(clicks) as report_clicks,
                    SUM(impressions) as report_impressions,
                    SUM(sales) as report_sales,
                    SUM(CASE 
                        WHEN ad_fees IS NOT NULL 
                        THEN CAST(REPLACE(REPLACE(ad_fees, "USD ", ""), "$", "") AS DECIMAL(10,2)) 
                        ELSE 0 
                    END) as report_spend
                ')
                ->first();
            
            // Combine data from both sources
            $clicks[] = ($dayData->total_clicks ?? 0) + ($reportData->report_clicks ?? 0);
            $spend[] = $reportData->report_spend ?? 0;
            $ad_sales[] = ($reportData->report_sales ?? 0) * ($dayData->avg_price ?? 0);
            $ad_sold[] = $reportData->report_sales ?? 0;
            
            // Calculate derived metrics
            $dailySpend = $reportData->report_spend ?? 0;
            $dailySales = ($reportData->report_sales ?? 0) * ($dayData->avg_price ?? 0);
            $dailyClicks = ($dayData->total_clicks ?? 0) + ($reportData->report_clicks ?? 0);
            
            $acos[] = $dailySales > 0 ? (($dailySpend / $dailySales) * 100) : 0;
            $cvr[] = $dailyClicks > 0 ? (($reportData->report_sales ?? 0) / $dailyClicks * 100) : 0;
        }
        
        return view('campaign.ebay-under-utilized', compact(
            'dates', 'clicks', 'spend', 'ad_sales', 'ad_sold', 'acos', 'cvr'
        ));
    }

    public function ebayCorrectlyUtilized(){
        return view('campaign.ebay-correctly-utilized');
    }

    public function ebayMakeCampaignKw(){
        return view('campaign.ebay-make-campaign-kw');
    }

    public function getEbayMakeNewCampaignKw()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

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

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');

            $adFees   = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $sales    = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0 );

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            // If acos is 0 (no sales or no ad fees), set it to 100 for display
            if($acos === 0){
                $row['acos'] = 100;
            }else{
                $row['acos'] = $acos;
            }

            $row['l7_spend'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['l7_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0);
            $row['l1_spend'] = (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0);
            $row['l1_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0);

            $row['NR'] = '';
            $row['NRL'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['NRL'] = $raw['NRL'] ?? null;

                }
            }
            if ($row['campaignName'] === '' && ($row['NR'] !== 'NRA' && $row['NRL'] !== 'NRL')) {
                $result[] = (object) $row;
            }


        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function filterOverUtilizedAds(Request $request)
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $data = DB::table('ebay_priority_reports')
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
        $startDate = $request->input('start_date', \Carbon\Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->format('Y-m-d'));

        $data = DB::table('ebay_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('campaign_name', $campaignName)
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
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

        // Generate date range from start to end
        $period = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate);
        $end = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate);
        
        while ($period->lte($end)) {
            $date = $period->format('Y-m-d');
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
            
            $period->addDay();
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

    public function ebayUtilizedView()
    {
        return view('campaign.ebay.ebay-utilized');
    }

    public function getEbayUtilizedAdsData()
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
        $ebayMetricDataRaw = EbayMetric::whereIn('sku', $skus)->get();
        $ebayMetricData = [];
        foreach ($ebayMetricDataRaw as $ebay) {
            // Store with normalized key for matching
            $normalizedKey = $normalizeSku($ebay->sku);
            $ebayMetricData[$normalizedKey] = $ebay;
        }
        
        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = EbayPriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
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

        foreach ($productMasters as $pm) {
            // Skip PARENT SKUs
            if (stripos($pm->sku, 'PARENT') !== false) {
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
                $ebay = EbayMetric::where('sku', $pm->sku)->first();
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
                $l7Views = $ebay->l7_views ?? 0;
                
                // Track eBay SKU (if has eBay data with price > 0 or campaign)
                if (($ebay && $price > 0) || $hasCampaign) {
                    $ebaySkuSet[$sku] = true;
                }
                
                $invValue = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
                
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
                    'l7_views' => (int)$l7Views,
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
                // Entry already exists - update inventory if this SKU has higher inventory
                if ($shopify && isset($shopify->inv)) {
                    $currentInv = (int)$shopify->inv;
                    $existingInv = (int)($campaignMap[$mapKey]['INV'] ?? 0);
                    
                    // Use the maximum inventory value
                    if ($currentInv > $existingInv) {
                        $campaignMap[$mapKey]['INV'] = $currentInv;
                        $campaignMap[$mapKey]['L30'] = ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0;
                        // Also update the SKU to show the one with inventory
                        $campaignMap[$mapKey]['sku'] = $pm->sku;
                    }
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
            $l7Views = 0;
            if ($ebay) {
                $price = $ebay->ebay_price ?? 0;
                $ebayL30 = $ebay->ebay_l30 ?? 0;
                $views = $ebay->views ?? 0;
                $l7Views = $ebay->l7_views ?? 0;
            } else {
                // Try to find by campaign name
                $ebayMetricByName = EbayMetric::where('sku', $campaignName)->first();
                if ($ebayMetricByName) {
                    $price = $ebayMetricByName->ebay_price ?? 0;
                    $ebayL30 = $ebayMetricByName->ebay_l30 ?? 0;
                    $views = $ebayMetricByName->views ?? 0;
                    $l7Views = $ebayMetricByName->l7_views ?? 0;
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
                'l7_views' => (int)$l7Views,
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

        // Fetch last 30 days daily data from ebay_priority_reports
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
            $dailyDataLast30Days = DB::table('ebay_priority_reports')
                ->select(
                    'campaign_id',
                    'campaign_name',
                    DB::raw('SUM(cpc_clicks) as total_clicks'),
                    DB::raw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend'),
                    DB::raw('SUM(cpc_attributed_sales) as total_ad_sold')
                )
                ->whereRaw("report_range >= ? AND report_range <= ? AND report_range NOT IN ('L7', 'L1', 'L30')", [$thirtyDaysAgo, $today])
                ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
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
        $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
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
        
        $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
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
        $sbidMReports = EbayPriorityReport::where(function($q) use ($yesterday) {
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
        $apprSbidReports = EbayPriorityReport::where(function($q) use ($yesterday) {
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
        $pinkDilPausedReports = EbayPriorityReport::where('report_range', 'L30')
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

        // Calculate total SKU count (excluding PARENT SKUs and deleted records)
        $totalSkuCount = ProductMaster::whereNull('deleted_at')
            ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
            ->count();

        // Calculate eBay SKU count - count all unique SKUs from EbayMetric table
        $ebaySkuCount = EbayMetric::select('sku')
            ->distinct()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
            ->count();

        foreach ($campaignMap as $campaignId => $row) {
            $result[] = (object) $row;
        }

        // Calculate and save SBID for yesterday's actual date records (not L1, L7, L30)
        // This is saved for tracking: to compare calculated SBID with what was actually updated on eBay
        // When cron runs and new data comes, page will refresh, so we need to save SBID to database
        try {
            $this->calculateAndSaveSBID($result);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Error saving eBay SBID: ' . $e->getMessage());
        }

        // Get pause statistics from database (pink_dil_paused_at column)
        $pausedCampaignsQuery = EbayPriorityReport::where('report_range', 'L30')
            ->whereNotNull('pink_dil_paused_at')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->orderBy('pink_dil_paused_at', 'desc');

        $pausedCampaignsList = [];
        $pauseCount = $pausedCampaignsQuery->count();
        $lastPauseRunAt = null;

        if ($pauseCount > 0) {
            // Get all paused campaigns with their details
            $pausedReports = $pausedCampaignsQuery->get();
            
            // Get the most recent pause timestamp from the first record (already sorted desc)
            if ($pausedReports->isNotEmpty()) {
                $lastPaused = $pausedReports->first();
                $lastPauseRunAt = $lastPaused->pink_dil_paused_at ? $lastPaused->pink_dil_paused_at->toDateTimeString() : null;
            }
            
            foreach ($pausedReports as $report) {
                // Calculate DIL and ACOS for display
                $adFees = (float) str_replace(['USD ', ','], '', $report->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $report->cpc_sale_amount_payout_currency ?? '0');
                $acos = $sales > 0 ? round(($adFees / $sales) * 100, 2) : ($adFees > 0 ? 100 : 0);

                // Get SKU from campaign name for DIL calculation
                $sku = strtoupper(trim($report->campaign_name ?? ''));
                $shopify = ShopifySku::where('sku', $sku)->first();
                $dil = 0;
                if ($shopify && $shopify->inv > 0) {
                    $l30 = (float)($shopify->quantity ?? 0);
                    $inv = (float)($shopify->inv ?? 0);
                    $dil = ($l30 / $inv) * 100;
                }

                $pausedCampaignsList[] = [
                    'campaign_id' => $report->campaign_id ?? '',
                    'campaign_name' => $report->campaign_name ?? '',
                    'dil' => $dil,
                    'acos' => $acos,
                    'keywords_paused' => 0, // Can't track exact count from DB, but can be added if needed
                    'paused_at' => $report->pink_dil_paused_at ? $report->pink_dil_paused_at->toDateTimeString() : ''
                ];
            }
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
            'pause_stats' => [
                'count' => $pauseCount,
                'campaigns' => $pausedCampaignsList,
                'last_run_at' => $lastPauseRunAt,
                'total_keywords_paused' => 0 // Not tracked per campaign in DB, but total can be calculated
            ],
            'status' => 200,
        ]);
    }

    /**
     * Calculate SBID based on utilization type and save to database for yesterday's actual date records
     * This is saved for tracking: to compare calculated SBID with what was actually updated on eBay
     * When cron runs and new data comes, page will refresh, so we need to save SBID to database
     * We save to yesterday's report date because that's the date for which SBID is being calculated
     * Uses the same SBID calculation logic as ebay-utilized.blade.php
     * Optimized to use batch updates to avoid timeout
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
                    UPDATE ebay_priority_reports 
                    SET last_sbid = CASE campaign_id {$caseSql} END
                    WHERE campaign_id IN ({$placeholders})
                    AND report_range = ?
                    AND report_range NOT IN ('L7', 'L1', 'L30')
                    AND campaignStatus = 'RUNNING'
                ", array_merge($bindings, $campaignIds, [$yesterday]));
            }
        }
    }

    /**
     * Store all statistics from the Statistics section in the database
     * Stores with sku='ebay' and all statistics in the value column as JSON
     */
    public function storeEbayStatistics(Request $request)
    {
        try {
            // Get all data similar to getEbayUtilizedAdsData
            $normalizeSku = function ($sku) {
                if (empty($sku)) return '';
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return trim($sku);
            };
            
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            
            $shopifyDataRaw = ShopifySku::whereIn('sku', $skus)->get();
            $shopifyData = [];
            foreach ($shopifyDataRaw as $shopify) {
                $normalizedKey = $normalizeSku($shopify->sku);
                $shopifyData[$normalizedKey] = $shopify;
            }
            
            $ebayMetricDataRaw = EbayMetric::whereIn('sku', $skus)->get();
            $ebayMetricData = [];
            foreach ($ebayMetricDataRaw as $ebay) {
                $normalizedKey = $normalizeSku($ebay->sku);
                $ebayMetricData[$normalizedKey] = $ebay;
            }
            
            $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $reports = EbayPriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
                ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
                ->orderBy('report_range', 'asc')
                ->get();

            $campaignMap = [];
            $ebaySkuSet = [];
            $totalAcos = 0;
            $totalCvr = 0;
            $acosCount = 0;
            $cvrCount = 0;

            foreach ($productMasters as $pm) {
                if (stripos($pm->sku, 'PARENT') !== false) {
                    continue;
                }

                $normalizedSku = $normalizeSku($pm->sku);
                $sku = $normalizedSku;
                $parent = $pm->parent;
                
                $shopify = $shopifyData[$normalizedSku] ?? null;
                if (!$shopify) {
                    $shopify = ShopifySku::where('sku', $pm->sku)->first();
                }
                
                $ebay = $ebayMetricData[$normalizedSku] ?? null;
                if (!$ebay) {
                    $ebay = EbayMetric::where('sku', $pm->sku)->first();
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

                $price = $ebay->ebay_price ?? 0;
                $invValue = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
                
                // Track eBay SKU (if has eBay data with price > 0 or campaign)
                if (($ebay && $price > 0) || $hasCampaign) {
                    $ebaySkuSet[$sku] = true;
                }
                
                // Use SKU as key if no campaign, otherwise use campaignId
                $mapKey = !empty($campaignId) ? $campaignId : 'SKU_' . $sku;
                
                if (!isset($campaignMap[$mapKey])) {
                    $campaignMap[$mapKey] = [
                        'sku' => $pm->sku,
                        'hasCampaign' => $hasCampaign,
                        'campaignStatus' => $campaignStatus,
                        'INV' => $invValue,
                        'NR' => $nrValue,
                        'NRL' => $nrlValue,
                        'matchedCampaignL7' => $matchedCampaignL7,
                        'matchedCampaignL1' => $matchedCampaignL1,
                        'matchedCampaignL30' => $matchedCampaignL30,
                        'campaignBudgetAmount' => $campaignBudgetAmount,
                        'acos' => 0,
                        'cvr' => 0,
                        'clicks' => 0,
                    ];
                }
                
                // Add campaign data if exists
                if ($matchedCampaignL7) {
                    $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
                    $campaignMap[$mapKey]['l7_spend'] = $adFees;
                }
                
                if ($matchedCampaignL1) {
                    $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
                    $campaignMap[$mapKey]['l1_spend'] = $adFees;
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
                    
                    // Calculate CVR
                    if ($clicks > 0) {
                        $campaignMap[$mapKey]['cvr'] = round(($adSold / $clicks) * 100, 2);
                    }
                    
                    // Calculate ACOS
                    if ($sales > 0) {
                        $campaignMap[$mapKey]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$mapKey]['acos'] = 100;
                    }
                }
            }
            
            // Process campaigns that don't match ProductMaster SKUs (same as getEbayUtilizedAdsData)
            $allCampaignIds = $reports->where('campaignStatus', 'RUNNING')->pluck('campaign_id')->unique();
            $processedCampaignIds = array_keys($campaignMap);
            
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
                
                $normalizedCampaignName = $normalizeSku($campaignName);
                if (isset($productMasterSkuSet[$normalizedCampaignName])) {
                    continue;
                }
                
                // Track eBay SKU for campaigns not matching ProductMaster SKUs
                $ebayMetricByName = EbayMetric::where('sku', $campaignName)->first();
                if ($ebayMetricByName && ($ebayMetricByName->ebay_price ?? 0) > 0) {
                    $campaignSkuUpper = strtoupper(trim($campaignName));
                    if (!isset($ebaySkuSet[$campaignSkuUpper])) {
                        $ebaySkuSet[$campaignSkuUpper] = true;
                    }
                }
                
                $campaignMap[$campaignId] = [
                    'sku' => $campaignName,
                    'hasCampaign' => true,
                    'campaignStatus' => $firstCampaign->campaignStatus ?? '',
                    'INV' => 0,
                    'NR' => '',
                    'NRL' => '',
                    'matchedCampaignL7' => null,
                    'matchedCampaignL1' => null,
                    'matchedCampaignL30' => null,
                    'campaignBudgetAmount' => $firstCampaign->campaignBudgetAmount ?? 0,
                    'acos' => 0,
                    'cvr' => 0,
                    'clicks' => 0,
                ];
                
                foreach ($campaignReports as $campaign) {
                    $reportRange = $campaign->report_range ?? '';
                    if ($reportRange == 'L7') {
                        $campaignMap[$campaignId]['matchedCampaignL7'] = $campaign;
                        $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                        $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    }
                    if ($reportRange == 'L1') {
                        $campaignMap[$campaignId]['matchedCampaignL1'] = $campaign;
                        $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                        $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    }
                    if ($reportRange == 'L30') {
                        $campaignMap[$campaignId]['matchedCampaignL30'] = $campaign;
                        $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                        $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                        $clicks = (int) ($campaign->cpc_clicks ?? 0);
                        $adSold = (int) ($campaign->cpc_attributed_sales ?? 0);
                        
                        $campaignMap[$campaignId]['adFees'] = $adFees;
                        $campaignMap[$campaignId]['sales'] = $sales;
                        $campaignMap[$campaignId]['clicks'] = $clicks;
                        $campaignMap[$campaignId]['ad_sold'] = $adSold;
                        
                        if ($clicks > 0) {
                            $campaignMap[$campaignId]['cvr'] = round(($adSold / $clicks) * 100, 2);
                        }
                        
                        if ($sales > 0) {
                            $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                        } else if ($adFees > 0 && $sales == 0) {
                            $campaignMap[$campaignId]['acos'] = 100;
                        }
                    }
                }
            }
            
            // Track processed SKUs to avoid duplicates (same as frontend)
            $processedSkusForNra = [];
            $processedSkusForNrl = [];
            $processedSkusForCampaign = [];
            $processedSkusForMissing = [];
            $processedSkusForNraMissing = [];
            $processedSkusForNrlMissing = [];
            
            // Initialize count variables
            $totalCampaignCount = 0;
            $missingCampaignCount = 0;
            $nraMissingCount = 0;
            $nrlMissingCount = 0;
            $zeroInvCount = 0;
            $nraCount = 0;
            $nrlCount = 0;
            $raCount = 0;
            $ub7Count = 0;
            $ub7Ub1Count = 0;
            $pausedCampaignsCount = 0;
            
            // Now count statistics from campaignMap (same logic as frontend)
            foreach ($campaignMap as $key => $row) {
                $hasCampaign = $row['hasCampaign'] ?? false;
                $campaignStatus = $row['campaignStatus'] ?? '';
                $invValue = $row['INV'] ?? 0;
                $nrValue = trim($row['NR'] ?? '');
                $nrlValue = trim($row['NRL'] ?? '');
                $matchedCampaignL7 = $row['matchedCampaignL7'] ?? null;
                $matchedCampaignL1 = $row['matchedCampaignL1'] ?? null;
                $matchedCampaignL30 = $row['matchedCampaignL30'] ?? null;
                $campaignBudgetAmount = $row['campaignBudgetAmount'] ?? 0;
                $sku = $row['sku'] ?? '';
                
                // Skip PARENT SKUs and empty SKUs
                if (empty($sku) || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                
                // Count zero INV (before other filters) - count all rows with INV <= 0
                if ($invValue <= 0) {
                    $zeroInvCount++;
                }
                
                // Count NRA and RA (only once per SKU, same as frontend)
                if (!isset($processedSkusForNra[$sku])) {
                    $processedSkusForNra[$sku] = true;
                    if ($nrValue === 'NRA') {
                        $nraCount++;
                    } else {
                        // Empty/null defaults to RA (same as frontend)
                        $raCount++;
                    }
                }
                
                // Count NRL (only once per SKU, same as frontend)
                if (!isset($processedSkusForNrl[$sku])) {
                    $processedSkusForNrl[$sku] = true;
                    if ($nrlValue === 'NRL') {
                        $nrlCount++;
                    }
                }
                
                // Count campaigns and missing (only once per SKU, same as frontend)
                if ($hasCampaign) {
                    if (!isset($processedSkusForCampaign[$sku])) {
                        $processedSkusForCampaign[$sku] = true;
                        $totalCampaignCount++;
                    }
                } else {
                    // Only process missing if not already processed
                    if (!isset($processedSkusForMissing[$sku])) {
                        $processedSkusForMissing[$sku] = true;
                        // Only count as missing (red dot) if neither NRL='NRL' nor NRA='NRA' (same as frontend line 896)
                        if ($nrlValue !== 'NRL' && $nrValue !== 'NRA') {
                            $missingCampaignCount++;
                        } else {
                            // Count NRL missing (yellow dots) - same as frontend line 900-904
                            if ($nrlValue === 'NRL' && !isset($processedSkusForNrlMissing[$sku])) {
                                $processedSkusForNrlMissing[$sku] = true;
                                $nrlMissingCount++;
                            }
                            // Count NRA missing (yellow dots) - same as frontend line 907-917
                            if ($nrValue === 'NRA' && !isset($processedSkusForNraMissing[$sku])) {
                                $processedSkusForNraMissing[$sku] = true;
                                $nraMissingCount++;
                            } else if ($nrlValue === 'NRL' && !isset($processedSkusForNraMissing[$sku])) {
                                // If NRL='NRL' but NRA is not 'NRA', still count as NRA missing
                                $processedSkusForNraMissing[$sku] = true;
                                $nraMissingCount++;
                            }
                        }
                    }
                }
                
                // Count paused campaigns
                if ($campaignStatus === 'PAUSED') {
                    $pausedCampaignsCount++;
                }
                
                // Calculate UB7 and UB1 for counts (same as frontend lines 949-957)
                if ($hasCampaign && $matchedCampaignL7 && $matchedCampaignL1) {
                    $l7_spend = $row['l7_spend'] ?? 0;
                    $l1_spend = $row['l1_spend'] ?? 0;
                    
                    $ub7 = $campaignBudgetAmount > 0 ? ($l7_spend / ($campaignBudgetAmount * 7)) * 100 : 0;
                    $ub1 = $campaignBudgetAmount > 0 ? ($l1_spend / $campaignBudgetAmount) * 100 : 0;
                    
                    // Count 7UB (ub7 >= 66 && ub7 <= 99) - same as frontend line 950
                    if ($ub7 >= 66 && $ub7 <= 99) {
                        $ub7Count++;
                    }
                    
                    // Count 7UB + 1UB (both ub7 and ub1 >= 66 && <= 99) - same as frontend line 955
                    if ($ub7 >= 66 && $ub7 <= 99 && $ub1 >= 66 && $ub1 <= 99) {
                        $ub7Ub1Count++;
                    }
                }
                
                // Calculate averages from campaignMap
                if (isset($row['acos']) && $row['acos'] !== null) {
                    $totalAcos += (float) $row['acos'];
                    $acosCount++;
                }
                
                if (isset($row['clicks']) && $row['clicks'] > 0 && isset($row['cvr'])) {
                    $totalCvr += (float) $row['cvr'];
                    $cvrCount++;
                }
            }
            
            // Calculate total SKU count (same as getEbayUtilizedAdsData)
            $totalSkuCount = ProductMaster::whereNull('deleted_at')
                ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
                ->count();
            
            // Calculate eBay SKU count (same as getEbayUtilizedAdsData)
            $ebaySkuCount = EbayMetric::select('sku')
                ->distinct()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
                ->count();
            
            // Calculate L30 totals from ALL RUNNING campaigns (same as getEbayUtilizedAdsData)
            $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get();
            
            $totalL30Clicks = 0;
            $totalL30Spend = 0;
            $totalL30AdSold = 0;
            
            foreach ($allL30Campaigns as $campaign) {
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $clicks = (int) ($campaign->cpc_clicks ?? 0);
                $adSold = (int) ($campaign->cpc_attributed_sales ?? 0);
                $totalL30Clicks += $clicks;
                $totalL30Spend += $adFees;
                $totalL30AdSold += $adSold;
            }

            // Calculate averages (same as getEbayUtilizedAdsData)
            $avgAcos = $acosCount > 0 ? round($totalAcos / $acosCount, 2) : 0;
            $avgCvr = $cvrCount > 0 ? round($totalCvr / $cvrCount, 2) : 0;
            
            // Get pause statistics (same as getEbayUtilizedAdsData)
            $pauseCount = EbayPriorityReport::where('report_range', 'L30')
                ->whereNotNull('pink_dil_paused_at')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->count();

            // Store all statistics
            $statistics = [
                'total_sku_count' => $totalSkuCount,
                'ebay_sku_count' => $ebaySkuCount,
                'total_campaign_count' => $totalCampaignCount,
                'missing_campaign_count' => $missingCampaignCount,
                'nra_missing_count' => $nraMissingCount,
                'zero_inv_count' => $zeroInvCount,
                'nra_count' => $nraCount,
                'nrl_missing_count' => $nrlMissingCount,
                'nrl_count' => $nrlCount,
                'ra_count' => $raCount,
                'ub7_count' => $ub7Count,
                'ub7_1ub_count' => $ub7Ub1Count,
                'l30_total_clicks' => $totalL30Clicks,
                'l30_total_spend' => round($totalL30Spend, 2),
                'l30_total_ad_sold' => $totalL30AdSold,
                'avg_acos' => $avgAcos,
                'avg_cvr' => $avgCvr,
                'paused_campaigns_count' => $pauseCount,
                'date' => now()->format('Y-m-d')
            ];

            EbayDataView::updateOrCreate(
                ['sku' => 'ebay'],
                ['value' => $statistics]
            );

            return response()->json([
                'message' => 'Statistics stored successfully',
                'statistics' => $statistics,
                'debug' => [
                    'campaign_map_count' => count($campaignMap),
                    'total_sku_count' => $totalSkuCount,
                    'ebay_sku_count' => $ebaySkuCount,
                    'sample_entries' => array_map(function($row) {
                        return [
                            'sku' => $row['sku'] ?? 'N/A',
                            'hasCampaign' => $row['hasCampaign'] ?? false,
                            'INV' => $row['INV'] ?? 0,
                            'NR' => $row['NR'] ?? '',
                            'NRL' => $row['NRL'] ?? '',
                        ];
                    }, array_slice($campaignMap, 0, 5, true)),
                ],
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Error storing eBay statistics: " . $e->getMessage());
            return response()->json([
                'message' => 'Error storing statistics',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    public function getEbayUtilizationCounts(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $skuKey = 'EBAY_UTILIZATION_' . $today;

            $record = EbayDataView::where('sku', $skuKey)->first();

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
            $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
            $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
                ->where('campaignStatus', 'RUNNING')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->get();

            $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
                ->where('campaignStatus', 'RUNNING')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->get();

            $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->get();

            $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
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

    public function getEbayUtilizationChartData(Request $request)
    {
        try {
            $data = EbayDataView::where('sku', 'LIKE', 'EBAY_UTILIZATION_%')
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
     * Save SBID M to database for eBay campaigns
     */
    public function saveEbaySbidM(Request $request)
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
            // First try yesterday's date (RUNNING or PAUSED)
            $updated = DB::table('ebay_priority_reports')
                ->where('campaign_id', $campaignId)
                ->where('report_range', $yesterday)
                ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->update([
                    'sbid_m' => (string)$sbidM,
                    'apprSbid' => '' // Clear apprSbid to allow new bid push
                ]);
            
            // If no record found for yesterday, try L1
            if ($updated === 0) {
                $updated = DB::table('ebay_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_range', 'L1')
                    ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
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
                DB::table('ebay_priority_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_range', ['L7', 'L30'])
                    ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
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
    public function saveEbaySbidMBulk(Request $request)
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
            $updatedYesterday = DB::table('ebay_priority_reports')
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
            $updatedCampaignIds = DB::table('ebay_priority_reports')
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
                DB::table('ebay_priority_reports')
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
            DB::table('ebay_priority_reports')
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
            $totalUpdated = DB::table('ebay_priority_reports')
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
    public function clearEbaySbidMBulk(Request $request)
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
            $updatedYesterday = DB::table('ebay_priority_reports')
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
            $updatedCampaignIds = DB::table('ebay_priority_reports')
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
                DB::table('ebay_priority_reports')
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
            DB::table('ebay_priority_reports')
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
            $totalCleared = DB::table('ebay_priority_reports')
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

            $accessToken = $this->getEbayAccessToken();
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
                    
                    EbayPriorityReport::where('campaign_id', $campaignId)
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
            Log::error('Error toggling eBay campaign status: ' . $e->getMessage(), [
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
