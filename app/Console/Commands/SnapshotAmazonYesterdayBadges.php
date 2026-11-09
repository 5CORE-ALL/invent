<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Persist today's /amazon-tabulator-view "Yesterday" badge values (Y spend, ACOS, TACOS, sales, CVR, bgt, utilized)
 * into amazon_daily_badge_stats so trend dots / history charts fill even if nobody opens the page.
 */
class SnapshotAmazonYesterdayBadges extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'app:snapshot-amazon-yesterday-badges';

    protected $description = 'Store daily Amazon tabulator Yesterday-badge snapshot for trend dots / history';

    protected string $monitorJobName = 'Amazon Yesterday Badge Snapshot';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSnapshot($m),
            $this->monitorJobName
        );
    }

    protected function executeSnapshot(CronExecutionContext $monitor): int
    {
        $this->info('Saving Amazon Yesterday badge snapshot...');
        $monitor->startFresh()->markLocalOnly();
        $monitor->setFetched(1);
        $monitor->setExpected(1);

        $saved = app(OverallAmazonController::class)->snapshotYesterdayBadges();
        if ($saved === []) {
            $this->error('Amazon Yesterday badge snapshot saved nothing (migration not run, or see log).');
            $monitor->setFailed(1);

            return self::FAILURE;
        }

        $monitor->setProcessed(1);
        $monitor->setUpdated(1);
        $this->info('Saved: ' . json_encode($saved));

        return self::SUCCESS;
    }
}
