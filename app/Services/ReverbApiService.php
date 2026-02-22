<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;
use App\Models\ReverbProduct;

class ReverbApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $region;
    protected $marketplaceId;
    protected $awsAccessKey;
    protected $awsSecretKey;
    protected $endpoint;

    public function __construct()
    {
        $this->clientId = config('services.amazon_sp.client_id');
        $this->clientSecret = config('services.amazon_sp.client_secret');
        $this->refreshToken = config('services.amazon_sp.refresh_token');
        $this->region = config('services.amazon_sp.region');
        $this->marketplaceId = config('services.amazon_sp.marketplace_id');
        $this->awsAccessKey = config('services.amazon_sp.aws_access_key');
        $this->awsSecretKey = config('services.amazon_sp.aws_secret_key');
        $this->endpoint = 'https://sellingpartnerapi-na.amazon.com';
    }
    

 public function getInventory()
{
    $inventory = [];
    $url = 'https://api.reverb.com/api/my/listings'; // Start URL

    try {
        while ($url) {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                Log::error('Failed to fetch inventory page.', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return []; // or break; depending on whether you want partial data
            }

            $data = $response->json();

            // Process listings
            if (isset($data['listings']) && is_array($data['listings'])) {
                foreach ($data['listings'] as $item) {
                    if (isset($item['sku'], $item['inventory'])) {
                        $inventory[] = [
                            'sku' => $item['sku'],
                            'quantity' => $item['inventory'],
                        ];
                    }
                }
            }

            // Check for next page
            if (isset($data['_links']['next']['href'])) {
                $url = $data['_links']['next']['href'];
                // Clean URL: Reverb sometimes adds trailing spaces in href
                $url = trim($url);
            } else {
                $url = null; // No more pages
            }
        }
       
        foreach ($inventory as $sku => $data) {
            $sku = $data['sku'] ?? null;
                    $quantity = $data['quantity'];
                if (!$sku) {
                    Log::warning('Missing SKU in parsed Amazon data', $item);
                    continue;
                }
                
            ProductStockMapping::where('sku', $sku)->update(['inventory_reverb' => (int) $quantity]);
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $sku],
            //     ['inventory_reverb'=>$quantity,]
            // );
        }
        return $inventory;

    } catch (\Throwable $e) {
        Log::error('Exception during paginated inventory fetch: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return [];
    }
}

    /**
     * Get Reverb listing ID for a SKU.
     * First checks reverb_products.reverb_listing_id; if not found, paginates through API my/listings.
     *
     * @param string $sku
     * @return string|null Listing ID or null if not found
     */
    public function getListingIdBySku(string $sku): ?string
    {
        $normalizedSku = trim($sku);
        if ($normalizedSku === '') {
            return null;
        }

        // Prefer reverb_listing_id from reverb_products (fast, no API call)
        $product = ReverbProduct::where('sku', $normalizedSku)
            ->whereNotNull('reverb_listing_id')
            ->where('reverb_listing_id', '!=', '')
            ->first();
        if ($product && $product->reverb_listing_id) {
            return (string) trim($product->reverb_listing_id);
        }

        $url = 'https://api.reverb.com/api/my/listings';
        $token = config('services.reverb.token');
        if (!$token) {
            Log::warning('Reverb API token not configured (services.reverb.token)');
            return null;
        }

        try {
            while ($url) {
                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/hal+json',
                        'Accept-Version' => '3.0',
                    ])
                    ->get(trim($url));

                if ($response->failed()) {
                    Log::error('Reverb getListingIdBySku: failed to fetch listings', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();
                $listings = $data['listings'] ?? [];
                foreach ($listings as $item) {
                    $listingSku = isset($item['sku']) ? trim((string) $item['sku']) : null;
                    if ($listingSku !== null && strcasecmp($listingSku, $normalizedSku) === 0) {
                        $id = $item['id'] ?? null;
                        if ($id !== null) {
                            return (string) $id;
                        }
                    }
                }

                $url = isset($data['_links']['next']['href']) ? trim($data['_links']['next']['href']) : null;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Reverb getListingIdBySku exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Update listing price on Reverb by SKU.
     * Uses getListingIdBySku then PUT to /api/listings/{id} with price.
     *
     * @param string $sku
     * @param float $price
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updatePrice(string $sku, float $price): array
    {
        $token = config('services.reverb.token');
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (services.reverb.token).',
            ];
        }

        $price = round((float) $price, 2);
        if ($price <= 0) {
            return [
                'success' => false,
                'message' => 'Price must be greater than 0.',
            ];
        }

        $listingId = $this->getListingIdBySku($sku);
        if ($listingId === null) {
            return [
                'success' => false,
                'message' => "No Reverb listing found for SKU: {$sku}.",
            ];
        }

        $updateUrl = 'https://api.reverb.com/api/listings/' . $listingId;
        $payload = [
            'price' => [
                'amount' => number_format($price, 2, '.', ''),
                'currency' => 'USD',
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])
                ->put($updateUrl, $payload);

            if ($response->successful()) {
                Log::info('Reverb price updated successfully', [
                    'sku' => $sku,
                    'listing_id' => $listingId,
                    'price' => $price,
                ]);
                return [
                    'success' => true,
                    'message' => "Price \${$price} updated for SKU: {$sku} (listing ID: {$listingId}).",
                    'listing_id' => $listingId,
                ];
            }

            $body = $response->body();
            $status = $response->status();
            Log::error('Reverb price update failed', [
                'sku' => $sku,
                'listing_id' => $listingId,
                'status' => $status,
                'body' => $body,
            ]);
            return [
                'success' => false,
                'message' => "Reverb API error (HTTP {$status}): " . $body,
                'listing_id' => $listingId,
            ];
        } catch (\Throwable $e) {
            Log::error('Reverb updatePrice exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'listing_id' => $listingId,
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'listing_id' => $listingId,
            ];
        }
    }
}
