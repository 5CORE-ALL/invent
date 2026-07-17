<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\CronExecutionContext;

/**
 * Demo / smoke-test command showing the MonitoredCommand API.
 *
 * php artisan cron-monitor:demo
 * php artisan cron-monitor:demo --fail-rate=40
 */
class CronMonitorDemoCommand extends MonitoredCommand
{
    protected $signature = 'cron-monitor:demo
        {--expected=1000 : Expected record count}
        {--fail-rate=5 : Percentage of records to mark failed}
        {--skip-api : Simulate API failure}';

    protected $description = 'Demo cron monitoring lifecycle (safe, no side effects)';

    protected string $monitorJobName = 'Cron Monitor Demo';

    protected function handleMonitored(CronExecutionContext $monitor): int
    {
        $expected = (int) $this->option('expected');
        $failRate = max(0, min(100, (int) $this->option('fail-rate')));

        $monitor->setExpected($expected);

        if ($this->option('skip-api')) {
            $this->error('Simulating API failure');

            return self::FAILURE;
        }

        $monitor->markApiConnected();
        $monitor->incrementApiCalls();

        $fetched = $expected;
        $monitor->setFetched($fetched);
        $this->info("Fetched {$fetched} products");

        $failed = (int) round($fetched * ($failRate / 100));
        $updated = $fetched - $failed;

        for ($i = 0; $i < $fetched; $i++) {
            $monitor->incrementProcessed();
            if ($i < $updated) {
                $monitor->incrementUpdated();
            } else {
                $monitor->recordFailure(
                    sku: 'DEMO-SKU-' . ($i + 1),
                    marketplace: 'demo',
                    reason: 'Simulated failure',
                    apiResponse: ['error' => 'demo']
                );
            }
        }

        $this->info("Updated {$updated}, failed {$failed}");

        return self::SUCCESS;
    }
}
