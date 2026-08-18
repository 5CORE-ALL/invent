<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WayfairListingStatus;
use App\Models\WayfairPricingPrice;
use App\Services\MarketplaceManager\WayfairLiveListingsService;
use App\Services\WayfairApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingWayfairController extends Controller
{
    use HandlesListingPublishActions;


    public function updatePricing(Request $request)
    {
        $service = new WayfairApiService();

        $itemID = $request["sku"];
        $newPrice = $request["price"];

        $results = $service->updatePrice($itemID,$newPrice);

        dd($results);

        // $result = UpdateEbaySPriceJob::dispatch($itemID, $newPrice)->delay(now()->addMinutes(3));

        // $response = $service->reviseFixedPriceItem(
        //     itemId: $itemID,
        //     price: $newPrice,
        // );

        return response()->json(['status' => 200]);
    }

    public function listingWayfair(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('wayfair_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingWayfair', [
            'wayfairPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingWayfairData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('wayfair'),
        ]);
    }

    /**
     * Check Missing L SKUs against the Wayfair API and mark any found SKUs as listed
     * (same source as this page: wayfair_pricing_prices.sku).
     */
    public function verifyListings(Request $request)
    {
        @set_time_limit(0);

        $service = new WayfairApiService();
        if (! $service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Wayfair API credentials missing. Set WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET.',
            ], 422);
        }

        try {
            $rows = AutomatedListingPage::rows('wayfair');
            $missing = [];
            foreach ($rows as $item) {
                $sku = trim((string) ($item->sku ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                if (strcasecmp((string) ($item->listed ?? ''), 'Listed') === 0) {
                    continue;
                }
                if ((float) ($item->INV ?? 0) <= 0) {
                    continue;
                }
                $missing[] = $sku;
            }
            $missing = array_values(array_unique($missing));

            if ($missing === []) {
                return response()->json([
                    'success' => true,
                    'message' => 'No Missing L SKUs to verify (INV > 0 + not listed).',
                    'checked' => 0,
                    'found' => 0,
                    'updated' => 0,
                    'not_found' => 0,
                ]);
            }

            $lookup = $service->lookupInventoryBySkus($missing);
            $apiItems = $lookup['items'] ?? [];
            $updated = 0;
            $foundSkus = [];

            foreach ($missing as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                $hit = $apiItems[$norm] ?? $apiItems[strtoupper(trim($sku))] ?? null;
                if (! is_array($hit)) {
                    continue;
                }

                $qty = (int) ($hit['quantity'] ?? 0);
                $price = $hit['price'] ?? null;
                $attrs = ['wayfair_stock' => max(0, $qty)];
                if ($price !== null && is_numeric($price) && (float) $price > 0) {
                    $attrs['price'] = $price;
                }
                WayfairPricingPrice::upsertBySku($sku, $attrs);

                try {
                    $this->markListingStatusListed($sku);
                } catch (\Throwable $e) {
                    Log::warning('Wayfair verifyListings: listing status save failed', [
                        'sku' => $sku,
                        'error' => $e->getMessage(),
                    ]);
                }
                $updated++;
                $foundSkus[] = $sku;
            }

            try {
                app(WayfairLiveListingsService::class)->clearCache();
            } catch (\Throwable $e) {
                Log::warning('Wayfair verifyListings: live cache clear failed', ['error' => $e->getMessage()]);
            }

            $notFound = count($missing) - $updated;
            $source = (string) ($lookup['source'] ?? 'api');
            $message = 'Checked '.$this->countLabel(count($missing)).". Found {$updated} on Wayfair and updated.";
            if ($notFound > 0) {
                $message .= " {$notFound} still missing.";
            }
            if ($updated === 0 && ! empty($lookup['error'])) {
                $err = (string) $lookup['error'];
                if (stripos($err, 'permission') !== false || stripos($err, 'denied') !== false) {
                    $err = 'Wayfair catalog read is not enabled on the production API app (needs Read Catalog). Listed today only comes from wayfair_pricing_prices, which does not include live Partner Home inventory. '.$err;
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Wayfair API lookup failed: '.$err,
                    'checked' => count($missing),
                    'found' => 0,
                    'updated' => 0,
                    'not_found' => count($missing),
                    'source' => $source,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'checked' => count($missing),
                'found' => $updated,
                'updated' => $updated,
                'not_found' => $notFound,
                'source' => $source,
                'found_skus' => $foundSkus,
            ]);
        } catch (\Throwable $e) {
            Log::error('Wayfair verifyListings failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Verify failed: '.$e->getMessage(),
            ], 500);
        }
    }

    private function countLabel(int $n): string
    {
        return $n.' Missing L SKU'.($n === 1 ? '' : 's');
    }

    private function markListingStatusListed(string $sku): void
    {
        $status = WayfairListingStatus::where('sku', $sku)
            ->orderBy('updated_at', 'desc')
            ->first();
        $existing = [];
        if ($status) {
            $existing = is_array($status->value)
                ? $status->value
                : (json_decode((string) $status->value, true) ?? []);
            if (! is_array($existing)) {
                $existing = [];
            }
        }
        $existing['listed'] = 'Listed';
        WayfairListingStatus::upsertBySku($sku, $existing);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

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
        $status = WayfairListingStatus::where('sku', $sku)
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

        WayfairListingStatus::upsertBySku($sku, $existing);

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('wayfair');
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
            $allowedHeaders = ['sku', 'rl_nrl', 'listed', 'live_inactive', 'buyer_link', 'seller_link'];
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
                    $status = WayfairListingStatus::where('sku', $sku)
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
                    WayfairListingStatus::where('sku', $sku)->delete();

                    // Create a single clean record
                    WayfairListingStatus::create([
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
            'Content-Disposition' => 'attachment; filename="wayfair_listing_export.csv"',
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
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            // Get all status data
            $statusData = WayfairListingStatus::whereIn('sku', $skus)
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
                    'inv'          => $shopifyItem ? ($shopifyItem->inv ?? 0) : 0,
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
            'Content-Disposition' => 'attachment; filename="wayfair_listing_import_sample.csv"',
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
                    'buyer_link' => 'https://www.wayfair.com/buyer-link-example',
                    'seller_link' => 'https://www.wayfair.com/seller-link-example'
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
                    'buyer_link' => 'https://www.wayfair.com/buyer-link-example-2',
                    'seller_link' => 'https://www.wayfair.com/seller-link-example-2'
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
