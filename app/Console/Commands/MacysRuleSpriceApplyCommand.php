<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\MacysRuleSpriceApplyService;
use Illuminate\Console\Command;

class MacysRuleSpriceApplyCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'macys:rule-sprice-apply
        {--dry-run : Compute S PRC but do not write macy_data_view}
        {--limit= : Max SKUs (for testing)}';

    protected $description = 'Macys: clear stale SPRICE, apply Sprc Dil + A Price floor, save in the background.';

    protected string $monitorJobName = 'Macys Rule S PRC Apply';

    public function handle(MacysRuleSpriceApplyService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(MacysRuleSpriceApplyService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('Macys Rule S PRC Apply'.($dryRun ? ' [DRY RUN]' : ''));

        $summary = $service->run(
            dryRun: $dryRun,
            limit: $limit,
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg)
        );

        $stats = $summary['stats'] ?? [];
        $candidates = (int) ($stats['candidates'] ?? 0);
        $applied = (int) ($stats['applied'] ?? 0);
        $cleared = (int) ($stats['cleared'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $failed = count($stats['errors'] ?? []);

        if ($dryRun) {
            $monitor->meta['dry_run'] = true;
        }
        $monitor->markApiConnected();
        $monitor->setExpected($candidates);
        $monitor->setFetched($candidates);
        $monitor->setProcessed($applied + $cleared + $unchanged);
        $monitor->setUpdated($applied + $cleared);
        $monitor->setSkipped($unchanged);
        $monitor->setFailed($failed);

        $this->info(sprintf(
            'Done. candidates=%d applied=%d cleared=%d unchanged=%d errors=%d',
            $candidates,
            $applied,
            $cleared,
            $unchanged,
            $failed
        ));

        return ($failed > 0 && $applied === 0 && $cleared === 0 && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
