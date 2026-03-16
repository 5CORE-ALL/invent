<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;
use App\Models\GoogleAdsCampaign;
use App\Models\ShopifySku;

class UpdateSerpBudgetCronCommand extends Command
{
    protected $signature = 'budget:update-serp';
    protected $description = 'Update budget for SERP (SEARCH) campaigns based on ACOS (L30 data)';

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

            $this->info('Starting budget update cron for SERP (SEARCH) campaigns (ACOS-based)...');

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

        // Fetch SEARCH campaigns data within L30 range
        $googleCampaigns = GoogleAdsCampaign::select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_id',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'ga4_ad_sales'
            )
            ->where('advertising_channel_type', 'SEARCH')
            ->where('campaign_status', 'ENABLED')
            ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
            ->get();

        $this->info("Found " . $googleCampaigns->count() . " SERP (SEARCH) campaigns in L30 range");

        $campaignUpdates = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            // Use original SKU for shopifyData lookup
            $shopify = $shopifyData[$pm->sku] ?? null;
            if ($shopify && $shopify->inv <= 0) {
                continue; // Skip zero inventory
            }

            // Use improved matching logic for SEARCH campaigns
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
            $budgetId = $matchedCampaign->budget_id;
            
            if (!$budgetId) {
                $this->line("Skipping campaign {$campaignId} (SKU: {$pm->sku}) - No budget ID");
                continue;
            }

            // Aggregate metrics for L30 range
            $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRanges) {
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
                
                $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string)$c->date);
                $matchesDate = $campaignDate >= $dateRanges['L30']['start'] && $campaignDate <= $dateRanges['L30']['end'];
                
                return $matchesCampaign && $matchesStatus && $matchesDate;
            });

            $totalSpend = $campaignRanges->sum('metrics_cost_micros') / 1000000; // Convert to dollars
            $totalSales = $campaignRanges->sum('ga4_ad_sales'); // Already in dollars
            
            // Calculate ACOS: (Spend / Sales) * 100
            $acos = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;
            
            // Get current budget
            $currentBudget = $matchedCampaign->budget_amount_micros ? $matchedCampaign->budget_amount_micros / 1000000 : 0;

            // Determine budget value based on ACOS
            // ACOS under 10% → budget = 5
            // ACOS 10%-30% → budget = 4
            // ACOS more than 30% → budget = 1
            $newBudget = 1;
            if ($acos < 10) {
                $newBudget = 5;
            } elseif ($acos >= 10 && $acos <= 30) {
                $newBudget = 4;
            } else {
                $newBudget = 1;
            }
            
            if (!isset($campaignUpdates[$budgetId])) {
                try {
                    $budgetResourceName = "customers/{$customerId}/campaignBudgets/{$budgetId}";
                    $this->sbidService->updateCampaignBudget($customerId, $budgetResourceName, $newBudget);
                    $campaignUpdates[$budgetId] = true;
                    $this->info("Updated SERP campaign {$campaignId} (SKU: {$pm->sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                } catch (\Exception $e) {
                    $this->error("Failed to update SERP campaign budget {$campaignId}: " . $e->getMessage());
                }
            }
        }

            $processedCount = count($campaignUpdates);
            $this->info("Done. Processed: {$processedCount} unique SERP campaign budgets.");

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

