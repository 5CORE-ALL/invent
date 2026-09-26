<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\ShopifyB2cRuleSpriceApplyService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;

/**
 * Recalc Shopify B2C S PRC from Sprc Dil (then Amz floor) and save SPRICE.
 * Runs whether or not /shopify-b2c-pricing is open (same idea as amazon:dil-prmt-auto-push).
 */
class ShopifyB2cRuleSpriceApplyCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'shopify-b2c:rule-sprice-apply
        {--dry-run : Compute S PRC but do not write shopifyb2c_data_view}
        {--push : After save, queue S PRC → live Shopify price}
        {--limit= : Max SKUs (for testing)}';

    protected $description = 'Shopify B2C: apply Sprc Dil (Dil→SNROI) + Amz floor and save S PRC (page not required).';

    protected string $monitorJobName = 'Shopify B2C Rule S PRC Apply';

    public function handle(ShopifyB2cRuleSpriceApplyService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $enqueue, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(ShopifyB2cRuleSpriceApplyService $service, ChannelPushSpriceDailyEnqueue $enqueue, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $push = (bool) $this->option('push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Shopify B2C Rule S PRC Apply'.($dryRun ? ' [DRY RUN]' : '').($push ? ' + push' : ''));
        $this->info('Saves SPRICE from Sprc Dil with the page closed');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            limit: $limit,
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg)
        );

        $stats = $summary['stats'] ?? [];
        $candidates = (int) ($stats['candidates'] ?? 0);
        $applied = (int) ($stats['applied'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $failed = count($stats['errors'] ?? []);

        if ($dryRun) {
            $monitor->meta['dry_run'] = true;
        }
        $monitor->markApiConnected();
        $monitor->setExpected($candidates);
        $monitor->setFetched($candidates);
        $monitor->setProcessed($applied + $unchanged);
        $monitor->setUpdated($applied + $unchanged);
        $monitor->setSkipped($unchanged);
        $monitor->setFailed($failed);

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        if ($push && ! $dryRun) {
            $res = $enqueue->enqueueChannel('shopify_b2c');
            $this->info('Shopify B2C push: '.$res['message'].(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. candidates=%d applied=%d unchanged=%d errors=%d',
            $candidates,
            $applied,
            $unchanged,
            $failed
        ));

        return ($failed > 0 && $applied === 0 && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
