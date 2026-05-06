<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifySku;
use App\Models\ShopifySkuInventoryHistory;
use Carbon\Carbon;

class InventorySnapshot extends Command
{
    protected $signature = 'inventory:snapshot';

    protected $description = 'Create daily inventory snapshot for all SKUs and calculate sold/restocked quantities';

    public function handle()
    {
        set_time_limit(0);

        $runId = uniqid('snapshot_', true);
        $startTime = microtime(true);

        $pstTimezone = 'America/Los_Angeles';
        $now = Carbon::now($pstTimezone);
        $snapshotDate = $now->toDateString();

        $this->info("Starting inventory snapshot for date: {$snapshotDate}");
        $this->info("Run ID: {$runId}");

        Log::channel('daily')->info('inventory_snapshot_started', [
            'run_id' => $runId,
            'snapshot_date' => $snapshotDate,
            'pst_datetime' => $now->toDateTimeString(),
        ]);

        try {
            $shopifySkus = ShopifySku::whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->get();

            $totalSkus = $shopifySkus->count();
            $processedCount = 0;
            $createdCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            $this->info("Found {$totalSkus} SKUs to process");

            $bar = $this->output->createProgressBar($totalSkus);
            $bar->start();

            foreach ($shopifySkus as $shopifySku) {
                try {
                    $sku = trim($shopifySku->sku);
                    $currentInventory = (int) ($shopifySku->inv ?? 0);
                    $productName = $shopifySku->product_title ?? null;
                    $skuId = $shopifySku->id;

                    $existingRecord = ShopifySkuInventoryHistory::where('sku', $sku)
                        ->where('snapshot_date', $snapshotDate)
                        ->first();

                    if ($existingRecord) {
                        $skippedCount++;
                        $bar->advance();
                        continue;
                    }

                    $previousRecord = ShopifySkuInventoryHistory::where('sku', $sku)
                        ->orderBy('snapshot_date', 'desc')
                        ->first();

                    $openingInventory = $previousRecord 
                        ? $previousRecord->closing_inventory 
                        : $currentInventory;

                    $closingInventory = $currentInventory;

                    $soldQuantity = 0;
                    $restockedQuantity = 0;

                    if ($openingInventory > $closingInventory) {
                        $soldQuantity = $openingInventory - $closingInventory;
                    } elseif ($closingInventory > $openingInventory) {
                        $restockedQuantity = $closingInventory - $openingInventory;
                    }

                    $pstStartOfDay = Carbon::parse($snapshotDate, $pstTimezone)->startOfDay();
                    $pstEndOfDay = Carbon::parse($snapshotDate, $pstTimezone)->endOfDay();

                    ShopifySkuInventoryHistory::create([
                        'sku_id' => $skuId,
                        'sku' => $sku,
                        'product_name' => $productName,
                        'opening_inventory' => $openingInventory,
                        'closing_inventory' => $closingInventory,
                        'sold_quantity' => $soldQuantity,
                        'restocked_quantity' => $restockedQuantity,
                        'snapshot_date' => $snapshotDate,
                        'pst_start_datetime' => $pstStartOfDay,
                        'pst_end_datetime' => $pstEndOfDay,
                    ]);

                    $createdCount++;
                    $processedCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('inventory_snapshot_sku_error', [
                        'run_id' => $runId,
                        'sku' => $sku ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("Inventory snapshot completed successfully!");
            $this->info("Total SKUs: {$totalSkus}");
            $this->info("Created: {$createdCount}");
            $this->info("Skipped (already exists): {$skippedCount}");
            $this->info("Errors: {$errorCount}");
            $this->info("Duration: {$duration} seconds");

            Log::channel('daily')->info('inventory_snapshot_completed', [
                'run_id' => $runId,
                'snapshot_date' => $snapshotDate,
                'total_skus' => $totalSkus,
                'created' => $createdCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'duration_seconds' => $duration,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Inventory snapshot failed: " . $e->getMessage());

            Log::channel('daily')->error('inventory_snapshot_failed', [
                'run_id' => $runId,
                'snapshot_date' => $snapshotDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
