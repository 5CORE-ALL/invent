<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TikTokProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TikTokListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.tiktok_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();

        $childRows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['parent', 'sku'])
            ->map(function ($pm) use ($listedSkuSet) {
                $parent = trim((string) ($pm->parent ?? ''));
                $sku = trim((string) ($pm->sku ?? ''));
                $available = $this->isSkuListed($sku, $listedSkuSet);

                return [
                    'parent' => $parent,
                    'sku' => $sku,
                    'child_sku_available' => $available,
                ];
            })
            ->values()
            ->all();

        $parentGroups = [];
        foreach ($childRows as $row) {
            if ($row['parent'] === '') {
                continue;
            }
            $parentGroups[$row['parent']][] = $row;
        }

        $formattedData = [];
        foreach ($parentGroups as $parentKey => $children) {
            $known = array_filter($children, fn ($c) => $c['child_sku_available'] !== null);
            $availableCount = count(array_filter($known, fn ($c) => $c['child_sku_available'] === true));
            $requiredCount = count($children);
            $knownCount = count($known);
            $parentMatch = $knownCount > 0 ? ($availableCount === $requiredCount) : null;

            $formattedData[] = [
                'parent' => $parentKey,
                'is_parent' => true,
                'child_sku_required' => $requiredCount,
                'child_sku_required_label' => (string) $requiredCount,
                'child_sku_available' => $parentMatch,
                'child_sku_available_label' => $knownCount > 0
                    ? ($availableCount . '/' . $requiredCount)
                    : '—',
                'child_sku_available_count' => $availableCount,
                'child_sku_total' => $requiredCount,
                'match_status' => $parentMatch,
            ];
        }

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => TikTokProduct::query()->max('updated_at'),
                'has_listings_cache' => TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function pullListings(Request $request)
    {
        try {
            set_time_limit(3600);

            $exitCode = Artisan::call('sync:tiktok-api-data');
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return response()->json([
                    'status' => 422,
                    'message' => $output !== ''
                        ? $output
                        : 'Failed to pull TikTok 1 listings (exit code ' . $exitCode . ').',
                ], 422);
            }

            $count = (int) TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count();

            return response()->json([
                'status' => 200,
                'message' => "Pulled TikTok 1 listings. {$count} SKUs in tiktok_products. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => TikTokProduct::query()->max('updated_at'),
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{set: array<string, true>, empty: bool}
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];

        foreach (TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $set[$norm] = true;
            }
        }

        return [
            'set' => $set,
            'empty' => empty($set),
        ];
    }

    /**
     * @param  array{set: array<string, true>, empty: bool}  $lookup
     */
    private function isSkuListed(string $sku, array $lookup): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return false;
        }

        return isset($lookup['set'][$norm]);
    }
}
