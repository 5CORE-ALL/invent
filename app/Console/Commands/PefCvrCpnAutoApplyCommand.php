<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\PefCvrCpnAutoApplyService;
use Illuminate\Console\Command;

/**
 * Daily midnight CVR vs CPN auto-apply (eBay1 Coupon API), after Dil/PRMT price window.
 * Runs whether or not CVR/rules changed; CPN%=0 pauses coupon.
 */
class PefCvrCpnAutoApplyCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'pef:cvr-cpn-auto-apply
        {--dry-run : Compute CVR→CPN but do not call eBay Coupon API}
        {--limit= : Max eBay1 SKUs (for testing)}
        {--sleep-ms=250 : Delay between eBay API calls (ms)}';

    protected $description = 'PEF CVR vs CPN: apply saved (or default) rules to eBay1 coupons daily at midnight IST after Dil/PRMT. CPN%=0 pauses.';

    protected string $monitorJobName = 'PEF CVR vs CPN Auto Apply';

    public function handle(PefCvrCpnAutoApplyService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(PefCvrCpnAutoApplyService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('PEF CVR vs CPN Auto Apply'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: daily 00:30 Asia/Kolkata (after Dil/PRMT @ 00:00; always runs)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $stats = $service->run(
            dryRun: $dryRun,
            limit: $limit,
            sleepMs: $sleepMs,
            logger: fn (string $msg) => $this->line($msg)
        );

        $monitor->markApiConnected();
        $monitor->setExpected((int) $stats['candidates']);
        $monitor->setFetched((int) $stats['candidates']);
        $monitor->setProcessed((int) $stats['ok'] + (int) $stats['failed']);
        $monitor->setUpdated((int) $stats['ok']);
        $monitor->setFailed((int) $stats['failed']);

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. candidates=%d ok=%d paused=%d failed=%d skipped=%d',
            $stats['candidates'],
            $stats['ok'],
            $stats['paused'],
            $stats['failed'],
            $stats['skipped']
        ));

        return ($stats['failed'] > 0 && $stats['ok'] === 0 && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
