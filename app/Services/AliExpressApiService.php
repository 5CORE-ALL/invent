<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AliExpress Open Platform — Solution API via POST https://api-sg.aliexpress.com/rest
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
    /** REST path prefix for HMAC string (official IOP /rest protocol). */
    protected const REST_SIGN_PREFIX = '/rest';

    protected string $appKey;

    protected string $appSecret;

    protected ?string $accessToken;

    protected string $apiBase;

    public function __construct()
    {
        $this->appKey = (string) (config('services.aliexpress.app_key') ?: env('ALIEXPRESS_APP_KEY', ''));
        $this->appSecret = (string) (config('services.aliexpress.app_secret') ?: env('ALIEXPRESS_APP_SECRET', ''));
        $this->accessToken = config('services.aliexpress.access_token') ?: env('ALIEXPRESS_ACCESS_TOKEN');
        $this->apiBase = rtrim((string) (config('services.aliexpress.api_base') ?: env('ALIEXPRESS_API_BASE', 'https://api-sg.aliexpress.com')), '/');
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

        $raw = $this->callRest('aliexpress.solution.product.list.get', [
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

        return $this->callRest('aliexpress.solution.product.edit', [
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
     * Debug: same signing and URL as production REST calls.
     *
     * @param  array<string, string|int>  $restParams  Already-encoded business params (e.g. product_list_get_request => json string)
     */
    public function debugCallRest(string $method, array $restParams = []): array
    {
        $params = $this->buildBaseParams($method);
        foreach ($restParams as $k => $v) {
            $params[$k] = $v;
        }
        $signSource = $this->buildSignSource($params, self::REST_SIGN_PREFIX);
        $params['sign'] = $this->sign($signSource);

        $url = $this->apiBase . '/rest';
        $rawBody = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        Log::debug('AliExpress REST debug', [
            'url' => $url,
            'sign_source' => $signSource,
            'params_keys' => array_keys($params),
        ]);

        $response = Http::withoutVerifying()
            ->asForm()
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->post($url, $params);

        return [
            'request' => [
                'url' => $url,
                'params' => $params,
                'raw_body' => $rawBody,
                'sign_source' => $signSource,
            ],
            'response' => [
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $businessParams  Top-level REST keys (e.g. edit_product_request => JSON string)
     */
    private function callRest(string $method, array $businessParams = []): array
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
                'message' => 'AliExpress access_token is missing.',
            ];
        }

        $params = $this->buildBaseParams($method);
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $params[$key] = is_array($value) ? $this->encodeRequestPayload($value) : (string) $value;
        }

        $signSource = $this->buildSignSource($params, self::REST_SIGN_PREFIX);
        $params['sign'] = $this->sign($signSource);

        $url = $this->apiBase . '/rest';

        $response = Http::withoutVerifying()
            ->asForm()
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->post($url, $params);

        $json = $response->json();
        $body = $response->body();

        Log::info('AliExpress REST call', [
            'method' => $method,
            'status' => $response->status(),
            'request_url' => $url,
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

    private function buildBaseParams(string $method): array
    {
        return [
            'app_key' => $this->appKey,
            'timestamp' => (int) round(microtime(true) * 1000),
            'sign_method' => 'sha256',
            'method' => $method,
            'access_token' => $this->accessToken,
        ];
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
     * IOP REST: sign string = "/rest" + sorted key1val1key2val2... (no app_secret wrapping).
     */
    private function buildSignSource(array $params, string $apiPathPrefix): string
    {
        unset($params['sign']);
        ksort($params);

        $source = $apiPathPrefix;
        foreach ($params as $key => $value) {
            $source .= (string) $key . (string) $value;
        }

        return $source;
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
