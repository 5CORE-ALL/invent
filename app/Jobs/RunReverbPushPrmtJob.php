<?php

namespace App\Jobs;

use App\Services\Support\ReverbPushPrmtJobStore;
use App\Services\Support\ReverbPushPrmtRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunReverbPushPrmtJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct()
    {
        $this->onQueue('reverb-push-prmt');
    }

    public function uniqueId(): string
    {
        return 'reverb-push-prmt';
    }

    public function handle(): void
    {
        $store = ReverbPushPrmtJobStore::for();
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/reverb-push-prmt.log'),
            'level' => 'debug',
        ])->info('Queue worker started Reverb Push Prmt%', [
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        ReverbPushPrmtRunner::for()->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = ReverbPushPrmtJobStore::for();
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
