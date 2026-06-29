<?php

namespace App\Jobs;

use App\Services\Support\ShopifyVideoPullJobStore;
use App\Services\Support\ShopifyVideoPullRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunShopifyVideoPullJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'shopify-video-pull';

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct()
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'shopify-video-pull';
    }

    public function handle(ShopifyVideoPullRunner $runner, ShopifyVideoPullJobStore $store): void
    {
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/shopify-video-pull.log'),
            'level' => 'debug',
        ])->info('Queue worker started Shopify video pull', [
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        $runner->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = app(ShopifyVideoPullJobStore::class);
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
