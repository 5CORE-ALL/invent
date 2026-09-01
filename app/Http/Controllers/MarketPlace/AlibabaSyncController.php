<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\WarmAlibabaLiveListingsCache;
use App\Models\AlibabaMetric;
use App\Models\AlibabaOrderMetric;
use App\Models\AlibabaPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\AlibabaApiService;
use App\Services\AlibabaAuthService;
use App\Services\MarketplaceManager\AlibabaDetailFormatter;
use App\Services\MarketplaceManager\AlibabaInventorySyncService;
use App\Services\MarketplaceManager\AlibabaLinkMapSyncService;
use App\Services\MarketplaceManager\AlibabaLiveListingsService;
use App\Services\MarketplaceManager\AlibabaOrderDetailService;
use App\Services\MarketplaceManager\AlibabaOrderPushService;
use App\Services\MarketplaceManager\AlibabaOrderSyncService;
use App\Services\MarketplaceManager\AlibabaTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplacePortalStatusTabs;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use App\Services\MarketplaceManager\ShopifyLiveVerifiedCatalogService;
use App\Services\ShopifyApiService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AlibabaSyncController extends Controller
{
    public function __construct(
        protected AlibabaApiService $aliExpressApi,
        protected AlibabaAuthService $aliExpressAuth,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');
        $accessToken = (string) config('services.alibaba.access_token');
        $refreshToken = (string) env('ALIBABA_REFRESH_TOKEN', '');
        $apiBase = (string) config('services.alibaba.api_base');
        $credentialsReady = filled($appKey) && filled($appSecret) && filled($accessToken);

        return view('marketplace.alibaba.connect', [
            'title' => 'Alibaba — Connect',
            'connected' => $this->apiConfig->isConfigured('alibaba'),
            'credentialsReady' => $credentialsReady,
            'authorizeUrl' => $this->aliExpressAuth->getAuthorizeUrl(),
            'hasAppKey' => filled($appKey),
            'hasAppSecret' => filled($appSecret),
            'hasToken' => filled($accessToken),
            'hasRefreshToken' => filled($refreshToken),
            'maskedAppKey' => $this->maskCredential($appKey),
            'maskedAppSecret' => $this->maskCredential($appSecret, 2, 2),
            'maskedAccessToken' => $this->maskCredential($accessToken, 4, 4),
            'apiBase' => $apiBase !== '' ? rtrim($apiBase, '/').(str_ends_with(strtolower($apiBase), '/sync') ? '' : '/sync') : 'https://api-sg.alibaba.com/sync',
            'redirectUri' => (string) (config('services.alibaba.redirect_uri') ?: config('app.url')),
            'gateway' => config('services.alibaba.gateway', env('ALIBABA_GATEWAY', 'sync')),
            'restBase' => config('services.alibaba.rest_base', env('ALIBABA_REST_BASE', 'https://api-sg.alibaba.com/rest')),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('alibaba')) {
            return response()->json([
                'success' => false,
                'message' => 'Alibaba API credentials missing. Set ALIBABA_APP_KEY, ALIBABA_APP_SECRET, and ALIBABA_ACCESS_TOKEN in .env.',
            ]);
        }

        try {
            $result = $this->aliExpressApi->getInventory(1, 1);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }

        if (! empty($result['network_error'])) {
            return response()->json([
                'success' => false,
                'network_error' => true,
                'message' => $result['message'] ?? 'Could not reach Alibaba API (network timeout).',
                'detail' => $result['detail'] ?? null,
                'tips' => [
                    'Your PC cannot open TCP to api-sg.alibaba.com:443 — this is a network/firewall issue, not missing .env keys.',
                    'Try: mobile hotspot, VPN, disable antivirus HTTPS scanning, or test from your production server.',
                    'Whitelist your server public IP in the Alibaba app console (not only your home IP).',
                    'Optional .env: ALIBABA_GATEWAY=sync, ALIBABA_RESOLVE_IPV4=true, ALIBABA_HTTP_PROXY=http://host:port',
                ],
            ]);
        }

        if (empty($result['success'])) {
            $message = $result['message'] ?? 'API call failed.';
            if (str_contains(strtolower($message), 'token') || str_contains(strtolower($message), 'session')) {
                $message .= ' Your access token may be expired — use OAuth authorize to get a new one.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'response' => $result['response'] ?? null,
            ]);
        }

        $total = $result['data']['total_count'] ?? count($result['data']['products'] ?? []);
        $sample = $result['data']['products'][0] ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Connected successfully. Alibaba product list API responded.',
            'total_products' => $total,
            'sample_product_id' => is_array($sample) ? ($sample['product_id'] ?? $sample['id'] ?? null) : null,
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
        if (! in_array($linkTab, ['all', 'matched', 'matched_inactive', 'mismatch', 'linked_mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true)) {
            $linkTab = 'all';
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $clearCache = $request->boolean('clear_cache');
        $liveService = app(AlibabaLiveListingsService::class);
        if ($clearCache) {
            $liveService->clearCache();
        }
        $emptyCounts = ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'linked_mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.alibaba.products', [
                'products' => $products,
                'title' => 'Alibaba — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'counts' => $emptyCounts,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('alibaba'),
            ]);
        }

        if ($forceLive) {
            WarmAlibabaLiveListingsCache::dispatch();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedAlibabaSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->alibabaStockMapForSkus($allLinkedVerified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'alibaba');
        $counts = $classified['counts'] ?? $emptyCounts;
        $counts['all'] = $catalog->countDistinctAllSkus();
        $counts['matched_inactive'] = 0;
        $counts['mismatch_inactive'] = 0;

        if (in_array($linkTab, ['matched', 'mismatch', 'linked_mismatch', 'zero'], true) && $liveService->peekCached() === null && ! $forceLive) {
            WarmAlibabaLiveListingsCache::dispatch();
        }

        if (! $catalog->hasAnyActive()) {
            $apiError = trim(($apiError ? $apiError.' ' : '').'Shared Shopify live catalog is empty — refresh Shopify from Marketplace Manager.');
        }

        $matchedQty = $classified['matched'] ?? [];
        $mismatchQty = $classified['mismatch'] ?? [];
        $linkedMismatchQty = $classified['linked_mismatch'] ?? [];
        $zeroQty = $classified['zero'] ?? [];

        // Re-verify mismatch using shopify_skus.available_to_sell vs local Alibaba stock map.
        if ($mismatchQty !== []) {
            $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($mismatchQty);
            $liveMpByUpper = [];
            foreach ($mismatchQty as $sku) {
                $qty = MarketplaceListingStockResolver::qtyFromMap($mpStock, (string) $sku);
                if ($qty === null) {
                    continue;
                }
                $liveMpByUpper[strtoupper(trim((string) $sku))] = $qty;
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($norm !== '') {
                    $liveMpByUpper[$norm] = $qty;
                }
            }
            $reconciled = MarketplaceListingStockResolver::reconcileLinkedTabsWithLiveQty(
                $matchedQty,
                $mismatchQty,
                $zeroQty,
                $liveShopify,
                $liveMpByUpper,
                'alibaba'
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
                WarmAlibabaLiveListingsCache::dispatch();
            }
            $paginator = MarketplacePortalStatusTabs::paginate(
                $linkTab === 'mismatch_inactive' ? 'active' : 'inactive',
                $linkTab === 'mismatch_inactive' ? $mismatchInactive : $matchedInactive,
                $liveRows,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                'alibaba_state',
                'alibaba_title'
            );

            return view('marketplace.alibaba.products', [
                'products' => $paginator,
                'title' => 'Alibaba — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'counts' => $counts,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('alibaba'),
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

        if (in_array($linkTab, ['matched', 'matched_inactive', 'mismatch', 'linked_mismatch', 'mismatch_inactive', 'zero'], true)) {
            $catalog->restrictShopifySkuQuery($query, $linkedVerified);
        } elseif ($linkTab === 'all') {
            $catalog->restrictShopifySkuQuery($query, null, false);
        } else {
            $catalog->restrictShopifySkuQuery($query, null, true);
            if ($allLinkedVerified !== []) {
                $query->whereNotIn('sku', $allLinkedVerified);
            }
        }

        $paginator = $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $skus = collect($paginator->items())->pluck('sku')->filter()->values()->all();
        $aeMap = $this->alibabaMetricMapForSkus($skus);
        $aeStockMap = $this->alibabaStockMapForSkus($skus);
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);

        $enriched = collect($paginator->items())->map(function (ShopifySku $row) use ($aeMap, $aeStockMap, $liveShopifyQty) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnAlibaba($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $aeQty = $linked ? ($aeStockMap[strtoupper(trim($sku))] ?? null) : null;

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($metric->product_id ?? null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'alibaba_title' => $metric->product_name ?? null,
                'image_src' => $row->image_src ?? null,
                'price' => $linked ? ($metric->price ?? null) : null,
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.alibaba.products', [
            'products' => $paginator,
            'title' => 'Alibaba — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'counts' => $counts,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('alibaba'),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->alibabaMetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnAlibaba($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'none';
        $productId = $metric?->product_id ? (string) $metric->product_id : null;
        $canFetchAe = $productId && $productId !== (string) $metric?->sku && $this->apiConfig->isConfigured('alibaba');

        if ($canFetchAe) {
            $info = $this->aliExpressApi->getProductInfo($productId);
            if (! empty($info['success'])) {
                $aeLive = $info['data'] ?? null;
                $aeDataSource = 'api';
            } else {
                $aeLiveError = $info['message'] ?? 'Could not load live Alibaba product details.';
                $aeDataSource = 'cached';
            }
        } elseif ($metric?->product_id) {
            $aeDataSource = 'cached';
        }

        $aeSkuRows = [];
        if (is_array($aeLive)) {
            $aeSkuRows = $this->aliExpressApi->extractSkuRowsFromProductInfo(
                $aeLive,
                (string) ($metric->product_id ?? ''),
                $metric->product_name ?? null
            );
        }

        $title = trim(($shopifyRow->product_title ?? '').($shopifyRow->variant_title ? ' — '.$shopifyRow->variant_title : '')) ?: $sku;

        $detail = app(AlibabaDetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.alibaba.product-show', [
            'title' => 'Alibaba Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('alibaba'),
        ]);
    }

    /**
     * Push live Shopify qty → Alibaba for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('alibaba');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in Alibaba settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->alibabaMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnAlibaba($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Alibaba. Run Sync Alibaba link map first.',
            ], 422);
        }

        $result = app(AlibabaInventorySyncService::class)->syncSkusFromShopify([$sku]);

        return response()->json([
            'success' => ((int) ($result['updated'] ?? 0)) > 0 || ((int) ($result['failed'] ?? 0)) === 0,
            'queued' => false,
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => $result['message'] ?? 'Inventory sync finished.',
        ]);
    }

    public function pullProductFromAlibaba(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('alibaba')) {
            return response()->json(['success' => false, 'message' => 'Alibaba not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->alibabaMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $metric?->product_id || (string) $metric->product_id === (string) $metric->sku) {
            return response()->json([
                'success' => false,
                'message' => 'No Alibaba product_id mapped for this SKU. Run Sync AE link map on Listings first.',
            ]);
        }

        $info = $this->aliExpressApi->getProductInfo((string) $metric->product_id);
        if (empty($info['success'])) {
            return response()->json([
                'success' => false,
                'message' => $info['message'] ?? 'Failed to pull product details from Alibaba.',
            ]);
        }

        $aeData = is_array($info['data'] ?? null) ? $info['data'] : [];
        $rows = $this->aliExpressApi->extractSkuRowsFromProductInfo(
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
            'message' => 'Pulled latest Alibaba details for '.$sku.'. Nothing was pushed to Shopify or Alibaba.',
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('alibaba')) {
            return response()->json(['success' => false, 'message' => 'Alibaba not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('Alibaba link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(AlibabaLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(AlibabaLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('alibaba_order_metrics')) {
            $orders = AlibabaOrderMetric::query()
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.alibaba.orders', [
            'orders' => $orders,
            'title' => 'Alibaba — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('alibaba'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('alibaba'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = AlibabaOrderMetric::query()->findOrFail($id);
        $orderId = (string) $line->order_id;

        $lines = AlibabaOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(AlibabaOrderDetailService::class);

        if ($this->apiConfig->isConfigured('alibaba')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live Alibaba order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(AlibabaDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.alibaba.order-show', [
            'title' => 'Alibaba Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('alibaba'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('alibaba'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('alibaba', $line),
        ]);
    }

    public function pullOrderFromAlibaba(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('alibaba')) {
            return response()->json(['success' => false, 'message' => 'Alibaba not connected.']);
        }

        $line = AlibabaOrderMetric::query()->findOrFail($id);
        $result = app(AlibabaOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->order_id);

        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from Alibaba.' : 'Failed to pull order details.'),
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(AlibabaOrderSyncService::class);

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
        if ($request->boolean('import') || MarketplaceSyncSettings::canAutoImportToShopify('alibaba')) {
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
        $settings = MarketplaceSyncSettings::getFor('alibaba');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('alibaba');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to Alibaba (declare / modify shipment).
     */
    public function pushTrackingToAlibaba(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('alibaba')) {
            return response()->json(['success' => false, 'message' => 'Alibaba not connected.']);
        }

        $line = AlibabaOrderMetric::query()->findOrFail($id);
        $result = app(AlibabaTrackingSyncService::class)->pushTrackingForOrder($line);

        return response()->json([
            'success' => ! empty($result['success']),
            'skipped' => ! empty($result['skipped']),
            'action' => $result['action'] ?? null,
            'message' => $result['message'] ?? 'Tracking push finished.',
        ], ! empty($result['success']) || ! empty($result['skipped']) ? 200 : 422);
    }

    /**
     * Bulk push Shopify tracking → Alibaba for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('alibaba')) {
            return response()->json(['success' => false, 'message' => 'Alibaba not connected.']);
        }

        \App\Jobs\SyncAlibabaTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and declares/updates tracking on Alibaba.',
        ]);
    }

    /**
     * Push Shopify → Alibaba inventory for mismatch SKUs immediately (no queue).
     */
    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('alibaba');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(AlibabaLiveListingsService::class);
        $linkedSkus = $this->linkedAlibabaSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->alibabaStockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'alibaba');
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

        $result = app(AlibabaInventorySyncService::class)->syncSkusFromShopify($batch, null, true);
        $nextOffset = $offset + count($batch);
        $done = $nextOffset >= $total;

        return response()->json([
            'success' => true,
            'done' => $done,
            'total' => $total,
            'offset' => $nextOffset,
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => $done
                ? 'Mismatch inventory sync finished.'
                : 'Batch synced — call again with offset='.$nextOffset.' to continue.',
        ]);
    }

    public function pushOrderToShopify(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = AlibabaOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(AlibabaOrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('alibaba', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(AlibabaOrderPushService::class);
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
     * Delete a local Alibaba order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = AlibabaOrderMetric::find($id);

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
        $deleted = AlibabaOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed Alibaba order {$orderId} from ready-for-import ({$deleted} row(s)).",
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
        $order = AlibabaOrderMetric::find($id);

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

        $updated = AlibabaOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNull('shopify_order_id')
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'import_status' => 'imported',
                'pushed_to_shopify_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked Alibaba order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.alibaba.settings', [
            'settings' => MarketplaceSyncSettings::getFor('alibaba'),
            'title' => 'Alibaba — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('alibaba'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('alibaba');

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
            'push_tracking_to_alibaba', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_alibaba', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('alibaba', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Alibaba sync settings saved.']);
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
     * SKUs in alibaba_metric that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedAlibabaSkus(): array
    {
        if (! Schema::hasTable('alibaba_metrics')) {
            return [];
        }

        return AlibabaMetric::query()
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
        if (! Schema::hasTable('alibaba_metrics')) {
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
        if (! Schema::hasTable('alibaba_metrics')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (AlibabaMetric $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
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
        $aeStockMap = $this->alibabaStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (AlibabaMetric $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->product_id,
                'sku' => $sku,
                'title' => $metric->product_name ?? $metric->sku,
                'alibaba_title' => null,
                'image_src' => null,
                'price' => $metric->price ?? null,
                'shopify_price' => null,
                'quantity' => $aeStockMap[strtoupper(trim($sku))] ?? null,
                'ae_quantity' => $aeStockMap[strtoupper(trim($sku))] ?? null,
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
        return AlibabaMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, AlibabaMetric>
     */
    protected function alibabaMetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('alibaba_metrics')) {
            return [];
        }

        $exact = AlibabaMetric::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (AlibabaMetric::query()
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
     * Local AE stock from alibaba_pricing_prices (updated by inventory sync).
     *
     * @param  array<int, string>  $skus
     * @return array<string, int> keyed by uppercase SKU
     */
    protected function alibabaStockMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('alibaba_pricing_prices')) {
            return [];
        }

        $keys = [];
        foreach ($skus as $sku) {
            $trim = trim((string) $sku);
            if ($trim === '') {
                continue;
            }
            $keys[] = $trim;
            $keys[] = strtoupper($trim);
        }
        $keys = array_values(array_unique($keys));

        $map = [];
        AlibabaPricingPrice::query()
            ->whereIn('sku', $keys)
            ->get(['sku', 'ab_stock'])
            ->each(function (AlibabaPricingPrice $row) use (&$map) {
                $key = strtoupper(trim((string) $row->sku));
                if ($key !== '' && $row->ab_stock !== null) {
                    $map[$key] = (int) $row->ab_stock;
                }
            });

        return $map;
    }

    protected function isShopifySkuLinkedOnAlibaba(?AlibabaMetric $metric, string $shopifySku): bool
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
