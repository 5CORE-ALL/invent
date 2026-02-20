<?php

namespace App\Jobs;

use App\Models\PendingShopifyOrder;
use App\Models\ReverbOrderMetric;
use App\Services\ReverbOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportReverbOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 240, 480, 600, 900, 1800, 3600];

    public function __construct(
        protected int $reverbOrderMetricId
    ) {
        $this->onQueue('reverb');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("reverb_import:{$this->reverbOrderMetricId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    /**
     * Never throw – every Reverb order is either imported, created as custom, or stored in pending_shopify_orders.
     */
    public function handle(ReverbOrderPushService $pushService): void
    {
        if (Cache::has('reverb_sync_running')) {
            $this->release(120);
            Log::info('ImportReverbOrderToShopify: sync in progress, releasing job', ['order_id' => $this->reverbOrderMetricId]);
            return;
        }

        $order = ReverbOrderMetric::find($this->reverbOrderMetricId);

        if (!$order) {
            Log::warning('ImportReverbOrderToShopify: order not found', ['id' => $this->reverbOrderMetricId]);
            return;
        }

        if ($order->shopify_order_id) {
            Log::info('ImportReverbOrderToShopify: already imported', ['order_number' => $order->order_number]);
            return;
        }

        $fallbackPath = 'createOrderFromMarketplace_returned_null';
        try {
            $shopifyOrderId = $pushService->createOrderFromMarketplace($order);
        } catch (\Throwable $e) {
            $fallbackPath = 'exception_then_custom';
            Log::error('ImportReverbOrderToShopify: unexpected exception (fallback to custom)', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $shopifyOrderId = $pushService->createOrderWithCustomItem(
                $order,
                'Fallback after exception: ' . substr($e->getMessage(), 0, 100),
                ['Fallback - Exception']
            );
            if ($shopifyOrderId === null) {
                $reason = 'Exception: ' . $e->getMessage() . ' | Custom failed: ' . ($pushService->lastFailureReason ?? $pushService->lastApiErrorCode ?? 'Unknown');
                $pushService->storeInPending($order, $reason);
            }
        }

        if ($shopifyOrderId) {
            $order->update([
                'shopify_order_id' => (string) $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);

            if (class_exists(\App\Services\ReverbSyncLogService::class)) {
                app(\App\Services\ReverbSyncLogService::class)->logOrderPushedToShopify(
                    (string) ($order->order_number ?? $order->id),
                    (int) $shopifyOrderId,
                    $order->sku,
                    'Reverb order #' . ($order->order_number ?? $order->id)
                );
            }

            Log::info('ImportReverbOrderToShopify: success', [
                'order_number' => $order->order_number,
                'shopify_order_id' => $shopifyOrderId,
            ]);
            return;
        }

        $pending = PendingShopifyOrder::where('reverb_order_metric_id', $order->id)->exists();
        if ($pending) {
            $order->update(['import_status' => 'pending_shopify']);
            Log::warning('ImportReverbOrderToShopify: stored in pending_shopify_orders', [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'reason' => $pushService->lastFailureReason ?? $pushService->lastApiErrorCode ?? 'Unknown',
                'fallback_path' => $fallbackPath ?? 'createOrderFromMarketplace_returned_null',
                'last_api_status' => $pushService->lastApiStatus ?? null,
            ]);
        } else {
            $order->update(['import_status' => 'import_failed']);
            Log::error('ImportReverbOrderToShopify: no Shopify order and not in pending (unexpected)', [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'last_failure_reason' => $pushService->lastFailureReason ?? null,
                'last_api_error' => $pushService->lastApiErrorCode ?? null,
                'last_api_status' => $pushService->lastApiStatus ?? null,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $order = ReverbOrderMetric::find($this->reverbOrderMetricId);
        if ($order && !$order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportReverbOrderToShopify: job failed after all retries', [
            'order_id' => $this->reverbOrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
