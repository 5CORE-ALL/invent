<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MacysListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingMacysController extends Controller
{
    public function listingMacys(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingMacys', [
            'macysPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingMacysData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = MacysListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData) {
            $childSku = $item->sku;
            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->nr_req = null;
            $item->listed = null;
            $item->buyer_link = null;
            $item->seller_link = null;
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                $item->nr_req = $status['nr_req'] ?? null;
                $item->listed = $status['listed'] ?? null;
                $item->buyer_link = $status['buyer_link'] ?? null;
                $item->seller_link = $status['seller_link'] ?? null;
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
        $status = MacysListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        MacysListingStatus::updateOrCreate(
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
        $statusData = MacysListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

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

            // Listed/Pending logic
            $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            if ($listed === 'Listed') {
                $listedCount++;
            } elseif ($listed === 'Pending') {
                $pendingCount++;
            }
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
            Log::info('=== Macys CSV Import Started ===');
            
            $request->validate([
                'file' => 'required|mimes:csv,txt',
            ]);

            $file = $request->file('file');
            Log::info('File uploaded: ' . $file->getClientOriginalName());
            
            $fileContent = file($file);
            Log::info('Total lines in file: ' . count($fileContent));
            
            // Detect delimiter (comma or tab)
            $firstLine = $fileContent[0];
            Log::info('First line (raw): ' . json_encode($firstLine));
            
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
            Log::info('Detected delimiter: ' . ($delimiter === "\t" ? 'TAB' : 'COMMA'));
            
            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);
            
            // Process header - remove BOM if present
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
            }, $rows[0]);
            
            Log::info('Headers detected: ' . json_encode($header));

            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (!in_array($h, $allowedHeaders)) {
                    Log::error("Invalid header found: '$h'");
                    return response()->json([
                        'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                    ], 422);
                }
            }

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($rows as $rowIndex => $row) {
                if (count($row) < 1) {
                    Log::info("Row $rowIndex: Skipped (empty row)");
                    $skippedCount++;
                    continue; // skip empty
                }

                Log::info("Row $rowIndex data: " . json_encode($row));

                $rowData = array_combine($header, $row);
                Log::info("Row $rowIndex combined: " . json_encode($rowData));
                
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    Log::info("Row $rowIndex: Skipped (no SKU)");
                    $skippedCount++;
                    continue;
                }

                // Only import SKUs that exist in product_masters
                if (!ProductMaster::where('sku', $sku)->exists()) {
                    Log::warning("Row $rowIndex: SKU '$sku' not found in product_masters");
                    $skippedCount++;
                    continue;
                }

                $status = MacysListingStatus::where('sku', $sku)->first();
                $existing = $status ? $status->value : [];

                $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                        $existing[$field] = $rowData[$field];
                    }
                }

                MacysListingStatus::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => $existing]
                );
                
                Log::info("Row $rowIndex: SKU '$sku' processed successfully");
                $processedCount++;
            }

            Log::info("=== Macys CSV Import Completed ===");
            Log::info("Processed: $processedCount, Skipped: $skippedCount, Errors: $errorCount");

            return response()->json([
                'success' => 'CSV imported successfully',
                'processed' => $processedCount,
                'skipped' => $skippedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Macys CSV Import Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
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
                $status = MacysListingStatus::where('sku', $sku)->first();

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