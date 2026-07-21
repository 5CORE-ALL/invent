<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Temu2Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Temu2ListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.temu2_listing_variation_verify');
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
                'listings_count' => (int) Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => Temu2Pricing::query()->max('updated_at'),
                'has_listings_cache' => Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Temu 2 listings come from the temu2_pricing Excel upload (no API pull).
     * This endpoint refreshes meta from the current cache so the UI matches other pages.
     */
    public function pullListings(Request $request)
    {
        try {
            $count = (int) Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $lastPulledAt = Temu2Pricing::query()->max('updated_at');

            if ($count === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No Temu 2 listings in temu2_pricing. Upload pricing on Temu 2 Analytics first.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "Temu 2 listings ready. {$count} SKUs in temu2_pricing. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => $lastPulledAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Temu 2 Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Same SKU normalize as Temu 2 Analytics (PCS folding + space collapse).
     */
    private function normalizeSku(?string $sku): string
    {
        $sku = strtoupper(trim((string) $sku));
        $sku = str_replace("\xC2\xA0", ' ', $sku);
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
        $sku = preg_replace('/\s+/', ' ', $sku);

        return $sku;
    }

    /**
     * @return array{set: array<string, true>, empty: bool}
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];

        foreach (Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
            $norm = $this->normalizeSku($sku);
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

        $norm = $this->normalizeSku($sku);
        if ($norm === '') {
            return false;
        }

        return isset($lookup['set'][$norm]);
    }
}
