<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\Concerns\ResolvesBulletPointIdentifier;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\SavesMarketplaceImageMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class FaireService
{
    use ResolvesBulletPointIdentifier;
    use SavesMarketplaceVideoMetrics;
    use SavesMarketplaceImageMetrics;
    use VideoMasterMarketplaceMethods;

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

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');

        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and bullet points are required.'];
        }

        $productId = null;
        if (Schema::hasTable('faire_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('faire_metrics', $identifier, ['product_id', 'faire_product_id']);
            if ($row && ! empty($row->product_id)) {
                $productId = (string) $row->product_id;
            }
        }

        if (! $productId) {
            $productId = $this->getProductIdBySku(trim($identifier));
        }

        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $payload = [
            'description' => $bulletPoints,
            'short_description' => $bulletPoints,
        ];

        try {
            $res = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(45)
                ->patch("{$baseUrl}/products/{$productId}", $payload);

            if ($res->successful()) {
                return ['success' => true, 'message' => 'Faire bullet points updated', 'response' => $res->json()];
            }

            return ['success' => false, 'message' => 'Faire update failed: '.$res->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        return $this->updateBulletPoints($identifier, $description);
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');
        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $productId = null;
        if (Schema::hasTable('faire_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('faire_metrics', $identifier, ['product_id', 'faire_product_id']);
            if ($row && ! empty($row->product_id)) {
                $productId = (string) $row->product_id;
            }
        }
        if (! $productId) {
            $productId = $this->getProductIdBySku(trim($identifier));
        }
        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        $primary = $videos[0];
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payloadAttempts = [
            ['video_url' => $primary, 'video_urls' => $videos],
            ['videos' => $videos, 'product_video_url' => $primary],
        ];

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $lastMessage = 'Faire video update failed.';
        foreach ($payloadAttempts as $payload) {
            try {
                $res = Http::withoutVerifying()->withHeaders($headers)->timeout(45)->patch("{$baseUrl}/products/{$productId}", $payload);
                if ($res->successful()) {
                    $sku = trim($identifier);
                    $this->saveVideoUrlsToMetricsRow('faire_metrics', $sku, $videos);

                    return ['success' => true, 'message' => 'Faire product video updated.', 'normalized_urls' => $videos];
                }
                $lastMessage = 'Faire update failed: '.$res->body();
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if (trim($identifier) === '' || $images === []) {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and at least one image URL are required.'];
        }

        foreach ($images as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
            }
        }

        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');
        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $productId = null;
        if (Schema::hasTable('faire_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('faire_metrics', $identifier, ['product_id', 'faire_product_id']);
            if ($row && ! empty($row->product_id)) {
                $productId = (string) $row->product_id;
            }
        }
        if (! $productId) {
            $productId = $this->getProductIdBySku(trim($identifier));
        }
        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payloadAttempts = [
            ['image_url' => $images[0], 'image_urls' => $images, 'images' => $images],
            ['images' => array_map(fn ($url) => ['url' => $url], $images)],
        ];

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $lastMessage = 'Faire image update failed.';
        foreach ($payloadAttempts as $payload) {
            try {
                $res = Http::withoutVerifying()->withHeaders($headers)->timeout(45)->patch("{$baseUrl}/products/{$productId}", $payload);
                if ($res->successful()) {
                    $sku = trim($identifier);
                    $this->saveImageUrlsToMetricsRow('faire_metrics', $sku, $images);

                    return ['success' => true, 'message' => 'Faire product images updated.', 'normalized_urls' => $images];
                }
                $lastMessage = 'Faire update failed: '.$res->body();
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

}
