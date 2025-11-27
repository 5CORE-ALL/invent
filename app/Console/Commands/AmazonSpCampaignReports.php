<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AmazonSpCampaignReport;

class AmazonSpCampaignReports extends Command
{
    protected $signature = 'app:amazon-sp-campaign-reports';
    protected $description = 'Fetch and store Sponsored Products campaign reports';

    public function handle()
    {
        $profileId = env('AMAZON_ADS_PROFILE_IDS');
        $adType = 'SPONSORED_PRODUCTS';
        $reportTypeId = 'spCampaigns';

        $today = now();

        // Check if we need to do initial backfill (check if we have any daily data)
        $existingDailyData = AmazonSpCampaignReport::where('profile_id', $profileId)
            ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
            ->exists();

        if (!$existingDailyData) {
            $this->info("🔄 No daily data found. Performing initial backfill for last 30 days...");
            // Initial backfill - fetch last 30 days
            for ($i = 1; $i <= 30; $i++) {
                $date = $today->copy()->subDays($i)->toDateString();
                $this->fetchReport($profileId, $adType, $reportTypeId, $date, $date, $date, true);
                // Small delay to prevent rate limiting
                sleep(2);
            }
            $this->info("✅ Initial backfill completed.");
        } else {
            $this->info("📈 Daily data exists. Fetching incremental data...");
            // Incremental fetch - only yesterday's data
            $yesterday = $today->copy()->subDay()->toDateString();
            
            // Check if yesterday's data already exists
            $yesterdayExists = AmazonSpCampaignReport::where('profile_id', $profileId)
                ->where('report_date_range', $yesterday)
                ->exists();
                
            if (!$yesterdayExists) {
                $this->fetchReport($profileId, $adType, $reportTypeId, $yesterday, $yesterday, $yesterday, true);
                $this->info("✅ Yesterday's data fetched: {$yesterday}");
            } else {
                $this->info("ℹ️  Yesterday's data already exists: {$yesterday}");
            }
        }

        // Always fetch summary ranges for backward compatibility with table data
        $dateRanges = $this->getDateRanges();

        foreach ($dateRanges as $rangeLabel => [$startDate, $endDate]) {
            $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeLabel, false);
        }

        $this->info("✅ All Sponsored Products reports processed successfully.");
    }

    private function getDateRanges()
    {
        $today = now();

        $endL30 = $today->copy()->subDay();
        $startL30 = $endL30->copy()->subDays(29);

        return [
            'L30' => [$startL30->toDateString(), $endL30->toDateString()],
            'L15' => [$today->copy()->subDays(15)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L7'  => [$today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L1'  => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
        ];
    }

    private function fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeKey, $isDailyChart = false)
    {
        $accessToken = $this->getAccessToken();
        $reportName = "{$adType}_{$rangeKey}_Campaign";

        $timeUnit = ($startDate === $endDate) ? 'DAILY' : 'SUMMARY';
        
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->withHeaders([
                'Amazon-Advertising-API-Scope' => $profileId,
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                'Content-Type' => 'application/vnd.createasyncreportrequest.v3+json',
            ])
            ->post('https://advertising-api.amazon.com/reporting/reports', [
                'name' => $reportName,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'configuration' => [
                    'adProduct' => $adType,
                    'groupBy' => ['campaign'],
                    'reportTypeId' => $reportTypeId,
                    'columns' => $this->getAllowedMetrics($timeUnit),
                    'format' => 'GZIP_JSON',
                    'timeUnit' => $timeUnit,
                ]
            ]);

        // 💥 Handle 425 Duplicate
        if ($response->status() == 425) {
            $detail = $response->json('detail');
            $existingReportId = str_replace(
                'The Request is a duplicate of : ',
                '',
                $detail
            );

            $this->warn("[$reportName] Duplicate request. Using existing reportId: $existingReportId");

            $this->waitForReportReady($reportName, $profileId, trim($existingReportId), $adType, $startDate, $rangeKey, $isDailyChart);
            return;
        }

        // 🚦 Handle 429 Rate Limiting
        if ($response->status() == 429) {
            $this->warn("[$reportName] Rate limited. Waiting 60 seconds before retry...");
            sleep(60);
            // Retry once after rate limit
            $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeKey, $isDailyChart);
            return;
        }            if (!$response->ok()) {
                Log::error("Failed to request SP report {$rangeKey}: " . $response->body());
                return;
            }


        $reportId = $response->json('reportId');
        if (!$reportId) {
            $this->error("[$reportName] Report ID not returned.");
            return;
        }

        $this->waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey, $isDailyChart);
    }

    protected function waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey, $isDailyChart = false)
    {
        $start = now();
        $timeoutSeconds = 3600; // 1 hour max

        while (now()->diffInSeconds($start) < $timeoutSeconds) {
            sleep(300); // 5 minutes

            $token = $this->getAccessToken(); // 🔁 refresh token each poll

            $statusResponse = Http::timeout(30)
                ->retry(3, 2000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Content-Type' => 'application/vnd.getasyncreportresponse.v3+json',
                ])
                ->get("https://advertising-api.amazon.com/reporting/reports/{$reportId}");

            if ($statusResponse->status() === 401) {
                $this->warn("[Report: {$reportId}] 401 Unauthorized — refreshing token...");
                continue;
            }

            if (!$statusResponse->successful()) {
                $this->warn("[Report: {$reportId}] Polling failed: " . $statusResponse->body());
                continue;
            }

            $status = $statusResponse['status'] ?? 'UNKNOWN';
            $this->info(now()->toDateTimeString() . " [Report: {$reportId}] Status: $status");

            if ($status === 'COMPLETED') {
                $location = $statusResponse['location'] ?? $statusResponse['url'] ?? null;
                if (!$location) {
                    $this->error("[$reportName] Missing report location.");
                    return;
                }

                $this->downloadAndParseReport($location, $reportName, $profileId, $adType, $startDate, $rangeKey, $isDailyChart);
                return;
            }

            if ($status === 'FAILED') {
                $this->error("[Report: {$reportId}] Report generation failed.");
                return;
            }
        }

        $this->error("[Report: {$reportId}] Report not ready after {$timeoutSeconds} seconds.");
    }

    private function downloadAndParseReport($downloadUrl, $reportName, $profileId, $adType, $startDate, $rangeKey, $isDailyChart = false)
    {
        $this->info("[$reportName] Downloading and parsing report...");

        $response = Http::timeout(30)->retry(3, 2000)->withoutVerifying()->get($downloadUrl);

        if (!$response->ok()) {
            $this->error("[$reportName] Failed to download report file.");
            return;
        }

        $jsonString = gzdecode($response->body());
        if (!$jsonString) {
            $this->error("[$reportName] Failed to decode gzip content.");
            return;
        }

        $rows = json_decode($jsonString, true);
        if (!is_array($rows) || empty($rows)) {
            $this->warn("[$reportName] No records found.");
            return;
        }

        $this->info("[$reportName] Total rows: " . count($rows));

        foreach ($rows as $row) {
            // For daily chart data, use actual date as range key
            $finalRangeKey = $isDailyChart ? $startDate : $rangeKey;
            
            if ($isDailyChart) {
                // For daily data, include date in unique key to prevent overwriting
                AmazonSpCampaignReport::updateOrCreate(
                    [
                        'campaign_id' => $row['campaignId'] ?? null,
                        'profile_id' => $profileId,
                        'report_date_range' => $finalRangeKey, // This is the actual date
                    ],
                    array_merge($row, [
                        'profile_id' => $profileId,
                        'report_date_range' => $finalRangeKey,
                        'ad_type' => $adType,
                        'report_date' => $startDate, // Store actual report date
                    ])
                );
            } else {
                // For summary data, use range key as before
                AmazonSpCampaignReport::updateOrCreate(
                    [
                        'campaign_id' => $row['campaignId'] ?? null,
                        'profile_id' => $profileId,
                        'report_date_range' => $finalRangeKey,
                    ],
                    array_merge($row, [
                        'profile_id' => $profileId,
                        'report_date_range' => $finalRangeKey,
                        'ad_type' => $adType,
                    ])
                );
            }
        }

        $this->info("[SPONSORED_PRODUCTS - $finalRangeKey] Stored " . count($rows) . " rows to DB.");
        $this->info("[$reportName] Report saved successfully.");
    }

    private function getAccessToken()
    {
        $clientId = env('AMAZON_ADS_CLIENT_ID');
        $clientSecret = env('AMAZON_ADS_CLIENT_SECRET');
        $refreshToken = env('AMAZON_ADS_REFRESH_TOKEN');

        $tokenResponse = Http::timeout(15)->asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Token fetch failed: ' . $tokenResponse->body());
            $this->error('Token fetch failed.');
            return null;
        }

        $this->info('token generated');
        return $tokenResponse['access_token'];
    }

    private function getAllowedMetrics($timeUnit = 'SUMMARY'): array
    {
        $baseMetrics = [
            'impressions', 'clicks', 'cost', 'spend', 'purchases1d', 'purchases7d',
            'purchases14d', 'purchases30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d',
            'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
            'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d',
            'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d',
            'clickThroughRate', 'costPerClick', 'qualifiedBorrows', 'addToList',
            'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode',
            'royaltyQualifiedBorrows', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 
            'purchasesSameSku30d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d', 
            'campaignBiddingStrategy', 'campaignStatus',
        ];

        // Add date columns only for SUMMARY time unit, not for DAILY
        if ($timeUnit !== 'DAILY') {
            $baseMetrics[] = 'startDate';
            $baseMetrics[] = 'endDate';
        }

        return $baseMetrics;
    }
}
