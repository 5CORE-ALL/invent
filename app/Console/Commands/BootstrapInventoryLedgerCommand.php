<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\InventoryLedgerService;
use Illuminate\Console\Command;

class BootstrapInventoryLedgerCommand extends Command
{
    protected $signature = 'mm:bootstrap-inventory-ledger';

    protected $description = 'Seed mm_inventory_ledgers from shopify_catalog_variants (+ WMS inventory_item_id map)';

    public function handle(InventoryLedgerService $ledger): int
    {
        $this->info('Bootstrapping inventory ledger…');
        $result = $ledger->bootstrapFromCatalog();
        $this->info("Upserted: {$result['upserted']} (with inventory_item_id: {$result['with_item_id']})");

        return self::SUCCESS;
    }
}
