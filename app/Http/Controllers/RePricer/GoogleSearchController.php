<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\GoogleCompetitorItem;
use App\Models\GoogleSkuCompetitor;
use App\Models\ProductMaster;
use App\Services\GoogleLivePriceFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoogleSearchController extends Controller
{
    public function index()
    {
        return view('repricer.google_search.index');
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:0|max:500',
            'max_pages' => 'nullable|integer|min:1|max:5',
            'expand_sellers' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $searchQuery = trim($request->input('query'));
        $expandSellers = $request->boolean('expand_sellers', true);
        $deepExpand = $request->boolean('deep_expand', false);
        $maxPages = (int) $request->input('max_pages', 1);
        $limit = $expandSellers ? 0 : (int) $request->input('limit', 40);

        try {
            $fetcher = app(GoogleLivePriceFetcher::class);
            $results = $fetcher->searchShopping($searchQuery, $limit, [
                'max_pages' => $maxPages,
                'expand_sellers' => $expandSellers,
                'expand_multiple_only' => !$deepExpand,
                'max_immersive_products' => $deepExpand ? 25 : 12,
                'max_store_pages' => $deepExpand ? 3 : 1,
                'sort_by_price' => true,
            ]);

            $now = now();
            $rows = [];
            foreach ($results as $item) {
                $rows[] = [
                    'marketplace' => 'google',
                    'search_query' => $searchQuery,
                    'product_id' => $item['product_id'],
                    'source' => $item['source'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'link' => $item['link'],
                    'image' => $item['image'],
                    'rating' => $item['rating'],
                    'reviews' => $item['reviews'],
                    'position' => $item['position'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    GoogleCompetitorItem::upsert(
                        $chunk,
                        ['search_query', 'product_id', 'source'],
                        ['marketplace', 'title', 'price', 'link', 'image', 'rating', 'reviews', 'position', 'updated_at']
                    );
                }
            }

            $stored = collect($results)->map(fn ($item) => (object) [
                'id' => null,
                'search_query' => $searchQuery,
                'product_id' => $item['product_id'],
                'source' => $item['source'],
                'title' => $item['title'],
                'price' => $item['price'],
                'link' => $item['link'],
                'image' => $item['image'],
                'rating' => $item['rating'],
                'reviews' => $item['reviews'],
                'position' => $item['position'] ?? null,
            ]);

            $prices = $stored->pluck('price')->filter(fn ($p) => $p > 0);

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'query' => $searchQuery,
                'total_results' => $stored->count(),
                'expand_sellers' => $expandSellers,
                'unique_sources' => $stored->pluck('source')->filter()->unique()->values(),
                'price_stats' => [
                    'min_price' => $prices->min() ?? 0,
                    'max_price' => $prices->max() ?? 0,
                    'avg_price' => $prices->isNotEmpty() ? round($prices->avg(), 2) : 0,
                ],
                'data' => $stored,
            ]);
        } catch (\Throwable $e) {
            Log::error('GoogleSearchController search failed', [
                'query' => $searchQuery,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching Google Shopping results',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSearchHistory()
    {
        $searches = GoogleCompetitorItem::select('search_query')
            ->selectRaw('MAX(created_at) as last_searched')
            ->groupBy('search_query')
            ->orderByDesc('last_searched')
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

        $query = GoogleCompetitorItem::where('search_query', $searchQuery);

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }
        if ($request->filled('source')) {
            $query->where('source', 'like', '%' . $request->input('source') . '%');
        }

        $sortBy = $request->input('sort_by', 'price_low_high');
        if ($sortBy === 'price_high_low') {
            $query->orderByRaw('CAST(price AS DECIMAL(10,2)) DESC');
        } else {
            $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ASC');
        }

        $results = $query->get();
        $prices = $results->pluck('price')->filter(fn ($p) => $p > 0);

        return response()->json([
            'success' => true,
            'query' => $searchQuery,
            'total_results' => $results->count(),
            'price_stats' => [
                'min_price' => $prices->min() ?? 0,
                'max_price' => $prices->max() ?? 0,
                'avg_price' => $prices->isNotEmpty() ? round($prices->avg(), 2) : 0,
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
            'competitors.*.product_id' => 'required',
            'competitors.*.sku' => 'required|string',
            'competitors.*.source' => 'nullable|string',
            'competitors.*.search_query' => 'nullable|string',
            'competitors.*.product_title' => 'nullable|string',
            'competitors.*.product_link' => 'nullable|string',
            'competitors.*.image' => 'nullable|string',
            'competitors.*.price' => 'nullable|numeric',
            'competitors.*.rating' => 'nullable|numeric',
            'competitors.*.reviews' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $created = 0;
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($input['competitors'] as $competitor) {
                $sku = trim($competitor['sku'] ?? '');
                $productId = (string) ($competitor['product_id'] ?? '');
                if ($sku === '' || $productId === '') {
                    continue;
                }

                $result = GoogleSkuCompetitor::updateOrCreate(
                    [
                        'sku' => $sku,
                        'product_id' => $productId,
                        'source' => $competitor['source'] ?? null,
                    ],
                    [
                        'marketplace' => 'google',
                        'search_query' => $competitor['search_query'] ?? null,
                        'product_title' => $competitor['product_title'] ?? null,
                        'product_link' => $competitor['product_link'] ?? null,
                        'image' => $competitor['image'] ?? null,
                        'price' => $competitor['price'] ?? null,
                        'rating' => $competitor['rating'] ?? null,
                        'reviews' => $competitor['reviews'] ?? null,
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
                'message' => "Saved Google LMP: {$created} created, {$updated} updated",
                'created' => $created,
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('GoogleSearchController storeCompetitors failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save competitors: ' . $e->getMessage(),
            ], 500);
        }
    }
}
