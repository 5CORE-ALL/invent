<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\AmazonCompetitorAsin;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmazonSearchController extends Controller
{
    /**
     * Search Amazon for competitor ASINs using SerpApi
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
        $marketplace = $request->input('marketplace', 'amazon');
        $maxPages = $request->input('max_pages', 20);
        
        // Hardcoded API key
        $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
        
        if (!$serpApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'SerpApi key not configured'
            ], 500);
        }

        $collectedAsins = [];
        $categoryInfo = null;

        try {
            // Fetch up to 5 pages of results
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = Http::timeout(30)->get('https://serpapi.com/search', [
                    'engine' => 'amazon',
                    'amazon_domain' => 'amazon.com',
                    'k' => $searchQuery,  // Use 'k' instead of 'q' for Amazon keyword search
                    'page' => $page,
                    'api_key' => $serpApiKey,
                ]);

                if (!$response->successful()) {
                    // Log the error response for debugging
                    Log::error('SerpApi Error Response', [
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
                    $asin = $result['asin'] ?? null;
                    
                    if (!$asin) {
                        continue;
                    }

                    // De-duplicate ASINs
                    if (in_array($asin, $collectedAsins)) {
                        continue;
                    }

                    $collectedAsins[] = $asin;

                    // Check if ASIN already exists for this search query
                    $existing = AmazonCompetitorAsin::where('search_query', $searchQuery)
                        ->where('asin', $asin)
                        ->first();

                    // Skip if already exists
                    if ($existing) {
                        continue;
                    }

                    // Extract price
                    $price = null;
                    if (isset($result['price']['value'])) {
                        $price = $result['price']['value'];
                    } elseif (isset($result['price'])) {
                        // Try to extract numeric value from price string
                        $priceString = is_string($result['price']) ? $result['price'] : '';
                        preg_match('/[\d,.]+/', $priceString, $matches);
                        if (!empty($matches)) {
                            $price = str_replace(',', '', $matches[0]);
                        }
                    }

                    // Extract rating
                    $rating = $result['rating'] ?? null;

                    // Extract reviews count
                    $reviews = $result['reviews_count'] ?? $result['ratings_total'] ?? null;

                    // Calculate position (page-based)
                    $position = (($page - 1) * 20) + ($index + 1);

                    // Store in database
                    AmazonCompetitorAsin::create([
                        'marketplace' => $marketplace,
                        'search_query' => $searchQuery,
                        'asin' => $asin,
                        'title' => $result['title'] ?? null,
                        'price' => $price,
                        'rating' => $rating,
                        'reviews' => $reviews,
                        'position' => $position,
                        'image' => $result['thumbnail'] ?? $result['image'] ?? null,
                    ]);
                }
            }

            // Retrieve and return all stored results for this search query
            $results = AmazonCompetitorAsin::where('search_query', $searchQuery)
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
            Log::error('SerpApi Exception', [
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
        return view('repricer.amazon_search.index');
    }

    /**
     * Get search history
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSearchHistory()
    {
        $searches = AmazonCompetitorAsin::select('search_query')
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
        $query = AmazonCompetitorAsin::where('search_query', $searchQuery);

        // Apply price filters (item price only)
        if ($request->has('min_price') && $request->input('min_price') !== null) {
            $query->where('price', '>=', floatval($request->input('min_price')));
        }

        if ($request->has('max_price') && $request->input('max_price') !== null) {
            $query->where('price', '<=', floatval($request->input('max_price')));
        }

        // Apply rating filter
        if ($request->has('min_rating') && $request->input('min_rating') !== null) {
            $query->where('rating', '>=', floatval($request->input('min_rating')));
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
                $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price ASC, position ASC');
                break;
            case 'price_high_low':
            case 'price_highest':
            case 'highest':
            case 'high_to_low':
                $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price DESC, position ASC');
                break;
            case 'rating_high_low':
                $query->orderByRaw('CASE WHEN rating IS NULL THEN 1 ELSE 0 END, rating DESC, position ASC');
                break;
            case 'rating_low_high':
                $query->orderByRaw('CASE WHEN rating IS NULL THEN 1 ELSE 0 END, rating ASC, position ASC');
                break;
            case 'reviews_high_low':
                $query->orderByRaw('CASE WHEN reviews IS NULL THEN 1 ELSE 0 END, reviews DESC, position ASC');
                break;
            case 'position':
                $query->orderBy('position', $sortOrder);
                break;
            case 'price':
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

        return response()->json([
            'success' => true,
            'query' => $searchQuery,
            'total_results' => $results->count(),
            'filters_applied' => [
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'min_rating' => $request->input('min_rating'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'price_stats' => [
                'min_price' => $priceStats['min_price'],
                'max_price' => $priceStats['max_price'],
                'avg_price' => $priceStats['avg_price'],
            ],
            'data' => $results
        ]);
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
            ];
        }

        $prices = $results->pluck('price')->filter()->values();

        return [
            'min_price' => $prices->min() ?? 0,
            'max_price' => $prices->max() ?? 0,
            'avg_price' => round($prices->avg() ?? 0, 2),
        ];
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

        $results = AmazonCompetitorAsin::where('search_query', $searchQuery)->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'price_range' => ['min' => 0, 'max' => 0],
                    'rating_range' => ['min' => 0, 'max' => 5],
                ]
            ]);
        }

        // Get price ranges (item price only)
        $prices = $results->pluck('price')->filter();
        $ratings = $results->pluck('rating')->filter();

        return response()->json([
            'success' => true,
            'data' => [
                'price_range' => [
                    'min' => $prices->min() ?? 0,
                    'max' => $prices->max() ?? 0,
                ],
                'rating_range' => [
                    'min' => $ratings->min() ?? 0,
                    'max' => $ratings->max() ?? 5,
                ],
            ]
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
            'competitors.*.asin' => 'required|string',
            'competitors.*.sku' => 'required|string',
            'competitors.*.marketplace' => 'nullable|string',
            'competitors.*.product_title' => 'nullable|string',
            'competitors.*.product_link' => 'nullable|string',
            'competitors.*.image' => 'nullable|string',
            'competitors.*.price' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            Log::warning('AmazonSearchController storeCompetitors validation failed', [
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
                $sku = trim($competitor['sku']);
                $asin = trim($competitor['asin']);

                if (empty($sku) || empty($asin)) {
                    Log::warning('AmazonSearchController storeCompetitors: skipping empty sku/asin', [
                        'competitor' => $competitor,
                    ]);
                    continue;
                }

                $result = AmazonSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'asin' => $asin,
                    ],
                    [
                        'marketplace' => $competitor['marketplace'] ?? 'amazon',
                        'product_title' => $competitor['product_title'] ?? null,
                        'product_link' => $competitor['product_link'] ?? null,
                        'image' => $competitor['image'] ?? null,
                        'price' => $price,
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
            
            Log::error('Store Amazon Competitors Error', [
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
