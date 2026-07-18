<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\ShopifySku;
use App\Models\ShopifySkuInventoryHistory;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InventorySnapshot extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'inventory:snapshot
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Create daily inventory snapshot for all SKUs and calculate sold/restocked quantities';

    protected string $monitorJobName = 'Inventory Snapshot';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSnapshot($m),
            $this->monitorJobName
        );
    }

    protected function executeSnapshot(CronExecutionContext $monitor): int
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
            $query = ShopifySku::whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id');

            $totalSkus = (clone $query)->count();
            $createdCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            $this->info("Found {$totalSkus} SKUs to process");
            $monitor->setExpected($totalSkus);
            $monitor->setFetched($totalSkus);

            $this->processQueryInChunks(
                $monitor,
                $query,
                function ($rows) use (
                    $snapshotDate,
                    $pstTimezone,
                    $runId,
                    &$createdCount,
                    &$skippedCount,
                    &$errorCount
                ) {
                    $chunkCreated = 0;
                    $chunkSkipped = 0;
                    $chunkFailed = 0;

                    foreach ($rows as $shopifySku) {
                        try {
                            $sku = trim($shopifySku->sku);
                            $currentInventory = (int) ($shopifySku->inv ?? 0);
                            $productName = $shopifySku->product_title ?? null;
                            $skuId = $shopifySku->id;

                            $existingRecord = ShopifySkuInventoryHistory::where('sku', $sku)
                                ->where('snapshot_date', $snapshotDate)
                                ->first();

                            if ($existingRecord) {
                                $chunkSkipped++;
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

                            $chunkCreated++;
                        } catch (\Exception $e) {
                            $chunkFailed++;
                            Log::error('inventory_snapshot_sku_error', [
                                'run_id' => $runId,
                                'sku' => $sku ?? 'unknown',
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $createdCount += $chunkCreated;
                    $skippedCount += $chunkSkipped;
                    $errorCount += $chunkFailed;

                    return [
                        'updated' => $chunkCreated,
                        'skipped' => $chunkSkipped,
                        'failed' => $chunkFailed,
                        'processed' => $rows->count(),
                    ];
                }
            );

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

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Inventory snapshot failed: " . $e->getMessage());

            Log::channel('daily')->error('inventory_snapshot_failed', [
                'run_id' => $runId,
                'snapshot_date' => $snapshotDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $monitor->classifyAndRecord($e);

            return self::FAILURE;
        }
    }
}
