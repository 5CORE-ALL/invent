<?php

namespace App\Jobs;

use App\Models\B5cB2bOrder;
use App\Services\MarketplaceManager\B5cB2bOrderPushService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\MarketplaceShopifyImportQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportB5cB2bOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $b5cB2bOrderId
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('b5cb2b'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('b5cb2b_import:'.$this->b5cB2bOrderId))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(B5cB2bOrderPushService $pushService): void
    {
        $order = B5cB2bOrder::query()->find($this->b5cB2bOrderId);
        if (! $order) {
            Cache::forget($this->dispatchKey());

            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);
        Cache::forget($this->dispatchKey());
        if ($shopifyOrderId) {
            $order->update([
                'shopify_order_id' => $shopifyOrderId,
                'shopify_imported_at' => $order->shopify_imported_at ?? now(),
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        $reason = $pushService->lastFailureReason ?? null;
        if (MarketplaceShopifyImportQueue::isRetryableShopifyFailure($status, $reason)) {
            throw new RuntimeException($reason ?: "Shopify HTTP {$status}");
        }

        Log::warning('ImportB5cB2bOrderToShopify: not imported', [
            'id' => $this->b5cB2bOrderId,
            'store_order_id' => $order->store_order_id,
            'reason' => $reason,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Cache::forget($this->dispatchKey());
        Log::error('ImportB5cB2bOrderToShopify: job failed', [
            'id' => $this->b5cB2bOrderId,
            'error' => $exception->getMessage(),
        ]);
    }

    public static function dispatchKeyFor(int $id): string
    {
        return 'b5cb2b-shopify-import:'.$id;
    }

    protected function dispatchKey(): string
    {
        return self::dispatchKeyFor($this->b5cB2bOrderId);
    }
}
