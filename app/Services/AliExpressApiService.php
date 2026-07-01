<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AliexpressMetric;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\SavesMarketplaceImageMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

/**
 * AliExpress dropshipping / Solution API — POST https://api-sg.aliexpress.com/sync
 *
 * Sign string: `/sync` + sorted `key`+`value` for all public params (HMAC-SHA256, uppercase hex).
 * Token is sent as `session` (not `access_token`) for this gateway.
 *
 * Test (tinker):
 *   app(\App\Services\AliExpressApiService::class)->getInventory(1, 5);
 *   app(\App\Services\AliExpressApiService::class)->updateTitle('1000005237852', 'New title');
 *
 * Artisan:
 *   php artisan aliexpress:test list
 *   php artisan aliexpress:test edit --product-id=ID --title="New title"
 */
class AliExpressApiService
{
    use SavesMarketplaceVideoMetrics;
    use SavesMarketplaceImageMetrics;
    use VideoMasterMarketplaceMethods;

    protected string $appKey;

    protected string $appSecret;

    protected ?string $accessToken;

    /** Full POST URL including path, e.g. https://api-sg.aliexpress.com/sync */
    protected string $apiBase;

    /** Leading path used as prefix in sign string (e.g. /sync); must match dropshipping API path. */
    protected string $signPath;

    /** `session` (dropshipping /sync) or `access_token` if your gateway requires it. */
    protected string $tokenParam;

    protected string $partnerId;

    protected string $format;

    /** String "true" / "false" for form + signature. */
    protected string $simplify;

    /** Official IOP: query + multipart. Legacy: single x-www-form-urlencoded body. */
    protected string $transport;

    /** Matches IOP SDK msectime() vs true millisecond timestamp. */
    protected string $timestampStyle;

    public function __construct()
    {
        $this->appKey = (string) (config('services.aliexpress.app_key') ?: env('ALIEXPRESS_APP_KEY', ''));
        $this->appSecret = (string) (config('services.aliexpress.app_secret') ?: env('ALIEXPRESS_APP_SECRET', ''));
        $this->accessToken = config('services.aliexpress.access_token') ?: env('ALIEXPRESS_ACCESS_TOKEN');
        $this->apiBase = $this->normalizeSyncApiBase(
            (string) (config('services.aliexpress.api_base') ?: env('ALIEXPRESS_API_BASE', 'https://api-sg.aliexpress.com/sync'))
        );
        $sp = (string) (config('services.aliexpress.sign_path') ?? env('ALIEXPRESS_SIGN_PATH', '/sync'));
        $this->signPath = ($sp !== '' && $sp[0] === '/') ? $sp : '/'.$sp;
        $tp = strtolower((string) (config('services.aliexpress.token_param') ?: env('ALIEXPRESS_TOKEN_PARAM', 'session')));
        $this->tokenParam = in_array($tp, ['session', 'access_token'], true) ? $tp : 'session';
        $this->partnerId = (string) (config('services.aliexpress.partner_id') ?: env('ALIEXPRESS_PARTNER_ID', 'iop-sdk-php'));
        $this->format = (string) (config('services.aliexpress.format') ?: env('ALIEXPRESS_FORMAT', 'json'));
        $sim = config('services.aliexpress.simplify') ?? env('ALIEXPRESS_SIMPLIFY', 'true');
        $this->simplify = is_bool($sim) ? ($sim ? 'true' : 'false') : (string) $sim;
        $tr = strtolower((string) (config('services.aliexpress.transport') ?: env('ALIEXPRESS_TRANSPORT', 'iop')));
        $this->transport = in_array($tr, ['iop', 'form'], true) ? $tr : 'iop';
        $ts = strtolower((string) (config('services.aliexpress.timestamp_style') ?: env('ALIEXPRESS_TIMESTAMP_STYLE', 'iop')));
        $this->timestampStyle = in_array($ts, ['iop', 'ms'], true) ? $ts : 'iop';
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Product list — method aliexpress.solution.product.list.get with product_list_get_request (JSON).
     */
    public function getInventory(int $page = 1, int $pageSize = 20, array $extraListParams = []): array
    {
        $listRequest = $this->buildProductListRequest(array_merge([
            'current_page' => $page,
            'page_size' => $pageSize,
        ], $extraListParams));

        $raw = $this->callSync('aliexpress.solution.product.list.get', [
            'product_list_get_request' => $this->encodeRequestPayload($listRequest),
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $raw['data'] ?? [];
        $parsed = $this->parseSolutionProductListResponse($payload);

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $parsed,
            'raw' => $payload,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Update title — method aliexpress.solution.product.edit with edit_product_request (JSON).
     */
    public function updateTitle(string $productId, string $title, ?string $language = 'en'): array
    {
        $editRequest = $this->buildEditProductRequest($productId, $title, $language);

        return $this->callSync('aliexpress.solution.product.edit', [
            'edit_product_request' => $this->encodeRequestPayload($editRequest),
        ]);
    }

    /**
     * Build body for edit_product_request (multi-language title per official docs).
     */
    public function buildEditProductRequest(string $productId, string $title, ?string $language = 'en'): array
    {
        return [
            'product_id' => (string) $productId,
            'multi_language_subject_list' => [
                [
                    'subject' => $title,
                    'language' => $language ?: 'en',
                ],
            ],
        ];
    }

    /**
     * Build body for product_list_get_request.
     *
     * @param  array<string, mixed>  $params  e.g. current_page, page_size, optional filters from docs
     */
    public function buildProductListRequest(array $params): array
    {
        return array_merge([
            'current_page' => 1,
            'page_size' => 20,
        ], $params);
    }

    /**
     * Debug: same signing and URL as production /sync calls.
     *
     * @param  array<string, string|int>  $restParams  Already-encoded business params (e.g. product_list_get_request => json string)
     */
    public function debugCallRest(string $method, array $restParams = []): array
    {
        $system = $this->buildBaseParams($method);
        $api = [];
        foreach ($restParams as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $api[$k] = is_array($v) ? $this->encodeRequestPayload($v) : (string) $v;
        }

        $forSign = array_merge($api, $system);
        $signSource = $this->buildSignSource($forSign);
        $sign = $this->sign($signSource);
        $system['sign'] = $sign;

        $requestDebug = [
            'transport' => $this->transport,
            'sign_source' => $signSource,
            'sign' => $sign,
            'system_params' => $system,
            'api_params' => $api,
        ];

        if ($this->transport === 'iop') {
            $queryUrl = $this->apiBase.'?'.http_build_query($system, '', '&', PHP_QUERY_RFC3986);
            $requestDebug['request_url'] = $queryUrl;
            $requestDebug['api_multipart_keys'] = array_keys($api);

            $multipart = [];
            foreach ($api as $name => $contents) {
                $multipart[] = ['name' => $name, 'contents' => $contents];
            }

            $pending = Http::withoutVerifying()->asMultipart();
            $response = $multipart === []
                ? $pending->post($queryUrl)
                : $pending->post($queryUrl, $multipart);
        } else {
            $url = $this->apiBase;
            $merged = array_merge($api, $system);
            $requestDebug['request_url'] = $url;
            $requestDebug['raw_body'] = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
            $response = Http::withoutVerifying()
                ->asForm()
                ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->post($url, $merged);
        }

        Log::debug('AliExpress sync debug', $requestDebug);

        return [
            'request' => $requestDebug,
            'response' => [
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ],
        ];
    }

    /**
     * POST to dropshipping `/sync` endpoint with IOP-style transport (query + multipart).
     *
     * @param  array<string, mixed>  $businessParams  Top-level API keys (e.g. edit_product_request => JSON string)
     */
    private function callSync(string $method, array $businessParams = []): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return [
                'success' => false,
                'message' => 'AliExpress app_key / app_secret are missing.',
            ];
        }

        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => 'AliExpress OAuth token is missing (set ALIEXPRESS_ACCESS_TOKEN; sent as '.$this->tokenParam.').',
            ];
        }

        $system = $this->buildBaseParams($method);
        $api = [];
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $api[$key] = is_array($value) ? $this->encodeRequestPayload($value) : (string) $value;
        }

        $forSign = array_merge($api, $system);
        $signSource = $this->buildSignSource($forSign);
        $sign = $this->sign($signSource);
        $system['sign'] = $sign;

        if ($this->transport === 'iop') {
            $queryUrl = $this->apiBase.'?'.http_build_query($system, '', '&', PHP_QUERY_RFC3986);
            $multipart = [];
            foreach ($api as $name => $contents) {
                $multipart[] = ['name' => $name, 'contents' => $contents];
            }
            $pending = Http::withoutVerifying()->asMultipart();
            $response = $multipart === []
                ? $pending->post($queryUrl)
                : $pending->post($queryUrl, $multipart);
        } else {
            $merged = array_merge($api, $system);
            $response = Http::withoutVerifying()
                ->asForm()
                ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->post($this->apiBase, $merged);
        }

        $json = $response->json();
        $body = $response->body();

        Log::info('AliExpress sync call', [
            'method' => $method,
            'transport' => $this->transport,
            'status' => $response->status(),
            'response_body' => mb_substr((string) $body, 0, 4000),
        ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => 'AliExpress HTTP request failed.',
                'response' => $json ?: $body,
            ];
        }

        if (!is_array($json)) {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => 'Invalid JSON response.',
                'response' => $body,
            ];
        }

        if (isset($json['error_response'])) {
            $err = $json['error_response'];

            return [
                'success' => false,
                'status' => $response->status(),
                'message' => is_array($err)
                    ? ($err['msg'] ?? $err['message'] ?? $err['sub_msg'] ?? json_encode($err))
                    : (string) $err,
                'response' => $json,
            ];
        }

        $json = $this->unwrapSolutionEnvelope($json);

        if (isset($json['type'], $json['code']) && ($json['type'] ?? '') === 'ISV') {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $json['message'] ?? 'AliExpress ISV error.',
                'response' => $json,
            ];
        }

        // Success: code "0" (string) or 0
        if (array_key_exists('code', $json)) {
            $code = $json['code'];
            if ((string) $code !== '0' && $code !== 0) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'message' => $json['message'] ?? $json['msg'] ?? 'AliExpress API error.',
                    'code' => $code,
                    'response' => $json,
                ];
            }
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'data' => $json,
            'result' => $json['result'] ?? null,
            'request_id' => $json['request_id'] ?? null,
        ];
    }

    /**
     * IOP SDK {@see IopClient::msectime()} uses second + "000", not true ms; must match what you send.
     */
    private function buildTimestampForSign(): string
    {
        if ($this->timestampStyle === 'ms') {
            return (string) (int) round(microtime(true) * 1000);
        }

        return time().'000';
    }

    private function buildBaseParams(string $method): array
    {
        $params = [
            'app_key' => $this->appKey,
            'format' => $this->format,
            'method' => $method,
            'partner_id' => $this->partnerId,
            'sign_method' => 'sha256',
            'simplify' => $this->simplify,
            'timestamp' => $this->buildTimestampForSign(),
        ];

        if ($this->tokenParam === 'access_token') {
            $params['access_token'] = $this->accessToken;
        } else {
            $params['session'] = $this->accessToken;
        }

        return $params;
    }

    /**
     * JSON for *_request parameters; compact and stable for signing.
     *
     * @param  array<string, mixed>  $payload
     */
    private function encodeRequestPayload(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Dropshipping /sync: sign string = API path + sorted key1val1key2val2... then HMAC-SHA256 (uppercase).
     */
    private function buildSignSource(array $params): string
    {
        unset($params['sign']);
        ksort($params);

        $source = $this->signPath;
        foreach ($params as $key => $value) {
            $source .= (string) $key.(string) $value;
        }

        return $source;
    }

    /**
     * Ensure POST URL is the sync endpoint (migrate host-only or legacy /rest URLs).
     */
    private function normalizeSyncApiBase(string $raw): string
    {
        $raw = rtrim($raw, '/');
        $lower = strtolower($raw);
        if (str_ends_with($lower, '/rest')) {
            return substr($raw, 0, -strlen('/rest')).'/sync';
        }
        if (! str_ends_with($lower, '/sync')) {
            return $raw.'/sync';
        }

        return $raw;
    }

    private function sign(string $source): string
    {
        return strtoupper(hash_hmac('sha256', $source, $this->appSecret));
    }

    /**
     * Unwrap single-key nested response like aliexpress_solution_product_edit_response.
     */
    private function unwrapSolutionEnvelope(array $json): array
    {
        if (count($json) !== 1) {
            return $json;
        }
        $first = reset($json);
        if (!is_array($first)) {
            return $json;
        }
        $key = key($json);
        if (!is_string($key)) {
            return $json;
        }
        if (
            str_contains(strtolower($key), 'response')
            || str_contains(strtolower($key), 'aliexpress_')
        ) {
            return $first;
        }

        return $json;
    }

    /**
     * Normalize product list JSON after successful REST call.
     */
    private function parseSolutionProductListResponse($payload): array
    {
        if (!is_array($payload)) {
            return [
                'products' => [],
                'total_count' => null,
                'current_page' => null,
                'page_size' => null,
            ];
        }

        $payload = $this->unwrapSolutionEnvelope($payload);

        $result = $payload['result'] ?? $payload;

        if (!is_array($result)) {
            $result = [];
        }

        $products = $result['aeop_ae_product_display_dto_list']
            ?? $result['aeop_ae_product_display_d_t_o_list']
            ?? $result['product_list']
            ?? $result['products']
            ?? [];

        if (!is_array($products)) {
            $products = [];
        }

        if ($products !== [] && !isset($products[0]) && isset($products['product_id'])) {
            $products = [$products];
        }

        return [
            'products' => $products,
            'total_count' => $result['total_count'] ?? $result['total_item'] ?? $result['totalCount'] ?? null,
            'current_page' => $result['current_page'] ?? $result['currentPage'] ?? null,
            'page_size' => $result['page_size'] ?? $result['pageSize'] ?? null,
        ];
    }

    /**
     * Push detail / mobile description via solution.product.edit (no truncation).
     *
     * @return array{success: bool, message?: string, status?: int, data?: mixed}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints, ?string $language = 'en'): array
    {
        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and bullet points are required.'];
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;

        $html = '<ul>';
        foreach (preg_split('/\r\n|\r|\n/', $bulletPoints) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $html .= '</ul>';

        $editRequest = [
            'product_id' => (string) $productId,
            'multi_language_description_list' => [
                [
                    'language' => $language ?: 'en',
                    'mobile_detail' => $html,
                    'web_detail' => $html,
                ],
            ],
        ];

        $res = $this->callSync('aliexpress.solution.product.edit', [
            'edit_product_request' => $this->encodeRequestPayload($editRequest),
        ]);

        if (! empty($res['success'])) {
            return [
                'success' => true,
                'message' => 'AliExpress product detail updated.',
                'data' => $res['data'] ?? $res['result'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => (string) ($res['message'] ?? 'AliExpress product edit failed.'),
            'response' => $res['response'] ?? $res,
        ];
    }

    /**
     * Long-form product detail (prose HTML, not bullet list).
     *
     * @return array{success: bool, message?: string, status?: int, data?: mixed}
     */
    public function updateProductDescription(string $identifier, string $description, ?string $language = 'en'): array
    {
        $description = trim($description);
        if (trim($identifier) === '' || $description === '') {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and description are required.'];
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;

        $html = '<div class="product-description">'.nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</div>';

        $editRequest = [
            'product_id' => (string) $productId,
            'multi_language_description_list' => [
                [
                    'language' => $language ?: 'en',
                    'mobile_detail' => $html,
                    'web_detail' => $html,
                ],
            ],
        ];

        $res = $this->callSync('aliexpress.solution.product.edit', [
            'edit_product_request' => $this->encodeRequestPayload($editRequest),
        ]);

        if (! empty($res['success'])) {
            return [
                'success' => true,
                'message' => 'AliExpress product description updated.',
                'data' => $res['data'] ?? $res['result'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => (string) ($res['message'] ?? 'AliExpress product edit failed.'),
            'response' => $res['response'] ?? $res,
        ];
    }

    /**
     * Single product detail — aliexpress.solution.product.info.get (SKU list + prices).
     */
    public function getProductInfo(string $productId): array
    {
        $raw = $this->callSync('aliexpress.solution.product.info.get', [
            'product_id' => (string) $productId,
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $result,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Order list — aliexpress.solution.order.get (param0 = OrderQuery JSON).
     *
     * @param  array<string, mixed>  $query  create_date_start/end, modified_date_*, current_page, page_size, order_status, …
     */
    public function getOrders(int $page = 1, int $pageSize = 20, array $query = []): array
    {
        $orderQuery = array_merge([
            'current_page' => $page,
            'page_size' => $pageSize,
        ], $query);

        $raw = $this->callSync('aliexpress.solution.order.get', [
            'param0' => $this->encodeRequestPayload($orderQuery),
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $parsed = $this->parseSolutionOrderListResponse($payload);

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $parsed,
            'raw' => $payload,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Daily sales for one product (last 30 days) — aliexpress.data.redefining.queryproductsalesinfoeverydaybyid.
     */
    public function getProductDailySales(string $productId, string $startDate, string $endDate, int $page = 1, int $pageSize = 50): array
    {
        $raw = $this->callSync('aliexpress.data.redefining.queryproductsalesinfoeverydaybyid', [
            'product_id' => (string) $productId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'current_page' => $page,
            'page_size' => $pageSize,
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $resultJson = $payload['result'] ?? null;
        $parsed = is_string($resultJson) ? json_decode($resultJson, true) : (is_array($resultJson) ? $resultJson : $payload);

        return [
            'success' => true,
            'data' => is_array($parsed) ? $parsed : [],
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * @return array<int, array{sku: string, price: float, stock: int|null, product_id: string, product_name: string|null}>
     */
    public function extractSkuRowsFromListItem(array $item, bool $fetchDetail = false): array
    {
        $item = $this->normalizeApiRow($item);
        $productId = (string) ($item['product_id'] ?? $item['id'] ?? '');
        if ($productId === '') {
            return [];
        }

        $productName = $this->extractProductName($item);
        $rows = [];

        if ($fetchDetail) {
            $info = $this->getProductInfo($productId);
            if (! empty($info['success']) && is_array($info['data'] ?? null)) {
                $rows = $this->extractSkuRowsFromProductInfo($info['data'], $productId, $productName);

                return $rows;
            }
        }

        $nested = $item['aeop_ae_product_sku_list']
            ?? $item['aeop_ae_product_s_k_us']
            ?? $item['aeop_aeop_product_skus']
            ?? $item['skus']
            ?? $item['product_skus']
            ?? null;

        if (is_array($nested) && $nested !== []) {
            foreach ($this->normalizeList($nested) as $skuRow) {
                $skuRow = $this->normalizeApiRow($skuRow);
                $sku = trim((string) ($skuRow['sku_code'] ?? $skuRow['sku'] ?? ''));
                $price = $this->extractPriceFromRow($skuRow);
                if ($sku === '' && $price <= 0) {
                    continue;
                }
                $rows[] = [
                    'product_id' => $productId,
                    'sku' => $sku !== '' ? $sku : $productId,
                    'price' => $price > 0 ? $price : $this->extractListPrice($item),
                    'stock' => $this->extractStockFromRow($skuRow),
                    'product_name' => $productName,
                ];
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        $price = $this->extractListPrice($item);
        $stock = $this->extractStockFromRow($item);
        if ($price > 0 || $productName !== null || $stock !== null) {
            $rows[] = [
                'product_id' => $productId,
                'sku' => $productId,
                'price' => $price,
                'stock' => $stock,
                'product_name' => $productName,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{sku: string, price: float, stock: int|null, product_id: string, product_name: string|null}>
     */
    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $info = $this->normalizeApiRow($info);
        $productName = $productName ?? $this->extractProductName($info);
        $rows = [];

        $skus = $info['aeop_ae_product_sku_list']
            ?? $info['aeop_ae_product_s_k_us']
            ?? $info['aeop_a_e_product_s_k_u_list']
            ?? [];

        foreach ($this->normalizeList($skus) as $skuRow) {
            $skuRow = $this->normalizeApiRow($skuRow);
            $sku = trim((string) ($skuRow['sku_code'] ?? $skuRow['sku'] ?? ''));
            $price = $this->extractPriceFromRow($skuRow);
            if ($sku === '' && $price <= 0) {
                continue;
            }
            $rows[] = [
                'product_id' => $productId,
                'sku' => $sku !== '' ? $sku : $productId,
                'price' => $price,
                'stock' => $this->extractStockFromRow($skuRow),
                'product_name' => $productName,
            ];
        }

        if ($rows === []) {
            $price = $this->extractListPrice($info);
            $stock = $this->extractStockFromRow($info);
            if ($price > 0 || $stock !== null) {
                $rows[] = [
                    'product_id' => $productId,
                    'sku' => $productId,
                    'price' => $price,
                    'stock' => $stock,
                    'product_name' => $productName,
                ];
            }
        }

        return $rows;
    }

    /**
     * Normalize order list payload to a flat list of orders with product lines.
     */
    private function parseSolutionOrderListResponse(array $payload): array
    {
        $result = $payload['result'] ?? $payload;
        if (! is_array($result)) {
            $result = [];
        }

        $orders = $result['target_list']
            ?? $result['order_list']
            ?? $result['orders']
            ?? [];

        $orders = $this->normalizeList($orders);

        return [
            'orders' => $orders,
            'total_count' => $result['total_count'] ?? $result['totalCount'] ?? null,
            'current_page' => $result['current_page'] ?? null,
            'page_size' => $result['page_size'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractOrderProductLines(array $order): array
    {
        $order = $this->normalizeApiRow($order);
        $products = $order['product_list']['order_product_dto']
            ?? $order['product_list']['aeop_order_product_dto']
            ?? $order['product_list']
            ?? $order['child_order_list']
            ?? [];

        $lines = [];
        foreach ($this->normalizeList($products) as $product) {
            $product = $this->normalizeApiRow($product);
            $lines[] = [
                'product_id' => (string) ($product['product_id'] ?? ''),
                'sku_code' => (string) ($product['sku_code'] ?? $product['sku'] ?? ''),
                'product_count' => (int) ($product['product_count'] ?? $product['quantity'] ?? 1),
                'product_unit_price' => [
                    'amount' => $this->extractPriceFromRow($product),
                ],
                'product_name' => $product['product_name'] ?? $product['subject'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * Build order query date range (US Pacific) for the last N days.
     *
     * @return array{create_date_start: string, create_date_end: string}
     */
    public function buildOrderDateRange(int $days): array
    {
        $end = Carbon::now('America/Los_Angeles');
        $start = $end->copy()->subDays(max(1, $days));

        return [
            'create_date_start' => $start->format('Y-m-d H:i:s'),
            'create_date_end' => $end->format('Y-m-d H:i:s'),
        ];
    }

    private function extractListPrice(array $item): float
    {
        $item = $this->normalizeApiRow($item);
        foreach (['product_min_price', 'product_max_price', 'price', 'sale_price'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (float) $item[$key];
            }
        }

        return $this->extractPriceFromRow($item);
    }

    private function extractStockFromRow(array $row): ?int
    {
        $row = $this->normalizeApiRow($row);
        foreach (['ipm_sku_stock', 'sku_stock', 'stock', 'inventory', 'available_stock', 'sku_inventory', 'quantity'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $val = $row[$key];
            if ($val === true || $val === 'true') {
                return 1;
            }
            if ($val === false || $val === 'false' || $val === null || $val === '') {
                continue;
            }
            if (is_numeric($val)) {
                return max(0, (int) $val);
            }
        }

        return null;
    }

    private function extractPriceFromRow(array $row): float
    {
        $row = $this->normalizeApiRow($row);
        foreach (['sku_price', 'price', 'product_min_price', 'product_max_price', 'sale_price', 'unit_price'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (float) $row[$key];
            }
        }
        if (isset($row['product_unit_price']) && is_array($row['product_unit_price'])) {
            $amount = $row['product_unit_price']['amount'] ?? null;
            if ($amount !== null && is_numeric($amount)) {
                return (float) $amount;
            }
        }

        return 0.0;
    }

    private function extractProductName(array $item): ?string
    {
        $item = $this->normalizeApiRow($item);
        foreach (['subject', 'product_name', 'title', 'product_title'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return trim($item[$key]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeApiRow(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }
        if (is_object($row)) {
            return json_decode(json_encode($row), true) ?: [];
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }
        if ($list === []) {
            return [];
        }
        if (! isset($list[0]) && (isset($list['product_id']) || isset($list['sku_code']) || isset($list['order_id']))) {
            return [$list];
        }

        return array_values($list);
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message?: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;
        $primary = $videos[0];

        $attempts = [
            ['product_id' => $productId, 'video_url' => $primary, 'product_video_url' => $primary],
            ['product_id' => $productId, 'multimedia' => ['video_url' => $primary]],
            ['product_id' => $productId, 'aeop_a_e_multimedia' => ['aeop_a_e_videos' => [['video_url' => $primary]]]],
        ];

        $lastMessage = 'AliExpress video update failed.';
        foreach ($attempts as $editRequest) {
            $res = $this->callSync('aliexpress.solution.product.edit', [
                'edit_product_request' => $this->encodeRequestPayload($editRequest),
            ]);
            if (! empty($res['success'])) {
                $sku = $row && $row->sku ? (string) $row->sku : $trim;
                $this->saveVideoUrlsToMetricsRow('aliexpress_metrics', $sku, $videos);

                return [
                    'success' => true,
                    'message' => 'AliExpress product video updated.',
                    'normalized_urls' => $videos,
                ];
            }
            $lastMessage = (string) ($res['message'] ?? $lastMessage);
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message?: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if (trim($identifier) === '' || $images === []) {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and at least one image URL are required.'];
        }

        foreach ($images as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
            }
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;
        $primary = $images[0];

        $attempts = [
            ['product_id' => $productId, 'image_u_r_ls' => implode(';', $images), 'main_image_url' => $primary],
            ['product_id' => $productId, 'image_urls' => $images, 'main_image_url' => $primary],
            ['product_id' => $productId, 'aeop_a_e_product_s_k_us' => ['sku_code' => $trim, 'sku_image' => $primary]],
        ];

        $lastMessage = 'AliExpress image update failed.';
        foreach ($attempts as $editRequest) {
            $res = $this->callSync('aliexpress.solution.product.edit', [
                'edit_product_request' => $this->encodeRequestPayload($editRequest),
            ]);
            if (! empty($res['success'])) {
                $sku = $row && $row->sku ? (string) $row->sku : $trim;
                $this->saveImageUrlsToMetricsRow('aliexpress_metrics', $sku, $images);

                return [
                    'success' => true,
                    'message' => 'AliExpress product images updated.',
                    'normalized_urls' => $images,
                ];
            }
            $lastMessage = (string) ($res['message'] ?? $lastMessage);
        }

        return ['success' => false, 'message' => $lastMessage];
    }
}
