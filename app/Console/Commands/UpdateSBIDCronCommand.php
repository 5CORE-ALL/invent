<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $this->info('Starting SBID update cron for Google campaigns (L1/L7 with SKU matching)...');

        $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');
        $this->info("Customer ID: {$customerId}");

        // Calculate date ranges
        $today = now();
        $dateRanges = [
            'L1' => [
                'start' => $today->copy()->subDay(1)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
            ],
            'L7' => [
                'start' => $today->copy()->subDays(7)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
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

        // Fetch campaigns data within L7 range
        $googleCampaigns = GoogleAdsCampaign::select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks'
            )
            ->where('campaign_status', 'ENABLED')
            ->whereBetween('date', [$dateRanges['L7']['start'], $dateRanges['L7']['end']])
            ->get();

        $this->info("Found " . $googleCampaigns->count() . " Google Ads campaigns in L7 range");

        $ranges = ['L1', 'L7']; 

        $campaignUpdates = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            // Check inventory - skip if zero inventory
            $shopify = $shopifyData[$sku] ?? null;
            if ($shopify && $shopify->inv <= 0) {
                $this->line("Skipping SKU {$sku} - Zero inventory (inv: {$shopify->inv})");
                continue;
            }

            // Find the latest campaign for this SKU - use exact match
            $matchedCampaign = $googleCampaigns->filter(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                return $campaign === $sku && $c->campaign_status === 'ENABLED';
            })->sortByDesc('date')->first();

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
                    $matchesCampaign = $campaign === $sku; // Use exact match here too
                    $matchesStatus = $c->campaign_status === 'ENABLED';
                    $matchesDate = $c->date >= $dateRanges[$rangeName]['start'] && $c->date <= $dateRanges[$rangeName]['end'];
                    
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

            if ($ub7 > 90) {
                // Over Utilized - decrease bid
                if ($cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } else {
                    $sbid = floor($cpc_L7 * 0.90 * 100) / 100;
                }
                // Over utilized - decreasing SBID
            } elseif ($ub7 < 70) {
                // Under Utilized - increase bid
                if ($cpc_L1 === 0.0 && $cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } else if ($cpc_L1 > $cpc_L7) {
                    $sbid = floor($cpc_L1 * 1.10 * 100) / 100;
                } else {
                    $sbid = floor($cpc_L7 * 1.10 * 100) / 100;
                }
                // Under utilized - increasing SBID
            } else {
                // Budget utilization within target range - no update needed
                continue;
            }
            
            if ($sbid > 0 && !isset($campaignUpdates[$campaignId])) {
                try {
                    $this->sbidService->updateCampaignSbids($customerId, $campaignId, $sbid);
                    $campaignUpdates[$campaignId] = true;
                    $this->info("Updated campaign {$campaignId} (SKU: {$pm->sku}): SBID=\${$sbid}, UB7={$ub7}%");
                } catch (\Exception $e) {
                    $this->error("Failed to update campaign {$campaignId}: " . $e->getMessage());
                    Log::error("SBID Update Failed", [
                        'campaign_id' => $campaignId,
                        'sku' => $pm->sku,
                        'sbid' => $sbid,
                        'ub7' => $ub7,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $processedCount = count($campaignUpdates);
        $this->info("Done. Processed: {$processedCount} unique campaigns.");
        Log::info('SBID Cron Run', ['processed' => $processedCount]);

        return 0;
    }
}
