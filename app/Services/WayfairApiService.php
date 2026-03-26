<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\Concerns\ResolvesBulletPointIdentifier;

class WayfairApiService
{
    use ResolvesBulletPointIdentifier;

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
        $this->authenticate();
  

        $this->clientId = config('services.wayfair.client_id');
        $this->clientSecret = config('services.wayfair.client_secret');
        $this->audience = config('services.wayfair.audience');
    }

    /**
     * Authenticate with Wayfair and get access token (no scope).
     */
    protected function authenticate()
    {
        return $this->getAccessTokenWithScope(null);
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
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.wayfair.client_id'),
            'client_secret' => config('services.wayfair.client_secret'),
        ];
        $audience = config('services.wayfair.audience');
        if ($audience !== null && $audience !== '') {
            $payload['audience'] = $audience;
        }
        $scopeToUse = $scope ?? config('services.wayfair.catalog_scope');
        if ($scopeToUse !== null && $scopeToUse !== '') {
            $payload['scope'] = $scopeToUse;
        }

        $response = Http::withoutVerifying()->asForm()->post('https://sso.auth.wayfair.com/oauth/token', $payload);

        if ($response->failed()) {
            throw new \Exception('Failed to authenticate with Wayfair API: ' . $response->body());
        }

        return (string) $response->json('access_token');
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
        $response = Http::withoutVerifying()->asForm()->post($this->authUrl, [
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

            $requestId = $this->submitTitleUpdate($token, $sku, $title);
            if ($requestId === null) {
                return ['success' => false, 'message' => 'Wayfair: failed to submit title update or get requestId.'];
            }

            return $this->pollUpdateStatus($token, $requestId, $sku);
        } catch (\Throwable $e) {
            Log::error('Wayfair updateTitle exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Step 1: Submit updateMarketSpecificCatalogItems mutation; returns requestId or null.
     */
    private function submitTitleUpdate(string $token, string $sku, string $title): ?string
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
                        'itemName' => $title,
                    ],
                ],
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
            return null;
        }

        $requestId = $data['data']['updateCatalogEntitiesMutations']['updateMarketSpecificCatalogItems']['requestId'] ?? null;
        if ($requestId === null) {
            Log::warning('Wayfair - No requestId in response', ['sku' => $sku, 'response' => $data]);
            return null;
        }

        Log::info('Wayfair - Title update submitted', ['sku' => $sku, 'requestId' => $requestId]);
        return $requestId;
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
     * Uses the same key-features update path; description is split into lines.
     *
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description is required.'];
        }

        return $this->updateBulletPoints($identifier, $description);
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
}
