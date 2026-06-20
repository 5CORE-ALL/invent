<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\FbaTable;
use App\Models\ProductMaster;
use App\Models\TiktokCompetitorProduct;
use App\Models\TiktokSearchRawResponse;
use App\Models\TiktokSkuCompetitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * TikTok Shop competitor discovery for the repricer.
 *
 * NOTE: SerpApi has no TikTok engine and the official TikTok Shop Partner API
 * is seller-scoped (cannot return competitor listings). This controller talks
 * to an Apify Actor — by default `sentry/tiktok-shop-search-pro` — which
 * scrapes the public TikTok Shop catalog and returns product_id, title,
 * brand, seller, price (min/avg/max), rating, review_count, sold_count, and
 * a stable rank. Swap actors by setting APIFY_TIKTOK_ACTOR_ID.
 */
class TiktokSearchController extends Controller
{
    /** Display the search interface */
    public function index()
    {
        return view('repricer.tiktok_search.index');
    }

    /**
     * Run a TikTok Shop keyword search via Apify, persist normalized rows,
     * and return them. Mirrors AmazonSearchController::search().
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'marketplace' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:8',
            'max_products' => 'nullable|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $searchQuery = $request->input('query');
        $marketplace = $request->input('marketplace', 'tiktok');
        $region = strtoupper((string) $request->input('region', config('services.apify.tiktok.region', 'US')));
        $maxProducts = (int) $request->input('max_products', config('services.apify.tiktok.max_products', 100));

        $token = config('services.apify.token');
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Apify token not configured. Set APIFY_TOKEN in .env (see config/services.php → apify).',
            ], 500);
        }

        try {
            // Apify "run-sync-get-dataset-items" returns the dataset directly
            // when the actor finishes within `timeout` seconds — perfect for
            // a synchronous controller. Actor input shape follows
            // sentry/tiktok-shop-search-pro; override APIFY_TIKTOK_ACTOR_ID
            // to swap providers.
            $actor = config('services.apify.tiktok.actor_id', 'sentry~tiktok-shop-search-pro');
            $baseUrl = rtrim((string) config('services.apify.base_url', 'https://api.apify.com/v2'), '/');
            $timeout = (int) config('services.apify.tiktok.timeout_secs', 180);

            $endpoint = $baseUrl . '/acts/' . rawurlencode($actor) . '/run-sync-get-dataset-items';

            // TikTok Shop search pages return ~12–18 items each; budget extra
            // pages so the query-token relevance filter inside the actor still
            // hits maxResultsPerQuery. Cap at 80 pages so a typo doesn't burn
            // a huge bill.
            $maxPages = max(3, (int) ceil($maxProducts / 12) + 2);
            $maxPages = min($maxPages, 80);

            // Canonical input shape for sentry/tiktok-shop-search-pro
            // (queries / searchRegion / maxPagesPerQuery / maxResultsPerQuery /
            // forceMaxPages). Aliases below kept for forward-compat if the
            // operator swaps APIFY_TIKTOK_ACTOR_ID to another TikTok actor
            // that uses snake_case / camelCase variants.
            $input = [
                'queries' => [$searchQuery],
                'searchRegion' => $region,
                'maxPagesPerQuery' => $maxPages,
                'maxResultsPerQuery' => $maxProducts,
                'forceMaxPages' => true,
                'includeRawProduct' => false,
                'compactNullFields' => true,

                // Aliases recognized by other TikTok Shop actors. Harmless to
                // sentry/tiktok-shop-search-pro (it ignores unknown fields).
                'keywords' => [$searchQuery],
                'search' => $searchQuery,
                'region' => $region,
                'country' => $region,
                'maxResults' => $maxProducts,
                'maxProducts' => $maxProducts,
                'limit' => $maxProducts,
            ];

            // run-sync caps at 300s on Apify; clamp anything bigger.
            $apifyTimeout = min(max(60, $timeout), 300);

            $response = Http::timeout($apifyTimeout + 30)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($endpoint . '?token=' . urlencode($token) . '&timeout=' . $apifyTimeout . '&clean=true&format=json', $input);

            $rawBody = $response->body();
            $items = [];
            if ($response->successful()) {
                $decoded = json_decode($rawBody, true);
                $items = is_array($decoded) ? $decoded : [];
            }

            TiktokSearchRawResponse::create([
                'search_query' => $searchQuery,
                'marketplace' => $marketplace,
                'region' => $region,
                'provider' => 'apify',
                'provider_run_id' => $response->header('X-Apify-Run-Id') ?: null,
                'items_count' => is_array($items) ? count($items) : null,
                'raw_response' => $rawBody,
            ]);

            if (!$response->successful()) {
                Log::error('Apify TikTok Search Error', [
                    'status' => $response->status(),
                    'body' => mb_substr($rawBody, 0, 2000),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Apify request failed',
                    'error' => 'HTTP Status: ' . $response->status(),
                    'details' => json_decode($rawBody, true) ?: $rawBody,
                ], 500);
            }

            $collectedIds = [];
            $position = 0;

            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $position++;

                $normalized = $this->normalizeApifyRow($row, $searchQuery, $marketplace, $region, $position);
                if ($normalized === null) {
                    continue;
                }

                $productId = (string) $normalized['product_id'];
                if ($productId === '' || in_array($productId, $collectedIds, true)) {
                    continue;
                }
                $collectedIds[] = $productId;

                $existing = TiktokCompetitorProduct::where('search_query', $searchQuery)
                    ->where('region', $region)
                    ->where('product_id', $productId)
                    ->first();

                if ($existing) {
                    $existing->update($normalized);
                } else {
                    TiktokCompetitorProduct::create($normalized);
                }
            }

            $results = TiktokCompetitorProduct::where('search_query', $searchQuery)
                ->where('region', $region)
                ->orderBy('position', 'asc')
                ->get();

            $priceStats = $this->calculatePriceStats($results);

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'region' => $region,
                'provider' => 'apify',
                'actor' => $actor,
                'total_results' => $results->count(),
                'price_stats' => [
                    'min_price' => $priceStats['min_price'],
                    'max_price' => $priceStats['max_price'],
                    'avg_price' => $priceStats['avg_price'],
                ],
                'data' => $results,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('TikTok Search DB Exception', [
                'message' => $e->getMessage(),
            ]);
            $hint = str_contains($e->getMessage(), 'Base table or view not found')
                ? ' Run: php artisan migrate'
                : '';
            return response()->json([
                'success' => false,
                'message' => 'Database error (missing table or view).' . $hint,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        } catch (\Throwable $e) {
            Log::error('TikTok Search Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching TikTok data',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    /** Recent searches for the dropdown */
    public function getSearchHistory()
    {
        $searches = TiktokCompetitorProduct::select('search_query')
            ->groupBy('search_query')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(10)
            ->pluck('search_query');

        return response()->json([
            'success' => true,
            'data' => $searches,
        ]);
    }

    /** Filtered / sorted listing for the same search query (no re-fetch) */
    public function getResults(Request $request)
    {
        $searchQuery = $request->input('query');
        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $query = TiktokCompetitorProduct::where('search_query', $searchQuery);

        if ($region = $request->input('region')) {
            $query->where('region', strtoupper((string) $region));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', floatval($request->input('min_price')));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', floatval($request->input('max_price')));
        }
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', floatval($request->input('min_rating')));
        }

        $sortBy = $request->input('sort_by', 'position');
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc'));
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        switch ($sortBy) {
            case 'price_low_high':
            case 'low_to_high':
                $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price ASC, position ASC');
                break;
            case 'price_high_low':
            case 'high_to_low':
                $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price DESC, position ASC');
                break;
            case 'rating_high_low':
                $query->orderByRaw('CASE WHEN rating IS NULL THEN 1 ELSE 0 END, rating DESC, position ASC');
                break;
            case 'reviews_high_low':
                $query->orderByRaw('CASE WHEN reviews IS NULL THEN 1 ELSE 0 END, reviews DESC, position ASC');
                break;
            case 'sold_high_low':
                $query->orderByRaw('CASE WHEN sold_count IS NULL THEN 1 ELSE 0 END, sold_count DESC, position ASC');
                break;
            case 'position':
            default:
                $query->orderBy('position', $sortOrder);
                break;
        }

        $results = $query->get();
        $priceStats = $this->calculatePriceStats($results);

        return response()->json([
            'success' => true,
            'query' => $searchQuery,
            'total_results' => $results->count(),
            'price_stats' => [
                'min_price' => $priceStats['min_price'],
                'max_price' => $priceStats['max_price'],
                'avg_price' => $priceStats['avg_price'],
            ],
            'data' => $results,
        ]);
    }

    /** Price/rating ranges for the keyword (used to seed filter widgets) */
    public function getFilterOptions(Request $request)
    {
        $searchQuery = $request->input('query');
        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $results = TiktokCompetitorProduct::where('search_query', $searchQuery)->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'price_range' => ['min' => 0, 'max' => 0],
                    'rating_range' => ['min' => 0, 'max' => 5],
                ],
            ]);
        }

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
            ],
        ]);
    }

    /** Last saved raw Apify response so you can inspect the field shape */
    public function getRawResponse(Request $request)
    {
        $searchQuery = $request->input('query');
        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $record = TiktokSearchRawResponse::where('search_query', $searchQuery)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No saved raw response found for this search. Run a search first.',
            ], 404);
        }

        $raw = $record->raw_response;
        $parsed = is_string($raw) ? (json_decode($raw, true) ?? $raw) : $raw;

        return response()->json([
            'success' => true,
            'meta' => [
                'search_query' => $record->search_query,
                'marketplace' => $record->marketplace,
                'region' => $record->region,
                'provider' => $record->provider,
                'provider_run_id' => $record->provider_run_id,
                'items_count' => $record->items_count,
                'created_at' => $record->created_at?->toDateTimeString(),
            ],
            'response' => $parsed,
        ]);
    }

    /** SKU dropdown (same shape as AmazonSearchController::getSkus) */
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

    /** Map selected competitor rows to one or many SKUs */
    public function storeCompetitors(Request $request)
    {
        $input = $request->all();
        if (empty($input['competitors']) && $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $validator = Validator::make($input, [
            'competitors' => 'required|array',
            'competitors.*.product_id' => 'required|string',
            'competitors.*.sku' => 'required|string',
            'competitors.*.marketplace' => 'nullable|string',
            'competitors.*.region' => 'nullable|string',
            'competitors.*.product_title' => 'nullable|string',
            'competitors.*.product_link' => 'nullable|string',
            'competitors.*.image' => 'nullable|string',
            'competitors.*.seller_name' => 'nullable|string',
            'competitors.*.brand_name' => 'nullable|string',
            'competitors.*.price' => 'nullable|numeric',
            'competitors.*.min_price' => 'nullable|numeric',
            'competitors.*.max_price' => 'nullable|numeric',
            'competitors.*.rating' => 'nullable|numeric',
            'competitors.*.reviews' => 'nullable|integer',
            'competitors.*.sold_count' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $competitors = $input['competitors'];
        $created = 0;
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($competitors as $competitor) {
                $sku = trim((string) $competitor['sku']);
                $productId = trim((string) $competitor['product_id']);
                if ($sku === '' || $productId === '') {
                    continue;
                }

                $marketplace = $competitor['marketplace'] ?? 'tiktok';
                $region = strtoupper((string) ($competitor['region'] ?? 'US'));

                $result = TiktokSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'product_id' => $productId,
                        'marketplace' => $marketplace,
                        'region' => $region,
                    ],
                    [
                        'product_title' => $competitor['product_title'] ?? null,
                        'product_link' => $competitor['product_link'] ?? null,
                        'image' => $competitor['image'] ?? null,
                        'seller_name' => $competitor['seller_name'] ?? null,
                        'brand_name' => $competitor['brand_name'] ?? null,
                        'price' => isset($competitor['price']) ? (float) $competitor['price'] : null,
                        'min_price' => isset($competitor['min_price']) ? (float) $competitor['min_price'] : null,
                        'max_price' => isset($competitor['max_price']) ? (float) $competitor['max_price'] : null,
                        'rating' => isset($competitor['rating']) ? (float) $competitor['rating'] : null,
                        'reviews' => isset($competitor['reviews']) ? (int) $competitor['reviews'] : null,
                        'sold_count' => isset($competitor['sold_count']) ? (int) $competitor['sold_count'] : null,
                    ]
                );

                $result->wasRecentlyCreated ? $created++ : $updated++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Created {$created} new mappings, updated {$updated} existing mappings",
                'created' => $created,
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Store TikTok Competitors Error', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error storing competitor mappings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map one Apify Actor row into our schema. Apify actors differ in field
     * casing, so we accept both snake_case (TikTok Shop Search Pro) and
     * camelCase (Product Scraper) keys, plus a few aliases.
     */
    private function normalizeApifyRow(array $row, string $searchQuery, string $marketplace, string $region, int $fallbackPosition): ?array
    {
        $pick = static function (array $row, array $keys) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    return $row[$k];
                }
            }
            return null;
        };

        $productId = $pick($row, ['product_id', 'productId', 'id', 'product_sku', 'productID']);
        if (!$productId) {
            return null;
        }
        $productId = (string) $productId;

        $title = $pick($row, ['product_name', 'productName', 'title', 'name']);
        $brand = $pick($row, ['brand_name', 'brandName', 'brand']);
        $seller = $pick($row, ['seller', 'seller_name', 'sellerName', 'shop_name', 'shopName']);
        if (is_array($seller)) {
            $seller = $seller['name'] ?? ($seller['shop_name'] ?? ($seller['title'] ?? null));
        }

        // Price ordering: prefer the actual "real_price" the buyer pays, then
        // explicit price, then the avg/min as fallback. sentry/tiktok-shop-search-pro
        // uses real_price + min_price + max_price + avg_price.
        $price = $this->extractDecimal($pick($row, ['real_price', 'price', 'current_price', 'currentPrice', 'sale_price', 'salePrice', 'avg_price', 'avgPrice']));
        $minPrice = $this->extractDecimal($pick($row, ['min_price', 'minPrice', 'lowest_price', 'lowestPrice']));
        $maxPrice = $this->extractDecimal($pick($row, ['max_price', 'maxPrice', 'highest_price', 'highestPrice']));
        if ($price === null && $minPrice !== null) {
            $price = $minPrice;
        }

        $rating = $this->extractDecimal($pick($row, ['product_rating', 'productRating', 'rating', 'avg_rating', 'avgRating']));
        $reviews = $this->extractInt($pick($row, ['review_count', 'reviewCount', 'reviews', 'reviews_count']));
        // total_sale_cnt is what sentry/tiktok-shop-search-pro emits for the
        // "X sold" badge; other TikTok actors use sold_count / sales / sold.
        $sold = $this->extractInt($pick($row, ['total_sale_cnt', 'totalSaleCnt', 'sold_count', 'soldCount', 'sales', 'sold', 'units_sold']));
        $position = $this->extractInt($pick($row, ['rank_global', 'rankGlobal', 'rank_on_page', 'rankOnPage', 'position'])) ?? $fallbackPosition;

        $link = $pick($row, ['product_url', 'productUrl', 'url', 'link', 'product_link', 'productLink']);
        // sentry/tiktok-shop-search-pro emits the image as `product_image_url`
        // (preferred) with `cover_url` as a fallback; other actors use plain
        // `image` / `thumbnail` / nested arrays. Try them in that order.
        $image = $pick($row, ['product_image_url', 'productImageUrl', 'cover_url', 'coverUrl', 'image_url', 'imageUrl', 'image', 'thumbnail', 'product_image', 'productImage', 'cover_image']);
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        return [
            'marketplace' => $marketplace,
            'region' => $region,
            'search_query' => $searchQuery,
            'product_id' => $productId,
            'product_link' => $link ? (string) $link : null,
            'title' => $title ? (string) $title : null,
            'brand_name' => $brand ? (string) $brand : null,
            'seller_name' => $seller ? (string) $seller : null,
            'price' => $price,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'rating' => $rating,
            'reviews' => $reviews,
            'sold_count' => $sold,
            'position' => $position,
            'image' => $image ? mb_substr((string) $image, 0, 1024) : null,
        ];
    }

    private function extractDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('/[\d,.]+/', $value, $m)) {
            return (float) str_replace(',', '', $m[0]);
        }
        if (is_array($value)) {
            foreach (['value', 'amount', 'price'] as $k) {
                if (isset($value[$k]) && is_numeric($value[$k])) {
                    return (float) $value[$k];
                }
            }
        }
        return null;
    }

    private function extractInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            // Handle "1.2K sold", "3.4M reviews" etc.
            $clean = strtolower(trim($value));
            if (preg_match('/([\d.]+)\s*([km])?/', $clean, $m)) {
                $n = (float) $m[1];
                if (!empty($m[2])) {
                    $n *= ($m[2] === 'k' ? 1_000 : 1_000_000);
                }
                return (int) round($n);
            }
        }
        return null;
    }

    private function calculatePriceStats($results): array
    {
        if ($results->isEmpty()) {
            return ['min_price' => 0, 'max_price' => 0, 'avg_price' => 0];
        }
        $prices = $results->pluck('price')->filter()->values();
        return [
            'min_price' => $prices->min() ?? 0,
            'max_price' => $prices->max() ?? 0,
            'avg_price' => round((float) ($prices->avg() ?? 0), 2),
        ];
    }
}
