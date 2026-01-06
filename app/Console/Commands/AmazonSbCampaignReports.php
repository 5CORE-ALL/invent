<?php

namespace App\Console\Commands;

use App\Models\AmazonSbCampaignReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class AmazonSbCampaignReports extends Command
{
    protected $signature = 'app:amazon-sb-campaign-reports';
    protected $description = 'Fetch and store Sponsored Brands campaign reports';

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

            $profileId = env('AMAZON_ADS_PROFILE_IDS');
            $adType = 'SPONSORED_BRANDS';
            $reportTypeId = 'sbCampaigns';

            if (empty($profileId)) {
                $this->error("AMAZON_ADS_PROFILE_IDS is not set in environment.");
                return 1;
            }

            $today = now();

            // Fetch only yesterday's data for charts
            $yesterday = $today->copy()->subDay()->toDateString();
            
            // Check if yesterday's data already exists
            $yesterdayExists = AmazonSbCampaignReport::where('profile_id', $profileId)
                ->where('report_date_range', $yesterday)
                ->exists();
                
            if (!$yesterdayExists) {
                $this->fetchReport($profileId, $adType, $reportTypeId, $yesterday, $yesterday, $yesterday, true);
                $this->info("✅ Yesterday's data fetched: {$yesterday}");
            } else {
                $this->info("ℹ️  Yesterday's data already exists: {$yesterday}");
            }

            // Always fetch summary ranges for backward compatibility with table data
            $dateRanges = $this->getDateRanges();

            foreach ($dateRanges as $rangeLabel => [$startDate, $endDate]) {
                $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeLabel, false);
                // Close connection between reports to prevent buildup
                DB::disconnect();
            }

            $this->info("✅ All Sponsored Brands reports processed successfully.");
        } catch (\Exception $e) {
            $this->error("Error in handle: " . $e->getMessage());
            $this->info("Error trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            // Ensure connection is closed
            DB::disconnect();
        }

        return 0;
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
        }

        if (!$response->ok()) {
            $this->error("Failed to request SB report {$rangeKey}: " . $response->body());
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
        $timeoutSeconds = 7200; // 2 hours max

        while (now()->diffInSeconds($start) < $timeoutSeconds) {
            sleep(240); // 2 minutes

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
        try {
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

            // For daily chart data, use actual date as range key
            $finalRangeKey = $isDailyChart ? $startDate : $rangeKey;

            // Process in chunks to prevent memory and connection issues
            $chunks = array_chunk($rows, 100);
            $totalStored = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $row) {
                    if (empty($row['campaignId'])) {
                        continue; // Skip rows without campaign ID
                    }

                    try {
                        if ($isDailyChart) {
                            // For daily data, include date in unique key to prevent overwriting
                            AmazonSbCampaignReport::updateOrCreate(
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
                            AmazonSbCampaignReport::updateOrCreate(
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
                        $totalStored++;
                    } catch (\Exception $e) {
                        $this->info("Error storing row in {$reportName}: " . $e->getMessage());
                        continue;
                    }
                }

                // Close connection after each chunk to prevent buildup
                DB::disconnect();
            }

            $this->info("[SPONSORED_BRANDS - $finalRangeKey] Stored " . $totalStored . " rows to DB.");
            $this->info("[$reportName] Report saved successfully.");
        } catch (\Exception $e) {
            $this->error("[$reportName] Error in downloadAndParseReport: " . $e->getMessage());
            $this->info("Error trace: " . $e->getTraceAsString());
        } finally {
            DB::disconnect();
        }
    }

    private function getAccessToken()
    {
        try {
            $clientId = env('AMAZON_ADS_CLIENT_ID');
            $clientSecret = env('AMAZON_ADS_CLIENT_SECRET');
            $refreshToken = env('AMAZON_ADS_REFRESH_TOKEN');

            if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
                $this->error('Amazon Ads credentials are not set in environment.');
                return null;
            }

            $tokenResponse = Http::timeout(15)->asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$tokenResponse->successful()) {
                $this->error('Token fetch failed: ' . $tokenResponse->body());
                return null;
            }

            $accessToken = $tokenResponse['access_token'] ?? null;
            if (empty($accessToken)) {
                $this->error('Access token not returned in response: ' . $tokenResponse->body());
                return null;
            }

            $this->info('token generated');
            return $accessToken;
        } catch (\Exception $e) {
            $this->error('Error getting access token: ' . $e->getMessage());
            return null;
        }
    }

    private function getAllowedMetrics($timeUnit = 'SUMMARY'): array
    {
        $baseMetrics = [
            'addToCart', 'addToCartClicks', 'addToCartRate', 'addToList', 'addToListFromClicks', 'qualifiedBorrows', 'qualifiedBorrowsFromClicks', 
            'royaltyQualifiedBorrows', 'royaltyQualifiedBorrowsFromClicks', 'impressions', 'clicks', 'cost', 'sales', 'salesClicks', 'purchases', 'purchasesClicks',
            'brandedSearches', 'brandedSearchesClicks', 'newToBrandSales', 'newToBrandSalesClicks', 'newToBrandPurchases', 'newToBrandPurchasesClicks',
            'campaignBudgetType', 'videoCompleteViews', 'videoUnmutes', 'viewabilityRate', 'viewClickThroughRate',
            'detailPageViews', 'detailPageViewsClicks', 'eCPAddToCart', 'newToBrandDetailPageViewRate', 'newToBrandDetailPageViews', 
            'newToBrandDetailPageViewsClicks', 'newToBrandECPDetailPageView', 'newToBrandPurchasesPercentage', 'newToBrandUnitsSold', 'newToBrandUnitsSoldClicks', 
            'newToBrandUnitsSoldPercentage', 'unitsSold', 'unitsSoldClicks', 'topOfSearchImpressionShare', 'newToBrandPurchasesRate',
            'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode', 'newToBrandSalesPercentage',
            'campaignStatus', 'salesPromoted', 'video5SecondViewRate', 'video5SecondViews', 'videoFirstQuartileViews', 'videoMidpointViews', 
            'videoThirdQuartileViews', 'viewableImpressions',
        ];

        // Add date columns only for SUMMARY time unit, not for DAILY
        if ($timeUnit !== 'DAILY') {
            $baseMetrics[] = 'startDate';
            $baseMetrics[] = 'endDate';
        }

        return $baseMetrics;
    }
}
