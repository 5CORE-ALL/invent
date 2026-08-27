<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ReverbCompetitorItem;
use App\Models\ReverbSkuCompetitor;
use App\Services\ReverbApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReverbSearchController extends Controller
{
    /**
     * Search Reverb for competitor listings via the public listings API.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'marketplace' => 'nullable|string|max:50',
            'max_pages' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $searchQuery = $request->input('query');
        $marketplace = $request->input('marketplace', 'reverb');
        $maxPages = (int) $request->input('max_pages', 5);
        $forceRefresh = $request->boolean('force_refresh', false);

        if (! $forceRefresh) {
            $cached = ReverbCompetitorItem::where('search_query', $searchQuery)
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

        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $collectedItemIds = [];
        $partialFailure = null;

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = $this->reverbListingsRequest($apiBase.'/listings', [
                    'query' => $searchQuery,
                    'page' => $page,
                    'per_page' => 50,
                ]);

                if (! $response->successful()) {
                    Log::error('Reverb listings search failed', [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 800),
                        'query' => $searchQuery,
                        'page' => $page,
                    ]);

                    // Page 1 is required for a fresh fetch; later pages can fail without
                    // killing the whole search. If we already have cached rows for this
                    // query (from a prior run), serve those instead of a hard error.
                    if ($page === 1 || empty($collectedItemIds)) {
                        $cached = ReverbCompetitorItem::where('search_query', $searchQuery)
                            ->orderBy('position', 'asc')
                            ->get();

                        if ($cached->isNotEmpty()) {
                            $priceStats = $this->calculatePriceStats($cached);

                            return response()->json([
                                'success' => true,
                                'message' => 'Reverb API temporarily unavailable (HTTP '.$response->status().'); showing cached results',
                                'query' => $searchQuery,
                                'total_results' => $cached->count(),
                                'from_cache' => true,
                                'category_info' => null,
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
                            'message' => 'Reverb API request failed',
                            'error' => 'HTTP Status: '.$response->status(),
                            'details' => $response->json() ?: ['body' => substr($response->body(), 0, 300)],
                        ], 500);
                    }

                    $partialFailure = 'HTTP Status: '.$response->status().' on page '.$page;
                    break;
                }

                $data = $response->json() ?? [];
                $listings = $data['listings'] ?? [];

                if (empty($listings)) {
                    break;
                }

                foreach ($listings as $index => $listing) {
                    $itemId = (string) ($listing['id'] ?? '');
                    if ($itemId === '' || in_array($itemId, $collectedItemIds, true)) {
                        continue;
                    }
                    $collectedItemIds[] = $itemId;

                    $existing = ReverbCompetitorItem::where('search_query', $searchQuery)
                        ->where('item_id', $itemId)
                        ->first();
                    if ($existing) {
                        continue;
                    }

                    $price = $this->extractAmount($listing['buyer_price'] ?? $listing['price'] ?? null);
                    $shippingCost = $this->extractShippingCost($listing);
                    $condition = $listing['condition']['display_name']
                        ?? $listing['condition']['slug']
                        ?? (is_string($listing['condition'] ?? null) ? $listing['condition'] : null);
                    $sellerName = $listing['shop']['name'] ?? null;
                    $sellerRating = isset($listing['shop']['rating'])
                        ? (string) $listing['shop']['rating']
                        : null;
                    $location = $listing['shop']['address']['locality']
                        ?? $listing['shop']['address']['region']
                        ?? $listing['shop']['location']
                        ?? null;
                    $link = $listing['_links']['web']['href']
                        ?? $listing['_links']['self']['href']
                        ?? ('https://reverb.com/item/'.$itemId);
                    $image = $this->extractImage($listing);
                    $position = (($page - 1) * 50) + ($index + 1);

                    ReverbCompetitorItem::create([
                        'marketplace' => $marketplace,
                        'search_query' => $searchQuery,
                        'item_id' => $itemId,
                        'link' => $link,
                        'title' => $listing['title'] ?? null,
                        'price' => $price,
                        'condition' => $condition,
                        'seller_name' => $sellerName,
                        'seller_rating' => $sellerRating,
                        'position' => $position,
                        'image' => $image,
                        'shipping_cost' => $shippingCost,
                        'location' => is_string($location) ? $location : null,
                    ]);
                }

                // Stop if Reverb reports no next page.
                if (empty($data['_links']['next']['href'])) {
                    break;
                }
            }

            $results = ReverbCompetitorItem::where('search_query', $searchQuery)
                ->orderBy('position', 'asc')
                ->get();

            $priceStats = $this->calculatePriceStats($results);

            return response()->json([
                'success' => true,
                'message' => $partialFailure
                    ? 'Search completed with partial results ('.$partialFailure.')'
                    : 'Search completed successfully',
                'query' => $searchQuery,
                'total_results' => $results->count(),
                'partial_failure' => $partialFailure,
                'category_info' => null,
                'price_stats' => [
                    'min_price' => $priceStats['min_price'],
                    'max_price' => $priceStats['max_price'],
                    'avg_price' => $priceStats['avg_price'],
                ],
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Reverb listings search exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching data from Reverb',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    public function index()
    {
        return view('repricer.reverb_search.index');
    }

    public function getSearchHistory()
    {
        $searches = ReverbCompetitorItem::select('search_query')
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

        $query = ReverbCompetitorItem::where('search_query', $searchQuery);

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

        $conditions = ReverbCompetitorItem::where('search_query', $searchQuery)
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

        $results = ReverbCompetitorItem::where('search_query', $searchQuery)->get();

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

        $conditions = $results->pluck('condition')->filter()->unique()->values();
        $locations = $results->pluck('location')->filter()->unique()->values();
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
            Log::warning('ReverbSearchController storeCompetitors validation failed', [
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
                $sku = trim($competitor['sku']);
                $itemId = $this->resolveListingId(
                    $competitor['product_link'] ?? null,
                    $competitor['item_id'] ?? null
                );

                if ($sku === '' || $itemId === '') {
                    Log::warning('ReverbSearchController storeCompetitors: skipping empty sku/item_id', [
                        'competitor' => $competitor,
                    ]);
                    continue;
                }

                $result = ReverbSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'item_id' => $itemId,
                    ],
                    [
                        'marketplace' => $competitor['marketplace'] ?? 'reverb',
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

            Log::error('Store Reverb Competitors Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $competitors ?? [],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error storing competitor mappings',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    /**
     * GET Reverb listings with retries for transient 502/503/429 gateway errors.
     *
     * @param  array<string, mixed>  $query
     * @return \Illuminate\Http\Client\Response
     */
    private function reverbListingsRequest(string $url, array $query)
    {
        $token = ReverbApiService::getReverbBearerToken();
        $attempts = [
            // Prefer authenticated (higher consistency), then public fallback.
            ['auth' => true],
            ['auth' => true],
            ['auth' => false],
        ];

        $lastResponse = null;

        foreach ($attempts as $i => $attempt) {
            $headers = [
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
                'User-Agent' => 'Invent-Reverb-LMP/1.0 (+https://reverb.com)',
            ];

            if ($attempt['auth'] && is_string($token) && $token !== '') {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            try {
                $lastResponse = Http::withoutVerifying()
                    ->timeout(60)
                    ->retry(0, 0)
                    ->withHeaders($headers)
                    ->get($url, $query);
            } catch (\Throwable $e) {
                Log::warning('Reverb listings request exception', [
                    'attempt' => $i + 1,
                    'message' => $e->getMessage(),
                    'url' => $url,
                    'query' => $query,
                ]);
                usleep(400000 * ($i + 1));
                continue;
            }

            if ($lastResponse->successful()) {
                return $lastResponse;
            }

            $status = $lastResponse->status();
            $retryable = in_array($status, [429, 502, 503, 504], true);

            Log::warning('Reverb listings request non-success', [
                'attempt' => $i + 1,
                'status' => $status,
                'auth' => $attempt['auth'],
                'retryable' => $retryable,
                'body' => substr($lastResponse->body(), 0, 300),
            ]);

            if (! $retryable) {
                return $lastResponse;
            }

            // Brief backoff; 429 may include Retry-After.
            $sleepMs = 500 * ($i + 1);
            if ($status === 429) {
                $retryAfter = (int) $lastResponse->header('Retry-After');
                if ($retryAfter > 0) {
                    $sleepMs = min($retryAfter * 1000, 5000);
                }
            }
            usleep($sleepMs * 1000);
        }

        return $lastResponse;
    }

    /**
     * @param  mixed  $priceField
     */
    private function extractAmount($priceField): ?float
    {
        if (is_array($priceField) && isset($priceField['amount']) && is_numeric($priceField['amount'])) {
            return (float) $priceField['amount'];
        }
        if (is_numeric($priceField)) {
            return (float) $priceField;
        }
        if (is_string($priceField)) {
            preg_match('/[\d,.]+/', $priceField, $matches);
            if (! empty($matches)) {
                return (float) str_replace(',', '', $matches[0]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function extractShippingCost(array $listing): ?float
    {
        $shipping = $listing['shipping'] ?? null;
        if (is_array($shipping)) {
            if (isset($shipping['rates']) && is_array($shipping['rates']) && ! empty($shipping['rates'])) {
                $rate = $shipping['rates'][0];
                $amount = $this->extractAmount($rate['rate'] ?? $rate['amount'] ?? $rate['price'] ?? null);
                if ($amount !== null) {
                    return $amount;
                }
            }
            $amount = $this->extractAmount($shipping['rate'] ?? $shipping['amount'] ?? $shipping['price'] ?? null);
            if ($amount !== null) {
                return $amount;
            }
            if (! empty($shipping['local']) || ! empty($shipping['us_rate'])) {
                $local = $shipping['local'] ?? $shipping['us_rate'] ?? null;
                $amount = $this->extractAmount(is_array($local) ? ($local['amount'] ?? $local['rate'] ?? null) : $local);
                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        if (isset($listing['shipping_price'])) {
            return $this->extractAmount($listing['shipping_price']);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function extractImage(array $listing): ?string
    {
        if (! empty($listing['photos']) && is_array($listing['photos'])) {
            $photo = $listing['photos'][0];
            if (is_array($photo)) {
                return $photo['_links']['large_crop']['href']
                    ?? $photo['_links']['thumbnail']['href']
                    ?? $photo['url']
                    ?? null;
            }
        }

        if (! empty($listing['thumbnail_url']) && is_string($listing['thumbnail_url'])) {
            return $listing['thumbnail_url'];
        }

        if (isset($listing['photo']['url']) && is_string($listing['photo']['url'])) {
            return $listing['photo']['url'];
        }

        return null;
    }

    private function resolveListingId(?string $link, $fallbackId = null): string
    {
        if (is_string($fallbackId) || is_numeric($fallbackId)) {
            $id = trim((string) $fallbackId);
            if ($id !== '') {
                return $id;
            }
        }

        if (is_string($link) && $link !== '') {
            if (preg_match('#/item/(\d+)#', $link, $m)) {
                return $m[1];
            }
            if (preg_match('#/listings/(\d+)#', $link, $m)) {
                return $m[1];
            }
        }

        return '';
    }

    private function calculatePriceStats($results): array
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
}
