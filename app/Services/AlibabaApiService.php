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

    public function extractSkuRowsFromListItem(array $item, bool $fetchDetail = false): array
    {
        $parent = parent::extractSkuRowsFromListItem($item, $fetchDetail);
        $productId = (string) ($item['product_id'] ?? $item['productId'] ?? $item['id'] ?? '');

        return $this->preferSkuRows($this->icbuSkuRows($item, $productId), $parent);
    }

    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $parent = parent::extractSkuRowsFromProductInfo($info, $productId, $productName);

        return $this->preferSkuRows($this->icbuSkuRows($info, $productId, $productName), $parent);
    }

    public function getProductInfo(string $productId): array
    {
        $productId = trim($productId);
        $this->useIcbuRest();
        $listed = $this->callRestGateway('/icbu/product/get', [
            'product_get_request' => [
                'productId' => $productId,
                'language' => 'ENGLISH',
            ],
        ]);
        if (! empty($listed['success'])) {
            $payload = is_array($listed['data'] ?? null) ? $listed['data'] : [];
            $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
            if (isset($result['product']) && is_array($result['product'])) {
                $result = $result['product'];
            }

            return [
                'success' => true,
                'status' => $listed['status'] ?? 200,
                'data' => is_array($result) ? $result : [],
                'request_id' => $listed['request_id'] ?? null,
            ];
        }

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

        $sellerQuery = [
            'role' => 'seller',
            'page_size' => $pageSize,
            'start_page' => max(0, $page - 1),
        ];
        if ($start !== '') {
            $sellerQuery['create_date_start'] = ['date_str' => $start];
        }
        if ($end !== '') {
            $sellerQuery['create_date_end'] = ['date_str' => $end];
        }

        $seller = $this->callIcbu('alibaba.seller.order.list', [
            'param_trade_ecology_order_list_query' => $sellerQuery,
        ]);
        if (! empty($seller['success'])) {
            $payload = $this->unwrapSolutionEnvelope($seller['data'] ?? []);
            $parsed = $this->parseIcbuOrderList(is_array($payload) ? $payload : []);

            return [
                'success' => true,
                'status' => $seller['status'] ?? 200,
                'data' => $parsed,
                'raw' => $payload,
                'request_id' => $seller['request_id'] ?? null,
            ];
        }
        if ($this->isAuthError($seller)) {
            return $seller;
        }

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
        $seller = $this->callIcbu('alibaba.seller.order.get', [
            'e_trade_id' => $orderId,
        ]);
        if (! empty($seller['success'])) {
            $payload = $this->unwrapSolutionEnvelope($seller['data'] ?? []);
            $result = $this->unwrapSellerOrder($payload);

            return [
                'success' => true,
                'status' => $seller['status'] ?? 200,
                'data' => $result,
                'request_id' => $seller['request_id'] ?? null,
            ];
        }
        if ($this->isAuthError($seller)) {
            return $seller;
        }

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
     * Push Shopify quantities through /icbu/product/inventory/update.
     * That call sets inventory.amount on a numeric skuId. sku_code is not accepted.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     * @return array{success: bool, message: string, updated: int, errors: list<string>, failed_skus: list<string>}
     */
    public function batchUpdateInventory(array $rows): array
    {
        if ($rows === []) {
            return ['success' => true, 'message' => 'No rows to update.', 'updated' => 0, 'errors' => [], 'failed_skus' => []];
        }

        $byProduct = [];
        foreach ($rows as $row) {
            $productId = trim((string) ($row['product_id'] ?? ''));
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
            $byProduct[$productId][] = [
                'sku_code' => $skuCode,
                'inventory' => $inventory,
            ];
        }

        if ($byProduct === []) {
            return ['success' => true, 'message' => 'No linked SKUs to update.', 'updated' => 0, 'errors' => [], 'failed_skus' => []];
        }

        $this->useIcbuRest();
        $updated = 0;
        $errors = [];
        $failedSkus = [];
        $productIds = array_keys($byProduct);

        foreach ($productIds as $index => $productId) {
            $group = $byProduct[$productId];
            $resolved = $this->icbuInventoryItems((string) $productId, $group);
            if ($resolved['auth']) {
                foreach (array_slice($productIds, $index) as $laterId) {
                    foreach ($byProduct[$laterId] as $item) {
                        $failedSkus[] = $item['sku_code'];
                        $errors[] = $item['sku_code'].': '.$resolved['error'];
                    }
                }
                break;
            }

            foreach ($resolved['missing'] as $skuCode) {
                $failedSkus[] = $skuCode;
                $errors[] = $skuCode.': '.$resolved['error'];
            }

            if ($resolved['items'] !== []) {
                $payloadItems = array_map(static function (array $item): array {
                    return [
                        'productId' => $item['productId'],
                        'skuId' => $item['skuId'],
                        'inventory' => $item['inventory'],
                    ];
                }, $resolved['items']);
                $raw = $this->callRestGateway('/icbu/product/inventory/update', [
                    'inventory_update_request' => [
                        'inventoryItems' => $payloadItems,
                    ],
                ]);
                $failure = $this->icbuInventoryWriteError($raw);
                if ($failure === null) {
                    $updated += count($resolved['items']);
                } else {
                    foreach ($resolved['items'] as $item) {
                        $skuCode = (string) ($item['sku_code'] ?? '');
                        $failedSkus[] = $skuCode;
                        $errors[] = $skuCode.': '.$failure;
                    }
                    if ($this->isAuthError($raw) || $this->isAuthError(['message' => $failure])) {
                        foreach (array_slice($productIds, $index + 1) as $laterId) {
                            foreach ($byProduct[$laterId] as $item) {
                                $failedSkus[] = $item['sku_code'];
                                $errors[] = $item['sku_code'].': '.$failure;
                            }
                        }
                        break;
                    }
                }
            }

            if (! app()->runningUnitTests()) {
                usleep(150000);
            }
        }

        $failedSkus = array_values(array_unique($failedSkus));
        $shown = array_slice(array_values(array_unique($errors)), 0, 2);
        $extra = count($errors) - count($shown);
        $failed = count($failedSkus);

        return [
            'success' => $errors === [],
            'message' => $errors === []
                ? "Inventory updated for {$updated} SKU(s)."
                : 'Pushed '.$updated.' inventory row(s) to Alibaba ('.$failed.' API fail).'
                    .($shown !== [] ? ' '.implode(' | ', $shown).($extra > 0 ? " (+{$extra} more)" : '') : ''),
            'updated' => $updated,
            'errors' => $errors,
            'failed_skus' => $failedSkus,
        ];
    }

    /**
     * @param  list<array{sku_code: string, inventory: int}>  $rows
     * @return array{items: list<array<string, mixed>>, missing: list<string>, error: string, auth: bool}
     */
    protected function icbuInventoryItems(string $productId, array $rows): array
    {
        $info = $this->getProductInfo($productId);
        if ($this->isAuthError($info)) {
            return [
                'items' => [],
                'missing' => [],
                'error' => (string) ($info['message'] ?? 'Alibaba auth failed.'),
                'auth' => true,
            ];
        }

        $product = (! empty($info['success']) && is_array($info['data'] ?? null)) ? $info['data'] : [];
        $nodes = $product !== [] ? $this->skuDefinitionNodes($product) : [];
        $items = [];
        $unresolved = [];

        foreach ($rows as $row) {
            $skuId = $this->icbuSkuIdForCode($nodes, (string) $row['sku_code']);
            if ($skuId === null) {
                $unresolved[] = $row;
                continue;
            }
            $items[] = $this->icbuInventoryItem($productId, $skuId, (int) $row['inventory'], (string) $row['sku_code']);
        }

        if ($unresolved !== []) {
            $lookup = $this->icbuSkuIdsFromInventory($productId);
            if ($lookup['auth']) {
                return [
                    'items' => [],
                    'missing' => [],
                    'error' => $lookup['error'],
                    'auth' => true,
                ];
            }

            $stillMissing = [];
            foreach ($unresolved as $row) {
                $skuId = $this->icbuSkuIdFromInventoryItems($lookup['items'], (string) $row['sku_code'], count($rows) === 1 && count($nodes) <= 1);
                if ($skuId === null) {
                    $stillMissing[] = (string) $row['sku_code'];
                    continue;
                }
                $items[] = $this->icbuInventoryItem($productId, $skuId, (int) $row['inventory'], (string) $row['sku_code']);
            }
            $unresolved = $stillMissing;
        }

        $error = 'Alibaba SKU id was not found, so inventory was not pushed.';

        return [
            'items' => $items,
            'missing' => array_values(array_map('strval', $unresolved)),
            'error' => $error,
            'auth' => false,
        ];
    }

    /**
     * @return array{productId: string, skuId: string, inventory: array{amount: string}, sku_code: string}
     */
    protected function icbuInventoryItem(string $productId, string $skuId, int $inventory, string $skuCode): array
    {
        return [
            'productId' => $productId,
            'skuId' => $skuId,
            'inventory' => ['amount' => (string) max(0, $inventory)],
            'sku_code' => $skuCode,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    protected function icbuSkuIdForCode(array $nodes, string $sku): ?string
    {
        $want = strtoupper(trim($sku));
        $only = count($nodes) === 1;
        foreach ($nodes as $node) {
            $code = strtoupper(trim((string) ($node['skuCode'] ?? $node['sku_code'] ?? '')));
            $skuId = $this->icbuNumericId($node['skuId'] ?? $node['sku_id'] ?? null);
            if ($skuId === null) {
                continue;
            }
            if ($only || ($want !== '' && $code === $want)) {
                return $skuId;
            }
        }

        return null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, error: string, auth: bool}
     */
    protected function icbuSkuIdsFromInventory(string $productId): array
    {
        $raw = $this->callRestGateway('/icbu/product/inventory/get', [
            'inventory_get_request' => ['productId' => $productId],
        ]);
        if ($this->isAuthError($raw)) {
            return ['items' => [], 'error' => (string) ($raw['message'] ?? 'Alibaba auth failed.'), 'auth' => true];
        }
        if (empty($raw['success'])) {
            return ['items' => [], 'error' => (string) ($raw['message'] ?? 'Alibaba inventory lookup failed.'), 'auth' => false];
        }

        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $result = is_array($data['result'] ?? null) ? $data['result'] : $data;
        $list = $result['inventoryItems'] ?? $result['inventory_items'] ?? [];
        if (is_array($list) && $list !== [] && ! array_is_list($list)) {
            $list = [$list];
        }

        $items = [];
        foreach (is_array($list) ? $list : [] as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return ['items' => $items, 'error' => '', 'auth' => false];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function icbuSkuIdFromInventoryItems(array $items, string $sku, bool $allowOnlyItem): ?string
    {
        $want = strtoupper(trim($sku));
        $onlyId = null;
        if (count($items) === 1) {
            $onlyId = $this->icbuNumericId($items[0]['skuId'] ?? $items[0]['sku_id'] ?? null);
        }

        foreach ($items as $item) {
            $skuId = $this->icbuNumericId($item['skuId'] ?? $item['sku_id'] ?? null);
            if ($skuId === null) {
                continue;
            }
            $labels = [strtoupper(trim((string) ($item['skuCode'] ?? $item['sku_code'] ?? '')))];
            $attributes = $item['attributes'] ?? [];
            if (is_array($attributes)) {
                if ($attributes !== [] && ! array_is_list($attributes)) {
                    $attributes = [$attributes];
                }
                foreach ($attributes as $attribute) {
                    if (! is_array($attribute)) {
                        continue;
                    }
                    $labels[] = strtoupper(trim((string) ($attribute['attributeValue'] ?? $attribute['attribute_value'] ?? $attribute['value'] ?? '')));
                }
            }
            if ($want !== '' && in_array($want, $labels, true)) {
                return $skuId;
            }
        }

        return $allowOnlyItem ? $onlyId : null;
    }

    protected function icbuNumericId(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) (int) $value;
        }
        $id = trim((string) $value);

        return preg_match('/^\d+$/', $id) === 1 ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function icbuInventoryWriteError(array $raw): ?string
    {
        if (empty($raw['success'])) {
            $message = trim((string) ($raw['message'] ?? ''));

            return $message !== '' ? $message : 'Alibaba inventory update failed.';
        }

        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $flag = $data['success'] ?? null;
        if ($flag === false || $flag === 'false' || $flag === 0 || $flag === '0') {
            $message = trim((string) ($data['message'] ?? $data['msg'] ?? ''));

            return $message !== '' ? $message : 'Alibaba inventory update failed.';
        }

        $result = $data['result'] ?? null;
        if (is_array($result)) {
            $inner = $result['success'] ?? null;
            if ($inner === false || $inner === 'false' || $inner === 0 || $inner === '0') {
                $message = trim((string) ($result['message'] ?? $result['error_message'] ?? $result['msg'] ?? ''));

                return $message !== '' ? $message : 'Alibaba inventory update failed.';
            }
        }

        return null;
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
     * Alibaba.com order lines live on order_products (quantity + unit_price.amount + sku_code).
     *
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    public function extractOrderProductLines(array $order): array
    {
        $products = $order['order_products']['trade_ecology_order_product']
            ?? $order['order_products']
            ?? null;

        $lines = [];
        foreach ($this->alibabaProductNodes($products) as $product) {
            $qtyRaw = $product['quantity'] ?? $product['product_count'] ?? null;
            $qty = is_numeric($qtyRaw) ? (int) round((float) $qtyRaw) : 0;
            $unit = $product['unit_price']['amount']
                ?? (is_numeric($product['unit_price'] ?? null) ? $product['unit_price'] : null);
            $price = is_numeric($unit) ? (float) $unit : 0.0;
            if ($qty <= 0 && $price <= 0 && trim((string) ($product['sku_code'] ?? '')) === '') {
                continue;
            }

            $skuCode = trim((string) ($product['sku_code'] ?? ''));
            if ($skuCode === '') {
                $skuCode = trim((string) ($product['model_number'] ?? ''));
            }

            $lines[] = [
                'product_id' => (string) ($product['product_id'] ?? ''),
                'sku_code' => $skuCode,
                'product_count' => $qty,
                'quantity' => $qty,
                'product_unit_price' => ['amount' => $price],
                'product_name' => $product['name'] ?? $product['product_name'] ?? null,
            ];
        }

        if ($lines !== []) {
            return $lines;
        }

        return parent::extractOrderProductLines($order);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function unwrapSellerOrder(array $payload): array
    {
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        if (is_array($result['value'] ?? null)) {
            $result = $result['value'];
        }
        if (isset($result['order']) && is_array($result['order'])) {
            $result = $result['order'];
        }

        return is_array($result) ? $result : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function alibabaProductNodes(mixed $products): array
    {
        if (! is_array($products) || $products === []) {
            return [];
        }
        if (isset($products['trade_ecology_order_product']) && is_array($products['trade_ecology_order_product'])) {
            $products = $products['trade_ecology_order_product'];
        }
        $isLine = isset($products['product_id']) || isset($products['sku_code']) || isset($products['quantity']) || isset($products['unit_price']);
        if ($isLine && ! array_is_list($products)) {
            return [$products];
        }

        $nodes = [];
        foreach ($products as $product) {
            if (is_array($product)) {
                $nodes[] = $product;
            }
        }

        return $nodes;
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
        if (is_array($result['value'] ?? null)) {
            $valueTotal = $result['value']['total_count'] ?? $result['value']['totalCount'] ?? null;
            $result = array_merge($result, $result['value']);
            if ($valueTotal !== null) {
                $result['total_count'] = $valueTotal;
            }
        }
        $orders = $result['order_list']
            ?? $result['orders']
            ?? $result['target_list']
            ?? $result['list']
            ?? [];
        if (is_array($orders) && isset($orders['trade_ecology_order']) && is_array($orders['trade_ecology_order'])) {
            $orders = $orders['trade_ecology_order'];
        }
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

    /**
     * ICBU product payloads use product_sku.skus, not AliExpress aeop SKU lists.
     *
     * @param  array<string, mixed>  $info
     * @return array<int, array{product_id: string, sku: string, price: float, stock: int|null, product_name: ?string, status: ?string}>
     */
    protected function icbuSkuRows(array $info, string $productId, ?string $productName = null): array
    {
        $productId = trim($productId !== '' ? $productId : (string) ($info['product_id'] ?? $info['id'] ?? ''));
        if ($productId === '') {
            return [];
        }

        $productName = $productName ?: $this->icbuText($info['subject'] ?? $info['product_name'] ?? $info['title'] ?? null);
        $status = $this->icbuText($info['status'] ?? $info['display'] ?? null);
        $rows = [];

        foreach ($this->icbuSkuNodes($info) as $node) {
            $sku = trim((string) ($node['sku_code'] ?? $node['skuCode'] ?? $node['sku'] ?? $node['cargo_number'] ?? ''));
            if ($sku === '' || strcasecmp($sku, $productId) === 0) {
                continue;
            }
            $rows[] = [
                'product_id' => $productId,
                'sku' => $sku,
                'price' => $this->listedSkuPrice($node),
                'stock' => $this->icbuStock($node),
                'product_name' => $productName,
                'status' => $status,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $primary
     * @param  array<int, array<string, mixed>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    protected function preferSkuRows(array $primary, array $fallback): array
    {
        if ($primary === []) {
            return $fallback;
        }

        $bySku = [];
        foreach ($fallback as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $bySku[$sku] = $row;
            }
        }

        $out = [];
        foreach ($primary as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            $price = (float) ($row['price'] ?? 0);
            if ($price <= 0 && isset($bySku[$sku]) && is_numeric($bySku[$sku]['price'] ?? null)) {
                $row['price'] = (float) $bySku[$sku]['price'];
            }
            if (($row['stock'] ?? null) === null && isset($bySku[$sku])) {
                $row['stock'] = $bySku[$sku]['stock'] ?? null;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<int, array<string, mixed>>
     */
    protected function icbuSkuNodes(array $info): array
    {
        $bags = [$info];
        foreach (['product_sku', 'productSku'] as $key) {
            if (isset($info[$key]) && is_array($info[$key])) {
                $bags[] = $info[$key];
            }
        }

        $out = [];
        foreach ($bags as $bag) {
            foreach (['skus', 'sku_list', 'sku_definition', 'sku_info_list', 'product_sku_list'] as $key) {
                if (! isset($bag[$key])) {
                    continue;
                }
                $list = $bag[$key];
                if (is_array($list) && isset($list['sku_definition']) && is_array($list['sku_definition'])) {
                    $list = $list['sku_definition'];
                } elseif (is_array($list) && isset($list['sku']) && is_array($list['sku'])) {
                    $list = $list['sku'];
                }
                if (is_array($list) && $list !== [] && ! array_is_list($list)) {
                    $list = [$list];
                }
                if (! is_array($list)) {
                    continue;
                }
                foreach ($list as $node) {
                    if (is_array($node)) {
                        $out[] = $node;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function icbuPrice(array $node): float
    {
        foreach (['price', 'sku_price', 'bulk_price', 'unit_price', 'fob_price', 'fob_min_price'] as $key) {
            $amount = $this->icbuMoney($node[$key] ?? null);
            if ($amount > 0) {
                return $amount;
            }
        }

        foreach (['sourcing_trade', 'wholesale_trade', 'product_price'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            $amount = $this->icbuPrice($node[$key]);
            if ($amount > 0) {
                return $amount;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function icbuStock(array $node): ?int
    {
        foreach (['inventory', 'stock', 'sku_stock', 'current_inventory'] as $key) {
            if (isset($node[$key]) && is_numeric($node[$key])) {
                return max(0, (int) $node[$key]);
            }
        }

        $list = $node['inventory_dto_list'] ?? $node['inventory_list'] ?? null;
        if (is_array($list) && $list !== [] && ! array_is_list($list)) {
            $list = [$list];
        }
        if (! is_array($list)) {
            return null;
        }

        $sum = 0;
        $found = false;
        foreach ($list as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach (['current_inventory', 'inventory', 'stock'] as $key) {
                if (isset($entry[$key]) && is_numeric($entry[$key])) {
                    $sum += max(0, (int) $entry[$key]);
                    $found = true;
                    break;
                }
            }
        }

        return $found ? $sum : null;
    }

    protected function icbuMoney(mixed $value): float
    {
        if (is_array($value)) {
            foreach (['amount', 'min', 'min_price', 'value', 'price'] as $key) {
                if (array_key_exists($key, $value)) {
                    $amount = $this->icbuMoney($value[$key]);
                    if ($amount > 0) {
                        return $amount;
                    }
                }
            }

            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('/\d+(?:\.\d+)?/', $value, $match) === 1) {
            return (float) $match[0];
        }

        return 0.0;
    }

    protected function icbuAttributeText(array $product): string
    {
        $chunks = [];
        $this->collectPieceLabels($product['productSku'] ?? $product['product_sku'] ?? [], $chunks);
        $this->collectPieceLabels($product['attributes'] ?? [], $chunks);

        return implode(' ', $chunks);
    }

    /**
     * @param  list<string>  $chunks
     */
    protected function collectPieceLabels(mixed $node, array &$chunks): void
    {
        if (is_string($node)) {
            if (preg_match('/\d+\s*(?:pcs?|pieces?)/i', $node) === 1) {
                $chunks[] = $node;
            }

            return;
        }
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $value) {
            $this->collectPieceLabels($value, $chunks);
        }
    }

    protected function icbuText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    /**
     * Product list on the ICBU gateway that accepts this app key.
     *
     * @return array{success: bool, message?: string, products: array<int, array<string, mixed>>, total_item: ?int, page_size: int}
     */
    public function listIcbuProducts(int $page = 1, int $pageSize = 10): array
    {
        $this->useIcbuRest();
        $page = max(1, $page);
        $pageSize = max(1, min(20, $pageSize));
        $raw = $this->callRestGateway('/alibaba/icbu/product/list', [
            'current_page' => $page,
            'page_size' => $pageSize,
            'language' => 'ENGLISH',
        ]);
        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => (string) ($raw['message'] ?? 'Alibaba product list failed.'),
                'products' => [],
                'total_item' => null,
                'page_size' => $pageSize,
            ];
        }

        $result = $raw['data']['result'] ?? [];
        $products = is_array($result) ? ($result['products'] ?? []) : [];
        if (isset($products['product']) && is_array($products['product'])) {
            $products = $products['product'];
        }
        if (is_array($products) && $products !== [] && ! array_is_list($products)) {
            $products = [$products];
        }

        $total = is_array($result) ? ($result['total_item'] ?? null) : null;

        return [
            'success' => true,
            'products' => is_array($products) ? array_values($products) : [],
            'total_item' => is_numeric($total) ? (int) $total : null,
            'page_size' => $pageSize,
        ];
    }

    /**
     * One product from /icbu/product/get, reduced to the analytics columns.
     *
     * @return array{product_id: string, sku: string, status: string, sku_price: ?float, soh: ?int}|null
     */
    public function icbuAnalyticsRow(string $productId): ?array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return null;
        }

        $this->useIcbuRest();
        $raw = $this->callRestGateway('/icbu/product/get', [
            'product_get_request' => [
                'productId' => $productId,
                'language' => 'ENGLISH',
            ],
        ]);
        if (empty($raw['success'])) {
            return null;
        }

        $product = $raw['data']['product'] ?? null;
        if (! is_array($product)) {
            return null;
        }

        $skuBag = $product['productSku']['skus'] ?? $product['product_sku']['skus'] ?? [];
        if (isset($skuBag['skuDefinition']) && is_array($skuBag['skuDefinition'])) {
            $skuBag = $skuBag['skuDefinition'];
        } elseif (isset($skuBag['sku_definition']) && is_array($skuBag['sku_definition'])) {
            $skuBag = $skuBag['sku_definition'];
        }
        if (is_array($skuBag) && $skuBag !== [] && ! array_is_list($skuBag)) {
            $skuBag = [$skuBag];
        }

        $sku = '';
        $skuCodes = [];
        $priceBySku = [];
        $price = 0.0;
        $soh = null;
        if (is_array($skuBag)) {
            foreach ($skuBag as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $code = trim((string) ($node['skuCode'] ?? $node['sku_code'] ?? ''));
                if ($code !== '') {
                    $skuCodes[] = $code;
                }
                if ($sku === '' && $code !== '') {
                    $sku = $code;
                }
                $nodePrice = $this->listedSkuPrice($node);
                if ($code !== '' && $nodePrice > 0) {
                    $priceBySku[strtoupper($code)] = $nodePrice;
                }
                if ($price <= 0 && $nodePrice > 0) {
                    $price = $nodePrice;
                }
                $nodeStock = $this->icbuStock($node);
                if ($nodeStock === null) {
                    $nodeStock = $this->icbuStock(['inventory_dto_list' => $node['inventoryDTOList'] ?? $node['inventoryDtoList'] ?? null]);
                }
                if ($nodeStock !== null) {
                    $soh = ($soh ?? 0) + $nodeStock;
                }
            }
        }

        if ($price <= 0) {
            $sourcing = $product['sourcingTrade'] ?? $product['sourcing_trade'] ?? [];
            $price = is_array($sourcing)
                ? $this->icbuMoney($sourcing['fobMinPrice'] ?? $sourcing['fob_min_price'] ?? null)
                : 0.0;
        }
        if ($price <= 0) {
            $price = $this->icbuPrice($product['wholesaleTrade'] ?? $product['wholesale_trade'] ?? []);
        }

        if ($sku === '') {
            $mapped = AlibabaMetric::query()->where('product_id', $productId)->value('sku');
            $sku = trim((string) $mapped);
        }
        $subject = $this->icbuText($product['subject'] ?? $product['product_name'] ?? $product['title'] ?? null) ?? '';
        $attrText = $this->icbuAttributeText($product);
        $hint = trim($subject.' '.$attrText);
        if (preg_match('/\b(\d+)\s*(?:pcs?|pieces?)\b/i', $hint, $match) === 1) {
            $labeled = null;
            foreach ($skuCodes as $code) {
                if (preg_match('/\b'.$match[1].'\s*pcs?\b/i', $code) === 1) {
                    $labeled = $code;
                    break;
                }
            }
            if ($labeled !== null) {
                $sku = $labeled;
                $labeledPrice = $priceBySku[strtoupper($labeled)] ?? 0.0;
                if ($labeledPrice > 0) {
                    $price = $labeledPrice;
                }
            } elseif ($sku !== '' && preg_match('/\b\d+\s*pcs?\b/i', $sku) !== 1) {
                $sku = trim($sku.' '.$match[1].'PCS');
            }
        }

        if ($sku === '' || $sku === $productId) {
            return null;
        }

        $display = strtoupper(trim((string) ($product['display'] ?? '')));
        $status = strtolower(trim((string) ($product['status'] ?? '')));
        if ($display === 'N') {
            $statusLabel = 'Offline';
        } elseif ($status === 'approved' || $display === 'Y') {
            $statusLabel = 'Active';
        } else {
            $statusLabel = $status !== '' ? $status : '';
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'status' => $statusLabel,
            'sku_price' => $price > 0 ? round($price, 2) : null,
            'soh' => $soh,
        ];
    }

    /**
     * Unit price shown on the Alibaba listing.
     * Ladder rows are often cheapest-first; the page lists the price at the smallest order quantity.
     */
    protected function listedSkuPrice(array $node): float
    {
        $fromTiers = $this->firstBulkPrice($node['bulkDiscountPrices'] ?? $node['bulk_discount_prices'] ?? null);
        if ($fromTiers > 0) {
            return $fromTiers;
        }

        return $this->icbuPrice($node);
    }

    protected function firstBulkPrice(mixed $discounts): float
    {
        if (! is_array($discounts)) {
            return 0.0;
        }
        if (isset($discounts['price']) && ! array_is_list($discounts)) {
            return $this->icbuMoney($discounts['price']);
        }
        if (isset($discounts['bulk_discount_price']) && is_array($discounts['bulk_discount_price'])) {
            $discounts = $discounts['bulk_discount_price'];
        }

        $rows = [];
        foreach ($discounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = $this->icbuMoney($row['price'] ?? null);
            if ($amount <= 0) {
                continue;
            }
            $qty = $row['startQuantity'] ?? $row['start_quantity'] ?? -1;
            $rows[] = [
                'qty' => is_numeric($qty) ? (int) $qty : -1,
                'price' => $amount,
            ];
        }
        if ($rows === []) {
            return 0.0;
        }

        $atMoq = array_values(array_filter($rows, static fn (array $row): bool => $row['qty'] >= 1));
        $pool = $atMoq !== [] ? $atMoq : $rows;
        usort($pool, static fn (array $a, array $b): int => $a['qty'] <=> $b['qty']);

        return $pool[0]['price'];
    }

    /**
     * Incremental schema XML that sets the buyer-facing unit price.
     * FOB listings set the single-piece min and max. Ladder listings change only the MOQ tier.
     *
     * @param  array<string, mixed>  $product
     */
    public function listedPriceUpdateXml(array $product, float $price, ?string $sku = null): string
    {
        $price = round($price, 2);
        if ($price < 0.01 || $price > 9999999) {
            throw new \InvalidArgumentException('Alibaba price must be between 0.01 and 9999999.00.');
        }

        $type = strtolower(trim((string) ($product['priceType'] ?? $product['price_type'] ?? '')));
        $amount = number_format($price, 2, '.', '');

        if ($type === 'sku_price' || $type === 'sku') {
            return $this->skuPriceXml($product, $amount, $sku);
        }

        if ($type === 'ladder_price' || $type === 'ladder') {
            return $this->ladderPriceXml($product, $amount, $sku);
        }

        return $this->fobPriceXml($product, $amount);
    }

    /**
     * Push the listing unit price through /icbu/product/schema/update.
     *
     * @return array{success: bool, message?: string, price?: float, product_id?: string}
     */
    public function pushListedPrice(string $productId, float $price, ?string $sku = null): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['success' => false, 'message' => 'Alibaba product id is missing.'];
        }

        $info = $this->getProductInfo($productId);
        if (empty($info['success'])) {
            return ['success' => false, 'message' => (string) ($info['message'] ?? 'Alibaba product lookup failed.')];
        }

        $product = is_array($info['data'] ?? null) ? $info['data'] : [];
        $catId = $product['categoryId'] ?? $product['category_id'] ?? $product['catId'] ?? null;
        if ($catId === null || $catId === '') {
            return ['success' => false, 'message' => 'Alibaba category id is missing for this listing.'];
        }

        try {
            $xml = $this->listedPriceUpdateXml($product, $price, $sku);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $this->useIcbuRest();
        $raw = $this->callRestGateway('/icbu/product/schema/update', [
            'xml' => $xml,
            'product_id' => $productId,
            'cat_id' => (string) $catId,
            'language' => 'en_US',
        ]);
        if (empty($raw['success'])) {
            return ['success' => false, 'message' => $this->icbuErrorMessage($raw)];
        }

        return [
            'success' => true,
            'product_id' => $productId,
            'price' => round($price, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function fobPriceXml(array $product, string $amount): string
    {
        $sourcing = $product['sourcingTrade'] ?? $product['sourcing_trade'] ?? [];
        if (! is_array($sourcing)) {
            $sourcing = [];
        }
        $currency = strtoupper(trim((string) ($sourcing['fobCurrency'] ?? $sourcing['fob_currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        $unit = [
            'USD' => '1',
            'RMB' => '2',
            'CNY' => '2',
            'EUR' => '3',
            'GBP' => '5',
            'JPY' => '6',
            'NTD' => '11',
            'HKD' => '12',
            'NZD' => '13',
            'SGD' => '14',
        ][$currency] ?? null;
        if ($unit === null) {
            throw new \InvalidArgumentException('Alibaba FOB currency '.$currency.' cannot be pushed.');
        }

        $moqXml = '';
        $moqRaw = $sourcing['minOrderQuantity'] ?? $sourcing['min_order_quantity'] ?? null;
        if (is_numeric($moqRaw) && (float) $moqRaw > 0) {
            $moq = rtrim(rtrim(number_format((float) $moqRaw, 2, '.', ''), '0'), '.');
            $moqXml = '<field id="minOrderQuantity" type="input"><value>'.$moq.'</value></field>';
        }

        return '<itemSchema>'
            .'<field id="scPrice" type="singleCheck"><value>2</value></field>'
            .'<field id="fob" type="complex"><complex-value>'
            .'<field id="range_min" type="input"><value>'.$amount.'</value></field>'
            .'<field id="range_max" type="input"><value>'.$amount.'</value></field>'
            .'<field id="unit_type" type="singleCheck"><value>'.$unit.'</value></field>'
            .'</complex-value></field>'
            .$moqXml
            .'</itemSchema>';
    }

    /**
     * Wholesale SKU price. Every SKU id is sent so a sibling price is kept.
     * The matching SKU, or the only SKU on the listing, receives the new price.
     *
     * @param  array<string, mixed>  $product
     */
    protected function skuPriceXml(array $product, string $amount, ?string $sku): string
    {
        $nodes = $this->skuDefinitionNodes($product);
        if ($nodes === []) {
            throw new \InvalidArgumentException('Alibaba SKU prices could not be read for this listing.');
        }

        $want = strtoupper(trim((string) $sku));
        $only = count($nodes) === 1;
        $values = '';
        $changed = false;
        foreach ($nodes as $node) {
            $code = strtoupper(trim((string) ($node['skuCode'] ?? $node['sku_code'] ?? '')));
            $skuId = trim((string) ($node['skuId'] ?? $node['sku_id'] ?? ''));
            if ($skuId === '' || preg_match('/^\d+$/', $skuId) !== 1) {
                throw new \InvalidArgumentException('Alibaba SKU id is missing, so this SKU price was not pushed.');
            }
            $isTarget = $only || ($want !== '' && $code === $want);
            if ($isTarget) {
                $nodePrice = $amount;
                $changed = true;
            } else {
                $kept = $this->listedSkuPrice($node);
                if (! ($kept > 0)) {
                    throw new \InvalidArgumentException('Another SKU on this Alibaba listing has no price, so the update was not sent.');
                }
                $nodePrice = number_format($kept, 2, '.', '');
            }
            $values .= '<complex-values>'
                .'<field id="skuId" type="input"><value>'.$skuId.'</value></field>'
                .'<field id="price" type="input"><value>'.$nodePrice.'</value></field>'
                .'</complex-values>';
        }
        if (! $changed) {
            throw new \InvalidArgumentException('This SKU was not found on the Alibaba listing, so its price was not pushed.');
        }

        $productType = strtolower(trim((string) ($product['productType'] ?? $product['product_type'] ?? '')));
        $typeField = $productType !== '' && $productType !== 'sourcing' ? 'marketPrice' : 'scPrice';

        return '<itemSchema>'
            .'<field id="'.$typeField.'" type="singleCheck"><value>3</value></field>'
            .'<field id="sku" type="multiComplex">'.$values.'</field>'
            .'</itemSchema>';
    }

    /**
     * @param  array<string, mixed>  $product
     * @return list<array<string, mixed>>
     */
    protected function skuDefinitionNodes(array $product): array
    {
        $skuBag = $product['productSku']['skus'] ?? $product['product_sku']['skus'] ?? [];
        if (isset($skuBag['skuDefinition']) && is_array($skuBag['skuDefinition'])) {
            $skuBag = $skuBag['skuDefinition'];
        } elseif (isset($skuBag['sku_definition']) && is_array($skuBag['sku_definition'])) {
            $skuBag = $skuBag['sku_definition'];
        }
        if (is_array($skuBag) && $skuBag !== [] && ! array_is_list($skuBag)) {
            $skuBag = [$skuBag];
        }
        if (! is_array($skuBag)) {
            return [];
        }

        $nodes = [];
        foreach ($skuBag as $node) {
            if (is_array($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function ladderPriceXml(array $product, string $amount, ?string $sku): string
    {
        $tiers = $this->ladderTiers($product, $sku);
        if ($tiers === []) {
            throw new \InvalidArgumentException('Alibaba ladder prices could not be read for this listing.');
        }

        usort($tiers, static fn (array $a, array $b): int => $a['qty'] <=> $b['qty']);
        $tiers[0]['price'] = $amount;

        $productType = strtolower(trim((string) ($product['productType'] ?? $product['product_type'] ?? '')));
        $typeField = $productType !== '' && $productType !== 'sourcing' ? 'marketPrice' : 'scPrice';
        $steps = '';
        foreach (array_values($tiers) as $index => $tier) {
            if ($index > 3) {
                break;
            }
            $steps .= '<field id="ladderPrice_'.$index.'" type="complex"><complex-value>'
                .'<field id="quantity" type="input"><value>'.$tier['qty'].'</value></field>'
                .'<field id="price" type="input"><value>'.$tier['price'].'</value></field>'
                .'</complex-value></field>';
        }

        return '<itemSchema>'
            .'<field id="'.$typeField.'" type="singleCheck"><value>1</value></field>'
            .'<field id="ladderPrice" type="complex"><complex-value>'.$steps.'</complex-value></field>'
            .'</itemSchema>';
    }

    /**
     * @param  array<string, mixed>  $product
     * @return list<array{qty: int, price: string}>
     */
    protected function ladderTiers(array $product, ?string $sku): array
    {
        $want = strtoupper(trim((string) $sku));
        $bags = [];
        foreach (['wholesaleTrade', 'wholesale_trade'] as $key) {
            $trade = $product[$key] ?? null;
            if (is_array($trade)) {
                foreach (['ladderPrice', 'ladder_price', 'bulkDiscountPrices', 'bulk_discount_prices'] as $priceKey) {
                    if (isset($trade[$priceKey])) {
                        $bags[] = $trade[$priceKey];
                    }
                }
            }
        }
        foreach (['bulkDiscountPrices', 'bulk_discount_prices'] as $priceKey) {
            if (isset($product[$priceKey])) {
                $bags[] = $product[$priceKey];
            }
        }

        $skuBag = $product['productSku']['skus'] ?? $product['product_sku']['skus'] ?? [];
        if (isset($skuBag['skuDefinition']) && is_array($skuBag['skuDefinition'])) {
            $skuBag = $skuBag['skuDefinition'];
        } elseif (isset($skuBag['sku_definition']) && is_array($skuBag['sku_definition'])) {
            $skuBag = $skuBag['sku_definition'];
        }
        if (is_array($skuBag) && $skuBag !== [] && ! array_is_list($skuBag)) {
            $skuBag = [$skuBag];
        }
        $matched = [];
        $fallback = [];
        if (is_array($skuBag)) {
            foreach ($skuBag as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $discounts = $node['bulkDiscountPrices'] ?? $node['bulk_discount_prices'] ?? null;
                if (! is_array($discounts)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($node['skuCode'] ?? $node['sku_code'] ?? '')));
                if ($want !== '' && $code === $want) {
                    $matched[] = $discounts;
                } else {
                    $fallback[] = $discounts;
                }
            }
        }
        foreach ($want !== '' && $matched !== [] ? $matched : array_merge($bags, $fallback) as $bag) {
            $tiers = $this->normalizeLadderTiers($bag);
            if ($tiers !== []) {
                return $tiers;
            }
        }

        return [];
    }

    /**
     * @return list<array{qty: int, price: string}>
     */
    protected function normalizeLadderTiers(mixed $discounts): array
    {
        if (! is_array($discounts)) {
            return [];
        }
        if (isset($discounts['bulk_discount_price']) && is_array($discounts['bulk_discount_price'])) {
            $discounts = $discounts['bulk_discount_price'];
        }
        if (isset($discounts['price']) && ! array_is_list($discounts)) {
            $discounts = [$discounts];
        }

        $rows = [];
        foreach ($discounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = $this->icbuMoney($row['price'] ?? null);
            $qty = $row['startQuantity'] ?? $row['start_quantity'] ?? $row['quantity'] ?? null;
            if ($amount <= 0 || ! is_numeric($qty) || (int) $qty < 1) {
                continue;
            }
            $rows[] = [
                'qty' => (int) $qty,
                'price' => number_format($amount, 2, '.', ''),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function icbuErrorMessage(array $raw): string
    {
        $candidates = [
            $raw['response']['result']['message_info'] ?? null,
            $raw['data']['result']['message_info'] ?? null,
            $raw['response']['result']['message'] ?? null,
            $raw['message'] ?? null,
        ];
        foreach ($candidates as $message) {
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        return 'Alibaba price update failed.';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function schemaError(array $raw, string $fallback): string
    {
        $message = $this->icbuErrorMessage($raw);

        return $message === 'Alibaba price update failed.' ? $fallback : $message;
    }

    protected function useIcbuRest(): void
    {
        if (! str_contains($this->restBase, 'openapi-api.alibaba.com')) {
            $this->restBase = 'https://openapi-api.alibaba.com/rest';
        }
    }

    /**
     * Full schema XML of a live product. Used to clone a listed sibling when adding a Missing L SKU.
     *
     * @return array{success: bool, message?: string, xml?: string, data?: mixed}
     */
    public function productSchemaXml(string $productId): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['success' => false, 'message' => 'Alibaba product id is missing.'];
        }

        $this->useIcbuRest();
        $params = [
            'product_id' => $productId,
            'language' => 'en_US',
        ];
        $raw = $this->callRestGateway('/icbu/product/schema/get', $params);
        if (empty($raw['success'])) {
            $raw = $this->callIcbu('alibaba.icbu.product.schema.get', $params);
        }
        if (empty($raw['success'])) {
            return ['success' => false, 'message' => $this->schemaError($raw, 'Alibaba did not return a product schema to copy.')];
        }

        $xml = \App\Support\Marketplace\AlibabaProductSchema::xmlFromPayload($raw);
        if ($xml === '') {
            return ['success' => false, 'message' => 'Alibaba did not return a product schema to copy.'];
        }

        return ['success' => true, 'xml' => $xml, 'data' => $raw['data'] ?? null];
    }

    /**
     * Empty schema for a category when no listed sibling exists to copy.
     *
     * @return array{success: bool, message?: string, xml?: string}
     */
    public function renderCategorySchema(string $catId): array
    {
        $catId = trim($catId);
        if ($catId === '' || ! ctype_digit($catId)) {
            return ['success' => false, 'message' => 'Alibaba category id is missing.'];
        }

        $this->useIcbuRest();
        $params = [
            'cat_id' => $catId,
            'language' => 'en_US',
        ];
        $raw = $this->callRestGateway('/icbu/product/schema/render', $params);
        if (empty($raw['success'])) {
            $raw = $this->callIcbu('alibaba.icbu.product.schema.render', $params);
        }
        if (empty($raw['success'])) {
            return ['success' => false, 'message' => $this->schemaError($raw, 'Alibaba did not return a category schema.')];
        }

        $xml = \App\Support\Marketplace\AlibabaProductSchema::xmlFromPayload($raw);
        if ($xml === '') {
            return ['success' => false, 'message' => 'Alibaba did not return a category schema.'];
        }

        return ['success' => true, 'xml' => $xml];
    }

    /**
     * Create a product from schema XML.
     *
     * @return array{success: bool, message?: string, product_id?: string, data?: mixed}
     */
    public function addProductSchema(string $catId, string $xml): array
    {
        $catId = trim($catId);
        $xml = trim($xml);
        if ($catId === '' || ! ctype_digit($catId)) {
            return ['success' => false, 'message' => 'Alibaba category id is missing.'];
        }
        if ($xml === '' || ! str_contains($xml, '<field')) {
            return ['success' => false, 'message' => 'Alibaba product schema is empty.'];
        }

        $this->useIcbuRest();
        $params = [
            'cat_id' => $catId,
            'xml' => $xml,
            'language' => 'en_US',
        ];
        $raw = $this->callRestGateway('/icbu/product/schema/add', $params);
        if (empty($raw['success']) && ! $this->isAuthError($raw)) {
            $raw = $this->callIcbu('alibaba.icbu.product.schema.add', $params);
        }
        if (empty($raw['success'])) {
            return ['success' => false, 'message' => $this->schemaError($raw, 'Alibaba did not create the product.')];
        }

        $productId = \App\Support\Marketplace\AlibabaProductSchema::productIdFromPayload($raw);

        return [
            'success' => $productId !== '',
            'message' => $productId !== '' ? '' : 'Alibaba accepted the product but did not return a product id.',
            'product_id' => $productId !== '' ? $productId : null,
            'data' => $raw['data'] ?? null,
        ];
    }
}
