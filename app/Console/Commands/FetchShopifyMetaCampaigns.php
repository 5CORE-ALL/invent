<?php

namespace App\Console\Commands;

use App\Models\ShopifyMetaCampaign;
use App\Services\ShopifyMarketingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchShopifyMetaCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:fetch-meta-campaigns {--range=all : Date range (7_days, 30_days, 60_days, or all)} {--channel=both : Channel (facebook, instagram, or both)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Meta (Facebook & Instagram) campaign data from Shopify for sales and orders (7, 30, 60 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $range = $this->option('range');
        $channel = $this->option('channel');
        
        $validRanges = ['7_days', '30_days', '60_days', 'all'];
        $validChannels = ['facebook', 'instagram', 'both'];
        
        if (!in_array($range, $validRanges)) {
            $this->error("Invalid range. Valid options: " . implode(', ', $validRanges));
            return 1;
        }

        if (!in_array($channel, $validChannels)) {
            $this->error("Invalid channel. Valid options: " . implode(', ', $validChannels));
            return 1;
        }

        $this->info("Starting to fetch Shopify Meta campaigns data for: {$range}, Channel: {$channel}");
        Log::info("Starting Shopify Meta Campaigns fetch", ['date_range' => $range, 'channel' => $channel]);
        
        try {
            $service = new ShopifyMarketingService();
            
            $dateRanges = $range === 'all' 
                ? ['7_days', '30_days', '60_days'] 
                : [$range];

            $totalCampaigns = 0;

            foreach ($dateRanges as $dateRange) {
                $this->info("Fetching data for range: {$dateRange}, Channel: {$channel}");
                Log::info("Fetching data for range: {$dateRange}, Channel: {$channel}");
                
                // Fetch campaign data from Shopify
                $campaigns = $service->fetchOrdersWithUtmData($dateRange, $channel);
                
                if (empty($campaigns)) {
                    $this->warn("No campaigns found for range: {$dateRange}, Channel: {$channel}");
                    Log::warning("No campaigns found for range: {$dateRange}, Channel: {$channel}");
                    continue;
                }

                // Store or update campaigns in database
                foreach ($campaigns as $campaignData) {
                    $this->storeCampaign($campaignData);
                    $totalCampaigns++;
                }

                $totalSales = array_sum(array_column($campaigns, 'sales'));
                $totalOrders = array_sum(array_column($campaigns, 'orders'));

                $this->info("✓ Processed {$dateRange} ({$channel}): " . count($campaigns) . " campaigns, Sales: $" . number_format($totalSales, 2) . ", Orders: {$totalOrders}");
                
                Log::info("Successfully processed {$dateRange}", [
                    'channel' => $channel,
                    'campaigns_count' => count($campaigns),
                    'total_sales' => $totalSales,
                    'total_orders' => $totalOrders
                ]);

                // Wait 3 seconds between date ranges to avoid rate limiting
                if (count($dateRanges) > 1 && $dateRange !== end($dateRanges)) {
                    $this->info("Waiting 3 seconds before next range...");
                    sleep(3);
                }
            }

            $this->info("✓ Completed! Total campaigns processed: {$totalCampaigns}");
            Log::info("Completed Shopify Meta Campaigns fetch", ['total_campaigns' => $totalCampaigns, 'channel' => $channel]);
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Error in FetchShopifyMetaCampaigns", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
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
            ShopifyMetaCampaign::updateOrCreate(
                [
                    'campaign_id' => $campaignData['campaign_id'],
                    'date_range' => $campaignData['date_range'],
                    'start_date' => $campaignData['start_date'],
                    'end_date' => $campaignData['end_date'],
                    'referring_channel' => $campaignData['referring_channel'],
                ],
                [
                    'campaign_name' => $campaignData['campaign_name'],
                    'sales' => $campaignData['sales'],
                    'orders' => $campaignData['orders'],
                    'sessions' => $campaignData['sessions'],
                    'conversion_rate' => $campaignData['conversion_rate'],
                    'ad_spend' => $campaignData['ad_spend'],
                    'roas' => $campaignData['roas'],
                    'traffic_type' => $campaignData['traffic_type'],
                    'country' => $campaignData['country'],
                ]
            );

        } catch (\Exception $e) {
            Log::error("Error storing campaign", [
                'campaign' => $campaignData,
                'error' => $e->getMessage()
            ]);
        }
    }
}

