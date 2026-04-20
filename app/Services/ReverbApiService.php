<?php

namespace App\Services;

use App\Models\ProductStockMapping;
use App\Models\ReverbListingStatus;
use App\Models\ReverbProduct;
use App\Services\Support\DescriptionWithImagesFormatter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
     * Bearer token for Reverb API calls.
     * Uses OAuth2 client_credentials at config('services.reverb.oauth_url') when client_id + client_secret are set;
     * otherwise falls back to config('services.reverb.token') (manual personal / legacy token).
     */
    public static function getReverbBearerToken(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'reverb_oauth_access_token';
        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } else {
            Cache::forget($cacheKey);
        }

        $clientId = trim((string) config('services.reverb.client_id', ''));
        $clientSecret = trim((string) config('services.reverb.client_secret', ''));
        $staticToken = trim((string) config('services.reverb.token', ''));

        if ($clientId === '' || $clientSecret === '') {
            return $staticToken !== '' ? $staticToken : null;
        }

        $oauthUrl = trim((string) config('services.reverb.oauth_url', 'https://reverb.com/oauth/token'));
        $scope = trim((string) config('services.reverb.scope', 'read_listings write_listings read_orders'));

        try {
            $response = Http::withoutVerifying()
                ->asForm()
                ->acceptJson()
                ->timeout(45)
                ->post($oauthUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $scope,
                ]);

            if (! $response->successful()) {
                Log::warning('Reverb OAuth token request failed', [
                    'url' => $oauthUrl,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 800),
                ]);

                return $staticToken !== '' ? $staticToken : null;
            }

            $json = $response->json();
            $access = $json['access_token'] ?? null;
            if (! is_string($access) || $access === '') {
                Log::warning('Reverb OAuth response missing access_token', ['response_keys' => is_array($json) ? array_keys($json) : []]);

                return $staticToken !== '' ? $staticToken : null;
            }

            $expiresIn = (int) ($json['expires_in'] ?? 3500);
            $ttlSeconds = max(120, $expiresIn - 180);
            Cache::put($cacheKey, $access, now()->addSeconds($ttlSeconds));

            return $access;
        } catch (\Throwable $e) {
            Log::error('Reverb OAuth exception', ['error' => $e->getMessage()]);

            return $staticToken !== '' ? $staticToken : null;
        }
    }

    public static function forgetCachedReverbToken(): void
    {
        Cache::forget('reverb_oauth_access_token');
    }

    /**
     * Normalize listing state/status from API response.
     *
     * @param  array<string, mixed>  $item
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
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $url = $apiBase.'/my/listings?state=all&per_page=100';
        $pageNumber = 0;
        $startedAt = now()->toIso8601String();

        try {
            if (! File::isDirectory(storage_path('logs/reverb'))) {
                File::ensureDirectoryExists(storage_path('logs/reverb'));
            }
            $log->info('Reverb getInventory started', [
                'timestamp' => $startedAt,
                'initial_url' => $url,
            ]);

            $token = self::getReverbBearerToken();
            if (! $token) {
                $log->error('Reverb getInventory: no bearer token (set REVERB_CLIENT_ID/REVERB_CLIENT_SECRET or REVERB_TOKEN)');

                return [];
            }

            while ($url) {
                $pageNumber++;
                $log->debug('Reverb getInventory page request', [
                    'page' => $pageNumber,
                    'url' => $url,
                ]);

                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
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
        $token = self::getReverbBearerToken();
        if (! $token) {
            Log::warning('Reverb API token not configured (REVERB_CLIENT_ID/REVERB_CLIENT_SECRET or REVERB_TOKEN)');

            return null;
        }

        try {
            while ($url) {
                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
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
            Log::error('Reverb getListingIdBySku exception: '.$e->getMessage(), [
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
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updatePrice(string $sku, float $price): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
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

        $updateUrl = 'https://api.reverb.com/api/listings/'.$listingId;
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
                    'Authorization' => 'Bearer '.$token,
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
                'message' => "Reverb API error (HTTP {$status}): ".$body,
                'listing_id' => $listingId,
            ];
        } catch (\Throwable $e) {
            Log::error('Reverb updatePrice exception: '.$e->getMessage(), [
                'sku' => $sku,
                'listing_id' => $listingId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: '.$e->getMessage(),
                'listing_id' => $listingId,
            ];
        }
    }

    /**
     * Update product title on Reverb by SKU.
     * Uses getListingIdBySku then PUT to /api/listings/{id} with title.
     *
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
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

        $updateUrl = 'https://api.reverb.com/api/listings/'.$listingId;
        $payload = ['title' => $title];

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
                $titlePreview = strlen($title) > 80 ? substr($title, 0, 80).'...' : $title;
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
                'message' => "Reverb API error (HTTP {$status}): ".$body,
                'listing_id' => $listingId,
            ];
        } catch (\Throwable $e) {
            Log::error('Reverb updateTitle exception: '.$e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: '.$e->getMessage(),
                'listing_id' => $listingId,
            ];
        }
    }

    /**
     * Push bullet lines to the listing Features section (PUT listing.features), not the long description.
     * Long-form copy remains on {@see updateDescription()}.
     *
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).'];
        }

        $features = self::bulletPointsStringToFeatureList($bulletPoints);
        if ($features === []) {
            return ['success' => false, 'message' => 'Bullet points cannot be empty.'];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.'];
        }

        $listingId = null;
        $product = ReverbProduct::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if ($product && $product->reverb_listing_id) {
            $listingId = trim((string) $product->reverb_listing_id);
        }
        if (! $listingId) {
            $product = ReverbProduct::query()->where('reverb_listing_id', $trim)->first();
            if ($product && $product->reverb_listing_id) {
                $listingId = trim((string) $product->reverb_listing_id);
            }
        }
        if (! $listingId) {
            $listingId = $this->getListingIdBySku($trim);
        }
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        $current = $this->fetchCurrentReverbDescription($token, $listingId, $trim);
        if (($current['html'] ?? '') === '' && ($current['plain'] ?? '') === '') {
            $dbDesc = trim((string) (ReverbProduct::query()
                ->where(function ($q) use ($trim, $listingId) {
                    $q->where('sku', $trim)
                        ->orWhere('sku', strtoupper($trim))
                        ->orWhere('sku', strtolower($trim))
                        ->orWhere('reverb_listing_id', $listingId);
                })
                ->value('description') ?? ''));
            if ($dbDesc !== '') {
                $current['plain'] = $dbDesc;
                $current['html'] = '<div class="product-description">'.nl2br(htmlspecialchars($dbDesc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</div>';
            }
        }

        // Use top-level fields (same shape as other Reverb updates), and only include description
        // keys when we have non-empty values to avoid accidental clearing.
        $payload = [
            'features' => $features,
        ];
        if (($current['html'] ?? '') !== '') {
            $payload['description'] = $current['html'];
        }
        if (($current['plain'] ?? '') !== '') {
            $payload['plain_text_description'] = $current['plain'];
        }

        try {
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);

            if ($response->successful()) {
                $this->saveFeaturesToReverbProducts($trim, $listingId, $features);

                return [
                    'success' => true,
                    'message' => 'Reverb listing features updated.',
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

    /**
     * Newline-separated bullet text → non-empty feature strings (one per line).
     *
     * @return list<string>
     */
    private static function bulletPointsStringToFeatureList(string $bulletPoints): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $bulletPoints);
        if (! is_array($lines)) {
            return [];
        }
        $features = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^[-*•\d.\)\s]+/u', '', $line);
            $line = trim($line);
            if ($line !== '') {
                $features[] = $line;
            }
        }

        return $features;
    }

    /**
     * PUT /api/listings/{id} with 429/503 retry (same spirit as Shopify rate-limit retries).
     *
     * @param  array<string, mixed>  $payload
     */
    private function reverbPutListingWithRetry(string $token, string $listingId, array $payload, int $maxRetries = 4): Response
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $updateUrl = $apiBase.'/listings/'.$listingId;
        $bearer = $token;
        $last = null;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $last = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$bearer,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])
                ->put($updateUrl, $payload);

            if ($last->successful()) {
                return $last;
            }
            if ($last->status() === 401 && $attempt < $maxRetries - 1) {
                self::forgetCachedReverbToken();
                $refreshed = self::getReverbBearerToken(true);
                if (is_string($refreshed) && $refreshed !== '') {
                    $bearer = $refreshed;
                    usleep(400000);

                    continue;
                }
            }
            if (in_array($last->status(), [429, 503], true) && $attempt < $maxRetries - 1) {
                $waitMs = (int) (500000 * ($attempt + 1));
                if ($last->status() === 429 && is_numeric($last->header('Retry-After'))) {
                    $waitMs = min(2_000_000, (int) ((float) $last->header('Retry-After') * 1_000_000));
                }
                usleep($waitMs);

                continue;
            }
            break;
        }

        return $last;
    }

    /**
     * Long-form description (not bullet list). PUT listing `description` / `plain_text_description`.
     *
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).'];
        }

        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description cannot be empty.'];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.'];
        }

        $listingId = null;
        $product = ReverbProduct::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if ($product && $product->reverb_listing_id) {
            $listingId = trim((string) $product->reverb_listing_id);
        }
        if (! $listingId) {
            $product = ReverbProduct::query()->where('reverb_listing_id', $trim)->first();
            if ($product && $product->reverb_listing_id) {
                $listingId = trim((string) $product->reverb_listing_id);
            }
        }
        if (! $listingId) {
            $listingId = $this->getListingIdBySku($trim);
        }
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        $current = $this->fetchCurrentReverbDescription($token, $listingId, $trim);
        $incomingPlain = trim($description);
        $skuForImages = $product && $product->sku ? (string) $product->sku : $trim;
        $incomingHtml = DescriptionWithImagesFormatter::buildHtmlWithImages(
            $incomingPlain,
            $trim,
            $skuForImages,
            'Product Image',
            12,
            $imageUrls
        )['html'];
        $mergedPlain = $this->appendUniqueText($current['plain'], $incomingPlain);
        $hasImagePush = array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== '') !== [];
        if ($hasImagePush) {
            $mergedHtml = $incomingHtml;
        } elseif ($current['html'] !== '') {
            $mergedHtml = $this->appendUniqueHtml($current['html'], $incomingHtml, $incomingPlain);
        } else {
            $mergedHtml = $incomingHtml;
        }

        $payload = [
            'description' => $mergedHtml,
            'plain_text_description' => $mergedPlain,
        ];

        try {
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);

            if ($response->successful()) {
                $photoUrls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
                $photoUrls = array_slice($photoUrls, 0, 25);
                if ($photoUrls !== []) {
                    $imgRes = $this->updateListingImages($trim, $photoUrls);
                    if (! ($imgRes['success'] ?? false)) {
                        return [
                            'success' => true,
                            'message' => 'Reverb listing description updated. Photos: '.($imgRes['message'] ?? 'update skipped'),
                            'listing_id' => $listingId,
                        ];
                    }
                }

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

    /**
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateProductDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->updateDescription($identifier, $description, $imageUrls);
    }

    /**
     * Replace listing photos (public HTTPS URLs). Reverb may require URLs it can fetch.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).'];
        }

        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 25);
        if ($urls === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.'];
        }

        $listingId = null;
        $product = ReverbProduct::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if ($product && $product->reverb_listing_id) {
            $listingId = trim((string) $product->reverb_listing_id);
        }
        if (! $listingId) {
            $product = ReverbProduct::query()->where('reverb_listing_id', $trim)->first();
            if ($product && $product->reverb_listing_id) {
                $listingId = trim((string) $product->reverb_listing_id);
            }
        }
        if (! $listingId) {
            $listingId = $this->getListingIdBySku($trim);
        }
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        $payload = [
            'photos' => $urls,
            'photo_upload_method' => 'override_position',
        ];

        try {
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Reverb listing images updated.',
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

    /**
     * Append a gallery image to a Reverb listing by public HTTPS URL (Reverb fetches the image from your server).
     * Merges with existing photos then PUTs with {@see photo_upload_method} override (same as image docs).
     *
     * Uses a per-listing cache lock so concurrent image-push jobs do not read the same snapshot and
     * overwrite each other (which previously left only one new image on the listing).
     *
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function appendImageUrlToListingBySku(string $sku, string $newPublicImageUrl): array
    {
        $newPublicImageUrl = trim($newPublicImageUrl);
        if ($newPublicImageUrl === '') {
            return ['success' => false, 'message' => 'Image URL is empty.'];
        }
        if (! str_starts_with($newPublicImageUrl, 'http')) {
            return [
                'success' => false,
                'message' => 'Reverb needs an absolute public URL. Set APP_URL to your publicly reachable site (HTTPS).',
            ];
        }

        $listingId = $this->getListingIdBySku(trim($sku));
        if ($listingId === null) {
            return [
                'success' => false,
                'message' => 'No Reverb listing found for SKU: '.trim($sku).'. Create or link the listing on Reverb first.',
            ];
        }

        $lockKey = 'reverb:append-listing-image:'.sha1((string) $listingId);

        try {
            return Cache::lock($lockKey, 120)->block(90, function () use ($listingId, $newPublicImageUrl) {
                $token = self::getReverbBearerToken();
                if (! $token) {
                    return [
                        'success' => false,
                        'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
                        'listing_id' => $listingId,
                    ];
                }

                $existing = $this->fetchListingImagePublicUrls($token, $listingId);
                $norm = static function (string $u): string {
                    return rtrim(strtolower($u), '/');
                };
                $newN = $norm($newPublicImageUrl);
                foreach ($existing as $u) {
                    if ($norm((string) $u) === $newN) {
                        return [
                            'success' => true,
                            'message' => 'This image is already on the Reverb listing.',
                            'listing_id' => $listingId,
                        ];
                    }
                }

                $all = array_values(array_unique([...$existing, $newPublicImageUrl]));
                if (count($all) > 25) {
                    $all = array_slice($all, 0, 25);
                }

                $payload = [
                    'photos' => $all,
                    'photo_upload_method' => 'override_position',
                ];

                try {
                    $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);
                    if ($response->successful()) {
                        return [
                            'success' => true,
                            'message' => 'Image added to the Reverb listing; Reverb is processing the photo from your URL.',
                            'listing_id' => $listingId,
                        ];
                    }

                    $body = $response->body();

                    return [
                        'success' => false,
                        'message' => 'Reverb API error (HTTP '.$response->status().'): '.mb_substr($body, 0, 1000),
                        'listing_id' => $listingId,
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'message' => 'Reverb request failed: '.$e->getMessage(),
                        'listing_id' => $listingId,
                    ];
                }
            });
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'message' => 'Timed out waiting to update this listing (other image pushes are still running). Retry in a few seconds.',
                'listing_id' => $listingId,
            ];
        }
    }

    /**
     * @return list<string> Public Reverb image URLs (empty if the listing has no images or the endpoint shape differs)
     */
    private function fetchListingImagePublicUrls(string $token, string $listingId): array
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $listingId = rawurlencode(trim($listingId));
        $candidates = [
            $apiBase.'/listings/'.$listingId.'/images',
            $apiBase.'/listings/'.$listingId.'/images/',
        ];
        foreach ($candidates as $url) {
            try {
                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/hal+json',
                        'Accept-Version' => '3.0',
                    ])->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $data = $response->json() ?? [];
                if (! is_array($data)) {
                    continue;
                }
                $urls = $this->parseReverbListingImagesResponse($data);
                if ($urls !== []) {
                    return $urls;
                }
            } catch (\Throwable) {
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function parseReverbListingImagesResponse(array $data): array
    {
        $out = [];
        $buckets = [
            $data['images'] ?? null,
            $data['photos'] ?? null,
        ];
        if (isset($data['_embedded']['images']) && is_array($data['_embedded']['images'])) {
            $buckets[] = $data['_embedded']['images'];
        }
        if (isset($data['_embedded']['photos']) && is_array($data['_embedded']['photos'])) {
            $buckets[] = $data['_embedded']['photos'];
        }
        foreach ($buckets as $candidates) {
            if (! is_array($candidates)) {
                continue;
            }
            foreach ($candidates as $img) {
                if (is_string($img) && str_starts_with($img, 'http')) {
                    $out[] = $img;
                } elseif (is_array($img) && ! empty($img['url']) && is_string($img['url']) && str_starts_with($img['url'], 'http')) {
                    $out[] = (string) $img['url'];
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Image Master compatibility method: push images then persist image_urls in reverb_products.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateImages(string $identifier, array $images): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $res = $this->updateListingImages($identifier, $images);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $trim = trim($identifier);
        $listingId = (string) ($res['listing_id'] ?? '');
        $saved = $this->saveImageUrlsToReverbProducts($trim, $listingId, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Reverb listing images updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToReverbProducts(string $identifier, string $listingId, array $images): bool
    {
        try {
            if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'image_urls')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $query = ReverbProduct::query();
            $matched = false;
            if ($identifier !== '') {
                $count = (clone $query)
                    ->where(function ($q) use ($identifier) {
                        $q->where('sku', $identifier)
                            ->orWhere('sku', strtoupper($identifier))
                            ->orWhere('sku', strtolower($identifier));
                    })
                    ->update(['image_urls' => $payload]);
                $matched = $count > 0;
            }
            if (! $matched && $listingId !== '') {
                $count = ReverbProduct::query()->where('reverb_listing_id', $listingId)->update(['image_urls' => $payload]);
                $matched = $count > 0;
            }

            return $matched;
        } catch (\Throwable $e) {
            Log::warning('Reverb image_urls save failed', ['identifier' => $identifier, 'listing_id' => $listingId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{plain: string, html: string}
     */
    private function fetchCurrentReverbDescription(string $token, string $listingId, ?string $identifier = null): array
    {
        // 1) Database first (requested) from reverb_products.
        try {
            if (Schema::hasTable('reverb_products')) {
                $row = ReverbProduct::query()
                    ->when($identifier !== null && trim($identifier) !== '', function ($q) use ($identifier, $listingId) {
                        $id = trim((string) $identifier);
                        $q->where(function ($qq) use ($id, $listingId) {
                            $qq->where('sku', $id)
                                ->orWhere('sku', strtoupper($id))
                                ->orWhere('sku', strtolower($id))
                                ->orWhere('reverb_listing_id', $listingId);
                        });
                    }, function ($q) use ($listingId) {
                        $q->where('reverb_listing_id', $listingId);
                    })
                    ->first();
                if ($row) {
                    $plainDb = trim((string) ($row->description ?? ''));
                    if ($plainDb !== '') {
                        return [
                            'plain' => $plainDb,
                            'html' => '<div class="product-description">'.nl2br(htmlspecialchars($plainDb, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</div>',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Reverb DB-first description fetch failed', ['identifier' => $identifier, 'listing_id' => $listingId, 'error' => $e->getMessage()]);
        }

        // 2) API fallback
        try {
            $url = 'https://api.reverb.com/api/listings/'.$listingId;
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])->get($url);

            if (! $response->successful()) {
                return ['plain' => '', 'html' => ''];
            }

            $json = $response->json();
            $plain = trim((string) ($json['listing']['plain_text_description']
                ?? $json['plain_text_description']
                ?? $json['listing']['plain_text']
                ?? $json['plain_text']
                ?? ''));
            $html = trim((string) ($json['listing']['description']
                ?? $json['description']
                ?? $json['listing']['body']
                ?? $json['body']
                ?? ''));
            if ($plain === '' && $html !== '') {
                $plain = trim(strip_tags($html));
            }

            return ['plain' => $plain, 'html' => $html];
        } catch (\Throwable $e) {
            Log::warning('Reverb fetch current description failed', ['listing_id' => $listingId, 'error' => $e->getMessage()]);

            return ['plain' => '', 'html' => ''];
        }
    }

    private function appendUniqueText(string $current, string $incoming): string
    {
        $current = trim($current);
        $incoming = trim($incoming);
        if ($incoming === '') {
            return $current;
        }
        if ($current === '') {
            return $incoming;
        }
        if (str_contains(mb_strtolower($current), mb_strtolower($incoming))) {
            return $current;
        }

        return $current."\n\n".$incoming;
    }

    private function appendUniqueHtml(string $currentHtml, string $incomingHtml, string $incomingPlain): string
    {
        $currentHtml = trim($currentHtml);
        if ($currentHtml === '') {
            return $incomingHtml;
        }
        $currentPlain = trim(strip_tags($currentHtml));
        if ($incomingPlain !== '' && str_contains(mb_strtolower($currentPlain), mb_strtolower($incomingPlain))) {
            return $currentHtml;
        }

        return $currentHtml.'<br><br>'.$incomingHtml;
    }

    /**
     * @param  list<string>  $features
     */
    private function saveFeaturesToReverbProducts(string $identifier, string $listingId, array $features): bool
    {
        try {
            if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'features')) {
                return false;
            }
            $payload = json_encode(array_values($features), JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return false;
            }

            $matched = false;
            if ($identifier !== '') {
                $count = ReverbProduct::query()
                    ->where(function ($q) use ($identifier) {
                        $q->where('sku', $identifier)
                            ->orWhere('sku', strtoupper($identifier))
                            ->orWhere('sku', strtolower($identifier));
                    })
                    ->update(['features' => $payload]);
                $matched = $count > 0;
            }
            if (! $matched && $listingId !== '') {
                $count = ReverbProduct::query()->where('reverb_listing_id', $listingId)->update(['features' => $payload]);
                $matched = $count > 0;
            }

            return $matched;
        } catch (\Throwable $e) {
            Log::warning('Reverb saveFeaturesToReverbProducts failed', ['identifier' => $identifier, 'listing_id' => $listingId, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
