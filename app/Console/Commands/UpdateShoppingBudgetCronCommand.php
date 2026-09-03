<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\GoogleAdsCampaign;
use App\Models\GoogleDataView;
use App\Models\ProductMaster;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\GoogleAdsSbidService;
use App\Support\GoogleShoppingBgtParts;
use App\Support\GoogleShoppingBgtSkuMetrics;
use App\Support\GoogleShoppingCampaignNameMatcher;
use App\Support\GoogleShoppingCampaignsRawRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateShoppingBudgetCronCommand extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'budget:update-shopping
                            {--dry-run : Run without actually updating budgets}
                            {--campaign-ids= : Comma-separated Google Ads campaign IDs (SHOPPING only; limits which campaigns are updated)}
                            {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';

    protected $description = 'Update budget for SHOPPING campaigns based on ACOS (L30 data)';

    protected string $monitorJobName = 'Google Budget Sync (Shopping)';

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
            @ini_set('memory_limit', '512M');

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

            $campaignIdsFilter = $this->parseCampaignIdsFilterOption();
            if ($campaignIdsFilter !== null) {
                $this->info('Scope: '.count($campaignIdsFilter).' campaign id(s) (--campaign-ids).');
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
                    'end' => $endDate,
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

            // Get NRA values from GoogleDataView (matching frontend logic)
            $nrValues = GoogleDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            DB::connection()->disconnect();

            $this->info('Found '.$productMasters->count().' product masters');

            // Fetch SHOPPING campaigns (ENABLED + PAUSED; exclude only ARCHIVED) so L30
            // aggregation matches the UI. We only *update* campaigns that are ENABLED (see
            // $matchedCampaign and $campaignRanges aggregate below).
            $googleCampaignsQuery = GoogleAdsCampaign::select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_id',
                'budget_amount_micros',
                'date',
                'metrics_cost_micros',
                'metrics_clicks',
                'ga4_ad_sales',
                'ga4_actual_revenue',
                'ga4_actual_sold_units'
            )
                ->where('advertising_channel_type', 'SHOPPING')
                ->where('campaign_status', '!=', 'ARCHIVED')
                ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']]);
            if ($campaignIdsFilter !== null) {
                $googleCampaignsQuery->whereIn('campaign_id', $campaignIdsFilter);
            }
            $googleCampaigns = $googleCampaignsQuery->get();

            $this->info('Found '.$googleCampaigns->count().' SHOPPING campaigns in L30 range');

            $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();
            $bgtSkuResolver = GoogleShoppingBgtSkuMetrics::resolver();

            $toPush = [];
            $toPause = [];
            $budgetDetails = [];
            $skipCounters = [
                'zero_inventory' => 0,
                'paused_zero_sbgt' => 0,
                'nra_skip' => 0,
                'no_matching_campaign' => 0,
                'campaign_not_enabled' => 0,
                'no_budget_id' => 0,
                'duplicate_budget' => 0,
                'total_processed' => 0,
                'total_campaigns_processed' => 0, // Count individual campaigns, not just unique budgets
            ];

            foreach ($productMasters as $pm) {
                $sku = strtoupper(trim($pm->sku));
                $isParentSku = stripos($sku, 'PARENT') !== false;

                // Only process PARENT SKUs (parent ads are running now)
                if (! $isParentSku) {
                    continue;
                }

                // Check NRA (Not Running Ads) - skip if NRA
                $nra = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (! is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nra = $raw['NRA'] ?? '';
                    }
                }
                if (! empty($nra) && strtoupper(trim($nra)) === 'NRA') {
                    $skipCounters['nra_skip']++;

                    continue; // Skip NRA campaigns
                }

                // Use improved matching logic for SHOPPING campaigns (matching frontend logic)
                $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                    return GoogleShoppingCampaignNameMatcher::matches((string) $c->campaign_name, $sku)
                        && $c->campaign_status === 'ENABLED';
                });

                if (! $matchedCampaign) {
                    // For PARENT SKUs: fallback to DB lookup by current name (campaign may have been renamed)
                    if ($isParentSku) {
                        $skuCleaned = rtrim(trim($sku), '.');
                        $parentCampaign = DB::table('google_ads_campaigns')
                            ->select('campaign_id', 'campaign_name', 'campaign_status', 'budget_id', 'budget_amount_micros')
                            ->where('advertising_channel_type', 'SHOPPING')
                            ->where('campaign_status', 'ENABLED')
                            ->where(function ($q) use ($sku, $skuCleaned) {
                                $q->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$sku])
                                    ->orWhereRaw('UPPER(TRIM(campaign_name)) = ?', [$sku.'.'])
                                    ->orWhereRaw('TRIM(TRAILING \'.\' FROM UPPER(TRIM(campaign_name))) = ?', [$skuCleaned]);
                            })
                            ->orderBy('date', 'desc')
                            ->first();

                        if ($parentCampaign) {
                            $matchedCampaign = $parentCampaign;
                        }
                    }

                    if (! $matchedCampaign) {
                        // Check if campaign exists but is not ENABLED (matching frontend logic)
                        $anyCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                            return GoogleShoppingCampaignNameMatcher::matches((string) $c->campaign_name, $sku);
                        });

                        if ($anyCampaign && $anyCampaign->campaign_status !== 'ENABLED') {
                            $skipCounters['campaign_not_enabled']++;
                        } else {
                            $skipCounters['no_matching_campaign']++;
                        }

                        continue;
                    }
                }

                $campaignId = $matchedCampaign->campaign_id;
                // FIX: Convert filter to strings for consistent comparison (campaign_id from DB is string, filter may be int)
                if ($campaignIdsFilter !== null && ! in_array((string) $campaignId, array_map('strval', $campaignIdsFilter), true)) {
                    continue;
                }
                $budgetId = $matchedCampaign->budget_id;

                if (! $budgetId) {
                    $skipCounters['no_budget_id']++;
                    $this->line("Skipping campaign {$campaignId} (SKU: {$pm->sku}) - No budget ID");

                    continue;
                }

                // Aggregate metrics for L30 (include ENABLED + PAUSED so ACOS matches UI/SBGT)
                $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRanges) {
                    if (! GoogleShoppingCampaignNameMatcher::matches((string) $c->campaign_name, $sku)) {
                        return false;
                    }

                    $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string) $c->date);

                    return $campaignDate >= $dateRanges['L30']['start'] && $campaignDate <= $dateRanges['L30']['end'];
                });

                $totalSpend = $campaignRanges->sum('metrics_cost_micros') / 1000000; // Convert to dollars
                // Use same L30 sales as frontend (aggregateMetricsByRange): prefer GA4 actual revenue when available
                $totalGA4ActualSales = $campaignRanges->sum('ga4_actual_revenue');
                $totalSales = ($totalGA4ActualSales > 0) ? $totalGA4ActualSales : $campaignRanges->sum('ga4_ad_sales');
                $totalClicks = (int) $campaignRanges->sum('metrics_clicks');
                $totalSold = (float) $campaignRanges->sum('ga4_actual_sold_units');

                // ACOS from rounded L30 spend/sales — same as G-Shopping raw grid (enrichRawRowGoogleShoppingStyle)
                $spendR = (int) round($totalSpend);
                $salesR = (int) round($totalSales);
                $acos = 0.0;
                if ($salesR >= 1) {
                    $acos = ($spendR / $salesR) * 100.0;
                } elseif ($spendR > 0) {
                    $acos = 100.0;
                }
                $cvrL30 = $totalClicks > 0 ? round(($totalSold / $totalClicks) * 100.0, 1) : 0.0;
                $skuMetrics = $bgtSkuResolver((string) ($matchedCampaign->campaign_name ?? $pm->sku));

                // Get current budget from latest-by-date row (same as frontend BGT in getGoogleShoppingAdsData)
                $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)->sortByDesc('date')->first();
                $currentBudget = ($latestCampaign && $latestCampaign->budget_amount_micros) ? $latestCampaign->budget_amount_micros / 1000000 : 0;

                // Daily budget = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC (same as grid SBGT).
                // INV ≤ 0 forces SBGT 0 — cannot push $0, so the campaign is paused instead.
                $inv = isset($skuMetrics['inv']) && is_numeric($skuMetrics['inv'])
                    ? (float) $skuMetrics['inv']
                    : 0.0;
                $newBudget = GoogleShoppingBgtParts::suggestedDailyBudget(
                    (float) $acos,
                    $cvrL30,
                    isset($skuMetrics['views_l7']) ? (float) $skuMetrics['views_l7'] : 0.0,
                    isset($skuMetrics['price']) && is_numeric($skuMetrics['price']) ? (float) $skuMetrics['price'] : null,
                    $rawRule,
                    $inv
                );

                $skipCounters['total_campaigns_processed']++;

                if ($newBudget < 1) {
                    if ($inv <= 0) {
                        $skipCounters['zero_inventory']++;
                    }
                    $toPause[(string) $campaignId] = [
                        'sku' => $pm->sku,
                        'inv' => $inv,
                        'acos' => $acos,
                    ];
                    if ($dryRun) {
                        $this->info("[DRY RUN] [PARENT] Would pause SHOPPING campaign {$campaignId} (SKU: {$pm->sku}): SBGT=0 INV={$inv} (cannot push \$0)");
                    }

                    continue;
                }

                if (! isset($toPush[$budgetId])) {
                    if ($dryRun) {
                        $this->info("[DRY RUN] [PARENT] Would update SHOPPING campaign {$campaignId} (SKU: {$pm->sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                        $skipCounters['total_processed']++;
                    }
                    $toPush[$budgetId] = $newBudget;
                    $budgetDetails[$budgetId] = [
                        'campaignId' => $campaignId,
                        'sku' => $pm->sku,
                        'currentBudget' => $currentBudget,
                        'acos' => $acos,
                    ];
                } else {
                    $skipCounters['duplicate_budget']++;
                }
            }

            $processedCount = count($toPush);
            $pauseCount = count($toPause);

            if ($processedCount === 0 && $pauseCount === 0) {
                $this->info('Done. Would process: 0 unique SHOPPING campaign budgets.');
                $this->printShoppingSkipStats($skipCounters, $dryRun ? 'Would process' : 'Processed');
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            if ($dryRun) {
                $this->info("Done. Would process: {$processedCount} unique SHOPPING campaign budgets, pause {$pauseCount} with SBGT 0.");
                $this->printShoppingSkipStats($skipCounters, 'Would process');
                $this->warn("\n⚠️  This was a DRY RUN. No budgets were actually updated.");
                $this->info('Run without --dry-run to perform actual updates.');
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $monitor->setExpected($processedCount + $pauseCount);
                $monitor->setFetched($processedCount + $pauseCount);
                $monitor->setSkipped($processedCount + $pauseCount);

                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            foreach ($toPause as $pauseCampaignId => $pauseDetail) {
                try {
                    $campaignResourceName = "customers/{$customerId}/campaigns/{$pauseCampaignId}";
                    $this->sbidService->pauseCampaign($customerId, $campaignResourceName);
                    GoogleAdsCampaign::where('campaign_id', $pauseCampaignId)
                        ->update(['campaign_status' => 'PAUSED']);
                    $skipCounters['paused_zero_sbgt']++;
                    $invNote = isset($pauseDetail['inv']) ? ' INV='.$pauseDetail['inv'] : '';
                    $this->info("[PARENT] Paused SHOPPING campaign {$pauseCampaignId} (SKU: ".($pauseDetail['sku'] ?? '')."): SBGT=0{$invNote} (cannot push \$0)");
                } catch (\Exception $e) {
                    $this->error("Failed to pause SHOPPING campaign {$pauseCampaignId}: ".$e->getMessage());
                }
            }

            if ($processedCount === 0) {
                $this->info("Done. Paused {$skipCounters['paused_zero_sbgt']} campaign(s) with SBGT 0.");
                $this->printShoppingSkipStats($skipCounters, 'Processed');
                $monitor->setExpected($pauseCount);
                $monitor->setFetched($skipCounters['paused_zero_sbgt']);

                return self::SUCCESS;
            }
            $stats = $this->updateIdMapInChunks(
                $monitor,
                $toPush,
                function (array $chunkIds, array $chunkValues, int $chunkIndex) use ($customerId, $budgetDetails, &$skipCounters) {
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
                            $skipCounters['total_processed']++;
                            $this->info("[PARENT] Updated SHOPPING campaign {$campaignId} (SKU: {$sku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%)");
                        } catch (\Exception $e) {
                            $failed++;
                            $failures[] = [
                                'sku' => (string) $budgetId,
                                'marketplace' => 'google',
                                'reason' => $e->getMessage(),
                                'http_status' => 500,
                            ];
                            $this->error("Failed to update SHOPPING campaign budget {$campaignId}: ".$e->getMessage());
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

            $this->info("Done. Processed: {$processedCount} unique SHOPPING campaign budgets.");
            $this->printShoppingSkipStats($skipCounters, 'Processed');

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

    /**
     * @param  array<string, int>  $skipCounters
     */
    private function printShoppingSkipStats(array $skipCounters, string $action): void
    {
        $this->info('Skip Statistics:');
        $this->info("  - Zero Inventory (SBGT 0): {$skipCounters['zero_inventory']}");
        $this->info('  - Paused SBGT 0: '.($skipCounters['paused_zero_sbgt'] ?? 0));
        $this->info("  - NRA (Not Running Ads): {$skipCounters['nra_skip']}");
        $this->info("  - No Matching Campaign: {$skipCounters['no_matching_campaign']}");
        $this->info("  - Campaign Not ENABLED: {$skipCounters['campaign_not_enabled']}");
        $this->info("  - No Budget ID: {$skipCounters['no_budget_id']}");
        $this->info("  - Duplicate Budget (already processed): {$skipCounters['duplicate_budget']}");
        $this->info("  - Total Individual Campaigns {$action}: {$skipCounters['total_campaigns_processed']}");
        $this->info("  - Total Unique Budgets {$action}: {$skipCounters['total_processed']}");
    }

    /**
     * @return list<string>|null null = no filter (all PARENT Shopping campaigns as before)
     */
    private function parseCampaignIdsFilterOption(): ?array
    {
        $raw = $this->option('campaign-ids');
        if ($raw === null || $raw === false || trim((string) $raw) === '') {
            return null;
        }
        $parts = preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $d = preg_replace('/\D/', '', (string) $p);
            if ($d !== '' && strlen($d) <= 32) {
                $out[$d] = true;
            }
            if (count($out) >= 1000) {
                break;
            }
        }

        return $out === [] ? null : array_keys($out);
    }
}
