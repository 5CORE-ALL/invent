<?php

namespace App\Jobs\CronMonitor;

use App\Services\CronMonitor\ManualActionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryEntireJobJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $command,
        public bool $resume = false
    ) {}

    public function handle(ManualActionService $actions): void
    {
        $code = $actions->runCommandNow($this->command, $this->resume);
        Log::info('[CronMonitor] RetryEntireJobJob finished', [
            'command' => $this->command,
            'resume' => $this->resume,
            'exit' => $code,
        ]);
    }
}
