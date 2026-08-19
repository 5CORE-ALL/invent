<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\ProductMaster;
use App\Models\ReverbMetric;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\ReverbApiService;
use App\Services\ReverbManagerApiService;
use App\Services\ReverbAuthService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use App\Services\MarketplaceManager\ReverbDetailFormatter;
use App\Services\MarketplaceManager\ReverbInventorySyncService;
use App\Services\MarketplaceManager\ReverbLinkMapSyncService;
use App\Services\MarketplaceManager\ReverbListingValidator;
use App\Jobs\WarmReverbLiveListingsCache;
use App\Services\MarketplaceManager\ReverbLiveListingsService;
use App\Services\MarketplaceManager\ShopifyLiveVerifiedCatalogService;
use App\Services\MarketplaceManager\ReverbOrderDetailService;
use App\Services\MarketplaceManager\ReverbOrderPushService;
use App\Services\MarketplaceManager\ReverbOrderSyncService;
use App\Services\MarketplaceManager\ReverbTrackingSyncService;
use App\Services\ShopifyApiService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReverbSyncController extends Controller
{
    public function __construct(
        protected ReverbManagerApiService $aliExpressApi,
        protected ReverbAuthService $aliExpressAuth,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $appKey = (string) config('services.reverb.client_id');
        $appSecret = (string) config('services.reverb.client_secret');
        $accessToken = (string) config('services.reverb.token');
        $refreshToken = '';
        $apiBase = (string) config('services.reverb.api_url', 'https://api.reverb.com/api');
        $credentialsReady = (filled($appKey) && filled($appSecret)) || filled($accessToken);

        return view('marketplace.reverb.connect', [
            'title' => 'Reverb — Connect',
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'credentialsReady' => $credentialsReady,
            'authorizeUrl' => $this->aliExpressAuth->getAuthorizeUrl(),
            'hasAppKey' => filled($appKey),
            'hasAppSecret' => filled($appSecret),
            'hasToken' => filled($accessToken),
            'hasRefreshToken' => false,
            'maskedAppKey' => $this->maskCredential($appKey),
            'maskedAppSecret' => $this->maskCredential($appSecret, 2, 2),
            'maskedAccessToken' => $this->maskCredential($accessToken, 4, 4),
            'apiBase' => $apiBase,
            'redirectUri' => (string) config('app.url'),
            'gateway' => 'rest',
            'restBase' => $apiBase,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json([
                'success' => false,
                'message' => 'Reverb API credentials missing. Set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET, or REVERB_TOKEN in .env.',
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
                'message' => $result['message'] ?? 'Could not reach Reverb API (network timeout).',
                'detail' => $result['detail'] ?? null,
                'tips' => [
                    'Your PC cannot open TCP to api.reverb.com:443 — this is a network/firewall issue, not missing .env keys.',
                    'Try: mobile hotspot, VPN, disable antivirus HTTPS scanning, or test from your production server.',
                    'Confirm REVERB_CLIENT_ID / REVERB_CLIENT_SECRET or REVERB_TOKEN in .env.',
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
            'message' => 'Connected successfully. Reverb product list API responded.',
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
        // Legacy tab redirects
        if (in_array($linkTab, ['not_in_shopify', 'linked', 'linked_with_inv'], true)) {
            $linkTab = 'matched';
        }
        if ($linkTab === 'linked_zero') {
            $linkTab = 'zero';
        }
        if (! in_array($linkTab, ['all', 'matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true)) {
            $linkTab = 'all';
        }
        $stateTab = $this->parseReverbStateTab($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $clearCache = $request->boolean('clear_cache');
        $liveQueued = 0;
        $liveMode = in_array($linkTab, ['matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero'], true);
        $emptyStateCounts = $this->emptyReverbStateCounts();
        $emptyCounts = ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(ReverbLiveListingsService::class);

        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.reverb.products', [
                'products' => $products,
                'title' => 'Reverb — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => $stateTab,
                'counts' => $emptyCounts,
                'stateCounts' => $emptyStateCounts,
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('reverb'),
                'liveMode' => false,
                'liveQueued' => 0,
                'shopifyCatalogReady' => false,
                'shopifyCatalogSyncedAt' => null,
            ]);
        }

        if (! $catalog->tablesReady() || ! $catalog->hasAnyActive()) {
            $apiError = 'Shared Shopify live catalog is empty — refresh Shopify from Marketplace Manager.';
        }

        if ($forceLive) {
            WarmReverbLiveListingsCache::dispatch();
        }

        $linkedSkus = $this->linkedReverbSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        // Prefer warm live cache, but fill gaps from local stock (ended/inactive often omit qty).
        $localMpStock = $this->reverbStockMapForSkus($allLinkedVerified);
        $liveRows = $liveService->peekCached();
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveRows,
            $localMpStock
        );
        if (($liveRows === null || $liveRows === []) && ! $forceLive && ! $clearCache) {
            WarmReverbLiveListingsCache::dispatch();
        }
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $counts = $classified['counts'] ?? $emptyCounts;
        $counts['all'] = $catalog->countDistinctAllSkus();
        $counts['matched_inactive'] = 0;
        $counts['mismatch_inactive'] = 0;

        $matchedQty = $classified['matched'] ?? [];
        $mismatchQty = $classified['mismatch'] ?? [];
        $zeroQty = $classified['zero'] ?? [];

        // Re-verify mismatch using shopify_skus live qty (available_to_sell) + live Reverb.
        if ($mismatchQty !== []) {
            $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($mismatchQty);
            if ($liveShopify === []) {
                $liveShopify = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($mismatchQty);
            }
            $metricMap = $this->reverbMetricMapForSkus($mismatchQty);
            $listingIds = [];
            $idToSku = [];
            foreach ($mismatchQty as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $this->isShopifySkuLinkedOnReverb($metric, (string) $sku)) {
                    continue;
                }
                $pid = (string) ($metric->product_id ?? '');
                if ($pid === '') {
                    continue;
                }
                $listingIds[] = $pid;
                $idToSku[$pid] = (string) $sku;
            }
            $liveMpByUpper = [];
            if ($listingIds !== []) {
                foreach ($liveService->liveDetailsByListingIds(array_values(array_unique($listingIds))) as $pid => $row) {
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

        // Qty-matched split: marketplace-active vs inactive (ended/draft/other/unknown).
        $matchedActive = $matchedQty;
        $matchedInactive = [];
        $matchedNormToSku = [];
        foreach ($matchedQty as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $matchedNormToSku[$n] = (string) $sku;
            }
        }
        $matchedStateIndex = $this->reverbStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($matchedNormToSku[$norm]),
            count($matchedNormToSku),
            $matchedNormToSku
        );
        if ($matchedStateIndex['ready']) {
            $activeMpSkus = array_values(array_unique(array_merge(
                $matchedStateIndex['skusByState']['live'] ?? [],
                $matchedStateIndex['skusByState']['out_of_stock'] ?? [],
                $matchedStateIndex['skusByState']['sold'] ?? []
            )));
            $matchedActive = $catalog->filterSkusByNormalizedAllowList($matchedQty, $activeMpSkus);
            $matchedInactive = $catalog->excludeSkusByNormalizedList($matchedQty, $matchedActive);
            $counts['matched'] = count($matchedActive);
            $counts['matched_inactive'] = count($matchedInactive);
            $counts['linked_with_inv'] = $counts['matched'];
        }

        // Qty-mismatch split: same active vs inactive rules as matched.
        $mismatchActive = $mismatchQty;
        $mismatchInactive = [];
        $mismatchNormToSku = [];
        foreach ($mismatchQty as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $mismatchNormToSku[$n] = (string) $sku;
            }
        }
        $mismatchStateIndex = $this->reverbStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($mismatchNormToSku[$norm]),
            count($mismatchNormToSku),
            $mismatchNormToSku
        );
        if ($mismatchStateIndex['ready']) {
            $activeMismatchMpSkus = array_values(array_unique(array_merge(
                $mismatchStateIndex['skusByState']['live'] ?? [],
                $mismatchStateIndex['skusByState']['out_of_stock'] ?? [],
                $mismatchStateIndex['skusByState']['sold'] ?? []
            )));
            $mismatchActive = $catalog->filterSkusByNormalizedAllowList($mismatchQty, $activeMismatchMpSkus);
            $mismatchInactive = $catalog->excludeSkusByNormalizedList($mismatchQty, $mismatchActive);
            $counts['mismatch'] = count($mismatchActive);
            $counts['mismatch_inactive'] = count($mismatchInactive);
        }

        if ($liveMode) {
            $tabLinked = match ($linkTab) {
                'mismatch' => $mismatchActive,
                'mismatch_inactive' => $mismatchInactive,
                'zero' => $zeroQty,
                'matched_inactive' => $matchedInactive,
                default => $matchedActive,
            };

            return $this->syncProductsShopifyFirstLinked(
                $request,
                $searchSku,
                $searchName,
                $stateTab,
                $page,
                $perPage,
                $tabLinked,
                $counts,
                $liveService,
                $catalog,
                $apiError,
                $linkTab
            );
        }

        // All = every Shopify live SKU; Not on marketplace = in-stock active Shopify not linked.
        $verifiedSkus = $this->filterVerifiedSkusForTab(
            $catalog,
            $linkTab === 'all' ? 'all' : 'unlinked',
            $linkedSkus,
            $searchSku,
            $searchName
        );

        $paginator = $this->paginateSkuList($verifiedSkus, $page, $perPage);
        $pageSkus = collect($paginator->items())->all();
        $pageRows = $this->shopifySkuRowsForVerifiedPage($pageSkus, $catalog);
        $aeMap = $this->reverbMetricMapForSkus($pageSkus);
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($pageSkus);
        if ($liveShopifyQty === []) {
            $liveShopifyQty = MarketplaceListingStockResolver::dbShopifyQtyMapForRows($pageRows);
        }
        $listingIds = [];
        foreach ($aeMap as $metric) {
            if ($metric && ! empty($metric->product_id)) {
                $listingIds[] = (string) $metric->product_id;
            }
        }
        $liveReverb = $liveService->liveDetailsByListingIds($listingIds);

        $listingValidator = app(ReverbListingValidator::class);
        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $liveShopifyQty, $liveReverb, $listingValidator) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnReverb($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
            $live = ($pid !== '' && isset($liveReverb[$pid])) ? $liveReverb[$pid] : null;
            $aeQty = $linked
                ? MarketplaceListingStockResolver::displayedMarketplaceQty(
                    is_array($live) ? $live : null,
                    null,
                    null
                )
                : null;
            $incomplete = $linked
                ? $listingValidator->incompletenessFromLive(is_array($live) ? $live : null)
                : ['incomplete' => false, 'issue_count' => 0];

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'reverb_title' => $live['title'] ?? ($metric->product_name ?? null),
                'image_src' => $row->image_src ?? null,
                'price' => $live['price'] ?? ($linked ? ($metric->price ?? null) : null),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'rv_quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'reverb_state' => $live['state'] ?? null,
                'listing_incomplete' => (bool) ($incomplete['incomplete'] ?? false),
                'listing_issue_count' => (int) ($incomplete['issue_count'] ?? 0),
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => $stateTab,
            'counts' => $counts,
            'stateCounts' => $emptyStateCounts,
            'stateCacheReady' => false,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'liveMode' => $liveMode,
            'liveQueued' => $liveQueued,
            'shopifyCatalogReady' => $catalog->hasAnyActive(),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    /**
     * Shopify-first Linked tab: paginate live-verified Shopify SKUs that are linked, live-hydrate current page only.
     *
     * @param  array<int, string>  $linkedSkus
     * @param  array{all: int, linked: int, unlinked: int, not_in_shopify: int}  $counts
     */
    protected function syncProductsShopifyFirstLinked(
        Request $request,
        string $searchSku,
        string $searchName,
        string $stateTab,
        int $page,
        int $perPage,
        array $linkedSkus,
        array $counts,
        ReverbLiveListingsService $liveService,
        ShopifyLiveVerifiedCatalogService $catalog,
        ?string $apiError,
        string $linkTab = 'linked'
    ): View {
        $verifiedNormToSku = $catalog->tablesReady() ? $catalog->normalizedToSkuMap() : [];
        $catalogReady = $verifiedNormToSku !== [];
        $linkedVerifiedSkus = [];
        $linkedNormToSku = [];
        foreach ($linkedSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n === '') {
                continue;
            }
            // $linkedSkus already filtered to with_inv / zero_inv catalog SKUs
            $canonical = $verifiedNormToSku[$n] ?? (string) $sku;
            if ($catalogReady && ! isset($verifiedNormToSku[$n])) {
                continue;
            }
            $linkedVerifiedSkus[] = $canonical;
            $linkedNormToSku[$n] = $canonical;
        }
        $linkedVerifiedSkus = array_values(array_unique($linkedVerifiedSkus));

        $stateIndex = $this->reverbStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => isset($linkedNormToSku[$norm]),
            count($linkedVerifiedSkus),
            $linkedNormToSku
        );

        $skuList = $linkedVerifiedSkus;
        if ($stateTab !== 'all') {
            $stateSkus = $stateIndex['skusByState'][$stateTab] ?? [];
            $stateNorm = [];
            foreach ($stateSkus as $s) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $s);
                if ($n !== '') {
                    $stateNorm[$n] = true;
                }
            }
            $skuList = array_values(array_filter(
                $linkedVerifiedSkus,
                static function (string $sku) use ($stateNorm) {
                    $n = ShopifySku::normalizeSkuForShopifyLookup($sku);

                    return $n !== '' && isset($stateNorm[$n]);
                }
            ));
        }

        $skuList = $this->applySkuSearchFilter($skuList, $searchSku, $searchName, $catalog);
        sort($skuList, SORT_STRING | SORT_FLAG_CASE);

        $paginator = $this->paginateSkuList($skuList, $page, $perPage);
        $pageSkus = collect($paginator->items())->all();
        $pageRows = $this->shopifySkuRowsForVerifiedPage($pageSkus, $catalog);
        $aeMap = $this->reverbMetricMapForSkus($pageSkus);

        // Shopify qty from shopify_skus.available_to_sell (SyncShopifyLiveInventory SoT).
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($pageSkus);
        if ($liveShopifyQty === []) {
            $liveShopifyQty = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($pageSkus);
        }
        if ($liveShopifyQty === []) {
            $liveShopifyQty = MarketplaceListingStockResolver::dbShopifyQtyMapForRows($pageRows);
        }

        // 2) Live Reverb qty/state for this page's listing IDs only (parallel)
        $listingIds = [];
        foreach ($pageSkus as $sku) {
            $metric = $aeMap[$sku] ?? null;
            if ($metric && MarketplaceLiveInventoryRules::isLinked((string) $metric->product_id, (string) $metric->sku)) {
                $listingIds[] = (string) $metric->product_id;
            }
        }
        $liveReverb = $liveService->liveDetailsByListingIds($listingIds);

        $listingValidator = app(ReverbListingValidator::class);
        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $liveShopifyQty, $liveReverb, $listingValidator) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnReverb($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
            $live = ($pid !== '' && isset($liveReverb[$pid])) ? $liveReverb[$pid] : null;

            $state = (string) ($live['state'] ?? '');
            $rvQty = null;
            if ($linked && $live !== null) {
                $rvQty = (int) ($live['inventory'] ?? 0);
            }
            $incomplete = $linked
                ? $listingValidator->incompletenessFromLive(is_array($live) ? $live : null)
                : ['incomplete' => false, 'issue_count' => 0];

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $pid !== '' ? $pid : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'reverb_title' => $live['title'] ?? ($metric->product_name ?? null),
                'image_src' => $row->image_src ?? null,
                'price' => $live['price'] ?? ($metric->price ?? null),
                'shopify_price' => $row->b2c_price ?? $row->price ?? null,
                'quantity' => $rvQty,
                'rv_quantity' => $rvQty,
                'ae_quantity' => $rvQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => true,
                'listing_status' => 'linked',
                'reverb_state' => $state !== '' ? $state : null,
                'listing_incomplete' => (bool) ($incomplete['incomplete'] ?? false),
                'listing_issue_count' => (int) ($incomplete['issue_count'] ?? 0),
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => $stateTab,
            'counts' => $counts,
            'stateCounts' => $stateIndex['counts'],
            'stateCacheReady' => $stateIndex['ready'],
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'liveMode' => true,
            'liveQueued' => 0,
            'shopifyCatalogReady' => $catalog->hasAnyActive(),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    /**
     * Not-in-Shopify: paginate Reverb metrics missing from Shopify; live Reverb qty for page only.
     *
     * @param  array<string, true>  $shopifyNormKeys
     * @param  array{all: int, linked: int, unlinked: int, not_in_shopify: int}  $counts
     */
    protected function syncProductsNotInShopifyLivePage(
        Request $request,
        string $searchSku,
        string $searchName,
        string $stateTab,
        int $page,
        int $perPage,
        array $shopifyNormKeys,
        array $counts,
        ReverbLiveListingsService $liveService,
        ?string $apiError
    ): View {
        $stateIndex = $this->reverbStateIndexFromCache(
            $liveService,
            static fn (string $norm): bool => $norm !== '' && ! isset($shopifyNormKeys[$norm]),
            (int) ($counts['not_in_shopify'] ?? 0)
        );

        $allowSkus = null;
        if ($stateTab !== 'all') {
            $allowSkus = $stateIndex['skusByState'][$stateTab] ?? [];
        }

        $paginator = $this->paginateAeNotInShopify($searchSku, $searchName, $shopifyNormKeys, $page, $perPage, $allowSkus);
        $items = collect($paginator->items());
        $listingIds = $items->pluck('product_id')->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();
        $liveReverb = $liveService->liveDetailsByListingIds($listingIds);

        $enriched = $items->map(function ($p) use ($liveReverb) {
            $pid = (string) ($p->product_id ?? '');
            $live = ($pid !== '' && isset($liveReverb[$pid])) ? $liveReverb[$pid] : null;
            $p->rv_quantity = $live['inventory'] ?? ($p->quantity ?? null);
            $p->quantity = $p->rv_quantity;
            $p->shopify_quantity = null;
            $p->reverb_state = $live['state'] ?? ($p->reverb_state ?? null);
            $p->reverb_title = $live['title'] ?? ($p->reverb_title ?? $p->title ?? null);
            if ($live && isset($live['price'])) {
                $p->price = $live['price'];
            }

            return $p;
        });

        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => 'not_in_shopify',
            'stateTab' => $stateTab,
            'counts' => $counts,
            'stateCounts' => $stateIndex['counts'],
            'stateCacheReady' => $stateIndex['ready'],
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'liveMode' => true,
            'liveQueued' => 0,
            'shopifyCatalogReady' => app(ShopifyLiveVerifiedCatalogService::class)->hasAnyActive(),
            'shopifyCatalogSyncedAt' => app(ShopifyLiveVerifiedCatalogService::class)->latestSyncedAt(),
        ]);
    }
    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $shopifyRow = MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($shopifyRow);
        $sku = (string) $shopifyRow->sku;
        $aeMap = $this->reverbMetricMapForSkus([$sku]);
        $metric = $aeMap[$sku] ?? null;
        $linked = $this->isShopifySkuLinkedOnReverb($metric, $sku);

        $aeLive = null;
        $aeLiveError = null;
        $aeDataSource = 'none';
        $productId = $metric?->product_id ? (string) $metric->product_id : null;
        $canFetchAe = $productId && $productId !== (string) $metric?->sku && $this->apiConfig->isConfigured('reverb');

        if ($canFetchAe) {
            $info = $this->aliExpressApi->getProductInfo($productId);
            if (! empty($info['success'])) {
                $aeLive = $info['data'] ?? null;
                $aeDataSource = 'api';
            } else {
                $aeLiveError = $info['message'] ?? 'Could not load live Reverb product details.';
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

        $detail = app(ReverbDetailFormatter::class)->formatProduct(
            is_array($aeLive) ? $aeLive : null,
            $metric,
            $shopifyRow,
            $aeSkuRows
        );

        return view('marketplace.reverb.product-show', [
            'title' => 'Reverb Listing — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'linked' => $linked,
            'displayTitle' => $title,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('reverb'),
        ]);
    }

    /**
     * Push live Shopify qty → Reverb for this one SKU immediately (no queue).
     */
    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        @set_time_limit(120);

        $settings = MarketplaceSyncSettings::getFor('reverb');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in Reverb settings first.',
            ], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = trim((string) $shopifyRow->sku);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU missing on this Shopify row.'], 422);
        }

        $metric = $this->reverbMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnReverb($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Reverb. Run Sync Reverb link map first.',
            ], 422);
        }

        $result = app(ReverbInventorySyncService::class)->syncSkusFromShopify([$sku]);

        return response()->json([
            'success' => ((int) ($result['updated'] ?? 0)) > 0 || ((int) ($result['failed'] ?? 0)) === 0,
            'queued' => false,
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => $result['message'] ?? 'Inventory sync finished.',
        ]);
    }

    public function pullProductFromReverb(int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.']);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->reverbMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $metric?->product_id || (string) $metric->product_id === (string) $metric->sku) {
            return response()->json([
                'success' => false,
                'message' => 'No Reverb product_id mapped for this SKU. Run Sync AE link map on Listings first.',
            ]);
        }

        $info = $this->aliExpressApi->getProductInfo((string) $metric->product_id);
        if (empty($info['success'])) {
            return response()->json([
                'success' => false,
                'message' => $info['message'] ?? 'Failed to pull product details from Reverb.',
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
            'message' => 'Pulled latest Reverb details for '.$sku.'. Nothing was pushed to Shopify or Reverb.',
        ]);
    }

    /**
     * JSON payload for the View Listing editable modal.
     */
    public function listingEditor(string $marketplace, int $shopifySkuId): JsonResponse
    {
        return $this->buildListingEditorResponse($shopifySkuId, false);
    }

    /**
     * Force-pull listing from Reverb API into the modal.
     */
    public function listingEditorPull(string $marketplace, int $shopifySkuId): JsonResponse
    {
        return $this->buildListingEditorResponse($shopifySkuId, true);
    }

    /**
     * Push edited listing fields from the modal to Reverb.
     */
    public function listingEditorPush(Request $request, string $marketplace, int $shopifySkuId): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.'], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->reverbMetricMapForSkus([$sku])[$sku] ?? null;
        if (! $this->isShopifySkuLinkedOnReverb($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Reverb. Run Sync Reverb link map first.',
            ], 422);
        }

        $fields = $request->input('listing', $request->all());
        if (! is_array($fields)) {
            return response()->json(['success' => false, 'message' => 'Invalid listing payload.'], 422);
        }

        // Merge bullet lines into a Highlighted Features block when description lacks one.
        if (! empty($fields['bullets']) && is_array($fields['bullets'])) {
            $bullets = array_values(array_filter(array_map(static fn ($b) => trim((string) $b), $fields['bullets'])));
            $description = (string) ($fields['description'] ?? '');
            if ($bullets !== [] && ! str_contains($description, 'highlighted-features')) {
                $features = '<div class="highlighted-features">'."\n";
                foreach ($bullets as $b) {
                    $features .= '<p>'.e($b).'<br></p>'."\n";
                }
                $features .= '</div>';
                $fields['description'] = $features."\n".$description;
            }
        }

        $validator = app(ReverbListingValidator::class);
        $validation = $validator->validate($fields);
        if (! ($validation['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Listing has validation issues. Fix red-triangle fields before pushing.',
                'validation' => $validation,
            ], 422);
        }

        $listingId = (string) ($metric->product_id ?? '');
        $result = app(ReverbApiService::class)->updateListing($listingId !== '' ? $listingId : $sku, $fields);

        if (! empty($result['success']) && $metric) {
            $metric->update([
                'product_name' => trim((string) ($fields['title'] ?? $metric->product_name)) ?: $metric->product_name,
                'price' => isset($fields['price_amount']) && is_numeric($fields['price_amount'])
                    ? (float) $fields['price_amount']
                    : $metric->price,
            ]);
        }

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'Push finished.',
            'listing_id' => $result['listing_id'] ?? $listingId,
            'validation' => $validation,
        ], ! empty($result['success']) ? 200 : 422);
    }

    /**
     * Autofill modal fields from Product Master (does not push to Reverb).
     */
    public function listingEditorProductMaster(Request $request, string $marketplace, int $shopifySkuId): JsonResponse
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $section = strtolower(trim((string) $request->query('section', 'full')));
        if (! in_array($section, ['full', 'title', 'images', 'bullets', 'description', 'videos', 'details'], true)) {
            $section = 'full';
        }

        $pm = $this->findProductMasterForSku($sku);
        if (! $pm) {
            return response()->json([
                'success' => false,
                'message' => 'No Product Master row found for SKU '.$sku.'.',
            ], 404);
        }

        $partial = $this->productMasterListingPartial($pm, $section);

        return response()->json([
            'success' => true,
            'message' => 'Loaded Product Master data ('.$section.'). Review fields, then Push to Reverb.',
            'section' => $section,
            'partial' => $partial,
        ]);
    }

    /**
     * Fill only blank / underfilled listing fields from Product Master pages.
     * Does not overwrite existing filled Reverb form values.
     */
    public function listingEditorAutopopulateMissing(Request $request, string $marketplace, int $shopifySkuId): JsonResponse
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $pm = $this->findProductMasterForSku($sku);
        if (! $pm) {
            return response()->json([
                'success' => false,
                'message' => 'No Product Master row found for SKU '.$sku.'. Create/update it under Product Masters first.',
                'master_url' => route('reverb.listing.master'),
            ], 404);
        }

        $current = $request->input('listing', []);
        if (! is_array($current)) {
            $current = [];
        }

        $source = $this->productMasterListingPartial($pm, 'full');
        $filled = [];
        $stillMissing = [];
        $sourcesUsed = [];

        $fillText = static function (string $field, mixed $currentVal, mixed $sourceVal, string $sourceLabel) use (&$filled, &$stillMissing, &$sourcesUsed): void {
            $cur = trim((string) ($currentVal ?? ''));
            $src = trim((string) ($sourceVal ?? ''));
            if ($cur !== '') {
                return;
            }
            if ($src !== '') {
                $filled[$field] = $src;
                $sourcesUsed[$field] = $sourceLabel;
            } else {
                $stillMissing[] = $field;
            }
        };

        $fillText('title', $current['title'] ?? '', $source['title'] ?? '', 'Title Master');
        $fillText('make', $current['make'] ?? '', $source['make'] ?? '', 'Reverb Listing Master / Brand');
        $fillText('model', $current['model'] ?? '', $source['model'] ?? '', 'Reverb Listing Master');
        $fillText('finish', $current['finish'] ?? '', $source['finish'] ?? '', 'Reverb Listing Master');
        $fillText('year', $current['year'] ?? '', $source['year'] ?? '', 'Reverb Listing Master');
        $fillText('sku', $current['sku'] ?? '', $source['sku'] ?? $sku, 'Product Master SKU');
        $fillText('condition_name', $current['condition_name'] ?? '', $source['condition_name'] ?? '', 'Reverb Listing Master / General Specific');
        $fillText('category_name', $current['category_name'] ?? '', $source['category_name'] ?? '', 'Category Master');
        $fillText('upc', $current['upc'] ?? '', $source['upc'] ?? '', 'ID Master / Product Master');
        $fillText('description', $current['description'] ?? '', $source['description'] ?? '', 'Description Master');
        $fillText('shipping_profile_id', $current['shipping_profile_id'] ?? '', $source['shipping_profile_id'] ?? '', 'Reverb Listing Master');

        $curPrice = $current['price_amount'] ?? null;
        if (($curPrice === null || $curPrice === '' || ! is_numeric($curPrice) || (float) $curPrice <= 0)
            && isset($source['price_amount']) && is_numeric($source['price_amount']) && (float) $source['price_amount'] > 0) {
            $filled['price_amount'] = (float) $source['price_amount'];
            $filled['price_currency'] = $source['price_currency'] ?? 'USD';
            $sourcesUsed['price_amount'] = 'Pricing / Shipping Master (LP+Ship)';
        } elseif ($curPrice === null || $curPrice === '' || ! is_numeric($curPrice) || (float) $curPrice <= 0) {
            $stillMissing[] = 'price_amount';
        }

        $curCurrency = trim((string) ($current['price_currency'] ?? ''));
        if ($curCurrency === '' && empty($filled['price_currency'])) {
            $filled['price_currency'] = 'USD';
            $sourcesUsed['price_currency'] = 'Default USD';
        }

        $curPhotos = is_array($current['photos'] ?? null) ? array_values(array_filter(array_map('trim', $current['photos']))) : [];
        $srcPhotos = is_array($source['photos'] ?? null) ? $source['photos'] : [];
        if (count($curPhotos) < 11) {
            if ($srcPhotos !== []) {
                $merged = array_values(array_unique(array_slice(array_merge($curPhotos, $srcPhotos), 0, 25)));
                if ($merged !== $curPhotos) {
                    $filled['photos'] = $merged;
                    $sourcesUsed['photos'] = 'Image Master (+ Hero/gallery)';
                }
                if (count($merged) < 11) {
                    $stillMissing[] = 'photos';
                }
            } else {
                $stillMissing[] = 'photos';
            }
        }

        $curVideos = is_array($current['videos'] ?? null) ? array_values(array_filter(array_map('trim', $current['videos']))) : [];
        $srcVideos = is_array($source['videos'] ?? null) ? $source['videos'] : [];
        if (count($curVideos) < 1) {
            if ($srcVideos !== []) {
                $filled['videos'] = array_values(array_unique(array_slice($srcVideos, 0, 3)));
                $sourcesUsed['videos'] = 'Video Master';
            } else {
                $stillMissing[] = 'videos';
            }
        }

        // Highlighted features: always use Bullet Points Master (/bullet-points) data.
        $srcBullets = is_array($source['bullets'] ?? null) ? $source['bullets'] : [];
        if ($srcBullets !== []) {
            $filled['bullets'] = $srcBullets;
            $sourcesUsed['bullets'] = 'Bullet Points Master (/bullet-points)';
            if (! empty($source['highlighted_features_html'])) {
                $filled['highlighted_features_html'] = $source['highlighted_features_html'];
            }
        } else {
            $stillMissing[] = 'bullets';
        }

        $stillMissing = array_values(array_unique($stillMissing));
        $masterUrl = route('reverb.listing.master');

        return response()->json([
            'success' => true,
            'message' => $filled === []
                ? 'No blank fields could be filled from Product Master. Add missing master data, then retry.'
                : ('Autopopulated '.count($filled).' missing field(s) from Product Master pages.'),
            'partial' => $filled,
            'sources' => $sourcesUsed,
            'still_missing' => $stillMissing,
            'master_url' => $masterUrl,
            'hint' => $stillMissing !== []
                ? 'Still missing: '.implode(', ', $stillMissing).'. Edit under Reverb Listing Master / related Product Masters.'
                : null,
        ]);
    }

    protected function findProductMasterForSku(string $sku): ?ProductMaster
    {
        $skuTrim = trim($sku);
        if ($skuTrim === '') {
            return null;
        }

        $pm = ProductMaster::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($skuTrim)])
            ->first();

        if (! $pm && method_exists(ShopifySku::class, 'normalizeSkuForShopifyLookup')) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($skuTrim);
            $candidates = ProductMaster::query()
                ->where('sku', 'like', '%'.preg_replace('/\s+/', '%', $skuTrim).'%')
                ->limit(50)
                ->get();
            $pm = $candidates->first(function ($row) use ($norm) {
                return ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? '')) === $norm;
            });
        }

        return $pm;
    }

    /**
     * @return array{success: bool, message?: string, listing?: array<string, mixed>, validation?: array<string, mixed>}
     */
    protected function buildListingEditorResponse(int $shopifySkuId, bool $forcePull): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.'], 422);
        }

        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $shopifyRow->sku;
        $metric = $this->reverbMetricMapForSkus([$sku])[$sku] ?? null;

        if (! $this->isShopifySkuLinkedOnReverb($metric, $sku)) {
            return response()->json([
                'success' => false,
                'message' => 'This SKU is not linked on Reverb. Run Sync Reverb link map first.',
            ], 422);
        }

        $productId = (string) ($metric->product_id ?? '');
        $info = $this->aliExpressApi->getProductInfo($productId);
        if (empty($info['success'])) {
            return response()->json([
                'success' => false,
                'message' => $info['message'] ?? 'Failed to load listing from Reverb.',
            ], 422);
        }

        $aeData = is_array($info['data'] ?? null) ? $info['data'] : [];
        $listing = app(ReverbDetailFormatter::class)->toListingEditor($aeData, $metric, $sku);
        unset($listing['raw']);
        // Highlighted features always come from Bullet Points Master (/bullet-points).
        $listing = $this->applyBulletPointsMasterToListing($listing, $sku);
        $validation = app(ReverbListingValidator::class)->validate($listing);

        if ($forcePull && $metric) {
            $metric->update([
                'product_name' => trim((string) ($listing['title'] ?? $metric->product_name)) ?: $metric->product_name,
                'price' => $listing['price_amount'] ?? $metric->price,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $forcePull ? 'Pulled latest listing from Reverb.' : 'Listing loaded.',
            'shopify_sku_id' => $shopifySkuId,
            'sku' => $sku,
            'listing' => $listing,
            'validation' => $validation,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function productMasterListingPartial(ProductMaster $pm, string $section): array
    {
        $partial = [];
        $values = is_array($pm->Values) ? $pm->Values : [];

        if (in_array($section, ['full', 'title', 'details'], true)) {
            $title = trim((string) ($pm->title150 ?? $pm->title100 ?? ''));
            if ($title !== '') {
                $partial['title'] = $title;
            }
        }

        if (in_array($section, ['full', 'details'], true)) {
            $make = trim((string) ($pm->reverb_make ?? $values['brand'] ?? ''));
            $model = trim((string) ($pm->reverb_model ?? ''));
            $finish = trim((string) ($pm->reverb_finish ?? ''));
            $year = trim((string) ($pm->reverb_year ?? ''));
            $condition = trim((string) ($pm->reverb_condition ?? $values['condition'] ?? ''));
            $upc = trim((string) ($pm->barcode ?? $values['upc'] ?? $values['gtin'] ?? $values['ean'] ?? ''));
            $shippingProfile = trim((string) ($pm->reverb_shipping_profile_id ?? ''));

            if ($make !== '') {
                $partial['make'] = $make;
            }
            if ($model !== '') {
                $partial['model'] = $model;
            }
            if ($finish !== '') {
                $partial['finish'] = $finish;
            }
            if ($year !== '') {
                $partial['year'] = $year;
            }
            if ($condition !== '') {
                $partial['condition_name'] = $condition;
            }
            if ($upc !== '') {
                $partial['upc'] = $upc;
            }
            if ($shippingProfile !== '') {
                $partial['shipping_profile_id'] = $shippingProfile;
            }
            $partial['sku'] = trim((string) ($pm->sku ?? ''));

            $categoryName = trim((string) ($pm->category ?? ''));
            if ($categoryName === '' && ! empty($pm->category_id)) {
                $cat = ProductCategory::query()->find($pm->category_id);
                $categoryName = trim((string) ($cat->category_name ?? ''));
            }
            if ($categoryName !== '') {
                $partial['category_name'] = $categoryName;
            }

            $lp = isset($values['lp']) && is_numeric($values['lp']) ? (float) $values['lp'] : null;
            $ship = isset($values['ship']) && is_numeric($values['ship']) ? (float) $values['ship'] : 0.0;
            if ($lp !== null && $lp > 0) {
                // Suggested sellable floor from masters; user can edit before push.
                $partial['price_amount'] = round($lp + $ship, 2);
                $partial['price_currency'] = 'USD';
            }
        }

        if (in_array($section, ['full', 'images'], true)) {
            $photos = [];
            foreach (array_merge([$pm->main_image ?? null], [
                $pm->image1, $pm->image2, $pm->image3, $pm->image4, $pm->image5,
                $pm->image6, $pm->image7, $pm->image8, $pm->image9, $pm->image10,
                $pm->image11, $pm->image12, $pm->image13, $pm->image14, $pm->image15,
                $pm->image16, $pm->image17, $pm->image18, $pm->image19, $pm->image20,
            ]) as $url) {
                $url = trim((string) $url);
                if ($url !== '') {
                    $photos[] = $url;
                }
            }
            foreach (['hero_image', 'trust_image', 'ugc_image', 'image_path'] as $vk) {
                $url = trim((string) ($values[$vk] ?? ''));
                if ($url !== '') {
                    $photos[] = $url;
                }
            }
            $partial['photos'] = array_values(array_unique(array_slice($photos, 0, 25)));
        }

        if (in_array($section, ['full', 'bullets'], true)) {
            // Source of truth: Bullet Points Master page (/bullet-points) → product_master.bullet1–5
            $bullets = $this->bulletPointsFromProductMaster($pm);
            $partial['bullets'] = $bullets;
            if ($bullets !== []) {
                $features = '<div class="highlighted-features">'."\n";
                foreach ($bullets as $b) {
                    $features .= '<p>'.e($b).'<br></p>'."\n";
                }
                $features .= '</div>';
                $partial['highlighted_features_html'] = $features;
            }
        }

        if (in_array($section, ['full', 'description'], true)) {
            $html = trim((string) ($pm->description_html ?? ''));
            if ($html === '') {
                foreach (['description_1500', 'description_1000', 'description_800', 'description_600', 'product_description'] as $col) {
                    $text = trim((string) ($pm->{$col} ?? ''));
                    if ($text !== '') {
                        $html = '<p>'.nl2br(e($text), false).'</p>';
                        break;
                    }
                }
            }
            if ($html !== '') {
                $partial['description'] = $html;
            }
        }

        if (in_array($section, ['full', 'videos'], true)) {
            $videos = [];
            foreach ([
                'video_product_overview',
                'video_unboxing',
                'video_how_to',
                'video_setup',
                'video_troubleshooting',
                'video_brand_story',
                'video_product_benefits',
            ] as $col) {
                $url = trim((string) ($pm->{$col} ?? ''));
                if ($url !== '') {
                    $videos[] = $url;
                }
            }
            $partial['videos'] = array_values(array_unique(array_slice($videos, 0, 3)));
        }

        return $partial;
    }

    /**
     * Bullet Points Master (/bullet-points) → product_master.bullet1–bullet5.
     *
     * @return list<string>
     */
    protected function bulletPointsFromProductMaster(ProductMaster $pm): array
    {
        $bullets = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $b = trim((string) ($pm->{$col} ?? ''));
            if ($b !== '') {
                $bullets[] = $b;
            }
        }

        return $bullets;
    }

    /**
     * Always prefer Bullet Points Master data for the Highlighted features field.
     *
     * @param  array<string, mixed>  $listing
     * @return array<string, mixed>
     */
    protected function applyBulletPointsMasterToListing(array $listing, string $sku): array
    {
        $pm = $this->findProductMasterForSku($sku);
        if (! $pm) {
            return $listing;
        }

        $bullets = $this->bulletPointsFromProductMaster($pm);
        if ($bullets !== []) {
            $listing['bullets'] = $bullets;
        }

        return $listing;
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.']);
        }

        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        Log::info('Reverb link map sync page', ['page' => $page, 'reset' => $reset]);

        $result = app(ReverbLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $progress = app(ReverbLinkMapSyncService::class)->getProgress();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('reverb_order_metrics')) {
            $orders = ReverbOrderMetric::query()
                ->where('order_date', '>=', ReverbOrderSyncService::MIN_ORDER_DATE)
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.reverb.orders', [
            'orders' => $orders,
            'title' => 'Reverb — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('reverb'),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = ReverbOrderMetric::query()->findOrFail($id);
        $orderId = $line->orderRef();

        $lines = $orderId !== ''
            ? ReverbOrderMetric::query()
                ->where(function ($q) use ($orderId) {
                    $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
                })
                ->orderBy('id')
                ->get()
            : collect([$line]);

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(ReverbOrderDetailService::class);

        if ($orderId === '') {
            $aeLiveError = 'Order ID is missing on this row. Reverb order_number and order_id should be the same — re-fetch orders or open a row that has order_number filled.';
        } elseif ($this->apiConfig->isConfigured('reverb')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
                $orderId = $line->orderRef() ?: $orderId;
                $lines = ReverbOrderMetric::query()
                    ->where(function ($q) use ($orderId) {
                        $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
                    })
                    ->orderBy('id')
                    ->get();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live Reverb order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(ReverbDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.reverb.order-show', [
            'title' => 'Reverb Order — '.($orderId !== '' ? $orderId : '#'.$line->id),
            'orderId' => $orderId !== '' ? $orderId : (string) $line->id,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'importPaidOrdersOnly' => MarketplaceSyncSettings::importPaidOrdersOnly('reverb'),
            'orderIsPaid' => MarketplaceOrderPaidFilter::isPaid('reverb', $line),
        ]);
    }

    public function pullOrderFromReverb(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.']);
        }

        $line = ReverbOrderMetric::query()->findOrFail($id);
        $orderId = $line->orderRef();
        if ($orderId === '') {
            return response()->json(['success' => false, 'message' => 'Order ID is missing on this row.']);
        }

        $result = app(ReverbOrderDetailService::class)->fetchAndPersistOrderDetail($orderId);

        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from Reverb.' : 'Failed to pull order details.'),
        ]);
    }

    /**
     * Push Shopify fulfillment tracking number to Reverb (mark shipped).
     */
    public function pushTrackingToReverb(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.']);
        }

        $line = ReverbOrderMetric::query()->findOrFail($id);
        $result = app(ReverbTrackingSyncService::class)->pushTrackingForOrder($line);

        return response()->json([
            'success' => ! empty($result['success']),
            'skipped' => ! empty($result['skipped']),
            'action' => $result['action'] ?? null,
            'message' => $result['message'] ?? 'Tracking push finished.',
            'shopify_tracking' => $result['shopify_tracking'] ?? null,
            'shopify_carrier' => $result['shopify_carrier'] ?? null,
            'provider' => $result['provider'] ?? null,
        ], ! empty($result['success']) || ! empty($result['skipped']) ? 200 : 422);
    }

    /**
     * Bulk push Shopify tracking → Reverb for linked orders.
     */
    public function syncTrackingNow(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.']);
        }

        \App\Jobs\SyncReverbTrackingJob::dispatch(false);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Tracking sync queued. It reads Shopify fulfillments and marks orders shipped on Reverb.',
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $fromDate = trim((string) $request->input('from_date', ''));
        $sync = app(ReverbOrderSyncService::class);

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
        $settings = MarketplaceSyncSettings::getFor('reverb');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        \App\Jobs\RunMarketplaceInventorySyncJob::dispatch('reverb');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Inventory sync queued. It runs in the background from live Shopify (usually a few minutes). Keep inventory sync ON — webhook + 15-min schedule also push automatically.',
        ]);
    }

    /**
     * Push Shopify → Reverb inventory for mismatch SKUs immediately (no queue).
     */
    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor('reverb');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ], 422);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = app(ReverbLiveListingsService::class);
        $linkedSkus = $this->linkedReverbSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $liveService->peekCached(),
            $this->reverbStockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $mismatchQty = $classified['mismatch'] ?? [];
        $scope = strtolower((string) $request->input('scope', $request->input('link', 'all')));
        if (in_array($scope, ['mismatch', 'active', 'mismatch_active'], true)
            || in_array($scope, ['mismatch_inactive', 'inactive'], true)) {
            $mismatchNormToSku = [];
            foreach ($mismatchQty as $sku) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($n !== '') {
                    $mismatchNormToSku[$n] = (string) $sku;
                }
            }
            $idx = $this->reverbStateIndexFromCache(
                $liveService,
                static fn (string $norm): bool => isset($mismatchNormToSku[$norm]),
                count($mismatchNormToSku),
                $mismatchNormToSku
            );
            if ($idx['ready']) {
                $activeMpSkus = array_values(array_unique(array_merge(
                    $idx['skusByState']['live'] ?? [],
                    $idx['skusByState']['out_of_stock'] ?? [],
                    $idx['skusByState']['sold'] ?? []
                )));
                $active = $catalog->filterSkusByNormalizedAllowList($mismatchQty, $activeMpSkus);
                $mismatch = in_array($scope, ['mismatch_inactive', 'inactive'], true)
                    ? $catalog->excludeSkusByNormalizedList($mismatchQty, $active)
                    : $active;
            } else {
                $mismatch = in_array($scope, ['mismatch_inactive', 'inactive'], true) ? [] : $mismatchQty;
            }
        } else {
            $mismatch = $mismatchQty;
        }

        $requested = $request->input('skus');
        if (is_array($requested) && $requested !== []) {
            $allow = [];
            foreach ($requested as $sku) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($n !== '') {
                    $allow[$n] = true;
                }
            }
            $mismatch = array_values(array_filter($mismatch, static function (string $sku) use ($allow) {
                $n = ShopifySku::normalizeSkuForShopifyLookup($sku);

                return $n !== '' && isset($allow[$n]);
            }));
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

        $result = app(ReverbInventorySyncService::class)->syncSkusFromShopify($batch, null, true);
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
        $order = ReverbOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($request->boolean('dry_run')) {
            $preview = app(ReverbOrderPushService::class)->previewShopifyPush($order);

            return response()->json($preview);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        if (MarketplaceOrderPaidFilter::blocksUnpaidPush('reverb', $order)) {
            return response()->json([
                'success' => false,
                'message' => MarketplaceOrderPaidFilter::unpaidPushBlockedMessage(),
            ], 422);
        }

        // Manual push is synchronous — only auto-import uses the queue.
        $push = app(ReverbOrderPushService::class);
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
     * Delete a local Reverb order that is still ready for Shopify import
     * (not yet imported). Removes all line rows for that AE order_id.
     */
    public function deleteReadyOrder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $order = ReverbOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (! empty($order->shopify_order_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This order is already imported to Shopify and cannot be deleted here.',
            ], 422);
        }

        $orderId = $order->orderRef();
        if ($orderId === '') {
            return response()->json(['success' => false, 'message' => 'Order ID is missing on this row.'], 422);
        }

        $deleted = ReverbOrderMetric::query()
            ->where(function ($q) use ($orderId) {
                $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
            })
            ->whereNull('shopify_order_id')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Removed Reverb order {$orderId} from ready-for-import ({$deleted} row(s)).",
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
        $order = ReverbOrderMetric::find($id);

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

        $orderId = $order->orderRef();
        if ($orderId === '') {
            return response()->json(['success' => false, 'message' => 'Order ID is missing on this row.'], 422);
        }

        $shopifyOrderId = trim((string) $request->input('shopify_order_id', ''));
        if ($shopifyOrderId === '') {
            $shopifyOrderId = 'manual:'.$orderId;
        }

        $query = ReverbOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) use ($orderId) {
                $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
            });

        $updated = $query->update([
            'shopify_order_id' => $shopifyOrderId,
            'import_status' => 'imported',
            'pushed_to_shopify_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Marked Reverb order {$orderId} as already imported ({$updated} row(s)).",
            'shopify_order_id' => $shopifyOrderId,
            'updated' => $updated,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.reverb.settings', [
            'settings' => MarketplaceSyncSettings::getFor('reverb'),
            'title' => 'Reverb — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('reverb'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('reverb');

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
            'push_tracking_to_reverb', 'tracking_send_notification', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_reverb', 'sync_title', 'sync_images',
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

        MarketplaceSyncSettings::setFor('reverb', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Reverb sync settings saved.']);
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
     * SKUs in reverb_metric that map to a real Shopify SKU (not product_id placeholders).
     *
     * @return array<int, string>
     */
    protected function linkedReverbSkus(): array
    {
        if (! Schema::hasTable('reverb_metric')) {
            return [];
        }

        return ReverbMetric::query()
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
    protected function shopifyListingCounts(
        array $linkedSkus,
        array $shopifyNormKeys = [],
        ?ShopifyLiveVerifiedCatalogService $catalog = null
    ): array {
        $catalog = $catalog ?? app(ShopifyLiveVerifiedCatalogService::class);
        $mpStock = $this->reverbStockMapForSkus($catalog->filterLinkedToVerified($linkedSkus));
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        if ($classified !== null) {
            return $classified['counts'];
        }

        return ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0];
    }

    /**
     * @return array<string, true>
     */
    protected function shopifyNormalizedSkuKeys(): array
    {
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        if ($catalog->tablesReady() && $catalog->hasAnyActive()) {
            return $catalog->normalizedKeys();
        }

        return $this->shopifyNormalizedSkuKeysFromShopifySkusTable();
    }

    /**
     * @return array<string, true>
     */
    protected function shopifyNormalizedSkuKeysFromShopifySkusTable(): array
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
     * @param  array<int, string>  $linkedSkus
     * @return list<string>
     */
    protected function filterVerifiedSkusForTab(
        ShopifyLiveVerifiedCatalogService $catalog,
        string $linkTab,
        array $linkedSkus,
        string $searchSku,
        string $searchName
    ): array {
        $skus = $catalog->tablesReady()
            ? (($linkTab === 'all')
                ? $catalog->allSkuList()
                : (($linkTab === 'unlinked')
                    ? $catalog->inStockActiveSkuList()
                    : $catalog->activeSkuList()))
            : [];
        if ($skus === []) {
            return [];
        }

        $linkedNorm = [];
        foreach ($linkedSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($n !== '') {
                $linkedNorm[$n] = true;
            }
        }

        if ($linkTab === 'unlinked' && $linkedNorm !== []) {
            $skus = array_values(array_filter($skus, static function (string $sku) use ($linkedNorm) {
                $n = ShopifySku::normalizeSkuForShopifyLookup($sku);

                return $n === '' || ! isset($linkedNorm[$n]);
            }));
        }

        $skus = $this->applySkuSearchFilter($skus, $searchSku, $searchName, $catalog);
        sort($skus, SORT_STRING | SORT_FLAG_CASE);

        return $skus;
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    protected function applySkuSearchFilter(
        array $skus,
        string $searchSku,
        string $searchName,
        ShopifyLiveVerifiedCatalogService $catalog
    ): array {
        if ($searchSku !== '') {
            $needle = mb_strtolower($searchSku);
            $skus = array_values(array_filter($skus, static function (string $sku) use ($needle) {
                return str_contains(mb_strtolower($sku), $needle);
            }));
        }

        if ($searchName === '' || $skus === [] || ! $catalog->tablesReady()) {
            return $skus;
        }

        $needle = mb_strtolower($searchName);
        $matchNorm = [];
        $catalog->activeVariantQuery()
            ->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(p.title) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw("LOWER(COALESCE(v.variant_title, '')) LIKE ?", ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(v.sku) LIKE ?', ['%'.$needle.'%']);
            })
            ->select(['v.sku'])
            ->orderBy('v.id')
            ->chunk(1000, function ($rows) use (&$matchNorm) {
                foreach ($rows as $row) {
                    $n = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
                    if ($n !== '') {
                        $matchNorm[$n] = true;
                    }
                }
            });

        return array_values(array_filter($skus, static function (string $sku) use ($matchNorm) {
            $n = ShopifySku::normalizeSkuForShopifyLookup($sku);

            return $n !== '' && isset($matchNorm[$n]);
        }));
    }

    /**
     * @param  list<string>  $skus
     */
    protected function paginateSkuList(array $skus, int $page, int $perPage): LengthAwarePaginator
    {
        $total = count($skus);
        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($skus, $offset, $perPage);

        return (new LengthAwarePaginator($slice, $total, $perPage, $page))
            ->withQueryString()
            ->withPath(request()->url());
    }

    /**
     * Build ShopifySku models for a page of verified catalog SKUs (for live qty + detail links).
     *
     * @param  list<string>  $pageSkus
     * @return list<ShopifySku>
     */
    protected function shopifySkuRowsForVerifiedPage(array $pageSkus, ShopifyLiveVerifiedCatalogService $catalog): array
    {
        if ($pageSkus === []) {
            return [];
        }

        $existing = ShopifySku::query()
            ->whereIn('sku', $pageSkus)
            ->get()
            ->keyBy(fn (ShopifySku $r) => ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku));

        $catalogMeta = [];
        if ($catalog->tablesReady()) {
            $catalog->activeVariantQuery()
                ->whereIn('v.sku', $pageSkus)
                ->select([
                    'v.sku',
                    'v.shopify_variant_id',
                    'v.price',
                    'v.variant_title',
                    'v.inventory_quantity',
                    'p.title as product_title',
                ])
                ->orderBy('v.id')
                ->get()
                ->each(function ($row) use (&$catalogMeta) {
                    $n = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
                    if ($n !== '' && ! isset($catalogMeta[$n])) {
                        $catalogMeta[$n] = $row;
                    }
                });
        }

        $out = [];
        foreach ($pageSkus as $sku) {
            $n = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $row = $n !== '' ? ($existing[$n] ?? null) : null;
            $meta = $n !== '' ? ($catalogMeta[$n] ?? null) : null;

            if ($row) {
                if ($meta) {
                    if (empty($row->variant_id) && ! empty($meta->shopify_variant_id)) {
                        $row->variant_id = (string) $meta->shopify_variant_id;
                    }
                    if (empty($row->product_title) && ! empty($meta->product_title)) {
                        $row->product_title = (string) $meta->product_title;
                    }
                    if (empty($row->variant_title) && ! empty($meta->variant_title)) {
                        $row->variant_title = (string) $meta->variant_title;
                    }
                    if ($row->price === null && isset($meta->price)) {
                        $row->price = $meta->price;
                    }
                }
                $out[] = $row;

                continue;
            }

            $stub = new ShopifySku([
                'sku' => $sku,
                'variant_id' => $meta->shopify_variant_id ?? null,
                'product_title' => $meta->product_title ?? null,
                'variant_title' => $meta->variant_title ?? null,
                'price' => $meta->price ?? null,
                'inv' => $meta->inventory_quantity ?? null,
                'available_to_sell' => $meta->inventory_quantity ?? null,
                'on_hand' => $meta->inventory_quantity ?? null,
            ]);
            $stub->id = null;
            $out[] = $stub;
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $shopifyNormKeys
     */
    protected function countAeNotInShopify(array $shopifyNormKeys): int
    {
        if (! Schema::hasTable('reverb_metric')) {
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
        int $perPage,
        ?array $allowSkus = null
    ): LengthAwarePaginator {
        if (! Schema::hasTable('reverb_metric')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $allowNorms = null;
        if (is_array($allowSkus)) {
            $allowNorms = [];
            foreach ($allowSkus as $sku) {
                $n = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($n !== '') {
                    $allowNorms[$n] = true;
                }
            }
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (ReverbMetric $metric) use ($shopifyNormKeys, $searchSku, $searchName, $allowNorms) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);
            if ($norm === '' || isset($shopifyNormKeys[$norm])) {
                return false;
            }
            if ($allowNorms !== null && ! isset($allowNorms[$norm])) {
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
        $aeStockMap = $this->reverbStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (ReverbMetric $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;
            $aeQty = MarketplaceListingStockResolver::qtyFromMap($aeStockMap, $sku);

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->product_id,
                'sku' => $sku,
                'title' => $metric->product_name ?? $metric->sku,
                'reverb_title' => null,
                'image_src' => null,
                'price' => $metric->price ?? null,
                'shopify_price' => null,
                'quantity' => $aeQty,
                'rv_quantity' => $aeQty,
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

    /**
     * @return array{all: int, live: int, sold: int, out_of_stock: int, ended: int, draft: int, other: int}
     */
    protected function emptyReverbStateCounts(int $all = 0): array
    {
        return [
            'all' => $all,
            'live' => 0,
            'sold' => 0,
            'out_of_stock' => 0,
            'ended' => 0,
            'draft' => 0,
            'other' => 0,
        ];
    }

    protected function parseReverbStateTab(Request $request): string
    {
        $state = strtolower(trim((string) $request->input('state', 'all')));
        $allowed = ['all', 'live', 'sold', 'out_of_stock', 'ended', 'draft', 'other'];

        return in_array($state, $allowed, true) ? $state : 'all';
    }

    /**
     * Bucket Reverb API states into filter tabs.
     */
    protected function reverbStateBucket(?string $state): string
    {
        $state = strtolower(trim((string) $state));
        if ($state === 'live' || $state === 'active') {
            return 'live';
        }
        if ($state === 'sold') {
            return 'sold';
        }
        if ($state === 'out_of_stock') {
            return 'out_of_stock';
        }
        if ($state === 'ended') {
            return 'ended';
        }
        if (MarketplaceLiveInventoryRules::reverbIsDraftLike($state) || $state === 'draft') {
            return 'draft';
        }

        return $state === '' ? 'other' : 'other';
    }

    /**
     * State counts + SKUs from warm live-listings cache (Refresh live).
     *
     * @param  callable(string): bool  $includeNorm
     * @param  array<string, string>  $normToSku  optional map so whereIn uses Shopify-facing SKUs
     * @return array{counts: array{all: int, live: int, sold: int, out_of_stock: int, ended: int, draft: int, other: int}, skusByState: array<string, array<int, string>>, ready: bool}
     */
    protected function reverbStateIndexFromCache(
        ReverbLiveListingsService $liveService,
        callable $includeNorm,
        int $allCount,
        array $normToSku = []
    ): array {
        $counts = $this->emptyReverbStateCounts($allCount);
        $skusByState = [
            'live' => [],
            'sold' => [],
            'out_of_stock' => [],
            'ended' => [],
            'draft' => [],
            'other' => [],
        ];

        $cached = $liveService->peekCached();
        if (! is_array($cached) || $cached === []) {
            return ['counts' => $counts, 'skusByState' => $skusByState, 'ready' => false];
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
            $bucket = $this->reverbStateBucket((string) ($row['state'] ?? ''));
            if (! isset($counts[$bucket])) {
                $bucket = 'other';
            }
            $counts[$bucket]++;
            $skusByState[$bucket][] = $normToSku[$norm] ?? $rawSku;
        }

        return ['counts' => $counts, 'skusByState' => $skusByState, 'ready' => true];
    }

    protected function aeMetricsWithRealSkuQuery()
    {
        return ReverbMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, ReverbMetric>
     */
    protected function reverbMetricMapForSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('reverb_metric')) {
            return [];
        }

        $exact = ReverbMetric::query()->whereIn('sku', $skus)->get()->keyBy('sku');
        $byNorm = [];
        foreach (ReverbMetric::query()
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
     * Local Reverb stock for listings index — same resolver as detail pages.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    protected function reverbStockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_REVERB,
            $skus
        );
    }

    protected function isShopifySkuLinkedOnReverb(?ReverbMetric $metric, string $shopifySku): bool
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
