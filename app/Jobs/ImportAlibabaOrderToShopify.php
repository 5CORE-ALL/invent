<?php

namespace App\Jobs;

use App\Models\AlibabaOrderMetric;
use App\Services\MarketplaceManager\AlibabaOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportAlibabaOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $alibabaOrderMetricId
    ) {
        $this->onQueue('alibaba');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("alibaba_import:{$this->alibabaOrderMetricId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(AlibabaOrderPushService $pushService): void
    {
        $order = AlibabaOrderMetric::find($this->alibabaOrderMetricId);
        if (! $order) {
            Log::warning('ImportAlibabaOrderToShopify: order not found', ['id' => $this->alibabaOrderMetricId]);

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
            Log::info('ImportAlibabaOrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
            ]);

            return;
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportAlibabaOrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason,
        ]);
    }
}
