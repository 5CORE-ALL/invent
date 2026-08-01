<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\Ebay2OrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fill missing Shopify shipping/customer address from eBay 2 for linked orders.
 */
class SyncEbay2AddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('ebay2'));
    }

    public function uniqueId(): string
    {
        return 'mm-ebay2-address-sync';
    }

    public function handle(Ebay2OrderPushService $push): void
    {
        if ($this->respectSettings && ! Ebay2OrderPushService::canAutoSyncAddress()) {
            Log::info('SyncEbay2AddressJob: skipped (sync_address_to_shopify Off)');

            return;
        }

        $result = $push->syncPendingAddressesToShopify($this->limit);

        Log::info('SyncEbay2AddressJob: completed', $result);
    }
}
