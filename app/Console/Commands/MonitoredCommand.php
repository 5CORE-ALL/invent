<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

/**
 * Extend this instead of Command to get automatic cron health monitoring.
 *
 * Implement handleMonitored() with business logic only.
 * Use $this->monitor() (or the injected context) to record metrics.
 */
abstract class MonitoredCommand extends Command
{
    use MonitorsCronExecution;

    /**
     * Optional friendly name shown in dashboard / alerts.
     * Defaults to the artisan signature name.
     */
    protected string $monitorJobName = '';

    /**
     * Business logic for the cron. Return Command::SUCCESS / FAILURE.
     */
    abstract protected function handleMonitored(CronExecutionContext $monitor): int;

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $ctx) => $this->handleMonitored($ctx),
            $this->monitorJobName !== '' ? $this->monitorJobName : null
        );
    }
}
