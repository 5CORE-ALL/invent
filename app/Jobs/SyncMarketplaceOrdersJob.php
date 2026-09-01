<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Fetch marketplace orders (+ optional Shopify import dispatch) on that channel's
 * dedicated mm-{slug} queue so all marketplaces run in parallel.
 *
 * Settings are enforced inside the artisan commands / OrderSyncService:
 * - fetch_orders off → skip fetch
 * - auto_import_to_shopify off → no import jobs even when $import=true
 */
class SyncMarketplaceOrdersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1700;

    public int $uniqueFor = 1800;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $marketplace,
        public string $fromDate = '',
        public bool $import = true,
        public int $days = 0,
    ) {
        $this->marketplace = strtolower(trim($this->marketplace));
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->marketplace));
    }

    public function uniqueId(): string
    {
        return 'mm-orders-'.$this->marketplace;
    }

    public function handle(): void
    {
        $slug = $this->marketplace;
        $command = match ($slug) {
            'reverb' => 'reverb:manager-sync-orders',
            'aliexpress' => 'aliexpress:sync-orders',
            'alibaba' => 'alibaba:sync-orders',
            'newegg' => 'newegg:sync-orders',
            'shein' => 'shein:sync-orders',
            'amazon' => 'amazon:sync-orders',
            'topdawg' => 'topdawg:sync-orders',
            'temu' => 'temu:sync-orders',
            'temu2' => 'temu2:sync-orders',
            'purchasingpower' => 'purchasingpower:sync-orders',
            'wayfair' => 'wayfair:sync-orders',
            'bestbuy' => 'bestbuy:sync-orders',
            'macy' => 'macy:sync-orders',
            'doba' => 'doba:sync-orders',
            'ebay1' => 'ebay1:sync-orders',
            'ebay2' => 'ebay2:sync-orders',
            'ebay3' => 'ebay3:sync-orders',
            'faire' => 'faire:sync-orders',
            'tiktok2' => 'tiktok2:sync-orders',
            'tiktok' => 'tiktok:sync-orders',
            default => null,
        };

        if ($command === null) {
            Log::warning('SyncMarketplaceOrdersJob: unsupported marketplace', ['marketplace' => $slug]);

            return;
        }

        $params = [];
        $from = $this->fromDate;
        if ($from === '' && $this->days > 0) {
            $from = now()->subHours($this->days * 24)->toDateTimeString();
        }
        if ($from !== '') {
            $params['--from'] = $from;
        }
        if ($this->import) {
            $params['--import'] = true;
        }

        Log::info('SyncMarketplaceOrdersJob: starting', [
            'marketplace' => $slug,
            'queue' => MarketplaceManagerRegistry::queueFor($slug),
            'params' => $params,
        ]);

        try {
            Artisan::call($command, $params);
            Log::info('SyncMarketplaceOrdersJob: completed', [
                'marketplace' => $slug,
                'output' => trim(Artisan::output()),
            ]);

            // After order fetch/import, backfill addresses then fulfill Shopify + push tracking.
            $this->queueAddressSyncIfEnabled($slug);
            $this->queueTrackingSync($slug);
        } catch (\Throwable $e) {
            Log::error('SyncMarketplaceOrdersJob: failed', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);
            // Do not rethrow: ShouldBeUnique would keep the lock and skip the next cycle.
            $this->queueAddressSyncIfEnabled($slug);
            $this->queueTrackingSync($slug);
        }
    }

    protected function queueTrackingSync(string $slug): void
    {
        try {
            \App\Services\MarketplaceManager\MarketplaceChannelFulfillmentHub::dispatchTrackingJob($slug, 40);
        } catch (\Throwable $e) {
            Log::warning('SyncMarketplaceOrdersJob: could not queue tracking sync', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function queueAddressSyncIfEnabled(string $slug): void
    {
        try {
            if ($slug === 'aliexpress' && \App\Services\MarketplaceManager\AliexpressOrderPushService::canAutoSyncAddress()) {
                SyncAliexpressAddressJob::dispatch(false, 25);
            } elseif ($slug === 'alibaba' && \App\Services\MarketplaceManager\AlibabaOrderPushService::canAutoSyncAddress()) {
                SyncAlibabaAddressJob::dispatch(false, 25);
            } elseif ($slug === 'reverb' && \App\Services\MarketplaceManager\ReverbOrderPushService::canAutoSyncAddress()) {
                SyncReverbAddressJob::dispatch(false, 25);
            } elseif ($slug === 'newegg' && \App\Services\MarketplaceManager\NeweggOrderPushService::canAutoSyncAddress()) {
                SyncNeweggAddressJob::dispatch(false, 25);
            } elseif ($slug === 'shein') {
                if (\App\Services\MarketplaceManager\SheinOrderDetailService::canAutoAccept()) {
                    SyncSheinAcceptJob::dispatch(false, 25);
                }
                if (\App\Services\MarketplaceManager\SheinOrderPushService::canAutoSyncAddress()) {
                    SyncSheinAddressJob::dispatch(false, 25);
                }
            } elseif ($slug === 'topdawg' && \App\Services\MarketplaceManager\TopDawgOrderPushService::canAutoSyncAddress()) {
                SyncTopDawgAddressJob::dispatch(false, 25);
            // Temu / Temu 2: no auto address apply from cron (manual only).
            } elseif ($slug === 'purchasingpower' && \App\Services\MarketplaceManager\PurchasingPowerOrderPushService::canAutoSyncAddress()) {
                SyncPurchasingPowerAddressJob::dispatch(false, 25);
            } elseif ($slug === 'wayfair' && \App\Services\MarketplaceManager\WayfairOrderPushService::canAutoSyncAddress()) {
                SyncWayfairAddressJob::dispatch(false, 25);
            } elseif ($slug === 'bestbuy' && \App\Services\MarketplaceManager\BestBuyOrderPushService::canAutoSyncAddress()) {
                SyncBestBuyAddressJob::dispatch(false, 25);
            } elseif ($slug === 'macy' && \App\Services\MarketplaceManager\MacyOrderPushService::canAutoSyncAddress()) {
                SyncMacyAddressJob::dispatch(false, 25);
            } elseif ($slug === 'doba' && \App\Services\MarketplaceManager\DobaOrderPushService::canAutoSyncAddress()) {
                SyncDobaAddressJob::dispatch(false, 25);
            } elseif ($slug === 'amazon' && \App\Services\MarketplaceManager\AmazonOrderPushService::canAutoSyncAddress()) {
                SyncAmazonAddressJob::dispatch(false, 25);
            } elseif ($slug === 'ebay1' && \App\Services\MarketplaceManager\Ebay1OrderPushService::canAutoSyncAddress()) {
                SyncEbay1AddressJob::dispatch(false, 25);
            } elseif ($slug === 'ebay2' && \App\Services\MarketplaceManager\Ebay2OrderPushService::canAutoSyncAddress()) {
                SyncEbay2AddressJob::dispatch(false, 25);
            } elseif ($slug === 'ebay3' && \App\Services\MarketplaceManager\Ebay3OrderPushService::canAutoSyncAddress()) {
                SyncEbay3AddressJob::dispatch(false, 25);
            } elseif ($slug === 'faire' && \App\Services\MarketplaceManager\FaireOrderPushService::canAutoSyncAddress()) {
                \App\Jobs\SyncFaireAddressJob::dispatch(false, 25);
            } elseif ($slug === 'tiktok2' && \App\Services\MarketplaceManager\TikTok2OrderPushService::canAutoSyncAddress()) {
                \App\Jobs\SyncTikTok2AddressJob::dispatch(false, 25);
            } elseif ($slug === 'tiktok' && \App\Services\MarketplaceManager\TikTokOrderPushService::canAutoSyncAddress()) {
                \App\Jobs\SyncTikTokAddressJob::dispatch(false, 25);
            }
        } catch (\Throwable $e) {
            Log::warning('SyncMarketplaceOrdersJob: could not queue address sync', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
