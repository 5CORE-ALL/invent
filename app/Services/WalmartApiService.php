<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ProductStockMapping;

/**
 * Walmart Marketplace API service.
 *
 * - Auth: OAuth2 client_credentials token for v3 API.
 * - Price: Update item price via /v3/price.
 * - Inventory: Fetch all inventory from /v3/inventories (paginated) and sync
 *   quantities to ProductStockMapping.inventory_walmart.
 */
class WalmartApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $marketplaceId;

    public function __construct()
    {
        $this->clientId      = config('services.walmart.client_id');
        $this->clientSecret  = config('services.walmart.client_secret');
        $this->baseUrl       = config('services.walmart.api_endpoint');
        $this->marketplaceId = config('services.walmart.marketplace_id');
    }

    /**
     * Get OAuth2 access token for Walmart Marketplace API (v3).
     * Returns null if credentials are missing or token request fails.
     */
    public function getAccessToken(): ?string
    {
        $clientId     = $this->clientId ?: config('services.walmart.client_id');
        $clientSecret = $this->clientSecret ?: config('services.walmart.client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('Walmart API: credentials missing.');
            return null;
        }

        $authorization = base64_encode("{$clientId}:{$clientSecret}");
        $response = Http::withoutVerifying()->asForm()->withHeaders([
            'Authorization'         => "Basic {$authorization}",
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'Accept'                => 'application/json',
            'Content-Type'          => 'application/x-www-form-urlencoded',
        ])->post('https://marketplace.walmartapis.com/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'] ?? null;
        }

        Log::error('Walmart API: failed to get access token', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);
        return null;
    }



    /**
     * Update a single item's price on Walmart (v3 price API).
     *
     * @throws Exception if token is missing or API request fails
     */
    public function updatePrice(string $sku, float $price): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new Exception('Walmart API: no access token (check credentials).');
        }

        $payload = [
            'sku'     => $sku,
            'pricing' => [
                [
                    'currentPriceType' => 'BASE',
                    'currentPrice'    => [
                        'currency' => 'USD',
                        'amount'   => number_format($price, 2, '.', ''),
                    ],
                ],
            ],
        ];

        $endpoint = rtrim($this->baseUrl, '/') . '/v3/price';
        $response = Http::withHeaders([
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SEC.ACCESS_TOKEN'   => $accessToken,
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'Accept'                => 'application/json',
            'Content-Type'          => 'application/json',
        ])->put($endpoint, $payload);

        if ($response->failed()) {
            throw new Exception('Failed to update Walmart price: ' . $response->body());
        }
        Log::info('Walmart price updated', ['sku' => $sku, 'response' => $response->json()]);
        return $response->json();
    }



    /**
     * Fetch all inventory from Walmart v3/inventories (paginated) and sync
     * quantities to ProductStockMapping.inventory_walmart.
     * Uses nextCursor for pagination; updates are batched for performance.
     *
     * @return array Raw inventory elements from the API
     * @throws \Exception if token missing or API request fails
     */
    public function getinventory(): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Walmart API: no access token (check credentials).');
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/v3/inventories';
        $limit    = 50;
        $cursor   = null;
        $collected = [];

        $request = Http::withHeaders([
            'WM_SEC.ACCESS_TOKEN'   => $accessToken,
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'Accept'                => 'application/json',
        ]);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        do {
            $query = ['limit' => $limit];
            if ($cursor !== null && $cursor !== '') {
                $query['nextCursor'] = $cursor;
            }
            $response = $request->get($endpoint, $query);

            if ($response->failed()) {
                Log::error('Walmart inventory fetch failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \Exception('Failed to fetch Walmart inventory: ' . $response->body());
            }

            $json   = $response->json();
            $items  = $json['elements']['inventories'] ?? [];
            foreach ($items as $item) {
                $collected[] = $item;
            }
            $cursor = $json['meta']['nextCursor'] ?? null;
        } while ($cursor);

        $this->syncInventoryToMapping($collected);
        Log::info('Walmart inventory synced', ['count' => count($collected)]);
        return $collected;
    }

    /**
     * Batch-update ProductStockMapping.inventory_walmart from API inventory list.
     * Uses one bulk CASE WHEN UPDATE per chunk to avoid N+1 queries.
     */
    protected function syncInventoryToMapping(array $inventories): void
    {
        $updates = [];
        foreach ($inventories as $item) {
            $sku = $item['sku'] ?? null;
            if (!$sku) {
                Log::warning('Walmart inventory: missing SKU', $item);
                continue;
            }
            $quantity = 0;
            if (isset($item['nodes'][0]['availToSellQty']['amount'])) {
                $quantity = (int) $item['nodes'][0]['availToSellQty']['amount'];
            } elseif (isset($item['nodes'][0]['inputQty']['amount'])) {
                $quantity = (int) $item['nodes'][0]['inputQty']['amount'];
            }
            $updates[$sku] = $quantity;
        }

        $chunkSize = 100;
        $chunks    = array_chunk($updates, $chunkSize, true);
        foreach ($chunks as $chunk) {
            $skus    = array_keys($chunk);
            $cases   = [];
            $bindings = [];
            foreach ($chunk as $sku => $qty) {
                $cases[]    = 'WHEN ? THEN ?';
                $bindings[] = $sku;
                $bindings[] = $qty;
            }
            $placeholders = implode(' ', $cases);
            $inPlaceholders = implode(',', array_fill(0, count($skus), '?'));
            $bindings = array_merge($bindings, $skus);
            $table = (new ProductStockMapping)->getTable();
            DB::update(
                "UPDATE {$table} SET inventory_walmart = CASE sku {$placeholders} END WHERE sku IN ({$inPlaceholders})",
                $bindings
            );
        }
    }
}
