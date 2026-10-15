<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\PurchasingPowerSprcDilAutoPushService;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Twice daily 4:35 AM and 8:35 PM IST: Purchasing Power Std prc vs dil (Age/Dil/B/0 Sold/CVR/Rev Disc,
 * Amz rule) → SPRICE → MCM price import. Page not required.
 * Only pushes SKUs whose target price differs from the live PP Price and from the last pushed price.
 * purchasing-power:push-blue-prices handles the 10-minute catch-up.
 */
class PurchasingPowerSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'purchasing-power:sprc-dil-auto-push
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to Purchasing Power}
        {--skip-push : Save SPRICE, but skip the Purchasing Power push}
        {--push-all : Push every eligible SKU (ignore the last-pushed match and cooldown)}
        {--sku=* : Only these SKUs (repeat the option). Prints the inputs and the S PRC result per SKU}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between Purchasing Power price pushes (ms)}';

    protected $description = 'Purchasing Power Std prc vs dil (Amz rule) → SPRICE → MCM (4:35 AM + 8:35 PM IST).';

    protected string $monitorJobName = 'Purchasing Power Sprc Dil Auto Push';

    public function handle(PurchasingPowerSprcDilAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(PurchasingPowerSprcDilAutoPushService $service, CronExecutionContext $monitor): int
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
        $this->info('Purchasing Power Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:35 and 20:35 Asia/Kolkata (IST)');
        $this->info('Rules: purchasing_power_std_prc_vs_dil (Age/Dil/B/0 Sold/CVR/Rev Disc) + Amz rule (never below A Price)');
        $this->info('Push: MCM price import — only when Price differs from target'.($pushAll ? ' [PUSH ALL]' : ''));
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            skipPush: $skipPush,
            limit: $limit,
            sleepMs: $sleepMs,
            onlySkus: array_values(array_filter((array) $this->option('sku'))) ?: null,
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
