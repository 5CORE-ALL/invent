<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\BestbuyUSAListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingBestbuyUSAController extends Controller
{
    public function listingBestbuyUSA(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('bestbuyusa_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingBestbuyUSA', [
            'bestbuyUSAPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingBestbuyUSAData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Get status data, handling duplicates by taking the most recent non-empty record
        $statusData = BestbuyUSAListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                // Filter out records with empty or null values
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && (isset($value['nr_req']) || isset($value['listed']) || isset($value['buyer_link']) || isset($value['seller_link']));
            })
            ->keyBy('sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData) {
            $childSku = $item->sku;
            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            
            // If status exists, fill values from JSON
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                if (is_string($status)) {
                    $status = json_decode($status, true) ?? [];
                }
                if (is_array($status) && !empty($status)) {
                    // Use stored values or calculate defaults based on INV
                    $item->nr_req = $status['nr_req'] ?? (floatval($item->INV) > 0 ? 'REQ' : 'NR');
                    $item->listed = $status['listed'] ?? null;
                    $item->buyer_link = $status['buyer_link'] ?? null;
                    $item->seller_link = $status['seller_link'] ?? null;
                } else {
                    // Empty status - set defaults
                    $item->nr_req = floatval($item->INV) > 0 ? 'REQ' : 'NR';
                    $item->listed = null;
                    $item->buyer_link = null;
                    $item->seller_link = null;
                }
            } else {
                // No status record exists - set defaults based on INV
                $item->nr_req = floatval($item->INV) > 0 ? 'REQ' : 'NR';
                $item->listed = null;
                $item->buyer_link = null;
                $item->seller_link = null;
            }
            return $item;
        })->values();

        return response()->json([
            'status' => 200,
            'data' => $processedData
        ]);
    }

    public function saveStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = trim($validated['sku']);
        
        // Get the most recent non-empty record, or create new
        $status = BestbuyUSAListingStatus::where('sku', $sku)
            ->orderBy('updated_at', 'desc')
            ->first();

        // If we have a record, use its value, otherwise start fresh
        if ($status) {
            $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
            
            // If existing is empty array, start fresh
            if (empty($existing)) {
                $existing = [];
            }
        } else {
            $existing = [];
        }

        // Only update the fields that are present in the request and not empty
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $existing[$field] = $validated[$field];
            }
        }

        // Clean up: Delete any duplicate records for this SKU before creating/updating
        BestbuyUSAListingStatus::where('sku', $sku)->delete();

        // Create a single clean record
        BestbuyUSAListingStatus::create([
            'sku' => $sku,
            'value' => $existing
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Get status data, handling duplicates by taking the most recent non-empty record
        $statusData = BestbuyUSAListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                // Filter out records with empty or null values
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && (isset($value['nr_req']) || isset($value['listed']) || isset($value['buyer_link']) || isset($value['seller_link']));
            })
            ->keyBy('sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = null;
            if (isset($statusData[$sku])) {
                $status = $statusData[$sku]->value;
                if (is_string($status)) {
                    $status = json_decode($status, true);
                }
                if (is_array($status) && empty($status)) {
                    $status = null;
                }
            }

            // NR/REQ logic
            $nrReq = ($status && isset($status['nr_req'])) ? $status['nr_req'] : (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            $listed = ($status && isset($status['listed'])) ? $status['listed'] : null;
            if ($listed === 'Listed') {
                $listedCount++;
            }

            // Row-wise pending logic to match frontend
            if ($nrReq !== 'NR' && ($listed === 'Pending' || empty($listed))) {
                $pendingCount++;
            }
            // $nrReq = $status['nr_req'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            // if ($nrReq === 'REQ') {
            //     $reqCount++;
            // }

            // // Listed/Pending logic
            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            // if ($listed === 'Listed') {
            //     $listedCount++;
            // } elseif ($listed === 'Pending') {
            //     $pendingCount++;
            // }
        }

        return [
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }

     public function import(Request $request)
    {
        try {
            Log::info('=== Bestbuy USA CSV Import Started ===');
            
            $request->validate([
                'file' => 'required|mimes:csv,txt',
            ]);
            Log::info('File validation passed');

            $file = $request->file('file');
            Log::info('File received', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize()
            ]);

            $content = file_get_contents($file->getRealPath());
            Log::info('File content length: ' . strlen($content));
            
            // Remove BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            
            // Detect delimiter (tab or comma)
            $firstLine = strtok($content, "\n");
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
            Log::info('Detected delimiter: ' . ($delimiter === "\t" ? 'TAB' : 'COMMA'));

            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, explode("\n", $content));

            Log::info('Total rows parsed: ' . count($rows));

            // Process header
            $header = array_map('trim', $rows[0]);
            Log::info('CSV Headers detected', ['headers' => $header]);
            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (!in_array($h, $allowedHeaders)) {
                    Log::error('Invalid header found', ['header' => $h, 'allowed' => $allowedHeaders]);
                    return response()->json([
                        'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                    ], 422);
                }
            }
            Log::info('Header validation passed');

            $processedCount = 0;
            $skippedCount = 0;

            foreach ($rows as $index => $row) {
                if (count($row) < 1 || (count($row) === 1 && trim($row[0]) === '')) {
                    Log::info("Row $index: Skipped (empty row)");
                    $skippedCount++;
                    continue;
                }

                $rowData = array_combine($header, $row);
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    Log::info("Row $index: Skipped (no SKU)");
                    $skippedCount++;
                    continue;
                }

                Log::info("Row $index: Processing SKU", ['sku' => $sku, 'data' => $rowData]);

                // Only import SKUs that exist in product_masters
                if (!ProductMaster::where('sku', $sku)->exists()) {
                    Log::info("Row $index: Skipped (SKU not in product_masters)", ['sku' => $sku]);
                    $skippedCount++;
                    continue;
                }

                // Get the most recent non-empty record, or start fresh
                $status = BestbuyUSAListingStatus::where('sku', $sku)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($status) {
                    $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
                    if (empty($existing)) {
                        $existing = [];
                    }
                } else {
                    $existing = [];
                }
                Log::info("Row $index: Existing status", ['sku' => $sku, 'existing' => $existing]);

                $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                        $existing[$field] = trim($rowData[$field]);
                        Log::info("Row $index: Updated field", ['sku' => $sku, 'field' => $field, 'value' => $rowData[$field]]);
                    }
                }

                // Clean up duplicates before creating/updating
                BestbuyUSAListingStatus::where('sku', $sku)->delete();

                // Create a single clean record
                BestbuyUSAListingStatus::create([
                    'sku' => $sku,
                    'value' => $existing
                ]);
                Log::info("Row $index: Successfully saved", ['sku' => $sku, 'final_data' => $existing]);
                $processedCount++;
            }

            Log::info('=== Bestbuy USA CSV Import Completed ===', [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'total_rows' => count($rows)
            ]);

            return response()->json([
                'success' => 'CSV imported successfully',
                'processed' => $processedCount,
                'skipped' => $skippedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Bestbuy USA CSV Import Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = BestbuyUSAListingStatus::where('sku', $sku)->first();

                $row = [
                    'sku'         => $sku,
                    'nr_req'      => $status->value['nr_req'] ?? '',
                    'listed'      => $status->value['listed'] ?? '',
                    'buyer_link'  => $status->value['buyer_link'] ?? '',
                    'seller_link' => $status->value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}