<?php

namespace App\Providers;

use App\Events\CronMonitor\CronExecutionFinished;
use App\Events\CronMonitor\CronMissed;
use App\Events\CronMonitor\CronTimedOut;
use App\Listeners\CronMonitor\AutoMonitorScheduledCommand;
use App\Listeners\CronMonitor\SendCronExecutionAlert;
use App\Listeners\CronMonitor\SendCronWatchdogAlert;
use App\Repositories\CronExecutionLogRepository;
use App\Services\CronMonitor\CronAnomalyDetector;
use App\Services\CronMonitor\CronHealthScoreCalculator;
use App\Services\CronMonitor\CronMonitorService;
use App\Services\CronMonitor\CronNotificationDispatcher;
use App\Services\CronMonitor\CronRetryService;
use App\Services\CronMonitor\CronStatusResolver;
use App\Services\CronMonitor\CronValidationService;
use App\Services\CronMonitor\CronWatchdogService;
use App\Services\CronMonitor\ScheduledJobRegistry;
use App\Services\CronMonitor\TaskManagerStatusReporter;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CronMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CronExecutionLogRepository::class);
        $this->app->singleton(CronValidationService::class);
        $this->app->singleton(CronStatusResolver::class);
        $this->app->singleton(CronHealthScoreCalculator::class);
        $this->app->singleton(CronAnomalyDetector::class);
        $this->app->singleton(CronNotificationDispatcher::class);
        $this->app->singleton(CronWatchdogService::class);
        $this->app->singleton(CronRetryService::class);
        $this->app->singleton(TaskManagerStatusReporter::class);
        $this->app->singleton(ScheduledJobRegistry::class);
        $this->app->singleton(AutoMonitorScheduledCommand::class);

        // Scoped per-console-run so context is isolated
        $this->app->singleton(CronMonitorService::class);
    }

    public function boot(): void
    {
        Event::listen(CommandStarting::class, [AutoMonitorScheduledCommand::class, 'handleStarting']);
        Event::listen(CommandFinished::class, [AutoMonitorScheduledCommand::class, 'handleFinished']);

        Event::listen(CronExecutionFinished::class, SendCronExecutionAlert::class);
        Event::listen(CronMissed::class, [SendCronWatchdogAlert::class, 'handle']);
        Event::listen(CronTimedOut::class, [SendCronWatchdogAlert::class, 'handle']);
    }
}
