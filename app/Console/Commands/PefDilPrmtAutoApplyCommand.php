<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\PefDilPrmtAutoApplyService;
use Illuminate\Console\Command;

/**
 * Daily midnight Dil vs PRMT auto-apply (eBay1 Promotion API).
 * Runs whether or not Dil/INV/rules changed; INV=0 → PRMT%=0 (pause).
 */
class PefDilPrmtAutoApplyCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'pef:dil-prmt-auto-apply
        {--dry-run : Compute Dil→PRMT but do not call eBay Promotion API}
        {--limit= : Max eBay1 SKUs (for testing)}
        {--sleep-ms=250 : Delay between eBay API calls (ms)}';

    protected $description = 'PEF Dil vs PRMT: apply saved (or default) rules to eBay1 promotions at least once daily at midnight IST. INV=0 → PRMT%=0.';

    protected string $monitorJobName = 'PEF Dil vs PRMT Auto Apply';

    public function handle(PefDilPrmtAutoApplyService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(PefDilPrmtAutoApplyService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('PEF Dil vs PRMT Auto Apply'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: daily 00:00 Asia/Kolkata (always runs, even if unchanged)');
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
