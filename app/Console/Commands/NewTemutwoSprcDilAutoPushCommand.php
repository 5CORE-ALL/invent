<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\NewTemutwoSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Twice daily 4:20 AM and 8:20 PM IST: New Temu Two Std prc vs dil (Age/Dil/B/0 Sold/CVR/Rev Disc,
 * Min NPFT, eBay/Amz/LMP cap) → S PRC → Temu 2 base price API. Page not required.
 * Only pushes SKUs whose target Base differs from the live Base.
 */
class NewTemutwoSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'newtemutwo:sprc-dil-auto-push
        {--dry-run : Compute only. Do NOT save S PRC and do NOT push to Temu}
        {--skip-push : Save S PRC, but skip the Temu push}
        {--push-all : Push every eligible SKU (ignore the last-pushed match and cooldown)}
        {--limit= : Max SKUs (for testing)}
        {--sku=* : Only these SKUs (for testing)}
        {--sleep-ms=300 : Delay between Temu price API calls (ms)}';

    protected $description = 'New Temu Two Std prc vs dil (Min NPFT, eBay/Amz/LMP cap) → S PRC → Temu 2 (4:20 AM + 8:20 PM IST).';

    protected string $monitorJobName = 'New Temu Two Sprc Dil Auto Push';

    public function handle(NewTemutwoSprcDilAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(NewTemutwoSprcDilAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $pushAll = (bool) $this->option('push-all');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('New Temu Two Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:20 and 20:20 Asia/Kolkata (IST)');
        $this->info('Rules: newtemutwo_std_prc_vs_dil (Age/Dil/B/0 Sold/CVR/Rev Disc) + Min NPFT + eBay/Amz/LMP cap');
        $this->info('Push: Temu 2 base price API — only when Base differs from target'.($pushAll ? ' [PUSH ALL]' : ''));
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            skipPush: $skipPush,
            limit: $limit,
            sleepMs: $sleepMs,
            onlySkus: $skus !== [] ? $skus : null,
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
