<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\GoogleAdsCampaign;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\GoogleAdsSbidService;
use App\Support\GoogleShoppingCampaignsRawRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateSerpBudgetCronCommand extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'budget:update-serp
                            {--dry-run : Run without actually updating budgets}
                            {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';

    protected $description = 'Update budget for SERP (SEARCH) campaigns based on ACOS (L30 data)';

    protected string $monitorJobName = 'Google Budget Sync (SERP)';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeBudgetUpdate($m),
            $this->monitorJobName
        );
    }

    protected function executeBudgetUpdate(CronExecutionContext $monitor): int
    {
        try {
            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: '.$e->getMessage());
                $monitor->classifyAndRecord($e);

                return self::FAILURE;
            }

            $dryRun = $this->option('dry-run');
            if ($dryRun) {
                $this->warn('⚠️  DRY RUN MODE - No budgets will be updated');
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
                    'end' => $endDate,
                ],
            ];

            $this->info("Date range - L30: {$dateRanges['L30']['start']} to {$dateRanges['L30']['end']}");

            // Fetch product masters
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn('No product masters found!');
                DB::connection()->disconnect();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            // Get all SKUs to fetch Shopify inventory data
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->toArray();

            if (empty($skus)) {
                $this->warn('No valid SKUs found!');
                DB::connection()->disconnect();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $shopifyData = [];
            if (! empty($skus)) {
                $shopifyData = ShopifySku::mapByProductSkus($skus);
            }
            DB::connection()->disconnect();

            $this->info('Found '.$productMasters->count().' product masters');

            // Fetch SEARCH campaigns data within L30 range
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
                ->where('advertising_channel_type', 'SEARCH')
                ->where('campaign_status', 'ENABLED')
                ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
                ->get();

            $this->info('Found '.$googleCampaigns->count().' SERP (SEARCH) campaigns in L30 range');

            $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();

            $toPush = [];
            $budgetDetails = [];

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
                    if (! str_ends_with($campaign, ' SEARCH.')) {
                        return false;
                    }

                    // Remove ' SEARCH.' suffix for matching
                    $campaignBase = str_replace(' SEARCH.', '', $campaign);

                    // Check if SKU is in comma-separated list
                    $parts = array_map('trim', explode(',', $campaignBase));
                    $exactMatch = in_array($skuTrimmed, $parts);

                    // If not in list, check if campaign base exactly equals SKU
                    if (! $exactMatch) {
                        $exactMatch = $campaignBase === $skuTrimmed;
                    }

                    return $exactMatch && $c->campaign_status === 'ENABLED';
                });

                if (! $matchedCampaign) {
                    continue;
                }

                $campaignId = $matchedCampaign->campaign_id;
                $budgetId = $matchedCampaign->budget_id;

                if (! $budgetId) {
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
                        if (! $exactMatch) {
                            $exactMatch = $campaignBase === $skuTrimmed;
                        }
                    } else {
                        $exactMatch = false;
                    }

                    $matchesCampaign = $exactMatch;
                    $matchesStatus = $c->campaign_status === 'ENABLED';

                    $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string) $c->date);
                    $matchesDate = $campaignDate >= $dateRanges['L30']['start'] && $campaignDate <= $dateRanges['L30']['end'];

                    return $matchesCampaign && $matchesStatus && $matchesDate;
                });

                $totalSpend = $campaignRanges->sum('metrics_cost_micros') / 1000000; // Convert to dollars
                $totalGA4ActualSales = $campaignRanges->sum('ga4_actual_revenue');
                $totalSales = ($totalGA4ActualSales > 0) ? $totalGA4ActualSales : $campaignRanges->sum('ga4_ad_sales');

                $spendR = (int) round($totalSpend);
                $salesR = (int) round($totalSales);
                $acos = 0.0;
                if ($salesR >= 1) {
                    $acos = ($spendR / $salesR) * 100.0;
                } elseif ($spendR > 0) {
                    $acos = 100.0;
                }

                // Get current budget
                $currentBudget = $matchedCampaign->budget_amount_micros ? $matchedCampaign->budget_amount_micros / 1000000 : 0;

                $newBudget = GoogleShoppingCampaignsRawRule::sbgtFromAcos((float) $acos, $rawRule);

                if (! isset($toPush[$budgetId])) {
                    if ($dryRun) {
                        $this->info("[DRY RUN] Would update SERP campaign {$campaignId} (SKU: {$pm->sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                    }
                    $toPush[$budgetId] = $newBudget;
                    $budgetDetails[$budgetId] = [
                        'campaignId' => $campaignId,
                        'sku' => $pm->sku,
                        'currentBudget' => $currentBudget,
                        'acos' => $acos,
                    ];
                }
            }

            $processedCount = count($toPush);

            if ($processedCount === 0) {
                $this->info('Done. No SERP campaign budgets to update.');
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            if ($dryRun) {
                $this->info("Done. Would have processed: {$processedCount} unique SERP campaign budgets (dry run).");
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $monitor->setExpected($processedCount);
                $monitor->setFetched($processedCount);
                $monitor->setSkipped($processedCount);

                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            $stats = $this->updateIdMapInChunks(
                $monitor,
                $toPush,
                function (array $chunkIds, array $chunkValues, int $chunkIndex) use ($customerId, $budgetDetails) {
                    $updated = 0;
                    $failed = 0;
                    $failures = [];
                    foreach ($chunkIds as $i => $budgetId) {
                        $detail = $budgetDetails[$budgetId] ?? null;
                        $campaignId = $detail['campaignId'] ?? $budgetId;
                        $sku = $detail['sku'] ?? '';
                        $currentBudget = $detail['currentBudget'] ?? 0;
                        $acos = $detail['acos'] ?? 0;
                        $newBudget = $chunkValues[$i];
                        try {
                            $budgetResourceName = "customers/{$customerId}/campaignBudgets/{$budgetId}";
                            $this->sbidService->updateCampaignBudget($customerId, $budgetResourceName, $newBudget);
                            $updated++;
                            $this->info("Updated SERP campaign {$campaignId} (SKU: {$sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                        } catch (\Exception $e) {
                            $failed++;
                            $failures[] = [
                                'sku' => (string) $budgetId,
                                'marketplace' => 'google',
                                'reason' => $e->getMessage(),
                                'http_status' => 500,
                            ];
                            $this->error("Failed to update SERP campaign budget {$campaignId}: ".$e->getMessage());
                        }
                    }

                    return [
                        'updated' => $updated,
                        'failed' => $failed,
                        'processed' => count($chunkIds),
                        'failures' => $failures,
                    ];
                }
            );

            $this->info("Done. Processed: {$processedCount} unique SERP campaign budgets.");

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Error occurred: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());
            $monitor->classifyAndRecord($e);

            return self::FAILURE;
        } finally {
            DB::connection()->disconnect();
        }
    }
}
