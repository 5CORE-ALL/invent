<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TestGshCampaignData extends Command
{
    protected $signature = 'test:gsh-campaign-data {--live : Fetch live data from Amazon API}';
    protected $description = 'Test GSH campaign data to debug budget issue';

    public function handle()
    {
        $this->info("╔═══════════════════════════════════════════════════════════╗");
        $this->info("║   GSH CAMPAIGN DATA VERIFICATION TOOL                     ║");
        $this->info("╚═══════════════════════════════════════════════════════════╝\n");

        // Step 1: Check database
        $this->checkDatabase();

        // Step 2: Check configuration
        $this->info("\n--- STEP 2: Configuration Check ---");
        $profileId = env('AMAZON_ADS_PROFILE_IDS');
        $clientId = env('AMAZON_ADS_CLIENT_ID');
        
        if (!empty($profileId) && !empty($clientId)) {
            $this->info("✓ Amazon Ads credentials configured");
            $this->info("  Profile ID: " . substr($profileId, 0, 10) . "...");
            
            if ($this->option('live')) {
                $this->fetchLiveData();
            } else {
                $this->warn("\nTo fetch live data from Amazon API, run:");
                $this->warn("  php artisan test:gsh-campaign-data --live");
            }
        } else {
            $this->warn("⚠ Amazon Ads credentials not configured");
            $this->info("  Cannot fetch live data without API credentials");
        }

        $this->info("\n=== SUMMARY ===");
        $this->displaySummary();

        return 0;
    }

    private function checkDatabase()
    {
        $this->info("--- STEP 1: Database Analysis ---\n");

        // Get all GSH campaigns
        $allGshCampaigns = DB::table('amazon_sp_campaign_reports')
            ->where('campaignName', 'LIKE', '%GSH%')
            ->where('campaignName', 'NOT LIKE', '%GSH%PT%')
            ->where('report_date_range', 'L30')
            ->orderBy('campaignName')
            ->orderBy('campaignStatus')
            ->orderBy('created_at', 'DESC')
            ->select('campaignName', 'campaign_id', 'campaignBudgetAmount', 'campaignStatus', 
                    'spend', 'sales30d', 'created_at', 'updated_at')
            ->get();

        $this->info("Found " . $allGshCampaigns->count() . " GSH campaigns (excluding PT) in L30 data:\n");
        $this->info(str_repeat("=", 130));

        $parentGshCampaigns = [];

        foreach ($allGshCampaigns as $campaign) {
            if ($campaign->campaignName === 'PARENT GSH') {
                $parentGshCampaigns[] = $campaign;
            }

            $this->displayCampaign($campaign);
        }

        // Focus on PARENT GSH
        if (!empty($parentGshCampaigns)) {
            $this->info("\n" . str_repeat("=", 130));
            $this->info("\n🔍 PARENT GSH CAMPAIGN ANALYSIS:\n");

            foreach ($parentGshCampaigns as $idx => $campaign) {
                $num = $idx + 1;
                $this->info("Campaign #{$num}:");
                $this->info("  Status:          " . $campaign->campaignStatus);
                $this->info("  Campaign ID:     " . $campaign->campaign_id);
                $this->info("  Budget:          \$" . $campaign->campaignBudgetAmount);
                $this->info("  Spend (L30):     \$" . number_format($campaign->spend, 2));
                $this->info("  Sales (L30):     \$" . number_format($campaign->sales30d, 2));
                
                // Calculate ACOS
                if ($campaign->spend > 0 && $campaign->sales30d > 0) {
                    $acos = ($campaign->spend / $campaign->sales30d) * 100;
                    $this->info("  ACOS:            " . number_format($acos, 2) . "%");
                    
                    // Calculate SBGT
                    $sbgt = $this->calculateSbgt($acos);
                    $this->info("  Calculated SBGT: \$" . $sbgt);
                } else {
                    $this->info("  ACOS:            N/A");
                }
                
                $this->info("  Last Updated:    " . $campaign->updated_at);
                $this->info("");
            }

            // Check what controller would select
            $this->info("--- What the Controller Sees ---");
            
            $controllerSelection = collect($parentGshCampaigns)->first(function($item) {
                return strtoupper(trim(rtrim($item->campaignName ?? '', '.'))) === 'PARENT GSH';
            });

            if ($controllerSelection) {
                $this->warn("\n⚠️  ISSUE IDENTIFIED:");
                $this->warn("The controller's ->first() method would select:");
                $this->warn("  Status: " . $controllerSelection->campaignStatus);
                $this->warn("  Budget: \$" . $controllerSelection->campaignBudgetAmount);
                
                if ($controllerSelection->campaignStatus === 'ARCHIVED') {
                    $this->error("\n❌ BUG: Controller selects ARCHIVED campaign!");
                    $this->error("This is why the table shows \$" . $controllerSelection->campaignBudgetAmount . " instead of the ENABLED campaign's budget.");
                }
            }

            // Check inventory
            $this->info("\n--- Inventory Check ---");
            $inventory = DB::table('shopify_skus')->where('sku', 'PARENT GSH')->first();
            if ($inventory) {
                $this->info("Shopify Inventory: " . ($inventory->inv ?? 'NULL'));
                if (($inventory->inv ?? 0) == 0) {
                    $this->warn("⚠️  Inventory is 0 - AutoUpdateAmazonBgtKw cronjob will SKIP this campaign");
                }
            } else {
                $this->warn("No inventory record found");
            }
        } else {
            $this->warn("\nNo PARENT GSH campaigns found in database!");
        }
    }

    private function displayCampaign($campaign)
    {
        $statusColor = $campaign->campaignStatus === 'ENABLED' ? 'info' : 'comment';
        
        $line = sprintf(
            "%-25s | %-20s | \$%-8s | %-10s | Spend: \$%-8s | Sales: \$%-8s",
            $campaign->campaignName,
            substr($campaign->campaign_id, 0, 18) . "...",
            number_format($campaign->campaignBudgetAmount, 2),
            $campaign->campaignStatus,
            number_format($campaign->spend, 2),
            number_format($campaign->sales30d, 2)
        );
        
        $this->line($line);
    }

    private function calculateSbgt($acos)
    {
        if ($acos > 25) return 1;
        elseif ($acos >= 20) return 2;
        elseif ($acos >= 15) return 4;
        elseif ($acos >= 10) return 6;
        elseif ($acos >= 5) return 8;
        else return 10;
    }

    private function fetchLiveData()
    {
        $this->info("\n--- STEP 3: Fetching Live Data from Amazon API ---\n");

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error("Failed to get access token - cannot fetch live data");
            return;
        }

        $profileId = env('AMAZON_ADS_PROFILE_IDS');
        $today = now();
        $endDate = $today->copy()->subDay()->toDateString();
        $startDate = $today->copy()->subDays(30)->toDateString();

        $this->info("Requesting campaign report for date range: {$startDate} to {$endDate}");

        $reportResponse = Http::timeout(30)
            ->withToken($accessToken)
            ->withHeaders([
                'Amazon-Advertising-API-Scope' => $profileId,
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                'Content-Type' => 'application/vnd.createasyncreportrequest.v3+json',
            ])
            ->post('https://advertising-api.amazon.com/reporting/reports', [
                'name' => 'TEST_GSH_LIVE',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'configuration' => [
                    'adProduct' => 'SPONSORED_PRODUCTS',
                    'groupBy' => ['campaign'],
                    'reportTypeId' => 'spCampaigns',
                    'columns' => [
                        'campaignId', 'campaignName', 'campaignBudgetAmount', 
                        'campaignStatus', 'spend', 'sales30d'
                    ],
                    'format' => 'GZIP_JSON',
                    'timeUnit' => 'SUMMARY',
                ]
            ]);

        if (!$reportResponse->ok()) {
            $this->error("Failed to request report: " . $reportResponse->body());
            return;
        }

        $reportId = $reportResponse->json('reportId');
        $this->info("Report requested. ID: {$reportId}");
        $this->info("Waiting for report to be ready (max 60 seconds)...");

        $reportUrl = $this->pollForReport($accessToken, $profileId, $reportId);
        
        if (!$reportUrl) {
            $this->error("Report not ready in time");
            return;
        }

        $this->info("Downloading report...");
        $downloadResponse = Http::timeout(30)->withoutVerifying()->get($reportUrl);
        
        if (!$downloadResponse->ok()) {
            $this->error("Failed to download report");
            return;
        }

        $jsonString = gzdecode($downloadResponse->body());
        $campaigns = json_decode($jsonString, true);

        $gshCampaigns = array_filter($campaigns, function($c) {
            return stripos($c['campaignName'] ?? '', 'PARENT GSH') !== false 
                && stripos($c['campaignName'] ?? '', 'PT') === false;
        });

        $this->info("\n✓ Live data from Amazon API:");
        $this->info(str_repeat("=", 80));
        
        foreach ($gshCampaigns as $campaign) {
            $this->info("\n🔹 Campaign: " . $campaign['campaignName']);
            $this->info(str_repeat("-", 80));
            $this->info("  📋 FULL API RESPONSE DATA:");
            $this->info("  " . str_repeat("─", 78));
            
            // Display all fields from API
            foreach ($campaign as $key => $value) {
                if (is_numeric($value)) {
                    $this->info("  " . str_pad($key . ":", 30) . (is_float($value) ? number_format($value, 2) : $value));
                } else {
                    $this->info("  " . str_pad($key . ":", 30) . $value);
                }
            }
            
            $this->info("\n  💰 KEY FIELDS:");
            $this->info("  " . str_repeat("─", 78));
            $this->info("  Budget (campaignBudgetAmount): \$" . ($campaign['campaignBudgetAmount'] ?? '0'));
            $this->info("  Status (campaignStatus):       " . ($campaign['campaignStatus'] ?? 'N/A'));
            $this->info("  Spend (spend):                 \$" . number_format($campaign['spend'] ?? 0, 2));
            $this->info("  Sales (sales30d):              \$" . number_format($campaign['sales30d'] ?? 0, 2));
        }
        
        $this->info("\n" . str_repeat("=", 80));
    }

    private function pollForReport($accessToken, $profileId, $reportId, $maxAttempts = 12)
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(5);
            
            $statusResponse = Http::timeout(30)
                ->withToken($accessToken)
                ->withHeaders([
                    'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                    'Amazon-Advertising-API-Scope' => $profileId,
                ])
                ->get("https://advertising-api.amazon.com/reporting/reports/{$reportId}");

            if (!$statusResponse->successful()) {
                continue;
            }

            $status = $statusResponse['status'] ?? 'UNKNOWN';
            
            if ($status === 'COMPLETED') {
                return $statusResponse['location'] ?? $statusResponse['url'] ?? null;
            }
            
            if ($status === 'FAILED') {
                return null;
            }
        }
        
        return null;
    }

    private function getAccessToken()
    {
        try {
            $clientId = env('AMAZON_ADS_CLIENT_ID');
            $clientSecret = env('AMAZON_ADS_CLIENT_SECRET');
            $refreshToken = env('AMAZON_ADS_REFRESH_TOKEN');

            if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
                return null;
            }

            $tokenResponse = Http::timeout(15)->asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$tokenResponse->successful()) {
                return null;
            }

            return $tokenResponse['access_token'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function displaySummary()
    {
        $enabledCampaign = DB::table('amazon_sp_campaign_reports')
            ->where('campaignName', 'PARENT GSH')
            ->where('campaignStatus', 'ENABLED')
            ->where('report_date_range', 'L30')
            ->orderBy('created_at', 'DESC')
            ->first();

        $archivedCampaign = DB::table('amazon_sp_campaign_reports')
            ->where('campaignName', 'PARENT GSH')
            ->where('campaignStatus', 'ARCHIVED')
            ->where('report_date_range', 'L30')
            ->orderBy('created_at', 'DESC')
            ->first();

        $this->info("\n📊 PARENT GSH Status:");
        
        if ($enabledCampaign) {
            $this->info("  ✓ ENABLED campaign:  Budget = \$" . $enabledCampaign->campaignBudgetAmount);
            $this->info("                       Sales = \$" . number_format($enabledCampaign->sales30d, 2));
        } else {
            $this->warn("  ✗ No ENABLED campaign found");
        }

        if ($archivedCampaign) {
            $this->warn("  ⚠ ARCHIVED campaign: Budget = \$" . $archivedCampaign->campaignBudgetAmount . " (THIS IS SHOWN IN TABLE!)");
        }

        $this->info("\n🔧 Issue:");
        $this->info("  - You set daily budget to: \$100 in Amazon");
        if ($enabledCampaign) {
            $this->info("  - Database shows:          \$" . $enabledCampaign->campaignBudgetAmount);
        }
        if ($archivedCampaign) {
            $this->error("  - Table displays:          \$" . $archivedCampaign->campaignBudgetAmount . " ← WRONG (from ARCHIVED campaign)");
        }
        
        $this->info("\n💡 Next Steps:");
        $this->info("  1. Run with --live flag to see current Amazon API data");
        $this->info("  2. Fix controller to exclude ARCHIVED campaigns");
        $this->info("  3. Wait for next API sync (daily at 6:00 AM IST) to update budget");
    }
}
