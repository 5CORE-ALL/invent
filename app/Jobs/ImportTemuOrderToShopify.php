<?php

namespace App\Jobs;

use App\Models\TemuOrder;
use App\Services\MarketplaceManager\TemuOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportTemuOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $temuOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('temu'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("temu_import:{$this->temuOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(TemuOrderPushService $pushService): void
    {
        $order = TemuOrder::find($this->temuOrderId);
        if (! $order) {
            Log::warning('ImportTemuOrderToShopify: order not found', ['id' => $this->temuOrderId]);

            return;
        }

        if ($order->shopify_order_id) {
            return;
        }

        Log::info('ImportTemuOrderToShopify: not fully implemented — skipping', [
            'parent_order_sn' => $order->parent_order_sn,
            'order_sn' => $order->order_sn,
        ]);

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
        $order = TemuOrder::find($this->temuOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTemuOrderToShopify: job failed', [
            'order_id' => $this->temuOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
