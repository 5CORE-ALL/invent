<?php

namespace App\Jobs;

use App\Models\NeweggOrderMetric;
use App\Services\MarketplaceManager\NeweggOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportNeweggOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $neweggOrderMetricId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('newegg'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("newegg_import:{$this->neweggOrderMetricId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(NeweggOrderPushService $pushService): void
    {
        $order = NeweggOrderMetric::find($this->neweggOrderMetricId);
        if (! $order) {
            Log::warning('ImportNeweggOrderToShopify: order not found', ['id' => $this->neweggOrderMetricId]);

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
            Log::info('ImportNeweggOrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus;
        if ($status === 429 || ($status !== null && $status >= 500)) {
            Log::warning('ImportNeweggOrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
                'reason' => $pushService->lastFailureReason,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportNeweggOrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = NeweggOrderMetric::find($this->neweggOrderMetricId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportNeweggOrderToShopify: job failed after all retries', [
            'order_id' => $this->neweggOrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
