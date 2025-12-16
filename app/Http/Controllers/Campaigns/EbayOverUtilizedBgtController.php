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
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

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

            // Use L7 if available, otherwise fall back to L30
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            
            // Only show RUNNING campaigns
            if (!$campaignForDisplay || $campaignForDisplay->campaignStatus !== 'RUNNING') {
                continue;
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

            $adFees   = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $sales    = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0 );

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

            $row['l7_spend'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['l7_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0);
            $row['l1_spend'] = (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0);
            $row['l1_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0);

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
}
