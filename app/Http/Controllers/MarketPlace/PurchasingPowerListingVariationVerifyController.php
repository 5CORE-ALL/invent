<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MacysPriceData;
use App\Models\PurchasingPowerProduct;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchasingPowerListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.purchasing_power_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     * Listed source: purchasing_power_products + Mirakl offers (macys_price_data),
     * same PP Price rule as /purchasing-power-pricing (Missing when PP Price <= 0).
     * Missing = CP Master child with PP Price <= 0.
     * Extra   = listed PP SKU in this parent family that is not a CP Master child.
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

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) PurchasingPowerProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => max(
                    PurchasingPowerProduct::query()->max('updated_at'),
                    MacysPriceData::query()->max('updated_at')
                ),
                'has_listings_cache' => PurchasingPowerProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->exists()
                    || MacysPriceData::query()->exists(),
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Purchasing Power listings come from /purchasing-power-pricing
     * (purchasing_power_products + Mirakl offer prices in macys_price_data).
     */
    public function pullListings(Request $request)
    {
        try {
            $productsCount = (int) PurchasingPowerProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $offersCount = (int) MacysPriceData::query()->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('offer_sku')->where('offer_sku', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('sku')->where('sku', '!=', '');
                });
            })->count();
            $lastPulledAt = max(
                PurchasingPowerProduct::query()->max('updated_at'),
                MacysPriceData::query()->max('updated_at')
            );

            if ($productsCount === 0 && $offersCount === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No Purchasing Power listings in purchasing_power_products / macys_price_data. Update data on Purchasing Power Pricing (/purchasing-power-pricing) first.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "Purchasing Power listings ready. {$productsCount} in purchasing_power_products, {$offersCount} offer rows. Parent Vs Listed SKU updated.",
                'count' => $productsCount,
                'last_pulled_at' => $lastPulledAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Purchasing Power Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Same SKU key as /purchasing-power-pricing (uppercase trim).
     */
    private function normalizeSku(?string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim((string) $sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
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
            $norm = $this->normalizeSku($child['sku']);
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

        $parentNorm = $this->normalizeSku($parentKey);
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

        // Mirakl portal offers (preferred PP Price on /purchasing-power-pricing)
        $offerPriceByNorm = [];
        $offerSkuByNorm = [];
        foreach (MacysPriceData::query()->get(['sku', 'offer_sku', 'price']) as $row) {
            $sku = trim((string) (($row->offer_sku ?: $row->sku) ?? ''));
            if ($sku === '') {
                continue;
            }
            $norm = $this->normalizeSku($sku);
            if ($norm === '') {
                continue;
            }
            $offerPriceByNorm[$norm] = (float) ($row->price ?? 0);
            if (! isset($offerSkuByNorm[$norm])) {
                $offerSkuByNorm[$norm] = $sku;
            }
        }

        $productPriceByNorm = [];
        $productSkuByNorm = [];
        foreach (PurchasingPowerProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'price']) as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $norm = $this->normalizeSku($sku);
            if ($norm === '') {
                continue;
            }
            $productPriceByNorm[$norm] = (float) ($row->price ?? 0);
            if (! isset($productSkuByNorm[$norm])) {
                $productSkuByNorm[$norm] = $sku;
            }
        }

        // Excess candidates: PP catalog rows with effective PP Price > 0
        foreach ($productPriceByNorm as $norm => $productPrice) {
            $price = array_key_exists($norm, $offerPriceByNorm)
                ? $offerPriceByNorm[$norm]
                : $productPrice;
            if ($price <= 0) {
                continue;
            }
            $set[$norm] = true;
            $skuToListed[$norm] = $productSkuByNorm[$norm] ?? $norm;
        }

        // Offer-only SKUs still count as listed for Missing (same PP Price rule)
        foreach ($offerPriceByNorm as $norm => $offerPrice) {
            if ($offerPrice <= 0 || isset($set[$norm])) {
                continue;
            }
            $set[$norm] = true;
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
            $norm = $this->normalizeSku((string) ($row->sku ?? ''));
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

        $norm = $this->normalizeSku($sku);
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
