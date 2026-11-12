<?php

namespace App\Services\Support;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Archives / restores the main-store Shopify product that carries a Product Master SKU.
 *
 * Used when a SKU is marked DC (archive) and when it is restored from the app archive
 * (back to active). A Shopify product can hold several variants; it is only archived when
 * every other variant's SKU is also DC or already archived in the app.
 */
class ShopifyProductArchiveService
{
    private const API_VERSION = '2025-01';

    /**
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public function archive(string $sku): array
    {
        return $this->setStatus($sku, 'archived');
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public function restore(string $sku): array
    {
        return $this->setStatus($sku, 'active');
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string}
     */
    private function setStatus(string $sku, string $status): array
    {
        @set_time_limit(0);

        try {
            $domain = $this->domain();
            $token = $this->token();
            if ($domain === '' || $token === '') {
                return $this->done($sku, $status, false, true, 'Shopify credentials are not configured.');
            }

            $variantId = ShopifySku::variantIdForProductSku($sku);
            if ($variantId === null || trim($variantId) === '') {
                return $this->done($sku, $status, true, true, 'SKU has no Shopify variant; nothing to do on Shopify.');
            }

            $variant = $this->request($domain, $token, 'GET', "variants/{$variantId}.json");
            if (! $variant['ok']) {
                return $this->done($sku, $status, false, false, 'Could not read Shopify variant: '.$variant['error']);
            }
            $productId = $variant['json']['variant']['product_id'] ?? null;
            if (! $productId) {
                return $this->done($sku, $status, false, false, 'Shopify variant has no product id.');
            }

            $product = $this->request($domain, $token, 'GET', "products/{$productId}.json", ['fields' => 'id,status,variants']);
            if (! $product['ok']) {
                return $this->done($sku, $status, false, false, 'Could not read Shopify product: '.$product['error']);
            }
            $current = strtolower((string) ($product['json']['product']['status'] ?? ''));
            if ($current === $status) {
                return $this->done($sku, $status, true, true, "Shopify product {$productId} is already {$status}.");
            }

            if ($status === 'archived') {
                $blocking = $this->blockingSiblingSkus($sku, $product['json']['product']['variants'] ?? []);
                if ($blocking !== []) {
                    $result = $this->done($sku, $status, true, true,
                        "Shopify product {$productId} also holds non-DC SKU(s) (".implode(', ', array_slice($blocking, 0, 5)).'); left as is.');
                    $result['blocked'] = true;

                    return $result;
                }
            }

            $update = $this->request($domain, $token, 'PUT', "products/{$productId}.json", [
                'product' => ['id' => (int) $productId, 'status' => $status],
            ]);
            if (! $update['ok']) {
                return $this->done($sku, $status, false, false, 'Shopify update failed: '.$update['error']);
            }

            return $this->done($sku, $status, true, false, "Shopify product {$productId} set to {$status}.");
        } catch (\Throwable $e) {
            return $this->done($sku, $status, false, false, 'Exception: '.$e->getMessage());
        }
    }

    /**
     * Other variants on the same Shopify product whose Product Master row is still live and not DC.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return list<string>
     */
    private function blockingSiblingSkus(string $sku, array $variants): array
    {
        $blocking = [];
        foreach ($variants as $v) {
            $vsku = trim((string) ($v['sku'] ?? ''));
            if ($vsku === '' || ShopifySku::skusMatch($vsku, $sku)) {
                continue;
            }
            $row = ProductMaster::findByCompactSku($vsku); // soft-deleted rows are excluded
            if (! $row) {
                continue;
            }
            if (strtoupper(ProductMaster::statusValueFromValues($row->Values ?? [])) !== 'DC') {
                $blocking[] = $vsku;
            }
        }

        return $blocking;
    }

    /**
     * @return array{ok: bool, json: array<string, mixed>, error: string}
     */
    private function request(string $domain, string $token, string $method, string $path, array $data = []): array
    {
        $url = "https://{$domain}/admin/api/".self::API_VERSION.'/'.ltrim($path, '/');
        $delay = 2;
        $last = '';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            usleep(600000); // stay under Shopify's 2 calls/second REST limit

            $http = Http::withHeaders(['X-Shopify-Access-Token' => $token])->timeout(30);
            $response = match ($method) {
                'PUT' => $http->put($url, $data),
                'POST' => $http->post($url, $data),
                default => $http->get($url, $data),
            };

            if ($response->successful()) {
                return ['ok' => true, 'json' => (array) $response->json(), 'error' => ''];
            }

            $last = 'HTTP '.$response->status().' '.mb_substr((string) $response->body(), 0, 300);
            if ($response->status() === 429 && $attempt < 4) {
                sleep($delay);
                $delay *= 2;

                continue;
            }
            break;
        }

        return ['ok' => false, 'json' => [], 'error' => $last];
    }

    private function domain(): string
    {
        $d = (string) (config('services.shopify.store_url') ?: config('services.shopify_5core.domain') ?: '');
        $d = preg_replace('#^https?://#', '', trim($d)) ?? '';

        return rtrim($d, '/');
    }

    private function token(): string
    {
        return (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string}
     */
    private function done(string $sku, string $status, bool $ok, bool $skipped, string $message): array
    {
        $ctx = ['sku' => $sku, 'target_status' => $status, 'ok' => $ok, 'skipped' => $skipped];
        if ($ok) {
            Log::info('Shopify DC archive: '.$message, $ctx);
        } else {
            Log::warning('Shopify DC archive failed: '.$message, $ctx);
        }

        return ['ok' => $ok, 'skipped' => $skipped, 'message' => $message];
    }
}
