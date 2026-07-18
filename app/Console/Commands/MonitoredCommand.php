<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Extend this instead of Command to get automatic cron health monitoring.
 *
 * Implement handleMonitored() (or executeJob()) with business logic only.
 */
abstract class MonitoredCommand extends Command
{
    use MonitorsCronExecution;

    protected string $monitorJobName = '';

    /**
     * Business logic for the cron. Return Command::SUCCESS / FAILURE.
     */
    abstract protected function handleMonitored(CronExecutionContext $monitor): int;

    /**
     * Alias used in docs / newer integrations.
     */
    protected function executeJob(CronExecutionContext $monitor): int
    {
        return $this->handleMonitored($monitor);
    }

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $ctx) => $this->executeJob($ctx),
            $this->monitorJobName !== '' ? $this->monitorJobName : null
        );
    }
}
