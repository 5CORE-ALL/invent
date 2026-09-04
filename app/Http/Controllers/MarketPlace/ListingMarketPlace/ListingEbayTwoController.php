<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\EbayTwoListingStatus;
use App\Models\EbayTwoDataView;
use App\Models\Ebay2Metric;
use App\Support\Marketplace\EbayTwoListingCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingEbayTwoController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'ebaytwo';
    }

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
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

        $shopifyData = ListingCountsEngine::shopifyMap($skus);

        // Links only — NRL/REQ + Listed are automated (same sources as /ebay2-tabulator-view)
        $statusData = EbayTwoListingStatus::whereIn('sku', $skus)
            ->get()
            ->mapWithKeys(function ($row) {
                return [strtolower(trim((string) $row->sku)) => $row];
            });

        // NRL column — same source as ebay2-tabulator-view (EbayTwoDataView.value.NRL)
        $nrValues = ListingCountsEngine::loadNrValues(EbayTwoDataView::class, $skus);

        // Missing Listing / Listed — same rule as ebay2-tabulator-view Missing L (ebay_2_metrics.item_id)
        $ebayMetrics = \App\Support\Marketplace\ListingCountsEngine::listedIdsFromColumn(
            Ebay2Metric::class,
            $skus,
            'item_id'
        );

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData, $nrValues, $ebayMetrics) {
            $childSku = (string) $item->sku;
            $skuLower = strtolower(trim($childSku));

            $shopify = ListingCountsEngine::shopifyRow($shopifyData, $childSku);
            $item->INV = ListingCountsEngine::shopifyInv($shopify);
            $item->L30 = $shopify?->quantity ?? 0;

            // Links from listing status table (buyer/seller only)
            $item->buyer_link = null;
            $item->seller_link = null;
            if (isset($statusData[$skuLower])) {
                $statusValue = $statusData[$skuLower]->value;
                $status = is_array($statusValue)
                    ? $statusValue
                    : (json_decode($statusValue, true) ?? []);
                $item->buyer_link = $status['buyer_link'] ?? null;
                $item->seller_link = $status['seller_link'] ?? null;
            }

            // NRL/REQ from EbayTwoDataView (same as ebay2 NRL column / EbayTwoListingCounts)
            $item->nr_req = EbayTwoListingCounts::nrReqFromDataView(
                ListingCountsEngine::lookupNrValue($nrValues, $childSku)
            );

            // Listed / Not Listed from ebay_2_metrics.item_id (Missing Listing logic)
            $itemId = \App\Support\Marketplace\ListingCountsEngine::listingIdFromMap($ebayMetrics, $childSku);
            $item->eBay_item_id = $itemId !== '' ? $itemId : null;
            $item->listed = $item->eBay_item_id ? 'Listed' : 'Pending';

            return $item;
        })->values();

        return response()->json([
            'status' => 200,
            'data' => $processedData
        ]);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

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
        $counts = EbayTwoListingCounts::counts();

        return [
            'REQ' => $counts['REQ'],
            'NRL' => $counts['NRL'],
            'Listed' => $counts['Listed'],
            'Pending' => $counts['MissingL'],
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