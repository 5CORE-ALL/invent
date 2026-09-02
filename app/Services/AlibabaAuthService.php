<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Alibaba.com Open Platform (openapi.alibaba.com) OAuth — AOP, not oauth.alibaba.com.
 *
 * Apps created at https://openapi.alibaba.com (short AppKeys like 502948) are
 * registered on gw.api.alibaba.com. Hitting oauth.alibaba.com returns
 * param-appkey.not.exists / appkey不存在.
 *
 * Authorize: https://gw.api.alibaba.com/auth/authorize.htm?client_id=&site=alibaba&redirect_uri=
 * Token:     https://gw.api.alibaba.com/openapi/param2/1/system.oauth2/getToken/{appKey}
 */
class AlibabaAuthService
{
    public function getAuthorizeUrl(?string $state = null): string
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');
        $redirect = $this->redirectUri();
        $state = $state ?: bin2hex(random_bytes(8));
        $site = (string) (config('services.alibaba.oauth_site') ?: 'alibaba');
        $authUrl = (string) (config('services.alibaba.auth_base') ?: 'https://gw.api.alibaba.com/auth/authorize.htm');

        // Never send this app to AliExpress/TOP oauth.alibaba.com — that registry
        // does not contain openapi.alibaba.com AppKeys.
        if (str_contains($authUrl, 'oauth.alibaba.com') || str_contains($authUrl, 'aliexpress.com')) {
            $authUrl = 'https://gw.api.alibaba.com/auth/authorize.htm';
        }

        $params = [
            'client_id' => $appKey,
            'site' => $site,
            'redirect_uri' => $redirect,
            'state' => $state,
        ];

        if (str_contains($authUrl, 'authorize.htm') && $appSecret !== '') {
            $path = (string) (parse_url($authUrl, PHP_URL_PATH) ?: '/auth/authorize.htm');
            $params['_aop_signature'] = $this->signAop($path, $params, $appSecret);
        }

        return $authUrl.'?'.http_build_query($params);
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        return $this->requestToken([
            'grant_type' => 'authorization_code',
            'need_refresh_token' => 'true',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
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

        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function redirectUri(): string
    {
        $redirect = (string) (config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', ''));
        if ($redirect === '') {
            $redirect = rtrim((string) config('app.url'), '/').'/index';
        }

        return $redirect;
    }

    /**
     * @param  array<string, string>  $extra
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function requestToken(array $extra): array
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');

        if ($appKey === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'ALIBABA_APP_KEY / ALIBABA_APP_SECRET missing.'];
        }

        $params = array_merge([
            'client_id' => $appKey,
            'client_secret' => $appSecret,
        ], $extra);

        $urls = [
            'https://gw.api.alibaba.com/openapi/param2/1/system.oauth2/getToken/'.$appKey,
            'https://gw.api.alibaba.com/openapi/http/1/system.oauth2/getToken/'.$appKey,
        ];

        $lastMessage = 'Alibaba token request failed.';
        foreach ($urls as $url) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
            $signed = $params;
            if ($path !== '') {
                $signed['_aop_signature'] = $this->signAop($path, $params, $appSecret);
            }

            foreach ([$signed, $params] as $body) {
                $response = Http::withoutVerifying()->asForm()->post($url, $body);
                $parsed = $this->parseTokenResponse($response);
                if (! empty($parsed['success'])) {
                    return $parsed;
                }
                $lastMessage = $parsed['message'] ?? $lastMessage;
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * AOP HMAC-SHA1: path + sorted key+value (no _aop_signature), uppercase hex.
     *
     * @param  array<string, string>  $params
     */
    protected function signAop(string $path, array $params, string $secret): string
    {
        unset($params['_aop_signature']);
        ksort($params);
        $source = $path;
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $source .= (string) $key.(string) $value;
            }
        }

        return strtoupper(bin2hex(hash_hmac('sha1', $source, $secret, true)));
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    protected function parseTokenResponse(\Illuminate\Http\Client\Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return [
                'success' => false,
                'message' => $response->body() !== '' ? $response->body() : 'Invalid token response.',
            ];
        }

        $access = $json['access_token']
            ?? data_get($json, 'token_result.access_token')
            ?? data_get($json, 'result.access_token');
        $refresh = $json['refresh_token']
            ?? data_get($json, 'token_result.refresh_token')
            ?? data_get($json, 'result.refresh_token');

        if (empty($access)) {
            return [
                'success' => false,
                'message' => (string) (
                    $json['error_description']
                    ?? $json['error_msg']
                    ?? $json['message']
                    ?? $json['error']
                    ?? $json['error_code']
                    ?? $response->body()
                ),
            ];
        }

        $expiresIn = $json['expires_in']
            ?? data_get($json, 'token_result.expires_in')
            ?? data_get($json, 'result.expires_in');

        return [
            'success' => true,
            'access_token' => (string) $access,
            'refresh_token' => $refresh ? (string) $refresh : null,
            'expires_in' => $expiresIn !== null ? (int) $expiresIn : null,
        ];
    }
}
