<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\ImportAliexpressOrderToShopify;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\AliExpressApiService;
use App\Services\AliExpressAuthService;
use App\Services\MarketplaceManager\AliexpressInventorySyncService;
use App\Services\MarketplaceManager\AliexpressOrderSyncService;
use App\Services\ShopifyApiService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AliexpressSyncController extends Controller
{
    public function __construct(
        protected AliExpressApiService $aliExpressApi,
        protected AliExpressAuthService $aliExpressAuth,
        protected ShopifyApiService $shopifyApi,
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function connect(Request $request): View
    {
        $appKey = (string) config('services.aliexpress.app_key');
        $appSecret = (string) config('services.aliexpress.app_secret');
        $accessToken = (string) config('services.aliexpress.access_token');
        $refreshToken = (string) env('ALIEXPRESS_REFRESH_TOKEN', '');
        $apiBase = (string) config('services.aliexpress.api_base');
        $credentialsReady = filled($appKey) && filled($appSecret) && filled($accessToken);

        return view('marketplace.aliexpress.connect', [
            'title' => 'AliExpress — Connect',
            'connected' => $this->apiConfig->isConfigured('aliexpress'),
            'credentialsReady' => $credentialsReady,
            'authorizeUrl' => $this->aliExpressAuth->getAuthorizeUrl(),
            'hasAppKey' => filled($appKey),
            'hasAppSecret' => filled($appSecret),
            'hasToken' => filled($accessToken),
            'hasRefreshToken' => filled($refreshToken),
            'maskedAppKey' => $this->maskCredential($appKey),
            'maskedAppSecret' => $this->maskCredential($appSecret, 2, 2),
            'maskedAccessToken' => $this->maskCredential($accessToken, 4, 4),
            'apiBase' => $apiBase !== '' ? rtrim($apiBase, '/').(str_ends_with(strtolower($apiBase), '/sync') ? '' : '/sync') : 'https://api-sg.aliexpress.com/sync',
            'redirectUri' => (string) (config('services.aliexpress.redirect_uri') ?: config('app.url')),
            'gateway' => config('services.aliexpress.gateway', env('ALIEXPRESS_GATEWAY', 'sync')),
            'restBase' => config('services.aliexpress.rest_base', env('ALIEXPRESS_REST_BASE', 'https://api-sg.aliexpress.com/rest')),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('aliexpress')) {
            return response()->json([
                'success' => false,
                'message' => 'AliExpress API credentials missing. Set ALIEXPRESS_APP_KEY, ALIEXPRESS_APP_SECRET, and ALIEXPRESS_ACCESS_TOKEN in .env.',
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
                'message' => $result['message'] ?? 'Could not reach AliExpress API (network timeout).',
                'detail' => $result['detail'] ?? null,
                'tips' => [
                    'Your PC cannot open TCP to api-sg.aliexpress.com:443 — this is a network/firewall issue, not missing .env keys.',
                    'Try: mobile hotspot, VPN, disable antivirus HTTPS scanning, or test from your production server.',
                    'Whitelist your server public IP in the AliExpress app console (not only your home IP).',
                    'Optional .env: ALIEXPRESS_GATEWAY=sync, ALIEXPRESS_RESOLVE_IPV4=true, ALIEXPRESS_HTTP_PROXY=http://host:port',
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
            'message' => 'Connected successfully. AliExpress product list API responded.',
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
        $source = $request->input('source', 'db');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;

        if ($source === 'api') {
            [$products, $apiError] = $this->productsFromApi($page, $perPage, $searchSku, $searchName);
        } else {
            $products = $this->productsFromDatabase($page, $perPage, $searchSku, $searchName);
        }

        $skus = collect($products->items())->pluck('sku')->filter()->values()->all();
        $shopifyDetails = $skus ? $this->shopifyApi->getProductDetailsBySkuMap($skus) : [];
        $listingStatuses = $this->listingStatusMap($skus);

        $enriched = collect($products->items())->map(function ($row) use ($shopifyDetails, $listingStatuses) {
            $sku = $row->sku ?? '';
            $shopify = $shopifyDetails[$sku] ?? null;
            $listing = $listingStatuses[$sku] ?? null;
            $listed = is_array($listing) ? ($listing['listed'] ?? $listing['rl_nrl'] ?? null) : null;

            return (object) [
                'product_id' => $row->product_id ?? null,
                'sku' => $sku,
                'title' => $shopify['title'] ?? ($row->product_name ?? $row->title ?? $sku),
                'aliexpress_title' => $row->product_name ?? $row->title ?? null,
                'image_src' => $shopify['image_src'] ?? ($row->image_src ?? null),
                'price' => $row->price ?? null,
                'shopify_price' => $shopify['price'] ?? null,
                'quantity' => $row->quantity ?? $row->stock ?? null,
                'shopify_quantity' => $shopify['quantity'] ?? null,
                'linked' => $listed === 'listed' || $listed === 'RL' || ! empty($row->product_id),
                'listing_status' => $listed,
            ];
        });

        $products->setCollection($enriched);

        return view('marketplace.aliexpress.products', [
            'products' => $products,
            'title' => 'AliExpress — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'source' => $source,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('aliexpress'),
        ]);
    }

    public function refreshProducts(Request $request): JsonResponse
    {
        if (! $this->apiConfig->isConfigured('aliexpress')) {
            return response()->json(['success' => false, 'message' => 'AliExpress not connected.']);
        }

        $page = max(1, (int) $request->input('page', 1));
        $result = $this->aliExpressApi->getInventory($page, 50);

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to fetch products from AliExpress.',
            ]);
        }

        $upserted = 0;
        foreach ($result['data']['products'] ?? [] as $item) {
            foreach ($this->aliExpressApi->extractSkuRowsFromListItem($item) as $row) {
                if (! Schema::hasTable('aliexpress_metric')) {
                    break 2;
                }
                AliexpressMetric::updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'product_id' => $row['product_id'],
                        'product_name' => $row['product_name'],
                        'price' => $row['price'] ?? 0,
                    ]
                );
                $upserted++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$upserted} SKU row(s) from AliExpress API (page {$page}).",
            'upserted' => $upserted,
        ]);
    }

    public function syncOrders(Request $request): View
    {
        $apiError = null;

        if (Schema::hasTable('aliexpress_order_metrics')) {
            $orders = AliexpressOrderMetric::query()
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orders = new LengthAwarePaginator([], 0, 50, 1);
            $apiError = 'Run migrations: php artisan migrate';
        }

        return view('marketplace.aliexpress.orders', [
            'orders' => $orders,
            'title' => 'AliExpress — Orders',
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('aliexpress'),
        ]);
    }

    public function fetchOrders(Request $request): JsonResponse
    {
        $days = max(1, min(60, (int) $request->input('days', 7)));
        $result = app(AliexpressOrderSyncService::class)->fetchAndStore($days);

        if ($request->boolean('import')) {
            $dispatched = app(AliexpressOrderSyncService::class)->dispatchImportsForNewOrders();
            $result['message'] .= " Dispatched {$dispatched} import job(s).";
        }

        return response()->json([
            'success' => str_contains(strtolower($result['message']), 'missing') ? false : true,
            'message' => $result['message'],
            'fetched' => $result['fetched'] ?? 0,
            'stored' => $result['stored'] ?? 0,
        ]);
    }

    public function syncInventoryNow(): JsonResponse
    {
        $result = app(AliexpressInventorySyncService::class)->syncFromShopify(false);

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
        $order = AliexpressOrderMetric::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => true,
                'message' => 'Already imported.',
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        }

        ImportAliexpressOrderToShopify::dispatch($order->id);
        $order->update(['import_status' => 'queued']);

        return response()->json(['success' => true, 'message' => 'Import queued. Ensure queue worker is running.']);
    }

    public function syncSettings(Request $request): View
    {
        return view('marketplace.aliexpress.settings', [
            'settings' => MarketplaceSyncSettings::getFor('aliexpress'),
            'title' => 'AliExpress — Sync Settings',
            'connected' => $this->apiConfig->isConfigured('aliexpress'),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $current = MarketplaceSyncSettings::getFor('aliexpress');

        $pricing = array_merge($current['pricing'] ?? [], $request->input('pricing', []));
        $inventory = array_merge($current['inventory'] ?? [], $request->input('inventory', []));
        $order = array_merge($current['order'] ?? [], $request->input('order', []));
        $listings = array_merge($current['listings'] ?? [], $request->input('listings', []));

        foreach (['pricing', 'inventory', 'order', 'listings'] as $section) {
            foreach ($$section as $key => $value) {
                if (in_array($value, ['1', 'on', 'true'], true)) {
                    $$section[$key] = true;
                } elseif (in_array($value, ['0', 'off', 'false'], true)) {
                    $$section[$key] = false;
                }
            }
        }

        if ($request->has('order.shopify_order_tags')) {
            $tags = $request->input('order.shopify_order_tags');
            $order['shopify_order_tags'] = is_array($tags)
                ? $tags
                : array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
        }

        MarketplaceSyncSettings::setFor('aliexpress', [
            'pricing' => $pricing,
            'inventory' => $inventory,
            'order' => $order,
            'listings' => $listings,
        ]);

        return response()->json(['success' => true, 'message' => 'AliExpress sync settings saved.']);
    }

    protected function productsFromDatabase(int $page, int $perPage, string $searchSku, string $searchName): LengthAwarePaginator
    {
        if (! Schema::hasTable('aliexpress_metric')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = AliexpressMetric::query()->whereNotNull('sku');

        if ($searchSku !== '') {
            $query->where('sku', 'like', '%'.$searchSku.'%');
        }
        if ($searchName !== '') {
            $query->where(function ($q) use ($searchName) {
                $q->where('product_name', 'like', '%'.$searchName.'%')
                    ->orWhere('sku', 'like', '%'.$searchName.'%');
            });
        }

        return $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: string|null}
     */
    protected function productsFromApi(int $page, int $perPage, string $searchSku, string $searchName): array
    {
        if (! $this->apiConfig->isConfigured('aliexpress')) {
            return [new LengthAwarePaginator([], 0, $perPage, $page), 'AliExpress API not configured.'];
        }

        $result = $this->aliExpressApi->getInventory($page, $perPage);
        if (empty($result['success'])) {
            return [new LengthAwarePaginator([], 0, $perPage, $page), $result['message'] ?? 'API error'];
        }

        $rows = [];
        foreach ($result['data']['products'] ?? [] as $item) {
            foreach ($this->aliExpressApi->extractSkuRowsFromListItem($item) as $row) {
                if ($searchSku !== '' && stripos($row['sku'], $searchSku) === false) {
                    continue;
                }
                if ($searchName !== '' && stripos((string) ($row['product_name'] ?? ''), $searchName) === false) {
                    continue;
                }
                $rows[] = (object) [
                    'product_id' => $row['product_id'],
                    'sku' => $row['sku'],
                    'product_name' => $row['product_name'],
                    'price' => $row['price'],
                    'quantity' => $row['stock'],
                ];
            }
        }

        $total = (int) ($result['data']['total_count'] ?? count($rows));

        return [
            new LengthAwarePaginator($rows, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]),
            null,
        ];
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array<string, mixed>>
     */
    protected function listingStatusMap(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('aliexpress_listing_statuses')) {
            return [];
        }

        return AliexpressListingStatus::query()
            ->whereIn('sku', $skus)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->sku => (array) ($row->value ?? [])])
            ->all();
    }
}
