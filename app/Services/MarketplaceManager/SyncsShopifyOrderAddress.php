<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared Shopify order/customer address write helpers for MM push services.
 *
 * Host class must expose:
 * - public ?string $lastFailureReason
 * - public ?int $lastApiStatus
 * - protected function shopifyConfig(): array
 */
trait SyncsShopifyOrderAddress
{
    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $payload
     */
    protected function putShopifyOrder(array $config, string $shopifyOrderId, array $payload): bool
    {
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json';
        $maxAttempts = 5;
        $backoff = [2, 4, 8, 16, 30];

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $config['token'],
                    'Content-Type' => 'application/json',
                ])->timeout(60)->put($url, $payload);

                $this->lastApiStatus = $response->status();

                if ($response->successful()) {
                    return true;
                }

                $retryable = $response->status() === 429 || $response->status() >= 500;
                if ($retryable && $attempt < $maxAttempts) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 0);
                    $wait = max($retryAfter, $backoff[$attempt - 1] ?? 30);
                    sleep($wait);

                    continue;
                }

                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error(static::class.': Shopify order address update failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return false;
            }

            return false;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error(static::class.': Shopify order address update exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    protected function withShopifyCountryName(array $address): array
    {
        $code = strtoupper(trim((string) ($address['country_code'] ?? '')));
        if ($code === '' || ! empty($address['country'])) {
            return $address;
        }

        $names = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'MX' => 'Mexico',
        ];
        if (isset($names[$code])) {
            $address['country'] = $names[$code];
        }

        return $address;
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $customer
     */
    protected function syncShopifyCustomerFromAddress(
        array $config,
        string $shopifyOrderId,
        array $address,
        array $customer
    ): void {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
            ])->timeout(30)->get(
                'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json',
                ['fields' => 'id,customer']
            );

            if (! $response->successful()) {
                return;
            }

            $customerId = (int) ($response->json('order.customer.id') ?? 0);
            if ($customerId <= 0) {
                return;
            }

            $payload = array_filter([
                'id' => $customerId,
                'first_name' => $customer['first_name'] ?? $address['first_name'] ?? null,
                'last_name' => $customer['last_name'] ?? $address['last_name'] ?? null,
                'phone' => $address['phone'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            $email = trim((string) ($customer['email'] ?? ''));
            if ($email !== '') {
                $payload['email'] = $email;
            }

            $addressPayload = array_filter(
                array_merge($address, ['default' => true]),
                static fn ($v) => $v !== null && $v !== ''
            );
            if (! empty($addressPayload['address1'])) {
                $payload['addresses'] = [$addressPayload];
            }

            Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(30)->put(
                'https://'.$config['store_url'].'/admin/api/2024-01/customers/'.$customerId.'.json',
                ['customer' => $payload]
            );
        } catch (\Throwable $e) {
            Log::warning(static::class.': Shopify customer address sync failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $placeholderNamePairs
     */
    protected function shopifyOrderNeedsAddress(string $shopifyOrderId, array $placeholderNamePairs = []): bool
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '') {
            return false;
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
            ])->timeout(30)->get(
                'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json',
                ['fields' => 'id,shipping_address,billing_address,customer']
            );

            if ($response->status() === 429) {
                sleep(2);
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $config['token'],
                ])->timeout(30)->get(
                    'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json',
                    ['fields' => 'id,shipping_address,billing_address,customer']
                );
            }

            if (! $response->successful()) {
                return true;
            }

            $ship1 = trim((string) ($response->json('order.shipping_address.address1') ?? ''));
            $city = trim((string) ($response->json('order.shipping_address.city') ?? ''));
            $zip = trim((string) ($response->json('order.shipping_address.zip') ?? ''));
            $blank = static function (string $v): bool {
                $t = trim($v);

                return $t === '' || preg_match('/^\*+$/', $t) === 1;
            };
            // Empty street OR missing city/zip (or privacy-masked ***) counts as incomplete.
            if ($blank($ship1) || $blank($city) || $blank($zip)) {
                return true;
            }

            $first = strtolower(trim((string) ($response->json('order.customer.first_name') ?? '')));
            $last = strtolower(trim((string) ($response->json('order.customer.last_name') ?? '')));
            foreach ($placeholderNamePairs as $pair) {
                if ($first === strtolower($pair[0]) && $last === strtolower($pair[1])) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }
}
