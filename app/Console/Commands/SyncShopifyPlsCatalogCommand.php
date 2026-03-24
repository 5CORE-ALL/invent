<?php

namespace App\Console\Commands;

use App\Services\ShopifyCatalogSyncService;
use Illuminate\Console\Command;

class SyncShopifyPlsCatalogCommand extends Command
{
    protected $signature = 'shopify-pls:sync';

    protected $description = 'Sync PLS Shopify products/variants into shopify_catalog_* (store=pls).';

    public function handle(ShopifyCatalogSyncService $sync): int
    {
        $domain = config('services.prolightsounds.domain') ?? config('services.prolightsounds.store_url');
        $token = config('services.prolightsounds.password') ?? config('services.prolightsounds.access_token');
        if (! $domain || ! $token) {
            $this->error('PLS Shopify credentials missing (services.prolightsounds).');

            return self::FAILURE;
        }

        $this->info('Syncing Shopify PLS catalog...');
        $result = $sync->syncCatalog('pls');
        $this->info("Upserted ~{$result['products']} product rows, ~{$result['variants']} variant rows.");

        return self::SUCCESS;
    }
}
