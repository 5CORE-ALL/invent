<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Console\Command;

class FetchMarketplaceShopifyTrackingCommand extends Command
{
    protected $signature = 'marketplace:fetch-shopify-tracking
                            {--limit=200 : Max linked marketplace orders to check}';

    protected $description = 'Fetch Veeqo / GOFO (4Seller) / marketplace tracking onto unfulfilled Shopify copies.';

    public function handle(VeeqoShopifyFulfillmentService $sync): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $this->info('Checking Veeqo and GOFO (4Seller) for labels, then fulfilling Shopify copies.');
        $result = $sync->syncPendingUnfulfilled($limit);
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
