<?php

namespace App\Console\Commands;

use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Ebay1PausePinkDilKwAds extends Command
{
    protected $signature = 'ebay1:auto-pause-pink-dil-kw-ads {--dry-run : Run without actually pausing ads} {--campaign-id= : Test with specific campaign ID only}';
    protected $description = 'Automatically pause eBay1 KW ads if DIL is pink (DIL > 50%). Ignore if ACOS < 7%. Only for RUNNING campaigns.';

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
            $this->info("🚀 Starting eBay1 Auto-Pause Pink DIL KW Ads");
            if ($isDryRun) {
                $this->warn("⚠️  DRY-RUN MODE: No ads will be paused on eBay");
            }
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            $testCampaignId = $this->option('campaign-id');
            
            $campaigns = $this->getEbay1PinkDilCampaigns($testCampaignId);

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
                $dil = $campaign->dil ?? 0;
                $acos = $campaign->acos ?? 0;
                
                $this->line("   " . ($index + 1) . ". Campaign: {$campaignName} | ID: {$campaignId} | DIL: " . number_format($dil, 2) . "% | ACOS: " . number_format($acos, 2) . "%");
            }
            
            $this->info("");

            if ($isDryRun) {
                $this->info("🔍 DRY-RUN: Would pause " . count($campaigns) . " campaign(s)");
                $this->info("");
                return 0;
            }

            $this->info("🔄 Pausing ads via eBay1 API...");
            $this->info("");

            $totalSuccess = 0;
            $totalFailed = 0;
            $hasError = false;
            $pausedCampaigns = []; // Track paused campaigns

            $accessToken = $this->getEbay1AccessToken();

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
                    $adGroups = $this->getAdGroups($campaignId, $accessToken);
                    if (empty($adGroups['adGroups'] ?? [])) {
                        $this->warn("   ⚠️  No ad groups found for campaign {$campaignId}");
                        continue;
                    }

                    $keywordsPaused = 0;
                    $keywordsFailed = 0;

                    foreach ($adGroups['adGroups'] as $adGroup) {
                        $adGroupId = $adGroup['adGroupId'];
                        $keywords = $this->getKeywords($campaignId, $adGroupId, $accessToken);

                        if (empty($keywords)) {
                            continue;
                        }

                        // Process keywords in chunks of 100
                        foreach (array_chunk($keywords, 100) as $keywordChunk) {
                            $payload = [
                                "requests" => []
                            ];

                            foreach ($keywordChunk as $keywordId) {
                                $payload["requests"][] = [
                                    "keywordId" => $keywordId,
                                    "keywordStatus" => "PAUSED"
                                ];
                            }

                            $endpoint = "https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_keyword";

                            try {
                                $response = Http::withHeaders([
                                    'Authorization' => "Bearer {$accessToken}",
                                    'Content-Type'  => 'application/json',
                                ])->post($endpoint, $payload);

                                if ($response->successful()) {
                                    $keywordsPaused += count($keywordChunk);
                                } else {
                                    $keywordsFailed += count($keywordChunk);
                                    $hasError = true;
                                    $this->error("   ❌ Failed to pause keywords: " . $response->body());
                                }
                            } catch (\Exception $e) {
                                $keywordsFailed += count($keywordChunk);
                                $hasError = true;
                                $this->error("   ❌ Exception pausing keywords: " . $e->getMessage());
                            }
                        }
                    }

                    if ($keywordsPaused > 0) {
                        $this->info("   ✅ Paused {$keywordsPaused} keywords");
                        $totalSuccess += $keywordsPaused;
                        
                        // Update campaign status to PAUSED in database and set pink_dil_paused_at timestamp
                        $updatedCount = EbayPriorityReport::where('campaign_id', $campaignId)
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
                                'dil' => $campaign->dil ?? 0,
                                'acos' => $campaign->acos ?? 0,
                                'keywords_paused' => $keywordsPaused,
                                'paused_at' => now()->toDateTimeString()
                            ];
                        }
                        
                        $this->info("   ✅ Updated campaign status to PAUSED in database");
                    }
                    if ($keywordsFailed > 0) {
                        $this->error("   ❌ Failed to pause {$keywordsFailed} keywords");
                        $totalFailed += $keywordsFailed;
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
            $this->info("📈 Summary: {$totalSuccess} keywords paused successfully, {$totalFailed} failed");
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

    public function getEbay1PinkDilCampaigns($testCampaignId = null)
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
            $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

            $reportsQuery = EbayPriorityReport::where('report_range', 'L30')
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

                // Calculate DIL: (L30 / INV) * 100
                $inv = (float)($shopify->inv ?? 0);
                $l30 = (float)($shopify->quantity ?? 0);
                $dil = $inv > 0 ? ($l30 / $inv) * 100 : 0;

                // DIL is pink if DIL > 50%
                if ($dil <= 50) {
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
                        $acos = $sales > 0 ? round(($adFees / $sales) * 100, 2) : ($adFees > 0 ? 100 : 0);

                        // Ignore if ACOS < 7%
                        if ($acos < 7) {
                            continue;
                        }

                        $campaignMap[$campaignId] = [
                            'sku' => $pm->sku,
                            'campaign_id' => $campaignId,
                            'campaignName' => $campaign->campaign_name ?? '',
                            'dil' => $dil,
                            'acos' => $acos,
                        ];
                    }
                }
            }

            // Convert map to result array
            foreach ($campaignMap as $campaignId => $row) {
                $result[] = (object) $row;
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getEbay1PinkDilCampaigns: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function getEbay1AccessToken()
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
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['access_token'])) {
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 7200;

            Cache::put('ebay_access_token', $accessToken, $expiresIn - 60);

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
                Cache::forget('ebay_access_token');
                $accessToken = $this->getEbay1AccessToken();
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
                        Cache::forget('ebay_access_token');
                        $accessToken = $this->getEbay1AccessToken();
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
