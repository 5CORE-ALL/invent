<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EbaySkuCompetitor;
use App\Models\EbayCompetitorItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateEbaySkuCompetitorPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:update-sku-prices {--sku= : Update specific SKU only} {--dry-run : Run without updating database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update eBay SKU competitor prices from the latest competitor items data';

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

        // Get SKU competitors to update
        $specificSku = $this->option('sku');
        
        $query = EbaySkuCompetitor::query();
        
        if ($specificSku) {
            $query->where('sku', $specificSku);
            $this->info("Updating specific SKU: {$specificSku}");
        }
        
        $skuCompetitors = $query->get();
        
        $this->info('Found ' . $skuCompetitors->count() . ' SKU competitor mappings to check');
        
        $totalUpdated = 0;
        $totalUnchanged = 0;
        $totalNotFound = 0;

        $progressBar = $this->output->createProgressBar($skuCompetitors->count());
        $progressBar->start();

        foreach ($skuCompetitors as $skuCompetitor) {
            try {
                // Find the latest price from competitor items table
                $latestItem = EbayCompetitorItem::where('item_id', $skuCompetitor->item_id)
                    ->where('marketplace', $skuCompetitor->marketplace)
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
}
