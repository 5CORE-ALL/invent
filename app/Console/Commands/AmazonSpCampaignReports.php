<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonKwLastSbidDaily;

class AmazonSpCampaignReports extends Command
{
    protected $signature = 'app:amazon-sp-campaign-reports';
    protected $description = 'Fetch and store Sponsored Products campaign reports (daily + L30/L15/L7)';

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

            $profileId = config('services.amazon_ads.profile_ids');
            $adType = 'SPONSORED_PRODUCTS';
            $reportTypeId = 'spCampaigns';

            if (empty($profileId)) {
                $this->error("AMAZON_ADS_PROFILE_IDS is not set in environment.");
                return 1;
            }

            $yesterday = now()->copy()->subDay()->toDateString();

            // Daily: fetch only yesterday's data (normalized CPC, spend, clicks etc.) and sync L1 from same source
            $this->fetchReport($profileId, $adType, $reportTypeId, $yesterday, $yesterday, $yesterday, true, true);
            $this->info("✅ Daily data fetched: {$yesterday} (L1 synced)");
            DB::connection()->disconnect();

            // Summary ranges for table (L30, L15, L7)
            $dateRanges = $this->getDateRanges();

            foreach ($dateRanges as $rangeLabel => [$startDate, $endDate]) {
                $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeLabel, false);
                // Close connection between reports to prevent buildup
                DB::connection()->disconnect();
            }

            $this->info("✅ All Sponsored Products reports processed successfully.");
        } catch (\Exception $e) {
            $this->error("Error in handle: " . $e->getMessage());
            $this->info("Error trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            // Ensure connection is closed
            DB::connection()->disconnect();
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
            // L1 is populated from yesterday's daily fetch above (same source = no daily data mismatch)
        ];
    }

    private function fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeKey, $isDailyChart = false, $syncL1 = false)
    {
        $accessToken = $this->getAccessToken();
        $reportName = "{$adType}_{$rangeKey}_Campaign";

        $timeUnit = ($startDate === $endDate) ? 'DAILY' : 'SUMMARY';
        
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->withHeaders([
                'Amazon-Advertising-API-Scope' => $profileId,
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
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

            $this->waitForReportReady($reportName, $profileId, trim($existingReportId), $adType, $startDate, $rangeKey, $isDailyChart, $syncL1);
            return;
        }

        // 🚦 Handle 429 Rate Limiting
        if ($response->status() == 429) {
            $this->warn("[$reportName] Rate limited. Waiting 60 seconds before retry...");
            sleep(60);
            // Retry once after rate limit
            $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeKey, $isDailyChart, $syncL1);
            return;
        }

        if (!$response->ok()) {
            $this->error("Failed to request SP report {$rangeKey}: " . $response->body());
            return;
        }


        $reportId = $response->json('reportId');
        if (!$reportId) {
            $this->error("[$reportName] Report ID not returned.");
            return;
        }

        $this->waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey, $isDailyChart, $syncL1);
    }

    protected function waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey, $isDailyChart = false, $syncL1 = false)
    {
        $start = now();
        $timeoutSeconds = 3600; // 1 hour max

        while (now()->diffInSeconds($start) < $timeoutSeconds) {
            sleep(300); // 5 minutes

            $token = $this->getAccessToken(); // 🔁 refresh token each poll

            $statusResponse = Http::timeout(60)
                ->retry(3, 3000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
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

                $this->downloadAndParseReport($location, $reportName, $profileId, $adType, $startDate, $rangeKey, $isDailyChart, $syncL1);
                return;
            }

            if ($status === 'FAILED') {
                $this->error("[Report: {$reportId}] Report generation failed.");
                return;
            }
        }

        $this->error("[Report: {$reportId}] Report not ready after {$timeoutSeconds} seconds.");
    }

    private function downloadAndParseReport($downloadUrl, $reportName, $profileId, $adType, $startDate, $rangeKey, $isDailyChart = false, $syncL1 = false)
    {
        try {
            $this->info("[$reportName] Downloading and parsing report...");

            $response = Http::timeout(60)->retry(3, 3000)->withoutVerifying()->get($downloadUrl);

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
                            $row = $this->normalizeRowForDaily($row);
                            $payload = array_merge($row, [
                                'profile_id' => $profileId,
                                'report_date_range' => $finalRangeKey,
                                'ad_type' => $adType,
                                'report_date' => $startDate,
                            ]);
                            // Carry forward last_sbid date-wise for KW Last SBID graph (from previous day or L1)
                            $campaignId = $row['campaignId'] ?? null;
                            if ($campaignId && (!isset($payload['last_sbid']) || $payload['last_sbid'] === null || $payload['last_sbid'] === '')) {
                                $prevDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
                                $existing = AmazonSpCampaignReport::where('campaign_id', $campaignId)
                                    ->where('profile_id', $profileId)
                                    ->where('ad_type', $adType)
                                    ->where(function ($q) use ($prevDate) {
                                        $q->where('report_date_range', $prevDate)->orWhere('report_date_range', 'L1');
                                    })
                                    ->orderByRaw("CASE WHEN report_date_range = 'L1' THEN 0 ELSE 1 END")
                                    ->value('last_sbid');
                                if ($existing !== null && $existing !== '') {
                                    $payload['last_sbid'] = $existing;
                                }
                            }
                            AmazonSpCampaignReport::updateOrCreate(
                                [
                                    'campaign_id' => $campaignId,
                                    'profile_id' => $profileId,
                                    'report_date_range' => $finalRangeKey,
                                ],
                                $payload
                            );
                            // Record KW Last SBID data only (date-wise daily) for chart
                            if ($adType === 'SPONSORED_PRODUCTS') {
                                $lastSbid = $payload['last_sbid'] ?? null;
                                if ($lastSbid !== null && $lastSbid !== '') {
                                    AmazonKwLastSbidDaily::updateOrCreate(
                                        [
                                            'campaign_id' => $campaignId,
                                            'report_date' => $finalRangeKey,
                                        ],
                                        [
                                            'profile_id' => $profileId,
                                            'last_sbid' => $lastSbid,
                                            'campaign_name' => $payload['campaignName'] ?? null,
                                        ]
                                    );
                                }
                            }
                            if ($syncL1) {
                                AmazonSpCampaignReport::updateOrCreate(
                                    [
                                        'campaign_id' => $row['campaignId'] ?? null,
                                        'profile_id' => $profileId,
                                        'report_date_range' => 'L1',
                                    ],
                                    array_merge($row, [
                                        'profile_id' => $profileId,
                                        'report_date_range' => 'L1',
                                        'ad_type' => $adType,
                                    ])
                                );
                            }
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
                        $totalStored++;
                    } catch (\Exception $e) {
                        $this->info("Error storing row in {$reportName}: " . $e->getMessage());
                        continue;
                    }
                }

                // Close connection after each chunk to prevent buildup
                DB::connection()->disconnect();
            }

            $this->info("[SPONSORED_PRODUCTS - $finalRangeKey] Stored " . $totalStored . " rows to DB.");
            $this->info("[$reportName] Report saved successfully.");
        } catch (\Exception $e) {
            $this->error("[$reportName] Error in downloadAndParseReport: " . $e->getMessage());
            $this->info("Error trace: " . $e->getTraceAsString());
        } finally {
            DB::connection()->disconnect();
        }
    }

    /**
     * Normalize daily report row so CPC, spend, clicks, cost, sales etc. match Amazon report (avoid float/rounding mismatch).
     */
    private function normalizeRowForDaily(array $row): array
    {
        $round4 = static function ($v) {
            return $v !== null && $v !== '' ? round((float) $v, 4) : null;
        };
        $int = static function ($v) {
            return $v !== null && $v !== '' ? (int) round((float) $v) : null;
        };

        $clicks = $int($row['clicks'] ?? 0);
        $cost = $round4($row['cost'] ?? $row['spend'] ?? null);
        $spend = $round4($row['spend'] ?? $row['cost'] ?? null);

        $row['clicks'] = $clicks;
        $row['cost'] = $cost;
        $row['spend'] = $spend ?? $cost;

        $apiCpc = isset($row['costPerClick']) && (float) ($row['costPerClick'] ?? 0) > 0
            ? (float) $row['costPerClick']
            : null;
        if ($apiCpc !== null) {
            $row['costPerClick'] = round($apiCpc, 4);
        } elseif ($clicks > 0 && ($spend !== null || $cost !== null)) {
            $row['costPerClick'] = round(($spend ?? $cost) / $clicks, 4);
        }

        foreach (['sales1d', 'sales7d', 'sales14d', 'sales30d', 'campaignBudgetAmount', 'clickThroughRate'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $round4($row[$key]);
            }
        }
        foreach (['impressions', 'purchases1d', 'purchases7d', 'purchases14d', 'purchases30d', 'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $int($row[$key]);
            }
        }

        return $row;
    }

    private function getAccessToken()
    {
        try {
            $clientId = config('services.amazon_ads.client_id');
            $clientSecret = config('services.amazon_ads.client_secret');
            $refreshToken = config('services.amazon_ads.refresh_token');

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
