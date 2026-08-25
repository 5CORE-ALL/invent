<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingRaw;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Support\Marketplace\AmazonListingCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AmzListingVariationVerifyController extends Controller
{
    /** Shared with VariationsVerifyMasterController::fetchMismatchCount (route amz.listing.variation.verify). */
    public const SIDEBAR_MISMATCH_CACHE_KEY = 'variations_verify_masters.mismatch.amz.listing.variation.verify';

    public function index()
    {
        return view('market-places.amz_listing_variation_verify');
    }

    /**
     * Sidebar badge — Amz LVV mismatch_count (same as page MISMATCH badge).
     * Populated when this page's data() or Variations Verify Masters runs.
     */
    public static function mismatchCountForSidebar(): int
    {
        try {
            $cached = Cache::get(self::SIDEBAR_MISMATCH_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // File cache dirs may be missing mid-request after optimize:clear.
        }

        return 0;
    }

    /**
     * Live MISMATCH count (same as page badge). Refreshes sidebar cache.
     */
    public static function freshMismatchCount(): int
    {
        try {
            $controller = app(self::class);
            $response = $controller->data(Request::create('/', 'GET'));
            $payload = $response instanceof \Illuminate\Http\JsonResponse
                ? $response->getData(true)
                : [];

            return (int) ($payload['meta']['mismatch_count'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Amz LVV freshMismatchCount failed: '.$e->getMessage());

            return self::mismatchCountForSidebar();
        }
    }

    /**
     * Parent-only rows: Parent, Required, Parent Vs Listed SKU.
     * Missing = CP Master child not listed on Amazon.
     * Extra   = listed Amazon SKU in this parent family that is not a CP Master child.
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $pmParentByNorm = $this->buildProductMasterParentLookup();
        $invByNorm = $this->buildShopifyInvLookup();

        $productMasters = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['parent', 'sku', 'Values']);
        $nrlSet = AmazonListingCounts::nrlSetForSkus(
            $productMasters->pluck('sku')->filter()->unique()->values()->all()
        );

        $childRows = $productMasters
            ->map(function ($pm) use ($listedSkuSet, $invByNorm, $nrlSet) {
                $parent = trim((string) ($pm->parent ?? ''));
                $sku = trim((string) ($pm->sku ?? ''));
                $available = $this->isSkuListed($sku, $listedSkuSet);
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                $inv = ($norm !== '' && isset($invByNorm[$norm])) ? (float) $invByNorm[$norm] : 0.0;
                $isComing = $pm->isComing();
                $isNrl = AmazonListingCounts::skuIsNrl($sku, $nrlSet);
                $isZeroInv = $inv <= 0;

                return [
                    'parent' => $parent,
                    'sku' => $sku,
                    'inv' => $inv,
                    'child_sku_available' => $available,
                    'is_coming' => $isComing,
                    'is_nrl' => $isNrl,
                    'is_zero_inv' => $isZeroInv,
                    'skip_listing_required' => $isComing || $isNrl || $isZeroInv,
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

            $requiredCount = count(array_filter($children, fn ($c) => empty($c['skip_listing_required'])));
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

            $inv = 0.0;
            foreach ($children as $child) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($child['sku'] ?? '');
                if ($norm !== '' && isset($invByNorm[$norm])) {
                    $inv += $invByNorm[$norm];
                }
            }

            $childPayload = [];
            foreach ($children as $child) {
                $listed = $child['child_sku_available'];
                $isComing = ! empty($child['is_coming']);
                $isNrl = ! empty($child['is_nrl']);
                $isZeroInv = ! empty($child['is_zero_inv']);
                $skipRequired = ! empty($child['skip_listing_required']);
                $childInv = (int) round((float) ($child['inv'] ?? 0));
                $isMissing = ! $skipRequired && $listed === false;
                $childPayload[] = [
                    'parent' => $parentKey,
                    'sku' => $child['sku'],
                    'is_parent' => false,
                    'INV' => $childInv,
                    'is_coming' => $isComing,
                    'is_nrl' => $isNrl,
                    'is_zero_inv' => $isZeroInv,
                    'child_sku_required' => $skipRequired ? 0 : 1,
                    'child_sku_required_label' => $skipRequired ? '0' : '1',
                    'child_sku_available' => $listed,
                    'child_sku_available_label' => $isComing
                        ? 'Coming'
                        : ($isNrl && $listed === false
                            ? 'NRL'
                            : ($isZeroInv && $listed === false
                                ? 'INV ≤0'
                                : ($listed === null ? '—' : ($listed ? 'Listed' : 'Missing')))),
                    'child_sku_available_count' => (! $skipRequired && $listed) ? 1 : 0,
                    'child_sku_total' => $skipRequired ? 0 : 1,
                    'missing_skus' => $isMissing ? [$child['sku']] : [],
                    'extra_skus' => [],
                    'missing_count' => $isMissing ? 1 : 0,
                    'extra_count' => 0,
                    'match_status' => $skipRequired ? null : $listed,
                ];
            }
            foreach ($extraSkus as $extraSku) {
                $extraInv = 0;
                if (isset($invByNorm) && is_array($invByNorm)) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($extraSku);
                    if ($norm !== '' && isset($invByNorm[$norm])) {
                        $extraInv = (int) round($invByNorm[$norm]);
                    }
                }
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

        $mismatchCount = count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false));
        $mismatchInvGt0Count = count(array_filter(
            $formattedData,
            fn ($r) => ($r['match_status'] ?? null) === false && (float) ($r['INV'] ?? 0) > 0
        ));

        try {
            Cache::put(self::SIDEBAR_MISMATCH_CACHE_KEY, $mismatchCount, now()->addDay());
        } catch (\Throwable $e) {
            // ignore cache write failures
        }

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) AmazonListingRaw::query()->count(),
                'last_pulled_at' => AmazonListingRaw::query()->max('report_imported_at'),
                'has_listings_cache' => AmazonListingRaw::query()->exists()
                    || AmazonDatasheet::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => $mismatchCount,
                'mismatch_inv_gt0_count' => $mismatchInvGt0Count,
                'required_child_count' => count(array_filter($childRows, fn ($c) => empty($c['skip_listing_required']))),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function pullListings(Request $request)
    {
        try {
            set_time_limit(3600);

            $service = new AmazonSpApiService();
            $result = $service->fetchAndStoreListingsReport();

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'status' => 422,
                    'message' => $result['message'] ?? 'Failed to pull Amazon listings.',
                ], 422);
            }

            $count = (int) ($result['count'] ?? 0);

            return response()->json([
                'status' => 200,
                'message' => "Pulled {$count} Amazon listings. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => AmazonListingRaw::query()->max('report_imported_at'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Amz Listing Variation Verify: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<int, array{parent: string, sku: string, child_sku_available: ?bool, is_coming?: bool, is_nrl?: bool, is_zero_inv?: bool, skip_listing_required?: bool}>  $children
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
            $norm = AmazonDatasheet::normalizeSkuForLookup($child['sku']);
            if ($norm !== '' && ! isset($requiredNormToSku[$norm])) {
                $requiredNormToSku[$norm] = [
                    'sku' => $child['sku'],
                    'is_coming' => ! empty($child['is_coming']),
                    'skip_listing_required' => ! empty($child['skip_listing_required'])
                        || ! empty($child['is_coming'])
                        || ! empty($child['is_nrl'])
                        || ! empty($child['is_zero_inv']),
                ];
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
        foreach ($requiredNormToSku as $norm => $info) {
            $sku = $info['sku'];
            $skipRequired = ! empty($info['skip_listing_required']);
            if (isset($lookup['set'][$norm])) {
                if (! $skipRequired) {
                    $availableCount++;
                }
            } elseif (! $skipRequired) {
                $missingSkus[] = $sku;
            }
        }

        $parentNorm = AmazonDatasheet::normalizeSkuForLookup($parentKey);
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
            // Belongs to another CP parent — not excess for this group.
            if ($pmParent !== null && $pmParent !== $parentKey) {
                continue;
            }
            // Same parent in PM would already be in required; skip if somehow present.
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

        foreach (AmazonListingRaw::query()->whereNotNull('seller_sku')->where('seller_sku', '!=', '')->pluck('seller_sku') as $sellerSku) {
            $sku = trim((string) $sellerSku);
            if ($sku === '') {
                continue;
            }
            $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
            if ($norm === '') {
                continue;
            }
            $set[$norm] = true;
            if (! isset($skuToListed[$norm])) {
                $skuToListed[$norm] = $sku;
            }
        }

        if (empty($set)) {
            foreach (AmazonDatasheet::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '') {
                    continue;
                }
                $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
                if ($norm === '') {
                    continue;
                }
                $set[$norm] = true;
                if (! isset($skuToListed[$norm])) {
                    $skuToListed[$norm] = $sku;
                }
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
            $norm = AmazonDatasheet::normalizeSkuForLookup((string) ($row->sku ?? ''));
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
    /**
     * Shopify stock on hand keyed by normalized SKU.
     *
     * @return array<string, float>
     */
    private function buildShopifyInvLookup(): array
    {
        $map = [];

        $rows = ShopifySku::query()
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

    private function isSkuListed(string $sku, array $lookup): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
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

        // Avoid tiny prefixes that match unrelated SKUs (e.g. "CS").
        if (strlen($childPrefix) >= 4 && str_starts_with($skuNorm, $childPrefix)) {
            return true;
        }

        return false;
    }
}
