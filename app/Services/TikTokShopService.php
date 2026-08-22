<?php

namespace App\Services;

use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Services\Support\Concerns\HandlesMarketplaceApiExceptions;
use App\Services\Support\MarketplaceCharacterLimits;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class TikTokShopService
{
    use HandlesMarketplaceApiExceptions;
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;

    protected $client;
    protected $clientKey;
    protected $clientSecret;
    protected $shopId;
    protected $shopCipher = null;
    protected $accessToken;
    protected $refreshToken;
    protected $lastResponse = null;
    protected $lastResponseCode = null;
    protected $outputCallback = null;

    /** config/services.php key — overridden by TikTok2ShopService */
    protected string $configKey = 'tiktok';

    /** Cache key prefix — overridden by TikTok2ShopService */
    protected string $cachePrefix = 'tiktok';

    protected bool $ipAllowListBlocked = false;

    /** @var array<string, array<string, mixed>> */
    protected array $productDetailCache = [];

    /** @var array<string, array<string, mixed>> */
    protected array $inventorySearchCache = [];

    /** @var array<string, array<string, mixed>> */
    protected array $productSearchCache = [];

    /** version|method that last succeeded for inventory update on this request. */
    protected ?string $workingInventoryPath = null;

    /** @var array<string, true> Paths that this shop does not support (no schema / invalid version). */
    protected array $deadInventoryKeys = [];

    /** LIVE listings reject Update Inventory 202309; skip that path for the rest of this request. */
    protected bool $skipInventoryUpdateApi = false;

    /** version|status that last succeeded for product search on this request. */
    protected ?string $workingProductSearchKey = null;

    /** @var array<string, true> Products already activated while pushing inventory. */
    protected array $activatedForInventory = [];

    public function __construct()
    {
        $cfg = config('services.'.$this->configKey, []);
        $this->clientKey = $cfg['client_key'] ?? null;
        $this->clientSecret = $cfg['client_secret'] ?? null;
        $this->shopId = $cfg['shop_id'] ?? null;

        // Get tokens from cache first, then fallback to env/config
        $this->accessToken = Cache::get($this->cachePrefix.'_access_token') ?? ($cfg['access_token'] ?? null);
        $this->refreshToken = Cache::get($this->cachePrefix.'_refresh_token') ?? ($cfg['refresh_token'] ?? null);

        // Initialize the TikTok Shop client library (same as ship_hub).
        // Explicit timeouts — Guzzle defaults to 0 (wait forever), which freezes listings sync.
        $this->client = new Client($this->clientKey, $this->clientSecret, [
            'timeout' => 45,
            'connect_timeout' => 10,
        ]);

        if ($this->accessToken) {
            $this->client->setAccessToken($this->accessToken);
        }
    }

    public function configKey(): string
    {
        return $this->configKey;
    }

    public function cachePrefix(): string
    {
        return $this->cachePrefix;
    }

    public function envAccessTokenKey(): string
    {
        return strtoupper($this->configKey).'_ACCESS_TOKEN';
    }

    public function envRefreshTokenKey(): string
    {
        return strtoupper($this->configKey).'_REFRESH_TOKEN';
    }

    /**
     * Get shop info using the library
     */
    public function getShopInfo($outputCallback = null): ?array
    {
        try {
            if (!$this->accessToken) {
                if ($outputCallback) $outputCallback('error', 'No access token available');
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            
            $response = $this->client->Authorization->getAuthorizedShop();
            
            $this->lastResponse = $response;
            
            $this->applyShopCipherFromShopInfo($response);

            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            // Token expired - try to refresh
            if ($outputCallback) {
                $outputCallback('info', 'Token expired, attempting to refresh...');
            }
            
            if ($this->refreshAccessToken()) {
                // Retry with new token
                $this->client->setAccessToken($this->accessToken);
                try {
                    $response = $this->client->Authorization->getAuthorizedShop();
                    $this->lastResponse = $response;
                    $this->applyShopCipherFromShopInfo($response);
                    
                    if ($outputCallback) {
                        $outputCallback('info', '✓ Token refreshed and request succeeded');
                    }
                    return $response;
                } catch (\Exception $retryException) {
                    if ($outputCallback) {
                        $outputCallback('error', 'Retry after refresh failed: ' . $retryException->getMessage());
                    }
                }
            } else {
                if ($outputCallback) {
                    $outputCallback('error', 'Failed to refresh token');
                }
            }
            
            if ($outputCallback) {
                $outputCallback('error', 'Error: ' . $e->getMessage());
            }
            Log::error('TikTok getShopInfo failed', ['error' => $e->getMessage()]);
            return ['code' => 999999, 'message' => $e->getMessage(), 'data' => null];
        } catch (\Exception $e) {
            if ($outputCallback) {
                $outputCallback('error', 'Error: ' . $e->getMessage());
                $outputCallback('error', 'Error Class: ' . get_class($e));
            }
            Log::error('TikTok getShopInfo failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return ['code' => 999999, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Normalize shops list from getAuthorizedShop response shapes.
     *
     * @return list<array<string, mixed>>
     */
    protected function extractShopsFromShopInfo(?array $shopInfo): array
    {
        if (! is_array($shopInfo)) {
            return [];
        }

        $candidates = [
            $shopInfo['shops'] ?? null,
            $shopInfo['data']['shops'] ?? null,
            $shopInfo['list'] ?? null,
            $shopInfo['data']['list'] ?? null,
        ];

        foreach ($candidates as $shops) {
            if (is_array($shops) && $shops !== []) {
                return array_values(array_filter($shops, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $shopInfo
     */
    protected function applyShopCipherFromShopInfo(?array $shopInfo): ?string
    {
        $shops = $this->extractShopsFromShopInfo($shopInfo);
        $preferred = array_values(array_filter([
            trim((string) (config('services.'.$this->configKey.'.shop_id') ?? '')),
            trim((string) (Cache::get($this->cachePrefix.'_shop_id') ?? '')),
        ], static fn (string $id) => $id !== ''));

        $pick = null;
        foreach ($shops as $shop) {
            $cipher = trim((string) ($shop['cipher'] ?? ''));
            if (! $this->isUsableShopCipher($cipher)) {
                continue;
            }
            $shopId = trim((string) ($shop['id'] ?? $shop['shop_id'] ?? ''));
            if ($pick === null) {
                $pick = ['cipher' => $cipher, 'shop_id' => $shopId];
            }
            if ($shopId !== '' && in_array($shopId, $preferred, true)) {
                $pick = ['cipher' => $cipher, 'shop_id' => $shopId];
                break;
            }
        }

        if ($pick === null) {
            return null;
        }

        $this->rememberShopCipher($pick['cipher']);
        if ($pick['shop_id'] !== '') {
            Cache::put($this->cachePrefix.'_shop_id', $pick['shop_id'], 86400 * 30);
        }

        return $pick['cipher'];
    }

    protected function isUsableShopCipher(string $cipher): bool
    {
        $cipher = trim($cipher);
        if ($cipher === '' || strlen($cipher) < 12) {
            return false;
        }
        $lower = strtolower($cipher);

        return ! str_contains($lower, 'xxxx')
            && ! str_contains($lower, 'the_real_one')
            && ! str_contains($lower, 'placeholder');
    }

    protected function rememberShopCipher(string $cipher): void
    {
        $cipher = trim($cipher);
        if (! $this->isUsableShopCipher($cipher)) {
            return;
        }

        $this->shopCipher = $cipher;
        $this->client->setShopCipher($cipher);
        Cache::put($this->cachePrefix.'_shop_cipher', $cipher, 86400 * 30);

        // Survive deploy / artisan cache:clear (Cache alone is wiped).
        try {
            $path = $this->durableShopCipherPath();
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($path, $cipher);
        } catch (\Throwable) {
            // Best-effort.
        }
    }

    protected function durableShopCipherPath(): string
    {
        return storage_path('app/'.$this->cachePrefix.'_shop_cipher.txt');
    }

    protected function readDurableShopCipher(): ?string
    {
        $path = $this->durableShopCipherPath();
        if (! is_file($path)) {
            return null;
        }
        $cipher = trim((string) @file_get_contents($path));

        return $this->isUsableShopCipher($cipher) ? $cipher : null;
    }

    /**
     * Set output callback for console output
     */
    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    /**
     * Output message using callback or log
     */
    protected function output(string $type, string $message): void
    {
        if (is_callable($this->outputCallback)) {
            call_user_func($this->outputCallback, $type, $message);
        } else {
            // Fallback to Log if no callback is set
            if ($type === 'info') Log::info($message);
            elseif ($type === 'error') Log::error($message);
            elseif ($type === 'warn') Log::warning($message);
            else Log::debug($message);
        }
    }

    /**
     * Get products list using the library.
     * Pagination uses query page_token (not body cursor). Status filter: ACTIVATE / ALL / etc.
     *
     * @param  int|string  $status  0/"ALL" = no filter; 1/"ACTIVATE" = live listings
     * @param  array<string, mixed>  $filters  Optional body filters (e.g. update_time_ge, update_time_lt)
     */
    public function getProducts(int $pageSize = 20, string $pageToken = '', int|string $status = 0, $outputCallback = null, array $filters = []): ?array
    {
        $callback = $outputCallback ?? $this->outputCallback;

        try {
            if (! $this->accessToken) {
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'error', 'No access token available');
                }

                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            if (! is_string($this->shopCipher) || $this->shopCipher === '') {
                $msg = 'Missing shop_cipher — cannot search products. Open Connect and re-authorize TikTok, or set TIKTOK_SHOP_CIPHER in .env (deploy cache:clear may have wiped the cached cipher).';
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'error', $msg);
                }

                return [
                    'code' => 999999,
                    'message' => $msg,
                    'products' => [],
                ];
            }

            $statusFilter = $this->normalizeProductStatusFilter($status);

            // page_size + page_token must be query params (SDK extractParams), status goes in JSON body
            $query = [
                'page_size' => min(max($pageSize, 1), 100),
            ];
            if ($pageToken !== '') {
                $query['page_token'] = $pageToken;
            }

            $body = [];
            if ($statusFilter !== null) {
                $body['status'] = $statusFilter;
            }
            foreach (['update_time_ge', 'update_time_lt', 'create_time_ge', 'create_time_lt'] as $timeKey) {
                if (isset($filters[$timeKey]) && is_numeric($filters[$timeKey])) {
                    $body[$timeKey] = (int) $filters[$timeKey];
                }
            }

            if ($body === []) {
                $response = $this->client->Product->searchProducts($query);
            } else {
                $response = $this->client->Product->searchProducts($query, $body);
            }

            $this->lastResponse = $response;

            if ($callback && is_callable($callback)) {
                $count = count($response['products'] ?? []);
                $total = $response['total_count'] ?? '?';
                call_user_func($callback, 'info', "Products page: {$count} rows (total_count={$total})");
            }

            if (isset($response['code']) && $response['code'] != 0) {
                $errorMsg = 'API Error Code: '.$response['code'].', Message: '.($response['message'] ?? 'No message');
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'error', $errorMsg);
                }
            }

            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'info', 'Token expired, attempting to refresh...');
            }
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'info', 'Token refreshed, retrying products page...');
                }

                return $this->getProducts($pageSize, $pageToken, $status, $callback, $filters);
            }
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'error', 'Failed to refresh token: '.$e->getMessage());
            }

            return null;
        } catch (\Exception $e) {
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'error', 'Exception getting products: '.$e->getMessage());
            }

            return null;
        }
    }

    /**
     * Map legacy/int status args to TikTok searchProducts status filter.
     */
    protected function normalizeProductStatusFilter(int|string $status): ?string
    {
        if ($status === 0 || $status === '0' || $status === '' || strtoupper((string) $status) === 'ALL') {
            return null;
        }
        if ($status === 1 || $status === '1') {
            return 'ACTIVATE';
        }

        return strtoupper((string) $status);
    }

    /**
     * Get all products with page_token pagination.
     *
     * @param  int|string  $status  0/ALL = all; 1/ACTIVATE = live (~900)
     */
    public function getAllProducts(int|string $status = 0): array
    {
        $allProducts = [];
        $pageToken = '';
        $page = 1;
        $seenTokens = [];

        while (true) {
            if ($pageToken !== '') {
                if (isset($seenTokens[$pageToken])) {
                    $this->output('warn', 'Pagination token repeated — stopping to avoid loop');
                    break;
                }
                $seenTokens[$pageToken] = true;
            }

            $response = $this->getProducts(100, $pageToken, $status, $this->outputCallback);

            if (! $response) {
                $this->output('error', "Page {$page}: No response received");
                break;
            }

            if (isset($response['code']) && (int) $response['code'] !== 0) {
                $this->output('error', "Page {$page}: API code ".$response['code']);
                break;
            }

            $products = $response['products'] ?? $response['data']['products'] ?? [];
            if (! is_array($products)) {
                $products = [];
            }

            if ($products !== []) {
                $allProducts = array_merge($allProducts, $products);
            }

            $totalCount = $response['total_count'] ?? null;
            $this->output(
                'info',
                "Fetched page {$page}: +".count($products).' (running total '.count($allProducts)
                .($totalCount !== null ? " / {$totalCount}" : '').')'
            );

            $next = (string) ($response['next_page_token'] ?? $response['data']['next_page_token'] ?? '');
            if ($next === '' || $products === []) {
                break;
            }

            $pageToken = $next;
            $page++;

            if (count($allProducts) > 20000) {
                $this->output('warn', 'Safety stop at 20000 products');
                break;
            }

            usleep(150000);
        }

        return $allProducts;
    }

    /**
     * Search orders page (TikTok Order API).
     * Query: page_size, page_token. Body: create_time_ge/lt, update_time_ge/lt, order_status.
     */
    public function getOrders(array $query = [], array $body = []): ?array
    {
        try {
            if (! $this->accessToken) {
                $this->output('error', 'getOrders: No access token available');

                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $query = array_merge([
                'page_size' => 50,
            ], $query);

            if ($body === []) {
                $response = $this->client->Order->getOrderList($query);
            } else {
                $response = $this->client->Order->getOrderList($query, $body);
            }

            $this->lastResponse = $response;

            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            $this->output('info', 'getOrders: Token expired, refreshing...');
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);

                return $this->getOrders($query, $body);
            }
            $this->output('error', 'getOrders: Failed to refresh token - '.$e->getMessage());

            return null;
        } catch (\Exception $e) {
            $this->output('error', 'getOrders Exception: '.$e->getMessage());
            Log::error('TikTok getOrders failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * TikTok Shop rejects create_time ranges longer than 30 days (empty/error).
     * Split into 14-day windows so scheduled fetches still pick up new orders.
     */
    private const ORDER_SEARCH_MAX_SPAN_SECONDS = 14 * 86400;

    /**
     * Fetch all orders in a create-time window with page_token pagination.
     *
     * @return list<array>
     */
    public function getAllOrders(int $createTimeGe, int $createTimeLt, ?string $orderStatus = null, int $pageSize = 50): array
    {
        if ($createTimeLt <= $createTimeGe) {
            return [];
        }

        if (($createTimeLt - $createTimeGe) > self::ORDER_SEARCH_MAX_SPAN_SECONDS) {
            $all = [];
            $cursor = $createTimeGe;
            while ($cursor < $createTimeLt) {
                $end = min($cursor + self::ORDER_SEARCH_MAX_SPAN_SECONDS, $createTimeLt);
                $all = array_merge($all, $this->getAllOrdersInWindow($cursor, $end, $orderStatus, $pageSize));
                $cursor = $end;
            }

            $byId = [];
            foreach ($all as $i => $order) {
                if (! is_array($order)) {
                    continue;
                }
                $id = trim((string) ($order['id'] ?? $order['order_id'] ?? ''));
                $byId[$id !== '' ? $id : 'row-'.$i] = $order;
            }

            return array_values($byId);
        }

        return $this->getAllOrdersInWindow($createTimeGe, $createTimeLt, $orderStatus, $pageSize);
    }

    /**
     * @return list<array>
     */
    protected function getAllOrdersInWindow(int $createTimeGe, int $createTimeLt, ?string $orderStatus, int $pageSize): array
    {
        $all = [];
        $pageToken = '';
        $page = 1;
        $seen = [];

        while (true) {
            if ($pageToken !== '' && isset($seen[$pageToken])) {
                $this->output('warn', 'Order pagination token repeated — stopping');
                break;
            }
            if ($pageToken !== '') {
                $seen[$pageToken] = true;
            }

            $query = ['page_size' => min(max($pageSize, 1), 100)];
            if ($pageToken !== '') {
                $query['page_token'] = $pageToken;
            }

            $body = [
                'create_time_ge' => $createTimeGe,
                'create_time_lt' => $createTimeLt,
            ];
            if ($orderStatus) {
                $body['order_status'] = $orderStatus;
            }

            $response = $this->getOrders($query, $body);
            if (! $response) {
                break;
            }

            $code = (int) ($response['code'] ?? 0);
            if ($code !== 0) {
                $this->output('error', 'getAllOrders API error: code='.$code.' '.($response['message'] ?? ''));
                Log::error('TikTok getAllOrders failed', [
                    'code' => $code,
                    'message' => $response['message'] ?? null,
                    'create_time_ge' => $createTimeGe,
                    'create_time_lt' => $createTimeLt,
                ]);
                break;
            }

            $orders = $response['orders'] ?? $response['data']['orders'] ?? [];
            if (! is_array($orders)) {
                $orders = [];
            }

            if ($orders !== []) {
                $all = array_merge($all, $orders);
            }

            $total = $response['total_count'] ?? $response['data']['total_count'] ?? null;
            $this->output(
                'info',
                "Orders page {$page}: +".count($orders).' (running '.count($all)
                .($total !== null ? " / {$total}" : '').')'
            );

            $next = (string) ($response['next_page_token'] ?? $response['data']['next_page_token'] ?? '');
            if ($next === '' || $orders === []) {
                break;
            }

            $pageToken = $next;
            $page++;
            usleep(150000);

            if (count($all) > 50000) {
                $this->output('warn', 'Safety stop at 50000 orders');
                break;
            }
        }

        return $all;
    }

    /**
     * Optional: enrich with GET orders detail (batch of up to 50 ids).
     */
    public function getOrderDetails(array $orderIds): ?array
    {
        try {
            if (! $this->accessToken || $orderIds === []) {
                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $ids = array_values(array_unique(array_map('strval', array_slice($orderIds, 0, 50))));
            $response = $this->client->Order->getOrderDetail($ids);
            $this->lastResponse = $response;

            return $response;
        } catch (\Exception $e) {
            $this->output('error', 'getOrderDetails Exception: '.$e->getMessage());
            Log::error('TikTok getOrderDetails failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get product inventory using the library
     */
    public function getProductInventory(array $productIds): ?array
    {
        try {
            if (!$this->accessToken) {
                $this->output('error', 'getProductInventory: No access token available');
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Use inventorySearch method from library
            // Ensure all product IDs are strings
            $productIdList = array_map('strval', array_slice($productIds, 0, 50));
            $this->output('info', 'Calling Product->inventorySearch() with ' . count($productIdList) . ' product IDs');
            $this->output('info', 'Sample product IDs: ' . implode(', ', array_slice($productIdList, 0, 3)));
            $response = $this->client->Product->inventorySearch([
                'product_id_list' => $productIdList,
            ]);
            $this->lastResponse = $response;
            
            if (isset($response['code']) && $response['code'] != 0) {
                $this->output('error', 'getProductInventory API error: Code ' . $response['code'] . ', Message: ' . ($response['message'] ?? 'No message'));
                return null;
            }
            
            $this->output('info', 'getProductInventory: Response received. Keys: ' . implode(', ', array_keys($response)));
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            $this->output('info', 'getProductInventory: Token expired, refreshing...');
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                $productIdList = array_map('strval', array_slice($productIds, 0, 50));
                $response = $this->client->Product->inventorySearch([
                    'product_id_list' => $productIdList,
                ]);
                $this->lastResponse = $response;
                return $response;
            }
            $this->output('error', 'getProductInventory: Failed to refresh token - ' . $e->getMessage());
            Log::error('TikTok getProductInventory failed', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
            $this->output('error', 'getProductInventory Exception: ' . $e->getMessage() . ' (Class: ' . get_class($e) . ')');
            Log::error('TikTok getProductInventory failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * One inventory row per seller SKU (never product-total stock).
     * Combined listings must not copy the sum of variants onto the first SKU.
     *
     * @return list<array{product_id: string, sku: string, sku_id: string, available_stock: int, stock: int}>
     */
    public function getAllProductInventory(array $products): array
    {
        $allInventory = [];

        if (empty($products)) {
            $this->output('warn', 'getAllProductInventory: No products provided');

            return $allInventory;
        }

        $this->output('info', 'getAllProductInventory: Extracting SKU inventory from '.count($products).' products');

        foreach ($products as $product) {
            $productId = $product['id'] ?? $product['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $skus = $product['skus'] ?? [];
            if (is_array($skus) && $skus !== []) {
                foreach ($skus as $sku) {
                    if (! is_array($sku)) {
                        continue;
                    }
                    $sellerSku = trim((string) ($sku['seller_sku'] ?? $sku['sku'] ?? ''));
                    $skuId = trim((string) ($sku['id'] ?? $sku['sku_id'] ?? ''));
                    if ($sellerSku === '' && $skuId === '') {
                        continue;
                    }
                    $stock = (int) (self::skuNodeAvailableQty($sku) ?? 0);
                    $allInventory[] = [
                        'product_id' => (string) $productId,
                        'sku' => $sellerSku,
                        'sku_id' => $skuId,
                        'available_stock' => $stock,
                        'stock' => $stock,
                    ];
                }

                continue;
            }

            $sellerSku = trim((string) ($product['seller_sku'] ?? $product['sku'] ?? ''));
            $stock = (int) ($product['available_stock']
                ?? $product['stock']
                ?? $product['inventory_quantity']
                ?? (is_array($product['inventory'] ?? null) ? ($product['inventory']['available_stock'] ?? 0) : 0)
                ?? 0);
            if ($sellerSku !== '' || $stock > 0) {
                $allInventory[] = [
                    'product_id' => (string) $productId,
                    'sku' => $sellerSku,
                    'sku_id' => '',
                    'available_stock' => $stock,
                    'stock' => $stock,
                ];
            }
        }

        $this->output('info', 'getAllProductInventory: Extracted inventory for '.count($allInventory).' SKUs');

        return $allInventory;
    }

    /**
     * Available qty for one TikTok SKU node. Prefer warehouse inventory rows;
     * do not add quantity on top of inventory[] (that double-counts).
     */
    public static function skuNodeAvailableQty(array $skuNode): ?int
    {
        $rows = self::skuNodeWarehouseRows($skuNode);
        if ($rows !== []) {
            $stock = 0;
            foreach ($rows as $row) {
                $stock += (int) ($row['quantity'] ?? 0);
            }

            return $stock;
        }

        if (isset($skuNode['available_stock'])) {
            return (int) $skuNode['available_stock'];
        }
        if (isset($skuNode['quantity'])) {
            return (int) $skuNode['quantity'];
        }

        return null;
    }

    /**
     * @return list<array{warehouse_id: string, quantity: int}>
     */
    public static function skuNodeWarehouseRows(array $skuNode): array
    {
        $out = [];
        $seen = [];

        foreach (['inventory', 'warehouse_inventory', 'warehouses', 'stock_infos', 'inventory_list'] as $key) {
            $inventory = $skuNode[$key] ?? null;
            if (! is_array($inventory)) {
                continue;
            }
            $rows = array_is_list($inventory) ? $inventory : [$inventory];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $wid = trim((string) ($row['warehouse_id'] ?? $row['warehouseId'] ?? ($row['warehouse']['id'] ?? '')));
                if ($wid === '' || isset($seen[$wid])) {
                    continue;
                }
                $seen[$wid] = true;
                $out[] = [
                    'warehouse_id' => $wid,
                    'quantity' => (int) ($row['quantity'] ?? $row['available_quantity'] ?? $row['available_stock'] ?? $row['stock'] ?? 0),
                ];
            }
        }

        $wid = trim((string) ($skuNode['warehouse_id'] ?? $skuNode['warehouseId'] ?? ''));
        if ($wid !== '' && ! isset($seen[$wid])) {
            $out[] = [
                'warehouse_id' => $wid,
                'quantity' => (int) ($skuNode['quantity'] ?? $skuNode['available_stock'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Get product analytics using the library with API version 202405
     */
    /**
     * Run a callback against Analytics APIs (requires client API version 202405).
     * Restores the default product API version afterwards.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withAnalyticsApi(callable $callback)
    {
        $this->client->useVersion('202405');
        try {
            return $callback();
        } finally {
            $this->client->useVersion(Client::DEFAULT_VERSION);
        }
    }

    /**
     * Product performance list (GMV/orders). Date params must be Y-m-d.
     */
    public function getProductAnalytics(?string $startDate = null, ?string $endDate = null, array $productIds = []): ?array
    {
        try {
            if (! $this->accessToken) {
                $this->output('error', 'getProductAnalytics: No access token available');

                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $tz = 'America/Los_Angeles';
            $startDate = $startDate ?: Carbon::now($tz)->subDays(30)->format('Y-m-d');
            $endDate = $endDate ?: Carbon::now($tz)->format('Y-m-d');

            $params = [
                'start_date_ge' => $startDate,
                'end_date_lt' => $endDate,
                'page_size' => 50,
            ];

            $this->output('info', "Calling Analytics shop_products/performance ({$startDate}..{$endDate})...");

            $response = $this->withAnalyticsApi(
                fn () => $this->client->Analytics->getShopProductPerformanceList($params)
            );

            $this->lastResponse = $response;
            $this->output('info', 'getProductAnalytics: Response keys: '.implode(', ', array_keys(is_array($response) ? $response : [])));

            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            $this->output('info', 'getProductAnalytics: Token expired, refreshing...');
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);

                return $this->getProductAnalytics($startDate, $endDate, $productIds);
            }
            $this->output('error', 'getProductAnalytics: Failed to refresh token - '.$e->getMessage());
            Log::error('TikTok getProductAnalytics failed', ['error' => $e->getMessage()]);

            return null;
        } catch (\Exception $e) {
            $this->output('error', 'getProductAnalytics Exception: '.$e->getMessage());
            Log::error('TikTok getProductAnalytics failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function ensureShopCipher(): void
    {
        if (is_string($this->shopCipher) && $this->shopCipher !== '') {
            $this->client->setShopCipher($this->shopCipher);

            return;
        }

        // Prefer previously cached cipher (survives getShopInfo IP allow-list failures)
        $cached = Cache::get($this->cachePrefix.'_shop_cipher');
        if (is_string($cached) && $this->isUsableShopCipher($cached)) {
            $this->rememberShopCipher(trim($cached));

            return;
        }

        $durable = $this->readDurableShopCipher();
        if ($durable !== null) {
            $this->rememberShopCipher($durable);

            return;
        }

        $cfgCipher = config('services.'.$this->configKey.'.shop_cipher');
        if (is_string($cfgCipher) && trim($cfgCipher) !== '') {
            $this->rememberShopCipher(trim($cfgCipher));

            return;
        }

        $shopInfo = $this->getShopInfo();
        if ($this->applyShopCipherFromShopInfo(is_array($shopInfo) ? $shopInfo : null)) {
            return;
        }
    }

    /**
     * Get product details using the library
     */
    public function getProductDetails(array $productIds): ?array
    {
        try {
            if (!$this->accessToken) {
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Get first product ID (library method takes single ID)
            if (empty($productIds)) {
                return null;
            }
            
            $productId = $productIds[0];
            $response = $this->client->Product->getProduct($productId);
            $this->lastResponse = $response;
            
            return $response;
        } catch (\Exception $e) {
            Log::error('TikTok getProductDetails failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch L30 page_views via Analytics getShopProductPerformance (API 202405).
     * List endpoint does not return page_views; per-product performance does.
     */
    public function getProductViews(array $products): ?array
    {
        try {
            if (empty($products)) {
                $this->output('warn', 'getProductViews: No products provided');

                return null;
            }

            if (! $this->accessToken) {
                $this->output('error', 'getProductViews: No access token available');

                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $tz = 'America/Los_Angeles';
            $startDate = Carbon::now($tz)->subDays(30)->format('Y-m-d');
            $endDate = Carbon::now($tz)->format('Y-m-d');

            $productMap = [];
            foreach ($products as $product) {
                $productId = (string) ($product['id'] ?? $product['product_id'] ?? '');
                if ($productId === '') {
                    continue;
                }
                $productMap[$productId] = $product;
            }

            $productIds = array_keys($productMap);
            $this->output('info', 'getProductViews: Fetching page_views for '.count($productIds)." products ({$startDate}..{$endDate})...");

            $viewsData = [];
            $failed = 0;

            foreach ($productIds as $index => $productId) {
                try {
                    $perf = $this->withAnalyticsApi(
                        fn () => $this->client->Analytics->getShopProductPerformance($productId, [
                            'start_date_ge' => $startDate,
                            'end_date_lt' => $endDate,
                            'data_granularity' => 'ALL',
                        ])
                    );

                    $interval = $perf['performance']['intervals'][0] ?? null;
                    $views = $interval['page_views']
                        ?? $perf['performance']['page_views']
                        ?? $perf['page_views']
                        ?? null;

                    if ($views === null) {
                        $failed++;
                        continue;
                    }

                    $product = $productMap[$productId] ?? [];
                    $sku = $product['seller_sku']
                        ?? $product['sku']
                        ?? ($product['skus'][0]['seller_sku'] ?? $product['skus'][0]['sku'] ?? null);

                    $viewsData[] = [
                        'product_id' => $productId,
                        'sku' => $sku,
                        'views' => (int) $views,
                        'product_views' => (int) $views,
                        'impressions' => (int) ($interval['impressions'] ?? 0),
                        'orders' => (int) ($interval['orders'] ?? 0),
                    ];
                } catch (\Throwable $e) {
                    $failed++;
                    if ($failed <= 3) {
                        $this->output('warn', "getProductViews: product {$productId} failed — ".$e->getMessage());
                    }
                }

                if (($index + 1) % 10 === 0) {
                    $this->output('info', 'getProductViews: processed '.($index + 1).'/'.count($productIds));
                }

                usleep(80000);
            }

            $this->output('info', 'getProductViews: Found views for '.count($viewsData).' products'.($failed ? " ({$failed} skipped)" : ''));
            $this->lastResponse = ['analytics' => $viewsData];

            return ['analytics' => $viewsData];
        } catch (\Exception $e) {
            $this->output('error', 'getProductViews Exception: '.$e->getMessage());
            Log::error('TikTok getProductViews failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get product reviews/ratings - extract from product data since TikTok doesn't expose individual reviews
     * TikTok product data includes review_count and shop_rating/rating fields
     */
    public function getProductReviews(array $products): ?array
    {
        try {
            if (empty($products)) {
                $this->output('warn', 'getProductReviews: No products provided');
                return null;
            }
            
            $this->output('info', 'getProductReviews: Extracting review data from ' . count($products) . ' products');
            
            // Debug: Log first product structure to see what fields are available
            if (!empty($products[0])) {
                $sampleProduct = $products[0];
                $this->output('info', 'Sample product keys: ' . implode(', ', array_keys($sampleProduct)));
                if (isset($sampleProduct['data']) && is_array($sampleProduct['data'])) {
                    $this->output('info', 'Sample product data keys: ' . implode(', ', array_keys($sampleProduct['data'])));
                }
            }
            
            $reviewsData = [];
            foreach ($products as $product) {
                $productId = $product['id'] ?? $product['product_id'] ?? null;
                if (!$productId) continue;
                
                // Extract review count and rating from product data - try various field names
                $reviewCount = $product['review_count'] 
                    ?? $product['reviews_count'] 
                    ?? $product['total_reviews']
                    ?? $product['reviews']
                    ?? $product['data']['review_count'] 
                    ?? $product['data']['reviews_count']
                    ?? $product['data']['total_reviews']
                    ?? $product['rating_info']['review_count'] ?? 0;
                
                $rating = $product['shop_rating'] 
                    ?? $product['rating'] 
                    ?? $product['average_rating']
                    ?? $product['avg_rating']
                    ?? $product['data']['shop_rating'] 
                    ?? $product['data']['rating']
                    ?? $product['rating_info']['rating']
                    ?? $product['rating_info']['average_rating'] ?? null;
                
                // Always include product with review data (even if 0/null) - let the processing method decide
                $reviewsData[] = [
                    'product_id' => (string)$productId,
                    'review_count' => (int)$reviewCount,
                    'rating' => $rating !== null ? (float)$rating : null,
                ];
            }
            
            $this->output('info', "getProductReviews: Extracted review data for " . count($reviewsData) . " products");
            
            $this->lastResponse = ['reviews' => $reviewsData];
            return ['reviews' => $reviewsData];
        } catch (\Exception $e) {
            $this->output('error', 'getProductReviews Exception: ' . $e->getMessage() . ' (Class: ' . get_class($e) . ')');
            Log::error('TikTok getProductReviews failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Sync all product data
     */
    public function syncAllProductData(): array
    {
        $result = [
            'products' => [],
            'inventory' => [],
            'analytics' => [],
            'reviews' => [],
            'errors' => []
        ];

        try {
            $this->output('info', 'Starting syncAllProductData...');
            
            $this->output('info', 'Step 1: Fetching products...');
            $products = $this->getAllProducts(1);
            $result['products'] = $products;
            $this->output('info', '✓ Fetched ' . count($products) . ' products');

            // Fetch inventory for products
            $this->output('info', 'Step 2: Fetching inventory data...');
            $inventoryData = $this->getAllProductInventory($products);
            $result['inventory'] = $inventoryData;
            if (!empty($inventoryData)) {
                $this->output('info', '✓ Fetched inventory for ' . count($inventoryData) . ' products');
            } else {
                $this->output('warn', '⚠ No inventory data retrieved');
            }
            
            // Analytics/Views via Analytics API v202405 (per-product page_views)
            $this->output('info', 'Step 3: Fetching view data from TikTok Analytics API...');
            $viewsData = $this->getProductViews($products);
            if ($viewsData && ! empty($viewsData['analytics'])) {
                $result['analytics'] = $viewsData['analytics'];
                $this->output('info', '✓ Fetched view data for '.count($viewsData['analytics']).' products');
            } else {
                $this->output('warn', '⚠ No view data returned from Analytics API');
                $result['analytics'] = [];
            }
            
            // Reviews: Extract review_count and rating from product data (TikTok provides aggregated stats, not individual reviews)
            $this->output('info', 'Step 4: Extracting review data from products...');
            $reviews = $this->getProductReviews($products);
            if ($reviews && !empty($reviews['reviews'])) {
                $result['reviews'] = $reviews['reviews'];
                $this->output('info', '✓ Extracted review data for ' . count($reviews['reviews']) . ' products');
            } else {
                $this->output('warn', '⚠ No review data found in products');
                $result['reviews'] = [];
            }

        } catch (\Exception $e) {
            $errorMsg = 'TikTok syncAllProductData error: ' . $e->getMessage();
            $this->output('error', '✗ Exception: ' . $errorMsg);
            Log::error($errorMsg, ['trace' => $e->getTraceAsString()]);
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->accessToken);
    }
    
    public function setTokens(string $accessToken, string $refreshToken = null): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;

        Cache::put($this->cachePrefix.'_access_token', $accessToken, 86400);
        if ($refreshToken) {
            Cache::put($this->cachePrefix.'_refresh_token', $refreshToken, 86400 * 30);
        }

        if ($this->client) {
            $this->client->setAccessToken($accessToken);
        }
    }
    
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }
    
    public function getLastResponseCode(): ?int
    {
        return $this->lastResponseCode;
    }
    
    public function getAuthorizationUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        Cache::put($this->cachePrefix.'_oauth_state', $state, 600);

        // Include redirect_uri explicitly — must match Partner Center + .env exactly.
        $params = [
            'app_key' => $this->clientKey,
            'state' => $state,
        ];
        $redirectUri = trim((string) config('services.'.$this->configKey.'.redirect_uri', ''));
        if ($redirectUri !== '') {
            $params['redirect_uri'] = $redirectUri;
        }

        return 'https://auth.tiktok-shops.com/oauth/authorize?'.http_build_query($params);
    }

    /**
     * Exchange a one-time OAuth auth_code for access/refresh tokens.
     *
     * @return array{success: bool, message: string, access_token?: string, refresh_token?: string, raw?: array}
     */
    public function exchangeAuthCode(string $code): array
    {
        // Callback codes are URL-encoded; decode once. Already-decoded values stay unchanged.
        $code = trim(rawurldecode($code));
        if ($code === '') {
            return ['success' => false, 'message' => 'Authorization code is empty.'];
        }

        try {
            // Prefer direct HTTP (same as TikTok Shop docs) so we can surface full API errors.
            $response = Http::timeout(30)->get('https://auth.tiktok-shops.com/api/v2/token/get', [
                'app_key' => $this->clientKey,
                'app_secret' => $this->clientSecret,
                'auth_code' => $code,
                'grant_type' => 'authorized_code',
            ]);

            $json = $response->json();
            if (! is_array($json)) {
                return [
                    'success' => false,
                    'message' => 'TikTok token endpoint returned non-JSON (HTTP '.$response->status().').',
                ];
            }

            $apiCode = (int) ($json['code'] ?? -1);
            if ($apiCode !== 0) {
                Log::warning('TikTok token exchange API error', [
                    'api_code' => $apiCode,
                    'message' => $json['message'] ?? null,
                    'request_id' => $json['request_id'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => ($json['message'] ?? 'token exchange failed')
                        .' (api_code='.$apiCode
                        .(isset($json['request_id']) ? ' request_id='.$json['request_id'] : '')
                        .'). Auth codes are single-use — start again from /tiktok/connect for a NEW code.',
                    'raw' => $json,
                ];
            }

            $token = $json['data'] ?? [];
            $accessToken = $token['access_token'] ?? null;
            if (! $accessToken) {
                return [
                    'success' => false,
                    'message' => 'TikTok token response missing access_token.',
                    'raw' => $json,
                ];
            }

            $refreshToken = $token['refresh_token'] ?? null;
            $this->setTokens($accessToken, $refreshToken);

            config([
                'services.'.$this->configKey.'.access_token' => $accessToken,
                'services.'.$this->configKey.'.refresh_token' => $refreshToken,
            ]);

            return [
                'success' => true,
                'message' => 'TikTok tokens obtained.',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'raw' => $token,
            ];
        } catch (\Throwable $e) {
            Log::error('TikTok exchangeAuthCode failed', [
                'channel' => $this->configKey,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    public function refreshAccessToken(): ?array
    {
        if (!$this->refreshToken) {
            return null;
        }

        try {
            $auth = $this->client->auth();
            $newToken = $auth->refreshNewToken($this->refreshToken);
            
            if (isset($newToken['access_token'])) {
                $this->accessToken = $newToken['access_token'];
                $this->refreshToken = $newToken['refresh_token'] ?? $this->refreshToken;
                
                $expiresIn = $newToken['expire_in'] ?? 86400;
                Cache::put($this->cachePrefix.'_access_token', $this->accessToken, $expiresIn - 300);
                Cache::put($this->cachePrefix.'_refresh_token', $this->refreshToken, 86400 * 30);
                
                $this->client->setAccessToken($this->accessToken);
                
                return $newToken;
            }
        } catch (\Exception $e) {
            Log::error('TikTok refreshAccessToken failed', [
                'channel' => $this->configKey,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'SKU / product id and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok Shop access token not available.'];
        }

        $this->client->setAccessToken($this->accessToken);
        if ($this->shopCipher) {
            $this->client->setShopCipher($this->shopCipher);
        }

        $productId = $this->resolveTikTokProductIdForIdentifier(trim($identifier));
        if (! $productId) {
            return ['success' => false, 'message' => 'TikTok Shop product not found for SKU / id.'];
        }

        $body = [
            'product_id' => $productId,
            'video' => ['url' => $videos[0]],
            'videos' => array_map(fn ($url) => ['url' => $url], $videos),
        ];

        try {
            if (! method_exists($this->client->Product, 'editProduct')) {
                return ['success' => false, 'message' => 'TikTok Shop product edit API is not available in this SDK version.'];
            }

            $response = $this->client->Product->editProduct([], $body);
            $this->lastResponse = $response;
            if (isset($response['code']) && (int) $response['code'] === 0) {
                $this->saveVideoUrlsToMetricsRow('tiktok_metrics', trim($identifier), $videos);

                return ['success' => true, 'message' => 'TikTok Shop product video updated.', 'normalized_urls' => $videos];
            }

            return [
                'success' => false,
                'message' => (string) ($response['message'] ?? 'TikTok Shop video update failed.'),
            ];
        } catch (\Throwable $e) {
            Log::error('TikTok updateVideos failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update product title on TikTok Shop (Title Master).
     *
     * @return array{success: bool, message: string}
     */
    public function updateProductTitle(string $productId, string $title): array
    {
        $productId = trim($productId);
        $title = MarketplaceCharacterLimits::truncateTitle($title, 'tiktok');

        if ($productId === '' || $title === '') {
            return ['success' => false, 'message' => 'Product ID and title are required.'];
        }

        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok access token not configured.'];
        }

        $baseUrl = 'https://open-api.tiktokglobalshop.com';
        $headers = [
            'Authorization' => 'Bearer '.$this->accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payload = ['title' => $title];

        $endpoints = [
            "/product/202309/products/{$productId}",
            "/api/products/{$productId}",
        ];

        $lastStatus = 0;
        $lastBody = '';

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(45)
                    ->patch($baseUrl.$endpoint, $payload);

                $lastStatus = $response->status();
                $lastBody = $response->body();

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && (isset($data['data']) || ($data['success'] ?? false))) {
                        return $this->marketplaceApiSuccess('TikTok updateProductTitle', $productId);
                    }
                }

                if ($lastStatus === 404) {
                    continue;
                }
            } catch (\Throwable $e) {
                return $this->handleMarketplaceThrowable('TikTok updateProductTitle', $productId, $e);
            }
        }

        return $this->marketplaceApiFailure(
            'TikTok updateProductTitle',
            $productId,
            $lastStatus === 401 || $lastStatus === 403
                ? "Authentication failed (HTTP {$lastStatus}). Check TikTok API credentials."
                : ($lastBody !== '' ? mb_substr($lastBody, 0, 500) : "TikTok title update failed (HTTP {$lastStatus})."),
            ['status' => $lastStatus]
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{success: bool, message: string}
     */
    private function updateTikTokProductFields(string $identifier, array $fields): array
    {
        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok Shop access token not available.'];
        }

        $productId = $this->resolveTikTokProductIdForIdentifier(trim($identifier));
        if (! $productId) {
            return ['success' => false, 'message' => 'TikTok Shop product not found for SKU / id.'];
        }

        $this->client->setAccessToken($this->accessToken);
        if ($this->shopCipher) {
            $this->client->setShopCipher($this->shopCipher);
        }

        $body = array_merge(['product_id' => $productId], $fields);

        try {
            if (! method_exists($this->client->Product, 'editProduct')) {
                return ['success' => false, 'message' => 'TikTok Shop product edit API is not available in this SDK version.'];
            }

            $response = $this->client->Product->editProduct($body);
            if (is_array($response) && (int) ($response['code'] ?? -1) === 0) {
                return ['success' => true, 'message' => 'TikTok Shop product updated.'];
            }

            return [
                'success' => false,
                'message' => (string) ($response['message'] ?? 'TikTok Shop product update failed.'),
            ];
        } catch (\Throwable $e) {
            Log::error('TikTok product field update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bulletPoints) ?: []));
        $html = $lines === [] ? '' : '<ul>'.implode('', array_map(fn ($l) => '<li>'.htmlspecialchars($l, ENT_QUOTES, 'UTF-8').'</li>', $lines)).'</ul>';

        return $this->updateTikTokProductFields($identifier, [
            'description' => $html,
            'product_attributes' => ['bullet_points' => array_values($lines)],
        ]);
    }

    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->updateTikTokProductFields($identifier, ['description' => $description]);
    }

    public function updateProductDescription(string $identifier, string $description): array
    {
        return $this->updateDescription($identifier, $description);
    }

    /**
     * @param  list<string>  $images
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_values(array_filter(array_map('trim', $images), fn ($v) => $v !== ''));
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        return $this->updateTikTokProductFields($identifier, [
            'images' => array_map(fn ($url) => ['url' => $url], $images),
            'main_image' => ['url' => $images[0]],
        ]);
    }

    /**
     * Update sale price for a single SKU on TikTok Shop.
     * API: POST product/.../products/{product_id}/prices/update
     *
     * @param  string  $productId  TikTok product ID
     * @param  string  $skuId      TikTok SKU ID (from product.skus[].id)
     * @param  float   $price      Sale price to set
     * @param  string  $currency   ISO currency (default USD)
     * @return array{success: bool, message: string}
     */
    public function updateProductPrice(string $productId, string $skuId, float $price, string $currency = 'USD'): array
    {
        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok access token not available. Connect the shop first.'];
        }

        $productId = trim($productId);
        $skuId = trim($skuId);
        if ($productId === '' || $skuId === '') {
            return ['success' => false, 'message' => 'TikTok product_id and sku_id are required. Run Sync products first.'];
        }
        if (! ($price > 0)) {
            return ['success' => false, 'message' => 'Price must be greater than 0.'];
        }

        try {
            $this->client->setAccessToken($this->accessToken);

            // Refresh cipher when possible; if getShopInfo is IP-blocked, fall back to cache/file/.env
            $shopInfo = $this->getShopInfo();
            $shopInfoFailed = is_array($shopInfo)
                && array_key_exists('code', $shopInfo)
                && (int) $shopInfo['code'] !== 0;
            if (! $shopInfoFailed) {
                $this->applyShopCipherFromShopInfo($shopInfo);
            }
            if (! $this->shopCipher) {
                $this->ensureShopCipher();
            }

            if (! $this->shopCipher) {
                $msg = $shopInfoFailed
                    ? (string) ($shopInfo['message'] ?? 'TikTok shop authorization failed.')
                    : 'TikTok shop cipher missing. Re-authorize the shop (Connect) or set TIKTOK_SHOP_CIPHER in .env.';

                return ['success' => false, 'message' => $msg];
            }

            $params = [
                'skus' => [
                    [
                        'id' => $skuId,
                        'price' => [
                            'amount' => number_format($price, 2, '.', ''),
                            'currency' => strtoupper(trim($currency) ?: 'USD'),
                        ],
                    ],
                ],
            ];

            $response = $this->client->Product->updatePrice($productId, $params);
            $this->lastResponse = $response;

            if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
                return [
                    'success' => false,
                    'message' => (string) ($response['message'] ?? 'TikTok price update failed.'),
                ];
            }

            return [
                'success' => true,
                'message' => 'Price $'.number_format($price, 2).' pushed to TikTok Shop.',
            ];
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->refreshAccessToken()) {
                return $this->updateProductPrice($productId, $skuId, $price, $currency);
            }

            return ['success' => false, 'message' => 'Token expired and refresh failed: '.$e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('TikTok updateProductPrice failed', [
                'channel' => $this->configKey,
                'product_id' => $productId,
                'sku_id' => $skuId,
                'price' => $price,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isIpAllowListError(?string $message): bool
    {
        $message = trim((string) $message);

        return $message !== '' && (
            stripos($message, 'IP allow list') !== false
            || stripos($message, 'IP address is not in the IP allow list') !== false
        );
    }

    public function isIpAllowListBlocked(): bool
    {
        return $this->ipAllowListBlocked;
    }

    protected function rememberIpAllowList(?string $message): void
    {
        if (self::isIpAllowListError($message)) {
            $this->ipAllowListBlocked = true;
        }
    }

    /**
     * Update inventory for a single SKU on TikTok Shop.
     *
     * @param  string  $productId  TikTok product ID
     * @param  string  $skuId      TikTok SKU ID (from product.skus[].id)
     * @param  int     $quantity   Available quantity to set
     * @return array{success: bool, message: string}
     */
    public function updateProductInventory(string $productId, string $skuId, int $quantity, bool $retried = false): array
    {
        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok access token not available.'];
        }

        if ($this->ipAllowListBlocked) {
            return ['success' => false, 'message' => 'Access denied. Your IP address is not in the IP allow list configured for this app.'];
        }

        try {
            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();
            if ($this->ipAllowListBlocked) {
                return ['success' => false, 'message' => 'Access denied. Your IP address is not in the IP allow list configured for this app.'];
            }

            $skuWarehouses = $this->skuWarehouseInventoryRows($productId, $skuId);
            if ($skuWarehouses !== []) {
                $liveTotal = 0;
                foreach ($skuWarehouses as $row) {
                    $liveTotal += (int) ($row['quantity'] ?? 0);
                }
                if ($liveTotal === max(0, $quantity)) {
                    return ['success' => true, 'message' => 'Inventory already matches.'];
                }
                $rows = $this->inventoryRowsForPushQty($skuWarehouses, $quantity);
                $result = $this->sendInventoryRows($productId, $skuId, $rows);
                if (! empty($result['success'])) {
                    return $result;
                }
                $message = (string) ($result['message'] ?? '');
                $this->rememberIpAllowList($message);
                if ($this->ipAllowListBlocked) {
                    return ['success' => false, 'message' => $message];
                }
            }

            $warehouseId = $this->resolveDefaultWarehouseId();
            $result = $this->sendProductInventoryUpdate($productId, $skuId, $quantity, $warehouseId);
            if (! empty($result['success'])) {
                return $result;
            }

            $message = (string) ($result['message'] ?? 'TikTok inventory update failed.');
            $this->rememberIpAllowList($message);
            if ($this->ipAllowListBlocked) {
                return ['success' => false, 'message' => $message];
            }

            if (stripos($message, 'warehouse') !== false) {
                $skuWarehouse = $this->warehouseIdFromProductSku($productId, $skuId);
                if ($skuWarehouse !== null && $skuWarehouse !== '' && $skuWarehouse !== $warehouseId) {
                    $retry = $this->sendProductInventoryUpdate($productId, $skuId, $quantity, $skuWarehouse);
                    if (! empty($retry['success'])) {
                        return $retry;
                    }
                    $message = (string) ($retry['message'] ?? $message);
                }
            }

            return ['success' => false, 'message' => $message !== '' ? $message : 'TikTok inventory update failed.'];
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            $this->rememberIpAllowList($e->getMessage());
            if ($this->ipAllowListBlocked) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            if ($this->isInvalidApiVersionError($e->getMessage())) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            if (! $retried && $this->refreshAccessToken()) {
                return $this->updateProductInventory($productId, $skuId, $quantity, true);
            }

            return ['success' => false, 'message' => 'Token expired and refresh failed: '.$e->getMessage()];
        } catch (\Throwable $e) {
            $this->rememberIpAllowList($e->getMessage());
            Log::error('TikTok updateProductInventory failed', [
                'product_id' => $productId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Extra JSON fields (available_stock, sku_id) make TikTok return "no schema found".
     * LIVE listings are rejected by 202309 Update Inventory; Partial Edit (202509+) is the fallback.
     *
     * @return list<string>
     */
    protected function productDetailApiVersions(): array
    {
        return ['202509', '202405', '202312', '202309'];
    }

    /**
     * @return list<string>
     */
    protected function inventoryApiVersions(): array
    {
        return $this->productDetailApiVersions();
    }

    protected function isProductStatusRestrictionError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'operation not allowed')
            || str_contains($message, 'must be in one of these statuses')
            || str_contains($message, 'precondition required')
            || str_contains($message, 'valid product status')
            || (str_contains($message, 'seller_deactivated') && str_contains($message, 'activate'));
    }

    protected function isSalesAttributesError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'sales_attributes');
    }

    protected function isInvalidApiVersionError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'invalid api version')
            || str_contains($message, 'version query parameter is invalid')
            || str_contains($message, "version' query parameter is invalid")
            || str_contains($message, 'unsupported version');
    }

    protected function isNoSchemaError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'no schema found')
            || str_contains($message, 'schema not found');
    }

    protected function shouldRetryInventoryApiVersion(string $message): bool
    {
        return $this->isProductStatusRestrictionError($message)
            || $this->isInvalidApiVersionError($message)
            || $this->isNoSchemaError($message);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{warehouse_id?: string, quantity: int}
     */
    protected function inventoryPushRow(array $row, ?int $quantity = null): array
    {
        $qty = max(0, $quantity ?? (int) ($row['quantity'] ?? $row['available_stock'] ?? 0));
        $out = ['quantity' => $qty];
        $wid = trim((string) ($row['warehouse_id'] ?? ''));
        if ($wid !== '') {
            $out['warehouse_id'] = $wid;
        }

        return $out;
    }

    /**
     * LIVE Partial Edit requires each sales attribute to have `id` (built-in) or `name` (custom).
     *
     * @param  list<array{warehouse_id?: string, quantity: int}>  $rows
     * @return array{skus: list<array<string, mixed>>}
     */
    protected function partialEditInventoryParams(string $productId, string $skuId, array $rows, bool $forceSearch = false): array
    {
        $node = $this->skuNodeForPartialEdit($productId, $skuId, $forceSearch);
        $sku = [
            'id' => $skuId,
            'inventory' => $rows,
        ];
        $sellerSku = trim((string) ($node['seller_sku'] ?? $node['sku'] ?? ''));
        if ($sellerSku !== '') {
            $sku['seller_sku'] = $sellerSku;
        }
        $attrs = $this->sanitizeSalesAttributes(is_array($node['sales_attributes'] ?? null) ? $node['sales_attributes'] : []);
        if ($attrs !== []) {
            $sku['sales_attributes'] = $attrs;
        }

        return ['skus' => [$sku]];
    }

    /**
     * @param  list<mixed>  $attrs
     * @return list<array<string, string>>
     */
    protected function sanitizeSalesAttributes(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $id = trim((string) ($attr['id'] ?? $attr['attribute_id'] ?? ''));
            $name = trim((string) ($attr['name'] ?? $attr['attribute_name'] ?? ''));
            $valueId = trim((string) ($attr['value_id'] ?? ''));
            $valueName = trim((string) ($attr['value_name'] ?? $attr['value'] ?? ''));
            if ($id === '' && $name === '') {
                if ($valueName === '') {
                    continue;
                }
                $name = $valueName;
            }
            $row = [];
            if ($id !== '') {
                $row['id'] = $id;
            }
            if ($name !== '') {
                $row['name'] = $name;
            }
            if ($valueId !== '') {
                $row['value_id'] = $valueId;
            }
            if ($valueName !== '') {
                $row['value_name'] = $valueName;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function skuNodeForPartialEdit(string $productId, string $skuId, bool $forceSearch = false): array
    {
        $data = $this->searchProductDataById($productId);
        $node = $this->skuNodeFromProductData($data, $skuId);
        $attrs = $this->sanitizeSalesAttributes(is_array($node['sales_attributes'] ?? null) ? $node['sales_attributes'] : []);
        if ($node !== [] && ($attrs !== [] || ! $forceSearch)) {
            if ($attrs !== []) {
                $node['sales_attributes'] = $attrs;
            }

            return $node;
        }

        try {
            $data = $this->fetchProductData($productId);
            $detailNode = $this->skuNodeFromProductData($data, $skuId);
            if ($detailNode !== []) {
                return $detailNode;
            }
        } catch (\Throwable $e) {
            $this->rememberIpAllowList($e->getMessage());
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function skuNodeFromProductData(array $data, string $skuId): array
    {
        $skus = $data['skus'] ?? $data['sku_list'] ?? null;
        if (! is_array($skus) || $skus === []) {
            return [];
        }
        foreach ($skus as $sku) {
            if (! is_array($sku)) {
                continue;
            }
            $id = trim((string) ($sku['id'] ?? $sku['sku_id'] ?? ''));
            if ($skuId === '' || $id === $skuId) {
                return $sku;
            }
        }

        return [];
    }

    /**
     * Newer Search Products versions require a status. ALL/LIVE/ACTIVATE cover live listings.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function searchProductAttempts(string $productId): array
    {
        $bodies = [
            ['product_ids' => [$productId], 'status' => 'ALL'],
            ['product_ids' => [$productId], 'status' => 'LIVE'],
            ['product_ids' => [$productId], 'status' => 'ACTIVATE'],
            ['product_ids' => [$productId], 'status' => 'SELLER_DEACTIVATED'],
            ['product_ids' => [$productId]],
        ];
        $attempts = [];
        if ($this->workingProductSearchKey !== null) {
            [$version, $status] = array_pad(explode('|', $this->workingProductSearchKey, 2), 2, '');
            $body = ['product_ids' => [$productId]];
            if ($status !== '' && $status !== 'none') {
                $body['status'] = $status;
            }
            $attempts[] = [$version !== '' ? $version : '202509', $body];
        }
        foreach ($this->productDetailApiVersions() as $version) {
            foreach ($bodies as $body) {
                $attempts[] = [$version, $body];
            }
        }

        return $attempts;
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchProductDataById(string $productId): array
    {
        if ($productId === '') {
            return [];
        }
        if (array_key_exists($productId, $this->productSearchCache)) {
            return $this->productSearchCache[$productId];
        }

        $seen = [];
        $deadVersions = [];
        foreach ($this->searchProductAttempts($productId) as [$version, $body]) {
            $status = trim((string) ($body['status'] ?? 'none'));
            $key = $version.'|'.$status;
            if (isset($seen[$key]) || isset($deadVersions[$version])) {
                continue;
            }
            $seen[$key] = true;
            try {
                $response = $this->client->Product->useVersion($version)->searchProducts(
                    ['page_size' => 20],
                    $body
                );
            } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    $deadVersions[$version] = true;
                    continue;
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->rememberIpAllowList($e->getMessage());
                if ($this->ipAllowListBlocked) {
                    return [];
                }
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    $deadVersions[$version] = true;
                    continue;
                }
                if ($this->isProductStatusRestrictionError($e->getMessage())) {
                    continue;
                }
                Log::info('TikTok searchProducts for inventory SKU failed', [
                    'product_id' => $productId,
                    'version' => $version,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $payload = is_array($response) ? ($response['data'] ?? $response) : [];
            if (! is_array($payload)) {
                $payload = [];
            }
            if (is_array($payload) && array_key_exists('code', $payload) && (int) $payload['code'] !== 0) {
                $message = (string) ($payload['message'] ?? '');
                if ($this->isProductStatusRestrictionError($message)
                    || $this->isInvalidApiVersionError($message)
                    || $this->isNoSchemaError($message)) {
                    continue;
                }
            }
            $products = $payload['products'] ?? $payload['product_list'] ?? null;
            if (! is_array($products)) {
                $products = [];
            }
            foreach ($products as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $id = trim((string) ($product['id'] ?? $product['product_id'] ?? ''));
                if ($id === $productId) {
                    $this->workingProductSearchKey = $key;
                    $this->productSearchCache[$productId] = $product;

                    return $product;
                }
            }
        }

        $this->productSearchCache[$productId] = [];

        return [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, message: string, retry?: bool}
     */
    protected function invokeSdkInventory(string $productId, array $params, string $version, string $method): array
    {
        try {
            $product = $this->client->Product->useVersion($version);
            if ($method === 'partial') {
                $response = $product->partialEditProduct($productId, $params);
            } else {
                $response = $product->updateInventory($productId, $params);
            }
            $this->lastResponse = $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                return ['success' => false, 'message' => $e->getMessage(), 'retry' => true];
            }
            throw $e;
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            return [
                'success' => false,
                'message' => $message,
                'retry' => $this->shouldRetryInventoryApiVersion($message),
            ];
        }

        if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
            $message = (string) ($response['message'] ?? 'TikTok inventory update failed.');

            return [
                'success' => false,
                'message' => $message,
                'retry' => $this->shouldRetryInventoryApiVersion($message),
            ];
        }

        return ['success' => true, 'message' => 'Inventory updated.'];
    }

    /**
     * @param  list<array{warehouse_id?: string, quantity?: int, available_stock?: int}>  $inventoryRows
     * @return array{success: bool, message: string}
     */
    protected function postInventoryUpdate(string $productId, string $skuId, array $inventoryRows): array
    {
        $rows = [];
        foreach ($inventoryRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = $this->inventoryPushRow($row);
        }
        $rows = $this->ensureInventoryRowsHaveWarehouse($rows);
        if ($rows === []) {
            return ['success' => false, 'message' => 'No TikTok warehouse rows to update.'];
        }

        $lastMessage = 'TikTok inventory update failed.';
        $sawStatusError = false;

        foreach ($this->inventoryUpdateAttempts($productId) as $path) {
            if (isset($this->deadInventoryKeys[$path])) {
                continue;
            }
            [$version, $method] = array_pad(explode('|', $path, 2), 2, 'partial');
            if ($method === 'inventory' && $this->skipInventoryUpdateApi && $version === '202309') {
                continue;
            }

            $params = $method === 'partial'
                ? $this->partialEditInventoryParams($productId, $skuId, $rows)
                : ['skus' => [['id' => $skuId, 'inventory' => $rows]]];
            $result = $this->invokeSdkInventory($productId, $params, $version, $method);
            if (! empty($result['success'])) {
                $this->workingInventoryPath = $path;

                return $result;
            }

            $lastMessage = (string) ($result['message'] ?? $lastMessage);
            $this->rememberIpAllowList($lastMessage);
            if ($this->ipAllowListBlocked) {
                return ['success' => false, 'message' => $lastMessage];
            }

            if ($this->isSalesAttributesError($lastMessage) && $method === 'partial') {
                $retry = $this->invokeSdkInventory(
                    $productId,
                    $this->partialEditInventoryParams($productId, $skuId, $rows, true),
                    $version,
                    'partial'
                );
                if (! empty($retry['success'])) {
                    $this->workingInventoryPath = $path;

                    return $retry;
                }
                $lastMessage = (string) ($retry['message'] ?? $lastMessage);
            }

            if ($this->isProductStatusRestrictionError($lastMessage)
                || $this->isNoSchemaError($lastMessage)
                || $this->isInvalidApiVersionError($lastMessage)) {
                $this->deadInventoryKeys[$path] = true;
                if ($method === 'inventory' && $version === '202309') {
                    $this->skipInventoryUpdateApi = true;
                }
                if ($this->isProductStatusRestrictionError($lastMessage)) {
                    $sawStatusError = true;
                }
            }

            Log::info('TikTok inventory update attempt failed', [
                'product_id' => $productId,
                'sku_id' => $skuId,
                'version' => $version,
                'method' => $method,
                'error' => $lastMessage,
            ]);
        }

        if ($sawStatusError && $this->activateProductForInventory($productId)) {
            $retryRows = $this->ensureInventoryRowsHaveWarehouse($rows);
            $retryPaths = $this->workingInventoryPath
                ? [$this->workingInventoryPath]
                : ['202509|partial', '202309|inventory'];
            foreach ($retryPaths as $path) {
                [$version, $method] = array_pad(explode('|', $path, 2), 2, 'partial');
                $params = $method === 'partial'
                    ? $this->partialEditInventoryParams($productId, $skuId, $retryRows, true)
                    : ['skus' => [['id' => $skuId, 'inventory' => $retryRows]]];
                $retry = $this->invokeSdkInventory($productId, $params, $version, $method);
                if (! empty($retry['success'])) {
                    $this->workingInventoryPath = $path;

                    return $retry;
                }
                $lastMessage = (string) ($retry['message'] ?? $lastMessage);
            }
        }

        return ['success' => false, 'message' => $lastMessage !== '' ? $lastMessage : 'TikTok inventory update failed.'];
    }

    /**
     * LIVE listings need Partial Edit (202509 first). Draft/pending still use Update Inventory.
     *
     * @return list<string>
     */
    protected function inventoryUpdateAttempts(string $productId): array
    {
        $paths = [];
        if ($this->workingInventoryPath) {
            $paths[] = $this->workingInventoryPath;
        }

        $liveLike = $this->productIsLiveLike($productId);
        $methods = $liveLike ? ['partial', 'inventory'] : ['inventory', 'partial'];
        foreach ($methods as $method) {
            foreach ($this->inventoryApiVersions() as $version) {
                $paths[] = $version.'|'.$method;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<array{warehouse_id?: string, quantity: int}>  $rows
     * @return list<array{warehouse_id?: string, quantity: int}>
     */
    protected function ensureInventoryRowsHaveWarehouse(array $rows): array
    {
        $missing = false;
        foreach ($rows as $row) {
            if (trim((string) ($row['warehouse_id'] ?? '')) === '') {
                $missing = true;
                break;
            }
        }
        if (! $missing) {
            return $rows;
        }

        $wid = trim((string) ($this->resolveDefaultWarehouseId() ?? ''));
        if ($wid === '') {
            return $rows;
        }

        foreach ($rows as $i => $row) {
            if (trim((string) ($row['warehouse_id'] ?? '')) === '') {
                $rows[$i]['warehouse_id'] = $wid;
            }
        }

        return $rows;
    }

    protected function cachedProductStatus(string $productId): string
    {
        $data = $this->productSearchCache[$productId] ?? $this->productDetailCache[$productId] ?? [];

        return strtoupper(trim((string) (
            $data['status'] ?? $data['product_status'] ?? $data['listing_status'] ?? ''
        )));
    }

    protected function productIsLiveLike(string $productId): bool
    {
        $status = $this->cachedProductStatus($productId);

        return $status === '' || in_array($status, ['LIVE', 'ACTIVATE', 'ACTIVE', 'APPROVED'], true);
    }

    protected function productIsSellerDeactivated(string $productId): bool
    {
        $status = $this->cachedProductStatus($productId);

        return in_array($status, ['SELLER_DEACTIVATED', 'DEACTIVATED', 'INACTIVE'], true);
    }

    protected function activateProductForInventory(string $productId): bool
    {
        $productId = trim($productId);
        if ($productId === '' || isset($this->activatedForInventory[$productId])) {
            return false;
        }
        if ($this->cachedProductStatus($productId) === '') {
            $this->searchProductDataById($productId);
        }
        if (! $this->productIsSellerDeactivated($productId)) {
            return false;
        }
        $this->activatedForInventory[$productId] = true;

        foreach ($this->productDetailApiVersions() as $version) {
            try {
                $response = $this->client->Product->useVersion($version)->activateProducts([$productId]);
                $this->lastResponse = $response;
            } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    continue;
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->rememberIpAllowList($e->getMessage());
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    continue;
                }
                Log::info('TikTok activate product for inventory failed', [
                    'product_id' => $productId,
                    'version' => $version,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
                $message = (string) ($response['message'] ?? '');
                if ($this->isInvalidApiVersionError($message) || $this->isNoSchemaError($message)) {
                    continue;
                }
                Log::info('TikTok activate product for inventory failed', [
                    'product_id' => $productId,
                    'version' => $version,
                    'error' => $message,
                ]);

                return false;
            }

            unset($this->productSearchCache[$productId], $this->productDetailCache[$productId]);
            usleep(200000);

            return true;
        }

        return false;
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function sendProductInventoryUpdate(string $productId, string $skuId, int $quantity, ?string $warehouseId): array
    {
        $row = $this->inventoryPushRow(
            ['warehouse_id' => (string) ($warehouseId ?? '')],
            max(0, $quantity)
        );

        return $this->postInventoryUpdate($productId, $skuId, [$row]);
    }

    /**
     * @param  list<array{warehouse_id: string, quantity: int}>  $inventoryRows
     * @return array{success: bool, message: string}
     */
    protected function sendInventoryRows(string $productId, string $skuId, array $inventoryRows): array
    {
        if ($inventoryRows === []) {
            return ['success' => false, 'message' => 'No TikTok warehouse rows to update.'];
        }

        return $this->postInventoryUpdate($productId, $skuId, $inventoryRows);
    }

    /**
     * @param  list<array{warehouse_id: string, quantity: int}>  $warehouses
     * @return list<array{warehouse_id: string, quantity: int}>
     */
    protected function inventoryRowsForPushQty(array $warehouses, int $quantity): array
    {
        $bestWid = '';
        $bestQty = -1;
        foreach ($warehouses as $row) {
            $wid = trim((string) ($row['warehouse_id'] ?? ''));
            if ($wid === '') {
                continue;
            }
            $q = (int) ($row['quantity'] ?? 0);
            if ($bestWid === '' || $q > $bestQty) {
                $bestQty = $q;
                $bestWid = $wid;
            }
        }

        $out = [];
        $seen = [];
        foreach ($warehouses as $row) {
            $wid = trim((string) ($row['warehouse_id'] ?? ''));
            if ($wid === '' || isset($seen[$wid])) {
                continue;
            }
            $seen[$wid] = true;
            $out[] = [
                'warehouse_id' => $wid,
                'quantity' => $wid === $bestWid ? max(0, $quantity) : 0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{warehouse_id: string, quantity: int}>
     */
    protected function skuWarehouseInventoryRows(string $productId, string $skuId): array
    {
        $fromSearch = $this->skuWarehouseRowsFromInventorySearch($productId, $skuId);
        if ($fromSearch !== []) {
            return $fromSearch;
        }

        // Get Product 202309 rejects LIVE listings and can wait the full HTTP
        // timeout per SKU. Fall through to the shop default warehouse instead.

        return [];
    }

    /**
     * Inventory Search works on LIVE listings; Get Product 202309 does not.
     *
     * @return list<array{warehouse_id: string, quantity: int}>
     */
    protected function skuWarehouseRowsFromInventorySearch(string $productId, string $skuId): array
    {
        try {
            $data = $this->fetchInventorySearchData($productId, $skuId);

            return $this->extractWarehouseRowsFromInventorySearch($data, $productId, $skuId);
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                return [];
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->rememberIpAllowList($e->getMessage());
            Log::info('TikTok inventorySearch for warehouse rows failed', [
                'product_id' => $productId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchInventorySearchData(string $productId, string $skuId): array
    {
        if ($productId !== '' && array_key_exists($productId, $this->inventorySearchCache)) {
            $cached = $this->inventorySearchCache[$productId];
            if ($skuId === '' || $this->extractWarehouseRowsFromInventorySearch($cached, $productId, $skuId) !== []) {
                return $cached;
            }
        }

        $skuCacheKey = $skuId !== '' ? 'sku:'.$skuId : '';
        if ($skuCacheKey !== '' && array_key_exists($skuCacheKey, $this->inventorySearchCache)) {
            return $this->inventorySearchCache[$skuCacheKey];
        }

        $attempts = [];
        if ($productId !== '') {
            $attempts[] = ['body' => ['product_ids' => [$productId]], 'cache' => $productId];
        }

        $data = [];
        foreach ($attempts as $attempt) {
            try {
                $response = $this->client->Product->inventorySearch($attempt['body']);
            } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    return [];
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->rememberIpAllowList($e->getMessage());
                continue;
            }

            $payload = is_array($response) ? ($response['data'] ?? $response) : [];
            if (! is_array($payload)) {
                $payload = [];
            }
            $cacheKey = (string) ($attempt['cache'] ?? '');
            if ($cacheKey !== '' && $payload !== []) {
                $this->inventorySearchCache[$cacheKey] = $payload;
            }
            if ($this->extractWarehouseRowsFromInventorySearch($payload, $productId, $skuId) !== []) {
                return $payload;
            }
            if ($data === [] && $payload !== []) {
                $data = $payload;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{warehouse_id: string, quantity: int}>
     */
    protected function extractWarehouseRowsFromInventorySearch(array $data, string $productId, string $skuId): array
    {
        $items = $data['inventory']
            ?? $data['inventories']
            ?? $data['products']
            ?? $data['skus']
            ?? null;
        if (! is_array($items) || $items === []) {
            $items = array_is_list($data) ? $data : [$data];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemProductId = trim((string) ($item['product_id'] ?? $item['id'] ?? ''));
            $skus = $item['skus'] ?? $item['sku_list'] ?? null;
            if (! is_array($skus) || $skus === []) {
                $skus = [$item];
            }

            foreach ($skus as $sku) {
                if (! is_array($sku)) {
                    continue;
                }
                $rowSkuId = trim((string) ($sku['id'] ?? $sku['sku_id'] ?? ''));
                if ($skuId !== '' && $rowSkuId !== $skuId) {
                    continue;
                }
                if ($productId !== '' && $itemProductId !== '' && $itemProductId !== $productId) {
                    continue;
                }

                $warehouseSource = $sku;
                if (is_array($sku['warehouse_inventory'] ?? null)) {
                    $warehouseSource = ['inventory' => $sku['warehouse_inventory']];
                }
                $rows = self::skuNodeWarehouseRows($warehouseSource);
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchProductData(string $productId): array
    {
        if (array_key_exists($productId, $this->productDetailCache)) {
            return $this->productDetailCache[$productId];
        }

        $lastError = null;
        foreach ($this->productDetailApiVersions() as $version) {
            try {
                $response = $this->client->Product->useVersion($version)->getProduct($productId, [
                    'return_under_review_version' => false,
                ]);
            } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    $lastError = $e;
                    continue;
                }
                throw $e;
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($this->isProductStatusRestrictionError($e->getMessage())
                    || $this->isInvalidApiVersionError($e->getMessage())
                    || $this->isNoSchemaError($e->getMessage())) {
                    continue;
                }
                throw $e;
            }

            $data = is_array($response) ? ($response['data'] ?? $response) : [];
            if (! is_array($data)) {
                $data = [];
            }
            if (! isset($data['skus']) && is_array($data['data'] ?? null)) {
                $data = $data['data'];
            }

            $this->productDetailCache[$productId] = $data;

            return $data;
        }

        if ($lastError !== null) {
            Log::info('TikTok getProduct for inventory skipped', [
                'product_id' => $productId,
                'error' => $lastError->getMessage(),
            ]);
        }

        $this->productDetailCache[$productId] = [];

        return [];
    }

    protected function warehouseIdFromProductSku(string $productId, string $skuId): ?string
    {
        $rows = $this->skuWarehouseInventoryRows($productId, $skuId);
        foreach ($rows as $row) {
            $wid = trim((string) ($row['warehouse_id'] ?? ''));
            if ($wid !== '') {
                return $wid;
            }
        }

        return null;
    }

    protected function cacheWarehouseId(string $cacheKey, string $value, int $ttl): void
    {
        try {
            Cache::put($cacheKey, $value, $ttl);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function resolveDefaultWarehouseId(): ?string
    {
        $fromConfig = trim((string) (config('services.'.$this->configKey.'.warehouse_id') ?? ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $cacheKey = $this->cachePrefix.'_default_warehouse_id';

        try {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached === '__none__' ? null : $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $response = $this->client->Logistic->getWarehouseList();
            if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
                $message = (string) ($response['message'] ?? 'TikTok warehouse list failed.');
                $this->rememberIpAllowList($message);
                $this->cacheWarehouseId($cacheKey, '__none__', 600);
                Log::warning('TikTok resolveDefaultWarehouseId failed', ['error' => $message]);

                return null;
            }
            $warehouses = [];
            if (is_array($response)) {
                $warehouses = $response['warehouses']
                    ?? (is_array($response['data'] ?? null) ? ($response['data']['warehouses'] ?? $response['data']) : null)
                    ?? (array_is_list($response) ? $response : []);
            }
            if (! is_array($warehouses)) {
                $warehouses = [];
            }

            $fallback = null;
            foreach ($warehouses as $wh) {
                if (! is_array($wh)) {
                    continue;
                }
                $id = trim((string) ($wh['id'] ?? $wh['warehouse_id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $fallback ??= $id;
                $type = strtoupper((string) ($wh['type'] ?? $wh['warehouse_type'] ?? ''));
                $isDefault = ! empty($wh['is_default'])
                    || str_contains($type, 'SALES')
                    || str_contains($type, 'DEFAULT');
                if ($isDefault) {
                    $this->cacheWarehouseId($cacheKey, $id, 3600);

                    return $id;
                }
            }

            $this->cacheWarehouseId($cacheKey, $fallback ?? '__none__', $fallback ? 3600 : 600);

            return $fallback;
        } catch (\Throwable $e) {
            $this->rememberIpAllowList($e->getMessage());
            $this->cacheWarehouseId($cacheKey, '__none__', 600);
            Log::warning('TikTok resolveDefaultWarehouseId failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Mark a TikTok order as shipped with tracking info.
     *
     * @param  string  $orderId           TikTok order ID
     * @param  string  $trackingNumber    Carrier tracking number
     * @param  string  $shippingProvider  Shipping provider ID (required by TikTok)
     * @return array{success: bool, message: string}
     */
    public function markOrderShipped(string $orderId, string $trackingNumber, string $shippingProvider = ''): array
    {
        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok access token not available.'];
        }

        if ($shippingProvider === '') {
            return ['success' => false, 'message' => 'TikTok shipping_provider_id is required to mark shipped.'];
        }

        try {
            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            // SDK: Fulfillment::markPackageAsShipped($order_id, $tracking_number, $shipping_provider_id, $line_ids)
            $response = $this->client->Fulfillment->markPackageAsShipped(
                $orderId,
                $trackingNumber,
                $shippingProvider,
                []
            );
            $this->lastResponse = $response;

            if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
                return ['success' => false, 'message' => (string) ($response['message'] ?? 'TikTok ship order failed.')];
            }

            return ['success' => true, 'message' => "Order {$orderId} marked shipped."];
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->refreshAccessToken()) {
                return $this->markOrderShipped($orderId, $trackingNumber, $shippingProvider);
            }

            return ['success' => false, 'message' => 'Token expired and refresh failed: '.$e->getMessage()];
        } catch (\Throwable $e) {
            // Fallback: update shipping info if package already exists
            try {
                $response = $this->client->Fulfillment->updateShippingInfo($orderId, $trackingNumber, $shippingProvider);
                $this->lastResponse = $response;
                if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
                    return ['success' => false, 'message' => (string) ($response['message'] ?? $e->getMessage())];
                }

                return ['success' => true, 'message' => "Order {$orderId} shipping info updated."];
            } catch (\Throwable $e2) {
                Log::error('TikTok markOrderShipped failed', [
                    'order_id' => $orderId,
                    'tracking' => $trackingNumber,
                    'error' => $e->getMessage(),
                    'fallback_error' => $e2->getMessage(),
                ]);

                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * Shipping providers for a delivery option (correct API for shipping_provider_id).
     *
     * @return list<array<string, mixed>>
     */
    public function getShippingProvidersForDeliveryOption(string $deliveryOptionId): array
    {
        $deliveryOptionId = trim($deliveryOptionId);
        if ($deliveryOptionId === '' || ! $this->accessToken) {
            return [];
        }

        try {
            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $response = $this->client->Logistic->getShippingProvider($deliveryOptionId);
            $this->lastResponse = $response;

            $list = $response['shipping_providers']
                ?? $response['data']['shipping_providers']
                ?? $response['providers']
                ?? [];

            return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
        } catch (\Throwable $e) {
            Log::warning('TikTok getShippingProvidersForDeliveryOption failed', [
                'delivery_option_id' => $deliveryOptionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Query shipping providers for an order.
     * Prefer delivery_option_id → Logistics API; fall back to eligible shipping services.
     *
     * @return array|null
     */
    public function getShippingProviders(string $orderId, string $deliveryOptionId = ''): ?array
    {
        try {
            if (! $this->accessToken) {
                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $deliveryOptionId = trim($deliveryOptionId);
            if ($deliveryOptionId === '') {
                $deliveryOptionId = $this->resolveDeliveryOptionIdForOrder($orderId);
            }

            if ($deliveryOptionId !== '') {
                $fromLogistics = $this->getShippingProvidersForDeliveryOption($deliveryOptionId);
                if ($fromLogistics !== []) {
                    return ['shipping_providers' => $fromLogistics, 'delivery_option_id' => $deliveryOptionId];
                }
            }

            $response = $this->client->Fulfillment->getEligibleShippingService($orderId, []);
            $this->lastResponse = $response;

            return $response['shipping_services']
                ?? $response['shipping_providers']
                ?? $response['data']['shipping_services']
                ?? $response['data']['shipping_providers']
                ?? (is_array($response) ? $response : null);
        } catch (\Throwable $e) {
            Log::warning('TikTok getShippingProviders failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Pull delivery_option_id from order detail API when missing on the local row.
     */
    public function resolveDeliveryOptionIdForOrder(string $orderId): string
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return '';
        }

        try {
            $details = $this->getOrderDetails([$orderId]);
            $orders = $details['orders'] ?? $details['data']['orders'] ?? [];
            if (! is_array($orders) || $orders === []) {
                return '';
            }
            $order = $orders[0] ?? null;
            if (! is_array($order)) {
                return '';
            }

            $id = trim((string) (
                $order['delivery_option_id']
                ?? $order['shipping_provider_id']
                ?? ($order['packages'][0]['delivery_option_id'] ?? '')
                ?? ''
            ));

            return $id;
        } catch (\Throwable $e) {
            Log::warning('TikTok resolveDeliveryOptionIdForOrder failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function resolveTikTokProductIdForIdentifier(string $identifier): ?string
    {
        if (preg_match('/^\d+$/', $identifier)) {
            return $identifier;
        }

        $cursor = '';
        do {
            $response = $this->getProducts(50, $cursor);
            if (! $response || (isset($response['code']) && (int) $response['code'] !== 0)) {
                break;
            }

            foreach (($response['data']['products'] ?? []) as $product) {
                $productId = (string) ($product['id'] ?? $product['product_id'] ?? '');
                foreach (($product['skus'] ?? []) as $skuRow) {
                    $sellerSku = trim((string) ($skuRow['seller_sku'] ?? $skuRow['sku'] ?? ''));
                    if ($sellerSku !== '' && strcasecmp($sellerSku, $identifier) === 0 && $productId !== '') {
                        return $productId;
                    }
                }
            }

            $cursor = (string) ($response['data']['next_page_token'] ?? $response['data']['cursor'] ?? '');
        } while ($cursor !== '');

        return null;
    }

    /**
     * Unread TikTok Shop customer-service conversations.
     *
     * @return array{success: bool, count: int, message?: string}
     */
    public function getPendingConversationCount(): array
    {
        if (! $this->accessToken || ! $this->clientKey || ! $this->clientSecret) {
            return ['success' => false, 'count' => 0, 'message' => 'TikTok credentials are missing.'];
        }

        $this->client->setAccessToken($this->accessToken);
        $this->ensureShopCipher();
        $cipher = is_string($this->shopCipher) ? trim($this->shopCipher) : '';
        if ($cipher === '') {
            return ['success' => false, 'count' => 0, 'message' => 'TikTok shop_cipher is missing.'];
        }

        $unread = 0;
        $pageToken = '';
        $pages = 0;
        $base = rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: 'https://open-api.tiktokglobalshop.com'), '/');
        $path = '/customer_service/202309/conversations';

        do {
            $pages++;
            $query = [
                'app_key' => (string) $this->clientKey,
                'timestamp' => (string) time(),
                'shop_cipher' => $cipher,
                'page_size' => '50',
                'locale' => 'en-US',
            ];
            if ($pageToken !== '') {
                $query['page_token'] = $pageToken;
            }
            $query['sign'] = $this->signTikTokRequest($path, $query, '');

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'x-tts-access-token' => $this->accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(12)
                    ->connectTimeout(6)
                    ->get($base.$path, $query);
            } catch (\Throwable $e) {
                return ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
            }

            $json = $response->json() ?? [];
            $code = (int) ($json['code'] ?? $response->status());
            if (! $response->successful() || ($code !== 0 && isset($json['code']))) {
                return [
                    'success' => false,
                    'count' => 0,
                    'message' => (string) ($json['message'] ?? $json['msg'] ?? ('TikTok conversations HTTP '.$response->status())),
                ];
            }

            $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
            foreach ($data['conversations'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $unread += max(0, (int) ($row['unread_count'] ?? $row['unreadCount'] ?? 0));
            }
            $pageToken = (string) ($data['next_page_token'] ?? '');
        } while ($pageToken !== '' && $pages < 10);

        return ['success' => true, 'count' => $unread];
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function signTikTokRequest(string $path, array $query, string $body): string
    {
        unset($query['sign'], $query['access_token']);
        ksort($query);
        $concat = '';
        foreach ($query as $key => $value) {
            $concat .= $key.$value;
        }
        $secret = (string) $this->clientSecret;
        $source = $secret.$path.$concat.$body.$secret;

        return hash_hmac('sha256', $source, $secret);
    }
}
