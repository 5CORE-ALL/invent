<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchMarketplaceShopifyTrackingCommand extends Command
{
    protected $signature = 'marketplace:fetch-shopify-tracking
                            {--limit=2000 : Max Shopify copies + linked marketplace orders to check}
                            {--fresh : Recheck Shopify copies even if recently cached}
                            {--all : Walk newest and oldest unfulfilled/partial copies, not only the latest page}
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
        $fresh = (bool) $this->option('fresh');
        $all = (bool) $this->option('all');
        $this->info('Checking every marketplace: Veeqo and GOFO (4Seller) labels → Shopify fulfill.');
        $this->info('Tracking is attached only after the full marketplace order id and SKU match the Shopify copy.');
        if ($fresh) {
            $this->info('Fresh pass: cached Shopify copies will be rechecked.');
        }
        if ($all) {
            $this->info('All-ages pass: newest and oldest Unfulfilled/Partial copies.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            Cache::put('mm.label_ssl_broken', 1, now()->addHours(2));
            Cache::put('mm.temu.ip_blocked', 1, now()->addHours(6));
            $this->warn('This Windows machine cannot reach Veeqo/GOFO (SSL) or Temu (IP whitelist). Tracking is taken from the local marketplace row after the full marketplace order id matches.');
        }
        $result = $sync->syncPendingUnfulfilled($limit, $fresh, $all);
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
