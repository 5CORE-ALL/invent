<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\Ebay1VolumePricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Twice daily 05:30 and 21:30 IST: eBay 1 volume pricing (Buy 2 / Buy 3 / Buy 4 from Weight + Dil + Std NPFT).
 * Recomputes every listing from the saved rules and pushes only promotions whose listings changed.
 * /ebay-volume-pricing does not need to be open. Runs only when the page has Rules On and Auto push On.
 */
class Ebay1VolumePricingAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'ebay1:volume-pricing-auto-push
        {--dry-run : Work out the promotions and show what would change. Do NOT call eBay}
        {--force : Run even when Auto push is off, and re-send every promotion (ignore the saved fingerprint)}';

    protected $description = 'eBay 1 volume pricing (Weight + Dil + Std NPFT) → eBay Buy 2/3/4 promotions (05:30 + 21:30 IST).';

    protected string $monitorJobName = 'eBay 1 Volume Pricing Auto Push';

    public const LOCK_KEY = 'ebay1_volume_pricing_auto_push_lock';

    public const PENDING_KEY = 'ebay1_volume_pricing_auto_push_pending';

    public function handle(Ebay1VolumePricingService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(Ebay1VolumePricingService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('eBay 1 Volume Pricing Auto Push'.($dryRun ? ' [DRY RUN]' : '').($force ? ' [FORCE]' : ''));

        // One run at a time (cron + a rules save can both start one). A save that arrives while a run is
        // going leaves a note, and the running command makes one more pass so the new rules are used.
        $lock = $dryRun ? null : Cache::lock(self::LOCK_KEY, 3 * 3600);
        if ($lock !== null && ! $lock->get()) {
            Cache::put(self::PENDING_KEY, true, 3600);
            $this->info('Another volume pricing run is in progress. It will pick up the latest rules when it finishes.');
            $monitor->markApiConnected();

            return self::SUCCESS;
        }

        try {
            $summary = ['status' => 'off', 'message' => '', 'listings' => 0, 'pushed' => 0, 'groups' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => []];
            for ($pass = 1; $pass <= 3; $pass++) {
                Cache::forget(self::PENDING_KEY);
                $pass1 = $service->autoRun($dryRun, $force, fn (string $msg) => $this->line($msg));
                $this->info($pass1['message']);
                $summary = [
                    'status' => $pass1['status'],
                    'message' => $pass1['message'],
                    'listings' => max($summary['listings'], $pass1['listings']),
                    'pushed' => $summary['pushed'] + $pass1['pushed'],
                    'groups' => $pass1['groups'],
                    'unchanged' => $pass1['unchanged'],
                    'skipped' => $pass1['skipped'],
                    'errors' => array_merge($summary['errors'], $pass1['errors']),
                ];
                if ($dryRun || ! Cache::pull(self::PENDING_KEY)) {
                    break;
                }
                $this->info('Rules were saved during the run. Running again with the new rules.');
            }
        } finally {
            $lock?->release();
        }
        foreach (array_slice($summary['errors'], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        if ($dryRun) {
            $monitor->meta['dry_run'] = true;
        }
        $monitor->markApiConnected();
        $monitor->setExpected($summary['listings']);
        $monitor->setFetched($summary['listings']);
        $monitor->setProcessed($summary['listings']);
        $monitor->setUpdated($summary['pushed']);
        $monitor->setSkipped($summary['skipped']);
        $monitor->setFailed(count($summary['errors']));

        return $summary['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
