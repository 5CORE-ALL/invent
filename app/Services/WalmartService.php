<?php

namespace App\Services;

use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalmartService
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
        $cacheKey = 'walmart_access_token';
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $clientId     = config('services.walmart.client_id');
        $clientSecret = config('services.walmart.client_secret');

        $authorization = base64_encode("{$clientId}:{$clientSecret}");

        $response = Http::asForm()->withHeaders([
            'Authorization'          => "Basic {$authorization}",
            'WM_QOS.CORRELATION_ID'  => uniqid(),
            'WM_SVC.NAME'            => 'Walmart Marketplace',
            'accept'                 => 'application/json',
        ])->post('https://marketplace.walmartapis.com/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            $token = $response->json()['access_token'] ?? null;
            if ($token) {
                // Walmart token is valid for ~15 minutes; cache for 14.
                Cache::put($cacheKey, $token, now()->addMinutes(14));
            }
            return $token;
        }

        // dd($response->json());

        return null;
    }

    /**
     * Update Walmart item title using Feeds API (MP_ITEM / REPLACE).
     *
     * @return array{success:bool,message:string,feed_id?:string,feed_status?:string,response?:mixed}
     */
    public function updateTitle(string $sku, string $title): array
    {
        Log::info("🚀 Walmart title update started - SKU: {$sku}");

        try {
            $sku = trim($sku);
            $title = trim($title);
            if ($sku === '' || $title === '') {
                return ['success' => false, 'message' => 'SKU and title are required'];
            }

            $accessToken = $this->getAccessToken();
            if (! $accessToken) {
                Log::error("❌ Walmart title update failed - SKU: {$sku}, Error: Failed to get access token");
                return ['success' => false, 'message' => 'Failed to get Walmart access token'];
            }

            $feedXml = $this->buildItemFeedXml($sku, $title);
            Log::info('Walmart feed XML payload', [
                'sku' => $sku,
                'xml' => $feedXml,
            ]);

            $response = Http::withoutVerifying()->withHeaders([
                'WM_SEC.ACCESS_TOKEN' => $accessToken,
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_MARKET_ID' => $this->marketplaceId,
                'WM_CONSUMER.CHANNEL.TYPE' => config('services.walmart.channel_type'),
                'Accept' => 'application/json',
            ])->attach('file', $feedXml, 'mp-item-feed.xml')->post($this->baseUrl . '/v3/feeds', [
                'feedType' => 'MP_ITEM',
            ]);

            Log::info('Walmart feed submission response', [
                'sku' => $sku,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (! $response->successful() && $response->status() !== 202) {
                Log::error("❌ Walmart title update failed - SKU: {$sku}, Error: Feed submission failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'message' => 'Feed submission failed: ' . $response->body()];
            }

            $payload = $response->json() ?? [];
            $feedId = $payload['feedId'] ?? $payload['id'] ?? null;

            if (! $feedId && is_string($response->body())) {
                if (preg_match('/"feedId"\s*:\s*"?(?<id>[^",\s]+)"?/', $response->body(), $m)) {
                    $feedId = $m['id'];
                } elseif (preg_match('/<feedId>(?<id>[^<]+)<\/feedId>/', $response->body(), $m)) {
                    $feedId = $m['id'];
                }
            }

            if (! $feedId) {
                Log::error("❌ Walmart title update failed - SKU: {$sku}, Error: feedId not returned", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'message' => 'Feed accepted but feedId missing'];
            }

            Log::info("📤 Walmart feed submitted - Feed ID: {$feedId}");

            // Poll feed status until final state.
            $statusResult = $this->pollFeedStatus((string) $feedId, $accessToken);
            $finalStatus = strtoupper((string) ($statusResult['feedStatus'] ?? $statusResult['feed_status'] ?? 'UNKNOWN'));

            if (in_array($finalStatus, ['PROCESSED', 'COMPLETED', 'DONE'], true)) {
                Log::info("✅ Walmart title updated successfully - SKU: {$sku}");
                return [
                    'success' => true,
                    'message' => 'Walmart title updated successfully',
                    'feed_id' => (string) $feedId,
                    'feed_status' => $finalStatus,
                    'response' => $statusResult,
                ];
            }

            Log::error("❌ Walmart title update failed - SKU: {$sku}, Error: Feed status {$finalStatus}", [
                'feed_id' => $feedId,
                'feed_response' => $statusResult,
            ]);
            return [
                'success' => false,
                'message' => 'Feed processing failed with status: ' . $finalStatus,
                'feed_id' => (string) $feedId,
                'feed_status' => $finalStatus,
                'response' => $statusResult,
            ];
        } catch (\Throwable $e) {
            Log::error("❌ Walmart title update failed - SKU: {$sku}, Error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update bullet points for a Walmart item (150 char limit).
     * Uses MP_ITEM feed with shortDescription.
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $sku, string $bulletPoints): array
    {
        $sku = trim($sku);
        $bulletPoints = mb_substr(trim($bulletPoints), 0, 150);
        if ($bulletPoints === '') {
            return ['success' => false, 'message' => 'Bullet points cannot be empty.'];
        }

        try {
            $product = ProductMaster::where('sku', $sku)->orWhere('SKU', $sku)->first();
            $productName = $product?->parent ?? $product?->sku ?? $sku;

            $accessToken = $this->getAccessToken();
            if (! $accessToken) {
                return ['success' => false, 'message' => 'Failed to get Walmart access token.'];
            }

            $feedXml = $this->buildItemFeedXmlWithBullets($sku, $productName, $bulletPoints);
            $response = Http::withoutVerifying()->withHeaders([
                'WM_SEC.ACCESS_TOKEN' => $accessToken,
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_MARKET_ID' => $this->marketplaceId,
                'WM_CONSUMER.CHANNEL.TYPE' => config('services.walmart.channel_type'),
                'Accept' => 'application/json',
            ])->attach('file', $feedXml, 'mp-item-feed.xml')->post($this->baseUrl . '/v3/feeds', [
                'feedType' => 'MP_ITEM',
            ]);

            if (! $response->successful() && $response->status() !== 202) {
                return ['success' => false, 'message' => 'Feed submission failed: ' . $response->body()];
            }

            $payload = $response->json() ?? [];
            $feedId = $payload['feedId'] ?? $payload['id'] ?? null;
            if (! $feedId && preg_match('/"feedId"\s*:\s*"?(?<id>[^",\s]+)"?/', (string) $response->body(), $m)) {
                $feedId = $m['id'];
            }
            if (! $feedId) {
                return ['success' => false, 'message' => 'Feed accepted but feedId missing.'];
            }

            $statusResult = $this->pollFeedStatus((string) $feedId, $accessToken);
            $finalStatus = strtoupper((string) ($statusResult['feedStatus'] ?? $statusResult['feed_status'] ?? 'UNKNOWN'));

            if (in_array($finalStatus, ['PROCESSED', 'COMPLETED', 'DONE'], true)) {
                return ['success' => true, 'message' => 'Bullet points updated successfully.'];
            }

            return ['success' => false, 'message' => 'Feed status: ' . $finalStatus];
        } catch (\Throwable $e) {
            Log::error('Walmart updateBulletPoints exception', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function buildItemFeedXmlWithBullets(string $sku, string $productName, string $bulletPoints): string
    {
        $escapedSku = htmlspecialchars($sku, ENT_XML1);
        $escapedName = htmlspecialchars($productName, ENT_XML1);
        $escapedBullets = htmlspecialchars($bulletPoints, ENT_XML1);

        return '<?xml version="1.0" encoding="UTF-8"?>
<MPItemFeed xmlns="http://walmart.com/" xmlns:ns2="http://walmart.com/mp/orders" xmlns:ns3="http://walmart.com/">
  <MPItemFeedHeader>
    <mart>US</mart>
    <sellingChannel>marketplace</sellingChannel>
    <processMode>REPLACE</processMode>
    <subset>EXTERNAL</subset>
    <locale>en</locale>
    <version>4.8</version>
  </MPItemFeedHeader>
  <MPItem>
    <Orderable>
      <sku>' . $escapedSku . '</sku>
      <productName>' . $escapedName . '</productName>
      <shelfDescription>' . $escapedBullets . '</shelfDescription>
      <productType>Item</productType>
    </Orderable>
  </MPItem>
</MPItemFeed>';
    }

    private function buildItemFeedXml(string $sku, string $title): string
    {
        $escapedSku = htmlspecialchars($sku, ENT_XML1);
        $escapedTitle = htmlspecialchars($title, ENT_XML1);

        return '<?xml version="1.0" encoding="UTF-8"?>
<MPItemFeed xmlns="http://walmart.com/" xmlns:ns2="http://walmart.com/mp/orders" xmlns:ns3="http://walmart.com/">
  <MPItemFeedHeader>
    <mart>US</mart>
    <sellingChannel>marketplace</sellingChannel>
    <processMode>REPLACE</processMode>
    <subset>EXTERNAL</subset>
    <locale>en</locale>
    <version>4.8</version>
  </MPItemFeedHeader>
  <MPItem>
    <Orderable>
      <sku>' . $escapedSku . '</sku>
      <productName>' . $escapedTitle . '</productName>
      <shelfDescription>' . $escapedTitle . '</shelfDescription>
      <productType>Item</productType>
    </Orderable>
  </MPItem>
</MPItemFeed>';
    }

    /**
     * Get Walmart feed status by feed ID.
     */
    public function getFeedStatus(string $feedId, ?string $accessToken = null): array
    {
        $token = $accessToken ?: $this->getAccessToken();
        if (! $token) {
            return ['feedStatus' => 'ERROR', 'message' => 'Failed to get access token for feed status'];
        }

        $response = Http::withoutVerifying()->withHeaders([
            'WM_SEC.ACCESS_TOKEN' => $token,
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_MARKET_ID' => $this->marketplaceId,
            'WM_CONSUMER.CHANNEL.TYPE' => config('services.walmart.channel_type'),
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/v3/feeds/' . $feedId);

        if ($response->failed()) {
            return [
                'feedStatus' => 'ERROR',
                'status' => $response->status(),
                'message' => $response->body(),
            ];
        }

        return $response->json() ?? [];
    }

    private function pollFeedStatus(string $feedId, string $accessToken): array
    {
        $attempts = 10;
        $sleepSeconds = 3;

        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->getFeedStatus($feedId, $accessToken);
            $feedStatus = strtoupper((string) ($status['feedStatus'] ?? $status['feed_status'] ?? 'UNKNOWN'));

            if (in_array($feedStatus, ['PROCESSED', 'COMPLETED', 'DONE', 'ERROR', 'FAILED'], true)) {
                return $status;
            }

            sleep($sleepSeconds);
        }

        return ['feedStatus' => 'UNKNOWN', 'message' => 'Feed status polling timeout', 'feedId' => $feedId];
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


public function getinventory(): array
{
    $accessToken = $this->getAccessToken();
    if (!$accessToken) {
        throw new \Exception('Failed to retrieve Walmart access token.');
    }

    $endpoint = $this->baseUrl . '/v3/inventories';
    $limit = 50;
    $cursor = null;
    $collected = [];

    do {
        $query = ['limit' => $limit];
        if ($cursor) {
            $query['nextCursor'] = $cursor;
        }

        $headers = [
            'WM_SEC.ACCESS_TOKEN'   => $accessToken,
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'WM_MARKET_ID'          => $this->marketplaceId,
            'Accept'                => 'application/json',
        ];

        $request = Http::withHeaders($headers);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        $response = $request->get($endpoint, $query);

        if ($response->failed()) {
            Log::error('Walmart inventory fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch Walmart inventory');
        }

        $json = $response->json();
        $items = $json['elements'] ?? [];
        $collected = array_merge($collected, $items);
        $cursor = $json['meta']['nextCursor'] ?? null;

    } while ($cursor);

    // Process the collected inventory data
    $collected=$collected['inventories'];
    // dd($collected);
    foreach ($collected as $item) {
        $sku = $item['sku'] ?? null;
        $quantity = 0;
        
        // Extract quantity from the first node's available to sell amount
        if (isset($item['nodes'][0]['availToSellQty']['amount'])) {
            $quantity = (int) $item['nodes'][0]['availToSellQty']['amount'];
        } elseif (isset($item['nodes'][0]['inputQty']['amount'])) {
            // Fallback to inputQty if availToSellQty is not available
            $quantity = (int) $item['nodes'][0]['inputQty']['amount'];
        }
         if (!$sku) {
                Log::warning('Missing SKU in parsed Amazon data', $item);
                continue;
            }
            
        // Only process if we have a valid SKU
        if ($sku !== null) {
            ProductStockMapping::updateOrCreate(
                ['sku' => $sku],
                ['inventory_walmart' => $quantity]
            );
        }
    }

    return $collected;
}

}
