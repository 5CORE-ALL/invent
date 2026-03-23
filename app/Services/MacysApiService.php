<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;
class MacysApiService
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

    public function getInventory(){
        $token = $this->getAccessToken();
        if (!$token) return;
        $pageToken = null;
        $page = 1;
        $allProducts = [];

        do {
            $url = 'https://miraklconnect.com/api/products?limit=1000';
            if ($pageToken) {
                $url .= '&page_token=' . urlencode($pageToken);
            }
            $request=Http::withoutVerifying()->withToken($token);
            $response = $request->get($url);
            if (!$response->successful()) {
                Log::error('Product fetch failed: ' . $response->body());
                return;
            }
            $json = $response->json();
            // dd($json['data'][0]);
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
                $allProducts[]=[
                    'sku'=>$sku,
                    'quantity'=>$totalQuantity
                ];
            }
             $page++;
        } while ($pageToken);
        foreach ($allProducts as $sku => $data) {
        $sku = $data['sku'] ?? null;
        $quantity =$data['quantity'];
        
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $sku],
            //     ['inventory_macy'=>$quantity,]
            // );
            
             ProductStockMapping::where('sku', $sku)->update(['inventory_macy' => (int) $quantity]);    
        }
        return $allProducts;
    }

    /**
     * Update Macy's product title by SKU.
     *
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateTitle(string $sku, string $title): array
    {
        Log::info('🚀 Macy title update started', ['sku' => $sku]);

        try {
            $token = $this->getAccessToken();
            if (! $token) {
                Log::error('❌ Macy push failed', ['sku' => $sku, 'error' => 'Access token not available']);
                return ['success' => false, 'message' => 'Macy access token not available'];
            }

            // Mirakl Connect style product endpoint.
            $baseUrl = 'https://miraklconnect.com/api/products';
            $productPayload = [
                'id' => $sku,
                'attributes' => [
                    'productName' => $title,
                    'title' => $title,
                ],
            ];

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            $channelId = config('services.macy.company_id');
            if (! empty($channelId)) {
                $headers['channel_id'] = $channelId;
            }

            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(45);

            // Try documented upsert pattern first, then direct PATCH/PUT fallback.
            $response = $request->post($baseUrl, ['products' => [$productPayload]]);
            if (! $response->successful()) {
                $response = $request->patch("{$baseUrl}/{$sku}", $productPayload);
            }
            if (! $response->successful()) {
                $response = $request->put("{$baseUrl}/{$sku}", $productPayload);
            }

            if (! $response->successful()) {
                Log::error('❌ Macy push failed', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);
                return ['success' => false, 'message' => 'Macy update failed: ' . $response->body()];
            }

            Log::info('✅ Macy title updated', ['sku' => $sku]);
            return ['success' => true, 'message' => 'Macy title updated', 'response' => $response->json()];
        } catch (\Throwable $e) {
            Log::error('❌ Macy push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
