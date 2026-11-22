<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TikTokProduct;
use App\Support\Marketplace\MarketplaceListingPresence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TikTokListingVariationVerifyController extends Controller
{
    private const LAST_PULL_CACHE_KEY = 'tiktok_lvv_last_pull_at';

    /** Statuses that mean TikTok no longer has the product live (a row in these states is not a current listing). */
    private const NOT_LIVE_STATUSES = ['inactive', 'seller_deactivated', 'platform_deactivated', 'freeze', 'suspended'];

    public function index()
    {
        return view('market-places.tiktok_listing_variation_verify');
    }

    /**
     * Parent rows (with their child rows). Every CP Master child is required: there is no NR here, all SKUs are REQ.
     *
     * Missing = a CP Master child that is not a live TikTok 1 listing anywhere (it is checked against all live
     *           listings, so a SKU sitting on another TikTok product of the same parent is not reported as missing).
     * Excess  = a SKU on a TikTok product that holds this parent's SKUs, which is not a CP Master child.
     * Only live listings count: rows TikTok no longer lists (inactive) and rows without a product_id are ignored.
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

                return [
                    'parent' => $parent,
                    'sku' => $sku,
                    'child_sku_available' => $this->isSkuListed($sku, $listedSkuSet),
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

            $requiredCount = $diff['required_count'];
            $availableCount = $diff['available_count'];
            $missingSkus = $diff['missing_skus'];
            $extraSkus = $diff['extra_skus'];
            $known = $diff['known'];

            $parentMatch = $known
                ? (count($missingSkus) === 0 && count($extraSkus) === 0)
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
                $inv += $this->invFor($child['sku'] ?? '', $invByNorm);
            }

            // Stock sitting on the SKUs that are really missing: this is what "MISMATCH INV>0" counts.
            $missingInv = 0.0;
            foreach ($missingSkus as $missingSku) {
                $missingInv += $this->invFor($missingSku, $invByNorm);
            }

            $childPayload = [];
            foreach ($children as $child) {
                $listed = $child['child_sku_available'];
                $childInv = (int) round($this->invFor($child['sku'] ?? '', $invByNorm));
                $childPayload[] = [
                    'parent' => $parentKey,
                    'sku' => $child['sku'],
                    'is_parent' => false,
                    'INV' => $childInv,
                    'missing_inv' => ($listed === false) ? $childInv : 0,
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
                $childPayload[] = [
                    'parent' => $parentKey,
                    'sku' => $extraSku,
                    'is_parent' => false,
                    'INV' => (int) round($this->invFor($extraSku, $invByNorm)),
                    'missing_inv' => 0,
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
                'missing_inv' => (int) round($missingInv),
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
                'listings_count' => $listedSkuSet['live_count'],
                'last_pulled_at' => Cache::get(self::LAST_PULL_CACHE_KEY),
                'has_listings_cache' => ! $listedSkuSet['empty'],
                'required_parent_count' => count($parentGroups),
                'mismatch_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false)),
                'mismatch_inv_gt0_count' => count(array_filter($formattedData, fn ($r) => ($r['match_status'] ?? null) === false && (float) ($r['missing_inv'] ?? 0) > 0)),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Fetch the current live listings from TikTok 1 (live products only, one call per 100 products) and bring
     * tiktok_products in line: listed SKUs get their product_id, SKUs TikTok no longer lists become inactive.
     */
    public function pullListings(Request $request)
    {
        try {
            set_time_limit(900);

            $exitCode = Artisan::call('sync:tiktok-api-data', ['--listings-only' => true]);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return response()->json([
                    'status' => 422,
                    'message' => $output !== ''
                        ? $this->lastLines($output, 6)
                        : 'Failed to pull TikTok 1 listings (exit code ' . $exitCode . ').',
                ], 422);
            }

            $now = now()->toDateTimeString();
            Cache::forever(self::LAST_PULL_CACHE_KEY, $now);

            $lookup = $this->buildListedSkuLookup();
            $summary = '';
            if (preg_match('/Live listings synced:[^\r\n]*/u', $output, $m)) {
                $summary = trim(str_replace('✅', '', $m[0]));
            }
            $inactive = '';
            if (preg_match('/Marked inactive[^\r\n]*/u', $output, $m)) {
                $inactive = ' ' . trim($m[0]);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Pulled the current TikTok 1 listings. ' . ($summary !== '' ? $summary . '.' : $lookup['live_count'] . ' live SKUs.') . $inactive,
                'count' => $lookup['live_count'],
                'last_pulled_at' => $now,
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

    private function lastLines(string $text, int $count): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn ($l) => $l !== ''));

        return implode("\n", array_slice($lines, -$count));
    }

    private function invFor(string $sku, array $invByNorm): float
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($invByNorm[$norm])) {
            return (float) $invByNorm[$norm];
        }
        $compact = ShopifySku::compactSkuForLookup($sku);

        return ($compact !== '' && isset($invByNorm['#'.$compact])) ? (float) $invByNorm['#'.$compact] : 0.0;
    }

    /**
     * Compare the CP Master children of a parent with the live TikTok listings.
     *
     * @param  array<int, array{parent: string, sku: string, child_sku_available: ?bool}>  $children
     * @return array{
     *   known: bool,
     *   required_count: int,
     *   available_count: int,
     *   missing_skus: list<string>,
     *   extra_skus: list<string>
     * }
     */
    private function diffParentListing(string $parentKey, array $children, array $lookup): array
    {
        $requiredNormToSku = [];
        $requiredCompact = [];
        foreach ($children as $child) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($child['sku']);
            if ($norm !== '' && ! isset($requiredNormToSku[$norm])) {
                $requiredNormToSku[$norm] = $child['sku'];
                $requiredCompact[ShopifySku::compactSkuForLookup($child['sku'])] = true;
            }
        }

        if ($lookup['empty']) {
            return [
                'known' => false,
                'required_count' => count($requiredNormToSku),
                'available_count' => 0,
                'missing_skus' => [],
                'extra_skus' => [],
            ];
        }

        // Missing: not a live listing anywhere on TikTok 1.
        $availableCount = 0;
        $missingSkus = [];
        $productIds = [];
        foreach ($requiredNormToSku as $norm => $sku) {
            $compact = ShopifySku::compactSkuForLookup($sku);
            $listed = isset($lookup['set'][$norm]) || ($compact !== '' && isset($lookup['compact_set'][$compact]));
            if (! $listed) {
                $missingSkus[] = $sku;

                continue;
            }
            $availableCount++;
            $pid = $lookup['sku_to_product_id'][$norm] ?? ($lookup['compact_to_product_id'][$compact] ?? null);
            if ($pid !== null && $pid !== '') {
                $productIds[$pid] = true;
            }
        }

        // The TikTok parent row (PARENT <name>) belongs to the same listing even when no child is listed on it.
        $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($parentKey);
        if ($parentNorm !== '' && isset($lookup['parent_to_product_id'][$parentNorm])) {
            $productIds[$lookup['parent_to_product_id'][$parentNorm]] = true;
        }

        // Excess: on a TikTok product that holds this parent's SKUs, but not a CP Master child.
        $extraSkus = [];
        $seenExtra = [];
        foreach (array_keys($productIds) as $productId) {
            foreach ($lookup['product_id_to_skus'][$productId] ?? [] as $listedSku) {
                $trimmed = trim((string) $listedSku);
                if ($trimmed === '' || preg_match('/^PARENT[\s\-_]+/i', $trimmed) || ! $this->looksLikeSku($trimmed)) {
                    continue;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($trimmed);
                $compact = ShopifySku::compactSkuForLookup($trimmed);
                if ($norm === '' || isset($requiredNormToSku[$norm]) || isset($requiredCompact[$compact]) || isset($seenExtra[$norm])) {
                    continue;
                }
                $seenExtra[$norm] = true;
                $extraSkus[] = $trimmed;
            }
        }

        sort($missingSkus, SORT_NATURAL | SORT_FLAG_CASE);
        sort($extraSkus, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'known' => true,
            'required_count' => count($requiredNormToSku),
            'available_count' => $availableCount,
            'missing_skus' => $missingSkus,
            'extra_skus' => $extraSkus,
        ];
    }

    private function isLiveStatus(?string $status): bool
    {
        $status = str_replace([' ', '-'], '_', strtolower(trim((string) $status)));
        if ($status === '') {
            return true;
        }

        return ! in_array($status, self::NOT_LIVE_STATUSES, true) && ! MarketplaceListingPresence::isAbsent($status);
    }

    /**
     * Live TikTok 1 listings only: a row with a product_id whose status is not inactive / deleted.
     *
     * @return array{
     *   set: array<string, true>,
     *   compact_set: array<string, true>,
     *   empty: bool,
     *   live_count: int,
     *   sku_to_product_id: array<string, string>,
     *   compact_to_product_id: array<string, string>,
     *   product_id_to_skus: array<string, list<string>>,
     *   parent_to_product_id: array<string, string>
     * }
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];
        $compactSet = [];
        $skuToProductId = [];
        $compactToProductId = [];
        $productIdToSkus = [];
        $parentToProductId = [];
        $live = 0;

        $columns = ['sku', 'product_id'];
        $hasStatus = Schema::hasColumn((new TikTokProduct)->getTable(), 'listing_status');
        if ($hasStatus) {
            $columns[] = 'listing_status';
        }

        $rows = TikTokProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get($columns);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $productId = trim((string) ($row->product_id ?? ''));
            if ($sku === '' || $productId === '') {
                continue;
            }
            if ($hasStatus && ! $this->isLiveStatus((string) ($row->listing_status ?? ''))) {
                continue;
            }

            $live++;
            $productIdToSkus[$productId][] = $sku;

            if (preg_match('/^PARENT[\s\-_]+(.+)$/i', $sku, $m)) {
                $parentNorm = ShopifySku::normalizeSkuForShopifyLookup(trim($m[1]));
                if ($parentNorm !== '' && ! isset($parentToProductId[$parentNorm])) {
                    $parentToProductId[$parentNorm] = $productId;
                }

                continue;
            }

            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $set[$norm] = true;
                $skuToProductId[$norm] ??= $productId;
            }
            $compact = ShopifySku::compactSkuForLookup($sku);
            if ($compact !== '') {
                $compactSet[$compact] = true;
                $compactToProductId[$compact] ??= $productId;
            }
        }

        return [
            'set' => $set,
            'compact_set' => $compactSet,
            'empty' => empty($set),
            'live_count' => $live,
            'sku_to_product_id' => $skuToProductId,
            'compact_to_product_id' => $compactToProductId,
            'product_id_to_skus' => $productIdToSkus,
            'parent_to_product_id' => $parentToProductId,
        ];
    }

    /**
     * Shopify stock on hand keyed by normalized SKU (and by "#" + compact SKU for spacing differences).
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
            $sku = (string) ($row->sku ?? '');
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm === '' || isset($map[$norm])) {
                continue;
            }
            $map[$norm] = (float) ($row->inv ?? 0);
            $compact = ShopifySku::compactSkuForLookup($sku);
            if ($compact !== '' && ! isset($map['#'.$compact])) {
                $map['#'.$compact] = $map[$norm];
            }
        }

        return $map;
    }

    private function isSkuListed(string $sku, array $lookup): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return false;
        }
        if (isset($lookup['set'][$norm])) {
            return true;
        }
        $compact = ShopifySku::compactSkuForLookup($sku);

        return $compact !== '' && isset($lookup['compact_set'][$compact]);
    }

    /**
     * Skip junk rows sometimes stored in tiktok_products.sku (URLs, bare prices).
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
