<?php

namespace App\Console\Commands;

use App\Services\ShopifyCatalogSyncService;
use Illuminate\Console\Command;

class SyncShopifyCatalogCommand extends Command
{
    protected $signature = 'shopify:sync {--store=main : Store key: main}';

    protected $description = 'Sync main Shopify products/variants into shopify_catalog_* tables.';

    public function handle(ShopifyCatalogSyncService $sync): int
    {
        $store = strtolower((string) $this->option('store'));
        if ($store !== 'main') {
            $this->warn('For PLS use: php artisan shopify-pls:sync. Using main.');
            $store = 'main';
        }

        $this->info('Syncing Shopify catalog (main)...');
        $result = $sync->syncCatalog('main');
        $this->info("Upserted ~{$result['products']} product rows, ~{$result['variants']} variant rows.");

        return self::SUCCESS;
    }
}
