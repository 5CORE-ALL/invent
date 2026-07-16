<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\ShopifyCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of live-verified Shopify catalog (shopify_catalog_*).
 * Do not run on page request.
 */
class WarmShopifyLiveCatalogCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('reverb'));
    }

    public function handle(ShopifyCatalogSyncService $sync): void
    {
        $result = $sync->syncCatalog('main');
        Log::info('WarmShopifyLiveCatalogCache: warmed', $result);
    }
}
