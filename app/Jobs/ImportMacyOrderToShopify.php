<?php

namespace App\Jobs;

use App\Models\MacyOrderMetric;
use App\Services\MarketplaceManager\MacyOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportMacyOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $macyOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('macy'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("macy_import:{$this->macyOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(MacyOrderPushService $pushService): void
    {
        $order = MacyOrderMetric::find($this->macyOrderId);
        if (! $order) {
            Log::warning('ImportMacyOrderToShopify: order not found', ['id' => $this->macyOrderId]);

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

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        $reason = $pushService->lastFailureReason ?? null;
        if (\App\Services\MarketplaceManager\MarketplaceShopifyImportQueue::isRetryableShopifyFailure($status, $reason)) {
            throw new RuntimeException($reason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
    }

    public function failed(\Throwable $exception): void
    {
        $order = MacyOrderMetric::find($this->macyOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportMacyOrderToShopify: job failed', [
            'order_id' => $this->macyOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
