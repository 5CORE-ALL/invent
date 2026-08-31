<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AliexpressOrderPushService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fill missing Shopify shipping/customer address from AliExpress for linked orders.
 */
class SyncAliexpressAddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 850;

    public int $uniqueFor = 900;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('aliexpress'));
    }

    public function uniqueId(): string
    {
        return 'mm-aliexpress-address-sync';
    }

    public function handle(AliexpressOrderPushService $push): void
    {
        if ($this->respectSettings && ! AliexpressOrderPushService::canAutoSyncAddress()) {
            Log::info('SyncAliexpressAddressJob: skipped (sync_address_to_shopify Off)');

            return;
        }

        $result = $push->syncPendingAddressesToShopify($this->limit);

        Log::info('SyncAliexpressAddressJob: completed', $result);
    }
}
