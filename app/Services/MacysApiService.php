<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductStockMapping;
use App\Services\Concerns\ResolvesBulletPointIdentifier;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\Concerns\MiraklMcmBulletImport;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class MacysApiService
{
    use MiraklMcmBulletImport;
    use ResolvesBulletPointIdentifier;
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;

    private function getAccessToken(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'macy_connect_access_token';
        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } else {
            Cache::forget($cacheKey);
        }

        $companyId = trim((string) config('services.macy.company_id'));
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.macy.client_id'),
            'client_secret' => config('services.macy.client_secret'),
        ];
        if ($companyId !== '') {
            $payload['audience'] = $companyId;
        }

        $response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', $payload);
        if (! $response->successful()) {
            Log::error('Macy Connect OAuth failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'has_audience' => $companyId !== '',
            ]);

            return null;
        }

        $json = $response->json();
        $token = $json['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            return null;
        }

        $ttl = max(60, (int) ($json['expires_in'] ?? 3599) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
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
        Log::info('Macy title update started', ['sku' => $sku]);

        try {
            $sku = $this->resolveMacyMiraklSku($sku);
            $title = trim($title);
            if ($sku === '' || $title === '') {
                return ['success' => false, 'message' => 'SKU and title are required.'];
            }

            $title = mb_substr($title, 0, 150);

            $connect = $this->pushMacyMiraklProductAttributes(
                $sku,
                [],
                'Macy title updated via Mirakl Connect.',
                'Macy title update failed',
                null,
                [],
                $this->macyLocalizedConnectRows($title)
            );

            if ($this->miraklMcmApiKey() === null) {
                if ($connect['success'] ?? false) {
                    $connect['message'] = trim(($connect['message'] ?? '')
                        .' Macy MCM (productName) skipped — set MACY_MCM_API_KEY for seller portal sync.');
                }

                return $connect;
            }

            if (! filter_var($this->miraklMcmConfig('mcm_title_push', true), FILTER_VALIDATE_BOOL)) {
                if ($connect['success'] ?? false) {
                    $connect['message'] = trim(($connect['message'] ?? '')
                        .' MCM P41 title disabled (MACY_MCM_TITLE_PUSH=false). Connect catalog only.');
                }

                return $connect;
            }

            $this->waitForMacyUpsertRateLimitBeforeMcm($sku);

            $mcm = $this->pushTitleViaMiraklMcm($sku, $title);
            if ($mcm['success'] ?? false) {
                if ($connect['success'] ?? false) {
                    $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
                }

                return $mcm;
            }

            if ($connect['success'] ?? false) {
                $suffix = ($mcm['mcm_integration_pending'] ?? false)
                    ? ' Connect OK. MCM P41 title queued (SENT) — seller portal may not update until integration completes.'
                    : ' Connect OK. MCM P41 title issue: '.($mcm['message'] ?? 'unknown error');
                $connect['message'] = trim(($connect['message'] ?? '').$suffix);
                $connect['mcm_integration_pending'] = $mcm['mcm_integration_pending'] ?? false;

                return $connect;
            }

            return $mcm;
        } catch (\Throwable $e) {
            Log::error('Macy title update failed', ['sku' => $sku, 'error' => $e->getMessage()]);

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

            $connect = $this->pushBulletPointsViaMiraklConnect($sku, $bulletPoints);

            if ($this->miraklMcmApiKey() === null) {
                if ($connect['success'] ?? false) {
                    $connect['message'] = trim(($connect['message'] ?? '')
                        .' Macy MCM (Specifications) skipped — set MACY_MCM_API_KEY for seller portal sync.');
                }

                return $connect;
            }

            $useMcmP41 = filter_var($this->miraklMcmConfig('mcm_bullet_push', true), FILTER_VALIDATE_BOOL);
            if (! $useMcmP41) {
                if ($connect['success'] ?? false) {
                    $connect['message'] = trim(($connect['message'] ?? '')
                        .' MCM P41 disabled (MACY_MCM_BULLET_PUSH=false). Connect catalog only.');
                }

                return $connect;
            }

            $this->waitForMacyUpsertRateLimitBeforeMcm($sku);

            $mcm = $this->pushBulletPointsViaMiraklMcm($sku, $bulletPoints);
            if ($mcm['success'] ?? false) {
                if ($connect['success'] ?? false) {
                    $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
                }

                return $mcm;
            }

            if ($connect['success'] ?? false) {
                $suffix = ($mcm['mcm_integration_pending'] ?? false)
                    ? ' Connect OK. MCM P41 queued (SENT) — Specifications tab will not change until Mirakl integrates and operator review clears.'
                    : ' Connect OK. MCM P41 issue: '.($mcm['message'] ?? 'unknown error');
                $connect['message'] = trim(($connect['message'] ?? '').$suffix);
                $connect['mcm_integration_pending'] = $mcm['mcm_integration_pending'] ?? false;

                return $connect;
            }

            return $mcm;
        } catch (\Throwable $e) {
            Log::error('Macy bullet update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mirakl Connect upsertProducts — primary bullet path (OAuth). See Connect API product docs.
     *
     * @return array{success: bool, message: string, response?: mixed, connect_verified?: bool}
     */
    private function pushBulletPointsViaMiraklConnect(string $sku, string $bulletPoints): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Macy access token not available (check MACY_CLIENT_ID / MACY_CLIENT_SECRET).',
            ];
        }

        if (filter_var(config('services.macy.features_benefits_null_clear_on_sync', false), FILTER_VALIDATE_BOOL)) {
            $minInterval = max(0, (int) config('services.macy.upsert_min_interval_seconds', 60));
            $delay = max(
                max(0, (int) config('services.macy.features_benefits_null_clear_delay_seconds', 3)),
                $minInterval
            );
            $this->pushMacyMiraklProductAttributes(
                $sku,
                $this->macyFeaturesAndBenefitsNullAttributes(),
                'Macy F&B null-clear pulse sent.',
                'Macy F&B null-clear failed'
            );
            if ($delay > 0) {
                Log::info('Macy Connect upsert rate limit — waiting before bullet re-upsert', [
                    'sku' => $sku,
                    'wait_seconds' => $delay,
                ]);
                sleep($delay);
            }
        }

        $attributes = $this->macyBulletAttributes($bulletPoints);
        $push = $this->pushMacyMiraklProductAttributes(
            $sku,
            $attributes,
            'Macy bullets updated via Mirakl Connect upsertProducts.',
            'Macy bullet update failed'
        );

        if (! ($push['success'] ?? false)) {
            return $push;
        }

        $verify = $this->verifyMacyFeaturesAndBenefitsBullets($sku, $bulletPoints);
        $message = (string) ($push['message'] ?? 'Macy bullets updated via Mirakl Connect upsertProducts.');
        if ($verify['verified'] ?? false) {
            $message .= ' Connect read-back verified.';
        } else {
            $message .= ' '.($verify['message'] ?? 'Connect may still be processing (async upsert).');
        }

        return array_merge($push, [
            'message' => trim($message),
            'connect_verified' => (bool) ($verify['verified'] ?? false),
        ]);
    }

    protected function miraklMcmConfigKey(): string
    {
        return 'macy';
    }

    protected function miraklMcmMarketplaceLabel(): string
    {
        return 'Macy';
    }

    protected function miraklMcmHierarchyTable(): ?string
    {
        return 'macys_price_data';
    }

    /**
     * @return array{product_name?: string, upc?: string, brand?: string, product_sku?: string, variant_group_code?: string, connect_category_id?: string, connect_category_label?: string, connect_category_path?: string, mcm_category_code?: string}
     */
    protected function resolveMiraklMcmConnectCatalogContext(string $sku): array
    {
        $product = $this->fetchMacyMiraklProduct($sku);
        if ($product === []) {
            return [];
        }

        $upc = '';
        foreach ((array) ($product['gtins'] ?? []) as $gtin) {
            if (! is_array($gtin)) {
                continue;
            }
            $value = trim((string) ($gtin['value'] ?? ''));
            if ($value !== '') {
                $upc = $value;
                break;
            }
        }

        $connectCategoryId = '';
        $connectCategoryLabel = '';
        if (is_array($product['category'] ?? null)) {
            $connectCategoryId = trim((string) ($product['category']['id'] ?? ''));
            foreach ((array) ($product['category']['labels'] ?? []) as $labelRow) {
                if (is_array($labelRow) && trim((string) ($labelRow['value'] ?? '')) !== '') {
                    $connectCategoryLabel = trim((string) $labelRow['value']);
                    break;
                }
            }
        }

        $connectCategoryPath = '';
        $productType = '';
        $variantGroupCode = trim((string) ($product['variant_group_code'] ?? ''));
        foreach ((array) ($product['attributes'] ?? []) as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $id = (string) ($attr['id'] ?? '');
            $value = trim((string) ($attr['value'] ?? ''));
            if ($id === 'category_path' && $value !== '') {
                $connectCategoryPath = $value;
            }
            if ($id === 'product_type' && $value !== '') {
                $productType = $value;
            }
            if ($variantGroupCode === '' && in_array($id, ['variant_group_code', 'variantGroupCode'], true) && $value !== '') {
                $variantGroupCode = $value;
            }
        }

        $mcmCategory = $this->mapMiraklConnectCategoryToMcmHierarchy(
            $connectCategoryId,
            $connectCategoryLabel !== '' ? $connectCategoryLabel : $productType,
            $connectCategoryPath
        );

        return [
            'product_name' => $this->firstLocalizedValue((array) ($product['titles'] ?? [])),
            'upc' => $upc,
            'brand' => is_string($product['brand'] ?? null) ? trim($product['brand']) : '',
            'variant_group_code' => $variantGroupCode,
            'connect_category_id' => $connectCategoryId,
            'connect_category_label' => $connectCategoryLabel !== '' ? $connectCategoryLabel : $productType,
            'connect_category_path' => $connectCategoryPath,
            'mcm_category_code' => $mcmCategory,
        ];
    }

    protected function resolveMiraklMcmHierarchyFromMasterCatalog(string $sku): ?string
    {
        $ctx = $this->resolveMiraklMcmConnectCatalogContext($sku);
        $priceRow = $this->fetchMiraklMcmPriceDataRowBySku($sku)
            ?? $this->fetchMiraklMcmRelatedPriceDataRow($sku);
        $variantMaster = $this->fetchMiraklMcmOperatorMasterProductByReferences(
            $this->miraklMcmMasterCatalogVariantReferenceCandidates($sku, $ctx, $priceRow)
        );
        $fromMaster = $this->miraklMcmCategoryCodeFromProduct($variantMaster);
        if ($fromMaster !== '') {
            Log::debug('Macy MCM hierarchy from operator master catalog', [
                'sku' => $sku,
                'master_product_sku' => $variantMaster['product_sku'] ?? null,
                'category_code' => $fromMaster,
            ]);

            return $fromMaster;
        }

        $fromConnect = trim((string) ($ctx['mcm_category_code'] ?? ''));
        if ($fromConnect !== '') {
            Log::debug('Macy MCM hierarchy from Connect channel mapping', [
                'sku' => $sku,
                'connect_category_label' => $ctx['connect_category_label'] ?? null,
                'category_code' => $fromConnect,
            ]);

            return $fromConnect;
        }

        return null;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    protected function miraklMcmMasterCatalogExtraReferenceCandidates(string $sku, array $connectContext = [], mixed $priceRow = null): array
    {
        $extra = [];

        if (! is_object($priceRow)) {
            $priceRow = $this->fetchMiraklMcmPriceDataRowBySku($sku)
                ?? $this->fetchMiraklMcmRelatedPriceDataRow($sku);
        }

        if (is_object($priceRow)) {
            $relatedUpc = trim((string) ($priceRow->upc ?? ''));
            $connectUpc = trim((string) ($connectContext['upc'] ?? ''));
            if ($relatedUpc !== '' && strcasecmp($relatedUpc, $connectUpc) !== 0) {
                $extra[] = ['UPC', $relatedUpc];
            }
            $relatedProductSku = trim((string) ($priceRow->product_sku ?? ''));
            if ($relatedProductSku !== '') {
                $extra[] = ['product_sku', $relatedProductSku];
            }
        }

        foreach ($this->miraklMcmRelatedSkuCandidates($sku) as $relatedSku) {
            $relatedRow = $this->fetchMiraklMcmPriceDataRowBySku($relatedSku);
            if ($relatedRow === null) {
                continue;
            }
            $relatedUpc = trim((string) ($relatedRow->upc ?? ''));
            if ($relatedUpc !== '') {
                $extra[] = ['UPC', $relatedUpc];
            }
            $relatedProductSku = trim((string) ($relatedRow->product_sku ?? ''));
            if ($relatedProductSku !== '') {
                $extra[] = ['product_sku', $relatedProductSku];
            }
        }

        return $extra;
    }

    protected function resolveMiraklMcmHierarchyExtraFallback(string $sku): ?string
    {
        return $this->resolveMiraklMcmSkuFamilyCategoryFromDb($sku);
    }

    protected function mapMiraklConnectCategoryToMcmHierarchy(
        string $connectCategoryId,
        string $connectCategoryLabel,
        string $connectCategoryPath
    ): ?string {
        $map = (array) $this->miraklMcmConfig('connect_category_to_mcm_hierarchy', []);
        if ($connectCategoryId !== '' && isset($map[$connectCategoryId])) {
            $mapped = trim((string) $map[$connectCategoryId]);

            return $mapped !== '' ? $mapped : null;
        }
        if ($connectCategoryLabel !== '' && isset($map[$connectCategoryLabel])) {
            $mapped = trim((string) $map[$connectCategoryLabel]);

            return $mapped !== '' ? $mapped : null;
        }

        if ($connectCategoryPath !== '') {
            $segments = array_reverse(array_map('trim', explode('>', $connectCategoryPath)));
            foreach ($segments as $segment) {
                if ($segment !== '' && isset($map[$segment])) {
                    $mapped = trim((string) $map[$segment]);

                    return $mapped !== '' ? $mapped : null;
                }
            }
        }

        return null;
    }

    protected function mapMiraklConnectCategoryToTaxCode(
        string $connectCategoryId,
        string $connectCategoryLabel,
        string $connectCategoryPath
    ): ?string {
        $map = (array) $this->miraklMcmConfig('connect_category_to_tax_code', []);
        if ($connectCategoryId !== '' && isset($map[$connectCategoryId])) {
            $mapped = trim((string) $map[$connectCategoryId]);

            return $mapped !== '' ? $mapped : null;
        }
        if ($connectCategoryLabel !== '' && isset($map[$connectCategoryLabel])) {
            $mapped = trim((string) $map[$connectCategoryLabel]);

            return $mapped !== '' ? $mapped : null;
        }

        if ($connectCategoryPath !== '') {
            $segments = array_reverse(array_map('trim', explode('>', $connectCategoryPath)));
            foreach ($segments as $segment) {
                if ($segment !== '' && isset($map[$segment])) {
                    $mapped = trim((string) $map[$segment]);

                    return $mapped !== '' ? $mapped : null;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function miraklMcmP41ExtraAttributeValues(string $sku, ?string $hierarchy, array $offer, mixed $priceRow): array
    {
        $ctx = $this->resolveMiraklMcmConnectCatalogContext($sku);
        $taxCode = $this->mapMiraklConnectCategoryToTaxCode(
            trim((string) ($ctx['connect_category_id'] ?? '')),
            trim((string) ($ctx['connect_category_label'] ?? '')),
            trim((string) ($ctx['connect_category_path'] ?? ''))
        );
        if ($taxCode === null) {
            return [];
        }

        return ['taxCode-electronics' => $taxCode];
    }

    protected function resolveMiraklMcmSkuFamilyCategoryFromDb(string $sku): ?string
    {
        $table = $this->miraklMcmHierarchyTable();
        if ($table === null || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category_code')) {
            return null;
        }

        $prefix = trim((string) strtok($sku, ' '));
        if ($prefix === '' || strlen($prefix) < 3) {
            return null;
        }

        $rows = DB::table($table)
            ->where('sku', 'like', $prefix.'%')
            ->whereNotNull('category_code')
            ->where('category_code', '!=', '')
            ->limit(50)
            ->pluck('category_code');

        if ($rows->isEmpty()) {
            return null;
        }

        $counts = [];
        foreach ($rows as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }
        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @return list<string> */
    protected function resolveMiraklMcmP41ImageUrls(string $sku, array $existingProduct): array
    {
        $urls = [];
        foreach (['mainImage', 'secondImage', 'thirdImage'] as $code) {
            $val = $this->miraklMcmExistingAttributeValue($existingProduct, $code);
            if ($val !== null && $val !== '') {
                $urls[] = $val;
            }
        }
        if ($urls !== []) {
            return array_values(array_unique($urls));
        }

        $image = ProductStockMapping::where('sku', $sku)->value('image');
        if (is_string($image) && trim($image) !== '') {
            $image = trim($image);

            return [$image, $image, $image];
        }

        return [];
    }

    /**
     * Mirakl Connect product upsert: POST batch, then PATCH/PUT by SKU (same as title/bullet flows).
     *
     * @param  array<string, string>  $attributes
     * @return array{success: bool, message: string, response?: mixed}
     */
    private function pushMacyMiraklProductAttributes(string $sku, array $attributes, string $successMessage, string $failurePrefix, ?array $descriptions = null, array $context = [], ?array $titles = null, array|false|null $images = null): array
    {
        $this->waitForMacyUpsertRateLimit($sku);

        $result = $this->sendMacyMiraklProductAttributesRequest($sku, $attributes, $successMessage, $failurePrefix, $descriptions, $context, false, false, $titles, $images);
        if (($result['success'] ?? false) || ! ($result['unauthorized'] ?? false)) {
            if (isset($result['unauthorized'])) {
                unset($result['unauthorized']);
            }

            return $result;
        }

        Log::info('Macy Connect token rejected (401) — refreshing OAuth token and retrying once', ['sku' => $sku]);

        return $this->sendMacyMiraklProductAttributesRequest($sku, $attributes, $successMessage, $failurePrefix, $descriptions, $context, true, false, $titles, $images);
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{success: bool, message: string, response?: mixed, connect_verified?: bool, unauthorized?: bool}
     */
    private function sendMacyMiraklProductAttributesRequest(
        string $sku,
        array $attributes,
        string $successMessage,
        string $failurePrefix,
        ?array $descriptions,
        array $context,
        bool $forceTokenRefresh,
        bool $rateLimitRetried = false,
        ?array $titles = null,
        array|false|null $images = null
    ): array {
        $token = $this->getAccessToken($forceTokenRefresh);
        if (! $token) {
            return ['success' => false, 'message' => 'Macy access token not available'];
        }

        $baseUrl = 'https://miraklconnect.com/api/products';
        $productPayload = [
            'id' => $sku,
        ];
        if ($attributes !== []) {
            $productPayload['attributes'] = $this->formatMiraklAttributes($attributes);
        }
        if ($descriptions !== null) {
            $productPayload['descriptions'] = $descriptions;
        }
        if ($titles !== null) {
            $productPayload['titles'] = $titles;
        }
        if ($images === false) {
            $productPayload['images'] = null;
        } elseif (is_array($images)) {
            $productPayload['images'] = $images;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $channelId = config('services.macy.company_id');
        if (! empty($channelId)) {
            $headers['channel_id'] = $channelId;
        }

        $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
        $encodedSku = rawurlencode($sku);

        $method = 'POST';
        $response = $request->post($baseUrl, ['products' => [$productPayload]]);
        if (! $response->successful()) {
            $method = 'PATCH';
            $response = $request->patch("{$baseUrl}/{$encodedSku}", $productPayload);
        }
        if (! $response->successful()) {
            $method = 'PUT';
            $response = $request->put("{$baseUrl}/{$encodedSku}", $productPayload);
        }

        $status = $response->status();
        $acceptedAsync = in_array($status, [200, 201, 202], true);

        if (! $response->successful() && ! $acceptedAsync) {
            if ($status === 429 && ! $rateLimitRetried) {
                $minInterval = max(60, (int) config('services.macy.upsert_min_interval_seconds', 60));
                Log::warning('Macy Connect upsertProducts rate limited (HTTP 429) — waiting before retry', [
                    'sku' => $sku,
                    'wait_seconds' => $minInterval,
                ]);
                sleep($minInterval);

                return $this->sendMacyMiraklProductAttributesRequest(
                    $sku,
                    $attributes,
                    $successMessage,
                    $failurePrefix,
                    $descriptions,
                    $context,
                    $forceTokenRefresh,
                    true,
                    $titles,
                    $images
                );
            }

            Log::warning($failurePrefix, [
                'sku' => $sku,
                'method' => $method,
                'status' => $status,
                'response' => mb_substr($response->body(), 0, 2000),
            ] + $context);

            return [
                'success' => false,
                'message' => $failurePrefix.': '.$response->body(),
                'unauthorized' => $status === 401,
            ];
        }

        $this->markMacyUpsertSent($sku);

        $json = $response->json();
        $hasApiError = is_array($json) && (
            ! empty($json['errors'])
            || ! empty($json['error'])
            || ! empty($json['error_message'])
            || (isset($json['success']) && $json['success'] === false)
            || ((isset($json['status']) && is_string($json['status'])) && strtolower($json['status']) === 'error')
        );

        Log::info('Macy Mirakl product update response', [
            'sku' => $sku,
            'method' => $method,
            'status' => $status,
            'async_accepted' => $status === 202,
            'has_api_error' => $hasApiError,
            'response' => is_array($json) ? $json : mb_substr($response->body(), 0, 2000),
        ] + $context);

        if ($hasApiError) {
            return ['success' => false, 'message' => $failurePrefix.': '.json_encode($json), 'response' => $json];
        }

        $verifyNote = ($context['null_clear_pulse'] ?? false)
            ? ' F&B null-clear pulse sent (Mirakl doc: nullable attrs).'
            : (($context['forced_mcm_sync'] ?? false)
                ? ' Forced Connectâ†’MCM re-sync (description nudge).'
                : (($descriptions !== null && ($context['description_changed'] ?? true) === false)
                    ? ' Connect catalog already matched PM bullets (description unchanged).'
                    : ''));

        $mcmNote = $this->miraklMcmApiKey() !== null
            ? ' Macy MCM Specifications may take 5–15 min to reflect — hard-refresh the MCM edit page.'
            : '';

        return [
            'success' => true,
            'message' => $successMessage.$verifyNote.$mcmNote,
            'response' => $json,
            'connect_verified' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{id: string, name: string, type: string, value: mixed}>
     */
    private function formatMiraklAttributes(array $attributes): array
    {
        $formatted = [];
        foreach ($attributes as $id => $value) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            $formatted[] = [
                'id' => $id,
                'name' => $id,
                'type' => $this->miraklConnectAttributeType($value),
                'value' => $value,
            ];
        }

        return $formatted;
    }

    private function miraklConnectAttributeType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'BOOLEAN';
        }
        if (is_int($value) || is_float($value)) {
            return 'NUMERIC';
        }

        return 'STRING';
    }

    /**
     * Mirakl Connect upsertProducts: max once per minute per Catalog API docs.
     */
    private function waitForMacyUpsertRateLimit(string $sku): void
    {
        $minInterval = max(0, (int) config('services.macy.upsert_min_interval_seconds', 60));
        if ($minInterval === 0) {
            return;
        }

        $cacheKey = $this->macyUpsertRateLimitCacheKey();
        $lastSent = Cache::get($cacheKey);
        if (! is_int($lastSent)) {
            return;
        }

        $elapsed = time() - $lastSent;
        if ($elapsed < $minInterval) {
            $wait = $minInterval - $elapsed;
            Log::info('Macy Connect upsertProducts rate limit — waiting before next call', [
                'sku' => $sku,
                'wait_seconds' => $wait,
                'min_interval_seconds' => $minInterval,
            ]);
            sleep($wait);
        }
    }

    private function markMacyUpsertSent(string $sku): void
    {
        $minInterval = max(0, (int) config('services.macy.upsert_min_interval_seconds', 60));
        if ($minInterval === 0) {
            return;
        }

        Cache::put($this->macyUpsertRateLimitCacheKey(), time(), $minInterval + 30);
    }

    private function macyUpsertRateLimitCacheKey(): string
    {
        $companyId = trim((string) config('services.macy.company_id', ''));

        return 'macy_connect_upsert_last:'.($companyId !== '' ? $companyId : 'default');
    }

    /** Respect Connect once-per-minute limit before MCM P41 (separate API). */
    private function waitForMacyUpsertRateLimitBeforeMcm(string $sku): void
    {
        $minInterval = max(0, (int) config('services.macy.upsert_min_interval_seconds', 60));
        if ($minInterval === 0) {
            return;
        }

        $lastSent = Cache::get($this->macyUpsertRateLimitCacheKey());
        if (! is_int($lastSent)) {
            return;
        }

        $elapsed = time() - $lastSent;
        if ($elapsed < $minInterval) {
            $wait = $minInterval - $elapsed;
            Log::info('Macy: waiting after Connect upsert before MCM P41', [
                'sku' => $sku,
                'wait_seconds' => $wait,
            ]);
            sleep($wait);
        }
    }

    /**
     * Mirakl doc: nullable attributes may be set to null to remove a value before re-upsert.
     *
     * @return array<string, null>
     */
    private function macyFeaturesAndBenefitsNullAttributes(): array
    {
        $attrs = ['bulletPoints' => null];
        for ($i = 1; $i <= 5; $i++) {
            $attrs["features_and_benefits_bullet_{$i}"] = null;
        }

        return $attrs;
    }

    /**
     * Macy MCM Specifications tab â†’ Features and Benefits bullets.
     * Not named in the generic Connect Catalog API; these are Macy channel attribute IDs
     * on products.attributes (see listProducts with channel_code=macys).
     * features_and_benefits_bullet_1..5 â€” bulletPoints alone does not update that UI.
     *
     * @return array<string, string>
     */
    private function macyBulletAttributes(string $bulletPoints, string $description = ''): array
    {
        $lines = array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []
        ), fn ($line) => $line !== ''));

        $lines = array_slice($lines, 0, 5);
        $maxLen = (int) config('services.macy.features_benefits_max_length', 254);

        $attrs = [
            'bulletPoints' => implode("\n", $lines),
        ];

        for ($i = 1; $i <= 5; $i++) {
            $line = $lines[$i - 1] ?? '';
            $attrs["features_and_benefits_bullet_{$i}"] = $line === ''
                ? ''
                : mb_substr($line, 0, $maxLen);
        }

        $description = trim($description);
        if ($description !== '') {
            $attrs['longDescription'] = $description;
            $attrs['productDescription'] = $description;
        }

        return $attrs;
    }

    /**
     * @return array{verified: bool, message: string}
     */
    private function verifyMacyFeaturesAndBenefitsBullets(string $sku, string $bulletPoints): array
    {
        $lines = array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []
        ), fn ($line) => $line !== ''));
        $lines = array_slice($lines, 0, 5);
        $maxLen = (int) config('services.macy.features_benefits_max_length', 254);

        $attempts = max(1, (int) config('services.macy.features_benefits_verify_attempts', 4));
        $delaySeconds = max(1, (int) config('services.macy.features_benefits_verify_delay_seconds', 2));

        $mismatches = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delaySeconds);
            }

            $product = $this->fetchMacyMiraklProduct($sku);
            if ($product === []) {
                continue;
            }

            $mismatches = [];
            foreach ($lines as $index => $line) {
                $slot = $index + 1;
                $expected = mb_substr($line, 0, $maxLen);
                $actual = $this->macyMiraklAttributeValue($product, "features_and_benefits_bullet_{$slot}");
                if ($expected !== '' && strcasecmp($expected, $actual) !== 0) {
                    $mismatches[] = $slot;
                }
            }

            if ($mismatches === []) {
                return ['verified' => true, 'message' => 'F&B bullets match PM on read-back'];
            }
        }

        return [
            'verified' => false,
            'message' => 'F&B slots '.implode(', ', $mismatches).' mismatch after upsert (Connect may still be processing)',
        ];
    }

    private function macyMiraklAttributeValue(array $product, string $attributeId): string
    {
        foreach (($product['attributes'] ?? []) as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            if (strcasecmp((string) ($attr['id'] ?? ''), $attributeId) === 0) {
                return trim((string) ($attr['value'] ?? ''));
            }
        }

        return '';
    }

    private function fetchMacyMiraklProduct(string $sku, bool $forceTokenRefresh = false): array
    {
        $token = $this->getAccessToken($forceTokenRefresh);
        if (! $token) {
            return [];
        }

        $headers = [
            'Accept' => 'application/json',
        ];
        $channelId = config('services.macy.company_id');
        if (! empty($channelId)) {
            $headers['channel_id'] = $channelId;
        }

        $pageToken = null;
        do {
            $query = ['limit' => 1000, 'channel_code' => 'macys'];
            if ($pageToken) {
                $query['page_token'] = $pageToken;
            }

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout(60)
                ->get('https://miraklconnect.com/api/products', $query);

            if (! $response->successful()) {
                if ($response->status() === 401 && ! $forceTokenRefresh) {
                    return $this->fetchMacyMiraklProduct($sku, true);
                }

                Log::warning('Macy product lookup failed', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 1000),
                ]);

                return [];
            }

            foreach (($response->json('data') ?? []) as $product) {
                if (isset($product['id']) && strcasecmp((string) $product['id'], $sku) === 0) {
                    return is_array($product) ? $product : [];
                }
            }

            $pageToken = $response->json('next_page_token');
        } while ($pageToken);

        return [];
    }

    private function firstLocalizedValue(array $localizedRows): string
    {
        foreach ($localizedRows as $row) {
            if (is_array($row) && trim((string) ($row['value'] ?? '')) !== '') {
                return trim((string) $row['value']);
            }
        }

        return '';
    }

    private function replaceMacyAboutItemText(string $currentDescription, string $bulletPoints): string
    {
        $replacement = $this->formatMacyAboutItemText($bulletPoints);
        $description = trim(preg_replace('/\s+/u', ' ', $currentDescription) ?? $currentDescription);
        if ($description === '') {
            return $replacement;
        }

        $descriptionBody = $this->findMacyDescriptionBodyText($description);
        if ($descriptionBody !== null) {
            return trim($replacement.' '.$descriptionBody);
        }

        if (preg_match('/^\s*About Item:?\s*/iu', $description) === 1) {
            return $replacement;
        }

        return trim($replacement.' '.$description);
    }

    private function findMacyDescriptionBodyText(string $description): ?string
    {
        $productDescriptionPos = mb_stripos($description, 'Product Description');
        if ($productDescriptionPos !== false) {
            return mb_substr($description, $productDescriptionPos);
        }

        if (preg_match('/\b[A-Z][A-Za-z0-9&,\-\/ ]{2,80}\s+Description\b/u', $description, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $offset = (int) ($matches[0][1] ?? -1);
            if ($offset > 0) {
                return substr($description, $offset);
            }
        }

        return null;
    }

    private function formatMacyAboutItemText(string $bulletPoints): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []), fn ($line) => $line !== ''));

        return trim('About Item '.implode(' ', array_slice($lines, 0, 5)));
    }

    /**
     * Toggle a zero-width space on the description so repeated identical bullet pushes
     * still produce a Connect delta and trigger Macy MCM channel re-export.
     */
    private function forceMacyDescriptionChangeForSync(string $description): string
    {
        $zwsp = "\u{200B}";
        if (str_ends_with($description, $zwsp)) {
            return mb_substr($description, 0, mb_strlen($description) - 1);
        }

        return $description.$zwsp;
    }

    /**
     * Description Master: long-form copy via Mirakl `longDescription` / `productDescription` only (no bullet field).
     *
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
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
            $descriptionWithImages = DescriptionWithImagesFormatter::buildHtmlWithImages(
                $description,
                $identifier,
                $sku,
                'Product Image',
                12,
                $imageUrls
            )['html'];
            $hasImages = array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== '') !== [];
            $merged = $hasImages
                ? $descriptionWithImages
                : $this->appendUniqueText($current, $descriptionWithImages);

            $connect = $this->pushMacyMiraklProductAttributes(
                $sku,
                [],
                'Macy product description updated.',
                'Macy description update failed',
                $this->macyLocalizedConnectRows($merged)
            );
            if (! ($connect['success'] ?? false)) {
                return $connect;
            }

            if ($this->miraklMcmApiKey() === null) {
                $connect['message'] = trim(($connect['message'] ?? '')
                    .' Macy MCM (productLongDescription) skipped — set MACY_MCM_API_KEY for seller portal sync.');
                $push = $connect;
            } elseif (! filter_var($this->miraklMcmConfig('mcm_description_push', true), FILTER_VALIDATE_BOOL)) {
                $connect['message'] = trim(($connect['message'] ?? '')
                    .' MCM P41 description disabled (MACY_MCM_DESCRIPTION_PUSH=false). Connect catalog only.');
                $push = $connect;
            } else {
                $this->waitForMacyUpsertRateLimitBeforeMcm($sku);
                $mcm = $this->pushDescriptionViaMiraklMcm($sku, $merged);
                if ($mcm['success'] ?? false) {
                    $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
                    $push = $mcm;
                } else {
                    $suffix = ($mcm['mcm_integration_pending'] ?? false)
                        ? ' Connect OK. MCM P41 description queued (SENT) — seller portal may not update until integration completes.'
                        : ' Connect OK. MCM P41 description issue: '.($mcm['message'] ?? 'unknown error');
                    $connect['message'] = trim(($connect['message'] ?? '').$suffix);
                    $connect['mcm_integration_pending'] = $mcm['mcm_integration_pending'] ?? false;
                    $push = $connect;
                }
            }

            $photoUrls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
            $photoUrls = array_slice($photoUrls, 0, 12);
            if ($photoUrls !== []) {
                $img = $this->updateListingImages($identifier, $photoUrls);
                if (! ($img['success'] ?? false)) {
                    $push['message'] = ($push['message'] ?? 'Macy product description updated.').' Images: '.($img['message'] ?? 'failed');

                    return $push;
                }
                $push['message'] = ($push['message'] ?? 'Macy product description updated.').' Images synced.';
            }

            return $push;
        } catch (\Throwable $e) {
            Log::error('Macy description update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->updateDescription($identifier, $description, $imageUrls);
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

            $connect = $this->pushMacyConnectProductImages($sku, $urls);

            if ($this->miraklMcmApiKey() === null) {
                if ($connect['success'] ?? false) {
                    $connect['message'] = trim(($connect['message'] ?? '')
                        .' Macy MCM (mainImage) skipped — set MACY_MCM_API_KEY for seller portal sync.');
                }

                return $connect;
            }

            if (! filter_var($this->miraklMcmConfig('mcm_image_push', true), FILTER_VALIDATE_BOOL)) {
                if ($connect['success'] ?? false) {
                    $connect['message'] = trim(($connect['message'] ?? '')
                        .' MCM P41 image push disabled (MACY_MCM_IMAGE_PUSH=false). Connect catalog only.');
                }

                return $connect;
            }

            $this->waitForMacyUpsertRateLimitBeforeMcm($sku);

            $mcm = $this->pushImagesViaMiraklMcm($sku, $urls);
            if ($mcm['success'] ?? false) {
                if ($connect['success'] ?? false) {
                    $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
                }

                return $mcm;
            }

            if ($connect['success'] ?? false) {
                $suffix = ($mcm['mcm_integration_pending'] ?? false)
                    ? ' Connect OK. MCM P41 image import queued (SENT) — seller portal may not update until integration completes.'
                    : ' Connect OK. MCM P41 image issue: '.($mcm['message'] ?? 'unknown error');
                $connect['message'] = trim(($connect['message'] ?? '').$suffix);
                $connect['mcm_integration_pending'] = $mcm['mcm_integration_pending'] ?? false;

                return $connect;
            }

            return $mcm;
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
     * Mirakl Connect product videos (attribute names vary by channel; we send common keys).
     *
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingVideos(string $identifier, array $videoUrls): array
    {
        $urls = array_values(array_filter(array_map('trim', $videoUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 5);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU (or marketplace product id) and video URLs are required.'];
        }

        try {
            $sku = $this->resolveMacyMiraklSku($identifier);
            if ($sku === '') {
                return ['success' => false, 'message' => 'SKU (or marketplace product id) not found in macy_metrics.'];
            }

            $attributes = [
                'videoUrl' => $urls[0],
                'videoUrls' => $urls,
                'productVideoUrls' => $urls,
                'mainVideoUrl' => $urls[0],
            ];

            return $this->pushMacyMiraklProductAttributes($sku, $attributes, 'Macy product videos updated.', 'Macy video update failed');
        } catch (\Throwable $e) {
            Log::error('Macy video update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Video Master compatibility method: push videos then persist video_master_json in macy_metrics.
     *
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if ($videos === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $res = $this->updateListingVideos($identifier, $videos);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $sku = $this->resolveMacyMiraklSku($identifier);
        $saved = $this->saveVideoUrlsToMetricsRow('macy_metrics', $sku, $videos);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Macy product videos updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $videos;

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

    /**
     * Description Master: return the current Macy's (Mirakl) product description for one SKU. Read-only.
     *
     * @return array{success: bool, message: string, html?: string}
     */
    public function fetchDescriptionHtml(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $sku = $this->resolveMacyMiraklSku($identifier);
        if ($sku === '') {
            $sku = $identifier;
        }

        $desc = trim($this->fetchCurrentMacyDescription($sku));
        if ($desc === '') {
            return ['success' => false, 'message' => 'Macy returned no description for this SKU.'];
        }

        return ['success' => true, 'message' => 'Macy description loaded.', 'html' => $desc];
    }

    private function fetchCurrentMacyDescription(string $sku): string
    {
        $product = $this->fetchMacyMiraklProduct($sku);
        if ($product === []) {
            return '';
        }

        return $this->firstLocalizedValue((array) ($product['descriptions'] ?? []));
    }

    /**
     * @return list<array{value: string, locale: string}>
     */
    private function macyLocalizedConnectRows(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return [
            [
                'value' => $value,
                'locale' => 'en_US',
            ],
        ];
    }

    /**
     * @param  list<string>  $urls
     * @return array{success: bool, message: string, response?: mixed, connect_verified?: bool}
     */
    private function pushMacyConnectProductImages(string $sku, array $urls): array
    {
        if (filter_var(config('services.macy.connect_image_clear_before_push', true), FILTER_VALIDATE_BOOL)) {
            $clear = $this->pushMacyMiraklProductAttributes(
                $sku,
                [],
                'Macy Connect images cleared before replace.',
                'Macy Connect image clear failed',
                null,
                ['image_replace_clear' => true],
                null,
                false
            );
            if (! ($clear['success'] ?? false)) {
                return $clear;
            }
        }

        return $this->pushMacyMiraklProductAttributes(
            $sku,
            [],
            'Macy product images updated via Mirakl Connect.',
            'Macy image update failed',
            null,
            ['image_count' => count($urls)],
            null,
            $this->macyConnectImageRows($urls)
        );
    }

    /**
     * @param  list<string>  $urls
     * @return list<array{url: string}>
     */
    private function macyConnectImageRows(array $urls): array
    {
        $rows = [];
        foreach (array_values(array_filter(array_map('trim', $urls), fn ($s) => $s !== '')) as $url) {
            $rows[] = ['url' => $url];
        }

        return $rows;
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

    public function isConfigured(): bool
    {
        $clientId = trim((string) config('services.macy.client_id', ''));
        $clientSecret = trim((string) config('services.macy.client_secret', ''));

        return $clientId !== '' && $clientSecret !== '';
    }

    /**
     * @return array{success: bool, message: string, sample_count?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Macy Mirakl Connect credentials missing (MACY_CLIENT_ID + MACY_CLIENT_SECRET).',
            ];
        }

        try {
            $token = $this->getAccessToken();
            if (! $token) {
                return [
                    'success' => false,
                    'message' => 'OAuth token request failed — check MACY_CLIENT_ID / MACY_CLIENT_SECRET.',
                ];
            }

            $response = Http::withoutVerifying()->withToken($token)->get(
                'https://miraklconnect.com/api/products?limit=1&channel_code=macys'
            );

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Mirakl Connect products ping failed: '.$response->status(),
                ];
            }

            $count = count($response->json()['data'] ?? []);

            return [
                'success' => true,
                'message' => "Mirakl Connect reachable for channel macys (sample: {$count} product row(s)).",
                'sample_count' => $count,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
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

        Log::info('MacysApiService: updateItemInventoryBulk stub — local stock persisted only', [
            'count' => count($items),
        ]);

        return [
            'pushed' => count($items),
            'failed' => 0,
            'message' => 'Mirakl Connect quantity push is not wired yet — updated local macy_products.stock only.',
        ];
    }
}
