<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\GoogleCompetitorItem;
use App\Models\GoogleSkuCompetitor;
use App\Services\GoogleLivePriceFetcher;
use App\Services\LmpSkuGroupService;
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

            $linkedSkus = $request->input('linked_lmp_skus', []);
            if (! is_array($linkedSkus)) {
                $linkedSkus = $linkedSkus !== null && $linkedSkus !== ''
                    ? [trim((string) $linkedSkus)]
                    : [];
            }

            $groupSkus = $this->resolveGoogleLmpGroupSkus($sku, $linkedSkus);

            $competitors = GoogleSkuCompetitor::getCompetitorsForSkus($groupSkus, 'google');

            // Live SerpApi refresh is opt-in (?refresh=1). Default is DB-only so LMP modal
            // opens quickly; background jobs keep prices fresh.
            if ($request->boolean('refresh')) {
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

                $competitors = GoogleSkuCompetitor::getCompetitorsForSkus($groupSkus, 'google');
            }

            $lowest = $competitors->first();

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'linked_lmp_skus' => $groupSkus,
                'competitors' => $competitors->map(fn ($comp) => [
                    'id' => $comp->id,
                    'sku' => $comp->sku,
                    'product_id' => $comp->product_id,
                    'source' => $comp->source,
                    'price' => (float) ($comp->price ?? 0),
                    'ignored' => (bool) ($comp->ignored ?? false),
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

            $productId = trim((string) ($lmp->product_id ?? ''));
            // Only remove Sku-Link copies of this listing inside the same LMP group.
            // Matching on product_id alone used to delete that ID on every SKU in the table
            // (manual adds often reuse short IDs like 1 / 2 / a model number).
            $toDelete = collect([$lmp]);
            if ($productId !== '') {
                $groupSkus = $this->resolveGoogleLmpGroupSkus((string) $lmp->sku);
                $groupKeys = [];
                foreach ($groupSkus as $groupSku) {
                    $key = GoogleSkuCompetitor::normalizeSkuKey((string) $groupSku);
                    if ($key !== '') {
                        $groupKeys[$key] = true;
                    }
                }

                $candidates = GoogleSkuCompetitor::query()
                    ->where('product_id', $productId)
                    ->get();
                $found = $candidates->filter(function ($row) use ($groupKeys) {
                    $key = GoogleSkuCompetitor::normalizeSkuKey((string) $row->sku);

                    return $key !== '' && isset($groupKeys[$key]);
                })->values();
                $toDelete = $found->isNotEmpty() ? $found : collect([$lmp]);
            }

            $deletedIds = [];
            foreach ($toDelete as $row) {
                $deletedIds[] = (int) $row->id;
                $row->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($deletedIds) > 1
                    ? ('Competitor deleted successfully (' . count($deletedIds) . ' linked rows)')
                    : 'Competitor deleted successfully',
                'deleted_id' => (int) $id,
                'deleted_ids' => $deletedIds,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to delete LMP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing Google LMP competitor price/link.
     */
    public function updateGoogleLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'price' => 'required|numeric|min:0',
                'product_link' => 'nullable|string',
                'product_id' => 'nullable|string',
            ]);

            $lmp = GoogleSkuCompetitor::find($validated['id']);
            if (!$lmp) {
                return response()->json(['error' => 'LMP entry not found'], 404);
            }

            DB::beginTransaction();
            $lmp->price = $validated['price'];
            if (array_key_exists('product_link', $validated)) {
                $lmp->product_link = $validated['product_link'] ?: null;
            }
            if (!empty($validated['product_id'])) {
                $lmp->product_id = $validated['product_id'];
            }
            $lmp->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'LMP updated successfully',
                'data' => $lmp,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to update LMP: ' . $e->getMessage()], 500);
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
        try {
            $results = $fetcher->searchShopping($validated['search_query'], 0, [
                'max_pages' => 2,
                'expand_sellers' => true,
                'expand_multiple_only' => true,
                'max_immersive_products' => min((int) ($validated['limit'] ?? 12), 12),
                'max_store_pages' => 1,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google Shopping search failed: ' . $e->getMessage(),
            ], 500);
        }
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

    /**
     * @param  list<string>  $linkedSkus
     * @return list<string>
     */
    private function resolveGoogleLmpGroupSkus(string $sku, array $linkedSkus = []): array
    {
        $sku = trim($sku);
        $groupSkus = $sku !== '' ? [$sku] : [];

        try {
            $lmpGroupService = new LmpSkuGroupService();
            $seed = array_values(array_filter(array_map(
                static fn ($value) => trim((string) $value),
                array_merge([$sku], $linkedSkus)
            )));
            $lmpGroupService->prepareForSkus($seed);
            $resolved = $sku !== '' ? $lmpGroupService->groupContaining($sku) : [];
            if (! empty($resolved)) {
                $groupSkus = $resolved;
            }
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService in Google LMP failed: '.$e->getMessage());
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            array_merge($groupSkus, $linkedSkus, [$sku])
        ))));
    }
}
