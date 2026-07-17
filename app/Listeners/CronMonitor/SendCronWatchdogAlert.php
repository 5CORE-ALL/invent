<?php

namespace App\Listeners\CronMonitor;

use App\Events\CronMonitor\CronMissed;
use App\Events\CronMonitor\CronTimedOut;
use App\Services\CronMonitor\CronNotificationDispatcher;

class SendCronWatchdogAlert
{
    public function __construct(protected CronNotificationDispatcher $dispatcher) {}

    public function handle(CronMissed|CronTimedOut $event): void
    {
        $this->dispatcher->dispatchAlert($event->alert);
    }
}
