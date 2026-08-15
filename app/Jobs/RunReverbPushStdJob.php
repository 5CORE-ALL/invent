<?php

namespace App\Jobs;

use App\Services\Support\ReverbPushStdJobStore;
use App\Services\Support\ReverbPushStdRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunReverbPushStdJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct()
    {
        $this->onQueue('reverb-push-std');
    }

    public function uniqueId(): string
    {
        return 'reverb-push-std';
    }

    public function handle(): void
    {
        $store = ReverbPushStdJobStore::for();
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/reverb-push-std.log'),
            'level' => 'debug',
        ])->info('Queue worker started Reverb Push Std', [
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        ReverbPushStdRunner::for()->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = ReverbPushStdJobStore::for();
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
