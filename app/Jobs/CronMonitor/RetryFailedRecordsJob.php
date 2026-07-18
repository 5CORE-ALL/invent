<?php

namespace App\Jobs\CronMonitor;

use App\Services\CronMonitor\IntelligentRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $jobName,
        public ?int $limit = 100
    ) {}

    public function handle(IntelligentRetryService $retry): void
    {
        // Default handler cannot re-run business logic; mark dry inventory.
        // Commands register handlers via ManualActionService / CronRetryService callbacks.
        $stats = $retry->retryUnresolved($this->jobName, function ($failure) {
            Log::info('[CronMonitor] RetryFailedRecordsJob placeholder — provide job-specific handler', [
                'failure_id' => $failure->id,
                'sku' => $failure->sku,
            ]);

            return false;
        }, $this->limit);

        Log::info('[CronMonitor] RetryFailedRecordsJob finished', [
            'job' => $this->jobName,
            'stats' => $stats,
        ]);
    }
}
