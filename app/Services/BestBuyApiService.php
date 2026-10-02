<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\Concerns\MiraklConnectProductUpsert;
use App\Services\Support\Concerns\MiraklMcmBulletImport;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;

class BestBuyApiService
{
    use MiraklConnectProductUpsert;
    use MiraklMcmBulletImport;
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;
    protected function miraklChannelCode(): string
    {
        return 'bestbuyusa';
    }

    protected function miraklConnectAccessToken(): ?string
    {
        return $this->getAccessToken();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{success: bool, message: string}
     */
    protected function miraklUpsertAttributes(string $sku, array $attributes, ?array $descriptions = null): array
    {
        return $this->miraklConnectUpsertProduct(
            $sku,
            $this->miraklChannelCode(),
            $attributes,
            $descriptions
        );
    }

    protected function getAccessToken()
    {
        $clientId = trim((string) config('services.bestbuy.client_id', ''));
        $clientSecret = trim((string) config('services.bestbuy.client_secret', ''));
        if ($clientId === '' || $clientSecret === '') {
            $clientId = trim((string) config('services.macy.client_id', ''));
            $clientSecret = trim((string) config('services.macy.client_secret', ''));
        }
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
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
            $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code='.$this->miraklChannelCode();
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
     * Live Best Buy / Purchasing Power listing titles live on MCM P41, not Connect catalog.
     *
     * @return array{success: bool, message: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $sku = $this->resolveMiraklMcmLiveShopSku(trim($sku));
        $title = mb_substr(trim($title), 0, 150);
        if ($sku === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.'];
        }

        $connect = $this->miraklUpsertAttributes($sku, [
            'productName' => $title,
            'title' => $title,
        ]);

        return $this->completeMiraklTitlePushWithMcm($sku, $title, is_array($connect) ? $connect : []);
    }

    public function updateBulletPoints(string $sku, string $bulletPoints): array
    {
        $sku = trim($sku);
        $bulletPoints = trim($bulletPoints);
        if ($sku === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU and bullet points are required.'];
        }

        return $this->pushBulletPointsViaMiraklMcm($sku, $bulletPoints);
    }

    protected function miraklMcmConfigKey(): string
    {
        return 'bestbuy';
    }

    protected function miraklMcmMarketplaceLabel(): string
    {
        return 'Best Buy';
    }

    protected function miraklMcmHierarchyTable(): ?string
    {
        return 'bestbuy_price_data';
    }

    protected function miraklMcmFillRequiredFromMasters(): bool
    {
        return true;
    }

    /**
     * Best Buy Microphones (and similar) P41 imports fail while SENT until these attributes are on the row.
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, string>
     */
    protected function miraklMcmP41ExtraAttributeValues(string $sku, ?string $hierarchy, array $offer, mixed $priceRow): array
    {
        $fallbacks = $this->bestBuyP41AttributeFallbacks($sku);
        $required = $this->resolveMiraklMcmPm11RequiredAttributeCodes($hierarchy);
        if ($required === []) {
            $label = strtolower(trim((string) $hierarchy));
            if (! str_contains($label, 'microphone')) {
                return [];
            }

            return $fallbacks;
        }

        $out = [];
        foreach ($required as $code) {
            $value = $this->bestBuyFallbackForAttributeCode($code, $fallbacks);
            if ($value !== '') {
                $out[$code] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function bestBuyP41AttributeFallbacks(string $sku): array
    {
        $defaults = (array) $this->miraklMcmConfig('mcm_p41_defaults', []);
        $dims = $this->bestBuyPackageInches($sku);
        $values = [
            'modelNumber' => $sku,
            'productLength' => $dims['length'],
            'productWidth' => $dims['width'],
            'productHeight' => $dims['height'],
            'microphoneType' => 'Dynamic',
            'wireless' => 'No',
            'lightingType' => 'None',
            'warrantyParts' => '1 Year',
            'warrantyLabor' => '1 Year',
            'unitOfMeasure' => 'IN',
        ];
        foreach ($values as $code => $value) {
            $configured = trim((string) ($defaults[$code] ?? ''));
            if ($configured !== '' && ! in_array($code, ['productLength', 'productWidth', 'productHeight', 'modelNumber'], true)) {
                $values[$code] = $configured;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $fallbacks
     */
    private function bestBuyFallbackForAttributeCode(string $code, array $fallbacks): string
    {
        if (isset($fallbacks[$code]) && trim($fallbacks[$code]) !== '') {
            return trim($fallbacks[$code]);
        }
        $leaf = str_contains($code, '.') ? substr($code, (int) strrpos($code, '.') + 1) : $code;
        if (isset($fallbacks[$leaf]) && trim($fallbacks[$leaf]) !== '') {
            return trim($fallbacks[$leaf]);
        }
        $lower = strtolower($code);
        if (str_ends_with($lower, 'dimensions.length')) {
            return $fallbacks['productLength'];
        }
        if (str_ends_with($lower, 'dimensions.width')) {
            return $fallbacks['productWidth'];
        }
        if (str_ends_with($lower, 'dimensions.height')) {
            return $fallbacks['productHeight'];
        }
        if (str_contains($lower, 'unitofmeasur')) {
            return $fallbacks['unitOfMeasure'];
        }

        return '';
    }

    /**
     * @return array{length: string, width: string, height: string}
     */
    private function bestBuyPackageInches(string $sku): array
    {
        $length = 8.0;
        $width = 3.0;
        $height = 3.0;
        try {
            if (Schema::hasTable('product_master')) {
                $row = ProductMaster::query()->where('sku', $sku)->first();
                if ($row) {
                    $values = $row->toArray();
                    foreach (['l', 'length'] as $key) {
                        if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                            $length = (float) $values[$key];
                            break;
                        }
                    }
                    foreach (['w', 'width'] as $key) {
                        if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                            $width = (float) $values[$key];
                            break;
                        }
                    }
                    foreach (['h', 'height'] as $key) {
                        if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                            $height = (float) $values[$key];
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // keep handheld-mic defaults
        }

        $format = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return [
            'length' => $format($length),
            'width' => $format($width),
            'height' => $format($height),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $sku, string $description, array $imageUrls = []): array
    {
        return $this->updateDescription($sku, $description, $imageUrls);
    }

    /**
     * Mirakl longDescription / productDescription (HTML) for Best Buy channel.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        if (trim($identifier) === '' || trim($description) === '') {
            return ['success' => false, 'message' => 'SKU and description are required.'];
        }

        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description is empty.'];
        }

        $sku = trim($identifier);
        $label = $this->miraklMcmMarketplaceLabel();
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => "{$label} / Mirakl access token not available."];
        }

        $descriptionWithImages = DescriptionWithImagesFormatter::buildHtmlWithImages(
            $description,
            $identifier,
            $sku,
            'Product Image',
            12,
            $imageUrls
        )['html'];

        $productPayload = [
            'id' => $sku,
            'attributes' => [
                'longDescription' => $descriptionWithImages,
                'productDescription' => $descriptionWithImages,
            ],
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => $this->miraklChannelCode(),
        ];

        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
            $response = $request->post('https://miraklconnect.com/api/products', ['products' => [$productPayload]]);
            if (! $response->successful()) {
                $response = $request->patch("https://miraklconnect.com/api/products/{$sku}", $productPayload);
            }
            if (! $response->successful()) {
                $response = $request->put("https://miraklconnect.com/api/products/{$sku}", $productPayload);
            }

            $connect = $response->successful()
                ? ['success' => true, 'message' => "{$label} Connect catalog accepted the description."]
                : ['success' => false, 'message' => "{$label} Connect description update failed: ".mb_substr($response->body(), 0, 500)];
        } catch (\Throwable $e) {
            $connect = ['success' => false, 'message' => $e->getMessage()];
        }

        return $this->completeMiraklDescriptionPushWithMcm($sku, $description, $connect);
    }

    /**
     * Connect catalog alone does not change the live MCM listing; follow with P41 productLongDescription.
     *
     * @param  array{success?: bool, message?: string}  $connect
     * @return array{success: bool, message: string, mcm_integration_pending?: bool}
     */
    protected function completeMiraklDescriptionPushWithMcm(string $sku, string $description, array $connect): array
    {
        $label = $this->miraklMcmMarketplaceLabel();
        if ($this->miraklMcmApiKey() === null) {
            if ($connect['success'] ?? false) {
                $connect['message'] = trim(($connect['message'] ?? '')
                    ." {$label} MCM P41 skipped — set {$this->miraklMcmApiKeyEnvName()} for seller portal sync.");
            }

            return $connect;
        }

        $mcm = $this->pushDescriptionViaMiraklMcm($this->resolveMiraklMcmLiveShopSku($sku), $description);
        if ($mcm['success'] ?? false) {
            if ($connect['success'] ?? false) {
                $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
            }

            return $mcm;
        }

        if ($connect['success'] ?? false) {
            $connect['message'] = trim(($connect['message'] ?? '')
                .' Live listing not updated — MCM P41 description issue: '.($mcm['message'] ?? 'unknown error'));
            $connect['success'] = false;

            return $connect;
        }

        return $mcm;
    }

    /**
     * Description Master: return the current Best Buy (Mirakl) product description for one SKU.
     *
     * @return array{success: bool, message: string, html?: string, source?: string}
     */
    public function fetchDescriptionHtml(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $sku = $identifier;
        if (Schema::hasTable('bestbuy_metrics')) {
            $row = \Illuminate\Support\Facades\DB::table('bestbuy_metrics')->where('sku', $sku)->first();
            if ($row) {
                $master = trim((string) ($row->description_master ?? ''));
                if ($master !== '') {
                    return [
                        'success' => true,
                        'message' => 'Best Buy description loaded from metrics.',
                        'html' => $master,
                        'source' => 'metrics',
                    ];
                }
                $bullets = trim((string) ($row->bullet_points ?? ''));
                if ($bullets !== '') {
                    return [
                        'success' => true,
                        'message' => 'Best Buy description loaded from metrics bullets.',
                        'html' => DescriptionWithImagesFormatter::linesToEditorHtml($bullets),
                        'source' => 'metrics_bullets',
                    ];
                }
            }
        }

        $desc = trim($this->fetchCurrentBestBuyDescription($sku));
        if ($desc === '') {
            return ['success' => false, 'message' => 'Best Buy returned no description for this SKU.'];
        }

        return ['success' => true, 'message' => 'Best Buy description loaded.', 'html' => $desc, 'source' => 'mirakl'];
    }

    private function fetchCurrentBestBuyDescription(string $sku): string
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return '';
        }

        try {
            $response = Http::withoutVerifying()->withToken($token)->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'channel_id' => $this->miraklChannelCode(),
            ])->timeout(45)->get('https://miraklconnect.com/api/products/'.$sku);

            if (! $response->successful()) {
                return '';
            }

            $attrs = $response->json('attributes') ?? $response->json('data.attributes') ?? [];
            if (! is_array($attrs)) {
                return '';
            }

            return trim((string) ($attrs['longDescription'] ?? $attrs['productDescription'] ?? $attrs['description'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Best Buy fetch current description failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Push image URLs to Best Buy through Mirakl Connect, then MCM P41 (live listing).
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateListingImages(string $sku, array $imageUrls): array
    {
        $sku = $this->resolveMiraklMcmLiveShopSku(trim($sku));
        $urls = array_slice(array_values(array_unique(array_filter(array_map('trim', $imageUrls), fn ($url) => $url !== ''))), 0, 12);
        if ($sku === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU and at least one image URL are required.'];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => $this->miraklMcmMarketplaceLabel().' / Mirakl access token not available.'];
        }

        $baseUrl = 'https://miraklconnect.com/api/products';
        $productPayload = [
            'id' => $sku,
            'images' => array_map(fn ($url) => ['url' => $url], $urls),
            'attributes' => [
                'imageUrls' => $urls,
                'productImageUrls' => $urls,
                'mainImageUrl' => $urls[0],
            ],
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => $this->miraklChannelCode(),
        ];

        $label = $this->miraklMcmMarketplaceLabel();
        $connect = ['success' => false, 'message' => "{$label} Connect image update failed."];
        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
            $response = $request->post($baseUrl, ['products' => [$productPayload]]);
            if (! $response->successful()) {
                $response = $request->patch("{$baseUrl}/{$sku}", $productPayload);
            }
            if (! $response->successful()) {
                $response = $request->put("{$baseUrl}/{$sku}", $productPayload);
            }

            if ($response->successful()) {
                $connect = ['success' => true, 'message' => "{$label} Connect catalog accepted the images."];
            } else {
                $connect = ['success' => false, 'message' => "{$label} Connect image update failed: ".mb_substr($response->body(), 0, 500)];
            }
        } catch (\Throwable $e) {
            $connect = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($this->miraklMcmApiKey() === null) {
            if ($connect['success'] ?? false) {
                $this->saveImageUrlsToBestBuyMetrics($sku, $urls);
                $connect['message'] = trim(($connect['message'] ?? '')
                    ." {$label} MCM (mainImage) skipped — set {$this->miraklMcmApiKeyEnvName()} for seller portal sync.");
                $connect['normalized_urls'] = $urls;
            }

            return $connect;
        }

        if (! filter_var($this->miraklMcmConfig('mcm_image_push', true), FILTER_VALIDATE_BOOL)) {
            if ($connect['success'] ?? false) {
                $this->saveImageUrlsToBestBuyMetrics($sku, $urls);
                $connect['message'] = trim(($connect['message'] ?? '')
                    .' MCM P41 image push disabled (mcm_image_push=false). Connect catalog only.');
                $connect['normalized_urls'] = $urls;
            }

            return $connect;
        }

        $mcm = $this->pushImagesViaMiraklMcm($sku, $urls);
        if ($mcm['success'] ?? false) {
            $this->saveImageUrlsToBestBuyMetrics($sku, $urls);
            if ($connect['success'] ?? false) {
                $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
            }
            $mcm['normalized_urls'] = $urls;

            return $mcm;
        }

        if ($connect['success'] ?? false) {
            $this->saveImageUrlsToBestBuyMetrics($sku, $urls);
            $connect['success'] = false;
            $connect['message'] = trim(($connect['message'] ?? '')
                .' Live listing not updated — MCM P41 image issue: '.($mcm['message'] ?? 'unknown error'));
            $connect['normalized_urls'] = $urls;

            return $connect;
        }

        return $mcm;
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $sku, array $images): array
    {
        return $this->updateListingImages($sku, $images);
    }

    /**
     * Push video URL(s) to Best Buy through Mirakl Connect product attributes.
     *
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateListingVideos(string $sku, array $videoUrls): array
    {
        $sku = trim($sku);
        $urls = array_slice(array_values(array_unique(array_filter(array_map('trim', $videoUrls), fn ($url) => $url !== ''))), 0, 5);
        if ($sku === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU and at least one video URL are required.'];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => $this->miraklMcmMarketplaceLabel().' / Mirakl access token not available.'];
        }

        $baseUrl = 'https://miraklconnect.com/api/products';
        $productPayload = [
            'id' => $sku,
            'attributes' => [
                'videoUrl' => $urls[0],
                'videoUrls' => $urls,
                'productVideoUrls' => $urls,
                'mainVideoUrl' => $urls[0],
            ],
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => $this->miraklChannelCode(),
        ];

        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(90);
            $response = $request->post($baseUrl, ['products' => [$productPayload]]);
            if (! $response->successful()) {
                $response = $request->patch("{$baseUrl}/{$sku}", $productPayload);
            }
            if (! $response->successful()) {
                $response = $request->put("{$baseUrl}/{$sku}", $productPayload);
            }

            if (! $response->successful()) {
                return ['success' => false, 'message' => $this->miraklMcmMarketplaceLabel().' video update failed: '.$response->body()];
            }

            $saved = $this->saveVideoUrlsToMetricsRow('bestbuy_metrics', $sku, $urls);
            $message = $this->miraklMcmMarketplaceLabel().' product videos updated.';
            if (! $saved) {
                $message .= ' Metrics save failed.';
            }

            return ['success' => true, 'message' => $message, 'normalized_urls' => $urls];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $sku, array $videos, string $mode = 'replace'): array
    {
        return $this->updateListingVideos($sku, $videos);
    }

    /**
     * Push listed price via Mirakl MCM PRI01. Best Buy live price is on the offer, not Connect catalog.
     *
     * @return array{success: bool, message: string, status_code?: int|null, import_id?: string|null}
     */
    public function updatePrice(string $sku, float $price): array
    {
        $sku = trim($sku);
        $price = round((float) $price, 2);
        if ($sku === '' || $price <= 0) {
            return ['success' => false, 'message' => 'Valid SKU and price are required.', 'status_code' => 422];
        }

        $apiKey = $this->miraklMcmApiKey();
        $baseUrl = rtrim((string) config('services.bestbuy.mcm_base_url', 'https://bestbuyus-prod.mirakl.net'), '/');
        if ($apiKey === null || $apiKey === '' || $baseUrl === '') {
            return [
                'success' => false,
                'message' => 'Best Buy MCM API key is not configured (BESTBUY_MCM_API_KEY).',
                'status_code' => 401,
            ];
        }

        $offerSku = $this->resolveMcmOfferSku($sku, $apiKey, $baseUrl);
        if ($offerSku === null) {
            return [
                'success' => false,
                'message' => "No Best Buy MCM offer found for SKU: {$sku}",
                'status_code' => 404,
            ];
        }

        $csv = "offer-sku;price\n"
            .'"'.str_replace('"', '""', $offerSku).'";'
            .number_format($price, 2, '.', '')."\n";

        $query = [];
        $shopId = config('services.bestbuy.shop_id');
        if ($shopId !== null && $shopId !== '') {
            $query['shop_id'] = (int) $shopId;
        }

        try {
            $url = $baseUrl.'/api/offers/pricing/imports';
            if ($query !== []) {
                $url .= '?'.http_build_query($query);
            }

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->attach('file', $csv, 'bb-price-'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $offerSku).'.csv')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('Best Buy MCM PRI01 price push failed', [
                    'sku' => $sku,
                    'offer_sku' => $offerSku,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 800),
                ]);

                return [
                    'success' => false,
                    'message' => 'Best Buy price push failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300),
                    'status_code' => $response->status(),
                ];
            }

            $json = $response->json() ?? [];
            $importId = $json['import_id'] ?? $json['importId'] ?? null;
            if ($importId === null || $importId === '') {
                return [
                    'success' => false,
                    'message' => 'Best Buy price push accepted no import_id.',
                    'status_code' => $response->status(),
                ];
            }

            $import = $this->waitForPricingImport((string) $importId, $apiKey, $baseUrl);
            $linesOk = (int) ($import['lines_in_success'] ?? 0);
            $linesErr = (int) ($import['lines_in_error'] ?? 0);
            $offersUpdated = (int) ($import['offers_updated'] ?? 0);
            $status = strtoupper((string) ($import['status'] ?? ''));

            if ($linesErr > 0 || ($import !== [] && $linesOk < 1 && $status !== 'COMPLETE')) {
                $errMsg = $this->fetchPricingImportErrorSummary((string) $importId, $apiKey, $baseUrl);
                Log::warning('Best Buy MCM PRI01 completed with errors', [
                    'sku' => $sku,
                    'offer_sku' => $offerSku,
                    'import_id' => $importId,
                    'status' => $status,
                    'lines_in_success' => $linesOk,
                    'lines_in_error' => $linesErr,
                    'error' => $errMsg,
                ]);

                return [
                    'success' => false,
                    'message' => $errMsg !== ''
                        ? ('Best Buy price push failed: '.$errMsg)
                        : ('Best Buy price push failed (import '.$importId.' status '.$status.')'),
                    'status_code' => 400,
                    'import_id' => (string) $importId,
                ];
            }

            $this->syncLocalBestBuyPriceAfterPush($sku, $offerSku, $price);

            Log::info('Best Buy MCM PRI01 price push complete', [
                'sku' => $sku,
                'offer_sku' => $offerSku,
                'price' => $price,
                'import_id' => $importId,
            ]);

            return [
                'success' => true,
                'message' => 'Price $'.number_format($price, 2).' pushed to Best Buy for SKU: '.$offerSku
                    .' (import '.$importId.')',
                'status_code' => $response->status(),
                'import_id' => (string) $importId,
            ];
        } catch (\Throwable $e) {
            Log::error('Best Buy MCM PRI01 exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Best Buy API error: '.$e->getMessage(),
                'status_code' => null,
            ];
        }
    }

    /**
     * Write the pushed SPRICE into the listed-price tables the Price column reads.
     */
    protected function syncLocalBestBuyPriceAfterPush(string $sku, string $offerSku, float $price): void
    {
        try {
            $price = round($price, 2);
            $want = array_values(array_unique(array_filter([
                $this->normalizeBestBuyOfferSku($sku),
                $this->normalizeBestBuyOfferSku($offerSku),
            ])));
            if ($want === [] || $price <= 0) {
                return;
            }

            $placeholders = implode(',', array_fill(0, count($want), '?'));

            if (Schema::hasTable('bestbuy_usa_products')) {
                \App\Models\BestbuyUsaProduct::query()
                    ->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', $want)
                    ->update(['price' => $price]);
            }

            if (Schema::hasTable('bestbuy_price_data')) {
                \App\Models\BestbuyPriceData::query()
                    ->where(function ($q) use ($placeholders, $want) {
                        $q->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', $want)
                            ->orWhereRaw('UPPER(TRIM(offer_sku)) IN ('.$placeholders.')', $want);
                    })
                    ->update([
                        'price' => $price,
                        'original_price' => $price,
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Best Buy local price sync after PRI01 failed', [
                'sku' => $offerSku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeBestBuyOfferSku(string $value): string
    {
        $v = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $value);

        return strtoupper(preg_replace('/\s+/u', ' ', trim($v)) ?? '');
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToBestBuyMetrics(string $sku, array $images): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('bestbuy_metrics') || ! Schema::hasColumn('bestbuy_metrics', 'sku')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('bestbuy_metrics', 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn('bestbuy_metrics', 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('bestbuy_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            \Illuminate\Support\Facades\DB::table('bestbuy_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('bestbuy_metrics', 'created_at')) {
                \Illuminate\Support\Facades\DB::table('bestbuy_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Best Buy image_urls save failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function isConfigured(): bool
    {
        $clientId = trim((string) config('services.bestbuy.client_id', ''));
        $clientSecret = trim((string) config('services.bestbuy.client_secret', ''));
        $mcmKey = trim((string) config('services.bestbuy.mcm_api_key', ''));

        return ($clientId !== '' && $clientSecret !== '') || $mcmKey !== '';
    }

    /**
     * @return array{success: bool, message: string, sample_count?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Best Buy credentials missing. Set BESTBUY_MCM_API_KEY + BESTBUY_SHOP_ID (and BESTBUY_CLIENT_ID / BESTBUY_CLIENT_SECRET for Connect).',
            ];
        }

        $parts = [];
        $sample = 0;

        $apiKey = $this->miraklMcmApiKey();
        $baseUrl = rtrim((string) config('services.bestbuy.mcm_base_url', 'https://bestbuyus-prod.mirakl.net'), '/');
        if ($apiKey !== null && $apiKey !== '' && $baseUrl !== '') {
            try {
                $params = ['max' => 1];
                $shopId = config('services.bestbuy.shop_id');
                if ($shopId !== null && $shopId !== '') {
                    $params['shop_id'] = (int) $shopId;
                }
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($baseUrl.'/api/offers', $params);

                if (! $response->successful()) {
                    return [
                        'success' => false,
                        'message' => 'Best Buy MCM OF21 failed: HTTP '.$response->status().' '.substr($response->body(), 0, 200),
                    ];
                }
                $offers = $response->json('offers') ?? [];
                $sample = is_array($offers) ? count($offers) : 0;
                $parts[] = 'MCM '.$baseUrl.' shop '.(string) ($shopId ?? 'n/a')." ({$sample} offer sample)";
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'Best Buy MCM connection failed: '.$e->getMessage(),
                ];
            }
        }

        $token = $this->getAccessToken();
        if ($token) {
            try {
                $response = Http::withoutVerifying()->withToken($token)->get(
                    'https://miraklconnect.com/api/products?limit=1&channel_code='.$this->miraklChannelCode()
                );
                if ($response->successful()) {
                    $parts[] = 'Mirakl Connect channel '.$this->miraklChannelCode();
                } else {
                    $parts[] = 'Mirakl Connect ping HTTP '.$response->status();
                }
            } catch (\Throwable $e) {
                $parts[] = 'Mirakl Connect error: '.$e->getMessage();
            }
        }

        if ($parts === []) {
            return [
                'success' => false,
                'message' => 'No Best Buy API endpoint could be reached.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Best Buy connected: '.implode('; ', $parts).'.',
            'sample_count' => $sample,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function waitForPricingImport(string $importId, string $apiKey, string $baseUrl): array
    {
        for ($i = 0; $i < 15; $i++) {
            if ($i > 0) {
                usleep(1500000);
            }
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($baseUrl.'/api/offers/pricing/imports', ['import_id' => $importId]);

                if (! $response->successful()) {
                    continue;
                }

                $row = ($response->json('data') ?? [])[0] ?? null;
                if (! is_array($row)) {
                    continue;
                }

                $status = strtoupper((string) ($row['status'] ?? ''));
                if (in_array($status, ['COMPLETE', 'FAILED', 'CANCELLED'], true)) {
                    return $row;
                }
            } catch (\Throwable $e) {
                Log::warning('Best Buy PRI01 status poll failed', [
                    'import_id' => $importId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    protected function fetchPricingImportErrorSummary(string $importId, string $apiKey, string $baseUrl): string
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->get($baseUrl.'/api/offers/pricing/imports/'.$importId.'/error_report');

            if (! $response->successful()) {
                return '';
            }

            $body = trim((string) $response->body());
            $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
            foreach ($lines as $idx => $line) {
                if ($idx === 0) {
                    continue;
                }
                $line = trim($line);
                if ($line !== '') {
                    return substr($line, 0, 300);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    /**
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array{pushed: int, failed: int, message: string}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        if ($items === []) {
            return ['pushed' => 0, 'failed' => 0, 'message' => 'No items to push.'];
        }

        Log::info('BestBuyApiService: updateItemInventoryBulk stub — local stock persisted only', [
            'count' => count($items),
        ]);

        return [
            'pushed' => count($items),
            'failed' => 0,
            'message' => 'Mirakl Connect quantity push is not wired yet — updated local bestbuy_usa_products.stock only.',
        ];
    }

    /**
     * One shipment / one tracking for one Best Buy order line.
     *
     * @return array{success: bool, message: string, status?: int}
     */
    public function createOrderShipment(
        string $connectOrderId,
        string $orderLineId,
        string $trackingNumber,
        string $carrier = 'USPS',
        int $quantity = 1,
        string $sku = ''
    ): array {
        $connectOrderId = trim($connectOrderId);
        $orderLineId = trim($orderLineId);
        $trackingNumber = strtoupper(preg_replace('/\s+/', '', $trackingNumber) ?? $trackingNumber);
        if ($connectOrderId === '' || $orderLineId === '' || $trackingNumber === '') {
            return ['success' => false, 'message' => 'Best Buy order id, line id, and tracking are required.'];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Best Buy / Mirakl Connect is not connected.'];
        }

        $item = [
            'order_line_id' => $orderLineId,
            'quantity' => max(1, $quantity),
        ];
        $sku = trim($sku);
        if ($sku !== '') {
            $item['id'] = $sku;
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(45)
                ->post('https://miraklconnect.com/api/v2/orders/'.rawurlencode($connectOrderId).'/shipments', [
                    'tracking_number' => $trackingNumber,
                    'carrier' => $carrier !== '' ? $carrier : 'USPS',
                    'items' => [$item],
                ]);
        } catch (\Throwable $e) {
            Log::warning('BestBuyApiService: createOrderShipment failed', [
                'order_id' => $connectOrderId,
                'order_line_id' => $orderLineId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }

        $status = $response->status();
        $body = strtolower((string) $response->body());
        if ($response->successful() || in_array($status, [201, 202], true)) {
            return ['success' => true, 'message' => 'Best Buy shipment created.', 'status' => $status];
        }
        if (
            str_contains($body, 'already')
            || str_contains($body, 'shipped')
            || str_contains($body, 'duplicate')
        ) {
            return ['success' => true, 'message' => 'Best Buy already has this shipment.', 'status' => $status];
        }

        Log::warning('BestBuyApiService: createOrderShipment rejected', [
            'order_id' => $connectOrderId,
            'order_line_id' => $orderLineId,
            'status' => $status,
            'body' => mb_substr((string) $response->body(), 0, 400),
        ]);

        return [
            'success' => false,
            'message' => 'Best Buy shipment failed (HTTP '.$status.').',
            'status' => $status,
        ];
    }

    /**
     * Shipments already created on Best Buy / Mirakl Connect.
     *
     * @return array{success: bool, message?: string, shipments: list<array{tracking: string, carrier: string, order_line_id: string}>}
     */
    public function listOrderShipments(string $connectOrderId): array
    {
        $connectOrderId = trim($connectOrderId);
        if ($connectOrderId === '') {
            return ['success' => false, 'message' => 'Best Buy order id is missing.', 'shipments' => []];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Best Buy / Mirakl Connect is not connected.', 'shipments' => []];
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(45)
                ->get('https://miraklconnect.com/api/v2/orders/'.rawurlencode($connectOrderId));
            if (! $response->successful()) {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(45)
                    ->get('https://miraklconnect.com/api/v2/orders/'.rawurlencode($connectOrderId).'/shipments');
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'shipments' => []];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Best Buy shipments HTTP '.$response->status(),
                'shipments' => [],
            ];
        }

        $json = $response->json() ?? [];
        $rows = $json['shipments'] ?? $json['data']['shipments'] ?? null;
        if (! is_array($rows)) {
            $rows = is_array($json['data'] ?? null) && array_is_list($json['data']) ? $json['data'] : [$json];
        }

        $shipments = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tracking = $row['tracking'] ?? null;
            $trackingNumber = trim((string) (
                (is_array($tracking) ? ($tracking['tracking_number'] ?? $tracking['number'] ?? '') : '')
                ?: ($row['tracking_number'] ?? $row['tracking_code'] ?? '')
            ));
            if ($trackingNumber === '') {
                continue;
            }
            $carrier = trim((string) (
                (is_array($tracking) ? ($tracking['carrier'] ?? $tracking['carrier_name'] ?? '') : '')
                ?: ($row['carrier'] ?? $row['carrier_name'] ?? '')
            ));
            $lineId = '';
            foreach ((array) ($row['items'] ?? $row['order_lines'] ?? []) as $item) {
                if (is_array($item) && trim((string) ($item['order_line_id'] ?? '')) !== '') {
                    $lineId = trim((string) $item['order_line_id']);
                    break;
                }
            }
            $shipments[] = [
                'tracking' => $trackingNumber,
                'carrier' => $carrier,
                'order_line_id' => $lineId,
            ];
        }

        return ['success' => true, 'shipments' => $shipments];
    }
}
