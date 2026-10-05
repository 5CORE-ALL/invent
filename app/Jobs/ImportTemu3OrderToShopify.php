<?php

namespace App\Jobs;

use App\Models\Temu3ApiOrder;
use App\Services\MarketplaceManager\Temu3OrderPushService;
use App\Services\MarketplaceManager\Temu3OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportTemu3OrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $temuOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('temu3'));
    }

    public function middleware(): array
    {
        $order = Temu3ApiOrder::find($this->temuOrderId);
        $key = $order?->parent_order_sn
            ? 'temu3_import_parent:'.$order->parent_order_sn
            : 'temu3_import:'.$this->temuOrderId;

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(Temu3OrderPushService $pushService): void
    {
        $order = Temu3ApiOrder::find($this->temuOrderId);
        if (! $order) {
            Log::warning('ImportTemu3OrderToShopify: order not found', ['id' => $this->temuOrderId]);

            return;
        }

        if ($order->shopify_order_id) {
            $pushService->importToShopify($order);

            return;
        }

        $parent = trim((string) $order->parent_order_sn);

        // Hard stop for cancelled / delivered / closed even if a job was already queued.
        $sync = app(Temu3OrderSyncService::class);
        if (! $sync->isEligibleForAutoImport($order)) {
            if ($parent !== '') {
                Temu3ApiOrder::query()
                    ->where('parent_order_sn', $parent)
                    ->whereNull('shopify_order_id')
                    ->update(['import_status' => 'skipped_closed']);
            } else {
                $order->update(['import_status' => 'skipped_closed']);
            }
            Log::info('ImportTemu3OrderToShopify: skipped ineligible order', [
                'id' => $this->temuOrderId,
                'parent_order_sn' => $parent,
                'status' => Temu3OrderSyncService::resolveOrderStatus($order),
                'parent_order_time' => $order->parent_order_time,
            ]);

            return;
        }

        $sibling = $parent !== ''
            ? Temu3ApiOrder::query()
                ->where('parent_order_sn', $parent)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->first()
            : null;
        if ($sibling) {
            Temu3ApiOrder::query()
                ->where('parent_order_sn', $parent)
                ->whereNull('shopify_order_id')
                ->update([
                    'shopify_order_id' => $sibling->shopify_order_id,
                    'pushed_to_shopify_at' => $sibling->pushed_to_shopify_at ?? now(),
                    'import_status' => 'imported',
                ]);
            $pushService->importToShopify($order->fresh() ?? $order);

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

        Temu3ApiOrder::query()
            ->where('parent_order_sn', $parent !== '' ? $parent : $order->parent_order_sn)
            ->whereNull('shopify_order_id')
            ->update(['import_status' => 'import_failed']);

        Log::warning('ImportTemu3OrderToShopify: import failed', [
            'id' => $this->temuOrderId,
            'parent_order_sn' => $parent,
            'reason' => $pushService->lastFailureReason,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = Temu3ApiOrder::find($this->temuOrderId);
        if ($order && ! $order->shopify_order_id) {
            $parent = trim((string) $order->parent_order_sn);
            Temu3ApiOrder::query()
                ->where('parent_order_sn', $parent !== '' ? $parent : $order->parent_order_sn)
                ->whereNull('shopify_order_id')
                ->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTemu3OrderToShopify: job failed', [
            'order_id' => $this->temuOrderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
