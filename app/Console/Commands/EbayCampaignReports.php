<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\EbayCampaignReport;
use App\Models\EbayGeneralReport;
use App\Models\EbayPriorityReport;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EbayCampaignReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ebay-campaign-reports';

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
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to retrieve eBay access token.');
            return;
        }

        // Step 1: Fetch all campaigns once (campaigns don't change across date ranges)
        $this->info("Fetching all campaigns from eBay...");
        $campaignsMap = $this->getAllCampaigns($accessToken);
        if (empty($campaignsMap)) {
            $this->error("No campaigns fetched from eBay!");
            return;
        }
        $this->info("✅ Successfully fetched " . count($campaignsMap) . " campaigns. Will use for all date ranges.");
        
        // Step 2        
        $ranges = [
            'L90' => [Carbon::today()->subDays(90), Carbon::today()->subDays(31)->endOfDay()],
            'L60' => [Carbon::today()->subDays(60), Carbon::today()->subDays(31)->endOfDay()],
            'L30' => [Carbon::today()->subDays(30), Carbon::today()->subDay()->endOfDay()],
            'L15' => [Carbon::today()->subDays(15), Carbon::today()->subDay()->endOfDay()],
            'L7' => [Carbon::today()->subDays(7), Carbon::today()->subDay()->endOfDay()],
            'L1' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
        ];

        // Loop through date ranges first: L30 then L60
        foreach ($ranges as $rangeKey => [$from, $to]) {
            $this->info("Processing ALL_CAMPAIGN_PERFORMANCE_SUMMARY_REPORT: {$rangeKey} ({$from->toDateString()} → {$to->toDateString()})");

            $body = ["reportType" => "ALL_CAMPAIGN_PERFORMANCE_SUMMARY_REPORT",
                "dateFrom" => $from,
                "dateTo" => $to,
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
            if (!$taskId) continue;

            $reportId = $this->pollReportStatus($accessToken, $taskId);
            if (!$reportId) continue;
    
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
            foreach($campaignsMap as $campaignId => $campaignData){
                $campaignName = $campaignData['name'];
                $campaignStatus = $campaignData['status'];
                $campaignBudget = $campaignData['daily_budget'];

                if (!$campaignName) {
                    Log::warning("Missing campaignName for ID: {$campaignId}");
                    $skippedCount++;
                    continue;
                }
                
                // Get report data if available, otherwise use zero values
                $reportItem = $reportDataMap[$campaignId] ?? null;
            
                // Safely extract report data - if reportItem exists, use its values, otherwise use defaults
                $hasReportData = !empty($reportItem);
            
                EbayPriorityReport::updateOrCreate(
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
            
            $this->info("✅ ALL_CAMPAIGN_PERFORMANCE_SUMMARY_REPORT Data stored for range: {$rangeKey}");
            $this->info("   - Total campaigns stored: {$storedCount}");
            $this->info("   - Campaigns with performance data: " . count($reportDataMap));
            $this->info("   - Campaigns skipped (no name): {$skippedCount}");
        }
        
        foreach ($ranges as $rangeKey => [$from, $to]) {
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
            if (!$taskId) continue;

            $reportId = $this->pollReportStatus($accessToken, $taskId);
            if (!$reportId) continue;
    
            $items = $this->downloadParseAndStoreReport($accessToken, $reportId, $rangeKey);

            foreach($items as $item){
                if (!$item || empty($item['listing_id'])) continue;
                
                EbayGeneralReport::updateOrCreate(
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
            $this->info("CAMPAIGN_PERFORMANCE_REPORT Data stored for range: {$rangeKey}");
        }

        $this->info("✅ All campaign data processed.");
    }

    private function submitReportTask($token, $body)
    {
        $res = Http::withToken($token)->post('https://api.ebay.com/sell/marketing/v1/ad_report_task', $body);

        if ($res->failed()) {
            $this->error("Task creation failed: " . $res->body());
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
        do {
            sleep(60);
            $check = Http::withToken($token)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_report_task/{$taskId}");
            
            if ($check->status() === 401 || $check->json('errors.0.message') === 'Invalid access token') {
                logger()->error("Access token expired, refreshing...");

                $token = $this->getAccessToken();
                $check = Http::withToken($token)
                    ->get("https://api.ebay.com/sell/marketing/v1/ad_report_task/{$taskId}");
            }
            
            $status = $check['reportTaskStatus'] ?? 'IN_PROGRESS';

            logger()->info("Polling status for $taskId: $status");
            $this->info("Polling status for $taskId: $status");
        } while (!in_array($status, ['SUCCESS','FAILED']));

        if ($status !== 'SUCCESS') {
            logger()->error("Report task $taskId failed or timed out");
            $this->error("Report task $taskId failed or timed out");
            return [];
        }

        $reportId = $check['reportId'] ?? null;
        return $reportId;
    }

    private function downloadParseAndStoreReport($token, $reportId, $rangeKey)
    {
        $res = Http::withToken($token)->get("https://api.ebay.com/sell/marketing/v1/ad_report/{$reportId}");
        if (!$res->ok()) {
            $this->error("Failed to fetch report metadata.");
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
        $clientId = env('EBAY_APP_ID');
        $clientSecret = env('EBAY_CERT_ID');

        $scope = implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly'
        ]);

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('EBAY_REFRESH_TOKEN'),
                    'scope' => $scope,
                ]);

            if ($response->successful()) {
                Log::error('eBay token', ['response' => 'Token generated!']);
                return $response->json()['access_token'];
            }

            Log::error('eBay token refresh error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('eBay token refresh exception: ' . $e->getMessage());
        }

        return null;
    }

    private function getAllCampaigns($token)
    {
        $campaigns = [];
        $limit = 200; // eBay allows up to 200 per page
        $offset = 0;
        $maxRetries = 3;

        while (true) {
            $res = Http::withToken($token)
                ->timeout(120)
                ->retry(3, 5000)
                ->get('https://api.ebay.com/sell/marketing/v1/ad_campaign', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

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
                Log::error("eBay getAllCampaigns failed", [
                    'offset' => $offset,
                    'status' => $statusCode,
                    'response' => $errorBody
                ]);
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
        Log::info("eBay getAllCampaigns completed", ['total_campaigns' => $totalCampaigns]);

        return $campaigns;
    }

}
