<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\RunMarketplaceInventorySyncJob;
use App\Jobs\SyncTikTokTrackingJob;
use App\Models\MarketplaceSyncSettings;
use App\Models\TikTokProduct;
use App\Models\TiktokOrder;
use App\Services\MarketplaceManager\TikTokOrderSyncService;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\TikTokShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TikTokSyncController extends Controller
{
    public function __construct(
        protected TikTokShopService $tiktok,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $clientKey = (string) config('services.tiktok.client_key', '');
        $clientSecret = (string) config('services.tiktok.client_secret', '');
        $accessToken = (string) (config('services.tiktok.access_token') ?: '');
        $refreshToken = (string) (config('services.tiktok.refresh_token') ?: '');
        $shopId = (string) config('services.tiktok.shop_id', '');
        $redirectUri = (string) config('services.tiktok.redirect_uri', '');
        $hasAppCreds = filled($clientKey) && filled($clientSecret);
        $connected = $this->apiConfig->isConfigured('tiktok') && $this->tiktok->isAuthenticated();

        return view('marketplace.tiktok.connect', [
            'title' => 'TikTok Shop — Connect',
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
            'authorizeUrl' => $hasAppCreds ? route('tiktok.oauth.connect') : null,
            'exchangeUrl' => route('tiktok.oauth.exchange'),
            'flashSuccess' => $request->session()->pull('tiktok_connect_success'),
            'flashError' => $request->session()->pull('tiktok_connect_error'),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'No TikTok Shop access token. Use Connect with TikTok Shop (OAuth) first.',
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
                'message' => 'TikTok Shop API working — '.($shop['name'] ?? 'shop').' (ID: '.($shop['id'] ?? 'n/a').')',
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
                'message' => 'TikTok Shop is not connected. Authorize via Connect first.',
                'connect_url' => route('marketplace.manager.tiktok.connect'),
            ], 401);
        }

        @set_time_limit(0);

        try {
            $exit = Artisan::call('sync:tiktok-api-data', ['--channel' => 'tiktok']);
            $output = trim(Artisan::output());
            $count = Schema::hasTable('tiktok_products')
                ? (int) TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0;

            if ($exit !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'TikTok Shop product sync failed.',
                    'output' => $output,
                    'count' => $count,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "TikTok Shop products synced ({$count} SKUs).",
                'count' => $count,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok Shop MM refreshProducts failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Product sync error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok Shop is not connected. Authorize via Connect first.',
                'connect_url' => route('marketplace.manager.tiktok.connect'),
            ], 401);
        }

        @set_time_limit(0);

        $days = max(1, min(90, (int) $request->input('days', 60)));
        $import = filter_var($request->input('import', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $service = app(TikTokOrderSyncService::class);
            $from = now()->subDays($days)->toDateString();
            $result = $service->sync($from, $import);

            $count = Schema::hasTable('tiktok_orders')
                ? (int) TiktokOrder::query()->count()
                : 0;

            return response()->json([
                'success' => ! empty($result['success']),
                'message' => $result['message'] ?? "TikTok Shop orders fetched (last {$days} days). Rows in DB: {$count}.",
                'count' => $count,
                'days' => $days,
                'upserted' => $result['upserted'] ?? 0,
            ], empty($result['success']) ? 500 : 200);
        } catch (\Throwable $e) {
            Log::error('TikTok Shop MM fetchOrders failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Order sync error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $count = Schema::hasTable('tiktok_products')
            ? (int) TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
            : 0;

        return response()->json([
            'success' => true,
            'progress' => [
                'status' => 'idle',
                'message' => "{$count} SKUs in tiktok_products",
                'count' => $count,
            ],
        ]);
    }

    public function syncProducts(Request $request): View
    {
        $searchSku = trim((string) $request->input('search_sku', ''));
        $searchName = trim((string) $request->input('search_name', ''));
        $apiError = null;

        if (! Schema::hasTable('tiktok_products')) {
            $products = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Table tiktok_products missing. Run migrations, then Sync products.';
        } else {
            $q = TikTokProduct::query()->orderByDesc('updated_at')->orderByDesc('id');
            if ($searchSku !== '') {
                $q->where('sku', 'like', '%'.$searchSku.'%');
            }
            if ($searchName !== '') {
                $q->where(function ($inner) use ($searchName) {
                    $inner->where('sku', 'like', '%'.$searchName.'%')
                        ->orWhere('product_id', 'like', '%'.$searchName.'%');
                });
            }
            $products = $q->paginate(50)->withQueryString();
        }

        return view('marketplace.tiktok.products', [
            'products' => $products,
            'title' => 'TikTok Shop — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('tiktok') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $apiError = null;

        if (! Schema::hasTable('tiktok_orders')) {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Table tiktok_orders missing. Run migrations, then Fetch orders.';
        } else {
            $q = TiktokOrder::query()->orderByDesc('order_created_at')->orderByDesc('id');
            if ($search !== '') {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_id', 'like', '%'.$search.'%')
                        ->orWhere('seller_sku', 'like', '%'.$search.'%')
                        ->orWhere('product_name', 'like', '%'.$search.'%');
                });
            }
            $orders = $q->paginate(50)->withQueryString();
        }

        return view('marketplace.tiktok.orders', [
            'orders' => $orders,
            'title' => 'TikTok Shop — Orders',
            'search' => $search,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('tiktok') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = TiktokOrder::query()->findOrFail($id);
        $lines = TiktokOrder::query()
            ->where('order_id', $line->order_id)
            ->orderBy('id')
            ->get();

        return view('marketplace.tiktok.order-show', [
            'title' => 'TikTok Shop — Order '.$line->order_id,
            'line' => $line,
            'lines' => $lines,
            'connected' => $this->apiConfig->isConfigured('tiktok') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.tiktok.settings', [
            'settings' => MarketplaceSyncSettings::getFor('tiktok'),
            'title' => 'TikTok Shop — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('tiktok') && $this->tiktok->isAuthenticated(),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('tiktok');

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
            'keep_order_number_from_channel', 'push_tracking_to_tiktok', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_tiktok', 'sync_title', 'sync_images',
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
        $order['shopify_source_name'] = trim((string) $request->input('order.shopify_source_name', $order['shopify_source_name'] ?? 'tiktok'));
        $order['shopify_source_display_name'] = trim((string) $request->input('order.shopify_source_display_name', $order['shopify_source_display_name'] ?? 'TikTok Shop'));

        MarketplaceSyncSettings::setFor('tiktok', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'TikTok Shop sync settings saved.']);
    }

    public function syncInventoryNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory sync is disabled in TikTok Shop settings. Enable it first.',
            ], 422);
        }

        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok Shop is not connected.',
            ], 401);
        }

        RunMarketplaceInventorySyncJob::dispatch('tiktok');

        return response()->json([
            'success' => true,
            'message' => 'Inventory sync job queued (Shopify → TikTok Shop). Check back shortly.',
        ]);
    }

    public function syncMismatchInventoryNow(): JsonResponse
    {
        return $this->syncInventoryNow();
    }

    public function syncTrackingNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        if (! ($settings['order']['push_tracking_to_tiktok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking push is disabled in TikTok Shop settings. Enable it first.',
            ], 422);
        }

        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'TikTok Shop is not connected.',
            ], 401);
        }

        SyncTikTokTrackingJob::dispatch(false, 40);

        return response()->json([
            'success' => true,
            'message' => 'Tracking sync job queued (Shopify → TikTok Shop). Check back shortly.',
        ]);
    }

    public function pushOrderToShopify(Request $request, int $id): JsonResponse
    {
        $order = TiktokOrder::findOrFail($id);

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported to Shopify.',
                'shopify_order_id' => (string) $order->shopify_order_id,
            ]);
        }

        $pushService = app(\App\Services\MarketplaceManager\TikTokOrderPushService::class);
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
