<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Alibaba.com Open Platform OAuth.
 *
 * Docs: https://openapi.alibaba.com/doc/api.htm#/api?cid=4&path=/auth/token/create
 *
 * 1. Seller authorizes → callback receives ?code=
 * 2. Exchange code with signed IOP POST /auth/token/create (no existing access_token).
 * 3. Optional refresh: POST /auth/token/refresh
 *
 * Official authorize hosts: oauth.alibaba.com (sp=icbu), open-api.alibaba.com, api.taobao.global.
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
            'force_auth' => 'true',
        ];

        if (str_contains($authUrl, 'oauth.alibaba.com')) {
            $query['sp'] = 'icbu';
            $query['view'] = 'web';
            $query['force_login'] = 'true';
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
        $redirect = trim((string) (config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', '')));
        if ($redirect === '' || str_ends_with(rtrim($redirect, '/'), '/index')) {
            $redirect = 'https://inventory.5coremanagement.com/alibaba/callback';
        }

        return $redirect;
    }

    /**
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

        $lastMessage = 'Alibaba '.$path.' failed.';

        foreach ($this->iopRestBases() as $rest) {
            foreach ($this->iopBusinessVariants($business) as $params) {
                $parsed = $this->postSignedIop($rest, $path, $appKey, $appSecret, $params);
                if (! empty($parsed['success'])) {
                    return $parsed;
                }
                $lastMessage = $parsed['message'] ?? $lastMessage;
            }

            $direct = $this->postSignedIop(rtrim($rest, '/').$path, $path, $appKey, $appSecret, $business, false);
            if (! empty($direct['success'])) {
                return $direct;
            }
            $lastMessage = $direct['message'] ?? $lastMessage;
        }

        $form = array_merge([
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'grant_type' => isset($business['refresh_token']) ? 'refresh_token' : 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ], $business);

        foreach ($this->simpleTokenUrls($path) as $url) {
            $parsed = $this->postForm($url, $form);
            if (! empty($parsed['success'])) {
                return $parsed;
            }
            $lastMessage = $parsed['message'] ?? $lastMessage;
        }

        $top = $this->postTopAuthTokenCreate($appKey, $appSecret, $business);
        if (! empty($top['success'])) {
            return $top;
        }

        Log::warning('Alibaba token API failed', [
            'path' => $path,
            'message' => $top['message'] ?? $lastMessage,
        ]);

        return ['success' => false, 'message' => $top['message'] ?? $lastMessage];
    }

    /**
     * @return list<string>
     */
    protected function iopRestBases(): array
    {
        $configured = trim((string) (config('services.alibaba.rest_base') ?: ''));

        return array_values(array_unique(array_filter([
            $configured !== '' ? rtrim($configured, '/') : null,
            'https://open-api.alibaba.com/rest',
            'https://api.taobao.global/rest',
            'https://api-sg.alibaba.com/rest',
            'https://openapi.alibaba.com/rest',
        ])));
    }

    /**
     * @return list<string>
     */
    protected function simpleTokenUrls(string $path): array
    {
        $leaf = $path === '/auth/token/refresh' ? 'refresh' : 'create';

        return [
            'https://open-api.alibaba.com/rest/auth/token/'.$leaf,
            'https://api.taobao.global/rest/auth/token/'.$leaf,
            'https://api-sg.alibaba.com/auth/token/'.$leaf,
            'https://openapi.alibaba.com/auth/token/'.$leaf,
            'https://oauth.alibaba.com/token',
        ];
    }

    /**
     * Official IopRequest only sends `code` (and optionally grantType).
     *
     * @param  array<string, string>  $business
     * @return list<array<string, string>>
     */
    protected function iopBusinessVariants(array $business): array
    {
        $core = array_filter([
            'code' => $business['code'] ?? null,
            'refresh_token' => $business['refresh_token'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $withGrant = $core;
        if (isset($core['code'])) {
            $withGrant['grantType'] = 'authorization_code';
        }

        return isset($core['code']) ? [$core, $withGrant] : [$core];
    }

    /**
     * @param  array<string, string>  $business
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function postSignedIop(string $url, string $method, string $appKey, string $appSecret, array $business, bool $includeMethod = true): array
    {
        $params = array_merge([
            'app_key' => $appKey,
            'sign_method' => 'sha256',
            'timestamp' => (string) (int) round(microtime(true) * 1000),
        ], $business);

        if ($includeMethod) {
            $params['method'] = $method;
        }

        $params['sign'] = $this->signIop($params, $method, $appSecret);

        return $this->postForm($url, $params);
    }

    /**
     * Official ICBU step 3: taobao.top.auth.token.create on the TOP router.
     *
     * @param  array<string, string>  $business
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function postTopAuthTokenCreate(string $appKey, string $appSecret, array $business): array
    {
        $method = isset($business['refresh_token'])
            ? 'taobao.top.auth.token.refresh'
            : 'taobao.top.auth.token.create';

        $params = [
            'method' => $method,
            'app_key' => $appKey,
            'timestamp' => gmdate('Y-m-d H:i:s', time() + 8 * 3600),
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => 'md5',
        ];
        if (isset($business['code'])) {
            $params['code'] = $business['code'];
        }
        if (isset($business['refresh_token'])) {
            $params['refresh_token'] = $business['refresh_token'];
        }

        $params['sign'] = $this->signTopMd5($params, $appSecret);

        $last = ['success' => false, 'message' => 'TOP token create failed.'];
        foreach ([
            'https://api.taobao.com/router/rest',
            'https://gw.api.taobao.com/router/rest',
        ] as $url) {
            $last = $this->postForm($url, $params);
            if (! empty($last['success'])) {
                return $last;
            }
        }

        return $last;
    }

    /**
     * @param  array<string, string>  $form
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function postForm(string $url, array $form): array
    {
        try {
            $response = Http::withoutVerifying()
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
     * @param  array<string, string>  $params
     */
    protected function signTopMd5(array $params, string $secret): string
    {
        unset($params['sign']);
        ksort($params);
        $source = $secret;
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $source .= (string) $key.(string) $value;
            }
        }
        $source .= $secret;

        return strtoupper(md5($source));
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function parseTokenResponse(\Illuminate\Http\Client\Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $body = trim((string) $response->body());

            return [
                'success' => false,
                'message' => $body !== '' ? mb_substr($body, 0, 400) : 'Invalid token response from '.$response->effectiveUri(),
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

            return ['success' => false, 'message' => $message !== '' ? mb_substr($message, 0, 400) : 'Token API returned no access_token.'];
        }

        $expiresIn = $json['expires_in'] ?? $json['expire_time'] ?? null;

        return [
            'success' => true,
            'access_token' => (string) $access,
            'refresh_token' => $refresh ? (string) $refresh : null,
            'expires_in' => $expiresIn !== null ? (int) $expiresIn : null,
        ];
    }
}
