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

    public int $tries = 1;

    public int $timeout = 1700;

    public int $uniqueFor = 1800;

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
            default => null,
        };

        if ($command === null) {
            Log::warning('SyncMarketplaceOrdersJob: unsupported marketplace', ['marketplace' => $slug]);

            return;
        }

        $params = [];
        if ($this->fromDate !== '') {
            $params['--from'] = $this->fromDate;
        } elseif ($this->days > 0) {
            $params['--days'] = $this->days;
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

            // After order fetch/import, backfill missing Shopify addresses when enabled.
            $this->queueAddressSyncIfEnabled($slug);
        } catch (\Throwable $e) {
            Log::error('SyncMarketplaceOrdersJob: failed', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function queueAddressSyncIfEnabled(string $slug): void
    {
        try {
            if ($slug === 'aliexpress' && \App\Services\MarketplaceManager\AliexpressOrderPushService::canAutoSyncAddress()) {
                SyncAliexpressAddressJob::dispatch(false, 25);
            } elseif ($slug === 'reverb' && \App\Services\MarketplaceManager\ReverbOrderPushService::canAutoSyncAddress()) {
                SyncReverbAddressJob::dispatch(false, 25);
            } elseif ($slug === 'newegg' && \App\Services\MarketplaceManager\NeweggOrderPushService::canAutoSyncAddress()) {
                SyncNeweggAddressJob::dispatch(false, 25);
            }
        } catch (\Throwable $e) {
            Log::warning('SyncMarketplaceOrdersJob: could not queue address sync', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
