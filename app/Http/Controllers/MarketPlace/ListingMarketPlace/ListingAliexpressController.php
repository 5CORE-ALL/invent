<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AliexpressListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingAliexpressController extends Controller
{
    public function listingAliexpress(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('aliexpress_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingAliexpress', [
            'aliexpressPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingAliexpressData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Get status data, handling duplicates by taking the most recent non-empty record
        $statusData = AliexpressListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                // Filter out records with empty or null values
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && (isset($value['rl_nrl']) || isset($value['nr_req']) || isset($value['listed']) || isset($value['live_inactive']) || isset($value['buyer_link']) || isset($value['seller_link']));
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
                    // Use stored values, support both old 'nr_req' and new 'rl_nrl'
                    $item->rl_nrl = $status['rl_nrl'] ?? $status['nr_req'] ?? (floatval($item->INV) > 0 ? 'RL' : 'NRL');
                    // Map old values to new values if needed
                    if (isset($status['nr_req']) && !isset($status['rl_nrl'])) {
                        $item->rl_nrl = ($status['nr_req'] === 'REQ') ? 'RL' : (($status['nr_req'] === 'NR') ? 'NRL' : $item->rl_nrl);
                    }
                    $item->listed = $status['listed'] ?? null;
                    $item->live_inactive = $status['live_inactive'] ?? null;
                    $item->buyer_link = $status['buyer_link'] ?? null;
                    $item->seller_link = $status['seller_link'] ?? null;
                } else {
                    // Empty status - set defaults
                    $item->rl_nrl = floatval($item->INV) > 0 ? 'RL' : 'NRL';
                    $item->listed = null;
                    $item->live_inactive = null;
                    $item->buyer_link = null;
                    $item->seller_link = null;
                }
            } else {
                // No status record exists - set defaults based on INV
                $item->rl_nrl = floatval($item->INV) > 0 ? 'RL' : 'NRL';
                $item->listed = null;
                $item->live_inactive = null;
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
            'rl_nrl' => 'nullable|string',
            'listed' => 'nullable|string',
            'live_inactive' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = trim($validated['sku']);
        
        // Get the most recent non-empty record, or create new
        $status = AliexpressListingStatus::where('sku', $sku)
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
        $fields = ['rl_nrl', 'listed', 'live_inactive', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $existing[$field] = $validated[$field];
            }
        }

        // Clean up: Delete any duplicate records for this SKU before creating/updating
        AliexpressListingStatus::where('sku', $sku)->delete();

        // Create a single clean record
        AliexpressListingStatus::create([
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
        $statusData = AliexpressListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

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

            // RL/NRL logic (support legacy nr_req for backward compatibility)
            $rlNrl = ($status && isset($status['rl_nrl'])) ? $status['rl_nrl'] : (($status && isset($status['nr_req'])) ? (($status['nr_req'] === 'REQ') ? 'RL' : 'NRL') : (floatval($inv) > 0 ? 'RL' : 'NRL'));
            if ($rlNrl === 'RL') {
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
            $request->validate([
                'file' => 'required|mimes:csv,txt',
            ]);

            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            
            // Remove BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            
            // Detect delimiter (tab or comma)
            $firstLine = strtok($content, "\n");
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";

            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, explode("\n", $content));

            // Process header
            $header = array_map('trim', $rows[0]);
            unset($rows[0]);

            // Allowed headers: SKU is required, plus all editable fields
            // Explicitly exclude: parent, inv, listing_status (these are read-only/computed)
            $requiredHeaders = ['sku'];
            $allowedHeaders = ['sku', 'rl_nrl', 'nr_req', 'listed', 'live_inactive', 'buyer_link', 'seller_link'];
            $excludedHeaders = ['parent', 'inv', 'listing_status', 'listing status'];
            
            // Normalize header keys to lowercase for comparison
            $headerLower = array_map('strtolower', $header);
            
            // Check if SKU is present
            if (!in_array('sku', $headerLower)) {
                return response()->json([
                    'error' => "Required header 'sku' is missing. CSV must include 'sku' column."
                ], 422);
            }

            // Check for excluded headers and reject them
            $foundExcluded = [];
            $excludedLower = array_map('strtolower', $excludedHeaders);
            foreach ($headerLower as $index => $h) {
                if (in_array($h, $excludedLower)) {
                    $foundExcluded[] = $header[$index];
                }
            }
            
            if (!empty($foundExcluded)) {
                return response()->json([
                    'error' => "Excluded header(s) found: " . implode(', ', $foundExcluded) . ". These columns (parent, inv, listing_status) cannot be imported. Please remove them from your CSV file."
                ], 422);
            }

            // Validate all headers are allowed
            $invalidHeaders = [];
            $allowedLower = array_map('strtolower', $allowedHeaders);
            foreach ($headerLower as $index => $h) {
                if (!in_array($h, $allowedLower)) {
                    $invalidHeaders[] = $header[$index];
                }
            }
            
            if (!empty($invalidHeaders)) {
                return response()->json([
                    'error' => "Invalid header(s): " . implode(', ', $invalidHeaders) . ". Allowed headers: sku, rl_nrl, listed, live_inactive, buyer_link, seller_link"
                ], 422);
            }

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($rows as $index => $row) {
                if (count($row) < 1 || (count($row) === 1 && trim($row[0]) === '')) {
                    $skippedCount++;
                    continue;
                }

                // Pad row with empty strings if it has fewer columns than header
                $headerCount = count($header);
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                }
                
                // Trim row if it has more columns than header
                if ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                $rowData = array_combine($header, $row);
                
                // Normalize keys to lowercase for case-insensitive matching
                $rowDataNormalized = [];
                foreach ($rowData as $key => $value) {
                    $rowDataNormalized[strtolower($key)] = $value;
                }
                
                $sku = trim($rowDataNormalized['sku'] ?? '');

                if (!$sku) {
                    $skippedCount++;
                    continue;
                }

                try {
                    // Only import SKUs that exist in product_masters
                    if (!ProductMaster::where('sku', $sku)->whereNull('deleted_at')->exists()) {
                        $skippedCount++;
                        continue;
                    }

                    // Get the most recent non-empty record, or start fresh
                    $status = AliexpressListingStatus::where('sku', $sku)
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

                    // Import editable fields (case-insensitive matching)
                    $fields = ['rl_nrl', 'listed', 'live_inactive', 'buyer_link', 'seller_link'];
                    foreach ($fields as $field) {
                        $fieldKey = strtolower($field);
                        if (array_key_exists($fieldKey, $rowDataNormalized) && trim($rowDataNormalized[$fieldKey]) !== '') {
                            $existing[$field] = trim($rowDataNormalized[$fieldKey]);
                        }
                    }
                    
                    // Support legacy 'nr_req' field for backward compatibility
                    $nrReqKey = strtolower('nr_req');
                    if (array_key_exists($nrReqKey, $rowDataNormalized) && trim($rowDataNormalized[$nrReqKey]) !== '' && !isset($existing['rl_nrl'])) {
                        $nrReq = trim($rowDataNormalized[$nrReqKey]);
                        $existing['rl_nrl'] = ($nrReq === 'REQ') ? 'RL' : (($nrReq === 'NR') ? 'NRL' : $nrReq);
                    }
                    
                    // Note: parent, inv, and listing_status columns are ignored as they are read-only or computed

                    // Clean up duplicates before creating/updating
                    AliexpressListingStatus::where('sku', $sku)->delete();

                    // Create a single clean record
                    AliexpressListingStatus::create([
                        'sku' => $sku,
                        'value' => $existing
                    ]);
                    
                    $processedCount++;
                    
                } catch (\Exception $rowError) {
                    $errorCount++;
                }
            }

            $message = 'CSV imported successfully';
            if ($errorCount > 0) {
                $message .= " (Processed: $processedCount, Skipped: $skippedCount, Errors: $errorCount)";
            }

            return response()->json([
                'success' => $message,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="aliexpress_listing_export.csv"',
        ];

        // Export columns: Parent, SKU, INV (for reference), and all editable fields (excluding Listing Status as it's computed)
        $columns = ['parent', 'sku', 'inv', 'rl_nrl', 'listed', 'live_inactive', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all products from product master
            $productMasters = ProductMaster::whereNull('deleted_at')->get();
            $skus = $productMasters->pluck('sku')->unique()->toArray();

            // Get Shopify inventory data
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            // Get all status data
            $statusData = AliexpressListingStatus::whereIn('sku', $skus)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->keyBy('sku');

            foreach ($productMasters as $product) {
                $sku = $product->sku;
                $shopifyItem = $shopifyData[$sku] ?? null;
                $status = $statusData[$sku] ?? null;

                $statusValue = [];
                if ($status) {
                    $statusValue = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
                }

                // Handle rl_nrl with backward compatibility for nr_req
                $rlNrl = $statusValue['rl_nrl'] ?? '';
                if (empty($rlNrl) && isset($statusValue['nr_req'])) {
                    $rlNrl = ($statusValue['nr_req'] === 'REQ') ? 'RL' : (($statusValue['nr_req'] === 'NR') ? 'NRL' : '');
                }

                $row = [
                    'parent'       => $product->parent ?? '',
                    'sku'          => $sku,
                    'inv'          => $shopifyItem->inv ?? 0,
                    'rl_nrl'       => $rlNrl,
                    'listed'       => $statusValue['listed'] ?? '',
                    'live_inactive' => $statusValue['live_inactive'] ?? '',
                    'buyer_link'   => $statusValue['buyer_link'] ?? '',
                    'seller_link'  => $statusValue['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="aliexpress_listing_import_sample.csv"',
        ];

        // Sample file columns: Only editable fields (exclude parent, inv, listing_status)
        $columns = ['sku', 'rl_nrl', 'listed', 'live_inactive', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Write sample data rows
            $sampleRows = [
                [
                    'sku' => 'EXAMPLE-SKU-001',
                    'rl_nrl' => 'RL',
                    'listed' => 'Listed',
                    'live_inactive' => 'Live',
                    'buyer_link' => 'https://www.aliexpress.com/buyer-link-example',
                    'seller_link' => 'https://www.aliexpress.com/seller-link-example'
                ],
                [
                    'sku' => 'EXAMPLE-SKU-002',
                    'rl_nrl' => 'NRL',
                    'listed' => 'Pending',
                    'live_inactive' => 'Inactive',
                    'buyer_link' => '',
                    'seller_link' => ''
                ],
                [
                    'sku' => 'EXAMPLE-SKU-003',
                    'rl_nrl' => 'RL',
                    'listed' => 'Listed',
                    'live_inactive' => 'Live',
                    'buyer_link' => 'https://www.aliexpress.com/buyer-link-example-2',
                    'seller_link' => 'https://www.aliexpress.com/seller-link-example-2'
                ]
            ];

            foreach ($sampleRows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}