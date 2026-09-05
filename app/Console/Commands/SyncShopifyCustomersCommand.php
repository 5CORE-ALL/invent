<?php

namespace App\Console\Commands;

use App\Services\Crm\Contracts\ShopifyServiceInterface;
use Illuminate\Console\Command;

class SyncShopifyCustomersCommand extends Command
{
    protected $signature = 'shopify:sync-customers
        {--limit=250 : Shopify REST page size (1-250)}';

    protected $description = 'Pull Shopify customers into shopify_customers and refresh Last synced.';

    public function handle(ShopifyServiceInterface $shopify): int
    {
        if (! $shopify->isConfigured()) {
            $this->error('Shopify is not configured.');

            return self::FAILURE;
        }

        $count = $shopify->syncCustomers((int) $this->option('limit'));
        $this->info("Synced {$count} Shopify customers.");

        return self::SUCCESS;
    }
}
