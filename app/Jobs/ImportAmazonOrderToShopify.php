<?php

namespace App\Jobs;

use App\Models\AmazonOrder;
use App\Services\MarketplaceManager\AmazonOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportAmazonOrderToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        protected int $amazonOrderId
    ) {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('amazon'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("amazon_import:{$this->amazonOrderId}"))
                ->releaseAfter(120)
                ->expireAfter(600),
        ];
    }

    public function handle(AmazonOrderPushService $pushService): void
    {
        $order = AmazonOrder::find($this->amazonOrderId);
        if (! $order) {
            Log::warning('ImportAmazonOrderToShopify: order not found', ['id' => $this->amazonOrderId]);

            return;
        }

        $shopifyOrderId = $pushService->importToShopify($order);
        if ($shopifyOrderId === null) {
            Log::info('ImportAmazonOrderToShopify: stub — import not implemented', [
                'amazon_order_id' => $order->amazon_order_id,
            ]);
        }
    }
}
