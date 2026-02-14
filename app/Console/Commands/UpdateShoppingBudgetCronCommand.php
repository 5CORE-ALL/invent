<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;
use App\Models\GoogleAdsCampaign;
use App\Models\ShopifySku;
use App\Models\GoogleDataView;

class UpdateShoppingBudgetCronCommand extends Command
{
    protected $signature = 'budget:update-shopping {--dry-run : Run without actually updating budgets}';
    protected $description = 'Update budget for SHOPPING campaigns based on ACOS (L30 data)';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

    public function handle()
    {
        try {
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
                $this->warn('⚠️  DRY RUN MODE - No budgets will be updated');
            }
            
            $this->info('Starting budget update cron for SHOPPING campaigns (ACOS-based)...');

            $customerId = config('services.google_ads.login_customer_id');
            $this->info("Customer ID: {$customerId}");

        // Calculate date ranges - same logic as GoogleAdsDateRangeTrait
        $today = now();
        $currentHour = (int) $today->format('H');
        $endDateDaysBack = ($currentHour < 12) ? 2 : 1;
        $endDate = $today->copy()->subDays($endDateDaysBack)->format('Y-m-d');
        
        $dateRanges = [
            'L30' => [
                // L30 = last 30 days including end date (end date - 29 days = 30 days total)
                'start' => $today->copy()->subDays($endDateDaysBack + 29)->format('Y-m-d'),
                'end' => $endDate
            ],
        ];

        $this->info("Date range - L30: {$dateRanges['L30']['start']} to {$dateRanges['L30']['end']}");

            // Fetch product masters (exclude soft deleted, matching frontend logic)
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy('parent', 'asc')
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
            
            // Get NRA values from GoogleDataView (matching frontend logic)
            $nrValues = GoogleDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            
            DB::connection()->disconnect();

        $this->info("Found " . $productMasters->count() . " product masters");

        // Fetch SHOPPING campaigns (ENABLED + PAUSED; exclude only ARCHIVED) so L30
        // aggregation matches the UI. We only *update* campaigns that are ENABLED (see
        // $matchedCampaign and $campaignRanges aggregate below).
        $googleCampaigns = GoogleAdsCampaign::select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_id',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'ga4_ad_sales',
                'ga4_actual_revenue'
            )
            ->where('advertising_channel_type', 'SHOPPING')
            ->where('campaign_status', '!=', 'ARCHIVED')
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        $this->info("Found " . $googleCampaigns->count() . " SHOPPING campaigns in L30 range");

        $campaignUpdates = []; 
        $skipCounters = [
            'zero_inventory' => 0,
            'nra_skip' => 0,
            'no_matching_campaign' => 0,
            'campaign_not_enabled' => 0,
            'no_budget_id' => 0,
            'duplicate_budget' => 0,
            'total_processed' => 0,
            'total_campaigns_processed' => 0 // Count individual campaigns, not just unique budgets
        ];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            // Use original SKU for shopifyData lookup
            $shopify = $shopifyData[$pm->sku] ?? null;
            if ($shopify && $shopify->inv <= 0) {
                $skipCounters['zero_inventory']++;
                continue; // Skip zero inventory
            }
            
            // Check NRA (Not Running Ads) - skip if NRA
            $nra = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                }
            }
            if (!empty($nra) && strtoupper(trim($nra)) === 'NRA') {
                $skipCounters['nra_skip']++;
                continue; // Skip NRA campaigns
            }

            // Use improved matching logic for SHOPPING campaigns (matching frontend logic)
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $campaignCleaned = rtrim(trim($campaign), '.'); // Remove trailing dots like frontend
                $skuTrimmed = strtoupper(trim($sku));
                
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaignCleaned));
                $parts = array_map(function($part) {
                    return rtrim(trim($part), '.'); // Remove trailing dots from each part
                }, $parts);
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign name exactly equals SKU
                if (!$exactMatch) {
                    $exactMatch = $campaignCleaned === $skuTrimmed;
                }
                
                return $exactMatch && $c->campaign_status === 'ENABLED';
            });

            if (!$matchedCampaign) {
                // Check if campaign exists but is not ENABLED (matching frontend logic)
                $anyCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $campaignCleaned = rtrim(trim($campaign), '.'); // Remove trailing dots
                    $skuTrimmed = strtoupper(trim($sku));
                    $parts = array_map('trim', explode(',', $campaignCleaned));
                    $parts = array_map(function($part) {
                        return rtrim(trim($part), '.'); // Remove trailing dots from each part
                    }, $parts);
                    $exactMatch = in_array($skuTrimmed, $parts);
                    if (!$exactMatch) {
                        $exactMatch = $campaignCleaned === $skuTrimmed;
                    }
                    return $exactMatch;
                });
                
                if ($anyCampaign && $anyCampaign->campaign_status !== 'ENABLED') {
                    $skipCounters['campaign_not_enabled']++;
                } else {
                    $skipCounters['no_matching_campaign']++;
                }
                continue;
            }

            $campaignId = $matchedCampaign->campaign_id;
            $budgetId = $matchedCampaign->budget_id;
            
            if (!$budgetId) {
                $skipCounters['no_budget_id']++;
                $this->line("Skipping campaign {$campaignId} (SKU: {$pm->sku}) - No budget ID");
                continue;
            }

            // Aggregate metrics for L30 (include ENABLED + PAUSED so ACOS matches UI/SBGT)
            $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRanges) {
                $campaign = strtoupper(trim($c->campaign_name));
                $campaignCleaned = rtrim(trim($campaign), '.'); // Remove trailing dots
                $skuTrimmed = strtoupper(trim($sku));
                
                $parts = array_map('trim', explode(',', $campaignCleaned));
                $parts = array_map(function($part) {
                    return rtrim(trim($part), '.'); // Remove trailing dots from each part
                }, $parts);
                $exactMatch = in_array($skuTrimmed, $parts);
                
                if (!$exactMatch) {
                    $exactMatch = $campaignCleaned === $skuTrimmed;
                }
                
                $matchesCampaign = $exactMatch;
                // Include all statuses (ENABLED + PAUSED) so ACOS aligns with UI; else
                // ENABLED-only can undercount spend/overcount sales → ACOS < 10% → BGT=5
                // while UI shows ACOS 100% and SBGT 1.
                $matchesStatus = true;
                
                $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string)$c->date);
                $matchesDate = $campaignDate >= $dateRanges['L30']['start'] && $campaignDate <= $dateRanges['L30']['end'];
                
                return $matchesCampaign && $matchesStatus && $matchesDate;
            });

            $totalSpend = $campaignRanges->sum('metrics_cost_micros') / 1000000; // Convert to dollars
            // Use same L30 sales as frontend (aggregateMetricsByRange): prefer GA4 actual revenue when available
            $totalGA4ActualSales = $campaignRanges->sum('ga4_actual_revenue');
            $totalSales = ($totalGA4ActualSales > 0) ? $totalGA4ActualSales : $campaignRanges->sum('ga4_ad_sales');
            
            // Calculate ACOS: (Spend / Sales) * 100
            // If sales = 0 but spend > 0, ACOS should be 100% (matching frontend logic)
            if ($totalSales > 0) {
                $acos = ($totalSpend / $totalSales) * 100;
            } elseif ($totalSpend > 0 && $totalSales == 0) {
                $acos = 100; // When there's spend but no sales, ACOS is 100%
            } else {
                $acos = 0; // No spend and no sales
            }
            
            // Get current budget from latest-by-date row (same as frontend BGT in getGoogleShoppingAdsData)
            $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)->sortByDesc('date')->first();
            $currentBudget = ($latestCampaign && $latestCampaign->budget_amount_micros) ? $latestCampaign->budget_amount_micros / 1000000 : 0;

            // Determine budget (BGT) from ACOS — same buckets as SBGT. When ACOS ≥ 50%
            // (e.g. 100% when Sales=0), BGT = $1 so it aligns with SBGT=1 in the UI.
            // ACOS < 10% → $5 | 10–30% → $4 | 30–40% → $3 | 40–50% → $2 | ≥50% → $1
            $newBudget = 1;
            if ($acos < 10) {
                $newBudget = 5;
            } elseif ($acos >= 10 && $acos < 30) {
                $newBudget = 4;
            } elseif ($acos >= 30 && $acos < 40) {
                $newBudget = 3;
            } elseif ($acos >= 40 && $acos < 50) {
                $newBudget = 2;
            } else {
                $newBudget = 1;
            }
            
            // Track individual campaigns (for statistics)
            $skipCounters['total_campaigns_processed']++;
            
            if (!isset($campaignUpdates[$budgetId])) {
                if ($dryRun) {
                    $campaignUpdates[$budgetId] = true;
                    $skipCounters['total_processed']++;
                    $this->info("[DRY RUN] Would update SHOPPING campaign {$campaignId} (SKU: {$pm->sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                } else {
                    try {
                        $budgetResourceName = "customers/{$customerId}/campaignBudgets/{$budgetId}";
                        $this->sbidService->updateCampaignBudget($customerId, $budgetResourceName, $newBudget);
                        $campaignUpdates[$budgetId] = true;
                        $skipCounters['total_processed']++;
                        $this->info("Updated SHOPPING campaign {$campaignId} (SKU: {$pm->sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                    } catch (\Exception $e) {
                        $this->error("Failed to update SHOPPING campaign budget {$campaignId}: " . $e->getMessage());
                    }
                }
            } else {
                $skipCounters['duplicate_budget']++;
            }
        }

            $processedCount = count($campaignUpdates);
            $action = $dryRun ? "Would process" : "Processed";
            $this->info("Done. {$action}: {$processedCount} unique SHOPPING campaign budgets.");
            $this->info("Skip Statistics:");
            $this->info("  - Zero Inventory: {$skipCounters['zero_inventory']}");
            $this->info("  - NRA (Not Running Ads): {$skipCounters['nra_skip']}");
            $this->info("  - No Matching Campaign: {$skipCounters['no_matching_campaign']}");
            $this->info("  - Campaign Not ENABLED: {$skipCounters['campaign_not_enabled']}");
            $this->info("  - No Budget ID: {$skipCounters['no_budget_id']}");
            $this->info("  - Duplicate Budget (already processed): {$skipCounters['duplicate_budget']}");
            $this->info("  - Total Individual Campaigns {$action}: {$skipCounters['total_campaigns_processed']}");
            $this->info("  - Total Unique Budgets {$action}: {$skipCounters['total_processed']}");
            
            if ($dryRun) {
                $this->warn("\n⚠️  This was a DRY RUN. No budgets were actually updated.");
                $this->info("Run without --dry-run to perform actual updates.");
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

