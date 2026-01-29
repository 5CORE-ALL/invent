<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;
use App\Models\ShopifySku;

class UpdateSBIDCronCommand extends Command
{
    protected $signature = 'sbid:update {--dry-run : Run without applying changes (test only)}';
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
            @ini_set('memory_limit', '512M');

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

            $dryRun = $this->option('dry-run');
            if ($dryRun) {
                $this->warn('DRY RUN — no actual updates will be made.');
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
                DB::connection()->disconnect();
                return 0;
            }

            // Get all SKUs to fetch Shopify inventory data
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->toArray();

            if (empty($skus)) {
                $this->warn("No valid SKUs found!");
                DB::connection()->disconnect();
                return 0;
            }

            $shopifyData = [];
            if (!empty($skus)) {
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            }
            DB::connection()->disconnect();

        $this->info("Found " . $productMasters->count() . " product masters");

        // Stream SHOPPING campaigns (L30 range) and aggregate L1/L7 in memory to avoid OOM
        $l30Start = $today->copy()->subDays($endDateDaysBack + 29)->format('Y-m-d');
        $l1Start = $dateRanges['L1']['start'];
        $l1End = $dateRanges['L1']['end'];
        $l7Start = $dateRanges['L7']['start'];
        $l7End = $dateRanges['L7']['end'];

        $campaignMetrics = [];
        $cursor = DB::table('google_ads_campaigns')
            ->select('campaign_id', 'campaign_name', 'campaign_status', 'budget_amount_micros', 'date', 'metrics_cost_micros', 'metrics_clicks')
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', 'ENABLED')
            ->whereBetween('date', [$l30Start, $dateRanges['L7']['end']])
            ->orderBy('campaign_id')
            ->orderBy('date')
            ->cursor();

        foreach ($cursor as $c) {
            $cid = $c->campaign_id;
            $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string) $c->date);

            if (!isset($campaignMetrics[$cid])) {
                $campaignMetrics[$cid] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $c->campaign_name,
                    'campaign_status' => $c->campaign_status,
                    'budget_amount_micros' => $c->budget_amount_micros,
                    'budget_date' => $campaignDate,
                    'spend_L1' => 0,
                    'clicks_L1' => 0,
                    'spend_L7' => 0,
                    'clicks_L7' => 0,
                ];
            }
            $m = &$campaignMetrics[$cid];
            if ($campaignDate >= $m['budget_date']) {
                $m['budget_amount_micros'] = $c->budget_amount_micros;
                $m['budget_date'] = $campaignDate;
            }
            if ($campaignDate >= $l1Start && $campaignDate <= $l1End) {
                $m['spend_L1'] += $c->metrics_cost_micros ?? 0;
                $m['clicks_L1'] += $c->metrics_clicks ?? 0;
            }
            if ($campaignDate >= $l7Start && $campaignDate <= $l7End) {
                $m['spend_L7'] += $c->metrics_cost_micros ?? 0;
                $m['clicks_L7'] += $c->metrics_clicks ?? 0;
            }
            unset($m);
        }

        $this->info("Aggregated " . count($campaignMetrics) . " Google Ads campaigns (L1/L7).");

        $campaignUpdates = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            $shopify = $shopifyData[$pm->sku] ?? null;
            if ($shopify && $shopify->inv <= 0) {
                continue;
            }

            $matched = null;
            foreach ($campaignMetrics as $m) {
                $campaign = strtoupper(trim($m['campaign_name']));
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($sku, $parts) || $campaign === $sku;
                if ($exactMatch && $m['campaign_status'] === 'ENABLED') {
                    $matched = $m;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            $campaignId = $matched['campaign_id'];
            $budget = $matched['budget_amount_micros'] ? $matched['budget_amount_micros'] / 1000000 : null;
            $spend_L1 = $matched['spend_L1'] / 1000000;
            $spend_L7 = $matched['spend_L7'] / 1000000;
            $clicks_L1 = $matched['clicks_L1'];
            $clicks_L7 = $matched['clicks_L7'];
            $row = [
                'campaignBudgetAmount' => $budget,
                'spend_L1' => $spend_L1,
                'clicks_L1' => $clicks_L1,
                'cpc_L1' => $clicks_L1 > 0 ? $spend_L1 / $clicks_L1 : 0,
                'spend_L7' => $spend_L7,
                'clicks_L7' => $clicks_L7,
                'cpc_L7' => $clicks_L7 > 0 ? $spend_L7 / $clicks_L7 : 0,
            ];

            $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($spend_L1 / $budget) * 100 : 0;
            
            // Budget analysis completed

            $sbid = 0;
            $cpc_L1 = isset($row["cpc_L1"]) ? floatval($row["cpc_L1"]) : 0;
            $cpc_L7 = isset($row["cpc_L7"]) ? floatval($row["cpc_L7"]) : 0;

            // Double Rule: PINK+PINK (ub7 > 99 and ub1 > 99) - decrease bid
            if ($ub7 > 99 && $ub1 > 99) {
                // PINK+PINK - decrease bid by 10%
                $sbid = floor($cpc_L1 * 0.90 * 100) / 100;
                // PINK+PINK - decreasing SBID
            }
            // Double Rule: RED+RED (ub7 < 66 and ub1 < 66) - increase bid
            elseif ($ub7 < 66 && $ub1 < 66) {
                // RED+RED - increase bid by 10%
                if ($cpc_L1 === 0.0 && $cpc_L7 === 0.0) {
                    $sbid = 0.75;
                } elseif ($cpc_L1 > 0) {
                    $sbid = floor($cpc_L1 * 1.10 * 100) / 100;
                } else {
                    $sbid = floor($cpc_L7 * 1.10 * 100) / 100;
                }
                // RED+RED - increasing SBID
            } else {
                // Not PINK+PINK or RED+RED - no update needed
                continue;
            }
            
            if ($sbid > 0 && !isset($campaignUpdates[$campaignId])) {
                // Determine action type for logging
                if ($ub7 > 99 && $ub1 > 99) {
                    $action = "PINK+PINK (decreased)";
                } else {
                    $action = "RED+RED (increased)";
                }

                if ($dryRun) {
                    $this->info("[DRY RUN] Would update campaign {$campaignId} (SKU: {$pm->sku}): L1CPC=\${$cpc_L1}, L7CPC=\${$cpc_L7}, SBID=\${$sbid}, UB7={$ub7}%, UB1={$ub1}%, Action: {$action}");
                    $campaignUpdates[$campaignId] = true;
                } else {
                    try {
                        $this->sbidService->updateCampaignSbids($customerId, $campaignId, $sbid);
                        $campaignUpdates[$campaignId] = true;
                        $this->info("Updated campaign {$campaignId} (SKU: {$pm->sku}): L1CPC=\${$cpc_L1}, L7CPC=\${$cpc_L7}, SBID=\${$sbid}, UB7={$ub7}%, UB1={$ub1}%, Action: {$action}");
                    } catch (\Exception $e) {
                        $this->error("Failed to update campaign {$campaignId}: " . $e->getMessage());
                    }
                }
            }
        }

            $processedCount = count($campaignUpdates);
            if ($dryRun) {
                $this->info("Done. Would have processed: {$processedCount} unique campaigns (dry run).");
            } else {
                $this->info("Done. Processed: {$processedCount} unique campaigns.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }
}
