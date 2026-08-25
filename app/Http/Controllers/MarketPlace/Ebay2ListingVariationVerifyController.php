<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\Ebay2Metric;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay2ListingVariationVerifyController extends Controller
{
    public const DATA_CACHE_KEY = 'ebay2.listing.variation.verify.data.v5';

    public function index()
    {
        return view('market-places.ebay2_listing_variation_verify');
    }

    /**
     * Parent-only rows: Parent, INV (Shopify child sum), Required, Parent Vs Listed SKU.
     * Missing = CP Master child not on the parent eBay 2 listing (item_id group)
     *           and that child has Shopify INV > 0. Zero-INV SKUs are not missing.
     * Extra   = SKU on this parent listing that is not a CP Master child of this
     *           parent and is not a child of another CP parent.
     */
    public function data(Request $request)
    {
        $refresh = $request->boolean('refresh');

        if (! $refresh) {
            try {
                $cached = Cache::get(self::DATA_CACHE_KEY);
                if (is_array($cached) && isset($cached['data'])) {
                    return response()->json($cached);
                }
            } catch (\Throwable $e) {
                // File cache dirs may be missing after optimize:clear.
            }
        }

        $payload = $this->buildDataPayload();

        try {
            Cache::put(self::DATA_CACHE_KEY, $payload, now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore cache write failures
        }

        return response()->json($payload);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function buildDataPayload(): array
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $invByNorm = $this->buildShopifyInvLookup();

        $parentGroups = [];
        $pmParentByNorm = [];
        $childRowsCount = 0;

        $pmRows = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->get(['parent', 'sku']);

        foreach ($pmRows as $pm) {
            $parent = trim((string) ($pm->parent ?? ''));
            $sku = trim((string) ($pm->sku ?? ''));
            if ($parent === '' || $sku === '' || preg_match('/^PARENT/i', $sku)) {
                continue;
            }

            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && ! isset($pmParentByNorm[$norm])) {
                $pmParentByNorm[$norm] = $parent;
            }
            $available = $listedSkuSet['empty']
                ? null
                : ($norm !== '' && isset($listedSkuSet['set'][$norm]));

            $parentGroups[$parent][] = [
                'parent' => $parent,
                'sku' => $sku,
                'norm' => $norm,
                'child_sku_available' => $available,
            ];
            $childRowsCount++;
        }

        $formattedData = [];
        foreach ($parentGroups as $parentKey => $children) {
            $diff = $this->diffParentListing($parentKey, $children, $listedSkuSet, $pmParentByNorm);

            $requiredCount = count($children);
            $extraSkus = $diff['extra_skus'];
            $known = $diff['known'];

            // Parent Missing = unlisted children with INV > 0 only.
            $missingSkus = [];
            if ($known) {
                foreach ($diff['missing_skus'] as $sku) {
                    $missNorm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                    $missInv = ($missNorm !== '' && isset($invByNorm[$missNorm]))
                        ? (float) $invByNorm[$missNorm]
                        : 0.0;
                    if ($missInv > 0) {
                        $missingSkus[] = $sku;
                    }
                }
            }

            $availableCount = $known
                ? ($requiredCount - count($missingSkus))
                : (int) $diff['available_count'];

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

            $inv = 0.0;
            $childPayload = [];
            foreach ($children as $child) {
                $listed = $child['child_sku_available'];
                $norm = $child['norm'] ?? '';
                $childInv = ($norm !== '' && isset($invByNorm[$norm]))
                    ? (int) round($invByNorm[$norm])
                    : 0;
                $inv += $childInv;

                $childPayload[] = [
                    'parent' => $parentKey,
                    'sku' => $child['sku'],
                    'is_parent' => false,
                    'INV' => $childInv,
                    'child_sku_required' => 1,
                    'child_sku_required_label' => '1',
                    'child_sku_available' => $listed,
                    'child_sku_available_label' => $listed === null ? '—' : ($listed ? 'Listed' : 'Missing'),
                    'child_sku_available_count' => $listed ? 1 : 0,
                    'child_sku_total' => 1,
                    'missing_skus' => ($listed === false) ? [$child['sku']] : [],
                    'extra_skus' => [],
                    'missing_count' => ($listed === false) ? 1 : 0,
                    'extra_count' => 0,
                    'match_status' => $listed,
                ];
            }

            foreach ($extraSkus as $extraSku) {
                $extraNorm = ShopifySku::normalizeSkuForShopifyLookup($extraSku);
                $extraInv = ($extraNorm !== '' && isset($invByNorm[$extraNorm]))
                    ? (int) round($invByNorm[$extraNorm])
                    : 0;
                $childPayload[] = [
                    'parent' => $parentKey,
                    'sku' => $extraSku,
                    'is_parent' => false,
                    'INV' => $extraInv,
                    'child_sku_required' => 0,
                    'child_sku_required_label' => '0',
                    'child_sku_available' => false,
                    'child_sku_available_label' => 'Excess',
                    'child_sku_available_count' => 1,
                    'child_sku_total' => 0,
                    'missing_skus' => [],
                    'extra_skus' => [$extraSku],
                    'missing_count' => 0,
                    'extra_count' => 1,
                    'match_status' => false,
                ];
            }

            $formattedData[] = [
                'parent' => $parentKey,
                'is_parent' => true,
                '_children' => $childPayload,
                'INV' => (int) round($inv),
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

        $listingsCount = (int) ($listedSkuSet['listings_count'] ?? 0);

        return [
            'data' => $formattedData,
            'meta' => [
                'listings_count' => $listingsCount,
                'last_pulled_at' => $listedSkuSet['last_pulled_at'] ?? null,
                'has_listings_cache' => $listingsCount > 0,
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'mismatch_inv_gt0_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false && (float) ($r['INV'] ?? 0) > 0)),
                'required_child_count' => $childRowsCount,
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ];
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

            $this->forgetDataCache();

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
     * Compare CP Master children to SKUs on the parent eBay 2 listing (shared item_id).
     *
     * @param  array<int, array{parent: string, sku: string, norm?: string, child_sku_available: ?bool}>  $children
     * @param  array{
     *   set: array<string, true>,
     *   empty: bool,
     *   sku_to_item_id: array<string, string>,
     *   item_id_to_skus: array<string, list<string>>,
     *   parent_to_item_id: array<string, string>
     * }  $lookup
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
            $norm = $child['norm'] ?? ShopifySku::normalizeSkuForShopifyLookup($child['sku']);
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

        $childPrefix = $this->commonPrefix(array_keys($requiredNormToSku));
        $extraSkus = [];
        foreach ($listedOnParent as $norm => $sku) {
            if (isset($requiredNormToSku[$norm])) {
                continue;
            }

            $pmParent = $pmParentByNorm[$norm] ?? null;
            // Belongs to another CP parent — not excess for this group.
            if ($pmParent !== null && $pmParent !== $parentKey) {
                continue;
            }
            if ($pmParent === $parentKey) {
                continue;
            }

            // Orphan listed SKU: only extra when it belongs to this parent family.
            if ($this->skuBelongsToParentFamily($norm, $parentNorm, $childPrefix)) {
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
     *   parent_to_item_id: array<string, string>,
     *   listings_count: int,
     *   last_pulled_at: ?string
     * }
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];
        $skuToItemId = [];
        $itemIdToSkus = [];
        $parentToItemId = [];
        $listingsCount = 0;
        $lastPulledAt = null;

        $rows = DB::table('ebay_2_metrics')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'item_id', 'updated_at']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $listingsCount++;
            $updatedAt = $row->updated_at ?? null;
            if ($updatedAt !== null && $updatedAt !== '') {
                $updatedAt = (string) $updatedAt;
                if ($lastPulledAt === null || $updatedAt > $lastPulledAt) {
                    $lastPulledAt = $updatedAt;
                }
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
            'listings_count' => $listingsCount,
            'last_pulled_at' => $lastPulledAt,
        ];
    }

    /**
     * Shopify stock on hand keyed by normalized SKU (same source as ebay2 tabulator INV).
     *
     * @return array<string, float>
     */
    private function buildShopifyInvLookup(): array
    {
        $map = [];

        $rows = DB::table('shopify_skus')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'inv']);

        foreach ($rows as $row) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
            if ($norm === '' || isset($map[$norm])) {
                continue;
            }
            $map[$norm] = (float) ($row->inv ?? 0);
        }

        return $map;
    }

    private function forgetDataCache(): void
    {
        try {
            Cache::forget(self::DATA_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
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
