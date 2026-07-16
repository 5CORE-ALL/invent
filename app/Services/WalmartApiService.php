<?php

namespace App\Services;

use App\Models\ProductStockMapping;
use App\Models\WalmartDailyData;
use App\Models\WalmartPricingSales;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Walmart Marketplace API service.
 *
 * - Auth: OAuth2 client_credentials token for v3 API.
 * - Price: Update item price via /v3/price; fetch listed price via /v3/items.
 * - Orders: Fetch purchase orders via /v3/orders into walmart_daily_data.
 * - Inventory: Fetch all inventory from /v3/inventories (paginated) and sync
 *   quantities to ProductStockMapping.inventory_walmart.
 */
class WalmartApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $marketplaceId;

    public function __construct()
    {
        $this->clientId      = config('services.walmart.client_id');
        $this->clientSecret  = config('services.walmart.client_secret');
        $this->baseUrl       = $this->normalizeApiBaseUrl((string) config('services.walmart.api_endpoint'));
        $this->marketplaceId = config('services.walmart.marketplace_id');
    }

    private function normalizeApiBaseUrl(string $endpoint): string
    {
        $base = rtrim(trim($endpoint), '/');
        if ($base === '') {
            $base = 'https://marketplace.walmartapis.com';
        }

        return (string) preg_replace('#/v3$#', '', $base);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $accessToken): array
    {
        return [
            'WM_SEC.ACCESS_TOKEN'   => $accessToken,
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'WM_MARKET_ID'          => (string) $this->marketplaceId,
            'Accept'                => 'application/json',
        ];
    }

    /**
     * Get OAuth2 access token for Walmart Marketplace API (v3).
     * Returns null if credentials are missing or token request fails.
     */
    public function getAccessToken(): ?string
    {
        $cacheKey = 'walmart_access_token';
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $clientId     = $this->clientId ?: config('services.walmart.client_id');
        $clientSecret = $this->clientSecret ?: config('services.walmart.client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('Walmart API: credentials missing.');
            return null;
        }

        $authorization = base64_encode("{$clientId}:{$clientSecret}");
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization'         => "Basic {$authorization}",
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                'WM_SVC.NAME'           => 'Walmart Marketplace',
                'Accept'                => 'application/json',
            ])
            ->asForm()
            ->post(rtrim($this->baseUrl, '/') . '/v3/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->successful()) {
            $token = $response->json()['access_token'] ?? null;
            if ($token) {
                // Walmart token is valid for ~15 minutes; cache for 14.
                Cache::put($cacheKey, $token, now()->addMinutes(14));
            }
            return $token;
        }

        $body = $response->json();
        Log::error('Walmart API: failed to get access token', [
            'status' => $response->status(),
            'error'  => is_array($body)
                ? ($body['error'] ?? $body['error_description'] ?? $body)
                : $response->body(),
        ]);
        return null;
    }



    /**
     * Update a single item's price on Walmart (v3 price API).
     *
     * @throws Exception if token is missing or API request fails
     */
    public function updatePrice(string $sku, float $price): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new Exception('Walmart API: no access token (check credentials).');
        }

        $payload = [
            'sku'     => $sku,
            'pricing' => [
                [
                    'currentPriceType' => 'BASE',
                    'currentPrice'    => [
                        'currency' => 'USD',
                        'amount'   => number_format($price, 2, '.', ''),
                    ],
                ],
            ],
        ];

        $endpoint = rtrim($this->baseUrl, '/') . '/v3/price';
        $response = Http::withHeaders(array_merge($this->apiHeaders($accessToken), [
            'Content-Type' => 'application/json',
        ]))->put($endpoint, $payload);

        if ($response->failed()) {
            throw new Exception('Failed to update Walmart price: ' . $response->body());
        }
        Log::info('Walmart price updated', ['sku' => $sku, 'response' => $response->json()]);
        return $response->json();
    }



    /**
     * Fetch all inventory from Walmart v3/inventories (paginated) and sync
     * quantities to ProductStockMapping.inventory_walmart.
     * Uses nextCursor for pagination; updates are batched for performance.
     *
     * @return array Raw inventory elements from the API
     * @throws \Exception if token missing or API request fails
     */
    public function getinventory(): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Walmart API: no access token (check credentials).');
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/v3/inventories';
        $limit    = 50;
        $cursor   = null;
        $collected = [];

        $request = Http::withHeaders($this->apiHeaders($accessToken));
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        do {
            $query = ['limit' => $limit];
            if ($cursor !== null && $cursor !== '') {
                $query['nextCursor'] = $cursor;
            }
            $response = $request->get($endpoint, $query);

            if ($response->failed()) {
                Log::error('Walmart inventory fetch failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \Exception('Failed to fetch Walmart inventory: ' . $response->body());
            }

            $json   = $response->json();
            $items  = $json['elements']['inventories'] ?? [];
            foreach ($items as $item) {
                $collected[] = $item;
            }
            $cursor = $json['meta']['nextCursor'] ?? null;
        } while ($cursor);

        $this->syncInventoryToMapping($collected);
        Log::info('Walmart inventory synced', ['count' => count($collected)]);
        return $collected;
    }

    /**
     * Batch-update ProductStockMapping.inventory_walmart from API inventory list.
     * Uses one bulk CASE WHEN UPDATE per chunk to avoid N+1 queries.
     */
    protected function syncInventoryToMapping(array $inventories): void
    {
        $updates = [];
        foreach ($inventories as $item) {
            $sku = $item['sku'] ?? null;
            if (!$sku) {
                Log::warning('Walmart inventory: missing SKU', $item);
                continue;
            }
            $quantity = 0;
            if (isset($item['nodes'][0]['availToSellQty']['amount'])) {
                $quantity = (int) $item['nodes'][0]['availToSellQty']['amount'];
            } elseif (isset($item['nodes'][0]['inputQty']['amount'])) {
                $quantity = (int) $item['nodes'][0]['inputQty']['amount'];
            }
            $updates[$sku] = $quantity;
        }

        $chunkSize = 100;
        $chunks    = array_chunk($updates, $chunkSize, true);
        foreach ($chunks as $chunk) {
            $skus    = array_keys($chunk);
            $cases   = [];
            $bindings = [];
            foreach ($chunk as $sku => $qty) {
                $cases[]    = 'WHEN ? THEN ?';
                $bindings[] = $sku;
                $bindings[] = $qty;
            }
            $placeholders = implode(' ', $cases);
            $inPlaceholders = implode(',', array_fill(0, count($skus), '?'));
            $bindings = array_merge($bindings, $skus);
            $table = (new ProductStockMapping)->getTable();
            DB::update(
                "UPDATE {$table} SET inventory_walmart = CASE sku {$placeholders} END WHERE sku IN ({$inPlaceholders})",
                $bindings
            );
        }
    }

    /**
     * Fetch all catalog items from Walmart GET /v3/items (paginated).
     * Each item includes listed price as price.amount.
     *
     * @param  callable|null  $onPage  Optional callback(array $pageItems, int $pageNum, ?int $totalItems): void
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchAllItems(?callable $onPage = null, int $limit = 200): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new Exception(
                'Walmart API: no access token. Check WALMART_CLIENT_ID / WALMART_CLIENT_SECRET in .env (see laravel.log for token response).'
            );
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/v3/items';
        $limit = max(1, min($limit, 200));
        // Walmart Items API: start with nextCursor=* then follow returned cursor.
        $cursor = '*';
        $collected = [];
        $pageNum = 0;
        $rateLimiter = new WalmartRateLimiter();
        $seenCursors = [];

        do {
            $pageNum++;
            $query = [
                'limit' => $limit,
                'nextCursor' => $cursor,
            ];

            $json = $rateLimiter->executeWithRetry(function () use ($endpoint, $query, $accessToken) {
                $request = Http::withHeaders($this->apiHeaders($accessToken));
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }

                $response = $request->get($endpoint, $query);
                if ($response->failed()) {
                    throw new Exception(
                        'Failed to fetch Walmart items [' . $response->status() . ']: ' . $response->body()
                    );
                }

                return $response->json() ?? [];
            }, 'listing', 3);

            $items = $json['ItemResponse']
                ?? $json['itemResponse']
                ?? $json['items']
                ?? [];

            if (!is_array($items)) {
                $items = [];
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $collected[] = $item;
                }
            }

            if ($onPage) {
                $totalItems = isset($json['totalItems']) ? (int) $json['totalItems'] : null;
                $onPage($items, $pageNum, $totalItems);
            }

            $next = $json['nextCursor'] ?? $json['NextCursor'] ?? null;
            if (!is_string($next) || $next === '' || $next === '*' || isset($seenCursors[$next])) {
                break;
            }

            $seenCursors[$next] = true;
            $cursor = $next;

            // Stop when a page returns fewer than the page size.
            if (count($items) < $limit) {
                break;
            }
        } while ($pageNum < 500);

        Log::info('Walmart items fetched', ['count' => count($collected), 'pages' => $pageNum]);

        return $collected;
    }

    /**
     * Extract listed price amount from a Walmart item payload.
     */
    public function extractListedPrice(array $item): ?float
    {
        $price = $item['price'] ?? null;

        if (is_array($price) && isset($price['amount']) && is_numeric($price['amount'])) {
            return round((float) $price['amount'], 2);
        }

        if (is_numeric($price)) {
            return round((float) $price, 2);
        }

        foreach (['currentPrice', 'current_price', 'listPrice', 'list_price'] as $key) {
            $value = $item[$key] ?? null;
            if (is_array($value) && isset($value['amount']) && is_numeric($value['amount'])) {
                return round((float) $value['amount'], 2);
            }
            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    /**
     * Fetch listed prices from Walmart /v3/items and upsert into walmart_pricing.
     *
     * @param  callable|null  $onPage  Optional progress callback
     * @return array{fetched:int, upserted:int, with_price:int, missing_price:int, skipped:int}
     *
     * @throws Exception
     */
    public function syncListedPrices(?callable $onPage = null, int $limit = 200): array
    {
        $stats = [
            'fetched' => 0,
            'upserted' => 0,
            'with_price' => 0,
            'missing_price' => 0,
            'skipped' => 0,
        ];

        $items = $this->fetchAllItems($onPage, $limit);
        $stats['fetched'] = count($items);

        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
            if ($sku === '') {
                $stats['skipped']++;
                continue;
            }

            $listedPrice = $this->extractListedPrice($item);
            if ($listedPrice !== null) {
                $stats['with_price']++;
            } else {
                $stats['missing_price']++;
            }

            $rows[] = [
                'sku' => $sku,
                'item_id' => $item['wpid'] ?? $item['itemId'] ?? $item['item_id'] ?? null,
                'item_name' => isset($item['productName'])
                    ? mb_substr((string) $item['productName'], 0, 500)
                    : (isset($item['product_name']) ? mb_substr((string) $item['product_name'], 0, 500) : null),
                'current_price' => $listedPrice,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            WalmartPricingSales::upsert(
                $chunk,
                ['sku'],
                ['item_id', 'item_name', 'current_price', 'updated_at']
            );
            $stats['upserted'] += count($chunk);
        }

        Log::info('Walmart listed prices synced', $stats);

        return $stats;
    }

    /**
     * Fetch purchase orders from Walmart GET /v3/orders (paginated).
     *
     * @param  callable|null  $onPage  Optional callback(array $orders, int $pageNum, ?int $totalCount): void
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function fetchOrders(int $days = 60, ?callable $onPage = null, int $limit = 200): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new Exception(
                'Walmart API: no access token. Check WALMART_CLIENT_ID / WALMART_CLIENT_SECRET in .env (see laravel.log for token response).'
            );
        }

        $days = max(1, min($days, 90));
        $limit = max(1, min($limit, 200));
        $endpoint = rtrim($this->baseUrl, '/') . '/v3/orders';

        $createdStartDate = now()->subDays($days)->startOfDay()->utc()->format('Y-m-d\TH:i:s.000\Z');
        $createdEndDate = now()->endOfDay()->utc()->format('Y-m-d\TH:i:s.000\Z');

        $collected = [];
        $pageNum = 0;
        $nextCursor = null;
        $seenCursors = [];
        $rateLimiter = new WalmartRateLimiter();

        do {
            $pageNum++;

            $json = $rateLimiter->executeWithRetry(function () use (
                $endpoint,
                $accessToken,
                $nextCursor,
                $limit,
                $createdStartDate,
                $createdEndDate
            ) {
                $request = Http::withHeaders($this->apiHeaders($accessToken));
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }

                // Walmart returns nextCursor as a full query string starting with "?".
                if (is_string($nextCursor) && str_starts_with($nextCursor, '?')) {
                    $response = $request->get($endpoint . $nextCursor);
                } else {
                    $query = [
                        'limit' => $limit,
                        'createdStartDate' => $createdStartDate,
                        'createdEndDate' => $createdEndDate,
                    ];
                    if (is_string($nextCursor) && $nextCursor !== '') {
                        $query['nextCursor'] = $nextCursor;
                    }
                    $response = $request->get($endpoint, $query);
                }

                if ($response->failed()) {
                    throw new Exception(
                        'Failed to fetch Walmart orders [' . $response->status() . ']: ' . $response->body()
                    );
                }

                return $response->json() ?? [];
            }, 'orders', 3);

            $orders = data_get($json, 'list.elements.order')
                ?? data_get($json, 'elements.order')
                ?? $json['orders']
                ?? [];

            if (!is_array($orders)) {
                $orders = [];
            }

            // Single-order responses sometimes return one object instead of a list.
            if ($orders !== [] && array_is_list($orders) === false && isset($orders['purchaseOrderId'])) {
                $orders = [$orders];
            }

            foreach ($orders as $order) {
                if (is_array($order)) {
                    $collected[] = $order;
                }
            }

            if ($onPage) {
                $totalCount = data_get($json, 'list.meta.totalCount');
                $onPage($orders, $pageNum, $totalCount !== null ? (int) $totalCount : null);
            }

            $cursor = data_get($json, 'list.meta.nextCursor')
                ?? data_get($json, 'meta.nextCursor')
                ?? $json['nextCursor']
                ?? null;

            if (!is_string($cursor) || $cursor === '' || isset($seenCursors[$cursor])) {
                break;
            }

            $seenCursors[$cursor] = true;
            $nextCursor = $cursor;

            if (count($orders) === 0) {
                break;
            }
        } while ($pageNum < 500);

        Log::info('Walmart orders fetched', [
            'count' => count($collected),
            'pages' => $pageNum,
            'days' => $days,
        ]);

        return $collected;
    }

    /**
     * Map a Walmart order + line into a walmart_daily_data row.
     *
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    public function mapOrderLineToDailyRow(array $order, array $line): ?array
    {
        $purchaseOrderId = (string) ($order['purchaseOrderId'] ?? '');
        $lineNumber = isset($line['lineNumber']) ? (int) $line['lineNumber'] : null;
        $sku = strtoupper(trim((string) data_get($line, 'item.sku', '')));

        if ($purchaseOrderId === '' || $lineNumber === null || $sku === '') {
            return null;
        }

        $orderDate = $this->parseWalmartTimestamp($order['orderDate'] ?? null);
        $statusDate = $this->parseWalmartTimestamp($line['statusDate'] ?? null);

        $statuses = data_get($line, 'orderLineStatuses.orderLineStatus', []);
        if (!is_array($statuses)) {
            $statuses = [];
        }
        $status = data_get($statuses, '0.status')
            ?? data_get($line, 'orderLineStatus')
            ?? null;

        $quantity = (int) data_get($line, 'orderLineQuantity.amount', 0);

        $productCharge = 0.0;
        $shippingCharge = 0.0;
        $taxAmount = 0.0;
        $currency = 'USD';
        $charges = data_get($line, 'charges.charge', []);
        if (!is_array($charges)) {
            $charges = [];
        }
        foreach ($charges as $charge) {
            if (!is_array($charge)) {
                continue;
            }
            $type = strtoupper((string) ($charge['chargeType'] ?? ''));
            $amount = (float) data_get($charge, 'chargeAmount.amount', 0);
            $currency = data_get($charge, 'chargeAmount.currency', $currency) ?: $currency;
            $taxAmount += (float) data_get($charge, 'tax.taxAmount.amount', 0);

            if ($type === 'PRODUCT') {
                $productCharge += $amount;
            } elseif ($type === 'SHIPPING') {
                $shippingCharge += $amount;
            }
        }

        // Existing walmart_daily_data stores PRODUCT charge total in unit_price
        // (e.g. qty=2, unit_price=51.98 for a $25.99 item).
        $unitPrice = round($productCharge, 2);

        $fulfillmentOption = data_get($line, 'fulfillment.fulfillmentOption')
            ?? data_get($order, 'shipNode.type')
            ?? null;
        if (is_string($fulfillmentOption) && in_array(strtoupper($fulfillmentOption), ['S2H', 'DELIVERY'], true)) {
            $fulfillmentOption = 'DELIVERY';
        }

        $period = 'l60';
        if ($orderDate && $orderDate->gte(now()->subDays(30)->startOfDay())) {
            $period = 'l30';
        }

        $shipping = $order['shippingInfo'] ?? [];
        $postalAddress = is_array($shipping) ? ($shipping['postalAddress'] ?? []) : [];

        $now = now();

        $fmt = static fn (?Carbon $dt): ?string => $dt ? $dt->format('Y-m-d H:i:s') : null;

        return [
            'purchase_order_id' => $purchaseOrderId,
            'customer_order_id' => $order['customerOrderId'] ?? null,
            'order_date' => $fmt($orderDate),
            'order_type' => $order['orderType'] ?? null,
            'mart_id' => $order['mart'] ?? $order['martId'] ?? null,
            'is_replacement' => strtoupper((string) ($order['orderType'] ?? '')) === 'REPLACEMENT',
            'original_customer_order_id' => $order['originalCustomerOrderID'] ?? $order['originalCustomerOrderId'] ?? null,
            'order_line_number' => $lineNumber,
            'period' => $period,
            'sku' => $sku,
            'product_name' => data_get($line, 'item.productName'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'tax_amount' => round($taxAmount, 2),
            'shipping_charge' => round($shippingCharge, 2),
            'status' => $status,
            'all_statuses_json' => $statuses !== [] ? json_encode($statuses) : null,
            'order_line_json' => json_encode($line),
            'status_date' => $fmt($statusDate),
            'customer_name' => $postalAddress['name'] ?? null,
            'customer_phone' => is_array($shipping) ? ($shipping['phone'] ?? null) : null,
            'customer_email' => $order['customerEmailId'] ?? null,
            'shipping_address1' => $postalAddress['address1'] ?? null,
            'shipping_address2' => $postalAddress['address2'] ?? null,
            'shipping_city' => $postalAddress['city'] ?? null,
            'shipping_state' => $postalAddress['state'] ?? null,
            'shipping_postal_code' => $postalAddress['postalCode'] ?? null,
            'shipping_country' => $postalAddress['country'] ?? null,
            'shipping_method' => is_array($shipping) ? ($shipping['methodCode'] ?? null) : null,
            'ship_method_code' => data_get($line, 'fulfillment.shipMethod'),
            'estimated_delivery_date' => $fmt($this->parseWalmartTimestamp(
                is_array($shipping) ? ($shipping['estimatedDeliveryDate'] ?? null) : null
            )),
            'estimated_ship_date' => $fmt($this->parseWalmartTimestamp(
                is_array($shipping) ? ($shipping['estimatedShipDate'] ?? null) : null
            )),
            'fulfillment_option' => $fulfillmentOption,
            'ship_node_type' => data_get($order, 'shipNode.type'),
            'ship_node_name' => data_get($order, 'shipNode.name'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Fetch orders from Walmart API and upsert into walmart_daily_data.
     *
     * @param  callable|null  $onPage
     * @return array{fetched_orders:int, upserted_lines:int, skipped:int, days:int}
     *
     * @throws Exception
     */
    public function syncOrders(int $days = 60, ?callable $onPage = null, int $limit = 200): array
    {
        $stats = [
            'fetched_orders' => 0,
            'upserted_lines' => 0,
            'skipped' => 0,
            'days' => $days,
        ];

        $orders = $this->fetchOrders($days, $onPage, $limit);
        $stats['fetched_orders'] = count($orders);

        $rows = [];
        foreach ($orders as $order) {
            $lines = data_get($order, 'orderLines.orderLine', []);
            if (!is_array($lines)) {
                $lines = [];
            }
            if ($lines !== [] && array_is_list($lines) === false && isset($lines['lineNumber'])) {
                $lines = [$lines];
            }

            foreach ($lines as $line) {
                if (!is_array($line)) {
                    $stats['skipped']++;
                    continue;
                }
                $row = $this->mapOrderLineToDailyRow($order, $line);
                if ($row === null) {
                    $stats['skipped']++;
                    continue;
                }
                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            WalmartDailyData::upsert(
                $chunk,
                ['purchase_order_id', 'order_line_number'],
                [
                    'customer_order_id',
                    'order_date',
                    'order_type',
                    'mart_id',
                    'is_replacement',
                    'original_customer_order_id',
                    'period',
                    'sku',
                    'product_name',
                    'quantity',
                    'unit_price',
                    'currency',
                    'tax_amount',
                    'shipping_charge',
                    'status',
                    'all_statuses_json',
                    'order_line_json',
                    'status_date',
                    'customer_name',
                    'customer_phone',
                    'customer_email',
                    'shipping_address1',
                    'shipping_address2',
                    'shipping_city',
                    'shipping_state',
                    'shipping_postal_code',
                    'shipping_country',
                    'shipping_method',
                    'ship_method_code',
                    'estimated_delivery_date',
                    'estimated_ship_date',
                    'fulfillment_option',
                    'ship_node_type',
                    'ship_node_name',
                    'updated_at',
                ]
            );
            $stats['upserted_lines'] += count($chunk);
        }

        // Refresh period tags for rows in the synced window.
        $cutoff30 = now()->subDays(30)->startOfDay();
        $cutoff60 = now()->subDays(60)->startOfDay();
        WalmartDailyData::where('order_date', '>=', $cutoff30)->update(['period' => 'l30']);
        WalmartDailyData::where('order_date', '>=', $cutoff60)
            ->where('order_date', '<', $cutoff30)
            ->update(['period' => 'l60']);

        Log::info('Walmart orders synced', $stats);

        return $stats;
    }

    private function parseWalmartTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $num = (float) $value;
                // Walmart often returns epoch milliseconds.
                if ($num > 1_000_000_000_000) {
                    return Carbon::createFromTimestampMs((int) $num);
                }

                return Carbon::createFromTimestamp((int) $num);
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
