<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\RunMarketplaceInventorySyncJob;
use App\Jobs\SyncTikTok2TrackingJob;
use App\Jobs\WarmTikTok2LiveListingsCache;
use App\Models\MarketplaceSyncSettings;
use App\Models\TikTokProductTwo;
use App\Models\Tiktok2Order;
use App\Services\MarketplaceManager\TikTok2OrderSyncService;
use App\Services\MarketplaceManager\TikTokListingsPageBuilder;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\TikTok2ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TikTok2SyncController extends Controller
{
    public function __construct(
        protected TikTok2ShopService $tiktok,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $clientKey = (string) config('services.tiktok2.client_key', '');
        $clientSecret = (string) config('services.tiktok2.client_secret', '');
        $accessToken = (string) (config('services.tiktok2.access_token') ?: '');
        $refreshToken = (string) (config('services.tiktok2.refresh_token') ?: '');
        $shopId = (string) config('services.tiktok2.shop_id', '');
        $redirectUri = (string) config('services.tiktok2.redirect_uri', '');
        $hasAppCreds = filled($clientKey) && filled($clientSecret);
        $connected = $this->apiConfig->isConfigured('tiktok2') && $this->tiktok->isAuthenticated();

        return view('marketplace.tiktok2.connect', [
            'title' => 'TikTok 2 — Connect',
            'connected' => $connected,
            'credentialsReady' => $hasAppCreds,
            'hasClientKey' => filled($clientKey),
            'hasClientSecret' => filled($clientSecret),
            'hasAccessToken' => filled($accessToken) || $this->tiktok->isAuthenticated(),
            'hasRefreshToken' => filled($refreshToken),
            'hasShopId' => filled($shopId),
            'maskedClientKey' => $this->maskCredential($clientKey, 4, 4),
            'maskedClientSecret' => $this->maskCredential($clientSecret, 2, 2),
            'maskedAccessToken' => $this->maskCredential($accessToken !== '' ? $accessToken : 'cached', 4, 4),
            'maskedShopId' => $this->maskCredential($shopId, 4, 4),
            'redirectUri' => $redirectUri,
            'authorizeUrl' => $hasAppCreds ? route('tiktok2.oauth.connect') : null,
            'exchangeUrl' => route('tiktok2.oauth.exchange'),
            'flashSuccess' => $request->session()->pull('tiktok2_connect_success'),
            'flashError' => $request->session()->pull('tiktok2_connect_error'),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'No TikTok 2 access token. Use Connect with TikTok 2 (OAuth) first.',
            ], 400);
        }

        try {
            $shopInfo = $this->tiktok->getShopInfo();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ], 422);
        }

        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        $shop = is_array($shops) && ! empty($shops[0]) ? $shops[0] : null;

        if ($shop) {
            return response()->json([
                'success' => true,
                'message' => 'TikTok Shop 2 API working — '.($shop['name'] ?? 'shop').' (ID: '.($shop['id'] ?? 'n/a').')',
                'shop' => [
                    'id' => $shop['id'] ?? null,
                    'name' => $shop['name'] ?? null,
                    'region' => $shop['region'] ?? ($shop['shop_region'] ?? null),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => is_array($shopInfo)
                ? ('code='.($shopInfo['code'] ?? '?').' '.($shopInfo['message'] ?? 'No shops'))
                : 'Shop info call failed.',
        ], 422);
    }

    public function refreshProducts(): JsonResponse
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok 2 is not connected. Authorize via Connect first.',
                'connect_url' => route('marketplace.manager.tiktok2.connect'),
            ], 401);
        }

        @set_time_limit(0);

        try {
            $exit = Artisan::call('sync:tiktok-api-data', [
                '--channel' => 'tiktok2',
                '--products-only' => true,
            ]);
            $output = trim(Artisan::output());
            $count = Schema::hasTable('tiktok_products_two')
                ? (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0;

            if ($exit !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'TikTok 2 product sync failed.',
                    'output' => $output,
                    'count' => $count,
                ], 500);
            }

            WarmTikTok2LiveListingsCache::dispatch();

            return response()->json([
                'success' => true,
                'done' => true,
                'message' => "TikTok 2 products synced ({$count} SKUs).",
                'count' => $count,
                'total_upserted' => $count,
                'page' => 1,
                'total_page' => 1,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok 2 MM refreshProducts failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'done' => true,
                'message' => 'Product sync error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok 2 is not connected. Authorize via Connect first.',
                'connect_url' => route('marketplace.manager.tiktok2.connect'),
            ], 401);
        }

        @set_time_limit(0);

        $days = max(1, min(90, (int) $request->input('days', 60)));
        $import = filter_var($request->input('import', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $service = app(TikTok2OrderSyncService::class);
            $from = now()->subDays($days)->toDateString();
            $result = $service->sync($from, $import);

            $count = Schema::hasTable('tiktok2_orders')
                ? (int) Tiktok2Order::query()->count()
                : 0;

            return response()->json([
                'success' => ! empty($result['success']),
                'message' => $result['message'] ?? "TikTok 2 orders fetched (last {$days} days). Rows in DB: {$count}.",
                'count' => $count,
                'days' => $days,
                'upserted' => $result['upserted'] ?? 0,
            ], empty($result['success']) ? 500 : 200);
        } catch (\Throwable $e) {
            Log::error('TikTok 2 MM fetchOrders failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Order sync error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $count = Schema::hasTable('tiktok_products_two')
            ? (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
            : 0;

        return response()->json([
            'success' => true,
            'progress' => [
                'status' => 'idle',
                'message' => "{$count} SKUs in tiktok_products_two",
                'count' => $count,
            ],
        ]);
    }

    public function syncProducts(Request $request): View
    {
        return TikTokListingsPageBuilder::for('tiktok2')->syncProducts($request);
    }

    public function showProduct(int $shopifySkuId): View
    {
        return TikTokListingsPageBuilder::for('tiktok2')->showProduct($shopifySkuId);
    }

    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        $result = TikTokListingsPageBuilder::for('tiktok2')->pushProductInventory($shopifySkuId);
        $ok = ! empty($result['success']);

        return response()->json($result, $ok ? 200 : 422);
    }

    public function syncOrders(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $apiError = null;

        if (! Schema::hasTable('tiktok2_orders')) {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Table tiktok2_orders missing. Run migrations, then Fetch orders.';
        } else {
            $q = Tiktok2Order::query()->orderByDesc('order_created_at')->orderByDesc('id');
            if ($search !== '') {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_id', 'like', '%'.$search.'%')
                        ->orWhere('seller_sku', 'like', '%'.$search.'%')
                        ->orWhere('product_name', 'like', '%'.$search.'%');
                });
            }
            $orders = $q->paginate(50)->withQueryString();
        }

        return view('marketplace.tiktok2.orders', [
            'orders' => $orders,
            'title' => 'TikTok 2 — Orders',
            'search' => $search,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('tiktok2') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = Tiktok2Order::query()->findOrFail($id);
        $lines = Tiktok2Order::query()
            ->where('order_id', $line->order_id)
            ->orderBy('id')
            ->get();

        return view('marketplace.tiktok2.order-show', [
            'title' => 'TikTok 2 — Order '.$line->order_id,
            'line' => $line,
            'lines' => $lines,
            'connected' => $this->apiConfig->isConfigured('tiktok2') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.tiktok2.settings', [
            'settings' => MarketplaceSyncSettings::getFor('tiktok2'),
            'title' => 'TikTok 2 — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('tiktok2') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('tiktok2');

        $pricing = $this->mergeSettingsSection($current['pricing'] ?? [], $request->input('pricing', []), [
            'price_sync', 'use_sale_price', 'currency_conversion',
        ]);
        $inventory = $this->mergeSettingsSection($current['inventory'] ?? [], $request->input('inventory', []), [
            'inventory_sync',
        ]);
        $inventory['min_quantity'] = 0;
        if ($request->has('inventory.quantity_calc_percent')) {
            $inventory['quantity_calc_percent'] = max(0, min(100, (int) $request->input('inventory.quantity_calc_percent')));
        }
        $inventory['max_quantity'] = null;
        $order = $this->mergeSettingsSection($current['order'] ?? [], $request->input('order', []), [
            'fetch_orders', 'auto_import_to_shopify', 'import_paid_orders_only',
            'keep_order_number_from_channel', 'push_tracking_to_tiktok2', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_tiktok2', 'sync_title', 'sync_images',
        ]);

        if ($request->has('order.shopify_order_tags')) {
            $tags = $request->input('order.shopify_order_tags');
            $order['shopify_order_tags'] = is_array($tags)
                ? $tags
                : array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
        }
        if ($request->filled('order.shopify_store')) {
            $store = (string) $request->input('order.shopify_store');
            if (in_array($store, ['main', '5core', 'business', 'prolightsounds'], true)) {
                $order['shopify_store'] = $store;
            }
        }
        $order['shopify_source_name'] = trim((string) $request->input('order.shopify_source_name', $order['shopify_source_name'] ?? 'tiktok2'));
        $order['shopify_source_display_name'] = trim((string) $request->input('order.shopify_source_display_name', $order['shopify_source_display_name'] ?? 'TikTok 2'));

        MarketplaceSyncSettings::setFor('tiktok2', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'TikTok 2 sync settings saved.']);
    }

    public function syncInventoryNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok2');
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory sync is disabled in TikTok 2 settings. Enable it first.',
            ], 422);
        }

        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok 2 is not connected.',
            ], 401);
        }

        RunMarketplaceInventorySyncJob::dispatch('tiktok2');

        return response()->json([
            'success' => true,
            'message' => 'Inventory sync job queued (Shopify → TikTok 2). Check back shortly.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        $result = TikTokListingsPageBuilder::for('tiktok2')->syncMismatchInventoryNow($request);
        $status = (! empty($result['success'])) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function syncTrackingNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok2');
        if (! ($settings['order']['push_tracking_to_tiktok2'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking push is disabled in TikTok 2 settings. Enable it first.',
            ], 422);
        }

        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok 2 is not connected.',
            ], 401);
        }

        SyncTikTok2TrackingJob::dispatch(false, 40);

        return response()->json([
            'success' => true,
            'message' => 'Tracking sync job queued (Shopify → TikTok 2). Check back shortly.',
        ]);
    }

    public function pushOrderToShopify(Request $request, int $id): JsonResponse
    {
        $order = Tiktok2Order::findOrFail($id);

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported to Shopify.',
                'shopify_order_id' => (string) $order->shopify_order_id,
            ]);
        }

        $pushService = app(\App\Services\MarketplaceManager\TikTok2OrderPushService::class);
        $shopifyOrderId = $pushService->importToShopify($order);

        if ($shopifyOrderId) {
            return response()->json([
                'success' => true,
                'message' => "Imported to Shopify (order #{$shopifyOrderId}).",
                'shopify_order_id' => $shopifyOrderId,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $pushService->lastFailureReason ?: 'Failed to push order to Shopify.',
        ], 422);
    }

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

    protected function maskCredential(string $value, int $showStart = 3, int $showEnd = 4): string
    {
        $value = trim($value);
        if ($value === '' || $value === 'cached') {
            return $value === 'cached' ? '(from cache)' : '—';
        }
        $len = strlen($value);
        if ($len <= $showStart + $showEnd) {
            return str_repeat('•', $len);
        }

        return substr($value, 0, $showStart)
            .str_repeat('•', min(12, $len - $showStart - $showEnd))
            .substr($value, -$showEnd);
    }
}
