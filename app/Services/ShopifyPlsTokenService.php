<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProLightSounds (PLS) Shopify access token — client_credentials tokens expire ~24h.
 */
class ShopifyPlsTokenService
{
    private const CACHE_KEY = 'shopify_pls_access_token';

    public function getDomain(): ?string
    {
        $domain = config('services.prolightsounds.domain')
            ?? config('services.prolightsounds.store_url');

        if (! $domain) {
            return null;
        }

        return rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
    }

    public function getAccessToken(bool $forceRefresh = false): ?string
    {
        if ($forceRefresh) {
            $this->clearCache();
        } else {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        if ($this->hasClientCredentials()) {
            return $this->refreshAccessToken();
        }

        return config('services.prolightsounds.password')
            ?? config('services.prolightsounds.access_token');
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->getDomain() && $this->getAccessToken());
    }

    private function hasClientCredentials(): bool
    {
        $clientId = config('services.prolightsounds.client_id');
        $clientSecret = config('services.prolightsounds.client_secret');

        return ! empty($clientId) && ! empty($clientSecret);
    }

    private function refreshAccessToken(): ?string
    {
        $lock = Cache::lock('shopify_pls_token_refresh', 30);

        try {
            return $lock->block(15, function () {
                $cached = Cache::get(self::CACHE_KEY);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }

                $domain = $this->getDomain();
                $clientId = config('services.prolightsounds.client_id');
                $clientSecret = config('services.prolightsounds.client_secret');

                if (! $domain || ! $clientId || ! $clientSecret) {
                    return config('services.prolightsounds.password')
                        ?? config('services.prolightsounds.access_token');
                }

                $response = Http::asForm()->timeout(30)->post(
                    "https://{$domain}/admin/oauth/access_token",
                    [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                    ]
                );

                if (! $response->successful()) {
                    Log::error('Shopify PLS token refresh failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return config('services.prolightsounds.password')
                        ?? config('services.prolightsounds.access_token');
                }

                $data = $response->json();
                $token = $data['access_token'] ?? null;
                $expiresIn = (int) ($data['expires_in'] ?? 86399);

                if (! is_string($token) || $token === '') {
                    return null;
                }

                $ttl = max(60, $expiresIn - 300);
                Cache::put(self::CACHE_KEY, $token, $ttl);

                Log::info('Shopify PLS access token refreshed', [
                    'expires_in' => $expiresIn,
                    'cached_for_seconds' => $ttl,
                ]);

                return $token;
            });
        } catch (\Throwable $e) {
            Log::error('Shopify PLS token refresh lock failed', ['error' => $e->getMessage()]);

            return config('services.prolightsounds.password')
                ?? config('services.prolightsounds.access_token');
        }
    }

    /**
     * @return array{connected: bool, shop: ?string, domain: ?string, message: string}
     */
    public function pingShopCached(int $ttlSeconds = 600): array
    {
        $key = 'mm.shopify.pls.api.ping.v1';
        try {
            $cached = Cache::get($key);
            if (is_array($cached) && array_key_exists('connected', $cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $result = $this->pingShop();
        $ttl = ! empty($result['connected'])
            ? max(30, $ttlSeconds)
            : 45;
        try {
            Cache::put($key, $result, now()->addSeconds($ttl));
        } catch (\Throwable $e) {
            // ignore
        }

        return $result;
    }

    /**
     * @return array{connected: bool, shop: ?string, domain: ?string, message: string}
     */
    public function pingShop(): array
    {
        $domain = $this->getDomain();
        $token = $this->getAccessToken();
        if (! $domain || ! $token) {
            return [
                'connected' => false,
                'shop' => null,
                'domain' => $domain,
                'message' => 'PLS Shopify credentials missing.',
            ];
        }

        $host = preg_replace('#^https?://#', '', (string) $domain);
        $host = rtrim((string) $host, '/');

        $response = $this->requestShop($host, $token);
        if ($response && in_array($response->status(), [401, 403], true) && $this->hasClientCredentials()) {
            $token = $this->getAccessToken(true);
            if (is_string($token) && $token !== '') {
                $response = $this->requestShop($host, $token);
            }
        }

        if ($response === null) {
            return [
                'connected' => false,
                'shop' => null,
                'domain' => $host,
                'message' => 'PLS API request failed.',
            ];
        }

        if (! $response->successful()) {
            return [
                'connected' => false,
                'shop' => null,
                'domain' => $host,
                'message' => 'PLS API HTTP '.$response->status(),
            ];
        }

        $shop = $response->json('shop') ?? [];
        $name = trim((string) ($shop['name'] ?? ''));
        $myshopify = trim((string) ($shop['myshopify_domain'] ?? $host));

        return [
            'connected' => true,
            'shop' => $name !== '' ? $name : $myshopify,
            'domain' => $myshopify,
            'message' => 'PLS API connected'.($name !== '' ? ' — '.$name : '').' ('.$myshopify.')',
        ];
    }

    private function requestShop(string $host, string $token): ?\Illuminate\Http\Client\Response
    {
        try {
            $request = Http::withHeaders(['X-Shopify-Access-Token' => $token])->timeout(20);
            if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }

            return $request->get("https://{$host}/admin/api/2024-01/shop.json");
        } catch (\Throwable $e) {
            Log::warning('Shopify PLS shop ping failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
