<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\ShopifyB2cSprcDilAutoPushService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;

/**
 * Twice daily 4:50 AM and 8:50 PM IST: Shopify B2C Std prc vs dil (Age/Dil/B/0 Sold/CVR/Rev Disc,
 * LMP cap) → SPRICE → Shopify live price. Page not required.
 * Only queues SKUs whose saved S PRC differs from the live Shopify price. Negative SNROI is never pushed.
 */
class ShopifyB2cSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'shopify-b2c:sprc-dil-auto-push
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to Shopify}
        {--skip-push : Save SPRICE, but skip the Shopify push}
        {--limit= : Max SKUs (for testing)}
        {--sku=* : Only these SKUs (for testing)}';

    protected $description = 'Shopify B2C Std prc vs dil (LMP cap) → SPRICE → Shopify (4:50 AM + 8:50 PM IST).';

    protected string $monitorJobName = 'Shopify B2C Sprc Dil Auto Push';

    public function handle(ShopifyB2cSprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $enqueue, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(ShopifyB2cSprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Shopify B2C Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:50 and 20:50 Asia/Kolkata (IST)');
        $this->info('Rules: shopify_b2c_std_prc_vs_dil (Age/Dil/B/0 Sold/CVR/Rev Disc) + LMP cap');
        $this->info('Push: Shopify live price — only when S PRC differs from Price');
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
            $res = $enqueue->enqueueChannel('shopify_b2c');
            $queued = (int) ($res['queued'] ?? 0);
            $this->info('Shopify B2C push: '.$res['message'].(($res['spawned'] ?? false) ? ' — worker started' : ''));
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
