<?php

namespace App\Events\CronMonitor;

use App\Models\CronExecutionLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CronExecutionStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public CronExecutionLog $log) {}
}
