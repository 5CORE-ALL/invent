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
            'max_pages' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $searchQuery = $request->input('query');
        $marketplace = $request->input('marketplace', 'ebay');
        $maxPages = $request->input('max_pages', 20); // Allow fetching more pages
        
        // Hardcoded API key (same as Amazon)
        $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
        
        if (!$serpApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'SerpApi key not configured'
            ], 500);
        }

        $collectedItemIds = [];
        $categoryInfo = null;

        try {
            // Fetch multiple pages of results
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = Http::timeout(30)->get('https://serpapi.com/search', [
                    'engine' => 'ebay',
                    'ebay_domain' => 'ebay.com',
                    '_nkw' => $searchQuery,  // eBay uses _nkw parameter for search
                    '_pgn' => $page,  // eBay pagination
                    'api_key' => $serpApiKey,
                ]);

                if (!$response->successful()) {
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
                
                // Extract category information from first page
                if ($page === 1 && isset($data['filters'])) {
                    $categoryInfo = $this->extractCategoryInfo($data['filters']);
                }
                
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
                        $priceString = $result['price']['raw'];
                        preg_match('/[\d,.]+/', $priceString, $matches);
                        if (!empty($matches)) {
                            $price = str_replace(',', '', $matches[0]);
                        }
                    } elseif (isset($result['price'])) {
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

                    // Calculate total price
                    $totalPrice = ($price ?? 0) + ($shippingCost ?? 0);

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

            // Retrieve all stored results for this search query
            $results = EbayCompetitorItem::where('search_query', $searchQuery)
                ->orderBy('position', 'asc')
                ->get();

            // Calculate price statistics
            $priceStats = $this->calculatePriceStats($results);

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'total_results' => $results->count(),
                'category_info' => $categoryInfo,
                'price_stats' => [
                    'min_price' => $priceStats['min_price'],
                    'max_price' => $priceStats['max_price'],
                    'avg_price' => $priceStats['avg_price'],
                ],
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
     * Extract category information from eBay filters
     *
     * @param array $filters
     * @return array|null
     */
    private function extractCategoryInfo($filters)
    {
        foreach ($filters as $filter) {
            if (isset($filter['name']) && strtolower($filter['name']) === 'category') {
                return [
                    'categories' => $filter['values'] ?? [],
                ];
            }
        }
        return null;
    }

    /**
     * Calculate price statistics from results
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    private function calculatePriceStats($results)
    {
        if ($results->isEmpty()) {
            return [
                'min_price' => 0,
                'max_price' => 0,
                'avg_price' => 0,
                'min_total_price' => 0,
                'max_total_price' => 0,
                'avg_total_price' => 0,
            ];
        }

        $prices = $results->pluck('price')->filter()->values();
        $totalPrices = $results->map(function ($item) {
            return ($item->price ?? 0) + ($item->shipping_cost ?? 0);
        })->filter()->values();

        return [
            'min_price' => $prices->min() ?? 0,
            'max_price' => $prices->max() ?? 0,
            'avg_price' => round($prices->avg() ?? 0, 2),
            'min_total_price' => $totalPrices->min() ?? 0,
            'max_total_price' => $totalPrices->max() ?? 0,
            'avg_total_price' => round($totalPrices->avg() ?? 0, 2),
        ];
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
     * Get results for a specific search query with filtering and sorting
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

        // Start building the query
        $query = EbayCompetitorItem::where('search_query', $searchQuery);

        // Apply price filters (item price only)
        if ($request->has('min_price') && $request->input('min_price') !== null) {
            $query->where('price', '>=', floatval($request->input('min_price')));
        }

        if ($request->has('max_price') && $request->input('max_price') !== null) {
            $query->where('price', '<=', floatval($request->input('max_price')));
        }

        // Apply condition filter
        if ($request->has('condition') && $request->input('condition') !== null) {
            $query->where('condition', $request->input('condition'));
        }

        // Apply seller name filter
        if ($request->has('seller_name') && $request->input('seller_name') !== null) {
            $query->where('seller_name', 'like', '%' . $request->input('seller_name') . '%');
        }

        // Apply location filter
        if ($request->has('location') && $request->input('location') !== null) {
            $query->where('location', 'like', '%' . $request->input('location') . '%');
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'position');
        $sortOrder = $request->input('sort_order', 'asc');

        // Validate sort order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'asc';
        }

        // Apply sorting based on sort_by parameter
        switch ($sortBy) {
            case 'price_low_high':
            case 'price_lowest':
            case 'lowest':
            case 'low_to_high':
                // Sort by price ascending, nulls last, then by position
                $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price ASC, position ASC');
                break;
            case 'price_high_low':
            case 'price_highest':
            case 'highest':
            case 'high_to_low':
                // Sort by price descending, nulls last, then by position
                $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price DESC, position ASC');
                break;
            case 'position':
                $query->orderBy('position', $sortOrder);
                break;
            case 'seller_rating':
                $query->orderBy('seller_rating', $sortOrder)->orderBy('position', 'asc');
                break;
            case 'condition':
                $query->orderBy('condition', $sortOrder)->orderBy('position', 'asc');
                break;
            case 'price':
                // Generic price sort using sort_order parameter
                if ($sortOrder === 'asc') {
                    $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price ASC, position ASC');
                } else {
                    $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price DESC, position ASC');
                }
                break;
            default:
                $query->orderBy('position', 'asc');
                break;
        }

        // Get all results (no pagination limit)
        $results = $query->get();

        // Calculate price statistics (item price only)
        $priceStats = $this->calculatePriceStats($results);

        // Get unique conditions for filtering
        $conditions = EbayCompetitorItem::where('search_query', $searchQuery)
            ->whereNotNull('condition')
            ->distinct()
            ->pluck('condition');

        return response()->json([
            'success' => true,
            'query' => $searchQuery,
            'total_results' => $results->count(),
            'filters_applied' => [
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'condition' => $request->input('condition'),
                'seller_name' => $request->input('seller_name'),
                'location' => $request->input('location'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'available_conditions' => $conditions,
            'price_stats' => [
                'min_price' => $priceStats['min_price'],
                'max_price' => $priceStats['max_price'],
                'avg_price' => $priceStats['avg_price'],
            ],
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
     * Get filter options for a search query
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions(Request $request)
    {
        $searchQuery = $request->input('query');

        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required'
            ], 422);
        }

        $results = EbayCompetitorItem::where('search_query', $searchQuery)->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'conditions' => [],
                    'locations' => [],
                    'price_range' => ['min' => 0, 'max' => 0],
                ]
            ]);
        }

        // Get unique conditions
        $conditions = $results->pluck('condition')
            ->filter()
            ->unique()
            ->values();

        // Get unique locations
        $locations = $results->pluck('location')
            ->filter()
            ->unique()
            ->values();

        // Get price ranges (item price only)
        $prices = $results->pluck('price')->filter();

        return response()->json([
            'success' => true,
            'data' => [
                'conditions' => $conditions,
                'locations' => $locations,
                'price_range' => [
                    'min' => $prices->min() ?? 0,
                    'max' => $prices->max() ?? 0,
                ],
            ]
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

        DB::beginTransaction();
        
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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Created {$created} new mappings, updated {$updated} existing mappings",
                'created' => $created,
                'updated' => $updated
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Store eBay Competitors Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $competitors ?? []
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error storing competitor mappings',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }
}
