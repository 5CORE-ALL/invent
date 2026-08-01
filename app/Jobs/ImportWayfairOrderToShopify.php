<?php

namespace App\Jobs;

use App\Models\WayfairDailyData;
use App\Services\MarketplaceManager\WayfairOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportWayfairOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $wfOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('wayfair'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("wf_import:{$this->wfOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(WayfairOrderPushService $pushService): void
    {
        $order = WayfairDailyData::find($this->wfOrderId);
        if (! $order) {
            Log::warning('ImportWayfairOrderToShopify: order not found', ['id' => $this->wfOrderId]);

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

        $order->update(['import_status' => 'import_failed']);
    }

    public function failed(\Throwable $exception): void
    {
        $order = WayfairDailyData::find($this->wfOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportWayfairOrderToShopify: job failed', [
            'order_id' => $this->wfOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
