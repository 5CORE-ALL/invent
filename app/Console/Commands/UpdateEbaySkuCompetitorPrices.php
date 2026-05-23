<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EbaySkuCompetitor;
use App\Models\EbayCompetitorItem;
use App\Services\EbayLivePriceFetcher;
use Illuminate\Support\Facades\Log;

class UpdateEbaySkuCompetitorPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:update-sku-prices
                            {--sku= : Update specific SKU only}
                            {--dry-run : Run without updating database}
                            {--skip-search-refresh : Skip SerpApi search refresh before syncing}
                            {--skip-live-fetch : Skip direct live price fetch from each listing URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh eBay LMP competitor search results and sync prices into ebay_sku_competitors (used by ebay-tabulator-view)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = now();
        $this->info('Starting eBay SKU Competitor Price Update...');
        $this->info('Started at: ' . $startTime->format('Y-m-d H:i:s'));
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No database changes will be made');
        }

        $specificSku = $this->option('sku');

        $query = EbaySkuCompetitor::query();

        if ($specificSku) {
            $query->where('sku', $specificSku);
            $this->info("Updating specific SKU: {$specificSku}");
        }

        $skuCompetitors = $query->get();

        if (!$this->option('skip-live-fetch')) {
            $this->refreshLiveListingPrices($skuCompetitors, $isDryRun);
        } else {
            $this->warn('Skipping direct live listing fetch (--skip-live-fetch).');
        }

        if (!$this->option('skip-search-refresh')) {
            $this->refreshLinkedSearchQueries($skuCompetitors, $isDryRun);
        } else {
            $this->warn('Skipping SerpApi search refresh (--skip-search-refresh).');
        }

        $this->info('Found ' . $skuCompetitors->count() . ' SKU competitor mappings to check');
        
        $totalUpdated = 0;
        $totalUnchanged = 0;
        $totalNotFound = 0;

        $progressBar = $this->output->createProgressBar($skuCompetitors->count());
        $progressBar->start();

        foreach ($skuCompetitors as $skuCompetitor) {
            try {
                $fetcher = app(EbayLivePriceFetcher::class);
                $listingId = $fetcher->resolveListingId($skuCompetitor->product_link, $skuCompetitor->item_id);

                // Find the latest price from competitor items table
                $latestItem = EbayCompetitorItem::where('marketplace', $skuCompetitor->marketplace)
                    ->where(function ($query) use ($skuCompetitor, $listingId) {
                        $query->where('item_id', $skuCompetitor->item_id);
                        if ($listingId) {
                            $query->orWhere('item_id', $listingId)
                                ->orWhere('link', 'like', '%/itm/' . $listingId . '%');
                        }
                    })
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($latestItem) {
                    // Check if price has changed
                    $newPrice = floatval($latestItem->price ?? 0);
                    $newShipping = floatval($latestItem->shipping_cost ?? 0);
                    $newTotalPrice = $newPrice + $newShipping;
                    
                    $oldPrice = floatval($skuCompetitor->price ?? 0);
                    $oldShipping = floatval($skuCompetitor->shipping_cost ?? 0);
                    $oldTotalPrice = floatval($skuCompetitor->total_price ?? 0);

                    if ($newPrice != $oldPrice || $newShipping != $oldShipping) {
                        if (!$isDryRun) {
                            $skuCompetitor->update([
                                'price' => $newPrice,
                                'shipping_cost' => $newShipping,
                                'total_price' => $newTotalPrice,
                                'product_title' => $latestItem->title,
                                'product_link' => $latestItem->link,
                                'image' => $latestItem->image,
                            ]);
                        }
                        
                        $this->newLine();
                        $this->info("  Updated SKU: {$skuCompetitor->sku}, Item: {$skuCompetitor->item_id}");
                        $this->info("    Price: \${$oldPrice} → \${$newPrice}");
                        $this->info("    Shipping: \${$oldShipping} → \${$newShipping}");
                        
                        $totalUpdated++;
                    } else {
                        $totalUnchanged++;
                    }
                } else {
                    $totalNotFound++;
                }

            } catch (\Exception $e) {
                $this->error("  Failed to update SKU {$skuCompetitor->sku}: " . $e->getMessage());
                Log::error('eBay SKU Price Update Error', [
                    'sku' => $skuCompetitor->sku,
                    'item_id' => $skuCompetitor->item_id,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $endTime = now();
        $duration = $endTime->diffInSeconds($startTime);
        
        $this->info('=== Update Complete ===');
        $this->info('Total Checked: ' . $skuCompetitors->count());
        $this->info('Updated: ' . $totalUpdated);
        $this->info('Unchanged: ' . $totalUnchanged);
        $this->info('Not Found in Items: ' . $totalNotFound);
        $this->info('Duration: ' . gmdate('H:i:s', $duration));
        $this->info('Completed at: ' . $endTime->format('Y-m-d H:i:s'));
        
        Log::info('eBay SKU Competitor Price Update Completed', [
            'total_checked' => $skuCompetitors->count(),
            'updated' => $totalUpdated,
            'unchanged' => $totalUnchanged,
            'not_found' => $totalNotFound,
            'duration_seconds' => $duration,
            'dry_run' => $isDryRun
        ]);

        return 0;
    }

    /**
     * Fetch the current eBay listing price for each LMP competitor using the
     * listing ID from product_link (/itm/{id}), not the stored epid item_id.
     */
    protected function refreshLiveListingPrices($skuCompetitors, bool $isDryRun): void
    {
        $fetcher = app(EbayLivePriceFetcher::class);
        $listingMap = [];

        foreach ($skuCompetitors as $competitor) {
            $listingId = $fetcher->resolveListingId($competitor->product_link, $competitor->item_id);
            if (!$listingId) {
                continue;
            }

            $listingMap[$listingId][] = $competitor->id;
        }

        if (empty($listingMap)) {
            $this->warn('No listing IDs found in LMP product links.');
            return;
        }

        $this->info('Fetching live eBay prices for ' . count($listingMap) . ' unique listings...');
        $liveUpdated = 0;
        $liveFailed = 0;
        $index = 0;

        foreach ($listingMap as $listingId => $competitorIds) {
            $index++;
            $live = $fetcher->fetchByListingId($listingId);

            if (!$live) {
                $liveFailed++;
                $this->line("  [{$index}/" . count($listingMap) . "] Listing {$listingId}: no live data");
                usleep(500000);
                continue;
            }

            $competitors = EbaySkuCompetitor::whereIn('id', $competitorIds)->get();
            foreach ($competitors as $competitor) {
                $oldPrice = floatval($competitor->price ?? 0);
                $oldShipping = floatval($competitor->shipping_cost ?? 0);
                $originalItemId = $competitor->item_id;

                if (!$isDryRun) {
                    $competitor->update([
                        'item_id' => $listingId,
                        'price' => $live['price'],
                        'shipping_cost' => $live['shipping_cost'],
                        'total_price' => $live['total_price'],
                        'product_title' => $live['title'] ?? $competitor->product_title,
                        'product_link' => $live['link'] ?? $competitor->product_link,
                        'image' => $live['image'] ?? $competitor->image,
                    ]);

                    EbayCompetitorItem::where(function ($query) use ($originalItemId, $listingId) {
                        $query->where('item_id', $originalItemId)
                            ->orWhere('link', 'like', '%/itm/' . $listingId . '%');
                    })->update([
                        'item_id' => $listingId,
                        'price' => $live['price'],
                        'shipping_cost' => $live['shipping_cost'],
                        'link' => $live['link'],
                        'title' => $live['title'],
                        'image' => $live['image'],
                    ]);
                }

                if ($oldPrice != $live['price'] || $oldShipping != $live['shipping_cost']) {
                    $this->line("  [{$index}/" . count($listingMap) . "] Listing {$listingId}: \${$oldPrice} → \${$live['price']} (SKU: {$competitor->sku})");
                    $liveUpdated++;
                }
            }

            usleep(500000);
        }

        $this->info("Live listing fetch complete. Updated: {$liveUpdated}, Failed: {$liveFailed}");
        $this->newLine();
    }

    /**
     * Refresh SerpApi search results for queries tied to LMP item IDs, so
     * ebay_competitor_items has current prices before syncing to ebay_sku_competitors.
     */
    protected function refreshLinkedSearchQueries($skuCompetitors, bool $isDryRun): void
    {
        $linkedItemIds = $skuCompetitors->pluck('item_id')->filter()->unique()->values();

        if ($linkedItemIds->isEmpty()) {
            $this->warn('No LMP item IDs found to refresh.');
            return;
        }

        $searchQueries = EbayCompetitorItem::whereIn('item_id', $linkedItemIds)
            ->select('search_query')
            ->distinct()
            ->orderBy('search_query')
            ->pluck('search_query')
            ->filter()
            ->values();

        if ($searchQueries->isEmpty()) {
            $this->warn('No linked search queries found in ebay_competitor_items for LMP item IDs.');
            return;
        }

        $this->info('Refreshing ' . $searchQueries->count() . ' SerpApi search queries linked to LMP competitors...');

        foreach ($searchQueries as $index => $searchQuery) {
            $this->newLine();
            $this->info('[' . ($index + 1) . '/' . $searchQueries->count() . "] ebay:update-prices --search-query=\"{$searchQuery}\"");

            $args = ['--search-query' => $searchQuery];
            if ($isDryRun) {
                $args['--dry-run'] = true;
            }

            $this->call('ebay:update-prices', $args);
        }

        $this->newLine();
        $this->info('SerpApi search refresh complete. Syncing LMP prices into ebay_sku_competitors...');
    }
}
