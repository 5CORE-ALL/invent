<?php

namespace App\Services;

use App\Models\ShopifyCatalogVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sets a Shopify variant price on the GraphQL Admin API.
 *
 * REST Admin is a shared 2-calls-per-second bucket. Inventory crawls fill it,
 * so a price push waited and then retried. GraphQL has its own point budget,
 * so a price change does not line up behind those crawls.
 */
class ShopifyVariantPriceUpdater
{
    public function update(string $variantId, float $newPrice, string $store = 'b2c'): array
    {
        $variantId = $this->numericId($variantId);
        $price = number_format($newPrice, 2, '.', '');
        if ($variantId === '' || (float) $price <= 0) {
            return ['status' => 'error', 'message' => 'Variant id and price are required'];
        }

        [$storeUrl, $token, $storeName, $catalogStore] = $this->credentials($store);
        if ($storeUrl === '' || $token === '') {
            return ['status' => 'error', 'message' => $storeName.' credentials not configured'];
        }

        try {
            $graphql = $this->updateViaGraphql($storeUrl, $token, $storeName, $catalogStore, $variantId, $price);
            if (($graphql['status'] ?? '') === 'success' || ! ($graphql['fallback'] ?? false)) {
                unset($graphql['fallback']);

                return $graphql;
            }
        } catch (\Throwable $e) {
            Log::warning($storeName.' GraphQL price update failed, using REST once', [
                'variant_id' => $variantId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->updateViaRest($storeUrl, $token, $storeName, $store, $variantId, $price);
    }

    /**
     * @return array{status: string, message?: string, verified_price?: float, data?: mixed, fallback?: bool}
     */
    private function updateViaGraphql(
        string $storeUrl,
        string $token,
        string $storeName,
        string $catalogStore,
        string $variantId,
        string $price
    ): array {
        $productId = $this->catalogProductId($catalogStore, $variantId);
        if ($productId === '') {
            $lookupQuery = 'query VariantProduct($id: ID!) { productVariant(id: $id) { product { id } } }';
            $lookedUp = $this->graphql($storeUrl, $token, $lookupQuery, [
                'id' => 'gid://shopify/ProductVariant/'.$variantId,
            ]);

            if ($this->isThrottled($lookedUp)) {
                usleep((int) ($this->throttleSeconds($lookedUp) * 1_000_000));
                $lookedUp = $this->graphql($storeUrl, $token, $lookupQuery, [
                    'id' => 'gid://shopify/ProductVariant/'.$variantId,
                ]);
            }

            $productId = $this->numericId((string) data_get($lookedUp, 'data.productVariant.product.id'));
            if ($productId === '') {
                return [
                    'status' => 'error',
                    'message' => $this->graphqlError($lookedUp) ?: 'Shopify variant was not found',
                    'fallback' => $lookedUp === null,
                ];
            }
        }

        $mutation = 'mutation PricePush($productId: ID!, $variants: [ProductVariantsBulkInput!]!) { productVariantsBulkUpdate(productId: $productId, variants: $variants) { productVariants { id price } userErrors { field message } } }';
        $variables = [
            'productId' => 'gid://shopify/Product/'.$productId,
            'variants' => [[
                'id' => 'gid://shopify/ProductVariant/'.$variantId,
                'price' => $price,
            ]],
        ];

        $json = $this->graphql($storeUrl, $token, $mutation, $variables);
        if ($this->isThrottled($json)) {
            usleep((int) ($this->throttleSeconds($json) * 1_000_000));
            $json = $this->graphql($storeUrl, $token, $mutation, $variables);
        }

        if ($json === null) {
            return ['status' => 'error', 'message' => 'Shopify GraphQL price update failed', 'fallback' => true];
        }

        $userErrors = data_get($json, 'data.productVariantsBulkUpdate.userErrors', []);
        if (is_array($userErrors) && $userErrors !== []) {
            $message = (string) ($userErrors[0]['message'] ?? 'Shopify rejected the price');

            return ['status' => 'error', 'message' => $message];
        }

        $updated = data_get($json, 'data.productVariantsBulkUpdate.productVariants.0.price');
        $verified = is_numeric($updated) ? number_format((float) $updated, 2, '.', '') : '';
        if ($verified !== $price) {
            $graphqlError = $this->graphqlError($json);
            if ($graphqlError !== '') {
                return ['status' => 'error', 'message' => $graphqlError, 'fallback' => true];
            }

            return [
                'status' => 'error',
                'message' => 'Price update verification failed - price mismatch in API response',
                'expected_price' => (float) $price,
                'actual_price' => $verified !== '' ? (float) $verified : null,
            ];
        }

        Log::info($storeName.' GraphQL price updated and verified', [
            'variant_id' => $variantId,
            'verified_price' => (float) $verified,
        ]);

        return [
            'status' => 'success',
            'verified_price' => (float) $verified,
            'data' => $json,
        ];
    }

    /**
     * Last resort when GraphQL cannot be reached. Two tries, not eight.
     *
     * @return array{status: string, message?: string, verified_price?: float, code?: int}
     */
    private function updateViaRest(
        string $storeUrl,
        string $token,
        string $storeName,
        string $store,
        string $variantId,
        string $price
    ): array {
        $url = rtrim($storeUrl, '/').'/admin/api/2025-01/variants/'.$variantId.'.json';
        $payload = ['variant' => ['id' => $variantId, 'price' => $price]];
        $response = null;
        $body = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            ShopifyAdminCallGate::acquire($store);
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(20)->connectTimeout(10)->put($url, $payload);
            ShopifyAdminCallGate::record($response, $store);
            $body = $response->json();
            if (! ShopifyAdminCallGate::isRateLimited($response)) {
                break;
            }
            usleep(1_500_000);
        }

        if ($response && $response->successful()) {
            $updated = data_get($body, 'variant.price');
            $verified = is_numeric($updated) ? number_format((float) $updated, 2, '.', '') : '';
            if ($verified === $price) {
                return ['status' => 'success', 'verified_price' => (float) $verified, 'data' => $body];
            }

            return [
                'status' => 'error',
                'message' => 'Price update verification failed - price mismatch in API response',
                'expected_price' => (float) $price,
                'actual_price' => $verified !== '' ? (float) $verified : null,
            ];
        }

        $message = 'API returned error';
        $errors = is_array($body) ? ($body['errors'] ?? $body['error'] ?? null) : null;
        if ($errors !== null) {
            $message = is_scalar($errors) ? (string) $errors : json_encode($errors);
        }

        Log::error($storeName.' REST price update failed', [
            'variant_id' => $variantId,
            'status_code' => $response ? $response->status() : null,
            'error' => $message,
        ]);

        return [
            'status' => 'error',
            'code' => $response ? $response->status() : 0,
            'message' => $message,
        ];
    }

    private function graphql(string $storeUrl, string $token, string $query, array $variables): ?array
    {
        $url = rtrim($storeUrl, '/').'/admin/api/2025-01/graphql.json';
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(20)->connectTimeout(10)->post($url, [
                'query' => $query,
                'variables' => $variables,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Shopify GraphQL price request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Shopify GraphQL price HTTP error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function catalogProductId(string $catalogStore, string $variantId): string
    {
        try {
            if (! Schema::hasTable('shopify_catalog_variants')) {
                return '';
            }
            $productId = ShopifyCatalogVariant::query()
                ->where('store', $catalogStore)
                ->where('shopify_variant_id', $variantId)
                ->value('shopify_product_id');

            return $this->numericId((string) $productId);
        } catch (\Throwable) {
            return '';
        }
    }

    private function isThrottled(?array $json): bool
    {
        if ($json === null) {
            return false;
        }
        foreach ($json['errors'] ?? [] as $error) {
            if (! is_array($error)) {
                continue;
            }
            $code = strtoupper((string) ($error['extensions']['code'] ?? ''));
            if ($code === 'THROTTLED' || strcasecmp((string) ($error['message'] ?? ''), 'Throttled') === 0) {
                return true;
            }
        }

        return false;
    }

    private function throttleSeconds(?array $json): float
    {
        $requested = (float) data_get($json, 'extensions.cost.requestedQueryCost', 10);
        $available = (float) data_get($json, 'extensions.cost.throttleStatus.currentlyAvailable', 0);
        $restore = (float) data_get($json, 'extensions.cost.throttleStatus.restoreRate', 50);
        if ($restore <= 0) {
            $restore = 50;
        }
        $need = max(0, $requested - $available);

        return min(3.0, max(0.5, $need / $restore));
    }

    private function graphqlError(?array $json): string
    {
        $message = data_get($json, 'errors.0.message');

        return is_string($message) ? $message : '';
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function credentials(string $store): array
    {
        if ($store === 'pls' || $store === 'prolightsounds') {
            $domain = (string) config('services.prolightsounds_shopify.store_url');
            $token = (string) app(ShopifyPlsTokenService::class)->getAccessToken();

            return [$this->storeUrl($domain), $token, 'ProLightSounds', 'pls'];
        }

        $domain = (string) config('services.shopify.store_url');
        $token = (string) (config('services.shopify.password') ?: config('services.shopify.access_token'));

        return [$this->storeUrl($domain), $token, 'Shopify B2C', 'main'];
    }

    private function storeUrl(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }
        if (! str_starts_with($domain, 'http')) {
            $domain = 'https://'.$domain;
        }

        return rtrim($domain, '/');
    }

    private function numericId(string $id): string
    {
        if (preg_match('/(\d+)\s*$/', trim($id), $m)) {
            return $m[1];
        }

        return '';
    }
}
