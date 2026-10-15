<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\AliexpressSprcDilAutoPushService;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Twice daily 4:15 AM and 8:15 PM IST: AliExpress Std prc vs dil (Age/Dil/B/0 Sold/CVR/Rev Disc,
 * LMP cap) → SPRICE → AliExpress price API. Page not required.
 * Only pushes SKUs whose target price differs from the live Price. Price column updates on each successful push.
 */
class AliexpressSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'aliexpress:sprc-dil-auto-push
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to AliExpress}
        {--skip-push : Save SPRICE, but skip the AliExpress push}
        {--push-all : Push every eligible SKU (ignore the last-pushed match and cooldown)}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between AliExpress price API batches (ms)}';

    protected $description = 'AliExpress Std prc vs dil (LMP cap) → SPRICE → AliExpress (4:15 AM + 8:15 PM IST).';

    protected string $monitorJobName = 'AliExpress Sprc Dil Auto Push';

    public function handle(AliexpressSprcDilAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(AliexpressSprcDilAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $pushAll = (bool) $this->option('push-all');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('AliExpress Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:15 and 20:15 Asia/Kolkata (IST)');
        $this->info('Rules: aliexpress_std_prc_vs_dil (Age/Dil/B/0 Sold/CVR/Rev Disc) + LMP cap');
        $this->info('Push: AliExpress price API — only when Price differs from target'.($pushAll ? ' [PUSH ALL]' : ''));
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            skipPush: $skipPush,
            limit: $limit,
            sleepMs: $sleepMs,
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg),
            pushAll: $pushAll
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
