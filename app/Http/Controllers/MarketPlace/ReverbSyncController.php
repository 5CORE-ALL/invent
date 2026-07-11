<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\ImportReverbManagerOrderToShopify;
use App\Models\ReverbMetric;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\ReverbManagerApiService;
use App\Services\ReverbAuthService;
use App\Services\MarketplaceManager\ReverbDetailFormatter;
use App\Services\MarketplaceManager\ReverbInventorySyncService;
use App\Services\MarketplaceManager\ReverbLinkMapSyncService;
use App\Services\MarketplaceManager\ReverbOrderDetailService;
use App\Services\MarketplaceManager\ReverbOrderPushService;
use App\Services\MarketplaceManager\ReverbOrderSyncService;
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
        if (! in_array($linkTab, ['all', 'linked', 'unlinked', 'not_in_shopify'], true)) {
            $linkTab = 'all';
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);
            $counts = ['all' => 0, 'linked' => 0, 'unlinked' => 0, 'not_in_shopify' => 0];

            return view('marketplace.reverb.products', [
                'products' => $products,
                'title' => 'Reverb — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'counts' => $counts,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('reverb'),
            ]);
        }

        $linkedSkus = $this->linkedReverbSkus();
        $shopifyNormKeys = $this->shopifyNormalizedSkuKeys();
        $counts = $this->shopifyListingCounts($linkedSkus, $shopifyNormKeys);

        if ($linkTab === 'not_in_shopify') {
            $paginator = $this->paginateAeNotInShopify($searchSku, $searchName, $shopifyNormKeys, $page, $perPage);

            return view('marketplace.reverb.products', [
                'products' => $paginator,
                'title' => 'Reverb — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'counts' => $counts,
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('reverb'),
            ]);
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

        if ($linkTab === 'linked' && $linkedSkus !== []) {
            $query->whereIn('sku', $linkedSkus);
        } elseif ($linkTab === 'unlinked' && $linkedSkus !== []) {
            $query->whereNotIn('sku', $linkedSkus);
        } elseif ($linkTab === 'linked') {
            $query->whereRaw('1 = 0');
        }

        $paginator = $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $skus = collect($paginator->items())->pluck('sku')->filter()->values()->all();
        $aeMap = $this->reverbMetricMapForSkus($skus);
        $aeStockMap = $this->reverbStockMapForSkus($skus);

        $enriched = collect($paginator->items())->map(function (ShopifySku $row) use ($aeMap, $aeStockMap) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnReverb($metric, $sku);
            $shopifyQty = $row->available_to_sell ?? $row->inv ?? $row->on_hand ?? null;
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $aeQty = $linked ? ($aeStockMap[strtoupper(trim($sku))] ?? null) : null;

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($metric->product_id ?? null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'reverb_title' => $metric->product_name ?? null,
                'image_src' => $row->image_src ?? null,
                'price' => $linked ? ($metric->price ?? null) : null,
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'rv_quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'counts' => $counts,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
        ]);
    }

    public function showProduct(int $shopifySkuId): View
    {
        $shopifyRow = ShopifySku::query()->findOrFail($shopifySkuId);
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
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = ReverbOrderMetric::query()->findOrFail($id);
        $orderId = (string) $line->order_id;

        $lines = ReverbOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $aeLiveError = null;
        $aeDataSource = 'cached';
        $detailService = app(ReverbOrderDetailService::class);

        if ($this->apiConfig->isConfigured('reverb')) {
            $pull = $detailService->fetchAndPersistOrderDetail($orderId);
            if (! empty($pull['success'])) {
                $aeDataSource = 'api';
                $line->refresh();
            } else {
                $aeLiveError = $pull['message'] ?? 'Could not refresh live Reverb order details.';
            }
        }

        $orderRoot = $detailService->resolveOrderRoot($line);
        $detail = app(ReverbDetailFormatter::class)->formatOrder($orderRoot, $lines, $line);

        return view('marketplace.reverb.order-show', [
            'title' => 'Reverb Order — '.$orderId,
            'orderId' => $orderId,
            'line' => $line,
            'detail' => $detail,
            'aeLiveError' => $aeLiveError,
            'aeDataSource' => $aeDataSource,
            'connected' => $this->apiConfig->isConfigured('reverb'),
        ]);
    }

    public function pullOrderFromReverb(int $id): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('reverb')) {
            return response()->json(['success' => false, 'message' => 'Reverb not connected.']);
        }

        $line = ReverbOrderMetric::query()->findOrFail($id);
        $result = app(ReverbOrderDetailService::class)->fetchAndPersistOrderDetail((string) $line->order_id);

        return response()->json([
            'success' => ! empty($result['success']),
            'message' => $result['message'] ?? ($result['success'] ? 'Order details updated from Reverb.' : 'Failed to pull order details.'),
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
        $result = app(ReverbInventorySyncService::class)->syncFromShopify(false);

        return response()->json([
            'success' => ($result['failed'] ?? 0) === 0,
            'message' => $result['message'],
            'updated' => $result['updated'] ?? 0,
            'price_updated' => $result['price_updated'] ?? 0,
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

        ImportReverbManagerOrderToShopify::dispatch($order->id);
        $order->update(['import_status' => 'queued']);

        return response()->json(['success' => true, 'message' => 'Import queued. Ensure queue worker is running.']);
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

        $orderId = (string) $order->order_id;
        $deleted = ReverbOrderMetric::query()
            ->where('order_id', $orderId)
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

        $orderId = (string) ($order->order_id ?: $order->order_number);
        $shopifyOrderId = trim((string) $request->input('shopify_order_id', ''));
        if ($shopifyOrderId === '') {
            $shopifyOrderId = 'manual:'.$orderId;
        }

        $query = ReverbOrderMetric::query()->whereNull('shopify_order_id');
        if ($order->order_id) {
            $query->where('order_id', $order->order_id);
        } else {
            $query->where('id', $order->id);
        }

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
        $order = $this->mergeSettingsSection($current['order'] ?? [], $request->input('order', []), [
            'fetch_orders', 'auto_import_to_shopify', 'keep_order_number_from_channel',
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
        int $perPage
    ): LengthAwarePaginator {
        if (! Schema::hasTable('reverb_metric')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $rows = $this->aeMetricsWithRealSkuQuery()->orderBy('sku')->get()->filter(function (ReverbMetric $metric) use ($shopifyNormKeys, $searchSku, $searchName) {
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
        $aeStockMap = $this->reverbStockMapForSkus($sliceSkus);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->map(function (ReverbMetric $metric) use ($aeStockMap) {
            $sku = (string) $metric->sku;

            return (object) [
                'shopify_sku_id' => null,
                'product_id' => $metric->product_id,
                'sku' => $sku,
                'title' => $metric->product_name ?? $metric->sku,
                'reverb_title' => null,
                'image_src' => null,
                'price' => $metric->price ?? null,
                'shopify_price' => null,
                'quantity' => $aeStockMap[strtoupper(trim($sku))] ?? null,
                'rv_quantity' => $aeStockMap[strtoupper(trim($sku))] ?? null,
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
     * Local Reverb stock for listings index.
     * Prefer Marketplace Manager pricing cache, then product_stock_mappings / reverb_products.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int> keyed by uppercase SKU
     */
    protected function reverbStockMapForSkus(array $skus): array
    {
        if ($skus === []) {
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
        if ($keys === []) {
            return [];
        }

        $map = [];

        if (Schema::hasTable('reverb_pricing_prices') && Schema::hasColumn('reverb_pricing_prices', 'rv_stock')) {
            ReverbPricingPrice::query()
                ->whereIn('sku', $keys)
                ->get(['sku', 'rv_stock'])
                ->each(function (ReverbPricingPrice $row) use (&$map) {
                    $key = strtoupper(trim((string) $row->sku));
                    if ($key !== '' && $row->rv_stock !== null) {
                        $map[$key] = (int) $row->rv_stock;
                    }
                });
        }

        $missing = array_values(array_filter($keys, static function ($sku) use ($map) {
            return ! array_key_exists(strtoupper(trim((string) $sku)), $map);
        }));
        $missingUpper = array_values(array_unique(array_map(
            static fn ($sku) => strtoupper(trim((string) $sku)),
            $missing
        )));

        if ($missingUpper !== []
            && Schema::hasTable('product_stock_mappings')
            && Schema::hasColumn('product_stock_mappings', 'inventory_reverb')
        ) {
            \App\Models\ProductStockMapping::query()
                ->whereIn('sku', $keys)
                ->whereNotNull('inventory_reverb')
                ->get(['sku', 'inventory_reverb'])
                ->each(function ($row) use (&$map) {
                    $key = strtoupper(trim((string) $row->sku));
                    if ($key === '' || array_key_exists($key, $map)) {
                        return;
                    }
                    $raw = $row->inventory_reverb;
                    if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                        return;
                    }
                    $map[$key] = (int) $raw;
                });
        }

        $stillMissing = array_values(array_filter($missingUpper, static fn ($sku) => ! array_key_exists($sku, $map)));
        if ($stillMissing !== [] && Schema::hasTable('reverb_products') && Schema::hasColumn('reverb_products', 'remaining_inventory')) {
            \Illuminate\Support\Facades\DB::table('reverb_products')
                ->whereNotNull('remaining_inventory')
                ->where(function ($q) use ($stillMissing, $keys) {
                    $q->whereIn('sku', $keys);
                    foreach ($stillMissing as $sku) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                    }
                })
                ->get(['sku', 'remaining_inventory'])
                ->each(function ($row) use (&$map) {
                    $key = strtoupper(trim((string) $row->sku));
                    if ($key === '' || array_key_exists($key, $map) || $row->remaining_inventory === null) {
                        return;
                    }
                    $map[$key] = (int) $row->remaining_inventory;
                });
        }

        return $map;
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
