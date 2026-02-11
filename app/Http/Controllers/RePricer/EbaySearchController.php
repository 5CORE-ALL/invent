<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\EbayCompetitorItem;
use App\Models\EbaySkuCompetitor;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbaySearchController extends Controller
{
    /**
     * Search eBay for competitor items using SerpApi
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'marketplace' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $searchQuery = $request->input('query');
        $marketplace = $request->input('marketplace', 'ebay');
        
        // Hardcoded API key (same as Amazon)
        $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
        
        if (!$serpApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'SerpApi key not configured'
            ], 500);
        }

        $collectedItemIds = [];
        $maxPages = 10; // Increased from 5 to 10 for more comprehensive results

        try {
            // Fetch up to 5 pages of results
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = Http::timeout(30)->get('https://serpapi.com/search', [
                    'engine' => 'ebay',
                    'ebay_domain' => 'ebay.com',
                    '_nkw' => $searchQuery,  // eBay uses _nkw parameter for search
                    '_pgn' => $page,  // eBay pagination
                    'api_key' => $serpApiKey,
                ]);

                if (!$response->successful()) {
                    // Log the error response for debugging
                    Log::error('SerpApi eBay Error Response', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'SerpApi request failed',
                        'error' => 'HTTP Status: ' . $response->status(),
                        'details' => $response->json()
                    ], 500);
                }

                $data = $response->json();
                
                // Check if there are organic results
                if (!isset($data['organic_results']) || empty($data['organic_results'])) {
                    break;
                }

                $organicResults = $data['organic_results'];
                
                foreach ($organicResults as $index => $result) {
                    $itemId = $result['item_id'] ?? $result['epid'] ?? null;
                    
                    if (!$itemId) {
                        continue;
                    }

                    // De-duplicate item IDs
                    if (in_array($itemId, $collectedItemIds)) {
                        continue;
                    }

                    $collectedItemIds[] = $itemId;

                    // Check if item ID already exists for this search query
                    $existing = EbayCompetitorItem::where('search_query', $searchQuery)
                        ->where('item_id', $itemId)
                        ->first();

                    // Skip if already exists
                    if ($existing) {
                        continue;
                    }

                    // Extract price
                    $price = null;
                    if (isset($result['price']['value'])) {
                        $price = $result['price']['value'];
                    } elseif (isset($result['price']['raw'])) {
                        // Try to extract numeric value from raw price string
                        $priceString = $result['price']['raw'];
                        preg_match('/[\d,.]+/', $priceString, $matches);
                        if (!empty($matches)) {
                            $price = str_replace(',', '', $matches[0]);
                        }
                    } elseif (isset($result['price'])) {
                        // Try to extract numeric value from price
                        $priceString = is_string($result['price']) ? $result['price'] : '';
                        preg_match('/[\d,.]+/', $priceString, $matches);
                        if (!empty($matches)) {
                            $price = str_replace(',', '', $matches[0]);
                        }
                    }

                    // Extract shipping cost
                    $shippingCost = null;
                    if (isset($result['shipping']['value'])) {
                        $shippingCost = $result['shipping']['value'];
                    } elseif (isset($result['shipping'])) {
                        $shippingString = is_string($result['shipping']) ? $result['shipping'] : '';
                        if (stripos($shippingString, 'free') === false) {
                            preg_match('/[\d,.]+/', $shippingString, $matches);
                            if (!empty($matches)) {
                                $shippingCost = str_replace(',', '', $matches[0]);
                            }
                        } else {
                            $shippingCost = 0;
                        }
                    }

                    // Extract condition
                    $condition = $result['condition'] ?? null;

                    // Extract seller info
                    $sellerName = $result['seller']['name'] ?? null;
                    $sellerRating = $result['seller']['rating'] ?? null;

                    // Extract location
                    $location = $result['location'] ?? null;

                    // Extract the actual eBay link from SerpApi
                    $link = $result['link'] ?? null;

                    // Calculate position (page-based)
                    $position = (($page - 1) * 50) + ($index + 1);

                    // Store in database
                    EbayCompetitorItem::create([
                        'marketplace' => $marketplace,
                        'search_query' => $searchQuery,
                        'item_id' => $itemId,
                        'link' => $link,
                        'title' => $result['title'] ?? null,
                        'price' => $price,
                        'condition' => $condition,
                        'seller_name' => $sellerName,
                        'seller_rating' => $sellerRating,
                        'position' => $position,
                        'image' => $result['thumbnail'] ?? $result['image'] ?? null,
                        'shipping_cost' => $shippingCost,
                        'location' => $location,
                    ]);
                }
            }

            // Retrieve and return all stored results for this search query
            $results = EbayCompetitorItem::where('search_query', $searchQuery)
                ->orderBy('position', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'total_results' => $results->count(),
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('SerpApi eBay Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data from SerpApi',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }

    /**
     * Display the search interface
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('repricer.ebay_search.index');
    }

    /**
     * Get search history
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSearchHistory()
    {
        $searches = EbayCompetitorItem::select('search_query')
            ->groupBy('search_query')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->pluck('search_query');

        return response()->json([
            'success' => true,
            'data' => $searches
        ]);
    }

    /**
     * Get results for a specific search query
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResults(Request $request)
    {
        $searchQuery = $request->input('query');

        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required'
            ], 422);
        }

        $results = EbayCompetitorItem::where('search_query', $searchQuery)
            ->orderBy('position', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'query' => $searchQuery,
            'total_results' => $results->count(),
            'data' => $results
        ]);
    }

    /**
     * Get SKUs for dropdown
     * Fetches from product_master to show all available SKUs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSkus()
    {
        // Get unique SKUs from product_master (excludes PARENT SKUs)
        $skus = ProductMaster::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'NOT LIKE', 'PARENT%')
            ->distinct()
            ->orderBy('sku', 'asc')
            ->pluck('sku');

        return response()->json([
            'success' => true,
            'data' => $skus,
            'total' => $skus->count(),
            'source' => 'product_master'
        ]);
    }

    /**
     * Store selected competitors mapped to SKUs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeCompetitors(Request $request)
    {
        // Support both JSON and form-encoded requests
        $input = $request->all();
        if (empty($input['competitors']) && $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $validator = Validator::make($input, [
            'competitors' => 'required|array',
            'competitors.*.item_id' => 'required',
            'competitors.*.sku' => 'required|string',
            'competitors.*.marketplace' => 'nullable|string',
            'competitors.*.product_title' => 'nullable|string',
            'competitors.*.product_link' => 'nullable|string',
            'competitors.*.image' => 'nullable|string',
            'competitors.*.price' => 'nullable|numeric',
            'competitors.*.shipping_cost' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            Log::warning('EbaySearchController storeCompetitors validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input_keys' => array_keys($input),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $competitors = $input['competitors'];
        $created = 0;
        $updated = 0;

        try {
            foreach ($competitors as $competitor) {
                $price = floatval($competitor['price'] ?? 0);
                $shippingCost = floatval($competitor['shipping_cost'] ?? 0);
                $totalPrice = $price + $shippingCost;
                $sku = trim($competitor['sku']);
                $itemId = (string) ($competitor['item_id'] ?? '');

                if (empty($sku) || empty($itemId)) {
                    Log::warning('EbaySearchController storeCompetitors: skipping empty sku/item_id', [
                        'competitor' => $competitor,
                    ]);
                    continue;
                }

                $result = EbaySkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'item_id' => $itemId,
                    ],
                    [
                        'marketplace' => $competitor['marketplace'] ?? 'ebay',
                        'product_title' => $competitor['product_title'] ?? null,
                        'product_link' => $competitor['product_link'] ?? null,
                        'image' => $competitor['image'] ?? null,
                        'price' => $price,
                        'shipping_cost' => $shippingCost,
                        'total_price' => $totalPrice,
                    ]
                );

                if ($result->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Created {$created} new mappings, updated {$updated} existing mappings",
                'created' => $created,
                'updated' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('Store eBay Competitors Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error storing competitor mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
