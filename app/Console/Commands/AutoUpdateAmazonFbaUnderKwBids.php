<?php

namespace App\Console\Commands;

use App\Console\Concerns\CalculatesAmazonFbaBidUpdates;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AutoUpdateAmazonFbaUnderKwBids extends Command
{
    use CalculatesAmazonFbaBidUpdates;

    protected $signature = 'amazon-fba:auto-update-under-kw-bids {--dry-run : Run without updating Amazon} {--campaign-id= : Only update this campaign ID}';
    protected $description = 'Auto-update Amazon FBA under-utilized keyword bids';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTs = microtime(true);
        $startedAtIso = now()->toIso8601String();
        $dryRun = (bool) $this->option('dry-run');
        $verbose = $this->output->isVerbose();
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

        $candidates = $this->getAutomateAmzUtilizedBgtKw();
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

                return 1;
            }
            $this->info("Testing only campaign: {$specificCampaignId}");
        }

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

        $this->info('Eligible campaigns (with bid change): ' . count($candidates));
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
                $summary = $this->applyKeywordBidUpdates($amazon, $candidates, $dryRun, $verbose);
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
     * Build bid update candidates for FBA under-utilized keyword campaigns.
     *
     * @return array<int, object> objects containing: campaign_id, sbid, ub7, ub1, l7_cpc, l1_cpc, l7_spend, l1_spend, inv
     */
    public function getAutomateAmzUtilizedBgtKw(): array
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
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            })->filter()->unique()->values()->all();
            if (empty($baseSkus)) {
                return [];
            }

            $shopifyData = ShopifySku::mapByProductSkus($baseSkus);

            // Preload enabled keyword campaign reports (exclude PT campaigns).
            $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L7')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
                ->get();

            $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L1')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
                ->get();

            $amazonSpCampaignReportsL2 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L2')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->where(function ($q) use ($sellerSkus) {
                    foreach ($sellerSkus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    }
                })
                ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
                ->get();

            $candidatesByCampaignId = [];
            $sbidRule = \App\Support\AmazonAdsSbidRule::resolvedRule();

            foreach ($fbaData as $fba) {
                $sellerSkuUpper = strtoupper(trim((string) ($fba->seller_sku ?? '')));
                if ($sellerSkuUpper === '') {
                    continue;
                }

                // Base SKU for Shopify inventory lookup.
                $baseSkuUpper = strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string) $fba->seller_sku)));
                $shopify = $shopifyData[$baseSkuUpper] ?? null;
                $inv = (int) ($shopify->inv ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim((string) ($item->campaignName ?? ''), '.')));
                    if (str_contains($cleanName, $sellerSkuUpper) === false) {
                        return false;
                    }
                    // Safety check: exclude PT.
                    return !preg_match('/\bPT\b/i', $cleanName) && strtoupper((string) ($item->campaignStatus ?? '')) === 'ENABLED';
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim((string) ($item->campaignName ?? ''), '.')));
                    if (str_contains($cleanName, $sellerSkuUpper) === false) {
                        return false;
                    }
                    // Safety check: exclude PT.
                    return !preg_match('/\bPT\b/i', $cleanName) && strtoupper((string) ($item->campaignStatus ?? '')) === 'ENABLED';
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
                    'fba_kw',
                    false,
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
                    'source' => (string) ($ruleBid['bid_out']['band'] ?? 'under'),
                    'band' => (string) ($ruleBid['bid_out']['band'] ?? 'under'),
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

                // Deduplicate by campaign_id (keep lowest ub1 = most under-utilized).
                if (!isset($candidatesByCampaignId[$candidate->campaign_id])) {
                    $candidatesByCampaignId[$candidate->campaign_id] = $candidate;
                } else {
                    $existing = $candidatesByCampaignId[$candidate->campaign_id];
                    if (floatval($candidate->ub1) < floatval($existing->ub1)) {
                        $candidatesByCampaignId[$candidate->campaign_id] = $candidate;
                    }
                }
            }

            return array_values($candidatesByCampaignId);
        } catch (\Throwable $e) {
            Log::error('Error in getAutomateAmzUtilizedBgtKw (FBA Under KW): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply bid updates for keyword campaigns with retry/backoff.
     *
     * @param array<int, object> $candidates
     * @return array{updated_count:int, skipped_count:int, failed_count:int, attempts:int}
     */
    private function applyKeywordBidUpdates(
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
            $currentBidByCampaignId = [];
            $ub1ByCampaignId = [];
            $invByCampaignId = [];
            $bidCalcByCampaignId = [];
            $campaignIds = [];
            $bids = [];
            foreach ($chunk as $c) {
                $cid = (string) $c->campaign_id;
                $bid = (float) $c->sbid;
                $bidByCampaignId[$cid] = $bid;
                $currentBidByCampaignId[$cid] = (float) ($c->current_bid ?? 0);
                $ub1ByCampaignId[$cid] = (float) ($c->ub1 ?? 0);
                $invByCampaignId[$cid] = (int) ($c->inv ?? 0);
                $bidCalcByCampaignId[$cid] = is_array($c->bid_calc ?? null) ? $c->bid_calc : [];
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
                    $result = $controller->updateAutoCampaignKeywordsBid($currentCampaignIds, $currentBids);
                } catch (\Throwable $e) {
                    $this->error("Chunk #{$chunkIndex} attempt {$attempt}: exception calling update API: " . $e->getMessage());
                    $result = ['status' => 500, 'error' => $e->getMessage(), 'failed' => []];
                }

                if (is_object($result) && method_exists($result, 'getData')) {
                    $result = $result->getData(true);
                }

                $status = is_array($result) ? (int) ($result['status'] ?? 0) : 0;
                $errMsg = is_array($result) ? (string) ($result['message'] ?? $result['error'] ?? '') : '';

                if ($status !== 200 && $status !== 207) {
                    foreach ($currentCampaignIds as $cid) {
                        $failedCampaignIds[$cid] = true;
                    }
                    $this->error("Chunk #{$chunkIndex} attempt {$attempt}: keywords API status {$status}" . ($errMsg !== '' ? " — {$errMsg}" : ''));
                    $retryable = $this->hasRateLimitOrServerFailure([['error' => $errMsg]]);
                    if (!$retryable || $attempt >= $maxRetries) {
                        break;
                    }

                    continue;
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
                    $skippedIdsThisRound = [];
                    foreach ($skipped as $s) {
                        if (!empty($s['campaign_id'])) {
                            $skippedIdsThisRound[(string) $s['campaign_id']] = true;
                        }
                    }
                    foreach ($currentCampaignIds as $cid) {
                        if (!empty($skippedIdsThisRound[$cid])) {
                            continue;
                        }
                        $old = $currentBidByCampaignId[$cid] ?? null;
                        $new = $bidByCampaignId[$cid] ?? null;
                        $bc = $bidCalcByCampaignId[$cid] ?? [];
                        Log::info('FBA Bid Update', [
                            'campaign_id' => $cid,
                            'current_bid' => $old,
                            'utilization_1d' => $ub1ByCampaignId[$cid] ?? null,
                            'inventory' => $invByCampaignId[$cid] ?? null,
                            'new_bid' => $new,
                            'bid_cpc_source' => $bc['source'] ?? null,
                            'base_cpc' => $bc['base_cpc'] ?? null,
                            'cpc_multiplier' => $bc['multiplier'] ?? null,
                            'action' => ($old !== null && $new !== null && abs((float) $old - (float) $new) >= 0.005) ? 'UPDATED' : 'NO_CHANGE',
                        ]);
                        if ($verbose) {
                            $this->info("✓ Updated campaign {$cid} bid {$old} → {$new} (" . $this->describeBidCpcSource($bc) . ')');
                        }
                    }
                    if ($verbose) {
                        $this->info("Chunk #{$chunkIndex} succeeded (HTTP {$status}).");
                    }
                    break;
                }

                $retryable = $this->hasRateLimitOrServerFailure($failed);
                if (!$retryable || $attempt >= $maxRetries) {
                    if ($verbose) {
                        $this->warn("Chunk #{$chunkIndex} stopping retries. retryable=" . ($retryable ? 'true' : 'false'));
                    }
                    break;
                }

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

                if ($verbose) {
                    $this->warn("Chunk #{$chunkIndex}: failed=" . count($failed) . ', retrying subset...');
                }
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