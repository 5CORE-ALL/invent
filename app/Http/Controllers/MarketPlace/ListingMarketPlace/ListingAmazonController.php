<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ProductStockMapping;
use App\Support\Marketplace\AmazonListingCounts;
use Illuminate\Http\Request;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingDailyMetric;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ListingAmazonController extends Controller
{
    use HandlesListingPublishActions;

    public function listingAmazon(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingAmazon', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getViewListingAmazonData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Links only — NRL/REQ + Listed are automated (same pattern as /listing-ebaytwo)
        $statusData = AmazonDataView::whereIn('sku', $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });

        // Listed / Missing L from amazon_listings_raw (SP-API), not datasheet
        $listingsByNorm = AmazonListingCounts::listingsByNormalizedSku();

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData, $listingsByNorm) {
            $childSku = (string) $item->sku;
            $skuUpper = strtoupper(trim($childSku));

            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;

            $raw = $statusData->has($skuUpper) ? $statusData->get($skuUpper) : null;

            // NRL/REQ from AmazonDataView (same as amazon-tabulator / AmazonListingCounts)
            $item->nr_req = AmazonListingCounts::nrReqFromDataView($raw);
            $item->NR = $item->nr_req;

            $listing = AmazonListingCounts::pickListingForProductSku($childSku, $listingsByNorm);
            $asin = AmazonListingCounts::asinFromApi($listing);
            $item->asin = $asin !== '' ? $asin : null;
            $item->listed = AmazonListingCounts::isListedFromApi($listing) ? 'Listed' : 'Pending';
            $item->listing_status = null;

            return $item;
        })->values();

        return response()->json([
            'status' => 200,
            'data' => $processedData,
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
            'status' => 'nullable|string|in:Active,DC,2BDC,Sourcing,In Transit,To Order,MFRG,',
        ]);

        $sku = $validated['sku'];
        $status = AmazonDataView::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];
        if (! is_array($existing)) {
            $existing = is_string($existing) ? (json_decode($existing, true) ?: []) : [];
        }

        // Handle nr_req - save as NRL field in amazon_data_view
        // Map to match format used by other Amazon pages: 'RL' for RL, 'NRL' for NRL
        if ($request->has('nr_req')) {
            // Map: 'NR' -> 'NRL', 'REQ' -> 'RL' (to sync with other Amazon pages)
            $existing['NRL'] = ($validated['nr_req'] === 'NR') ? 'NRL' : 'RL';
        }

        // Handle listed field - save as Listed (capitalized) to match the JSON structure
        if ($request->has('listed')) {
            // Save to both 'Listed' (for boolean conversion) and 'listed' (for string)
            $existing['Listed'] = ($validated['listed'] === 'Listed') ? true : false;
            $existing['listed'] = $validated['listed'];
        }

        // Buyer/Seller links are dynamic from ASIN (same as listing-amazon UI) — not manually saved.

        AmazonDataView::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        // Handle status - save to ProductMaster Values field
        if ($request->has('status')) {
            $product = ProductMaster::where('sku', $sku)->first();
            if ($product) {
                $values = is_array($product->Values) ? $product->Values : 
                         (is_string($product->Values) ? json_decode($product->Values, true) : []);
                
                if (!is_array($values)) {
                    $values = [];
                }
                
                // Update status in Values field
                $statusValue = $validated['status'];
                if ($statusValue === '') {
                    // Remove status if empty string
                    unset($values['status']);
                } else {
                    $values['status'] = $statusValue;
                }
                
                $product->Values = $values;
                $product->save();
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $counts = AmazonListingCounts::counts();

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
                        if (! in_array($extension, ['csv', 'txt'], true)) {
                            $fail('The file must be a CSV or TXT file.');
                        }
                    },
                ],
            ]);

            $file = $request->file('file');
            $fileContent = file($file->getRealPath());
            $firstLine = $fileContent[0] ?? '';
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ',';

            $rows = array_map(function ($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);

            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
            }, $rows[0] ?? []);
            unset($rows[0]);

            // Buyer/Seller links are ASIN-dynamic on the page — not imported manually.
            $allowedHeaders = ['sku', 'nr_req', 'listed'];
            foreach ($header as $h) {
                if (! in_array($h, $allowedHeaders, true)) {
                    return response()->json([
                        'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders),
                    ], 422);
                }
            }

            $processedCount = 0;
            $skippedCount = 0;

            foreach ($rows as $row) {
                if (count($row) < 1) {
                    $skippedCount++;
                    continue;
                }

                $headerCount = count($header);
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                }
                if ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                $rowData = array_combine($header, $row);
                $sku = trim($rowData['sku'] ?? '');
                if ($sku === '' || ! ProductMaster::where('sku', $sku)->whereNull('deleted_at')->exists()) {
                    $skippedCount++;
                    continue;
                }

                $status = AmazonDataView::where('sku', $sku)->first();
                $existing = $status ? (is_array($status->value) ? $status->value : (json_decode($status->value, true) ?: [])) : [];

                // Optional: import NRL into AmazonDataView (REQ/NR → RL/NRL)
                if (array_key_exists('nr_req', $rowData) && trim((string) $rowData['nr_req']) !== '') {
                    $nr = strtoupper(trim((string) $rowData['nr_req']));
                    $existing['NRL'] = in_array($nr, ['NR', 'NRL'], true) ? 'NRL' : 'RL';
                }

                if (array_key_exists('listed', $rowData) && trim((string) $rowData['listed']) !== '') {
                    $listed = trim((string) $rowData['listed']);
                    $existing['Listed'] = (strcasecmp($listed, 'Listed') === 0);
                    $existing['listed'] = $listed;
                }

                AmazonDataView::updateOrCreate(['sku' => $sku], ['value' => $existing]);
                $processedCount++;
            }

            return response()->json([
                'success' => 'CSV imported successfully',
                'processed' => $processedCount,
                'skipped' => $skippedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_amazon_' . date('Y-m-d') . '.csv"',
        ];

        // Export ASIN-derived links (same formula as the listing-amazon UI) — not stored manual URLs.
        $columns = ['sku', 'nr_req', 'listed', 'asin', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $productMasters = ProductMaster::whereNull('deleted_at')->get(['sku']);
            $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

            $statusData = AmazonDataView::whereIn('sku', $skus)
                ->get(['sku', 'value'])
                ->mapWithKeys(fn ($row) => [strtoupper(trim((string) $row->sku)) => $row->value]);

            $listingsByNorm = AmazonListingCounts::listingsByNormalizedSku();

            foreach ($productMasters as $product) {
                $sku = (string) $product->sku;
                $skuUpper = strtoupper(trim($sku));
                $raw = $statusData->has($skuUpper) ? $statusData->get($skuUpper) : null;

                $listing = AmazonListingCounts::pickListingForProductSku($sku, $listingsByNorm);
                $asin = AmazonListingCounts::asinFromApi($listing);
                $buyerLink = $asin !== '' ? ('https://www.amazon.com/dp/' . $asin) : '';
                $sellerLink = $asin !== ''
                    ? ('https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin=' . $asin)
                    : '';

                fputcsv($file, [
                    'sku' => $sku,
                    'nr_req' => AmazonListingCounts::nrReqFromDataView($raw),
                    'listed' => AmazonListingCounts::isListedFromApi($listing) ? 'Listed' : 'Pending',
                    'asin' => $asin,
                    'buyer_link' => $buyerLink,
                    'seller_link' => $sellerLink,
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Fetch and update links from Amazon API for a specific SKU or all SKUs
     */
    public function fetchAndUpdateLinks(Request $request)
    {
        $sku = $request->input('sku');
        $updateAll = $request->input('update_all', false);

        try {
            if ($updateAll) {
                // Fetch links for all SKUs
                $skus = ProductMaster::whereNull('deleted_at')
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->where('sku', 'NOT LIKE', '%PARENT%')
                    ->pluck('sku')
                    ->unique()
                    ->values();

                $updated = 0;
                $failed = 0;

                foreach ($skus as $currentSku) {
                    $result = $this->fetchLinksForSku($currentSku);
                    if ($result['success']) {
                        $updated++;
                    } else {
                        $failed++;
                    }
                    // Small delay to avoid rate limiting
                    usleep(100000); // 100ms
                }

                return response()->json([
                    'status' => 'success',
                    'message' => "Updated {$updated} SKUs, {$failed} failed",
                    'updated' => $updated,
                    'failed' => $failed
                ]);
            } else {
                // Fetch links for a single SKU
                if (!$sku) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'SKU is required'
                    ], 400);
                }

                $result = $this->fetchLinksForSku($sku);
                
                if ($result['success']) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Links updated successfully',
                        'data' => $result['data']
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $result['message']
                    ], 400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error fetching Amazon links', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch links for a specific SKU from Amazon API
     */
    private function fetchLinksForSku($sku)
    {
        try {
            $sellerId = config('services.amazon_sp.seller_id');
            $marketplaceId = config('services.amazon_sp.marketplace_id');
            $endpoint = config('services.amazon_sp.endpoint');

            if (!$sellerId) {
                return [
                    'success' => false,
                    'message' => 'Amazon Seller ID not configured'
                ];
            }

            // Get access token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to get Amazon access token'
                ];
            }

            // Try to find the correct SKU format in Amazon
            $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken, $sellerId, $endpoint, $marketplaceId);
            if (!$amazonSku) {
                // SKU not found in Amazon - clear existing links
                $this->clearLinksForSku($sku);
                // Also clear listing status from amazon_datsheets
                AmazonDatasheet::where('sku', $sku)->update(['listing_status' => null]);
                
                return [
                    'success' => false,
                    'message' => 'SKU not found in Amazon listings - Links cleared'
                ];
            }

            // Fetch listing data from Amazon API
            $encodedSku = rawurlencode($amazonSku);
            $url = "{$endpoint}/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds={$marketplaceId}";

            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            if (!$response->successful()) {
                // If 404, SKU doesn't exist - clear links
                if ($response->status() === 404) {
                    $this->clearLinksForSku($sku);
                    AmazonDatasheet::where('sku', $sku)->update(['listing_status' => null]);
                    return [
                        'success' => false,
                        'message' => 'SKU not found in Amazon (404) - Links cleared'
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Failed to fetch listing data from Amazon API'
                ];
            }

            $data = $response->json();
            $asin = null;

            // Extract ASIN from response
            if (isset($data['summaries'][0]['asin'])) {
                $asin = $data['summaries'][0]['asin'];
            } elseif (isset($data['attributes']['identifiers'][0]['marketplace_asin']['asin'])) {
                $asin = $data['attributes']['identifiers'][0]['marketplace_asin']['asin'];
            }

            // If ASIN not in API response, try to get from amazon_datsheets
            if (!$asin) {
                $amazonSheet = AmazonDatasheet::where('sku', $sku)->first();
                if ($amazonSheet && $amazonSheet->asin) {
                    $asin = $amazonSheet->asin;
                }
            }

            $buyerLink = null;
            $sellerLink = null;

            // Generate buyer link from ASIN
            if ($asin) {
                $buyerLink = "https://www.amazon.com/dp/{$asin}";
                
                // Generate seller link (Seller Central format)
                // Note: This is a generic format. Actual seller link might need to be constructed differently
                // based on your seller central setup
                $sellerLink = "https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin={$asin}";
            }

            // Update links in amazon_data_view and listing status
            if ($buyerLink || $sellerLink) {
                $status = AmazonDataView::where('sku', $sku)->first();
                $existing = $status ? $status->value : [];
                if (!is_array($existing)) {
                    $existing = json_decode($existing, true) ?? [];
                }

                if ($buyerLink) {
                    $existing['buyer_link'] = $buyerLink;
                }
                if ($sellerLink) {
                    $existing['seller_link'] = $sellerLink;
                }

                AmazonDataView::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => $existing]
                );
                
                // Update listing status in amazon_datsheets - check if listing is ACTIVE
                $listingStatus = $this->determineListingStatusFromResponse($data);
                if ($listingStatus) {
                    AmazonDatasheet::updateOrCreate(
                        ['sku' => $sku],
                        ['listing_status' => $listingStatus]
                    );
                }

                return [
                    'success' => true,
                    'message' => 'Links updated successfully',
                    'data' => [
                        'sku' => $sku,
                        'asin' => $asin,
                        'buyer_link' => $buyerLink,
                        'seller_link' => $sellerLink,
                        'listing_status' => $listingStatus
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Could not generate links - ASIN not found'
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching links for SKU', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Determine listing status from Amazon API response
     */
    private function determineListingStatusFromResponse($data)
    {
        if (!is_array($data)) {
            return null;
        }

        // Method 1: Check summaries array (most common location)
        if (isset($data['summaries']) && is_array($data['summaries']) && !empty($data['summaries'])) {
            foreach ($data['summaries'] as $summary) {
                // Check if status is an array
                if (isset($summary['status']) && is_array($summary['status']) && !empty($summary['status'])) {
                    foreach ($summary['status'] as $statusItem) {
                        // Prioritize BUYABLE status
                        if (strtoupper($statusItem) === 'BUYABLE' || strtoupper($statusItem) === 'BUYABLE_BY_QUANTITY') {
                            return 'ACTIVE';
                        }
                    }
                    // If no BUYABLE found, use first status
                    $statusValue = $summary['status'][0];
                    return $this->mapStatusValue($statusValue);
                }
                // Check if status is a string
                elseif (isset($summary['status']) && is_string($summary['status'])) {
                    return $this->mapStatusValue($summary['status']);
                }
            }
        }
        
        // Method 2: Check for buyBoxEligible or other indicators of active status
        if (isset($data['buyBoxEligible']) && $data['buyBoxEligible'] === true) {
            return 'ACTIVE';
        }
        
        if (isset($data['summaries']) && is_array($data['summaries'])) {
            foreach ($data['summaries'] as $summary) {
                if (isset($summary['buyBoxEligible']) && $summary['buyBoxEligible'] === true) {
                    return 'ACTIVE';
                }
                // Check for availability
                if (isset($summary['availability']) && 
                    (stripos($summary['availability'], 'in stock') !== false || 
                     stripos($summary['availability'], 'available') !== false)) {
                    return 'ACTIVE';
                }
            }
        }
        
        // If we found summaries data but no clear status, assume ACTIVE if we have ASIN
        if (isset($data['summaries'][0]['asin'])) {
            return 'ACTIVE';
        }
        
        return null;
    }

    /**
     * Map Amazon status value to our status format
     */
    private function mapStatusValue($statusValue)
    {
        if (!$statusValue) {
            return null;
        }
        
        $statusValue = strtoupper(trim($statusValue));
        
        // Active statuses
        if (in_array($statusValue, ['BUYABLE', 'BUYABLE_BY_QUANTITY', 'ACTIVE', 'LIVE', 'PUBLISHED'])) {
            return 'ACTIVE';
        }
        
        // Inactive statuses
        if (in_array($statusValue, ['DISCOVERABLE', 'INELIGIBLE', 'INVALID', 'OUT_OF_STOCK', 'UNBUYABLE', 'INACTIVE', 'SUPPRESSED', 'STOPPED'])) {
            return 'INACTIVE';
        }
        
        // Incomplete statuses
        if (in_array($statusValue, ['INCOMPLETE', 'DRAFT', 'PENDING'])) {
            return 'INCOMPLETE';
        }
        
        // Default to ACTIVE if we have a status value
        return 'ACTIVE';
    }

    /**
     * Find the correct SKU format in Amazon (helper method)
     */
    private function findAmazonSkuFormat($sku, $accessToken, $sellerId, $endpoint, $marketplaceId)
    {
        // Try different SKU variations
        $variations = [
            $sku,
            strtoupper($sku),
            strtolower($sku),
            trim($sku),
        ];

        foreach ($variations as $skuVariation) {
            try {
                $encodedSku = rawurlencode($skuVariation);
                $url = "{$endpoint}/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds={$marketplaceId}";

                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['summaries']) && !empty($data['summaries'])) {
                        return $skuVariation;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Get Amazon access token
     */
    private function getAccessToken()
    {
        $clientId = config('services.amazon_sp.client_id');
        $clientSecret = config('services.amazon_sp.client_secret');
        $refreshToken = config('services.amazon_sp.refresh_token');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            Log::error('Missing Amazon SP-API credentials');
            return null;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->asForm()
                ->post('https://api.amazon.com/auth/o2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get Amazon access token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get daily metrics data for chart (Missing & INV>0 count)
     */
    public function getDailyMetrics(Request $request)
    {
        try {
            $days = $request->input('days', 30); // Default to last 30 days
            
            // Get metrics for the specified number of days
            $endDate = Carbon::today();
            $startDate = $endDate->copy()->subDays($days - 1);
            
            $metrics = AmazonListingDailyMetric::whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->get();
            
            // Format data for chart
            $chartData = [];
            foreach ($metrics as $metric) {
                $chartData[] = [
                    'date' => $metric->date->format('Y-m-d'),
                    'count' => $metric->missing_status_inv_count
                ];
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $chartData
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Amazon listing daily metrics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch metrics data'
            ], 500);
        }
    }

}