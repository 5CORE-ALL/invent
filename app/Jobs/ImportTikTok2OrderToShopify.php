<?php

namespace App\Jobs;

use App\Models\Tiktok2Order;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TikTok2OrderPushService;
use App\Services\MarketplaceManager\TikTok2OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportTikTok2OrderToShopify implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $tiktok2OrderId
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('tiktok2'));
    }

    public function uniqueId(): string
    {
        return 'tiktok2-import-'.$this->marketplaceOrderKey();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('tiktok2_import:'.$this->marketplaceOrderKey()))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(TikTok2OrderPushService $pushService): void
    {
        $order = Tiktok2Order::find($this->tiktok2OrderId);
        if (! $order) {
            Log::warning('ImportTikTok2OrderToShopify: order not found', ['id' => $this->tiktok2OrderId]);

            return;
        }

        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return;
        }

        if (! app(TikTok2OrderSyncService::class)->isEligibleForAutoImport($order)) {
            Log::info('ImportTikTok2OrderToShopify: skipped (already on Shopify, old, or not importable)', [
                'id' => $this->tiktok2OrderId,
                'order_id' => $order->order_id,
                'status' => $order->order_status,
            ]);

            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);

        if ($shopifyOrderId) {
            Log::info('ImportTikTok2OrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
                'linked_existing' => $pushService->lastDuplicateLinkMessage !== null,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        $reason = $pushService->lastFailureReason ?? null;
        if (\App\Services\MarketplaceManager\MarketplaceShopifyImportQueue::isRetryableShopifyFailure($status, $reason)) {
            Log::warning('ImportTikTok2OrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
            ]);

            throw new RuntimeException($reason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportTikTok2OrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason ?? 'Import returned no Shopify order id',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = Tiktok2Order::find($this->tiktok2OrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTikTok2OrderToShopify: job failed after all retries', [
            'id' => $this->tiktok2OrderId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function marketplaceOrderKey(): string
    {
        $orderId = Tiktok2Order::query()->where('id', $this->tiktok2OrderId)->value('order_id');

        return $orderId ? (string) $orderId : (string) $this->tiktok2OrderId;
    }
}
