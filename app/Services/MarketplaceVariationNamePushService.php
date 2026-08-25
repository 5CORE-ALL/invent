<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MarketplaceVariationNamePushService
{
    /**
     * @return array{success: bool, message: string, endpoint?: string|null}
     */
    public function push(string $marketplace, string $sku, string $name): array
    {
        $marketplace = strtolower(trim($marketplace));
        $sku = trim($sku);
        $name = trim($name);

        if ($sku === '' || $name === '') {
            return ['success' => false, 'message' => 'SKU and variation name are required.', 'endpoint' => null];
        }

        try {
            return match ($marketplace) {
                'shopify', 'shopify_main', 'shopifyb2c' => $this->wrapBool(
                    app(ShopifyApiService::class)->updateVariantTitle($sku, $name),
                    'ShopifyApiService::updateVariantTitle',
                    'Main Shopify variation name update failed.'
                ),
                'shopify_pls', 'pls', 'shopifypls' => $this->wrapBool(
                    app(ShopifyPLSApiService::class)->updateVariantTitle($sku, $name),
                    'ShopifyPLSApiService::updateVariantTitle',
                    'Shopify PLS variation name update failed.'
                ),
                'shopify_b5c', 'business5core' => $this->wrapBool(
                    app(ShopifyPLSApiService::class)->updateVariantTitle($sku, $name),
                    'ShopifyPLSApiService::updateVariantTitle (B5C)',
                    'Business 5Core variation name update failed.'
                ),
                default => [
                    'success' => false,
                    'message' => "Variation name API is not available for {$marketplace}.",
                    'endpoint' => null,
                ],
            };
        } catch (\Throwable $e) {
            Log::error('Variation name push failed', [
                'marketplace' => $marketplace,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage(), 'endpoint' => null];
        }
    }

    public static function supports(string $marketplace): bool
    {
        $key = strtolower(trim($marketplace));

        return in_array($key, [
            'shopify',
            'shopify_main',
            'shopifyb2c',
            'shopify_pls',
            'pls',
            'shopifypls',
            'shopify_b5c',
            'business5core',
        ], true);
    }

    /**
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function wrapBool(bool $ok, string $endpoint, string $failMessage): array
    {
        return [
            'success' => $ok,
            'message' => $ok ? 'OK' : $failMessage,
            'endpoint' => $endpoint,
        ];
    }
}
