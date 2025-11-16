<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
            'client_id'     => env('WAYFAIR_CLIENT_ID'),
            'client_secret' => env('WAYFAIR_CLIENT_SECRET'),
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
      $limit = 100;
    $offset = 0;
    $inventoryUrl = 'https://api.wayfair.io/v1/product-catalog-api/graphql';
    $allInventory = [];

    do {
        $query = <<<'GRAPHQL'
     
        GRAPHQL;

        $response = Http::withoutVerifying()->withToken($this->getAccessToken())->post($inventoryUrl, [
            'query' => $query,
            'variables' => [
                'limit' => $limit,
                'offset' => $offset,
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception("Wayfair API Error: " . $response->body());
        }

        dd($response->body());

        $inventoryItems = $response->json()['data']['inventory'] ?? [];

        if (empty($inventoryItems)) {
            break;
        }

        $allInventory = array_merge($allInventory, $inventoryItems);
        $offset += $limit;
    } while (count($inventoryItems) === $limit);

    dd($allInventory);

    return array_map(function ($item) {
        return [
            'sku' => $item['supplierPartNumber'] ?? null,
            'quantity' => $item['quantityOnHand'] ?? 0,
        ];
    }, $allInventory);

}


      
}
