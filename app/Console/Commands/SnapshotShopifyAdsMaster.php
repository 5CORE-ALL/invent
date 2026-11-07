<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ShopifyAdsMasterController;
use Illuminate\Console\Command;

/**
 * Saves the /shopify-ads-master channel snapshot (and fills any missing days
 * from the ad sources) without anyone opening the page, so the trend chart and
 * the table's trend dots never depend on a person visiting that day.
 */
class SnapshotShopifyAdsMaster extends Command
{
    protected $signature = 'shopify-ads-master:snapshot';

    protected $description = 'Save today\'s Shopify Ads Master channel snapshot and fill missing history days';

    public function handle(): int
    {
        $controller = app(ShopifyAdsMasterController::class);

        // data() writes today's snapshot; history() stores any calculated gap days.
        $controller->data();
        $controller->history(request()->duplicate(['days' => 32]));

        $this->info('Shopify Ads Master snapshot saved.');

        return self::SUCCESS;
    }
}
