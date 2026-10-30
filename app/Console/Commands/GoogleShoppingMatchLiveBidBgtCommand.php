<?php

namespace App\Console\Commands;

use App\Models\CronExecutionLog;
use App\Services\CronMonitor\CronMonitorService;
use App\Services\GoogleShoppingLiveMatchService;
use App\Support\SchedulerLease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GoogleShoppingMatchLiveBidBgtCommand extends Command
{
    public const LEASE = 'google-shopping:match-live-bid-bgt';

    protected $signature = 'google-shopping:match-live-bid-bgt
        {--campaign-id= : Only this campaign ID}
        {--retry-failed : Also push campaigns whose last push did not stick (otherwise they wait 3 hours)}
        {--dry-run : Pull and report the mismatches, push nothing}';

    protected $description = 'Google Shopping: pull live bid/budget, push SBID/SBGT to every ENABLED campaign that differs, pull again to confirm. No page needed.';

    public function handle(GoogleShoppingLiveMatchService $match): int
    {
        // One run at a time, enforced by a database lease that is renewed while the run works and
        // expires by itself if the process dies (see SchedulerLease). No manual unlock is ever needed.
        if (! Schema::hasTable('scheduler_leases')) {
            $this->error('Table scheduler_leases is missing. Run: php artisan migrate');
            Log::error('[GoogleShoppingLiveMatch] not started: scheduler_leases table missing (run php artisan migrate)');

            return self::FAILURE;
        }

        $lease = SchedulerLease::acquire(self::LEASE);
        if ($lease === null) {
            $this->warn('Another Google Shopping live match is already running.');
            Log::info('[GoogleShoppingLiveMatch] skipped', ['reason' => 'lease held by a live run']);
            $this->reportToMonitor(CronExecutionLog::STATUS_SKIPPED, null, 'lease held by a live run');

            return self::SUCCESS;
        }
        // Renews the lease even while this process is blocked in a slow Google Ads call.
        $lease->startBackgroundHeartbeat();
        $lease->guardProcess();

        $status = 'failed';
        try {
            @ini_set('memory_limit', '512M');
            $campaign = $this->option('campaign-id');
            $stats = $match->run(
                (bool) $this->option('dry-run'),
                $campaign !== null && $campaign !== '' ? (string) $campaign : null,
                (bool) $this->option('retry-failed'),
                $lease
            );
            $status = $stats['status'];
        } catch (Throwable $e) {
            Log::error('[GoogleShoppingLiveMatch] crashed', ['execution' => $lease->token, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            $lease->release($status);
        }

        $this->info('Enabled shopping campaigns checked: '.$stats['checked']);
        $this->info('SBID differs from live: '.$stats['bid_mismatch'].' | SBGT differs from live: '.$stats['bgt_mismatch']);
        if (! $this->option('dry-run')) {
            $this->info('After push: SBID matched '.$stats['bid_pushed'].', still different '.$stats['bid_left']
                .' | SBGT matched '.$stats['bgt_pushed'].', still different '.$stats['bgt_left']);
        }
        if ($stats['waiting'] > 0) {
            $this->info('Waiting before the next try (last push did not stick): '.$stats['waiting'].' — use --retry-failed to push them now.');
        }
        foreach ($stats['push_errors'] as $err) {
            $this->warn($err);
        }
        foreach ($stats['failed'] as $key => $reason) {
            $this->warn("Failed {$key}: {$reason}");
        }
        $sum = $stats['summary'];
        $line = 'Run '.$stats['execution'].' finished: status='.$stats['status']
            .' | checked '.$sum['checked'].' | mismatches SBID '.$sum['sbid_mismatch'].' SBGT '.$sum['sbgt_mismatch']
            .' | pushed '.$sum['pushed'].' | confirmed '.$sum['confirmed'].' | held '.$sum['held']
            .' | skipped '.$sum['skipped'].' | busy '.$sum['busy'].' | failed '.$sum['failed'];
        $stats['status'] === GoogleShoppingLiveMatchService::STATUS_SUCCESS ? $this->info($line) : $this->warn($line);

        $this->reportToMonitor($stats['status'], $stats);

        if ($stats['error'] !== null) {
            $this->error($stats['error']);
        }

        // A run-level error, or every operation failing, is a failed run. Partial success exits 0 on purpose:
        // the status above (and the Cron Monitor) carries it, and a non-zero exit would be shown as plain "failed".
        return $stats['status'] === GoogleShoppingLiveMatchService::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Tell the Cron Monitor how the run really ended (success / partial_success / failed / skipped) and with which
     * counts, so the dashboard does not have to guess from the exit code. Monitoring must never break the job.
     *
     * @param  array<string, mixed>|null  $stats
     */
    private function reportToMonitor(string $status, ?array $stats, ?string $skippedReason = null): void
    {
        try {
            $ctx = app(CronMonitorService::class)->context();
            if ($ctx === null) {
                return;
            }
            $meta = ['result_status' => $status];
            if ($skippedReason !== null) {
                $meta += ['skipped' => true, 'skipped_reason' => $skippedReason];
            }
            if ($stats !== null) {
                $sum = $stats['summary'];
                $ok = $sum['confirmed'];
                $attempted = $ok + $sum['failed'];
                $meta += [
                    'summary' => $sum,
                    'execution' => $stats['execution'],
                    'success_percentage' => $attempted > 0 ? round($ok / $attempted * 100, 2) : 100.0,
                ];
                $ctx->setFetched($sum['checked'])
                    ->setProcessed($sum['checked'])
                    ->setUpdated($sum['confirmed'])
                    ->setSkipped($sum['skipped'] + $sum['busy'] + $sum['held'])
                    ->setFailed($sum['failed']);
                if ($status === GoogleShoppingLiveMatchService::STATUS_FAILED) {
                    $ctx->rootCause = $stats['error'] ?? "Live match failed: {$sum['failed']} of {$attempted} pushes did not stick.";
                }
            }
            $ctx->mergeMeta($meta);
        } catch (Throwable) {
            // monitoring must never break the job
        }
    }
}
