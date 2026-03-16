<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInventoryCSVAndPushJob;
use App\Models\InventoryImportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ShopifyInventoryImportController extends Controller
{
    /**
     * Show the import form
     */
    public function index(Request $request)
    {
        $batches = InventoryImportBatch::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('inventory.shopify-import.index', compact('batches', 'mode', 'demo'));
    }

    /**
     * Import CSV file
     */
    public function importCSV(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:51200', // Max 50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('csv_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store file in storage/app/inventory_imports
            $filepath = $file->storeAs('inventory_imports', $filename);

            // Create import batch record
            $batch = InventoryImportBatch::create([
                'filename' => $filename,
                'filepath' => $filepath,
                'total_rows' => 0,
                'processed_rows' => 0,
                'successful_rows' => 0,
                'failed_rows' => 0,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // Dispatch queued job
            ProcessInventoryCSVAndPushJob::dispatch($batch->id);

            Log::info("CSV import job dispatched", [
                'batch_id' => $batch->id,
                'filename' => $filename,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'CSV file uploaded successfully. Processing has started in the background.',
                'batch_id' => $batch->id,
                'batch' => $batch,
            ]);

        } catch (\Exception $e) {
            Log::error("CSV upload failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload CSV file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get batch status
     */
    public function getBatchStatus($batchId)
    {
        $batch = InventoryImportBatch::with(['errors' => function($query) {
            $query->orderBy('row_number');
        }])->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'batch' => $batch,
            'progress_percentage' => $batch->total_rows > 0 
                ? round(($batch->processed_rows / $batch->total_rows) * 100, 2)
                : 0,
        ]);
    }

    /**
     * Get batch errors
     */
    public function getBatchErrors($batchId)
    {
        $batch = InventoryImportBatch::find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        $errors = $batch->errors()
            ->orderBy('row_number')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'errors' => $errors,
        ]);
    }

    /**
     * Download error report
     */
    public function downloadErrorReport($batchId)
    {
        $batch = InventoryImportBatch::with('errors')->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        if ($batch->errors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No errors found for this batch',
            ], 404);
        }

        // Generate CSV error report
        $csvData = "Row Number,SKU,Error Type,Error Message\n";
        
        foreach ($batch->errors as $error) {
            $csvData .= sprintf(
                "%s,%s,%s,%s\n",
                $error->row_number,
                $error->sku ?? 'N/A',
                $error->error_type,
                str_replace(["\n", "\r", ","], [" ", " ", ";"], $error->error_message)
            );
        }

        $filename = "error_report_batch_{$batchId}_" . date('Y-m-d_His') . ".csv";

        return response($csvData, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Retry failed items
     */
    public function retryBatch($batchId)
    {
        $batch = InventoryImportBatch::find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        if ($batch->status === 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Batch is currently processing',
            ], 400);
        }

        try {
            // Reset batch status
            $batch->update([
                'status' => 'pending',
                'processed_rows' => 0,
                'successful_rows' => 0,
                'failed_rows' => 0,
                'error_message' => null,
            ]);

            // Clear old errors
            $batch->errors()->delete();

            // Dispatch job again
            ProcessInventoryCSVAndPushJob::dispatch($batch->id);

            return response()->json([
                'success' => true,
                'message' => 'Batch retry started',
                'batch' => $batch,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry batch: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete batch
     */
    public function deleteBatch($batchId)
    {
        $batch = InventoryImportBatch::find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        if ($batch->status === 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a batch that is currently processing',
            ], 400);
        }

        try {
            // Delete file
            if (Storage::exists($batch->filepath)) {
                Storage::delete($batch->filepath);
            }

            // Delete errors
            $batch->errors()->delete();

            // Delete batch
            $batch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Batch deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete batch: ' . $e->getMessage(),
            ], 500);
        }
    }
}
