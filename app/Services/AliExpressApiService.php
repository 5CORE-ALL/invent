<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AliExpressApiService
{
    protected string $appKey;
    protected string $appSecret;
    protected string $apiBase;
    protected ?string $refreshToken;
    protected ?string $accessTokenFromConfig;
    protected int $timeoutSeconds = 45;

    public function __construct()
    {
        $this->appKey = (string) (config('services.aliexpress.app_key') ?: env('ALIEXPRESS_APP_KEY'));
        $this->appSecret = (string) (config('services.aliexpress.app_secret') ?: env('ALIEXPRESS_APP_SECRET'));
        $this->apiBase = rtrim((string) config('services.aliexpress.api_base', 'https://api-sg.aliexpress.com'), '/');
        $this->accessTokenFromConfig = config('services.aliexpress.access_token') ?: env('ALIEXPRESS_ACCESS_TOKEN');
        $this->refreshToken = config('services.aliexpress.refresh_token') ?: env('ALIEXPRESS_REFRESH_TOKEN');
    }

    public function getAccessToken(): ?string
    {
        $cacheKey = 'aliexpress_access_token';
        $cached = Cache::get($cacheKey);
        if (! empty($cached)) {
            return $cached;
        }

        if ($this->appKey === '' || $this->appSecret === '') {
            Log::error('AliExpress token: missing app credentials', [
                'has_app_key' => $this->appKey !== '',
                'has_app_secret' => $this->appSecret !== '',
            ]);
            return $this->accessTokenFromConfig;
        }

        if (! empty($this->refreshToken)) {
            $refreshed = $this->refreshAccessToken((string) $this->refreshToken);
            if (! empty($refreshed)) {
                return $refreshed;
            }
        } else {
            Log::warning('AliExpress token: refresh token missing, using configured access token');
        }

        if (! empty($this->accessTokenFromConfig)) {
            Cache::put($cacheKey, $this->accessTokenFromConfig, now()->addMinutes(30));
            return $this->accessTokenFromConfig;
        }

        return null;
    }

    public function getInventory(int $page = 1, int $pageSize = 20, string $status = 'ONLINE'): array
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return ['success' => false, 'message' => 'AliExpress access token unavailable'];
        }

        $bizParams = [
            'channel' => 'AE_GLOBAL',
            'current_page' => $page,
            'page_size' => $pageSize,
            'search_condition_do' => json_encode([
                'product_status' => $status,
            ], JSON_UNESCAPED_UNICODE),
        ];

        $result = $this->callRestApi('aliexpress.local.service.products.list', $bizParams, $accessToken);
        if (($result['success'] ?? false) !== true) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'Inventory fetched successfully',
            'data' => $result['data'],
        ];
    }

    public function updateTitle(string $productId, string $title): array
    {
        $productId = trim($productId);
        $title = trim($title);
        if ($productId === '' || $title === '') {
            return ['success' => false, 'message' => 'productId and title are required'];
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return ['success' => false, 'message' => 'AliExpress access token unavailable'];
        }

        $methods = [
            'aliexpress.product.update',
            'aliexpress.solution.product.edit',
            'aliexpress.solution.schema.product.full.update',
        ];

        $payloads = [
            ['product_id' => $productId, 'subject' => mb_substr($title, 0, 218)],
            ['product_id' => $productId, 'title' => mb_substr($title, 0, 218)],
            ['product_id' => $productId, 'subject' => mb_substr($title, 0, 218), 'channel' => 'AE_GLOBAL'],
        ];

        $lastError = 'Unknown AliExpress error';
        foreach ($methods as $method) {
            foreach ($payloads as $bizParams) {
                $res = $this->callRestApi($method, $bizParams, $accessToken);
                if (($res['success'] ?? false) === true) {
                    Log::info('AliExpress title updated', [
                        'method' => $method,
                        'product_id' => $productId,
                    ]);
                    return [
                        'success' => true,
                        'message' => 'AliExpress title updated successfully',
                        'method' => $method,
                        'response' => $res['data'],
                    ];
                }
                $lastError = $res['message'] ?? $lastError;
            }
        }

        Log::error('AliExpress updateTitle failed', [
            'product_id' => $productId,
            'title_preview' => mb_substr($title, 0, 80),
            'error' => $lastError,
        ]);
        return ['success' => false, 'message' => $lastError];
    }

    private function callRestApi(string $method, array $businessParams, string $accessToken): array
    {
        $url = $this->apiBase . '/rest';

        $params = [
            'app_key' => $this->appKey,
            'method' => $method,
            'timestamp' => (string) round(microtime(true) * 1000),
            'sign_method' => 'sha256',
            'access_token' => $accessToken,
        ];
        $params = array_merge($params, $businessParams);
        $params['sign'] = $this->generateSignature($params);

        try {
            $response = Http::withoutVerifying()
                ->asForm()
                ->timeout($this->timeoutSeconds)
                ->post($url, $params);

            if ($response->failed()) {
                Log::error('AliExpress API HTTP failure', [
                    'method' => $method,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'message' => 'AliExpress API HTTP error: ' . $response->status(),
                    'raw' => $response->body(),
                ];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return [
                    'success' => false,
                    'message' => 'AliExpress API returned non-JSON response',
                    'raw' => $response->body(),
                ];
            }

            if ($this->hasApiError($data)) {
                $msg = $this->extractApiError($data);
                Log::error('AliExpress API business failure', [
                    'method' => $method,
                    'message' => $msg,
                    'response' => $data,
                ]);
                return ['success' => false, 'message' => $msg, 'data' => $data];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('AliExpress API exception', [
                'method' => $method,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateSignature(array $params): string
    {
        unset($params['sign']);
        ksort($params);
        $stringToSign = $this->appSecret;
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . $value;
            }
        }
        $stringToSign .= $this->appSecret;
        return strtoupper(hash_hmac('sha256', $stringToSign, $this->appSecret));
    }

    private function refreshAccessToken(string $refreshToken): ?string
    {
        $endpoints = [
            $this->apiBase . '/rest/auth/token/refresh',
            $this->apiBase . '/rest/auth/token/create',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::withoutVerifying()
                    ->asForm()
                    ->timeout($this->timeoutSeconds)
                    ->post($endpoint, [
                        'grant_type' => 'refresh_token',
                        'client_id' => $this->appKey,
                        'app_key' => $this->appKey,
                        'client_secret' => $this->appSecret,
                        'refresh_token' => $refreshToken,
                        'uuid' => (string) Str::uuid(),
                    ]);

                if ($response->failed()) {
                    Log::warning('AliExpress refresh token HTTP failure', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    continue;
                }

                $json = $response->json() ?? [];
                $newAccessToken = $json['access_token'] ?? ($json['result']['access_token'] ?? null);
                $newRefreshToken = $json['refresh_token'] ?? ($json['result']['refresh_token'] ?? null);
                $expiresIn = (int) ($json['expires_in'] ?? ($json['result']['expires_in'] ?? 3600));

                if (! empty($newAccessToken)) {
                    Cache::put('aliexpress_access_token', $newAccessToken, now()->addSeconds(max(60, $expiresIn - 60)));
                    if (! empty($newRefreshToken)) {
                        Cache::put('aliexpress_refresh_token', $newRefreshToken, now()->addDays(25));
                    }
                    Log::info('AliExpress access token refreshed', [
                        'endpoint' => $endpoint,
                        'expires_in' => $expiresIn,
                    ]);
                    return $newAccessToken;
                }
            } catch (\Throwable $e) {
                Log::warning('AliExpress refresh token exception', [
                    'endpoint' => $endpoint,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function hasApiError(array $response): bool
    {
        return isset($response['error_response']) ||
            isset($response['errorCode']) ||
            isset($response['error_code']) ||
            (isset($response['success']) && $response['success'] === false);
    }

    private function extractApiError(array $response): string
    {
        if (isset($response['error_response']) && is_array($response['error_response'])) {
            $error = $response['error_response'];
            return (string) ($error['msg'] ?? $error['sub_msg'] ?? $error['message'] ?? 'AliExpress API error');
        }
        return (string) ($response['message'] ?? $response['msg'] ?? $response['error_message'] ?? 'AliExpress API error');
    }
}
