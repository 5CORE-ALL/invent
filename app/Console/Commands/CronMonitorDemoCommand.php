<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\CronExecutionContext;
use RuntimeException;

/**
 * Demo / smoke-test for self-healing monitoring.
 *
 * php artisan cron-monitor:demo
 * php artisan cron-monitor:demo --expected=500 --fail-rate=10 --checkpoint
 * php artisan cron-monitor:demo --simulate-timeout
 */
class CronMonitorDemoCommand extends MonitoredCommand
{
    protected $signature = 'cron-monitor:demo
        {--expected=1000 : Expected record count}
        {--fail-rate=5 : Percentage of records to mark failed}
        {--skip-api : Simulate API failure}
        {--checkpoint : Persist checkpoints while processing}
        {--simulate-timeout : Throw a recoverable timeout once, then succeed}
        {--resume : Force resume from last checkpoint}';

    protected $description = 'Demo cron monitoring + self-healing lifecycle (safe, no side effects)';

    protected string $monitorJobName = 'Cron Monitor Demo';

    protected static bool $timeoutSimulated = false;

    protected function handleMonitored(CronExecutionContext $monitor): int
    {
        $expected = (int) $this->option('expected');
        $failRate = max(0, min(100, (int) $this->option('fail-rate')));
        $startOffset = 0;

        if ($this->option('resume') || env('CRON_MONITOR_RESUME')) {
            $startOffset = $monitor->resumeOffset();
            $this->info("Resume offset: {$startOffset}");
        }

        $monitor->setExpected($expected);

        if ($this->option('skip-api')) {
            $this->error('Simulating API failure');
            throw new RuntimeException('Authentication permanently failed: invalid API key');
        }

        $monitor->markApiConnected();
        $monitor->incrementApiCalls();
        $monitor->incrementApiLatency(120);

        if ($this->option('simulate-timeout') && ! self::$timeoutSimulated) {
            self::$timeoutSimulated = true;
            // Instant retries for demo (do not wait 30s/120s)
            config(['cron-monitor.retry.retry_delay' => [1 => 0, 2 => 0, 3 => 0]]);
            throw new RuntimeException('cURL error 28: Operation timed out after 30001 ms (HTTP 504)');
        }

        $fetched = $expected;
        $monitor->setFetched($fetched);
        $this->info("Fetched {$fetched} products (starting at offset {$startOffset})");

        $failedTarget = (int) round($fetched * ($failRate / 100));
        $updated = 0;
        $failed = 0;

        for ($i = $startOffset; $i < $fetched; $i++) {
            $monitor->incrementProcessed();
            $isFail = $failed < $failedTarget && ($i % max(1, (int) floor(100 / max(1, $failRate)))) === 0;

            if ($isFail) {
                $failed++;
                $recoverable = $i % 2 === 0;
                $monitor->recordFailure(
                    sku: 'DEMO-SKU-' . ($i + 1),
                    marketplace: 'demo',
                    reason: $recoverable ? 'HTTP 503 Upstream error' : 'Invalid SKU / product not found',
                    apiResponse: ['status' => $recoverable ? 503 : 404],
                    httpStatus: $recoverable ? 503 : 404,
                );
            } else {
                $updated++;
                $monitor->incrementUpdated();
            }

            if ($this->option('checkpoint') && ($i + 1) % 100 === 0) {
                $monitor->checkpoint(['index' => $i + 1], $i + 1);
            }
        }

        $this->info("Updated {$updated}, failed {$failed}");

        return self::SUCCESS;
    }
}
