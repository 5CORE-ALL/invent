<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * AliExpress OAuth — obtain session token for /sync API calls.
 *
 * 1. Open authorize URL in browser (seller logs in).
 * 2. Exchange ?code= from redirect for access_token via /auth/token/create.
 */
class AliExpressAuthService
{
    public function getAuthorizeUrl(?string $state = null): string
    {
        $appKey = (string) config('services.aliexpress.app_key');
        $redirect = urlencode((string) (config('services.aliexpress.redirect_uri') ?: env('ALIEXPRESS_REDIRECT_URI', config('app.url'))));
        $state = $state ?: bin2hex(random_bytes(8));

        return 'https://api-sg.aliexpress.com/oauth/authorize?response_type=code&force_auth=true'
            .'&redirect_uri='.$redirect
            .'&client_id='.$appKey
            .'&state='.$state;
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, message?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $appKey = (string) config('services.aliexpress.app_key');
        $appSecret = (string) config('services.aliexpress.app_secret');

        if ($appKey === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'ALIEXPRESS_APP_KEY / ALIEXPRESS_APP_SECRET missing.'];
        }

        $response = Http::withoutVerifying()->asForm()->post('https://api-sg.aliexpress.com/auth/token/create', [
            'code' => $code,
            'client_id' => $appKey,
            'client_secret' => $appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.aliexpress.redirect_uri') ?: env('ALIEXPRESS_REDIRECT_URI', config('app.url')),
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
