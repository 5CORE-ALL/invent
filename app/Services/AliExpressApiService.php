<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliExpressApiService
{
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

    public function getInventory(int $page = 1, int $pageSize = 20): array
    {
        return $this->callSync(
            'aliexpress.solution.product.list.get',
            [
                'current_page' => $page,
                'page_size' => $pageSize,
            ]
        );
    }

    public function updateTitle(string $productId, string $title): array
    {
        return $this->callSync(
            'aliexpress.solution.product.edit',
            [
                'product_id' => $productId,
                'subject' => $title,
            ]
        );
    }

    /**
     * Temporary debug helper:
     * Returns exact request URL, raw body, signature source string and full response.
     */
    public function debugCallSync(string $method, array $bizParams = []): array
    {
        $params = $this->buildBaseParams($method);
        $params = array_merge($params, $this->normalizeParams($bizParams));
        $signSource = $this->buildSignSource($params, null); // /sync style: no apiPath prefix
        $params['sign'] = $this->sign($signSource);

        $url = $this->apiBase . '/sync';
        $rawBody = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        Log::debug('AliExpress debug request', [
            'url' => $url,
            'params' => $params,
            'raw_body' => $rawBody,
            'sign_source' => $signSource,
        ]);

        $response = Http::withoutVerifying()
            ->asForm()
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->post($url, $params);

        $result = [
            'request' => [
                'url' => $url,
                'params' => $params,
                'raw_body' => $rawBody,
                'sign_source' => $signSource,
            ],
            'response' => [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'json' => $response->json(),
            ],
        ];

        Log::debug('AliExpress debug response', $result['response']);

        return $result;
    }

    private function callSync(string $method, array $bizParams = []): array
    {
        if (empty($this->appKey) || empty($this->appSecret)) {
            return [
                'success' => false,
                'message' => 'AliExpress app credentials are missing.',
            ];
        }

        $params = $this->buildBaseParams($method);
        $params = array_merge($params, $this->normalizeParams($bizParams));
        $signSource = $this->buildSignSource($params, null); // /sync style
        $params['sign'] = $this->sign($signSource);

        $url = $this->apiBase . '/sync';

        $response = Http::withoutVerifying()
            ->asForm()
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->post($url, $params);

        $json = $response->json();
        $body = $response->body();

        Log::info('AliExpress callSync', [
            'method' => $method,
            'status' => $response->status(),
            'request_url' => $url,
            'request_body' => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            'response_body' => $body,
        ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => 'AliExpress HTTP request failed.',
                'response' => $json ?: $body,
            ];
        }

        // AliExpress business errors often come in code/message at top-level.
        if (is_array($json) && isset($json['code']) && (string) $json['code'] !== '0') {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $json['message'] ?? 'AliExpress API error.',
                'response' => $json,
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'data' => $json ?: $body,
        ];
    }

    private function buildBaseParams(string $method): array
    {
        $params = [
            'app_key' => $this->appKey,
            'timestamp' => (int) round(microtime(true) * 1000),
            'sign_method' => 'sha256',
            'method' => $method,
        ];

        if (!empty($this->accessToken)) {
            $params['access_token'] = $this->accessToken;
        }

        return $params;
    }

    private function normalizeParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized[$key] = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $value;
        }
        return $normalized;
    }

    private function buildSignSource(array $params, ?string $apiPath): string
    {
        unset($params['sign']);
        ksort($params);

        $source = '';
        if (!empty($apiPath)) {
            $source .= $apiPath;
        }

        foreach ($params as $key => $value) {
            $source .= (string) $key . (string) $value;
        }

        return $source;
    }

    private function sign(string $source): string
    {
        return strtoupper(hash_hmac('sha256', $source, $this->appSecret));
    }
}
