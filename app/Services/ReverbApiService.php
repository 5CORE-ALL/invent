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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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

    /** Ended / out_of_stock / suspended listings => 0 inventory for pricing & N Map. */
    public static function effectiveInventoryQuantity(int $qty, ?string $listingState): int
    {
        $state = $listingState ? strtolower(trim((string) $listingState)) : 'live';
        $zeroStates = ['ended', 'out_of_stock', 'suspended'];

        return in_array($state, $zeroStates, true) ? 0 : max(0, $qty);
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
        $product = ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->where('reverb_listing_id', '!=', '')
            ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($normalizedSku)])
            ->first();
        if ($product && $product->reverb_listing_id) {
            return (string) trim($product->reverb_listing_id);
        }

        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $token = self::getReverbBearerToken();
        if (! $token) {
            Log::warning('Reverb API token not configured (REVERB_CLIENT_ID/REVERB_CLIENT_SECRET or REVERB_TOKEN)');

            return null;
        }

        try {
            // Fast path: filtered search (docs: my/listings?sku=&state=all includes drafts)
            $filteredUrl = $apiBase.'/my/listings?'.http_build_query([
                'sku' => $normalizedSku,
                'state' => 'all',
                'per_page' => 50,
            ]);
            $filteredRes = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->get($filteredUrl);

            if ($filteredRes->successful()) {
                $data = $filteredRes->json();
                $listings = $data['listings'] ?? [];
                foreach ($listings as $item) {
                    $listingSku = isset($item['sku']) ? trim((string) $item['sku']) : null;
                    if ($listingSku !== null && strcasecmp($listingSku, $normalizedSku) === 0) {
                        $id = $item['id'] ?? null;
                        if ($id !== null) {
                            $this->persistReverbListingId($normalizedSku, (string) $id);

                            return (string) $id;
                        }
                    }
                }
            }

            // Paginate all listings — must use state=all or drafts / non-live SKUs are invisible (default filter).
            $url = $apiBase.'/my/listings?state=all&per_page=50';
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
                            $this->persistReverbListingId($normalizedSku, (string) $id);

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
     * Resolve listing id from reverb_products or Reverb API (same order as image push).
     */
    private function resolveReverbListingId(string $identifier): ?string
    {
        $trim = trim($identifier);
        if ($trim === '') {
            return null;
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

        return $listingId !== '' ? $listingId : null;
    }

    /**
     * Persist the freshly-resolved listing id so the next lookup short-circuits to the DB
     * instead of paginating my/listings again (which is the source of intermittent failures).
     */
    private function persistReverbListingId(string $sku, string $listingId): void
    {
        $sku = trim($sku);
        $listingId = trim($listingId);
        if ($sku === '' || $listingId === '') {
            return;
        }
        try {
            if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'reverb_listing_id')) {
                return;
            }
            ReverbProduct::updateOrCreate(
                ['sku' => $sku],
                ['reverb_listing_id' => $listingId]
            );
        } catch (\Throwable $e) {
            Log::warning('Reverb persistReverbListingId failed', [
                'sku' => $sku,
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);
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

        $payload = [
            'price' => [
                'amount' => number_format($price, 2, '.', ''),
                'currency' => 'USD',
            ],
        ];

        try {
            // Same retry/refresh path as updateTitle: 401 refreshes token, 429/503 honour Retry-After.
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);

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

        $payload = ['title' => $title];

        try {
            // Route through retry helper so 401 refreshes the OAuth token and 429/503 honour Retry-After.
            // Without this, an intermittent stale-cache token or rate-limit blip surfaces as a one-off
            // "title push failed" — see updateBulletPoints/updateDescription which already use this path.
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);

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

        $current = $this->fetchCurrentReverbDescriptionFromApi($token, $listingId);
        if (($current['html'] ?? '') === '' && ($current['plain'] ?? '') === '') {
            $current = $this->fetchCurrentReverbDescription($token, $listingId, $trim);
        }

        $currentHtml = (string) ($current['html'] ?? '');
        if ($currentHtml === '' && ($current['plain'] ?? '') !== '') {
            $currentHtml = '<div class="product-description">'.nl2br(htmlspecialchars((string) $current['plain'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</div>';
        }

        $updatedDescription = $this->replaceReverbHighlightedFeaturesBlock($currentHtml, $features);
        $plainDescription = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $updatedDescription)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $payload = [
            'description' => $updatedDescription,
            'plain_text_description' => $plainDescription,
        ];

        Log::info('Reverb updateBulletPoints request', [
            'identifier' => $identifier,
            'listing_id' => $listingId,
            'feature_count' => count($features),
            'fields' => array_keys($payload),
        ]);

        try {
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);

            if ($response->successful()) {
                $localSaved = $this->saveFeaturesToReverbProducts($trim, $listingId, $features);

                Log::info('Reverb updateBulletPoints API response', [
                    'identifier' => $identifier,
                    'listing_id' => $listingId,
                    'status' => $response->status(),
                    'feature_count' => count($features),
                    'local_features_saved' => $localSaved,
                    'body_preview' => mb_substr($response->body(), 0, 800),
                ]);

                return [
                    'success' => true,
                    'message' => 'Reverb listing highlighted features updated.',
                    'listing_id' => $listingId,
                ];
            }

            Log::warning('Reverb updateBulletPoints API failed', [
                'identifier' => $identifier,
                'listing_id' => $listingId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Reverb API error (HTTP '.$response->status().'): '.$response->body(),
                'listing_id' => $listingId,
            ];
        } catch (\Throwable $e) {
            Log::error('Reverb updateBulletPoints exception', [
                'identifier' => $identifier,
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);

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
            if ($line !== '') {
                $features[] = $line;
            }
        }

        return $features;
    }

    /**
     * Replace only the visible Reverb highlighted-features block in listing description HTML.
     *
     * @param  list<string>  $features
     */
    private function replaceReverbHighlightedFeaturesBlock(string $currentDescriptionHtml, array $features): string
    {
        $replacement = $this->formatReverbHighlightedFeaturesBlock($features);
        if ($replacement === '') {
            return $currentDescriptionHtml;
        }

        $body = trim($currentDescriptionHtml);
        if ($body === '') {
            return $replacement;
        }

        $patterns = [
            '/<p\b[^>]*>\s*(?:<strong\b[^>]*>\s*)?(?:Highlighted\s+Features|About\s+Item):?\s*(?:<\/strong>)?\s*(?:<b>\s*<\/b>)?\s*<\/p>\s*(?:<p\b[^>]*>\s*<strong\b[^>]*>.*?<\/p>\s*){1,5}/is',
            '/<h[1-6]\b[^>]*>\s*(?:Highlighted\s+Features|About\s+Item):?\s*<\/h[1-6]>\s*(?:<p\b[^>]*>.*?<\/p>\s*){1,5}/is',
            '/<(?:ul|ol)\b[^>]*>\s*(?:<li\b[^>]*>.*?<\/li>\s*){1,5}<\/(?:ul|ol)>/is',
        ];

        foreach ($patterns as $pattern) {
            $updated = preg_replace($pattern, $replacement, $body, 1, $count);
            if ($count > 0 && is_string($updated)) {
                return trim($updated);
            }
        }

        return $replacement."\n".$body;
    }

    /**
     * @param  list<string>  $features
     */
    private function formatReverbHighlightedFeaturesBlock(array $features): string
    {
        $parts = ['<p><strong>Highlighted Features</strong></p>'];
        foreach (array_slice($features, 0, 5) as $feature) {
            $feature = trim((string) $feature);
            if ($feature === '') {
                continue;
            }

            $dashPos = mb_strpos($feature, ' - ');
            if ($dashPos !== false && $dashPos > 0 && $dashPos < mb_strlen($feature) - 3) {
                $label = trim(mb_substr($feature, 0, $dashPos));
                $rest = trim(mb_substr($feature, $dashPos + 3));
                $parts[] = '<p><strong>'.htmlspecialchars($label.' -', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong> '.htmlspecialchars($rest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'<br></p>';

                continue;
            }

            $parts[] = '<p>'.htmlspecialchars($feature, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'<br></p>';
        }

        return count($parts) > 1 ? implode("\n", $parts) : '';
    }

    /**
     * PUT /api/listings/{id} with 429/503 retry (same spirit as Shopify rate-limit retries).
     *
     * @param  array<string, mixed>  $payload
     */
    private function reverbPutListingWithRetry(string $token, string $listingId, array $payload, int $maxRetries = 4): Response
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $listingSegment = rawurlencode(trim((string) $listingId));
        $updateUrl = $apiBase.'/listings/'.$listingSegment;
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
                    $imgRes = $this->updateListingImages($trim, $photoUrls, 'replace');
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
     * Reverb downloads each listing photo from the URL server-side. Rewrites local {@see Storage}
     * URLs using {@see config('services.reverb.sku_image_public_base_url')} / {@see config('app.url')}
     * (same behaviour as {@see \App\Services\Marketplaces\ReverbService}) and rejects hosts
     * Reverb cannot reach (localhost / private LAN).
     *
     * @param  list<string>  $urls
     * @return array{success: bool, urls: list<string>, message: string}
     */
    private function prepareReverbPhotoUrls(array $urls): array
    {
        $urls = array_values(array_filter(array_map('trim', $urls), fn ($s) => $s !== ''));
        if ($urls === []) {
            return ['success' => false, 'urls' => [], 'message' => 'At least one image URL is required.'];
        }

        $normalized = [];
        foreach ($urls as $raw) {
            $rel = $this->extractPublicDiskRelativePathFromUrl($raw);
            if ($rel !== null && $rel !== '') {
                $normalized[] = $this->absoluteUrlForPublicStoragePath($rel);
            } else {
                $normalized[] = $raw;
            }
        }

        $normalized = array_values(array_unique($normalized));

        foreach ($normalized as $u) {
            $host = strtolower((string) (parse_url($u, PHP_URL_HOST) ?? ''));
            if ($host === '') {
                return ['success' => false, 'urls' => [], 'message' => 'Invalid image URL (missing hostname): '.mb_substr($u, 0, 120)];
            }
            if ($this->isHostUnreachableFromReverb($host)) {
                return [
                    'success' => false,
                    'urls' => [],
                    'message' => 'Reverb cannot fetch images from '.$host.'. Set REVERB_SKU_IMAGE_PUBLIC_BASE_URL (or APP_URL) to the public HTTPS origin where /storage/… is reachable from the internet, then run php artisan config:clear.',
                ];
            }
            if (str_starts_with($u, 'http://')) {
                Log::warning('Reverb image push: URL uses HTTP; Reverb may require HTTPS.', ['url' => mb_substr($u, 0, 200)]);
            }
        }

        return ['success' => true, 'urls' => $normalized, 'message' => ''];
    }

    private function isHostUnreachableFromReverb(string $host): bool
    {
        $host = strtolower($host);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if (str_ends_with($host, '.local')) {
            return true;
        }
        if (preg_match('/^192\.168\.\d+\.\d+$/', $host)) {
            return true;
        }
        if (preg_match('/^10\.\d+\.\d+\.\d+$/', $host)) {
            return true;
        }

        return (bool) preg_match('/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/', $host);
    }

    private function extractPublicDiskRelativePathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (preg_match('#/storage/(.+)$#', $path, $m)) {
            return rawurldecode($m[1]);
        }

        return null;
    }

    /**
     * Same URL rule as {@see \App\Services\Marketplaces\ReverbService::publicUrlForStoragePath}.
     */
    private function absoluteUrlForPublicStoragePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $base = rtrim((string) (config('services.reverb.sku_image_public_base_url') ?? ''), '/');
        if ($base !== '' && ! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.$base;
        }
        if ($base !== '') {
            $segments = array_values(array_filter(explode('/', $relativePath), fn ($s) => $s !== ''));

            return $base.'/storage/'.implode('/', array_map('rawurlencode', $segments));
        }

        return URL::to(Storage::disk('public')->url($relativePath));
    }

    /**
     * Walk HAL/JSON and collect numeric image ids from any {@see images} array on the listing.
     *
     * @return list<string>
     */
    private function collectReverbListingImageIds(array $data): array
    {
        $ids = [];
        $walk = function (mixed $node) use (&$walk, &$ids): void {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $key => $val) {
                if (($key === 'images' || $key === 'photos') && is_array($val)) {
                    foreach ($val as $img) {
                        if (is_array($img) && isset($img['id'])) {
                            $ids[] = (string) $img['id'];
                        }
                    }
                } elseif (is_array($val)) {
                    $walk($val);
                }
            }
        };
        $walk($data);

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return list<string>
     */
    private function fetchListingImageIdsForDeletion(string $token, string $listingId): array
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $seg = rawurlencode(trim((string) $listingId));

        foreach ([$apiBase.'/listings/'.$seg.'/images/', $apiBase.'/listings/'.$seg.'/images'] as $url) {
            try {
                $response = Http::withoutVerifying()
                    ->timeout(45)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/hal+json',
                        'Accept-Version' => '3.0',
                    ])->get($url);
                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json)) {
                        $ids = $this->collectReverbListingImageIds($json);
                        if ($ids !== []) {
                            return $ids;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        // Fallback: full listing resource often embeds photo metadata.
        try {
            $url = $apiBase.'/listings/'.$seg;
            $response = Http::withoutVerifying()
                ->timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])->get($url);
            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    return $this->collectReverbListingImageIds($json);
                }
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /**
     * DELETE /api/listings/{listing_id}/images/{image_id} with auth refresh + 429 retry.
     */
    private function reverbDeleteListingImageWithRetry(string $token, string $deleteUrl, int $maxRetries = 4): Response
    {
        $bearer = $token;
        $last = null;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $last = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$bearer,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->delete($deleteUrl);

            if ($last->successful() || $last->status() === 404) {
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
     * @return list<array{id: string, url: string}>
     */
    private function collectReverbListingImageRecords(array $data): array
    {
        $out = [];
        $seen = [];
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
                if (! is_array($img) || ! isset($img['id'])) {
                    continue;
                }
                $id = trim((string) $img['id']);
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $url = '';
                if (! empty($img['url']) && is_string($img['url'])) {
                    $url = (string) $img['url'];
                } elseif (isset($img['_links']['full']['href']) && is_string($img['_links']['full']['href'])) {
                    $url = (string) $img['_links']['full']['href'];
                } elseif (isset($img['_links']['thumbnail']['href']) && is_string($img['_links']['thumbnail']['href'])) {
                    $url = (string) $img['_links']['thumbnail']['href'];
                }
                $seen[$id] = true;
                $out[] = ['id' => $id, 'url' => $url];
            }
        }

        return $out;
    }

    /**
     * @return list<array{id: string, url: string}>
     */
    private function fetchListingImageRecords(string $token, string $listingId): array
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $seg = rawurlencode(trim((string) $listingId));

        foreach ([$apiBase.'/listings/'.$seg.'/images/', $apiBase.'/listings/'.$seg.'/images'] as $url) {
            try {
                $response = Http::withoutVerifying()
                    ->timeout(45)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/hal+json',
                        'Accept-Version' => '3.0',
                    ])->get($url);
                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json)) {
                        $records = $this->collectReverbListingImageRecords($json);
                        if ($records !== []) {
                            return $records;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])->get($apiBase.'/listings/'.$seg);
            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    return $this->collectReverbListingImageRecords($json);
                }
            }
        } catch (\Throwable) {
        }

        return [];
    }

    private function deleteReverbListingImageById(string $token, string $listingId, string $imageId): bool
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $listingSeg = rawurlencode(trim($listingId));
        $delUrl = $apiBase.'/listings/'.$listingSeg.'/images/'.rawurlencode(trim($imageId));
        $res = $this->reverbDeleteListingImageWithRetry($token, $delUrl);

        if ($res->successful() || $res->status() === 404) {
            return true;
        }

        if ($res->status() === 400 && str_contains($res->body(), 'Cannot delete the last photo')) {
            Log::info('Reverb: skip DELETE (last photo protected)', [
                'listing_id' => $listingId,
                'image_id' => $imageId,
            ]);

            return false;
        }

        Log::warning('Reverb DELETE listing image failed', [
            'listing_id' => $listingId,
            'image_id' => $imageId,
            'status' => $res->status(),
            'body' => mb_substr($res->body(), 0, 500),
        ]);

        return false;
    }

    /**
     * Count listing images whose ids were not present before a replace push.
     *
     * @param  list<array{id: string, url: string}>  $records
     * @param  array<string, int>  $oldIdSet
     */
    private function countReverbImagesNotInSet(array $records, array $oldIdSet): int
    {
        $n = 0;
        foreach ($records as $record) {
            $id = (string) ($record['id'] ?? '');
            if ($id !== '' && ! isset($oldIdSet[$id])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Poll until Reverb has ingested at least {@see $minCount} new images (by id).
     *
     * @param  array<string, int>  $oldIdSet
     */
    private function waitForReverbNewImageIngest(string $token, string $listingId, array $oldIdSet, int $minCount, int $maxSeconds = 120): int
    {
        $lastNew = 0;
        for ($wait = 0; $wait < $maxSeconds; $wait++) {
            $current = $this->fetchListingImageRecords($token, $listingId);
            $lastNew = $this->countReverbImagesNotInSet($current, $oldIdSet);
            if ($lastNew >= $minCount) {
                return $lastNew;
            }
            usleep(1_000_000);
        }

        return $lastNew;
    }

    /**
     * Poll until the listing has at least {@see $minCount} total images.
     */
    private function waitForReverbListingImageCount(string $token, string $listingId, int $minCount, int $maxSeconds = 60): int
    {
        $last = 0;
        for ($wait = 0; $wait < $maxSeconds; $wait++) {
            $current = $this->fetchListingImageRecords($token, $listingId);
            $last = count($current);
            if ($last >= $minCount) {
                return $last;
            }
            usleep(1_000_000);
        }

        return $last;
    }

    /**
     * @param  list<array{id: string, url: string}>  $records
     * @param  array<string, int>  $oldIdSet
     * @return list<string>
     */
    private function reverbImageIdsStillInOldSet(array $records, array $oldIdSet): array
    {
        $ids = [];
        foreach ($records as $record) {
            $id = (string) ($record['id'] ?? '');
            if ($id !== '' && isset($oldIdSet[$id])) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Remove every pre-push image id once at least one replacement photo exists.
     *
     * @param  array<string, int>  $oldIdSet
     */
    private function purgeReverbOldListingImages(string $token, string $listingId, array $oldIdSet): int
    {
        $deleted = 0;
        for ($pass = 0; $pass < 15; $pass++) {
            $current = $this->fetchListingImageRecords($token, $listingId);
            $newCount = $this->countReverbImagesNotInSet($current, $oldIdSet);
            if ($newCount < 1) {
                break;
            }

            $removedThisPass = false;
            foreach ($this->reverbImageIdsStillInOldSet($current, $oldIdSet) as $oldId) {
                $fresh = $this->fetchListingImageRecords($token, $listingId);
                if (count($fresh) <= 1) {
                    break 2;
                }
                if ($this->countReverbImagesNotInSet($fresh, $oldIdSet) < 1) {
                    break 2;
                }
                if ($this->deleteReverbListingImageById($token, $listingId, $oldId)) {
                    $deleted++;
                    $removedThisPass = true;
                    usleep(200000);
                }
            }
            if (! $removedThisPass) {
                break;
            }
        }

        return $deleted;
    }

    /**
     * @param  array<string, int>  $oldIdSet
     * @return array{total: int, new: int, old_ids: list<string>}
     */
    private function reverbListingGalleryCounts(string $token, string $listingId, array $oldIdSet): array
    {
        $records = $this->fetchListingImageRecords($token, $listingId);

        return [
            'total' => count($records),
            'new' => $this->countReverbImagesNotInSet($records, $oldIdSet),
            'old_ids' => $this->reverbImageIdsStillInOldSet($records, $oldIdSet),
        ];
    }

    /**
     * PUT the pushed URLs repeatedly until the gallery has exactly the target count of new photos.
     *
     * @param  array<string, int>  $oldIdSet
     * @param  list<string>  $urls
     */
    private function syncReverbListingGalleryToUrls(string $token, string $listingId, array $urls, array $oldIdSet, Response $fallback): Response
    {
        $targetCount = count($urls);
        $final = $fallback;
        $waitSecs = min(90, 20 + (int) ceil($targetCount / 2));

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $counts = $this->reverbListingGalleryCounts($token, $listingId, $oldIdSet);

            if ($counts['total'] >= $targetCount && $counts['old_ids'] === []) {
                $orderPut = $this->putReverbListingPhotos($token, $listingId, $urls);
                if ($orderPut->successful()) {
                    return $orderPut;
                }

                return $final->successful() ? $final : $orderPut;
            }

            if ($counts['old_ids'] !== [] && $counts['new'] >= $targetCount) {
                $this->purgeReverbOldListingImages($token, $listingId, $oldIdSet);
                continue;
            }

            $put = $this->putReverbListingPhotos($token, $listingId, $urls);
            if ($put->successful()) {
                $final = $put;
            }

            $this->waitForReverbListingImageCount($token, $listingId, $targetCount, $waitSecs);
            $this->waitForReverbNewImageIngest($token, $listingId, $oldIdSet, $targetCount, $waitSecs);
        }

        for ($topUp = 0; $topUp < 3; $topUp++) {
            $counts = $this->reverbListingGalleryCounts($token, $listingId, $oldIdSet);
            if ($counts['total'] >= $targetCount && $counts['old_ids'] === []) {
                break;
            }

            $put = $this->putReverbListingPhotos($token, $listingId, $urls);
            if ($put->successful()) {
                $final = $put;
            }
            $this->waitForReverbListingImageCount($token, $listingId, $targetCount, $waitSecs);
        }

        return $final;
    }

    /**
     * Full replace: gallery ends up as exactly the pushed public URLs (any count 1–25).
     * Reverb forbids deleting the last photo on a live listing, so we keep one old photo
     * as a temporary anchor, PUT replacements, purge every pre-push id, then re-sync.
     *
     * @param  list<string>  $urls
     */
    private function replaceReverbListingImages(string $token, string $listingId, array $urls): Response
    {
        $targetCount = count($urls);
        $records = $this->fetchListingImageRecords($token, $listingId);
        $oldIdSet = array_flip(array_column($records, 'id'));
        $deletedSecondary = 0;

        if ($records === []) {
            $response = $this->putReverbListingPhotos($token, $listingId, $urls);
            if (! $response->successful()) {
                return $response;
            }
            $this->waitForReverbListingImageCount($token, $listingId, $targetCount, min(240, 90 + ($targetCount * 8)));
            $remainingRecords = $this->fetchListingImageRecords($token, $listingId);
            Log::info('Reverb: replaced listing images (empty gallery)', [
                'listing_id' => $listingId,
                'target_count' => $targetCount,
                'remaining' => count($remainingRecords),
            ]);

            return $response;
        }

        if (count($records) > 1) {
            foreach (array_slice($records, 1) as $record) {
                if ($this->deleteReverbListingImageById($token, $listingId, $record['id'])) {
                    $deletedSecondary++;
                }
                usleep(120000);
            }
        }

        // Prime the anchor slot so the first pushed URL gets a new image id before the full gallery PUT.
        if ($targetCount > 1) {
            $prime = $this->putReverbListingPhotos($token, $listingId, [$urls[0]]);
            if ($prime->successful()) {
                $this->waitForReverbListingImageCount($token, $listingId, 1, 25);
                usleep(400000);
            }
        }

        $response = $this->putReverbListingPhotos($token, $listingId, $urls);
        if (! $response->successful()) {
            return $response;
        }

        $maxWait = min(240, 90 + ($targetCount * 8));
        $this->waitForReverbListingImageCount($token, $listingId, $targetCount, $maxWait);

        $ingestedNew = 0;
        for ($fill = 0; $fill < 4; $fill++) {
            $counts = $this->reverbListingGalleryCounts($token, $listingId, $oldIdSet);
            $ingestedNew = $counts['new'];
            if ($ingestedNew >= $targetCount) {
                break;
            }
            if ($counts['total'] < $targetCount) {
                $this->waitForReverbListingImageCount($token, $listingId, $targetCount, min(60, $maxWait));
                $counts = $this->reverbListingGalleryCounts($token, $listingId, $oldIdSet);
                $ingestedNew = $counts['new'];
                if ($ingestedNew >= $targetCount) {
                    break;
                }
            }

            $retry = $this->putReverbListingPhotos($token, $listingId, $urls);
            if ($retry->successful()) {
                $response = $retry;
            }
            $this->waitForReverbListingImageCount($token, $listingId, $targetCount, 45);
            $ingestedNew = max(
                $ingestedNew,
                $this->waitForReverbNewImageIngest($token, $listingId, $oldIdSet, $targetCount, 45)
            );
        }

        $deletedOld = 0;
        $counts = $this->reverbListingGalleryCounts($token, $listingId, $oldIdSet);
        if ($counts['new'] >= $targetCount) {
            $deletedOld = $this->purgeReverbOldListingImages($token, $listingId, $oldIdSet);
        } elseif ($ingestedNew < 1) {
            $ingestedNew = $this->waitForReverbNewImageIngest($token, $listingId, $oldIdSet, 1, 60);
            if ($ingestedNew >= 1) {
                $deletedOld = $this->purgeReverbOldListingImages($token, $listingId, $oldIdSet);
            }
        }

        $final = $this->syncReverbListingGalleryToUrls($token, $listingId, $urls, $oldIdSet, $response);

        $remainingRecords = $this->fetchListingImageRecords($token, $listingId);
        $oldIdsRemaining = $this->reverbImageIdsStillInOldSet($remainingRecords, $oldIdSet);
        Log::info('Reverb: replaced listing images', [
            'listing_id' => $listingId,
            'target_count' => $targetCount,
            'deleted_secondary' => $deletedSecondary,
            'deleted_old' => $deletedOld,
            'ingested_new' => $ingestedNew,
            'remaining' => count($remainingRecords),
            'old_ids_remaining' => count($oldIdsRemaining),
            'remaining_ids' => array_column($remainingRecords, 'id'),
        ]);

        if ($oldIdsRemaining !== []) {
            Log::warning('Reverb replace: pre-push photos still on listing after sync', [
                'listing_id' => $listingId,
                'target_count' => $targetCount,
                'old_ids_remaining' => $oldIdsRemaining,
            ]);
        }

        if (count($remainingRecords) < $targetCount) {
            Log::warning('Reverb replace: gallery short of target after sync', [
                'listing_id' => $listingId,
                'target_count' => $targetCount,
                'remaining' => count($remainingRecords),
            ]);
        }

        return $final->successful() ? $final : $response;
    }

    /**
     * Try payloads Reverb accepts when applying fresh photo URLs.
     *
     * @param  list<string>  $urls
     */
    private function putReverbListingPhotos(string $token, string $listingId, array $urls): Response
    {
        $variants = [
            ['photos' => $urls],
            ['photos' => $urls, 'photo_upload_method' => 'override_position'],
        ];
        $last = null;
        foreach ($variants as $payload) {
            $last = $this->reverbPutListingWithRetry($token, $listingId, $payload);
            if ($last->successful()) {
                return $last;
            }
            Log::warning('Reverb PUT listing photos attempt failed', [
                'listing_id' => $listingId,
                'status' => $last->status(),
                'body' => mb_substr($last->body(), 0, 800),
            ]);
        }

        return $last;
    }

    /**
     * Replace listing photos (public HTTPS URLs). Reverb may require URLs it can fetch.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, listing_id?: string, normalized_urls?: list<string>}
     */
    public function updateListingImages(string $identifier, array $imageUrls, string $mode = 'replace'): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).'];
        }

        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $prep = $this->prepareReverbPhotoUrls($urls);
        if (! $prep['success']) {
            return ['success' => false, 'message' => $prep['message']];
        }
        $urls = array_slice($prep['urls'], 0, 25);
        if ($urls === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.'];
        }

        $listingId = $this->resolveReverbListingId($trim);
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        $mode = strtolower(trim($mode)) === 'add' ? 'add' : 'replace';

        if ($mode === 'add') {
            $existing = $this->fetchListingImagePublicUrls($token, $listingId);
            $norm = static function (string $u): string {
                return rtrim(strtolower($u), '/');
            };
            $seen = [];
            $merged = [];
            foreach ($existing as $u) {
                if (! is_string($u) || $u === '') {
                    continue;
                }
                $k = $norm($u);
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $merged[] = $u;
            }
            foreach ($urls as $u) {
                $k = $norm($u);
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $merged[] = $u;
            }
            $urls = array_slice($merged, 0, 25);
        }

        try {
            $response = $mode === 'replace'
                ? $this->replaceReverbListingImages($token, (string) $listingId, $urls)
                : $this->putReverbListingPhotos($token, (string) $listingId, $urls);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Reverb listing images updated.',
                    'listing_id' => $listingId,
                    'normalized_urls' => $urls,
                ];
            }

            return [
                'success' => false,
                'message' => 'Reverb API error (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 2000),
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

        $prep = $this->prepareReverbPhotoUrls([$newPublicImageUrl]);
        if (! $prep['success']) {
            return ['success' => false, 'message' => $prep['message']];
        }
        $newPublicImageUrl = $prep['urls'][0] ?? '';
        if ($newPublicImageUrl === '') {
            return ['success' => false, 'message' => 'Image URL is empty.'];
        }

        $listingId = $this->getListingIdBySku(trim($sku));
        if ($listingId === null) {
            return [
                'success' => false,
                'message' => 'No Reverb listing found for SKU: '.trim($sku).'. Create or link the listing on Reverb first.',
            ];
        }

        $lockKey = 'reverb:append-listing-image:'.sha1((string) $listingId);

        $run = function () use ($listingId, $newPublicImageUrl) {
            return $this->appendImageUrlToListingBySkuBody($listingId, $newPublicImageUrl);
        };

        try {
            return Cache::lock($lockKey, 120)->block(90, $run);
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'message' => 'Timed out waiting to update this listing (other image pushes are still running). Retry in a few seconds.',
                'listing_id' => $listingId,
            ];
        } catch (\Throwable $e) {
            Log::warning('Reverb append image: cache lock unavailable; running without lock (possible race if parallel workers)', [
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);

            return $run();
        }
    }

    /**
     * @return array{success: bool, message: string, listing_id?: string}
     */
    private function appendImageUrlToListingBySkuBody(string $listingId, string $newPublicImageUrl): array
    {
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

        try {
            $response = $this->putReverbListingPhotos($token, (string) $listingId, $all);
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
     * Validate Reverb image push readiness without calling the listing photo API.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, dry_run?: bool, listing_id?: string, normalized_urls?: list<string>}
     */
    public function dryRunUpdateImages(string $identifier, array $imageUrls): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
                'dry_run' => true,
            ];
        }

        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $prep = $this->prepareReverbPhotoUrls($urls);
        if (! $prep['success']) {
            return ['success' => false, 'message' => $prep['message'], 'dry_run' => true];
        }
        $urls = array_slice($prep['urls'], 0, 25);
        if ($urls === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.', 'dry_run' => true];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.', 'dry_run' => true];
        }

        $listingId = $this->resolveReverbListingId($trim);
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.', 'dry_run' => true];
        }

        return [
            'success' => true,
            'dry_run' => true,
            'message' => 'Dry run OK: would push '.count($urls).' image(s) to Reverb listing '.$listingId.'.',
            'listing_id' => $listingId,
            'normalized_urls' => $urls,
        ];
    }

    /**
     * Image Master compatibility method: push images then persist image_urls in reverb_products.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string, listing_id?: string}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 25);
        if ($images === [] && strtolower(trim($mode)) !== 'replace') {
            return ['success' => true, 'message' => 'No images to add; skipped.'];
        }
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $res = $this->updateListingImages($identifier, $images, $mode);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $trim = trim($identifier);
        $listingId = (string) ($res['listing_id'] ?? '');
        $toSave = isset($res['normalized_urls']) && is_array($res['normalized_urls']) ? $res['normalized_urls'] : $images;
        $saved = $this->saveImageUrlsToReverbProducts($trim, $listingId, $toSave);
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

    /**
     * @return array{plain: string, html: string}
     */
    private function fetchCurrentReverbDescriptionFromApi(string $token, string $listingId): array
    {
        try {
            $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
            $listingSegment = rawurlencode(trim((string) $listingId));
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->get($apiBase.'/listings/'.$listingSegment);

            if (! $response->successful()) {
                Log::warning('Reverb API-first description fetch failed', [
                    'listing_id' => $listingId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 800),
                ]);

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

            return ['plain' => $plain, 'html' => $html];
        } catch (\Throwable $e) {
            Log::warning('Reverb API-first description fetch exception', [
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);

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
