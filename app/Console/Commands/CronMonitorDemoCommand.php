<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Services\CronMonitor\CronExecutionContext;
use RuntimeException;

/**
 * Demo / smoke-test for self-healing monitoring.
 *
 * php artisan cron-monitor:demo
 * php artisan cron-monitor:demo --expected=500 --fail-rate=10 --checkpoint --chunk=50
 * php artisan cron-monitor:demo --simulate-timeout
 */
class CronMonitorDemoCommand extends MonitoredCommand
{
    use ProcessesUpdatesInChunks;

    protected $signature = 'cron-monitor:demo
        {--expected=1000 : Expected record count}
        {--fail-rate=5 : Percentage of records to mark failed}
        {--skip-api : Simulate API failure}
        {--checkpoint : Persist checkpoints while processing}
        {--simulate-timeout : Throw a recoverable timeout once, then succeed}
        {--resume : Force resume from last checkpoint}
        {--chunk= : Chunk size (default from cron-monitor config)}';

    protected $description = 'Demo cron monitoring + self-healing lifecycle (safe, no side effects)';

    protected string $monitorJobName = 'Cron Monitor Demo';

    protected static bool $timeoutSimulated = false;

    protected function handleMonitored(CronExecutionContext $monitor): int
    {
        $expected = (int) $this->option('expected');
        $failRate = max(0, min(100, (int) $this->option('fail-rate')));

        if ($this->option('resume') || env('CRON_MONITOR_RESUME')) {
            $this->info('Resume offset: ' . $monitor->resumeOffset());
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

        $items = range(0, max(0, $expected - 1));
        $failedTarget = (int) round($expected * ($failRate / 100));
        $failedSoFar = 0;

        $stats = $this->chunkProcessor()->process(
            $monitor,
            $items,
            function (array $chunk, int $chunkIndex, int $absoluteOffset) use (
                $failRate,
                $failedTarget,
                &$failedSoFar
            ) {
                $updated = 0;
                $failed = 0;
                $failures = [];

                foreach ($chunk as $i) {
                    $isFail = $failedSoFar < $failedTarget
                        && ($i % max(1, (int) floor(100 / max(1, $failRate)))) === 0;

                    if ($isFail) {
                        $failed++;
                        $failedSoFar++;
                        $recoverable = $i % 2 === 0;
                        $failures[] = [
                            'sku' => 'DEMO-SKU-' . ($i + 1),
                            'marketplace' => 'demo',
                            'reason' => $recoverable ? 'HTTP 503 Upstream error' : 'Invalid SKU / product not found',
                            'api_response' => ['status' => $recoverable ? 503 : 404],
                            'http_status' => $recoverable ? 503 : 404,
                        ];
                    } else {
                        $updated++;
                    }
                }

                return [
                    'updated' => $updated,
                    'failed' => $failed,
                    'processed' => count($chunk),
                    'failures' => $failures,
                ];
            },
            $this->monitoredChunkSize()
        );

        $this->info("Updated {$stats['updated']}, failed {$stats['failed']}, chunks {$stats['chunks']}");

        return self::SUCCESS;
    }
}
