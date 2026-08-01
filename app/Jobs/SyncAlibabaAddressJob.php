<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AlibabaOrderPushService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fill missing Shopify shipping/customer address from Alibaba for linked orders (stub).
 */
class SyncAlibabaAddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('alibaba'));
    }

    public function uniqueId(): string
    {
        return 'mm-alibaba-address-sync';
    }

    public function handle(AlibabaOrderPushService $push): void
    {
        if ($this->respectSettings && ! AlibabaOrderPushService::canAutoSyncAddress()) {
            Log::info('SyncAlibabaAddressJob: skipped (sync_address_to_shopify Off)');

            return;
        }

        $result = $push->syncPendingAddressesToShopify($this->limit);

        Log::info('SyncAlibabaAddressJob: completed', $result);
    }
}
