<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmEbay3LiveListingsCache;
use App\Models\Ebay3Metric;
use App\Models\Ebay3OrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\EbayThreeApiService;
use App\Services\MarketplaceManager\Ebay3DetailFormatter;
use App\Services\MarketplaceManager\Ebay3InventorySyncService;
use App\Services\MarketplaceManager\Ebay3LinkMapSyncService;
use App\Services\MarketplaceManager\Ebay3LiveListingsService;
use App\Services\MarketplaceManager\Ebay3OrderDetailService;
use App\Services\MarketplaceManager\Ebay3OrderPushService;
use App\Services\MarketplaceManager\Ebay3OrderSyncService;
use App\Services\MarketplaceManager\Ebay3TrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use App\Services\MarketplaceManager\ReverbLiveListingsService;
use App\Services\MarketplaceManager\ShopifyLiveVerifiedCatalogService;
use App\Services\ShopifyApiService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class Ebay3SyncController extends Controller
{
    public function __construct(
        protected EbayThreeApiService $ebay3Api,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $appId = (string) config('services.ebay3.app_id', env('EBAY_3_APP_ID'));
        $certId = (string) config('services.ebay3.cert_id', env('EBAY_3_CERT_ID'));
        $devId = (string) config('services.ebay3.dev_id', env('EBAY_3_DEV_ID'));
        $refresh = (string) config('services.ebay3.refresh_token', env('EBAY_3_REFRESH_TOKEN'));
        $credentialsReady = $this->ebay3Api->isConfigured();

        return view('marketplace.ebay3.connect', [
            'title' => 'eBay 3 — Connect',
            'connected' => $this->apiConfig->isConfigured('ebay3'),
            'credentialsReady' => $credentialsReady,
            'hasAppId' => filled($appId),
            'hasCertId' => filled($certId),
            'hasDevId' => filled($devId),
            'hasRefreshToken' => filled($refresh),
            'maskedAppId' => $this->maskCredential($appId),
            'maskedCertId' => $this->maskCredential($certId, 2, 2),
            'maskedDevId' => $this->maskCredential($devId),
            'maskedRefreshToken' => $this->maskCredential($refresh, 4, 4),
            'apiBase' => 'https://api.ebay.com',
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('ebay3')) {
            return response()->json([
                'success' => false,
                'message' => 'eBay 3 API credentials missing. Set EBAY_3_APP_ID, EBAY_3_CERT_ID, EBAY_3_DEV_ID, EBAY_3_REFRESH_TOKEN in .env.',
            ]);
        }

        try {
            $result = $this->ebay3Api->testConnection();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Connection test failed.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Connected successfully.',
            'sample_count' => $result['sample_count'] ?? null,
        ]);
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
        $liveService = app(Ebay3LiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.ebay3.products', [
                'products' => $products,
                'title' => 'eBay 3 — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('ebay3'),
            ]);
        }

        if ($forceLive) {
            WarmEbay3LiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedEbay3Skus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Live cache often omits inventory for inactive rows — fill gaps from local map
        // so qty-matched inactive SKUs land in Inactive & Matched (not Mismatch).
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->ebay3StockMapForSkus($allLinkedVerified)
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
            $metricMap = $this->ebay3MetricMapForSkus($mismatchQty);
            $productIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnEbay3($metric, (string) $sku)) {
                    continue;
                }
                $pid = (string) ($metric->product_id ?? '');
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
            WarmEbay3LiveListingsCache::dispatch();
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
        $aeMap = $this->ebay3MetricMapForSkus($skus);
        $aeStockMap = $this->ebay3StockMapForSkus($skus);
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
                if (! $metric || ! $this->isShopifySkuLinkedOnEbay3($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) $metric->product_id;
            }
            if ($needIds !== []) {
                $pageLiveByProduct = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $aeStockMap, $liveShopifyQty, $stateIndex, $pageLiveByProduct) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnEbay3($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
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
                'ebay3_title' => $live['title'] ?? ($cached['title'] ?? ($metric->product_name ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ($metric->price ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'ebay3_state' => $state !== '' ? $state : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.ebay3.products', [
            'products' => $paginator,
            'title' => 'eBay 3 — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('ebay3'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->ebay3MetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnEbay3($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'none';
        $productId = $metric?->product_id ? (string) $metric->product_id : null;
        $canFetchAe = $productId && $productId !== (string) $metric?->sku && $this->apiConfig->isConfigured('ebay3');

        if ($canFetchAe) {
            $info = $this->ebay3Api->getItem($productId);
            if (! empty($info['success'])) {
                $aeLive = $info['data'] ?? null;
                $aeDataSource = 'api';
            } else {
                $aeLiveError = $info['message'] ?? 'Could not load live eBay 3 product details.';
                $aeDataSource = 'cached';
            }
        } elseif ($metric?->product_id) {
            $aeDataSource = 'cached';
        }

        $aeSkuRows = [];
        if (is_array($aeLive)) {
            $aeSkuRows = $this->ebay3Api->extractSkuRowsFromProductInfo(
                $aeLive,
                (string) ($metric->product_id ?? ''),
                $metric->product_name ?? null
            );
        }

        $title = trim(($shopifyRow->product_title ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(Ebay3DetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.ebay3.product-show', [
            'title' => 'eBay 3 Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('ebay3'),
        ]);
    }

    /**
     * Push live Shopify qty → eBay 3 for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('ebay3');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in eBay 3 settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->ebay3MetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnEbay3($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on eBay 3. Run Sync eBay 3 link map first.',
            ], 422);
        }

        $result = app(Ebay3InventorySyncService::class)->syncSkusFromShopify([$sku]);

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

    public function pullProductFromEbay3(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('ebay3')) {
            return response()->json(['success' => false, 'message' => 'eBay 3 not connected.']);
        }

        $shopify = ShopifySku::query()->find($shopifySkuId);
        if (! $shopify) {
            return response()->json(['success' => false, 'message' => 'Shopify SKU not found.'], 404);
        }

        $sku = trim((string) ($shopify->sku ?? ''));
        $metric = Ebay3Metric::query()->where('sku', $sku)->orWhere('sku', strtoupper($sku))->first();
        if (! $metric?->item_id || (string) $metric->item_id === (string) $metric->sku) {
            return response()->json([
                'success' => false,
                'message' => 'No eBay 3 item_id mapped for this SKU. Run Sync eBay 3 link map on Listings first.',
            ]);
        }

        try {
            $info = $this->ebay3Api->getItem((string) $metric->item_id);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'GetItem failed: '.$e->getMessage()]);
        }

        $item = is_array($info['Item'] ?? null) ? $info['Item'] : (is_array($info) ? $info : []);
        $title = trim((string) ($item['Title'] ?? $metric->ebay_title ?? ''));
        $qty = $item['Quantity'] ?? $item['QuantityAvailable'] ?? null;
        $price = $item['StartPrice'] ?? $item['SellingStatus']['CurrentPrice'] ?? null;
        if (is_array($price)) {
            $price = $price['@content'] ?? $price['#text'] ?? $price['_'] ?? reset($price);
        }

        $updates = array_filter([
            'ebay_title' => $title !== '' ? $title : null,
            'ebay_stock' => $qty !== null ? (int) $qty : null,
            'ebay_price' => $price !== null && $price !== '' ? (float) $price : null,
        ], static fn ($v) => $v !== null);
        if ($updates !== []) {
            $metric->update($updates);
        }

        app(Ebay3LiveListingsService::class)->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Pulled live eBay 3 item '.$metric->item_id.' for SKU '.$sku.'.',
            'product_id' => (string) $metric->item_id,
            'title' => $title,
            'inventory' => $qty !== null ? (int) $qty : null,
            'price' => $price !== null && $price !== '' ? (float) $price : null,
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('ebay3')) {
            return response()->json(['success' => false, 'message' => 'eBay 3 not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('eBay 3 link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(Ebay3LinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(Ebay3LinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;
        $statusCounts = [];

        if (Schema::hasTable('ebay3_order_metrics')) {
            $orders = Ebay3OrderMetric::query()
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();

            $statusCounts = Ebay3OrderMetric::query()
                ->selectRaw("COALESCE(NULLIF(TRIM(status), ''), 'UNKNOWN') as status_key, COUNT(*) as cnt")
                ->groupBy('status_key')
                ->orderByDesc('cnt')
                ->pluck('cnt', 'status_key')
                ->all();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.ebay3.orders', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'title' => 'eBay 3 — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('ebay3'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('ebay3'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = Ebay3OrderMetric::query()->findOrFail($id);
        $orderId = (string) $line->order_id;

        $lines = Ebay3OrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(Ebay3OrderDetailService::class);

        if ($this->apiConfig->isConfigured('ebay3')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live eBay 3 order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(Ebay3DetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.ebay3.order-show', [
            'title' => 'eBay 3 Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('ebay3'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('ebay3'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('ebay3', $line),
        ]);
    }

    public function pullOrderFromEbay3(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('ebay3')) {
            return response()->json(['success' => false, 'message' => 'eBay 3 not connected.']);
        }

        $line = Ebay3OrderMetric::query()->findOrFail($id);
        $result = app(Ebay3OrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->order_id);

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to pull order details.',
            ]);
        }

        $line->refresh();
        $message = $result['message'] ?? 'Order details updated from eBay 3.';
        $shopifySynced = null;

        // Already-imported orders were often created without ShipTo; push address on pull.
        if (! empty($line->shopify_order_id)) {
            $sync = app(Ebay3OrderPushService::class)->syncShippingAddressToShopify($line);
            $shopifySynced = ! empty($sync['success']);

            if ($shopifySynced) {
                $message = 'Pulled from eBay 3 and updated shipping address on Shopify.';
            } elseif (! empty($sync['skipped'])) {
                $message = 'Pulled from eBay 3. '.($sync['message'] ?? 'Shopify address not updated.');
            } else {
                return response()->json([
                    'success' => false,
                    'pulled' => true,
                    'shopify_synced' => false,
                    'message' => 'Pulled from eBay 3, but Shopify address update failed: '.($sync['message'] ?? 'unknown error'),
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'pulled' => true,
            'shopify_synced' => $shopifySynced,
            'message' => $message,
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to eBay 3 (Ship Order).
     */
    public function pushTrackingToEbay3(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('ebay3')) {
            return response()->json(['success' => false, 'message' => 'eBay 3 not connected.']);
        }

        $line = Ebay3OrderMetric::query()->findOrFail($id);
        $result = app(Ebay3TrackingSyncService::class)->pushTrackingForOrder($line);

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
     * Bulk push Shopify tracking → eBay 3 for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('ebay3')) {
            return response()->json(['success' => false, 'message' => 'eBay 3 not connected.']);
        }

        \App\Jobs\SyncEbay3TrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and ships orders on eBay 3.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(Ebay3OrderSyncService::class);

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
        $settings = MarketplaceSyncSettings::getFor('ebay3');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('ebay3');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        try {
            $settings = MarketplaceSyncSettings::getFor('ebay3');
            if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
                ], 422);
            }

            $scope = strtolower((string) $request->input('scope', $request->input('link', 'all')));
            $offset = max(0, (int) $request->input('offset', 0));
            $limit = max(1, min(20, (int) $request->input('limit', 10)));
            $cacheKey = 'ebay3_mismatch_sync_list_'.(string) (auth()->id() ?? 'guest').'_'.$scope;

            // Rebuild mismatch list only on first batch; later offsets reuse cache (avoids timeout).
            $mismatch = null;
            if ($offset > 0) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    $mismatch = $cached;
                }
            }
            if (! is_array($mismatch)) {
                $mismatch = $this->resolveEbay3MismatchSkuList($scope);
                Cache::put($cacheKey, array_values($mismatch), now()->addMinutes(30));
            }

            $total = count($mismatch);
            $batch = array_slice($mismatch, $offset, $limit);

            if ($batch === []) {
                Cache::forget($cacheKey);

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

            $result = app(Ebay3InventorySyncService::class)->syncSkusFromShopify($batch);
            $nextOffset = $offset + count($batch);
            $done = $nextOffset >= $total;
            if ($done) {
                Cache::forget($cacheKey);
            }

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
        } catch (\Throwable $e) {
            Log::error('Ebay3 syncMismatchInventoryNow failed', [
                'error' => $e->getMessage(),
                'offset' => (int) $request->input('offset', 0),
            ]);

            return response()->json([
                'success' => false,
                'done' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolveEbay3MismatchSkuList(string $scope): array
    {
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(Ebay3LiveListingsService::class);
        $linkedSkus = $this->linkedEbay3Skus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->ebay3StockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $mismatchQty = $classified['mismatch'] ?? [];

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
                return array_values($catalog->filterSkusByNormalizedAllowList(
                    $mismatchQty,
                    $idx['skusByState']['active'] ?? []
                ));
            }

            return array_values($mismatchQty);
        }

        if (in_array($scope, ['mismatch_inactive', 'inactive'], true)) {
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

                return array_values($catalog->excludeSkusByNormalizedList($mismatchQty, $active));
            }

            return [];
        }

        return array_values($mismatchQty);
    }

    public function pushOrderToShopify(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = Ebay3OrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(Ebay3OrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('ebay3', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(Ebay3OrderPushService::class);
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
     * Delete a local eBay 3 order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = Ebay3OrderMetric::find($id);

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
        $deleted = Ebay3OrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed eBay 3 order {$orderId} from ready-for-import ({$deleted} row(s)).",
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
        $order = Ebay3OrderMetric::find($id);

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

        $updated = Ebay3OrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'import_status' => 'imported',
                'pushed_to_shopify_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked eBay 3 order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.ebay3.settings', [
            'settings' => MarketplaceSyncSettings::getFor('ebay3'),
            'title' => 'eBay 3 — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('ebay3'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('ebay3');

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
            'push_tracking_to_ebay3', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_ebay3', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('ebay3', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'eBay 3 sync settings saved.']);
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
        Ebay3LiveListingsService $liveService,
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
     * SKUs in ebay_3_metrics that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedEbay3Skus(): array
    {
        if (! Schema::hasTable('ebay_3_metrics')) {
            return [];
        }

        return Ebay3Metric::query()
            ->whereNotNull('sku')
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->where('item_id', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
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
        if (! Schema::hasTable('ebay_3_metrics')) {
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
        if (! Schema::hasTable('ebay_3_metrics')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (Ebay3Metric $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm === '' || isset($shopifyNormKeys[$norm])) {
                return false;
            }
            if ($searchSku !== '' && ! str_contains(strtoupper((string) $metric->sku), strtoupper($searchSku))) {
                return false;
            }
            if ($searchName !== '') {
                $haystack = strtoupper((string) (($metric->product_name ?? '').' '.$metric->sku));
                if (! str_contains($haystack, strtoupper($searchName))) {
                    return false;
                }
            }

            return true;
        })->values();

        $total = $rows->count();
        $sliceSkus = $rows->slice(($page - 1) * $perPage, $perPage)->pluck('sku')->map(fn ($s) => (string) $s)->all();
        $aeStockMap = $this->ebay3StockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (Ebay3Metric $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->item_id,
                'sku' => $sku,
                'title' => $metric->product_name ?? $metric->sku,
                'ebay3_title' => null,
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
        return Ebay3Metric::query()
            ->whereNotNull('sku')
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->where('item_id', '!=', '')
            ->whereColumn('sku', '!=', 'item_id');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, Ebay3Metric>
     */
    protected function ebay3MetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('ebay_3_metrics')) {
            return [];
        }

        $exact = Ebay3Metric::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (Ebay3Metric::query()
            ->whereNotNull('sku')
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->where('item_id', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
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
    protected function ebay3StockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_EBAY3,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnEbay3(?Ebay3Metric $metric, string $shopifySku): bool
    {
        if (! $metric || empty($metric->product_id)) {
            return false;
        }

        $mappedSku = trim((string) $metric->sku);
        if ($mappedSku === '' || $mappedSku === (string) $metric->product_id) {
            return false;
        }

        return ShopifySku::normalizeSkuForShopifyLookup($mappedSku)
            === ShopifySku::normalizeSkuForShopifyLookup($shopifySku);
    }
}
