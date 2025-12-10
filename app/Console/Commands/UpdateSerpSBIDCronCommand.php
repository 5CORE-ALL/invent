<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;
use App\Models\GoogleAdsCampaign;
use App\Models\ShopifySku;

class UpdateSerpSBIDCronCommand extends Command
{
    protected $signature = 'sbid:update-serp';
    protected $description = 'Update SBID for SERP (SEARCH) campaigns using L1/L7 ranges with same rules as SHOPPING';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

    public function handle()
    {
        $this->info('Starting SBID update cron for SERP (SEARCH) campaigns (L1/L7 with SKU matching)...');

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

        // Get all SKUs to fetch Shopify inventory data
        $skus = $productMasters->pluck('sku')->toArray();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $this->info("Found " . $productMasters->count() . " product masters");

        // Fetch SEARCH campaigns data within L7 range only
        // Note: SHOPPING campaigns should not be updated via this command
        $googleCampaigns = GoogleAdsCampaign::select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks'
            )
            ->where('advertising_channel_type', 'SEARCH')
            ->where('campaign_status', 'ENABLED')
            ->whereBetween('date', [$dateRanges['L7']['start'], $dateRanges['L7']['end']])
            ->get();

        $this->info("Found " . $googleCampaigns->count() . " SERP (SEARCH) campaigns in L7 range");

        $ranges = ['L1', 'L7']; 

        $campaignUpdates = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            // Fixed: Use original SKU for shopifyData lookup (not uppercase)
            $shopify = $shopifyData[$pm->sku] ?? null;
            if ($shopify && $shopify->inv <= 0) {
                $this->line("Skipping SKU {$pm->sku} - Zero inventory (inv: {$shopify->inv})");
                continue;
            }

            // Fixed: Use improved matching logic for SEARCH campaigns
            // SEARCH campaigns end with " SEARCH." so we need special handling
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $skuTrimmed = strtoupper(trim($sku));
                
                // Check if campaign ends with ' SEARCH.'
                if (!str_ends_with($campaign, ' SEARCH.')) {
                    return false;
                }
                
                // Remove ' SEARCH.' suffix for matching
                $campaignBase = str_replace(' SEARCH.', '', $campaign);
                
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaignBase));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign base exactly equals SKU
                if (!$exactMatch) {
                    $exactMatch = $campaignBase === $skuTrimmed;
                }
                
                return $exactMatch && $c->campaign_status === 'ENABLED';
            });

            if (!$matchedCampaign) {
                continue;
            }

            $campaignId = $matchedCampaign->campaign_id;
            $row = [];
            $row['campaignBudgetAmount'] = $matchedCampaign->budget_amount_micros ? $matchedCampaign->budget_amount_micros / 1000000 : null;

            // Aggregate metrics for each date range
            foreach ($ranges as $rangeName) {
                $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRanges, $rangeName) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    // Handle SEARCH campaigns (end with " SEARCH.")
                    $isSearchCampaign = str_ends_with($campaign, ' SEARCH.');
                    if ($isSearchCampaign) {
                        // Remove ' SEARCH.' suffix for matching
                        $campaignBase = str_replace(' SEARCH.', '', $campaign);
                        
                        // Check if SKU is in comma-separated list
                        $parts = array_map('trim', explode(',', $campaignBase));
                        $exactMatch = in_array($skuTrimmed, $parts);
                        
                        // If not in list, check if campaign base exactly equals SKU
                        if (!$exactMatch) {
                            $exactMatch = $campaignBase === $skuTrimmed;
                        }
                    } else {
                        $exactMatch = false;
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
                
                $row["spend_$rangeName"] = $totalCost / 1000000;
                $row["clicks_$rangeName"] = $totalClicks;
                $row["cpc_$rangeName"] = $totalClicks ? ($totalCost / 1000000) / $totalClicks : 0;
            }
    
            $ub7 = $row['campaignBudgetAmount'] > 0 ? ($row["spend_L7"] / ($row['campaignBudgetAmount'] * 7)) * 100 : 0;
            
            // Budget analysis completed

            $sbid = 0;
            $cpc_L1 = isset($row["cpc_L1"]) ? floatval($row["cpc_L1"]) : 0;
            $cpc_L7 = isset($row["cpc_L7"]) ? floatval($row["cpc_L7"]) : 0;

            // SBID Rules (same as SHOPPING):
            // 7UB - 70-90% - NO CHANGE
            // 7UB - MORE THAN 90% - REDUCE BID BY 10%
            // 7UB - LESS THAN 70% - INCREASE BID BY 10%
            
            if ($ub7 > 90) {
                // Over Utilized - decrease bid by 10%
                if ($cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } else {
                    $sbid = floor($cpc_L7 * 0.90 * 100) / 100;
                }
                // Over utilized - decreasing SBID
            } elseif ($ub7 < 70) {
                // Under Utilized - increase bid by 10%
                if ($cpc_L1 === 0.0 && $cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } else if ($cpc_L1 > $cpc_L7) {
                    $sbid = floor($cpc_L1 * 1.10 * 100) / 100;
                } else {
                    $sbid = floor($cpc_L7 * 1.10 * 100) / 100;
                }
                // Under utilized - increasing SBID
            } else {
                // Budget utilization within target range (70-90%) - no update needed
                continue;
            }
            
            if ($sbid > 0 && !isset($campaignUpdates[$campaignId])) {
                try {
                    $this->sbidService->updateCampaignSbids($customerId, $campaignId, $sbid);
                    $campaignUpdates[$campaignId] = true;
                    $this->info("Updated SERP campaign {$campaignId} (SKU: {$pm->sku}): SBID=\${$sbid}, UB7={$ub7}%");
                } catch (\Exception $e) {
                    $this->error("Failed to update SERP campaign {$campaignId}: " . $e->getMessage());
                }
            }
        }

        $processedCount = count($campaignUpdates);
        $this->info("Done. Processed: {$processedCount} unique SERP campaigns.");

        return 0;
    }
}

