<?php

namespace App\Services;

/**
 * Shopify Business 5Core store — uses BUSINESS_5CORE_SHOPIFY_* credentials.
 * Never reuse PLS / main-store tokens.
 */
class ShopifyB5CApiService extends ShopifyPLSApiService
{
    protected function plsDomain(): ?string
    {
        $domain = config('services.shopify_b5c.domain');
        if (! $domain) {
            return null;
        }

        return rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
    }

    protected function plsToken(bool $forceRefresh = false): ?string
    {
        $token = config('services.shopify_b5c.access_token') ?: config('services.shopify_b5c.password');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    protected function catalogStoreKey(): string
    {
        return 'b5c';
    }

    protected function storeDisplayName(): string
    {
        return 'Business 5Core Shopify';
    }
}
