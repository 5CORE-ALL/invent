<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use AWS\CRT\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        return view('campaign.ebay-over-utilization');
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
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
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
            $matchedCampaignL7 = $matchedCampaignsL7->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL7->first();

            $matchedCampaignsL1 = $ebayCampaignReportsL1->filter(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });
            $matchedCampaignL1 = $matchedCampaignsL1->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL1->first();

            $matchedCampaignsL30 = $ebayCampaignReportsL30->filter(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });
            $matchedCampaignL30 = $matchedCampaignsL30->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL30->first();

            // Use L7 if available, otherwise fall back to L30
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            
            if (!$campaignForDisplay) {
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
            
            if($adFees > 0 && $sales === 0){
                $row['acos'] = 100;
            }else{
                $row['acos'] = $acos;
            }

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

            // Only show items with price >= 30
            if($row['price'] >= 30){
                $result[] = (object) $row;
            }
        }

        // Process additional RUNNING campaigns not in product_masters
        $allL7Reports = EbayPriorityReport::where('report_range', 'L7')->get();
        $allL1Reports = EbayPriorityReport::where('report_range', 'L1')->get();
        $allL30Reports = EbayPriorityReport::where('report_range', 'L30')->get();

        foreach ($additionalRunningCampaigns as $campaignSku) {
            $matchedCampaignsL7 = $allL7Reports->filter(function ($item) use ($campaignSku) {
                return strtoupper(trim($item->campaign_name)) === $campaignSku;
            });
            $matchedCampaignL7 = $matchedCampaignsL7->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL7->first();

            $matchedCampaignsL1 = $allL1Reports->filter(function ($item) use ($campaignSku) {
                return strtoupper(trim($item->campaign_name)) === $campaignSku;
            });
            $matchedCampaignL1 = $matchedCampaignsL1->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL1->first();

            $matchedCampaignsL30 = $allL30Reports->filter(function ($item) use ($campaignSku) {
                return strtoupper(trim($item->campaign_name)) === $campaignSku;
            });
            $matchedCampaignL30 = $matchedCampaignsL30->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL30->first();

            if (!$matchedCampaignL7) {
                continue;
            }

            $row = [];
            $row['parent'] = '';
            $row['sku'] = $campaignSku;
            $row['INV'] = 0;
            $row['L30'] = 0;
            $row['price'] = 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? '';
            $row['campaignName'] = $matchedCampaignL7->campaign_name ?? '';
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? '';

            $adFees = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $sales = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            if ($adFees > 0 && $sales === 0) {
                $row['acos'] = 100;
            } else {
                $row['acos'] = $acos;
            }

            $row['l7_spend'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['l7_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0);
            $row['l1_spend'] = (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0);
            $row['l1_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0);
            $row['NR'] = '';

            // Only show items with price >= 30
            if($row['price'] >= 30){
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
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
        return view('campaign.ebay-under-utilized');
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
            
            if($adFees > 0 && $sales === 0){
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
}
