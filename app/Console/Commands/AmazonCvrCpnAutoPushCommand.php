<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\AmazonCvrCpnAutoPushService;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Daily ~4 AM America/New_York: CVR vs CPN rules → 5%/10% coupon tiers → SPRICE → Amazon Listings API.
 * Only pushes SKUs whose target price/tier changed; enforces 1 coupon tier change per SKU per ET day.
 */
class AmazonCvrCpnAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'amazon:cvr-cpn-auto-push
        {--dry-run : Compute + save SPRICE, but do NOT push to Amazon}
        {--skip-push : Skip Amazon push (same as dry-run for the push step)}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between Amazon Listings API calls (ms)}';

    protected $description = 'CVR vs CPN: snap to 5%/10% Amazon coupons (1/day), refresh SPRICE, push only changed prices (daily 4 AM EST/EDT).';

    protected string $monitorJobName = 'Amazon CVR vs CPN Auto Push';

    public function handle(AmazonCvrCpnAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(AmazonCvrCpnAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Amazon CVR vs CPN Auto Push'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('Schedule: daily 04:05 America/New_York (EST/EDT)');
        $this->info('Coupons: 5% + 10% (1 coupon per day only)');
        $this->info('Rules: pef_cvr_vs_cpn (shared with pricing-errors-fix)');
        $this->info('Push: Amazon Listings our_price — only when price/tier changed');
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
        $onePerDay = (int) ($stats['skipped_one_per_day'] ?? 0);
        $changedOk = $dryRun || $skipPush ? $totalApplied : $totalPushed;
        $intentionalSkip = $unchanged + $onePerDay;

        if ($dryRun || $skipPush) {
            $monitor->meta['dry_run'] = true;
        }

        $monitor->markApiConnected();
        $monitor->setExpected($totalExpected);
        $monitor->setFetched($totalExpected);
        $monitor->setProcessed($totalApplied + $intentionalSkip);
        // Unchanged / 1-per-day SKUs are intentional — count as OK for monitor ratio.
        $monitor->setUpdated($changedOk + $intentionalSkip);
        $monitor->setSkipped($intentionalSkip);
        $monitor->setFailed($totalFailed);

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. candidates=%d applied=%d pushed=%d unchanged=%d one_per_day=%d failed=%d',
            $totalExpected,
            $totalApplied,
            $totalPushed,
            $unchanged,
            $onePerDay,
            $totalFailed
        ));

        return ($totalFailed > 0 && $totalPushed === 0 && ! $dryRun && ! $skipPush)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
