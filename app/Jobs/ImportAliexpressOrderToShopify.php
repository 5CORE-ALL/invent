<?php

namespace App\Jobs;

use App\Models\AliexpressOrderMetric;
use App\Services\MarketplaceManager\AliexpressOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportAliexpressOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $aliexpressOrderMetricId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('aliexpress'));
    }

    public function middleware(): array
    {
        $orderId = AliexpressOrderMetric::query()->where('id', $this->aliexpressOrderMetricId)->value('order_id');
        $key = $orderId ? 'aliexpress_import_order:'.$orderId : "aliexpress_import:{$this->aliexpressOrderMetricId}";

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(AliexpressOrderPushService $pushService): void
    {
        $order = AliexpressOrderMetric::find($this->aliexpressOrderMetricId);
        if (! $order) {
            Log::warning('ImportAliexpressOrderToShopify: order not found', ['id' => $this->aliexpressOrderMetricId]);

            return;
        }

        if ($order->shopify_order_id) {
            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);

        if ($shopifyOrderId) {
            $order->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);
            Log::info('ImportAliexpressOrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
                'linked_existing' => $pushService->lastDuplicateLinkMessage !== null,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus;
        $reason = $pushService->lastFailureReason ?? null;
        if (\App\Services\MarketplaceManager\MarketplaceShopifyImportQueue::isRetryableShopifyFailure($status, $reason)) {
            Log::warning('ImportAliexpressOrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
                'reason' => $pushService->lastFailureReason,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportAliexpressOrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = AliexpressOrderMetric::find($this->aliexpressOrderMetricId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportAliexpressOrderToShopify: job failed after all retries', [
            'order_id' => $this->aliexpressOrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
