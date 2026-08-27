<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\BestbuyCompetitorItem;
use App\Models\BestbuySkuCompetitor;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Best Buy competitor discovery for the repricer.
 *
 * SerpApi has no dedicated Best Buy engine. This controller uses
 * engine=google_shopping with "{query} best buy" and keeps rows whose
 * source / link match Best Buy — same UX shape as ebay-search / reverb-search.
 */
class BestbuySearchController extends Controller
{
    public function index()
    {
        return view('repricer.bestbuy_search.index');
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'marketplace' => 'nullable|string|max:50',
            'max_pages' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $searchQuery = trim((string) $request->input('query'));
        $marketplace = $request->input('marketplace', 'bestbuy');
        $maxPages = (int) $request->input('max_pages', 3);
        $forceRefresh = $request->boolean('force_refresh', false);

        if (! $forceRefresh) {
            $cached = BestbuyCompetitorItem::where('search_query', $searchQuery)
                ->orderBy('position', 'asc')
                ->get();
            if ($cached->isNotEmpty()) {
                $priceStats = $this->calculatePriceStats($cached);

                return response()->json([
                    'success' => true,
                    'message' => 'Loaded saved results (no API credits used)',
                    'query' => $searchQuery,
                    'from_cache' => true,
                    'total_results' => $cached->count(),
                    'price_stats' => [
                        'min_price' => $priceStats['min_price'],
                        'max_price' => $priceStats['max_price'],
                        'avg_price' => $priceStats['avg_price'],
                    ],
                    'data' => $cached,
                ]);
            }
        }

        $serpApiKey = config('services.serpapi.key');
        if (! $serpApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'SerpApi key not configured',
            ], 500);
        }

        // Bias Google Shopping toward Best Buy without breaking user keywords.
        $shoppingQuery = $this->buildShoppingQuery($searchQuery);
        $collectedItemIds = [];

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $start = ($page - 1) * 40;

                $response = Http::timeout(60)->get('https://serpapi.com/search', [
                    'engine' => 'google_shopping',
                    'q' => $shoppingQuery,
                    'hl' => 'en',
                    'gl' => 'us',
                    'start' => $start,
                    'num' => 40,
                    'api_key' => $serpApiKey,
                ]);

                if (! $response->successful()) {
                    Log::error('SerpApi BestBuy shopping error', [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 800),
                        'query' => $searchQuery,
                        'page' => $page,
                    ]);

                    if ($page === 1) {
                        $cached = BestbuyCompetitorItem::where('search_query', $searchQuery)
                            ->orderBy('position', 'asc')
                            ->get();
                        if ($cached->isNotEmpty()) {
                            $priceStats = $this->calculatePriceStats($cached);

                            return response()->json([
                                'success' => true,
                                'message' => 'SerpApi temporarily unavailable; showing cached results',
                                'query' => $searchQuery,
                                'total_results' => $cached->count(),
                                'from_cache' => true,
                                'price_stats' => [
                                    'min_price' => $priceStats['min_price'],
                                    'max_price' => $priceStats['max_price'],
                                    'avg_price' => $priceStats['avg_price'],
                                ],
                                'data' => $cached,
                            ]);
                        }

                        return response()->json([
                            'success' => false,
                            'message' => 'SerpApi request failed',
                            'error' => 'HTTP Status: '.$response->status(),
                            'details' => $response->json(),
                        ], 500);
                    }

                    break;
                }

                $data = $response->json() ?? [];
                $organicResults = $data['shopping_results'] ?? [];

                if (empty($organicResults)) {
                    break;
                }

                $pageHadBestbuy = false;

                foreach ($organicResults as $index => $result) {
                    if (! $this->isBestbuyResult($result)) {
                        continue;
                    }

                    $itemId = $this->resolveItemId($result);
                    if ($itemId === '' || in_array($itemId, $collectedItemIds, true)) {
                        continue;
                    }
                    $collectedItemIds[] = $itemId;
                    $pageHadBestbuy = true;

                    $existing = BestbuyCompetitorItem::where('search_query', $searchQuery)
                        ->where('item_id', $itemId)
                        ->first();
                    if ($existing) {
                        continue;
                    }

                    $price = $this->extractPrice($result);
                    $shippingCost = $this->extractShippingCost($result);
                    $link = $result['product_link']
                        ?? $result['link']
                        ?? ('https://www.bestbuy.com/site/searchpage.jsp?st='.rawurlencode($searchQuery));
                    $sellerRating = isset($result['rating']) ? (string) $result['rating'] : null;
                    $position = $start + ($index + 1);

                    BestbuyCompetitorItem::create([
                        'marketplace' => $marketplace,
                        'search_query' => $searchQuery,
                        'item_id' => $itemId,
                        'link' => $link,
                        'title' => $result['title'] ?? null,
                        'price' => $price,
                        'condition' => $result['condition'] ?? 'New',
                        'seller_name' => $result['source'] ?? 'Best Buy',
                        'seller_rating' => $sellerRating,
                        'position' => $position,
                        'image' => $result['thumbnail'] ?? $result['serpapi_thumbnail'] ?? $result['image'] ?? null,
                        'shipping_cost' => $shippingCost,
                        'location' => is_string($result['delivery'] ?? null) ? $result['delivery'] : null,
                    ]);
                }

                // No Best Buy hits on this page — further pages unlikely to help.
                if (! $pageHadBestbuy) {
                    break;
                }
            }

            $results = BestbuyCompetitorItem::where('search_query', $searchQuery)
                ->orderBy('position', 'asc')
                ->get();

            $priceStats = $this->calculatePriceStats($results);

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'shopping_query' => $shoppingQuery,
                'total_results' => $results->count(),
                'category_info' => null,
                'price_stats' => [
                    'min_price' => $priceStats['min_price'],
                    'max_price' => $priceStats['max_price'],
                    'avg_price' => $priceStats['avg_price'],
                ],
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('SerpApi BestBuy exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching data from SerpApi',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    public function getSearchHistory()
    {
        $searches = BestbuyCompetitorItem::select('search_query')
            ->groupBy('search_query')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(50)
            ->pluck('search_query');

        return response()->json([
            'success' => true,
            'data' => $searches,
        ]);
    }

    public function getResults(Request $request)
    {
        $searchQuery = $request->input('query');

        if (! $searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $query = BestbuyCompetitorItem::where('search_query', $searchQuery);

        if ($request->has('min_price') && $request->input('min_price') !== null) {
            $query->where('price', '>=', floatval($request->input('min_price')));
        }
        if ($request->has('max_price') && $request->input('max_price') !== null) {
            $query->where('price', '<=', floatval($request->input('max_price')));
        }
        if ($request->has('condition') && $request->input('condition') !== null) {
            $query->where('condition', $request->input('condition'));
        }
        if ($request->has('seller_name') && $request->input('seller_name') !== null) {
            $query->where('seller_name', 'like', '%'.$request->input('seller_name').'%');
        }
        if ($request->has('location') && $request->input('location') !== null) {
            $query->where('location', 'like', '%'.$request->input('location').'%');
        }

        $sortBy = $request->input('sort_by', 'position');
        $sortOrder = $request->input('sort_order', 'asc');
        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

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

        $results = $query->get();
        $priceStats = $this->calculatePriceStats($results);
        $conditions = BestbuyCompetitorItem::where('search_query', $searchQuery)
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
            'data' => $results,
        ]);
    }

    public function getSkus()
    {
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
            'source' => 'product_master',
        ]);
    }

    public function getFilterOptions(Request $request)
    {
        $searchQuery = $request->input('query');

        if (! $searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $results = BestbuyCompetitorItem::where('search_query', $searchQuery)->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'conditions' => [],
                    'locations' => [],
                    'price_range' => ['min' => 0, 'max' => 0],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conditions' => $results->pluck('condition')->filter()->unique()->values(),
                'locations' => $results->pluck('location')->filter()->unique()->values(),
                'price_range' => [
                    'min' => $results->pluck('price')->filter()->min() ?? 0,
                    'max' => $results->pluck('price')->filter()->max() ?? 0,
                ],
            ],
        ]);
    }

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
            Log::warning('BestbuySearchController storeCompetitors validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input_keys' => array_keys($input),
            ]);

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
                $price = floatval($competitor['price'] ?? 0);
                $shippingCost = floatval($competitor['shipping_cost'] ?? 0);
                $totalPrice = $price + $shippingCost;
                $sku = trim((string) $competitor['sku']);
                $itemId = trim((string) ($competitor['item_id'] ?? ''));

                if ($sku === '' || $itemId === '') {
                    continue;
                }

                $result = BestbuySkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'item_id' => $itemId,
                    ],
                    [
                        'marketplace' => $competitor['marketplace'] ?? 'bestbuy',
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
                'updated' => $updated,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Store BestBuy Competitors Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error storing competitor mappings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildShoppingQuery(string $searchQuery): string
    {
        $q = trim($searchQuery);
        if ($q === '') {
            return 'best buy';
        }
        if (preg_match('/\bbest\s*buy\b/i', $q)) {
            return $q;
        }

        return $q.' best buy';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isBestbuyResult(array $result): bool
    {
        $source = (string) ($result['source'] ?? '');
        $link = (string) ($result['product_link'] ?? $result['link'] ?? '');

        return stripos($source, 'best buy') !== false
            || stripos($source, 'bestbuy') !== false
            || stripos($link, 'bestbuy.com') !== false;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resolveItemId(array $result): string
    {
        foreach (['product_id', 'product_id_token', 'id'] as $key) {
            if (! empty($result[$key])) {
                return (string) $result[$key];
            }
        }

        $link = (string) ($result['product_link'] ?? $result['link'] ?? '');
        if (preg_match('/ID=(\d+)/', $link, $m) || preg_match('/\/(\d+)\.html/', $link, $m)) {
            return $m[1];
        }

        // Stable fallback so we can still store/dedupe the row.
        $title = (string) ($result['title'] ?? '');
        if ($title !== '') {
            return substr(sha1($title.'|'.($result['extracted_price'] ?? '')), 0, 20);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractPrice(array $result): ?float
    {
        if (isset($result['extracted_price']) && is_numeric($result['extracted_price'])) {
            return (float) $result['extracted_price'];
        }
        if (isset($result['price'])) {
            if (is_numeric($result['price'])) {
                return (float) $result['price'];
            }
            if (is_string($result['price'])) {
                preg_match('/[\d,.]+/', $result['price'], $matches);
                if (! empty($matches)) {
                    return (float) str_replace(',', '', $matches[0]);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractShippingCost(array $result): ?float
    {
        $delivery = $result['delivery'] ?? null;
        if (is_string($delivery)) {
            if (stripos($delivery, 'free') !== false) {
                return 0;
            }
            preg_match('/[\d,.]+/', $delivery, $matches);
            if (! empty($matches)) {
                return (float) str_replace(',', '', $matches[0]);
            }
        }

        return 0;
    }

    private function calculatePriceStats($results): array
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
}
