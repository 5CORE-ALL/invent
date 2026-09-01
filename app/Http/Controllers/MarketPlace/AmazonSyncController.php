<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmAmazonLiveListingsCache;
use App\Models\AmazonListingStatus;
use App\Models\AmazonOrder;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\MarketplaceManager\AmazonDetailFormatter;
use App\Services\MarketplaceManager\AmazonInventorySyncService;
use App\Services\MarketplaceManager\AmazonLinkMapSyncService;
use App\Services\MarketplaceManager\AmazonListingStatusHelper;
use App\Services\MarketplaceManager\AmazonLiveListingsService;
use App\Services\MarketplaceManager\AmazonOrderDetailService;
use App\Services\MarketplaceManager\AmazonOrderPushService;
use App\Services\MarketplaceManager\AmazonOrderSyncService;
use App\Services\MarketplaceManager\AmazonTrackingSyncService;
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

class AmazonSyncController extends Controller
{
    public function __construct(
        protected AmazonSpApiService $amazonApi,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $clientId = (string) config('services.amazon_sp.client_id');
        $clientSecret = (string) config('services.amazon_sp.client_secret');
        $refresh = (string) config('services.amazon_sp.refresh_token');
        $sellerId = (string) config('services.amazon_sp.seller_id');
        $marketplaceId = (string) config('services.amazon_sp.marketplace_id');

        return view('marketplace.amazon.connect', [
            'title' => 'Amazon — Connect',
            'connected' => $this->apiConfig->isConfigured('amazon'),
            'credentialsReady' => $this->apiConfig->isConfigured('amazon'),
            'hasClientId' => filled($clientId),
            'hasClientSecret' => filled($clientSecret),
            'hasRefreshToken' => filled($refresh),
            'hasSellerId' => filled($sellerId),
            'hasMarketplaceId' => filled($marketplaceId),
            'maskedClientId' => $this->maskCredential($clientId),
            'maskedClientSecret' => $this->maskCredential($clientSecret, 2, 2),
            'maskedRefreshToken' => $this->maskCredential($refresh, 4, 4),
            'maskedSellerId' => $this->maskCredential($sellerId),
            'marketplaceId' => $marketplaceId ?: '—',
            'apiBase' => 'https://sellingpartnerapi-na.amazon.com',
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('amazon')) {
            return response()->json([
                'success' => false,
                'message' => 'Amazon API credentials missing. Set AMAZON_API_TOKEN in .env.',
            ]);
        }

        try {
            $result = $this->amazonApi->testConnection();
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
        $liveService = app(AmazonLiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.amazon.products', [
                'products' => $products,
                'title' => 'Amazon — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('amazon'),
            ]);
        }

        if ($forceLive) {
            WarmAmazonLiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedAmazonSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Live cache often omits inventory for inactive rows — fill gaps from local map
        // so qty-matched inactive SKUs land in Inactive & Matched (not Mismatch).
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->amazonStockMapForSkus($allLinkedVerified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'amazon');
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
            $metricMap = $this->amazonListingMapForSkus($mismatchQty);
            $productIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnAmazon($metric, (string) $sku)) {
                    continue;
                }
                $pid = (string) (AmazonListingStatusHelper::resolveProductId($metric) ?? '');
                if ($pid === '') {
                    continue;
                }
                $productIds[] = $pid;
                $idToSku[$pid] = (string) $sku;
            }
            // Same stock map the table columns use so equal Shopify/Amz qty
            // is not left on Mismatch when ASIN live lookup misses.
            $liveMpByUpper = $this->amazonStockMapForSkus($mismatchQty);
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
                'amazon'
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
                WarmAmazonLiveListingsCache::dispatch();
            }
            $paginator = MarketplacePortalStatusTabs::paginate(
                $linkTab === 'mismatch_inactive' ? 'active' : 'inactive',
                $linkTab === 'mismatch_inactive' ? $mismatchInactive : $matchedInactive,
                $liveRows,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                'amazon_state',
                'amazon_title'
            );

            return view('marketplace.amazon.products', [
                'products' => $paginator,
                'title' => 'Amz — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => 'all',
                'counts' => $counts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => is_array($liveService->peekCached()),
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('amazon'),
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
            WarmAmazonLiveListingsCache::dispatch();
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
        $aeMap = $this->amazonListingMapForSkus($skus);
        $aeStockMap = $this->amazonStockMapForSkus($skus);
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
                if (! $metric || ! $this->isShopifySkuLinkedOnAmazon($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) AmazonListingStatusHelper::resolveProductId($metric);
            }
            if ($needIds !== []) {
                $pageLiveByProduct = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $aeStockMap, $liveShopifyQty, $stateIndex, $pageLiveByProduct) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnAmazon($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) (AmazonListingStatusHelper::resolveProductId($metric) ?? '') : '';
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
                'amazon_title' => $live['title'] ?? ($cached['title'] ?? ((AmazonListingStatusHelper::valueArray($metric)['title'] ?? null) ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ((AmazonListingStatusHelper::valueArray($metric)['price'] ?? null) ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'amazon_state' => $state !== '' ? $state : null,
                'mp_state' => $state !== '' ? $state : null,
                'inactive_reason' => MarketplacePortalStatusTabs::bucket($state) === 'inactive'
                    ? MarketplacePortalStatusTabs::inactiveReason(is_array($live) ? $live : (is_array($cached) ? $cached : []), $aeQty)
                    : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.amazon.products', [
            'products' => $paginator,
            'title' => 'Amazon — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('amazon'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->amazonListingMapForSkus([$sku])[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnAmazon($metric, $sku);
        $title = trim(($shopifyRow->product_title ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(AmazonDetailFormatter::class)->formatProduct($metric, $shopifyRow);

        return view('marketplace.amazon.product-show', [
            'title' => 'Amazon Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => null,
            'aeDataSource' => $linked ? 'cached' : 'none',
            'connected' => $this->apiConfig->isConfigured('amazon'),
        ]);
    }

    /**
     * Push live Shopify qty → Amazon for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in Amazon settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->amazonListingMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnAmazon($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Amazon. Run Sync Amazon link map first.',
            ], 422);
        }

        $result = app(AmazonInventorySyncService::class)->syncSkusFromShopify([$sku]);

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

    public function pullProductFromAmazon(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('amazon')) {
            return response()->json(['success' => false, 'message' => 'Amazon not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->amazonListingMapForSkus([$sku])[$sku] ?? null;

        if (! $this->isShopifySkuLinkedOnAmazon($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'No Amazon listing mapped for this SKU. Run Sync Amazon link map on Listings first.',
            ]);
        }

        try {
            $this->amazonApi->getinventory();
        } catch (\Throwable $e) {
            Log::warning('pullProductFromAmazon: inventory pull failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        $value = AmazonListingStatusHelper::valueArray($metric);
        $qty = MarketplaceListingStockResolver::resolveMarketplaceQty(
            MarketplaceListingStockResolver::CHANNEL_AMAZON,
            $sku
        );
        if ($qty !== null) {
            $value['quantity'] = $qty;
        }
        $metric->value = $value;
        $metric->touch();
        $metric->save();

        WarmAmazonLiveListingsCache::dispatch();

        return response()->json([
            'success' => true,
            'message' => 'Refreshed local Amazon listing data for '.$sku.' (merchant listings report / inventory_amazon). Nothing was pushed to Shopify.',
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('amazon')) {
            return response()->json(['success' => false, 'message' => 'Amazon not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('Amazon link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(AmazonLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(AmazonLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;
        $statusCounts = [];

        if (Schema::hasTable('amazon_orders')) {
            $statusFilter = trim((string) $request->input('status', ''));
            $search = trim((string) $request->input('q', ''));

            $query = AmazonOrder::query()->with(['items' => function ($q) {
                $q->orderBy('id');
            }]);

            if ($statusFilter !== '') {
                $query->where('status', $statusFilter);
            }
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('amazon_order_id', 'like', '%'.$search.'%')
                        ->orWhereHas('items', function ($iq) use ($search) {
                            $iq->where('sku', 'like', '%'.$search.'%')
                                ->orWhere('asin', 'like', '%'.$search.'%');
                        });
                });
            }

            $orders = $query
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();

            $statusCounts = AmazonOrder::query()
                ->selectRaw("COALESCE(NULLIF(TRIM(status), ''), 'UNKNOWN') as status_key, COUNT(*) as cnt")
                ->groupBy('status_key')
                ->orderByDesc('cnt')
                ->pluck('cnt', 'status_key')
                ->all();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'amazon_orders table missing.';
        }

        return view('marketplace.amazon.orders', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'title' => 'Amazon — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('amazon'),
            'statusFilter' => $statusFilter ?? '',
            'search' => $search ?? '',
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('amazon'),
            'autoImportToShopify' => MarketplaceSyncSettings::canAutoImportToShopify('amazon'),
            'shopifyImportCutoff' => AmazonOrder::SHOPIFY_IMPORT_CUTOFF_DATE,
        ]);
    }

    public function showOrder(int $id): View
    {
        $order = AmazonOrder::query()->with('items')->findOrFail($id);
        $raw = AmazonOrder::decodeRawPayload($order->raw_data);

        return view('marketplace.amazon.order-show', [
            'title' => 'Amazon Order — '.$order->amazon_order_id,
            'order' => $order,
            'items' => $order->items,
            'raw' => $raw,
            'connected' => $this->apiConfig->isConfigured('amazon'),
            'shippingAddress' => $this->extractShippingAddress($raw),
            'buyerInfo' => $this->extractBuyerInfo($raw),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('amazon'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('amazon', $order),
        ]);
    }

    public function pullOrderFromAmazon(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('amazon')) {
            return response()->json(['success' => false, 'message' => 'Amazon not connected.']);
        }

        $line = AmazonOrder::query()->findOrFail($id);
        $result = app(AmazonOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->amazon_order_id);

        $line->refresh();
        $message = $result['message'] ?? ($result['success'] ? 'Order details updated from Amazon.' : 'Failed to pull order details.');
        $shopifySynced = null;

        if (! empty($result['success']) && ! empty($line->shopify_order_id)) {
            $sync = app(AmazonOrderPushService::class)->syncShippingAddressToShopify($line);
            $shopifySynced = ! empty($sync['success']);
            if ($shopifySynced) {
                $message = 'Pulled from Amazon and updated shipping address on Shopify.';
            } elseif (! empty($sync['skipped'])) {
                $message = 'Pulled from Amazon. '.($sync['message'] ?? 'Shopify address not updated.');
            }
        }

        return response()->json([
            'success' => ! empty($result['success']),
            'shopify_synced' => $shopifySynced,
            'message' => $message,
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to Amazon (Ship Order).
     */
    public function pushTrackingToAmazon(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('amazon')) {
            return response()->json(['success' => false, 'message' => 'Amazon not connected.']);
        }

        $line = AmazonOrder::query()->findOrFail($id);
        $result = app(AmazonTrackingSyncService::class)->pushTrackingForOrder($line);

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
     * Bulk push Shopify tracking → Amazon for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('amazon')) {
            return response()->json(['success' => false, 'message' => 'Amazon not connected.']);
        }

        \App\Jobs\SyncAmazonTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and ships orders on Amazon.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(AmazonOrderSyncService::class);

        if ($fromDate !== '') {
            $result = $sync->fetchAndStoreFromDate($fromDate);
        } else {
            $daysInput = $request->input('days', 7);
            $days = max(1, min(90, (int) $daysInput));
            $result = $sync->fetchAndStore($days);
        }

        // Same as other MM channels: when auto-import is ON, queue FBM Shopify creates
        // after fetch. Duplicate check links existing Shopify orders; FBA is skipped.
        if ($request->boolean('import') || MarketplaceSyncSettings::canAutoImportToShopify('amazon')) {
            $dispatched = $sync->dispatchImportsForNewOrders();
            $result['message'] .= " Dispatched {$dispatched} Shopify import job(s).";
        }

        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? 'Done.',
            'fetched' => $result['fetched'] ?? 0,
            'stored' => $result['stored'] ?? 0,
        ], ! empty($result['success']) ? 200 : 422);
    }

    public function syncInventoryNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\SyncInventoryToAmazon::dispatch();

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(AmazonLiveListingsService::class);
        $linkedSkus = $this->linkedAmazonSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->amazonStockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'amazon');
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

        $result = app(AmazonInventorySyncService::class)->syncSkusFromShopify($batch, null, true);
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
        $order = AmazonOrder::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            return response()->json(app(AmazonOrderPushService::class)->previewShopifyPush($order));
        }

        if (trim((string) ($order->shopify_order_id ?? '')) !== '') {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('amazon', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        $push = app(AmazonOrderPushService::class);
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
            $message = $push->lastDuplicateLinkMessage ?: 'Pushed to Shopify.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'shopify_order_id' => $shopifyOrderId,
                'linked_existing' => $push->lastDuplicateLinkMessage !== null,
            ]);
        }

        if (! $push->lastSkipStatus) {
            $order->update(['import_status' => 'import_failed']);
        }

        return response()->json([
            'success' => false,
            'message' => $push->lastFailureReason ?: 'Shopify import failed.',
            'skip_status' => $push->lastSkipStatus,
        ], 422);
    }

    public function deleteReadyOrder(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Delete ready order is not supported for Amazon MM (local analytics orders).',
        ], 422);
    }

    public function markOrderAlreadyImported(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = AmazonOrder::find($id);

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

        $shopifyOrderId = trim((string) $request->input('shopify_order_id', ''));
        if ($shopifyOrderId === '') {
            $shopifyOrderId = 'manual:'.$order->amazon_order_id;
        }

        $order->update([
            'shopify_order_id' => $shopifyOrderId,
            'import_status' => 'imported',
            'pushed_to_shopify_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Marked Amazon order {$order->amazon_order_id} as already imported.",
            'shopify_order_id' => $shopifyOrderId,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.amazon.settings', [
            'title' => 'Amazon — Settings',
            'settings' => MarketplaceSyncSettings::getFor('amazon'),
            'connected' => $this->apiConfig->isConfigured('amazon'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('amazon');

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
            'push_tracking_to_amazon', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_amazon', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('amazon', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Amazon sync settings saved.']);
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
        AmazonLiveListingsService $liveService,
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
     * Linked Amazon SKUs from amazon_listing_statuses + amazon_listings_raw (Active report).
     *
     * @return array<int, string>
     */
    protected function linkedAmazonSkus(): array
    {
        return AmazonListingStatusHelper::linkedSkus();
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
        if (! Schema::hasTable('amazon_listing_statuses')) {
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
        if (! Schema::hasTable('amazon_listing_statuses')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (AmazonListingStatus $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm === '' || isset($shopifyNormKeys[$norm])) {
                return false;
            }
            if ($searchSku !== '' && ! str_contains(strtoupper((string) $metric->sku), strtoupper($searchSku))) {
                return false;
            }
            if ($searchName !== '') {
                $haystack = strtoupper((string) (((AmazonListingStatusHelper::valueArray($metric)['title'] ?? null) ?? '').' '.$metric->sku));
                if (! str_contains($haystack, strtoupper($searchName))) {
                    return false;
                }
            }

            return true;
        })->values();

        $total = $rows->count();
        $sliceSkus = $rows->slice(($page - 1) * $perPage, $perPage)->pluck('sku')->map(fn ($s) => (string) $s)->all();
        $aeStockMap = $this->amazonStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (AmazonListingStatus $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => AmazonListingStatusHelper::resolveProductId($metric),
                'sku' => $sku,
                'title' => (AmazonListingStatusHelper::valueArray($metric)['title'] ?? null) ?? $metric->sku,
                'amazon_title' => null,
                'image_src' => null,
                'price' => (AmazonListingStatusHelper::valueArray($metric)['price'] ?? null) ?? null,
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
        return AmazonListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, AmazonListingStatus>
     */
    protected function amazonListingMapForSkus(array $skus): array
    {
        return AmazonListingStatusHelper::mapForSkus($skus);
    }

    /**
     * Local AE stock for listings index — same resolver as detail pages.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    protected function amazonStockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_AMAZON,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnAmazon(?AmazonListingStatus $metric, string $shopifySku): bool
    {
        return AmazonListingStatusHelper::isLinked($metric, $shopifySku);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string|null>
     */
    protected function extractShippingAddress(array $raw): array
    {
        $addr = $raw['ShippingAddress'] ?? $raw['shippingAddress'] ?? [];
        if (! is_array($addr)) {
            return [];
        }

        return [
            'name' => $addr['Name'] ?? $addr['name'] ?? null,
            'line1' => $addr['AddressLine1'] ?? $addr['addressLine1'] ?? null,
            'line2' => $addr['AddressLine2'] ?? $addr['addressLine2'] ?? null,
            'city' => $addr['City'] ?? $addr['city'] ?? null,
            'state' => $addr['StateOrRegion'] ?? $addr['stateOrRegion'] ?? null,
            'postal' => $addr['PostalCode'] ?? $addr['postalCode'] ?? null,
            'country' => $addr['CountryCode'] ?? $addr['countryCode'] ?? null,
            'phone' => $addr['Phone'] ?? $addr['phone'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string|null>
     */
    protected function extractBuyerInfo(array $raw): array
    {
        $buyer = $raw['BuyerInfo'] ?? $raw['buyerInfo'] ?? [];
        if (! is_array($buyer)) {
            $buyer = [];
        }

        return [
            'email' => $buyer['BuyerEmail'] ?? $buyer['buyerEmail'] ?? null,
            'name' => $buyer['BuyerName'] ?? $buyer['buyerName'] ?? ($raw['BuyerName'] ?? null),
        ];
    }

}
