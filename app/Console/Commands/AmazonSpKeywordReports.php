<?php

namespace App\Console\Commands;

use App\Models\AmazonSpKeywordReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetch Sponsored Products keyword / targeting performance via the Reporting API v3
 * (reportTypeId = spTargeting, groupBy = targeting) and store one row per keyword per
 * report_date_range. Mirrors {@see AmazonSpCampaignReports}: yesterday daily (+ L1 sync)
 * plus L30 / L15 / L7 summary windows, async request → poll → gzip download → upsert.
 */
class AmazonSpKeywordReports extends Command
{
    protected $signature = 'app:amazon-sp-keyword-reports';

    protected $description = 'Fetch and store Sponsored Products keyword/targeting performance (daily + L30/L15/L7)';

    private const AD_TYPE = 'SPONSORED_PRODUCTS';

    private const REPORT_TYPE_ID = 'spTargeting';

    public function handle()
    {
        try {
            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: '.$e->getMessage());

                return 1;
            }

            $profileId = config('services.amazon_ads.profile_ids');
            if (empty($profileId)) {
                $this->error('AMAZON_ADS_PROFILE_IDS is not set in environment.');

                return 1;
            }

            $yesterday = now()->copy()->subDay()->toDateString();

            // Daily (yesterday) + L1 synced from the same fetch.
            $this->fetchReport($profileId, $yesterday, $yesterday, $yesterday, true, true);
            $this->info("✅ Daily keyword data fetched: {$yesterday} (L1 synced)");
            DB::connection()->disconnect();

            foreach ($this->getDateRanges() as $rangeLabel => [$startDate, $endDate]) {
                $this->fetchReport($profileId, $startDate, $endDate, $rangeLabel, false);
                DB::connection()->disconnect();
            }

            $this->info('✅ All Sponsored Products keyword reports processed successfully.');
        } catch (\Exception $e) {
            $this->error('Error in handle: '.$e->getMessage());
            $this->info('Error trace: '.$e->getTraceAsString());

            return 1;
        } finally {
            DB::connection()->disconnect();
        }

        return 0;
    }

    private function getDateRanges(): array
    {
        $today = now();
        $endL30 = $today->copy()->subDay();
        $startL30 = $endL30->copy()->subDays(29);

        return [
            'L30' => [$startL30->toDateString(), $endL30->toDateString()],
            'L15' => [$today->copy()->subDays(15)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L7' => [$today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString()],
        ];
    }

    private function fetchReport($profileId, $startDate, $endDate, $rangeKey, bool $isDaily = false, bool $syncL1 = false): void
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return;
        }

        $reportName = self::AD_TYPE.'_'.$rangeKey.'_Keyword';
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
                    'adProduct' => self::AD_TYPE,
                    'groupBy' => ['targeting'],
                    'reportTypeId' => self::REPORT_TYPE_ID,
                    'columns' => $this->getAllowedMetrics($timeUnit),
                    'format' => 'GZIP_JSON',
                    'timeUnit' => $timeUnit,
                ],
            ]);

        if ($response->status() == 425) {
            $existingReportId = trim(str_replace('The Request is a duplicate of : ', '', (string) $response->json('detail')));
            $this->warn("[$reportName] Duplicate request. Using existing reportId: $existingReportId");
            $this->waitForReportReady($reportName, $profileId, $existingReportId, $startDate, $rangeKey, $isDaily, $syncL1);

            return;
        }

        if ($response->status() == 429) {
            $this->warn("[$reportName] Rate limited. Waiting 60 seconds before retry...");
            sleep(60);
            $this->fetchReport($profileId, $startDate, $endDate, $rangeKey, $isDaily, $syncL1);

            return;
        }

        if (! $response->ok()) {
            $this->error("Failed to request SP keyword report {$rangeKey}: ".$response->body());

            return;
        }

        $reportId = $response->json('reportId');
        if (! $reportId) {
            $this->error("[$reportName] Report ID not returned.");

            return;
        }

        $this->waitForReportReady($reportName, $profileId, $reportId, $startDate, $rangeKey, $isDaily, $syncL1);
    }

    private function waitForReportReady($reportName, $profileId, $reportId, $startDate, $rangeKey, bool $isDaily, bool $syncL1): void
    {
        $start = now();
        $timeoutSeconds = 3600;

        while (now()->diffInSeconds($start) < $timeoutSeconds) {
            sleep(300);

            $token = $this->getAccessToken();

            $statusResponse = Http::timeout(60)
                ->retry(3, 3000)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Content-Type' => 'application/vnd.getasyncreportresponse.v3+json',
                ])
                ->get("https://advertising-api.amazon.com/reporting/reports/{$reportId}");

            if ($statusResponse->status() === 401) {
                $this->warn("[Report: {$reportId}] 401 Unauthorized — refreshing token...");

                continue;
            }

            if (! $statusResponse->successful()) {
                $this->warn("[Report: {$reportId}] Polling failed: ".$statusResponse->body());

                continue;
            }

            $status = $statusResponse['status'] ?? 'UNKNOWN';
            $this->info(now()->toDateTimeString()." [Report: {$reportId}] Status: $status");

            if ($status === 'COMPLETED') {
                $location = $statusResponse['location'] ?? $statusResponse['url'] ?? null;
                if (! $location) {
                    $this->error("[$reportName] Missing report location.");

                    return;
                }

                $this->downloadAndParseReport($location, $reportName, $profileId, $startDate, $rangeKey, $isDaily, $syncL1);

                return;
            }

            if ($status === 'FAILED') {
                $this->error("[Report: {$reportId}] Report generation failed.");

                return;
            }
        }

        $this->error("[Report: {$reportId}] Report not ready after {$timeoutSeconds} seconds.");
    }

    private function downloadAndParseReport($downloadUrl, $reportName, $profileId, $startDate, $rangeKey, bool $isDaily, bool $syncL1): void
    {
        try {
            $this->info("[$reportName] Downloading and parsing report...");

            $response = Http::timeout(60)->retry(3, 3000)->withoutVerifying()->get($downloadUrl);
            if (! $response->ok()) {
                $this->error("[$reportName] Failed to download report file.");

                return;
            }

            $jsonString = gzdecode($response->body());
            if (! $jsonString) {
                $this->error("[$reportName] Failed to decode gzip content.");

                return;
            }

            $rows = json_decode($jsonString, true);
            if (! is_array($rows) || empty($rows)) {
                $this->warn("[$reportName] No records found.");

                return;
            }

            $this->info("[$reportName] Total rows: ".count($rows));

            $finalRangeKey = $isDaily ? $startDate : $rangeKey;
            $totalStored = 0;

            foreach (array_chunk($rows, 200) as $chunk) {
                foreach ($chunk as $row) {
                    try {
                        $this->storeRow($row, $profileId, $finalRangeKey);
                        if ($syncL1) {
                            $this->storeRow($row, $profileId, 'L1');
                        }
                        $totalStored++;
                    } catch (\Exception $e) {
                        $this->info("Error storing row in {$reportName}: ".$e->getMessage());

                        continue;
                    }
                }
                DB::connection()->disconnect();
            }

            $this->info("[SP Keyword - $finalRangeKey] Stored ".$totalStored.' rows to DB.');
        } catch (\Exception $e) {
            $this->error("[$reportName] Error in downloadAndParseReport: ".$e->getMessage());
            $this->info('Error trace: '.$e->getTraceAsString());
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function storeRow(array $row, string $profileId, string $rangeKey): void
    {
        $keywordId = $row['keywordId'] ?? null;
        $targeting = $row['targeting'] ?? ($row['keyword'] ?? null);

        // Skip rows with no keyword and no targeting expression (nothing to key on).
        if (($keywordId === null || $keywordId === '') && ($targeting === null || $targeting === '')) {
            return;
        }

        // Only keep ENABLED keywords / campaigns — skip paused/archived so we don't store the
        // whole account history (keeps the grid to live keywords and speeds up the upserts).
        if ($this->isNotEnabled($row['adKeywordStatus'] ?? null) || $this->isNotEnabled($row['campaignStatus'] ?? null)) {
            return;
        }

        $payload = array_merge($row, [
            'profile_id' => $profileId,
            'report_date_range' => $rangeKey,
            'ad_type' => self::AD_TYPE,
            'campaign_id' => $row['campaignId'] ?? null,
            'ad_group_id' => $row['adGroupId'] ?? null,
            'keyword_id' => $keywordId,
            'targeting' => $targeting,
        ]);

        AmazonSpKeywordReport::updateOrCreate(
            [
                'profile_id' => $profileId,
                'report_date_range' => $rangeKey,
                'keyword_id' => $keywordId,
                'targeting' => $targeting,
            ],
            $payload
        );
    }

    /**
     * True only when the status is explicitly a non-ENABLED value (PAUSED / ARCHIVED / …).
     * Null / empty statuses are treated as keep (don't drop rows just because the field is absent).
     */
    private function isNotEnabled($status): bool
    {
        if ($status === null || $status === '') {
            return false;
        }

        return strtoupper(trim((string) $status)) !== 'ENABLED';
    }

    private function getAllowedMetrics(string $timeUnit = 'SUMMARY'): array
    {
        $metrics = [
            'campaignId', 'campaignName', 'campaignStatus',
            'adGroupId', 'adGroupName',
            'keywordId', 'keyword', 'keywordType', 'matchType', 'targeting', 'adKeywordStatus',
            'impressions', 'clicks', 'cost', 'costPerClick', 'clickThroughRate',
            'purchases1d', 'purchases7d', 'purchases14d', 'purchases30d',
            'sales1d', 'sales7d', 'sales14d', 'sales30d',
            'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
            'acosClicks14d', 'roasClicks14d',
        ];

        if ($timeUnit !== 'DAILY') {
            $metrics[] = 'startDate';
            $metrics[] = 'endDate';
        }

        return $metrics;
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

            if (! $tokenResponse->successful()) {
                $this->error('Token fetch failed: '.$tokenResponse->body());

                return null;
            }

            $accessToken = $tokenResponse['access_token'] ?? null;
            if (empty($accessToken)) {
                $this->error('Access token not returned in response: '.$tokenResponse->body());

                return null;
            }

            return $accessToken;
        } catch (\Exception $e) {
            $this->error('Error getting access token: '.$e->getMessage());

            return null;
        }
    }
}
