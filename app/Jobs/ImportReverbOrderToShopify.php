<?php

namespace App\Jobs;

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

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

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

    public function handle(ReverbOrderPushService $pushService): void
    {
        if (Cache::has('reverb_sync_running')) {
            $this->release(120);
            Log::info('ImportReverbOrderToShopify: sync in progress, releasing job to retry in 2 min', [
                'order_id' => $this->reverbOrderMetricId,
            ]);
            return;
        }

        $order = ReverbOrderMetric::find($this->reverbOrderMetricId);

        if (!$order) {
            Log::warning('ImportReverbOrderToShopify: order not found', ['id' => $this->reverbOrderMetricId]);
            return;
        }

        // Duplicate protection: skip if already imported
        if ($order->shopify_order_id) {
            Log::info('ImportReverbOrderToShopify: order already imported, skipping', [
                'order_number' => $order->order_number,
                'shopify_order_id' => $order->shopify_order_id,
            ]);
            return;
        }

        try {
            $shopifyOrderId = $pushService->createOrderFromMarketplace($order);

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

                Log::info('ImportReverbOrderToShopify: order imported successfully', [
                    'order_number' => $order->order_number,
                    'shopify_order_id' => $shopifyOrderId,
                ]);
            } else {
                $this->markImportFailed($order, 'Shopify order creation returned null');
            }
        } catch (\Throwable $e) {
            Log::error('ImportReverbOrderToShopify: import failed (will retry up to ' . $this->tries . ' times)', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function markImportFailed(ReverbOrderMetric $order, string $error): void
    {
        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportReverbOrderToShopify: marked as import_failed', [
            'order_number' => $order->order_number,
            'error' => $error,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = ReverbOrderMetric::find($this->reverbOrderMetricId);
        if ($order && !$order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportReverbOrderToShopify: job failed permanently', [
            'order_id' => $this->reverbOrderMetricId,
            'error' => $exception->getMessage(),
        ]);
    }
}
