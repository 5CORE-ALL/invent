<?php

namespace App\Console\Concerns;

use App\Services\AmazonAdsLiveBidBgtSyncService;
use App\Support\AmazonAdsDesiredSbgtResolver;
use App\Support\AmazonAdsSbgt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * KW/PT/HL SBGT crons: pull live Amazon BGT, compare to 6-part SBGT, push+verify.
 * Local campaignBudgetAmount is written only after a verified live match.
 */
trait AppliesAmazonBudgetCronUpdates
{
    /**
     * @param  Collection<int, object>  $validCampaigns  rows with campaign_id, campaignName, sbgt, optional current_bgt
     * @param  callable(list<string>, list<float>): array  $updater  unused; kept for existing cron call sites
     * @return array{exit_code: int, pushed: int, unchanged: int, failed: int}
     */
    protected function applyAmazonBudgetCronUpdates(
        Collection $validCampaigns,
        callable $updater,
        string $reportTable,
        string $pushType,
        string $sourceLabel,
        bool $dryRun
    ): array {
        unset($updater, $reportTable);
        $channel = str_starts_with($pushType, 'sb_') ? 'sb' : 'sp';
        $desiredByCid = [];
        try {
            $desiredByCid = AmazonAdsDesiredSbgtResolver::sbgtForCampaigns($validCampaigns);
        } catch (Throwable $e) {
            Log::warning('amazon-ads live sync: 6-part SBGT resolver failed, using cron SBGT', [
                'source' => $sourceLabel,
                'error' => $e->getMessage(),
            ]);
        }

        $idToBgt = [];
        $names = [];
        foreach ($validCampaigns as $campaign) {
            $cid = trim((string) ($campaign->campaign_id ?? ''));
            if ($cid === '') {
                continue;
            }
            $resolved = $desiredByCid[$cid] ?? null;
            $sbgt = $resolved !== null ? $resolved : ($campaign->sbgt ?? null);
            if ($sbgt === null || $sbgt === '') {
                continue;
            }
            if (AmazonAdsSbgt::isExplicitZero($sbgt)) {
                $idToBgt[$cid] = 0.0;
            } elseif (AmazonAdsSbgt::parsePushableBudget($sbgt) !== null) {
                $idToBgt[$cid] = (float) AmazonAdsSbgt::parsePushableBudget($sbgt);
            } else {
                continue;
            }
            $names[$cid] = (string) ($campaign->campaignName ?? '');
        }

        if ($idToBgt === []) {
            $this->info('No campaigns with a pushable SBGT.');

            return ['exit_code' => 0, 'pushed' => 0, 'unchanged' => 0, 'failed' => 0];
        }

        $this->info('Live BGT sync candidates: '.count($idToBgt));

        if ($dryRun) {
            foreach ($idToBgt as $cid => $sbgt) {
                $this->line('  '.($names[$cid] ?? $cid).': SBGT $'.$sbgt);
            }
            $this->warn('DRY RUN - No updates were made to Amazon.');

            return ['exit_code' => 0, 'pushed' => 0, 'unchanged' => 0, 'failed' => 0];
        }

        $outcome = app(AmazonAdsLiveBidBgtSyncService::class)->syncBudgetMap($channel, $idToBgt, $names, $sourceLabel);
        $pushed = count($outcome['updated_ids'] ?? []);
        $failed = count($outcome['failed'] ?? []);
        $unchanged = count($outcome['skipped'] ?? []);
        foreach ($outcome['failed'] ?? [] as $f) {
            $this->error('FAILED '.($f['campaign_id'] ?? '').': '.($f['reason'] ?? $f['error'] ?? 'sync failed'));
        }
        $this->info("Verified: {$pushed} | Skipped: {$unchanged} | Failed: {$failed}");

        if ($failed > 0) {
            Log::error("{$sourceLabel}: live BGT sync finished with failures", [
                'pushed' => $pushed,
                'failed' => $failed,
                'failed_ids' => array_column($outcome['failed'] ?? [], 'campaign_id'),
            ]);

            return ['exit_code' => 1, 'pushed' => $pushed, 'unchanged' => $unchanged, 'failed' => $failed];
        }

        return ['exit_code' => 0, 'pushed' => $pushed, 'unchanged' => $unchanged, 'failed' => 0];
    }
}
