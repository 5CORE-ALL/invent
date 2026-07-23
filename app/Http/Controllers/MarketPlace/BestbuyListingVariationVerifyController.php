<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyPriceData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BestbuyListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.bestbuy_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     * Listed source: bestbuy_usa_products + bestbuy_price_data (same as /bestbuy-pricing).
     * Missing = CP Master child not listed on Best Buy.
     * Extra   = listed Best Buy SKU in this parent family that is not a CP Master child.
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $pmParentByNorm = $this->buildProductMasterParentLookup();

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
            $diff = $this->diffParentListing($parentKey, $children, $listedSkuSet, $pmParentByNorm);

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

        $productsCount = (int) BestbuyUsaProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
        $priceDataCount = (int) BestbuyPriceData::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
        $lastPulledAt = collect([
            BestbuyUsaProduct::query()->max('updated_at'),
            BestbuyPriceData::query()->max('updated_at'),
        ])->filter()->max();

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => max($productsCount, $priceDataCount),
                'last_pulled_at' => $lastPulledAt,
                'has_listings_cache' => $productsCount > 0 || $priceDataCount > 0,
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Best Buy listings come from /bestbuy-pricing (bestbuy_usa_products + bestbuy_price_data upload).
     * This endpoint refreshes meta from the current cache.
     */
    public function pullListings(Request $request)
    {
        try {
            $productsCount = (int) BestbuyUsaProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $priceDataCount = (int) BestbuyPriceData::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $count = max($productsCount, $priceDataCount);
            $lastPulledAt = collect([
                BestbuyUsaProduct::query()->max('updated_at'),
                BestbuyPriceData::query()->max('updated_at'),
            ])->filter()->max();

            if ($count === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No Best Buy listings in bestbuy_usa_products / bestbuy_price_data. Update data on Best Buy Pricing (/bestbuy-pricing) first.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "Best Buy listings ready. {$productsCount} in bestbuy_usa_products, {$priceDataCount} in bestbuy_price_data. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => $lastPulledAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bestbuy Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<int, array{parent: string, sku: string, child_sku_available: ?bool}>  $children
     * @param  array{set: array<string, true>, empty: bool, sku_to_listed: array<string, string>}  $lookup
     * @param  array<string, string>  $pmParentByNorm
     * @return array{
     *   known: bool,
     *   available_count: int,
     *   missing_skus: list<string>,
     *   extra_skus: list<string>
     * }
     */
    private function diffParentListing(string $parentKey, array $children, array $lookup, array $pmParentByNorm): array
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

        $availableCount = 0;
        $missingSkus = [];
        foreach ($requiredNormToSku as $norm => $sku) {
            if (isset($lookup['set'][$norm])) {
                $availableCount++;
            } else {
                $missingSkus[] = $sku;
            }
        }

        $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($parentKey);
        $childPrefix = $this->commonPrefix(array_keys($requiredNormToSku));
        $extraSkus = [];

        foreach ($lookup['sku_to_listed'] as $norm => $listedSku) {
            if (isset($requiredNormToSku[$norm])) {
                continue;
            }
            if (preg_match('/^PARENT/i', trim((string) $listedSku))) {
                continue;
            }

            $pmParent = $pmParentByNorm[$norm] ?? null;
            if ($pmParent !== null && $pmParent !== $parentKey) {
                continue;
            }
            if ($pmParent === $parentKey) {
                continue;
            }

            if ($this->skuBelongsToParentFamily($norm, $parentNorm, $childPrefix)) {
                $extraSkus[] = $listedSku;
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
     * @return array{set: array<string, true>, empty: bool, sku_to_listed: array<string, string>}
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];
        $skuToListed = [];

        $addSku = function (string $sku) use (&$set, &$skuToListed): void {
            $sku = trim($sku);
            if ($sku === '') {
                return;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm === '') {
                return;
            }
            $set[$norm] = true;
            if (! isset($skuToListed[$norm])) {
                $skuToListed[$norm] = $sku;
            }
        };

        foreach (BestbuyUsaProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
            $addSku((string) $sku);
        }

        foreach (BestbuyPriceData::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
            $addSku((string) $sku);
        }

        return [
            'set' => $set,
            'empty' => empty($set),
            'sku_to_listed' => $skuToListed,
        ];
    }

    /**
     * @return array<string, string> normalized sku => parent
     */
    private function buildProductMasterParentLookup(): array
    {
        $map = [];

        $rows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->get(['sku', 'parent']);

        foreach ($rows as $row) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
            if ($norm === '' || isset($map[$norm])) {
                continue;
            }
            $map[$norm] = trim((string) ($row->parent ?? ''));
        }

        return $map;
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
     * @param  list<string>  $norms
     */
    private function commonPrefix(array $norms): string
    {
        if ($norms === []) {
            return '';
        }

        $prefix = $norms[0];
        foreach ($norms as $norm) {
            $max = min(strlen($prefix), strlen($norm));
            $i = 0;
            while ($i < $max && $prefix[$i] === $norm[$i]) {
                $i++;
            }
            $prefix = substr($prefix, 0, $i);
            if ($prefix === '') {
                return '';
            }
        }

        return $prefix;
    }

    private function skuBelongsToParentFamily(string $skuNorm, string $parentNorm, string $childPrefix): bool
    {
        if ($parentNorm !== '' && str_starts_with($skuNorm, $parentNorm)) {
            return true;
        }

        // Compact form (no spaces) — Shopify normalize keeps spaces; also match collapsed.
        $skuCompact = str_replace(' ', '', $skuNorm);
        $parentCompact = str_replace(' ', '', $parentNorm);
        if ($parentCompact !== '' && str_starts_with($skuCompact, $parentCompact)) {
            return true;
        }

        if (strlen($childPrefix) >= 4 && str_starts_with($skuNorm, $childPrefix)) {
            return true;
        }

        return false;
    }
}
