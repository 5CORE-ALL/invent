<?php

namespace App\Console\Commands;

use App\Services\Support\QueueWorkerWatchdog;
use Illuminate\Console\Command;

class EnsureQueueWorkerWatchdogDaemonCommand extends Command
{
    protected $signature = 'queue:ensure-watchdog-daemon';

    protected $description = 'Start the permanent queue:watchdog daemon when it is not already running (all dedicated queues in config/queue_workers.php)';

    public function handle(): int
    {
        if (QueueWorkerWatchdog::isWatchdogDaemonRunning()) {
            $this->line('Queue watchdog daemon is already running.');

            return self::SUCCESS;
        }

        $started = QueueWorkerWatchdog::ensureWatchdogDaemonRunning();

        if ($started) {
            $this->info('Started queue watchdog daemon (queue:watchdog).');

            return self::SUCCESS;
        }

        $this->warn('Could not start queue watchdog daemon. Check storage/logs/queue-watchdog-daemon.log');

        return self::FAILURE;
    }
}
