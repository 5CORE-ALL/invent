<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsCampaign;
use App\Services\GoogleAdsSbidService;
use App\Services\GA4ApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
    protected $ga4Service;

    public function __construct(GoogleAdsSbidService $googleAdsService, GA4ApiService $ga4Service)
    {
        parent::__construct();
        $this->googleAdsService = $googleAdsService;
        $this->ga4Service = $ga4Service;
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
            $this->info("Step 1: Fetching all active + paused campaigns...");
            $allActiveCampaigns = $this->fetchAllActiveCampaigns($customerId);
            $this->info("Found " . count($allActiveCampaigns) . " campaigns (ENABLED + PAUSED)");

            // Step 2: Fetch metrics in 7-day chunks to avoid memory exhaustion (30 days × 400+ campaigns = OOM)
            $this->info("Step 2: Fetching metrics for date range (in 7-day chunks)...");
            $insertedCount = 0;
            $updatedCount = 0;
            $processedCampaignIds = [];
            $campaignCurrentStatuses = [];
            $chunkDays = 7;
            $cursor = Carbon::parse($startDate);
            $endDateObj = Carbon::parse($endDate);

            while ($cursor->lte($endDateObj)) {
                $chunkStart = $cursor->format('Y-m-d');
                $chunkEnd = $cursor->copy()->addDays($chunkDays - 1);
                if ($chunkEnd->gt($endDateObj)) {
                    $chunkEnd = $endDateObj->copy();
                }
                $chunkEndStr = $chunkEnd->format('Y-m-d');

                $query = $this->buildQuery($chunkStart, $chunkEndStr);
                $results = $this->googleAdsService->runQuery($customerId, $query);
                $this->info("  Chunk {$chunkStart} to {$chunkEndStr}: " . count($results) . " records");

                foreach ($results as $row) {
                    try {
                        $data = $this->prepareData($row);
                        $processedCampaignIds[$data['campaign_id']] = true;

                        // Collect current status for each campaign (for Step 4b status sync)
                        if (!isset($campaignCurrentStatuses[$data['campaign_id']])) {
                            $campaignCurrentStatuses[$data['campaign_id']] = [
                                'campaign_status' => $data['campaign_status'] ?? null,
                                'campaign_primary_status' => $data['campaign_primary_status'] ?? null,
                                'campaign_primary_status_reasons' => $data['campaign_primary_status_reasons'] ?? null,
                                'campaign_serving_status' => $data['campaign_serving_status'] ?? null,
                            ];
                        }

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
                            'trace' => $e->getTraceAsString()
                        ]);
                        $this->error('Error processing record: ' . $e->getMessage());
                    }
                }

                unset($results);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                $cursor->addDays($chunkDays);
            }

            $this->info("Found " . ($insertedCount + $updatedCount) . " records with metrics (inserted: {$insertedCount}, updated: {$updatedCount})");

            // Step 4: For ENABLED campaigns without metrics, create records with zero metrics
            // (Skip PAUSED campaigns — they're only needed for Step 4b status sync, not zero-metric rows)
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

                // Only create zero-metric rows for ENABLED campaigns (not PAUSED)
                $campaignStatus = $campaign['status'] ?? null;
                if ($campaignStatus !== 'ENABLED') {
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

            // Step 4b: Sync campaign_status across ALL historical rows for each campaign.
            // The daily cron (--days=1) only updates yesterday's row, but Google Ads dashboard
            // shows spend by CURRENT campaign status. So we need all rows for a campaign to
            // reflect its current status, otherwise the spend total drifts over time.
            $this->info("");
            $this->info("Step 4b: Syncing campaign status across all historical rows...");
            $statusSyncCount = 0;

            // Also include active campaigns (Step 1) that might not have had metrics
            foreach ($allActiveCampaigns as $campaignData) {
                $campaign = $campaignData['campaign'] ?? [];
                $campaignId = (string) ($campaign['id'] ?? '');
                if (!empty($campaignId) && !isset($campaignCurrentStatuses[$campaignId])) {
                    $campaignCurrentStatuses[$campaignId] = [
                        'campaign_status' => $campaign['status'] ?? null,
                        'campaign_primary_status' => $campaign['primaryStatus'] ?? null,
                        'campaign_primary_status_reasons' => is_array($campaign['primaryStatusReasons'] ?? null)
                            ? implode(', ', $campaign['primaryStatusReasons'])
                            : ($campaign['primaryStatusReasons'] ?? null),
                        'campaign_serving_status' => $campaign['servingStatus'] ?? null,
                    ];
                }
            }

            // Update all historical rows for each campaign with current status
            foreach ($campaignCurrentStatuses as $campaignId => $statusData) {
                $updated = DB::table('google_ads_campaigns')
                    ->where('campaign_id', $campaignId)
                    ->where(function ($query) use ($statusData) {
                        // Only update rows that have a different status (avoid unnecessary writes)
                        // Also handle NULL values: NULL != 'ENABLED' is NULL in MySQL, not true
                        foreach ($statusData as $field => $value) {
                            if ($value === null) {
                                $query->orWhereNotNull($field);
                            } else {
                                $query->orWhere($field, '!=', $value)
                                      ->orWhereNull($field);
                            }
                        }
                    })
                    ->update(array_merge($statusData, ['updated_at' => now()]));
                $statusSyncCount += $updated;
            }
            $this->info("Synced status for " . count($campaignCurrentStatuses) . " campaigns, updated {$statusSyncCount} historical rows");

            // Step 5: Fetch GA4 actual data for the same date range
            $this->info("");
            $this->info("Step 5: Fetching GA4 actual data...");
            $this->fetchGA4Data($startDate, $endDate);

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
     * Fetch all active + paused campaigns (without metrics filter).
     * Includes PAUSED so Step 4b can sync status across all historical rows
     * even when a recently-paused campaign has 0 metrics on the cron date.
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
            WHERE campaign.status IN ('ENABLED', 'PAUSED')
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
            
            // ga4_sold_units: Use conversions (primary=Purchase) to align with GA4 "Key events purchase"
            // allConversions = add-to-cart, begin-checkout, purchase, etc. — too high vs GA4
            // conversions = primary conversion (Purchase) — closer to GA4 Key events purchase (e.g. 97)
            'ga4_sold_units' => $metrics['conversions'] ?? 0,
            // ga4_ad_sales: Use conversionsValue (primary conversion value) to align with conversions count
            // Note: This is Google Ads conversion value (set in conversion action), NOT actual GA4 e-commerce revenue
            // GA4 "Total revenue" uses actual purchase amounts from e-commerce tracking, which may differ
            // conversionsValue = value of primary conversion (Purchase) — matches conversions count
            // allConversionsValue = includes all conversion actions (add-to-cart, checkout, etc.) — too high
            'ga4_ad_sales' => $metrics['conversionsValue'] ?? 0,
            
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

    /**
     * Fetch GA4 actual data and update database
     */
    private function fetchGA4Data($startDate, $endDate)
    {
        try {
            // Check if GA4 API is configured
            if (empty(env('GA4_PROPERTY_ID')) || empty(env('GA4_CLIENT_ID')) || 
                empty(env('GA4_CLIENT_SECRET')) || empty(env('GA4_REFRESH_TOKEN'))) {
                $this->warn('GA4 API not configured. Skipping GA4 data fetch.');
                $this->info('To enable GA4 data, configure GA4_PROPERTY_ID, GA4_CLIENT_ID, GA4_CLIENT_SECRET, GA4_REFRESH_TOKEN in .env');
                return;
            }

            $this->info("Fetching GA4 data for date range: {$startDate} to {$endDate}");

            // Fetch daily GA4 data
            $ga4DailyData = $this->ga4Service->getCampaignMetricsDaily($startDate, $endDate);

            if (empty($ga4DailyData)) {
                $this->warn('No GA4 data returned. Check API credentials and property ID.');
                return;
            }

            $this->info('Found ' . count($ga4DailyData) . ' campaigns in GA4');

            $updated = 0;
            $notFound = 0;
            $totalRecords = 0;

            // Update database with GA4 daily data
            foreach ($ga4DailyData as $campaignName => $dailyRecords) {
                $campaignNameUpper = strtoupper(trim($campaignName));
                $campaignNameClean = trim($campaignName);
                
                // Find matching campaign in database - try multiple matching strategies
                $dbCampaign = DB::table('google_ads_campaigns')
                    ->where('advertising_channel_type', 'SHOPPING')
                    ->where(function($query) use ($campaignNameUpper, $campaignNameClean) {
                        // Exact match (case-insensitive, trimmed)
                        $query->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameUpper])
                              // Partial match from GA4 name
                              ->orWhere('campaign_name', 'LIKE', '%' . $campaignNameClean . '%')
                              // Reverse partial match (database name contains GA4 name)
                              ->orWhereRaw('UPPER(TRIM(campaign_name)) LIKE ?', ['%' . $campaignNameUpper . '%']);
                    })
                    ->select('campaign_id', 'campaign_name')
                    ->distinct()
                    ->first();

                if (!$dbCampaign) {
                    $notFound++;
                    $this->warn("  Not found in DB: {$campaignName}");
                    continue;
                }

                // Update each day's data
                foreach ($dailyRecords as $date => $metrics) {
                    $updatedCount = DB::table('google_ads_campaigns')
                        ->where('campaign_id', $dbCampaign->campaign_id)
                        ->where('date', $date)
                        ->where('advertising_channel_type', 'SHOPPING')
                        ->update([
                            'ga4_actual_sold_units' => $metrics['purchases'],
                            'ga4_actual_revenue' => $metrics['revenue'],
                        ]);

                    if ($updatedCount > 0) {
                        $totalRecords += $updatedCount;
                    }
                }
                
                $totalPurchases = array_sum(array_column($dailyRecords, 'purchases'));
                $totalRevenue = array_sum(array_column($dailyRecords, 'revenue'));
                $updated++;
                $this->info("  Updated: {$dbCampaign->campaign_name} - Purchases: {$totalPurchases}, Revenue: \${$totalRevenue}");
            }

            $this->info("GA4 data update completed: {$updated} campaigns, {$totalRecords} records updated");
            if ($notFound > 0) {
                $this->warn("  {$notFound} GA4 campaigns not found in database");
            }

        } catch (\Exception $e) {
            $this->error("Error fetching GA4 data: " . $e->getMessage());
            Log::error('Error fetching GA4 data in FetchGoogleAdsCampaigns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
