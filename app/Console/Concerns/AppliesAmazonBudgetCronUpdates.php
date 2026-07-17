<?php

namespace App\Console\Concerns;

use App\Models\AmazonAdsPushLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Shared guards for Amazon KW/PT/HL SBGT budget crons:
 * skip already-applied budgets, parse API outcome, persist local BGT, log Fail Cpg rows.
 */
trait AppliesAmazonBudgetCronUpdates
{
    /**
     * @param  Collection<int, object>  $validCampaigns  rows with campaign_id, campaignName, sbgt, optional current_bgt
     * @param  callable(list<string>, list<float>): array  $updater
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
        $toPush = [];
        $unchanged = 0;
        $pushLogs = [];

        foreach ($validCampaigns as $campaign) {
            $cid = trim((string) ($campaign->campaign_id ?? ''));
            $name = (string) ($campaign->campaignName ?? '');
            $sbgt = (float) ($campaign->sbgt ?? 0);
            $current = isset($campaign->current_bgt) ? (float) $campaign->current_bgt : null;

            if ($cid === '' || $sbgt <= 0) {
                continue;
            }

            if ($current !== null && abs($current - $sbgt) < 0.005) {
                $unchanged++;
                $pushLogs[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'value' => $sbgt,
                    'status' => 'skipped',
                    'reason' => 'Already at SBGT (current BGT $'.number_format($current, 2).')',
                ];
                continue;
            }

            $toPush[] = (object) [
                'campaign_id' => $cid,
                'campaignName' => $name,
                'sbgt' => $sbgt,
                'current_bgt' => $current,
            ];
        }

        if ($toPush === []) {
            $this->info("No budget deltas to push ({$unchanged} already at SBGT).");
            if ($pushLogs !== []) {
                AmazonAdsPushLog::logBatch($pushType, $pushLogs, $sourceLabel);
            }

            return ['exit_code' => 0, 'pushed' => 0, 'unchanged' => $unchanged, 'failed' => 0];
        }

        $this->info('Budgets to change: '.count($toPush).' | Already at SBGT: '.$unchanged);

        if ($dryRun) {
            foreach ($toPush as $c) {
                $from = $c->current_bgt !== null ? '$'.number_format((float) $c->current_bgt, 2) : 'n/a';
                $this->line("  {$c->campaignName}: {$from} → \${$c->sbgt}");
            }
            $this->warn('DRY RUN - No updates were made to Amazon.');

            return ['exit_code' => 0, 'pushed' => 0, 'unchanged' => $unchanged, 'failed' => 0];
        }

        $ids = array_map(static fn ($c) => (string) $c->campaign_id, $toPush);
        $bgts = array_map(static fn ($c) => (float) $c->sbgt, $toPush);
        $byId = [];
        foreach ($toPush as $c) {
            $byId[(string) $c->campaign_id] = $c;
        }

        $result = $updater($ids, $bgts);
        $status = (int) ($result['status'] ?? 500);
        $successIds = array_map('strval', $result['success_ids'] ?? []);
        $failedRows = is_array($result['failed'] ?? null) ? $result['failed'] : [];

        // Legacy callers: HTTP 200 with no parsed lists → treat all as success
        if ($successIds === [] && $failedRows === [] && $status === 200) {
            $successIds = $ids;
        }

        if ($successIds !== []) {
            $this->persistLocalCampaignBudgets($reportTable, $successIds, $byId);
        }

        foreach ($successIds as $cid) {
            $c = $byId[$cid] ?? null;
            $pushLogs[] = [
                'campaign_id' => $cid,
                'campaign_name' => $c->campaignName ?? null,
                'value' => $c->sbgt ?? null,
                'status' => 'success',
                'reason' => 'Budget updated to SBGT',
                'http_status' => $status,
                'response_data' => ['source' => $sourceLabel],
            ];
        }

        foreach ($failedRows as $f) {
            $cid = (string) ($f['campaign_id'] ?? '');
            $c = $byId[$cid] ?? null;
            $reason = (string) ($f['reason'] ?? 'Unknown Amazon error');
            $pushLogs[] = [
                'campaign_id' => $cid,
                'campaign_name' => $c->campaignName ?? null,
                'value' => $c->sbgt ?? null,
                'status' => 'failed',
                'reason' => $reason,
                'http_status' => $status,
                'response_data' => $f,
            ];
            $this->error("FAILED {$cid} ".($c->campaignName ?? '').": {$reason}");
        }

        if ($pushLogs !== []) {
            AmazonAdsPushLog::logBatch($pushType, $pushLogs, $sourceLabel);
        }

        $pushed = count($successIds);
        $failed = count($failedRows);
        $this->info("Pushed: {$pushed} | Unchanged: {$unchanged} | Failed: {$failed}");

        if ($failed > 0) {
            Log::error("{$sourceLabel}: budget cron finished with failures", [
                'pushed' => $pushed,
                'failed' => $failed,
                'failed_ids' => array_column($failedRows, 'campaign_id'),
            ]);

            return ['exit_code' => 1, 'pushed' => $pushed, 'unchanged' => $unchanged, 'failed' => $failed];
        }

        return ['exit_code' => 0, 'pushed' => $pushed, 'unchanged' => $unchanged, 'failed' => 0];
    }

    /**
     * @param  list<string>  $successIds
     * @param  array<string, object>  $byId
     */
    protected function persistLocalCampaignBudgets(string $reportTable, array $successIds, array $byId): void
    {
        if ($successIds === [] || ! Schema::hasTable($reportTable) || ! Schema::hasColumn($reportTable, 'campaignBudgetAmount')) {
            return;
        }

        $ranges = ['L30', 'L15', 'L7', 'L1'];
        $latestDaily = DB::table($reportTable)
            ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max(DB::raw('LEFT(TRIM(report_date_range), 10)'));
        if (is_string($latestDaily) && $latestDaily !== '') {
            $ranges[] = $latestDaily;
        }

        foreach ($successIds as $cid) {
            $bgt = isset($byId[$cid]) ? round((float) $byId[$cid]->sbgt, 2) : null;
            if ($bgt === null || $bgt <= 0) {
                continue;
            }
            DB::table($reportTable)
                ->where('campaign_id', $cid)
                ->whereIn('report_date_range', $ranges)
                ->update([
                    'campaignBudgetAmount' => $bgt,
                    'updated_at' => now(),
                ]);
        }
    }
}
