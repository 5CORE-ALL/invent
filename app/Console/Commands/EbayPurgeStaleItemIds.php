<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\EbayStaleItemIdPurger;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Console\Command;

/**
 * Clear eBay item ids that eBay no longer has on that SKU (GetItem), so the SKU shows in Missing L and lists
 * as a new product. See EbayStaleItemIdPurger.
 */
class EbayPurgeStaleItemIds extends Command
{
    protected $signature = 'ebay:purge-stale-item-ids
        {--store=2 : eBay store 1, 2, 3 or all}
        {--sku=* : Only these SKUs}
        {--zero-only : Only check rows whose eBay quantity is 0 (default: every live item id)}
        {--limit=3000 : Maximum rows to check per store (an item id shared by many SKUs is asked once)}
        {--dry-run : Report what would be cleared without changing anything}
        {--quiet-live : Do not print the rows that are still live}';

    protected $description = 'Clear eBay item ids that eBay no longer has on that SKU (GetItem), so the SKU shows in Missing L and lists as new';

    public function handle(EbayStaleItemIdPurger $purger): int
    {
        $storeOpt = strtolower(trim((string) $this->option('store')));
        $stores = $storeOpt === 'all' ? [1, 2, 3] : [(int) $storeOpt];
        $stores = array_values(array_filter($stores, static fn ($s) => in_array($s, [1, 2, 3], true)));
        if ($stores === []) {
            $this->error('Use --store=1, 2, 3 or all.');

            return 1;
        }

        $dry = (bool) $this->option('dry-run');
        $quietLive = (bool) $this->option('quiet-live');
        $failed = false;
        foreach ($stores as $store) {
            $this->info("eBay {$store}: verifying item ids on eBay".($dry ? ' [dry run]' : '').'...');
            $res = $purger->purge(
                $store,
                (array) $this->option('sku'),
                (bool) $this->option('zero-only'),
                max(1, (int) $this->option('limit')),
                $dry,
                function (string $line) use ($quietLive): void {
                    if ($quietLive && str_contains($line, 'still live')) {
                        return;
                    }
                    $this->line('  '.$line);
                }
            );
            $this->info("eBay {$store}: ".$res['message']);
            $failed = $failed || ! $res['ok'];

            if (! $dry && $res['cleared'] > 0) {
                try {
                    ListingChannelCounts::refreshChannelOnMissingListingPage([1 => 'ebay', 2 => 'ebaytwo', 3 => 'ebaythree'][$store]);
                } catch (\Throwable $e) {
                    $this->warn('Missing Listing refresh skipped: '.$e->getMessage());
                }
            }
        }

        return $failed ? 1 : 0;
    }
}
