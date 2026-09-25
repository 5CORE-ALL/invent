<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\RunMarketplaceInventorySyncJob;
use App\Jobs\SyncMarketplaceOrdersJob;
use App\Models\B5cB2bOrder;
use App\Models\B5cB2bProduct;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use App\Services\MarketplaceManager\B5cB2bListingsPageBuilder;
use App\Services\MarketplaceManager\B5cB2bLiveListingsService;
use App\Services\MarketplaceManager\B5cB2bOrderPushService;
use App\Services\MarketplaceManager\B5cB2bOrderSyncService;
use App\Services\MarketplaceManager\B5cB2bTrackingSyncService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class B5cB2bSyncController extends Controller
{
    public function __construct(
        protected Business5CoreB2bApiService $api,
        protected MarketplaceApiConfigService $apiConfig
    ) {
    }

    public function connect(): View
    {
        $url = (string) config('services.b5cb2b.url');
        $key = (string) config('services.b5cb2b.api_key');

        return view('marketplace.b5cb2b.connect', [
            'title' => 'Business 5 Core (B2B) — Connect',
            'connected' => $this->apiConfig->isConfigured('b5cb2b'),
            'credentialsReady' => $this->api->isConfigured(),
            'hasUrl' => filled($url),
            'hasKey' => filled($key),
            'maskedKey' => $this->maskCredential($key),
            'apiBase' => $url,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->api->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Set BUSINESS5CORE_B2B_API_URL and BUSINESS5CORE_B2B_API_KEY in .env.',
            ], 422);
        }

        try {
            $ping = $this->api->ping();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connected to '.((string) ($ping['name'] ?? 'Business 5 Core Laravel')).'.',
            'body' => $ping,
        ]);
    }

    public function refreshProducts(): JsonResponse
    {
        $result = app(B5cB2bLiveListingsService::class)->refresh();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function refreshProductsStatus(): JsonResponse
    {
        $count = Schema::hasTable('b5c_b2b_products') ? B5cB2bProduct::query()->count() : 0;

        return response()->json(['success' => true, 'count' => $count]);
    }

    public function fetchOrders(): JsonResponse
    {
        SyncMarketplaceOrdersJob::dispatch('b5cb2b', now()->subDays(14)->toDateString(), true, 14);

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Business 5 Core order fetch queued. New orders import to Shopify as B5-0009.',
        ]);
    }

    public function pushUnlinkedToShopify(): JsonResponse
    {
        $sync = app(B5cB2bOrderSyncService::class);
        $inline = $sync->importUnlinkedInline(4);
        $queued = $sync->dispatchImportsForNewOrders(true);
        $message = (string) ($inline['message'] ?? '');
        if ($queued > 0) {
            $message .= ($message !== '' ? ' ' : '')."Queued {$queued} more.";
        }

        return response()->json([
            'success' => ($inline['failed'] ?? 0) === 0,
            'imported' => $inline['imported'] ?? 0,
            'failed' => $inline['failed'] ?? 0,
            'queued' => $queued,
            'message' => $message,
        ], ($inline['failed'] ?? 0) > 0 && ($inline['imported'] ?? 0) === 0 ? 422 : 200);
    }

    public function pushOrderToShopify(Request $request): JsonResponse
    {
        $id = (int) $request->input('order_id', $request->input('id', 0));
        $row = B5cB2bOrder::query()->where('store_order_id', $id)->first()
            ?: ($id > 0 ? B5cB2bOrder::query()->find($id) : null);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $push = app(B5cB2bOrderPushService::class);
        $shopifyId = $push->importToShopify($row);
        if (! $shopifyId) {
            return response()->json([
                'success' => false,
                'message' => $push->lastFailureReason ?: 'Shopify import failed.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'shopify_order_id' => $shopifyId,
            'order_number' => $row->channelOrderNumber(),
            'message' => $row->channelOrderNumber().' linked to Shopify order '.$shopifyId.'.',
        ]);
    }

    public function renameShopifyTag(Request $request): JsonResponse
    {
        $id = (int) $request->input('order_id', $request->input('id', 0));
        $row = B5cB2bOrder::query()->where('store_order_id', $id)->first()
            ?: ($id > 0 ? B5cB2bOrder::query()->find($id) : null);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $shopifyId = trim((string) ($row->shopify_order_id ?? ''));
        if ($shopifyId === '') {
            return response()->json(['success' => false, 'message' => 'Order is not in Shopify yet.'], 422);
        }

        $result = app(B5cB2bOrderPushService::class)->renameShopifyTag($shopifyId);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function syncInventoryNow(): JsonResponse
    {
        $settings = MarketplaceSyncSettings::getFor('b5cb2b');
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory sync is disabled in Business 5 Core (B2B) settings. Enable it first.',
            ], 422);
        }

        RunMarketplaceInventorySyncJob::dispatch('b5cb2b');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Business 5 Core B2B inventory sync queued.',
        ]);
    }

    public function syncMismatchInventoryNow(Request $request): JsonResponse
    {
        try {
            $result = app(B5cB2bListingsPageBuilder::class)->syncMismatchInventoryNow($request);

            return response()->json($result, ! empty($result['success']) ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mismatch sync failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncTrackingNow(): JsonResponse
    {
        $result = app(B5cB2bTrackingSyncService::class)->syncFromShopify(40);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function syncProducts(Request $request): View
    {
        return app(B5cB2bListingsPageBuilder::class)->syncProducts($request);
    }

    public function pushProductInventory(int $shopifySkuId): JsonResponse
    {
        $result = app(B5cB2bListingsPageBuilder::class)->pushProductInventory($shopifySkuId);

        return response()->json($result, ! empty($result['success']) ? 200 : 422);
    }

    public function pullProductFromB5cB2b(int $shopifySkuId): JsonResponse
    {
        $result = app(B5cB2bListingsPageBuilder::class)->pullProductFromB5cB2b($shopifySkuId);

        return response()->json($result, ! empty($result['success']) ? 200 : 422);
    }

    public function syncOrders(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $query = B5cB2bOrder::query()->orderByDesc('store_order_id');
        if ($search !== '') {
            $storeId = B5cB2bOrder::storeIdFromSearch($search);
            $query->where(function ($q) use ($search, $storeId) {
                if ($storeId !== null) {
                    $q->where('store_order_id', $storeId);
                } else {
                    $q->whereRaw('1 = 0');
                }
                $q->orWhere('customer_email', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhere('shopify_order_id', 'like', '%'.$search.'%');
            });
        }
        $orders = Schema::hasTable('b5c_b2b_orders')
            ? $query->paginate(50)->appends($request->query())
            : new LengthAwarePaginator([], 0, 50);

        return view('marketplace.b5cb2b.orders', [
            'title' => 'Business 5 Core (B2B) — Orders',
            'orders' => $orders,
            'search' => $search,
            'connected' => $this->apiConfig->isConfigured('b5cb2b'),
        ]);
    }

    public function showProduct(int $shopifySku): View
    {
        return app(B5cB2bListingsPageBuilder::class)->showProduct($shopifySku);
    }

    public function showOrder(int $order): View
    {
        $row = B5cB2bOrder::query()->where('store_order_id', $order)->first()
            ?: B5cB2bOrder::query()->find($order);
        abort_if(! $row, 404);

        $pushError = null;
        if (trim((string) $row->shopify_order_id) === '') {
            $push = app(B5cB2bOrderPushService::class);
            $shopifyId = $push->importToShopify($row);
            $row->refresh();
            if (! $shopifyId) {
                $pushError = $push->lastFailureReason ?: 'Shopify import failed.';
            }
        }

        return view('marketplace.b5cb2b.order-show', [
            'title' => 'B5C B2B Order '.$row->channelOrderNumber(),
            'order' => $row,
            'pushError' => $pushError,
        ]);
    }

    public function syncSettings(Request $request): View
    {
        return $this->settings();
    }

    public function settings(): View
    {
        return view('marketplace.b5cb2b.settings', [
            'title' => 'Business 5 Core (B2B) — Settings',
            'settings' => MarketplaceSyncSettings::getFor('b5cb2b'),
            'connected' => $this->apiConfig->isConfigured('b5cb2b'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('b5cb2b');

        $pricing = $this->mergeSettingsSection($current['pricing'] ?? [], $request->input('pricing', []), [
            'price_sync', 'use_sale_price', 'currency_conversion',
        ]);
        $inventory = $this->mergeSettingsSection($current['inventory'] ?? [], $request->input('inventory', []), [
            'inventory_sync',
        ]);
        $inventory['min_quantity'] = 0;
        $order = $this->mergeSettingsSection($current['order'] ?? [], $request->input('order', []), [
            'fetch_orders', 'auto_import_to_shopify', 'import_paid_orders_only',
            'keep_order_number_from_channel', 'push_tracking_to_b5cb2b', 'sync_address_to_shopify',
        ]);
        $listings = $this->mergeSettingsSection($current['listings'] ?? [], $request->input('listings', []), [
            'auto_link_by_sku', 'create_products_on_b5cb2b', 'sync_title', 'sync_images',
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
        if ($request->filled('order.shopify_source_name')) {
            $order['shopify_source_name'] = trim((string) $request->input('order.shopify_source_name'));
        }
        if ($request->filled('order.shopify_source_display_name')) {
            $order['shopify_source_display_name'] = trim((string) $request->input('order.shopify_source_display_name'));
        }

        MarketplaceSyncSettings::setFor('b5cb2b', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'Business 5 Core (B2B) sync settings saved.']);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $input
     * @param  list<string>  $booleanKeys
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

    protected function maskCredential(string $value, int $showStart = 4, int $showEnd = 4): string
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
