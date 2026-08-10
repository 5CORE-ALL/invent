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
        $order = TemuOrder::find($this->temuOrderId);
        $key = $order?->parent_order_sn
            ? 'temu_import_parent:'.$order->parent_order_sn
            : 'temu_import:'.$this->temuOrderId;

        return [
            (new WithoutOverlapping($key))
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

        $parent = trim((string) $order->parent_order_sn);
        $sibling = $parent !== ''
            ? TemuOrder::query()
                ->where('parent_order_sn', $parent)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->first()
            : null;
        if ($sibling) {
            TemuOrder::query()
                ->where('parent_order_sn', $parent)
                ->whereNull('shopify_order_id')
                ->update([
                    'shopify_order_id' => $sibling->shopify_order_id,
                    'pushed_to_shopify_at' => $sibling->pushed_to_shopify_at ?? now(),
                    'import_status' => 'imported',
                ]);

            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);
        if ($shopifyOrderId) {
            return;
        }

        TemuOrder::query()
            ->where('parent_order_sn', $parent !== '' ? $parent : $order->parent_order_sn)
            ->whereNull('shopify_order_id')
            ->update(['import_status' => 'import_failed']);

        Log::warning('ImportTemuOrderToShopify: import failed', [
            'id' => $this->temuOrderId,
            'parent_order_sn' => $parent,
            'reason' => $pushService->lastFailureReason,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = TemuOrder::find($this->temuOrderId);
        if ($order && ! $order->shopify_order_id) {
            $parent = trim((string) $order->parent_order_sn);
            TemuOrder::query()
                ->where('parent_order_sn', $parent !== '' ? $parent : $order->parent_order_sn)
                ->whereNull('shopify_order_id')
                ->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTemuOrderToShopify: job failed', [
            'order_id' => $this->temuOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
