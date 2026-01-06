<?php

namespace App\Console\Commands;

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
    protected $description = 'Automatically pause/enable FBA campaigns based on L30 sales data';

    protected $profileId = "4216505535403428";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $this->info("Starting FBA campaign auto pause/enable based on L30...");

            $campaigns = $this->getFbaCampaignsWithL30();

        if (empty($campaigns)) {
            $this->warn("No FBA campaigns found.");
            return 0;
        }

        $this->info("Found " . count($campaigns) . " FBA campaigns to process.");

        $campaignsToEnable = [];
        $campaignsToPause = [];

        foreach ($campaigns as $campaign) {
            $l30Units = $campaign->l30_units ?? 0;
            $inv = $campaign->inv ?? 0;
            $campaignId = $campaign->campaign_id ?? '';
            $campaignName = $campaign->campaignName ?? '';

            if (empty($campaignId)) {
                continue;
            }

            // Enable if INV > 0 AND L30 = 0
            if ($inv > 0 && $l30Units == 0) {
                $campaignsToEnable[] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $campaignName,
                    'inv' => $inv,
                    'l30_units' => $l30Units
                ];
            } elseif ($l30Units > 0) {
                // Pause only when L30 > 0
                $campaignsToPause[] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $campaignName,
                    'inv' => $inv,
                    'l30_units' => $l30Units
                ];
            }
            // Otherwise (INV = 0 AND L30 = 0): No action, skip
        }

        $this->line("");
        $this->info("Campaigns to ENABLE (INV > 0 AND L30 = 0): " . count($campaignsToEnable));
        $this->info("Campaigns to PAUSE (L30 > 0): " . count($campaignsToPause));
        $this->line("");

        $enableCount = 0;
        $pauseCount = 0;

        // Enable campaigns
        if (!empty($campaignsToEnable)) {
            $enableIds = array_column($campaignsToEnable, 'campaign_id');
            $result = $this->updateCampaignStates($enableIds, 'ENABLED');
            if ($result['status'] == 200) {
                $enableCount = count($enableIds);
                $this->info("✓ Enabled " . $enableCount . " campaigns.");
                foreach ($campaignsToEnable as $camp) {
                    $this->line("  Enabled: {$camp['campaign_name']} (INV: {$camp['inv']}, L30: {$camp['l30_units']})");
                }
            } else {
                $this->error("✗ Failed to enable campaigns: " . ($result['message'] ?? 'Unknown error'));
            }
        }

        // Pause campaigns
        if (!empty($campaignsToPause)) {
            $pauseIds = array_column($campaignsToPause, 'campaign_id');
            $result = $this->updateCampaignStates($pauseIds, 'PAUSED');
            if ($result['status'] == 200) {
                $pauseCount = count($pauseIds);
                $this->info("✓ Paused " . $pauseCount . " campaigns.");
                foreach ($campaignsToPause as $camp) {
                    $this->line("  Paused: {$camp['campaign_name']} (INV: {$camp['inv']}, L30: {$camp['l30_units']})");
                }
            } else {
                $this->error("✗ Failed to pause campaigns: " . ($result['message'] ?? 'Unknown error'));
            }
        }

            $this->line("");
            $this->info("Summary: Enabled {$enableCount} campaigns, Paused {$pauseCount} campaigns");

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::disconnect();
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
                DB::disconnect();
                return [];
            }

            // Extract seller SKUs for campaigns matching
            $sellerSkus = $fbaData->pluck('seller_sku')->filter()->unique()->values()->toArray();

            if (empty($sellerSkus)) {
                $this->warn("No valid seller SKUs found!");
                DB::disconnect();
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

        $result = [];

        // Process KW campaigns
        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));

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
        
        DB::disconnect();
        return $result;
    
    } catch (\Exception $e) {
        $this->error("Error in getFbaCampaignsWithL30: " . $e->getMessage());
        $this->error("Stack trace: " . $e->getTraceAsString());
        return [];
    } finally {
        DB::disconnect();
    }
}

    protected function getAccessToken()
    {
        return Cache::remember('amazon_ads_access_token', 55 * 60, function () {
            $client = new Client();

            $response = $client->post('https://api.amazon.com/auth/o2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('AMAZON_ADS_REFRESH_TOKEN'),
                    'client_id' => env('AMAZON_ADS_CLIENT_ID'),
                    'client_secret' => env('AMAZON_ADS_CLIENT_SECRET'),
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
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
}

