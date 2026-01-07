<?php

namespace App\Console\Commands;

use App\Models\Ebay2GeneralReport;
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
                $this->error('Failed to retrieve eBay2 access token.');
                return 1;
            }

        // Fetch only yesterday's data for charts
        $yesterday = Carbon::yesterday()->toDateString();
        
        // Check if yesterday's data already exists
        $yesterdayExists = Ebay2GeneralReport::where('report_range', $yesterday)
            ->exists();
            
        if (!$yesterdayExists) {
            $this->info("📊 Yesterday's data not found. Fetching for charts: {$yesterday}");
            $this->fetchAndStoreListingReport($accessToken, Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay(), $yesterday);
            $this->info("✅ Yesterday's data fetched: {$yesterday}");
        } else {
            $this->info("ℹ️  Yesterday's data already exists: {$yesterday}");
        }

        // Always fetch summary ranges for backward compatibility with table data
        $ranges = [
            'L60' => [Carbon::today()->subDays(60), Carbon::today()->subDays(31)->endOfDay()],
            'L30' => [Carbon::today()->subDays(30), Carbon::today()->subDays(1)->endOfDay()],
            'L15' => [Carbon::today()->subDays(15), Carbon::today()->subDays(1)->endOfDay()],
            'L7' => [Carbon::today()->subDays(7), Carbon::today()->subDays(1)->endOfDay()],
            'L1' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
        ];
        
        foreach ($ranges as $rangeKey => [$from, $to]) {
            $this->fetchAndStoreListingReport($accessToken, $from, $to, $rangeKey);
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
        $tsv = fopen($tsvPath, 'wb');
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
        
        while (($line = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($line) !== count($headers)) continue;
            $item = array_combine($headers, $line);
            if ($item) {
                $allData[] = $item;
            }
        }
        
        fclose($handle);

        @unlink($gzPath); 
        @unlink($tsvPath);
        
        return $allData;
    }

    private function getAccessToken()
    {
        $clientId = env('EBAY2_APP_ID');
        $clientSecret = env('EBAY2_CERT_ID');

        try {
            // Note: Don't send scope parameter when refreshing token
            // Scope is only used during initial authorization
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('EBAY2_REFRESH_TOKEN'),
                ]);

            if ($response->successful()) {
                $this->info('✅ eBay2 token generated successfully');
                return $response->json()['access_token'];
            }

            $this->error('❌ eBay2 token refresh error: ' . json_encode($response->json()));
        } catch (\Exception $e) {
            $this->error('❌ eBay2 token refresh exception: ' . $e->getMessage());
        }

        return null;
    }
}
