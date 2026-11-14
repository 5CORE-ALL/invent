<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\PlsSprcDilAutoPushService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;

/**
 * Twice daily 07:45 and 23:45 IST: PLS Std prc vs dil (Age/Dil/B/0 Sold/CVR/Rev Disc,
 * Ship, no ads, no LMP) → SPRICE → ProLightSounds Shopify price. Page not required.
 * Same as shopify-b2c:sprc-dil-auto-push: saves S PRC, then queues SKUs whose saved S PRC differs from the live PLS Price
 * for the shared S PRC worker. Negative SNROI is never pushed.
 * pls:push-blue-prices handles the 10-minute catch-up.
 */
class PlsSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'pls:sprc-dil-auto-push
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to PLS}
        {--skip-push : Save SPRICE, but skip the PLS push}
        {--limit= : Max SKUs (for testing)}
        {--sku=* : Only these SKUs (for testing)}';

    protected $description = 'PLS Std prc vs dil (Ship, no ads, no LMP) → SPRICE → PLS (07:45 + 23:45 IST).';

    protected string $monitorJobName = 'PLS Sprc Dil Auto Push';

    public function handle(PlsSprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $enqueue, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(PlsSprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('PLS Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 07:45 and 23:45 Asia/Kolkata (IST)');
        $this->info('Rules: pls_std_prc_vs_dil (Age/Dil/B/0 Sold/CVR/Rev Disc)');
        $this->info('Push: ProLightSounds Shopify price — only when S PRC differs from Price');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            limit: $limit,
            onlySkus: $skus !== [] ? $skus : null,
            logger: fn (string $msg) => $this->line($msg)
        );

        $stats = $summary['stats'] ?? [];
        $candidates = (int) ($stats['candidates'] ?? 0);
        $applied = (int) ($stats['applied'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $failed = count($stats['errors'] ?? []);

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        $queued = 0;
        if (! $dryRun && ! $skipPush) {
            $res = $enqueue->enqueueChannel('pls');
            $queued = (int) ($res['queued'] ?? 0);
            $this->info('PLS push: '.$res['message'].(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        if ($dryRun || $skipPush) {
            $monitor->meta['dry_run'] = true;
        }
        $monitor->meta['push_queued'] = $queued;
        $monitor->markApiConnected();
        $monitor->setExpected($candidates);
        $monitor->setFetched($candidates);
        $monitor->setProcessed($applied + $unchanged);
        $monitor->setUpdated($applied + $unchanged);
        $monitor->setSkipped($unchanged);
        $monitor->setFailed($failed);

        $this->newLine();
        $this->info(sprintf(
            'Done. candidates=%d applied=%d unchanged=%d queued=%d errors=%d',
            $candidates,
            $applied,
            $unchanged,
            $queued,
            $failed
        ));

        return ($failed > 0 && $applied === 0 && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
