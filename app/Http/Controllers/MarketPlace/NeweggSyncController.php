<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmNeweggLiveListingsCache;
use App\Models\NeweggMetric;
use App\Models\NeweggOrderMetric;
use App\Models\NeweggPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\NeweggApiService;
use App\Services\MarketplaceManager\NeweggDetailFormatter;
use App\Services\MarketplaceManager\NeweggInventorySyncService;
use App\Services\MarketplaceManager\NeweggLinkMapSyncService;
use App\Services\MarketplaceManager\NeweggLiveListingsService;
use App\Services\MarketplaceManager\NeweggOrderDetailService;
use App\Services\MarketplaceManager\NeweggOrderPushService;
use App\Services\MarketplaceManager\NeweggOrderSyncService;
use App\Services\MarketplaceManager\NeweggTrackingSyncService;
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

class NeweggSyncController extends Controller
{
    public function __construct(
        protected NeweggApiService $neweggApi,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $sellerId = (string) config('services.newegg.seller_id');
        $apiKey = (string) config('services.newegg.api_key');
        $secretKey = (string) config('services.newegg.secret_key');
        $baseUrl = (string) config('services.newegg.base_url', 'https://api.newegg.com');
        $credentialsReady = $this->neweggApi->isConfigured();

        return view('marketplace.newegg.connect', [
            'title' => 'Newegg — Connect',
            'connected' => $this->apiConfig->isConfigured('newegg'),
            'credentialsReady' => $credentialsReady,
            'hasSellerId' => filled($sellerId),
            'hasApiKey' => filled($apiKey),
            'hasSecretKey' => filled($secretKey),
            'maskedSellerId' => $this->maskCredential($sellerId),
            'maskedApiKey' => $this->maskCredential($apiKey),
            'maskedSecretKey' => $this->maskCredential($secretKey, 2, 2),
            'apiBase' => $baseUrl,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('newegg')) {
            return response()->json([
                'success' => false,
                'message' => 'Newegg API credentials missing. Set NEWEGG_SELLER_ID, NEWEGG_API_KEY, and NEWEGG_SECRET_KEY in .env.',
            ]);
        }

        try {
            $result = $this->neweggApi->getServiceStatus('contentmgmt');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }

        if (! empty($result['blocked_by_cloudflare'])) {
            return response()->json([
                'success' => false,
                'network_error' => true,
                'message' => 'Blocked by Cloudflare managed challenge.',
                'tips' => [
                    'Whitelist this server IP in the Newegg Seller Portal.',
                    'api.newegg.com rejects non-whitelisted IPs with a CAPTCHA HTML page.',
                ],
            ]);
        }

        if (empty($result['ok'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? ('Service status failed HTTP '.($result['status'] ?? 0)),
                'response' => $result['json'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connected successfully. Newegg Service Status API responded.',
            'status' => $result['status'] ?? null,
            'body' => $result['json'] ?? null,
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
        $liveService = app(NeweggLiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.newegg.products', [
                'products' => $products,
                'title' => 'Newegg — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('newegg'),
            ]);
        }

        if ($forceLive) {
            WarmNeweggLiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedNeweggSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Live cache often omits inventory for inactive rows — fill gaps from local map
        // so qty-matched inactive SKUs land in Inactive & Matched (not Mismatch).
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->neweggStockMapForSkus($allLinkedVerified)
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
            $metricMap = $this->neweggMetricMapForSkus($mismatchQty);
            $productIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnNewegg($metric, (string) $sku)) {
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
            WarmNeweggLiveListingsCache::dispatch();
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
        $aeMap = $this->neweggMetricMapForSkus($skus);
        $aeStockMap = $this->neweggStockMapForSkus($skus);
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
                if (! $metric || ! $this->isShopifySkuLinkedOnNewegg($metric, (string) $sku)) {
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
            $linked = $this->isShopifySkuLinkedOnNewegg($metric, $sku);
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
            if ($linked && $live !== null && array_key_exists('inventory', $live) && $live['inventory'] !== null) {
                $aeQty = (int) $live['inventory'];
            } elseif ($linked && $cached && array_key_exists('inventory', $cached) && $cached['inventory'] !== null) {
                $aeQty = (int) $cached['inventory'];
            }

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'newegg_title' => $live['title'] ?? ($cached['title'] ?? ($metric->product_name ?? null)),
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : (isset($cached['price']) ? $cached['price'] : ($linked ? ($metric->price ?? null) : null)),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'newegg_state' => $state !== '' ? $state : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.newegg.products', [
            'products' => $paginator,
            'title' => 'Newegg — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => in_array($linkTab, $liveLinkTabs, true) ? $stateTab : 'all',
            'counts' => $counts,
            'stateCounts' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['counts'] : $emptyStateCounts,
            'stateCacheReady' => in_array($linkTab, $liveLinkTabs, true) ? $stateIndex['ready'] : false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('newegg'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->neweggMetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnNewegg($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'none';
        $productId = $metric?->product_id ? (string) $metric->product_id : null;
        $canFetchAe = $productId && $productId !== (string) $metric?->sku && $this->apiConfig->isConfigured('newegg');

        if ($canFetchAe) {
            $info = $this->neweggApi->getProductInfo($productId);
            if (! empty($info['success'])) {
                $aeLive = $info['data'] ?? null;
                $aeDataSource = 'api';
            } else {
                $aeLiveError = $info['message'] ?? 'Could not load live Newegg product details.';
                $aeDataSource = 'cached';
            }
        } elseif ($metric?->product_id) {
            $aeDataSource = 'cached';
        }

        $aeSkuRows = [];
        if (is_array($aeLive)) {
            $aeSkuRows = $this->neweggApi->extractSkuRowsFromProductInfo(
                $aeLive,
                (string) ($metric->product_id ?? ''),
                $metric->product_name ?? null
            );
        }

        $title = trim(($shopifyRow->product_title ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(NeweggDetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.newegg.product-show', [
            'title' => 'Newegg Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('newegg'),
        ]);
    }

    /**
     * Push live Shopify qty → Newegg for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('newegg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in Newegg settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->neweggMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnNewegg($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Newegg. Run Sync Newegg link map first.',
            ], 422);
        }

        $result = app(NeweggInventorySyncService::class)->syncSkusFromShopify([$sku]);

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

    public function pullProductFromNewegg(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('newegg')) {
            return response()->json(['success' => false, 'message' => 'Newegg not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->neweggMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $metric?->product_id || (string) $metric->product_id === (string) $metric->sku) {
            return response()->json([
                'success' => false,
                'message' => 'No Newegg product_id mapped for this SKU. Run Sync Newegg link map on Listings first.',
            ]);
        }

        $info = $this->neweggApi->getProductInfo((string) $metric->product_id);
        if (empty($info['success'])) {
            return response()->json([
                'success' => false,
                'message' => $info['message'] ?? 'Failed to pull product details from Newegg.',
            ]);
        }

        $aeData = is_array($info['data'] ?? null) ? $info['data'] : [];
        $rows = $this->neweggApi->extractSkuRowsFromProductInfo(
            $aeData,
            (string) $metric->product_id,
            $metric->product_name
        );
        $matched = collect($rows)->first(function ($row) use ($sku) {
            return ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''))
                === ShopifySku::normalizeSkuForShopifyLookup($sku);
        }) ?? ($rows[0] ?? null);

        $metric->update([
            'product_name' => trim((string) (
                $aeData['subject']
                ?? $aeData['product_name']
                ?? $metric->product_name
                ?? ''
            )) ?: $metric->product_name,
            'price' => $matched['price'] ?? $metric->price,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pulled latest Newegg details for '.$sku.'. Nothing was pushed to Shopify or Newegg.',
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('newegg')) {
            return response()->json(['success' => false, 'message' => 'Newegg not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('Newegg link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(NeweggLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(NeweggLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('newegg_order_metrics')) {
            $orders = NeweggOrderMetric::query()
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.newegg.orders', [
            'orders' => $orders,
            'title' => 'Newegg — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('newegg'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('newegg'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = NeweggOrderMetric::query()->findOrFail($id);
        $orderId = (string) $line->order_id;

        $lines = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(NeweggOrderDetailService::class);

        if ($this->apiConfig->isConfigured('newegg')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live Newegg order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(NeweggDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.newegg.order-show', [
            'title' => 'Newegg Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('newegg'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('newegg'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('newegg', $line),
        ]);
    }

    public function pullOrderFromNewegg(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('newegg')) {
            return response()->json(['success' => false, 'message' => 'Newegg not connected.']);
        }

        $line = NeweggOrderMetric::query()->findOrFail($id);
        $result = app(NeweggOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->order_id);

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to pull order details.',
            ]);
        }

        $line->refresh();
        $message = $result['message'] ?? 'Order details updated from Newegg.';
        $shopifySynced = null;

        // Already-imported orders were often created without ShipTo; push address on pull.
        if (! empty($line->shopify_order_id)) {
            $sync = app(NeweggOrderPushService::class)->syncShippingAddressToShopify($line);
            $shopifySynced = ! empty($sync['success']);

            if ($shopifySynced) {
                $message = 'Pulled from Newegg and updated shipping address on Shopify.';
            } elseif (! empty($sync['skipped'])) {
                $message = 'Pulled from Newegg. '.($sync['message'] ?? 'Shopify address not updated.');
            } else {
                return response()->json([
                    'success' => false,
                    'pulled' => true,
                    'shopify_synced' => false,
                    'message' => 'Pulled from Newegg, but Shopify address update failed: '.($sync['message'] ?? 'unknown error'),
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
     * Push Shopify fulfillment tracking number to Newegg (Ship Order).
     */
    public function pushTrackingToNewegg(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('newegg')) {
            return response()->json(['success' => false, 'message' => 'Newegg not connected.']);
        }

        $line = NeweggOrderMetric::query()->findOrFail($id);
        $result = app(NeweggTrackingSyncService::class)->pushTrackingForOrder($line);

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
     * Bulk push Shopify tracking → Newegg for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('newegg')) {
            return response()->json(['success' => false, 'message' => 'Newegg not connected.']);
        }

        \App\Jobs\SyncNeweggTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and ships orders on Newegg.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(NeweggOrderSyncService::class);

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
        $settings = MarketplaceSyncSettings::getFor('newegg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('newegg');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('newegg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(NeweggLiveListingsService::class);
        $linkedSkus = $this->linkedNeweggSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->neweggStockMapForSkus($verified)
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

        $result = app(NeweggInventorySyncService::class)->syncSkusFromShopify($batch);
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
        $order = NeweggOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(NeweggOrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('newegg', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(NeweggOrderPushService::class);
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
     * Delete a local Newegg order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = NeweggOrderMetric::find($id);

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
        $deleted = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed Newegg order {$orderId} from ready-for-import ({$deleted} row(s)).",
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
        $order = NeweggOrderMetric::find($id);

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

        $updated = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'import_status' => 'imported',
                'pushed_to_shopify_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked Newegg order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.newegg.settings', [
            'settings' => MarketplaceSyncSettings::getFor('newegg'),
            'title' => 'Newegg — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('newegg'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('newegg');

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
            'push_tracking_to_newegg',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_newegg', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('newegg', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Newegg sync settings saved.']);
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
        NeweggLiveListingsService $liveService,
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
     * SKUs in newegg_metric that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedNeweggSkus(): array
    {
        if (! Schema::hasTable('newegg_metric')) {
            return [];
        }

        return NeweggMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
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
        if (! Schema::hasTable('newegg_metric')) {
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
        if (! Schema::hasTable('newegg_metric')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (NeweggMetric $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
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
        $aeStockMap = $this->neweggStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (NeweggMetric $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->product_id,
                'sku' => $sku,
                'title' => $metric->product_name ?? $metric->sku,
                'newegg_title' => null,
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
        return NeweggMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, NeweggMetric>
     */
    protected function neweggMetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('newegg_metric')) {
            return [];
        }

        $exact = NeweggMetric::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (NeweggMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
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
    protected function neweggStockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_NEWEGG,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnNewegg(?NeweggMetric $metric, string $shopifySku): bool
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
