<?php

namespace App\Console\Commands;

use App\Models\AmazonListingRaw;
use App\Services\AmazonSpApiService;
use Illuminate\Console\Command;

class AmazonDebugSku extends Command
{
    protected $signature = 'amazon:debug-sku {--sku= : SKU to debug (e.g. "3501 USB")}';

    protected $description = 'Debug Amazon enrichment for single SKU: fetch Catalog + Listings API, show raw responses and merged data';

    public function handle(): int
    {
        $sku = $this->option('sku');
        if (empty($sku) && $this->input->isInteractive()) {
            $sku = $this->ask('Enter SKU (e.g. 3501 USB)');
        }
        $sku = trim((string) $sku);
        if (empty($sku)) {
            $this->error('SKU is required. Use: php artisan amazon:debug-sku --sku="3501 USB"');
            return 1;
        }

        $listing = AmazonListingRaw::where('seller_sku', $sku)->first()
            ?? AmazonListingRaw::where('seller_sku', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $sku) . '%')->first();

        if (! $listing) {
            $this->error("SKU '{$sku}' not found in amazon_listings_raw. Run report import first.");
            return 1;
        }

        $asin = $listing->asin1;
        if (empty($asin)) {
            $this->error("ASIN empty for SKU '{$listing->seller_sku}'.");
            return 1;
        }

        $service = new AmazonSpApiService();
        $this->info("=== Debug SKU: {$listing->seller_sku} | ASIN: {$asin} ===");

        // 1. Catalog API
        $this->newLine();
        $this->info('--- 1. CATALOG API (getCatalogItemByAsin) ---');
        $context = [];
        sleep(1);
        $catalogData = $service->getCatalogItemByAsin($asin, $context);

        if ($catalogData === null) {
            $this->warn('Catalog API: returned NULL (404/error)');
        } else {
            $this->info('Catalog API: SUCCESS');
            $this->line('Keys: ' . implode(', ', array_keys($catalogData)));
            if (! empty($catalogData['summaries'])) {
                $sum = $catalogData['summaries'][0] ?? [];
                $this->line('summaries[0] keys: ' . implode(', ', array_keys($sum)));
                $this->table(
                    ['Field', 'Value'],
                    collect($sum)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->take(20)->values()->toArray()
                );
            }
            if (! empty($catalogData['attributes'])) {
                $this->line('attributes keys: ' . implode(', ', array_keys($catalogData['attributes'])));
            }
            $catalogAttrs = $service->extractCatalogAttributes($catalogData);
            $this->info('Extracted Catalog fields: ' . count($catalogAttrs));
            $this->table(['Field', 'Value'], collect($catalogAttrs)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->values()->toArray());
        }

        // 2. Listings Items API
        $this->newLine();
        $this->info('--- 2. LISTINGS ITEMS API (getListingsItemFullDetails) ---');
        usleep(200000);
        $listingsData = $service->getListingsItemFullDetails($listing->seller_sku);
        $this->info('Listings API response keys: ' . implode(', ', array_keys($listingsData)));
        $listingsNonEmpty = array_filter($listingsData, fn ($v) => $v !== null && $v !== '' && (! is_array($v) || ! empty($v)));
        $this->table(['Field', 'Value'], collect($listingsNonEmpty)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->values()->toArray());

        // 3. Merged data (what enrichListingData would produce)
        $this->newLine();
        $this->info('--- 3. MERGED DATA (what would be saved) ---');
        $mergeContext = [];
        $updates = $service->enrichListingData($asin, $listing->seller_sku, $mergeContext);

        $fillable = (new AmazonListingRaw)->getFillable();
        $filtered = array_intersect_key($updates, array_flip($fillable));

        $this->info('Total merged fields: ' . count($updates));
        $this->info('Fillable fields (would save): ' . count($filtered));

        $rows = collect($filtered)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'true' : 'false') : $v)])->values()->toArray();
        if (empty($rows)) {
            $this->warn('No fields would be saved!');
        } else {
            $this->table(['Field', 'Value'], $rows);
        }

        if (! empty($mergeContext['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($mergeContext['warnings'] as $w) {
                $this->warn('  - ' . $w);
            }
        }

        $required = ['brand', 'color', 'material', 'item_name', 'model_number', 'manufacturer', 'item_dimensions', 'external_product_id'];
        $missing = array_diff($required, array_keys($filtered));
        if (! empty($missing)) {
            $this->newLine();
            $this->warn('MISSING required fields: ' . implode(', ', $missing));
        }

        $this->newLine();
        $this->info('Done. Run: php artisan amazon:sync-products --sku="' . $listing->seller_sku . '" --skip-report --enrich');

        return 0;
    }
}
