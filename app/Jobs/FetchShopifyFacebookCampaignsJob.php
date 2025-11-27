<?php

namespace App\Jobs;

use App\Models\ShopifyFacebookCampaign;
use App\Services\ShopifyMarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchShopifyFacebookCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dateRange;

    /**
     * Create a new job instance.
     *
     * @param string $dateRange ('7_days', '30_days', '60_days', or 'all')
     */
    public function __construct($dateRange = 'all')
    {
        $this->dateRange = $dateRange;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting Shopify Facebook Campaigns fetch", ['date_range' => $this->dateRange]);

        $service = new ShopifyMarketingService();
        
        $dateRanges = $this->dateRange === 'all' 
            ? ['7_days', '30_days', '60_days'] 
            : [$this->dateRange];

        foreach ($dateRanges as $range) {
            try {
                Log::info("Fetching data for range: {$range}");
                
                // Fetch campaign data from Shopify
                $campaigns = $service->fetchOrdersWithUtmData($range);
                
                if (empty($campaigns)) {
                    Log::warning("No campaigns found for range: {$range}");
                    continue;
                }

                // Store or update campaigns in database
                foreach ($campaigns as $campaignData) {
                    $this->storeCampaign($campaignData);
                }

                Log::info("Successfully processed {$range}", [
                    'campaigns_count' => count($campaigns),
                    'total_sales' => array_sum(array_column($campaigns, 'sales')),
                    'total_orders' => array_sum(array_column($campaigns, 'orders'))
                ]);

                // Wait 3 seconds between date ranges to avoid rate limiting
                if (count($dateRanges) > 1) {
                    sleep(3);
                }

            } catch (\Exception $e) {
                Log::error("Error processing date range: {$range}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info("Completed Shopify Facebook Campaigns fetch");
    }

    /**
     * Store or update campaign data
     * 
     * @param array $campaignData
     */
    protected function storeCampaign($campaignData)
    {
        try {
            // Use updateOrCreate to avoid duplicates
            ShopifyFacebookCampaign::updateOrCreate(
                [
                    'campaign_id' => $campaignData['campaign_id'],
                    'date_range' => $campaignData['date_range'],
                    'start_date' => $campaignData['start_date'],
                    'end_date' => $campaignData['end_date'],
                ],
                [
                    'campaign_name' => $campaignData['campaign_name'],
                    'sales' => $campaignData['sales'],
                    'orders' => $campaignData['orders'],
                    'sessions' => $campaignData['sessions'],
                    'conversion_rate' => $campaignData['conversion_rate'],
                    'ad_spend' => $campaignData['ad_spend'],
                    'roas' => $campaignData['roas'],
                    'referring_channel' => $campaignData['referring_channel'],
                    'traffic_type' => $campaignData['traffic_type'],
                    'country' => $campaignData['country'],
                ]
            );

            Log::info("Stored campaign: {$campaignData['campaign_name']}", [
                'date_range' => $campaignData['date_range'],
                'sales' => $campaignData['sales'],
                'orders' => $campaignData['orders']
            ]);

        } catch (\Exception $e) {
            Log::error("Error storing campaign", [
                'campaign' => $campaignData,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        Log::error("FetchShopifyFacebookCampaignsJob failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
