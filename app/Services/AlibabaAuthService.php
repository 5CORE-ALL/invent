<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Alibaba OAuth — obtain access token for Open Platform API calls.
 *
 * Uses the same authorize/token endpoints pattern as AliExpress when
 * ALIBABA credentials are configured. Adjust host via config if needed.
 */
class AlibabaAuthService
{
    public function getAuthorizeUrl(?string $state = null): string
    {
        $appKey = (string) config('services.alibaba.app_key');
        $redirect = urlencode((string) (config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', config('app.url'))));
        $state = $state ?: bin2hex(random_bytes(8));
        $authBase = rtrim((string) (config('services.alibaba.auth_base') ?: 'https://api-sg.aliexpress.com'), '/');

        return $authBase.'/oauth/authorize?response_type=code&force_auth=true'
            .'&redirect_uri='.$redirect
            .'&client_id='.$appKey
            .'&state='.$state;
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $appKey = (string) config('services.alibaba.app_key');
        $appSecret = (string) config('services.alibaba.app_secret');

        if ($appKey === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'ALIBABA_APP_KEY / ALIBABA_APP_SECRET missing.'];
        }

        $tokenUrl = (string) (config('services.alibaba.token_url') ?: 'https://api-sg.aliexpress.com/auth/token/create');

        $response = Http::withoutVerifying()->asForm()->post($tokenUrl, [
            'code' => $code,
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.alibaba.redirect_uri') ?: env('ALIBABA_REDIRECT_URI', config('app.url')),
        ]);

        $json = $response->json();
        if (! $response->successful() || empty($json['access_token'])) {
            return [
                'success' => false,
                'message' => is_array($json) ? ($json['error_description'] ?? $json['message'] ?? $response->body()) : $response->body(),
            ];
        }

        return [
            'success' => true,
            'access_token' => (string) $json['access_token'],
            'refresh_token' => $json['refresh_token'] ?? null,
            'expires_in' => isset($json['expires_in']) ? (int) $json['expires_in'] : null,
        ];
    }
}
