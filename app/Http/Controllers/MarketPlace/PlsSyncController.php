<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceSyncSettings;
use App\Models\PLSProduct;
use App\Models\PlsSale;
use App\Services\ShopifyPlsTokenService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PlsSyncController extends Controller
{
    public function __construct(
        protected ShopifyPlsTokenService $tokenService,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $domain = (string) ($this->tokenService->getDomain() ?? '');
        $clientId = (string) (config('services.prolightsounds.client_id') ?: config('services.prolightsounds.api_key') ?: '');
        $clientSecret = (string) (config('services.prolightsounds.client_secret') ?: '');
        $staticToken = (string) (config('services.prolightsounds.access_token') ?: config('services.prolightsounds.password') ?: '');
        $hasClientCreds = filled($clientId) && filled($clientSecret);
        $connected = $this->apiConfig->isConfigured('pls') || $this->tokenService->isConfigured();

        return view('marketplace.pls.connect', [
            'title' => 'Shopify PLS — Connect',
            'connected' => $connected,
            'credentialsReady' => filled($domain) && ($hasClientCreds || filled($staticToken)),
            'hasDomain' => filled($domain),
            'hasClientId' => filled($clientId),
            'hasClientSecret' => filled($clientSecret),
            'hasStaticToken' => filled($staticToken),
            'hasClientCreds' => $hasClientCreds,
            'domain' => $domain,
            'maskedClientId' => $this->maskCredential($clientId, 4, 4),
            'maskedClientSecret' => $this->maskCredential($clientSecret, 2, 2),
            'maskedStaticToken' => $this->maskCredential($staticToken, 4, 4),
            'flashSuccess' => $request->session()->pull('pls_connect_success'),
            'flashError' => $request->session()->pull('pls_connect_error'),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        $domain = $this->tokenService->getDomain();
        $token = $this->tokenService->getAccessToken();

        if (! $domain || ! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify PLS credentials missing. Set PROLIGHTSOUNDS_SHOPIFY_DOMAIN plus client id/secret (or access token) in .env.',
            ], 400);
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$domain}/admin/api/2024-01/shop.json");
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ], 422);
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify PLS API error HTTP '.$response->status().' — try Refresh token, then Test again.',
            ], 422);
        }

        $shop = $response->json('shop') ?? [];

        return response()->json([
            'success' => true,
            'message' => 'Shopify PLS API working — '.($shop['name'] ?? $domain)
                .(isset($shop['myshopify_domain']) ? ' ('.$shop['myshopify_domain'].')' : ''),
            'shop' => [
                'name' => $shop['name'] ?? null,
                'domain' => $shop['myshopify_domain'] ?? $domain,
                'id' => $shop['id'] ?? null,
            ],
        ]);
    }

    public function refreshToken(): JsonResponse
    {
        $domain = $this->tokenService->getDomain();
        $clientId = config('services.prolightsounds.client_id') ?: config('services.prolightsounds.api_key');
        $clientSecret = config('services.prolightsounds.client_secret');

        if (! $domain || ! filled($clientId) || ! filled($clientSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Need PROLIGHTSOUNDS_SHOPIFY_DOMAIN, CLIENT_ID, and CLIENT_SECRET for client_credentials refresh.',
            ], 400);
        }

        $this->tokenService->clearCache();
        $token = $this->tokenService->getAccessToken(true);

        if (! is_string($token) || $token === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed. Check client id/secret against the PLS Shopify app.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shopify PLS access token refreshed and cached (~24h).',
            'masked_token' => $this->maskCredential($token, 4, 4),
        ]);
    }

    /**
     * Sync PLS Shopify catalog → shopify_catalog_* (store=pls).
     */
    public function refreshProducts(): JsonResponse
    {
        if (! $this->tokenService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify PLS is not connected. Set credentials / refresh token first.',
                'connect_url' => route('marketplace.manager.pls.connect'),
            ], 401);
        }

        @set_time_limit(0);

        try {
            $exit = Artisan::call('shopify-pls:sync');
            $output = trim(Artisan::output());
            $variantCount = Schema::hasTable('shopify_catalog_variants')
                ? (int) DB::table('shopify_catalog_variants')->where('store', 'pls')->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0;

            if ($exit !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'PLS catalog sync failed.',
                    'output' => $output,
                    'count' => $variantCount,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "PLS catalog synced ({$variantCount} variant rows with SKU).",
                'count' => $variantCount,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('PLS MM refreshProducts failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Catalog sync error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rebuild pls_products pricing table from catalog + Shopify orders (L30/L60).
     */
    public function refreshPricing(): JsonResponse
    {
        if (! $this->tokenService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify PLS is not connected. Set credentials / refresh token first.',
                'connect_url' => route('marketplace.manager.pls.connect'),
            ], 401);
        }

        @set_time_limit(0);

        try {
            $exit = Artisan::call('app:fetch-pls-data');
            $output = trim(Artisan::output());
            $count = Schema::hasTable('pls_products')
                ? (int) PLSProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0;

            if ($exit !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'PLS pricing refresh failed. Run catalog sync first if shopify_catalog_variants (store=pls) is empty.',
                    'output' => $output,
                    'count' => $count,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "PLS pricing data refreshed ({$count} rows in pls_products).",
                'count' => $count,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('PLS MM refreshPricing failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Pricing refresh error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        if (! $this->tokenService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify PLS is not connected. Set credentials / refresh token first.',
                'connect_url' => route('marketplace.manager.pls.connect'),
            ], 401);
        }

        @set_time_limit(0);

        $days = max(1, min(365, (int) $request->input('days', 90)));

        try {
            $exit = Artisan::call('app:fetch-pls-sales-data', ['--days' => $days]);
            $output = trim(Artisan::output());
            $count = Schema::hasTable('pls_sales')
                ? (int) DB::table('pls_sales')->count()
                : 0;

            if ($exit !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'PLS sales sync failed.',
                    'output' => $output,
                    'count' => $count,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "PLS sales fetched (last {$days} days). Rows in pls_sales: {$count}.",
                'count' => $count,
                'days' => $days,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('PLS MM fetchOrders failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Sales sync error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $count = Schema::hasTable('pls_products')
            ? (int) PLSProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
            : 0;

        return response()->json([
            'success' => true,
            'progress' => [
                'status' => 'idle',
                'message' => "{$count} SKUs in pls_products",
                'count' => $count,
            ],
        ]);
    }

    public function syncProducts(Request $request): View
    {
        $searchSku = trim((string) $request->input('search_sku', ''));
        $apiError = null;

        if (! Schema::hasTable('pls_products')) {
            $products = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Table pls_products missing. Run Sync catalog + Refresh pricing.';
        } else {
            $q = PLSProduct::query()->orderByDesc('updated_at')->orderByDesc('id');
            if ($searchSku !== '') {
                $q->where('sku', 'like', '%'.$searchSku.'%');
            }
            $products = $q->paginate(50)->withQueryString();
        }

        $catalogCount = Schema::hasTable('shopify_catalog_variants')
            ? (int) DB::table('shopify_catalog_variants')->where('store', 'pls')->whereNotNull('sku')->where('sku', '!=', '')->count()
            : 0;

        return view('marketplace.pls.products', [
            'products' => $products,
            'title' => 'Shopify PLS — Listings',
            'searchSku' => $searchSku,
            'apiError' => $apiError,
            'catalogCount' => $catalogCount,
            'connected' => $this->apiConfig->isConfigured('pls') || $this->tokenService->isConfigured(),
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $apiError = null;

        if (! Schema::hasTable('pls_sales')) {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Table pls_sales missing. Run migrations, then Fetch sales.';
        } else {
            $q = PlsSale::query()->orderByDesc('order_date')->orderByDesc('id');
            if ($search !== '') {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_name', 'like', '%'.$search.'%')
                        ->orWhere('order_number', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('product_title', 'like', '%'.$search.'%');
                });
            }
            $orders = $q->paginate(50)->withQueryString();
        }

        return view('marketplace.pls.orders', [
            'orders' => $orders,
            'title' => 'Shopify PLS — Orders / Sales',
            'search' => $search,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('pls') || $this->tokenService->isConfigured(),
        ]);
    }

    public function showOrder(int $id): View
    {
        $line = PlsSale::query()->findOrFail($id);
        $lines = PlsSale::query()
            ->where('shopify_order_id', $line->shopify_order_id)
            ->orderBy('id')
            ->get();

        return view('marketplace.pls.order-show', [
            'title' => 'Shopify PLS — Order '.($line->order_name ?: $line->order_number),
            'line' => $line,
            'lines' => $lines,
            'connected' => $this->apiConfig->isConfigured('pls') || $this->tokenService->isConfigured(),
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.pls.settings', [
            'settings' => MarketplaceSyncSettings::getFor('pls'),
            'title' => 'Shopify PLS — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('pls') || $this->tokenService->isConfigured(),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('pls');

        $pricing = $this->mergeSettingsSection($current['pricing'] ?? [], $request->input('pricing', []), [
            'price_sync', 'use_sale_price', 'currency_conversion',
        ]);
        $inventory = $this->mergeSettingsSection($current['inventory'] ?? [], $request->input('inventory', []), [
            'inventory_sync',
        ]);
        $inventory['min_quantity'] = 0;
        $order = $this->mergeSettingsSection($current['order'] ?? [], $request->input('order', []), [
            'fetch_orders', 'auto_import_to_shopify', 'import_paid_orders_only',
            'keep_order_number_from_channel', 'push_tracking_to_pls', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_pls', 'sync_title', 'sync_images',
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
        $order['shopify_source_name'] = trim((string) $request->input('order.shopify_source_name', $order['shopify_source_name'] ?? 'pls'));
        $order['shopify_source_display_name'] = trim((string) $request->input('order.shopify_source_display_name', $order['shopify_source_display_name'] ?? 'Shopify PLS'));

        MarketplaceSyncSettings::setFor('pls', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Shopify PLS sync settings saved.']);
    }

    public function syncInventoryNow(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'B2C → PLS inventory push is not implemented in Marketplace Manager. PLS is itself a Shopify store — use Sync catalog / Refresh pricing to pull data.',
        ], 422);
    }

    public function syncMismatchInventoryNow(): JsonResponse
    {
        return $this->syncInventoryNow();
    }

    public function syncTrackingNow(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Tracking push for PLS is not implemented in Marketplace Manager. Fetch sales still works from the Orders page.',
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
}
