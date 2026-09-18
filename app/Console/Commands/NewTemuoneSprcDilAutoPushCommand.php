<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Http\Controllers\MarketPlace\NewTemuoneController;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Twice daily 4:10 AM and 8:10 PM IST: Sprc Dil (Dil→SNROI) + CVR + eBay/Amz/LMP cap
 * → NTO_SPRICE. Page not required. Does not push to Temu — save only, like the page.
 */
class NewTemuoneSprcDilAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'newtemuone:sprc-dil-auto-push
        {--dry-run : Compute NTO_SPRICE but do not write temu_data_view}
        {--limit= : Max SKUs (for testing)}';

    protected $description = 'New Temu One: Sprc Dil Dil→SNROI + eBay/Amz/LMP cap → NTO_SPRICE (4:10 AM + 8:10 PM IST).';

    protected string $monitorJobName = 'New Temu One Sprc Dil Auto Push';

    public function handle(NewTemuoneController $controller): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($controller, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(NewTemuoneController $controller, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('New Temu One Sprc Dil Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: 04:10 and 20:10 Asia/Kolkata (IST)');
        $this->info('Rules: temu_dil_vs_groi (page Sprc Dil Target SNROI) + CVR + eBay/Amz/LMP cap');
        $this->info('Save: NTO_SPRICE on temu_data_view (page not required)');
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
