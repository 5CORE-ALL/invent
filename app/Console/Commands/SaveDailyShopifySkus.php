<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ShopifyApiInventoryController;

/**
 * Pulls Shopify SKU snapshot into the app (ShopifyApiInventoryController::saveDailyInventory).
 *
 * Production cron: `scripts/cron-shopify-save-daily-inventory.sh` (lock + log). Not in Laravel schedule.
 * Raw equivalent: `php /path/to/artisan shopify:save-daily-inventory`
 */
class SaveDailyShopifySkus extends Command
{
    protected $signature = 'shopify:save-daily-inventory';

    protected $description = 'Save daily Shopify inventory data';

    public function handle(): int
    {
        $controller = new ShopifyApiInventoryController();
        $success = $controller->saveDailyInventory();

        if ($success) {
            $this->info('Successfully saved daily Shopify inventory data');

            return self::SUCCESS;
        }

        $this->error('Failed to save daily Shopify inventory data');

        return self::FAILURE;
    }
}