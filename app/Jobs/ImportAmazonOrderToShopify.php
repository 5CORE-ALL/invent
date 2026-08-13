<?php

namespace App\Jobs;

use App\Models\AmazonOrder;
use App\Services\MarketplaceManager\AmazonOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportAmazonOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $amazonOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('amazon'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("amazon_import:{$this->amazonOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(AmazonOrderPushService $pushService): void
    {
        $order = AmazonOrder::find($this->amazonOrderId);
        if (! $order) {
            Log::warning('ImportAmazonOrderToShopify: order not found', ['id' => $this->amazonOrderId]);

            return;
        }

        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);

        if ($shopifyOrderId) {
            $order->refresh();
            Log::info('ImportAmazonOrderToShopify: success', [
                'amazon_order_id' => $order->amazon_order_id,
                'shopify_order_id' => $shopifyOrderId,
                'linked_existing' => $pushService->lastDuplicateLinkMessage !== null,
            ]);

            return;
        }

        if ($pushService->lastSkipStatus) {
            Log::info('ImportAmazonOrderToShopify: skipped', [
                'amazon_order_id' => $order->amazon_order_id,
                'status' => $pushService->lastSkipStatus,
                'reason' => $pushService->lastFailureReason,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        if ($status === 429 || ($status !== null && $status >= 500)) {
            Log::warning('ImportAmazonOrderToShopify: temporary Shopify error, will retry', [
                'amazon_order_id' => $order->amazon_order_id,
                'status' => $status,
                'reason' => $pushService->lastFailureReason ?? null,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportAmazonOrderToShopify: failed', [
            'amazon_order_id' => $order->amazon_order_id,
            'reason' => $pushService->lastFailureReason ?? 'Import returned no Shopify order id',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = AmazonOrder::find($this->amazonOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportAmazonOrderToShopify: job failed after all retries', [
            'id' => $this->amazonOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
