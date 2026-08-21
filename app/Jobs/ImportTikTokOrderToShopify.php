<?php

namespace App\Jobs;

use App\Models\TiktokOrder;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TikTokOrderPushService;
use App\Services\MarketplaceManager\TikTokOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportTikTokOrderToShopify implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        protected int $tiktokOrderId
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('tiktok'));
    }

    public function uniqueId(): string
    {
        return 'tiktok-import-'.$this->marketplaceOrderKey();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('tiktok_import:'.$this->marketplaceOrderKey()))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(TikTokOrderPushService $pushService): void
    {
        $order = TiktokOrder::find($this->tiktokOrderId);
        if (! $order) {
            Log::warning('ImportTikTokOrderToShopify: order not found', ['id' => $this->tiktokOrderId]);

            return;
        }

        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return;
        }

        if (! app(TikTokOrderSyncService::class)->isEligibleForAutoImport($order)) {
            Log::info('ImportTikTokOrderToShopify: skipped (already on Shopify, old, or not importable)', [
                'id' => $this->tiktokOrderId,
                'order_id' => $order->order_id,
                'status' => $order->order_status,
            ]);

            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);

        if ($shopifyOrderId) {
            Log::info('ImportTikTokOrderToShopify: success', [
                'order_id' => $order->order_id,
                'shopify_order_id' => $shopifyOrderId,
                'linked_existing' => $pushService->lastDuplicateLinkMessage !== null,
            ]);

            return;
        }

        $status = $pushService->lastApiStatus ?? null;
        if ($status === 429 || ($status !== null && $status >= 500)) {
            Log::warning('ImportTikTokOrderToShopify: temporary Shopify error, will retry', [
                'order_id' => $order->order_id,
                'status' => $status,
            ]);

            throw new RuntimeException($pushService->lastFailureReason ?: "Shopify HTTP {$status}");
        }

        $order->update(['import_status' => 'import_failed']);
        Log::error('ImportTikTokOrderToShopify: failed', [
            'order_id' => $order->order_id,
            'reason' => $pushService->lastFailureReason ?? 'Import returned no Shopify order id',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $order = TiktokOrder::find($this->tiktokOrderId);
        if ($order && ! $order->shopify_order_id) {
            $order->update(['import_status' => 'import_failed']);
        }
        Log::error('ImportTikTokOrderToShopify: job failed after all retries', [
            'id' => $this->tiktokOrderId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function marketplaceOrderKey(): string
    {
        $orderId = TiktokOrder::query()->where('id', $this->tiktokOrderId)->value('order_id');

        return $orderId ? (string) $orderId : (string) $this->tiktokOrderId;
    }
}
