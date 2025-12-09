<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsCampaign;
use App\Services\GoogleAdsSbidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchGoogleAdsCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-google-ads-campaigns {--days=1 : Number of days to fetch data for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Google Ads campaign data and metrics';

    protected $googleAdsService;

    public function __construct(GoogleAdsSbidService $googleAdsService)
    {
        parent::__construct();
        $this->googleAdsService = $googleAdsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $days = (int) $this->option('days');
            
            // Get customer ID from config
            $customerId = config('services.google_ads.login_customer_id') ?? env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');
            
            if (empty($customerId)) {
                $this->error('Google Ads Customer ID is not configured');
                Log::error('Google Ads Customer ID is not configured');
                return 1;
            }

            // Remove hyphens from customer ID if present
            $customerId = str_replace('-', '', $customerId);

            $this->info("Fetching Google Ads campaign data for last {$days} day(s)...");
            Log::info("Starting Google Ads campaign data fetch for last {$days} day(s)");

            // Calculate date range
            $startDate = Carbon::now()->subDays($days)->format('Y-m-d');
            $endDate = Carbon::now()->subDay()->format('Y-m-d');

            $this->info("Date range: {$startDate} to {$endDate}");

            // Step 1: Fetch all active campaigns first (without date/metrics filter)
            // This ensures we capture campaigns even if they have no metrics
            $this->info("Step 1: Fetching all active campaigns...");
            $allActiveCampaigns = $this->fetchAllActiveCampaigns($customerId);
            $this->info("Found " . count($allActiveCampaigns) . " active campaigns");

            // Step 2: Fetch metrics for the date range
            $this->info("Step 2: Fetching metrics for date range...");
            $query = $this->buildQuery($startDate, $endDate);
            $results = $this->googleAdsService->runQuery($customerId, $query);
            $this->info("Found " . count($results) . " records with metrics");

            // Step 3: Process metrics data
            $insertedCount = 0;
            $updatedCount = 0;
            $processedCampaignIds = [];

            foreach ($results as $row) {
                try {
                    $data = $this->prepareData($row);
                    $processedCampaignIds[$data['campaign_id']] = true;
                    
                    // Update or create record
                    $campaign = GoogleAdsCampaign::updateOrCreate(
                        [
                            'campaign_id' => $data['campaign_id'],
                            'date' => $data['date']
                        ],
                        $data
                    );

                    if ($campaign->wasRecentlyCreated) {
                        $insertedCount++;
                    } else {
                        $updatedCount++;
                    }

                } catch (\Exception $e) {
                    Log::error('Error processing campaign data: ' . $e->getMessage(), [
                        'row' => $row,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error('Error processing record: ' . $e->getMessage());
                }
            }

            // Step 4: For active campaigns without metrics, create records with zero metrics
            $zeroMetricsCount = 0;
            foreach ($allActiveCampaigns as $campaignData) {
                $campaign = $campaignData['campaign'] ?? [];
                $campaignId = (string) ($campaign['id'] ?? '');
                
                if (empty($campaignId)) {
                    continue;
                }
                
                // Skip if we already processed this campaign (it has metrics)
                if (isset($processedCampaignIds[$campaignId])) {
                    continue;
                }

                // Create records with zero metrics for each date in range
                $currentDate = Carbon::parse($startDate);
                $endDateObj = Carbon::parse($endDate);
                
                while ($currentDate->lte($endDateObj)) {
                    try {
                        $data = $this->prepareDataFromCampaign($campaignData, $currentDate->format('Y-m-d'));
                        
                        $campaign = GoogleAdsCampaign::updateOrCreate(
                            [
                                'campaign_id' => $data['campaign_id'],
                                'date' => $data['date']
                            ],
                            $data
                        );

                        if ($campaign->wasRecentlyCreated) {
                            $zeroMetricsCount++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Error creating zero-metrics record: ' . $e->getMessage());
                    }
                    
                    $currentDate->addDay();
                }
            }

            $this->info("Successfully inserted: {$insertedCount}, updated: {$updatedCount} records with metrics");
            if ($zeroMetricsCount > 0) {
                $this->info("Created {$zeroMetricsCount} records for campaigns with zero metrics");
            }
            Log::info("Google Ads campaign data fetch completed. Inserted: {$insertedCount}, Updated: {$updatedCount}, Zero-metrics: {$zeroMetricsCount}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Error fetching Google Ads data: ' . $e->getMessage());
            Log::error('Error fetching Google Ads data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Fetch all active campaigns (without metrics filter)
     */
    private function fetchAllActiveCampaigns($customerId)
    {
        $query = "
            SELECT
                campaign.id,
                campaign.name,
                campaign.status,
                campaign.primary_status,
                campaign.primary_status_reasons,
                campaign.serving_status,
                campaign.advertising_channel_type,
                campaign.experiment_type,
                campaign.bidding_strategy_type,
                campaign.payment_mode,
                campaign.start_date,
                campaign.end_date,
                campaign.network_settings.target_google_search,
                campaign.network_settings.target_search_network,
                campaign.network_settings.target_content_network,
                campaign.network_settings.target_partner_search_network,
                campaign.shopping_setting.merchant_id,
                campaign.shopping_setting.feed_label,
                campaign.shopping_setting.campaign_priority,
                campaign.geo_target_type_setting.positive_geo_target_type,
                campaign.geo_target_type_setting.negative_geo_target_type,
                campaign.manual_cpc.enhanced_cpc_enabled,
                campaign_budget.id,
                campaign_budget.name,
                campaign_budget.status,
                campaign_budget.amount_micros,
                campaign_budget.total_amount_micros,
                campaign_budget.delivery_method,
                campaign_budget.period,
                campaign_budget.explicitly_shared,
                campaign_budget.has_recommended_budget
            FROM campaign
            WHERE campaign.status = 'ENABLED'
        ";

        try {
            return $this->googleAdsService->runQuery($customerId, $query);
        } catch (\Exception $e) {
            Log::error('Error fetching active campaigns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build GAQL query for fetching campaign data
     */
    private function buildQuery($startDate, $endDate)
    {
        return "
            SELECT
                campaign.id,
                campaign.name,
                campaign.status,
                campaign.primary_status,
                campaign.primary_status_reasons,
                campaign.serving_status,
                campaign.advertising_channel_type,
                campaign.experiment_type,
                campaign.bidding_strategy_type,
                campaign.payment_mode,
                campaign.start_date,
                campaign.end_date,
                campaign.network_settings.target_google_search,
                campaign.network_settings.target_search_network,
                campaign.network_settings.target_content_network,
                campaign.network_settings.target_partner_search_network,
                campaign.shopping_setting.merchant_id,
                campaign.shopping_setting.feed_label,
                campaign.shopping_setting.campaign_priority,
                campaign.geo_target_type_setting.positive_geo_target_type,
                campaign.geo_target_type_setting.negative_geo_target_type,
                campaign.manual_cpc.enhanced_cpc_enabled,
                campaign_budget.id,
                campaign_budget.name,
                campaign_budget.status,
                campaign_budget.amount_micros,
                campaign_budget.total_amount_micros,
                campaign_budget.delivery_method,
                campaign_budget.period,
                campaign_budget.explicitly_shared,
                campaign_budget.has_recommended_budget,
                metrics.impressions,
                metrics.clicks,
                metrics.ctr,
                metrics.average_cpc,
                metrics.average_cpm,
                metrics.average_cpe,
                metrics.average_cpv,
                metrics.cost_micros,
                metrics.interactions,
                metrics.interaction_rate,
                metrics.all_conversions,
                metrics.all_conversions_value,
                metrics.conversions,
                metrics.conversions_value,
                metrics.cost_per_conversion,
                metrics.cost_per_all_conversions,
                metrics.value_per_conversion,
                metrics.value_per_all_conversions,
                metrics.search_absolute_top_impression_share,
                metrics.search_impression_share,
                metrics.search_rank_lost_impression_share,
                metrics.search_budget_lost_impression_share,
                metrics.video_views,
                metrics.video_quartile_p25_rate,
                metrics.video_quartile_p50_rate,
                metrics.video_quartile_p75_rate,
                metrics.video_quartile_p100_rate,
                metrics.video_view_rate,
                segments.date
            FROM campaign
            WHERE segments.date BETWEEN '{$startDate}' AND '{$endDate}'
            ORDER BY segments.date DESC
        ";
    }

    /**
     * Prepare data for database insertion
     */
    private function prepareData($row)
    {
        $campaign = $row['campaign'] ?? [];
        $campaignBudget = $row['campaignBudget'] ?? [];
        $metrics = $row['metrics'] ?? [];
        $segments = $row['segments'] ?? [];

        // Safely access nested arrays to prevent null pointer errors
        $networkSettings = $campaign['networkSettings'] ?? [];
        $shoppingSetting = $campaign['shoppingSetting'] ?? [];
        $geoTargetTypeSetting = $campaign['geoTargetTypeSetting'] ?? [];
        $manualCpc = $campaign['manualCpc'] ?? [];

        return [
            'campaign_id' => (string) ($campaign['id'] ?? ''),
            'campaign_name' => $campaign['name'] ?? null,
            'campaign_status' => $campaign['status'] ?? null,
            'campaign_primary_status' => $campaign['primaryStatus'] ?? null,
            'campaign_primary_status_reasons' => is_array($campaign['primaryStatusReasons'] ?? null) 
                ? implode(', ', $campaign['primaryStatusReasons']) 
                : ($campaign['primaryStatusReasons'] ?? null),
            'campaign_serving_status' => $campaign['servingStatus'] ?? null,
            'advertising_channel_type' => $campaign['advertisingChannelType'] ?? null,
            'experiment_type' => $campaign['experimentType'] ?? null,
            'bidding_strategy_type' => $campaign['biddingStrategyType'] ?? null,
            'payment_mode' => $campaign['paymentMode'] ?? null,
            'start_date' => $campaign['startDate'] ?? null,
            'end_date' => $campaign['endDate'] ?? null,
            
            // Network Settings - Fixed: Safe nested array access
            'target_google_search' => (bool) ($networkSettings['targetGoogleSearch'] ?? false),
            'target_search_network' => (bool) ($networkSettings['targetSearchNetwork'] ?? false),
            'target_content_network' => (bool) ($networkSettings['targetContentNetwork'] ?? false),
            'target_partner_search_network' => (bool) ($networkSettings['targetPartnerSearchNetwork'] ?? false),
            
            // Shopping Settings - Fixed: Safe nested array access
            'shopping_merchant_id' => $shoppingSetting['merchantId'] ?? null,
            'shopping_feed_label' => $shoppingSetting['feedLabel'] ?? null,
            'shopping_campaign_priority' => $shoppingSetting['campaignPriority'] ?? null,
            
            // Geo Target Settings - Fixed: Safe nested array access
            'positive_geo_target_type' => $geoTargetTypeSetting['positiveGeoTargetType'] ?? null,
            'negative_geo_target_type' => $geoTargetTypeSetting['negativeGeoTargetType'] ?? null,
            
            // Manual CPC - Fixed: Safe nested array access
            'manual_cpc_enhanced_enabled' => (bool) ($manualCpc['enhancedCpcEnabled'] ?? false),
            
            // Budget Information
            'budget_id' => (string) ($campaignBudget['id'] ?? ''),
            'budget_name' => $campaignBudget['name'] ?? null,
            'budget_status' => $campaignBudget['status'] ?? null,
            'budget_amount_micros' => $campaignBudget['amountMicros'] ?? null,
            'budget_total_amount_micros' => $campaignBudget['totalAmountMicros'] ?? null,
            'budget_delivery_method' => $campaignBudget['deliveryMethod'] ?? null,
            'budget_period' => $campaignBudget['period'] ?? null,
            'budget_explicitly_shared' => (bool) ($campaignBudget['explicitlyShared'] ?? false),
            'budget_has_recommended_budget' => (bool) ($campaignBudget['hasRecommendedBudget'] ?? false),
            
            // Metrics
            'metrics_impressions' => $metrics['impressions'] ?? 0,
            'metrics_clicks' => $metrics['clicks'] ?? 0,
            'metrics_ctr' => $metrics['ctr'] ?? 0,
            'metrics_average_cpc' => isset($metrics['averageCpc']) ? $metrics['averageCpc'] / 1000000 : 0,
            'metrics_average_cpm' => isset($metrics['averageCpm']) ? $metrics['averageCpm'] / 1000000 : 0,
            'metrics_average_cpe' => isset($metrics['averageCpe']) ? $metrics['averageCpe'] / 1000000 : 0,
            'metrics_average_cpv' => isset($metrics['averageCpv']) ? $metrics['averageCpv'] / 1000000 : 0,
            'metrics_cost_micros' => $metrics['costMicros'] ?? 0,
            'metrics_interactions' => $metrics['interactions'] ?? 0,
            'metrics_interaction_rate' => $metrics['interactionRate'] ?? 0,
            'metrics_all_conversions' => $metrics['allConversions'] ?? 0,
            'metrics_all_conversions_value' => $metrics['allConversionsValue'] ?? 0,
            'metrics_conversions' => $metrics['conversions'] ?? 0,
            'metrics_conversions_value' => $metrics['conversionsValue'] ?? 0,
            'metrics_cost_per_conversion' => isset($metrics['costPerConversion']) ? $metrics['costPerConversion'] / 1000000 : 0,
            'metrics_cost_per_all_conversions' => isset($metrics['costPerAllConversions']) ? $metrics['costPerAllConversions'] / 1000000 : 0,
            'metrics_value_per_conversion' => $metrics['valuePerConversion'] ?? 0,
            'metrics_value_per_all_conversions' => $metrics['valuePerAllConversions'] ?? 0,
            'metrics_search_absolute_top_impression_share' => $metrics['searchAbsoluteTopImpressionShare'] ?? 0,
            'metrics_search_impression_share' => $metrics['searchImpressionShare'] ?? 0,
            'metrics_search_rank_lost_impression_share' => $metrics['searchRankLostImpressionShare'] ?? 0,
            'metrics_search_budget_lost_impression_share' => $metrics['searchBudgetLostImpressionShare'] ?? 0,
            'metrics_video_views' => $metrics['videoViews'] ?? 0,
            'metrics_video_quartile_p25_rate' => $metrics['videoQuartileP25Rate'] ?? 0,
            'metrics_video_quartile_p50_rate' => $metrics['videoQuartileP50Rate'] ?? 0,
            'metrics_video_quartile_p75_rate' => $metrics['videoQuartileP75Rate'] ?? 0,
            'metrics_video_quartile_p100_rate' => $metrics['videoQuartileP100Rate'] ?? 0,
            'metrics_video_view_rate' => $metrics['videoViewRate'] ?? 0,
            
            // GA4 Metrics - Note: These may need to come from GA4 API separately
            // Google Ads API doesn't directly provide GA4 metrics, they need separate integration
            'ga4_sold_units' => 0, // TODO: Fetch from GA4 API or Google Ads conversion tracking
            'ga4_ad_sales' => 0,   // TODO: Fetch from GA4 API or Google Ads conversion tracking
            
            // Date
            'date' => $segments['date'] ?? Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * Prepare data from campaign object (for zero-metrics campaigns)
     */
    private function prepareDataFromCampaign($campaignData, $date)
    {
        $campaign = $campaignData['campaign'] ?? [];
        $campaignBudget = $campaignData['campaignBudget'] ?? [];

        // Safely access nested arrays
        $networkSettings = $campaign['networkSettings'] ?? [];
        $shoppingSetting = $campaign['shoppingSetting'] ?? [];
        $geoTargetTypeSetting = $campaign['geoTargetTypeSetting'] ?? [];
        $manualCpc = $campaign['manualCpc'] ?? [];

        return [
            'campaign_id' => (string) ($campaign['id'] ?? ''),
            'campaign_name' => $campaign['name'] ?? null,
            'campaign_status' => $campaign['status'] ?? null,
            'campaign_primary_status' => $campaign['primaryStatus'] ?? null,
            'campaign_primary_status_reasons' => is_array($campaign['primaryStatusReasons'] ?? null) 
                ? implode(', ', $campaign['primaryStatusReasons']) 
                : ($campaign['primaryStatusReasons'] ?? null),
            'campaign_serving_status' => $campaign['servingStatus'] ?? null,
            'advertising_channel_type' => $campaign['advertisingChannelType'] ?? null,
            'experiment_type' => $campaign['experimentType'] ?? null,
            'bidding_strategy_type' => $campaign['biddingStrategyType'] ?? null,
            'payment_mode' => $campaign['paymentMode'] ?? null,
            'start_date' => $campaign['startDate'] ?? null,
            'end_date' => $campaign['endDate'] ?? null,
            
            // Network Settings
            'target_google_search' => (bool) ($networkSettings['targetGoogleSearch'] ?? false),
            'target_search_network' => (bool) ($networkSettings['targetSearchNetwork'] ?? false),
            'target_content_network' => (bool) ($networkSettings['targetContentNetwork'] ?? false),
            'target_partner_search_network' => (bool) ($networkSettings['targetPartnerSearchNetwork'] ?? false),
            
            // Shopping Settings
            'shopping_merchant_id' => $shoppingSetting['merchantId'] ?? null,
            'shopping_feed_label' => $shoppingSetting['feedLabel'] ?? null,
            'shopping_campaign_priority' => $shoppingSetting['campaignPriority'] ?? null,
            
            // Geo Target Settings
            'positive_geo_target_type' => $geoTargetTypeSetting['positiveGeoTargetType'] ?? null,
            'negative_geo_target_type' => $geoTargetTypeSetting['negativeGeoTargetType'] ?? null,
            
            // Manual CPC
            'manual_cpc_enhanced_enabled' => (bool) ($manualCpc['enhancedCpcEnabled'] ?? false),
            
            // Budget Information
            'budget_id' => (string) ($campaignBudget['id'] ?? ''),
            'budget_name' => $campaignBudget['name'] ?? null,
            'budget_status' => $campaignBudget['status'] ?? null,
            'budget_amount_micros' => $campaignBudget['amountMicros'] ?? null,
            'budget_total_amount_micros' => $campaignBudget['totalAmountMicros'] ?? null,
            'budget_delivery_method' => $campaignBudget['deliveryMethod'] ?? null,
            'budget_period' => $campaignBudget['period'] ?? null,
            'budget_explicitly_shared' => (bool) ($campaignBudget['explicitlyShared'] ?? false),
            'budget_has_recommended_budget' => (bool) ($campaignBudget['hasRecommendedBudget'] ?? false),
            
            // All metrics set to zero (no activity)
            'metrics_impressions' => 0,
            'metrics_clicks' => 0,
            'metrics_ctr' => 0,
            'metrics_average_cpc' => 0,
            'metrics_average_cpm' => 0,
            'metrics_average_cpe' => 0,
            'metrics_average_cpv' => 0,
            'metrics_cost_micros' => 0,
            'metrics_interactions' => 0,
            'metrics_interaction_rate' => 0,
            'metrics_all_conversions' => 0,
            'metrics_all_conversions_value' => 0,
            'metrics_conversions' => 0,
            'metrics_conversions_value' => 0,
            'metrics_cost_per_conversion' => 0,
            'metrics_cost_per_all_conversions' => 0,
            'metrics_value_per_conversion' => 0,
            'metrics_value_per_all_conversions' => 0,
            'metrics_search_absolute_top_impression_share' => 0,
            'metrics_search_impression_share' => 0,
            'metrics_search_rank_lost_impression_share' => 0,
            'metrics_search_budget_lost_impression_share' => 0,
            'metrics_video_views' => 0,
            'metrics_video_quartile_p25_rate' => 0,
            'metrics_video_quartile_p50_rate' => 0,
            'metrics_video_quartile_p75_rate' => 0,
            'metrics_video_quartile_p100_rate' => 0,
            'metrics_video_view_rate' => 0,
            
            // GA4 Metrics
            'ga4_sold_units' => 0,
            'ga4_ad_sales' => 0,
            
            // Date
            'date' => $date,
        ];
    }
}
