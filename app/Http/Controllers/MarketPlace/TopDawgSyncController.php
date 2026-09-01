<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmTopDawgLiveListingsCache;
use App\Models\TopDawgProduct;
use App\Models\TopDawgOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\TopDawgApiService;
use App\Services\MarketplaceManager\TopDawgDetailFormatter;
use App\Services\MarketplaceManager\TopDawgInventorySyncService;
use App\Services\MarketplaceManager\TopDawgLinkMapSyncService;
use App\Services\MarketplaceManager\TopDawgLiveListingsService;
use App\Services\MarketplaceManager\TopDawgOrderDetailService;
use App\Services\MarketplaceManager\TopDawgOrderPushService;
use App\Services\MarketplaceManager\TopDawgOrderSyncService;
use App\Services\MarketplaceManager\TopDawgTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplacePortalStatusTabs;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use App\Services\MarketplaceManager\ReverbLiveListingsService;
use App\Services\MarketplaceManager\ShopifyLiveVerifiedCatalogService;
use App\Services\ShopifyApiService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TopDawgSyncController extends Controller
{
    public function __construct(
        protected TopDawgApiService $topdawgApi,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $token = (string) config('services.topdawg.token', '');
        $baseUrl = (string) config('services.topdawg.base_url', 'https://topdawg.com/supplier/api');
        $credentialsReady = $this->topdawgApi->isConfigured();

        return view('marketplace.topdawg.connect', [
            'title' => 'TopDawg — Connect',
            'connected' => $this->apiConfig->isConfigured('topdawg'),
            'credentialsReady' => $credentialsReady,
            'hasToken' => filled($token),
            'maskedToken' => $this->maskCredential($token, 4, 4),
            'apiBase' => $baseUrl,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('topdawg')) {
            return response()->json([
                'success' => false,
                'message' => 'TopDawg API credentials missing. Set TOPDAWG_API_TOKEN in .env.',
            ]);
        }

        try {
            $result = $this->topdawgApi->testConnection();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? 'Connection test finished.',
            'sample_count' => $result['sample_count'] ?? null,
        ], ! empty($result['success']) ? 200 : 422);
    }

    protected function maskCredential(string $value, int $showStart = 3, int $showEnd = 4): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }
        $len = strlen($value);
        if ($len <= $showStart + $showEnd) {
            return str_repeat('•', $len);
        }

        return substr($value, 0, $showStart)
            .str_repeat('•', min(12, $len - $showStart - $showEnd))
            .substr($value, -$showEnd);
    }

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
        $stateTab = $this->parseAeStateTab($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $clearCache = $request->boolean('clear_cache');
        $emptyStateCounts = $this->emptyAeStateCounts();
        $emptyCounts = ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'linked_mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
        $liveLinkTabs = ['matched', 'mismatch', 'linked_mismatch', 'zero'];
        $liveService = app(TopDawgLiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.topdawg.products', [
                'products' => $products,
                'title' => 'TopDawg — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('topdawg'),
            ]);
        }

        if ($forceLive) {
            WarmTopDawgLiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedTopDawgSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Live cache often omits inventory for inactive rows — fill gaps from local map
        // so qty-matched inactive SKUs land in Inactive & Matched (not Mismatch).
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->topdawgStockMapForSkus($allLinkedVerified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'topdawg');
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
            $metricMap = $this->topdawgMetricMapForSkus($mismatchQty);
            $productIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnTopDawg($metric, (string) $sku)) {
                    continue;
                }
                $pid = (string) ($metric->topdawg_listing_id ?? '');
                if ($pid === '') {
                    continue;
                }
                $productIds[] = $pid;
                $idToSku[$pid] = (string) $sku;
            }
            // Same stock map the table columns use so equal Shopify/TopDawg qty
            // is not left on Mismatch when live product-id lookup misses.
            $liveMpByUpper = $this->topdawgStockMapForSkus($mismatchQty);
            if ($productIds !== []) {
                foreach ($liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($productIds)), 0, 80)) as $pid => $row) {
                    $sku = $idToSku[(string) $pid] ?? trim((string) ($row['sku'] ?? ''));
                    if ($sku === '' || ! array_key_exists('inventory', $row) || $row['inventory'] === null) {
                        continue;
                    }
                    $qty = (int) $row['inventory'];
                    $liveMpByUpper[strtoupper($sku)] = $qty;
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                    if ($norm !== '') {
                        $liveMpByUpper[$norm] = $qty;
                    }
                }
            }
            $reconciled = MarketplaceListingStockResolver::reconcileLinkedTabsWithLiveQty(
                $matchedQty,
                $mismatchQty,
                $zeroQty,
                $liveShopify,
                $liveMpByUpper,
                'topdawg'
            );
            $matchedQty = $reconciled['matched'];
            $mismatchQty = $reconciled['mismatch'];
            $linkedMismatchQty = $reconciled['linked_mismatch'] ?? $linkedMismatchQty;
            $zeroQty = $reconciled['zero'];
            $counts['matched'] = count($matchedQty);
            $counts['mismatch'] = count($mismatchQty);
            $counts['zero'] = count($zeroQty);
            $counts['linked'] = $counts['matched'] + $counts['mismatch'] + $counts['zero'];
            $counts['linked_with_inv'] = $counts['matched'];
            $counts['linked_zero_inv'] = $counts['zero'];
        }

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

        $portalTabs = ['matched_inactive', 'mismatch_inactive'];
        if (in_array($linkTab, $portalTabs, true)) {
            if ($counts[$linkTab] === 0 && ! $forceLive) {
                WarmTopDawgLiveListingsCache::dispatch();
            }
            $paginator = MarketplacePortalStatusTabs::paginate(
                $linkTab === 'mismatch_inactive' ? 'active' : 'inactive',
                $linkTab === 'mismatch_inactive' ? $mismatchInactive : $matchedInactive,
                $liveRows,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                'topdawg_state',
                'topdawg_title'
            );

            return view('marketplace.topdawg.products', [
                'products' => $paginator,
                'title' => 'Top Dawg — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => 'all',
                'counts' => $counts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => is_array($liveService->peekCached()),
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('topdawg'),
                'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
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
        $stateIndex = $this->aeStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($linkedNormToSku[$norm]),
            count($linkedNormToSku),
            $linkedNormToSku
        );

        if (in_array($linkTab, $liveLinkTabs, true) && ! $stateIndex['ready'] && ! $forceLive) {
            WarmTopDawgLiveListingsCache::dispatch();
        }

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
        $aeMap = $this->topdawgMetricMapForSkus($skus);
        $aeStockMap = $this->topdawgStockMapForSkus($skus);
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        if ($liveShopifyQty === []) {
            $liveShopifyQty = MarketplaceListingStockResolver::dbShopifyQtyMapForRows($pageRows);
        }

        // Always live-hydrate marketplace qty for this page's linked product IDs.
        $pageLiveByProduct = [];
        if (in_array($linkTab, $liveLinkTabs, true)) {
            $needIds = [];
            foreach ($skus as $sku) {
                $metric = $aeMap[$sku] ?? null;
                if (! $metric || ! $this->isShopifySkuLinkedOnTopDawg($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) $metric->topdawg_listing_id;
            }
            if ($needIds !== []) {
                $pageLiveByProduct = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $aeStockMap, $liveShopifyQty, $stateIndex, $pageLiveByProduct) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnTopDawg($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) ($metric->topdawg_listing_id ?? '') : '';
            $aeQty = $linked
                ? MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku, $metricSku)
                : null;
            $cached = $this->aeCachedRowForSku($sku, $stateIndex);
            $live = ($pid !== '' && isset($pageLiveByProduct[$pid])) ? $pageLiveByProduct[$pid] : null;
            $state = (string) ($live['state'] ?? $cached['state'] ?? '');
            if ($linked) {
                $aeQty = MarketplaceListingStockResolver::displayedMarketplaceQty(
                    is_array($live) ? $live : null,
                    is_array($cached) ? $cached : null,
                    $aeQty
                );
            }

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'topdawg_title' => $live['title'] ?? ($cached['title'] ?? ($metric->product_title ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ($metric->price ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'topdawg_state' => $state !== '' ? $state : null,
                'mp_state' => $state !== '' ? $state : null,
                'inactive_reason' => MarketplacePortalStatusTabs::bucket($state) === 'inactive'
                    ? MarketplacePortalStatusTabs::inactiveReason(is_array($live) ? $live : (is_array($cached) ? $cached : []), $aeQty)
                    : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.topdawg.products', [
            'products' => $paginator,
            'title' => 'TopDawg — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('topdawg'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->topdawgMetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnTopDawg($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'none';
        $productId = $metric?->topdawg_listing_id ? (string) $metric->topdawg_listing_id : null;
        $canFetchAe = $productId && $productId !== (string) $metric?->sku && $this->apiConfig->isConfigured('topdawg');

        if ($canFetchAe) {
            $info = $this->topdawgApi->getProductInfo($productId);
            if (! empty($info['success'])) {
                $aeLive = $info['data'] ?? null;
                $aeDataSource = 'api';
            } else {
                $aeLiveError = $info['message'] ?? 'Could not load live TopDawg product details.';
                $aeDataSource = 'cached';
            }
        } elseif ($metric?->topdawg_listing_id) {
            $aeDataSource = 'cached';
        }

        $aeSkuRows = [];
        if (is_array($aeLive)) {
            $aeSkuRows = $this->topdawgApi->extractSkuRowsFromProductInfo(
                $aeLive,
                (string) ($metric->topdawg_listing_id ?? ''),
                $metric->product_title ?? null
            );
        }

        $title = trim(($shopifyRow->product_title ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(TopDawgDetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.topdawg.product-show', [
            'title' => 'TopDawg Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('topdawg'),
        ]);
    }

    /**
     * Push live Shopify qty → TopDawg for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('topdawg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in TopDawg settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->topdawgMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnTopDawg($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on TopDawg. Run Sync TopDawg link map first.',
            ], 422);
        }

        $result = app(TopDawgInventorySyncService::class)->syncSkusFromShopify([$sku]);

        $updated = (int) ($result['updated'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);

        return response()->json([
            'success' => $updated > 0,
            'queued' => false,
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => $result['message'] ?? 'Inventory sync finished.',
        ]);
    }

    public function pullProductFromTopDawg(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('topdawg')) {
            return response()->json(['success' => false, 'message' => 'TopDawg not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->topdawgMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $metric?->topdawg_listing_id || (string) $metric->topdawg_listing_id === (string) $metric->sku) {
            return response()->json([
                'success' => false,
                'message' => 'No TopDawg product_id mapped for this SKU. Run Sync TopDawg link map on Listings first.',
            ]);
        }

        $info = $this->topdawgApi->getProductInfo((string) $metric->topdawg_listing_id);
        if (empty($info['success'])) {
            return response()->json([
                'success' => false,
                'message' => $info['message'] ?? 'Failed to pull product details from TopDawg.',
            ]);
        }

        $aeData = is_array($info['data'] ?? null) ? $info['data'] : [];
        $rows = $this->topdawgApi->extractSkuRowsFromProductInfo(
            $aeData,
            (string) $metric->topdawg_listing_id,
            $metric->product_title
        );
        $matched = collect($rows)->first(function ($row) use ($sku) {
            return ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''))
                === ShopifySku::normalizeSkuForShopifyLookup($sku);
        }) ?? ($rows[0] ?? null);

        $metric->update([
            'product_title' => trim((string) (
                $aeData['product_title']
                ?? $aeData['product_name']
                ?? $aeData['title']
                ?? $metric->product_title
                ?? ''
            )) ?: $metric->product_title,
            'price' => $matched['price'] ?? $metric->price,
            'remaining_inventory' => $matched['inventory'] ?? $matched['stock'] ?? $metric->remaining_inventory,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pulled latest TopDawg details for '.$sku.'. Nothing was pushed to Shopify or TopDawg.',
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('topdawg')) {
            return response()->json(['success' => false, 'message' => 'TopDawg not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('TopDawg link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(TopDawgLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(TopDawgLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('topdawg_order_metrics')) {
            $orders = TopDawgOrderMetric::query()
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.topdawg.orders', [
            'orders' => $orders,
            'title' => 'TopDawg — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('topdawg'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('topdawg'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = TopDawgOrderMetric::query()->findOrFail($id);
        $orderId = (string) $line->order_id;

        $lines = TopDawgOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(TopDawgOrderDetailService::class);

        if ($this->apiConfig->isConfigured('topdawg')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live TopDawg order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(TopDawgDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.topdawg.order-show', [
            'title' => 'TopDawg Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('topdawg'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('topdawg'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('topdawg', $line),
        ]);
    }

    public function pullOrderFromTopDawg(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('topdawg')) {
            return response()->json(['success' => false, 'message' => 'TopDawg not connected.']);
        }

        $line = TopDawgOrderMetric::query()->findOrFail($id);
        $result = app(TopDawgOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->order_id);

        // Persist only — Shopify address fill matches AliExpress / Reverb (SyncTopDawgAddressJob).
        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from TopDawg.' : 'Failed to pull order details.'),
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to TopDawg (Ship Order).
     */
    public function pushTrackingToTopDawg(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('topdawg')) {
            return response()->json(['success' => false, 'message' => 'TopDawg not connected.']);
        }

        $line = TopDawgOrderMetric::query()->findOrFail($id);
        $result = app(TopDawgTrackingSyncService::class)->pushTrackingForOrder($line);

        return response()->json([
            'success' => ! empty($result['success']),
            'skipped' => ! empty($result['skipped']),
            'action' => $result['action'] ?? null,
            'message' => $result['message'] ?? 'Tracking push finished.',
            'shopify_tracking' => $result['shopify_tracking'] ?? null,
            'shopify_carrier' => $result['shopify_carrier'] ?? null,
            'ship_carrier' => $result['ship_carrier'] ?? null,
        ], ! empty($result['success']) || ! empty($result['skipped']) ? 200 : 422);
    }

    /**
     * Bulk push Shopify tracking → TopDawg for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('topdawg')) {
            return response()->json(['success' => false, 'message' => 'TopDawg not connected.']);
        }

        \App\Jobs\SyncTopDawgTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and ships orders on TopDawg.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(TopDawgOrderSyncService::class);

        if ($fromDate !== '') {
            $result = $sync->fetchAndStoreFromDate($fromDate);
        } else {
            $daysInput = $request->input('days', 0);
            $days = $daysInput === 'all' || (int) $daysInput === 0
                ? 0
                : max(1, min(730, (int) $daysInput));
            $result = $sync->fetchAndStore($days);
        }

        // Only auto-queue Shopify imports when explicitly requested.
        // Prefer from_date fetches without import when older orders already exist on Shopify.
        if ($request->boolean('import') || MarketplaceSyncSettings::canAutoImportToShopify('topdawg')) {
            $dispatched = $sync->dispatchImportsForNewOrders();
            $result['message'] .= " Dispatched {$dispatched} import job(s).";
        }

        return response()->json([
            'success' => str_contains(strtolower($result['message']), 'missing')
                || str_contains(strtolower($result['message']), 'invalid')
                ? false
                : true,
            'message' => $result['message'],
            'fetched' => $result['fetched'] ?? 0,
            'stored' => $result['stored'] ?? 0,
        ]);
    }

    public function syncInventoryNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('topdawg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('topdawg');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('topdawg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(TopDawgLiveListingsService::class);
        $linkedSkus = $this->linkedTopDawgSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->topdawgStockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'topdawg');
        $mismatchQty = $classified['mismatch'] ?? [];
        $linkedMismatchQty = $classified['linked_mismatch'] ?? [];
        $scope = strtolower((string) $request->input('scope', $request->input('link', 'all')));
        $mismatch = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::qtyListForSyncScope($classified, $scope);

        $offset = max(0, (int) $request->input('offset', 0));
        $limit = max(1, min(40, (int) $request->input('limit', 25)));
        $total = count($mismatch);
        $batch = array_slice($mismatch, $offset, $limit);

        if ($batch === []) {
            return response()->json([
                'success' => true,
                'done' => true,
                'total' => $total,
                'offset' => $offset,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => $total === 0 ? 'No mismatch SKUs to sync.' : 'All mismatch batches finished.',
            ]);
        }

        $result = app(TopDawgInventorySyncService::class)->syncSkusFromShopify($batch, null, true);
        $nextOffset = $offset + count($batch);
        $done = $nextOffset >= $total;

        return response()->json([
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
        ]);
    }

    public function pushOrderToShopify(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = TopDawgOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(TopDawgOrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('topdawg', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(TopDawgOrderPushService::class);
        try {
            $shopifyOrderId = $push->importToShopify($order);
        } catch (\Throwable $e) {
            $order->update(['import_status' => 'import_failed']);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Shopify import failed.',
            ], 422);
        }

        if ($shopifyOrderId) {
            $order->refresh();
            $message = $push->lastDuplicateLinkMessage
                ?: 'Pushed to Shopify.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'shopify_order_id' => $shopifyOrderId,
                'linked_existing' => $push->lastDuplicateLinkMessage !== null,
            ]);
        }

        $order->update(['import_status' => 'import_failed']);

        return response()->json([
            'success' => false,
            'message' => $push->lastFailureReason ?: 'Shopify import failed.',
        ], 422);
    }

    /**
     * Delete a local TopDawg order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = TopDawgOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (! empty($order->shopify_order_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This order is already imported to Shopify and cannot be deleted here.',
            ], 422);
        }

        $orderId = (string) $order->order_id;
        $deleted = TopDawgOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed TopDawg order {$orderId} from ready-for-import ({$deleted} row(s)).",
            'deleted' => $deleted,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Mark a marketplace order as already imported / entered manually in Shopify
     * so it leaves the ready-for-import queue without creating a new Shopify order.
     */
    public function markOrderAlreadyImported(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = TopDawgOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (! empty($order->shopify_order_id)) {
            return response()->json([
                'success' => true,
                'message' => 'Already marked as imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        $orderId = (string) $order->order_id;
        $shopifyOrderId = trim((string) $request->input('shopify_order_id', ''));
        if ($shopifyOrderId === '') {
            $shopifyOrderId = 'manual:'.$orderId;
        }

        $updated = TopDawgOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'import_status' => 'imported',
                'pushed_to_shopify_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked TopDawg order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.topdawg.settings', [
            'settings' => MarketplaceSyncSettings::getFor('topdawg'),
            'title' => 'TopDawg — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('topdawg'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('topdawg');

        $pricing = $this->mergeSettingsSection($current['pricing'] ?? [], $request->input('pricing', []), [
            'price_sync', 'use_sale_price', 'currency_conversion',
        ]);
        $inventory = $this->mergeSettingsSection($current['inventory'] ?? [], $request->input('inventory', []), [
            'inventory_sync',
        ]);
        // Hard rule: never invent marketplace stock from Shopify 0 via min_quantity.
        $inventory['min_quantity'] = 0;
        $order = $this->mergeSettingsSection($current['order'] ?? [], $request->input('order', []), [
            'fetch_orders', 'auto_import_to_shopify', 'import_paid_orders_only', 'keep_order_number_from_channel',
            'push_tracking_to_topdawg', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_topdawg', 'sync_title', 'sync_images',
        ]);

        if ($request->has('order.shopify_order_tags')) {
            $tags = $request->input('order.shopify_order_tags');
            $order['shopify_order_tags'] = is_array($tags)
                ? $tags
                : array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
        }

        if ($request->filled('order.shopify_store')) {
            $store = (string) $request->input('order.shopify_store');
            $allowed = ['main', '5core', 'business', 'prolightsounds'];
            if (in_array($store, $allowed, true)) {
                $order['shopify_store'] = $store;
            }
        }

        if ($request->filled('order.shopify_source_name')) {
            $order['shopify_source_name'] = trim((string) $request->input('order.shopify_source_name'));
        }

        if ($request->filled('order.shopify_source_display_name')) {
            $order['shopify_source_display_name'] = trim((string) $request->input('order.shopify_source_display_name'));
        }

        MarketplaceSyncSettings::setFor('topdawg', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'TopDawg sync settings saved.']);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $booleanKeys
     * @return array<string, mixed>
     */
    protected function mergeSettingsSection(array $current, array $input, array $booleanKeys): array
    {
        $merged = array_merge($current, $input);

        if ($input !== []) {
            foreach ($booleanKeys as $key) {
                $merged[$key] = array_key_exists($key, $input)
                    ? filter_var($input[$key], FILTER_VALIDATE_BOOLEAN)
                    : false;
            }
        }

        return $merged;
    }

    /**
     * @return array{all: int, onselling: int, auditing: int, offline: int, draft: int, other: int}
     */
    protected function emptyAeStateCounts(int $all = 0): array
    {
        return [
            'all' => $all,
            'active' => 0,
            'inactive' => 0,
            'other' => 0,
        ];
    }

    protected function parseAeStateTab(Request $request): string
    {
        $state = strtolower(trim((string) $request->input('state', 'all')));
        $allowed = ['all', 'active', 'inactive', 'other'];

        return in_array($state, $allowed, true) ? $state : 'all';
    }

    protected function aeStateBucket(?string $state): string
    {
        $state = strtolower(trim((string) $state));
        if (in_array($state, ['active', '1', 'true', 'onselling', 'on_selling'], true)) {
            return 'active';
        }
        if (in_array($state, ['inactive', '0', 'false', 'offline', 'ended'], true)) {
            return 'inactive';
        }

        return 'other';
    }

    /**
     * @param  callable(string): bool  $includeNorm
     * @param  array<string, string>  $normToSku
     * @return array{counts: array{all: int, onselling: int, auditing: int, offline: int, draft: int, other: int}, skusByState: array<string, array<int, string>>, byNorm: array<string, array{state: string, inventory: int|null, title: ?string, price: ?float}>, ready: bool}
     */
    protected function aeStateIndexFromCache(
        TopDawgLiveListingsService $liveService,
        callable $includeNorm,
        int $allCount,
        array $normToSku = []
    ): array {
        $counts = $this->emptyAeStateCounts($allCount);
        $skusByState = [
            'active' => [],
            'inactive' => [],
            'other' => [],
        ];
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
            $bucket = $this->aeStateBucket((string) ($row['state'] ?? ''));
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
            ];
        }

        return ['counts' => $counts, 'skusByState' => $skusByState, 'byNorm' => $byNorm, 'ready' => true];
    }

    /**
     * @param  array{byNorm?: array<string, array{state: string, inventory: int|null, title: ?string, price: ?float}>}  $stateIndex
     * @return array{state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function aeCachedRowForSku(string $sku, array $stateIndex): ?array
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return null;
        }

        return $stateIndex['byNorm'][$norm] ?? null;
    }

    /**
     * SKUs in topdawg_products that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedTopDawgSkus(): array
    {
        if (! Schema::hasTable('topdawg_products')) {
            return [];
        }

        return TopDawgProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->where('topdawg_listing_id', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->pluck('sku')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $linkedSkus
     * @param  array<string, true>  $shopifyNormKeys
     * @return array{all: int, linked: int, unlinked: int, not_in_shopify: int}
     */
    protected function shopifyListingCounts(array $linkedSkus, array $shopifyNormKeys = []): array
    {
        $base = ShopifySku::query()->whereNotNull('sku')->where('sku', '!=', '');
        $all = (clone $base)->count();
        $linked = $linkedSkus === [] ? 0 : (clone $base)->whereIn('sku', $linkedSkus)->count();

        return [
            'all' => $all,
            'linked' => $linked,
            'unlinked' => max(0, $all - $linked),
            'not_in_shopify' => $this->countAeNotInShopify($shopifyNormKeys),
        ];
    }

    /**
     * @return array<string, true>
     */
    protected function shopifyNormalizedSkuKeys(): array
    {
        $keys = [];
        ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select(['id', 'sku'])
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use (&$keys) {
                foreach ($rows as $row) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($norm !== '') {
                        $keys[$norm] = true;
                    }
                }
            });

        return $keys;
    }

    /**
     * @param  array<string, true>  $shopifyNormKeys
     */
    protected function countAeNotInShopify(array $shopifyNormKeys): int
    {
        if (! Schema::hasTable('topdawg_products')) {
            return 0;
        }

        $count = 0;
        $this->aeMetricsWithRealSkuQuery()
            ->select(['id', 'sku'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($shopifyNormKeys, &$count) {
                foreach ($rows as $metric) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
                    if ($norm !== '' && ! isset($shopifyNormKeys[$norm])) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * @param  array<string, true>  $shopifyNormKeys
     */
    protected function paginateAeNotInShopify(
        string $searchSku,
        string $searchName,
        array $shopifyNormKeys,
        int $page,
        int $perPage
    ): LengthAwarePaginator {
        if (! Schema::hasTable('topdawg_products')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (TopDawgProduct $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm === '' || isset($shopifyNormKeys[$norm])) {
                return false;
            }
            if ($searchSku !== '' && ! str_contains(strtoupper((string) $metric->sku), strtoupper($searchSku))) {
                return false;
            }
            if ($searchName !== '') {
                $haystack = strtoupper((string) (($metric->product_title ?? '').' '.$metric->sku));
                if (! str_contains($haystack, strtoupper($searchName))) {
                    return false;
                }
            }

            return true;
        })->values();

        $total = $rows->count();
        $sliceSkus = $rows->slice(($page - 1) * $perPage, $perPage)->pluck('sku')->map(fn ($s) => (string) $s)->all();
        $aeStockMap = $this->topdawgStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (TopDawgProduct $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->topdawg_listing_id,
                'sku' => $sku,
                'title' => $metric->product_title ?? $metric->sku,
                'topdawg_title' => null,
                'image_src' => null,
                'price' => $metric->price ?? null,
                'shopify_price' => null,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => null,
                'linked' => false,
                'listing_status' => 'not_in_shopify',
            ];
        });

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    protected function aeMetricsWithRealSkuQuery()
    {
        return TopDawgProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->where('topdawg_listing_id', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, TopDawgProduct>
     */
    protected function topdawgMetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('topdawg_products')) {
            return [];
        }

        $exact = TopDawgProduct::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (TopDawgProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->where('topdawg_listing_id', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->get() as $metric) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm === '') {
                continue;
            }
            if (! isset($byNorm[$norm])) {
                $byNorm[$norm] = $metric;

                continue;
            }
            $existing = (string) $byNorm[$norm]->sku;
            $existingExact = strtoupper(trim($existing)) === ShopifySku::normalizeSkuForShopifyLookup($existing);
            $thisExact = strtoupper(trim((string) $metric->sku)) === $norm;
            // Prefer the exact listing (ND 58) over a hyphen alias (ND-58).
            if ($thisExact && ! $existingExact) {
                $byNorm[$norm] = $metric;
            }
        }

        $out = [];
        foreach ($skus as $sku) {
            if ($exact->has($sku)) {
                $out[$sku] = $exact->get($sku);

                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && isset($byNorm[$norm])) {
                $out[$sku] = $byNorm[$norm];
            }
        }

        return $out;
    }

    /**
     * Local AE stock for listings index — same resolver as detail pages.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    protected function topdawgStockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_TOPDAWG,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnTopDawg(?TopDawgProduct $metric, string $shopifySku): bool
    {
        if (! $metric || empty($metric->topdawg_listing_id)) {
            return false;
        }

        $mappedSku = trim((string) $metric->sku);
        if ($mappedSku === '' || $mappedSku === (string) $metric->topdawg_listing_id) {
            return false;
        }

        return ShopifySku::normalizeSkuForShopifyLookup($mappedSku)
            === ShopifySku::normalizeSkuForShopifyLookup($shopifySku);
    }

    /**
     * Sales dashboard view (Tabulator + badges from topdawg_order_metrics).
     */
    public function salesDashboard(Request $request): View
    {
        return view('market-places.topdawg_sales_dashboard', [
            'topdawgPercentage' => $this->topDawgMarketplacePercentage(),
        ]);
    }

    /**
     * JSON data for TopDawg sales dashboard.
     */
    public function getSalesData(Request $request): JsonResponse
    {
        if (! Schema::hasTable('topdawg_order_metrics')) {
            return response()->json([]);
        }

        $margin = $this->topDawgMarketplacePercentage() / 100.0;

        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);

            return $sku;
        };

        $orders = TopDawgOrderMetric::query()
            ->orderByDesc('order_date')
            ->orderByDesc('order_paid_at')
            ->orderByDesc('id')
            ->get();

        $skus = $orders->pluck('sku')->filter()->unique()->values()->all();
        $productMasters = \App\Models\ProductMaster::whereIn('sku', $skus)->get();
        $pmBySku = $productMasters->keyBy('sku');
        $pmByNormalized = $productMasters->keyBy(function ($pm) use ($normalizeSku) {
            return $normalizeSku($pm->sku ?? '');
        });

        $result = [];
        foreach ($orders as $row) {
            $sku = $row->sku ?? '';
            $pm = $pmBySku[$sku] ?? $pmByNormalized[$normalizeSku($sku)] ?? null;
            $lp = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values ?? null) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
            }

            $amount = (float) ($row->amount ?? 0);
            $quantity = (int) ($row->quantity ?? 1);
            $quantity = $quantity >= 1 ? $quantity : 1;
            $unitPrice = $quantity > 0 ? $amount / $quantity : 0;
            $cogs = $lp * $quantity;
            $pft = ($unitPrice * $margin - $lp) * $quantity;

            $result[] = [
                'id' => $row->id,
                'order_number' => $row->order_number ?? '',
                'order_date' => $row->order_date ? (\Carbon\Carbon::parse($row->order_date)->format('Y-m-d')) : null,
                'order_paid_at' => $row->order_paid_at ? $row->order_paid_at->format('Y-m-d H:i:s') : null,
                'status' => $row->status ?? '',
                'amount' => round($amount, 2),
                'display_sku' => $row->display_sku ?? '',
                'sku' => $sku,
                'quantity' => $quantity,
                'lp' => round($lp, 2),
                'cogs' => round($cogs, 2),
                'pft' => round($pft, 2),
                'created_at' => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : null,
            ];
        }

        return response()->json($result);
    }

    private function topDawgMarketplacePercentage(): float
    {
        $fromTable = \App\Models\MarketplacePercentage::where('marketplace', 'TopDawg')->value('percentage');

        return $fromTable !== null ? (float) $fromTable : 0.0;
    }

}
