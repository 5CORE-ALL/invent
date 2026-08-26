<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmPurchasingPowerLiveListingsCache;
use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\PurchasingPowerApiService;
use App\Services\MarketplaceManager\PurchasingPowerDetailFormatter;
use App\Services\MarketplaceManager\PurchasingPowerInventorySyncService;
use App\Services\MarketplaceManager\PurchasingPowerLinkMapSyncService;
use App\Services\MarketplaceManager\PurchasingPowerLiveListingsService;
use App\Services\MarketplaceManager\PurchasingPowerOrderDetailService;
use App\Services\MarketplaceManager\PurchasingPowerOrderPushService;
use App\Services\MarketplaceManager\PurchasingPowerOrderSyncService;
use App\Services\MarketplaceManager\PurchasingPowerTrackingSyncService;
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

class PurchasingPowerSyncController extends Controller
{
    public function __construct(
        protected PurchasingPowerApiService $ppApi,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $mcmKey = (string) config('services.purchasingpower.mcm_api_key', '');
        $apiKey = (string) config('services.purchasingpower.api_key', '');
        $credentialsReady = $this->ppApi->isConfigured();
        $mcmBaseUrl = (string) config('services.purchasingpower.mcm_base_url', 'https://purchasingpowerus-prod.mirakl.net');

        return view('marketplace.purchasingpower.connect', [
            'title' => 'Purchasing Power — Connect',
            'connected' => $this->apiConfig->isConfigured('purchasingpower'),
            'credentialsReady' => $credentialsReady,
            'hasMcmApiKey' => filled($mcmKey),
            'hasApiKey' => filled($apiKey),
            'maskedMcmApiKey' => $this->maskCredential($mcmKey, 4, 4),
            'maskedApiKey' => $this->maskCredential($apiKey, 4, 4),
            'mcmBaseUrl' => $mcmBaseUrl,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->ppApi->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Purchasing Power API credentials missing. Set PURCHASING_POWER_MCM_API_KEY (or PURCHASING_POWER_API_KEY) in .env.',
            ]);
        }

        try {
            $result = $this->ppApi->testConnection();
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
        $liveLinkTabs = ['matched', 'mismatch', 'zero'];
        $liveService = app(PurchasingPowerLiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.purchasingpower.products', [
                'products' => $products,
                'title' => 'PurchasingPower — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('purchasingpower'),
            ]);
        }

        if ($forceLive) {
            WarmPurchasingPowerLiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedPurchasingPowerSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Live cache often omits inventory for inactive rows — fill gaps from local map
        // so qty-matched inactive SKUs land in Inactive & Matched (not Mismatch).
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->purchasingpowerStockMapForSkus($allLinkedVerified)
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
            $metricMap = $this->purchasingpowerMetricMapForSkus($mismatchQty);
            $productIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnPurchasingPower($metric, (string) $sku)) {
                    continue;
                }
                $pid = (string) ($metric->sku ?? '');
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
                $liveMpByUpper
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
        $matchedActive = $overlay['matchedActive'];
        $matchedInactive = $overlay['matchedInactive'];
        $mismatchActive = $overlay['mismatchActive'];
        $mismatchInactive = $overlay['mismatchInactive'];

        $portalTabs = ['matched_inactive', 'mismatch_inactive'];
        if (in_array($linkTab, $portalTabs, true)) {
            if ($counts[$linkTab] === 0 && ! $forceLive) {
                WarmPurchasingPowerLiveListingsCache::dispatch();
            }
            $paginator = MarketplacePortalStatusTabs::paginate(
                $linkTab === 'mismatch_inactive' ? 'active' : 'inactive',
                $linkTab === 'mismatch_inactive' ? $mismatchInactive : $matchedInactive,
                $liveRows,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                'purchasingpower_state',
                'purchasingpower_title'
            );

            return view('marketplace.purchasingpower.products', [
                'products' => $paginator,
                'title' => 'Purchasing Power — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => 'all',
                'counts' => $counts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => is_array($liveService->peekCached()),
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('purchasingpower'),
                'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
            ]);
        }

        $linkedVerified = match ($linkTab) {
            'mismatch' => $mismatchActive,
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
            WarmPurchasingPowerLiveListingsCache::dispatch();
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
        $aeMap = $this->purchasingpowerMetricMapForSkus($skus);
        $aeStockMap = $this->purchasingpowerStockMapForSkus($skus);
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
                if (! $metric || ! $this->isShopifySkuLinkedOnPurchasingPower($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) $metric->sku;
            }
            if ($needIds !== []) {
                $pageLiveByProduct = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $aeStockMap, $liveShopifyQty, $stateIndex, $pageLiveByProduct) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnPurchasingPower($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) ($metric->sku ?? '') : '';
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
                'purchasingpower_title' => $live['title'] ?? ($cached['title'] ?? ($metric->product_title ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ($metric->price ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'stock' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'purchasingpower_state' => $state !== '' ? $state : null,
                'mp_state' => $state !== '' ? $state : null,
                'inactive_reason' => MarketplacePortalStatusTabs::bucket($state) === 'inactive'
                    ? MarketplacePortalStatusTabs::inactiveReason(is_array($live) ? $live : (is_array($cached) ? $cached : []), $aeQty)
                    : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.purchasingpower.products', [
            'products' => $paginator,
            'title' => 'PurchasingPower — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('purchasingpower'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->purchasingpowerMetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnPurchasingPower($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'cached';
        $aeSkuRows = [];

        $title = trim(($shopifyRow->product_title ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(PurchasingPowerDetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.purchasingpower.product-show', [
            'title' => 'PurchasingPower Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('purchasingpower'),
        ]);
    }

    /**
     * Push live Shopify qty → PurchasingPower for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('purchasingpower');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in PurchasingPower settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->purchasingpowerMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnPurchasingPower($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on PurchasingPower. Run Sync PurchasingPower link map first.',
            ], 422);
        }

        $result = app(PurchasingPowerInventorySyncService::class)->syncSkusFromShopify([$sku]);

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

    public function pullProductFromPurchasingPower(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('purchasingpower')) {
            return response()->json(['success' => false, 'message' => 'PurchasingPower not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->purchasingpowerMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $metric?->sku || (string) $metric->sku === (string) $metric->sku ?? $sku) {
            return response()->json([
                'success' => false,
                'message' => 'No PurchasingPower sku mapped for this SKU. Run Sync PurchasingPower link map on Listings first.',
            ]);
        }

        try {
            $this->ppApi->getInventory();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh PurchasingPower inventory: '.$e->getMessage(),
            ]);
        }

        $metric->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Refreshed PurchasingPower metrics for '.$sku.' from API. Nothing was pushed to Shopify or PurchasingPower.',
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('purchasingpower')) {
            return response()->json(['success' => false, 'message' => 'PurchasingPower not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('PurchasingPower link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(PurchasingPowerLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(PurchasingPowerLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('purchasing_power_sales')) {
            $orders = PurchasingPowerSale::query()
                ->orderByDesc('date_created')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.purchasingpower.orders', [
            'orders' => $orders,
            'title' => 'Purchasing Power — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('purchasingpower'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('purchasingpower'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = PurchasingPowerSale::query()->findOrFail($id);
        $orderId = (string) ($line->order_id ?: $line->order_number);

        $lines = PurchasingPowerSale::query()
            ->where(function ($q) use ($orderId) {
                $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
            })
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(PurchasingPowerOrderDetailService::class);

        if ($this->apiConfig->isConfigured('purchasingpower')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live PurchasingPower order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(PurchasingPowerDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.purchasingpower.order-show', [
            'title' => 'PurchasingPower Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('purchasingpower'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('purchasingpower'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('purchasingpower', $line),
        ]);
    }

    public function pullOrderFromPurchasingPower(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('purchasingpower')) {
            return response()->json(['success' => false, 'message' => 'PurchasingPower not connected.']);
        }

        $line = PurchasingPowerSale::query()->findOrFail($id);
        $result = app(PurchasingPowerOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->order_number);

        // Persist only — Shopify address fill matches AliExpress / Reverb (SyncPurchasingPowerAddressJob).
        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from PurchasingPower.' : 'Failed to pull order details.'),
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to PurchasingPower (Ship Order).
     */
    public function pushTrackingToPurchasingPower(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('purchasingpower')) {
            return response()->json(['success' => false, 'message' => 'PurchasingPower not connected.']);
        }

        $line = PurchasingPowerSale::query()->findOrFail($id);
        $result = app(PurchasingPowerTrackingSyncService::class)->pushTrackingForOrder($line);

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
     * Bulk push Shopify tracking → PurchasingPower for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('purchasingpower')) {
            return response()->json(['success' => false, 'message' => 'PurchasingPower not connected.']);
        }

        \App\Jobs\SyncPurchasingPowerTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and ships orders on PurchasingPower.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(PurchasingPowerOrderSyncService::class);

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
        if ($request->boolean('import') || MarketplaceSyncSettings::canAutoImportToShopify('purchasingpower')) {
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
        $settings = MarketplaceSyncSettings::getFor('purchasingpower');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('purchasingpower');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('purchasingpower');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(PurchasingPowerLiveListingsService::class);
        $linkedSkus = $this->linkedPurchasingPowerSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->purchasingpowerStockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $mismatchQty = $classified['mismatch'] ?? [];
        $scope = strtolower((string) $request->input('scope', $request->input('link', 'all')));
        $mismatch = in_array($scope, ['mismatch_inactive', 'inactive', 'matched_inactive'], true)
            ? []
            : $mismatchQty;

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

        $result = app(PurchasingPowerInventorySyncService::class)->syncSkusFromShopify($batch, null, true);
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
        $order = PurchasingPowerSale::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(PurchasingPowerOrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('purchasingpower', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(PurchasingPowerOrderPushService::class);
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

            return response()->json([
                'success' => true,
                'message' => 'Pushed to Shopify.',
                'shopify_order_id' => $shopifyOrderId,
            ]);
        }

        $order->update(['import_status' => 'import_failed']);

        return response()->json([
            'success' => false,
            'message' => $push->lastFailureReason ?: 'Shopify import failed.',
        ], 422);
    }

    /**
     * Delete a local PurchasingPower order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = PurchasingPowerSale::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (! empty($order->shopify_order_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This order is already imported to Shopify and cannot be deleted here.',
            ], 422);
        }

        $orderId = (string) $order->order_number;
        $deleted = PurchasingPowerSale::query()
            ->where('order_number', $orderId)
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed PurchasingPower order {$orderId} from ready-for-import ({$deleted} row(s)).",
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
        $order = PurchasingPowerSale::find($id);

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

        $orderId = (string) $order->order_number;
        $shopifyOrderId = trim((string) $request->input('shopify_order_id', ''));
        if ($shopifyOrderId === '') {
            $shopifyOrderId = 'manual:'.$orderId;
        }

        $updated = PurchasingPowerSale::query()
            ->where('order_number', $orderId)
            ->whereNull('shopify_order_id')
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'import_status' => 'imported',
                'pushed_to_shopify_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked PurchasingPower order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.purchasingpower.settings', [
            'settings' => MarketplaceSyncSettings::getFor('purchasingpower'),
            'title' => 'PurchasingPower — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('purchasingpower'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('purchasingpower');

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
            'push_tracking_to_purchasingpower', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_purchasingpower', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('purchasingpower', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'PurchasingPower sync settings saved.']);
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
        PurchasingPowerLiveListingsService $liveService,
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
     * SKUs in purchasingpower_products that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedPurchasingPowerSkus(): array
    {
        if (! Schema::hasTable('purchasing_power_products')) {
            return [];
        }

        return PurchasingPowerProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
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
        if (! Schema::hasTable('purchasing_power_products')) {
            return 0;
        }

        $count = 0;
        $this->aeMetricsWithRealSkuQuery()
            ->select(['id', 'sku'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($shopifyNormKeys, &$count) {
                foreach ($rows as $metric) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku ?? $sku);
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
        if (! Schema::hasTable('purchasing_power_products')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (PurchasingPowerProduct $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku ?? $sku);
            if ($norm === '' || isset($shopifyNormKeys[$norm])) {
                return false;
            }
            if ($searchSku !== '' && ! str_contains(strtoupper((string) $metric->sku ?? $sku), strtoupper($searchSku))) {
                return false;
            }
            if ($searchName !== '') {
                $haystack = strtoupper((string) (($metric->product_title ?? '').' '.$metric->sku ?? $sku));
                if (! str_contains($haystack, strtoupper($searchName))) {
                    return false;
                }
            }

            return true;
        })->values();

        $total = $rows->count();
        $sliceSkus = $rows->slice(($page - 1) * $perPage, $perPage)->pluck('sku')->map(fn ($s) => (string) $s)->all();
        $aeStockMap = $this->purchasingpowerStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (PurchasingPowerProduct $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->sku,
                'sku' => $sku,
                'title' => $metric->product_title ?? $metric->sku,
                'purchasingpower_title' => null,
                'image_src' => null,
                'price' => $metric->price ?? null,
                'shopify_price' => null,
                'stock' => $aeQty,
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
        return PurchasingPowerProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, PurchasingPowerProduct>
     */
    protected function purchasingpowerMetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('purchasing_power_products')) {
            return [];
        }

        $exact = PurchasingPowerProduct::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (PurchasingPowerProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get() as $metric) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku ?? $sku);
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
    protected function purchasingpowerStockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_PURCHASINGPOWER,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnPurchasingPower(?PurchasingPowerProduct $metric, string $shopifySku): bool
    {
        if (! $metric || trim((string) $metric->sku ?? $sku) === '') {
            return false;
        }

        return ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku ?? $sku)
            === ShopifySku::normalizeSkuForShopifyLookup($shopifySku);
    }


}
