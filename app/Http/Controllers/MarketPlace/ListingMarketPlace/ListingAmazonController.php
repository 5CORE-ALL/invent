<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ProductStockMapping;
use Illuminate\Http\Request;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingDailyMetric;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ListingAmazonController extends Controller
{
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
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->select('id', 'sku', 'parent', 'Values')
            ->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // Load all data in one go with proper indexing
        $shopifyData = ShopifySku::whereIn('sku', $skus)
            ->select('sku', 'inv', 'quantity', 'image_src')
            ->get()
            ->keyBy('sku');
        
        $statusData = AmazonDataView::whereIn('sku', $skus)
            ->select('sku', 'value')
            ->get()
            ->keyBy('sku');
        
        $listingStatusData = AmazonDatasheet::whereIn('sku', $skus)
            ->select('sku', 'listing_status')
            ->get()
            ->keyBy('sku');
        
        // Load NR values from AmazonListingStatus for fallback (matching amazon-tabulator-view)
        $nrListingStatuses = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        foreach ($productMasters as $item) {
            $childSku = $item->sku;
            
            // Skip SKUs that start with "PARENT"
            if (str_starts_with(strtoupper(trim($childSku)), 'PARENT')) {
                continue;
            }
            
            // Default values
            $nr_req = 'REQ'; // Default to REQ (shows as RL)
            $nr = null; // NR field for amazon-tabulator-view compatibility
            $listed = null;
            $buyer_link = null;
            $seller_link = null;
            $listing_status = isset($listingStatusData[$childSku]) ? $listingStatusData[$childSku]->listing_status : null;
            
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                if (!is_array($status)) {
                    $status = json_decode($status, true) ?? [];
                }
                
                // Read NRL field - matching amazon-tabulator-view logic exactly
                // "NRL" means NRL (NR), "RL" or "REQ" means RL (REQ)
                $nrlValue = $status['NRL'] ?? null;
                if ($nrlValue === 'NRL') {
                    $nr_req = 'NR';
                    $nr = 'NR'; // For amazon-tabulator-view compatibility
                } else if ($nrlValue === 'REQ') {
                    $nr_req = 'REQ';
                    $nr = 'REQ'; // For amazon-tabulator-view compatibility
                } else if ($nrlValue === 'RL') {
                    // 'RL' is the format saved by saveStatus, map to 'REQ' for display
                    $nr_req = 'REQ';
                    $nr = 'REQ'; // For amazon-tabulator-view compatibility
                } else {
                    // If NRL field is null or any other value, set NR to null (will fallback to AmazonListingStatus)
                    $nr = null;
                }
                
                $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Pending';
                } else {
                    $listed = $listedValue;
                }
                $buyer_link = $status['buyer_link'] ?? null;
                $seller_link = $status['seller_link'] ?? null;
            }
            
            // Fallback to AmazonListingStatus if NR not set from AmazonDataView (matching amazon-tabulator-view)
            if ($nr === null) {
                $listingStatus = $nrListingStatuses->get($childSku);
                if ($listingStatus && $listingStatus->value) {
                    $listingValue = is_array($listingStatus->value) ? $listingStatus->value : json_decode($listingStatus->value, true) ?? [];
                    $nr = $listingValue['nr_req'] ?? null;
                    // Only set links from listing status if not already set from AmazonDataView
                    if ($buyer_link === null) {
                        $buyer_link = $listingValue['buyer_link'] ?? null;
                    }
                    if ($seller_link === null) {
                        $seller_link = $listingValue['seller_link'] ?? null;
                    }
                }
            }
            
            // If still null after fallback, default to REQ
            if ($nr === null) {
                $nr = 'REQ';
                $nr_req = 'REQ';
            }
            
            // Get status from ProductMaster Values field
            $status = null;
            $image_path = null;
            if ($item->Values) {
                $values = is_array($item->Values) ? $item->Values : json_decode($item->Values, true);
                if (is_array($values)) {
                    $status = $values['status'] ?? null;
                    $image_path = $values['image_path'] ?? null;
                }
            }
            
            // Get image from Shopify first, then fallback to local image_path (same as product-master)
            $shopifyImage = isset($shopifyData[$childSku]) ? ($shopifyData[$childSku]->image_src ?? null) : null;
            
            if ($shopifyImage) {
                $image_path = $shopifyImage; // Use Shopify URL
            } elseif ($image_path) {
                $image_path = '/' . ltrim($image_path, '/'); // Use local path, ensure leading slash
            } else {
                $image_path = null;
            }
            
            $row = [
                'id' => $item->id,
                'sku' => $childSku,
                'parent' => $item->parent,
                'INV' => $shopifyData[$childSku]->inv ?? 0,
                'L30' => $shopifyData[$childSku]->quantity ?? 0,
                'nr_req' => $nr_req,
                'NR' => $nr, // Add NR field for amazon-tabulator-view compatibility
                'listed' => $listed,
                'buyer_link' => $buyer_link,
                'seller_link' => $seller_link,
                'listing_status' => $listing_status,
                'status' => $status, // Status from ProductMaster
                'image_path' => $image_path // Image path (Shopify first, then local)
            ];
            
            $processedData[] = $row;
        }

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
            'status' => 'nullable|string|in:Active,DC,2BDC,Sourcing,In Transit,To Order,MFRG,',
        ]);

        $sku = $validated['sku'];
        $status = AmazonDataView::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

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

        // Only update other fields that are present in the request
        $fields = ['buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

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
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonListed = ProductStockMapping::pluck('inventory_amazon_product', 'sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            
            // Skip SKUs that start with "PARENT"
            if (str_starts_with(strtoupper($sku), 'PARENT')) {
                continue;
            }
            
            $inv = $shopifyData[$sku]->inv ?? 0;

            if (floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $mappingValue = $amazonListed[$sku] ?? null;
            $listed = null;
            if ($mappingValue !== null) {
                $normalized = strtolower(trim($mappingValue));
                $listed = $normalized !== '' && $normalized !== 'not listed' ? 'Listed' : 'Not Listed';
            } else {
                // Check both 'Listed' (boolean) and 'listed' (string) fields
                $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Not Listed';
                } else {
                    $listed = $listedValue;
                }
            }

            // Read NRL field from amazon_data_view - "RL" means RL, "NRL" means NRL (synced with other Amazon pages)
            $nrlValue = $status['NRL'] ?? 'RL';
            // Support both 'RL' (new format) and 'REQ' (old format) for backward compatibility
            $nrReq = ($nrlValue === 'NRL') ? 'NR' : 'REQ';
            
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            if ($listed === 'Listed') {
                $listedCount++;
            }

            // Count as pending if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        Log::info("Amazon getNrReqCount: " . json_encode([
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ]));

        return [
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file));
        // $header = array_map('trim', $rows[0]); // first row = header
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
        }, $rows[0]);

        unset($rows[0]);

        $allowedHeaders = ['sku','listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (!in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue; // skip empty
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');

            if (!$sku) {
                continue;
            }

            // Only import SKUs that exist in product_masters
            if (!ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = AmazonDataView::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            AmazonDataView::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );
        }

        return response()->json(['success' => 'CSV imported successfully']);
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="amazon_listing_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = AmazonDataView::where('sku', $sku)->first();

                $row = [
                    'sku'         => $sku,
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
            $sellerId = env('AMAZON_SELLER_ID');
            $marketplaceId = env('SPAPI_MARKETPLACE_ID', 'ATVPDKIKX0DER');
            $endpoint = env('SPAPI_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com');

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
        $clientId = env('SPAPI_CLIENT_ID');
        $clientSecret = env('SPAPI_CLIENT_SECRET');
        $refreshToken = env('SPAPI_REFRESH_TOKEN');

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