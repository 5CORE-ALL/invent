<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ShopifyApiInventoryController;

class SaveDailyShopifySkus extends Command
{
    protected $signature = 'shopify:save-daily-inventory';
    protected $description = 'Save daily Shopify inventory data';

    public function handle()
    {
        $controller = new ShopifyApiInventoryController();
        $success = $controller->saveDailyInventory();
        
        if ($success) {
            $this->info('Successfully saved daily Shopify inventory data');
        } else {
            $this->error('Failed to save daily Shopify inventory data');
        }
    }
}



//to run php artisan shopify:save-daily-inventory