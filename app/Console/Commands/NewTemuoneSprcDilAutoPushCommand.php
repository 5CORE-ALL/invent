<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Http\Controllers\MarketPlace\NewTemuoneController;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;
use Throwable;

/**
 * Twice daily 4:10 AM and 8:10 PM IST: Sprc Dil (Dil→SNROI) + CVR + eBay/Amz/LMP cap
 * → NTO_SPRICE, then push S Base to Temu when it differs from the live base.
 */
class NewTemuoneSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'newtemuone:sprc-dil-auto-push
        {--dry-run : Compute NTO_SPRICE but do not write temu_data_view}
        {--skip-push : Save NTO_SPRICE but do not push S Base to Temu}
        {--limit= : Max SKUs (for testing)}';

    protected $description = 'New Temu One: Sprc Dil Dil→SNROI + eBay/Amz/LMP cap → NTO_SPRICE → Temu (4:10 AM + 8:10 PM IST).';

    protected string $monitorJobName = 'New Temu One Sprc Dil Auto Push';

    public function handle(NewTemuoneController $controller, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($controller, $enqueue, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(NewTemuoneController $controller, ChannelPushSpriceDailyEnqueue $enqueue, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('New Temu One Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:10 and 20:10 Asia/Kolkata (IST)');
        $this->info('Rules: temu_dil_vs_groi (page Sprc Dil Target SNROI) + CVR + eBay/Amz/LMP cap');
        $this->info('Save: NTO_SPRICE on temu_data_view, then push S Base when it differs'.($skipPush ? ' [SKIP PUSH]' : ''));
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            $summary = $controller->persistSuggestedCatalog($limit, $dryRun);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $monitor->setFailed(1);

            return self::FAILURE;
        }

        $stats = $summary['stats'] ?? [];
        $candidates = (int) ($stats['candidates'] ?? 0);
        $applied = (int) ($stats['applied'] ?? 0);
        $reused = (int) ($stats['reused'] ?? 0);

        if ($dryRun) {
            $monitor->meta['dry_run'] = true;
        }

        $monitor->markApiConnected();
        $monitor->setExpected($candidates);
        $monitor->setFetched($candidates);
        $monitor->setProcessed($applied + $reused);
        $monitor->setUpdated($applied + $reused);
        $monitor->setSkipped($reused);
        $monitor->setFailed(0);

        if (! $dryRun && ! $skipPush) {
            $res = $enqueue->enqueueChannel('newtemuone');
            $this->info('Temu 1 push: '.$res['message'].(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. candidates=%d applied=%d reused=%d',
            $candidates,
            $applied,
            $reused
        ));

        return self::SUCCESS;
    }
}
