<?php

namespace App\Listeners\CronMonitor;

use App\Events\CronMonitor\CronExecutionFinished;
use App\Services\CronMonitor\CronNotificationDispatcher;
use App\Services\CronMonitor\TaskManagerStatusReporter;

class SendCronExecutionAlert
{
    public function __construct(
        protected CronNotificationDispatcher $dispatcher,
        protected TaskManagerStatusReporter $taskManager,
    ) {}

    public function handle(CronExecutionFinished $event): void
    {
        // Healthy rich (MonitoredCommand) runs: push detailed metrics to Task Manager.
        // Auto Kernel schedule success is already covered by ScheduledTaskFinished —
        // avoid flooding Task Manager on every every-minute job.
        $meta = $event->log->meta ?? [];
        $isAutoSchedule = ($meta['mode'] ?? null) === 'schedule' || ! empty($meta['auto']);

        if (
            ! $isAutoSchedule
            && $event->log->isHealthy()
            && empty($event->log->anomalies)
            && in_array('taskmanager', config('cron-monitor.notifications.channels', []), true)
        ) {
            $this->taskManager->reportExecution($event->log);
        }

        $this->dispatcher->dispatchForExecution($event->log);
    }
}
