<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\Concerns\ResolvesBulletPointIdentifier;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class WayfairApiService
{
    use ResolvesBulletPointIdentifier;
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;

    protected $token;
 protected $authUrl = 'https://sso.auth.wayfair.com/oauth/token';
    protected $graphqlUrl = 'https://api.wayfair.com/v1/graphql';
     protected $clientId;
    protected $clientSecret;
    protected $audience;
    protected $accessToken;
    protected $grantType = 'client_credentials';

    public function __construct()
    {
        $this->clientId = config('services.wayfair.client_id');
        $this->clientSecret = config('services.wayfair.client_secret');
        $this->audience = config('services.wayfair.audience');
    }

    /**
     * Authenticate with Wayfair and get access token (no scope). Lazy — not called from constructor.
     */
    protected function authenticate()
    {
        if (! empty($this->accessToken)) {
            return $this->accessToken;
        }

        $this->accessToken = $this->getAccessTokenWithScope(null);

        return $this->accessToken;
    }

    /**
     * Get access token, optionally with a specific scope (e.g. for catalog updates).
     * Use when catalog mutation returns "Access Denied" – set WAYFAIR_CATALOG_SCOPE or run wayfair:test-scopes.
     *
     * @param string|null $scope Optional scope, e.g. write:catalog_items. If null, uses config catalog_scope.
     * @return string JWT access token
     */
    public function getAccessTokenWithScope(?string $scope = null): string
    {
        // Trim defensively – stray whitespace/newlines/quotes pasted into .env are a common
        // cause of Wayfair returning {"error":"invalid_client"}.
        $clientId = trim((string) config('services.wayfair.client_id'), " \t\n\r\0\x0B\"'");
        $clientSecret = trim((string) config('services.wayfair.client_secret'), " \t\n\r\0\x0B\"'");

        if ($clientId === '' || $clientSecret === '') {
            throw new \Exception('Wayfair credentials missing: set WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET in .env (and run `php artisan config:clear`).');
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
        $audience = trim((string) config('services.wayfair.audience'));
        if ($audience !== '') {
            $payload['audience'] = $audience;
        }
        $scopeToUse = $scope ?? config('services.wayfair.catalog_scope');
        if ($scopeToUse !== null && $scopeToUse !== '') {
            $payload['scope'] = $scopeToUse;
        }

        $response = $this->oauthHttpClient()
            ->asForm()
            ->post($this->authUrl, $payload);

        if ($response->failed()) {
            $body = $response->body();
            $hint = '';
            if (stripos($body, 'invalid_client') !== false) {
                $hint = ' [Wayfair rejected the credentials. Verify WAYFAIR_CLIENT_ID / WAYFAIR_CLIENT_SECRET / WAYFAIR_AUDIENCE in production .env, '
                    . 'check for stray whitespace or quotes, then run `php artisan config:clear`. '
                    . 'If the secret was rotated in the Wayfair partner portal, update it here.]';
            }
            throw new \Exception('Failed to authenticate with Wayfair API: ' . $body . $hint);
        }

        return (string) $response->json('access_token');
    }

    /**
     * HTTP client for Wayfair OAuth (longer timeout + retries for slow/unstable networks).
     */
    protected function oauthHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $retries = max(1, (int) config('services.wayfair.oauth_retries', 3));
        $retryDelayMs = 3000;

        return Http::withoutVerifying()
            ->connectTimeout((int) config('services.wayfair.connect_timeout', 30))
            ->timeout((int) config('services.wayfair.http_timeout', 90))
            ->retry($retries, $retryDelayMs, throw: false);
    }

    /**
     * HTTP client for Wayfair GraphQL / feed API calls.
     */
    public function apiHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withoutVerifying()
            ->connectTimeout((int) config('services.wayfair.connect_timeout', 30))
            ->timeout((int) config('services.wayfair.http_timeout', 90))
            ->retry(3, 2000, throw: false);
    }

    /**
     * Get token for Product Catalog API (title updates). Uses catalog_scope when set.
     */
    protected function getTokenForCatalog(): string
    {
        $scope = config('services.wayfair.catalog_scope');
        return $this->getAccessTokenWithScope($scope !== '' ? $scope : null);
    }

    public function updatePrice(string $sku, float $price)
    {
        // Build XML for pricing feed
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<PriceFeed xmlns="http://api.wayfair.com/v1/pricefeed.xsd">
    <Price>
        <SupplierPartNumber>{$sku}</SupplierPartNumber>
        <PriceAmount>{$price}</PriceAmount>
        <CurrencyCode>USD</CurrencyCode>
    </Price>
</PriceFeed>
XML;

        $response = Http::withToken($this->authenticate())
            ->attach('file', $xml, 'price_feed.xml')
            ->post('https://api.wayfair.com/v1/feeds/pricing');

        return $response->json();
    }



     private function getAccessToken()
    {
        $response = $this->oauthHttpClient()->asForm()->post($this->authUrl, [
            'grant_type' => $this->grantType,
            'audience' => $this->audience,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        return $response->successful() ? ($response->json()['access_token'] ?? null) : null;
    }


    public function getInventory()
    {
        // OLD CODE - Product Catalog API (Not Working)
        // $limit = 100;
        // $offset = 0;
        // $inventoryUrl = 'https://api.wayfair.io/v1/product-catalog-api/graphql';
        // $allInventory = [];
        // do {
        //     $query = <<<'GRAPHQL'
        //     GRAPHQL;
        //     $response = Http::withoutVerifying()->withToken($this->getAccessToken())->post($inventoryUrl, [
        //         'query' => $query,
        //         'variables' => [
        //             'limit' => $limit,
        //             'offset' => $offset,
        //         ]
        //     ]);
        //     if (!$response->successful()) {
        //         throw new \Exception("Wayfair API Error: " . $response->body());
        //     }
        //     $inventoryItems = $response->json()['data']['inventory'] ?? [];
        //     if (empty($inventoryItems)) {
        //         break;
        //     }
        //     $allInventory = array_merge($allInventory, $inventoryItems);
        //     $offset += $limit;
        // } while (count($inventoryItems) === $limit);
        // return array_map(function ($item) {
        //     return [
        //         'sku' => $item['supplierPartNumber'] ?? null,
        //         'quantity' => $item['quantityOnHand'] ?? 0,
        //     ];
        // }, $allInventory);

        // NEW CODE - Purchase Orders API (Working)
        $limit = 100;
        $offset = 0;
        $allOrders = [];
        $allProducts = [];

        do {
            $query = <<<'GRAPHQL'
            query GetPurchaseOrders($limit: Int!, $offset: Int!) {
                purchaseOrders(
                    limit: $limit,
                    offset: $offset
                ) {
                    poNumber
                    poDate
                    estimatedShipDate
                    products {
                        partNumber
                        quantity
                        price
                    }
                }
            }
            GRAPHQL;

            $response = Http::withoutVerifying()
                ->withToken($this->authenticate())
                ->post($this->graphqlUrl, [
                    'query' => $query,
                    'variables' => [
                        'limit' => $limit,
                        'offset' => $offset,
                    ]
                ]);

            if (!$response->successful()) {
                throw new \Exception("Wayfair API Error: " . $response->body());
            }

            $data = $response->json();
            $orders = $data['data']['purchaseOrders'] ?? [];

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                $allOrders[] = $order;
                if (!empty($order['products'])) {
                    foreach ($order['products'] as $product) {
                        $allProducts[] = [
                            'sku' => $product['partNumber'] ?? null,
                            'quantity' => $product['quantity'] ?? 0,
                            'price' => $product['price'] ?? 0,
                            'po_number' => $order['poNumber'] ?? null,
                            'po_date' => $order['poDate'] ?? null,
                        ];
                    }
                }
            }

            $offset += $limit;
        } while (count($orders) === $limit);

        return [
            'total_orders' => count($allOrders),
            'total_products' => count($allProducts),
            'products' => $allProducts,
        ];
    }

    /**
     * Update product title (item name) on Wayfair via Product Catalog GraphQL API.
     * Uses updateMarketSpecificCatalogItems mutation then polls statusOfUpdateRequest until COMPLETED.
     *
     * @param string $sku Supplier part number (SKU)
     * @param string $title New item name / title
     * @return array{success: bool, message: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $sku = trim($sku);
        $title = trim($title);
        if ($sku === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.'];
        }

        try {
            $token = $this->getTokenForCatalog();
            if (! $token) {
                return ['success' => false, 'message' => 'Wayfair authentication failed.'];
            }

            $lastMessage = 'Wayfair: failed to submit title update or get requestId.';
            foreach ($this->wayfairSkuCandidates($sku) as $candidate) {
                $submitted = $this->submitTitleUpdate($token, $candidate, $title);
                if (($submitted['request_id'] ?? null) !== null) {
                    $polled = $this->pollUpdateStatus($token, (string) $submitted['request_id'], $candidate);
                    if (! empty($polled['success'])) {
                        return $polled;
                    }
                    $lastMessage = (string) ($polled['message'] ?? $lastMessage);
                    continue;
                }
                if (trim((string) ($submitted['message'] ?? '')) !== '') {
                    $lastMessage = (string) $submitted['message'];
                }
            }

            return ['success' => false, 'message' => $lastMessage];
        } catch (\Throwable $e) {
            Log::error('Wayfair updateTitle exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Step 1: Submit updateMarketSpecificCatalogItems mutation; returns requestId or error.
     *
     * @return array{request_id: ?string, message: string}
     */
    private function submitTitleUpdate(string $token, string $sku, string $title): array
    {
        $url = config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        $supplierId = (string) config('services.wayfair.supplier_id', '2603');
        $brand = config('services.wayfair.brand', 'WAYFAIR');
        $country = config('services.wayfair.country', 'UNITED_STATES');
        $locale = config('services.wayfair.locale', 'en-US');

        $mutation = <<<'GRAPHQL'
        mutation UpdateMarketSpecificCatalogItems($input: UpdateMarketSpecificCatalogItemsInput!) {
          updateCatalogEntitiesMutations {
            updateMarketSpecificCatalogItems(input: $input) {
              requestId
            }
          }
        }
        GRAPHQL;

        $item = [
            'supplierPartNumber' => $sku,
            'itemName' => $title,
        ];

        $variables = [
            'input' => [
                'marketContext' => [
                    'locale' => $locale,
                    'country' => $country,
                    'brand' => $brand,
                ],
                'supplierId' => $supplierId,
                'catalogItemsToUpdate' => [$item],
                'validateOnly' => false,
            ],
        ];

        Log::info('Wayfair - Submitting title update (GraphQL)', ['sku' => $sku, 'url' => $url]);

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

        $data = $response->json();
        $errors = $data['errors'] ?? null;

        if ($errors) {
            Log::warning('Wayfair - GraphQL errors on submit', ['sku' => $sku, 'errors' => $errors]);

            return ['request_id' => null, 'message' => 'Wayfair GraphQL: '.$this->formatWayfairGraphqlErrors($errors)];
        }

        $requestId = $data['data']['updateCatalogEntitiesMutations']['updateMarketSpecificCatalogItems']['requestId'] ?? null;
        if ($requestId === null) {
            Log::warning('Wayfair - No requestId in response', ['sku' => $sku, 'response' => $data]);

            return [
                'request_id' => null,
                'message' => 'Wayfair: no requestId. '.mb_substr(json_encode($data) ?: $response->body(), 0, 400),
            ];
        }

        Log::info('Wayfair - Title update submitted', ['sku' => $sku, 'requestId' => $requestId]);

        return ['request_id' => (string) $requestId, 'message' => ''];
    }

    /**
     * @param  list<mixed>|array<string, mixed>  $errors
     */
    private function formatWayfairGraphqlErrors(array $errors): string
    {
        $parts = [];
        foreach ($errors as $error) {
            if (is_string($error)) {
                $parts[] = $error;
                continue;
            }
            if (! is_array($error)) {
                continue;
            }
            $msg = trim((string) ($error['message'] ?? ''));
            if ($msg !== '') {
                $parts[] = $msg;
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'unknown GraphQL error';
    }

    /**
     * @return list<string>
     */
    private function wayfairSkuCandidates(string $sku): array
    {
        $sku = trim($sku);
        $stripped = ltrim($sku, "- \t");
        $out = [];
        foreach ([
            $sku,
            $stripped,
            str_replace(' ', '', $sku),
            str_replace(' ', '', $stripped),
            preg_replace('/\s+/', '-', $sku) ?: '',
            preg_replace('/\s+/', '-', $stripped) ?: '',
            $this->normalizePartNumber($sku),
            $this->normalizePartNumber($stripped),
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        if (Schema::hasTable('wayfair_pricing_prices') && Schema::hasColumn('wayfair_pricing_prices', 'sku')) {
            $pricing = DB::table('wayfair_pricing_prices')
                ->where(function ($q) use ($out) {
                    $q->whereIn('sku', $out);
                    foreach ($out as $candidate) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($candidate)]);
                    }
                })
                ->value('sku');
            $fromPricing = trim((string) ($pricing ?? ''));
            if ($fromPricing !== '' && ! in_array($fromPricing, $out, true)) {
                array_unshift($out, $fromPricing);
            }
        }

        if (Schema::hasTable('wayfair_metrics') && Schema::hasColumn('wayfair_metrics', 'sku')) {
            $row = DB::table('wayfair_metrics')
                ->where(function ($q) use ($out) {
                    $q->whereIn('sku', $out);
                    foreach ($out as $candidate) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($candidate)]);
                    }
                })
                ->first();
            $fromDb = trim((string) ($row->sku ?? ''));
            if ($fromDb !== '' && ! in_array($fromDb, $out, true)) {
                array_unshift($out, $fromDb);
            }
        }

        return $out;
    }

    /**
     * Step 2: Poll statusOfUpdateRequest until COMPLETED or max attempts; return success/failure with message.
     */
    private function pollUpdateStatus(string $token, string $requestId, string $sku, int $maxAttempts = 10): array
    {
        $url = config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        $query = <<<'GRAPHQL'
        query StatusOfUpdateRequest($input: StatusOfUpdateRequestInput!) {
          statusOfUpdateRequest(input: $input) {
            requestId
            status
            problems {
              code
              title
              detail
              catalogEntityIdentifier
              catalogEntityProperty
            }
            successfulUpdates {
              entityIdentifier
              catalogEntityProperty
            }
          }
        }
        GRAPHQL;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'query' => $query,
                    'variables' => [
                        'input' => ['requestId' => $requestId],
                    ],
                ]);

            $data = $response->json();
            $errors = $data['errors'] ?? null;
            if ($errors) {
                Log::warning('Wayfair - GraphQL errors on status poll', ['requestId' => $requestId, 'errors' => $errors]);
                return ['success' => false, 'message' => 'Wayfair: status check failed. ' . json_encode($errors)];
            }

            $statusPayload = $data['data']['statusOfUpdateRequest'] ?? null;
            if ($statusPayload === null) {
                return ['success' => false, 'message' => 'Wayfair: no status in response.'];
            }

            $status = $statusPayload['status'] ?? '';
            $problems = $statusPayload['problems'] ?? [];

            Log::debug('Wayfair - Poll status', ['requestId' => $requestId, 'status' => $status, 'attempt' => $i + 1]);

            if (strtoupper($status) === 'COMPLETED') {
                if (empty($problems)) {
                    Log::info('Wayfair title updated successfully', ['sku' => $sku, 'requestId' => $requestId]);
                    return ['success' => true, 'message' => "Title updated for SKU: {$sku}."];
                }
                $msg = $this->formatProblemsMessage($problems);
                Log::warning('Wayfair - Update completed with problems', ['sku' => $sku, 'problems' => $problems]);
                return ['success' => false, 'message' => 'Wayfair: ' . $msg];
            }

            if (strtoupper($status) === 'FAILED') {
                $msg = $this->formatProblemsMessage($problems);
                Log::warning('Wayfair - Update failed', ['sku' => $sku, 'problems' => $problems]);
                return ['success' => false, 'message' => 'Wayfair: ' . $msg];
            }

            if ($i < $maxAttempts - 1) {
                sleep(2);
            }
        }

        Log::warning('Wayfair - Poll timeout', ['requestId' => $requestId, 'sku' => $sku]);
        return ['success' => false, 'message' => 'Wayfair: timeout waiting for update to complete.'];
    }

    private function formatProblemsMessage(array $problems): string
    {
        $parts = [];
        foreach ($problems as $p) {
            $detail = $p['detail'] ?? $p['title'] ?? $p['code'] ?? json_encode($p);
            $parts[] = $detail;
        }
        return implode('; ', $parts) ?: 'Update had errors.';
    }

    /**
     * Push bullet lines as catalog key features (GraphQL). No truncation.
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or supplier part number) and bullet points are required.'];
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bulletPoints))));
        if ($lines === []) {
            return ['success' => false, 'message' => 'No bullet lines found.'];
        }

        $sku = trim($identifier);
        if (Schema::hasTable('wayfair_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('wayfair_metrics', $identifier, [
                'supplier_part_number',
                'supplier_sku',
                'catalog_supplier_part_number',
            ]);
            if ($row && ! empty($row->sku)) {
                $sku = trim((string) $row->sku);
            }
        }

        try {
            $token = $this->getTokenForCatalog();
            if (! $token) {
                return ['success' => false, 'message' => 'Wayfair authentication failed.'];
            }

            $requestId = $this->submitKeyFeaturesUpdate($token, $sku, $lines);
            if ($requestId === null) {
                return ['success' => false, 'message' => 'Wayfair: failed to submit bullet/key feature update.'];
            }

            return $this->pollUpdateStatus($token, $requestId, $sku);
        } catch (\Throwable $e) {
            Log::error('Wayfair updateBulletPoints', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Uses the same key-features update path; HTML descriptions are split into formatted feature lines.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description is required.'];
        }

        $lines = \App\Services\Support\DescriptionWithImagesFormatter::htmlToFeatureLines($description);
        if ($lines === []) {
            return ['success' => false, 'message' => 'No description content found.'];
        }

        return $this->updateBulletPoints($identifier, implode("\n", $lines));
    }

    /**
     * Description Master: load Wayfair description (metrics first, then key-feature bullets as HTML list).
     *
     * @return array{success: bool, message: string, html?: string, source?: string}
     */
    public function fetchDescriptionHtml(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (Schema::hasTable('wayfair_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('wayfair_metrics', $identifier, [
                'supplier_part_number',
                'supplier_sku',
                'catalog_supplier_part_number',
            ]);
            if ($row) {
                $master = trim((string) ($row->description_master ?? ''));
                if ($master !== '') {
                    return [
                        'success' => true,
                        'message' => 'Wayfair description loaded from metrics.',
                        'html' => $master,
                        'source' => 'metrics',
                    ];
                }
                $bullets = trim((string) ($row->bullet_points ?? ''));
                if ($bullets !== '') {
                    return [
                        'success' => true,
                        'message' => 'Wayfair key features loaded from metrics.',
                        'html' => \App\Services\Support\DescriptionWithImagesFormatter::linesToEditorHtml($bullets),
                        'source' => 'metrics_bullets',
                    ];
                }
            }
        }

        return ['success' => false, 'message' => 'No Wayfair description found for this SKU.'];
    }

    /**
     * Push catalog image URLs for a Wayfair SKU through the Product Catalog mutation.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        $urls = array_slice(array_values(array_unique(array_filter(array_map('trim', $imageUrls), fn ($url) => $url !== ''))), 0, 12);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU and at least one image URL are required.'];
        }

        $sku = trim($identifier);
        if (Schema::hasTable('wayfair_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('wayfair_metrics', $identifier, [
                'supplier_part_number',
                'supplier_sku',
                'catalog_supplier_part_number',
            ]);
            if ($row && ! empty($row->sku)) {
                $sku = trim((string) $row->sku);
            }
        }

        try {
            $token = $this->getTokenForCatalog();
            if (! $token) {
                return ['success' => false, 'message' => 'Wayfair authentication failed.'];
            }

            $requestId = $this->submitImageUrlsUpdate($token, $sku, $urls);
            if ($requestId === null) {
                return ['success' => false, 'message' => 'Wayfair: failed to submit image update.'];
            }

            $result = $this->pollUpdateStatus($token, $requestId, $sku);
            if (! ($result['success'] ?? false)) {
                return $result;
            }

            $saved = $this->saveImageUrlsToWayfairMetrics($sku, $urls);
            if (! $saved) {
                $result['message'] = ($result['message'] ?? 'Wayfair images updated.').' Metrics save failed.';
            }
            $result['normalized_urls'] = $urls;

            return $result;
        } catch (\Throwable $e) {
            Log::error('Wayfair updateListingImages', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images): array
    {
        return $this->updateListingImages($identifier, $images);
    }

    /**
     * Push catalog video URLs for a Wayfair SKU through the Product Catalog mutation.
     *
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateListingVideos(string $identifier, array $videoUrls): array
    {
        $urls = array_slice(array_values(array_unique(array_filter(array_map('trim', $videoUrls), fn ($url) => $url !== ''))), 0, 5);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU and at least one video URL are required.'];
        }

        $sku = trim($identifier);
        if (Schema::hasTable('wayfair_metrics')) {
            $row = $this->findMetricRowBySkuOrAlternateIds('wayfair_metrics', $identifier, [
                'supplier_part_number',
                'supplier_sku',
                'catalog_supplier_part_number',
            ]);
            if ($row && ! empty($row->sku)) {
                $sku = trim((string) $row->sku);
            }
        }

        try {
            $token = $this->getTokenForCatalog();
            if (! $token) {
                return ['success' => false, 'message' => 'Wayfair authentication failed.'];
            }

            $requestId = $this->submitVideoUrlsUpdate($token, $sku, $urls);
            if ($requestId === null) {
                return ['success' => false, 'message' => 'Wayfair: failed to submit video update.'];
            }

            $result = $this->pollUpdateStatus($token, $requestId, $sku);
            if (! ($result['success'] ?? false)) {
                return $result;
            }

            $saved = $this->saveVideoUrlsToMetricsRow('wayfair_metrics', $sku, $urls);
            if (! $saved) {
                $result['message'] = ($result['message'] ?? 'Wayfair videos updated.').' Metrics save failed.';
            }
            $result['normalized_urls'] = $urls;

            return $result;
        } catch (\Throwable $e) {
            Log::error('Wayfair updateListingVideos', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        return $this->updateListingVideos($identifier, $videos);
    }

    /**
     * @param  list<string>  $features
     */
    private function submitKeyFeaturesUpdate(string $token, string $sku, array $features): ?string
    {
        $url = config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        $supplierId = (string) config('services.wayfair.supplier_id', '2603');
        $brand = config('services.wayfair.brand', 'WAYFAIR');
        $country = config('services.wayfair.country', 'UNITED_STATES');
        $locale = config('services.wayfair.locale', 'en-US');

        $mutation = <<<'GRAPHQL'
        mutation UpdateMarketSpecificCatalogItems($input: UpdateMarketSpecificCatalogItemsInput!) {
          updateCatalogEntitiesMutations {
            updateMarketSpecificCatalogItems(input: $input) {
              requestId
            }
          }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'marketContext' => [
                    'locale' => $locale,
                    'country' => $country,
                    'brand' => $brand,
                ],
                'supplierId' => $supplierId,
                'catalogItemsToUpdate' => [
                    [
                        'supplierPartNumber' => $sku,
                        'keyFeatures' => $features,
                    ],
                ],
                'validateOnly' => false,
            ],
        ];

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

        $data = $response->json();
        if (! empty($data['errors'])) {
            Log::warning('Wayfair keyFeatures GraphQL errors', ['sku' => $sku, 'errors' => $data['errors']]);

            return null;
        }

        return $data['data']['updateCatalogEntitiesMutations']['updateMarketSpecificCatalogItems']['requestId'] ?? null;
    }

    /**
     * @param  list<string>  $urls
     */
    private function submitImageUrlsUpdate(string $token, string $sku, array $urls): ?string
    {
        $url = config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        $supplierId = (string) config('services.wayfair.supplier_id', '2603');
        $brand = config('services.wayfair.brand', 'WAYFAIR');
        $country = config('services.wayfair.country', 'UNITED_STATES');
        $locale = config('services.wayfair.locale', 'en-US');

        $mutation = <<<'GRAPHQL'
        mutation UpdateMarketSpecificCatalogItems($input: UpdateMarketSpecificCatalogItemsInput!) {
          updateCatalogEntitiesMutations {
            updateMarketSpecificCatalogItems(input: $input) {
              requestId
            }
          }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'marketContext' => [
                    'locale' => $locale,
                    'country' => $country,
                    'brand' => $brand,
                ],
                'supplierId' => $supplierId,
                'catalogItemsToUpdate' => [
                    [
                        'supplierPartNumber' => $sku,
                        'images' => $urls,
                    ],
                ],
                'validateOnly' => false,
            ],
        ];

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

        $data = $response->json();
        if (! empty($data['errors'])) {
            Log::warning('Wayfair image GraphQL errors', ['sku' => $sku, 'errors' => $data['errors']]);

            return null;
        }

        return $data['data']['updateCatalogEntitiesMutations']['updateMarketSpecificCatalogItems']['requestId'] ?? null;
    }

    /**
     * @param  list<string>  $urls
     */
    private function submitVideoUrlsUpdate(string $token, string $sku, array $urls): ?string
    {
        $url = config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        $supplierId = (string) config('services.wayfair.supplier_id', '2603');
        $brand = config('services.wayfair.brand', 'WAYFAIR');
        $country = config('services.wayfair.country', 'UNITED_STATES');
        $locale = config('services.wayfair.locale', 'en-US');

        $mutation = <<<'GRAPHQL'
        mutation UpdateMarketSpecificCatalogItems($input: UpdateMarketSpecificCatalogItemsInput!) {
          updateCatalogEntitiesMutations {
            updateMarketSpecificCatalogItems(input: $input) {
              requestId
            }
          }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'marketContext' => [
                    'locale' => $locale,
                    'country' => $country,
                    'brand' => $brand,
                ],
                'supplierId' => $supplierId,
                'catalogItemsToUpdate' => [
                    [
                        'supplierPartNumber' => $sku,
                        'videos' => $urls,
                    ],
                ],
                'validateOnly' => false,
            ],
        ];

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

        $data = $response->json();
        if (! empty($data['errors'])) {
            Log::warning('Wayfair video GraphQL errors', ['sku' => $sku, 'errors' => $data['errors']]);

            return null;
        }

        return $data['data']['updateCatalogEntitiesMutations']['updateMarketSpecificCatalogItems']['requestId'] ?? null;
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToWayfairMetrics(string $sku, array $images): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('wayfair_metrics') || ! Schema::hasColumn('wayfair_metrics', 'sku')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('wayfair_metrics', 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn('wayfair_metrics', 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('wayfair_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            \Illuminate\Support\Facades\DB::table('wayfair_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('wayfair_metrics', 'created_at')) {
                \Illuminate\Support\Facades\DB::table('wayfair_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Wayfair image_urls save failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Look up SKUs on Wayfair (catalog inventory, then purchase-order history).
     *
     * @param  list<string>  $skus
     * @return array{
     *     items: array<string, array{sku: string, quantity: int, price: float|null}>,
     *     source: string,
     *     error: string|null
     * }
     */
    public function lookupInventoryBySkus(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $norm = $this->normalizePartNumber($sku);
            if ($norm !== '' && ! isset($wanted[$norm])) {
                $wanted[$norm] = $sku;
            }
        }

        if ($wanted === []) {
            return ['items' => [], 'source' => 'none', 'error' => null];
        }

        $error = null;

        $listedOnly = [];
        try {
            $fromSupplier = $this->lookupFromSupplierCatalog(array_values($wanted));
            if ($fromSupplier['items'] !== []) {
                $hasRealQty = false;
                foreach ($fromSupplier['items'] as $item) {
                    if ((int) ($item['quantity'] ?? 0) > 0) {
                        $hasRealQty = true;
                        break;
                    }
                }
                // Supplier catalog confirms the SKU is listed but hardcodes qty 0.
                // Do not treat that as live on-hand — fall through to inventory queries.
                if ($hasRealQty) {
                    return $fromSupplier;
                }
                $listedOnly = $fromSupplier['items'];
            }
            if (! empty($fromSupplier['error'])) {
                $error = $fromSupplier['error'];
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Wayfair lookupInventoryBySkus supplierCatalog failed', ['error' => $error]);
        }

        try {
            $fromCatalog = $this->lookupInventoryFromCatalog(array_keys($wanted));
            if ($fromCatalog['items'] !== []) {
                return $fromCatalog;
            }
            if (! empty($fromCatalog['error'])) {
                $error = $fromCatalog['error'];
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Wayfair lookupInventoryBySkus catalog failed', ['error' => $error]);
        }

        try {
            $fromPo = $this->lookupInventoryFromPurchaseOrders(array_keys($wanted));
            if ($fromPo['items'] !== []) {
                return $fromPo;
            }
            if ($error === null && ! empty($fromPo['error'])) {
                $error = $fromPo['error'];
            }
        } catch (\Throwable $e) {
            $error = $error ?: $e->getMessage();
            Log::warning('Wayfair lookupInventoryBySkus PO failed', ['error' => $e->getMessage()]);
        }

        if ($listedOnly !== []) {
            return ['items' => $listedOnly, 'source' => 'supplier_catalog', 'error' => $error];
        }

        return ['items' => [], 'source' => 'none', 'error' => $error];
    }

    protected function normalizePartNumber(string $sku): string
    {
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', $sku);
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return strtoupper((string) $s);
    }

    /**
     * Live catalog lookup via Supplier Catalog API (part number in catalog = listed).
     *
     * @param  list<string>  $skus
     * @return array{items: array<string, array{sku: string, quantity: int, price: float|null}>, source: string, error: string|null}
     */
    protected function lookupFromSupplierCatalog(array $skus): array
    {
        $token = $this->authenticate();
        $url = 'https://api.wayfair.io/v1/supplier-catalog-api/graphql';
        $supplierId = (int) config('services.wayfair.supplier_id');
        $query = <<<'GRAPHQL'
        query ($supplierId: Int!, $filter: ProductFilter, $paginationOptions: PaginationOptions) {
          supplierCatalog(supplierId: $supplierId, filter: $filter, paginationOptions: $paginationOptions) {
            pageInfo { page pageSize hasNextPage totalPages }
            products {
              productId
              supplierPartNumber
              status
              skus { sku displaySku isLive status }
            }
          }
        }
        GRAPHQL;

        $items = [];
        $lastError = null;

        foreach (array_chunk(array_values($skus), 25) as $chunk) {
            $response = $this->apiHttpClient()
                ->withToken($token)
                ->withHeaders([
                    'X-SELECTED-SUPPLIER-ID' => (string) $supplierId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'query' => $query,
                    'variables' => [
                        'supplierId' => $supplierId,
                        'filter' => [
                            'supplierPartNumber' => ['in' => $chunk],
                        ],
                        'paginationOptions' => [
                            'page' => 1,
                            'pageSize' => 25,
                        ],
                    ],
                ]);

            $json = $response->json();
            if (! empty($json['errors'])) {
                $lastError = $this->formatGraphqlErrors($json['errors']);
                if (stripos($lastError, 'permission') !== false || stripos($lastError, 'denied') !== false) {
                    return ['items' => $items, 'source' => 'supplier_catalog', 'error' => $lastError];
                }
                continue;
            }

            $products = $json['data']['supplierCatalog']['products'] ?? [];
            if (! is_array($products)) {
                continue;
            }

            foreach ($products as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $part = trim((string) ($product['supplierPartNumber'] ?? ''));
                $status = strtoupper((string) ($product['status'] ?? ''));
                $liveSku = false;
                foreach (($product['skus'] ?? []) as $skuRow) {
                    if (! is_array($skuRow)) {
                        continue;
                    }
                    if (! empty($skuRow['isLive']) || strtoupper((string) ($skuRow['status'] ?? '')) === 'LIVE') {
                        $liveSku = true;
                    }
                    foreach ([$skuRow['sku'] ?? '', $skuRow['displaySku'] ?? ''] as $alt) {
                        $altNorm = $this->normalizePartNumber((string) $alt);
                        if ($altNorm !== '') {
                            $items[$altNorm] = [
                                'sku' => trim((string) $alt) !== '' ? trim((string) $alt) : $part,
                                'quantity' => 0,
                                'price' => null,
                            ];
                        }
                    }
                }
                if ($part === '') {
                    continue;
                }
                if ($status === 'UNPURCHASABLE' && ! $liveSku) {
                    continue;
                }
                $items[$this->normalizePartNumber($part)] = [
                    'sku' => $part,
                    'quantity' => 0,
                    'price' => null,
                ];
            }
        }

        return ['items' => $items, 'source' => 'supplier_catalog', 'error' => $lastError];
    }

    /**
     * @param  list<string>  $wantedUpper
     * @return array{items: array<string, array{sku: string, quantity: int, price: float|null}>, source: string, error: string|null}
     */
    protected function lookupInventoryFromCatalog(array $wantedUpper): array
    {
        $wantedSet = array_fill_keys($wantedUpper, true);
        $token = $this->getTokenForCatalog();
        $urls = array_values(array_unique(array_filter([
            (string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
            $this->graphqlUrl,
        ])));

        $lastError = null;
        foreach ($urls as $url) {
            $batched = $this->queryInventoryByPartNumbers($url, $token, $wantedUpper);
            if (! empty($batched['error']) && empty($batched['items'])) {
                $lastError = $batched['error'];
            }
            if ($batched['items'] !== []) {
                return ['items' => $batched['items'], 'source' => 'catalog', 'error' => null];
            }

            $paged = $this->queryPaginatedInventory($url, $token, $wantedSet);
            if (! empty($paged['error']) && empty($paged['items'])) {
                $lastError = $paged['error'];
            }
            if ($paged['items'] !== []) {
                return ['items' => $paged['items'], 'source' => 'catalog', 'error' => null];
            }
        }

        return ['items' => [], 'source' => 'catalog', 'error' => $lastError];
    }

    /**
     * @param  list<string>  $wantedUpper
     * @return array{items: array<string, array{sku: string, quantity: int, price: float|null}>, error: ?string}
     */
    protected function queryInventoryByPartNumbers(string $url, string $token, array $wantedUpper): array
    {
        $items = [];
        $lastError = null;
        $chunks = array_chunk($wantedUpper, 50);

        $queries = [
            <<<'GRAPHQL'
            query InventoryByParts($parts: [String!]!) {
              inventory(supplierPartNumbers: $parts) {
                supplierPartNumber
                quantityOnHand
              }
            }
            GRAPHQL,
            <<<'GRAPHQL'
            query InventoryByFilter($parts: [String!]!) {
              inventory(filter: { supplierPartNumbers: $parts }) {
                supplierPartNumber
                quantityOnHand
              }
            }
            GRAPHQL,
        ];

        foreach ($chunks as $chunk) {
            $chunkFound = false;
            foreach ($queries as $query) {
                $res = $this->graphqlPost($url, $token, $query, ['parts' => array_values($chunk)]);
                if (! empty($res['json']['errors'])) {
                    $lastError = $this->formatGraphqlErrors($res['json']['errors']);
                    continue;
                }
                if (! $res['ok']) {
                    $lastError = 'HTTP '.$res['status'].': '.$res['body'];
                    continue;
                }
                $rows = $res['json']['data']['inventory'] ?? null;
                if (! is_array($rows)) {
                    continue;
                }
                $chunkFound = true;
                foreach ($rows as $row) {
                    $mapped = $this->mapInventoryRow($row);
                    if ($mapped !== null) {
                        $items[$mapped['key']] = $mapped['item'];
                    }
                }
                break;
            }
            if (! $chunkFound && $items === []) {
                return ['items' => [], 'error' => $lastError];
            }
        }

        return ['items' => $items, 'error' => $lastError];
    }

    /**
     * @param  array<string, true>  $wantedSet
     * @return array{items: array<string, array{sku: string, quantity: int, price: float|null}>, error: ?string}
     */
    protected function queryPaginatedInventory(string $url, string $token, array $wantedSet): array
    {
        $query = <<<'GRAPHQL'
        query GetInventory($limit: Int!, $offset: Int!) {
          inventory(limit: $limit, offset: $offset) {
            supplierPartNumber
            quantityOnHand
          }
        }
        GRAPHQL;

        $items = [];
        $limit = 100;
        $offset = 0;
        $lastError = null;

        for ($page = 0; $page < 250; $page++) {
            $res = $this->graphqlPost($url, $token, $query, [
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (! empty($res['json']['errors'])) {
                $lastError = $this->formatGraphqlErrors($res['json']['errors']);
                break;
            }
            if (! $res['ok']) {
                $lastError = 'HTTP '.$res['status'].': '.$res['body'];
                break;
            }
            $rows = $res['json']['data']['inventory'] ?? [];
            if (! is_array($rows) || $rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $mapped = $this->mapInventoryRow($row);
                if ($mapped === null) {
                    continue;
                }
                if (isset($wantedSet[$mapped['key']])) {
                    $items[$mapped['key']] = $mapped['item'];
                }
            }
            if (count($rows) < $limit) {
                break;
            }
            $offset += $limit;
        }

        return ['items' => $items, 'error' => $lastError];
    }

    /**
     * @param  list<string>  $wantedUpper
     * @return array{items: array<string, array{sku: string, quantity: int, price: float|null}>, source: string, error: string|null}
     */
    protected function lookupInventoryFromPurchaseOrders(array $wantedUpper): array
    {
        $wantedSet = array_fill_keys($wantedUpper, true);
        $po = $this->getInventory();
        $items = [];
        foreach (($po['products'] ?? []) as $product) {
            $sku = $this->normalizePartNumber((string) ($product['sku'] ?? ''));
            if ($sku === '' || ! isset($wantedSet[$sku])) {
                continue;
            }
            $qty = (int) ($product['quantity'] ?? 0);
            $price = isset($product['price']) && is_numeric($product['price']) ? (float) $product['price'] : null;
            if (! isset($items[$sku])) {
                $items[$sku] = [
                    'sku' => (string) ($product['sku'] ?? $sku),
                    'quantity' => $qty,
                    'price' => $price,
                ];
            } else {
                $items[$sku]['quantity'] += $qty;
                if ($items[$sku]['price'] === null && $price !== null) {
                    $items[$sku]['price'] = $price;
                }
            }
        }

        return ['items' => $items, 'source' => 'purchase_orders', 'error' => null];
    }

    /**
     * @param  mixed  $row
     * @return array{key: string, item: array{sku: string, quantity: int, price: float|null}}|null
     */
    protected function mapInventoryRow($row): ?array
    {
        if (! is_array($row)) {
            return null;
        }
        $sku = trim((string) ($row['supplierPartNumber'] ?? $row['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }

        return [
            'key' => $this->normalizePartNumber($sku),
            'item' => [
                'sku' => $sku,
                'quantity' => (int) ($row['quantityOnHand'] ?? $row['quantity'] ?? 0),
                'price' => isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : null,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    protected function formatGraphqlErrors(array $errors): string
    {
        $parts = [];
        foreach ($errors as $err) {
            if (is_array($err) && isset($err['message'])) {
                $parts[] = (string) $err['message'];
            }
        }

        return $parts !== [] ? implode('; ', $parts) : 'GraphQL error';
    }

    /**
     * @return array{ok: bool, json: array, body: string, status: int}
     */
    protected function graphqlPost(string $url, string $token, string $query, array $variables = []): array
    {
        $response = $this->apiHttpClient()
            ->withToken($token)
            ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
            ->post($url, [
                'query' => $query,
                'variables' => $variables,
            ]);

        $json = $response->json();

        return [
            'ok' => $response->successful(),
            'json' => is_array($json) ? $json : [],
            'body' => $response->body(),
            'status' => $response->status(),
        ];
    }

    public function isConfigured(): bool
    {
        $clientId = trim((string) config('services.wayfair.client_id'), " \t\n\r\0\x0B\"'");
        $clientSecret = trim((string) config('services.wayfair.client_secret'), " \t\n\r\0\x0B\"'");

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
                'message' => 'Wayfair credentials missing (WAYFAIR_CLIENT_ID / WAYFAIR_CLIENT_SECRET).',
            ];
        }

        try {
            $token = $this->getAccessTokenWithScope(null);
            if ($token === '') {
                return ['success' => false, 'message' => 'OAuth token request returned empty token.'];
            }

            return [
                'success' => true,
                'message' => 'Wayfair OAuth token acquired successfully.',
                'sample_count' => strlen($token),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Wayfair OAuth failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Push inventory to Wayfair via GraphQL inventory.save (differential feed).
     * Always sends dryRun: false so accepted feeds actually update on-hand qty.
     *
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array{pushed: int, failed: int, message: string, skus: list<string>}
     */
    public function updateItemInventoryBulk(array $items, string $feedKind = 'DIFFERENTIAL'): array
    {
        if ($items === []) {
            return ['pushed' => 0, 'failed' => 0, 'message' => 'No items to push.', 'skus' => []];
        }

        try {
            $token = $this->authenticate();
        } catch (\Throwable $e) {
            Log::error('WayfairApiService: inventory push auth failed', ['error' => $e->getMessage()]);

            return [
                'pushed' => 0,
                'failed' => count($items),
                'message' => 'Wayfair auth failed: '.$e->getMessage(),
                'skus' => [],
            ];
        }

        $supplierId = (int) config('services.wayfair.supplier_id', 2603);
        $feedKind = strtoupper(trim($feedKind)) === 'TRUE_UP' ? 'TRUE_UP' : 'DIFFERENTIAL';
        $mutations = [
            <<<'GRAPHQL'
            mutation saveInventory($inventory: [inventoryInput]!, $feedKind: inventoryFeedKind) {
              inventory {
                save(inventory: $inventory, feedKind: $feedKind, dryRun: false) {
                  id
                  handle
                  status
                  submittedAt
                  itemCount
                  errorCount
                  errors { key message }
                }
              }
            }
            GRAPHQL,
            <<<'GRAPHQL'
            mutation saveInventory($inventory: [inventoryInput]!, $feedKind: inventoryFeedKind) {
              inventory {
                save(inventory: $inventory, feedKind: $feedKind) {
                  id
                  handle
                  status
                  submittedAt
                  errors { key message }
                }
              }
            }
            GRAPHQL,
            <<<'GRAPHQL'
            mutation SaveInventory($inventory: [inventoryInput!]!) {
              inventory {
                save(inventory: $inventory, feedKind: DIFFERENTIAL) {
                  handle
                  submittedAt
                  errors { key message }
                }
              }
            }
            GRAPHQL,
        ];

        $pushed = 0;
        $failed = 0;
        $accepted = [];
        $lastError = null;

        foreach (array_chunk(array_values($items), 50) as $chunk) {
            $inventory = [];
            foreach ($chunk as $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku === '') {
                    $failed++;
                    continue;
                }
                $row = [
                    'supplierPartNumber' => $sku,
                    'quantityOnHand' => max(0, (int) ($item['quantity'] ?? 0)),
                ];
                if ($supplierId > 0) {
                    $row['supplierId'] = $supplierId;
                }
                $inventory[] = $row;
            }
            if ($inventory === []) {
                continue;
            }

            $save = null;
            $usedWithFeedKindVar = false;
            foreach ($mutations as $index => $mutation) {
                $variables = ['inventory' => $inventory];
                if ($index < 2) {
                    $variables['feedKind'] = $feedKind;
                    $usedWithFeedKindVar = true;
                }
                $res = $this->graphqlPost($this->graphqlUrl, $token, $mutation, $variables);
                if (! empty($res['json']['errors'])) {
                    $lastError = $this->formatGraphqlErrors($res['json']['errors']);
                    continue;
                }
                if (! $res['ok']) {
                    $lastError = 'HTTP '.$res['status'].': '.mb_substr((string) $res['body'], 0, 300);
                    continue;
                }
                $save = $res['json']['data']['inventory']['save'] ?? null;
                if (is_array($save)) {
                    break;
                }
                $lastError = 'Wayfair inventory.save returned no payload.';
            }

            if (! is_array($save)) {
                $failed += count($inventory);
                Log::warning('WayfairApiService: inventory save failed', [
                    'count' => count($inventory),
                    'error' => $lastError,
                    'feed_kind' => $feedKind,
                    'used_feed_kind_var' => $usedWithFeedKindVar,
                ]);
                continue;
            }

            $errorKeys = [];
            $feedLevelError = false;
            foreach (is_array($save['errors'] ?? null) ? $save['errors'] : [] as $err) {
                if (! is_array($err)) {
                    continue;
                }
                $key = trim((string) ($err['key'] ?? ''));
                $msg = trim((string) ($err['message'] ?? ''));
                if ($key !== '') {
                    $errorKeys[strtoupper($key)] = true;
                } elseif ($msg !== '') {
                    $feedLevelError = true;
                }
                if ($msg !== '') {
                    $lastError = $msg;
                }
            }

            $errorCount = (int) ($save['errorCount'] ?? 0);
            $handle = trim((string) ($save['handle'] ?? $save['id'] ?? ''));
            if ($handle === '' && $errorCount > 0) {
                $feedLevelError = true;
            }

            foreach ($inventory as $row) {
                $sku = (string) $row['supplierPartNumber'];
                if ($feedLevelError || isset($errorKeys[strtoupper($sku)])) {
                    $failed++;
                } else {
                    $pushed++;
                    $accepted[] = $sku;
                }
            }

            Log::info('WayfairApiService: inventory.save chunk', [
                'handle' => $handle !== '' ? $handle : null,
                'status' => $save['status'] ?? null,
                'item_count' => $save['itemCount'] ?? count($inventory),
                'error_count' => $errorCount,
                'accepted' => count($inventory) - count($errorKeys),
            ]);
        }

        $message = $pushed > 0
            ? "Pushed {$pushed} inventory row(s) to Wayfair.".($failed > 0 ? " {$failed} failed." : '')
            : ('Wayfair inventory push failed'.($lastError ? ': '.$lastError : '.'));

        Log::info('WayfairApiService: updateItemInventoryBulk', [
            'pushed' => $pushed,
            'failed' => $failed,
            'handle_error' => $lastError,
            'feed_kind' => $feedKind,
        ]);

        return [
            'pushed' => $pushed,
            'failed' => $failed,
            'message' => $message,
            'skus' => $accepted,
        ];
    }

    /**
     * @return array{locale: string, country: string, brand: string}
     */
    public function marketContext(): array
    {
        return [
            'locale' => (string) config('services.wayfair.locale', 'en-US'),
            'country' => (string) config('services.wayfair.country', 'UNITED_STATES'),
            'brand' => (string) config('services.wayfair.brand', 'WAYFAIR'),
        ];
    }

    /**
     * @return array{questions: list<array<string, mixed>>, message: string}
     */
    public function getProductAdditionQuestions(int $classId): array
    {
        if ($classId <= 0) {
            return ['questions' => [], 'message' => 'Wayfair class ID is required.'];
        }

        $query = <<<'GRAPHQL'
        query GetQuestions($request: GetProductAdditionQuestionsRequest!) {
          productAddition {
            questions(request: $request) {
              id
              parentId
              internalName
              displayName
              answerType
              isActive
              isMultiValue
              importanceType
              isUnavailableEligible
              isNotApplicableEligible
              description
              possibleAnswers { key value }
              childQuestions {
                id
                parentId
                internalName
                displayName
                answerType
                isActive
                isMultiValue
                importanceType
                isUnavailableEligible
                isNotApplicableEligible
                description
                possibleAnswers { key value }
              }
            }
          }
        }
        GRAPHQL;

        $res = $this->productAdditionGraphql($query, [
            'request' => [
                'classId' => $classId,
                'supplierId' => (int) config('services.wayfair.supplier_id'),
                'marketContext' => $this->marketContext(),
            ],
        ]);
        $questions = $res['data']['productAddition']['questions'] ?? [];

        return [
            'questions' => is_array($questions) ? array_values($questions) : [],
            'message' => (string) ($res['message'] ?? ''),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function getManufacturerAssociation(): ?array
    {
        $configured = (int) config('services.wayfair.manufacturer_id', 0);
        $query = <<<'GRAPHQL'
        query brandAssociations($request: GetSupplierBrandsAssociationsRequest) {
          supplierBrand {
            brandAssociations(request: $request) {
              brands {
                id
                manufacturer { id name }
              }
            }
          }
        }
        GRAPHQL;

        $res = $this->productAdditionGraphql($query, [
            'request' => [
                'supplierId' => (int) config('services.wayfair.supplier_id'),
                'marketContext' => $this->marketContext(),
                'page' => 1,
                'pageSize' => 25,
            ],
        ]);
        $brands = $res['data']['supplierBrand']['brandAssociations']['brands'] ?? [];
        $picked = null;
        foreach (is_array($brands) ? $brands : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['manufacturer']['id'] ?? $row['id'] ?? 0);
            $name = trim((string) ($row['manufacturer']['name'] ?? $row['name'] ?? ''));
            if ($id <= 0) {
                continue;
            }
            if ($configured > 0 && $id === $configured) {
                return ['id' => $id, 'name' => $name !== '' ? $name : 'Manufacturer '.$id];
            }
            if ($picked === null || stripos($name, '5 core') !== false) {
                $picked = ['id' => $id, 'name' => $name !== '' ? $name : 'Manufacturer '.$id];
                if (stripos($name, '5 core') !== false) {
                    return $picked;
                }
            }
        }
        if ($picked !== null) {
            return $picked;
        }
        if ($configured > 0) {
            return ['id' => $configured, 'name' => 'Configured manufacturer'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{success: bool, message: string, request_ids: list<string>}
     */
    public function submitProductAddition(array $request): array
    {
        $query = <<<'GRAPHQL'
        mutation submit($request: SubmitProductAdditionsRequest!) {
          productAddition {
            submit(request: $request) {
              requestIds
            }
          }
        }
        GRAPHQL;

        $res = $this->productAdditionGraphql($query, ['request' => $request]);
        $ids = $res['data']['productAddition']['submit']['requestIds'] ?? [];
        $requestIds = [];
        foreach (is_array($ids) ? $ids : [] as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $requestIds[] = $id;
            }
        }
        if ($requestIds !== []) {
            return [
                'success' => true,
                'message' => 'Wayfair accepted '.count($requestIds).' product addition request(s).',
                'request_ids' => $requestIds,
            ];
        }

        return [
            'success' => false,
            'message' => $res['message'] !== ''
                ? $res['message']
                : 'Wayfair product addition returned no request IDs.',
            'request_ids' => [],
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    public function getProductAdditionSubmissions(array $ids): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if ($ids === []) {
            return [];
        }

        $query = <<<'GRAPHQL'
        query submissions($request: GetProductAdditionsRequest!) {
          productAddition {
            submissions(request: $request) {
              requestId
              supplierId
              supplierPartNumber
              classId
              status
              validationStatus
              submissionStatus
              validationFlaws {
                questionId
                parentRank
                rank
                flawType
                flaw
              }
            }
          }
        }
        GRAPHQL;

        $res = $this->productAdditionGraphql($query, [
            'request' => [
                'supplierId' => (int) config('services.wayfair.supplier_id'),
                'ids' => $ids,
            ],
        ]);
        $rows = $res['data']['productAddition']['submissions'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @param  list<string>  $skus
     * @return array{class_id: int, class_name: string}|null
     */
    public function lookupCatalogClassForSkus(array $skus): ?array
    {
        $parts = $this->catalogPartNumberVariants($skus);
        if ($parts === []) {
            return null;
        }

        $fromWorking = $this->lookupClassFromWorkingCatalog($parts);
        if ($fromWorking !== null) {
            return $fromWorking;
        }

        return $this->lookupClassFromSupplierCatalogItems($parts);
    }

    /**
     * Read class from the supplier-catalog query used for listed SKUs, including nested SKU details.
     *
     * @param  list<string>  $parts
     * @return array{class_id: int, class_name: string}|null
     */
    private function lookupClassFromWorkingCatalog(array $parts): ?array
    {
        $queries = [
            <<<'GRAPHQL'
            query ($supplierId: Int!, $filter: ProductFilter, $paginationOptions: PaginationOptions) {
              supplierCatalog(supplierId: $supplierId, filter: $filter, paginationOptions: $paginationOptions) {
                products {
                  productId
                  supplierPartNumber
                  class { classId className }
                  classId
                  className
                  skus {
                    sku
                    displaySku
                    productDetails {
                      class { classId className }
                      classId
                      className
                      taxonomyCategoryId
                    }
                  }
                }
              }
            }
            GRAPHQL,
            <<<'GRAPHQL'
            query ($supplierId: Int!, $filter: ProductFilter, $paginationOptions: PaginationOptions) {
              supplierCatalog(supplierId: $supplierId, filter: $filter, paginationOptions: $paginationOptions) {
                products {
                  productId
                  supplierPartNumber
                  class { classId className }
                  classId
                  className
                }
              }
            }
            GRAPHQL,
            <<<'GRAPHQL'
            query ($supplierId: Int!, $filter: ProductFilter, $paginationOptions: PaginationOptions) {
              supplierCatalog(supplierId: $supplierId, filter: $filter, paginationOptions: $paginationOptions) {
                products {
                  productId
                  supplierPartNumber
                  classId
                  className
                }
              }
            }
            GRAPHQL,
            <<<'GRAPHQL'
            query ($supplierId: Int!, $filter: ProductFilter, $paginationOptions: PaginationOptions) {
              supplierCatalog(supplierId: $supplierId, filter: $filter, paginationOptions: $paginationOptions) {
                products {
                  productId
                  supplierPartNumber
                }
              }
            }
            GRAPHQL,
        ];

        $supplierId = $this->liveSupplierId();
        foreach ($queries as $query) {
            foreach (array_chunk($parts, 25) as $chunk) {
                $json = $this->catalogGraphqlRequest(
                    'https://api.wayfair.io/v1/supplier-catalog-api/graphql',
                    $query,
                    [
                        'supplierId' => $supplierId,
                        'filter' => ['supplierPartNumber' => ['in' => $chunk]],
                        'paginationOptions' => ['page' => 1, 'pageSize' => 25],
                    ]
                );
                if ($this->graphqlDenied($json)) {
                    return null;
                }
                if (! empty($json['errors'])) {
                    break;
                }
                $hit = $this->classFromCatalogRows($json['data']['supplierCatalog']['products'] ?? [], $parts);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
    }

    private function liveSupplierId(): int
    {
        $configured = (int) config('services.wayfair.supplier_id', 0);

        return $configured > 0 ? $configured : 2603;
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function catalogPartNumberVariants(array $skus): array
    {
        $parts = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $parts[] = $sku;
            $upper = $this->normalizePartNumber($sku);
            if ($upper !== '' && $upper !== $sku) {
                $parts[] = $upper;
            }
            $compact = preg_replace('/\s+/', '', $sku);
            if (is_string($compact) && $compact !== '' && $compact !== $sku) {
                $parts[] = $compact;
            }
        }

        return array_values(array_unique($parts));
    }

    /**
     * @param  list<string>  $parts
     * @return array{class_id: int, class_name: string}|null
     */
    private function lookupClassFromSupplierCatalogItems(array $parts): ?array
    {
        $query = <<<'GRAPHQL'
        query ($input: SupplierCatalogItemsInput!) {
          supplierCatalogItems(input: $input) {
            ... on SupplierCatalogItems {
              catalogItems {
                supplierPartNumber
                class { classId className }
              }
            }
            ... on SupplierCatalogItemsError {
              httpError { code message }
              internalError { code message }
            }
          }
        }
        GRAPHQL;

        foreach (array_chunk($parts, 25) as $chunk) {
            $json = $this->catalogGraphqlRequest(
                (string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
                $query,
                [
                    'input' => [
                        'filter' => ['supplierPartNumbers' => array_values($chunk)],
                        'paginationOptions' => ['page' => 1, 'pageSize' => 25],
                    ],
                ]
            );
            if ($this->graphqlDenied($json)) {
                return null;
            }
            if (! empty($json['errors'])) {
                Log::info('Wayfair supplierCatalogItems class lookup failed', [
                    'errors' => $this->formatGraphqlErrors($json['errors'] ?? []),
                ]);

                return null;
            }
            $items = $json['data']['supplierCatalogItems']['catalogItems'] ?? [];
            $hit = $this->classFromCatalogRows(is_array($items) ? $items : [], $parts);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * @return array{class_id: int, class_name: string}|null
     */
    public function searchTaxonomyClass(string $title): ?array
    {
        $title = trim($title);
        if (strlen($title) < 3) {
            return null;
        }

        $categories = $this->taxonomyCategories();
        if ($categories === []) {
            return null;
        }

        $hay = strtolower($title);
        $best = null;
        $bestScore = 0;
        foreach ($categories as $row) {
            if (! is_array($row)) {
                continue;
            }
            $classId = (int) ($row['taxonomyCategoryId'] ?? $row['classId'] ?? $row['class_id'] ?? 0);
            $className = trim((string) ($row['name'] ?? $row['className'] ?? ''));
            if ($classId <= 0 || $className === '') {
                continue;
            }
            $score = $this->taxonomyNameScore($hay, strtolower($className));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['class_id' => $classId, 'class_name' => $className];
            }
        }
        if ($best === null || $bestScore < 50) {
            return null;
        }
        if (! $this->productAdditionClassExists((int) $best['class_id'])) {
            return null;
        }

        return $best;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function taxonomyCategories(): array
    {
        $cached = Cache::get('wayfair.taxonomy_categories');
        if (is_array($cached)) {
            return $cached;
        }

        $query = <<<'GRAPHQL'
        query taxonomyCategories($marketContext: MarketContextInput!, $paginationOptions: PaginationOptions) {
          taxonomyCategories(marketContext: $marketContext, paginationOptions: $paginationOptions) {
            pageInfo { page pageSize hasNextPage totalPages }
            taxonomyCategories { taxonomyCategoryId name }
          }
        }
        GRAPHQL;
        $rows = [];
        $url = (string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        for ($page = 1; $page <= 20; $page++) {
            $json = $this->catalogGraphqlRequest($url, $query, [
                'marketContext' => $this->marketContext(),
                'paginationOptions' => ['page' => $page, 'pageSize' => 50],
            ]);
            if ($this->graphqlDenied($json) || ! empty($json['errors'])) {
                if ($page === 1) {
                    Cache::put('wayfair.taxonomy_categories', [], 900);
                }
                break;
            }
            $pageRows = $json['data']['taxonomyCategories']['taxonomyCategories'] ?? [];
            foreach (is_array($pageRows) ? $pageRows : [] as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            if (empty($json['data']['taxonomyCategories']['pageInfo']['hasNextPage'])) {
                break;
            }
        }
        Cache::put('wayfair.taxonomy_categories', $rows, $rows === [] ? 900 : 43200);

        return $rows;
    }

    private function taxonomyNameScore(string $title, string $name): int
    {
        if ($name === '') {
            return 0;
        }
        $score = 0;
        if ($name === $title || str_contains($title, $name)) {
            $score += 100 + strlen($name);
        }
        $words = preg_split('/\s+/', $name) ?: [];
        $hits = 0;
        foreach ($words as $word) {
            if (strlen($word) >= 3 && str_contains($title, $word)) {
                $hits++;
            }
        }
        if ($words !== [] && $hits === count($words)) {
            $score += 50 + strlen($name);
        } elseif ($hits >= 2) {
            $score += $hits * 8;
        }
        if (str_contains($title, 'speaker') && str_contains($name, 'speaker')) {
            $score += 40;
        }
        if (str_contains($title, 'ceiling') && str_contains($name, 'ceiling')) {
            $score += 30;
        }
        if ((str_contains($title, 'in wall') || str_contains($title, 'in-wall'))
            && (str_contains($name, 'in wall') || str_contains($name, 'in-wall') || str_contains($name, 'inwall'))) {
            $score += 30;
        }

        return $score;
    }

    private function productAdditionClassExists(int $classId): bool
    {
        if ($classId <= 0) {
            return false;
        }
        $res = $this->getProductAdditionQuestions($classId);

        return ($res['questions'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function graphqlDenied(array $json): bool
    {
        $message = strtolower($this->formatGraphqlErrors(is_array($json['errors'] ?? null) ? $json['errors'] : []));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'access denied')
            || str_contains($message, 'permission')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'deprecated');
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function catalogGraphqlRequest(string $url, string $query, array $variables = []): array
    {
        $supplierId = (string) $this->liveSupplierId();
        $tokens = [];
        try {
            $tokens[] = $this->authenticate();
        } catch (\Throwable) {
        }
        try {
            $catalog = $this->getTokenForCatalog();
            if ($catalog !== '' && ! in_array($catalog, $tokens, true)) {
                $tokens[] = $catalog;
            }
        } catch (\Throwable) {
        }
        $last = [];
        foreach ($tokens as $token) {
            foreach ([true, false] as $withSupplierHeader) {
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ];
                if ($withSupplierHeader && $supplierId !== '' && $supplierId !== '0') {
                    $headers['X-SELECTED-SUPPLIER-ID'] = $supplierId;
                }
                $response = $this->apiHttpClient()
                    ->withToken($token)
                    ->withHeaders($headers)
                    ->post($url, $variables === [] ? ['query' => $query] : ['query' => $query, 'variables' => $variables]);
                $json = $response->json();
                $json = is_array($json) ? $json : [];
                if (empty($json['errors']) && ($json['data'] ?? null) !== null) {
                    return $json;
                }
                $last = $json;
                if ($this->graphqlDenied($json)) {
                    continue;
                }
                if (! empty($json['errors'])) {
                    return $json;
                }
            }
        }

        return $last;
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $parts
     * @return array{class_id: int, class_name: string}|null
     */
    private function classFromCatalogRows(array $rows, array $parts): ?array
    {
        $want = [];
        foreach ($parts as $part) {
            $norm = $this->normalizePartNumber((string) $part);
            if ($norm !== '') {
                $want[$norm] = true;
            }
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $part = trim((string) ($row['supplierPartNumber'] ?? $row['supplier_part_number'] ?? ''));
            if ($want !== [] && $part !== '' && ! $this->catalogPartMatchesFamily($part, $want)) {
                continue;
            }
            $hit = $this->classFromAssoc($row);
            if ($hit !== null) {
                return $hit;
            }
            foreach (is_array($row['skus'] ?? null) ? $row['skus'] : [] as $skuRow) {
                if (! is_array($skuRow)) {
                    continue;
                }
                $hit = $this->classFromAssoc($skuRow);
                if ($hit !== null) {
                    return $hit;
                }
                $details = is_array($skuRow['productDetails'] ?? null) ? $skuRow['productDetails'] : [];
                $hit = $this->classFromAssoc($details);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{class_id: int, class_name: string}|null
     */
    private function classFromAssoc(array $row): ?array
    {
        $class = is_array($row['class'] ?? null) ? $row['class'] : [];
        $classId = (int) ($class['classId'] ?? $row['classId'] ?? $row['class_id'] ?? $row['taxonomyCategoryId'] ?? $row['taxonomy_category_id'] ?? 0);
        $className = trim((string) ($class['className'] ?? $row['className'] ?? $row['class_name'] ?? $row['name'] ?? ''));
        if ($classId > 0) {
            return ['class_id' => $classId, 'class_name' => $className];
        }

        return null;
    }

    /**
     * @param  array<string, true>  $want
     */
    private function catalogPartMatchesFamily(string $part, array $want): bool
    {
        $norm = $this->normalizePartNumber($part);
        if ($norm === '') {
            return false;
        }
        if (isset($want[$norm])) {
            return true;
        }
        $lettersHave = preg_replace('/\d+/', '', $norm) ?? '';
        foreach (array_keys($want) as $need) {
            $need = (string) $need;
            if ($need !== '' && (str_contains($norm, $need) || str_contains($need, $norm))) {
                return true;
            }
            $lettersNeed = preg_replace('/\d+/', '', $need) ?? '';
            if ($lettersNeed !== '' && strlen($lettersNeed) >= 6 && $lettersNeed === $lettersHave) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{data: array<string, mixed>, message: string}
     */
    private function productAdditionGraphql(string $query, array $variables = []): array
    {
        $token = $this->getTokenForCatalog();
        $supplierId = (string) config('services.wayfair.supplier_id');
        $urls = array_values(array_unique(array_filter([
            (string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
            $this->graphqlUrl,
        ])));

        $lastMessage = 'Wayfair product addition request failed.';
        foreach ($urls as $url) {
            $response = $this->apiHttpClient()
                ->withToken($token)
                ->withHeaders([
                    'X-SELECTED-SUPPLIER-ID' => $supplierId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'query' => $query,
                    'variables' => $variables,
                ]);
            $json = $response->json();
            $json = is_array($json) ? $json : [];
            if (! empty($json['errors'])) {
                $lastMessage = $this->formatWayfairGraphqlErrors($json['errors']);
                Log::warning('Wayfair product addition GraphQL error', [
                    'url' => $url,
                    'message' => $lastMessage,
                ]);
                continue;
            }

            return ['data' => is_array($json['data'] ?? null) ? $json['data'] : [], 'message' => ''];
        }

        return ['data' => [], 'message' => $lastMessage];
    }
}
