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
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

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
            ->select('id', 'sku', 'parent')
            ->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // Load all data in one go with proper indexing
        $shopifyData = ShopifySku::whereIn('sku', $skus)
            ->select('sku', 'inv', 'quantity')
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
                'listing_status' => $listing_status
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
                return [
                    'success' => false,
                    'message' => 'SKU not found in Amazon listings'
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

            // Update links in amazon_data_view
            if ($buyerLink || $sellerLink) {
                $status = AmazonDataView::where('sku', $sku)->first();
                $existing = $status ? $status->value : [];

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

                return [
                    'success' => true,
                    'message' => 'Links updated successfully',
                    'data' => [
                        'sku' => $sku,
                        'asin' => $asin,
                        'buyer_link' => $buyerLink,
                        'seller_link' => $sellerLink
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

}