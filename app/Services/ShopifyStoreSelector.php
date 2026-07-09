<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ShopifyStoreSelector
{
    protected array $stores = [
        'prolightsounds' => [
            'api_key' => 'PROLIGHTSOUNDS_SHOPIFY_API_KEY',
            'password' => 'PROLIGHTSOUNDS_SHOPIFY_PASSWORD',
            'store_url' => 'PROLIGHTSOUNDS_SHOPIFY_DOMAIN',
        ],
        'main' => [
            'api_key' => 'SHOPIFY_API_KEY',
            'password' => 'SHOPIFY_PASSWORD',
            'store_url' => 'SHOPIFY_STORE_URL',
        ],
        '5core' => [
            'api_key' => 'SHOPIFY_5CORE_API_KEY',
            'password' => 'SHOPIFY_5CORE_PASSWORD',
            'store_url' => 'SHOPIFY_5CORE_DOMAIN',
        ],
        'business' => [
            'api_key' => 'BUSINESS_5CORE_SHOPIFY_API_KEY',
            'password' => 'BUSINESS_5CORE_SHOPIFY_ACCESS_TOKEN',
            'store_url' => 'BUSINESS_5CORE_SHOPIFY_DOMAIN',
        ],
    ];

    public function getActiveStore(): string
    {
        if ($this->forcedStore !== null && $this->forcedStore !== '') {
            return $this->forcedStore;
        }

        $fromSession = session('shopify_active_store');
        if (is_string($fromSession) && $fromSession !== '') {
            return $fromSession;
        }

        return (string) env('SHOPIFY_ACTIVE_STORE', 'business');
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    public function getConfigForStore(?string $storeKey = null): array
    {
        $store = $storeKey ?: $this->getActiveStore();

        if ($store === 'main') {
            $url = (string) config('services.shopify.store_url');
            $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password'));

            return [
                'store_url' => $this->normalizeStoreUrl($url),
                'token' => $token,
                'store_key' => 'main',
            ];
        }

        $map = $this->stores[$store] ?? null;
        if ($map === null) {
            return $this->getConfigForStore('main');
        }

        $url = (string) env($map['store_url'], '');
        $token = (string) env($map['password'], '');

        if ($url === '' || $token === '') {
            return $this->getConfigForStore('main');
        }

        return [
            'store_url' => $this->normalizeStoreUrl($url),
            'token' => $token,
            'store_key' => $store,
        ];
    }

    public function useStore(string $storeKey): self
    {
        $clone = clone $this;
        $clone->forcedStore = $storeKey;

        return $clone;
    }

    protected ?string $forcedStore = null;

    protected function normalizeStoreUrl(string $url): string
    {
        return str_replace(['https://', 'http://'], '', trim($url));
    }

    public function getApiKey(): string
    {
        $store = $this->getActiveStore();
        $key = $this->stores[$store]['api_key'] ?? 'SHOPIFY_API_KEY';
        return env($key, '');
    }

    public function getPassword(): string
    {
        $store = $this->getActiveStore();
        $key = $this->stores[$store]['password'] ?? 'SHOPIFY_PASSWORD';
        return env($key, '');
    }

    public function getStoreUrl(): string
    {
        $store = $this->getActiveStore();
        $key = $this->stores[$store]['store_url'] ?? 'SHOPIFY_STORE_URL';
        return env($key, '');
    }
}
