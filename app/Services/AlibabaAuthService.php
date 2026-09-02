<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Alibaba.com (ICBU) OAuth — not AliExpress.
 *
 * Docs: https://developer.alibaba.com/docs/doc.htm?articleId=118416
 * 1. Open authorize URL (seller logs in on Alibaba.com).
 * 2. Exchange ?code= from redirect for access_token via oauth.alibaba.com/token.
 */
class AlibabaAuthService
{
    public function getAuthorizeUrl(?string $state = null): string
    {
        $appKey = (string) config('services.alibaba.app_key');
        $redirect = (string) (config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', config('app.url')));
        $state = $state ?: bin2hex(random_bytes(8));
        $authBase = rtrim((string) (config('services.alibaba.auth_base') ?: 'https://oauth.alibaba.com'), '/');

        // Prefer Alibaba.com ICBU authorize endpoint.
        if (str_contains($authBase, 'aliexpress.com')) {
            $authBase = 'https://oauth.alibaba.com';
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $appKey,
            'redirect_uri' => $redirect,
            'state' => $state,
            'view' => 'web',
            'sp' => 'ICBU',
        ]);

        return $authBase.'/authorize?'.$query;
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');
        $redirect = (string) (config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', config('app.url')));

        if ($appKey === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'ALIBABA_APP_KEY / ALIBABA_APP_SECRET missing.'];
        }

        $tokenUrl = (string) (config('services.alibaba.token_url') ?: 'https://oauth.alibaba.com/token');
        if (str_contains($tokenUrl, 'aliexpress.com')) {
            $tokenUrl = 'https://oauth.alibaba.com/token';
        }

        $response = Http::withoutVerifying()->asForm()->post($tokenUrl, [
            'grant_type' => 'authorization_code',
            'need_refresh_token' => 'true',
            'code' => $code,
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirect,
            'sp' => 'icbu',
        ]);

        $json = $response->json();
        if (! is_array($json)) {
            return [
                'success' => false,
                'message' => $response->body() !== '' ? $response->body() : 'Invalid token response.',
            ];
        }

        // Alibaba may nest token fields.
        $access = $json['access_token']
            ?? data_get($json, 'token_result.access_token')
            ?? data_get($json, 'result.access_token');
        $refresh = $json['refresh_token']
            ?? data_get($json, 'token_result.refresh_token')
            ?? data_get($json, 'result.refresh_token');

        if (! $response->successful() || empty($access)) {
            return [
                'success' => false,
                'message' => (string) (
                    $json['error_description']
                    ?? $json['error_msg']
                    ?? $json['message']
                    ?? $json['error']
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

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function refreshAccessToken(?string $refreshToken = null): array
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');
        $refreshToken = trim((string) ($refreshToken ?: config('services.alibaba.refresh_token') ?: ''));

        if ($appKey === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'ALIBABA_APP_KEY / ALIBABA_APP_SECRET missing.'];
        }
        if ($refreshToken === '') {
            return ['success' => false, 'message' => 'ALIBABA_REFRESH_TOKEN missing. Re-authorize to get a new token.'];
        }

        $tokenUrl = (string) (config('services.alibaba.token_url') ?: 'https://oauth.alibaba.com/token');
        if (str_contains($tokenUrl, 'aliexpress.com')) {
            $tokenUrl = 'https://oauth.alibaba.com/token';
        }

        $response = Http::withoutVerifying()->asForm()->post($tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'refresh_token' => $refreshToken,
            'sp' => 'icbu',
        ]);

        $json = $response->json();
        if (! is_array($json)) {
            return [
                'success' => false,
                'message' => $response->body() !== '' ? $response->body() : 'Invalid refresh response.',
            ];
        }

        $access = $json['access_token']
            ?? data_get($json, 'token_result.access_token')
            ?? data_get($json, 'result.access_token');
        $refresh = $json['refresh_token']
            ?? data_get($json, 'token_result.refresh_token')
            ?? data_get($json, 'result.refresh_token')
            ?? $refreshToken;

        if (! $response->successful() || empty($access)) {
            return [
                'success' => false,
                'message' => (string) (
                    $json['error_description']
                    ?? $json['error_msg']
                    ?? $json['message']
                    ?? $json['error']
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
