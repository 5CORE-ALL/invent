<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Console\Command;

class FetchMarketplaceShopifyTrackingCommand extends Command
{
    protected $signature = 'marketplace:fetch-shopify-tracking
                            {--limit=250 : Max Shopify copies + linked marketplace orders to check}
                            {--amazon= : Fulfill one Shopify copy by Amazon order id}
                            {--name= : Shopify order number, e.g. 331615}';

    protected $description = 'Fetch Veeqo / GOFO tracking onto unfulfilled Shopify copies for every marketplace.';

    public function handle(VeeqoShopifyFulfillmentService $sync): int
    {
        $amazon = trim((string) $this->option('amazon'));
        if ($amazon !== '') {
            $name = trim((string) $this->option('name')) ?: null;
            $this->info('Looking up Veeqo/GOFO tracking for Amazon '.$amazon.' and fulfilling Shopify.');
            $result = $sync->fulfillShopifyAmazonOrder($amazon, $name);
            $this->info($result['message'] ?? 'Done.');
            if (! empty($result['tracking'])) {
                $this->info('Tracking: '.$result['tracking'].' ('.($result['carrier'] ?? '').')');
            }

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $this->info('Checking every marketplace: Veeqo and GOFO (4Seller) labels → Shopify fulfill.');
        $result = $sync->syncPendingUnfulfilled($limit);
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
