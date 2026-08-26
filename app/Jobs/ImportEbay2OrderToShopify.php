<?php

namespace App\Jobs;

use App\Models\Ebay2OrderMetric;
use App\Services\MarketplaceManager\Ebay2OrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportEbay2OrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $ebay2OrderMetricId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('ebay2'));
    }

    public function middleware(): array
    {
        $orderId = Ebay2OrderMetric::query()->where('id', $this->ebay2OrderMetricId)->value('order_id');
        $key = $orderId ? 'ebay2_import_order:'.$orderId : "ebay2_import:{$this->ebay2OrderMetricId}";

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(Ebay2OrderPushService $pushService): void
    {
        $order = Ebay2OrderMetric::find($this->ebay2OrderMetricId);
        if (! $order) {
            Log::warning('ImportEbay2OrderToShopify: order not found', ['id' => $this->ebay2OrderMetricId]);

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
            Log::info('ImportEbay2OrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        $reason = $pushService->lastFailureReason ?? null;
        if (\App\Services\MarketplaceManager\MarketplaceShopifyImportQueue::isRetryableShopifyFailure($status, $reason)) {
            Log::warning('ImportEbay2OrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
                'reason' => $pushService->lastFailureReason ?? null,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportEbay2OrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason ?? 'Import returned no Shopify order id',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = Ebay2OrderMetric::find($this->ebay2OrderMetricId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportEbay2OrderToShopify: job failed after all retries', [
            'order_id' => $this->ebay2OrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
