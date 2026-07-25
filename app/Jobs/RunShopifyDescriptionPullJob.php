<?php

namespace App\Jobs;

use App\Services\Support\ShopifyDescriptionPullJobStore;
use App\Services\Support\ShopifyDescriptionPullRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunShopifyDescriptionPullJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'shopify-description-pull';

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct()
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'shopify-description-pull';
    }

    public function handle(ShopifyDescriptionPullRunner $runner, ShopifyDescriptionPullJobStore $store): void
    {
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/shopify-description-pull.log'),
            'level' => 'debug',
        ])->info('Queue worker started Shopify description pull', [
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        $runner->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = app(ShopifyDescriptionPullJobStore::class);
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
