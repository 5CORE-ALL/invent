<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TemuOrderPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fill missing Shopify shipping/customer address from Temu for linked orders.
 */
class SyncTemuAddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('temu'));
    }

    public function uniqueId(): string
    {
        return 'mm-temu-address-sync';
    }

    public function handle(TemuOrderPushService $push): void
    {
        if ($this->respectSettings && ! TemuOrderPushService::canAutoSyncAddress()) {
            Log::info('SyncTemuAddressJob: skipped (sync_address_to_shopify Off)');

            return;
        }

        $result = $push->syncPendingAddressesToShopify($this->limit);

        Log::info('SyncTemuAddressJob: completed', $result);
    }
}
