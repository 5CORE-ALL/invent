<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Every 5 minutes: take tracking already on /sales-order-fulfillment and
 * fulfill Shopify copies that are still Unfulfilled. Separate from the
 * 40-minute Veeqo fetch job so this cannot be unique-locked behind it.
 */
class PushSofTrackingToShopifyJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public int $uniqueFor = 280;

    public bool $failOnTimeout = false;

    public function __construct(
        public int $limit = 200,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function uniqueId(): string
    {
        return 'mm-sof-tracking-to-shopify';
    }

    public function handle(VeeqoShopifyFulfillmentService $sync): void
    {
        try {
            $fromShopify = $sync->syncUnfulfilledShopifyFromSofTracking($this->limit);
            Log::info('PushSofTrackingToShopifyJob: shopify-unfulfilled', $fromShopify);
            $fromRows = $sync->syncSofTrackingToUnfulfilledShopify(max(80, (int) ceil($this->limit * 0.5)));
            Log::info('PushSofTrackingToShopifyJob: marketplace-rows', $fromRows);
        } catch (\Throwable $e) {
            Log::error('PushSofTrackingToShopifyJob: failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
