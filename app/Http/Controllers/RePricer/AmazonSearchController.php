<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\AmazonCompetitorAsin;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use App\Models\FbaTable;
use App\Models\SerpApiRawResponse;
use App\Models\AmazonSearchRawResponse;
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
                $requestParams = [
                    'engine' => 'amazon',
                    'amazon_domain' => 'amazon.com',
                    'k' => $searchQuery,
                    'page' => $page,
                    'api_key' => $serpApiKey,
                ];
                $response = Http::timeout(30)->get('https://serpapi.com/search', $requestParams);

                // Store one row per page to avoid exceeding MySQL max_allowed_packet
                AmazonSearchRawResponse::create([
                    'search_query' => $searchQuery,
                    'marketplace' => $marketplace,
                    'page' => $page,
                    'raw_response' => $response->body(),
                    'pages_count' => $maxPages,
                ]);

                // Store raw response in serp_api_raw_responses (every request, success or failure)
                $paramsForStorage = $requestParams;
                if (isset($paramsForStorage['api_key'])) {
                    $paramsForStorage['api_key'] = '(stored)';
                }
                SerpApiRawResponse::create([
                    'search_query' => $searchQuery,
                    'page' => $page,
                    'marketplace' => $marketplace,
                    'request_params' => $paramsForStorage,
                    'http_status' => $response->status(),
                    'raw_body' => $response->body(),
                    'success' => $response->successful(),
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

                    // Check if ASIN already exists for this search query (we'll update it to backfill rating, reviews, extracted_old_price, delivery)
                    $existing = AmazonCompetitorAsin::where('search_query', $searchQuery)
                        ->where('asin', $asin)
                        ->first();

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

                    // Extract reviews count (SerpApi can use reviews, reviews_count, or ratings_total)
                    $reviews = $result['reviews'] ?? $result['reviews_count'] ?? $result['ratings_total'] ?? null;
                    if ($reviews !== null) {
                        $reviews = is_numeric($reviews) ? (int) $reviews : null;
                    }

                    // Extract extracted_old_price (try multiple keys) and delivery
                    $extractedOldPrice = null;
                    if (isset($result['extracted_old_price']) && is_numeric($result['extracted_old_price'])) {
                        $extractedOldPrice = (float) $result['extracted_old_price'];
                    } elseif (isset($result['old_price']) && is_string($result['old_price'])) {
                        preg_match('/[\d,.]+/', $result['old_price'], $m);
                        if (!empty($m)) {
                            $extractedOldPrice = (float) str_replace(',', '', $m[0]);
                        }
                    }
                    $delivery = null;
                    if (isset($result['delivery']) && is_array($result['delivery'])) {
                        $delivery = array_values(array_filter(array_map('strval', $result['delivery'])));
                    }

                    // Calculate position (page-based)
                    $position = (($page - 1) * 20) + ($index + 1);

                    $title = $result['title'] ?? null;
                    $payload = [
                        'marketplace' => $marketplace,
                        'search_query' => $searchQuery,
                        'asin' => $asin,
                        'title' => $title,
                        'seller_name' => $this->extractSellerFromTitle($title),
                        'price' => $price,
                        'rating' => $rating,
                        'reviews' => $reviews,
                        'position' => $position,
                        'image' => $result['thumbnail'] ?? $result['image'] ?? null,
                        'extracted_old_price' => $extractedOldPrice,
                        'delivery' => $delivery ?: null,
                    ];

                    if ($existing) {
                        // Update existing row so rating, reviews, extracted_old_price, delivery get backfilled
                        $existing->update($payload);
                    } else {
                        AmazonCompetitorAsin::create($payload);
                    }
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

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Amazon Search DB Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $hint = (str_contains($e->getMessage(), 'Base table or view not found'))
                ? ' Run: php artisan migrate'
                : '';
            return response()->json([
                'success' => false,
                'message' => 'Database error (missing table or view).' . $hint,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
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
     * Extract old/list price from SerpApi Amazon Product API response (product_results + purchase_options).
     * Tries extracted_old_price, old_price, and derive from discount % when available.
     *
     * @param array $data Full API response
     * @param array $pr product_results
     * @return float|null
     */
    private function extractOldPriceFromProductResponse(array $data, array $pr): ?float
    {
        if (isset($pr['extracted_old_price']) && (is_numeric($pr['extracted_old_price']) || (is_string($pr['extracted_old_price']) && preg_match('/^[\d.]+$/', $pr['extracted_old_price'])))) {
            return (float) $pr['extracted_old_price'];
        }
        if (!empty($pr['old_price']) && is_string($pr['old_price'])) {
            if (preg_match('/[\d,.]+/', $pr['old_price'], $m)) {
                return (float) str_replace(',', '', $m[0]);
            }
        }
        $buyNew = $data['purchase_options']['buy_new'] ?? null;
        if (is_array($buyNew)) {
            if (isset($buyNew['extracted_old_price']) && is_numeric($buyNew['extracted_old_price'])) {
                return (float) $buyNew['extracted_old_price'];
            }
            if (!empty($buyNew['old_price']) && is_string($buyNew['old_price']) && preg_match('/[\d,.]+/', $buyNew['old_price'], $m)) {
                return (float) str_replace(',', '', $m[0]);
            }
        }
        $currentPrice = isset($pr['extracted_price']) ? (float) $pr['extracted_price'] : null;
        if ($currentPrice === null && !empty($pr['price']) && is_string($pr['price']) && preg_match('/[\d,.]+/', $pr['price'], $m)) {
            $currentPrice = (float) str_replace(',', '', $m[0]);
        }
        if ($currentPrice !== null && $currentPrice > 0 && !empty($pr['discount']) && is_string($pr['discount']) && preg_match('/-\s*(\d+)\s*%/', $pr['discount'], $m)) {
            $pct = (int) $m[1];
            if ($pct > 0 && $pct < 100) {
                return round($currentPrice / (1 - $pct / 100), 2);
            }
        }
        return null;
    }

    /**
     * Extract seller name from product title (e.g. "Product by Seller", "Product - Seller", "Product (Seller)")
     *
     * @param string|null $title
     * @return string|null
     */
    private function extractSellerFromTitle(?string $title): ?string
    {
        if ($title === null || trim($title) === '') {
            return null;
        }
        $title = trim($title);
        $patterns = [
            '/\s+by\s+([^\-|(]+)$/i',           // "Product by Seller Name" at end
            '/\s+-\s+([^\-|(]+)$/u',             // "Product - Seller Name" at end
            '/\s*[|]\s*([^\-|(]+)$/u',           // "Product | Seller Name" at end
            '/\s*\(\s*([^)]+)\)\s*$/u',          // "Product (Seller Name)" at end
            '/Sold\s+by\s+([^\.\-|(]+)/i',       // "Sold by Seller Name"
            '/from\s+([^\.\-|(]+?)(?:\s*[\.\-|]|$)/i', // "from Seller Name"
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $m)) {
                $seller = trim($m[1]);
                $seller = preg_replace('/\s+/', ' ', $seller);
                if (strlen($seller) >= 2 && strlen($seller) <= 255 && !preg_match('/^\d+$/', $seller)) {
                    return $seller;
                }
            }
        }
        return null;
    }

    /**
     * Backfill rating, reviews, old price, delivery for existing amazon_sku_competitors rows (by ASIN).
     * Uses SerpApi Amazon Product API (engine=amazon_product) so we don't need to re-add SKUs.
     *
     * @param Request $request limit (optional), asin (optional single ASIN)
     * @return \Illuminate\Http\JsonResponse
     */
    public function backfillSkuCompetitorsByAsin(Request $request)
    {
        $limit = (int) $request->input('limit', 50);
        $limit = min(max(1, $limit), 200);
        $singleAsin = $request->input('asin');

        $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
        if (!$serpApiKey) {
            return response()->json(['success' => false, 'message' => 'SerpApi key not configured'], 500);
        }

        $baseQuery = AmazonSkuCompetitor::where(function ($q) {
            $q->whereNull('rating')->orWhereNull('reviews');
        });
        if ($singleAsin) {
            $baseQuery->where('asin', trim($singleAsin));
        }
        $asins = $baseQuery->select('asin')->distinct()->orderBy('asin')->limit($limit)->pluck('asin');

        if ($asins->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No SKU competitor rows with missing rating/reviews found.',
                'updated' => 0,
                'asins_processed' => 0,
                'asins_no_data' => [],
                'total_asins_needing_backfill' => 0,
            ]);
        }

        $totalNeeding = AmazonSkuCompetitor::where(function ($q) {
            $q->whereNull('rating')->orWhereNull('reviews');
        })->select('asin')->distinct()->count();

        $updated = 0;
        $asinsProcessed = 0;
        $asinsNoData = [];
        $errors = [];

        foreach ($asins as $asin) {
            try {
                $response = Http::timeout(25)->get('https://serpapi.com/search', [
                    'engine' => 'amazon_product',
                    'amazon_domain' => 'amazon.com',
                    'asin' => $asin,
                    'api_key' => $serpApiKey,
                ]);
                if (!$response->successful()) {
                    $errors[] = "ASIN {$asin}: HTTP " . $response->status();
                    $asinsNoData[] = $asin;
                    continue;
                }
                $data = $response->json();
                $pr = $data['product_results'] ?? null;
                if (!$pr) {
                    $errors[] = "ASIN {$asin}: no product_results";
                    $asinsNoData[] = $asin;
                    continue;
                }
                $rating = isset($pr['rating']) ? (is_numeric($pr['rating']) ? (float) $pr['rating'] : null) : null;
                $reviews = isset($pr['reviews']) ? (is_numeric($pr['reviews']) ? (int) $pr['reviews'] : null) : null;
                $price = isset($pr['extracted_price']) ? (float) $pr['extracted_price'] : null;
                if ($price === null && isset($pr['price']) && is_string($pr['price'])) {
                    preg_match('/[\d,.]+/', $pr['price'], $m);
                    if (!empty($m)) {
                        $price = (float) str_replace(',', '', $m[0]);
                    }
                }
                $extractedOldPrice = $this->extractOldPriceFromProductResponse($data, $pr);
                $delivery = isset($pr['delivery']) && is_array($pr['delivery'])
                    ? array_values(array_filter(array_map('strval', $pr['delivery'])))
                    : null;
                $title = $pr['title'] ?? null;
                $thumbnail = $pr['thumbnail'] ?? (isset($pr['thumbnails'][0]) ? $pr['thumbnails'][0] : null);

                $payload = array_filter([
                    'rating' => $rating,
                    'reviews' => $reviews,
                    'extracted_old_price' => $extractedOldPrice,
                    'delivery' => $delivery,
                    'seller_name' => $this->extractSellerFromTitle($title),
                    'product_title' => $title,
                    'image' => $thumbnail,
                    'price' => $price,
                ], function ($v) {
                    return $v !== null && $v !== '';
                });

                if (empty($payload)) {
                    $errors[] = "ASIN {$asin}: no usable data in response";
                    $asinsNoData[] = $asin;
                    continue;
                }

                $count = AmazonSkuCompetitor::where('asin', $asin)->update($payload);
                $updated += $count;
                $asinsProcessed++;
            } catch (\Throwable $e) {
                Log::warning('Backfill SKU competitor by ASIN failed', ['asin' => $asin, 'message' => $e->getMessage()]);
                $errors[] = "ASIN {$asin}: " . $e->getMessage();
                $asinsNoData[] = $asin;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Backfilled {$asinsProcessed} ASIN(s), updated {$updated} row(s). " .
                count($asinsNoData) . " ASIN(s) had no data or failed. Run again with same limit to process more (total needing backfill was {$totalNeeding}).",
            'updated' => $updated,
            'asins_processed' => $asinsProcessed,
            'asins_no_data' => array_values($asinsNoData),
            'total_asins_needing_backfill' => $totalNeeding,
            'errors' => array_slice($errors, 0, 20),
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
     * Get raw API response for a search (so you can see structure and decide what to save)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRawResponse(Request $request)
    {
        $searchQuery = $request->input('query');
        $page = (int) $request->input('page', 1);

        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required'
            ], 422);
        }

        $record = AmazonSearchRawResponse::where('search_query', $searchQuery)
            ->when($page > 0, fn ($q) => $q->where('page', $page))
            ->orderBy('page', 'asc')
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No saved raw response found for this search. Run a search first, then view raw response.'
            ], 404);
        }

        $raw = $record->raw_response;
        $parsed = null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $parsed = $decoded !== null ? $decoded : $raw;
        } else {
            $parsed = $raw;
        }

        return response()->json([
            'success' => true,
            'meta' => [
                'search_query' => $record->search_query,
                'marketplace' => $record->marketplace,
                'page' => $record->page,
                'pages_count' => $record->pages_count,
                'created_at' => $record->created_at?->toDateTimeString(),
            ],
            'response' => $parsed,
        ]);
    }

    /**
     * Get SKUs for dropdown
     * Merges product_master (MFN-style SKUs) with fba_table.seller_sku (FBA listings).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSkus()
    {
        $productSkus = ProductMaster::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'NOT LIKE', 'PARENT%')
            ->distinct()
            ->pluck('sku');

        $fbaSkus = FbaTable::select('seller_sku')
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->where('seller_sku', 'NOT LIKE', 'PARENT%')
            ->distinct()
            ->pluck('seller_sku');

        $skus = $productSkus
            ->merge($fbaSkus)
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $skus,
            'total' => $skus->count(),
            'source' => 'product_master,fba_table',
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
            'competitors.*.rating' => 'nullable|numeric',
            'competitors.*.reviews' => 'nullable|integer',
            'competitors.*.extracted_old_price' => 'nullable|numeric',
            'competitors.*.delivery' => 'nullable|array',
            'competitors.*.delivery.*' => 'nullable|string',
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

                $delivery = $competitor['delivery'] ?? null;
                if (is_string($delivery)) {
                    $decoded = json_decode($delivery, true);
                    $delivery = is_array($decoded) ? $decoded : null;
                }

                $productTitle = $competitor['product_title'] ?? null;
                $marketplace = $competitor['marketplace'] ?? 'amazon';
                $result = AmazonSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'asin' => $asin,
                        'marketplace' => $marketplace,
                    ],
                    [
                        'product_title' => $productTitle,
                        'seller_name' => $this->extractSellerFromTitle($productTitle),
                        'product_link' => $competitor['product_link'] ?? null,
                        'image' => $competitor['image'] ?? null,
                        'price' => $price,
                        'rating' => isset($competitor['rating']) ? (float) $competitor['rating'] : null,
                        'reviews' => isset($competitor['reviews']) ? (int) $competitor['reviews'] : null,
                        'extracted_old_price' => isset($competitor['extracted_old_price']) ? (float) $competitor['extracted_old_price'] : null,
                        'delivery' => $delivery,
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
