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
        $statusData = BestbuyUSAListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData) {
            $childSku = $item->sku;
            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            
            // If status exists, fill values from JSON
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                // Use stored values or calculate defaults based on INV
                $item->nr_req = $status['nr_req'] ?? (floatval($item->INV) > 0 ? 'REQ' : 'NR');
                $item->listed = $status['listed'] ?? null;
                $item->buyer_link = $status['buyer_link'] ?? null;
                $item->seller_link = $status['seller_link'] ?? null;
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
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = BestbuyUSAListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        BestbuyUSAListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = BestbuyUSAListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic
            $nrReq = $status['nr_req'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            $listed = $status['listed'] ?? null;
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

                $status = BestbuyUSAListingStatus::where('sku', $sku)->first();
                $existing = $status ? $status->value : [];
                Log::info("Row $index: Existing status", ['sku' => $sku, 'existing' => $existing]);

                $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                        $existing[$field] = $rowData[$field];
                        Log::info("Row $index: Updated field", ['sku' => $sku, 'field' => $field, 'value' => $rowData[$field]]);
                    }
                }

                BestbuyUSAListingStatus::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => $existing]
                );
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