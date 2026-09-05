<?php

namespace App\Services;

use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Services\Support\Concerns\HandlesMarketplaceApiExceptions;
use App\Services\Support\MarketplaceCharacterLimits;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;
use App\Support\Marketplace\ListingManagerAmazonHydrator;

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

    /** LIVE listings reject Update Inventory; skip it for the rest of this request. */
    protected bool $skipInventoryUpdateApi = false;

    public function __construct()
    {
        $cfg = config('services.'.$this->configKey, []);
        $this->clientKey = $cfg['client_key'] ?? null;
        $this->clientSecret = $cfg['client_secret'] ?? null;
        $this->shopId = $cfg['shop_id'] ?? null;

        // Get tokens from cache first, then fallback to env/config
        $this->accessToken = Cache::get($this->cachePrefix.'_access_token') ?? ($cfg['access_token'] ?? null);
        $this->refreshToken = Cache::get($this->cachePrefix.'_refresh_token') ?? ($cfg['refresh_token'] ?? null);
        try {
            $this->skipInventoryUpdateApi = (bool) Cache::get($this->cachePrefix.'_skip_inventory_update_api', false);
        } catch (\Throwable $e) {
            // ignore
        }

        // Initialize the TikTok Shop client library (same as ship_hub).
        // Explicit timeouts — Guzzle defaults to 0 (wait forever), which freezes listings sync.
        // XAMPP / Windows antivirus SSL inspection raises cURL 60 on open-api.tiktokglobalshop.com.
        $this->client = new Client($this->clientKey, $this->clientSecret, [
            'timeout' => 45,
            'connect_timeout' => 10,
            'verify' => false,
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

    /**
     * SKU performance list (may include extra money fields the product list omits).
     */
    public function getSkuAnalytics(?string $startDate = null, ?string $endDate = null, string $pageToken = ''): ?array
    {
        try {
            if (! $this->accessToken) {
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
            if ($pageToken !== '') {
                $params['page_token'] = $pageToken;
            }

            $response = $this->withAnalyticsApi(
                fn () => $this->client->Analytics->getShopSkuPerformanceList($params)
            );
            $this->lastResponse = $response;

            return is_array($response) ? $response : null;
        } catch (\Throwable $e) {
            Log::warning('TikTok getSkuAnalytics failed: '.$e->getMessage());

            return null;
        }
    }

    protected function ensureShopCipher(bool $allowLiveLookup = true): void
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
        if (is_string($cfgCipher) && $this->isUsableShopCipher($cfgCipher)) {
            $this->rememberShopCipher(trim($cfgCipher));

            return;
        }

        if (! $allowLiveLookup) {
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
        if (! $this->refreshToken) {
            Log::warning('TikTok refreshAccessToken skipped: no refresh_token', [
                'channel' => $this->configKey,
            ]);

            return null;
        }

        $apply = function (array $token): ?array {
            $access = trim((string) ($token['access_token'] ?? ''));
            if ($access === '') {
                return null;
            }
            $this->accessToken = $access;
            $this->refreshToken = trim((string) ($token['refresh_token'] ?? $this->refreshToken)) ?: $this->refreshToken;
            $expiresIn = (int) ($token['expire_in'] ?? $token['expires_in'] ?? 86400);
            if ($expiresIn < 600) {
                $expiresIn = 86400;
            }
            Cache::put($this->cachePrefix.'_access_token', $this->accessToken, $expiresIn - 300);
            Cache::put($this->cachePrefix.'_refresh_token', $this->refreshToken, 86400 * 30);
            $this->client->setAccessToken($this->accessToken);
            config([
                'services.'.$this->configKey.'.access_token' => $this->accessToken,
                'services.'.$this->configKey.'.refresh_token' => $this->refreshToken,
            ]);

            return $token;
        };

        try {
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->connectTimeout(8)
                ->get('https://auth.tiktok-shops.com/api/v2/token/refresh', [
                    'app_key' => $this->clientKey,
                    'app_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ]);
            $json = $response->json();
            if (is_array($json) && (int) ($json['code'] ?? -1) === 0 && is_array($json['data'] ?? null)) {
                $applied = $apply($json['data']);
                if ($applied !== null) {
                    return $applied;
                }
            }
            Log::warning('TikTok refreshAccessToken HTTP unexpected payload', [
                'channel' => $this->configKey,
                'http' => $response->status(),
                'code' => is_array($json) ? ($json['code'] ?? null) : null,
                'message' => is_array($json) ? ($json['message'] ?? null) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('TikTok refreshAccessToken HTTP failed', [
                'channel' => $this->configKey,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $auth = $this->client->auth();
            $newToken = $auth->refreshNewToken($this->refreshToken);
            if (is_array($newToken)) {
                $applied = $apply($newToken['data'] ?? $newToken);
                if ($applied !== null) {
                    return $applied;
                }
            }
        } catch (\Throwable $e) {
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

        $productId = $this->findTikTokProductIdBySku(trim($identifier));
        if (! $productId) {
            return ['success' => false, 'message' => 'TikTok Shop product not found for SKU / id.'];
        }

        $body = $this->withPreservedSellerSkus($productId, [
            'product_id' => $productId,
            'video' => ['url' => $videos[0]],
            'videos' => array_map(fn ($url) => ['url' => $url], $videos),
        ]);

        try {
            if (! method_exists($this->client->Product, 'editProduct')) {
                return ['success' => false, 'message' => 'TikTok Shop product edit API is not available in this SDK version.'];
            }

            $response = $this->client->Product->editProduct([], $body);
            $this->lastResponse = $response;
            if (isset($response['code']) && (int) $response['code'] === 0) {
                $this->saveVideoUrlsToMetricsRow('tiktok_metrics', trim($identifier), $videos);

                return $this->finishNonInventoryPartialEditSuccess($productId, [
                    'success' => true,
                    'message' => 'TikTok Shop product video updated.',
                    'normalized_urls' => $videos,
                ]);
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

    public function updateTitle(string $sku, string $title): array
    {
        $productId = $this->findTikTokProductIdBySku($sku);
        if ($productId === null || $productId === '') {
            return ['success' => false, 'message' => 'TikTok product ID not found for this SKU. Sync TikTok listings first.'];
        }

        return $this->updateProductTitle($productId, $title);
    }

    private function findTikTokProductIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $isTwo = ($this->configKey ?? 'tiktok') === 'tiktok2';
        $viewClass = $isTwo ? \App\Models\TiktokTwoShopDataView::class : \App\Models\TiktokShopDataView::class;
        if (class_exists($viewClass)) {
            $row = $viewClass::query()
                ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($sku)])
                ->first();
            $value = is_array($row?->value ?? null) ? $row->value : [];
            if ($value === [] && is_string($row?->value ?? null)) {
                $decoded = json_decode((string) $row->value, true);
                $value = is_array($decoded) ? $decoded : [];
            }
            $id = trim((string) ($value['product_id'] ?? $value['productId'] ?? $value['id'] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        $productClass = $isTwo ? \App\Models\TikTokProductTwo::class : \App\Models\TikTokProduct::class;
        if (class_exists($productClass)) {
            $id = trim((string) ($productClass::query()
                ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($sku)])
                ->value('product_id') ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return $this->resolveTikTokProductIdForIdentifier($sku);
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

        $this->client->setAccessToken($this->accessToken);
        $this->ensureShopCipher();
        if (is_string($this->shopCipher) && $this->shopCipher !== '') {
            $this->client->setShopCipher($this->shopCipher);
        }

        $hosts = array_values(array_unique(array_filter([
            rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: ''), '/'),
            'https://open-api.tiktokglobalshop.com',
            'https://open-api-us.tiktokglobalshop.com',
        ])));

        $tries = [
            ['path' => "/product/202509/products/{$productId}/partial_edit", 'query' => []],
            ['path' => "/product/202309/products/{$productId}/partial_edit", 'query' => ['version' => '202309']],
            ['path' => "/product/202309/products/{$productId}/partial_edit", 'query' => []],
            ['path' => "/product/products/{$productId}/partial_edit", 'query' => ['version' => '202309']],
        ];

        $titleBody = ['title' => $title];
        $bodies = [$this->withPreservedSellerSkus($productId, $titleBody)];
        if (($bodies[0]['skus'] ?? []) !== []) {
            $bodies[] = $titleBody;
        }
        $lastError = '';
        foreach ($hosts as $base) {
            foreach ($tries as $try) {
                foreach ($bodies as $body) {
                    try {
                        $this->tiktokOpenApi('POST', $try['path'], $try['query'], $body, 45, false, $base);

                        return $this->finishNonInventoryPartialEditSuccess(
                            $productId,
                            $this->marketplaceApiSuccess('TikTok updateProductTitle', $productId)
                        );
                    } catch (\Throwable $e) {
                        $lastError = $e->getMessage();
                        Log::warning('TikTok title signed call failed', [
                            'product_id' => $productId,
                            'base' => $base,
                            'path' => $try['path'],
                            'query' => $try['query'],
                            'error' => $lastError,
                        ]);
                        if ($this->isMissingRequiredAttributeError($lastError) && count($bodies) <= 2) {
                            $attrs = $this->productAttributesForTitleUpdate($productId, $lastError);
                            if ($attrs !== []) {
                                $attrBody = [
                                    'title' => $title,
                                    'product_attributes' => $attrs,
                                ];
                                $bodies[] = $this->withPreservedSellerSkus($productId, $attrBody);
                                if (($bodies[array_key_last($bodies)]['skus'] ?? []) !== []) {
                                    $bodies[] = $attrBody;
                                }
                            }
                        }
                    }
                }
            }
        }

        try {
            $sdkBody = $this->withPreservedSellerSkus($productId, ['title' => $title]);
            if ($this->isMissingRequiredAttributeError($lastError)) {
                $attrs = $this->productAttributesForTitleUpdate($productId, $lastError);
                if ($attrs !== []) {
                    $sdkBody = $this->withPreservedSellerSkus($productId, [
                        'title' => $title,
                        'product_attributes' => $attrs,
                    ]);
                }
            }
            $response = $this->client->Product->useVersion('202309')->partialEditProduct($productId, $sdkBody);
            if (is_array($response) && (int) ($response['code'] ?? -1) === 0) {
                return $this->finishNonInventoryPartialEditSuccess(
                    $productId,
                    $this->marketplaceApiSuccess('TikTok updateProductTitle', $productId)
                );
            }
            if ($response === [] || $response === null) {
                return $this->finishNonInventoryPartialEditSuccess(
                    $productId,
                    $this->marketplaceApiSuccess('TikTok updateProductTitle', $productId)
                );
            }
            $lastError = (string) ($response['message'] ?? $lastError);
        } catch (\Throwable $e) {
            $code = (int) $e->getCode();
            $lastError = $code > 0 ? $code.': '.$e->getMessage() : $e->getMessage();
        }

        return $this->marketplaceApiFailure(
            'TikTok updateProductTitle',
            $productId,
            $lastError !== '' ? $lastError : 'TikTok title update failed.'
        );
    }

    protected function isMissingRequiredAttributeError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'product attribute id')
            || str_contains($m, 'missing product attribute')
            || (str_contains($m, 'age range') && str_contains($m, 'invalid'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function productAttributesForTitleUpdate(string $productId, string $errorMessage = ''): array
    {
        $data = [];
        try {
            $data = $this->fetchProductData($productId);
        } catch (\Throwable $e) {
            Log::warning('TikTok title update could not load product attributes', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        $attrs = $this->sanitizeProductAttributes(is_array($data['product_attributes'] ?? null) ? $data['product_attributes'] : []);
        $missingId = $this->missingAttributeIdFromError($errorMessage) ?: '100433';
        if ($this->productAttributesHaveId($attrs, $missingId)) {
            return $attrs;
        }

        $fromCategory = $this->categoryAttributeValue($this->tiktokCategoryIdFromProduct($data), $missingId);
        if ($fromCategory !== null) {
            $attrs[] = $fromCategory;

            return $attrs;
        }

        $attrs[] = [
            'id' => $missingId,
            'values' => [['name' => 'Adults']],
        ];

        return $attrs;
    }

    protected function missingAttributeIdFromError(string $message): string
    {
        if (preg_match('/product attribute ID [`\'"]?(\d+)/i', $message, $m)) {
            return (string) $m[1];
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $attrs
     */
    protected function productAttributesHaveId(array $attrs, string $id): bool
    {
        foreach ($attrs as $attr) {
            if (trim((string) ($attr['id'] ?? '')) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $attrs
     * @return list<array<string, mixed>>
     */
    protected function sanitizeProductAttributes(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $id = trim((string) ($attr['id'] ?? $attr['attribute_id'] ?? ''));
            $values = $attr['values'] ?? [];
            if (! is_array($values)) {
                $values = [];
            }
            $cleanValues = [];
            foreach ($values as $value) {
                if (! is_array($value)) {
                    $name = trim((string) $value);
                    if ($name !== '') {
                        $cleanValues[] = ['name' => $name];
                    }
                    continue;
                }
                $row = [];
                $valueId = trim((string) ($value['id'] ?? $value['value_id'] ?? ''));
                $valueName = trim((string) ($value['name'] ?? $value['value_name'] ?? $value['value'] ?? ''));
                if ($valueId !== '') {
                    $row['id'] = $valueId;
                }
                if ($valueName !== '') {
                    $row['name'] = $valueName;
                }
                if ($row !== []) {
                    $cleanValues[] = $row;
                }
            }
            if ($id === '' || $cleanValues === []) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'values' => $cleanValues,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function tiktokCategoryIdFromProduct(array $data): string
    {
        foreach (['category_id', 'leaf_category_id'] as $key) {
            $id = trim((string) ($data[$key] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }
        $category = is_array($data['category'] ?? null) ? $data['category'] : [];
        $id = trim((string) ($category['id'] ?? $category['category_id'] ?? $category['leaf_category_id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
        $chains = $data['category_chains'] ?? $data['categories'] ?? [];
        if (is_array($chains) && $chains !== []) {
            $last = end($chains);
            if (is_array($last)) {
                return trim((string) ($last['id'] ?? $last['category_id'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @return array{id: string, values: list<array<string, string>>}|null
     */
    protected function categoryAttributeValue(string $categoryId, string $attributeId): ?array
    {
        if ($categoryId === '' || $attributeId === '') {
            return null;
        }

        $data = [];
        try {
            $data = $this->tiktokOpenApi('GET', "/product/202309/categories/{$categoryId}/attributes", [
                'locale' => 'en-US',
            ]);
        } catch (\Throwable) {
            try {
                $data = $this->tiktokOpenApi('GET', "/product/202509/categories/{$categoryId}/attributes", [
                    'locale' => 'en-US',
                ]);
            } catch (\Throwable) {
                return null;
            }
        }

        $list = $data['attributes'] ?? $data['category_attributes'] ?? $data;
        if (! is_array($list)) {
            return null;
        }
        foreach ($list as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $id = trim((string) ($attr['id'] ?? $attr['attribute_id'] ?? ''));
            if ($id !== $attributeId) {
                continue;
            }
            $values = $attr['values'] ?? $attr['value_list'] ?? [];
            if (! is_array($values) || $values === []) {
                continue;
            }
            $picked = $this->pickPreferredAttributeValue($values);
            if ($picked === null) {
                continue;
            }

            return [
                'id' => $attributeId,
                'values' => [$picked],
            ];
        }

        return null;
    }

    /**
     * @param  list<mixed>  $values
     * @return array<string, string>|null
     */
    protected function pickPreferredAttributeValue(array $values): ?array
    {
        $rows = [];
        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }
            $id = trim((string) ($value['id'] ?? $value['value_id'] ?? ''));
            $name = trim((string) ($value['name'] ?? $value['value_name'] ?? $value['value'] ?? ''));
            if ($id === '' && $name === '') {
                continue;
            }
            $rows[] = array_filter([
                'id' => $id !== '' ? $id : null,
                'name' => $name !== '' ? $name : null,
            ]);
        }
        if ($rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            $name = strtolower((string) ($row['name'] ?? ''));
            if (preg_match('/adult|13\+|18\+|all ages/', $name)) {
                return $row;
            }
        }

        return $rows[0];
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

        $productId = $this->findTikTokProductIdBySku(trim($identifier));
        if (! $productId) {
            return ['success' => false, 'message' => 'TikTok Shop product not found for SKU / id. Sync TikTok listings first.'];
        }

        $this->client->setAccessToken($this->accessToken);
        if ($this->shopCipher) {
            $this->client->setShopCipher($this->shopCipher);
        }

        $body = $this->withPreservedSellerSkus($productId, array_merge(['product_id' => $productId], $fields));

        try {
            if (! method_exists($this->client->Product, 'editProduct')) {
                return ['success' => false, 'message' => 'TikTok Shop product edit API is not available in this SDK version.'];
            }

            $response = $this->client->Product->editProduct($body);
            if (is_array($response) && (int) ($response['code'] ?? -1) === 0) {
                return $this->finishNonInventoryPartialEditSuccess($productId, [
                    'success' => true,
                    'message' => 'TikTok Shop product updated.',
                ]);
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
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 9);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        foreach ($images as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
            }
        }

        $productId = $this->findTikTokProductIdBySku(trim($identifier));
        if ($productId === null || $productId === '') {
            return ['success' => false, 'message' => 'TikTok product ID not found for this SKU. Sync TikTok listings first.'];
        }

        $uris = [];
        $errors = [];
        foreach ($images as $i => $url) {
            $uri = $this->tiktokUploadImageFromUrl($url, 'MAIN_IMAGE');
            if ($uri === null || $uri === '') {
                $errors[] = 'Image '.($i + 1).' upload failed.';
                continue;
            }
            $uris[] = ['uri' => $uri];
        }
        if ($uris === []) {
            return [
                'success' => false,
                'message' => 'TikTok image upload failed.'.($errors !== [] ? ' '.implode(' ', $errors) : ''),
            ];
        }

        $this->client->setAccessToken($this->accessToken);
        $this->ensureShopCipher();
        if (is_string($this->shopCipher) && $this->shopCipher !== '') {
            $this->client->setShopCipher($this->shopCipher);
        }

        $hosts = array_values(array_unique(array_filter([
            rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: ''), '/'),
            'https://open-api.tiktokglobalshop.com',
            'https://open-api-us.tiktokglobalshop.com',
        ])));
        $tries = [
            ['path' => "/product/202509/products/{$productId}/partial_edit", 'query' => []],
            ['path' => "/product/202309/products/{$productId}/partial_edit", 'query' => ['version' => '202309']],
            ['path' => "/product/202309/products/{$productId}/partial_edit", 'query' => []],
        ];
        $imageBodies = [
            ['main_images' => $uris],
            ['main_images' => $uris, 'images' => $uris],
        ];
        $bodies = [];
        foreach ($imageBodies as $imageBody) {
            $safe = $this->withPreservedSellerSkus($productId, $imageBody);
            $bodies[] = $safe;
            if (($safe['skus'] ?? []) !== []) {
                $bodies[] = $imageBody;
            }
        }

        $lastError = '';
        foreach ($hosts as $base) {
            foreach ($tries as $try) {
                foreach ($bodies as $body) {
                    try {
                        $this->tiktokOpenApi('POST', $try['path'], $try['query'], $body, 60, false, $base);

                        return $this->finishNonInventoryPartialEditSuccess($productId, [
                            'success' => true,
                            'message' => 'TikTok Shop product images updated.',
                            'normalized_urls' => $images,
                        ]);
                    } catch (\Throwable $e) {
                        $lastError = $e->getMessage();
                    }
                }
            }
        }

        $fallback = $this->updateTikTokProductFields($identifier, [
            'images' => array_map(fn ($url) => ['url' => $url], $images),
            'main_image' => ['url' => $images[0]],
            'main_images' => $uris,
        ]);
        if ($fallback['success'] ?? false) {
            $fallback['normalized_urls'] = $images;

            return $fallback;
        }

        return [
            'success' => false,
            'message' => $lastError !== '' ? $lastError : (string) ($fallback['message'] ?? 'TikTok Shop image update failed.'),
        ];
    }

    /**
     * Upload a public image URL to TikTok Shop and return the file uri.
     */
    protected function tiktokUploadImageFromUrl(string $imageUrl, string $useCase = 'MAIN_IMAGE'): ?string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '' || ! $this->accessToken) {
            return null;
        }

        $bytes = $this->listingImageBytes($imageUrl);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $converted = $this->listingImageToTikTokBytes($bytes, basename((string) (parse_url($imageUrl, PHP_URL_PATH) ?: 'image.jpg')));
        if ($converted === null) {
            return null;
        }

        return $this->tiktokUploadImageBytes($converted['bytes'], $converted['filename'], $useCase)['uri'] ?? null;
    }

    /**
     * Upload Image Master photos (local product_images files first, then CDN / public URLs).
     *
     * @return array{uris: list<array{uri: string}>, message: string}
     */
    public function uploadImageMasterForListing(string $sku, ?string $parentSku = null): array
    {
        if (! $this->accessToken) {
            return ['uris' => [], 'message' => 'TikTok access token is missing. Connect the shop, then try Publish again.'];
        }

        $sources = ListingManagerAmazonHydrator::imageMasterUploadSources($sku, $parentSku);
        if ($sources === []) {
            return [
                'uris' => [],
                'message' => 'No Image Master photo for '.$sku.'. Add images on Image Master, then try Publish again.',
            ];
        }

        $uris = [];
        $lastError = '';
        foreach ($sources as $source) {
            $bytes = null;
            $path = $source['path'] ?? null;
            if (is_string($path) && $path !== '' && is_readable($path)) {
                $read = @file_get_contents($path);
                if (is_string($read) && $read !== '') {
                    $bytes = $read;
                }
            }
            $url = trim((string) ($source['url'] ?? ''));
            if (($bytes === null || $bytes === '') && $url !== '') {
                $bytes = $this->listingImageBytes($url);
                if ($bytes === null || $bytes === '') {
                    $lastError = 'Could not read Image Master photo'.($url !== '' ? ' ('.mb_substr($url, 0, 120).')' : '').'.';
                    continue;
                }
            }
            if ($bytes === null || $bytes === '') {
                $lastError = 'Could not read Image Master photo for '.$sku.'.';
                continue;
            }

            $converted = $this->listingImageToTikTokBytes($bytes, (string) ($source['name'] ?? 'image.jpg'));
            if ($converted === null) {
                $lastError = 'Image Master photo is not a JPEG/PNG TikTok accepts.';
                continue;
            }

            $uploaded = $this->tiktokUploadImageBytes($converted['bytes'], $converted['filename'], 'MAIN_IMAGE');
            $uri = trim((string) ($uploaded['uri'] ?? ''));
            if ($uri !== '') {
                $uris[] = ['uri' => $uri];
                continue;
            }
            $lastError = $this->sanitizeTikTokClientError((string) ($uploaded['message'] ?? ''));
            if ($lastError === '') {
                $lastError = 'TikTok rejected the Image Master photo.';
            }
        }

        if ($uris === []) {
            return [
                'uris' => [],
                'message' => $lastError !== ''
                    ? 'TikTok image upload failed for '.$sku.'. '.$lastError
                    : 'TikTok image upload failed for '.$sku.'. Check Image Master photos.',
            ];
        }

        return ['uris' => $uris, 'message' => ''];
    }

    /**
     * @return array{uri: ?string, message: string}
     */
    protected function tiktokUploadImageBytes(string $bytes, string $filename, string $useCase = 'MAIN_IMAGE'): array
    {
        if ($bytes === '' || ! $this->accessToken) {
            return ['uri' => null, 'message' => 'TikTok access token is missing.'];
        }

        $this->ensureShopCipher();
        $viaSdk = $this->tiktokUploadImageViaSdk($bytes, $filename, $useCase);
        if (($viaSdk['uri'] ?? null) !== null && $viaSdk['uri'] !== '') {
            return $viaSdk;
        }

        $hosts = $this->tiktokImageUploadHosts();
        $tries = [];
        foreach (['202509', '202309', '202405'] as $ver) {
            foreach ([
                '/product/'.$ver.'/images/upload',
                '/product/'.$ver.'/products/upload_files',
            ] as $path) {
                $tries[] = ['path' => $path, 'query' => []];
                $tries[] = ['path' => $path, 'query' => ['version' => $ver]];
            }
        }
        $apiError = $this->sanitizeTikTokClientError((string) ($viaSdk['message'] ?? ''));
        $networkError = '';

        foreach ($hosts as $base) {
            foreach ($tries as $try) {
                $path = $try['path'];
                $query = array_merge([
                    'app_key' => (string) $this->clientKey,
                    'timestamp' => (string) time(),
                ], $try['query']);
                if (is_string($this->shopCipher) && $this->shopCipher !== '') {
                    $query['shop_cipher'] = $this->shopCipher;
                }
                $query['sign'] = $this->signTikTokRequest($path, $query, '');

                try {
                    $response = Http::withoutVerifying()
                        ->withHeaders(['x-tts-access-token' => (string) $this->accessToken])
                        ->timeout(120)
                        ->connectTimeout(10)
                        ->attach('data', $bytes, $filename)
                        ->post($base.$path.'?'.http_build_query($query), [
                            'use_case' => $useCase,
                        ]);
                    $json = $response->json() ?? [];
                    if ((int) ($json['code'] ?? -1) === 0) {
                        $uri = trim((string) (
                            $json['data']['uri']
                            ?? $json['data']['url']
                            ?? $json['data']['image_uri']
                            ?? ''
                        ));
                        if ($uri !== '') {
                            return ['uri' => $uri, 'message' => ''];
                        }
                    }
                    $msg = $this->sanitizeTikTokClientError((string) ($json['message'] ?? $response->body()));
                    Log::info('TikTok image upload attempt failed', [
                        'channel' => $this->configKey,
                        'base' => $base,
                        'path' => $path,
                        'code' => $json['code'] ?? null,
                        'message' => $msg,
                    ]);
                    if ($msg !== '' && ! $this->isInvalidApiVersionError($msg)) {
                        $apiError = $msg;
                    } elseif ($apiError === '') {
                        $apiError = $msg;
                    }
                } catch (\Throwable $e) {
                    $msg = $this->sanitizeTikTokClientError($e->getMessage());
                    Log::info('TikTok image upload exception', [
                        'channel' => $this->configKey,
                        'base' => $base,
                        'path' => $path,
                        'error' => $msg,
                    ]);
                    if ($this->isTikTokUnreachableHostError($e->getMessage())) {
                        $networkError = $msg;
                        break;
                    }
                    if ($msg !== '' && ! $this->isInvalidApiVersionError($msg)) {
                        $apiError = $msg;
                    } elseif ($apiError === '') {
                        $apiError = $msg;
                    }
                }
            }
        }

        return ['uri' => null, 'message' => $apiError !== '' ? $apiError : $networkError];
    }

    /**
     * @return array{uri: ?string, message: string}
     */
    private function tiktokUploadImageViaSdk(string $bytes, string $filename, string $useCase): array
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) ?: 'jpg';
        $tmp = tempnam(sys_get_temp_dir(), 'tts_img_');
        if (! is_string($tmp) || $tmp === '') {
            return ['uri' => null, 'message' => ''];
        }
        $path = $tmp.'.'.$ext;
        @unlink($tmp);
        if (@file_put_contents($path, $bytes) === false) {
            return ['uri' => null, 'message' => ''];
        }

        $lastError = '';
        try {
            $this->client->setAccessToken($this->accessToken);
            if (is_string($this->shopCipher) && $this->shopCipher !== '') {
                $this->client->setShopCipher($this->shopCipher);
            }
            foreach (['202509', '202309', '202405'] as $version) {
                try {
                    $product = $this->client->Product->useVersion($version);
                    $data = [];
                    if (method_exists($product, 'uploadProductImage')) {
                        $data = $product->uploadProductImage($path, $useCase);
                    } elseif (method_exists($product, 'uploadImage')) {
                        $data = $product->uploadImage($path, 1);
                    } else {
                        break;
                    }
                    $data = is_array($data) ? $data : [];
                    $uri = trim((string) ($data['uri'] ?? $data['url'] ?? $data['image_uri'] ?? ''));
                    if ($uri !== '') {
                        return ['uri' => $uri, 'message' => ''];
                    }
                } catch (\Throwable $e) {
                    $lastError = $this->sanitizeTikTokClientError($e->getMessage());
                    Log::info('TikTok SDK image upload failed', [
                        'channel' => $this->configKey,
                        'version' => $version,
                        'error' => $lastError,
                    ]);
                    if (! $this->isInvalidApiVersionError($e->getMessage()) && ! $this->isNoSchemaError($e->getMessage())) {
                        continue;
                    }
                }
            }
        } finally {
            @unlink($path);
        }

        return ['uri' => null, 'message' => $lastError];
    }

    /**
     * @return list<string>
     */
    private function tiktokImageUploadHosts(): array
    {
        $preferred = rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: 'https://open-api.tiktokglobalshop.com'), '/');
        $hosts = [];
        foreach ([$preferred, 'https://open-api.tiktokglobalshop.com'] as $base) {
            $base = rtrim((string) $base, '/');
            if ($base === '' || in_array($base, $hosts, true)) {
                continue;
            }
            if (! $this->tiktokHostResolves($base)) {
                continue;
            }
            $hosts[] = $base;
        }

        return $hosts !== [] ? $hosts : [$preferred];
    }

    private function tiktokHostResolves(string $base): bool
    {
        $host = (string) (parse_url($base, PHP_URL_HOST) ?: '');
        if ($host === '') {
            return false;
        }
        $ip = @gethostbyname($host);

        return $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function isTikTokUnreachableHostError(string $message): bool
    {
        return (bool) preg_match('/Could not resolve host|cURL error 6|cURL error 7|Failed to connect|Resolving timed out/i', $message);
    }

    private function sanitizeTikTokClientError(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '';
        }
        if (preg_match('/Could not resolve host:\s*([a-z0-9.-]+)/i', $message, $m)) {
            return 'Could not reach TikTok API host '.$m[1];
        }
        $message = preg_replace('/\?[^ \]]+/', '', $message) ?? $message;
        $message = preg_replace('#https?://[^\s\]]+#', '', $message) ?? $message;

        return trim(preg_replace('/\s+/', ' ', $message) ?? $message);
    }

    /**
     * @return array{bytes: string, filename: string}|null
     */
    private function listingImageToTikTokBytes(string $bytes, string $filename): ?array
    {
        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? strtolower((string) ($info['mime'] ?? '')) : '';
        $base = preg_replace('/\.[a-z0-9]+$/i', '', basename($filename)) ?: 'image';

        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return [
                'bytes' => $bytes,
                'filename' => $base.'.'.($mime === 'image/png' ? 'png' : 'jpg'),
            ];
        }

        if (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($bytes);
            if ($im !== false) {
                if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($im)) {
                    @imagepalettetotruecolor($im);
                }
                if (imageistruecolor($im)) {
                    $w = imagesx($im);
                    $h = imagesy($im);
                    $canvas = imagecreatetruecolor($w, $h);
                    if ($canvas !== false) {
                        $white = imagecolorallocate($canvas, 255, 255, 255);
                        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
                        imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);
                        imagedestroy($im);
                        $im = $canvas;
                    }
                }
                ob_start();
                $ok = imagejpeg($im, null, 90);
                imagedestroy($im);
                $jpeg = ob_get_clean();
                if ($ok && is_string($jpeg) && $jpeg !== '') {
                    return ['bytes' => $jpeg, 'filename' => $base.'.jpg'];
                }
            }
        }

        if (str_starts_with($bytes, "\xFF\xD8")) {
            return ['bytes' => $bytes, 'filename' => $base.'.jpg'];
        }
        if (str_starts_with($bytes, "\x89PNG")) {
            return ['bytes' => $bytes, 'filename' => $base.'.png'];
        }

        return null;
    }

    private function listingImageBytes(string $imageUrl): ?string
    {
        $local = $this->localListingImagePath($imageUrl);
        if ($local !== null && is_readable($local)) {
            $bytes = @file_get_contents($local);
            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        }
        $byName = $this->localListingImageByBasename($imageUrl);
        if ($byName !== null) {
            return $byName;
        }

        $candidates = [$imageUrl];
        $stripped = preg_replace('/\?.*$/', '', $imageUrl);
        if (is_string($stripped) && $stripped !== $imageUrl) {
            $candidates[] = $stripped;
        }

        foreach (array_values(array_unique($candidates)) as $try) {
            try {
                $imgResp = Http::withoutVerifying()
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'image/jpeg,image/jpg,image/png,image/webp,image/*,*/*;q=0.8',
                        'Referer' => 'https://admin.shopify.com/',
                    ])
                    ->timeout(90)
                    ->get($try);
                if (! $imgResp->successful()) {
                    Log::warning('TikTok image download failed', [
                        'channel' => $this->configKey,
                        'url' => mb_substr($try, 0, 300),
                        'status' => $imgResp->status(),
                    ]);
                    continue;
                }
                $bytes = $imgResp->body();
                if ($bytes !== '') {
                    return $bytes;
                }
            } catch (\Throwable $e) {
                Log::warning('TikTok image download exception', [
                    'channel' => $this->configKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function localListingImagePath(string $imageUrl): ?string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return null;
        }
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?: $imageUrl);
        $path = urldecode($path);

        if (preg_match('#/storage/(.+)$#', $path, $match)) {
            $full = storage_path('app/public/'.ltrim(str_replace('\\', '/', $match[1]), '/'));
            if (is_file($full)) {
                return $full;
            }
        }
        if (preg_match('#/listing-manager/media/([^/?]+)#', $path, $match)) {
            $full = storage_path('app/public/listing-manager/images/'.basename($match[1]));
            if (is_file($full)) {
                return $full;
            }
        }
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if (preg_match('#^(products|product_images|image_master)/#i', $rel)) {
            $full = storage_path('app/public/'.$rel);
            if (is_file($full)) {
                return $full;
            }
        }

        return null;
    }

    private function localListingImageByBasename(string $imageUrl): ?string
    {
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?: $imageUrl);
        $basename = basename(urldecode($path));
        if ($basename === '' || ! preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $basename)) {
            return null;
        }
        $escaped = str_replace(['%', '*', '?', '['], ['\%', '\*', '\?', '\['], $basename);
        foreach ([
            storage_path('app/public/products/*/'.$escaped),
            storage_path('app/public/product_images/*/'.$escaped),
            storage_path('app/public/image_master/*/'.$escaped),
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $hit) {
                if (is_file($hit) && is_readable($hit)) {
                    $bytes = @file_get_contents($hit);
                    if (is_string($bytes) && $bytes !== '') {
                        return $bytes;
                    }
                }
            }
        }

        return null;
    }

    public static function normalizeSellerSkuKey(?string $sku): string
    {
        return strtoupper(str_replace("\u{00a0}", ' ', trim((string) $sku)));
    }

    public static function isLiveListingStatus(?string $status): bool
    {
        $raw = strtoupper(trim((string) $status));

        return in_array($raw, ['ACTIVATE', 'ACTIVE', 'LIVE'], true);
    }

    public function isPriceUpdateBlockedByProductStatus(string $message): bool
    {
        return $this->isProductStatusRestrictionError($message);
    }

    /**
     * Live ACTIVATE listing for a seller SKU (ignores DELETED / DRAFT twins).
     *
     * @return array{product_id: string, sku_id: string, seller_sku: string, price: float, stock: int, status: string}|null
     */
    public function findActiveListingBySellerSku(string $sellerSku): ?array
    {
        $sellerSku = trim($sellerSku);
        if ($sellerSku === '' || ! $this->accessToken) {
            return null;
        }

        try {
            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();
            if (! $this->shopCipher) {
                return null;
            }

            $candidates = [$sellerSku];
            $nbsp = str_replace(' ', "\xC2\xA0", $sellerSku);
            if ($nbsp !== $sellerSku) {
                $candidates[] = $nbsp;
            }

            $response = $this->client->Product->useVersion('202309')->searchProducts(
                ['page_size' => 20],
                ['seller_skus' => $candidates, 'status' => 'ACTIVATE']
            );
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->refreshAccessToken()) {
                return $this->findActiveListingBySellerSku($sellerSku);
            }

            return null;
        } catch (\Throwable $e) {
            Log::info('TikTok findActiveListingBySellerSku failed', [
                'channel' => $this->configKey,
                'sku' => $sellerSku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $payload = is_array($response) ? ($response['data'] ?? $response) : [];
        if (! is_array($payload)) {
            $payload = [];
        }
        $products = $payload['products'] ?? $payload['product_list'] ?? [];
        if (! is_array($products)) {
            return null;
        }

        $want = self::normalizeSellerSkuKey($sellerSku);
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $status = strtoupper(trim((string) ($product['status'] ?? $product['product_status'] ?? '')));
            if ($status !== '' && ! self::isLiveListingStatus($status)) {
                continue;
            }
            $productId = trim((string) ($product['id'] ?? $product['product_id'] ?? ''));
            if ($productId === '') {
                continue;
            }
            foreach ((array) ($product['skus'] ?? []) as $skuNode) {
                if (! is_array($skuNode)) {
                    continue;
                }
                $liveSku = (string) ($skuNode['seller_sku'] ?? $skuNode['sku'] ?? '');
                if ($want !== '' && self::normalizeSellerSkuKey($liveSku) !== $want) {
                    continue;
                }
                $skuId = trim((string) ($skuNode['id'] ?? $skuNode['sku_id'] ?? ''));
                if ($skuId === '') {
                    continue;
                }

                return [
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'seller_sku' => $liveSku !== '' ? $liveSku : $sellerSku,
                    'price' => $this->salePriceFromSkuNode($skuNode),
                    'stock' => (int) (self::skuNodeAvailableQty($skuNode) ?? 0),
                    'status' => $status !== '' ? $status : 'ACTIVATE',
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $skuNode
     */
    protected function salePriceFromSkuNode(array $skuNode): float
    {
        $priceNode = $skuNode['price'] ?? null;
        $candidates = [];
        if (is_array($priceNode)) {
            $candidates[] = $priceNode['sale_price'] ?? $priceNode['tax_exclusive_price'] ?? $priceNode['amount'] ?? $priceNode['price'] ?? null;
        } elseif (is_numeric($priceNode)) {
            $candidates[] = $priceNode;
        }
        $candidates[] = $skuNode['sale_price'] ?? $skuNode['price_amount'] ?? null;

        foreach ($candidates as $value) {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }
            $price = (float) $value;
            if ($price > 0) {
                return $price;
            }
        }

        return 0.0;
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

            // Qty-only Update Inventory first (does not touch seller_sku). LIVE may
            // reject it with 12052901; then Partial Edit with a full SKU node.
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

            $skuWarehouses = $this->skuWarehouseInventoryRows($productId, $skuId);
            if ($skuWarehouses !== []) {
                $retry = $this->sendInventoryRows(
                    $productId,
                    $skuId,
                    $this->inventoryRowsForPushQty($skuWarehouses, $quantity)
                );
                if (! empty($retry['success'])) {
                    return $retry;
                }
                $message = (string) ($retry['message'] ?? $message);
                $this->rememberIpAllowList($message);
                if ($this->ipAllowListBlocked) {
                    return ['success' => false, 'message' => $message];
                }
            }

            if (stripos($message, 'warehouse') !== false) {
                $skuWarehouse = $this->warehouseIdFromProductSku($productId, $skuId);
                if ($skuWarehouse !== null && $skuWarehouse !== '' && $skuWarehouse !== $warehouseId) {
                    $whRetry = $this->sendProductInventoryUpdate($productId, $skuId, $quantity, $skuWarehouse);
                    if (! empty($whRetry['success'])) {
                        return $whRetry;
                    }
                    $message = (string) ($whRetry['message'] ?? $message);
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
     * LIVE listings are rejected by 202309 Update Inventory; Partial Edit is the fallback.
     *
     * @return list<string>
     */
    protected function productDetailApiVersions(): array
    {
        return ['202309'];
    }

    protected function isProductStatusRestrictionError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, '12052901')
            || str_contains($message, 'operation not allowed')
            || str_contains($message, 'must be in one of these statuses')
            || str_contains($message, 'precondition required')
            || str_contains($message, 'valid product status')
            || (str_contains($message, 'seller_deactivated') && str_contains($message, 'activate'));
    }

    protected function isEnforcementBlockedError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'enforcement')
            || str_contains($message, 'restricted product')
            || str_contains($message, 'product is restricted')
            || str_contains($message, 'product restricted')
            || str_contains($message, 'policy violation')
            || str_contains($message, 'under review')
            || str_contains($message, 'not allowed to edit');
    }

    protected function rememberSkipInventoryUpdateApi(): void
    {
        $this->skipInventoryUpdateApi = true;
        $this->deadInventoryKeys['202309|inventory'] = true;
        try {
            Cache::put($this->cachePrefix.'_skip_inventory_update_api', true, now()->addHours(6));
        } catch (\Throwable $e) {
            // ignore
        }
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
     * LIVE Partial Edit rewrites the SKU node. Omitting seller_sku (or sending "")
     * can wipe Seller Center SKU. Always include a non-empty seller_sku.
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
        $sellerSku = $this->sellerSkuForPartialEdit($productId, $skuId, $node);
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
     * @param  array<string, mixed>  $node
     */
    protected function sellerSkuForPartialEdit(string $productId, string $skuId, array $node): string
    {
        $live = trim((string) ($node['seller_sku'] ?? $node['sku'] ?? ''));
        if ($this->localSellerSkuLooksValid($live)) {
            return $live;
        }

        return $this->localSellerSku($productId, $skuId);
    }

    /**
     * @param  array{skus?: list<array<string, mixed>>}  $params
     */
    protected function partialEditSkuHasSellerSku(array $params): bool
    {
        $sku = is_array($params['skus'][0] ?? null) ? $params['skus'][0] : [];

        return $this->localSellerSkuLooksValid(trim((string) ($sku['seller_sku'] ?? '')));
    }

    protected function localProductsTable(): string
    {
        return $this->configKey === 'tiktok2' ? 'tiktok_products_two' : 'tiktok_products';
    }

    protected function localSellerSkuLooksValid(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $sku)) {
            return false;
        }
        if (preg_match('/^\$?\d+(\.\d+)?$/', $sku)) {
            return false;
        }

        return true;
    }

    protected function localSellerSku(string $productId, string $skuId): string
    {
        $table = $this->localProductsTable();
        $productId = trim($productId);
        $skuId = trim($skuId);
        if ($productId === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            return '';
        }

        try {
            $query = DB::table($table)->where('product_id', $productId);
            if ($skuId !== '' && Schema::hasColumn($table, 'sku_id')) {
                $match = (clone $query)->where('sku_id', $skuId)->value('sku');
                $sku = trim((string) $match);
                if ($this->localSellerSkuLooksValid($sku)) {
                    return $sku;
                }
            }

            $fallback = $query->whereNotNull('sku')->where('sku', '!=', '')->value('sku');
            $sku = trim((string) $fallback);

            return $this->localSellerSkuLooksValid($sku) ? $sku : '';
        } catch (\Throwable $e) {
            Log::info('TikTok local seller_sku lookup failed', [
                'table' => $table,
                'product_id' => $productId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
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
        try {
            $response = $this->client->Product->useVersion('202309')->searchProducts(
                ['page_size' => 20],
                ['product_ids' => [$productId]]
            );
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                return [];
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->rememberIpAllowList($e->getMessage());
            Log::info('TikTok searchProducts for inventory SKU failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $payload = is_array($response) ? ($response['data'] ?? $response) : [];
        if (! is_array($payload)) {
            $payload = [];
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
                $this->productSearchCache[$productId] = $product;

                return $product;
            }
        }

        $fallback = is_array($products[0] ?? null) ? $products[0] : [];
        $this->productSearchCache[$productId] = $fallback;

        return $fallback;
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
        if ($rows === []) {
            return ['success' => false, 'message' => 'No TikTok warehouse rows to update.'];
        }

        $lastMessage = 'TikTok inventory update failed.';

        // Qty-only inventory/update first (does not wipe seller_sku). LIVE often
        // rejects it with 12052901; Partial Edit is then used with a full SKU node.
        $openApi = $this->postInventoryUpdateViaOpenApi($productId, $skuId, $rows);
        if (! empty($openApi['success'])) {
            return $this->finishInventoryUpdateSuccess($productId, $skuId, $openApi);
        }
        $lastMessage = (string) ($openApi['message'] ?? $lastMessage);
        $this->rememberIpAllowList($lastMessage);
        if ($this->ipAllowListBlocked) {
            return ['success' => false, 'message' => $lastMessage];
        }
        if ($this->isProductStatusRestrictionError($lastMessage)) {
            $this->rememberSkipInventoryUpdateApi();
        }

        $partialParams = $this->partialEditInventoryParams($productId, $skuId, $rows);
        if ($this->partialEditSkuHasSellerSku($partialParams)) {
            $result = $this->invokeSdkInventory($productId, $partialParams, '202309', 'partial');
            if (! empty($result['success'])) {
                $this->workingInventoryPath = '202309|partial';

                return $this->finishInventoryUpdateSuccess($productId, $skuId, $result);
            }
            $lastMessage = (string) ($result['message'] ?? $lastMessage);
            $this->rememberIpAllowList($lastMessage);
            Log::info('TikTok inventory update attempt failed', [
                'product_id' => $productId,
                'sku_id' => $skuId,
                'version' => '202309',
                'method' => 'partial',
                'error' => $lastMessage,
            ]);

            if ($this->isSalesAttributesError($lastMessage)) {
                $partialParams = $this->partialEditInventoryParams($productId, $skuId, $rows, true);
                if ($this->partialEditSkuHasSellerSku($partialParams)) {
                    $retry = $this->invokeSdkInventory($productId, $partialParams, '202309', 'partial');
                    if (! empty($retry['success'])) {
                        $this->workingInventoryPath = '202309|partial';

                        return $this->finishInventoryUpdateSuccess($productId, $skuId, $retry);
                    }
                    $lastMessage = (string) ($retry['message'] ?? $lastMessage);
                }
                $openRetry = $this->postInventoryUpdateViaOpenApi($productId, $skuId, $rows);
                if (! empty($openRetry['success'])) {
                    return $this->finishInventoryUpdateSuccess($productId, $skuId, $openRetry);
                }
                $lastMessage = (string) ($openRetry['message'] ?? $lastMessage);
            }
        }

        if (! $this->skipInventoryUpdateApi && ! $this->isProductStatusRestrictionError($lastMessage)) {
            $inventoryParams = ['skus' => [['id' => $skuId, 'inventory' => $rows]]];
            $inv = $this->invokeSdkInventory($productId, $inventoryParams, '202309', 'inventory');
            if (! empty($inv['success'])) {
                $this->workingInventoryPath = '202309|inventory';

                return $this->finishInventoryUpdateSuccess($productId, $skuId, $inv);
            }
            $invMsg = (string) ($inv['message'] ?? '');
            $this->rememberIpAllowList($invMsg);
            if ($this->isProductStatusRestrictionError($invMsg)) {
                $this->rememberSkipInventoryUpdateApi();
            } elseif ($invMsg !== '') {
                $lastMessage = $invMsg;
            }
        }

        if (str_contains(strtolower($lastMessage), 'seller_deactivated')
            && $this->activateProductForInventory($productId)) {
            $afterActivate = $this->postInventoryUpdateViaOpenApi($productId, $skuId, $rows);
            if (! empty($afterActivate['success'])) {
                return $this->finishInventoryUpdateSuccess($productId, $skuId, $afterActivate);
            }
            $lastMessage = (string) ($afterActivate['message'] ?? $lastMessage);
        }

        return ['success' => false, 'message' => $lastMessage !== '' ? $lastMessage : 'TikTok inventory update failed.'];
    }

    /**
     * Qty-only inventory/update first. Partial Edit only with a full SKU node
     * (seller_sku required) — never POST id+qty alone to Partial Edit.
     *
     * @param  list<array{warehouse_id?: string, quantity: int}>  $rows
     * @return array{success: bool, message: string}
     */
    protected function postInventoryUpdateViaOpenApi(string $productId, string $skuId, array $rows): array
    {
        $rows = $this->ensureWarehouseOnInventoryRows($rows);
        $qtyOnly = ['skus' => [['id' => $skuId, 'inventory' => $rows]]];
        $host = rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: 'https://open-api.tiktokglobalshop.com'), '/');
        $lastError = 'TikTok inventory update failed.';

        if (! $this->skipInventoryUpdateApi) {
            $invPaths = [
                "/product/202309/products/{$productId}/inventory/update",
                "/product/202509/products/{$productId}/inventory/update",
            ];
            foreach ($invPaths as $path) {
                try {
                    $this->tiktokOpenApi('POST', $path, [], $qtyOnly, 12, false, $host);
                    $this->workingInventoryPath = str_contains($path, '202509') ? '202509|inventory' : '202309|inventory';
                    Log::info('TikTok inventory updated via Open API', [
                        'product_id' => $productId,
                        'sku_id' => $skuId,
                        'path' => $path,
                        'base' => $host,
                    ]);

                    return ['success' => true, 'message' => 'Inventory updated.'];
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    $this->rememberIpAllowList($lastError);
                    if ($this->ipAllowListBlocked) {
                        return ['success' => false, 'message' => $lastError];
                    }
                    Log::info('TikTok Open API inventory attempt failed', [
                        'product_id' => $productId,
                        'sku_id' => $skuId,
                        'path' => $path,
                        'base' => $host,
                        'error' => $lastError,
                    ]);
                    if ($this->isProductStatusRestrictionError($lastError)) {
                        $this->rememberSkipInventoryUpdateApi();
                        break;
                    }
                }
            }
        }

        $full = $this->partialEditInventoryParams($productId, $skuId, $rows);
        if (! $this->partialEditSkuHasSellerSku($full)) {
            $full = $this->partialEditInventoryParams($productId, $skuId, $rows, true);
        }
        if (! $this->partialEditSkuHasSellerSku($full)) {
            return [
                'success' => false,
                'message' => $lastError !== 'TikTok inventory update failed.'
                    ? $lastError
                    : 'Partial Edit blocked: seller_sku missing for '.$skuId,
            ];
        }

        $paths = [
            "/product/202509/products/{$productId}/partial_edit",
            "/product/202309/products/{$productId}/partial_edit",
        ];
        $triedForceSearch = false;
        foreach ($paths as $path) {
            try {
                $this->tiktokOpenApi('POST', $path, [], $full, 20, false, $host);
                $this->workingInventoryPath = str_contains($path, '202509') ? '202509|partial' : '202309|partial';
                Log::info('TikTok inventory updated via Open API', [
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'path' => $path,
                    'base' => $host,
                ]);

                return ['success' => true, 'message' => 'Inventory updated.'];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->rememberIpAllowList($lastError);
                if ($this->ipAllowListBlocked) {
                    return ['success' => false, 'message' => $lastError];
                }
                Log::info('TikTok Open API inventory attempt failed', [
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'path' => $path,
                    'base' => $host,
                    'error' => $lastError,
                ]);
                if ($this->isEnforcementBlockedError($lastError)) {
                    return ['success' => false, 'message' => $lastError];
                }
                if (! $triedForceSearch && $this->isSalesAttributesError($lastError)) {
                    $triedForceSearch = true;
                    $retryBody = $this->partialEditInventoryParams($productId, $skuId, $rows, true);
                    if ($this->partialEditSkuHasSellerSku($retryBody)) {
                        $full = $retryBody;
                        try {
                            $this->tiktokOpenApi('POST', $path, [], $full, 20, false, $host);
                            $this->workingInventoryPath = str_contains($path, '202509') ? '202509|partial' : '202309|partial';
                            Log::info('TikTok inventory updated via Open API', [
                                'product_id' => $productId,
                                'sku_id' => $skuId,
                                'path' => $path,
                                'base' => $host,
                                'retry' => 'sales_attributes',
                            ]);

                            return ['success' => true, 'message' => 'Inventory updated.'];
                        } catch (\Throwable $retryEx) {
                            $lastError = $retryEx->getMessage();
                            $this->rememberIpAllowList($lastError);
                            if ($this->ipAllowListBlocked || $this->isEnforcementBlockedError($lastError)) {
                                return ['success' => false, 'message' => $lastError];
                            }
                        }
                    }
                }
            }
        }

        return ['success' => false, 'message' => $lastError];
    }

    /**
     * @param  array{success: bool, message: string}  $result
     * @return array{success: bool, message: string}
     */
    protected function finishInventoryUpdateSuccess(string $productId, string $skuId, array $result): array
    {
        // Partial Edit already sent seller_sku. Qty-only inventory/update does not —
        // restore if Seller Center is still blank.
        if (! str_contains((string) ($this->workingInventoryPath ?? ''), 'partial')) {
            $this->restoreSellerSkuIfBlank($productId, $skuId);
        }

        return $result;
    }

    /**
     * Title / image / description Partial Edit must never send a SKU node
     * without seller_sku. Attach current SKUs when we have them.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function withPreservedSellerSkus(string $productId, array $body): array
    {
        $nodes = $this->skuNodesPreservingSellerSku($productId);
        if ($nodes !== []) {
            $body['skus'] = $nodes;
        }

        return $this->sanitizePartialEditBody($productId, $body);
    }

    /**
     * Drop any SKU node missing a valid seller_sku. Never send seller_sku: "".
     * An empty skus array is removed so TikTok does not wipe every variant.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function sanitizePartialEditBody(string $productId, array $body): array
    {
        if (! isset($body['skus']) || ! is_array($body['skus'])) {
            return $body;
        }

        $kept = [];
        foreach ($body['skus'] as $sku) {
            if (! is_array($sku)) {
                continue;
            }
            $skuId = trim((string) ($sku['id'] ?? $sku['sku_id'] ?? ''));
            $seller = trim((string) ($sku['seller_sku'] ?? ''));
            if ($seller === '' && $skuId !== '') {
                $seller = $this->localSellerSku($productId, $skuId);
            }
            if ($skuId === '' || ! $this->localSellerSkuLooksValid($seller)) {
                continue;
            }
            $sku['id'] = $skuId;
            $sku['seller_sku'] = $seller;
            $kept[] = $sku;
        }

        if ($kept === []) {
            unset($body['skus']);
        } else {
            $body['skus'] = $kept;
        }

        return $body;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function skuNodesPreservingSellerSku(string $productId): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return [];
        }

        $data = $this->searchProductDataById($productId);
        if ($data === []) {
            try {
                $data = $this->fetchProductData($productId);
            } catch (\Throwable $e) {
                $data = [];
            }
        }

        $skus = $data['skus'] ?? $data['sku_list'] ?? [];
        if (! is_array($skus) || $skus === []) {
            return $this->localSkuNodesPreservingSellerSku($productId);
        }

        $out = [];
        $seen = [];
        foreach ($skus as $node) {
            if (! is_array($node)) {
                continue;
            }
            $skuId = trim((string) ($node['id'] ?? $node['sku_id'] ?? ''));
            if ($skuId === '' || isset($seen[$skuId])) {
                continue;
            }
            $seller = $this->sellerSkuForPartialEdit($productId, $skuId, $node);
            if ($seller === '') {
                continue;
            }
            $seen[$skuId] = true;
            $row = [
                'id' => $skuId,
                'seller_sku' => $seller,
            ];
            $attrs = $this->sanitizeSalesAttributes(is_array($node['sales_attributes'] ?? null) ? $node['sales_attributes'] : []);
            if ($attrs !== []) {
                $row['sales_attributes'] = $attrs;
            }
            $out[] = $row;
        }

        return $out !== [] ? $out : $this->localSkuNodesPreservingSellerSku($productId);
    }

    /**
     * @return list<array{id: string, seller_sku: string}>
     */
    protected function localSkuNodesPreservingSellerSku(string $productId): array
    {
        $table = $this->localProductsTable();
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'sku_id')) {
            return [];
        }

        try {
            $rows = DB::table($table)
                ->where('product_id', $productId)
                ->whereNotNull('sku_id')
                ->where('sku_id', '!=', '')
                ->get(['sku_id', 'sku']);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $skuId = trim((string) ($row->sku_id ?? ''));
            $seller = trim((string) ($row->sku ?? ''));
            if ($skuId === '' || isset($seen[$skuId]) || ! $this->localSellerSkuLooksValid($seller)) {
                continue;
            }
            $seen[$skuId] = true;
            $out[] = [
                'id' => $skuId,
                'seller_sku' => $seller,
            ];
        }

        return $out;
    }

    /**
     * @param  array{success: bool, message: string}  $result
     * @return array{success: bool, message: string}
     */
    protected function finishNonInventoryPartialEditSuccess(string $productId, array $result): array
    {
        $this->restoreSellerSkusOnProduct($productId);

        return $result;
    }

    protected function restoreSellerSkusOnProduct(string $productId): void
    {
        $productId = trim($productId);
        if ($productId === '') {
            return;
        }

        $skuIds = [];
        $data = $this->searchProductDataById($productId);
        foreach (($data['skus'] ?? $data['sku_list'] ?? []) as $node) {
            if (! is_array($node)) {
                continue;
            }
            $skuId = trim((string) ($node['id'] ?? $node['sku_id'] ?? ''));
            if ($skuId !== '') {
                $skuIds[$skuId] = true;
            }
        }

        $table = $this->localProductsTable();
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'sku_id')) {
            try {
                foreach (DB::table($table)->where('product_id', $productId)->pluck('sku_id') as $skuId) {
                    $skuId = trim((string) $skuId);
                    if ($skuId !== '') {
                        $skuIds[$skuId] = true;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        foreach (array_keys($skuIds) as $skuId) {
            $this->restoreSellerSkuIfBlank($productId, $skuId);
        }
    }

    /**
     * After a qty push, write seller_sku back if Seller Center is blank.
     *
     * @return array{success: bool, restored: bool, message: string}
     */
    public function restoreSellerSkuIfBlank(string $productId, string $skuId): array
    {
        $productId = trim($productId);
        $skuId = trim($skuId);
        if ($productId === '' || $skuId === '') {
            return ['success' => false, 'restored' => false, 'message' => 'Missing product_id or sku_id.'];
        }

        $node = $this->skuNodeForPartialEdit($productId, $skuId, false);
        $live = trim((string) ($node['seller_sku'] ?? $node['sku'] ?? ''));
        if ($this->localSellerSkuLooksValid($live)) {
            return ['success' => true, 'restored' => false, 'message' => 'Seller SKU already present.'];
        }

        $local = $this->localSellerSku($productId, $skuId);
        if ($local === '') {
            return ['success' => false, 'restored' => false, 'message' => 'No local seller SKU to restore.'];
        }

        return $this->postSellerSkuRestore($productId, $skuId, $local, $node);
    }

    /**
     * One-time repair: restore blank live seller_sku from local product tables.
     * Does not create listings or change inventory.
     *
     * @return array{scanned: int, blank: int, restored: int, skipped: int, failed: int, message: string}
     */
    public function restoreBlankSellerSkus(bool $dryRun = false): array
    {
        $scanned = 0;
        $blank = 0;
        $restored = 0;
        $skipped = 0;
        $failed = 0;

        if (! $this->accessToken) {
            return [
                'scanned' => 0,
                'blank' => 0,
                'restored' => 0,
                'skipped' => 0,
                'failed' => 0,
                'message' => 'TikTok access token not available.',
            ];
        }

        $this->client->setAccessToken($this->accessToken);
        $this->ensureShopCipher();

        $products = $this->getAllProducts();
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $productId = trim((string) ($product['id'] ?? $product['product_id'] ?? ''));
            if ($productId === '') {
                continue;
            }
            $skus = $product['skus'] ?? $product['sku_list'] ?? [];
            if (! is_array($skus) || $skus === []) {
                continue;
            }
            foreach ($skus as $skuRow) {
                if (! is_array($skuRow)) {
                    continue;
                }
                $scanned++;
                $skuId = trim((string) ($skuRow['id'] ?? $skuRow['sku_id'] ?? ''));
                $live = trim((string) ($skuRow['seller_sku'] ?? $skuRow['sku'] ?? ''));
                if ($this->localSellerSkuLooksValid($live)) {
                    continue;
                }
                $blank++;
                if ($skuId === '') {
                    $skipped++;
                    continue;
                }
                $local = $this->localSellerSku($productId, $skuId);
                if ($local === '') {
                    $skipped++;
                    Log::info('TikTok seller_sku restore skipped (no local SKU)', [
                        'product_id' => $productId,
                        'sku_id' => $skuId,
                    ]);
                    continue;
                }
                if ($dryRun) {
                    $this->output('info', "Would restore {$productId}/{$skuId} → {$local}");
                    $restored++;
                    continue;
                }
                $result = $this->postSellerSkuRestore($productId, $skuId, $local, $skuRow);
                if (! empty($result['restored'])) {
                    $restored++;
                    $this->output('info', "Restored seller_sku {$local} on {$productId}/{$skuId}");
                } elseif ($this->isEnforcementBlockedError((string) ($result['message'] ?? ''))) {
                    $skipped++;
                    $this->output('warn', "Skipped enforcement-blocked {$productId}: ".($result['message'] ?? ''));
                } else {
                    $failed++;
                    $this->output('error', "Failed restore {$productId}/{$skuId}: ".($result['message'] ?? ''));
                }
                usleep(150000);
            }
        }

        $label = $dryRun ? 'Would restore' : 'Restored';
        $message = "Scanned {$scanned} SKUs, {$blank} blank. {$label} {$restored}, skipped {$skipped}, failed {$failed}.";

        return [
            'scanned' => $scanned,
            'blank' => $blank,
            'restored' => $restored,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{success: bool, restored: bool, message: string}
     */
    protected function postSellerSkuRestore(string $productId, string $skuId, string $sellerSku, array $node): array
    {
        $sku = [
            'id' => $skuId,
            'seller_sku' => $sellerSku,
        ];
        $attrs = $this->sanitizeSalesAttributes(is_array($node['sales_attributes'] ?? null) ? $node['sales_attributes'] : []);
        if ($attrs === []) {
            $fresh = $this->skuNodeForPartialEdit($productId, $skuId, true);
            $attrs = $this->sanitizeSalesAttributes(is_array($fresh['sales_attributes'] ?? null) ? $fresh['sales_attributes'] : []);
        }
        if ($attrs !== []) {
            $sku['sales_attributes'] = $attrs;
        }
        $body = ['skus' => [$sku]];
        $host = rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: 'https://open-api.tiktokglobalshop.com'), '/');
        $paths = [
            "/product/202509/products/{$productId}/partial_edit",
            "/product/202309/products/{$productId}/partial_edit",
        ];
        $lastError = 'TikTok seller_sku restore failed.';
        foreach ($paths as $path) {
            try {
                $this->tiktokOpenApi('POST', $path, [], $body, 20, false, $host);
                unset($this->productSearchCache[$productId], $this->productDetailCache[$productId]);
                Log::info('TikTok seller_sku restored via Partial Edit', [
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'seller_sku' => $sellerSku,
                    'path' => $path,
                ]);

                return ['success' => true, 'restored' => true, 'message' => 'Seller SKU restored.'];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->rememberIpAllowList($lastError);
                if ($this->ipAllowListBlocked || $this->isEnforcementBlockedError($lastError)) {
                    return ['success' => false, 'restored' => false, 'message' => $lastError];
                }
                Log::info('TikTok seller_sku restore attempt failed', [
                    'product_id' => $productId,
                    'sku_id' => $skuId,
                    'path' => $path,
                    'error' => $lastError,
                ]);
            }
        }

        return ['success' => false, 'restored' => false, 'message' => $lastError];
    }

    /**
     * @param  list<array{warehouse_id?: string, quantity: int}>  $rows
     * @return list<array{warehouse_id?: string, quantity: int}>
     */
    protected function ensureWarehouseOnInventoryRows(array $rows): array
    {
        $needsWarehouse = false;
        foreach ($rows as $row) {
            if (trim((string) ($row['warehouse_id'] ?? '')) === '') {
                $needsWarehouse = true;
                break;
            }
        }
        if (! $needsWarehouse) {
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

    protected function activateProductForInventory(string $productId): bool
    {
        $productId = trim($productId);
        if ($productId === '') {
            return false;
        }

        $hosts = array_values(array_unique(array_filter([
            rtrim((string) (config('services.'.$this->configKey.'.api_base') ?: ''), '/'),
            'https://open-api.tiktokglobalshop.com',
            'https://open-api-us.tiktokglobalshop.com',
        ])));
        $paths = [
            '/product/202309/products/activate',
            '/product/202509/products/activate',
        ];
        $body = ['product_ids' => [$productId]];

        foreach ($hosts as $base) {
            foreach ($paths as $path) {
                try {
                    $this->tiktokOpenApi('POST', $path, [], $body, 45, false, $base);
                    usleep(250000);
                    Log::info('TikTok product activated for inventory update', [
                        'product_id' => $productId,
                        'path' => $path,
                    ]);

                    return true;
                } catch (\Throwable $e) {
                    $msg = strtolower($e->getMessage());
                    if (str_contains($msg, 'already')) {
                        return true;
                    }
                    Log::info('TikTok activate-for-inventory failed', [
                        'product_id' => $productId,
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
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
        $defaultWid = trim((string) ($this->resolveDefaultWarehouseId() ?? ''));
        foreach ($warehouses as $row) {
            $wid = trim((string) ($row['warehouse_id'] ?? ''));
            if ($wid === '') {
                continue;
            }
            $q = (int) ($row['quantity'] ?? 0);
            $preferDefault = $q === $bestQty && $defaultWid !== '' && $wid === $defaultWid;
            if ($bestWid === '' || $q > $bestQty || $preferDefault) {
                $bestQty = $q;
                $bestWid = $wid;
            }
        }

        // All warehouses at 0: write the qty to every warehouse so a listing
        // bound to a non-default warehouse still updates (e.g. LS 180-6 at 0).
        $broadcast = $bestQty <= 0;

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
                'quantity' => ($broadcast || $wid === $bestWid) ? max(0, $quantity) : 0,
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

    /**
     * Seller Center-style category picker: live TikTok Recommend Category + GET Categories.
     *
     * @return array{success: bool, categories: list<array{id: string, path: string, suggested?: bool, restricted?: bool}>, message?: string}
     */
    public function searchListingCategories(string $query, string $title = '', string $description = ''): array
    {
        $query = trim($query);
        $title = trim($title);
        $description = trim(strip_tags($description));
        $keyword = $query !== '' ? $query : $title;

        try {
            if (! $this->accessToken) {
                return ['success' => false, 'categories' => [], 'message' => 'TikTok Shop is not connected. Open Connect and authorize the shop.'];
            }

            $this->client->setAccessToken($this->accessToken);
            // Cache/file/config only — getShopInfo can hang on IP allow-list and freezes the picker.
            $this->ensureShopCipher(false);

            if ($keyword === '') {
                return ['success' => true, 'categories' => []];
            }

            $categories = $this->recommendCategoriesFromMarketplace($keyword, $description);
            if ($categories === [] && $query !== '' && $title !== '' && strcasecmp($query, $title) !== 0) {
                $categories = $this->recommendCategoriesFromMarketplace($title, $description);
            }
            if ($categories === []) {
                $categories = $this->filterMarketplaceCategoryLeaves($keyword);
            }

            return [
                'success' => true,
                'categories' => $categories,
                'message' => $categories === []
                    ? 'TikTok returned no matching categories. Try a fuller product name (e.g. “6 inch ceiling speaker”).'
                    : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('TikTok category search failed', ['error' => $e->getMessage()]);
            $message = $e->getMessage();
            if ($this->isExpiredAccessTokenMessage(0, $message)) {
                $message = 'TikTok Shop access token expired. Open Connect and re-authorize the shop, then search again.';
            }

            return [
                'success' => false,
                'categories' => [],
                'message' => 'TikTok category search failed: '.$message,
            ];
        }
    }

    /**
     * @return list<array{id: string, path: string, suggested?: bool, restricted?: bool}>
     */
    protected function recommendCategoriesFromMarketplace(string $productTitle, string $description = ''): array
    {
        // Use cached leaf names only. Never download the full GET /categories tree here —
        // that call routinely exceeds 45s and leaves the editor stuck on “Searching…”.
        $byId = [];
        foreach ($this->listingCategoryLeaves(false) as $row) {
            $byId[$row['id']] = $row;
        }

        $lastError = null;
        $gotResponse = false;
        foreach ($this->tiktokCategoryVersions() as $version) {
            try {
                $data = $this->fetchRecommendCategory($productTitle, $description, $version);
                $gotResponse = true;
                $parsed = $this->parseRecommendCategoryResponse($data, $byId);
                if ($parsed !== []) {
                    return $parsed;
                }
                Log::info('TikTok recommendCategory returned no parseable leaves', [
                    'version' => $version,
                    'keys' => array_keys($data),
                ]);
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::info('TikTok recommendCategory HTTP failed', [
                    'version' => $version,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $gotResponse && $lastError) {
            throw $lastError;
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function listingCategoryVersion(): string
    {
        return 'v2';
    }

    /**
     * All-region TikTok shops must use V2 categories (error 12052217).
     *
     * @return list<string>
     */
    protected function tiktokCategoryVersions(): array
    {
        return [$this->listingCategoryVersion()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchRecommendCategory(string $productTitle, string $description, string $version): array
    {
        $payload = [
            'product_title' => $productTitle,
            'description' => mb_substr($description, 0, 2000),
            'category_version' => $version,
        ];

        $data = $this->tiktokOpenApi('POST', '/product/202309/categories/recommend', [], $payload, 15);
        if (isset($data['data']) && is_array($data['data']) && ! isset($data['leaf_category_id']) && ! isset($data['categories'])) {
            $data = $data['data'];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{id: string, name: string, path: string, restricted: bool}>  $leavesById
     * @return list<array{id: string, path: string, suggested: bool, restricted: bool}>
     */
    protected function parseRecommendCategoryResponse(array $data, array $leavesById): array
    {
        $out = [];
        $normalizePath = static function (string $path): string {
            $path = trim(str_replace([' > ', '>', ' / '], ' - ', $path));
            $path = preg_replace('/\s+-\s+/', ' - ', $path) ?? $path;

            return trim($path, " -\t");
        };
        $push = function (string $id, string $path, bool $suggested = true) use (&$out, $leavesById, $normalizePath): void {
            $id = trim($id);
            if ($id === '' || isset($out[$id])) {
                return;
            }
            $path = $normalizePath($path);
            if ($path === '' || $path === $id) {
                $path = $leavesById[$id]['path'] ?? $path;
            }
            if ($path === '') {
                $path = $leavesById[$id]['name'] ?? ('Category '.$id);
            }
            $out[$id] = [
                'id' => $id,
                'path' => $path,
                'suggested' => $suggested,
                'restricted' => (bool) ($leavesById[$id]['restricted'] ?? false),
            ];
        };

        $pathFromRows = static function (array $rows): string {
            $names = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['local_name'] ?? $row['name'] ?? $row['category_name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            return implode(' - ', $names);
        };

        $walkTree = function (array $items, array $ancestors) use (&$walkTree, $push): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? $item['category_id'] ?? ''));
                $name = trim((string) ($item['local_name'] ?? $item['name'] ?? $item['category_name'] ?? ''));
                $parts = $ancestors;
                if ($name !== '') {
                    $parts[] = $name;
                }
                $children = $item['children'] ?? $item['child_categories'] ?? null;
                $hasChildren = is_array($children) && $children !== [];
                $isLeaf = ! empty($item['is_leaf']) || ! $hasChildren;
                if ($isLeaf && $id !== '') {
                    $push($id, implode(' - ', $parts));
                }
                if ($hasChildren) {
                    $walkTree($children, $parts);
                }
            }
        };

        $pathRows = $data['categories'] ?? $data['category_tree'] ?? null;
        if (is_array($pathRows) && $pathRows !== [] && isset($pathRows[0]) && is_array($pathRows[0])) {
            $looksLikeTree = isset($pathRows[0]['children']) || isset($pathRows[0]['child_categories']);
            if ($looksLikeTree) {
                $walkTree($pathRows, []);
            } else {
                $joined = $pathFromRows($pathRows);
                $last = $pathRows[array_key_last($pathRows)];
                $id = trim((string) ($last['id'] ?? $last['category_id'] ?? $data['leaf_category_id'] ?? ''));
                $push($id, $joined);
                foreach ($pathRows as $row) {
                    if (! empty($row['is_leaf'])) {
                        $push(
                            trim((string) ($row['id'] ?? $row['category_id'] ?? '')),
                            $joined !== '' ? $joined : trim((string) ($row['local_name'] ?? $row['name'] ?? ''))
                        );
                    }
                }
            }
        }

        foreach (['recommended_category_list', 'recommend_categories', 'category_list', 'product_cates', 'categories'] as $key) {
            foreach ((array) ($data[$key] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $children = $row['children'] ?? $row['child_categories'] ?? null;
                if (is_array($children) && $children !== []) {
                    $walkTree([$row], []);
                    continue;
                }
                if (array_key_exists('is_leaf', $row) && empty($row['is_leaf'])) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? $row['category_id'] ?? $row['cate_id'] ?? $row['leaf_category_id'] ?? ''));
                $path = trim((string) ($row['category_path'] ?? $row['path'] ?? ''));
                if ($path === '') {
                    $path = $pathFromRows($row['ancestors'] ?? $row['parent_categories'] ?? [])
                        ?: trim((string) ($row['local_name'] ?? $row['name'] ?? $row['category_name'] ?? ''));
                }
                $push($id, $path);
            }
        }

        $leafId = trim((string) ($data['leaf_category_id'] ?? $data['category_id'] ?? ''));
        $push($leafId, $leavesById[$leafId]['path'] ?? '');

        return array_values($out);
    }

    /**
     * @return list<array{id: string, path: string, suggested?: bool, restricted?: bool}>
     */
    protected function filterMarketplaceCategoryLeaves(string $keyword): array
    {
        $q = mb_strtolower(trim($keyword));
        if ($q === '') {
            return [];
        }
        $out = [];
        foreach ($this->listingCategoryLeaves(false) as $row) {
            $hay = mb_strtolower($row['path'].' '.$row['name']);
            if (! str_contains($hay, $q)) {
                continue;
            }
            $out[] = [
                'id' => $row['id'],
                'path' => $row['path'],
                'restricted' => $row['restricted'],
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Live GET /product/202309/categories from TikTok (US shops use v2).
     *
     * @return list<array{id: string, name: string, path: string, restricted: bool}>
     */
    protected function listingCategoryLeaves(bool $fetchIfMissing = true): array
    {
        foreach ($this->tiktokCategoryVersions() as $version) {
            $cacheKey = $this->cachePrefix.'_listing_category_leaves_'.$version;
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
            if (! $fetchIfMissing) {
                continue;
            }

            $raw = $this->fetchMarketplaceCategoryTree($version);
            $leaves = $this->flattenTikTokCategoryLeaves($raw);
            if ($leaves !== []) {
                Cache::put($cacheKey, $leaves, now()->addHours(6));

                return $leaves;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchMarketplaceCategoryTree(string $version): array
    {
        $query = [
            'locale' => 'en-US',
            'category_version' => $version,
        ];

        try {
            $response = $this->client->Product->useVersion('202309')->getCategories($query);
            $raw = $response['categories'] ?? $response;
            if (is_array($raw) && $raw !== []) {
                return array_values($raw);
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok getCategories SDK failed, retrying HTTP', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $data = $this->tiktokOpenApi('GET', '/product/202309/categories', $query);
            $raw = $data['categories'] ?? $data['category_list'] ?? [];

            return is_array($raw) ? array_values($raw) : [];
        } catch (\Throwable $e) {
            Log::warning('TikTok getCategories HTTP failed', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Signed Open API call that skips local SSL inspection (cURL 60).
     *
     * @param  array<string, string>  $query
     * @param  array<string, mixed>|null  $jsonBody
     * @return array<string, mixed>
     */
    protected function tiktokOpenApi(string $method, string $path, array $query = [], ?array $jsonBody = null, int $timeout = 45, bool $retried = false, ?string $apiBase = null): array
    {
        if ($jsonBody !== null && preg_match('#/products/([^/]+)/partial_edit#', $path, $m)) {
            $jsonBody = $this->sanitizePartialEditBody((string) $m[1], $jsonBody);
        }

        $originalQuery = $query;
        $query['app_key'] = (string) $this->clientKey;
        $query['timestamp'] = (string) time();
        if (is_string($this->shopCipher) && $this->shopCipher !== '') {
            $query['shop_cipher'] = $this->shopCipher;
        }
        $body = $jsonBody === null ? '' : (string) json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $query['sign'] = $this->signTikTokRequest($path, $query, $body);
        $base = rtrim((string) ($apiBase ?: (config('services.'.$this->configKey.'.api_base') ?: 'https://open-api.tiktokglobalshop.com')), '/');
        $url = $base.$path;
        $request = Http::withoutVerifying()
            ->withHeaders([
                'x-tts-access-token' => (string) $this->accessToken,
                'Content-Type' => 'application/json',
            ])
            ->timeout(max(5, $timeout))
            ->connectTimeout(8);

        $response = strtoupper($method) === 'GET'
            ? $request->get($url, $query)
            : $request->withBody($body, 'application/json')->post($url.'?'.http_build_query($query));

        $json = $response->json() ?? [];
        $code = (int) ($json['code'] ?? -1);
        $message = (string) ($json['message'] ?? 'TikTok API error');
        if ($code !== 0) {
            if (
                ! $retried
                && $this->isExpiredAccessTokenMessage($code, $message)
                && $this->refreshAccessToken()
            ) {
                return $this->tiktokOpenApi($method, $path, $originalQuery, $jsonBody, $timeout, true, $apiBase);
            }
            throw new \RuntimeException(trim($code.': '.$message));
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    protected function isExpiredAccessTokenMessage(int $code, string $message): bool
    {
        if ($this->isInvalidApiVersionError($message)) {
            return false;
        }
        if (in_array($code, [105001, 105002], true)) {
            return true;
        }

        return (bool) preg_match('/expired credentials|access_token.+expir|token expir/i', $message);
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array{id: string, name: string, path: string, restricted: bool}>
     */
    protected function flattenTikTokCategoryLeaves(array $raw): array
    {
        $nodes = [];
        $walk = function (array $items, array $ancestors) use (&$walk, &$nodes): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? $item['category_id'] ?? ''));
                $name = trim((string) ($item['local_name'] ?? $item['name'] ?? $item['category_name'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $pathParts = $ancestors;
                if ($name !== '') {
                    $pathParts[] = $name;
                }
                $statuses = $item['permission_statuses'] ?? [];
                $restricted = is_array($statuses)
                    && $statuses !== []
                    && ! in_array('AVAILABLE', $statuses, true);
                $nodes[$id] = [
                    'id' => $id,
                    'parent_id' => trim((string) ($item['parent_id'] ?? '0')),
                    'name' => $name !== '' ? $name : $id,
                    'path_parts' => $pathParts,
                    'is_leaf' => (bool) ($item['is_leaf'] ?? empty($item['children'])),
                    'restricted' => $restricted,
                ];
                $children = $item['children'] ?? $item['child_categories'] ?? null;
                if (is_array($children) && $children !== []) {
                    $nodes[$id]['is_leaf'] = false;
                    $walk($children, $pathParts);
                }
            }
        };
        $walk($raw, []);

        $hasNested = false;
        foreach ($nodes as $node) {
            if (count($node['path_parts']) > 1) {
                $hasNested = true;
                break;
            }
        }
        if (! $hasNested) {
            foreach ($nodes as $id => $node) {
                $parts = [];
                $guard = 0;
                $cursor = $id;
                while ($cursor !== '' && $cursor !== '0' && isset($nodes[$cursor]) && $guard < 12) {
                    array_unshift($parts, $nodes[$cursor]['name']);
                    $cursor = $nodes[$cursor]['parent_id'];
                    $guard++;
                }
                $nodes[$id]['path_parts'] = $parts !== [] ? $parts : [$node['name']];
            }
        }

        $leaves = [];
        foreach ($nodes as $node) {
            if (! $node['is_leaf']) {
                continue;
            }
            $leaves[] = [
                'id' => $node['id'],
                'name' => $node['name'],
                'path' => implode(' - ', $node['path_parts']),
                'restricted' => $node['restricted'],
            ];
        }

        return $leaves;
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->clientKey) !== ''
            && trim((string) $this->clientSecret) !== ''
            && trim((string) $this->accessToken) !== '';
    }

    /**
     * @param  list<string>  $urls
     * @return list<array{uri: string}>
     */
    public function uploadListingImageUris(array $urls): array
    {
        $uris = [];
        foreach (array_slice(array_values($urls), 0, 9) as $url) {
            $uri = $this->tiktokUploadImageFromUrl((string) $url, 'MAIN_IMAGE');
            if ($uri !== null && $uri !== '') {
                $uris[] = ['uri' => $uri];
            }
        }

        return $uris;
    }

    public function listingWarehouseId(): ?string
    {
        $this->ensureShopCipher(false);

        return $this->resolveDefaultWarehouseId();
    }

    /**
     * @return array{id: string, path: string}
     */
    public function productCategory(string $productId): array
    {
        $empty = ['id' => '', 'path' => ''];
        $productId = trim($productId);
        if ($productId === '') {
            return $empty;
        }

        try {
            $data = $this->fetchProductData($productId);
        } catch (\Throwable) {
            return $empty;
        }

        $id = $this->tiktokCategoryIdFromProduct($data);
        $path = '';
        $chains = $data['category_chains'] ?? $data['categories'] ?? [];
        if (is_array($chains)) {
            $names = [];
            foreach ($chains as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['local_name'] ?? $row['name'] ?? $row['category_name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $path = implode(' - ', $names);
        }

        return ['id' => $id, 'path' => $path];
    }

    public function searchBrandId(string $categoryId, string $brandName): string
    {
        $categoryId = trim($categoryId);
        $brandName = trim($brandName);
        if ($categoryId === '' || $brandName === '' || ! $this->accessToken) {
            return '';
        }

        $this->ensureShopCipher(false);
        foreach (['/product/202309/brands', '/product/202509/brands'] as $path) {
            try {
                $data = $this->tiktokOpenApi('GET', $path, [
                    'category_id' => $categoryId,
                    'brand_name' => $brandName,
                    'page_size' => '20',
                    'category_version' => $this->listingCategoryVersion(),
                ], null, 20);
            } catch (\Throwable) {
                continue;
            }
            foreach (($data['brands'] ?? $data['brand_list'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? $row['brand_id'] ?? ''));
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array{id: string, values: list<array<string, string>>}>
     */
    public function requiredAttributesForCategory(string $categoryId): array
    {
        $categoryId = trim($categoryId);
        if ($categoryId === '' || ! $this->accessToken) {
            return [];
        }

        $this->ensureShopCipher(false);
        $data = [];
        foreach (["/product/202309/categories/{$categoryId}/attributes", "/product/202509/categories/{$categoryId}/attributes"] as $path) {
            try {
                $data = $this->tiktokOpenApi('GET', $path, [
                    'locale' => 'en-US',
                    'category_version' => $this->listingCategoryVersion(),
                ], null, 20);
                if ($data !== []) {
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $list = $data['attributes'] ?? $data['category_attributes'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $type = strtoupper((string) ($attr['type'] ?? $attr['attribute_type'] ?? $attr['attribute_type_name'] ?? ''));
            if (str_contains($type, 'SALES')) {
                continue;
            }
            $required = ! empty($attr['is_required'])
                || ! empty($attr['is_requried'])
                || ! empty($attr['is_mandatory'])
                || strtoupper((string) ($attr['is_required'] ?? '')) === 'TRUE';
            if (! $required) {
                continue;
            }
            $id = trim((string) ($attr['id'] ?? $attr['attribute_id'] ?? ''));
            $values = $attr['values'] ?? $attr['value_list'] ?? [];
            if ($id === '' || ! is_array($values) || $values === []) {
                continue;
            }
            $picked = $this->pickPreferredAttributeValue($values);
            if (! is_array($picked)) {
                $first = $values[0] ?? null;
                $picked = is_array($first) ? $first : null;
            }
            if (! is_array($picked)) {
                continue;
            }
            $valueId = trim((string) ($picked['id'] ?? $picked['value_id'] ?? ''));
            $valueName = trim((string) ($picked['name'] ?? $picked['value_name'] ?? ''));
            if ($valueId === '' && $valueName === '') {
                continue;
            }
            $value = [];
            if ($valueId !== '') {
                $value['id'] = $valueId;
            }
            if ($valueName !== '') {
                $value['name'] = $valueName;
            }
            $out[] = ['id' => $id, 'values' => [$value]];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, product_id?: string, sku_id?: string, skus?: list<array<string, mixed>>}
     */
    public function createListingProduct(array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'TikTok Shop is not connected. Open Connect and authorize the shop.'];
        }

        $this->client->setAccessToken($this->accessToken);
        $this->ensureShopCipher(true);
        if (is_string($this->shopCipher) && $this->shopCipher !== '') {
            $this->client->setShopCipher($this->shopCipher);
        }
        if (! isset($payload['category_version']) || trim((string) $payload['category_version']) === '') {
            $payload['category_version'] = $this->listingCategoryVersion();
        }

        $lastError = '';
        $versionError = '';
        foreach (['202309', '202509'] as $version) {
            try {
                $data = $this->client->Product->useVersion($version)->createProduct($payload);
                $parsed = $this->listingCreateSuccess(is_array($data) ? $data : []);
                if ($parsed['success'] ?? false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                $msg = $this->sanitizeTikTokClientError($e->getMessage());
                Log::warning('TikTok SDK create product failed', [
                    'channel' => $this->configKey,
                    'version' => $version,
                    'error' => $msg,
                ]);
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    $versionError = $msg;
                    continue;
                }
                $lastError = $msg !== '' ? $msg : $e->getMessage();
            }
        }

        foreach (['/product/202309/products', '/product/202509/products'] as $path) {
            try {
                $data = $this->tiktokOpenApi('POST', $path, [], $payload, 90);

                return $this->listingCreateSuccess(is_array($data) ? $data : []);
            } catch (\Throwable $e) {
                $msg = $this->sanitizeTikTokClientError($e->getMessage());
                Log::warning('TikTok create product failed', [
                    'channel' => $this->configKey,
                    'path' => $path,
                    'error' => $msg,
                ]);
                if ($this->isInvalidApiVersionError($e->getMessage()) || $this->isNoSchemaError($e->getMessage())) {
                    $versionError = $msg;
                    continue;
                }
                $lastError = $msg !== '' ? $msg : $e->getMessage();
            }
        }

        return [
            'success' => false,
            'message' => $lastError !== '' ? $lastError : ($versionError !== '' ? $versionError : 'TikTok create product failed.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, product_id?: string|null, sku_id?: string|null, skus?: list<array<string, mixed>>}
     */
    private function listingCreateSuccess(array $data): array
    {
        $productId = trim((string) ($data['product_id'] ?? $data['id'] ?? ''));
        $skus = is_array($data['skus'] ?? null) ? $data['skus'] : [];
        $skuId = '';
        foreach ($skus as $row) {
            if (! is_array($row)) {
                continue;
            }
            $skuId = trim((string) ($row['id'] ?? $row['sku_id'] ?? ''));
            if ($skuId !== '') {
                break;
            }
        }
        if ($productId !== '') {
            $this->activateProducts([$productId]);
        }

        return [
            'success' => true,
            'message' => $productId !== '' ? 'Published to TikTok Shop ('.$productId.').' : 'Published to TikTok Shop.',
            'product_id' => $productId !== '' ? $productId : null,
            'sku_id' => $skuId !== '' ? $skuId : null,
            'skus' => $skus,
        ];
    }

    /**
     * @param  list<string>  $productIds
     */
    public function activateProducts(array $productIds): void
    {
        $ids = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return;
        }

        foreach (['/product/202309/products/activate', '/product/202509/products/activate'] as $path) {
            try {
                $this->tiktokOpenApi('POST', $path, [], ['product_ids' => $ids], 30);
                return;
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
