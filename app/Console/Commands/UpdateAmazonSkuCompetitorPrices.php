<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonCompetitorAsin;
use App\Services\AmazonLivePriceFetcher;
use Illuminate\Support\Facades\Log;

class UpdateAmazonSkuCompetitorPrices extends Command
{
    protected $signature = 'amazon:update-sku-prices
                            {--sku= : Update specific SKU only}
                            {--dry-run : Run without updating database}
                            {--skip-search-refresh : Skip SerpApi search refresh before syncing}
                            {--skip-live-fetch : Skip direct live price fetch from each ASIN}';

    protected $description = 'Refresh Amazon LMP competitor prices and images into amazon_sku_competitors';

    public function handle()
    {
        $startTime = now();
        $this->info('Starting Amazon SKU Competitor Price Update...');
        $this->info('Started at: ' . $startTime->format('Y-m-d H:i:s'));

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No database changes will be made');
        }

        $specificSku = $this->option('sku');

        $query = AmazonSkuCompetitor::query();

        if ($specificSku) {
            $query->where('sku', $specificSku);
            $this->info("Updating specific SKU: {$specificSku}");
        }

        $skuCompetitors = $query->get();

        if (!$this->option('skip-live-fetch')) {
            $this->refreshLiveAsinPrices($skuCompetitors, $isDryRun);
        } else {
            $this->warn('Skipping direct live ASIN fetch (--skip-live-fetch).');
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
                $fetcher = app(AmazonLivePriceFetcher::class);
                $asin = $fetcher->resolveAsin($skuCompetitor->product_link, $skuCompetitor->asin);

                $latestItem = AmazonCompetitorAsin::where('marketplace', $skuCompetitor->marketplace)
                    ->where(function ($query) use ($skuCompetitor, $asin) {
                        $query->where('asin', $skuCompetitor->asin);
                        if ($asin) {
                            $query->orWhere('asin', $asin);
                        }
                    })
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($latestItem) {
                    $newPrice = floatval($latestItem->price ?? 0);
                    $oldPrice = floatval($skuCompetitor->price ?? 0);
                    $newImage = $latestItem->image ?? null;
                    $priceChanged = $newPrice != $oldPrice;
                    $imageNeedsUpdate = !empty($newImage) && empty($skuCompetitor->image);

                    if ($priceChanged || $imageNeedsUpdate) {
                        if (!$isDryRun) {
                            $skuCompetitor->update([
                                'price' => $newPrice,
                                'product_title' => $latestItem->title,
                                'product_link' => "https://www.amazon.com/dp/{$latestItem->asin}",
                                'image' => $newImage ?: $skuCompetitor->image,
                                'rating' => $latestItem->rating ?? $skuCompetitor->rating,
                                'reviews' => $latestItem->reviews ?? $skuCompetitor->reviews,
                                'extracted_old_price' => $latestItem->extracted_old_price ?? $skuCompetitor->extracted_old_price,
                                'delivery' => $latestItem->delivery ?? $skuCompetitor->delivery,
                                'seller_name' => $latestItem->seller_name ?? $skuCompetitor->seller_name,
                            ]);
                        }

                        if ($priceChanged) {
                            $this->newLine();
                            $this->info("  Updated SKU: {$skuCompetitor->sku}, ASIN: {$skuCompetitor->asin}");
                            $this->info("    Price: \${$oldPrice} → \${$newPrice}");
                        }

                        $totalUpdated++;
                    } else {
                        $totalUnchanged++;
                    }
                } else {
                    $totalNotFound++;
                }
            } catch (\Exception $e) {
                $this->error("  Failed to update SKU {$skuCompetitor->sku}: " . $e->getMessage());
                Log::error('Amazon SKU Price Update Error', [
                    'sku' => $skuCompetitor->sku,
                    'asin' => $skuCompetitor->asin,
                    'error' => $e->getMessage(),
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

        Log::info('Amazon SKU Competitor Price Update Completed', [
            'total_checked' => $skuCompetitors->count(),
            'updated' => $totalUpdated,
            'unchanged' => $totalUnchanged,
            'not_found' => $totalNotFound,
            'duration_seconds' => $duration,
            'dry_run' => $isDryRun,
        ]);

        return 0;
    }

    protected function refreshLiveAsinPrices($skuCompetitors, bool $isDryRun): void
    {
        $fetcher = app(AmazonLivePriceFetcher::class);
        $asinMap = [];

        foreach ($skuCompetitors as $competitor) {
            $asin = $fetcher->resolveAsin($competitor->product_link, $competitor->asin);
            if (!$asin) {
                continue;
            }

            $asinMap[$asin][] = [
                'id' => $competitor->id,
                'marketplace' => $competitor->marketplace,
            ];
        }

        if (empty($asinMap)) {
            $this->warn('No ASINs found in LMP competitor records.');
            return;
        }

        $this->info('Fetching live Amazon prices for ' . count($asinMap) . ' unique ASINs...');
        $liveUpdated = 0;
        $liveFailed = 0;
        $imagesUpdated = 0;
        $index = 0;

        foreach ($asinMap as $asin => $competitorRefs) {
            $index++;
            $marketplace = $competitorRefs[0]['marketplace'] ?? 'amazon';
            $live = $fetcher->fetchByAsin($asin, $marketplace);

            if (!$live) {
                $liveFailed++;
                $this->line("  [{$index}/" . count($asinMap) . "] ASIN {$asin}: no live data");
                usleep(500000);
                continue;
            }

            $competitorIds = collect($competitorRefs)->pluck('id');
            $competitors = AmazonSkuCompetitor::whereIn('id', $competitorIds)->get();

            foreach ($competitors as $competitor) {
                $oldPrice = floatval($competitor->price ?? 0);
                $newImage = $live['image'] ?? null;
                $imageChanged = !empty($newImage) && empty($competitor->image);
                $priceChanged = $oldPrice != $live['price'];

                if (!$isDryRun) {
                    $competitor->update([
                        'asin' => $asin,
                        'price' => $live['price'],
                        'product_title' => $live['title'] ?? $competitor->product_title,
                        'product_link' => $live['link'] ?? $competitor->product_link,
                        'image' => $newImage ?? $competitor->image,
                        'rating' => $live['rating'] ?? $competitor->rating,
                        'reviews' => $live['reviews'] ?? $competitor->reviews,
                        'extracted_old_price' => $live['extracted_old_price'] ?? $competitor->extracted_old_price,
                        'delivery' => $live['delivery'] ?? $competitor->delivery,
                        'seller_name' => $live['seller_name'] ?? $competitor->seller_name,
                    ]);

                    AmazonCompetitorAsin::where('asin', $asin)->update([
                        'price' => $live['price'],
                        'title' => $live['title'],
                        'image' => $newImage,
                        'rating' => $live['rating'],
                        'reviews' => $live['reviews'],
                        'extracted_old_price' => $live['extracted_old_price'],
                        'delivery' => $live['delivery'],
                        'seller_name' => $live['seller_name'],
                    ]);
                }

                if ($priceChanged) {
                    $this->line("  [{$index}/" . count($asinMap) . "] ASIN {$asin}: \${$oldPrice} → \${$live['price']} (SKU: {$competitor->sku})");
                    $liveUpdated++;
                } elseif ($imageChanged) {
                    $this->line("  [{$index}/" . count($asinMap) . "] ASIN {$asin}: image saved (SKU: {$competitor->sku})");
                    $imagesUpdated++;
                }
            }

            usleep(500000);
        }

        $this->info("Live ASIN fetch complete. Updated: {$liveUpdated}, Images saved: {$imagesUpdated}, Failed: {$liveFailed}");
        $this->newLine();
    }

    protected function refreshLinkedSearchQueries($skuCompetitors, bool $isDryRun): void
    {
        $linkedAsins = $skuCompetitors->pluck('asin')->filter()->unique()->values();

        if ($linkedAsins->isEmpty()) {
            $this->warn('No LMP ASINs found to refresh.');
            return;
        }

        $searchQueries = AmazonCompetitorAsin::whereIn('asin', $linkedAsins)
            ->select('search_query')
            ->distinct()
            ->orderBy('search_query')
            ->pluck('search_query')
            ->filter()
            ->values();

        if ($searchQueries->isEmpty()) {
            $this->warn('No linked search queries found in amazon_competitor_asins for LMP ASINs.');
            return;
        }

        $this->info('Refreshing ' . $searchQueries->count() . ' SerpApi search queries linked to LMP competitors...');

        foreach ($searchQueries as $index => $searchQuery) {
            $this->newLine();
            $this->info('[' . ($index + 1) . '/' . $searchQueries->count() . "] amazon:update-prices --search-query=\"{$searchQuery}\"");

            $args = ['--search-query' => $searchQuery];
            if ($isDryRun) {
                $args['--dry-run'] = true;
            }

            $this->call('amazon:update-prices', $args);
        }

        $this->newLine();
        $this->info('SerpApi search refresh complete. Syncing LMP prices into amazon_sku_competitors...');
    }
}
