<?php

namespace App\Jobs;

use App\Models\Ebay1OrderMetric;
use App\Services\MarketplaceManager\Ebay1OrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportEbay1OrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $ebay1OrderMetricId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('ebay1'));
    }

    public function middleware(): array
    {
        $orderId = Ebay1OrderMetric::query()->where('id', $this->ebay1OrderMetricId)->value('order_id');
        $key = $orderId ? 'ebay1_import_order:'.$orderId : "ebay1_import:{$this->ebay1OrderMetricId}";

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(Ebay1OrderPushService $pushService): void
    {
        $order = Ebay1OrderMetric::find($this->ebay1OrderMetricId);
        if (! $order) {
            Log::warning('ImportEbay1OrderToShopify: order not found', ['id' => $this->ebay1OrderMetricId]);

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
            Log::info('ImportEbay1OrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        if ($status === 429 || ($status !== null && $status >= 500)) {
            Log::warning('ImportEbay1OrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
                'reason' => $pushService->lastFailureReason ?? null,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportEbay1OrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason ?? 'Import returned no Shopify order id',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = Ebay1OrderMetric::find($this->ebay1OrderMetricId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportEbay1OrderToShopify: job failed after all retries', [
            'order_id' => $this->ebay1OrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
