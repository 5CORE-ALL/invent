<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TikTokProductTwo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TikTok2ListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.tiktok2_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     * Missing = CP Master child not on the parent TikTok listing (product_id group).
     * Extra   = SKU on the parent TikTok listing that is not a CP Master child.
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
                'listings_count' => (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => TikTokProductTwo::query()->max('updated_at'),
                'has_listings_cache' => TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * TikTok 2 listings come from CSV upload on /tiktok-2-pricing (no API pull).
     * This endpoint refreshes meta from the current tiktok_products_two cache.
     */
    public function pullListings(Request $request)
    {
        try {
            $count = (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $lastPulledAt = TikTokProductTwo::query()->max('updated_at');

            if ($count === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No TikTok 2 listings in tiktok_products_two. Upload CSV on TikTok 2 Shop - Analytics (/tiktok-2-pricing) first.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "TikTok 2 listings ready. {$count} SKUs in tiktok_products_two. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => $lastPulledAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok 2 Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare CP Master children to SKUs on the parent TikTok listing (shared product_id).
     *
     * @param  array<int, array{parent: string, sku: string, child_sku_available: ?bool}>  $children
     * @param  array{
     *   set: array<string, true>,
     *   empty: bool,
     *   sku_to_product_id: array<string, string>,
     *   product_id_to_skus: array<string, list<string>>,
     *   parent_to_product_id: array<string, string>
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

        // Prefer a PARENT-row product_id; else most common product_id among listed children.
        $productId = null;
        $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($parentKey);
        if ($parentNorm !== '' && isset($lookup['parent_to_product_id'][$parentNorm])) {
            $productId = $lookup['parent_to_product_id'][$parentNorm];
        } else {
            $productIdCounts = [];
            foreach ($requiredNormToSku as $norm => $_sku) {
                if (! isset($lookup['sku_to_product_id'][$norm])) {
                    continue;
                }
                $candidate = $lookup['sku_to_product_id'][$norm];
                $productIdCounts[$candidate] = ($productIdCounts[$candidate] ?? 0) + 1;
            }
            if ($productIdCounts !== []) {
                arsort($productIdCounts);
                $productId = (string) array_key_first($productIdCounts);
            }
        }

        // No shared listing found — fall back to flat listed-SKU check (missing only).
        if ($productId === null || $productId === '') {
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
        foreach ($lookup['product_id_to_skus'][$productId] ?? [] as $listedSku) {
            $trimmed = trim((string) $listedSku);
            if ($trimmed === '' || preg_match('/^PARENT\s+/i', $trimmed) || ! $this->looksLikeSku($trimmed)) {
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
     *   sku_to_product_id: array<string, string>,
     *   product_id_to_skus: array<string, list<string>>,
     *   parent_to_product_id: array<string, string>
     * }
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];
        $skuToProductId = [];
        $productIdToSkus = [];
        $parentToProductId = [];

        $rows = TikTokProductTwo::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'product_id']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $productId = trim((string) ($row->product_id ?? ''));
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

            if ($norm !== '') {
                $set[$norm] = true;
                if ($productId !== '' && ! isset($skuToProductId[$norm])) {
                    $skuToProductId[$norm] = $productId;
                }
            }

            if ($productId !== '') {
                $productIdToSkus[$productId][] = $sku;

                if (preg_match('/^PARENT\s+(.+)$/i', $sku, $m)) {
                    $parentNorm = ShopifySku::normalizeSkuForShopifyLookup(trim($m[1]));
                    if ($parentNorm !== '' && ! isset($parentToProductId[$parentNorm])) {
                        $parentToProductId[$parentNorm] = $productId;
                    }
                }
            }
        }

        return [
            'set' => $set,
            'empty' => empty($set),
            'sku_to_product_id' => $skuToProductId,
            'product_id_to_skus' => $productIdToSkus,
            'parent_to_product_id' => $parentToProductId,
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

    /**
     * Skip junk rows sometimes stored in tiktok_products_two.sku (URLs, bare prices).
     */
    private function looksLikeSku(string $sku): bool
    {
        if (preg_match('#^https?://#i', $sku)) {
            return false;
        }
        if (preg_match('/^\d+(\.\d+)?$/', $sku)) {
            return false;
        }

        return true;
    }
}
