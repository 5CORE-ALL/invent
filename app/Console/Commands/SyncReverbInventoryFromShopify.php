<?php

namespace App\Console\Commands;

use App\Models\ReverbProduct;
use App\Models\ReverbSyncState;
use App\Services\ReverbListingService;
use App\Services\ReverbSyncLogService;
use App\Services\ShopifyApiService;
use Illuminate\Console\Command;

class SyncReverbInventoryFromShopify extends Command
{
    protected $signature = 'reverb:sync-inventory-from-shopify
                            {--dry-run : Only show what would be updated}
                            {--all : Sync all listings; default is active only}';

    protected $description = 'Sync Reverb listing inventory from Shopify (Shopify as source of truth). Run after Shopify orders or on a schedule for bi-directional bridge.';

    public function handle(ShopifyApiService $shopify, ReverbListingService $reverb, ReverbSyncLogService $syncLog): int
    {
        $dryRun = $this->option('dry-run');
        $syncAll = $this->option('all');
        if ($dryRun) {
            $this->warn('Dry run – no Reverb API updates will be made.');
        }

        $query = ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '');
        if (! $syncAll && \Illuminate\Support\Facades\Schema::hasColumn('reverb_products', 'listing_state')) {
            $query->whereIn('listing_state', ['live', 'active']);
            $this->info('Processing only active listings (use --all to sync all).');
        }
        $products = $query->get();

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
            $listingId = $product->reverb_listing_id ? trim((string) $product->reverb_listing_id) : null;
            $sku = $product->sku;
            $qty = (int) ($shopifyQuantities[$sku] ?? 0);
            $oldReverbQty = (int) ($product->remaining_inventory ?? 0);

            if (! $listingId) {
                $this->warn("  Skipping {$sku}: no reverb_listing_id.");
                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] {$sku} (listing {$listingId}) => inventory {$qty}");
                $updated++;
                continue;
            }

            if ($reverb->updateListingInventory($listingId, $qty)) {
                $updated++;
                if (class_exists(\App\Models\ReverbSyncLog::class)) {
                    $syncLog->logShopifyToReverb($sku, $oldReverbQty, $qty, $listingId, null, 'Inventory synced to Reverb');
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('reverb_products', 'last_synced_at')) {
                    $product->update([
                        'last_synced_at' => now(),
                        'last_shopify_qty' => $qty,
                    ]);
                }
                $this->info("  Updated {$sku} => {$qty}");
            } else {
                $failed++;
            }
            usleep(400000); // ~2.5 requests/sec to avoid rate limit
        }

        if (! $dryRun && class_exists(ReverbSyncState::class)) {
            ReverbSyncState::setLastSync(ReverbSyncState::KEY_INVENTORY_LAST_SYNC);
        }
        $this->info("Done. Updated: {$updated}, Failed: {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
