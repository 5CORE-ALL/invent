<?php

namespace App\Jobs;

use App\Services\Support\ImageMasterPushJobStore;
use App\Services\Support\ImageMasterPushRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunImageMasterPushJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'image-master-push';

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct()
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'image-master-push';
    }

    public function handle(ImageMasterPushRunner $runner, ImageMasterPushJobStore $store): void
    {
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/image-master-push.log'),
            'level' => 'debug',
        ])->info('Queue worker started Image Master push', [
            'job_id' => $state['id'] ?? null,
            'sku' => $state['sku'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        $runner->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = app(ImageMasterPushJobStore::class);
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
