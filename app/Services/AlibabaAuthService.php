<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Alibaba.com Open Platform OAuth.
 *
 * Docs: https://openapi.alibaba.com/doc/api.htm#/api?cid=4&path=/auth/token/create&method=GET|POST
 *
 * 1. Seller authorizes → callback receives ?code=
 * 2. IopClient POST {gateway}/auth/token/create with only `code` (do not send uuid).
 * 3. Optional refresh: POST /auth/token/refresh with `refresh_token`.
 *
 * Sign: HMAC-SHA256 of api name + sorted key/value, uppercase hex.
 * Gateway: https://openapi-api.alibaba.com/rest
 */
class AlibabaAuthService
{
    /**
     * @return list<string>
     */
    public function authorizeBases(): array
    {
        $configured = trim((string) (config('services.alibaba.auth_base') ?: ''));
        $bases = [
            $configured !== '' ? $configured : 'https://oauth.alibaba.com/authorize',
            'https://oauth.alibaba.com/authorize',
            'https://open-api.alibaba.com/oauth/authorize',
            'https://api.taobao.global/oauth/authorize',
        ];

        $out = [];
        foreach ($bases as $base) {
            if (
                str_contains($base, 'authorize.htm')
                || str_contains($base, 'auth.1688.com')
                || str_contains($base, 'auth.alibaba.com')
                || str_contains($base, 'aliexpress.com')
            ) {
                continue;
            }
            $base = rtrim($base, '?&');
            if ($base !== '' && ! in_array($base, $out, true)) {
                $out[] = $base;
            }
        }

        return $out !== [] ? $out : ['https://oauth.alibaba.com/authorize'];
    }

    public function getAuthorizeUrl(?string $state = null): string
    {
        $urls = $this->getAuthorizeUrls($state);

        return $urls[0] ?? $this->buildAuthorizeUrl('https://oauth.alibaba.com/authorize', $state);
    }

    /**
     * @return list<string>
     */
    public function getAuthorizeUrls(?string $state = null): array
    {
        $state = $state ?: bin2hex(random_bytes(8));

        return array_values(array_map(
            fn (string $base) => $this->buildAuthorizeUrl($base, $state),
            $this->authorizeBases()
        ));
    }

    public function buildAuthorizeUrl(string $authUrl, ?string $state = null): string
    {
        $query = [
            'response_type' => 'code',
            'client_id' => (string) config('services.alibaba.app_key'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state ?: bin2hex(random_bytes(8)),
        ];

        if (str_contains($authUrl, 'oauth.alibaba.com')) {
            // Official ICBU authorize URL:
            // response_type, client_id, redirect_uri, state, view=web, sp=ICBU
            $query['view'] = 'web';
            $query['sp'] = 'ICBU';
        }

        return $authUrl.'?'.http_build_query($query);
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        return $this->callTokenApi('/auth/token/create', [
            'code' => $code,
        ]);
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function refreshAccessToken(?string $refreshToken = null): array
    {
        $refreshToken = trim((string) ($refreshToken ?: config('services.alibaba.refresh_token') ?: ''));
        if ($refreshToken === '') {
            return ['success' => false, 'message' => 'ALIBABA_REFRESH_TOKEN missing. Re-authorize to get a new token.'];
        }

        return $this->callTokenApi('/auth/token/refresh', [
            'refresh_token' => $refreshToken,
        ]);
    }

    public function redirectUri(): string
    {
        try {
            $request = request();
            $host = (string) $request->getHost();
            if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
                return $request->getSchemeAndHttpHost().'/alibaba/callback';
            }
        } catch (\Throwable $e) {
            // console / no HTTP request
        }

        $redirect = trim((string) (config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', '')));
        if ($redirect === '' || str_ends_with(rtrim($redirect, '/'), '/index')) {
            $redirect = 'https://inventory.5coremanagement.com/alibaba/callback';
        }

        return $redirect;
    }

    /**
     * Official IopClient call. Business body is only `code` or `refresh_token`.
     * `uuid` is documented as invalid and is never sent.
     *
     * @param  array<string, string>  $business
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function callTokenApi(string $path, array $business): array
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');

        if ($appKey === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'ALIBABA_APP_KEY / ALIBABA_APP_SECRET missing.'];
        }

        $params = $this->tokenBusinessParams($path, $business);
        if ($params === []) {
            return [
                'success' => false,
                'message' => $path === '/auth/token/refresh'
                    ? 'Refresh token is required.'
                    : 'Authorization code is required.',
            ];
        }

        $last = ['success' => false, 'message' => 'Alibaba '.$path.' failed.'];

        foreach ($this->tokenGateways() as $gateway) {
            $parsed = $this->postOfficialToken($gateway, $path, $appKey, $appSecret, $params, true);
            if (! empty($parsed['success'])) {
                return $parsed;
            }

            if ($this->isSignatureError($parsed)) {
                $parsed = $this->postOfficialToken($gateway, $path, $appKey, $appSecret, $params, false);
                if (! empty($parsed['success'])) {
                    return $parsed;
                }
            }

            $last = $parsed;
            if (! $this->isRetryableGatewayMiss($parsed)) {
                break;
            }
        }

        Log::warning('Alibaba token API failed', [
            'path' => $path,
            'message' => $last['message'] ?? null,
        ]);

        return $last;
    }

    /**
     * @param  array<string, string>  $business
     * @return array<string, string>
     */
    protected function tokenBusinessParams(string $path, array $business): array
    {
        if ($path === '/auth/token/refresh') {
            $refresh = trim((string) ($business['refresh_token'] ?? ''));

            return $refresh === '' ? [] : ['refresh_token' => $refresh];
        }

        $code = trim((string) ($business['code'] ?? ''));

        return $code === '' ? [] : ['code' => $code];
    }

    /**
     * Documented call entry first. A one-time code is not sent to another host
     * after a real API error.
     *
     * @return list<string>
     */
    protected function tokenGateways(): array
    {
        $gateways = ['https://openapi-api.alibaba.com/rest'];

        $tokenUrl = trim((string) (config('services.alibaba.token_url') ?: ''));
        if ($tokenUrl !== '') {
            $trimmed = preg_replace('#/auth/token/(create|refresh)$#', '', $tokenUrl) ?: $tokenUrl;
            $gateways[] = rtrim((string) $trimmed, '/');
        }

        $rest = trim((string) (config('services.alibaba.rest_base') ?: ''));
        if ($rest !== '') {
            $gateways[] = rtrim($rest, '/');
        }

        $out = [];
        foreach ($gateways as $gateway) {
            if ($gateway !== '' && ! in_array($gateway, $out, true)) {
                $out[] = $gateway;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $business
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function postOfficialToken(string $gateway, string $path, string $appKey, string $appSecret, array $business, bool $splitSystemQuery): array
    {
        $signed = $this->signedTokenParams($path, $business, $appKey, $appSecret);
        $sign = $signed['sign'];
        unset($signed['sign']);

        $system = [
            'app_key' => $signed['app_key'],
            'sign_method' => $signed['sign_method'],
            'timestamp' => $signed['timestamp'],
            'sign' => $sign,
        ];

        $url = rtrim($gateway, '/').$path;
        if ($splitSystemQuery) {
            $url .= '?'.http_build_query($system);

            return $this->postForm($url, $business);
        }

        return $this->postForm($url, $signed + ['sign' => $sign]);
    }

    /**
     * @param  array<string, string>  $business
     * @return array<string, string>
     */
    protected function signedTokenParams(string $path, array $business, string $appKey, string $appSecret, ?int $timestampMs = null): array
    {
        $params = array_merge([
            'app_key' => $appKey,
            'sign_method' => 'sha256',
            'timestamp' => (string) ($timestampMs ?? (int) round(microtime(true) * 1000)),
        ], $business);
        unset($params['uuid'], $params['sign'], $params['method'], $params['grant_type'], $params['grantType']);

        $params['sign'] = $this->signIop($params, $path, $appSecret);

        return $params;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function isSignatureError(array $result): bool
    {
        $message = strtolower((string) ($result['message'] ?? ''));

        return $message !== '' && (str_contains($message, 'signature') || str_contains($message, 'sign'));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function isRetryableGatewayMiss(array $result): bool
    {
        $message = strtolower((string) ($result['message'] ?? ''));
        if (
            str_contains($message, 'could not reach')
            || str_contains($message, 'connection')
            || str_contains($message, 'timed out')
            || str_contains($message, 'curl error')
            || str_contains($message, '<html')
            || str_contains($message, 'not found')
        ) {
            return true;
        }

        $status = (int) ($result['http_status'] ?? 0);

        return in_array($status, [404, 405, 502, 503], true);
    }

    /**
     * @param  array<string, string>  $form
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function postForm(string $url, array $form): array
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['X-Protocol' => 'GOP'])
                ->connectTimeout(12)
                ->timeout(25)
                ->asForm()
                ->post($url, $form);
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'Could not reach '.$url.': '.$e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseTokenResponse($response);
        if (empty($parsed['success'])) {
            Log::info('Alibaba token endpoint miss', [
                'url' => $url,
                'status' => $response->status(),
                'message' => $parsed['message'] ?? null,
            ]);
        }

        return $parsed;
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function signIop(array $params, string $apiName, string $secret): string
    {
        unset($params['sign']);
        ksort($params);
        $source = $apiName;
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $source .= (string) $key.(string) $value;
            }
        }

        return strtoupper(hash_hmac('sha256', $source, $secret));
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, refresh_expires_in?: int, account?: string, message?: string}
     */
    protected function parseTokenResponse(\Illuminate\Http\Client\Response $response): array
    {
        $json = $response->json();
        $raw = is_array($json) ? $json : ($response->body() !== '' ? $response->body() : null);
        if (! is_array($json)) {
            $body = trim((string) $response->body());

            return [
                'success' => false,
                'message' => $body !== '' ? mb_substr($body, 0, 400) : 'Invalid token response from '.$response->effectiveUri(),
                'raw' => $raw,
                'http_status' => $response->status(),
            ];
        }

        $tokenResult = $json['token_result']
            ?? data_get($json, 'top_auth_token_create_response.token_result')
            ?? data_get($json, 'top_auth_token_refresh_response.token_result');
        if (is_string($tokenResult) && $tokenResult !== '') {
            $decoded = json_decode($tokenResult, true);
            if (is_array($decoded)) {
                $json = array_merge($json, $decoded);
            }
        } elseif (is_array($tokenResult)) {
            $json = array_merge($json, $tokenResult);
        }

        $access = $json['access_token']
            ?? data_get($json, 'result.access_token');
        $refresh = $json['refresh_token']
            ?? data_get($json, 'result.refresh_token');

        if (empty($access)) {
            $err = $json['error_response'] ?? null;
            $message = is_array($err)
                ? (string) ($err['sub_msg'] ?? $err['msg'] ?? $err['message'] ?? json_encode($err))
                : (string) (
                    $json['error_description']
                    ?? $json['error_msg']
                    ?? $json['message']
                    ?? $json['error']
                    ?? $json['error_code']
                    ?? $response->body()
                );

            return [
                'success' => false,
                'message' => $message !== '' ? mb_substr($message, 0, 400) : 'Token API returned no access_token.',
                'raw' => $json,
                'http_status' => $response->status(),
            ];
        }

        $expiresIn = $json['expires_in'] ?? $json['expire_time'] ?? null;
        $refreshExpiresIn = $json['refresh_expires_in'] ?? null;

        return [
            'success' => true,
            'access_token' => (string) $access,
            'refresh_token' => $refresh ? (string) $refresh : null,
            'expires_in' => $expiresIn !== null && is_numeric($expiresIn) ? (int) $expiresIn : null,
            'refresh_expires_in' => is_numeric($refreshExpiresIn) ? (int) $refreshExpiresIn : null,
            'account' => isset($json['account']) ? (string) $json['account'] : null,
            'account_id' => isset($json['account_id']) ? (string) $json['account_id'] : null,
            'account_platform' => isset($json['account_platform']) ? (string) $json['account_platform'] : null,
            'country' => isset($json['country']) ? (string) $json['country'] : null,
            'raw' => $json,
            'http_status' => $response->status(),
        ];
    }
}
