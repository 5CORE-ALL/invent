<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\SheinPricingPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SheinListingVariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.shein_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     * Listed source: shein_pricing_prices (same Missing rule as /shein-pricing).
     * Missing = CP Master child not listed on Shein (no row or special_offer_price <= 0).
     * Extra   = listed Shein SKU in this parent family that is not a CP Master child.
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
                'listings_count' => (int) SheinPricingPrice::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => SheinPricingPrice::query()->max('updated_at'),
                'has_listings_cache' => SheinPricingPrice::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Shein listings come from Shein API sync on /shein-pricing (shein_pricing_prices).
     */
    public function pullListings(Request $request)
    {
        try {
            $count = (int) SheinPricingPrice::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $lastPulledAt = SheinPricingPrice::query()->max('updated_at');

            if ($count === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No Shein listings in shein_pricing_prices. Sync from API on Shein Pricing (/shein-pricing) first.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "Shein listings ready. {$count} SKUs in shein_pricing_prices. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => $lastPulledAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shein Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Same normalize as SheinController::normalizeSheinSkuExact.
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

        $rows = SheinPricingPrice::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'price', 'special_offer_price']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $spOffer = (float) ($row->special_offer_price ?? 0);
            $price = (float) ($row->price ?? 0);
            // Match /shein-pricing Missing: listed when special_offer_price > 0
            // (upload copies price into special_offer when special offer is blank).
            if ($spOffer <= 0 && $price <= 0) {
                continue;
            }
            if ($spOffer <= 0) {
                continue;
            }

            $norm = $this->normalizeSku($sku);
            if ($norm === '') {
                continue;
            }
            $set[$norm] = true;
            if (! isset($skuToListed[$norm])) {
                $skuToListed[$norm] = $sku;
            }
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
