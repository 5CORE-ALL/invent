<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaireService
{
    protected $clientId;
    protected $clientSecret;
    protected $redirectUrl;
    protected $refreshToken;
    protected $region;
    protected $marketplaceId;
    protected $awsAccessKey;
    protected $awsSecretKey;
    protected $endpoint;

    public function __construct()
    {
        $this->clientId     = config('services.faire.app_id');
        $this->clientSecret = config('services.faire.app_secret');
        $this->redirectUrl  = config('services.faire.redirect_url');
    }

    public function getInventory()
    {
    }

    public function getProductIdBySku(string $sku): ?string
    {
        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');

        if (! $token) {
            Log::warning('Faire product lookup skipped: token not configured', ['sku' => $sku]);
            return null;
        }

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Accept' => 'application/json',
        ];

        try {
            $res = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(45)
                ->get("{$baseUrl}/products", ['sku' => $sku, 'limit' => 50]);

            if (! $res->successful()) {
                Log::warning('Faire product lookup failed', [
                    'sku' => $sku,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                return null;
            }

            $data = $res->json();
            $products = $data['products'] ?? $data['data'] ?? [];
            foreach ($products as $product) {
                $candidateSku = $product['sku'] ?? $product['external_sku'] ?? null;
                if ($candidateSku && strcasecmp((string) $candidateSku, $sku) === 0) {
                    return (string) ($product['id'] ?? '');
                }
            }

            if (! empty($products[0]['id'])) {
                return (string) $products[0]['id'];
            }
        } catch (\Throwable $e) {
            Log::error('Faire product lookup exception', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateTitle(string $sku, string $title): array
    {
        Log::info('🚀 Faire title update started', ['sku' => $sku]);

        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');

        if (! $token) {
            Log::error('❌ Faire push failed', ['sku' => $sku, 'error' => 'API token is missing']);
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $productId = $this->getProductIdBySku($sku);
        if (! $productId) {
            Log::error('❌ Faire push failed', ['sku' => $sku, 'error' => 'Product not found by SKU']);
            return ['success' => false, 'message' => "Faire product not found for SKU {$sku}"];
        }

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payloads = [
            ['name' => $title, 'title' => $title],
            ['product_name' => $title, 'title' => $title],
        ];

        try {
            $res = null;
            foreach ($payloads as $payload) {
                $res = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(45)
                    ->patch("{$baseUrl}/products/{$productId}", $payload);

                if ($res->successful()) {
                    Log::info('✅ Faire title updated', ['sku' => $sku, 'product_id' => $productId]);
                    return ['success' => true, 'message' => 'Faire title updated', 'response' => $res->json()];
                }
            }

            Log::error('❌ Faire push failed', ['sku' => $sku, 'status' => $res?->status(), 'error' => $res?->body()]);
            return ['success' => false, 'message' => 'Faire update failed: ' . ($res?->body() ?? 'Unknown error')];
        } catch (\Throwable $e) {
            Log::error('❌ Faire push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

}
