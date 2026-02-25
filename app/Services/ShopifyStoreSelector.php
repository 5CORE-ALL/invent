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
        return session('shopify_active_store', env('SHOPIFY_ACTIVE_STORE', 'business'));
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
