<?php

namespace App\Http\Controllers\Repricer;

use App\Http\Controllers\Controller;
use App\Models\AmazonCompetitorAsin;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonDatasheet;
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $searchQuery = $request->input('query');
        $marketplace = $request->input('marketplace', 'amazon');
        
        // Hardcoded API key
        $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
        
        if (!$serpApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'SerpApi key not configured'
            ], 500);
        }

        $collectedAsins = [];
        $maxPages = 5;

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

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'total_results' => $results->count(),
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

        $results = AmazonCompetitorAsin::where('search_query', $searchQuery)
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
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSkus()
    {
        // Get unique SKUs from amazon_datsheets
        $skus = AmazonDatasheet::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->distinct()
            ->orderBy('sku', 'asc')
            ->limit(1000)
            ->pluck('sku');

        return response()->json([
            'success' => true,
            'data' => $skus
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
        $validator = Validator::make($request->all(), [
            'competitors' => 'required|array',
            'competitors.*.asin' => 'required|string',
            'competitors.*.sku' => 'required|string',
            'competitors.*.marketplace' => 'nullable|string',
            'competitors.*.product_title' => 'nullable|string',
            'competitors.*.product_link' => 'nullable|string',
            'competitors.*.price' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $competitors = $request->input('competitors');
        $created = 0;
        $updated = 0;

        try {
            foreach ($competitors as $competitor) {
                $result = AmazonSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $competitor['sku'],
                        'asin' => $competitor['asin'],
                    ],
                    [
                        'marketplace' => $competitor['marketplace'] ?? 'amazon',
                        'product_title' => $competitor['product_title'] ?? null,
                        'product_link' => $competitor['product_link'] ?? null,
                        'price' => $competitor['price'] ?? null,
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
            Log::error('Store Competitors Error', [
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
