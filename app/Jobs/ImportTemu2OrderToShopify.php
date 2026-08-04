<?php

namespace App\Jobs;

use App\Models\Temu2Order;
use App\Services\MarketplaceManager\Temu2OrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportTemu2OrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $temuOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('temu2'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("temu_import:{$this->temuOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(Temu2OrderPushService $pushService): void
    {
        $order = Temu2Order::find($this->temuOrderId);
        if (! $order) {
            Log::warning('ImportTemu2OrderToShopify: order not found', ['id' => $this->temuOrderId]);

            return;
        }

        if ($order->shopify_order_id) {
            return;
        }

        Log::info('ImportTemu2OrderToShopify: not fully implemented — skipping', [
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
        $order = Temu2Order::find($this->temuOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTemu2OrderToShopify: job failed', [
            'order_id' => $this->temuOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
