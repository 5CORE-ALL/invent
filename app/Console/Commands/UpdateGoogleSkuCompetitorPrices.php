<?php

namespace App\Console\Commands;

use App\Models\GoogleCompetitorItem;
use App\Models\GoogleSkuCompetitor;
use App\Services\GoogleLivePriceFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateGoogleSkuCompetitorPrices extends Command
{
    protected $signature = 'google:update-sku-prices
                            {--sku= : Update specific SKU only}
                            {--dry-run : Run without updating database}
                            {--skip-search-refresh : Skip SerpApi search refresh before syncing}
                            {--skip-live-fetch : Skip direct live price fetch from Google Shopping}';

    protected $description = 'Refresh Google LMP competitor prices and images into google_sku_competitors';

    public function handle(): int
    {
        $startTime = now();
        $isDryRun = (bool) $this->option('dry-run');

        $this->info('Starting Google SKU Competitor Price Update...');

        $query = GoogleSkuCompetitor::query();
        if ($sku = $this->option('sku')) {
            $query->where('sku', $sku);
            $this->info("Updating specific SKU: {$sku}");
        }

        $skuCompetitors = $query->get();

        if (!$this->option('skip-live-fetch')) {
            $this->refreshLiveProductPrices($skuCompetitors, $isDryRun);
        }

        if (!$this->option('skip-search-refresh')) {
            $this->refreshLinkedSearchQueries($skuCompetitors, $isDryRun);
        }

        $totalUpdated = 0;
        $totalUnchanged = 0;
        $totalNotFound = 0;

        $bar = $this->output->createProgressBar($skuCompetitors->count());
        $bar->start();

        foreach ($skuCompetitors as $row) {
            try {
                $latest = GoogleCompetitorItem::where('product_id', $row->product_id)
                    ->when($row->source, fn ($q) => $q->where('source', $row->source))
                    ->orderByDesc('updated_at')
                    ->first();

                if (!$latest) {
                    $totalNotFound++;
                    $bar->advance();
                    continue;
                }

                $newPrice = (float) ($latest->price ?? 0);
                $oldPrice = (float) ($row->price ?? 0);
                $imageNeedsUpdate = !empty($latest->image) && empty($row->image);
                $priceChanged = $newPrice != $oldPrice;

                if ($priceChanged || $imageNeedsUpdate) {
                    if (!$isDryRun) {
                        $row->update([
                            'price' => $newPrice,
                            'product_title' => $latest->title,
                            'product_link' => $latest->link,
                            'image' => $latest->image ?: $row->image,
                            'rating' => $latest->rating,
                            'reviews' => $latest->reviews,
                        ]);
                    }
                    $totalUpdated++;
                } else {
                    $totalUnchanged++;
                }
            } catch (\Throwable $e) {
                Log::error('Google SKU price update failed', [
                    'sku' => $row->sku,
                    'product_id' => $row->product_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Total Checked: ' . $skuCompetitors->count());
        $this->info('Updated: ' . $totalUpdated);
        $this->info('Unchanged: ' . $totalUnchanged);
        $this->info('Not Found in Items: ' . $totalNotFound);
        $this->info('Duration: ' . gmdate('H:i:s', now()->diffInSeconds($startTime)));

        return 0;
    }

    protected function refreshLiveProductPrices($skuCompetitors, bool $isDryRun): void
    {
        $fetcher = app(GoogleLivePriceFetcher::class);
        $map = [];

        foreach ($skuCompetitors as $competitor) {
            $key = $competitor->product_id . '|' . ($competitor->source ?? '');
            $map[$key][] = $competitor->id;
        }

        if (empty($map)) {
            $this->warn('No Google product IDs found.');

            return;
        }

        $this->info('Fetching live Google Shopping prices for ' . count($map) . ' unique offers...');
        $liveUpdated = 0;
        $imagesUpdated = 0;
        $liveFailed = 0;
        $index = 0;

        foreach ($map as $key => $ids) {
            $index++;
            $sample = GoogleSkuCompetitor::find($ids[0]);
            if (!$sample) {
                continue;
            }

            $live = $fetcher->fetchByProductId(
                (string) $sample->product_id,
                $sample->source,
                $sample->search_query
            );

            if (!$live) {
                $liveFailed++;
                usleep(500000);
                continue;
            }

            $competitors = GoogleSkuCompetitor::whereIn('id', $ids)->get();
            foreach ($competitors as $competitor) {
                $oldPrice = (float) ($competitor->price ?? 0);
                $newImage = $live['image'] ?? null;
                $imageChanged = !empty($newImage) && empty($competitor->image);
                $priceChanged = $oldPrice != ($live['price'] ?? 0);

                if (!$isDryRun) {
                    $competitor->update([
                        'price' => $live['price'],
                        'product_title' => $live['title'] ?? $competitor->product_title,
                        'product_link' => $live['link'] ?? $competitor->product_link,
                        'image' => $newImage ?? $competitor->image,
                        'rating' => $live['rating'] ?? $competitor->rating,
                        'reviews' => $live['reviews'] ?? $competitor->reviews,
                    ]);

                    GoogleCompetitorItem::where('product_id', $competitor->product_id)
                        ->when($competitor->source, fn ($q) => $q->where('source', $competitor->source))
                        ->update([
                            'price' => $live['price'],
                            'title' => $live['title'],
                            'link' => $live['link'],
                            'image' => $newImage,
                            'rating' => $live['rating'],
                            'reviews' => $live['reviews'],
                        ]);
                }

                if ($priceChanged) {
                    $liveUpdated++;
                } elseif ($imageChanged) {
                    $imagesUpdated++;
                }
            }

            usleep(500000);
        }

        $this->info("Live fetch complete. Updated: {$liveUpdated}, Images saved: {$imagesUpdated}, Failed: {$liveFailed}");
    }

    protected function refreshLinkedSearchQueries($skuCompetitors, bool $isDryRun): void
    {
        $queries = $skuCompetitors->pluck('search_query')->filter()->unique()->values();
        if ($queries->isEmpty()) {
            $queries = GoogleCompetitorItem::select('search_query')->distinct()->pluck('search_query')->filter()->values();
        }

        if ($queries->isEmpty()) {
            $this->warn('No linked Google search queries found.');

            return;
        }

        foreach ($queries as $index => $searchQuery) {
            $this->info('[' . ($index + 1) . '/' . $queries->count() . "] google:update-prices --search-query=\"{$searchQuery}\"");
            $args = ['--search-query' => $searchQuery];
            if ($isDryRun) {
                $args['--dry-run'] = true;
            }
            $this->call('google:update-prices', $args);
        }
    }
}
