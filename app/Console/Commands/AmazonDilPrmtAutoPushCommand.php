<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\AmazonDilPrmtAutoPushService;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Daily 4 AM America/New_York: Dil vs PRMT rules → SPRICE → Amazon Listings API.
 * Only pushes SKUs whose target price changed.
 */
class AmazonDilPrmtAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'amazon:dil-prmt-auto-push
        {--dry-run : Compute + save SPRICE, but do NOT push to Amazon}
        {--skip-push : Skip Amazon push (same as dry-run for the push step)}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between Amazon Listings API calls (ms)}';

    protected $description = 'Dil vs PRMT: refresh SPRICE from shared rules and push changed prices to Amazon (daily 4 AM EST/EDT).';

    protected string $monitorJobName = 'Amazon Dil vs PRMT Auto Push';

    public function handle(AmazonDilPrmtAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(AmazonDilPrmtAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Amazon Dil vs PRMT Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: daily 04:00 America/New_York (EST/EDT)');
        $this->info('Rules: dil_vs_prmt_shared (all marketplaces)');
        $this->info('Push: Amazon Listings our_price — only when price changed');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            skipPush: $skipPush,
            limit: $limit,
            sleepMs: $sleepMs,
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg)
        );

        $stats = $summary['stats'] ?? [];
        $totalExpected = (int) ($stats['candidates'] ?? 0);
        $totalApplied = (int) ($stats['applied'] ?? 0);
        $totalPushed = (int) ($stats['pushed'] ?? 0);
        $totalFailed = (int) ($stats['push_failed'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $changedOk = $dryRun || $skipPush ? $totalApplied : $totalPushed;

        if ($dryRun || $skipPush) {
            $monitor->meta['dry_run'] = true;
        }

        $monitor->markApiConnected();
        $monitor->setExpected($totalExpected);
        $monitor->setFetched($totalExpected);
        $monitor->setProcessed($totalApplied + $unchanged);
        // Unchanged SKUs are intentional skips (only push changed numbers) — count as OK.
        $monitor->setUpdated($changedOk + $unchanged);
        $monitor->setSkipped($unchanged);
        $monitor->setFailed($totalFailed);

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. candidates=%d applied=%d pushed=%d unchanged=%d failed=%d',
            $totalExpected,
            $totalApplied,
            $totalPushed,
            $unchanged,
            $totalFailed
        ));

        return ($totalFailed > 0 && $totalPushed === 0 && ! $dryRun && ! $skipPush)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
