<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSdCampaignReport;

class AmazonCampaignReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:amazon-campaign-reports';

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
        $profileId = env('AMAZON_ADS_PROFILE_IDS');

        $adTypes = [
            'SPONSORED_PRODUCTS' => 'spCampaigns',
            'SPONSORED_BRANDS' => 'sbCampaigns',
            'SPONSORED_DISPLAY' => 'sdCampaigns',
        ];
    
        $today = now();

        // Fetch last 30 days of DAILY data for charts
        for ($i = 1; $i <= 30; $i++) {
            $date = $today->copy()->subDays($i)->toDateString();
            
            foreach ($adTypes as $adType => $reportTypeId) {
                $this->fetchReport($profileId, $adType, $reportTypeId, $date, $date, $date);
            }
        }

        // Also fetch summary ranges for backward compatibility with table data
        $endL30 = $today->copy()->subDay();               
        $startL30 = $endL30->copy()->subDays(29);         

        $dateRanges = [
            'L30' => [$startL30->toDateString(), $endL30->toDateString()], 
            'L15' => [$today->copy()->subDays(15)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L7'  => [$today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString()],
            'L1'  => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
        ];
    
        foreach ($adTypes as $adType => $reportTypeId) {
            foreach ($dateRanges as $rangeLabel => [$startDate, $endDate]) {
                $this->fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeLabel);
            }
        }
    }

    private function fetchReport($profileId, $adType, $reportTypeId, $startDate, $endDate, $rangeKey)
    {
        $accessToken = $this->getAccessToken();
        $reportName = "{$adType}_{$rangeKey}_Campaign";
        
        $response = Http::withToken($accessToken)
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
                    'adProduct' => $adType, // SPONSORED_PRODUCTS / SPONSORED_BRANDS / SPONSORED_DISPLAY
                    'groupBy' => ['campaign'],
                    'reportTypeId' => $reportTypeId,
                    'columns' => $this->getAllowedMetricsForAdType($adType),
                    'format' => 'GZIP_JSON',
                    'timeUnit' => ($startDate === $endDate) ? 'DAILY' : 'SUMMARY', // Use DAILY for single dates, SUMMARY for ranges
                ]
            ]);

        if (!$response->ok()) {
            Log::error("Failed to request report for {$adType} - {$rangeKey}: " . $response->body());
            return;
        }

        if ($response->status() === 425) {
            $body = $response->json();
            if (preg_match('/([0-9a-f\-]{36})/', $body['detail'], $matches)) {
                $reportId = $matches[1]; 
                $this->info("Duplicate report request. Reusing existing reportId: $reportId");
                // wait logic here:
                $this->waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey);
                return;
            }
        }

        $reportId = $response->json('reportId');
        $downloadUrl = $this->waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey);
    }

    protected function waitForReportReady($reportName, $profileId, $reportId, $adType, $startDate, $rangeKey)
    {
        $token = $this->getAccessToken(); // Use fresh token
        $start = now();
        $timeoutSeconds = ($adType === 'SPONSORED_BRANDS') ? 5400 : 3600; // SB takes longer

        while (now()->diffInSeconds($start) < $timeoutSeconds) {
            sleep(600); // Wait 10 minutes between checks (Amazon's realistic interval)

            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                'Amazon-Advertising-API-Scope' => $profileId,
                'Content-Type' => 'application/vnd.getasyncreportresponse.v3+json',
            ])->get("https://advertising-api.amazon.com/reporting/reports/{$reportId}");

            if ($statusResponse->successful()) {
                $status = $statusResponse['status'] ?? 'UNKNOWN';
                $this->info("[Report: {$reportId}] after 5 mins. Status: $status");

                if ($status === 'COMPLETED') {
                    $location = $statusResponse['location'] ?? $statusResponse['url'] ?? null;

                    if (!$location) {
                        $this->error("[$reportName] Report location missing.");
                        return;
                    }
                
                    $this->info("[$reportName] Report ready. Downloading...");
                
                    $this->downloadAndParseReport($location, $reportName, $profileId, $adType, $startDate, $rangeKey);
                
                    return;
                }

                if ($status === 'FAILED') {
                    $this->error("[Report: {$reportId}] Report generation failed.");
                    return null;
                }
            } else {
                $this->warn("[Report: {$reportId}] Polling failed: " . $statusResponse->body());
            }
        }

        $this->error("[Report: {$reportId}] Report not ready after {$timeoutSeconds} seconds.");
        return null;
    }

    private function downloadAndParseReport($downloadUrl, $reportName, $profileId, $adType, $startDate, $rangeKey)
    {
        $this->info("[$reportName] Downloading and parsing report...");

        // Step 1: Download GZIP content
        $response = Http::withoutVerifying()->get($downloadUrl);

        if (!$response->ok()) {
            $this->error("[$reportName] Failed to download report file.");
            return;
        }

        // Step 2: Decompress GZIP
        $compressed = $response->body();
        $jsonString = gzdecode($compressed);

        if (!$jsonString) {
            $this->error("[$reportName] Failed to decode gzip content.");
            return;
        }

        // Step 3: Parse JSON
        $rows = json_decode($jsonString, true);

        if (!is_array($rows) || count($rows) === 0) {
            $this->warn("[$reportName] No records found.");
            return;
        }

        $this->info("[$reportName] Total rows: " . count($rows));

        // Step 4: Store each row
        foreach ($rows as $row) {
            $data = array_merge($row, [
                'profile_id' => $profileId,
                'report_date_range' => $rangeKey,
                'ad_type' => $adType,
            ]);
    
            switch ($adType) {
                case 'SPONSORED_PRODUCTS':
                    AmazonSpCampaignReport::updateOrCreate(
                        [
                            'campaign_id' => $data['campaignId'] ?? null,
                            'profile_id' => $profileId,
                            'report_date_range' => $rangeKey,
                        ],
                        $data
                    );
                    break;
            
                case 'SPONSORED_DISPLAY':
                    AmazonSdCampaignReport::updateOrCreate(
                        [
                            'campaign_id' => $data['campaignId'] ?? null,
                            'profile_id' => $profileId,
                            'report_date_range' => $rangeKey,
                        ],
                        $data
                    );
                    break;
            
                case 'SPONSORED_BRANDS':
                    AmazonSbCampaignReport::updateOrCreate(
                        [
                            'campaign_id' => $data['campaignId'] ?? null,
                            'profile_id' => $profileId,
                            'report_date_range' => $rangeKey,
                        ],
                        $data
                    );
                    break;
            
                default:
                    $this->warn("Unknown ad type: $adType");
            }
        }
    
        $this->info("[$adType - $rangeKey] Stored " . count($rows) . " rows to DB.");

        $this->info("[$reportName] Report saved successfully.");
    }

    private function getAccessToken()
    {
        $clientId = env('AMAZON_ADS_CLIENT_ID');
        $clientSecret = env('AMAZON_ADS_CLIENT_SECRET');
        $refreshToken = env('AMAZON_ADS_REFRESH_TOKEN');

        // Step 1: Get access token
        $tokenResponse = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$tokenResponse->successful()) {
            $this->error('Token fetch failed: ' . $tokenResponse->body());
            Log::error('Token fetch failed: ' . $tokenResponse->body());
            return;
        }

        $this->info('token generated');
        return $tokenResponse['access_token'];
    }

    private function getAllowedMetricsForAdType(string $adType): array
    {
        return match($adType) {
            'SPONSORED_PRODUCTS' => [
                'impressions', 'clicks', 'cost', 'spend', 'purchases1d', 'purchases7d',
                'purchases14d', 'purchases30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d',
                'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
                'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d',
                'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d',
                'clickThroughRate', 'costPerClick', 'qualifiedBorrows', 'addToList',
                'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode',
                'royaltyQualifiedBorrows', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 
                'purchasesSameSku30d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d', 'campaignBiddingStrategy', 'startDate', 'endDate', 'campaignStatus',
            ],
            'SPONSORED_BRANDS' => [
                'addToCart', 'addToCartClicks', 'addToCartRate', 'addToList', 'addToListFromClicks', 'qualifiedBorrows', 'qualifiedBorrowsFromClicks', 
                'royaltyQualifiedBorrows', 'royaltyQualifiedBorrowsFromClicks', 'impressions', 'clicks', 'cost', 'sales', 'salesClicks', 'purchases', 'purchasesClicks',
                'brandedSearches', 'brandedSearchesClicks', 'newToBrandSales', 'newToBrandSalesClicks', 'newToBrandPurchases', 'newToBrandPurchasesClicks',
                'campaignBudgetType', 'videoCompleteViews', 'videoUnmutes', 'viewabilityRate', 'viewClickThroughRate',
                'detailPageViews', 'detailPageViewsClicks', 'eCPAddToCart', 'newToBrandDetailPageViewRate', 'newToBrandDetailPageViews', 
                'newToBrandDetailPageViewsClicks', 'newToBrandECPDetailPageView', 'newToBrandPurchasesPercentage', 'newToBrandUnitsSold', 'newToBrandUnitsSoldClicks', 
                'newToBrandUnitsSoldPercentage', 'unitsSold', 'unitsSoldClicks', 'topOfSearchImpressionShare', 'newToBrandPurchasesRate',
                'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode', 'newToBrandSalesPercentage',
                'campaignStatus', 'salesPromoted', 'video5SecondViewRate', 'video5SecondViews', 'videoFirstQuartileViews', 'videoMidpointViews', 
                'videoThirdQuartileViews', 'viewableImpressions', 'startDate', 'endDate', 
            ],
            
            'SPONSORED_DISPLAY' => [
                'addToCart', 'addToCartClicks', 'addToCartRate', 'addToCartViews', 'addToList', 'addToListFromClicks', 'addToListFromViews', 'qualifiedBorrows', 
                'qualifiedBorrowsFromClicks', 'qualifiedBorrowsFromViews', 'royaltyQualifiedBorrows', 'royaltyQualifiedBorrowsFromClicks', 
                'royaltyQualifiedBorrowsFromViews', 'brandedSearches', 'brandedSearchesClicks', 'brandedSearchesViews', 'brandedSearchRate', 
                'campaignBudgetCurrencyCode', 'campaignId', 'campaignName', 'clicks', 'cost', 'detailPageViews', 'detailPageViewsClicks', 'eCPAddToCart', 
                'eCPBrandSearch', 'impressions', 'impressionsViews', 'newToBrandPurchases', 'newToBrandPurchasesClicks', 'newToBrandSalesClicks', 
                'newToBrandUnitsSold', 'newToBrandUnitsSoldClicks', 'purchases', 'purchasesClicks', 'purchasesPromotedClicks', 'sales', 'salesClicks', 
                'salesPromotedClicks', 'unitsSold', 'unitsSoldClicks', 'videoCompleteViews', 'videoFirstQuartileViews', 'videoMidpointViews', 
                'videoThirdQuartileViews', 'videoUnmutes', 'viewabilityRate', 'viewClickThroughRate', 'startDate', 'endDate', 'campaignStatus' 
            ],
            default => []
        };
    }

}
