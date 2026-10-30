<?php

namespace App\Console\Commands;

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
            $this->markSkipped('lease held by a live run');

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
            $status = $stats['error'] === null ? 'success' : 'failed';
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
        if ($stats['error'] !== null) {
            $this->error($stats['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Tag the CronMonitor log so a skipped run is not mistaken for a run that did the work. */
    private function markSkipped(string $reason): void
    {
        try {
            app(CronMonitorService::class)->context()?->mergeMeta(['skipped' => true, 'skipped_reason' => $reason]);
        } catch (Throwable) {
            // monitoring must never break the job
        }
    }
}
