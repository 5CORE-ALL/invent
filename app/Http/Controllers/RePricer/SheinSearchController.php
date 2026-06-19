<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\FbaTable;
use App\Models\ProductMaster;
use App\Models\SheinCompetitorProduct;
use App\Models\SheinSearchRawResponse;
use App\Models\SheinSkuCompetitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Shein competitor discovery for the repricer.
 *
 * NOTE: SerpApi has no Shein engine (we previously tried google_shopping and
 * Google Shopping does not surface Shein organically — coverage was near
 * zero). To match what `engine=amazon` gives us for Amazon, this controller
 * hits an Apify Actor — by default `scraper-engine/shein-search-products-scraper`.
 * The actor returns real Shein product data: goods_id (Shein's own product
 * id), goods_name, salePrice / retailPrice with USD amounts, rankInfo with
 * rating + review count, and the goods image. Swap actors by setting
 * APIFY_SHEIN_ACTOR_ID.
 *
 * Wire shape (controller endpoints) is intentionally identical to
 * AmazonSearchController so the blade view reuses the same JS.
 */
class SheinSearchController extends Controller
{
    public function index()
    {
        return view('repricer.shein_search.index');
    }

    /**
     * Run a Shein keyword search via Apify, persist normalized rows,
     * and return them. Mirrors AmazonSearchController::search().
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'marketplace' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:8',
            'max_products' => 'nullable|integer|min:1|max:500',
            'order_by' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $searchQuery = trim((string) $request->input('query'));
        $marketplace = $request->input('marketplace', 'shein');
        $country = strtolower((string) $request->input('country', config('services.apify.shein.country', 'us')));
        $maxProducts = (int) $request->input('max_products', config('services.apify.shein.max_products', 100));
        $orderBy = $request->input('order_by', 'recommend');

        $token = config('services.apify.token');
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Apify token not configured. Set APIFY_TOKEN in .env (see config/services.php → apify).',
            ], 500);
        }

        try {
            $actor = config('services.apify.shein.actor_id', 'scraper-engine~shein-search-products-scraper');
            $baseUrl = rtrim((string) config('services.apify.base_url', 'https://api.apify.com/v2'), '/');
            $timeout = (int) config('services.apify.shein.timeout_secs', 300);

            // perPage is an enum on this actor (20/40/60/80/100/120) — snap
            // the requested count up to the nearest bucket so we don't drop a
            // few rows due to rounding down.
            $perPage = $this->snapToBucket($maxProducts, [20, 40, 60, 80, 100, 120]);

            // Documented input for scraper-engine/shein-search-products-scraper:
            // query (array OR comma-separated string), countryCode (lowercase
            // ISO-3166), orderBy, page, perPage, minPrice, maxPrice, maxItems.
            // `maxItems` is the actor's primary count knob — without it the
            // actor caps at 10 rows per query. We keep a few snake_case +
            // camelCase aliases so swapping APIFY_SHEIN_ACTOR_ID to a fork
            // (pintostudio, simpleapi, ...) keeps working.
            $input = [
                'query' => [$searchQuery],

                // Shein actors require lowercase 2-letter codes ("us", "uk").
                'countryCode' => strtolower($country),
                'country' => strtolower($country),

                'orderBy' => $orderBy,
                'sort_order' => $orderBy,

                'page' => 1,
                'perPage' => (string) $perPage,

                // The knob the actor actually reads. All other *Max* fields
                // are forward-compat aliases.
                'maxItems' => $maxProducts,
                'maxResults' => $maxProducts,
                'maxResultsPerQuery' => $maxProducts,
                'maxProducts' => $maxProducts,
                'limit' => $maxProducts,

                'minPrice' => '',
                'maxPrice' => '',
                'proxyConfiguration' => ['useApifyProxy' => true],
            ];

            // We use the ASYNC pattern (start → poll → fetch dataset) rather
            // than run-sync-get-dataset-items because the Shein actor regularly
            // takes 2–4 minutes (Shein's SPA is slow to render) and run-sync
            // caps at 300s, returning HTTP 408 even when the actor itself
            // would have succeeded. Async polling lets us wait as long as the
            // configured timeout allows.
            $actorTimeoutSecs = max(60, $timeout);

            // 1) Start the actor run. We always pass an explicit
            // `maxTotalChargeUsd` because PAY_PER_EVENT actors otherwise get
            // capped at a nominal default (~$0.001) on Apify free / PAYG
            // plans, which auto-aborts before a single row is delivered.
            $maxUsd = (float) config('services.apify.shein.max_usd', 1.0);
            $startResponse = Http::timeout(30)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->post(
                    $baseUrl . '/acts/' . rawurlencode($actor) . '/runs'
                        . '?token=' . urlencode($token)
                        . '&timeout=' . $actorTimeoutSecs
                        . '&maxTotalChargeUsd=' . number_format($maxUsd, 4, '.', ''),
                    $input
                );

            $startBody = $startResponse->body();
            if (!$startResponse->successful()) {
                SheinSearchRawResponse::create([
                    'search_query' => $searchQuery,
                    'marketplace' => $marketplace,
                    'page' => 1,
                    'raw_response' => $startBody,
                    'pages_count' => 1,
                ]);
                Log::error('Apify Shein actor start failed', [
                    'status' => $startResponse->status(),
                    'body' => mb_substr($startBody, 0, 2000),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start Apify actor run',
                    'error' => 'HTTP Status: ' . $startResponse->status(),
                    'details' => json_decode($startBody, true) ?: $startBody,
                ], 500);
            }

            $startData = json_decode($startBody, true);
            $runId = $startData['data']['id'] ?? null;
            $datasetId = $startData['data']['defaultDatasetId'] ?? null;
            if (!$runId || !$datasetId) {
                SheinSearchRawResponse::create([
                    'search_query' => $searchQuery,
                    'marketplace' => $marketplace,
                    'page' => 1,
                    'raw_response' => $startBody,
                    'pages_count' => 1,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Apify start response missing run id / dataset id',
                    'details' => $startData,
                ], 500);
            }

            // 2) Poll the run until terminal. Apify's terminal statuses:
            // SUCCEEDED, FAILED, TIMED-OUT, ABORTED.
            $deadline = microtime(true) + $actorTimeoutSecs + 30;
            $sleepSecs = 5;
            $finalRun = null;
            while (microtime(true) < $deadline) {
                sleep($sleepSecs);
                $pollResponse = Http::timeout(15)
                    ->withToken($token)
                    ->get($baseUrl . '/actor-runs/' . urlencode($runId), ['token' => $token]);
                if (!$pollResponse->successful()) {
                    continue; // transient — keep polling
                }
                $pollData = $pollResponse->json();
                $status = $pollData['data']['status'] ?? null;
                if (in_array($status, ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)) {
                    $finalRun = $pollData['data'];
                    break;
                }
            }

            if ($finalRun === null) {
                return response()->json([
                    'success' => false,
                    'message' => "Apify actor still running after {$actorTimeoutSecs}s — try again or raise APIFY_SHEIN_TIMEOUT_SECS",
                    'run_id' => $runId,
                ], 504);
            }

            if (($finalRun['status'] ?? '') !== 'SUCCEEDED') {
                $runBody = json_encode($finalRun, JSON_PRETTY_PRINT);
                SheinSearchRawResponse::create([
                    'search_query' => $searchQuery,
                    'marketplace' => $marketplace,
                    'page' => 1,
                    'raw_response' => $runBody,
                    'pages_count' => 1,
                ]);

                $statusMessage = (string) ($finalRun['statusMessage'] ?? '');
                $message = 'Apify actor run ended with status: ' . ($finalRun['status'] ?? 'unknown');
                if (stripos($statusMessage, 'maximum cost') !== false) {
                    $configuredCap = number_format($maxUsd, 4, '.', '');
                    $actualCap = number_format((float) ($finalRun['options']['maxTotalChargeUsd'] ?? 0), 6, '.', '');
                    $message = "Apify aborted the run because it hit the cost cap (configured \${$configuredCap}, "
                        . "but Apify enforced \${$actualCap} — typically because the account is on a free/PAYG plan). "
                        . 'Raise APIFY_SHEIN_MAX_USD in .env and/or upgrade your Apify plan.';
                }
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'status_message' => $statusMessage ?: null,
                    'run_id' => $runId,
                    'details' => $finalRun,
                ], 500);
            }

            // 3) Fetch the dataset items.
            $itemsResponse = Http::timeout(60)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($baseUrl . '/datasets/' . urlencode($datasetId) . '/items', [
                    'token' => $token,
                    'format' => 'json',
                    'clean' => 'true',
                ]);

            $rawBody = $itemsResponse->body();
            $items = [];
            if ($itemsResponse->successful()) {
                $decoded = json_decode($rawBody, true);
                $items = is_array($decoded) ? $decoded : [];
            }

            SheinSearchRawResponse::create([
                'search_query' => $searchQuery,
                'marketplace' => $marketplace,
                'page' => 1,
                'raw_response' => $rawBody,
                'pages_count' => 1,
            ]);

            if (!$itemsResponse->successful()) {
                Log::error('Apify Shein dataset fetch failed', [
                    'status' => $itemsResponse->status(),
                    'body' => mb_substr($rawBody, 0, 2000),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Apify dataset fetch failed',
                    'error' => 'HTTP Status: ' . $itemsResponse->status(),
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

                $normalized = $this->normalizeApifyRow($row, $searchQuery, $marketplace, $country, $position);
                if ($normalized === null) {
                    continue;
                }

                $productId = (string) $normalized['product_id'];
                if ($productId === '' || in_array($productId, $collectedIds, true)) {
                    continue;
                }
                $collectedIds[] = $productId;

                $existing = SheinCompetitorProduct::where('search_query', $searchQuery)
                    ->where('product_id', $productId)
                    ->first();

                if ($existing) {
                    $existing->update($normalized);
                } else {
                    SheinCompetitorProduct::create($normalized);
                }
            }

            $results = SheinCompetitorProduct::where('search_query', $searchQuery)
                ->orderBy('position', 'asc')
                ->get();

            $priceStats = $this->calculatePriceStats($results);

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'country' => strtolower($country),
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
            Log::error('Shein Search DB Exception', [
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
            Log::error('Shein Search Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching Shein data',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ], 500);
        }
    }

    public function getSearchHistory()
    {
        $searches = SheinCompetitorProduct::select('search_query')
            ->groupBy('search_query')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(10)
            ->pluck('search_query');

        return response()->json([
            'success' => true,
            'data' => $searches,
        ]);
    }

    public function getResults(Request $request)
    {
        $searchQuery = $request->input('query');
        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $query = SheinCompetitorProduct::where('search_query', $searchQuery);

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
            case 'rating_low_high':
                $query->orderByRaw('CASE WHEN rating IS NULL THEN 1 ELSE 0 END, rating ASC, position ASC');
                break;
            case 'reviews_high_low':
                $query->orderByRaw('CASE WHEN reviews IS NULL THEN 1 ELSE 0 END, reviews DESC, position ASC');
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
            'data' => $results,
        ]);
    }

    public function getFilterOptions(Request $request)
    {
        $searchQuery = $request->input('query');
        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $results = SheinCompetitorProduct::where('search_query', $searchQuery)->get();

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

    public function getRawResponse(Request $request)
    {
        $searchQuery = $request->input('query');

        if (!$searchQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
            ], 422);
        }

        $record = SheinSearchRawResponse::where('search_query', $searchQuery)
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
                'page' => $record->page,
                'pages_count' => $record->pages_count,
                'created_at' => $record->created_at?->toDateTimeString(),
            ],
            'response' => $parsed,
        ]);
    }

    /** Mirror of AmazonSearchController::getSkus — same SKU dropdown source. */
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
            'competitors.*.product_title' => 'nullable|string',
            'competitors.*.product_link' => 'nullable|string',
            'competitors.*.image' => 'nullable|string',
            'competitors.*.seller_name' => 'nullable|string',
            'competitors.*.price' => 'nullable|numeric',
            'competitors.*.rating' => 'nullable|numeric',
            'competitors.*.reviews' => 'nullable|integer',
            'competitors.*.extracted_old_price' => 'nullable|numeric',
            'competitors.*.delivery' => 'nullable|array',
            'competitors.*.delivery.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('SheinSearchController storeCompetitors validation failed', [
                'errors' => $validator->errors()->toArray(),
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
                $sku = trim((string) $competitor['sku']);
                $productId = trim((string) $competitor['product_id']);
                if ($sku === '' || $productId === '') {
                    continue;
                }

                $marketplace = $competitor['marketplace'] ?? 'shein';

                $delivery = $competitor['delivery'] ?? null;
                if (is_string($delivery)) {
                    $decoded = json_decode($delivery, true);
                    $delivery = is_array($decoded) ? $decoded : null;
                }

                $result = SheinSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'product_id' => $productId,
                        'marketplace' => $marketplace,
                    ],
                    [
                        'product_title' => $competitor['product_title'] ?? null,
                        'seller_name' => $competitor['seller_name'] ?? null,
                        'product_link' => $competitor['product_link'] ?? null,
                        'image' => $competitor['image'] ?? null,
                        'price' => isset($competitor['price']) ? (float) $competitor['price'] : null,
                        'extracted_old_price' => isset($competitor['extracted_old_price']) ? (float) $competitor['extracted_old_price'] : null,
                        'rating' => isset($competitor['rating']) ? (float) $competitor['rating'] : null,
                        'reviews' => isset($competitor['reviews']) ? (int) $competitor['reviews'] : null,
                        'delivery' => $delivery,
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
            Log::error('Store Shein Competitors Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Map one Apify Shein actor row into our schema. Field naming differs
     * between publishers, so accept both Shein-native keys (goods_id /
     * goods_name / salePrice.usdAmount) and generic aliases (productId /
     * title / price) for forward-compat when APIFY_SHEIN_ACTOR_ID is
     * swapped.
     */
    private function normalizeApifyRow(array $row, string $searchQuery, string $marketplace, string $country, int $fallbackPosition): ?array
    {
        $pick = static function (array $row, array $keys) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    return $row[$k];
                }
            }
            return null;
        };

        $productId = $pick($row, ['goods_id', 'goodsId', 'product_id', 'productId', 'id', 'spu', 'goods_sn']);
        if (!$productId) {
            return null;
        }
        $productId = (string) $productId;

        $title = $pick($row, ['goods_name', 'goodsName', 'product_name', 'productName', 'title', 'name']);

        // Shein actor returns salePrice/retailPrice as objects: usdAmount is
        // the cleanest number for cross-country normalization. Fall back to
        // amount (local currency) when usdAmount is missing.
        $salePrice = $row['salePrice'] ?? $row['sale_price'] ?? null;
        $retailPrice = $row['retailPrice'] ?? $row['retail_price'] ?? null;

        $price = $this->extractMoney($salePrice);
        $oldPrice = $this->extractMoney($retailPrice);
        if ($price === null) {
            $price = $this->extractDecimal($pick($row, ['price', 'current_price', 'currentPrice', 'sale_price_value']));
        }
        if ($oldPrice === null) {
            $oldPrice = $this->extractDecimal($pick($row, ['original_price', 'originalPrice', 'list_price', 'listPrice']));
        }
        // If old price is the same as sale price (no discount), null it out so the UI doesn't show a useless strikethrough.
        if ($oldPrice !== null && $price !== null && abs($oldPrice - $price) < 0.005) {
            $oldPrice = null;
        }

        // Shein's actor exposes rating/reviews at the TOP level, not inside
        // rankInfo. `comment_rank_average` is the 0–5 star value;
        // `comment_num` is the integer review count (sometimes formatted in
        // `comment_num_show` as "1.2K+"). rankInfo here is the bestseller
        // badge, not engagement data.
        $rating = $this->extractDecimal($pick($row, [
            'comment_rank_average', 'commentRankAverage',
            'rating', 'avg_rating', 'avgRating', 'score',
        ]));

        $reviews = $this->extractInt($pick($row, [
            'comment_num', 'commentNum',
            'comment_num_show', 'commentNumShow',
            'review_count', 'reviewCount', 'reviews', 'reviews_count',
        ]));

        // Shein image URLs are protocol-relative ("//img.ltwebstatic.com/..."). Prepend https: for usable <img src>.
        $image = $pick($row, ['goods_img', 'goodsImg', 'image', 'image_url', 'imageUrl', 'thumbnail', 'cover_image', 'main_image']);
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }
        if (is_string($image) && str_starts_with($image, '//')) {
            $image = 'https:' . $image;
        }

        // Many Shein actors don't return a full URL; build one from goods_id
        // using Shein's canonical /-p-{id}.html path which always resolves.
        $link = $pick($row, ['product_url', 'productUrl', 'url', 'link', 'product_link', 'productLink', 'goods_url', 'goodsUrl']);
        if (!$link) {
            $link = 'https://www.shein.com/' . $productId . '-p-' . $productId . '.html';
        }
        if (is_string($link) && str_starts_with($link, '//')) {
            $link = 'https:' . $link;
        }

        $position = $this->extractInt($pick($row, ['position', 'rank', 'rank_on_page'])) ?? $fallbackPosition;

        return [
            'marketplace' => $marketplace,
            'search_query' => $searchQuery,
            'product_id' => $productId,
            'product_link' => $link ? (string) $link : null,
            'title' => $title ? (string) $title : null,
            'source' => 'Shein',
            'seller_name' => 'Shein',
            'price' => $price,
            'extracted_old_price' => $oldPrice,
            'rating' => $rating,
            'reviews' => $reviews,
            'position' => $position,
            'image' => $image ? mb_substr((string) $image, 0, 1024) : null,
            'delivery' => null,
            'extensions' => null,
        ];
    }

    /**
     * Pull a numeric price out of Shein's price object ({amount, usdAmount,
     * ...}). Prefer USD because the UI is dollar-based.
     */
    private function extractMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            return $this->extractDecimal($value);
        }
        if (is_array($value)) {
            foreach (['usdAmount', 'usd_amount', 'amount', 'value', 'price'] as $k) {
                if (isset($value[$k]) && is_numeric($value[$k])) {
                    return (float) $value[$k];
                }
            }
            foreach (['usdAmountWithSymbol', 'amountWithSymbol'] as $k) {
                if (isset($value[$k]) && is_string($value[$k])) {
                    $n = $this->extractDecimal($value[$k]);
                    if ($n !== null) {
                        return $n;
                    }
                }
            }
        }
        return null;
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
            $clean = strtolower(trim($value));
            // Handle "1.2K", "3.4M" etc. ("1.2K sold" / "3.4M reviews")
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

    /** Snap an integer up to the next allowed value in a sorted bucket list. */
    private function snapToBucket(int $value, array $buckets): int
    {
        foreach ($buckets as $b) {
            if ($value <= $b) {
                return $b;
            }
        }
        return end($buckets);
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
