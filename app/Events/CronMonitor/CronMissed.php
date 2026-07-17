<?php

namespace App\Events\CronMonitor;

use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CronMissed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CronExecutionLog $log,
        public CronMonitorAlert $alert
    ) {}
}
