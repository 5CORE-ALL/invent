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

        $dateRanges = $this->getDateRanges();

        foreach ($dateRanges as $rangeLabel => [$startDate, $endDate]) {
            $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeLabel);
        }

        $this->info("✅ All Sponsored Products reports fetched successfully.");
    }

    private function getDateRanges()
    {
        $today = now();

        $endL30 = $today->copy()->subDay();
        $startL30 = $endL30->copy()->subDays(29);

        $endL60 = $startL30->copy()->subDay();
        $startL60 = $endL60->copy()->subDays(29);

        $endL90 = $startL60->copy()->subDay();
        $startL90 = $endL90->copy()->subDays(29);

        return [
            'L90' => [$startL90->toDateString(), $endL90->toDateString()],
            'L60' => [$startL60->toDateString(), $endL60->toDateString()],
            'L30' => [$startL30->toDateString(), $endL30->toDateString()],
            'L15' => [$today->copy()->subDays(15)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L7'  => [$today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L1'  => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
        ];
    }

    private function fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeKey)
    {
        $accessToken = $this->getAccessToken();
        $reportName = "{$adType}_{$rangeKey}_Campaign";

        $response = Http::timeout(30)
            ->retry(5, 2000)
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
                    'columns' => $this->getAllowedMetrics(),
                    'format' => 'GZIP_JSON',
                    'timeUnit' => 'SUMMARY',
                ]
            ]);

        if (!$response->ok()) {
            Log::error("Failed to request SP report {$rangeKey}: " . $response->body());
            return;
        }

        $reportId = $response->json('reportId');
        if (!$reportId) {
            $this->error("[$reportName] Report ID not returned.");
            return;
        }

        $this->waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey);
    }

    protected function waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey)
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

                $this->downloadAndParseReport($location, $reportName, $profileId, $adType, $startDate, $rangeKey);
                return;
            }

            if ($status === 'FAILED') {
                $this->error("[Report: {$reportId}] Report generation failed.");
                return;
            }
        }

        $this->error("[Report: {$reportId}] Report not ready after {$timeoutSeconds} seconds.");
    }

    private function downloadAndParseReport($downloadUrl, $reportName, $profileId, $adType, $startDate, $rangeKey)
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
            AmazonSpCampaignReport::updateOrCreate(
                [
                    'campaign_id' => $row['campaignId'] ?? null,
                    'profile_id' => $profileId,
                    'report_date_range' => $rangeKey,
                ],
                array_merge($row, [
                    'profile_id' => $profileId,
                    'report_date_range' => $rangeKey,
                    'ad_type' => $adType,
                ])
            );
        }

        $this->info("[SPONSORED_PRODUCTS - $rangeKey] Stored " . count($rows) . " rows to DB.");
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

    private function getAllowedMetrics(): array
    {
        return [
            'impressions', 'clicks', 'cost', 'spend', 'purchases1d', 'purchases7d',
                'purchases14d', 'purchases30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d',
                'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
                'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d',
                'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d',
                'clickThroughRate', 'costPerClick', 'qualifiedBorrows', 'addToList',
                'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode',
                'royaltyQualifiedBorrows', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 
                'purchasesSameSku30d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d', 'campaignBiddingStrategy', 'startDate', 'endDate', 'campaignStatus',
        ];
    }
}
