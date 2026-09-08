<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\PurchasingPowerRuleSpriceApplyService;
use Illuminate\Console\Command;

class PurchasingPowerRuleSpriceApplyCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'purchasing-power:rule-sprice-apply
        {--dry-run : Compute S PRC but do not write or push}
        {--no-push : Save S PRC without MCM price push}
        {--limit= : Max SKUs (for testing)}';

    protected $description = 'Purchasing Power: apply Sprc Dil, save SPRICE, push listed price via MCM.';

    protected string $monitorJobName = 'Purchasing Power Rule S PRC Apply';

    public function handle(PurchasingPowerRuleSpriceApplyService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(PurchasingPowerRuleSpriceApplyService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $push = ! (bool) $this->option('no-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('Purchasing Power Rule S PRC Apply'.($dryRun ? ' [DRY RUN]' : '').($push ? '' : ' [NO PUSH]'));

        $summary = $service->run(
            dryRun: $dryRun,
            push: $push,
            limit: $limit,
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg)
        );

        $stats = $summary['stats'] ?? [];
        $candidates = (int) ($stats['candidates'] ?? 0);
        $applied = (int) ($stats['applied'] ?? 0);
        $cleared = (int) ($stats['cleared'] ?? 0);
        $pushed = (int) ($stats['pushed'] ?? 0);
        $pushFailed = (int) ($stats['push_failed'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $failed = count($stats['errors'] ?? []);

        if ($dryRun) {
            $monitor->meta['dry_run'] = true;
        }
        $monitor->markApiConnected();
        $monitor->setExpected($candidates);
        $monitor->setFetched($candidates);
        $monitor->setProcessed($applied + $cleared + $unchanged);
        $monitor->setUpdated($applied + $cleared + $pushed);
        $monitor->setSkipped($unchanged);
        $monitor->setFailed($failed + $pushFailed);

        $this->info(sprintf(
            'Done. candidates=%d applied=%d cleared=%d pushed=%d push_failed=%d unchanged=%d errors=%d',
            $candidates,
            $applied,
            $cleared,
            $pushed,
            $pushFailed,
            $unchanged,
            $failed
        ));

        return ($failed > 0 && $applied === 0 && $cleared === 0 && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
