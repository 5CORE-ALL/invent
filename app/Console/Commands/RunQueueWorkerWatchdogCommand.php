<?php

namespace App\Console\Commands;

use App\Services\Support\QueueWorkerWatchdog;
use Illuminate\Console\Command;

class RunQueueWorkerWatchdogCommand extends Command
{
    protected $signature = 'queue:watchdog
        {--queues= : Comma-separated queue names (default: config queue_workers.watchdog_queues)}
        {--interval= : Seconds between checks (default: config queue_workers.watchdog_interval_seconds)}';

    protected $description = 'Run permanently: keep dedicated queue:work processes alive for specific queues only';

    public function handle(): int
    {
        $interval = max(5, (int) ($this->option('interval') ?: config('queue_workers.watchdog_interval_seconds', 30)));
        $queues = $this->resolveQueues();

        if ($queues === []) {
            $this->error('No watchdog queues configured.');

            return self::FAILURE;
        }

        $this->info('Queue worker watchdog started.');
        $this->line('Watching: ' . implode(', ', array_keys($queues)));
        $this->line("Interval: {$interval}s (Ctrl+C to stop)");
        $this->line('Only explicit --queue workers are started; default queue is never processed.');

        while (true) {
            foreach ($queues as $queue => $options) {
                if (QueueWorkerWatchdog::isRunning($queue)) {
                    continue;
                }

                $started = QueueWorkerWatchdog::ensureRunning(
                    $queue,
                    (int) $options['timeout'],
                    (int) $options['max_time']
                );

                $message = $started
                    ? "Started dedicated worker for [{$queue}]"
                    : "Failed to start worker for [{$queue}] — see storage/logs/{$queue}-worker.log";

                $this->line('[' . now()->toDateTimeString() . '] ' . $message);
            }

            sleep($interval);
        }
    }

    /**
     * @return array<string, array{timeout: int, max_time: int}>
     */
    private function resolveQueues(): array
    {
        $raw = trim((string) $this->option('queues'));

        if ($raw !== '') {
            $configured = array_merge(
                config('queue_workers.watchdog_queues', []),
                config('queue_workers.optional_dedicated_queues', [])
            );
            $queues = [];

            foreach (array_filter(array_map('trim', explode(',', $raw))) as $queue) {
                $queues[$queue] = $configured[$queue] ?? ['timeout' => 3600, 'max_time' => 3600];
            }

            return $queues;
        }

        return config('queue_workers.watchdog_queues', []);
    }
}
