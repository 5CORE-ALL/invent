<?php

namespace App\Jobs;

use App\Services\Support\ReverbPushBumpJobStore;
use App\Services\Support\ReverbPushBumpRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunReverbPushBumpJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct()
    {
        $this->onQueue('reverb-push-bump');
    }

    public function uniqueId(): string
    {
        return 'reverb-push-bump';
    }

    public function handle(): void
    {
        $store = ReverbPushBumpJobStore::for();
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/reverb-push-bump.log'),
            'level' => 'debug',
        ])->info('Queue worker started Reverb Push B%', [
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        ReverbPushBumpRunner::for()->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = ReverbPushBumpJobStore::for();
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
