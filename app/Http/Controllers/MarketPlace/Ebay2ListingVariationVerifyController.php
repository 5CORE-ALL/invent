<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\Ebay2Metric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class Ebay2ListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.ebay2_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     * Missing = CP Master child not on the parent eBay listing (item_id group).
     * Extra   = SKU on the parent eBay listing that is not a CP Master child.
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
            $diff = $this->diffParentListing($parentKey, $children, $listedSkuSet);

            $requiredCount = count($children);
            $availableCount = $diff['available_count'];
            $missingSkus = $diff['missing_skus'];
            $extraSkus = $diff['extra_skus'];
            $known = $diff['known'];

            $parentMatch = $known
                ? ($availableCount === $requiredCount && count($extraSkus) === 0)
                : null;

            $label = '—';
            if ($known) {
                $label = $availableCount . '/' . $requiredCount;
                if (count($extraSkus) > 0) {
                    $label .= ' · +' . count($extraSkus) . ' extra';
                }
            }

            $formattedData[] = [
                'parent' => $parentKey,
                'is_parent' => true,
                'child_sku_required' => $requiredCount,
                'child_sku_required_label' => (string) $requiredCount,
                'child_sku_available' => $parentMatch,
                'child_sku_available_label' => $label,
                'child_sku_available_count' => $availableCount,
                'child_sku_total' => $requiredCount,
                'missing_skus' => $missingSkus,
                'extra_skus' => $extraSkus,
                'missing_count' => count($missingSkus),
                'extra_count' => count($extraSkus),
                'match_status' => $parentMatch,
            ];
        }

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) Ebay2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => Ebay2Metric::query()->max('updated_at'),
                'has_listings_cache' => Ebay2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function pullListings(Request $request)
    {
        try {
            set_time_limit(3600);

            $exitCode = Artisan::call('app:fetch-ebay-two-metrics');
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return response()->json([
                    'status' => 422,
                    'message' => $output !== ''
                        ? $output
                        : 'Failed to pull eBay 2 listings (exit code ' . $exitCode . ').',
                ], 422);
            }

            $count = (int) Ebay2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->count();

            return response()->json([
                'status' => 200,
                'message' => "Pulled eBay 2 listings. {$count} SKUs in ebay_2_metrics. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => Ebay2Metric::query()->max('updated_at'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Ebay 2 Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare CP Master children to SKUs on the parent eBay listing (shared item_id).
     *
     * @param  array<int, array{parent: string, sku: string, child_sku_available: ?bool}>  $children
     * @param  array{
     *   set: array<string, true>,
     *   empty: bool,
     *   sku_to_item_id: array<string, string>,
     *   item_id_to_skus: array<string, list<string>>,
     *   parent_to_item_id: array<string, string>
     * }  $lookup
     * @return array{
     *   known: bool,
     *   available_count: int,
     *   missing_skus: list<string>,
     *   extra_skus: list<string>
     * }
     */
    private function diffParentListing(string $parentKey, array $children, array $lookup): array
    {
        $requiredNormToSku = [];
        foreach ($children as $child) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($child['sku']);
            if ($norm !== '' && ! isset($requiredNormToSku[$norm])) {
                $requiredNormToSku[$norm] = $child['sku'];
            }
        }

        if ($lookup['empty']) {
            return [
                'known' => false,
                'available_count' => 0,
                'missing_skus' => [],
                'extra_skus' => [],
            ];
        }

        // Prefer the PARENT listing's item_id so we don't merge separate child listings.
        $itemId = null;
        $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($parentKey);
        if ($parentNorm !== '' && isset($lookup['parent_to_item_id'][$parentNorm])) {
            $itemId = $lookup['parent_to_item_id'][$parentNorm];
        } else {
            // Fallback: most common item_id among listed required children.
            $itemIdCounts = [];
            foreach ($requiredNormToSku as $norm => $_sku) {
                if (! isset($lookup['sku_to_item_id'][$norm])) {
                    continue;
                }
                $candidate = $lookup['sku_to_item_id'][$norm];
                $itemIdCounts[$candidate] = ($itemIdCounts[$candidate] ?? 0) + 1;
            }
            if ($itemIdCounts !== []) {
                arsort($itemIdCounts);
                $itemId = (string) array_key_first($itemIdCounts);
            }
        }

        // No shared listing found — fall back to flat listed-SKU check (missing only).
        if ($itemId === null || $itemId === '') {
            $availableCount = 0;
            $missingSkus = [];
            foreach ($requiredNormToSku as $norm => $sku) {
                if (isset($lookup['set'][$norm])) {
                    $availableCount++;
                } else {
                    $missingSkus[] = $sku;
                }
            }

            return [
                'known' => true,
                'available_count' => $availableCount,
                'missing_skus' => $missingSkus,
                'extra_skus' => [],
            ];
        }

        $listedOnParent = [];
        foreach ($lookup['item_id_to_skus'][$itemId] ?? [] as $listedSku) {
            $trimmed = trim((string) $listedSku);
            if ($trimmed === '' || preg_match('/^PARENT\s+/i', $trimmed)) {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($trimmed);
            if ($norm === '') {
                continue;
            }
            if (! isset($listedOnParent[$norm])) {
                $listedOnParent[$norm] = $trimmed;
            }
        }

        $availableCount = 0;
        $missingSkus = [];
        foreach ($requiredNormToSku as $norm => $sku) {
            if (isset($listedOnParent[$norm])) {
                $availableCount++;
            } else {
                $missingSkus[] = $sku;
            }
        }

        $extraSkus = [];
        foreach ($listedOnParent as $norm => $sku) {
            if (! isset($requiredNormToSku[$norm])) {
                $extraSkus[] = $sku;
            }
        }

        sort($missingSkus, SORT_NATURAL | SORT_FLAG_CASE);
        sort($extraSkus, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'known' => true,
            'available_count' => $availableCount,
            'missing_skus' => $missingSkus,
            'extra_skus' => $extraSkus,
        ];
    }

    /**
     * @return array{
     *   set: array<string, true>,
     *   empty: bool,
     *   sku_to_item_id: array<string, string>,
     *   item_id_to_skus: array<string, list<string>>,
     *   parent_to_item_id: array<string, string>
     * }
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];
        $skuToItemId = [];
        $itemIdToSkus = [];
        $parentToItemId = [];

        $rows = Ebay2Metric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'item_id']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $itemId = trim((string) ($row->item_id ?? ''));
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

            if ($norm !== '') {
                $set[$norm] = true;
                if ($itemId !== '' && ! isset($skuToItemId[$norm])) {
                    $skuToItemId[$norm] = $itemId;
                }
            }

            if ($itemId !== '') {
                $itemIdToSkus[$itemId][] = $sku;

                if (preg_match('/^PARENT\s+(.+)$/i', $sku, $m)) {
                    $parentNorm = ShopifySku::normalizeSkuForShopifyLookup(trim($m[1]));
                    if ($parentNorm !== '' && ! isset($parentToItemId[$parentNorm])) {
                        $parentToItemId[$parentNorm] = $itemId;
                    }
                }
            }
        }

        return [
            'set' => $set,
            'empty' => empty($set),
            'sku_to_item_id' => $skuToItemId,
            'item_id_to_skus' => $itemIdToSkus,
            'parent_to_item_id' => $parentToItemId,
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
