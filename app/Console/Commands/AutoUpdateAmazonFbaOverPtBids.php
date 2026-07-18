<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Console\Concerns\CalculatesAmazonFbaBidUpdates;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AutoUpdateAmazonFbaOverPtBids extends Command
{
    use CalculatesAmazonFbaBidUpdates;
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'amazon-fba:auto-update-over-pt-bids
        {--dry-run : Run without updating Amazon}
        {--campaign-id= : Only update this campaign ID}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';
    protected $description = 'Auto-update Amazon FBA over-utilized product targeting bids';

    const MAX_RETRY_ATTEMPTS = 5;
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'Amazon FBA Bid Sync (PT Over)';

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
        $startTs = microtime(true);
        $startedAtIso = now()->toIso8601String();
        $dryRun = (bool) $this->option('dry-run');
        $verbose = $this->output->isVerbose();
        $commandName = $this->getName();

        $this->info('[' . now()->toDateTimeString() . "] Start {$commandName} (dryRun=" . ($dryRun ? 'true' : 'false') . ')');

        if (!Schema::hasTable('fba_table') || !Schema::hasTable('amazon_sp_campaign_reports')) {
            $this->error('Missing required tables: ensure `fba_table` and `amazon_sp_campaign_reports` exist.');
            $this->writeHealth([
                'command' => $commandName,
                'status' => 'ERROR',
                'dry_run' => $dryRun,
                'started_at' => $startedAtIso,
                'ended_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
            ]);

            return self::FAILURE;
        }

        try {
            DB::connection()->getPdo();
            if ($verbose) {
                $this->info('✓ Database connection OK');
            }
        } catch (\Throwable $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
            $monitor->classifyAndRecord($e);
            $this->writeHealth([
                'command' => $commandName,
                'status' => 'ERROR',
                'dry_run' => $dryRun,
                'started_at' => $startedAtIso,
                'ended_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $amazon = new AmazonSpBudgetController();

        try {
            $token = $amazon->getAccessToken();
            if (empty($token)) {
                throw new \RuntimeException('Amazon access token is empty.');
            }
            if ($verbose) {
                $this->info('✓ Amazon token acquired (cached)');
            }
        } catch (\Throwable $e) {
            $this->error('✗ Failed to acquire Amazon access token: ' . $e->getMessage());
            $monitor->classifyAndRecord($e);
            $this->writeHealth([
                'command' => $commandName,
                'status' => 'ERROR',
                'dry_run' => $dryRun,
                'started_at' => $startedAtIso,
                'ended_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $candidates = $this->getAutomateAmzFbaUtilizedBgtPt();
        $candidates = array_values(array_filter($candidates, function ($c) {
            return !empty($c->campaign_id)
                && isset($c->current_bid, $c->sbid, $c->bid_calc)
                && is_numeric($c->sbid)
                && (float) $c->sbid > 0
                && abs((float) $c->sbid - (float) $c->current_bid) >= 0.005;
        }));

        $specificCampaignId = $this->option('campaign-id');
        if ($specificCampaignId !== null && $specificCampaignId !== '') {
            $specificCampaignId = trim((string) $specificCampaignId);
            $candidates = array_values(array_filter($candidates, function ($c) use ($specificCampaignId) {
                return (string) ($c->campaign_id ?? '') === $specificCampaignId;
            }));
            if (empty($candidates)) {
                $this->error("Campaign ID {$specificCampaignId} not found or not eligible.");
                $this->writeHealth([
                    'command' => $commandName,
                    'status' => 'ERROR',
                    'dry_run' => $dryRun,
                    'started_at' => $startedAtIso,
                    'ended_at' => now()->toIso8601String(),
                    'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                    'updated_count' => 0,
                    'skipped_count' => 0,
                    'failed_count' => 0,
                    'error' => 'campaign_id_not_found_or_not_eligible',
                    'campaign_id' => $specificCampaignId,
                ]);

                return self::FAILURE;
            }
            $this->info("Testing only campaign: {$specificCampaignId}");
        }

        if (empty($candidates)) {
            $this->warn('No eligible campaigns found.');
            $monitor->markApiConnected();
            $monitor->setExpected(0);
            $this->writeHealth([
                'command' => $commandName,
                'status' => 'NO_OP',
                'dry_run' => $dryRun,
                'started_at' => $startedAtIso,
                'ended_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
            ]);

            return self::SUCCESS;
        }

        $campaignBudgetMap = [];
        foreach ($candidates as $c) {
            $cid = (string) ($c->campaign_id ?? '');
            if ($cid !== '' && ! isset($campaignBudgetMap[$cid])) {
                $campaignBudgetMap[$cid] = (float) $c->sbid;
            }
        }

        $this->info('Eligible campaigns (with bid change): ' . count($campaignBudgetMap));
        if ($dryRun) {
            foreach ($candidates as $c) {
                $calc = is_array($c->bid_calc ?? null) ? $c->bid_calc : [];
                $note = $this->describeBidCpcSource($calc);
                $this->line(sprintf(
                    ' - %s: current=%.2f ub1=%.1f%% → new=%.2f (%s)',
                    $c->campaign_id,
                    (float) $c->current_bid,
                    (float) $c->ub1,
                    (float) $c->sbid,
                    $note
                ));
            }
        } elseif ($verbose) {
            foreach (array_slice($candidates, 0, 20) as $c) {
                $calc = is_array($c->bid_calc ?? null) ? $c->bid_calc : [];
                $this->info(sprintf(
                    ' - %s: current=%.2f → new=%.2f ub1=%.1f%% | %s',
                    $c->campaign_id,
                    (float) $c->current_bid,
                    (float) $c->sbid,
                    (float) $c->ub1,
                    $this->describeBidCpcSource($calc)
                ));
            }
            if (count($candidates) > 20) {
                $this->info(' ... (showing first 20 candidates only)');
            }
        }

        try {
            if ($dryRun) {
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $n = count($campaignBudgetMap);
                $monitor->setExpected($n);
                $monitor->setFetched($n);
                $monitor->setSkipped($n);
                $this->writeHealth([
                    'command' => $commandName,
                    'status' => 'DRY_RUN',
                    'dry_run' => true,
                    'started_at' => $startedAtIso,
                    'ended_at' => now()->toIso8601String(),
                    'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                    'updated_count' => $n,
                    'skipped_count' => 0,
                    'failed_count' => 0,
                    'attempts' => 0,
                ]);

                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            $stats = $this->pushAmazonAdsIdMapInChunks(
                $monitor,
                $campaignBudgetMap,
                fn (array $ids, array $bids) => $amazon->updateAutoCampaignTargetsBid($ids, $bids)
            );

            $status = ($stats['failed'] ?? 0) > 0 ? 'PARTIAL_FAILURE' : 'SUCCESS';
            $this->writeHealth([
                'command' => $commandName,
                'status' => $status,
                'dry_run' => false,
                'started_at' => $startedAtIso,
                'ended_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $startTs) * 1000),
                'updated_count' => (int) ($stats['updated'] ?? 0),
                'skipped_count' => (int) ($stats['skipped'] ?? 0),
                'failed_count' => (int) ($stats['failed'] ?? 0),
                'chunks' => (int) ($stats['chunks'] ?? 0),
            ]);

            $this->info("FINAL: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Error: ' . $e->getMessage());
            $monitor->classifyAndRecord($e);

            return self::FAILURE;
        } finally {
            DB::connection()->disconnect();
        }
    }

    /**
     * Build bid update candidates for FBA over-utilized product targeting campaigns.
     *
     * @return array<int, object> objects containing: campaign_id, sbid, ub7, ub1, l7_cpc, l1_cpc, l7_spend, l1_spend, inv
     */
    public function getAutomateAmzFbaUtilizedBgtPt(): array
    {
        try {
            $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                ->orderBy('seller_sku', 'asc')
                ->get();

            if ($fbaData->isEmpty()) {
                return [];
            }

            $sellerSkus = $fbaData->pluck('seller_sku')->filter()->unique()->values()->all();
            if (empty($sellerSkus)) {
                return [];
            }

            $baseSkus = $fbaData->map(function ($item) {
                $sku = $item->seller_sku ?? '';
                $base = preg_replace('/\s*FBA\s*/i', '', (string) $sku);
                return strtoupper(trim($base));
            })->filter()->unique()->values()->all();

            if (empty($baseSkus)) {
                return [];
            }

            $shopifyData = ShopifySku::mapByProductSkus($baseSkus);

            $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L7')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->where(function ($q) {
                    $q->whereRaw("LOWER(campaignName) LIKE '%fba pt%'")
                        ->orWhereRaw("LOWER(campaignName) LIKE '%fba pt.%'");
                })
                ->get();

            $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L1')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->where(function ($q) {
                    $q->whereRaw("LOWER(campaignName) LIKE '%fba pt%'")
                        ->orWhereRaw("LOWER(campaignName) LIKE '%fba pt.%'");
                })
                ->get();

            $amazonSpCampaignReportsL2 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L2')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->where(function ($q) {
                    $q->whereRaw("LOWER(campaignName) LIKE '%fba pt%'")
                        ->orWhereRaw("LOWER(campaignName) LIKE '%fba pt.%'");
                })
                ->get();

            $candidatesByCampaignId = [];
            $sbidRule = \App\Support\AmazonAdsSbidRule::resolvedRule();

            foreach ($fbaData as $fba) {
                $sellerSkuUpper = strtoupper(trim((string) ($fba->seller_sku ?? '')));
                if ($sellerSkuUpper === '') {
                    continue;
                }

                $baseSkuUpper = strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string) $fba->seller_sku)));
                $shopify = $shopifyData[$baseSkuUpper] ?? null;
                $inv = (int) ($shopify->inv ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim((string) ($item->campaignName ?? ''), '.')));
                    if (!str_contains($cleanName, $sellerSkuUpper)) {
                        return false;
                    }
                    return (
                        (str_contains($cleanName, $sellerSkuUpper . ' PT') || str_contains($cleanName, $sellerSkuUpper . ' PT.'))
                        && strtoupper((string) ($item->campaignStatus ?? '')) === 'ENABLED'
                    );
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim((string) ($item->campaignName ?? ''), '.')));
                    if (!str_contains($cleanName, $sellerSkuUpper)) {
                        return false;
                    }
                    return (
                        (str_contains($cleanName, $sellerSkuUpper . ' PT') || str_contains($cleanName, $sellerSkuUpper . ' PT.'))
                        && strtoupper((string) ($item->campaignStatus ?? '')) === 'ENABLED'
                    );
                });

                $campaignId = (string) (($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null)
                    ?? ($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : null) ?? '');
                if ($campaignId === '') {
                    continue;
                }

                $budget = floatval(
                    ($matchedCampaignL7 ? ($matchedCampaignL7->campaignBudgetAmount ?? null) : null)
                    ?? ($matchedCampaignL1 ? ($matchedCampaignL1->campaignBudgetAmount ?? null) : null) ?? 0
                );
                $l7_spend = floatval($matchedCampaignL7 ? ($matchedCampaignL7->spend ?? 0) : 0);
                $l1_spend = floatval($matchedCampaignL1 ? ($matchedCampaignL1->spend ?? 0) : 0);
                $l7_cpcRow = floatval($matchedCampaignL7 ? ($matchedCampaignL7->costPerClick ?? 0) : 0);
                $l1_cpcRow = floatval($matchedCampaignL1 ? ($matchedCampaignL1->costPerClick ?? 0) : 0);

                $cpcL1 = $this->cpcFromCampaign($amazonSpCampaignReportsL1, $campaignId);
                $cpcL2 = $this->cpcFromCampaign($amazonSpCampaignReportsL2, $campaignId);
                $cpcL7 = $this->cpcFromCampaign($amazonSpCampaignReportsL7, $campaignId);
                $l2_spend = $this->spendFromCampaign($amazonSpCampaignReportsL2, $campaignId);

                $ruleBid = $this->fbaRuleBasedSbidOrNull(
                    $campaignId,
                    'fba_pt',
                    true,
                    $budget,
                    $l7_spend,
                    $l1_spend,
                    $l2_spend,
                    $cpcL1,
                    $cpcL2,
                    $cpcL7,
                    $sbidRule
                );
                if ($ruleBid === null) {
                    continue;
                }

                $currentBid = $this->resolveCurrentBidFromReport($matchedCampaignL7, $matchedCampaignL1, $l7_cpcRow, $l1_cpcRow);
                $newBid = $ruleBid['sbid'];
                if ($newBid <= 0 || abs($newBid - $currentBid) < 0.001) {
                    continue;
                }

                $calc = [
                    'source' => (string) ($ruleBid['bid_out']['band'] ?? 'over'),
                    'band' => (string) ($ruleBid['bid_out']['band'] ?? 'over'),
                    'base_cpc' => 0.0,
                    'multiplier' => 1.0,
                    'ub_source' => $ruleBid['ub_source'],
                ];

                $candidate = (object) [
                    'campaign_id' => $campaignId,
                    'current_bid' => round($currentBid, 2),
                    'sbid' => (float) $newBid,
                    'ub7' => $ruleBid['ub7'],
                    'ub1' => $ruleBid['ub1'],
                    'ub2' => $ruleBid['ub2'],
                    'l7_cpc' => $cpcL7,
                    'l1_cpc' => $cpcL1,
                    'l2_cpc' => $cpcL2,
                    'l7_spend' => $l7_spend,
                    'l1_spend' => $l1_spend,
                    'inv' => $inv,
                    'bid_cpc_source' => $calc['source'],
                    'bid_calc' => $calc,
                ];

                if (!isset($candidatesByCampaignId[$candidate->campaign_id])) {
                    $candidatesByCampaignId[$candidate->campaign_id] = $candidate;
                } else {
                    if (floatval($candidate->ub1) > floatval($candidatesByCampaignId[$candidate->campaign_id]->ub1)) {
                        $candidatesByCampaignId[$candidate->campaign_id] = $candidate;
                    }
                }
            }

            return array_values($candidatesByCampaignId);
        } catch (\Throwable $e) {
            Log::error('Error in getAutomateAmzFbaUtilizedBgtPt (FBA Over PT): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Persist the last run health details in cache for the health endpoint.
     *
     * @param array<string, mixed> $payload
     */
    private function writeHealth(array $payload): void
    {
        cache()->put('amazon_fba_bid_update_health', $payload, now()->addDays(2));
    }
}