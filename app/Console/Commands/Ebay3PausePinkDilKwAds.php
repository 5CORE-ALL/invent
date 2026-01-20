<?php

namespace App\Console\Commands;

use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Ebay3PausePinkDilKwAds extends Command
{
    protected $signature = 'ebay3:auto-pause-pink-dil-kw-ads {--dry-run : Run without actually pausing ads} {--campaign-id= : Test with specific campaign ID only}';
    protected $description = 'Automatically pause eBay3 campaigns if l7_views >= 50 AND acos >= 10. Only for RUNNING campaigns.';

    public function handle()
    {
        try {
            set_time_limit(0);
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '1024M');

            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $isDryRun = $this->option('dry-run');
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🚀 Starting eBay3 Auto-Pause Pink DIL KW Ads");
            if ($isDryRun) {
                $this->warn("⚠️  DRY-RUN MODE: No ads will be paused on eBay");
            }
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            $testCampaignId = $this->option('campaign-id');
            
            $campaigns = $this->getEbay3PinkDilCampaigns($testCampaignId);

            if (empty($campaigns)) {
                if ($testCampaignId) {
                    $this->warn("⚠️  Campaign ID '{$testCampaignId}' not found or doesn't meet pause criteria.");
                } else {
                    $this->warn("⚠️  No campaigns found that need to be paused.");
                }
                return 0;
            }
            
            // If testing with specific campaign ID, filter campaigns
            if ($testCampaignId) {
                $campaigns = array_filter($campaigns, function($campaign) use ($testCampaignId) {
                    return ($campaign->campaign_id ?? '') == $testCampaignId;
                });
                $campaigns = array_values($campaigns); // Re-index array
                
                if (empty($campaigns)) {
                    $this->warn("⚠️  Campaign ID '{$testCampaignId}' not found in pink DIL campaigns list.");
                    return 0;
                }
                
                $this->info("🔍 TEST MODE: Processing only Campaign ID: {$testCampaignId}");
                $this->info("");
            }

            $this->info("📊 Found " . count($campaigns) . " campaigns to pause");
            $this->info("");

            $this->info("📋 Campaigns to be paused:");
            foreach ($campaigns as $index => $campaign) {
                $campaignName = $campaign->campaignName ?? 'Unknown';
                $campaignId = $campaign->campaign_id ?? 'N/A';
                $acos = $campaign->acos ?? 0;
                $l7Views = $campaign->l7_views ?? 0;
                
                $this->line("   " . ($index + 1) . ". Campaign: {$campaignName} | ID: {$campaignId} | ACOS: " . number_format($acos, 2) . "% | L7 Views: " . number_format($l7Views, 0));
            }
            
            $this->info("");

            if ($isDryRun) {
                $this->info("🔍 DRY-RUN: Would pause " . count($campaigns) . " campaign(s)");
                $this->info("");
                return 0;
            }

            $this->info("🔄 Pausing ads via eBay3 API...");
            $this->info("");

            $totalSuccess = 0;
            $totalFailed = 0;
            $hasError = false;
            $pausedCampaigns = []; // Track paused campaigns

            $accessToken = $this->getEbay3AccessToken();

            if (!$accessToken) {
                $this->error("✗ Failed to get access token");
                return 1;
            }

            foreach ($campaigns as $campaignIndex => $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                $campaignName = $campaign->campaignName ?? 'Unknown';
                
                if (empty($campaignId)) {
                    continue;
                }

                $this->info("📦 Processing campaign " . ($campaignIndex + 1) . "/" . count($campaigns) . ": {$campaignName} (ID: {$campaignId})");

                try {
                    // Pause the entire campaign using campaign-level pause endpoint
                    $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/pause";

                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type'  => 'application/json',
                        ])->post($endpoint);

                        if ($response->successful()) {
                            $totalSuccess++;
                            
                            // Update campaign status to PAUSED in database and set pink_dil_paused_at timestamp
                            $updatedCount = Ebay3PriorityReport::where('campaign_id', $campaignId)
                                ->where('campaignStatus', 'RUNNING')
                                ->update([
                                    'campaignStatus' => 'PAUSED',
                                    'pink_dil_paused_at' => now()
                                ]);
                            
                            if ($updatedCount > 0) {
                                // Track paused campaign
                                $pausedCampaigns[] = [
                                    'campaign_id' => $campaignId,
                                    'campaign_name' => $campaignName,
                                    'acos' => $campaign->acos ?? 0,
                                    'l7_views' => $campaign->l7_views ?? 0,
                                    'paused_at' => now()->toDateTimeString()
                                ];
                            }
                            
                            $this->info("   ✅ Campaign paused successfully");
                            $this->info("   ✅ Updated campaign status to PAUSED in database");
                        } else {
                            $totalFailed++;
                            $hasError = true;
                            $this->error("   ❌ Failed to pause campaign: " . $response->body());
                        }
                    } catch (\Exception $e) {
                        $totalFailed++;
                        $hasError = true;
                        $this->error("   ❌ Exception pausing campaign: " . $e->getMessage());
                    }

                } catch (\Exception $e) {
                    $hasError = true;
                    $this->error("   ❌ Campaign failed: " . $e->getMessage());
                    $totalFailed++;
                }

                $this->info("");
            }

            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📊 Pause Results");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Status: " . (!$hasError ? "✅ Success" : ($totalSuccess > 0 ? "⚠️  Partial Success" : "❌ Failed")));
            $this->info("📈 Summary: {$totalSuccess} campaign(s) paused successfully, {$totalFailed} failed");
            $this->info("📋 Campaigns Paused: " . count($pausedCampaigns));
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // Note: No need to store in cache anymore - data is in database column pink_dil_paused_at

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getEbay3PinkDilCampaigns($testCampaignId = null)
    {
        try {
            $normalizeSku = fn ($sku) => trim(preg_replace('/[^\S\r\n]+/u', ' ', strtoupper(trim($sku))));

            $productMasters = ProductMaster::whereNull('deleted_at')
                ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found in database!");
                return [];
            }

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

            $reportsQuery = Ebay3PriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%');
            
            // If testing with specific campaign ID, filter by it
            if ($testCampaignId) {
                $reportsQuery->where('campaign_id', $testCampaignId);
            }
            
            $reports = $reportsQuery->get();

            $result = [];
            $campaignMap = [];

            foreach ($productMasters as $pm) {
                $normalizedSku = $normalizeSku($pm->sku);
                $shopify = $shopifyData[$pm->sku] ?? null;
                $ebay = $ebayMetricData[$normalizedSku] ?? null;

                if (!$shopify) {
                    continue;
                }

                $matchedReports = $reports->filter(function ($item) use ($normalizedSku, $normalizeSku) {
                    return $normalizeSku($item->campaign_name ?? '') === $normalizedSku;
                });

                if ($matchedReports->isEmpty()) {
                    continue;
                }

                foreach ($matchedReports as $campaign) {
                    $campaignId = $campaign->campaign_id ?? '';
                    
                    if (empty($campaignId)) {
                        continue;
                    }

                    if (!isset($campaignMap[$campaignId])) {
                        $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                        $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                        
                        // Match controller ACOS calculation: if acos is 0, set it to 100 for display
                        $acos = $sales > 0 ? round(($adFees / $sales) * 100, 2) : 0;
                        if ($acos === 0) {
                            $acos = 100;
                        }

                        // Get l7_views from ebay metrics with fallback (matching controller logic)
                        $l7Views = 0;
                        if ($ebay) {
                            $l7Views = (float)($ebay->l7_views ?? 0);
                        } else {
                            // Fallback: Try to find by campaign name (matching controller fallback)
                            $campaignName = $campaign->campaign_name ?? '';
                            $ebayMetricByName = Ebay3Metric::where('sku', $campaignName)->first();
                            if ($ebayMetricByName) {
                                $l7Views = (float)($ebayMetricByName->l7_views ?? 0);
                            }
                        }

                        // Include campaign if: l7_views >= 50 AND acos >= 10 (matching view filter logic)
                        // Skip if: l7_views < 50 OR acos < 10
                        if ($l7Views < 50 || $acos < 10) {
                            continue;
                        }

                        $campaignMap[$campaignId] = [
                            'sku' => $pm->sku,
                            'campaign_id' => $campaignId,
                            'campaignName' => $campaign->campaign_name ?? '',
                            'acos' => $acos,
                            'l7_views' => $l7Views,
                        ];
                    }
                }
            }

            // Process additional RUNNING campaigns not in product_masters (matching controller logic)
            $productMasterSkus = $productMasters->pluck('sku')->map(function($sku) use ($normalizeSku) {
                return $normalizeSku($sku);
            })->unique()->values()->all();

            $additionalL7 = Ebay3PriorityReport::where('report_range', 'L7')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get()
                ->pluck('campaign_name')
                ->map(function($name) { 
                    return strtoupper(trim($name)); 
                });

            $additionalL30 = Ebay3PriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get()
                ->pluck('campaign_name')
                ->map(function($name) { 
                    return strtoupper(trim($name)); 
                });

            $additionalRunningCampaigns = $additionalL7->merge($additionalL30)
                ->unique()
                ->filter(function($campaignSku) use ($productMasterSkus) {
                    return !in_array($campaignSku, $productMasterSkus);
                })
                ->values()
                ->all();

            $allL30Reports = Ebay3PriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get();

            foreach ($additionalRunningCampaigns as $campaignSku) {
                $matchedCampaignsL30 = $allL30Reports->filter(function ($item) use ($campaignSku) {
                    return strtoupper(trim($item->campaign_name ?? '')) === $campaignSku;
                });
                $matchedCampaignL30 = $matchedCampaignsL30->first();

                if (!$matchedCampaignL30 || $matchedCampaignL30->campaignStatus !== 'RUNNING') {
                    continue;
                }

                $campaignId = $matchedCampaignL30->campaign_id ?? '';
                if (empty($campaignId) || isset($campaignMap[$campaignId])) {
                    continue;
                }

                $adFees = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
                
                // Match controller ACOS calculation: if acos is 0, set it to 100 for display
                $acos = $sales > 0 ? round(($adFees / $sales) * 100, 2) : 0;
                if ($acos === 0) {
                    $acos = 100;
                }

                // Get l7_views from ebay metrics using campaign name as SKU
                $ebayMetric = Ebay3Metric::where('sku', $matchedCampaignL30->campaign_name)->first();
                $l7Views = $ebayMetric ? (float)($ebayMetric->l7_views ?? 0) : 0;

                // Include campaign if: l7_views >= 50 AND acos >= 10 (matching view filter logic)
                // Skip if: l7_views < 50 OR acos < 10
                if ($l7Views < 50 || $acos < 10) {
                    continue;
                }

                $campaignMap[$campaignId] = [
                    'sku' => $matchedCampaignL30->campaign_name,
                    'campaign_id' => $campaignId,
                    'campaignName' => $matchedCampaignL30->campaign_name ?? '',
                    'acos' => $acos,
                    'l7_views' => $l7Views,
                ];
            }

            // Convert map to result array
            foreach ($campaignMap as $campaignId => $row) {
                $result[] = (object) $row;
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getEbay3PinkDilCampaigns: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

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
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['access_token'])) {
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 7200;

            Cache::put('ebay3_access_token', $accessToken, $expiresIn - 60);

            return $accessToken;
        }

        throw new \Exception("Failed to refresh token: " . json_encode($data));
    }

    private function getAdGroups($campaignId, $accessToken)
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(120)
                ->retry(3, 5000)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group");

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 401) {
                Cache::forget('ebay3_access_token');
                $accessToken = $this->getEbay3AccessToken();
                if ($accessToken) {
                    $response = Http::withToken($accessToken)
                        ->timeout(120)
                        ->retry(3, 5000)
                        ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad_group");
                    if ($response->successful()) {
                        return $response->json();
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

    private function getKeywords($campaignId, $adGroupId, $accessToken)
    {
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
                } else {
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
}
