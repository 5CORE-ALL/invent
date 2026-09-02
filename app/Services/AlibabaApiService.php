<?php

namespace App\Services;

use App\Models\AlibabaMetric;

/**
 * Alibaba.com Open Platform (ICBU) — same IOP/REST signing model as AliExpress,
 * but method names and hosts are Alibaba.com, not AliExpress solution.*.
 */
class AlibabaApiService extends AliExpressApiService
{
    protected string $channelLabel = 'Alibaba';

    protected string $tokenEnvKey = 'ALIBABA_ACCESS_TOKEN';

    public function __construct()
    {
        parent::__construct();

        $this->appKey = (string) (config('services.alibaba.app_key') ?: '');
        $this->appSecret = (string) (config('services.alibaba.app_secret') ?: '');
        $this->accessToken = config('services.alibaba.access_token');

        $base = (string) (config('services.alibaba.api_base') ?: 'https://openapi.alibaba.com');
        $this->apiBase = str_ends_with($base, '/sync') ? $base : rtrim($base, '/').'/sync';
        $this->signPath = '/sync';
        $this->tokenParam = 'access_token';

        $gw = strtolower((string) (config('services.alibaba.gateway') ?: 'rest'));
        $this->gateway = in_array($gw, ['sync', 'rest'], true) ? $gw : 'rest';

        $rest = (string) (config('services.alibaba.rest_base') ?: 'https://api-sg.alibaba.com/rest');
        if (str_contains(strtolower($rest), 'aliexpress.com')) {
            $rest = 'https://api-sg.alibaba.com/rest';
        }
        $this->restBase = rtrim($rest, '/');

        $rsm = strtolower((string) (config('services.alibaba.rest_sign_method') ?: 'hmac'));
        $this->restSignMethod = in_array($rsm, ['hmac', 'md5'], true) ? $rsm : 'hmac';
        $this->httpConnectTimeout = max(5, (int) (config('services.alibaba.connect_timeout') ?: 30));
        $this->httpTimeout = max(10, (int) (config('services.alibaba.timeout') ?: 60));
        $proxy = config('services.alibaba.http_proxy');
        $this->httpProxy = is_string($proxy) && $proxy !== '' ? $proxy : null;
        $this->resolveIpv4 = filter_var(
            config('services.alibaba.resolve_ipv4', true),
            FILTER_VALIDATE_BOOL
        );
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>, total_products?: int|null}
     */
    public function testConnection(): array
    {
        $result = $this->getInventory(1, 1);
        if (empty($result['success'])) {
            return $result;
        }

        $total = $result['data']['total_count'] ?? count($result['data']['products'] ?? []);

        return [
            'success' => true,
            'message' => 'Connected successfully. Alibaba product list API responded.',
            'total_products' => is_numeric($total) ? (int) $total : null,
            'data' => $result['data'] ?? [],
        ];
    }

    public function getInventory(int $page = 1, int $pageSize = 20, array $extraListParams = []): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));
        $base = array_merge([
            'current_page' => $page,
            'page_size' => $pageSize,
            'language' => 'ENGLISH',
        ], $extraListParams);

        $attempts = [
            ['alibaba.icbu.product.list', $base],
            ['alibaba.icbu.product.list.get', $base],
            ['alibaba.product.list.get', [
                'current_page' => $page,
                'page_size' => $pageSize,
            ]],
        ];

        $last = ['success' => false, 'message' => 'Alibaba product list API failed.'];
        foreach ($attempts as [$method, $params]) {
            $raw = $this->callIcbu($method, $params);
            if (empty($raw['success'])) {
                $last = $raw;
                if ($this->isAuthError($raw)) {
                    return $raw;
                }
                continue;
            }

            $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
            $parsed = $this->parseSolutionProductListResponse($payload);
            if ($parsed['products'] === [] && $parsed['total_count'] === null) {
                $parsed = $this->parseIcbuProductList($payload);
            }

            return [
                'success' => true,
                'status' => $raw['status'] ?? 200,
                'data' => $parsed,
                'raw' => $payload,
                'request_id' => $raw['request_id'] ?? null,
            ];
        }

        return $last;
    }

    public function getProductInfo(string $productId): array
    {
        $productId = trim($productId);
        $params = [
            'product_id' => $productId,
            'language' => 'ENGLISH',
        ];

        $raw = $this->callIcbuFirst([
            'alibaba.icbu.product.get',
            'alibaba.product.get',
            'alibaba.icbu.product.info.get',
        ], $params);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        if (isset($result['product']) && is_array($result['product'])) {
            $result = $result['product'];
        }

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => is_array($result) ? $result : [],
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    public function getOrders(int $page = 1, int $pageSize = 20, array $query = []): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));
        $start = (string) ($query['create_date_start'] ?? $query['create_start_time'] ?? '');
        $end = (string) ($query['create_date_end'] ?? $query['create_end_time'] ?? '');

        $shapes = [
            array_merge([
                'current_page' => $page,
                'page_size' => $pageSize,
            ], $query),
            array_filter([
                'page' => $page,
                'page_size' => $pageSize,
                'create_start_time' => $start !== '' ? $start : null,
                'create_end_time' => $end !== '' ? $end : null,
            ], static fn ($v) => $v !== null && $v !== ''),
            array_filter([
                'current_page' => $page,
                'page_size' => $pageSize,
                'gmt_create_start' => $start !== '' ? $start : null,
                'gmt_create_end' => $end !== '' ? $end : null,
            ], static fn ($v) => $v !== null && $v !== ''),
        ];

        $methods = [
            'alibaba.trade.getSellerOrderList',
            'alibaba.icbu.order.list',
            'alibaba.trade.icbu.order.list',
        ];

        $last = ['success' => false, 'message' => 'Alibaba order list API failed.'];
        foreach ($methods as $method) {
            foreach ($shapes as $params) {
                $raw = $this->callIcbu($method, $params);
                if (empty($raw['success'])) {
                    $last = $raw;
                    if ($this->isAuthError($raw)) {
                        return $raw;
                    }
                    continue;
                }

                $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
                $parsed = $this->parseSolutionOrderListResponse(
                    is_array($payload) ? $payload : []
                );
                if (($parsed['orders'] ?? []) === [] && ($parsed['total_count'] ?? null) === null) {
                    $parsed = $this->parseIcbuOrderList($payload);
                }

                return [
                    'success' => true,
                    'status' => $raw['status'] ?? 200,
                    'data' => $parsed,
                    'raw' => $payload,
                    'request_id' => $raw['request_id'] ?? null,
                ];
            }
        }

        return $last;
    }

    public function getOrderInfo(string $orderId): array
    {
        $orderId = trim($orderId);
        $shapes = [
            ['order_id' => $orderId],
            ['id' => $orderId],
            ['orderId' => $orderId],
        ];
        $methods = [
            'alibaba.trade.getSellerView',
            'alibaba.trade.get.sellerOrder',
            'alibaba.icbu.order.get',
            'alibaba.trade.icbu.order.get',
        ];

        $last = ['success' => false, 'message' => 'Alibaba order detail API failed.'];
        foreach ($methods as $method) {
            foreach ($shapes as $params) {
                $raw = $this->callIcbu($method, $params);
                if (empty($raw['success'])) {
                    $last = $raw;
                    if ($this->isAuthError($raw)) {
                        return $raw;
                    }
                    continue;
                }

                $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
                $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
                if (isset($result['order']) && is_array($result['order'])) {
                    $result = $result['order'];
                }

                return [
                    'success' => true,
                    'status' => $raw['status'] ?? 200,
                    'data' => is_array($result) ? $result : [],
                    'request_id' => $raw['request_id'] ?? null,
                ];
            }
        }

        return $last;
    }

    public function getOrderTradeDetail(string $orderId): array
    {
        return $this->getOrderInfo($orderId);
    }

    public function getOrderLoanFundList(string $orderId, int $page = 1, int $pageSize = 20): array
    {
        return [
            'success' => false,
            'message' => 'Alibaba ICBU does not expose AliExpress loan-fund APIs.',
            'data' => [],
        ];
    }

    public function getOrderReceiptInfo(string $orderId): array
    {
        $orderId = trim($orderId);
        $raw = $this->callIcbuFirst([
            'alibaba.trade.getSellerView',
            'alibaba.icbu.order.receipt.get',
        ], ['order_id' => $orderId]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        $address = $result['receipt_address']
            ?? $result['shipping_address']
            ?? $result['receiver']
            ?? $result;

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => is_array($address) ? $address : [],
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int}>  $rows
     */
    public function batchUpdateInventory(array $rows): array
    {
        if ($rows === []) {
            return ['success' => true, 'message' => 'No rows to update.', 'updated' => 0];
        }

        $updated = 0;
        $errors = [];

        foreach ($rows as $row) {
            $productId = (string) ($row['product_id'] ?? '');
            $skuCode = trim((string) ($row['sku_code'] ?? $row['sku'] ?? ''));
            if ($productId === '' || $skuCode === ''
                || ! \App\Services\MarketplaceManager\MarketplaceLiveInventoryRules::isLinked($productId, $skuCode)) {
                continue;
            }
            $inventory = max(0, min(999999, (int) ($row['inventory'] ?? $row['stock'] ?? 0)));
            if (array_key_exists('shopify_qty', $row)) {
                $inventory = \App\Services\MarketplaceManager\MarketplaceLiveInventoryRules::clampPushQty(
                    $inventory,
                    (int) $row['shopify_qty']
                );
            }

            $shapes = [
                [
                    'product_id' => $productId,
                    'sku_inventory_list' => $this->encodeRequestPayload([
                        ['sku_code' => $skuCode, 'inventory' => $inventory],
                    ]),
                ],
                [
                    'product_id' => $productId,
                    'sku_code' => $skuCode,
                    'inventory' => $inventory,
                ],
                [
                    'product_id' => $productId,
                    'cargo_number' => $skuCode,
                    'inventory' => $inventory,
                ],
            ];

            $ok = false;
            $lastMessage = 'Inventory update failed.';
            foreach (['alibaba.icbu.product.inventory.update', 'alibaba.product.inventory.update', 'alibaba.icbu.product.stock.update'] as $method) {
                foreach ($shapes as $params) {
                    $raw = $this->callIcbu($method, $params);
                    if (! empty($raw['success'])) {
                        $ok = true;
                        break 2;
                    }
                    $lastMessage = (string) ($raw['message'] ?? $lastMessage);
                    if ($this->isAuthError($raw)) {
                        return [
                            'success' => false,
                            'message' => $lastMessage,
                            'updated' => $updated,
                        ];
                    }
                }
            }

            if ($ok) {
                $updated++;
            } else {
                $errors[] = $skuCode.': '.$lastMessage;
            }
            usleep(150000);
        }

        return [
            'success' => $errors === [],
            'message' => $errors === []
                ? "Inventory updated for {$updated} SKU(s)."
                : implode(' | ', $errors),
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function declareSellerShipment(array $params): array
    {
        $outRef = trim((string) ($params['out_ref'] ?? ''));
        $logisticsNo = trim((string) ($params['logistics_no'] ?? ''));
        $serviceName = trim((string) ($params['service_name'] ?? ''));
        if ($outRef === '' || $logisticsNo === '' || $serviceName === '') {
            return [
                'success' => false,
                'message' => 'out_ref, logistics_no, and service_name are required to declare shipment.',
            ];
        }

        $business = [
            'order_id' => $outRef,
            'out_ref' => $outRef,
            'logistics_no' => $logisticsNo,
            'tracking_number' => $logisticsNo,
            'service_name' => $serviceName,
            'send_type' => strtolower(trim((string) ($params['send_type'] ?? 'all'))) ?: 'all',
        ];

        $raw = $this->callIcbuFirst([
            'alibaba.trade.logistics.shipment.declare',
            'alibaba.icbu.logistics.shipment.declare',
            'alibaba.trade.order.ship',
        ], $business);

        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => $raw['message'] ?? 'Alibaba declare shipment failed.',
                'response' => $raw['response'] ?? $raw['data'] ?? null,
                'request_id' => $raw['request_id'] ?? null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Shipment declared on Alibaba.',
            'data' => $raw['data'] ?? $raw['result'] ?? null,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    public function modifySellerShipment(array $params): array
    {
        $outRef = trim((string) ($params['out_ref'] ?? ''));
        $newNo = trim((string) ($params['new_logistics_no'] ?? ''));
        $newService = trim((string) ($params['new_service_name'] ?? ''));
        if ($outRef === '' || $newNo === '' || $newService === '') {
            return [
                'success' => false,
                'message' => 'out_ref, new logistics_no, and new service_name are required to modify shipment.',
            ];
        }

        $business = [
            'order_id' => $outRef,
            'out_ref' => $outRef,
            'old_logistics_no' => trim((string) ($params['old_logistics_no'] ?? '')),
            'new_logistics_no' => $newNo,
            'tracking_number' => $newNo,
            'old_service_name' => trim((string) ($params['old_service_name'] ?? '')),
            'new_service_name' => $newService,
            'service_name' => $newService,
        ];

        $raw = $this->callIcbuFirst([
            'alibaba.trade.logistics.shipment.modify',
            'alibaba.icbu.logistics.shipment.modify',
            'alibaba.trade.order.ship',
        ], array_filter($business, static fn ($v) => $v !== ''));

        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => $raw['message'] ?? 'Alibaba modify shipment failed.',
                'response' => $raw['response'] ?? $raw['data'] ?? null,
                'request_id' => $raw['request_id'] ?? null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Shipment tracking updated on Alibaba.',
            'data' => $raw['data'] ?? $raw['result'] ?? null,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    public function listLogisticsServices(): array
    {
        $raw = $this->callIcbuFirst([
            'alibaba.icbu.logistics.service.list',
            'alibaba.trade.logistics.service.list',
            'alibaba.logistics.service.list',
        ], []);

        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => $raw['message'] ?? 'Alibaba logistics service list failed.',
                'services' => [],
            ];
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $list = $payload['result']['service_list']
            ?? $payload['result']['services']
            ?? $payload['service_list']
            ?? $payload['services']
            ?? [];
        $services = [];
        foreach (is_array($list) ? $list : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['service_name'] ?? $row['code'] ?? $row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $services[] = [
                'service_name' => $name,
                'display_name' => (string) ($row['display_name'] ?? $row['name'] ?? $name),
            ];
        }

        return [
            'success' => true,
            'services' => $services,
        ];
    }

    /**
     * Alibaba.com ICBU unread / undealt messages. Does not fall back to AliExpress.
     *
     * @return array{success: bool, count: int, message?: string}
     */
    public function getPendingMessageCount(): array
    {
        $methods = [
            'alibaba.icbu.message.count',
            'alibaba.icbu.msg.unread.count',
            'alibaba.icbu.messagebox.count',
        ];
        foreach ($methods as $method) {
            $raw = $this->debugCallRest($method, []);
            $json = is_array($raw['response']['json'] ?? null) ? $raw['response']['json'] : [];
            $count = $this->extractPendingMessageTotal($json);
            if ($count !== null) {
                return ['success' => true, 'count' => $count];
            }
        }

        return ['success' => false, 'count' => 0, 'message' => 'Alibaba message count API returned no value.'];
    }

    protected function channelImageMetricsMarketplaceKey(): string
    {
        return 'alibaba';
    }

    /**
     * @return object{sku?: mixed, product_id?: mixed}|null
     */
    protected function findChannelMetricRow(string $trim): ?object
    {
        $row = AlibabaMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if ($row) {
            return $row;
        }

        return AlibabaMetric::query()->where('product_id', $trim)->first();
    }

    protected function findChannelProductIdFromDataView(string $trim): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function callIcbu(string $method, array $params = []): array
    {
        $raw = $this->callRestGateway($method, $params);
        if (! empty($raw['success'])) {
            return $raw;
        }

        $sync = $this->callSync($method, $params);
        if (! empty($sync['success'])) {
            return $sync;
        }

        return empty($raw['network_error']) ? $raw : $sync;
    }

    /**
     * @param  list<string>  $methods
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function callIcbuFirst(array $methods, array $params): array
    {
        $last = ['success' => false, 'message' => 'Alibaba API call failed.'];
        foreach ($methods as $method) {
            $raw = $this->callIcbu($method, $params);
            if (! empty($raw['success'])) {
                return $raw;
            }
            $last = $raw;
            if ($this->isAuthError($raw)) {
                return $raw;
            }
        }

        return $last;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function isAuthError(array $result): bool
    {
        $message = strtolower((string) ($result['message'] ?? ''));
        if ($message === '') {
            return false;
        }

        foreach (['invalid token', 'expired token', 'access token is', 'token is empty', 'invalid session'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{products: array<int, mixed>, total_count: mixed, total_page: mixed, current_page: mixed, page_size: mixed}
     */
    protected function parseIcbuProductList(array $payload): array
    {
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        $products = $result['products']
            ?? $result['product_list']
            ?? $result['list']
            ?? $result['items']
            ?? [];
        if (is_array($products) && isset($products['product']) && is_array($products['product'])) {
            $products = $products['product'];
        }
        if (! is_array($products)) {
            $products = [];
        }
        if ($products !== [] && ! array_is_list($products)) {
            $products = [$products];
        }

        $total = $result['total_item'] ?? $result['total_count'] ?? $result['totalCount'] ?? $result['total'] ?? null;
        $pageSize = $result['page_size'] ?? $result['pageSize'] ?? null;
        $totalPage = $result['total_page'] ?? $result['totalPage'] ?? null;
        if ($totalPage === null && is_numeric($total) && is_numeric($pageSize) && (int) $pageSize > 0) {
            $totalPage = (int) ceil(((int) $total) / (int) $pageSize);
        }

        return [
            'products' => array_values($products),
            'total_count' => $total,
            'total_page' => $totalPage,
            'current_page' => $result['current_page'] ?? $result['currentPage'] ?? null,
            'page_size' => $pageSize,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{orders: array<int, mixed>, total_count: mixed, total_page: mixed, current_page: mixed, page_size: mixed}
     */
    protected function parseIcbuOrderList(array $payload): array
    {
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        $orders = $result['order_list']
            ?? $result['orders']
            ?? $result['target_list']
            ?? $result['list']
            ?? [];
        if (is_array($orders) && isset($orders['trade_info']) && is_array($orders['trade_info'])) {
            $orders = $orders['trade_info'];
        }
        if (is_array($orders) && isset($orders['order']) && is_array($orders['order'])) {
            $orders = $orders['order'];
        }
        if (! is_array($orders)) {
            $orders = [];
        }
        if ($orders !== [] && ! array_is_list($orders)) {
            $orders = [$orders];
        }

        $total = $result['total_record'] ?? $result['total_count'] ?? $result['totalCount'] ?? null;
        $pageSize = $result['page_size'] ?? $result['pageSize'] ?? null;
        $totalPage = $result['total_page'] ?? $result['totalPage'] ?? null;
        if ($totalPage === null && is_numeric($total) && is_numeric($pageSize) && (int) $pageSize > 0) {
            $totalPage = (int) ceil(((int) $total) / (int) $pageSize);
        }

        return [
            'orders' => array_values($orders),
            'total_count' => $total,
            'total_page' => $totalPage,
            'current_page' => $result['current_page'] ?? $result['currentPage'] ?? null,
            'page_size' => $pageSize,
        ];
    }
}
