<?php

namespace App\Jobs;

use App\Models\Inventory;
use App\Models\InventoryImportBatch;
use App\Models\InventoryImportError;
use App\Models\InventoryLog;
use App\Services\ShopifyInventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessInventoryCSVAndPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;
    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(ShopifyInventoryService $shopifyService)
    {
        $batch = InventoryImportBatch::find($this->batchId);

        if (!$batch) {
            Log::error("Batch not found: {$this->batchId}");
            return;
        }

        try {
            $batch->markAsProcessing();

            $filepath = storage_path('app/' . $batch->filepath);

            if (!file_exists($filepath)) {
                throw new Exception("File not found: {$filepath}");
            }

            // Read and process CSV in chunks
            $this->processCSVInChunks($filepath, $batch, $shopifyService);

            $batch->markAsCompleted();

            Log::info("CSV import completed", [
                'batch_id' => $batch->id,
                'total_rows' => $batch->total_rows,
                'successful_rows' => $batch->successful_rows,
                'failed_rows' => $batch->failed_rows,
            ]);

        } catch (Exception $e) {
            $batch->markAsFailed($e->getMessage());
            
            Log::error("CSV import failed", [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process CSV file in chunks
     */
    protected function processCSVInChunks($filepath, $batch, $shopifyService)
    {
        $chunkSize = 1000;
        $rowNumber = 0;
        $totalRows = 0;
        $header = null;

        if (($handle = fopen($filepath, 'r')) !== false) {
            // Read header row
            $header = fgetcsv($handle);
            
            if (!$header) {
                throw new Exception("CSV file is empty or invalid");
            }

            // Normalize header (trim and lowercase)
            $header = array_map(function($col) {
                return strtolower(trim($col));
            }, $header);

            // Find SKU and quantity columns
            $skuIndex = $this->findColumnIndex($header, ['sku', 'item', 'product_sku']);
            $qtyIndex = $this->findColumnIndex($header, ['available', 'on hand', 'on_hand', 'quantity', 'qty', 'available_qty']);

            if ($skuIndex === false) {
                throw new Exception("SKU column not found in CSV. Expected columns: sku, item, or product_sku");
            }

            if ($qtyIndex === false) {
                throw new Exception("Quantity column not found in CSV. Expected columns: available, on hand, quantity, qty, or available_qty");
            }

            $chunk = [];

            // Read data rows
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $chunk[] = ['row' => $row, 'number' => $rowNumber];

                if (count($chunk) >= $chunkSize) {
                    $this->processChunk($chunk, $header, $skuIndex, $qtyIndex, $batch, $shopifyService);
                    $chunk = [];
                }
            }

            // Process remaining rows
            if (!empty($chunk)) {
                $this->processChunk($chunk, $header, $skuIndex, $qtyIndex, $batch, $shopifyService);
            }

            fclose($handle);

            // Update total rows count
            $batch->update(['total_rows' => $rowNumber]);
        } else {
            throw new Exception("Unable to open CSV file");
        }
    }

    /**
     * Process a chunk of rows
     */
    protected function processChunk($chunk, $header, $skuIndex, $qtyIndex, $batch, $shopifyService)
    {
        foreach ($chunk as $item) {
            $row = $item['row'];
            $rowNumber = $item['number'];

            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $sku = isset($row[$skuIndex]) ? trim($row[$skuIndex]) : null;
                $newQty = isset($row[$qtyIndex]) ? trim($row[$qtyIndex]) : null;

                // Validate SKU
                if (empty($sku)) {
                    $this->logError($batch, $rowNumber, null, 'validation_error', 
                        'SKU is empty', $row);
                    $batch->incrementProcessed(false);
                    continue;
                }

                // Validate quantity
                if ($newQty === null || $newQty === '') {
                    $this->logError($batch, $rowNumber, $sku, 'validation_error', 
                        'Quantity is empty', $row);
                    $batch->incrementProcessed(false);
                    continue;
                }

                $newQty = (int) $newQty;

                // Process this inventory update
                $this->processInventoryUpdate($sku, $newQty, $batch, $shopifyService, $rowNumber, $row);

            } catch (Exception $e) {
                Log::error("Error processing row {$rowNumber}", [
                    'error' => $e->getMessage(),
                    'row' => $row,
                ]);

                $this->logError($batch, $rowNumber, $sku ?? null, 'processing_error', 
                    $e->getMessage(), $row);
                
                $batch->incrementProcessed(false);
            }
        }
    }

    /**
     * Process individual inventory update
     */
    protected function processInventoryUpdate($sku, $newQty, $batch, $shopifyService, $rowNumber, $rowData)
    {
        DB::beginTransaction();

        try {
            // Find SKU in inventory_master (inventories table)
            $inventory = Inventory::where('sku', $sku)->first();

            if (!$inventory) {
                $this->logError($batch, $rowNumber, $sku, 'sku_not_found', 
                    "SKU not found in inventory master", $rowData);
                $batch->incrementProcessed(false);
                DB::rollBack();
                return;
            }

            // Save old quantity
            $oldQty = $inventory->available_qty ?? $inventory->on_hand ?? 0;

            // Update available_qty in inventory_master
            $inventory->available_qty = $newQty;
            $inventory->save();

            // Insert inventory_logs entry
            $log = InventoryLog::create([
                'sku' => $sku,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'qty_change' => $newQty - $oldQty,
                'change_source' => 'csv_import',
                'batch_id' => $batch->id,
                'notes' => "CSV import - Row #{$rowNumber}",
                'created_by' => $batch->created_by,
            ]);

            // Push to Shopify if inventory_item_id exists
            if ($inventory->shopify_inventory_item_id) {
                $result = $shopifyService->pushInventoryToShopify(
                    $inventory->shopify_inventory_item_id, 
                    $newQty
                );

                if ($result['success']) {
                    $log->markPushedToShopify();
                } else {
                    $log->markShopifyError($result['message']);
                    
                    $this->logError($batch, $rowNumber, $sku, 'shopify_push_failed', 
                        $result['message'], $rowData);
                }
            } else {
                // Try to find inventory_item_id from Shopify API
                $inventoryItemId = $shopifyService->getInventoryItemIdBySku($sku);
                
                if ($inventoryItemId) {
                    // Update inventory record with the found ID
                    $inventory->shopify_inventory_item_id = $inventoryItemId;
                    $inventory->save();

                    // Push to Shopify
                    $result = $shopifyService->pushInventoryToShopify($inventoryItemId, $newQty);
                    
                    if ($result['success']) {
                        $log->markPushedToShopify();
                    } else {
                        $log->markShopifyError($result['message']);
                        
                        $this->logError($batch, $rowNumber, $sku, 'shopify_push_failed', 
                            $result['message'], $rowData);
                    }
                } else {
                    $log->markShopifyError('Inventory item ID not found in Shopify');
                    
                    $this->logError($batch, $rowNumber, $sku, 'shopify_push_failed', 
                        'Inventory item ID not found in Shopify', $rowData);
                }
            }

            $batch->incrementProcessed(true);
            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error("Error updating inventory for SKU: {$sku}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logError($batch, $rowNumber, $sku, 'processing_error', 
                $e->getMessage(), $rowData);
            
            $batch->incrementProcessed(false);
        }
    }

    /**
     * Log import error
     */
    protected function logError($batch, $rowNumber, $sku, $errorType, $errorMessage, $rowData)
    {
        InventoryImportError::create([
            'batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'sku' => $sku,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'row_data' => $rowData,
        ]);
    }

    /**
     * Find column index by possible names
     */
    protected function findColumnIndex($header, $possibleNames)
    {
        foreach ($possibleNames as $name) {
            $index = array_search(strtolower($name), $header);
            if ($index !== false) {
                return $index;
            }
        }
        return false;
    }
}
