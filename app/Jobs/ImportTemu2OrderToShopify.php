<?php

namespace App\Jobs;

use App\Models\Temu2Order;
use App\Services\MarketplaceManager\Temu2OrderPushService;
use App\Services\MarketplaceManager\Temu2OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
        $order = Temu2Order::find($this->temuOrderId);
        $key = $order?->parent_order_sn
            ? 'temu2_import_parent:'.$order->parent_order_sn
            : 'temu2_import:'.$this->temuOrderId;

        return [
            (new WithoutOverlapping($key))
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

        $parent = trim((string) $order->parent_order_sn);

        // Hard stop for delivered / shipped / old backlog even if a job was already queued.
        $sync = app(Temu2OrderSyncService::class);
        if (! $sync->isEligibleForAutoImport($order)) {
            if ($parent !== '') {
                Temu2Order::query()
                    ->where('parent_order_sn', $parent)
                    ->whereNull('shopify_order_id')
                    ->update(['import_status' => 'skipped_closed']);
            } else {
                $order->update(['import_status' => 'skipped_closed']);
            }
            Log::info('ImportTemu2OrderToShopify: skipped ineligible order', [
                'id' => $this->temuOrderId,
                'parent_order_sn' => $parent,
                'status' => Temu2OrderSyncService::resolveOrderStatus($order),
                'parent_order_time' => $order->parent_order_time,
            ]);

            return;
        }

        $sibling = $parent !== ''
            ? Temu2Order::query()
                ->where('parent_order_sn', $parent)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->first()
            : null;
        if ($sibling) {
            Temu2Order::query()
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

        $status = $pushService->lastApiStatus ?? null;
        $reason = $pushService->lastFailureReason ?? null;
        if (\App\Services\MarketplaceManager\MarketplaceShopifyImportQueue::isRetryableShopifyFailure($status, $reason)) {
            throw new RuntimeException($reason ?: "Shopify HTTP {$status}");
        }

        Temu2Order::query()
            ->where('parent_order_sn', $parent !== '' ? $parent : $order->parent_order_sn)
            ->whereNull('shopify_order_id')
            ->update(['import_status' => 'import_failed']);

        Log::warning('ImportTemu2OrderToShopify: import failed', [
            'id' => $this->temuOrderId,
            'parent_order_sn' => $parent,
            'reason' => $pushService->lastFailureReason,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = Temu2Order::find($this->temuOrderId);
        if ($order && ! $order->shopify_order_id) {
            $parent = trim((string) $order->parent_order_sn);
            Temu2Order::query()
                ->where('parent_order_sn', $parent !== '' ? $parent : $order->parent_order_sn)
                ->whereNull('shopify_order_id')
                ->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTemu2OrderToShopify: job failed', [
            'order_id' => $this->temuOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
