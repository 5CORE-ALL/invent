<?php

namespace App\Console\Commands;

use App\Models\GoogleCompetitorItem;
use App\Services\GoogleLivePriceFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateGoogleCompetitorPrices extends Command
{
    protected $signature = 'google:update-prices {--search-query= : Update specific search query only} {--dry-run : Run without updating database}';

    protected $description = 'Refresh Google Shopping search results into google_competitor_items';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $fetcher = app(GoogleLivePriceFetcher::class);

        $searchQuery = $this->option('search-query');
        if ($searchQuery) {
            $queries = [trim($searchQuery)];
        } else {
            $queries = GoogleCompetitorItem::select('search_query')
                ->distinct()
                ->orderBy('search_query')
                ->pluck('search_query')
                ->filter()
                ->values()
                ->all();

            if (empty($queries)) {
                $queries = \App\Models\GoogleSkuCompetitor::select('search_query')
                    ->whereNotNull('search_query')
                    ->distinct()
                    ->pluck('search_query')
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        if (empty($queries)) {
            $this->warn('No Google search queries found.');

            return 0;
        }

        $updated = 0;
        $created = 0;

        foreach ($queries as $index => $query) {
            $this->info('[' . ($index + 1) . '/' . count($queries) . "] Searching: {$query}");
            $results = $fetcher->searchShopping($query, 0, [
                'max_pages' => 2,
                'expand_sellers' => true,
                'expand_multiple_only' => true,
                'max_immersive_products' => 12,
                'max_store_pages' => 1,
            ]);

            foreach ($results as $item) {
                $existing = GoogleCompetitorItem::where('search_query', $query)
                    ->where('product_id', $item['product_id'])
                    ->where('source', $item['source'])
                    ->first();

                $payload = [
                    'marketplace' => 'google',
                    'search_query' => $query,
                    'product_id' => $item['product_id'],
                    'source' => $item['source'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'link' => $item['link'],
                    'image' => $item['image'],
                    'rating' => $item['rating'],
                    'reviews' => $item['reviews'],
                    'position' => $item['position'] ?? null,
                ];

                if (!$isDryRun) {
                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        GoogleCompetitorItem::create($payload);
                        $created++;
                    }
                }
            }

            usleep(500000);
        }

        $this->info("Done. Updated: {$updated}, Created: {$created}");

        return 0;
    }
}
