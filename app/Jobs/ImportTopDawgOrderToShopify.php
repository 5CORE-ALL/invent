<?php

namespace App\Jobs;

use App\Models\TopDawgOrderMetric;
use App\Services\MarketplaceManager\TopDawgOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportTopDawgOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $topdawgOrderMetricId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('topdawg'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("topdawg_import:{$this->topdawgOrderMetricId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(TopDawgOrderPushService $pushService): void
    {
        $order = TopDawgOrderMetric::find($this->topdawgOrderMetricId);
        if (! $order) {
            Log::warning('ImportTopDawgOrderToShopify: order not found', ['id' => $this->topdawgOrderMetricId]);

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
            Log::info('ImportTopDawgOrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        if ($status === 429 || ($status !== null && $status >= 500)) {
            Log::warning('ImportTopDawgOrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
                'reason' => $pushService->lastFailureReason ?? null,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportTopDawgOrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason ?? 'Import returned no Shopify order id',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = TopDawgOrderMetric::find($this->topdawgOrderMetricId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTopDawgOrderToShopify: job failed after all retries', [
            'order_id' => $this->topdawgOrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
