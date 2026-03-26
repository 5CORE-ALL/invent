<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AutoUpdateAmazonFbaOverPtBids extends Command
{
    protected $signature = 'amazon-fba:auto-update-over-pt-bids {--dry-run : Run without updating Amazon} {--verbose : Detailed output}';
    protected $description = 'Auto-update Amazon FBA over-utilized product targeting bids';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTs = microtime(true);
        $startedAtIso = now()->toIso8601String();
        $dryRun = (bool) $this->option('dry-run');
        $verbose = (bool) $this->option('verbose');
        $commandName = $this->getName();

        $this->info('[' . now()->toDateTimeString() . "] Start {$commandName} (dryRun=" . ($dryRun ? 'true' : 'false') . ')');

        // Hard validation: required tables must exist.
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
            return 1;
        }

        // Check DB connection early.
        try {
            DB::connection()->getPdo();
            if ($verbose) {
                $this->info('✓ Database connection OK');
            }
        } catch (\Throwable $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
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
            return 1;
        }

        $amazon = new AmazonSpBudgetController();

        // Validate token by touching the cached token.
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
            return 1;
        }

        $candidates = $this->getAutomateAmzFbaUtilizedBgtPt();
        $candidates = array_values(array_filter($candidates, function ($c) {
            return !empty($c->campaign_id) && isset($c->sbid) && is_numeric($c->sbid) && (float) $c->sbid > 0;
        }));

        if (empty($candidates)) {
            $this->warn('No eligible campaigns found.');
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
            return 0;
        }

        $this->info('Eligible campaigns: ' . count($candidates));
        if ($verbose) {
            foreach (array_slice($candidates, 0, 20) as $c) {
                $this->info(" - {$c->campaign_id}: bid={$c->sbid} ub7={$c->ub7} ub1={$c->ub1} inv={$c->inv}");
            }
            if (count($candidates) > 20) {
                $this->info(' ... (showing first 20 candidates only)');
            }
        }

        try {
            $durationMs = (int) ((microtime(true) - $startTs) * 1000);

            if ($dryRun) {
                $status = 'DRY_RUN';
                $summary = [
                    'updated_count' => count($candidates),
                    'skipped_count' => 0,
                    'failed_count' => 0,
                    'attempts' => 0,
                ];
            } else {
                $summary = $this->applyTargetsBidUpdates($amazon, $candidates, $dryRun, $verbose);
                $status = ($summary['failed_count'] ?? 0) > 0 ? 'PARTIAL_FAILURE' : 'SUCCESS';
            }

            $this->writeHealth([
                'command' => $commandName,
                'status' => $status,
                'dry_run' => $dryRun,
                'started_at' => $startedAtIso,
                'ended_at' => now()->toIso8601String(),
                'duration_ms' => $durationMs,
                'updated_count' => (int) ($summary['updated_count'] ?? 0),
                'skipped_count' => (int) ($summary['skipped_count'] ?? 0),
                'failed_count' => (int) ($summary['failed_count'] ?? 0),
                'attempts' => (int) ($summary['attempts'] ?? 0),
            ]);

            return ($summary['failed_count'] ?? 0) > 0 ? 1 : 0;
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

            $shopifyData = ShopifySku::whereIn('sku', $baseSkus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper(trim((string) $item->sku));
                });

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

            $candidatesByCampaignId = [];

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

                $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
                if ($campaignId === '') {
                    continue;
                }

                $budget = floatval($matchedCampaignL7->campaignBudgetAmount ?? $matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = floatval($matchedCampaignL7->spend ?? 0);
                $l1_spend = floatval($matchedCampaignL1->spend ?? 0);
                $l7_cpc = floatval($matchedCampaignL7->costPerClick ?? 0);
                $l1_cpc = floatval($matchedCampaignL1->costPerClick ?? 0);

                $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;
                if ($ub7 <= 90 || $ub1 <= 90) {
                    continue;
                }

                $newBid = ($l7_cpc === 0.0)
                    ? 0.50
                    : (floor($l1_cpc * 0.90 * 100) / 100);

                $newBid = max(0.01, min(1000.0, (float) $newBid));
                if ($newBid <= 0) {
                    continue;
                }

                $candidate = (object) [
                    'campaign_id' => (string) $campaignId,
                    'sbid' => (float) $newBid,
                    'ub7' => round($ub7, 2),
                    'ub1' => round($ub1, 2),
                    'l7_cpc' => $l7_cpc,
                    'l1_cpc' => $l1_cpc,
                    'l7_spend' => $l7_spend,
                    'l1_spend' => $l1_spend,
                    'inv' => $inv,
                ];

                if (!isset($candidatesByCampaignId[$candidate->campaign_id])) {
                    $candidatesByCampaignId[$candidate->campaign_id] = $candidate;
                } else {
                    if (floatval($candidate->ub7) > floatval($candidatesByCampaignId[$candidate->campaign_id]->ub7)) {
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
     * Apply bid updates for product targeting campaigns with retry/backoff.
     *
     * @param array<int, object> $candidates
     * @return array{updated_count:int, skipped_count:int, failed_count:int, attempts:int}
     */
    private function applyTargetsBidUpdates(
        AmazonSpBudgetController $controller,
        array $candidates,
        bool $dryRun,
        bool $verbose
    ): array {
        $chunkSize = 25;
        $maxRetries = 3;
        $baseDelaySeconds = 3;
        $jitterMaxSeconds = 2;

        $total = count($candidates);
        $skippedCampaignIds = [];
        $failedCampaignIds = [];
        $attempts = 0;

        foreach (array_chunk($candidates, $chunkSize) as $chunkIndex => $chunk) {
            $bidByCampaignId = [];
            $campaignIds = [];
            $bids = [];
            foreach ($chunk as $c) {
                $cid = (string) $c->campaign_id;
                $bid = (float) $c->sbid;
                $bidByCampaignId[$cid] = $bid;
                $campaignIds[] = $cid;
                $bids[] = $bid;
            }

            if ($verbose) {
                $this->info("API chunk #{$chunkIndex}: candidates=" . count($campaignIds));
            }

            if ($dryRun) {
                $this->warn('DRY RUN: Skipping API update for chunk #' . $chunkIndex);
                continue;
            }

            $currentCampaignIds = $campaignIds;
            $currentBids = $bids;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $attempts++;

                if ($attempt > 1) {
                    $delay = ($baseDelaySeconds * (2 ** ($attempt - 1))) + random_int(0, $jitterMaxSeconds);
                    if ($verbose) {
                        $this->info("Retrying chunk #{$chunkIndex} attempt {$attempt}/{$maxRetries} after {$delay}s...");
                    }
                    sleep($delay);
                }

                $result = null;
                try {
                    $result = $controller->updateAutoCampaignTargetsBid($currentCampaignIds, $currentBids);
                } catch (\Throwable $e) {
                    $this->error("Chunk #{$chunkIndex} attempt {$attempt}: exception calling update API: " . $e->getMessage());
                    $result = ['status' => 500, 'error' => $e->getMessage(), 'failed' => []];
                }

                if (is_object($result) && method_exists($result, 'getData')) {
                    $result = $result->getData(true);
                }

                $failed = is_array($result) ? ($result['failed'] ?? []) : [];
                $skipped = is_array($result) ? ($result['skipped'] ?? []) : [];

                foreach ($skipped as $s) {
                    if (!empty($s['campaign_id'])) {
                        $skippedCampaignIds[(string) $s['campaign_id']] = true;
                    }
                }
                foreach ($failed as $f) {
                    if (!empty($f['campaign_id'])) {
                        $failedCampaignIds[(string) $f['campaign_id']] = true;
                    }
                }

                if (empty($failed)) {
                    if ($verbose) {
                        $this->info("Chunk #{$chunkIndex} succeeded (no failed campaigns).");
                    }
                    break;
                }

                $retryable = $this->hasRateLimitOrServerFailure($failed);
                if (!$retryable || $attempt >= $maxRetries) {
                    break;
                }

                // Retry only the failed campaigns we still have bids for.
                $retryCampaignIds = [];
                $retryBids = [];
                foreach ($failed as $f) {
                    $cid = (string) ($f['campaign_id'] ?? '');
                    if ($cid !== '' && isset($bidByCampaignId[$cid])) {
                        $retryCampaignIds[] = $cid;
                        $retryBids[] = $bidByCampaignId[$cid];
                    }
                }

                if (empty($retryCampaignIds)) {
                    break;
                }

                $currentCampaignIds = $retryCampaignIds;
                $currentBids = $retryBids;
            }
        }

        $skippedCount = count($skippedCampaignIds);
        $failedCount = count($failedCampaignIds);
        $updatedCount = max(0, $total - $skippedCount - $failedCount);

        return [
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'attempts' => $attempts,
        ];
    }

    /**
     * Decide whether retrying failures is likely useful.
     *
     * @param array<int, array<string, mixed>> $failed
     */
    private function hasRateLimitOrServerFailure(array $failed): bool
    {
        foreach ($failed as $f) {
            $err = (string) ($f['error'] ?? '');
            if (preg_match('/429|Too Many Requests|rate limit/i', $err)) {
                return true;
            }
            if (preg_match('/timeout|temporarily unavailable|server error|\\b5\\d\\d\\b|ECONN|ETIMEDOUT/i', $err)) {
                return true;
            }
        }
        return false;
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