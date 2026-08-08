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
        foreach ($shops as $shop) {
            $cipher = trim((string) ($shop['cipher'] ?? ''));
            if ($cipher === '') {
                continue;
            }
            $this->rememberShopCipher($cipher);
            $shopId = trim((string) ($shop['id'] ?? $shop['shop_id'] ?? ''));
            if ($shopId !== '') {
                Cache::put($this->cachePrefix.'_shop_id', $shopId, 86400 * 30);
            }

            return $cipher;
        }

        return null;
    }

    protected function rememberShopCipher(string $cipher): void
    {
        $cipher = trim($cipher);
        if ($cipher === '') {
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

        return $cipher !== '' ? $cipher : null;
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
     * Fetch all orders in a create-time window with page_token pagination.
     *
     * @return list<array>
     */
    public function getAllOrders(int $createTimeGe, int $createTimeLt, ?string $orderStatus = null, int $pageSize = 50): array
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

            $orders = $response['orders'] ?? $response['data']['orders'] ?? [];
            if (! is_array($orders)) {
                $orders = [];
            }

            if ($orders !== []) {
                $all = array_merge($all, $orders);
            }

            $total = $response['total_count'] ?? null;
            $this->output(
                'info',
                "Orders page {$page}: +".count($orders).' (running '.count($all)
                .($total !== null ? " / {$total}" : '').')'
            );

            $next = (string) ($response['next_page_token'] ?? '');
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
     * Get all product inventory - extract from product data (skus array)
     * TikTok product data includes SKU-level inventory information
     */
    public function getAllProductInventory(array $products): array
    {
        $allInventory = [];
        
        if (empty($products)) {
            $this->output('warn', 'getAllProductInventory: No products provided');
            return $allInventory;
        }
        
        $this->output('info', 'getAllProductInventory: Extracting inventory from ' . count($products) . ' products');
        
        // Extract inventory from product data (skus array contains inventory info)
        foreach ($products as $product) {
            $productId = $product['id'] ?? $product['product_id'] ?? null;
            if (!$productId) {
                continue;
            }
            
            // Check if product has skus array with inventory data
            $skus = $product['skus'] ?? [];
            
            if (!empty($skus) && is_array($skus)) {
                // Sum inventory across SKUs. TikTok shape: skus[].inventory[].quantity
                $totalStock = 0;
                foreach ($skus as $sku) {
                    $stock = 0;
                    if (isset($sku['inventory']) && is_array($sku['inventory'])) {
                        if (array_is_list($sku['inventory'])) {
                            foreach ($sku['inventory'] as $invRow) {
                                if (is_array($invRow)) {
                                    $stock += (int) ($invRow['quantity']
                                        ?? $invRow['available_stock']
                                        ?? $invRow['stock']
                                        ?? 0);
                                }
                            }
                        } else {
                            $stock = (int) ($sku['inventory']['quantity']
                                ?? $sku['inventory']['available_stock']
                                ?? $sku['inventory']['stock']
                                ?? 0);
                        }
                    }
                    if ($stock <= 0) {
                        $stock = (int) ($sku['available_stock']
                            ?? $sku['stock']
                            ?? $sku['inventory_quantity']
                            ?? $sku['quantity']
                            ?? 0);
                    }
                    $totalStock += $stock;
                }

                $allInventory[] = [
                    'product_id' => (string) $productId,
                    'available_stock' => $totalStock,
                    'stock' => $totalStock,
                ];
            } else {
                // Try to get inventory from product-level fields
                $stock = $product['available_stock'] 
                    ?? $product['stock'] 
                    ?? $product['inventory_quantity'] 
                    ?? $product['inventory']['available_stock'] 
                    ?? 0;
                
                if ($stock > 0) {
                    $allInventory[] = [
                        'product_id' => (string)$productId,
                        'available_stock' => (int)$stock,
                        'stock' => (int)$stock,
                    ];
                }
            }
        }
        
        $this->output('info', 'getAllProductInventory: Extracted inventory for ' . count($allInventory) . ' products');
        return $allInventory;
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
        if (is_string($cached) && trim($cached) !== '') {
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
        $auth = $this->client->auth();
        $state = bin2hex(random_bytes(16));
        Cache::put($this->cachePrefix.'_oauth_state', $state, 600);
        return $auth->createAuthRequest($state, true);
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

    /**
     * Update inventory for a single SKU on TikTok Shop.
     *
     * @param  string  $productId  TikTok product ID
     * @param  string  $skuId      TikTok SKU ID (from product.skus[].id)
     * @param  int     $quantity   Available quantity to set
     * @return array{success: bool, message: string}
     */
    public function updateProductInventory(string $productId, string $skuId, int $quantity): array
    {
        if (! $this->accessToken) {
            return ['success' => false, 'message' => 'TikTok access token not available.'];
        }

        try {
            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

            $warehouseId = $this->resolveDefaultWarehouseId();
            $inventoryRow = ['quantity' => max(0, $quantity)];
            if ($warehouseId !== null && $warehouseId !== '') {
                $inventoryRow['warehouse_id'] = $warehouseId;
            }

            // SDK: Product::updateInventory($product_id, $params)
            $params = [
                'skus' => [
                    [
                        'id' => $skuId,
                        'inventory' => [$inventoryRow],
                    ],
                ],
            ];

            $response = $this->client->Product->updateInventory($productId, $params);
            $this->lastResponse = $response;

            // Library may return data payload or wrapped {code, message, data}
            if (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0) {
                $message = (string) ($response['message'] ?? 'TikTok inventory update failed.');
                // Retry once with the warehouse from the product SKU if default warehouse was rejected.
                if ($warehouseId && (stripos($message, 'warehouse') !== false)) {
                    $skuWarehouse = $this->warehouseIdFromProductSku($productId, $skuId);
                    if ($skuWarehouse && $skuWarehouse !== $warehouseId) {
                        $params['skus'][0]['inventory'][0]['warehouse_id'] = $skuWarehouse;
                        $response = $this->client->Product->updateInventory($productId, $params);
                        $this->lastResponse = $response;
                        if (! (is_array($response) && array_key_exists('code', $response) && (int) $response['code'] !== 0)) {
                            return ['success' => true, 'message' => 'Inventory updated.'];
                        }
                        $message = (string) ($response['message'] ?? $message);
                    }
                }

                return ['success' => false, 'message' => $message];
            }

            return ['success' => true, 'message' => 'Inventory updated.'];
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->refreshAccessToken()) {
                return $this->updateProductInventory($productId, $skuId, $quantity);
            }

            return ['success' => false, 'message' => 'Token expired and refresh failed: '.$e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('TikTok updateProductInventory failed', [
                'product_id' => $productId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function warehouseIdFromProductSku(string $productId, string $skuId): ?string
    {
        try {
            $response = $this->client->Product->getProduct($productId);
            $data = is_array($response) ? ($response['data'] ?? $response) : [];
            $skus = is_array($data['skus'] ?? null) ? $data['skus'] : [];

            foreach ($skus as $sku) {
                if (! is_array($sku) || (string) ($sku['id'] ?? '') !== $skuId) {
                    continue;
                }
                $inventory = $sku['inventory'] ?? [];
                if (! is_array($inventory)) {
                    break;
                }
                $rows = array_is_list($inventory) ? $inventory : [$inventory];
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $wid = trim((string) ($row['warehouse_id'] ?? ''));
                    if ($wid !== '') {
                        return $wid;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('TikTok warehouseIdFromProductSku failed', [
                'product_id' => $productId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function resolveDefaultWarehouseId(): ?string
    {
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
                    try {
                        Cache::put($cacheKey, $id, 3600);
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    return $id;
                }
            }

            try {
                Cache::put($cacheKey, $fallback ?? '__none__', $fallback ? 3600 : 600);
            } catch (\Throwable $e) {
                // ignore
            }

            return $fallback;
        } catch (\Throwable $e) {
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
     * Query eligible shipping services / providers for an order (best-effort).
     *
     * @return array|null
     */
    public function getShippingProviders(string $orderId): ?array
    {
        try {
            if (! $this->accessToken) {
                return null;
            }

            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();

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
}
