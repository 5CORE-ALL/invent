<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ProductStockMapping;

class WalmartApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $marketplaceId;
    protected $token;

    public function __construct()
    {
        $this->clientId       = config('services.walmart.client_id');
        $this->clientSecret   = config('services.walmart.client_secret');
        $this->baseUrl        = config('services.walmart.api_endpoint');
        $this->marketplaceId  = config('services.walmart.marketplace_id');
        $this->token          = $this->getAccessToken();
    }


    public function getAccessToken()
    {
        $clientId     = config('services.walmart.client_id');
        $clientSecret = config('services.walmart.client_secret');

        $authorization = base64_encode("{$clientId}:{$clientSecret}");

        $response = Http::withoutVerifying()->asForm()->withHeaders([
            'Authorization'          => "Basic {$authorization}",
            'WM_QOS.CORRELATION_ID'  => uniqid(),
            'WM_SVC.NAME'            => 'Walmart Marketplace',
            'accept'                 => 'application/json',
        ])->post('https://marketplace.walmartapis.com/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'] ?? null;
        }

        // dd($response->json());

        return null;
    }

  public function getAccessTokenV1(): ?string
{
    $clientId     = config('services.walmart.client_id');
    $clientSecret = config('services.walmart.client_secret');

    if (!$clientId || !$clientSecret) {
        Log::error('Walmart credentials missing.');
        return null;
    }

    $authorization = base64_encode("{$clientId}:{$clientSecret}");

    $response = Http::withoutVerifying()->asForm()->withHeaders([
        'Authorization'         => "Basic {$authorization}",
        'WM_QOS.CORRELATION_ID' => "123",
        'WM_SVC.NAME'           => 'Walmart Marketplace',
        'Accept'                => 'application/json',
        'Content-Type'          => 'application/x-www-form-urlencoded',
    ])->post('https://marketplace.walmartapis.com/v3/token', [
        'grant_type' => 'client_credentials',
    ]);

    if ($response->successful()) {
        return $response->json()['access_token'] ?? null;
    }

    Log::error('Failed to fetch Walmart access token', [
        'status' => $response->status(),
        'body' => $response->json(),
    ]);

    return null;
}



    public function updatePrice(string $sku, float $price): array
    {
        $accessToken = $this->getAccessToken();

        $payload = [
            'sku' => $sku,
            'pricing' => [
                [
                    'currentPriceType' => 'BASE',
                    'currentPrice' => [
                        'currency' => 'USD',
                        'amount' => number_format($price, 2, '.', '')
                    ]
                ]
            ]
        ];

        $endpoint = $this->baseUrl . "/v3/price";

        $response = Http::withHeaders([
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'WM_SEC.ACCESS_TOKEN' => $accessToken,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->put($endpoint, $payload);

        if ($response->failed()) {
            throw new Exception('Failed to update Walmart price: ' . $response->body());
        }
        Log::info('Walmart Price Update Response: ', $response->json());
        return $response->json();
    }



    public function getinventory(): array
{
    $accessToken = $this->getAccessToken();
    if (!$accessToken) {
        throw new \Exception('Failed to retrieve Walmart access token.');
    }

    $endpoint = $this->baseUrl . '/v3/inventories';
    $limit = 50;
    $cursor = '';
    $collected = [];

    do {
        $query = ['limit' => $limit];
        if ($cursor) {
           $query['nextCursor'] = urlencode($cursor);
        }

        $headers = [
            'WM_SEC.ACCESS_TOKEN'   => $accessToken,
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'Accept'                => 'application/json',
        ];

        $request = Http::withHeaders($headers);
        if (config('filesystems.default') === 'local') {$request = $request->withoutVerifying();}

        // $response = $request->get($endpoint, $query);
        $response = $request->get($endpoint,['limit' => 50,'nextCursor' => $cursor]);

        if ($response->failed()) {
            Log::error('Walmart inventory fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch Walmart inventory');
        }

        $json = $response->json();
        $items = $json['elements']['inventories'] ?? [];
        // $collected = array_merge($collected, $items); // ✅ Correctly flatten the array
        foreach ($items as $item) { $collected[] = $item; }
        $cursor = $json['meta']['nextCursor']??'';
    } while ($cursor);

    // ✅ Now process after full collection
    foreach ($collected as $item) {
        $sku = $item['sku'] ?? null;
        $quantity = 0;

        if (isset($item['nodes'][0]['availToSellQty']['amount'])) {
            $quantity = (int) $item['nodes'][0]['availToSellQty']['amount'];
        } elseif (isset($item['nodes'][0]['inputQty']['amount'])) {
            $quantity = (int) $item['nodes'][0]['inputQty']['amount'];
        }

        if (!$sku) {
            Log::warning('Missing SKU in Walmart inventory data', $item);
            continue;
        }
   ProductStockMapping::where('sku', $sku)->update(['inventory_walmart' => (int) $quantity]);
   
        // ProductStockMapping::updateOrCreate(
        //     ['sku' => $sku],
        //     ['inventory_walmart' => $quantity]
        // );
    }

    Log::info('Total Walmart inventory items collected: ' . count($collected));
    return $collected;
}


}
