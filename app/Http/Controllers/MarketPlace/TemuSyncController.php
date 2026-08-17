<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmTemuLiveListingsCache;
use App\Models\TemuMetric;
use App\Models\TemuOrder;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\TemuApiService;
use App\Services\MarketplaceManager\TemuDetailFormatter;
use App\Services\MarketplaceManager\TemuInventorySyncService;
use App\Services\MarketplaceManager\TemuLinkMapSyncService;
use App\Services\MarketplaceManager\TemuLiveListingsService;
use App\Services\MarketplaceManager\TemuOrderDetailService;
use App\Services\MarketplaceManager\TemuOrderPushService;
use App\Services\MarketplaceManager\TemuOrderSyncService;
use App\Services\MarketplaceManager\TemuTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
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

class TemuSyncController extends Controller
{
    public function __construct(
        protected TemuApiService $temuApi,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $appKey = (string) config('services.temu.app_key', '');
        $secretKey = (string) config('services.temu.secret_key', '');
        $accessToken = (string) config('services.temu.access_token', '');
        $credentialsReady = $this->temuApi->isConfigured();
        $apiUrl = (string) config('services.temu.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router');

        return view('marketplace.temu.connect', [
            'title' => 'Temu — Connect',
            'connected' => $credentialsReady,
            'credentialsReady' => $credentialsReady,
            'hasAppKey' => filled($appKey),
            'hasSecretKey' => filled($secretKey),
            'hasAccessToken' => filled($accessToken),
            'maskedAppKey' => $this->maskCredential($appKey, 4, 4),
            'maskedSecretKey' => $this->maskCredential($secretKey, 2, 2),
            'maskedAccessToken' => $this->maskCredential($accessToken, 4, 4),
            'apiBase' => $apiUrl,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->temuApi->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Temu API credentials missing. Set TEMU_APP_KEY, TEMU_SECRET_KEY, and TEMU_ACCESS_TOKEN in .env.',
            ]);
        }

        try {
            $result = $this->temuApi->testConnection();
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
        if (! in_array($linkTab, ['all', 'matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true)) {
            $linkTab = 'all';
        }
        $stateTab = $this->parseAeStateTab($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $clearCache = $request->boolean('clear_cache');
        $emptyStateCounts = $this->emptyAeStateCounts();
        $emptyCounts = ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
        $liveLinkTabs = ['matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero'];
        $liveService = app(TemuLiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.temu.products', [
                'products' => $products,
                'title' => 'Temu — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('temu'),
            ]);
        }

        if ($forceLive) {
            WarmTemuLiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedTemuSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Live cache often omits inventory for inactive rows — fill gaps from local map
        // so qty-matched inactive SKUs land in Inactive & Matched (not Mismatch).
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->temuStockMapForSkus($allLinkedVerified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $counts = $classified['counts'] ?? $emptyCounts;
        $counts['all'] = $catalog->countDistinctAllSkus();
        $counts['matched_inactive'] = 0;
        $counts['mismatch_inactive'] = 0;

        if (! $catalog->hasAnyActive()) {
            $apiError = trim(($apiError ? $apiError.' ' : '').'Shared Shopify live catalog is empty — refresh Shopify from Marketplace Manager.');
        }

        $matchedQty = $classified['matched'] ?? [];
        $mismatchQty = $classified['mismatch'] ?? [];
        $zeroQty = $classified['zero'] ?? [];

        if ($mismatchQty !== []) {
            $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($mismatchQty);
            if ($liveShopify === []) {
                $liveShopify = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($mismatchQty);
            }
            $metricMap = $this->temuMetricMapForSkus($mismatchQty);
            $productIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnTemu($metric, (string) $sku)) {
                    continue;
                }
                $pid = (string) ($metric->goods_id ?? '');
                if ($pid === '') {
                    continue;
                }
                $productIds[] = $pid;
                $idToSku[$pid] = (string) $sku;
            }
            $liveMpByUpper = [];
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
                MarketplaceListingStockResolver::mergeLocalAndLiveStockMaps(
                    $liveMpByUpper,
                    $this->temuStockMapForSkus($mismatchQty)
                )
            );
            $matchedQty = $reconciled['matched'];
            $mismatchQty = $reconciled['mismatch'];
            $zeroQty = $reconciled['zero'];
            $counts['matched'] = count($matchedQty);
            $counts['mismatch'] = count($mismatchQty);
            $counts['zero'] = count($zeroQty);
            $counts['linked'] = $counts['matched'] + $counts['mismatch'] + $counts['zero'];
            $counts['linked_with_inv'] = $counts['matched'];
            $counts['linked_zero_inv'] = $counts['zero'];
        }

        $matchedActive = $matchedQty;
        $matchedInactive = [];
        $matchedNormToSku = [];
        foreach ($matchedQty as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $matchedNormToSku[$n] = (string) $sku;
            }
        }
        $matchedStateIndex = $this->aeStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($matchedNormToSku[$norm]),
            count($matchedNormToSku),
            $matchedNormToSku
        );
        if ($matchedStateIndex['ready']) {
            $matchedActive = $catalog->filterSkusByNormalizedAllowList(
                $matchedQty,
                $matchedStateIndex['skusByState']['active'] ?? []
            );
            $matchedInactive = $catalog->excludeSkusByNormalizedList($matchedQty, $matchedActive);
            $counts['matched'] = count($matchedActive);
            $counts['matched_inactive'] = count($matchedInactive);
            $counts['linked_with_inv'] = $counts['matched'];
        }

        $mismatchActive = $mismatchQty;
        $mismatchInactive = [];
        $mismatchNormToSku = [];
        foreach ($mismatchQty as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $mismatchNormToSku[$n] = (string) $sku;
            }
        }
        $mismatchStateIndex = $this->aeStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($mismatchNormToSku[$norm]),
            count($mismatchNormToSku),
            $mismatchNormToSku
        );
        if ($mismatchStateIndex['ready']) {
            $mismatchActive = $catalog->filterSkusByNormalizedAllowList(
                $mismatchQty,
                $mismatchStateIndex['skusByState']['active'] ?? []
            );
            $mismatchInactive = $catalog->excludeSkusByNormalizedList($mismatchQty, $mismatchActive);
            $counts['mismatch'] = count($mismatchActive);
            $counts['mismatch_inactive'] = count($mismatchInactive);
        }

        $linkedVerified = match ($linkTab) {
            'mismatch' => $mismatchActive,
            'mismatch_inactive' => $mismatchInactive,
            'zero' => $zeroQty,
            'matched_inactive' => $matchedInactive,
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
            WarmTemuLiveListingsCache::dispatch();
        }

        $query = ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($searchSku !== '') {
            $query->where('sku', 'like', '%'.$searchSku.'%');
        }
        if ($searchName !== '') {
            $query->where(function ($q) use ($searchName) {
                $q->where('goods_summary', 'like', '%'.$searchName.'%')
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
        $aeMap = $this->temuMetricMapForSkus($skus);
        $aeStockMap = $this->temuStockMapForSkus($skus);
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
                if (! $metric || ! $this->isShopifySkuLinkedOnTemu($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) $metric->goods_id;
            }
            if ($needIds !== []) {
                $pageLiveByProduct = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $aeStockMap, $liveShopifyQty, $stateIndex, $pageLiveByProduct) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnTemu($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) ($metric->goods_id ?? '') : '';
            $aeQty = $linked
                ? MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku, $metricSku)
                : null;
            $cached = $this->aeCachedRowForSku($sku, $stateIndex);
            $live = ($pid !== '' && isset($pageLiveByProduct[$pid])) ? $pageLiveByProduct[$pid] : null;
            $state = (string) ($live['state'] ?? $cached['state'] ?? '');
            if ($linked && $live !== null && array_key_exists('inventory', $live) && $live['inventory'] !== null) {
                $aeQty = (int) $live['inventory'];
            } elseif ($linked && $cached && array_key_exists('inventory', $cached) && $cached['inventory'] !== null) {
                $aeQty = (int) $cached['inventory'];
            }

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku' => $sku,
                'title' => trim(($row->goods_summary ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'temu_title' => $live['title'] ?? ($cached['title'] ?? ($metric->goods_summary ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ($metric->base_price ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'temu_state' => $state !== '' ? $state : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.temu.products', [
            'products' => $paginator,
            'title' => 'Temu — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('temu'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->temuMetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnTemu($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'cached';
        $aeSkuRows = [];

        $title = trim(($shopifyRow->goods_summary ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(TemuDetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.temu.product-show', [
            'title' => 'Temu Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('temu'),
        ]);
    }

    /**
     * Push live Shopify qty → Temu for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('temu');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in Temu settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->temuMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnTemu($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Temu. Run Sync Temu link map first.',
            ], 422);
        }

        $result = app(TemuInventorySyncService::class)->syncSkusFromShopify([$sku]);

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

    public function pullProductFromTemu(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('temu')) {
            return response()->json(['success' => false, 'message' => 'Temu not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->temuMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $metric?->goods_id || (string) $metric->goods_id === (string) $metric->sku) {
            return response()->json([
                'success' => false,
                'message' => 'No Temu goods_id mapped for this SKU. Run Sync Temu link map on Listings first.',
            ]);
        }

        try {
            $this->temuApi->getInventory();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh Temu inventory: '.$e->getMessage(),
            ]);
        }

        $metric->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Refreshed Temu metrics for '.$sku.' from API. Nothing was pushed to Shopify or Temu.',
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('temu')) {
            return response()->json(['success' => false, 'message' => 'Temu not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('Temu link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(TemuLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(TemuLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('temu_orders')) {
            // Temu schedule fetches without auto-import (same as Reverb manual-push flow).
            // Unstick failed/orphan "queued" rows so Shopify column shows Pending, not Queued.
            if (Schema::hasColumn('temu_orders', 'import_status')) {
                TemuOrder::query()
                    ->whereNull('shopify_order_id')
                    ->where('import_status', 'queued')
                    ->update(['import_status' => null]);
            }

            $cutoff = TemuOrderSyncService::MIN_ORDER_DATE.' 00:00:00';
            // One row per parent order (Shopify import is parent-scoped).
            // Hide pre-cutoff rows (same as Reverb) — older orders were entered on Shopify manually.
            $orders = TemuOrder::query()
                ->where('parent_order_time', '>=', $cutoff)
                ->whereIn('id', function ($q) use ($cutoff) {
                    $q->selectRaw('MAX(id)')
                        ->from('temu_orders')
                        ->where('parent_order_time', '>=', $cutoff)
                        ->groupBy('parent_order_sn');
                })
                ->orderByDesc('parent_order_time')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.temu.orders', [
            'orders' => $orders,
            'title' => 'Temu — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('temu'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('temu'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = TemuOrder::query()->findOrFail($id);
        $orderId = (string) $line->parent_order_sn;

        $lines = TemuOrder::query()
            ->where('parent_order_sn', $orderId)
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(TemuOrderDetailService::class);

        if ($this->apiConfig->isConfigured('temu')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live Temu order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(TemuDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.temu.order-show', [
            'title' => 'Temu Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('temu'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('temu'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('temu', $line),
        ]);
    }

    public function pullOrderFromTemu(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('temu')) {
            return response()->json(['success' => false, 'message' => 'Temu not connected.']);
        }

        $line = TemuOrder::query()->findOrFail($id);
        $result = app(TemuOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->parent_order_sn);

        // Persist only — Shopify address fill matches AliExpress / Reverb (SyncTemuAddressJob).
        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from Temu.' : 'Failed to pull order details.'),
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to Temu (Ship Order).
     */
    public function pushTrackingToTemu(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('temu')) {
            return response()->json(['success' => false, 'message' => 'Temu not connected.']);
        }

        $line = TemuOrder::query()->findOrFail($id);
        $result = app(TemuTrackingSyncService::class)->pushTrackingForOrder($line);

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
     * Bulk push Shopify tracking → Temu for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('temu')) {
            return response()->json(['success' => false, 'message' => 'Temu not connected.']);
        }

        \App\Jobs\SyncTemuTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and ships orders on Temu.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(TemuOrderSyncService::class);

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
        if ($request->boolean('import')) {
            $dispatched = $sync->dispatchImportsForNewOrders();
            $result['message'] .= " Dispatched {$dispatched} import job(s).";
        } elseif (Schema::hasTable('temu_orders') && Schema::hasColumn('temu_orders', 'import_status')) {
            // Fetch without import must leave rows Pending (same as Reverb), not stuck Queued.
            TemuOrder::query()
                ->whereNull('shopify_order_id')
                ->where('import_status', 'queued')
                ->update(['import_status' => null]);
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
        $settings = MarketplaceSyncSettings::getFor('temu');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('temu');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('temu');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(TemuLiveListingsService::class);
        $linkedSkus = $this->linkedTemuSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->temuStockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $mismatchQty = $classified['mismatch'] ?? [];
        $scope = strtolower((string) $request->input('scope', $request->input('link', 'all')));
        if (in_array($scope, ['mismatch', 'active', 'mismatch_active'], true)) {
            $mismatchNormToSku = [];
            foreach ($mismatchQty as $sku) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($n !== '') {
                    $mismatchNormToSku[$n] = (string) $sku;
                }
            }
            $idx = $this->aeStateIndexFromCache(
                $liveService,
                static fn (string $norm): bool => isset($mismatchNormToSku[$norm]),
                count($mismatchNormToSku),
                $mismatchNormToSku
            );
            if ($idx['ready']) {
                $mismatch = $catalog->filterSkusByNormalizedAllowList(
                    $mismatchQty,
                    $idx['skusByState']['active'] ?? []
                );
            } else {
                $mismatch = $mismatchQty;
            }
        } elseif (in_array($scope, ['mismatch_inactive', 'inactive'], true)) {
            $mismatchNormToSku = [];
            foreach ($mismatchQty as $sku) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($n !== '') {
                    $mismatchNormToSku[$n] = (string) $sku;
                }
            }
            $idx = $this->aeStateIndexFromCache(
                $liveService,
                static fn (string $norm): bool => isset($mismatchNormToSku[$norm]),
                count($mismatchNormToSku),
                $mismatchNormToSku
            );
            if ($idx['ready']) {
                $active = $catalog->filterSkusByNormalizedAllowList(
                    $mismatchQty,
                    $idx['skusByState']['active'] ?? []
                );
                $mismatch = $catalog->excludeSkusByNormalizedList($mismatchQty, $active);
            } else {
                $mismatch = [];
            }
        } else {
            $mismatch = $mismatchQty;
        }

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

        $result = app(TemuInventorySyncService::class)->syncSkusFromShopify($batch);
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
        $order = TemuOrder::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(TemuOrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('temu', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(TemuOrderPushService::class);
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

        TemuOrder::query()
            ->where('parent_order_sn', (string) $order->parent_order_sn)
            ->whereNull('shopify_order_id')
            ->update(['import_status' => 'import_failed']);

        return response()->json([
            'success' => false,
            'message' => $push->lastFailureReason ?: 'Shopify import failed.',
        ], 422);
    }

    /**
     * Push multiple Temu parent orders to Shopify (max 50).
     */
    public function bulkPushOrdersToShopify(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));

        if ($ids === []) {
            return response()->json(['success' => false, 'message' => 'No orders selected.'], 422);
        }
        if (count($ids) > 50) {
            return response()->json(['success' => false, 'message' => 'Select at most 50 orders at a time.'], 422);
        }

        $rows = TemuOrder::query()->whereIn('id', $ids)->orderBy('id')->get();
        $push = app(TemuOrderPushService::class);
        $seenParents = [];
        $pushed = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        foreach ($rows as $order) {
            $parent = trim((string) $order->parent_order_sn);
            if ($parent !== '' && isset($seenParents[$parent])) {
                $skipped++;

                continue;
            }
            if ($parent !== '') {
                $seenParents[$parent] = true;
            }

            if (! empty($order->shopify_order_id)) {
                $skipped++;
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'status' => 'skipped',
                    'message' => 'Already imported',
                    'shopify_order_id' => $order->shopify_order_id,
                ];

                continue;
            }

            if (MarketplaceOrderPaidFilter::blocksUnpaidPush('temu', $order)) {
                $skipped++;
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'status' => 'skipped',
                    'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
                ];

                continue;
            }

            try {
                $shopifyOrderId = $push->importToShopify($order);
            } catch (\Throwable $e) {
                $failed++;
                TemuOrder::query()
                    ->where('parent_order_sn', $parent)
                    ->whereNull('shopify_order_id')
                    ->update(['import_status' => 'import_failed']);
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'status' => 'failed',
                    'message' => $e->getMessage() ?: 'Shopify import failed.',
                ];
                usleep(250000);

                continue;
            }

            if ($shopifyOrderId) {
                $pushed++;
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'status' => 'pushed',
                    'shopify_order_id' => $shopifyOrderId,
                ];
            } else {
                $failed++;
                TemuOrder::query()
                    ->where('parent_order_sn', $parent)
                    ->whereNull('shopify_order_id')
                    ->update(['import_status' => 'import_failed']);
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'status' => 'failed',
                    'message' => $push->lastFailureReason ?: 'Shopify import failed.',
                ];
            }

            usleep(350000);
        }

        return response()->json([
            'success' => $failed === 0,
            'message' => "Bulk push: {$pushed} pushed, {$skipped} skipped, {$failed} failed.",
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
        ], $failed > 0 ? 422 : 200);
    }

    /**
     * Reverse a Shopify push for selected Temu rows (selected SKU only).
     * Clears local shopify_order_id for matching SKU lines under each parent.
     * Does NOT delete or cancel the Shopify order.
     */
    public function reversePushOrders(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));

        if ($ids === []) {
            return response()->json(['success' => false, 'message' => 'No orders selected.'], 422);
        }
        if (count($ids) > 50) {
            return response()->json(['success' => false, 'message' => 'Select at most 50 orders at a time.'], 422);
        }

        $rows = TemuOrder::query()->whereIn('id', $ids)->orderBy('id')->get();
        $reversed = 0;
        $skipped = 0;
        $lineCount = 0;
        $results = [];
        $seen = [];

        foreach ($rows as $order) {
            $parent = trim((string) $order->parent_order_sn);
            $sku = trim((string) ($order->ext_code ?: $order->display_sku ?: ''));
            $key = $parent.'|'.$sku.'|'.$order->id;
            if (isset($seen[$key])) {
                $skipped++;

                continue;
            }
            $seen[$key] = true;

            if (empty($order->shopify_order_id)) {
                $skipped++;
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'sku' => $sku,
                    'status' => 'skipped',
                    'message' => 'Not pushed to Shopify.',
                ];

                continue;
            }

            $query = TemuOrder::query()
                ->where('parent_order_sn', $parent)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '');

            // Selected SKU only — do not unlink sibling SKUs on the same parent.
            if ($sku !== '') {
                $query->where(function ($q) use ($sku) {
                    $q->where('ext_code', $sku)->orWhere('display_sku', $sku);
                });
            } else {
                $query->where('id', $order->id);
            }

            $updated = $query->update([
                'shopify_order_id' => null,
                'pushed_to_shopify_at' => null,
                'import_status' => null,
            ]);

            if ($updated > 0) {
                $reversed++;
                $lineCount += $updated;
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'sku' => $sku,
                    'status' => 'reversed',
                    'lines' => $updated,
                    'message' => 'Unlinked from Shopify (local only).',
                ];
            } else {
                $skipped++;
                $results[] = [
                    'id' => $order->id,
                    'parent_order_sn' => $parent,
                    'sku' => $sku,
                    'status' => 'skipped',
                    'message' => 'No matching pushed SKU lines found.',
                ];
            }
        }

        return response()->json([
            'success' => $reversed > 0,
            'message' => "Reverse push: {$reversed} order(s) / {$lineCount} line(s) unlinked, {$skipped} skipped. Shopify orders were not deleted.",
            'reversed' => $reversed,
            'lines' => $lineCount,
            'skipped' => $skipped,
            'results' => $results,
        ], $reversed > 0 ? 200 : 422);
    }

    /**
     * Delete a local Temu order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = TemuOrder::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (! empty($order->shopify_order_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This order is already imported to Shopify and cannot be deleted here.',
            ], 422);
        }

        $orderId = (string) $order->parent_order_sn;
        $deleted = TemuOrder::query()
            ->where('parent_order_sn', $orderId)
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed Temu order {$orderId} from ready-for-import ({$deleted} row(s)).",
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
        $order = TemuOrder::find($id);

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

        $orderId = (string) $order->parent_order_sn;
        $shopifyOrderId = trim((string) $request->input('shopify_order_id', ''));
        if ($shopifyOrderId === '') {
            $shopifyOrderId = 'manual:'.$orderId;
        }

        $updated = TemuOrder::query()
            ->where('parent_order_sn', $orderId)
            ->whereNull('shopify_order_id')
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'import_status' => 'imported',
                'pushed_to_shopify_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked Temu order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.temu.settings', [
            'settings' => MarketplaceSyncSettings::getFor('temu'),
            'title' => 'Temu — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('temu'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('temu');

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
            'push_tracking_to_temu', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_temu', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('temu', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Temu sync settings saved.']);
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
        TemuLiveListingsService $liveService,
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
     * SKUs in temu_products that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedTemuSkus(): array
    {
        if (! Schema::hasTable('temu_metrics')) {
            return [];
        }

        return TemuMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('goods_id')
            ->where('sku', '!=', '')
            ->where('goods_id', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id')
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
        if (! Schema::hasTable('temu_metrics')) {
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
        if (! Schema::hasTable('temu_metrics')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (TemuMetric $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm === '' || isset($shopifyNormKeys[$norm])) {
                return false;
            }
            if ($searchSku !== '' && ! str_contains(strtoupper((string) $metric->sku), strtoupper($searchSku))) {
                return false;
            }
            if ($searchName !== '') {
                $haystack = strtoupper((string) (($metric->goods_summary ?? '').' '.$metric->sku));
                if (! str_contains($haystack, strtoupper($searchName))) {
                    return false;
                }
            }

            return true;
        })->values();

        $total = $rows->count();
        $sliceSkus = $rows->slice(($page - 1) * $perPage, $perPage)->pluck('sku')->map(fn ($s) => (string) $s)->all();
        $aeStockMap = $this->temuStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (TemuMetric $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->goods_id,
                'sku' => $sku,
                'title' => $metric->goods_summary ?? $metric->sku,
                'temu_title' => null,
                'image_src' => null,
                'price' => $metric->base_price ?? null,
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
        return TemuMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('goods_id')
            ->where('sku', '!=', '')
            ->where('goods_id', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, TemuMetric>
     */
    protected function temuMetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('temu_metrics')) {
            return [];
        }

        $exact = TemuMetric::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (TemuMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('goods_id')
            ->where('sku', '!=', '')
            ->where('goods_id', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id')
            ->get() as $metric) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm !== '' && ! isset($byNorm[$norm])) {
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
    protected function temuStockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_TEMU,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnTemu(?TemuMetric $metric, string $shopifySku): bool
    {
        if (! $metric || empty($metric->goods_id)) {
            return false;
        }

        $mappedSku = trim((string) $metric->sku);
        if ($mappedSku === '' || $mappedSku === (string) $metric->goods_id) {
            return false;
        }

        return ShopifySku::normalizeSkuForShopifyLookup($mappedSku)
            === ShopifySku::normalizeSkuForShopifyLookup($shopifySku);
    }


}
