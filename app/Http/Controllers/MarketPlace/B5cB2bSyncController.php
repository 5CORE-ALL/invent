<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\RunMarketplaceInventorySyncJob;
use App\Jobs\SyncMarketplaceMismatchInventoryJob;
use App\Jobs\SyncMarketplaceOrdersJob;
use App\Models\B5cB2bOrder;
use App\Models\B5cB2bProduct;
use App\Models\MarketplaceSyncSettings;
use App\Services\Business5CoreB2bApiService;
use App\Services\MarketplaceManager\B5cB2bLiveListingsService;
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
            'message' => 'Business 5 Core B2B order fetch queued.',
        ]);
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

    public function syncMismatchInventoryNow(): JsonResponse
    {
        SyncMarketplaceMismatchInventoryJob::dispatch('b5cb2b');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Business 5 Core B2B mismatch inventory sync queued.',
        ]);
    }

    public function syncTrackingNow(): JsonResponse
    {
        $result = app(B5cB2bTrackingSyncService::class)->syncFromShopify(40);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function syncProducts(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $query = B5cB2bProduct::query()->orderBy('sku');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%');
            });
        }
        $products = Schema::hasTable('b5c_b2b_products')
            ? $query->paginate(50)->appends($request->query())
            : new LengthAwarePaginator([], 0, 50);

        return view('marketplace.b5cb2b.products', [
            'title' => 'Business 5 Core (B2B) — Listings',
            'products' => $products,
            'search' => $search,
            'connected' => $this->apiConfig->isConfigured('b5cb2b'),
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $query = B5cB2bOrder::query()->orderByDesc('store_order_id');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('store_order_id', $search)
                    ->orWhere('customer_email', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%');
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
        $row = B5cB2bProduct::query()->find($shopifySku)
            ?: B5cB2bProduct::query()->where('listing_id', $shopifySku)->first();
        abort_if(! $row, 404);

        return view('marketplace.b5cb2b.product-show', [
            'title' => 'B5C B2B — '.$row->sku,
            'product' => $row,
        ]);
    }

    public function showOrder(int $order): View
    {
        $row = B5cB2bOrder::query()->where('store_order_id', $order)->first()
            ?: B5cB2bOrder::query()->find($order);
        abort_if(! $row, 404);

        return view('marketplace.b5cb2b.order-show', [
            'title' => 'B5C B2B Order #'.$row->store_order_id,
            'order' => $row,
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
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('b5cb2b');
        $inventory = $current['inventory'] ?? [];
        $inventory['inventory_sync'] = $request->boolean('inventory.inventory_sync');
        $inventory['quantity_calc_percent'] = max(0, min(100, (int) $request->input(
            'inventory.quantity_calc_percent',
            $inventory['quantity_calc_percent'] ?? 100
        )));
        $order = $current['order'] ?? [];
        $order['fetch_orders'] = $request->boolean('order.fetch_orders');
        $order['push_tracking_to_b5cb2b'] = $request->boolean('order.push_tracking_to_b5cb2b');

        MarketplaceSyncSettings::setFor('b5cb2b', [
            'pricing' => $current['pricing'] ?? [],
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $current['listings'] ?? [],
        ]);

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
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
