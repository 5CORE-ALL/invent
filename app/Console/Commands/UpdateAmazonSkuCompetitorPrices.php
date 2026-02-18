<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonCompetitorAsin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateAmazonSkuCompetitorPrices extends Command
{
    protected $signature = 'amazon:update-sku-prices {--sku= : Update specific SKU only} {--dry-run : Run without updating database}';
    protected $description = 'Update Amazon SKU competitor prices from the latest competitor items data';

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
        
        $this->info('Found ' . $skuCompetitors->count() . ' SKU competitor mappings to check');
        
        $totalUpdated = 0;
        $totalUnchanged = 0;
        $totalNotFound = 0;

        $progressBar = $this->output->createProgressBar($skuCompetitors->count());
        $progressBar->start();

        foreach ($skuCompetitors as $skuCompetitor) {
            try {
                $latestItem = AmazonCompetitorAsin::where('asin', $skuCompetitor->asin)
                    ->where('marketplace', $skuCompetitor->marketplace)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($latestItem) {
                    $newPrice = floatval($latestItem->price ?? 0);
                    $oldPrice = floatval($skuCompetitor->price ?? 0);

                    if ($newPrice != $oldPrice) {
                        if (!$isDryRun) {
                            $skuCompetitor->update([
                                'price' => $newPrice,
                                'product_title' => $latestItem->title,
                                'product_link' => "https://www.amazon.com/dp/{$latestItem->asin}",
                                'image' => $latestItem->image,
                            ]);
                        }
                        
                        $this->newLine();
                        $this->info("  Updated SKU: {$skuCompetitor->sku}, ASIN: {$skuCompetitor->asin}");
                        $this->info("    Price: \${$oldPrice} → \${$newPrice}");
                        
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
        
        Log::info('Amazon SKU Competitor Price Update Completed', [
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
