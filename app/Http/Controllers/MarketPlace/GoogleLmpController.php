<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\GoogleCompetitorItem;
use App\Models\GoogleSkuCompetitor;
use App\Services\GoogleLivePriceFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleLmpController extends Controller
{
    public function getGoogleLmpData(Request $request)
    {
        try {
            $sku = trim((string) $request->input('sku'));
            if ($sku === '') {
                return response()->json(['error' => 'SKU is required'], 400);
            }

            $competitors = GoogleSkuCompetitor::getCompetitorsForSku($sku, 'google');
            $fetcher = app(GoogleLivePriceFetcher::class);

            foreach ($competitors as $competitor) {
                $live = $fetcher->fetchByProductId(
                    (string) $competitor->product_id,
                    $competitor->source,
                    $competitor->search_query
                );

                if (!$live) {
                    continue;
                }

                $competitor->update([
                    'price' => $live['price'],
                    'product_title' => $live['title'] ?? $competitor->product_title,
                    'product_link' => $live['link'] ?? $competitor->product_link,
                    'image' => $live['image'] ?? $competitor->image,
                    'rating' => $live['rating'] ?? $competitor->rating,
                    'reviews' => $live['reviews'] ?? $competitor->reviews,
                ]);

                GoogleCompetitorItem::where('product_id', $competitor->product_id)
                    ->when($competitor->source, fn ($q) => $q->where('source', $competitor->source))
                    ->update([
                        'price' => $live['price'],
                        'title' => $live['title'],
                        'link' => $live['link'],
                        'image' => $live['image'],
                        'rating' => $live['rating'],
                        'reviews' => $live['reviews'],
                    ]);
            }

            $competitors = GoogleSkuCompetitor::getCompetitorsForSku($sku, 'google');
            $lowest = $competitors->first();

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'competitors' => $competitors->map(fn ($comp) => [
                    'id' => $comp->id,
                    'product_id' => $comp->product_id,
                    'source' => $comp->source,
                    'price' => (float) ($comp->price ?? 0),
                    'link' => $comp->product_link,
                    'product_link' => $comp->product_link,
                    'title' => $comp->product_title,
                    'product_title' => $comp->product_title,
                    'image' => $comp->image,
                    'rating' => $comp->rating !== null ? (float) $comp->rating : null,
                    'reviews' => $comp->reviews !== null ? (int) $comp->reviews : null,
                ]),
                'lowest_price' => $lowest ? (float) $lowest->price : null,
                'total_count' => $competitors->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching Google LMP data', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to fetch LMP data: ' . $e->getMessage()], 500);
        }
    }

    public function addGoogleLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'product_id' => 'required|string',
                'source' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'product_link' => 'nullable|string',
                'product_title' => 'nullable|string',
                'search_query' => 'nullable|string',
                'image' => 'nullable|string',
            ]);

            $exists = GoogleSkuCompetitor::where('sku', $validated['sku'])
                ->where('product_id', $validated['product_id'])
                ->where('source', $validated['source'] ?? null)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'This Google offer is already saved for this SKU'], 409);
            }

            DB::beginTransaction();
            $lmp = GoogleSkuCompetitor::create([
                'sku' => $validated['sku'],
                'product_id' => $validated['product_id'],
                'source' => $validated['source'] ?? null,
                'price' => $validated['price'],
                'product_link' => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'search_query' => $validated['search_query'] ?? null,
                'image' => $validated['image'] ?? null,
                'marketplace' => 'google',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Google LMP added successfully',
                'data' => $lmp,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to add LMP: ' . $e->getMessage()], 500);
        }
    }

    public function deleteGoogleLmp(Request $request)
    {
        try {
            $id = $request->input('id');
            if (!$id || !is_numeric($id)) {
                return response()->json(['error' => 'Valid ID is required'], 400);
            }

            $lmp = GoogleSkuCompetitor::find($id);
            if (!$lmp) {
                return response()->json(['error' => 'LMP entry not found'], 404);
            }

            DB::beginTransaction();
            $lmp->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Competitor deleted successfully',
                'deleted_id' => (int) $id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to delete LMP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Import top Google Shopping results for a SKU search query into google_sku_competitors.
     */
    public function importGoogleSearch(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'search_query' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:40',
        ]);

        $fetcher = app(GoogleLivePriceFetcher::class);
        $results = $fetcher->searchShopping($validated['search_query'], 0, [
                'max_pages' => 2,
                'expand_sellers' => true,
                'expand_multiple_only' => true,
                'max_immersive_products' => min((int) ($validated['limit'] ?? 12), 12),
                'max_store_pages' => 1,
            ]);
        $imported = 0;

        foreach ($results as $item) {
            GoogleCompetitorItem::updateOrCreate(
                [
                    'search_query' => $validated['search_query'],
                    'product_id' => $item['product_id'],
                    'source' => $item['source'],
                ],
                [
                    'marketplace' => 'google',
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'link' => $item['link'],
                    'image' => $item['image'],
                    'rating' => $item['rating'],
                    'reviews' => $item['reviews'],
                    'position' => $item['position'] ?? null,
                ]
            );

            GoogleSkuCompetitor::updateOrCreate(
                [
                    'sku' => $validated['sku'],
                    'product_id' => $item['product_id'],
                    'source' => $item['source'],
                ],
                [
                    'marketplace' => 'google',
                    'search_query' => $validated['search_query'],
                    'price' => $item['price'],
                    'product_link' => $item['link'],
                    'product_title' => $item['title'],
                    'image' => $item['image'],
                    'rating' => $item['rating'],
                    'reviews' => $item['reviews'],
                ]
            );
            $imported++;
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'lowest_price' => $results[0]['price'] ?? null,
        ]);
    }
}
