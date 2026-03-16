<?php

namespace App\Console\Commands;

use App\Models\AmazonListingRaw;
use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExtractAmazonItemNamesToTitleMaster extends Command
{
    protected $signature = 'amazon:extract-item-names-to-title-master
                            {--dry-run : Do not update database, only report what would be done}';

    protected $description = 'Extract item_name from amazon_listings_raw and update product_master.title150 for matching SKUs';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run mode – no database updates will be made.');
        }

        $listings = AmazonListingRaw::all();
        $count = 0;
        $skipped = 0;
        $skippedSkus = [];

        foreach ($listings as $listing) {
            if (empty($listing->seller_sku)) {
                $skipped++;
                continue;
            }

            $itemName = null;
            $rawData = $listing->raw_data ? (is_string($listing->raw_data) ? json_decode($listing->raw_data, true) : $listing->raw_data) : null;
            if ($rawData && is_array($rawData)) {
                $possibleKeys = ['item-name', 'item_name', 'product-title', 'title', 'Item Name', 'itemName'];
                foreach ($possibleKeys as $key) {
                    if (! empty($rawData[$key]) && is_string($rawData[$key])) {
                        $itemName = trim($rawData[$key]);
                        break;
                    }
                }
            }
            if (empty($itemName) && ! empty($listing->item_name)) {
                $itemName = trim($listing->item_name);
            }

            if (empty($itemName)) {
                $skipped++;
                $skippedSkus[] = $listing->seller_sku;
                continue;
            }

            $title150 = mb_substr(trim($itemName), 0, 150);

            $product = ProductMaster::where('sku', $listing->seller_sku)->first();
            if (! $product) {
                $skipped++;
                $skippedSkus[] = $listing->seller_sku;
                Log::channel('single')->info('Extract titles: SKU not found in product_master', ['sku' => $listing->seller_sku]);
                continue;
            }

            if (! $dryRun) {
                $product->title150 = $title150;
                $product->save();
            }
            $count++;
        }

        $this->info('Extraction complete.');
        $this->info("Updated (or would update): {$count} title(s).");
        $this->info("Skipped: {$skipped} (no product_master match or missing item_name).");
        if (! empty($skippedSkus)) {
            $sample = array_slice($skippedSkus, 0, 20);
            $this->line('Sample skipped SKUs: ' . implode(', ', $sample));
        }

        return Command::SUCCESS;
    }
}
