<?php

namespace App\Jobs;

use App\Models\DobaDailyData;
use App\Services\MarketplaceManager\DobaOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportDobaOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $dobaOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('doba'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("doba_import:{$this->dobaOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(DobaOrderPushService $pushService): void
    {
        $order = DobaDailyData::find($this->dobaOrderId);
        if (! $order) {
            Log::warning('ImportDobaOrderToShopify: order not found', ['id' => $this->dobaOrderId]);

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
        $order = DobaDailyData::find($this->dobaOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportDobaOrderToShopify: job failed', [
            'order_id' => $this->dobaOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
