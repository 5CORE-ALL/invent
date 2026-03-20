<?php

namespace App\Services;

use App\Models\ProductStockMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalmartService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $marketplaceId;
    protected $channelType;
    protected $market;

    public function __construct()
    {
        $this->clientId = (string) config('services.walmart.client_id');
        $this->clientSecret = (string) config('services.walmart.client_secret');
        $this->baseUrl = rtrim((string) config('services.walmart.api_endpoint', 'https://marketplace.walmartapis.com'), '/');
        $this->marketplaceId = (string) config('services.walmart.marketplace_id', 'WMTMP');
        $this->channelType = (string) config('services.walmart.channel_type', '0f3e4dd4-0514-4346-b39d-af0e00ea066d');
        $this->market = strtolower((string) env('WALMART_MARKET', 'us'));
    }

    public function getAccessToken(): ?string
    {
        $cacheKey = 'walmart_access_token';
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        if ($this->clientId === '' || $this->clientSecret === '') {
            Log::error('Walmart token: missing credentials', [
                'has_client_id' => $this->clientId !== '',
                'has_client_secret' => $this->clientSecret !== '',
            ]);
            return null;
        }

        $tokenUrl = $this->baseUrl . '/v3/token';
        $authorization = base64_encode($this->clientId . ':' . $this->clientSecret);

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Basic ' . $authorization,
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                // Some Walmart docs require this for token endpoint.
                'WM_MARKET' => $this->market,
            ])
            ->timeout(45)
            ->withBody('grant_type=client_credentials', 'application/x-www-form-urlencoded')
            ->post($tokenUrl);

        if ($response->successful()) {
            $payload = $response->json() ?? [];
            $token = $payload['access_token'] ?? null;
            $expiresIn = (int) ($payload['expires_in'] ?? 900);
            if (!empty($token)) {
                Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 60)));
                Log::info('Walmart token generated', ['expires_in' => $expiresIn]);
                return $token;
            }
            Log::error('Walmart token response missing access_token', ['body' => $response->body()]);
            return null;
        }

        Log::error('Walmart token generation failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'token_url' => $tokenUrl,
            'hint' => 'Check Basic base64(client_id:client_secret), app credentials, and marketplace authorization.',
        ]);
        return null;
    }

    private function walmartHeaders(string $accessToken, bool $json = true): array
    {
        $headers = [
            'WM_SEC.ACCESS_TOKEN' => $accessToken,
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_MARKET_ID' => $this->marketplaceId,
            'WM_CONSUMER.CHANNEL.TYPE' => $this->channelType,
            'Accept' => 'application/json',
        ];
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
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

            $response = Http::withoutVerifying()
                ->withHeaders($this->walmartHeaders($accessToken, false))
                ->attach('file', $feedXml, 'mp-item-feed.xml')
                ->post($this->baseUrl . '/v3/feeds', ['feedType' => 'MP_ITEM']);

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

        $response = Http::withoutVerifying()
            ->withHeaders($this->walmartHeaders($token, false))
            ->get($this->baseUrl . '/v3/feeds/' . $feedId);

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
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get Walmart access token'];
        }

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

        $response = Http::withoutVerifying()
            ->withHeaders($this->walmartHeaders($accessToken))
            ->put($endpoint, $payload);

        if ($response->failed()) {
            Log::error('Walmart price update failed', [
                'sku' => $sku,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'message' => 'Failed to update Walmart price: ' . $response->body()];
        }
        Log::info('Walmart Price Update Response', ['sku' => $sku, 'response' => $response->json()]);
        return ['success' => true, 'response' => $response->json()];
    }


    public function getAccessTokenV1(): ?string
    {
        return $this->getAccessToken();
    }


    public function getinventory(): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('Walmart inventory: failed to retrieve access token');
            return [];
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

            $response = Http::withoutVerifying()
                ->withHeaders($this->walmartHeaders($accessToken, false))
                ->get($endpoint, $query);

            if ($response->failed()) {
                Log::error('Walmart inventory fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $json = $response->json() ?? [];
            $items = $json['elements'] ?? [];
            $collected = array_merge($collected, $items);
            $cursor = $json['meta']['nextCursor'] ?? null;
        } while ($cursor);

        foreach ($collected as $item) {
            $sku = $item['sku'] ?? null;
            if (!$sku) {
                Log::warning('Walmart inventory item missing SKU', ['item' => $item]);
                continue;
            }

            $quantity = 0;
            if (isset($item['nodes'][0]['availToSellQty']['amount'])) {
                $quantity = (int) $item['nodes'][0]['availToSellQty']['amount'];
            } elseif (isset($item['nodes'][0]['inputQty']['amount'])) {
                $quantity = (int) $item['nodes'][0]['inputQty']['amount'];
            }

            ProductStockMapping::updateOrCreate(
                ['sku' => $sku],
                ['inventory_walmart' => $quantity]
            );
        }

        return $collected;
    }

}
