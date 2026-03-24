<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;
use App\Models\ReverbProduct;
use App\Models\ReverbListingStatus;

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
    

    /**
     * Normalize listing state/status from API response.
     *
     * @param array<string, mixed> $item
     */
    public function normalizeListingState(array $item): string
    {
        $state = $item['state'] ?? $item['status'] ?? null;
        if (is_array($state)) {
            $state = $state['slug'] ?? $state['name'] ?? $state['title'] ?? 'unknown';
        }
        if ($state === null && isset($item['_embedded']['state'])) {
            $emb = $item['_embedded']['state'];
            $state = is_array($emb) ? ($emb['slug'] ?? $emb['name'] ?? 'unknown') : (string) $emb;
        }
        return $state ? strtolower(trim((string) $state)) : 'unknown';
    }

    /**
     * Fetch ALL Reverb listings (including ended) and update ProductStockMapping + ReverbListingStatus.
     * Uses state=all&per_page=100. Ended/out_of_stock/suspended => inventory_reverb=0; live => actual quantity.
     * SKUs not in API response get inventory_reverb=0 (cleanup).
     */
    public function getInventory()
    {
        $log = Log::channel('reverb_sync');
        $inventory = [];
        $url = 'https://api.reverb.com/api/my/listings?state=all&per_page=100';
        $pageNumber = 0;
        $startedAt = now()->toIso8601String();

        try {
            if (!File::isDirectory(storage_path('logs/reverb'))) {
                File::ensureDirectoryExists(storage_path('logs/reverb'));
            }
            $log->info('Reverb getInventory started', [
                'timestamp' => $startedAt,
                'initial_url' => $url,
            ]);

            while ($url) {
                $pageNumber++;
                $log->debug('Reverb getInventory page request', [
                    'page' => $pageNumber,
                    'url' => $url,
                ]);

                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('services.reverb.token'),
                        'Accept' => 'application/hal+json',
                        'Accept-Version' => '3.0',
                    ])->get($url);

                if ($response->failed()) {
                    $log->error('Reverb getInventory API error', [
                        'page' => $pageNumber,
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    Log::channel('reverb_daily')->error('Reverb getInventory API error', [
                        'page' => $pageNumber,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500),
                    ]);
                    return [];
                }

                $data = $response->json();
                $listings = $data['listings'] ?? [];
                $pageCount = is_array($listings) ? count($listings) : 0;
                $cumulativeTotal = count($inventory) + $pageCount;

                $log->info('Reverb getInventory page result', [
                    'page' => $pageNumber,
                    'listings_on_page' => $pageCount,
                    'cumulative_total' => $cumulativeTotal,
                ]);

                if (is_array($listings)) {
                    foreach ($listings as $item) {
                        $state = $this->normalizeListingState($item);
                        $sku = isset($item['sku']) ? trim((string) $item['sku']) : null;
                        $qty = isset($item['inventory']) ? (int) $item['inventory'] : 0;
                        $listingId = $item['id'] ?? null;
                        $log->debug('Reverb getInventory listing', [
                            'sku' => $sku,
                            'quantity' => $qty,
                            'state' => $state,
                            'listing_id' => $listingId,
                        ]);
                        if ($sku !== null && $sku !== '') {
                            $inventory[] = [
                                'sku' => $sku,
                                'quantity' => $qty,
                                'state' => $state,
                                'listing_id' => $listingId,
                                'title' => $item['title'] ?? null,
                            ];
                        }
                    }
                }

                $nextHref = $data['_links']['next']['href'] ?? null;
                $url = $nextHref ? trim($nextHref) : null;
                if ($url) {
                    usleep(200000);
                }
            }

            $totalListings = count($inventory);
            $log->info('Reverb getInventory fetch summary', [
                'total_listings_found' => $totalListings,
                'pages_fetched' => $pageNumber,
                'timestamp' => now()->toIso8601String(),
            ]);

            $apiSkus = [];
            $zeroStates = ['ended', 'out_of_stock', 'suspended'];

            foreach ($inventory as $entry) {
                $sku = $entry['sku'];
                $state = $entry['state'];
                $listingId = $entry['listing_id'];
                $qty = $entry['quantity'];
                $effectiveQty = in_array($state, $zeroStates, true) ? 0 : $qty;
                $apiSkus[$sku] = true;

                ReverbListingStatus::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'value' => [
                            'state' => $state,
                            'listing_id' => $listingId,
                            'inventory' => $qty,
                            'title' => $entry['title'] ?? null,
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]
                );

                $affected = ProductStockMapping::where('sku', $sku)->update(['inventory_reverb' => $effectiveQty]);
                if ($affected > 0) {
                    $log->debug('Reverb getInventory updated stock', ['sku' => $sku, 'inventory_reverb' => $effectiveQty, 'state' => $state]);
                }
            }

            $updatedCount = count($apiSkus);
            $dbTotalSkus = ProductStockMapping::count();
            $skusToZero = ProductStockMapping::whereNotIn('sku', array_keys($apiSkus))->pluck('sku')->all();
            $cleanupCount = 0;
            if (count($skusToZero) > 0) {
                $cleanupCount = ProductStockMapping::whereIn('sku', $skusToZero)->update(['inventory_reverb' => 0]);
                $log->info('Reverb getInventory cleanup: set inventory_reverb=0 for SKUs not in API', [
                    'skus_affected' => $cleanupCount,
                    'sample' => array_slice($skusToZero, 0, 20),
                ]);
            }

            $log->info('Reverb getInventory DB update comparison', [
                'total_listings_from_api' => $totalListings,
                'skus_updated_in_db' => $updatedCount,
                'cleanup_zeroed' => $cleanupCount,
                'product_stock_mapping_total_rows' => $dbTotalSkus,
            ]);

            return $inventory;
        } catch (\Throwable $e) {
            $log->error('Reverb getInventory exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Log::channel('reverb_daily')->error('Reverb getInventory exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

    /**
     * Update product title on Reverb by SKU.
     * Uses getListingIdBySku then PUT to /api/listings/{id} with title.
     *
     * @param string $sku
     * @param string $title
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $token = config('services.reverb.token');
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (services.reverb.token).',
            ];
        }

        $title = trim($title);
        if ($title === '') {
            return [
                'success' => false,
                'message' => 'Title cannot be empty.',
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
        $payload = ['title' => $title];

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
                $titlePreview = strlen($title) > 80 ? substr($title, 0, 80) . '...' : $title;
                Log::info('Reverb title updated successfully', [
                    'sku' => $sku,
                    'listing_id' => $listingId,
                    'title_preview' => $titlePreview,
                ]);
                return [
                    'success' => true,
                    'message' => "Title updated for SKU: {$sku} (listing ID: {$listingId}).",
                    'listing_id' => $listingId,
                ];
            }

            $body = $response->body();
            $status = $response->status();
            Log::error('Reverb title update failed', [
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
            Log::error('Reverb updateTitle exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'listing_id' => $listingId,
            ];
        }
    }

    /**
     * Update listing description / marketing copy with bullet text (full body, no truncation).
     *
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateBulletPoints(string $sku, string $bulletPoints): array
    {
        $token = config('services.reverb.token');
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb API token not configured (services.reverb.token).'];
        }

        $bulletPoints = trim($bulletPoints);
        if ($bulletPoints === '') {
            return ['success' => false, 'message' => 'Bullet points cannot be empty.'];
        }

        $listingId = $this->getListingIdBySku($sku);
        if ($listingId === null) {
            return ['success' => false, 'message' => "No Reverb listing found for SKU: {$sku}."];
        }

        $html = '<ul>';
        foreach (preg_split('/\r\n|\r|\n/', $bulletPoints) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $html .= '</ul>';

        $updateUrl = 'https://api.reverb.com/api/listings/'.$listingId;
        $payload = [
            'description' => $html,
            'plain_text_description' => $bulletPoints,
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])
                ->put($updateUrl, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Reverb listing description updated.',
                    'listing_id' => $listingId,
                ];
            }

            return [
                'success' => false,
                'message' => 'Reverb API error (HTTP '.$response->status().'): '.$response->body(),
                'listing_id' => $listingId,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'listing_id' => $listingId];
        }
    }
}
