<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2Metric;
use Illuminate\Support\Facades\Artisan;
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
     * Missing = CP Master child not on the parent Temu 2 listing (goods_id group).
     * Extra   = SKU on the parent Temu 2 listing that is not a CP Master child.
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $invByNorm = $this->buildShopifyInvLookup();

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
                $childInv = 0;
                if (isset($invByNorm) && is_array($invByNorm)) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($child['sku'] ?? '');
                    if ($norm !== '' && isset($invByNorm[$norm])) {
                        $childInv = (int) round($invByNorm[$norm]);
                    }
                }
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

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) Temu2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->count(),
                'last_pulled_at' => Temu2Metric::query()->max('updated_at'),
                'has_listings_cache' => Temu2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->exists(),
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'mismatch_inv_gt0_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false && (float) ($r['INV'] ?? 0) > 0)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Temu 2 listings come from the temu2_metrics Open API sync (app:fetch-temu2-metrics).
     * This endpoint refreshes meta from the current cache so the UI matches other pages.
     */
    public function pullListings(Request $request)
    {
        try {
            Artisan::call('app:fetch-temu2-metrics', ['--only' => 'skus']);
            Artisan::call('app:fetch-temu2-metrics', ['--only' => 'goods']);
            Artisan::call('app:fetch-temu2-metrics', ['--only' => 'price']);

            $count = (int) Temu2Metric::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $lastPulledAt = Temu2Metric::query()->max('updated_at');

            if ($count === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No Temu 2 listings returned from API. Check TEMU2_* credentials / access token.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "Temu 2 listings synced from API. {$count} SKUs in temu2_metrics. Parent Vs Listed SKU updated.",
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
     * Compare CP Master children to SKUs on the parent Temu 2 listing (shared goods_id).
     *
     * @param  array<int, array{parent: string, sku: string, child_sku_available: ?bool}>  $children
     * @param  array{
     *   set: array<string, true>,
     *   empty: bool,
     *   sku_to_goods_id: array<string, string>,
     *   goods_id_to_skus: array<string, list<string>>,
     *   parent_to_goods_id: array<string, string>
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

        // Prefer PARENT-row goods_id; else most common goods_id among listed required children.
        $goodsId = null;
        $parentNorm = $this->normalizeSku($parentKey);
        if ($parentNorm !== '' && isset($lookup['parent_to_goods_id'][$parentNorm])) {
            $goodsId = $lookup['parent_to_goods_id'][$parentNorm];
        } else {
            $goodsIdCounts = [];
            foreach ($requiredNormToSku as $norm => $_sku) {
                if (! isset($lookup['sku_to_goods_id'][$norm])) {
                    continue;
                }
                $candidate = $lookup['sku_to_goods_id'][$norm];
                $goodsIdCounts[$candidate] = ($goodsIdCounts[$candidate] ?? 0) + 1;
            }
            if ($goodsIdCounts !== []) {
                arsort($goodsIdCounts);
                $goodsId = (string) array_key_first($goodsIdCounts);
            }
        }

        // No shared listing found — fall back to flat listed-SKU check (missing only).
        if ($goodsId === null || $goodsId === '') {
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
        foreach ($lookup['goods_id_to_skus'][$goodsId] ?? [] as $listedSku) {
            $trimmed = trim((string) $listedSku);
            if ($trimmed === '' || preg_match('/^PARENT\s+/i', $trimmed)) {
                continue;
            }
            $norm = $this->normalizeSku($trimmed);
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
     * @return array{
     *   set: array<string, true>,
     *   empty: bool,
     *   sku_to_goods_id: array<string, string>,
     *   goods_id_to_skus: array<string, list<string>>,
     *   parent_to_goods_id: array<string, string>
     * }
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];
        $skuToGoodsId = [];
        $goodsIdToSkus = [];
        $parentToGoodsId = [];

        $rows = Temu2Metric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'goods_id']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $goodsId = trim((string) ($row->goods_id ?? ''));
            $norm = $this->normalizeSku($sku);

            if ($norm !== '') {
                $set[$norm] = true;
                if ($goodsId !== '' && ! isset($skuToGoodsId[$norm])) {
                    $skuToGoodsId[$norm] = $goodsId;
                }
            }

            if ($goodsId !== '') {
                $goodsIdToSkus[$goodsId][] = $sku;

                if (preg_match('/^PARENT\s+(.+)$/i', $sku, $m)) {
                    $parentNorm = $this->normalizeSku(trim($m[1]));
                    if ($parentNorm !== '' && ! isset($parentToGoodsId[$parentNorm])) {
                        $parentToGoodsId[$parentNorm] = $goodsId;
                    }
                }
            }
        }

        return [
            'set' => $set,
            'empty' => empty($set),
            'sku_to_goods_id' => $skuToGoodsId,
            'goods_id_to_skus' => $goodsIdToSkus,
            'parent_to_goods_id' => $parentToGoodsId,
        ];
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

        $norm = $this->normalizeSku($sku);
        if ($norm === '') {
            return false;
        }

        return isset($lookup['set'][$norm]);
    }
}
