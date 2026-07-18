<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbayUnderUtilzBidsAutoUpdate extends Command
{
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'ebay:auto-update-under-bids
        {--sku= : Test with a specific SKU only}
        {--dry-run : Show what would be pushed without actually updating bids}
        {--debug : Show detailed per-SKU trace}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';

    protected $description = 'Automatically update Ebay campaign keyword bids based on SCVR thresholds';

    /** Number of retry attempts for failed campaign updates (minimum 5 tries total for failures). */
    const MAX_RETRY_ATTEMPTS = 5;

    /** Seconds to wait between retry rounds for failed campaigns (rate-limit precaution). */
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'eBay Bid Sync (Under)';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeBidUpdate($m),
            $this->monitorJobName
        );
    }

    protected function executeBidUpdate(CronExecutionContext $monitor): int
    {
        try {
            set_time_limit(0);
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '1024M');

            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: '.$e->getMessage());
                $monitor->classifyAndRecord($e);

                return self::FAILURE;
            }

            $isDryRun = $this->option('dry-run');
            $testSku = $this->option('sku');
            $isDebug = $this->option('debug');

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🚀 Starting eBay SCVR-Based Bids Auto-Update'.($isDryRun ? ' [DRY RUN - no API calls]' : ''));
            if ($testSku) {
                $this->warn("🔍 SKU FILTER: Testing for SKU = {$testSku}");
            }
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $updateOverUtilizedBids = new EbayOverUtilizedBgtController;

            $campaigns = $this->getEbayOverUtilizCampaign($testSku, $isDebug);

            if (empty($campaigns)) {
                $this->warn('⚠️  No campaigns matched filter conditions.');
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $validCampaigns = array_filter($campaigns, function ($campaign) {
                return ! empty($campaign->campaign_id) && ! empty($campaign->sbid) && floatval($campaign->sbid) > 0;
            });

            if (empty($validCampaigns)) {
                $this->warn('⚠️  No valid campaigns found (all have empty campaign_id or zero/blank sbid).');
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $this->info('📊 Found '.count($validCampaigns).' campaigns to update');
            $this->info('');

            $this->info('📋 Campaigns to be updated (with SBID calculation details):');
            foreach ($validCampaigns as $index => $campaign) {
                $campaignName = $campaign->campaign_name ?? 'Unknown';
                $campaignId = $campaign->campaign_id ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $lastSbid = ! empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float) $campaign->last_sbid : 0;
                $l1Cpc = $campaign->l1_cpc ?? 0;
                $l7Cpc = $campaign->l7_cpc ?? 0;

                $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                $l7Spend = floatval($campaign->l7_spend ?? 0);
                $l1Spend = floatval($campaign->l1_spend ?? 0);
                $ub7 = $budget > 0 ? ($l7Spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1Spend / $budget) * 100 : 0;

                $ruleApplied = $campaign->rule_applied ?? 'Unknown rule';
                $baseBidSource = $campaign->base_bid_source ?? 'unknown';

                $baseBid = 0;
                if ($lastSbid > 0) {
                    $baseBid = $lastSbid;
                } elseif ($l1Cpc > 0) {
                    $baseBid = $l1Cpc;
                } elseif ($l7Cpc > 0) {
                    $baseBid = $l7Cpc;
                }

                $this->line('   '.($index + 1).". Campaign: {$campaignName}");
                $this->line("       ID: {$campaignId} | UB7: ".number_format($ub7, 2).'% | UB1: '.number_format($ub1, 2).'%');
                $this->line("       Base Bid: \${$baseBid} (Source: {$baseBidSource}) | Last SBID: \${$lastSbid} | New SBID: \${$newBid}");
                $this->line("       Rule Applied: {$ruleApplied}");
                $this->line('');
            }

            $this->info('');

            $campaignBidMap = [];
            foreach ($validCampaigns as $campaign) {
                $campaignBidMap[$campaign->campaign_id] = $campaign->sbid ?? 0;
            }

            if ($isDryRun) {
                $this->newLine();
                $this->warn('DRY RUN: No API call made. Remove --dry-run to apply updates.');
                $this->info('✓ Dry run completed. Total campaigns that would be updated: '.count($campaignBidMap));
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $monitor->setExpected(count($campaignBidMap));
                $monitor->setFetched(count($campaignBidMap));
                $monitor->setSkipped(count($campaignBidMap));

                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            $stats = $this->pushAmazonAdsIdMapInChunks(
                $monitor,
                $campaignBidMap,
                fn (array $ids, array $bids) => $this->normalizeEbayAdsPushResult(
                    $updateOverUtilizedBids->updatePlsCampaignBidPercentage($ids, $bids),
                    $ids
                ),
                'ebay'
            );

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("FINAL: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Log::info('ebay:auto-update-under-bids completed', $stats);

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
     * Normalize eBay JsonResponse into Amazon-style chunk result for retries.
     *
     * @param  list<string>  $ids
     * @return array{status: int, failed: list<array>, skipped: list<array>}
     */
    private function normalizeEbayAdsPushResult($result, array $ids): array
    {
        if (is_object($result) && method_exists($result, 'getData')) {
            $result = $result->getData(true);
        }

        if (! is_array($result)) {
            return [
                'status' => 500,
                'failed' => array_map(
                    fn ($id) => ['campaign_id' => (string) $id, 'error' => 'Unexpected API result', 'status' => 500],
                    $ids
                ),
                'skipped' => [],
            ];
        }

        $httpStatus = (int) ($result['status'] ?? 500);
        $data = $result['data'] ?? [];
        $failedById = [];
        $seenIds = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }
            $campId = $item['campaign_id'] ?? null;
            if ($campId !== null) {
                $seenIds[(string) $campId] = true;
            }
            if (($item['status'] ?? '') === 'error') {
                $key = (string) ($campId ?? 'unknown');
                $failedById[$key] = [
                    'campaign_id' => $key,
                    'error' => $item['message'] ?? 'Unknown error',
                    'status' => $httpStatus > 0 ? $httpStatus : 500,
                ];
            }
        }

        $skipped = [];
        foreach ($ids as $id) {
            if (! isset($seenIds[(string) $id])) {
                $skipped[] = ['campaign_id' => (string) $id, 'reason' => 'No response row'];
            }
        }

        $failed = array_values($failedById);

        return [
            'status' => empty($failed) ? 200 : 207,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    public function getEbayOverUtilizCampaign($testSku = null, $debug = false)
    {
        try {
            // eBay Account 1 uses generic date-named campaigns (e.g. "Campaign Oct 29 2025, 10:55:21")
            // so we cannot match by SKU name. Instead, compute aggregate SCVR across all EbayMetric
            // records and apply one bid to every RUNNING campaign.

            // Aggregate ebay_l30 and views across all (or one test) EbayMetric rows
            $metricQuery = EbayMetric::query();
            if ($testSku) {
                $metricQuery->where('sku', $testSku);
            }
            $metrics = $metricQuery->get(['sku', 'ebay_l30', 'views']);

            if ($debug) {
                $this->info('📦 EbayMetric rows: '.$metrics->count());
            }

            $totalL30 = $metrics->sum('ebay_l30');
            $totalViews = $metrics->sum('views');
            $scvr = ($totalViews > 0) ? ($totalL30 / $totalViews) * 100 : 0;

            if ($debug) {
                $this->info("📊 Aggregate L30={$totalL30} | Views={$totalViews} | SCVR=".round($scvr, 2).'%');
            }

            // Determine bid and rule from SCVR
            // SCVR = 0% (0 sold or no views) counts as RED → 9.1
            if ($scvr <= 4) {
                $sbid = 9.1;
                $ruleApplied = 'SCVR '.round($scvr, 2).'% ≤ 4% (RED) → 9.1';
            } elseif ($scvr <= 7) {
                $sbid = 7.1;
                $ruleApplied = 'SCVR 4–7% (YELLOW) → 7.1';
            } elseif ($scvr <= 13) {
                $sbid = 4.1;
                $ruleApplied = 'SCVR 7–13% (GREEN) → 4.1';
            } else {
                $sbid = 2.1;
                $ruleApplied = 'SCVR > 10% (PINK) → 2.1';
            }

            if ($debug) {
                $this->info("💡 Bid rule: {$ruleApplied} → SBID = {$sbid}");
            }

            if ($sbid <= 0) {
                if ($debug) {
                    $this->warn('⚠️  SBID = 0, no campaigns will be updated.');
                }

                return [];
            }

            // Fetch all RUNNING campaigns
            $runningCampaigns = EbayPriorityReport::where('report_range', 'L7')
                ->where('campaignStatus', 'RUNNING')
                ->get(['campaign_id', 'campaign_name', 'campaignBudgetAmount']);

            if ($debug) {
                $this->info('📋 RUNNING campaigns to update: '.$runningCampaigns->count());
            }

            $result = [];
            foreach ($runningCampaigns as $campaign) {
                if (empty($campaign->campaign_id)) {
                    continue;
                }
                $result[] = (object) [
                    'campaign_id' => $campaign->campaign_id,
                    'campaign_name' => $campaign->campaign_name,
                    'campaignBudgetAmount' => $campaign->campaignBudgetAmount,
                    'sbid' => $sbid,
                    'rule_applied' => $ruleApplied,
                    'scvr' => round($scvr, 2),
                ];
            }

            DB::connection()->disconnect();

            return $result;

        } catch (\Exception $e) {
            $this->error('Error in getEbayOverUtilizCampaign: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

    // Legacy per-SKU method kept for reference — not used
    private function getEbayOverUtilizCampaign_Legacy($testSku = null, $debug = false)
    {
        try {
            $normalizeSku = function ($sku) {
                $sku = trim($sku);
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return strtoupper($sku);
            };

            $query = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc');
            if ($testSku) {
                $query->where('sku', $testSku);
            }
            $productMasters = $query->get();

            if ($productMasters->isEmpty()) {
                $this->warn('No product masters found in database!');

                return [];
            }

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            if (empty($skus)) {
                $this->warn('No valid SKUs found!');

                return [];
            }

            // SKU normalization function
            $normalizeSku = function ($sku) {
                $sku = trim($sku);
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

                return strtoupper($sku);
            };

            $shopifyData = [];
            $nrValues = [];
            $ebayMetricData = [];

            if (! empty($skus)) {
                // Normalize ShopifySku data keys
                $shopifyRaw = ShopifySku::whereIn('sku', $skus)->get();
                $shopifyData = collect();
                foreach ($shopifyRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $shopifyData[$normalizedKey] = $item;
                }

                $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

                // Normalize EbayMetric data keys
                $ebayMetricRaw = EbayMetric::whereIn('sku', $skus)->get();
                $ebayMetricData = collect();
                foreach ($ebayMetricRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $ebayMetricData[$normalizedKey] = $item;
                }
            }

            $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
                ->where('campaignStatus', 'RUNNING')
                ->get();

            $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
                ->where('campaignStatus', 'RUNNING')
                ->get();

            // Fetch last_sbid from day-before-yesterday's date records
            $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
            $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
                ->where('campaignStatus', 'RUNNING')
                ->get();

            $lastSbidMap = [];
            foreach ($lastSbidReports as $report) {
                if (! empty($report->campaign_id) && ! empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }

            $result = [];

            foreach ($productMasters as $pm) {
                $normalizedSku = $normalizeSku($pm->sku);

                $shopify = $shopifyData[$normalizedSku] ?? null;

                $ebay = $ebayMetricData[$normalizedSku] ?? null;

                $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($normalizedSku, $normalizeSku) {
                    $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));
                    return $campaignName === $normalizedSku
                        || str_contains(strtoupper($item->campaign_name), strtoupper($normalizedSku));
                });

                $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($normalizedSku, $normalizeSku) {
                    $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));
                    return $campaignName === $normalizedSku
                        || str_contains(strtoupper($item->campaign_name), strtoupper($normalizedSku));
                });

                if (! $matchedCampaignL7 && ! $matchedCampaignL1) {
                    if ($debug) {
                        $this->line("   ⬜ [{$normalizedSku}] No matching L7/L1 campaign — skipped");
                    }
                    continue;
                }

                $row = [];
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['price'] = $ebay->ebay_price ?? 0;
                $row['ebay_l30'] = $ebay->ebay_l30 ?? 0;
                $row['views'] = $ebay->views ?? 0;
            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaign_id'] = $campaignId;
            $row['campaign_name'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');

            $row['l7_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
            $row['l7_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
            $row['l1_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
            $row['l1_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');
            $row['last_sbid'] = $lastSbidMap[$campaignId] ?? '';

                $l1_cpc = floatval($row['l1_cpc']);
                $l7_cpc = floatval($row['l7_cpc']);

                $budget = floatval($row['campaignBudgetAmount']);
                $l7_spend = floatval($row['l7_spend']);
                $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // PMT S BID: SCVR-based rule
            $ebayL30Sold = floatval($row['ebay_l30'] ?? 0);
            $ebayViews   = floatval($row['views'] ?? 0);

            {
                $scvr = ($ebayViews > 0) ? ($ebayL30Sold / $ebayViews) * 100 : 0;
                // SCVR = 0% (0 sold or no views) counts as RED → 9.1
                if ($scvr <= 4) {
                    $row['sbid'] = 9.1;
                    $row['rule_applied'] = 'SCVR '.round($scvr, 2).'% ≤ 4% (RED) → 9.1';
                } elseif ($scvr <= 7) {
                    $row['sbid'] = 7.1;
                    $row['rule_applied'] = 'SCVR 4–7% (YELLOW) → 7.1';
                } elseif ($scvr <= 13) {
                    $row['sbid'] = 4.1;
                    $row['rule_applied'] = 'SCVR 7–13% (GREEN) → 4.1';
                } else {
                    $row['sbid'] = 2.1;
                    $row['rule_applied'] = 'SCVR > 10% (PINK) → 2.1';
                }
            }
            $row['base_bid_source'] = 'scvr';

                $row['NR'] = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (! is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $row['NR'] = $raw['NR'] ?? null;
                    }
                }

                // Include if: has inventory, not NRA, and SCVR-based bid was calculated (sbid > 0)
                if ($debug) {
                    $this->line("   🔎 [{$normalizedSku}] INV={$row['INV']} | NR={$row['NR']} | ebay_l30={$row['ebay_l30']} | views={$row['views']} | sbid={$row['sbid']} | rule={$row['rule_applied']}");
                }
                if ($row['NR'] !== 'NRA' && $row['INV'] > 0 && $row['sbid'] > 0) {
                    $result[] = (object) $row;
                }

            }

            DB::connection()->disconnect();

            return $result;

        } catch (\Exception $e) {
            $this->error('Error in getEbayOverUtilizCampaign: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function getDilColor($l30, $inv)
    {
        if ($inv == 0) {
            return 'red';
        }

        $percent = ($l30 / $inv) * 100;

        if ($percent < 16.66) {
            return 'red';
        }
        if ($percent >= 16.66 && $percent < 25) {
            return 'yellow';
        }
        if ($percent >= 25 && $percent < 50) {
            return 'green';
        }

        return 'pink';
    }
}
