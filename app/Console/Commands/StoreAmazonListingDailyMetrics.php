<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingDailyMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StoreAmazonListingDailyMetrics extends Command
{
    protected $signature = 'amazon:store-listing-daily-metrics {--date= : Specific date to store (YYYY-MM-DD)}';
    protected $description = 'Store daily count of Missing & INV>0 metrics for Amazon listings';

    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        
        $this->info("Storing Amazon listing daily metrics for: " . $date->format('Y-m-d'));

        try {
            // Get all product masters (excluding deleted)
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->select('id', 'sku', 'parent', 'Values')
                ->get();
            
            $skus = $productMasters->pluck('sku')->unique()->toArray();

            // Load all data in one go with proper indexing
            $shopifyData = ShopifySku::whereIn('sku', $skus)
                ->select('sku', 'inv', 'quantity', 'image_src')
                ->get()
                ->keyBy('sku');
            
            $statusData = AmazonDataView::whereIn('sku', $skus)
                ->select('sku', 'value')
                ->get()
                ->keyBy('sku');
            
            $listingStatusData = AmazonDatasheet::whereIn('sku', $skus)
                ->select('sku', 'listing_status')
                ->get()
                ->keyBy('sku');
            
            // Load NR values from AmazonListingStatus for fallback
            $nrListingStatuses = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

            $missingInvCombinedCount = 0;

            foreach ($productMasters as $item) {
                $childSku = $item->sku;
                
                // Skip SKUs that start with "PARENT" (only count non-parent SKUs)
                if (str_starts_with(strtoupper(trim($childSku)), 'PARENT')) {
                    continue;
                }
                
                // Get INV
                $inv = isset($shopifyData[$childSku]) ? ($shopifyData[$childSku]->inv ?? 0) : 0;
                
                // Get listing status from datasheet
                $listingStatus = isset($listingStatusData[$childSku]) ? $listingStatusData[$childSku]->listing_status : null;
                
                // Get NR
                $nr = null;
                
                if (isset($statusData[$childSku])) {
                    $status = $statusData[$childSku]->value;
                    if (!is_array($status)) {
                        $status = json_decode($status, true) ?? [];
                    }
                    
                    // Read NRL field
                    $nrlValue = $status['NRL'] ?? null;
                    if ($nrlValue === 'NRL') {
                        $nr = 'NR';
                    } else {
                        $nr = 'REQ'; // Default to REQ
                    }
                }
                
                // Fallback to AmazonListingStatus if NR not set
                if ($nr === null) {
                    $listingStatusRecord = $nrListingStatuses->get($childSku);
                    if ($listingStatusRecord && $listingStatusRecord->value) {
                        $listingValue = is_array($listingStatusRecord->value) 
                            ? $listingStatusRecord->value 
                            : json_decode($listingStatusRecord->value, true) ?? [];
                        $nr = $listingValue['nr_req'] ?? 'REQ';
                    }
                }
                
                // If still null, default to REQ
                if ($nr === null) {
                    $nr = 'REQ';
                }
                
                // Count items with INV > 0 AND missing status (!listing_status && NR !== 'NR')
                // This matches the exact logic used in the view
                if (parseFloat($inv) > 0 && !$listingStatus && $nr !== 'NR') {
                    $missingInvCombinedCount++;
                }
            }

            // Store or update the metric for this date
            AmazonListingDailyMetric::updateOrCreate(
                ['date' => $date->format('Y-m-d')],
                ['missing_status_inv_count' => $missingInvCombinedCount]
            );

            $this->info("Successfully stored Missing & INV>0 count: {$missingInvCombinedCount} for date: " . $date->format('Y-m-d'));
            Log::info("Amazon Listing Daily Metrics stored", [
                'date' => $date->format('Y-m-d'),
                'missing_status_inv_count' => $missingInvCombinedCount
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error("Error storing Amazon listing daily metrics: " . $e->getMessage());
            Log::error("Error storing Amazon listing daily metrics", [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}