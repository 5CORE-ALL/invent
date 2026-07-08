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

class ImportAliexpressOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $aliexpressOrderMetricId
    ) {
        $this->onQueue('aliexpress');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("aliexpress_import:{$this->aliexpressOrderMetricId}"))
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
            ]);

            return;
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportAliexpressOrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason,
        ]);
    }
}
