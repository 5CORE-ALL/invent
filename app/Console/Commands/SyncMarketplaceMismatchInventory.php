<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceMismatchInventory extends Command
{
    protected $signature = 'marketplace:sync-mismatch-inventory
                            {marketplace : Marketplace slug, e.g. amazon}';

    protected $description = 'Push Shopify qty for Inv SKU Mismatch SKUs directly (not via the marketplace queue).';

    public function handle(MarketplaceMismatchInventoryPass $pass): int
    {
        @set_time_limit(0);
        $marketplace = strtolower(trim((string) $this->argument('marketplace')));

        $result = $pass->run($marketplace);
        Log::info('marketplace:sync-mismatch-inventory done', [
            'marketplace' => $marketplace,
            'result' => $result,
        ]);

        $this->info($result['message'] ?? 'Done.');
        $this->line(sprintf(
            'attempted=%d updated=%d failed=%d skipped=%d remaining=%d',
            (int) ($result['attempted'] ?? 0),
            (int) ($result['updated'] ?? 0),
            (int) ($result['failed'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            (int) ($result['remaining'] ?? 0)
        ));

        return self::SUCCESS;
    }
}
