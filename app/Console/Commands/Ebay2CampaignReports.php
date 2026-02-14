<?php

namespace App\Console\Commands;

use App\Models\Ebay2GeneralReport;
use App\Models\Ebay2PriorityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Ebay2CampaignReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ebay2-campaign-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
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

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                $this->error('Failed to retrieve eBay access token.');
                return 1;
            }

        // Step 1: Fetch all campaigns once (campaigns don't change across date ranges)
        $this->info("Fetching all campaigns from eBay...");
        $campaignsMap = $this->getAllCampaigns($accessToken);
        if (empty($campaignsMap)) {
            $this->error("No campaigns fetched from eBay!");
            return 1;
        }
        $this->info("✅ Successfully fetched " . count($campaignsMap) . " campaigns. Will use for all date ranges.");
        
        // Fetch only yesterday's data for charts
        $yesterday = Carbon::yesterday()->toDateString();
        
        // Check if yesterday's data already exists
        $yesterdayExists = Ebay2PriorityReport::where('report_range', $yesterday)
            ->exists();
            
        if (!$yesterdayExists) {
            $this->info("📊 Yesterday's data not found. Fetching for charts: {$yesterday}");
            $this->fetchAndStoreCampaignReport($accessToken, $campaignsMap, Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay(), $yesterday);
            $this->info("✅ Yesterday's data fetched: {$yesterday}");
        } else {
            $this->info("ℹ️  Yesterday's data already exists: {$yesterday}");
        }

        // Always fetch summary ranges for backward compatibility with table data
        $summaryRanges = [
            'L90' => [Carbon::today()->subDays(90), Carbon::today()->subDays(31)->endOfDay()],
            'L60' => [Carbon::today()->subDays(60), Carbon::today()->subDays(31)->endOfDay()],
            'L30' => [Carbon::today()->subDays(29), Carbon::today()->subDay()->endOfDay()],
            'L15' => [Carbon::today()->subDays(14), Carbon::today()->subDay()->endOfDay()],
            'L7' => [Carbon::today()->subDays(6), Carbon::today()->subDay()->endOfDay()],
            'L1' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()]
        ];

        // Loop through summary ranges
        foreach ($summaryRanges as $rangeKey => [$from, $to]) {
            $this->fetchAndStoreCampaignReport($accessToken, $campaignsMap, $from, $to, $rangeKey);
        }

            $this->info("✅ All campaign data processed.");
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function fetchAndStoreCampaignReport($accessToken, $campaignsMap, $from, $to, $rangeKey)
    {
        $this->info("Processing ALL_CAMPAIGN_PERFORMANCE_SUMMARY_REPORT: {$rangeKey} ({$from->toDateString()} → {$to->toDateString()})");

        // Use UTC timezone for eBay API compatibility
        $dateFrom = $from->copy()->startOfDay()->utc()->format('Y-m-d\TH:i:s.000\Z');
        $dateTo = $to->copy()->endOfDay()->utc()->format('Y-m-d\TH:i:s.000\Z');

        $body = ["reportType" => "ALL_CAMPAIGN_PERFORMANCE_SUMMARY_REPORT",
            "dateFrom" => $dateFrom,
            "dateTo" => $dateTo,
            "marketplaceId" => "EBAY_US",
            "reportFormat" => "TSV_GZIP",
            "fundingModels" => ["COST_PER_CLICK"],
            "dimensions" => [
                ["dimensionKey" => "campaign_id"],
            ],
            "metricKeys" => [
                "cpc_impressions",
                "cpc_clicks",
                "cpc_attributed_sales",
                "cpc_ctr",
                "cpc_ad_fees_listingsite_currency",
                "cpc_sale_amount_listingsite_currency",
                "cpc_avg_cost_per_sale",
                "cpc_return_on_ad_spend",
                "cpc_conversion_rate",
                "cpc_sale_amount_payout_currency",
                "cost_per_click",
                "cpc_ad_fees_payout_currency",
            ]
        ];

        $taskId = $this->submitReportTask($accessToken, $body);
        if (!$taskId) return;

        $reportId = $this->pollReportStatus($accessToken, $taskId);
        if (!$reportId) return;

        $items = $this->downloadParseAndStoreReport($accessToken, $reportId, $rangeKey);
        
        if (!is_array($items)) {
            $this->warn("Report data is not an array for range {$rangeKey}. Skipping.");
            $items = [];
        }
        
        // Create a map of report items by campaign_id for quick lookup
        $reportDataMap = [];
        foreach($items as $item){
            if (!$item || empty($item['campaign_id'])) continue;
            $reportDataMap[$item['campaign_id']] = $item;
        }
        
        $this->info("Report contains " . count($reportDataMap) . " campaigns with performance data");
        $this->info("Storing all " . count($campaignsMap) . " campaigns for range: {$rangeKey}");
        
        $storedCount = 0;
        $skippedCount = 0;
        
        // Store ALL campaigns from campaignsMap, not just those in report
        // Process in chunks to avoid too many connections
        $campaignsArray = array_chunk($campaignsMap, 100, true);
        
        foreach($campaignsArray as $chunkIndex => $campaignsChunk) {
            foreach($campaignsChunk as $campaignId => $campaignData){
            $campaignName = $campaignData['name'];
            $campaignStatus = $campaignData['status'];
            $campaignBudget = $campaignData['daily_budget'];

            if (!$campaignName) {
                $this->warn("Missing campaignName for ID: {$campaignId}");
                $skippedCount++;
                continue;
            }
            
            // Get report data if available, otherwise use zero values
            $reportItem = $reportDataMap[$campaignId] ?? null;
        
            // Safely extract report data - if reportItem exists, use its values, otherwise use defaults
            $hasReportData = !empty($reportItem);
        
            Ebay2PriorityReport::updateOrCreate(
                ['campaign_id' => $campaignId, 'report_range' => $rangeKey],
                [
                    'campaign_name' => $campaignName,
                    'campaignBudgetAmount' => $campaignBudget,
                    'campaignStatus' => $campaignStatus,
                    'cpc_impressions' => $hasReportData ? ($reportItem['cpc_impressions'] ?? 0) : 0,
                    'cpc_clicks' => $hasReportData ? ($reportItem['cpc_clicks'] ?? 0) : 0,
                    'cpc_attributed_sales' => $hasReportData ? ($reportItem['cpc_attributed_sales'] ?? 0) : 0,
                    'cpc_ctr' => $hasReportData && is_numeric($reportItem['cpc_ctr'] ?? null) ? (float)$reportItem['cpc_ctr'] : 0,
                    'cpc_ad_fees_listingsite_currency' => $hasReportData ? ($reportItem['cpc_ad_fees_listingsite_currency'] ?? null) : null,
                    'cpc_sale_amount_listingsite_currency' => $hasReportData ? ($reportItem['cpc_sale_amount_listingsite_currency'] ?? null) : null,
                    'cpc_avg_cost_per_sale' => $hasReportData ? ($reportItem['cpc_avg_cost_per_sale'] ?? null) : null,
                    'cpc_return_on_ad_spend' => $hasReportData && is_numeric($reportItem['cpc_return_on_ad_spend'] ?? null) 
                        ? (float)$reportItem['cpc_return_on_ad_spend'] 
                        : 0,
                    'cpc_conversion_rate' => $hasReportData && is_numeric($reportItem['cpc_conversion_rate'] ?? null) 
                        ? (float)$reportItem['cpc_conversion_rate'] 
                        : 0,
                    'cpc_sale_amount_payout_currency' => $hasReportData ? ($reportItem['cpc_sale_amount_payout_currency'] ?? null) : null,
                    'cost_per_click' => $hasReportData ? ($reportItem['cost_per_click'] ?? null) : null,
                    'cpc_ad_fees_payout_currency' => $hasReportData ? ($reportItem['cpc_ad_fees_payout_currency'] ?? null) : null,
                    'channels' => $hasReportData ? ($reportItem['channels'] ?? null) : null,
                ]
            );
                $storedCount++;
            }
            
            // Disconnect after each chunk
            DB::connection()->disconnect();
        }
        
        $this->info("✅ ALL_CAMPAIGN_PERFORMANCE_SUMMARY_REPORT Data stored for range: {$rangeKey}");
        $this->info("   - Total campaigns stored: {$storedCount}");
        $this->info("   - Campaigns with performance data: " . count($reportDataMap));
        $this->info("   - Campaigns skipped (no name): {$skippedCount}");
        
        // Also fetch CAMPAIGN_PERFORMANCE_REPORT for listing-level data
        $this->fetchAndStoreListingReport($accessToken, $from, $to, $rangeKey);
    }

    private function fetchAndStoreListingReport($accessToken, $from, $to, $rangeKey)
    {
        $this->info("Processing CAMPAIGN_PERFORMANCE_REPORT: {$rangeKey} ({$from->toDateString()} → {$to->toDateString()})");

        $body = ["reportType" => "CAMPAIGN_PERFORMANCE_REPORT",
            "dateFrom" => $from,
            "dateTo" => $to,
            "marketplaceId" => "EBAY_US",
            "reportFormat" => "TSV_GZIP",
            "fundingModels" => ["COST_PER_SALE"],
            "dimensions" => [
                ["dimensionKey" => "campaign_id"],
                ["dimensionKey" => "listing_id"],
            ],
            "metricKeys" => [
                "impressions",
                "clicks",
                "ad_fees",
                "sales", 
                "sale_amount",
                "avg_cost_per_sale",
                "ctr",
            ]
        ];

        $taskId = $this->submitReportTask($accessToken, $body);
        if (!$taskId) return;

        $reportId = $this->pollReportStatus($accessToken, $taskId);
        if (!$reportId) return;

        $items = $this->downloadParseAndStoreReport($accessToken, $reportId, $rangeKey);

        if (empty($items)) {
            $this->warn("No items found in report for range: {$rangeKey}");
            return;
        }

        // Process in chunks to avoid too many connections
        $chunks = array_chunk($items, 100);
        
        foreach($chunks as $chunkIndex => $chunk) {
            foreach($chunk as $item){
                if (!$item || empty($item['listing_id'])) continue;
                
                Ebay2GeneralReport::updateOrCreate(
                ['listing_id' => $item['listing_id'], 'report_range' => $rangeKey],
                [
                    'campaign_id' => $item['campaign_id'] ?? null,
                    'impressions' => $item['impressions'] ?? 0,
                    'clicks' => $item['clicks'] ?? 0,
                    'sales' => $item['sales'] ?? 0,
                    'ad_fees' => $item['ad_fees'] ?? null,
                    'sale_amount' => $item['sale_amount'] ?? null,
                    'avg_cost_per_sale' => $item['avg_cost_per_sale'] ?? null,
                    'ctr' => is_numeric($item['ctr'] ?? null) ? (float)$item['ctr'] : 0,
                    'channels' => $item['channels'] ?? null,
                ]
                );
            }
            
            // Disconnect after each chunk
            DB::connection()->disconnect();
        }
        $this->info("✅ CAMPAIGN_PERFORMANCE_REPORT Data stored for range: {$rangeKey}");
    }

    private function submitReportTask($token, $body)
    {
        $maxRetries = 3;
        $retryAttempt = 0;
        $res = null;
        
        while ($retryAttempt < $maxRetries) {
            $retryAttempt++;
            
            try {
                $res = Http::withToken($token)
                    ->timeout(120) // 2 minutes timeout
                    ->connectTimeout(30) // 30 seconds connection timeout
                    ->retry(2, 5000) // Retry 2 times with 5 second delay
                    ->post('https://api.ebay.com/sell/marketing/v1/ad_report_task', $body);
                
                if ($res->successful()) {
                    break;
                }
                
                if ($retryAttempt < $maxRetries) {
                    $this->warn("Task creation failed (attempt {$retryAttempt}/{$maxRetries}), retrying in 5 seconds...");
                    sleep(5);
                    continue;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if ($retryAttempt < $maxRetries) {
                    $this->warn("Connection timeout (attempt {$retryAttempt}/{$maxRetries}), retrying in 10 seconds...");
                    sleep(10);
                    continue;
                }
                $this->error("Connection timeout after {$maxRetries} attempts: " . $e->getMessage());
                return null;
            } catch (\Exception $e) {
                if ($retryAttempt < $maxRetries) {
                    $this->warn("Request failed (attempt {$retryAttempt}/{$maxRetries}): " . $e->getMessage() . ", retrying...");
                    sleep(5);
                    continue;
                }
                $this->error("Request failed after {$maxRetries} attempts: " . $e->getMessage());
                return null;
            }
        }

        if (!$res || $res->failed()) {
            $this->error("Task creation failed: " . ($res ? $res->body() : 'No response'));
            return null;
        }
    
        // Extract task ID from Location header
        $location = $res->header('Location');
    
        if (!$location || !str_contains($location, '/ad_report_task/')) {
            $this->error("No Location header with task ID returned.");
            return null;
        }
    
        $taskId = basename($location);
    
        $this->info("✅ Report task submitted. Task ID: $taskId");
    
        return $taskId;
    }

    private function pollReportStatus($token, $taskId)
    {
        $maxAttempts = 60; // Maximum polling attempts (60 minutes)
        $attempt = 0;
        
        do {
            sleep(60);
            $attempt++;
            
            $maxRetries = 3;
            $retryAttempt = 0;
            $check = null;
            
            while ($retryAttempt < $maxRetries) {
                $retryAttempt++;
                
                try {
                    $check = Http::withToken($token)
                        ->timeout(120) // 2 minutes timeout
                        ->connectTimeout(30) // 30 seconds connection timeout
                        ->retry(2, 5000) // Retry 2 times with 5 second delay
                        ->get("https://api.ebay.com/sell/marketing/v1/ad_report_task/{$taskId}");
                    
                    if ($check->successful() || $check->status() === 401) {
                        break;
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    if ($retryAttempt < $maxRetries) {
                        $this->warn("Connection timeout (attempt {$retryAttempt}/{$maxRetries}), retrying in 10 seconds...");
                        sleep(10);
                        continue;
                    }
                    $this->error("Connection timeout after {$maxRetries} attempts: " . $e->getMessage());
                    return null;
                } catch (\Exception $e) {
                    if ($retryAttempt < $maxRetries) {
                        $this->warn("Request failed (attempt {$retryAttempt}/{$maxRetries}): " . $e->getMessage() . ", retrying...");
                        sleep(5);
                        continue;
                    }
                    $this->error("Request failed after {$maxRetries} attempts: " . $e->getMessage());
                    return null;
                }
            }
            
            if (!$check) {
                $this->error("Failed to check report status after retries");
                return null;
            }
            
            if ($check->status() === 401 || $check->json('errors.0.message') === 'Invalid access token') {
                $this->warn("Access token expired, refreshing...");

                $token = $this->getAccessToken();
                if (!$token) {
                    $this->error("Failed to refresh access token");
                    return null;
                }
                
                // Retry with new token
                try {
                    $check = Http::withToken($token)
                        ->timeout(120)
                        ->connectTimeout(30)
                        ->retry(2, 5000)
                        ->get("https://api.ebay.com/sell/marketing/v1/ad_report_task/{$taskId}");
                } catch (\Exception $e) {
                    $this->error("Failed to check status with new token: " . $e->getMessage());
                    return null;
                }
            }
            
            $status = $check['reportTaskStatus'] ?? 'IN_PROGRESS';

            $this->info("Polling status for $taskId: $status (Attempt: {$attempt}/{$maxAttempts})");
            
            if ($attempt >= $maxAttempts) {
                $this->error("Report task $taskId polling timeout after {$maxAttempts} attempts");
                return null;
            }
        } while (!in_array($status, ['SUCCESS','FAILED']));

        if ($status !== 'SUCCESS') {
            $this->error("Report task $taskId failed or timed out");
            return null;
        }

        $reportId = $check['reportId'] ?? null;
        return $reportId;
    }

    private function downloadParseAndStoreReport($token, $reportId, $rangeKey)
    {
        $maxRetries = 3;
        $retryAttempt = 0;
        $res = null;
        
        while ($retryAttempt < $maxRetries) {
            $retryAttempt++;
            
            try {
                $res = Http::withToken($token)
                    ->timeout(300) // 5 minutes timeout for large file downloads
                    ->connectTimeout(60) // 60 seconds connection timeout
                    ->retry(2, 10000) // Retry 2 times with 10 second delay
                    ->get("https://api.ebay.com/sell/marketing/v1/ad_report/{$reportId}");
                
                if ($res->ok()) {
                    break;
                }
                
                if ($retryAttempt < $maxRetries) {
                    $this->warn("Failed to fetch report (attempt {$retryAttempt}/{$maxRetries}), retrying in 10 seconds...");
                    sleep(10);
                    continue;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if ($retryAttempt < $maxRetries) {
                    $this->warn("Connection timeout (attempt {$retryAttempt}/{$maxRetries}), retrying in 15 seconds...");
                    sleep(15);
                    continue;
                }
                $this->error("Connection timeout after {$maxRetries} attempts: " . $e->getMessage());
                return [];
            } catch (\Exception $e) {
                if ($retryAttempt < $maxRetries) {
                    $this->warn("Request failed (attempt {$retryAttempt}/{$maxRetries}): " . $e->getMessage() . ", retrying...");
                    sleep(10);
                    continue;
                }
                $this->error("Request failed after {$maxRetries} attempts: " . $e->getMessage());
                return [];
            }
        }
        
        if (!$res || !$res->ok()) {
            $this->error("Failed to fetch report metadata after retries.");
            return [];
        }        

        $gzPath = storage_path("app/{$rangeKey}_{$reportId}.tsv.gz");
        file_put_contents($gzPath, $res->body());

        // Extract TSV
        $tsvPath = str_replace('.gz', '', $gzPath);
        $gz = gzopen($gzPath, 'rb');
        if (!$gz) {
            $this->error("Unable to open gzip file.");
            return [];
        }
        
        $tsv = fopen($tsvPath, 'wb');
        if (!$tsv) {
            $this->error("Unable to create TSV file.");
            gzclose($gz);
            return [];
        }
        
        while (!gzeof($gz)) fwrite($tsv, gzread($gz, 4096));
        fclose($tsv); 
        gzclose($gz);

        $handle = fopen($tsvPath, 'rb');
        if (!$handle) {
            $this->error("Unable to open extracted TSV file.");
            @unlink($gzPath);
            @unlink($tsvPath);
            return [];
        }

        $headers = fgetcsv($handle, 0, "\t");
        if (!$headers || empty($headers)) {
            $this->error("Header row missing or unreadable.");
            fclose($handle);
            @unlink($gzPath);
            @unlink($tsvPath);
            return [];
        }
        
        $allData = [];
        $rowCount = 0;
        
        while (($line = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($line) !== count($headers)) continue;
            $item = array_combine($headers, $line);
            if ($item) {
                $allData[] = $item;
                $rowCount++;
            }
        }
        
        fclose($handle);
        
        $this->info("Parsed {$rowCount} rows from report {$reportId} for range {$rangeKey}");

        @unlink($gzPath); 
        @unlink($tsvPath);

        return $allData;
    }

    private function getAccessToken()
    {
        $clientId = config('services.ebay2.app_id');
        $clientSecret = config('services.ebay2.cert_id');

        // For refresh token, scope is optional - the refresh token already contains the granted scopes
        // Only specify scope if you want to request additional scopes
        $scope = 'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.marketing';

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => config('services.ebay2.refresh_token'),
                    'scope' => $scope,
                ]);

            if ($response->successful()) {
                $this->info('eBay token generated successfully');
                return $response->json()['access_token'];
            }

            $this->error('eBay token refresh error: ' . json_encode($response->json()));
        } catch (\Exception $e) {
            $this->error('eBay token refresh exception: ' . $e->getMessage());
        }

        return null;
    }

    private function getAllCampaigns($token)
    {
        $campaigns = [];
        $limit = 200; // eBay allows up to 200 per page
        $offset = 0;

        while (true) {
            $maxRetries = 3;
            $retryAttempt = 0;
            $res = null;
            
            while ($retryAttempt < $maxRetries) {
                $retryAttempt++;
                
                try {
                    $res = Http::withToken($token)
                        ->timeout(120)
                        ->connectTimeout(30)
                        ->retry(2, 5000)
                        ->get('https://api.ebay.com/sell/marketing/v1/ad_campaign', [
                            'limit' => $limit,
                            'offset' => $offset,
                        ]);
                    
                    if ($res->ok() || $res->status() === 401) {
                        break;
                    }
                    
                    if ($retryAttempt < $maxRetries) {
                        $this->warn("Failed to fetch campaigns (attempt {$retryAttempt}/{$maxRetries}), retrying in 5 seconds...");
                        sleep(5);
                        continue;
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    if ($retryAttempt < $maxRetries) {
                        $this->warn("Connection timeout (attempt {$retryAttempt}/{$maxRetries}), retrying in 10 seconds...");
                        sleep(10);
                        continue;
                    }
                    $this->error("Connection timeout after {$maxRetries} attempts: " . $e->getMessage());
                    break 2; // Break out of both loops
                } catch (\Exception $e) {
                    if ($retryAttempt < $maxRetries) {
                        $this->warn("Request failed (attempt {$retryAttempt}/{$maxRetries}): " . $e->getMessage() . ", retrying...");
                        sleep(5);
                        continue;
                    }
                    $this->error("Request failed after {$maxRetries} attempts: " . $e->getMessage());
                    break 2; // Break out of both loops
                }
            }
            
            if (!$res) {
                $this->error("Failed to fetch campaigns after retries at offset {$offset}");
                break;
            }

            if (!$res->ok()) {
                $statusCode = $res->status();
                $errorBody = $res->body();
                
                // If token expired, try to refresh
                if ($statusCode === 401) {
                    $this->warn("Access token expired while fetching campaigns, refreshing...");
                    $token = $this->getAccessToken();
                    if (!$token) {
                        $this->error("Failed to refresh access token.");
                        break;
                    }
                    // Retry the same request with new token
                    continue;
                }
                
                $this->error("Failed to fetch campaigns at offset {$offset}. Status: {$statusCode}, Response: {$errorBody}");
                break;
            }

            $data = $res->json();
            $pageCampaigns = $data['campaigns'] ?? [];

            if (empty($pageCampaigns)) {
                $this->info("No campaigns found at offset {$offset}. Stopping pagination.");
                break;
            }

            foreach ($pageCampaigns as $c) {
                // Safely access budget structure
                $budgetValue = null;
                $budgetCurrency = null;
                
                if (isset($c['budget']['daily']['amount']['value'])) {
                    $budgetValue = $c['budget']['daily']['amount']['value'];
                }
                if (isset($c['budget']['daily']['amount']['currency'])) {
                    $budgetCurrency = $c['budget']['daily']['amount']['currency'];
                }
                
                $campaigns[$c['campaignId']] = [
                    'name' => $c['campaignName'] ?? null,
                    'status' => $c['campaignStatus'] ?? null,
                    'daily_budget' => $budgetValue,
                    'currency' => $budgetCurrency,
                ];
            }

            $count = count($pageCampaigns);
            $this->info("Fetched {$count} campaigns at offset {$offset}. Total so far: " . count($campaigns));
            
            if ($count < $limit) {
                $this->info("Last page reached. Total campaigns fetched: " . count($campaigns));
                break; // last page reached
            }

            $offset += $limit;
        }

        $totalCampaigns = count($campaigns);
        $this->info("✅ Total campaigns fetched: {$totalCampaigns}");

        return $campaigns;
    }

}
