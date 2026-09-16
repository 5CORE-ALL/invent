<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\WarmB5cB2bLiveListingsCache;
use App\Models\B5cB2bProduct;
use App\Models\ShopifySku;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Shopify-first listings page + mismatch inventory sync for Business 5 Core B2B.
 */
class B5cB2bListingsPageBuilder
{
    public function syncProducts(Request $request): View
    {
        $searchSku = trim((string) $request->input('search_sku', ''));
        $searchName = trim((string) $request->input('search_name', ''));
        $linkTab = strtolower((string) $request->input('link', 'all'));
        if (in_array($linkTab, ['not_in_shopify', 'linked', 'linked_with_inv'], true)) {
            $linkTab = 'matched';
        }
        if ($linkTab === 'linked_zero') {
            $linkTab = 'zero';
        }
        if (! in_array($linkTab, ['all', 'matched', 'matched_inactive', 'mismatch', 'linked_mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true)) {
            $linkTab = 'all';
        }

        $stateTab = strtolower(trim((string) $request->input('state', 'all')));
        if (! in_array($stateTab, ['all', 'active', 'inactive', 'other'], true)) {
            $stateTab = 'all';
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $clearCache = $request->boolean('clear_cache');
        $emptyCounts = ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'linked_mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
        $emptyStateCounts = ['all' => 0, 'active' => 0, 'inactive' => 0, 'other' => 0];
        $liveLinkTabs = ['matched', 'mismatch', 'linked_mismatch', 'zero'];
        $portalTabs = ['matched_inactive', 'mismatch_inactive'];
        $liveService = $this->liveService();

        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            return view('marketplace.b5cb2b.products', $this->emptyViewData(
                $searchSku,
                $searchName,
                $linkTab,
                $emptyCounts,
                $emptyStateCounts,
                'shopify_skus table missing. Run Shopify inventory sync first.'
            ));
        }

        if ($forceLive) {
            WarmB5cB2bLiveListingsCache::dispatch();
            $apiError = 'Live Business 5 Core B2B inventory refresh queued. Reload in a minute.';
        }

        if ($liveService->peekCached() === null) {
            $liveService->all(false);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->stockMapForSkus($allLinkedVerified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'b5cb2b') ?? [];
        $counts = $classified['counts'] ?? $emptyCounts;
        $counts['all'] = $catalog->countDistinctAllSkus();
        $counts['matched_inactive'] = 0;
        $counts['mismatch_inactive'] = 0;

        if (! $catalog->hasAnyActive()) {
            $apiError = trim(($apiError ? $apiError.' ' : '').'Shared Shopify live catalog is empty — refresh Shopify from Marketplace Manager.');
        }

        $matchedQty = $classified['matched'] ?? [];
        $mismatchQty = $classified['mismatch'] ?? [];
        $linkedMismatchQty = $classified['linked_mismatch'] ?? [];
        $zeroQty = $classified['zero'] ?? [];

        if ($mismatchQty !== []) {
            $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($mismatchQty);
            if ($liveShopify === []) {
                $liveShopify = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($mismatchQty);
            }
            $liveMpByUpper = $this->stockMapForSkus($mismatchQty);
            $reconciled = MarketplaceListingStockResolver::reconcileLinkedTabsWithLiveQty(
                $matchedQty,
                $mismatchQty,
                $zeroQty,
                $liveShopify,
                $liveMpByUpper,
                'b5cb2b'
            );
            $matchedQty = $reconciled['matched'];
            $mismatchQty = $reconciled['mismatch'];
            $linkedMismatchQty = $reconciled['linked_mismatch'] ?? $linkedMismatchQty;
            $zeroQty = $reconciled['zero'];
        }

        $counts['matched'] = count($matchedQty);
        $counts['mismatch'] = count($mismatchQty);
        $counts['zero'] = count($zeroQty);
        $counts['linked'] = $counts['matched'] + $counts['mismatch'] + $counts['zero'];

        $liveRows = $liveService->peekCached();
        if (! is_array($liveRows) || $liveRows === []) {
            $liveRows = $liveService->all(false);
        }
        $overlay = MarketplacePortalStatusTabs::overlayQtyAndPortal(
            $counts,
            $matchedQty,
            $mismatchQty,
            $zeroQty,
            $liveRows
        );
        $counts = $overlay['counts'];
        $counts['linked_mismatch'] = count($linkedMismatchQty);
        $matchedActive = $overlay['matchedActive'];
        $matchedInactive = $overlay['matchedInactive'];
        $mismatchActive = $overlay['mismatchActive'];
        $mismatchInactive = $overlay['mismatchInactive'];
        $this->rememberMismatchSkus($mismatchActive);

        if (in_array($linkTab, $portalTabs, true)) {
            if ($counts[$linkTab] === 0 && ! $forceLive) {
                WarmB5cB2bLiveListingsCache::dispatch();
            }
            $paginator = MarketplacePortalStatusTabs::paginate(
                $linkTab === 'mismatch_inactive' ? 'active' : 'inactive',
                $linkTab === 'mismatch_inactive' ? $mismatchInactive : $matchedInactive,
                $liveRows,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                'b5c_state',
                'b5c_title'
            );

            return view('marketplace.b5cb2b.products', [
                'products' => $paginator,
                'title' => 'Business 5 Core (B2B) — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => 'all',
                'counts' => $counts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => is_array($liveService->peekCached()),
                'apiError' => $apiError,
                'connected' => $this->isConnected(),
                'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
                'b5cCatalogSyncedAt' => $this->catalogSyncedAt(),
            ]);
        }

        $linkedVerified = match ($linkTab) {
            'mismatch' => $mismatchActive,
            'linked_mismatch' => $linkedMismatchQty,
            'zero' => $zeroQty,
            'matched' => $matchedActive,
            default => [],
        };

        $linkedNormToSku = [];
        foreach ((in_array($linkTab, ['all', 'unlinked'], true) ? $allLinkedVerified : $linkedVerified) as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $linkedNormToSku[$n] = (string) $sku;
            }
        }
        $stateIndex = $this->stateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($linkedNormToSku[$norm]),
            count($linkedNormToSku),
            $linkedNormToSku
        );

        $query = ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($searchSku !== '') {
            $query->where('sku', 'like', '%'.$searchSku.'%');
        }
        if ($searchName !== '') {
            $query->where(function ($q) use ($searchName) {
                $q->where('product_title', 'like', '%'.$searchName.'%')
                    ->orWhere('variant_title', 'like', '%'.$searchName.'%')
                    ->orWhere('sku', 'like', '%'.$searchName.'%');
            });
        }

        if (in_array($linkTab, $liveLinkTabs, true)) {
            if ($linkedVerified === []) {
                $query->whereRaw('1 = 0');
            } elseif ($stateTab !== 'all') {
                $stateSkus = $stateIndex['skusByState'][$stateTab] ?? [];
                $catalog->restrictShopifySkuQuery($query, $stateSkus);
            } else {
                $catalog->restrictShopifySkuQuery($query, $linkedVerified);
            }
        } elseif ($linkTab === 'all') {
            $catalog->restrictShopifySkuQuery($query, null, false);
        } else {
            $catalog->restrictShopifySkuQuery($query, null, true);
            if ($allLinkedVerified !== []) {
                $query->whereNotIn('sku', $allLinkedVerified);
            }
        }

        $paginator = $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $pageRows = collect($paginator->items())->all();
        $skus = collect($pageRows)->pluck('sku')->filter()->values()->all();
        $metricMap = $this->metricMapForSkus($skus);
        $stockMap = $this->stockMapForSkus($skus);
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        if ($liveShopifyQty === []) {
            $liveShopifyQty = MarketplaceListingStockResolver::dbShopifyQtyMapForRows($pageRows);
        }

        $pageLive = [];
        if (in_array($linkTab, $liveLinkTabs, true)) {
            $needIds = [];
            foreach ($skus as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $metric || ! $this->isLinked($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) $metric->product_id;
            }
            if ($needIds !== []) {
                $pageLive = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($metricMap, $stockMap, $liveShopifyQty, $stateIndex, $pageLive) {
            $sku = (string) $row->sku;
            $metric = $metricMap[$sku] ?? null;
            $linked = $this->isLinked($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
            $mpQty = $linked
                ? MarketplaceListingStockResolver::qtyFromMap($stockMap, $sku, $metricSku)
                : null;
            $cached = $this->cachedRowForSku($sku, $stateIndex);
            $live = ($pid !== '' && isset($pageLive[$pid])) ? $pageLive[$pid] : null;
            $state = (string) ($live['state'] ?? $cached['state'] ?? ($metric->status ?? ''));
            if ($linked) {
                $mpQty = MarketplaceListingStockResolver::displayedMarketplaceQty(
                    is_array($live) ? $live : null,
                    is_array($cached) ? $cached : null,
                    $mpQty
                );
            }

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku_id' => $linked ? ($metric->sku_id ?? null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'b5c_title' => $live['title'] ?? ($cached['title'] ?? ($metric->title ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ($metric->price ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'quantity' => $mpQty,
                'ae_quantity' => $mpQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'b5c_state' => $state !== '' ? $state : null,
                'inactive_reason' => $live['inactive_reason'] ?? ($cached['inactive_reason'] ?? null),
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.b5cb2b.products', [
            'products' => $paginator,
            'title' => 'Business 5 Core (B2B) — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->isConnected(),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
            'b5cCatalogSyncedAt' => $this->catalogSyncedAt(),
        ]);
    }

    /**
     * @return array{success: bool, done?: bool, total?: int, offset?: int, batch?: int, updated?: int, failed?: int, skipped?: int, message: string, queued?: bool}
     */
    public function syncMismatchInventoryNow(Request $request): array
    {
        try {
            return $this->runMismatchInventoryNow($request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('B5C B2B mismatch inventory sync failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Mismatch sync failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, done?: bool, total?: int, offset?: int, batch?: int, updated?: int, failed?: int, skipped?: int, message: string, queued?: bool}
     */
    protected function runMismatchInventoryNow(Request $request): array
    {
        @set_time_limit(300);

        $liveService = $this->liveService();
        $scope = strtolower((string) $request->input('scope', $request->input('link', 'all')));
        $mismatch = $this->mismatchSkusForSync($scope);
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = max(1, min(10, (int) $request->input('limit', 5)));
        $total = count($mismatch);
        $batch = array_slice($mismatch, $offset, $limit);

        if ($batch === []) {
            return [
                'success' => true,
                'done' => true,
                'total' => $total,
                'offset' => $offset,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => $total === 0 ? 'No mismatch SKUs to sync.' : 'All mismatch batches finished.',
            ];
        }

        $result = $this->inventoryService()->syncSkusFromShopify($batch, null, true);
        $nextOffset = $offset + count($batch);
        $done = $nextOffset >= $total;
        if ($done) {
            $liveService->clearCache();
            $this->forgetMismatchSkuCache();
        }

        return [
            'success' => true,
            'done' => $done,
            'queued' => false,
            'total' => $total,
            'offset' => $nextOffset,
            'batch' => count($batch),
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => $result['message'] ?? ($done
                ? 'Mismatch inventory sync complete.'
                : 'Synced batch '.$nextOffset.' / '.$total.'…'),
        ];
    }

    public function showProduct(int $shopifySkuId): View
    {
        $row = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $row->sku;
        $metric = $this->metricMapForSkus([$sku])[$sku] ?? null;
        $linked = $this->isLinked($metric, $sku);
        $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus([$sku]);
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopify, $row, $sku);
        $stockMap = $this->stockMapForSkus([$sku]);
        $mpQty = $linked
            ? MarketplaceListingStockResolver::qtyFromMap($stockMap, $sku, (string) ($metric->sku ?? ''))
            : null;

        $live = null;
        if ($linked && ! empty($metric->product_id)) {
            $live = $this->liveService()->liveDetailsByProductIds([(string) $metric->product_id]);
            $live = $live[(string) $metric->product_id] ?? $live[strtoupper($sku)] ?? $live[$sku] ?? null;
            if (is_array($live)) {
                $mpQty = MarketplaceListingStockResolver::displayedMarketplaceQty($live, null, $mpQty);
            }
        }

        return view('marketplace.b5cb2b.product-show', [
            'title' => 'Business 5 Core (B2B) — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'sku' => $sku,
            'shopify' => $row,
            'metric' => $metric,
            'linked' => $linked,
            'shopifyQty' => $shopifyQty,
            'b5cQty' => $mpQty,
            'b5cState' => is_array($live) ? ($live['state'] ?? ($metric->status ?? null)) : ($metric->status ?? null),
            'connected' => $this->isConnected(),
        ]);
    }

    /**
     * @return array{success: bool, message: string, updated?: int, failed?: int, skipped?: int}
     */
    public function pushProductInventory(int $shopifySkuId): array
    {
        $row = ShopifySku::query()->find($shopifySkuId);
        if (! $row) {
            return ['success' => false, 'message' => 'Shopify SKU not found.'];
        }
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is empty.'];
        }

        $result = $this->inventoryService()->syncSkusFromShopify([$sku], null, true);
        $this->liveService()->clearCache();

        return [
            'success' => ((int) ($result['updated'] ?? 0)) > 0 || ((int) ($result['failed'] ?? 0)) === 0,
            'message' => $result['message'] ?? 'Inventory push finished.',
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function pullProductFromB5cB2b(int $shopifySkuId): array
    {
        $row = ShopifySku::query()->find($shopifySkuId);
        if (! $row) {
            return ['success' => false, 'message' => 'Shopify SKU not found.'];
        }

        return $this->liveService()->refreshSku((string) $row->sku);
    }

    /**
     * @return list<string>
     */
    public function linkedSkus(): array
    {
        if (! Schema::hasTable('b5c_b2b_products')) {
            return [];
        }

        return B5cB2bProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn (string $sku) => $sku !== '' && ! MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku))
            ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, object>
     */
    public function metricMapForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        if ($skus === [] || ! Schema::hasTable('b5c_b2b_products')) {
            return [];
        }

        $keys = [];
        foreach ($skus as $sku) {
            $keys[] = $sku;
            $keys[] = strtoupper($sku);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $keys[] = $norm;
            }
        }
        $keys = array_values(array_unique($keys));

        $map = [];
        foreach (B5cB2bProduct::query()->whereIn('sku', $keys)->get() as $row) {
            $obj = (object) [
                'sku' => (string) $row->sku,
                'product_id' => $row->listing_id !== null ? (string) $row->listing_id : '',
                'sku_id' => (string) $row->sku,
                'price' => $row->price,
                'inventory_quantity' => $row->qty,
                'status' => $row->is_active === false ? 'inactive' : 'active',
                'title' => $row->title,
                'handle' => $row->slug,
            ];
            $sku = (string) $row->sku;
            $map[$sku] = $obj;
            $map[strtoupper($sku)] = $obj;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $map[$norm] = $obj;
            }
        }

        $out = [];
        foreach ($skus as $sku) {
            $hit = $map[$sku]
                ?? $map[strtoupper($sku)]
                ?? $map[ShopifySku::normalizeSkuForShopifyLookup($sku)]
                ?? null;
            if ($hit) {
                $out[$sku] = $hit;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    public function stockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_B5CB2B,
            $skus
        );
    }

    public function isLinked(?object $metric, string $shopifySku): bool
    {
        if (! $metric) {
            return false;
        }
        $sku = trim((string) ($metric->sku ?? ''));

        return $sku !== '';
    }

    /**
     * @param  callable(string): bool  $includeNorm
     * @param  array<string, string>  $normToSku
     * @return array{counts: array{all: int, active: int, inactive: int, other: int}, skusByState: array<string, array<int, string>>, byNorm: array<string, array{state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}>, ready: bool}
     */
    protected function stateIndexFromCache(
        B5cB2bLiveListingsService $liveService,
        callable $includeNorm,
        int $allCount,
        array $normToSku = []
    ): array {
        $counts = ['all' => $allCount, 'active' => 0, 'inactive' => 0, 'other' => 0];
        $skusByState = ['active' => [], 'inactive' => [], 'other' => []];
        $byNorm = [];

        $cached = $liveService->peekCached();
        if (! is_array($cached) || $cached === []) {
            return ['counts' => $counts, 'skusByState' => $skusByState, 'byNorm' => $byNorm, 'ready' => false];
        }

        $seen = [];
        foreach ($cached as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rawSku = trim((string) ($row['sku'] ?? ''));
            if ($rawSku === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($rawSku);
            if ($norm === '' || isset($seen[$norm]) || ! $includeNorm($norm)) {
                continue;
            }
            $seen[$norm] = true;
            $bucket = $this->stateBucket((string) ($row['state'] ?? ''));
            if (! isset($counts[$bucket])) {
                $bucket = 'other';
            }
            $counts[$bucket]++;
            $skusByState[$bucket][] = $normToSku[$norm] ?? $rawSku;
            $byNorm[$norm] = [
                'state' => (string) ($row['state'] ?? $bucket),
                'inventory' => array_key_exists('inventory', $row) && $row['inventory'] !== null ? (int) $row['inventory'] : null,
                'title' => isset($row['title']) ? (string) $row['title'] : null,
                'price' => isset($row['price']) ? (float) $row['price'] : null,
                'inactive_reason' => isset($row['inactive_reason']) ? (string) $row['inactive_reason'] : null,
            ];
        }

        return ['counts' => $counts, 'skusByState' => $skusByState, 'byNorm' => $byNorm, 'ready' => true];
    }

    /**
     * @param  array{byNorm?: array<string, array{state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}>}  $stateIndex
     * @return array{state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}|null
     */
    protected function cachedRowForSku(string $sku, array $stateIndex): ?array
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return null;
        }

        return $stateIndex['byNorm'][$norm] ?? null;
    }

    protected function stateBucket(?string $state): string
    {
        $state = strtolower(trim((string) $state));
        if (in_array($state, ['active', '1', 'true', 'published'], true)) {
            return 'active';
        }
        if (in_array($state, ['inactive', 'draft', 'archived', 'unlisted', '0', 'false'], true)) {
            return 'inactive';
        }

        return 'other';
    }

    protected function liveService(): B5cB2bLiveListingsService
    {
        return app(B5cB2bLiveListingsService::class);
    }

    protected function inventoryService(): B5cB2bInventorySyncService
    {
        return app(B5cB2bInventorySyncService::class);
    }

    protected function isConnected(): bool
    {
        try {
            return app(MarketplaceApiConfigService::class)->isConfigured('b5cb2b');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function catalogSyncedAt(): ?string
    {
        if (! Schema::hasTable('b5c_b2b_products')) {
            return null;
        }
        $at = B5cB2bProduct::query()->max('updated_at');

        return $at ? (string) $at : null;
    }

    /**
     * @param  list<string>  $skus
     */
    protected function rememberMismatchSkus(array $skus): void
    {
        try {
            Cache::put($this->mismatchSkuCacheKey(), array_values($skus), now()->addMinutes(20));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function forgetMismatchSkuCache(): void
    {
        try {
            Cache::forget($this->mismatchSkuCacheKey());
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function mismatchSkuCacheKey(): string
    {
        return 'mm.b5cb2b.listings_mismatch_skus.v1';
    }

    /**
     * @return list<string>
     */
    protected function mismatchSkusForSync(string $scope): array
    {
        if (in_array($scope, ['mismatch_inactive', 'inactive', 'matched_inactive'], true)) {
            return [];
        }

        try {
            $cached = Cache::get($this->mismatchSkuCacheKey());
            if (is_array($cached)) {
                return array_values(array_filter(array_map('strval', $cached)));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $this->liveService()->peekCached(),
            $this->stockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'b5cb2b') ?? [];
        $mismatch = MarketplaceListingStockResolver::qtyListForSyncScope($classified, $scope);
        if ($scope !== 'linked_mismatch') {
            $this->rememberMismatchSkus($mismatch);
        }

        return $mismatch;
    }

    /**
     * @param  array<string, int>  $emptyCounts
     * @param  array<string, int>  $emptyStateCounts
     * @return array<string, mixed>
     */
    protected function emptyViewData(
        string $searchSku,
        string $searchName,
        string $linkTab,
        array $emptyCounts,
        array $emptyStateCounts,
        string $apiError
    ): array {
        return [
            'products' => new LengthAwarePaginator([], 0, 50, 1),
            'title' => 'Business 5 Core (B2B) — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => 'all',
            'counts' => $emptyCounts,
            'stateCounts' => $emptyStateCounts,
            'stateCacheReady' => false,
            'apiError' => $apiError,
            'connected' => $this->isConnected(),
            'shopifyCatalogSyncedAt' => null,
            'b5cCatalogSyncedAt' => null,
        ];
    }
}
