<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;

class BestBuyApiService
{
    private function getAccessToken()
    {
        $response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.macy.client_id'),
            'client_secret' => config('services.macy.client_secret'),
        ]);
        return $response->successful() ? $response->json()['access_token'] : null;
    }

    public function getInventory()
    {
        $token = $this->getAccessToken();
        if (!$token) return;
        $pageToken = null;
        $page = 1;
        $allProducts = [];

        do {
            $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code=bestbuyusa';
            if ($pageToken) {
                $url .= '&page_token=' . urlencode($pageToken);
            }
            $request = Http::withoutVerifying()->withToken($token);
            $response = $request->get($url);
            if (!$response->successful()) {
                $this->error('Product fetch failed: ' . $response->body());
                return;
            }
            // dd($response->body());
            $json = $response->json();
            // dd($json['data'][0]);
            $products = $json['data'] ?? [];
            $pageToken = $json['next_page_token'] ?? null;
            // $allProducts = array_merge($allProducts, $products);
            foreach ($products as $product) {
                $sku = $product['id'] ?? null;

                $totalQuantity = isset($product['quantities']) && is_array($product['quantities'])
                    ? array_sum(array_column($product['quantities'], 'available_quantity'))
                    : 0;

                if (!$sku) continue;
                $allProducts[] = [
                    'sku' => $sku,
                    'quantity' => $totalQuantity
                ];
            }
            $page++;
        } while ($pageToken);
        foreach ($allProducts as $sku => $data) {
            $sku = $data['sku'] ?? null;
            $quantity = $data['quantity'];
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $sku],
            //     ['inventory_bestbuy'=>$quantity,]
            // );
            ProductStockMapping::where('sku', $sku)->update(['inventory_bestbuy' => (int) $quantity]);
        }
        return $allProducts;
    }


    public function getMiraklChannels()
    {
        $token = $this->getAccessToken();
        $url = 'https://miraklconnect.com/api/channels';
        $response = Http::withoutVerifying()->withToken($token)->get($url);

        dd($response->body());
        if (!$response->successful()) {
            Log::error('Failed to fetch channels: ' . $response->body());
            return [];
        }

        return $response->json(); // returns array of channels
    }

    /**
     * Mirakl Connect product update for Best Buy channel (bullet / long description).
     *
     * @return array{success: bool, message: string, status_code?: int|null}
     */
    public function updateBulletPoints(string $sku, string $bulletPoints): array
    {
        $sku = trim($sku);
        $bulletPoints = trim($bulletPoints);
        if ($sku === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU and bullet points are required.'];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Best Buy / Mirakl access token not available.'];
        }

        $baseUrl = 'https://miraklconnect.com/api/products';
        $productPayload = [
            'id' => $sku,
            'attributes' => [
                'longDescription' => $bulletPoints,
                'productDescription' => $bulletPoints,
            ],
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => 'bestbuyusa',
        ];

        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
            $response = $request->post($baseUrl, ['products' => [$productPayload]]);
            if (! $response->successful()) {
                $response = $request->patch("{$baseUrl}/{$sku}", $productPayload);
            }
            if (! $response->successful()) {
                $response = $request->put("{$baseUrl}/{$sku}", $productPayload);
            }

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Best Buy update failed: '.$response->body()];
            }

            return ['success' => true, 'message' => 'Best Buy bullet points updated.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $sku, string $description): array
    {
        return $this->updateBulletPoints($sku, $description);
    }

    /**
     * Push price update to Best Buy (Mirakl Connect).
     *
     * @return array{success: bool, message: string}
     */
    public function updatePrice(string $sku, float $price): array
    {
        $sku = trim($sku);
        if ($sku === '' || $price <= 0) {
            return ['success' => false, 'message' => 'Valid SKU and price are required.', 'status_code' => 422];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Best Buy / Mirakl access token not available.', 'status_code' => 401];
        }

        $baseUrl = 'https://miraklconnect.com/api/products';
        $productPayload = [
            'id' => $sku,
            'attributes' => [
                'price' => round($price, 2),
            ],
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => 'bestbuyusa',
        ];

        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
            $response = $request->post($baseUrl, ['products' => [$productPayload]]);
            if (! $response->successful()) {
                $response = $request->patch("{$baseUrl}/{$sku}", $productPayload);
            }
            if (! $response->successful()) {
                $response = $request->put("{$baseUrl}/{$sku}", $productPayload);
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Best Buy price update failed: ' . $response->body(),
                    'status_code' => $response->status(),
                ];
            }

            $json = $response->json();
            $hasApiError = false;
            $apiErrorMessage = '';
            if (is_array($json)) {
                $hasApiError = ! empty($json['errors'])
                    || ! empty($json['error'])
                    || ! empty($json['error_message'])
                    || (isset($json['success']) && $json['success'] === false)
                    || ((isset($json['status']) && is_string($json['status'])) && strtolower($json['status']) === 'error');

                if ($hasApiError) {
                    $apiErrorMessage = (string) ($json['error_message']
                        ?? $json['error']
                        ?? (is_array($json['errors']) ? json_encode($json['errors']) : $json['errors'])
                        ?? 'Unknown API error');
                }
            }

            if ($hasApiError) {
                Log::warning('Best Buy price update returned API error payload', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'response' => $json,
                ]);
                return [
                    'success' => false,
                    'message' => 'Best Buy price update failed: ' . $apiErrorMessage,
                    'status_code' => $response->status(),
                ];
                
            }

            return ['success' => true, 'message' => 'Best Buy price updated.', 'status_code' => $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'status_code' => null];
        }
    }
}
