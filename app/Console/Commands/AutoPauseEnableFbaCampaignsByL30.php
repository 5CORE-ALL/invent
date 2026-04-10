<?php

namespace App\Console\Commands;

use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaMonthlySale;
use App\Models\FbaTable;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AutoPauseEnableFbaCampaignsByL30 extends Command
{
    protected $signature = 'fba:auto-pause-enable-by-l30';
    protected $description = 'Pause FBA-suffix SKU ads (SP KW/PT + SB HL) on Amazon when FBA available qty is 0 only; does not auto-enable';

    protected $profileId = "4216505535403428";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $this->info('Starting FBA campaign auto-pause when FBA inventory is 0...');

            $campaigns = $this->getFbaCampaignsWithL30();

        if (empty($campaigns)) {
            $this->warn("No FBA campaigns found.");
            return 0;
        }

        $this->info("Found " . count($campaigns) . " FBA campaigns to process.");

        $spPauseIds = [];
        $sbPauseIds = [];
        $metaPause = [];

        foreach ($campaigns as $campaign) {
            $l30Units = (int) ($campaign->l30_units ?? 0);
            $inv = (int) ($campaign->inv ?? 0);
            $campaignId = $campaign->campaign_id ?? '';
            $campaignName = $campaign->campaignName ?? '';
            $isHl = (($campaign->type ?? 'KW') === 'HL');

            if ($campaignId === null || $campaignId === '') {
                continue;
            }
            $campaignId = (string) $campaignId;

            if ($inv <= 0) {
                if ($isHl) {
                    $sbPauseIds[] = $campaignId;
                } else {
                    $spPauseIds[] = $campaignId;
                }
                $metaPause[] = [
                    'campaign_name' => $campaignName,
                    'inv' => $inv,
                    'l30_units' => $l30Units,
                    'channel' => $isHl ? 'SB (HL)' : 'SP',
                ];
            }
        }

        $spPauseIds = array_values(array_unique($spPauseIds));
        $sbPauseIds = array_values(array_unique($sbPauseIds));

        $this->line('');
        $this->info('Campaigns to PAUSE (FBA inv ≤ 0 only): ' . count($metaPause) . ' (SP: ' . count($spPauseIds) . ', SB: ' . count($sbPauseIds) . ')');
        $this->line('');

        $pauseCount = 0;

        if (! empty($spPauseIds)) {
            $result = $this->updateCampaignStates($spPauseIds, 'PAUSED');
            if ($result['status'] === 200) {
                $pauseCount += count($spPauseIds);
                $this->info('✓ Paused ' . count($spPauseIds) . ' SP campaign(s).');
            } else {
                $this->error('✗ Failed to pause SP campaigns: ' . ($result['message'] ?? 'Unknown error'));
            }
        }
        if (! empty($sbPauseIds)) {
            $result = $this->updateSbCampaignStates($sbPauseIds, 'PAUSED');
            if ($result['status'] === 200) {
                $pauseCount += count($sbPauseIds);
                $this->info('✓ Paused ' . count($sbPauseIds) . ' SB (HL) campaign(s).');
            } else {
                $this->error('✗ Failed to pause SB campaigns: ' . ($result['message'] ?? 'Unknown error'));
            }
        }
        foreach ($metaPause as $camp) {
            $this->line("  Paused [{$camp['channel']}]: {$camp['campaign_name']} (INV: {$camp['inv']}, L30: {$camp['l30_units']})");
        }

            $this->line('');
            $this->info("Summary: Paused {$pauseCount} campaign id(s) (FBA qty ≤ 0 only)");

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    protected function getFbaCampaignsWithL30()
    {
        try {
            // Get all FBA records
            $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                ->orderBy('seller_sku', 'asc')
                ->get();

            if ($fbaData->isEmpty()) {
                $this->warn("No FBA records found in database!");
                DB::connection()->disconnect();
                return [];
            }

            // Extract seller SKUs for campaigns matching
            $sellerSkus = $fbaData->pluck('seller_sku')->filter()->unique()->values()->toArray();

            if (empty($sellerSkus)) {
                $this->warn("No valid seller SKUs found!");
                DB::connection()->disconnect();
                return [];
            }

            // Get FBA monthly sales data (contains l30_units)
            $fbaMonthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper(trim($item->seller_sku));
                });

            // Get FBA KW campaigns (excluding PT)
            $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

            // Get FBA PT campaigns
            $amazonSpCampaignReportsL30Pt = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where(function ($q) {
                    $q->where('campaignName', 'LIKE', '%FBA PT%')
                        ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

            // SB Headline (HL) L30 — restrict to names containing FBA (MSKU campaigns)
            $amazonSbL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->where('campaignName', 'LIKE', '%FBA%')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

        $result = [];

        // Process KW campaigns (seller_sku must end with FBA — MSKU policy)
        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            if (! str_ends_with($sellerSkuUpper, 'FBA')) {
                continue;
            }

            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);
            $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            if ($matchedCampaignL30 && !empty($matchedCampaignL30->campaign_id)) {
                $result[] = (object) [
                    'campaign_id' => $matchedCampaignL30->campaign_id,
                    'campaignName' => $matchedCampaignL30->campaignName,
                    'inv' => $fba->quantity_available ?? 0,
                    'l30_units' => $l30Units,
                    'type' => 'KW'
                ];
            }
        }

        // Process PT campaigns (unique by base SKU)
        $processedPtSkus = [];
        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            if (! str_ends_with($sellerSkuUpper, 'FBA')) {
                continue;
            }

            // Get base SKU (without FBA) for PT uniqueness
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            if (in_array($baseSkuUpper, $processedPtSkus)) {
                continue;
            }

            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);
            $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;

            $matchedCampaignL30Pt = $amazonSpCampaignReportsL30Pt->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                $expected = $cleanSku . ' PT';
                return $cleanName === $expected || $cleanName === ($expected . '.');
            });

            if ($matchedCampaignL30Pt && !empty($matchedCampaignL30Pt->campaign_id)) {
                $processedPtSkus[] = $baseSkuUpper;
                $result[] = (object) [
                    'campaign_id' => $matchedCampaignL30Pt->campaign_id,
                    'campaignName' => $matchedCampaignL30Pt->campaignName,
                    'inv' => $fba->quantity_available ?? 0,
                    'l30_units' => $l30Units,
                    'type' => 'PT'
                ];
            }
        }

        $processedHlCampaignIds = [];
        foreach ($fbaData as $fba) {
            $sellerSkuUpper = strtoupper(trim($fba->seller_sku ?? ''));
            if (! str_ends_with($sellerSkuUpper, 'FBA')) {
                continue;
            }

            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);
            $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;

            $matchedHl = $amazonSbL30->first(function ($item) use ($sellerSkuUpper) {
                $cn = $this->normalizeHlCampaignName($item->campaignName ?? '');
                $sku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                $skuNd = rtrim($sku, '.');

                return $cn === $sku
                    || $cn === $sku . ' HEAD'
                    || $cn === $skuNd
                    || $cn === $skuNd . ' HEAD';
            });

            if ($matchedHl && ! empty($matchedHl->campaign_id)) {
                $cid = (string) $matchedHl->campaign_id;
                if (isset($processedHlCampaignIds[$cid])) {
                    continue;
                }
                $processedHlCampaignIds[$cid] = true;
                $result[] = (object) [
                    'campaign_id' => $matchedHl->campaign_id,
                    'campaignName' => $matchedHl->campaignName,
                    'inv' => $fba->quantity_available ?? 0,
                    'l30_units' => $l30Units,
                    'type' => 'HL',
                ];
            }
        }

        DB::connection()->disconnect();
        return $result;
    
    } catch (\Exception $e) {
        $this->error("Error in getFbaCampaignsWithL30: " . $e->getMessage());
        $this->error("Stack trace: " . $e->getTraceAsString());
        return [];
    } finally {
        DB::connection()->disconnect();
    }
}

    protected function getAccessToken()
    {
        return Cache::remember('amazon_ads_access_token', 55 * 60, function () {
            $client = new Client();

            $response = $client->post('https://api.amazon.com/auth/o2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => config('services.amazon_ads.refresh_token'),
                    'client_id' => config('services.amazon_ads.client_id'),
                    'client_secret' => config('services.amazon_ads.client_secret'),
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        });
    }

    protected function updateCampaignStates(array $campaignIds, string $state)
    {
        if (empty($campaignIds)) {
            return [
                'message' => 'No campaign IDs provided',
                'status' => 400
            ];
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/campaigns';
        $results = [];

        try {
            // Prepare campaigns data
            $allCampaigns = [];
            foreach ($campaignIds as $campaignId) {
                $allCampaigns[] = [
                    'campaignId' => (string) $campaignId,
                    'state' => $state
                ];
            }

            // Process in chunks of 100
            $chunks = array_chunk($allCampaigns, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spCampaign.v3+json',
                        'Accept' => 'application/vnd.spCampaign.v3+json',
                    ],
                    'json' => [
                        'campaigns' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }

            try {
                $updateData = ['campaignStatus' => $state];
                if ($state === 'PAUSED') {
                    $updateData['pink_dil_paused_at'] = now();
                } else {
                    $updateData['pink_dil_paused_at'] = null;
                }
                AmazonSpCampaignReport::whereIn('campaign_id', $campaignIds)->update($updateData);
            } catch (\Exception $dbError) {
                $this->warn('SP campaign DB status update failed: ' . $dbError->getMessage());
            }

            return [
                'message' => 'Campaign states updated successfully',
                'data' => $results,
                'status' => 200,
            ];

        } catch (\Exception $e) {
            $this->error("AutoPauseEnableFbaCampaignsByL30 Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [
                'message' => 'Error updating campaign states',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    protected function normalizeHlCampaignName(?string $campaignName): string
    {
        $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', (string) $campaignName);
        $cn = preg_replace('/\s+/', ' ', $cn);
        $cn = preg_replace('/\s+PCS\b/i', 'PCS', $cn);

        return strtoupper(trim(rtrim($cn, '.')));
    }

    protected function updateSbCampaignStates(array $campaignIds, string $state): array
    {
        if (empty($campaignIds)) {
            return [
                'message' => 'No campaign IDs provided',
                'status' => 400,
            ];
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sb/v4/campaigns';

        try {
            $allCampaigns = [];
            foreach ($campaignIds as $campaignId) {
                $allCampaigns[] = [
                    'campaignId' => (string) $campaignId,
                    'state' => $state,
                ];
            }

            foreach (array_chunk($allCampaigns, 10) as $chunk) {
                $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.sbcampaignresource.v4+json',
                        'Accept' => 'application/vnd.sbcampaignresource.v4+json',
                    ],
                    'json' => [
                        'campaigns' => $chunk,
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);
            }

            try {
                $updateData = ['campaignStatus' => $state];
                if ($state === 'PAUSED') {
                    $updateData['pink_dil_paused_at'] = now();
                } else {
                    $updateData['pink_dil_paused_at'] = null;
                }
                AmazonSbCampaignReport::whereIn('campaign_id', $campaignIds)->update($updateData);
            } catch (\Exception $dbError) {
                $this->warn('SB campaign DB status update failed: ' . $dbError->getMessage());
            }

            return [
                'message' => 'SB campaign states updated successfully',
                'status' => 200,
            ];
        } catch (\Exception $e) {
            $this->error('AutoPauseEnableFbaCampaignsByL30 SB Error: ' . $e->getMessage());

            return [
                'message' => 'Error updating SB campaign states',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }
}

