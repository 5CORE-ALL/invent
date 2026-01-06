<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;
use App\Models\GoogleAdsCampaign;
use App\Models\ShopifySku;

class UpdateSBIDCronCommand extends Command
{
    protected $signature = 'sbid:update';
    protected $description = 'Update SBID for AdGroups and Product Groups using L1 range only';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

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

            $this->info('Starting SBID update cron for Google campaigns (L1/L7 with SKU matching)...');

            $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');
            $this->info("Customer ID: {$customerId}");

        // Calculate date ranges - same logic as GoogleAdsDateRangeTrait
        // Google Ads data is fetched daily at 12 PM via cron
        // If it's before 12 PM, yesterday's data won't be available yet
        $today = now();
        $currentHour = (int) $today->format('H');
        $endDateDaysBack = ($currentHour < 12) ? 2 : 1; // Use 2 days ago if before 12 PM, otherwise yesterday
        $l1DaysBack = $endDateDaysBack;
        $endDate = $today->copy()->subDays($endDateDaysBack)->format('Y-m-d');
        
        $dateRanges = [
            'L1' => [
                'start' => $today->copy()->subDays($l1DaysBack)->format('Y-m-d'),
                'end' => $today->copy()->subDays($l1DaysBack)->format('Y-m-d')
            ],
            'L7' => [
                // L7 = last 7 days including end date (end date - 6 days = 7 days total)
                'start' => $today->copy()->subDays($endDateDaysBack + 6)->format('Y-m-d'),
                'end' => $endDate
            ],
        ];

        $this->info("Date ranges - L1: {$dateRanges['L1']['start']} to {$dateRanges['L1']['end']}");
        $this->info("Date ranges - L7: {$dateRanges['L7']['start']} to {$dateRanges['L7']['end']}");

            // Fetch product masters
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found!");
                DB::disconnect();
                return 0;
            }

            // Get all SKUs to fetch Shopify inventory data
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->toArray();

            if (empty($skus)) {
                $this->warn("No valid SKUs found!");
                DB::disconnect();
                return 0;
            }

            $shopifyData = [];
            if (!empty($skus)) {
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            }
            DB::disconnect();

        $this->info("Found " . $productMasters->count() . " product masters");

        // Fetch SHOPPING campaigns data within L30 range (same as getGoogleShoppingAdsData)
        // We fetch L30 data and then aggregate L7 from it to match controller logic
        // Note: SEARCH campaigns should not be updated via this command
        $l30Start = $today->copy()->subDays($endDateDaysBack + 29)->format('Y-m-d');
        $googleCampaigns = GoogleAdsCampaign::select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks'
            )
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', 'ENABLED')
            ->whereBetween('date', [$l30Start, $dateRanges['L7']['end']])
            ->get();

        $this->info("Found " . $googleCampaigns->count() . " Google Ads campaigns in L30 range (for L7 aggregation)");

        $ranges = ['L1', 'L7']; 

        $campaignUpdates = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            // Fixed: Use original SKU for shopifyData lookup (not uppercase)
            $shopify = $shopifyData[$pm->sku] ?? null;
            if ($shopify && $shopify->inv <= 0) {
                continue;
            }

            // Fixed: Use improved matching logic (same as GoogleAdsController)
            // Check if SKU is in comma-separated list OR campaign name exactly equals SKU
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign name exactly equals SKU
                if (!$exactMatch) {
                    $exactMatch = $campaign === $skuTrimmed;
                }
                
                return $exactMatch && $c->campaign_status === 'ENABLED';
            });

            if (!$matchedCampaign) {
                continue;
            }

            $campaignId = $matchedCampaign->campaign_id;
            $row = [];
            // Get budget from the latest campaign record for this campaign_id (to ensure consistency)
            // Budget should be same across dates, but get latest to be safe
            $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)
                ->sortByDesc('date')
                ->first();
            $row['campaignBudgetAmount'] = $latestCampaign && $latestCampaign->budget_amount_micros 
                ? $latestCampaign->budget_amount_micros / 1000000 
                : null;

            // Aggregate metrics for each date range using same logic as aggregateMetricsByRange
            foreach ($ranges as $rangeName) {
                $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRanges, $rangeName) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    // Use same matching logic as aggregateMetricsByRange (for SHOPPING campaigns)
                    $parts = array_map('trim', explode(',', $campaign));
                    $exactMatch = in_array($skuTrimmed, $parts);
                    
                    // If not in list, check if campaign name exactly equals SKU
                    if (!$exactMatch) {
                        $exactMatch = $campaign === $skuTrimmed;
                    }
                    
                    $matchesCampaign = $exactMatch;
                    $matchesStatus = $c->campaign_status === 'ENABLED';
                    
                    // Fixed: Handle both string and Carbon date instances for proper comparison
                    $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string)$c->date);
                    $matchesDate = $campaignDate >= $dateRanges[$rangeName]['start'] && $campaignDate <= $dateRanges[$rangeName]['end'];
                    
                    return $matchesCampaign && $matchesStatus && $matchesDate;
                });

                $totalCost = $campaignRanges->sum('metrics_cost_micros');
                $totalClicks = $campaignRanges->sum('metrics_clicks');
                
                // Same calculation as aggregateMetricsByRange
                $row["spend_$rangeName"] = $totalCost / 1000000;
                $row["clicks_$rangeName"] = $totalClicks;
                $row["cpc_$rangeName"] = $totalClicks > 0 ? ($totalCost / 1000000) / $totalClicks : 0;
            }
    
            $ub7 = $row['campaignBudgetAmount'] > 0 ? ($row["spend_L7"] / ($row['campaignBudgetAmount'] * 7)) * 100 : 0;
            
            // Budget analysis completed

            $sbid = 0;
            $cpc_L1 = isset($row["cpc_L1"]) ? floatval($row["cpc_L1"]) : 0;
            $cpc_L7 = isset($row["cpc_L7"]) ? floatval($row["cpc_L7"]) : 0;

            if ($ub7 > 90) {
                // Over Utilized (UB7 > 90%) - decrease bid by 10%
                if ($cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } else {
                    $sbid = floor($cpc_L7 * 0.90 * 100) / 100;
                }
                // Over utilized - decreasing SBID
            } elseif ($ub7 < 70) {
                // Under Utilized (UB7 < 70%) - increase bid by 10%
                if ($cpc_L1 === 0.0 && $cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } else {
                    $sbid = floor($cpc_L7 * 1.10 * 100) / 100;
                }
                // Under utilized - increasing SBID
            } else {
                // Budget utilization within target range (70% <= UB7 <= 90%) - no update needed
                continue;
            }
            
            if ($sbid > 0 && !isset($campaignUpdates[$campaignId])) {
                try {
                    $this->sbidService->updateCampaignSbids($customerId, $campaignId, $sbid);
                    $campaignUpdates[$campaignId] = true;
                    $action = $ub7 > 90 ? "OVER-UTILIZED (decreased)" : "UNDER-UTILIZED (increased)";
                    $this->info("Updated campaign {$campaignId} (SKU: {$pm->sku}): SBID=\${$sbid}, UB7={$ub7}%, Action: {$action}");
                } catch (\Exception $e) {
                    $this->error("Failed to update campaign {$campaignId}: " . $e->getMessage());
                }
            }
        }

            $processedCount = count($campaignUpdates);
            $this->info("Done. Processed: {$processedCount} unique campaigns.");

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::disconnect();
        }
    }
}
