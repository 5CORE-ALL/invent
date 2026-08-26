<?php

namespace App\Jobs;

use App\Models\BestBuySale;
use App\Services\MarketplaceManager\BestBuyOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportBestBuyOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $bbOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('bestbuy'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("bb_import:{$this->ppOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(BestBuyOrderPushService $pushService): void
    {
        $order = BestBuySale::find($this->ppOrderId);
        if (! $order) {
            Log::warning('ImportBestBuyOrderToShopify: order not found', ['id' => $this->ppOrderId]);

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
        $order = BestBuySale::find($this->ppOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportBestBuyOrderToShopify: job failed', [
            'order_id' => $this->ppOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
