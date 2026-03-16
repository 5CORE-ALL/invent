<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\EbayTwoListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingEbayTwoController extends Controller
{
    public function listingEbayTwo(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('ebaytwo_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingEbayTwo', [
            'ebayTwoPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingEbayTwoData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = EbayTwoListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData) {
            $childSku = $item->sku;
            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->nr_req = null;
            $item->listed = null;
            $item->buyer_link = null;
            $item->seller_link = null;
            if (isset($statusData[$childSku])) {
                // Handle value as array or JSON string
                $statusValue = $statusData[$childSku]->value;
                $status = is_array($statusValue) 
                    ? $statusValue 
                    : (json_decode($statusValue, true) ?? []);
                
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
        $status = EbayTwoListingStatus::where('sku', $sku)->first();

        // Handle existing value as array or JSON string
        $existing = [];
        if ($status && $status->value) {
            $existing = is_array($status->value) 
                ? $status->value 
                : (json_decode($status->value, true) ?? []);
        }

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        EbayTwoListingStatus::updateOrCreate(
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
        $statusData = EbayTwoListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

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

            $pendingCount = max($reqCount - $listedCount, 0);

            // Listed/Pending logic
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
            $request->validate([
                'file' => [
                    'required',
                    'file',
                    function ($attribute, $value, $fail) {
                        $extension = strtolower($value->getClientOriginalExtension());
                        $allowedExtensions = ['csv', 'txt'];
                        
                        if (!in_array($extension, $allowedExtensions)) {
                            $fail('The file must be a CSV or TXT file.');
                        }
                    }
                ]
            ]);

            $file = $request->file('file');
            $fileContent = file($file);
            
            // Detect delimiter (comma or tab)
            $firstLine = $fileContent[0];
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
            
            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);
            
            // Process header - remove BOM if present
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
            }, $rows[0]);

            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (!in_array($h, $allowedHeaders)) {
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
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    $skippedCount++;
                    continue;
                }

                try {
                    // Only import SKUs that exist in product_masters
                    if (!ProductMaster::where('sku', $sku)->exists()) {
                        $skippedCount++;
                        continue;
                    }

                    $status = EbayTwoListingStatus::where('sku', $sku)->first();
                    
                    // Handle existing value as array or JSON string
                    $existing = [];
                    if ($status && $status->value) {
                        $existing = is_array($status->value) 
                            ? $status->value 
                            : (json_decode($status->value, true) ?? []);
                    }

                    $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
                    foreach ($fields as $field) {
                        if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                            $existing[$field] = trim($rowData[$field]);
                        }
                    }

                    EbayTwoListingStatus::updateOrCreate(
                        ['sku' => $sku],
                        ['value' => $existing]
                    );
                    
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
            return response()->json([
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_ebaytwo_' . date('Y-m-d') . '.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = EbayTwoListingStatus::where('sku', $sku)->first();
                
                // Handle value as array or JSON string
                $value = [];
                if ($status && $status->value) {
                    $value = is_array($status->value) 
                        ? $status->value 
                        : (json_decode($status->value, true) ?? []);
                }

                $row = [
                    'sku'         => $sku,
                    'nr_req'      => $value['nr_req'] ?? '',
                    'listed'      => $value['listed'] ?? '',
                    'buyer_link'  => $value['buyer_link'] ?? '',
                    'seller_link' => $value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}