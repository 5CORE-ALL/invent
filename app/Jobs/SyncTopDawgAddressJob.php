<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TopDawgOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fill missing Shopify shipping/customer address from TopDawg for linked orders.
 */
class SyncTopDawgAddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('topdawg'));
    }

    public function uniqueId(): string
    {
        return 'mm-topdawg-address-sync';
    }

    public function handle(TopDawgOrderPushService $push): void
    {
        if ($this->respectSettings && ! TopDawgOrderPushService::canAutoSyncAddress()) {
            Log::info('SyncTopDawgAddressJob: skipped (sync_address_to_shopify Off)');

            return;
        }

        $result = $push->syncPendingAddressesToShopify($this->limit);

        Log::info('SyncTopDawgAddressJob: completed', $result);
    }
}
