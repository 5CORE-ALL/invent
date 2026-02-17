<?php

namespace App\Console\Commands;

use App\Models\ReverbProduct;
use App\Services\ReverbListingService;
use App\Services\ShopifyApiService;
use Illuminate\Console\Command;

class SyncReverbInventoryFromShopify extends Command
{
    protected $signature = 'reverb:sync-inventory-from-shopify
                            {--dry-run : Only show what would be updated}';

    protected $description = 'Sync Reverb listing inventory from Shopify (Shopify as source of truth). Run after Shopify orders or on a schedule for bi-directional bridge.';

    public function handle(ShopifyApiService $shopify, ReverbListingService $reverb): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run – no Reverb API updates will be made.');
        }

        $products = ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get();

        if ($products->isEmpty()) {
            $this->info('No Reverb products with listing ID found. Run reverb:fetch first.');
            return self::SUCCESS;
        }

        $skus = $products->pluck('sku')->unique()->values()->all();
        $this->info('Fetching Shopify inventory for ' . count($skus) . ' SKUs...');
        $shopifyQuantities = $shopify->getInventoryQuantitiesBySku($skus);

        $updated = 0;
        $failed = 0;
        foreach ($products as $product) {
            $listingId = $product->reverb_listing_id;
            $sku = $product->sku;
            $qty = $shopifyQuantities[$sku] ?? 0;

            if ($dryRun) {
                $this->line("  [dry-run] {$sku} (listing {$listingId}) => inventory {$qty}");
                $updated++;
                continue;
            }

            if ($reverb->updateListingInventory($listingId, $qty)) {
                $updated++;
                $this->info("  Updated {$sku} => {$qty}");
            } else {
                $failed++;
            }
            usleep(400000); // ~2.5 requests/sec to avoid rate limit
        }

        $this->info("Done. Updated: {$updated}, Failed: {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
