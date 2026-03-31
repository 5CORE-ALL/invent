<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductStockMapping;
use App\Services\Concerns\ResolvesBulletPointIdentifier;

class MacysApiService
{
    use ResolvesBulletPointIdentifier;

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
     * @return array{success:bool,message:string,status_code?:int|null,response?:mixed}
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

    /**
     * Update Macy's price by SKU.
     *
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updatePrice(string $sku, float $price): array
    {
        Log::info('Macy price update started', ['sku' => $sku, 'price' => $price]);

        try {
            $token = $this->getAccessToken();
            if (! $token) {
                return ['success' => false, 'message' => 'Macy access token not available', 'status_code' => 401];
            }

            $sku = trim($sku);
            if ($sku === '' || $price <= 0) {
                return ['success' => false, 'message' => 'Valid SKU and price are required', 'status_code' => 422];
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
            ];
            $channelId = config('services.macy.company_id');
            if (! empty($channelId)) {
                $headers['channel_id'] = $channelId;
            }

            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(45);
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
                    'message' => 'Macy price update failed: ' . $response->body(),
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
                Log::warning('Macy price update returned API error payload', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'response' => $json,
                ]);
                return [
                    'success' => false,
                    'message' => 'Macy price update failed: ' . $apiErrorMessage,
                    'status_code' => $response->status(),
                ];
                
            }

            return [
                'success' => true,
                'message' => 'Macy price updated',
                'status_code' => $response->status(),
                'response' => $json ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('Macy price update failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'status_code' => null];
        }
    }

    /**
     * Update long marketing copy / bullets (Mirakl product attributes). No truncation.
     *
     * @return array{success:bool,message:string,response?:mixed}
     */
    /**
     * Resolve Mirakl seller SKU from metrics (by SKU or product / listing id columns when present).
     */
    private function resolveMacyMiraklSku(string $identifier): string
    {
        $id = trim($identifier);
        if ($id === '') {
            return '';
        }

        if (Schema::hasTable('macy_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('macy_metrics', $identifier, [
                'product_id',
                'mirakl_product_id',
                'listing_id',
            ]);
            if ($row && ! empty($row->sku)) {
                return trim((string) $row->sku);
            }
        }

        return $id;
    }

    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        Log::info('Macy bullet update started', ['identifier' => $identifier]);

        try {
            $sku = $this->resolveMacyMiraklSku($identifier);
            $bulletPoints = trim($bulletPoints);
            if ($sku === '' || $bulletPoints === '') {
                return ['success' => false, 'message' => 'SKU (or marketplace product id) and bullet points are required.'];
            }

            $attributes = [
                'bulletPoints' => $bulletPoints,
            ];

            return $this->pushMacyMiraklProductAttributes($sku, $attributes, 'Macy bullet points updated', 'Macy bullet update failed');
        } catch (\Throwable $e) {
            Log::error('Macy bullet update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mirakl Connect product upsert: POST batch, then PATCH/PUT by SKU (same as title/bullet flows).
     *
     * @param  array<string, string>  $attributes
     * @return array{success: bool, message: string, response?: mixed}
     */
    private function pushMacyMiraklProductAttributes(string $sku, array $attributes, string $successMessage, string $failurePrefix): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Macy access token not available'];
        }

        $baseUrl = 'https://miraklconnect.com/api/products';
        $productPayload = [
            'id' => $sku,
            'attributes' => $attributes,
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $channelId = config('services.macy.company_id');
        if (! empty($channelId)) {
            $headers['channel_id'] = $channelId;
        }

        $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);

        $response = $request->post($baseUrl, ['products' => [$productPayload]]);
        if (! $response->successful()) {
            $response = $request->patch("{$baseUrl}/{$sku}", $productPayload);
        }
        if (! $response->successful()) {
            $response = $request->put("{$baseUrl}/{$sku}", $productPayload);
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => $failurePrefix.': '.$response->body()];
        }

        return ['success' => true, 'message' => $successMessage, 'response' => $response->json()];
    }

    /**
     * Description Master: long-form copy via Mirakl `longDescription` / `productDescription` only (no bullet field).
     *
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description): array
    {
        if (trim($identifier) === '' || trim($description) === '') {
            return ['success' => false, 'message' => 'SKU (or marketplace product id) and description are required.'];
        }

        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description is empty.'];
        }

        try {
            $sku = $this->resolveMacyMiraklSku($identifier);
            if ($sku === '') {
                return ['success' => false, 'message' => 'SKU (or marketplace product id) and description are required.'];
            }

            $current = $this->fetchCurrentMacyDescription($sku);
            $merged = $this->appendUniqueText($current, $description);
            $attributes = [
                'longDescription' => $merged,
                'productDescription' => $merged,
            ];

            return $this->pushMacyMiraklProductAttributes($sku, $attributes, 'Macy product description updated.', 'Macy description update failed');
        } catch (\Throwable $e) {
            Log::error('Macy description update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        return $this->updateDescription($identifier, $description);
    }

    /**
     * Mirakl Connect product images (attribute names vary by channel; we send common keys).
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 12);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU (or marketplace product id) and image URLs are required.'];
        }

        try {
            $sku = $this->resolveMacyMiraklSku($identifier);
            if ($sku === '') {
                return ['success' => false, 'message' => 'SKU (or marketplace product id) not found in macy_metrics.'];
            }

            $attributes = [
                'imageUrls' => $urls,
                'productImageUrls' => $urls,
                'mainImageUrl' => $urls[0],
            ];

            return $this->pushMacyMiraklProductAttributes($sku, $attributes, 'Macy product images updated.', 'Macy image update failed');
        } catch (\Throwable $e) {
            Log::error('Macy image update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Image Master compatibility method: push images then persist image_urls in macy_metrics.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string}
     */
    public function updateImages(string $identifier, array $images): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $res = $this->updateListingImages($identifier, $images);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $sku = $this->resolveMacyMiraklSku($identifier);
        $saved = $this->saveImageUrlsToMacyMetrics($sku, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Macy product images updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToMacyMetrics(string $sku, array $images): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('macy_metrics') || ! Schema::hasColumn('macy_metrics', 'sku')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('macy_metrics', 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn('macy_metrics', 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('macy_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('macy_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('macy_metrics', 'created_at')) {
                DB::table('macy_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Macy image_urls save failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function fetchCurrentMacyDescription(string $sku): string
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return '';
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $channelId = config('services.macy.company_id');
        if (! empty($channelId)) {
            $headers['channel_id'] = $channelId;
        }

        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(45);
            $response = $request->get('https://miraklconnect.com/api/products/'.$sku);
            if (! $response->successful()) {
                return '';
            }

            $json = $response->json();
            $attrs = $json['attributes'] ?? $json['data']['attributes'] ?? [];
            if (! is_array($attrs)) {
                return '';
            }

            $candidate = trim((string) ($attrs['longDescription'] ?? $attrs['productDescription'] ?? $attrs['description'] ?? ''));

            return $candidate;
        } catch (\Throwable $e) {
            Log::warning('Macy fetch current description failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return '';
        }
    }

    private function appendUniqueText(string $current, string $incoming): string
    {
        $current = trim($current);
        $incoming = trim($incoming);
        if ($incoming === '') {
            return $current;
        }
        if ($current === '') {
            return $incoming;
        }
        if (str_contains(mb_strtolower($current), mb_strtolower($incoming))) {
            return $current;
        }

        return $current."\n\n".$incoming;
    }
}
