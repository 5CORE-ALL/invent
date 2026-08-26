<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Http\Controllers\MarketPlace\Shopifyb2cController;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Persist today's /shopify-b2c-pricing badge totals into amazon_channel_summary_data
 * so trend dots / rolling history work even if nobody opens the page.
 */
class SnapshotShopifyB2cBadges extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'app:snapshot-shopify-b2c-badges';

    protected $description = 'Store daily Shopify B2C pricing badge snapshot for trend dots / history';

    protected string $monitorJobName = 'Shopify B2C Badge Snapshot';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSnapshot($m),
            $this->monitorJobName
        );
    }

    protected function executeSnapshot(CronExecutionContext $monitor): int
    {
        $this->info('Saving Shopify B2C badge daily snapshot...');
        $monitor->startFresh()->markLocalOnly();
        $monitor->setFetched(1);
        $monitor->setExpected(1);

        $summary = app(Shopifyb2cController::class)->snapshotDailyBadgeSummary();
        if ($summary === []) {
            $this->error('Shopify B2C badge snapshot failed (see log).');
            $monitor->setFailed(1);

            return self::FAILURE;
        }

        $monitor->setProcessed(1);
        $monitor->setUpdated(1);
        $this->info('Shopify B2C badge snapshot saved (' . count($summary) . ' metrics).');

        return self::SUCCESS;
    }
}
