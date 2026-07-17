<?php

namespace App\Providers;

use App\Events\Crm\ShopifyOrderImported;
use App\Listeners\Crm\CrmActivitySubscriber;
use App\Listeners\Crm\CreateFollowUpForNewShopifyOrder;
use App\Services\CronMonitor\TaskManagerStatusReporter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        ShopifyOrderImported::class => [
            CreateFollowUpForNewShopifyOrder::class,
        ],
    ];

    /**
     * @var array<int, class-string>
     */
    protected $subscribe = [
        CrmActivitySubscriber::class,
    ];

    public function boot(): void
    {
        // Listen when cron job starts
        Event::listen(ScheduledTaskStarting::class, function ($event) {
            app(TaskManagerStatusReporter::class)->post([
                'command' => $event->task->command ?? $event->task->description,
                'status' => 'running',
                'started_at' => now()->toDateTimeString(),
                'meta' => [
                    'expression' => $event->task->expression ?? null,
                    'timezone' => $event->task->timezone ?? null,
                ],
            ]);
        });

        // Listen when cron job finishes (process exited without exception).
        // Rich health metrics are posted separately by Cron Monitor when a
        // command uses MonitoredCommand / MonitorsCronExecution.
        Event::listen(ScheduledTaskFinished::class, function ($event) {
            app(TaskManagerStatusReporter::class)->post([
                'command' => $event->task->command ?? $event->task->description,
                'status' => 'success',
                'finished_at' => now()->toDateTimeString(),
                'runtime' => $event->runtime ?? null,
            ]);
        });

        // Listen when cron job fails
        Event::listen(ScheduledTaskFailed::class, function ($event) {
            $taskName = $event->task->command ?? $event->task->description;
            $errorMessage = $event->exception->getMessage();

            app(TaskManagerStatusReporter::class)->post([
                'command' => $taskName,
                'status' => 'failed',
                'finished_at' => now()->toDateTimeString(),
                'error' => $errorMessage,
            ]);
        });
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
