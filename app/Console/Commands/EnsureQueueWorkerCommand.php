<?php

namespace App\Console\Commands;

use App\Services\Support\QueueWorkerWatchdog;
use Illuminate\Console\Command;

class EnsureQueueWorkerCommand extends Command
{
    protected $signature = 'queue:ensure-worker
        {queue : Queue name to keep alive}
        {--timeout= : Worker job timeout in seconds}
        {--max-time= : Worker max runtime in seconds}';

    protected $description = 'Start a queue:work process when none is running for the given queue';

    public function handle(): int
    {
        $queue = (string) $this->argument('queue');

        if (QueueWorkerWatchdog::isRunning($queue)) {
            $this->line("Worker already running for queue [{$queue}].");

            return self::SUCCESS;
        }

        $timeout = $this->option('timeout') !== null ? (int) $this->option('timeout') : null;
        $maxTime = $this->option('max-time') !== null ? (int) $this->option('max-time') : null;

        $started = QueueWorkerWatchdog::ensureRunning($queue, $timeout, $maxTime);

        if ($started) {
            $this->info("Started queue worker for [{$queue}].");

            return self::SUCCESS;
        }

        $this->warn("Could not start queue worker for [{$queue}]. Check storage/logs/{$queue}-worker.log");

        return self::FAILURE;
    }
}
