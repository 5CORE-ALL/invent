<?php

namespace App\Console\Commands;

use App\Services\ShopifyCatalogSyncService;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Console\Command;

class SyncShopifyPlsCatalogCommand extends Command
{
    protected $signature = 'shopify-pls:sync';

    protected $description = 'Sync PLS Shopify products/variants into shopify_catalog_* (store=pls).';

    public function handle(ShopifyCatalogSyncService $sync): int
    {
        if (! app(ShopifyPlsTokenService::class)->isConfigured()) {
            $this->error('PLS Shopify credentials missing (services.prolightsounds).');

            return self::FAILURE;
        }

        $this->info('Syncing Shopify PLS catalog...');
        $result = $sync->syncCatalog('pls');
        $this->info("Upserted ~{$result['products']} product rows, ~{$result['variants']} variant rows.");

        return self::SUCCESS;
    }
}
