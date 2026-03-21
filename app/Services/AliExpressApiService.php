<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
}
