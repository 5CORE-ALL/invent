<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WayfairApiService
{
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
     * Authenticate with Wayfair and get access token
     */
    protected function authenticate()
    {
        $response = Http::withoutVerifying()->asForm()->post('https://sso.auth.wayfair.com/oauth/token', [
            'grant_type'    => 'client_credentials',
            'client_id'     => config('services.wayfair.client_id'),
            'client_secret' => config('services.wayfair.client_secret'),
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to authenticate with Wayfair API: ' . $response->body());
        }

        return $response->json('access_token');
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
     * Update product title on Wayfair by supplier part number (SKU).
     * Uses product/catalog update if available; otherwise attempts feed or returns not_supported.
     *
     * @param string $sku Supplier part number (SKU)
     * @param string $title New title
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
            $token = $this->authenticate();
            if (! $token) {
                return ['success' => false, 'message' => 'Wayfair authentication failed.'];
            }

            // Wayfair Product API: try GraphQL mutation or product update endpoint if available
            $mutation = <<<'GRAPHQL'
            mutation UpdateProductTitle($partNumber: String!, $title: String!) {
                updateProductTitle(partNumber: $partNumber, title: $title) {
                    success
                    message
                }
            }
            GRAPHQL;

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->post($this->graphqlUrl, [
                    'query' => $mutation,
                    'variables' => [
                        'partNumber' => $sku,
                        'title' => $title,
                    ],
                ]);

            $data = $response->json();
            $errors = $data['errors'] ?? null;
            $result = $data['data']['updateProductTitle'] ?? null;

            if ($errors || ! $result) {
                $msg = $errors[0]['message'] ?? $result['message'] ?? $response->body() ?? 'Unknown error';
                Log::warning('Wayfair title update failed or unsupported', [
                    'sku' => $sku,
                    'response' => $data,
                    'status' => $response->status(),
                ]);
                return ['success' => false, 'message' => (string) $msg];
            }

            if (! empty($result['success'])) {
                Log::info('Wayfair title updated successfully', ['sku' => $sku]);
                return ['success' => true, 'message' => "Title updated for SKU: {$sku}."];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Wayfair title update failed.',
            ];
        } catch (\Throwable $e) {
            Log::error('Wayfair updateTitle exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
