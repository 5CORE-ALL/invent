<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\TopDawgSprcDilAutoPushService;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Twice daily 4:30 AM and 8:30 PM IST: TopDawg Std prc vs dil (Age/Dil/B/0 Sold/CVR/Rev Disc,
 * no ship, no LMP) → SPRICE → TopDawg price API. Page not required.
 * Only pushes SKUs whose target price differs from the live TD Price. TopDawg queues a push for review (1–24h),
 * so a price already pushed is not pushed again here (topdawg:push-blue-prices handles the catch-up).
 */
class TopDawgSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'topdawg:sprc-dil-auto-push
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to TopDawg}
        {--skip-push : Save SPRICE, but skip the TopDawg push}
        {--push-all : Push every eligible SKU (ignore the last-pushed match and cooldown)}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between TopDawg price API calls (ms)}';

    protected $description = 'TopDawg Std prc vs dil (no ship, no LMP) → SPRICE → TopDawg (4:30 AM + 8:30 PM IST).';

    protected string $monitorJobName = 'TopDawg Sprc Dil Auto Push';

    public function handle(TopDawgSprcDilAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(TopDawgSprcDilAutoPushService $service, CronExecutionContext $monitor): int
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
        $this->info('TopDawg Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:30 and 20:30 Asia/Kolkata (IST)');
        $this->info('Rules: topdawg_std_prc_vs_dil (Age/Dil/B/0 Sold/CVR/Rev Disc)');
        $this->info('Push: TopDawg price API — only when Price differs from target'.($pushAll ? ' [PUSH ALL]' : ''));
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
            'Done. candidates=%d applied=%d pushed=%d unchanged=%d repaired=%d failed=%d',
            $totalExpected,
            $totalApplied,
            $totalPushed,
            $unchanged,
            (int) ($stats['price_repaired'] ?? 0),
            $totalFailed
        ));

        return ($totalFailed > 0 && $totalPushed === 0 && ! $dryRun && ! $skipPush)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
