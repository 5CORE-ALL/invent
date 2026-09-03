<?php

namespace App\Services;

use App\Models\ProductStockMapping;
use App\Models\ReverbListingStatus;
use App\Models\ReverbProduct;
use Carbon\Carbon;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\ShopifyBulletPointsFormatter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class ReverbApiService
{
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;
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

    public function isConfigured(): bool
    {
        $token = self::getReverbBearerToken();

        return is_string($token) && $token !== '';
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
     * Fetch ALL Reverb listings (including ended) and update ProductStockMapping + ReverbListingStatus
     * + upsert reverb_products (listing id / state / price / inventory) so /listing-reverb stays accurate.
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

                $response = null;
                $maxPageAttempts = 5;
                for ($attempt = 1; $attempt <= $maxPageAttempts; $attempt++) {
                    $response = Http::withoutVerifying()
                        ->timeout(60)
                        ->withHeaders([
                            'Authorization' => 'Bearer '.$token,
                            'Accept' => 'application/hal+json',
                            'Accept-Version' => '3.0',
                        ])->get($url);

                    if (! $response->failed()) {
                        break;
                    }

                    $status = $response->status();
                    $retriable = in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
                    $log->warning('Reverb getInventory page attempt failed', [
                        'page' => $pageNumber,
                        'attempt' => $attempt,
                        'status' => $status,
                        'retriable' => $retriable,
                    ]);
                    if (! $retriable || $attempt >= $maxPageAttempts) {
                        break;
                    }
                    usleep((int) (pow(2, $attempt) * 250000)); // 0.5s, 1s, 2s, 4s
                }

                if ($response === null || $response->failed()) {
                    $log->error('Reverb getInventory API error', [
                        'page' => $pageNumber,
                        'url' => $url,
                        'status' => $response?->status(),
                        'body' => $response?->body(),
                    ]);
                    Log::channel('reverb_daily')->error('Reverb getInventory API error', [
                        'page' => $pageNumber,
                        'status' => $response?->status(),
                        'body' => substr((string) $response?->body(), 0, 500),
                    ]);

                    // Keep partial progress instead of discarding earlier pages.
                    if ($inventory !== []) {
                        $log->warning('Reverb getInventory continuing with partial listing set', [
                            'listings_so_far' => count($inventory),
                            'failed_page' => $pageNumber,
                        ]);
                        break;
                    }

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
                        $price = $item['price']['amount'] ?? ($item['price'] ?? null);
                        if (is_array($price)) {
                            $price = $price['amount'] ?? null;
                        }
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
                                'price' => $price,
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
            $productsUpserted = 0;

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

                try {
                    if ($this->upsertReverbProductFromInventoryEntry($entry, $effectiveQty)) {
                        $productsUpserted++;
                    }
                } catch (\Throwable $e) {
                    $log->warning('Reverb getInventory reverb_products upsert failed', [
                        'sku' => $sku,
                        'listing_id' => $listingId,
                        'message' => $e->getMessage(),
                    ]);
                }

                $affected = ProductStockMapping::where('sku', $sku)->update(['inventory_reverb' => $effectiveQty]);
                if ($affected > 0) {
                    $log->debug('Reverb getInventory updated stock', ['sku' => $sku, 'inventory_reverb' => $effectiveQty, 'state' => $state]);
                }
            }

            $log->info('Reverb getInventory reverb_products upsert', [
                'rows_upserted' => $productsUpserted,
            ]);

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
     * Keep reverb_products in sync with listing-status inventory pulls (id/state/price/qty).
     * Preserves analytics fields (r_l30, bump_bid, etc.). Prefer live listings over ended duplicates.
     *
     * @param  array{sku?: mixed, listing_id?: mixed, state?: mixed, title?: mixed, price?: mixed, quantity?: mixed}  $entry
     */
    protected function upsertReverbProductFromInventoryEntry(array $entry, int $effectiveQty): bool
    {
        if (! Schema::hasTable('reverb_products')) {
            return false;
        }

        $sku = ReverbProduct::normalizeSkuForLookup((string) ($entry['sku'] ?? ''));
        if ($sku === '') {
            return false;
        }

        $listingId = trim((string) ($entry['listing_id'] ?? ''));
        if ($listingId === '') {
            return false;
        }

        $state = strtolower(trim((string) ($entry['state'] ?? 'live')));
        if ($state === '') {
            $state = 'live';
        }

        $existing = ReverbProduct::query()
            ->where(function ($q) use ($sku, $listingId) {
                $q->where('sku', $sku)->orWhere('reverb_listing_id', $listingId);
            })
            ->orderByRaw('CASE WHEN sku = ? THEN 0 ELSE 1 END', [$sku])
            ->first();

        if ($existing) {
            $existingId = trim((string) ($existing->reverb_listing_id ?? ''));
            $existingPriority = $this->listingStatePriorityForProductUpsert((string) ($existing->listing_state ?? ''));
            $newPriority = $this->listingStatePriorityForProductUpsert($state);
            if ($existingId !== '' && $existingId !== $listingId && $existingPriority > $newPriority) {
                return false;
            }

            $payload = [
                'sku' => $sku,
                'reverb_listing_id' => $listingId,
                'listing_state' => $state,
                'remaining_inventory' => $effectiveQty,
                'last_synced_at' => now(),
            ];
            if (! empty($entry['title'])) {
                $payload['product_title'] = $entry['title'];
            }
            if (isset($entry['price']) && $entry['price'] !== null && $entry['price'] !== '') {
                $payload['price'] = $entry['price'];
            }

            $existing->fill($payload);
            $existing->save();

            return true;
        }

        ReverbProduct::create([
            'sku' => $sku,
            'reverb_listing_id' => $listingId,
            'listing_state' => $state,
            'product_title' => $entry['title'] ?? null,
            'price' => $entry['price'] ?? null,
            'remaining_inventory' => $effectiveQty,
            'r_l30' => 0,
            'r_l60' => 0,
            'last_synced_at' => now(),
        ]);

        return true;
    }

    protected function listingStatePriorityForProductUpsert(?string $state): int
    {
        return match (strtolower((string) $state)) {
            'live', 'published' => 100,
            'sold' => 50,
            default => 10,
        };
    }

    /**
     * Collapse internal whitespace for Reverb SKU matching (e.g. "GSTOOL  BLK" == "GSTOOL BLK").
     */
    private static function normalizeReverbSkuKey(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        return preg_replace('/\s+/u', ' ', mb_strtolower($sku)) ?? mb_strtolower($sku);
    }

    private static function reverbListingSkuMatches(?string $listingSku, string $needleSku): bool
    {
        if ($listingSku === null || trim($listingSku) === '') {
            return false;
        }

        return self::normalizeReverbSkuKey($listingSku) === self::normalizeReverbSkuKey($needleSku);
    }

    /**
     * All Reverb listing IDs that share the same SKU (handles duplicate listings / spacing variants).
     *
     * @return list<string>
     */
    public function getAllListingIdsBySku(string $sku): array
    {
        $normalizedSku = trim($sku);
        if ($normalizedSku === '') {
            return [];
        }

        $ids = [];
        $product = ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->where('reverb_listing_id', '!=', '')
            ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($normalizedSku)])
            ->first();
        if ($product && $product->reverb_listing_id) {
            $ids[] = trim((string) $product->reverb_listing_id);
        }

        foreach ($this->fetchListingIdsFromReverbApiBySku($normalizedSku) as $id) {
            $ids[] = $id;
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => trim((string) $id) !== '')));
    }

    /**
     * @return list<string>
     */
    private function fetchListingIdsFromReverbApiBySku(string $sku): array
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [];
        }

        $ids = [];
        try {
            $filteredUrl = $apiBase.'/my/listings?'.http_build_query([
                'sku' => $sku,
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
                foreach ($filteredRes->json()['listings'] ?? [] as $item) {
                    $listingSku = isset($item['sku']) ? trim((string) $item['sku']) : '';
                    if (! self::reverbListingSkuMatches($listingSku, $sku)) {
                        continue;
                    }
                    $id = $item['id'] ?? null;
                    if ($id !== null && trim((string) $id) !== '') {
                        $ids[] = trim((string) $id);
                    }
                }
            }

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
                    break;
                }

                $data = $response->json();
                foreach ($data['listings'] ?? [] as $item) {
                    $listingSku = isset($item['sku']) ? trim((string) $item['sku']) : '';
                    if (! self::reverbListingSkuMatches($listingSku, $sku)) {
                        continue;
                    }
                    $id = $item['id'] ?? null;
                    if ($id !== null && trim((string) $id) !== '') {
                        $ids[] = trim((string) $id);
                    }
                }

                $url = isset($data['_links']['next']['href']) ? trim($data['_links']['next']['href']) : null;
            }
        } catch (\Throwable $e) {
            Log::warning('Reverb fetchListingIdsFromReverbApiBySku failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return array_values(array_unique($ids));
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

        $listingIds = $this->getAllListingIdsBySku($normalizedSku);
        if ($listingIds === []) {
            return null;
        }

        if (count($listingIds) === 1) {
            $this->persistReverbListingId($normalizedSku, $listingIds[0]);

            return $listingIds[0];
        }

        $primaryId = $listingIds[count($listingIds) - 1];
        $this->persistReverbListingId($normalizedSku, $primaryId);

        return $primaryId;
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
     * Apply Reverb's "Drop the Price By" sale % without changing the listing / Std price.
     * POST /api/my/listings/{id}/sales  { "percentage": N }
     * percent 0 removes the listing from seller sales.
     *
     * @return array{success: bool, message: string, listing_id?: string, percent?: int}
     */
    public function applyListingPriceDrop(string $sku, float $percent): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
            ];
        }

        $pct = (int) round(max(0, $percent));
        $listingId = $this->getListingIdBySku($sku);
        if ($listingId === null) {
            return [
                'success' => false,
                'message' => "No Reverb listing found for SKU: {$sku}.",
            ];
        }

        if ($pct <= 0) {
            $cleared = $this->clearListingPriceDrops($token, $listingId);
            if (! ($cleared['success'] ?? false)) {
                return array_merge($cleared, ['listing_id' => $listingId, 'percent' => 0]);
            }

            return [
                'success' => true,
                'message' => "Removed Drop the Price By sale for SKU: {$sku}.",
                'listing_id' => $listingId,
                'percent' => 0,
            ];
        }

        $existing = $this->listingSalePercent($token, $listingId);
        if ($existing !== null && (int) $existing === $pct) {
            return [
                'success' => true,
                'message' => "SKU {$sku} already has a {$pct}% Drop the Price By sale.",
                'listing_id' => $listingId,
                'percent' => $pct,
            ];
        }

        if ($existing !== null && (int) $existing !== $pct) {
            $this->clearListingPriceDrops($token, $listingId);
        }

        try {
            $response = $this->reverbApiRequestWithRetry($token, 'POST', '/listings/'.$listingId.'/sales', [
                'percentage' => $pct,
            ]);
            if ($response->successful()) {
                Log::info('Reverb Drop the Price By applied', [
                    'sku' => $sku,
                    'listing_id' => $listingId,
                    'percent' => $pct,
                ]);

                return [
                    'success' => true,
                    'message' => "Drop the Price By {$pct}% applied for SKU: {$sku}.",
                    'listing_id' => $listingId,
                    'percent' => $pct,
                ];
            }

            $body = mb_substr((string) $response->body(), 0, 2000);
            Log::error('Reverb Drop the Price By failed', [
                'sku' => $sku,
                'listing_id' => $listingId,
                'percent' => $pct,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'message' => "Reverb API error (HTTP {$response->status()}): ".$body,
                'listing_id' => $listingId,
                'percent' => $pct,
            ];
        } catch (\Throwable $e) {
            Log::error('Reverb applyListingPriceDrop exception: '.$e->getMessage(), [
                'sku' => $sku,
                'listing_id' => $listingId,
                'percent' => $pct,
            ]);

            return [
                'success' => false,
                'message' => 'Exception: '.$e->getMessage(),
                'listing_id' => $listingId,
                'percent' => $pct,
            ];
        }
    }

    /**
     * Set Reverb Bump bid % (S Bump% → live Bump%).
     * PUT /api/bump/v2/bids  { "products": [id], "bid": 0.05 }
     * percent 0 removes bump via DELETE /api/bump/v2/bids.
     *
     * @return array{success: bool, message: string, listing_id?: string, percent?: float, display?: string}
     */
    public function applyListingBumpBid(string $sku, float $percent): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
            ];
        }

        $listingId = $this->getListingIdBySku($sku);
        if ($listingId === null) {
            return [
                'success' => false,
                'message' => "No Reverb listing found for SKU: {$sku}.",
            ];
        }

        $pct = max(0, $percent);
        if ($pct > 0) {
            $pct = min(30, max(0.5, $pct));
            $pct = round($pct * 2) / 2;
        }

        $productId = is_numeric($listingId) ? (int) $listingId : $listingId;
        $current = $this->listingBumpPercent($token, $listingId);
        if ($current !== null && abs($current - $pct) < 0.05) {
            $display = $pct > 0 ? rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.').'%' : '0%';
            $this->persistBumpBid($sku, $display);

            return [
                'success' => true,
                'message' => "SKU {$sku} already has a {$display} Bump bid.",
                'listing_id' => (string) $listingId,
                'percent' => $pct,
                'display' => $display,
            ];
        }

        try {
            if ($pct <= 0) {
                $response = $this->reverbApiRequestWithRetry($token, 'DELETE', '/bump/v2/bids', [
                    'products' => [$productId],
                ]);
            } else {
                $response = $this->reverbApiRequestWithRetry($token, 'PUT', '/bump/v2/bids', [
                    'products' => [$productId],
                    'bid' => round($pct / 100, 4),
                ]);
            }

            if ($response->successful()) {
                $display = $pct > 0 ? rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.').'%' : '0%';
                $this->persistBumpBid($sku, $display);
                Log::info('Reverb Bump bid applied', [
                    'sku' => $sku,
                    'listing_id' => $listingId,
                    'percent' => $pct,
                ]);

                return [
                    'success' => true,
                    'message' => $pct > 0
                        ? "Bump {$display} applied for SKU: {$sku}."
                        : "Removed Bump for SKU: {$sku}.",
                    'listing_id' => (string) $listingId,
                    'percent' => $pct,
                    'display' => $display,
                ];
            }

            $body = mb_substr((string) $response->body(), 0, 2000);
            Log::error('Reverb Bump bid failed', [
                'sku' => $sku,
                'listing_id' => $listingId,
                'percent' => $pct,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'message' => "Reverb API error (HTTP {$response->status()}): ".$body,
                'listing_id' => (string) $listingId,
                'percent' => $pct,
            ];
        } catch (\Throwable $e) {
            Log::error('Reverb applyListingBumpBid exception: '.$e->getMessage(), [
                'sku' => $sku,
                'listing_id' => $listingId,
                'percent' => $pct,
            ]);

            return [
                'success' => false,
                'message' => 'Exception: '.$e->getMessage(),
                'listing_id' => (string) $listingId,
                'percent' => $pct,
            ];
        }
    }

    /**
     * Parse GET /listings/{id}/bump (or listing-embedded bump) into ads columns.
     *
     * @param  array<string, mixed>  $data
     * @return array{bump_bid: ?string, api_recommended_bid: ?string, views: int, total_interactions: int}
     */
    public static function parseListingBumpAds(array $data): array
    {
        if (isset($data['bump']) && is_array($data['bump'])) {
            $data = array_merge($data, $data['bump']);
        }

        $stats = is_array($data['bump_v2_stats'] ?? null) ? $data['bump_v2_stats'] : [];
        $recommendation = is_array($data['recommendation'] ?? null) ? $data['recommendation'] : [];

        $impressions = self::intFromMixed($stats['impressions'] ?? $data['impressions'] ?? 0);
        $interactions = self::intFromMixed(
            $data['total_interactions']
            ?? $data['interactions']
            ?? $stats['total_interactions']
            ?? $stats['interactions']
            ?? $impressions
        );

        return [
            'bump_bid' => self::formatBumpBidDisplay($data['current_bid'] ?? $stats['current_bid'] ?? null),
            'api_recommended_bid' => self::formatBumpBidDisplay(
                $data['recommended_bid']
                ?? $data['suggested_bid']
                ?? $data['recommended_bump']
                ?? $stats['recommended_bid']
                ?? $stats['suggested_bid']
                ?? $stats['recommended_bump']
                ?? ($recommendation['bid'] ?? $recommendation['recommended_bid'] ?? $recommendation['display'] ?? null)
                ?? self::recommendedBidFromBidsList($data['bids'] ?? $stats['bids'] ?? [])
            ),
            'views' => $impressions,
            'total_interactions' => $interactions,
        ];
    }

    /**
     * Query params so bump ads stats are requested for the last 30 days only.
     *
     * @return array{start_date: string, end_date: string}
     */
    public static function listingBumpL30Query(): array
    {
        $end = Carbon::now('America/Los_Angeles')->startOfDay();
        $start = $end->copy()->subDays(29);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /**
     * Lifetime interactions minus the snapshot from ~30 days ago (L30 only).
     *
     * @param  array<string, int>  $currentByNormSku
     * @return array<string, int>
     */
    public static function l30InteractionCounts(array $currentByNormSku): array
    {
        $baselines = self::l30InteractionBaselines();
        $out = [];
        foreach ($currentByNormSku as $sku => $current) {
            $lifetime = (int) $current;
            $out[$sku] = isset($baselines[$sku])
                ? max(0, $lifetime - $baselines[$sku])
                : $lifetime;
        }

        return $out;
    }

    /**
     * Latest views/interactions snapshot on or before 30 days ago, else the earliest snapshot.
     *
     * @return array<string, int>
     */
    public static function l30InteractionBaselines(): array
    {
        if (! Schema::hasTable('reverb_sku_daily_data')) {
            return [];
        }

        $baselineDate = Carbon::now('America/Los_Angeles')->subDays(30)->toDateString();
        $baselines = self::latestDailyInteractionSnapshotOnOrBefore($baselineDate);
        if ($baselines !== []) {
            return $baselines;
        }

        return self::earliestDailyInteractionSnapshot();
    }

    public static function l30InteractionsForSku(string $sku, int $lifetime, array $baselines): int
    {
        $key = ReverbProduct::normalizeSkuForLookup($sku);
        if ($key === '' || ! isset($baselines[$key])) {
            return max(0, $lifetime);
        }

        return max(0, $lifetime - $baselines[$key]);
    }

    /**
     * @return array<string, int>
     */
    private static function latestDailyInteractionSnapshotOnOrBefore(string $onOrBefore): array
    {
        $rows = DB::table('reverb_sku_daily_data')
            ->where('record_date', '<=', $onOrBefore)
            ->orderBy('record_date')
            ->get(['sku', 'record_date', 'daily_data']);

        $baselines = [];
        foreach ($rows as $row) {
            $key = ReverbProduct::normalizeSkuForLookup((string) ($row->sku ?? ''));
            $count = self::interactionCountFromDailyData($row->daily_data ?? null);
            if ($key === '' || $count === null) {
                continue;
            }
            $baselines[$key] = $count;
        }

        return $baselines;
    }

    /**
     * @return array<string, int>
     */
    private static function earliestDailyInteractionSnapshot(): array
    {
        $rows = DB::table('reverb_sku_daily_data')
            ->orderBy('record_date')
            ->get(['sku', 'record_date', 'daily_data']);

        $baselines = [];
        foreach ($rows as $row) {
            $key = ReverbProduct::normalizeSkuForLookup((string) ($row->sku ?? ''));
            if ($key === '' || isset($baselines[$key])) {
                continue;
            }
            $count = self::interactionCountFromDailyData($row->daily_data ?? null);
            if ($count === null) {
                continue;
            }
            $baselines[$key] = $count;
        }

        return $baselines;
    }

    private static function interactionCountFromDailyData(mixed $dailyData): ?int
    {
        $data = is_array($dailyData)
            ? $dailyData
            : (json_decode((string) $dailyData, true) ?: []);
        $raw = $data['views'] ?? $data['total_interactions'] ?? null;

        return is_numeric($raw) ? (int) $raw : null;
    }

    public static function formatBumpBidDisplay(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            if (isset($raw['display']) && is_string($raw['display']) && $raw['display'] !== '') {
                return self::normalizeBumpBidDisplay($raw['display']);
            }
            if (isset($raw['bid_percentage']) && is_numeric($raw['bid_percentage'])) {
                $n = (float) $raw['bid_percentage'];

                return self::percentNumberToDisplay($n > 0 && $n <= 1 ? $n * 100 : $n);
            }
            if (isset($raw['amount']) && is_numeric($raw['amount'])) {
                $n = (float) $raw['amount'];

                return self::percentNumberToDisplay($n > 0 && $n <= 1 ? $n * 100 : $n);
            }

            return null;
        }

        if (is_numeric($raw)) {
            $n = (float) $raw;

            return self::percentNumberToDisplay($n > 0 && $n <= 1 ? $n * 100 : $n);
        }

        if (is_string($raw)) {
            return self::normalizeBumpBidDisplay($raw);
        }

        return null;
    }

    private static function normalizeBumpBidDisplay(string $display): ?string
    {
        if (! preg_match('/(\d+(?:\.\d+)?)/', $display, $matches)) {
            return null;
        }

        return self::percentNumberToDisplay((float) $matches[1]);
    }

    private static function percentNumberToDisplay(float $n): string
    {
        $formatted = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return $formatted.'%';
    }

    /**
     * @param  mixed  $bids
     */
    private static function recommendedBidFromBidsList(mixed $bids): mixed
    {
        if (! is_array($bids)) {
            return null;
        }

        foreach ($bids as $bid) {
            if (! is_array($bid)) {
                continue;
            }
            if (! empty($bid['recommended']) || ! empty($bid['is_recommended'])
                || ! empty($bid['suggested']) || ! empty($bid['default'])) {
                return $bid;
            }
        }

        return null;
    }

    private static function intFromMixed(mixed $raw): int
    {
        return is_numeric($raw) && (int) $raw > 0 ? (int) $raw : 0;
    }

    private function listingBumpPercent(string $token, string $listingId): ?float
    {
        try {
            $response = $this->reverbApiRequestWithRetry($token, 'GET', '/listings/'.$listingId.'/bump');
            if (! $response->successful()) {
                return null;
            }
            $data = $response->json();
            $current = $data['current_bid'] ?? $data['bump_v2_stats']['current_bid'] ?? null;
            if (is_array($current)) {
                if (isset($current['bid_percentage']) && is_numeric($current['bid_percentage'])) {
                    return round(((float) $current['bid_percentage']) * 100, 2);
                }
                $display = (string) ($current['display'] ?? '');
                if (preg_match('/(\d+(?:\.\d+)?)/', $display, $m)) {
                    return (float) $m[1];
                }
            }
            if (is_numeric($current)) {
                $n = (float) $current;

                return $n > 0 && $n <= 1 ? round($n * 100, 2) : $n;
            }
            if (is_string($current) && preg_match('/(\d+(?:\.\d+)?)/', $current, $m)) {
                return (float) $m[1];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function persistBumpBid(string $sku, string $display): void
    {
        try {
            if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'bump_bid')) {
                return;
            }
            ReverbProduct::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                ->update([
                    'bump_bid' => $display,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Reverb persistBumpBid failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Current seller-sale discount % on a listing, or null if none.
     */
    private function listingSalePercent(string $token, string $listingId): ?int
    {
        try {
            $response = $this->reverbApiRequestWithRetry($token, 'GET', '/listings/'.$listingId.'/sales');
            if (! $response->successful()) {
                return null;
            }
            $sales = $response->json('sales');
            if (! is_array($sales)) {
                return null;
            }
            foreach ($sales as $sale) {
                if (! is_array($sale)) {
                    continue;
                }
                $pct = $sale['discount_percent'] ?? $sale['percentage'] ?? null;
                if (is_numeric($pct) && (int) $pct > 0) {
                    return (int) $pct;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function clearListingPriceDrops(string $token, string $listingId): array
    {
        $removed = false;
        try {
            $response = $this->reverbApiRequestWithRetry($token, 'GET', '/listings/'.$listingId.'/sales');
            $sales = $response->successful() ? $response->json('sales') : [];
            if (is_array($sales)) {
                foreach ($sales as $sale) {
                    if (! is_array($sale)) {
                        continue;
                    }
                    $saleId = $sale['id'] ?? $sale['slug'] ?? null;
                    if ($saleId === null || $saleId === '') {
                        continue;
                    }
                    $del = $this->reverbApiRequestWithRetry($token, 'DELETE', '/sales/'.$saleId.'/listings', [
                        'listing_ids' => [(string) $listingId],
                    ]);
                    if ($del->successful()) {
                        $removed = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Reverb clearListingPriceDrops listing-sales lookup failed', [
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $direct = $this->reverbApiRequestWithRetry($token, 'DELETE', '/my/listings/'.$listingId.'/sales');
            if ($direct->successful()) {
                $removed = true;
            }
        } catch (\Throwable) {
            // optional fallback
        }

        return [
            'success' => true,
            'message' => $removed ? 'Sale removed.' : 'No Drop the Price By sale to remove.',
        ];
    }

    /**
     * Authenticated Reverb API call with 401 refresh + 429/503 retry.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function reverbApiRequestWithRetry(string $token, string $method, string $path, ?array $payload = null, int $maxRetries = 4): Response
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $url = $apiBase.'/'.ltrim($path, '/');
        $bearer = $token;
        $last = null;
        $method = strtoupper($method);
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $req = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$bearer,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ]);
            $last = match ($method) {
                'GET' => $req->get($url),
                'DELETE' => $payload ? $req->withBody(json_encode($payload), 'application/hal+json')->delete($url) : $req->delete($url),
                'POST' => $req->post($url, $payload ?? []),
                'PUT' => $req->put($url, $payload ?? []),
                default => $req->send($method, $url, ['json' => $payload ?? []]),
            };

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
     * Full listing update for the View Listing modal (PUT /api/listings/{id}).
     *
     * @param  array<string, mixed>  $fields  Editor-shaped fields from the modal
     * @return array{success: bool, message: string, listing_id?: string, body?: string}
     */
    public function updateListing(string $identifier, array $fields): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
            ];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.'];
        }

        $listingId = $this->resolveReverbListingId($trim);
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        $payload = [];

        foreach (['title', 'make', 'model', 'finish', 'year', 'sku', 'upc'] as $key) {
            if (array_key_exists($key, $fields) && $fields[$key] !== null) {
                $payload[$key] = is_string($fields[$key]) ? trim((string) $fields[$key]) : $fields[$key];
            }
        }

        if (array_key_exists('upc_does_not_apply', $fields)) {
            $payload['upc_does_not_apply'] = filter_var($fields['upc_does_not_apply'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if (array_key_exists('description', $fields) && is_string($fields['description'])) {
            $desc = trim($fields['description']);
            if ($desc !== '') {
                $payload['description'] = $desc;
                $payload['plain_text_description'] = trim(html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        if (isset($fields['price_amount']) && is_numeric($fields['price_amount'])) {
            $currency = trim((string) ($fields['price_currency'] ?? $fields['currency'] ?? 'USD')) ?: 'USD';
            $payload['price'] = [
                'amount' => number_format((float) $fields['price_amount'], 2, '.', ''),
                'currency' => $currency,
            ];
        }

        if (array_key_exists('inventory', $fields) && is_numeric($fields['inventory'])) {
            $payload['inventory'] = (int) $fields['inventory'];
            $payload['has_inventory'] = true;
        } elseif (array_key_exists('has_inventory', $fields)) {
            $payload['has_inventory'] = (bool) $fields['has_inventory'];
        }

        foreach (['offers_enabled', 'handmade', 'local_pickup_only'] as $boolKey) {
            if (array_key_exists($boolKey, $fields)) {
                $payload[$boolKey] = (bool) $fields[$boolKey];
            }
        }

        $conditionUuid = trim((string) ($fields['condition_uuid'] ?? ''));
        if ($conditionUuid !== '') {
            $payload['condition'] = ['uuid' => $conditionUuid];
        }

        $categoryUuid = trim((string) ($fields['category_uuid'] ?? ''));
        if ($categoryUuid !== '') {
            $payload['categories'] = [['uuid' => $categoryUuid]];
        }

        $photos = [];
        if (isset($fields['photos']) && is_array($fields['photos'])) {
            foreach ($fields['photos'] as $photo) {
                if (is_string($photo) && trim($photo) !== '') {
                    $photos[] = trim($photo);
                } elseif (is_array($photo)) {
                    $u = trim((string) ($photo['url'] ?? $photo['href'] ?? ''));
                    if ($u !== '') {
                        $photos[] = $u;
                    }
                }
            }
        }
        if ($photos !== []) {
            $prep = $this->prepareReverbPhotoUrls($photos);
            if (! ($prep['success'] ?? false)) {
                return ['success' => false, 'message' => $prep['message'] ?? 'Invalid photo URLs.', 'listing_id' => $listingId];
            }
            $payload['photos'] = array_slice($prep['urls'], 0, 25);
            $payload['photo_upload_method'] = 'override_position';
        }

        $videos = [];
        if (isset($fields['videos']) && is_array($fields['videos'])) {
            foreach ($fields['videos'] as $video) {
                if (is_string($video) && trim($video) !== '') {
                    $videos[] = trim($video);
                } elseif (is_array($video)) {
                    $u = trim((string) ($video['link'] ?? $video['url'] ?? $video['href'] ?? ''));
                    if ($u !== '') {
                        $videos[] = $u;
                    }
                }
            }
        }
        $videos = array_slice(array_values(array_unique($videos)), 0, 3);
        if ($videos !== []) {
            $payload['videos'] = array_map(static fn (string $link): array => ['link' => $link], $videos);
        }

        if (isset($fields['shipping_profile_id']) && trim((string) $fields['shipping_profile_id']) !== '') {
            $payload['shipping_profile_id'] = trim((string) $fields['shipping_profile_id']);
        } elseif (isset($fields['shipping']) && is_array($fields['shipping'])) {
            $payload['shipping'] = $fields['shipping'];
        }

        if ($payload === []) {
            return ['success' => false, 'message' => 'No listing fields to update.', 'listing_id' => $listingId];
        }

        try {
            $response = $this->reverbPutListingWithRetry($token, $listingId, $payload);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Listing updated on Reverb.',
                    'listing_id' => $listingId,
                ];
            }

            return [
                'success' => false,
                'message' => 'Reverb API error (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 2000),
                'listing_id' => $listingId,
                'body' => mb_substr($response->body(), 0, 2000),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'listing_id' => $listingId,
            ];
        }
    }

    /**
     * Create a new Reverb listing (POST /api/listings).
     *
     * @param  array<string, mixed>  $fields  Same editor-shaped fields as {@see updateListing()}
     * @return array{success: bool, message: string, listing_id?: string, web_url?: string, body?: string}
     */
    public function createListing(array $fields): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).',
            ];
        }

        $title = trim((string) ($fields['title'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'message' => 'Title is required to create a Reverb listing.'];
        }

        $payload = [
            'title' => $title,
            'publish' => array_key_exists('publish', $fields)
                ? filter_var($fields['publish'], FILTER_VALIDATE_BOOLEAN)
                : true,
        ];

        foreach (['make', 'model', 'finish', 'year', 'sku'] as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                $payload[$key] = $value;
            }
        }

        $skipUpc = filter_var($fields['upc_does_not_apply'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || trim((string) ($fields['upc'] ?? '')) === '';
        if ($skipUpc) {
            $payload['upc_does_not_apply'] = true;
        } else {
            $payload['upc'] = trim((string) $fields['upc']);
        }

        $desc = trim((string) ($fields['description'] ?? ''));
        if ($desc !== '') {
            $payload['description'] = $desc;
            $payload['plain_text_description'] = trim(html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (isset($fields['price_amount']) && is_numeric($fields['price_amount'])) {
            $currency = trim((string) ($fields['price_currency'] ?? $fields['currency'] ?? 'USD')) ?: 'USD';
            $payload['price'] = [
                'amount' => number_format((float) $fields['price_amount'], 2, '.', ''),
                'currency' => $currency,
            ];
        }

        if (array_key_exists('inventory', $fields) && is_numeric($fields['inventory'])) {
            $payload['inventory'] = max(0, (int) $fields['inventory']);
            $payload['has_inventory'] = true;
        } else {
            $payload['has_inventory'] = filter_var($fields['has_inventory'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        $payload['offers_enabled'] = array_key_exists('offers_enabled', $fields)
            ? filter_var($fields['offers_enabled'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $conditionUuid = trim((string) ($fields['condition_uuid'] ?? ''));
        if ($conditionUuid !== '') {
            $payload['condition'] = ['uuid' => $conditionUuid];
        }

        $categoryUuid = trim((string) ($fields['category_uuid'] ?? ''));
        if ($categoryUuid !== '') {
            $payload['categories'] = [['uuid' => $categoryUuid]];
        }

        $photos = [];
        if (isset($fields['photos']) && is_array($fields['photos'])) {
            foreach ($fields['photos'] as $photo) {
                if (is_string($photo) && trim($photo) !== '') {
                    $photos[] = trim($photo);
                } elseif (is_array($photo)) {
                    $u = trim((string) ($photo['url'] ?? $photo['href'] ?? ''));
                    if ($u !== '') {
                        $photos[] = $u;
                    }
                }
            }
        }
        if ($photos !== []) {
            $prep = $this->prepareReverbPhotoUrls($photos);
            if (! ($prep['success'] ?? false)) {
                return ['success' => false, 'message' => $prep['message'] ?? 'Invalid photo URLs.'];
            }
            $payload['photos'] = array_slice($prep['urls'], 0, 25);
        }

        $shippingProfileId = trim((string) ($fields['shipping_profile_id'] ?? ''));
        if ($shippingProfileId !== '') {
            $payload['shipping_profile_id'] = is_numeric($shippingProfileId)
                ? (int) $shippingProfileId
                : $shippingProfileId;
        } elseif (isset($fields['shipping']) && is_array($fields['shipping'])) {
            $payload['shipping'] = $fields['shipping'];
        } else {
            $payload['shipping'] = $this->defaultCreateShippingRates();
        }

        try {
            $response = $this->reverbApiRequestWithRetry($token, 'POST', '/listings', $payload);
            if (! $response->successful() && isset($payload['photos']) && is_array($payload['photos'])) {
                $linked = $this->photosAsReverbLinks($payload['photos']);
                if ($linked !== $payload['photos']) {
                    $retry = $payload;
                    $retry['photos'] = $linked;
                    $linkedResponse = $this->reverbApiRequestWithRetry($token, 'POST', '/listings', $retry);
                    if ($linkedResponse->successful()) {
                        $response = $linkedResponse;
                    }
                }
            }

            if ($response->successful()) {
                $json = $response->json();
                $json = is_array($json) ? $json : [];
                $listingId = $this->extractCreatedListingId($json);
                $web = trim((string) (
                    $json['_links']['web']['href']
                    ?? $json['listing']['_links']['web']['href']
                    ?? $json['_links']['self']['href']
                    ?? ''
                ));

                return [
                    'success' => true,
                    'message' => 'Listing created on Reverb.',
                    'listing_id' => $listingId !== '' ? $listingId : null,
                    'web_url' => $web !== '' ? $web : null,
                ];
            }

            return [
                'success' => false,
                'message' => 'Reverb API error (HTTP '.$response->status().'): '.$this->formatReverbErrorBody($response),
                'body' => mb_substr($response->body(), 0, 2000),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getListing(string $listingId): array
    {
        $listingId = trim($listingId);
        if ($listingId === '') {
            return [];
        }
        $json = $this->reverbApiGet('/listings/'.$listingId);
        if (isset($json['listing']) && is_array($json['listing'])) {
            return $json['listing'];
        }

        return is_array($json) ? $json : [];
    }

    public function firstShippingProfileId(): string
    {
        $configured = trim((string) config('services.reverb.shipping_profile_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $cached = Cache::get('reverb_shipping_profile_id_v1');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        foreach (['shop/shipping_profiles', 'shipping/profiles', 'my/shipping_profiles'] as $path) {
            $json = $this->reverbApiGet($path);
            $rows = $json['shipping_profiles'] ?? $json['_embedded']['shipping_profiles'] ?? $json['profiles'] ?? [];
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? $row['uuid'] ?? ''));
                if ($id !== '') {
                    Cache::put('reverb_shipping_profile_id_v1', $id, now()->addHours(12));

                    return $id;
                }
            }
        }

        return '';
    }

    /**
     * Push bullet lines into the listing description `Highlighted Features` block (PUT listing description).
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

        $listingIds = [];
        if (ctype_digit($trim)) {
            $listingIds = [$trim];
        } else {
            $listingIds = $this->getAllListingIdsBySku($trim);
        }

        if ($listingIds === []) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        $results = [];
        foreach ($listingIds as $listingId) {
            $results[] = $this->pushBulletPointsToListingId($token, $listingId, $trim, $features);
        }

        $successes = array_values(array_filter($results, fn (array $result) => $result['success']));
        $failures = array_values(array_filter($results, fn (array $result) => ! $result['success']));

        if ($successes === []) {
            return [
                'success' => false,
                'message' => 'Reverb bullet update failed for all matching listings. '.($failures[0]['message'] ?? ''),
                'listing_id' => $listingIds[0] ?? null,
            ];
        }

        $updatedIds = array_map(fn (array $result) => $result['listing_id'], $successes);
        $this->saveFeaturesToReverbProducts($trim, $updatedIds[0], $features);
        if (count($updatedIds) > 1) {
            $this->persistReverbListingId($trim, $updatedIds[0]);
        }

        $message = 'Reverb listing highlighted features updated.';
        if (count($listingIds) > 1) {
            $message .= ' Updated '.count($successes).' of '.count($listingIds).' Reverb listings for this SKU (IDs: '.implode(', ', $updatedIds).').';
        }
        if ($failures !== []) {
            $message .= ' Warning: '.count($failures).' listing(s) failed.';
        }

        return [
            'success' => true,
            'message' => $message,
            'listing_id' => $updatedIds[0],
        ];
    }

    /**
     * @param  list<string>  $features
     * @return array{success: bool, message: string, listing_id: string, verified?: bool}
     */
    private function pushBulletPointsToListingId(string $token, string $listingId, string $identifier, array $features): array
    {
        $listingId = trim($listingId);
        $current = $this->fetchCurrentReverbDescriptionFromApi($token, $listingId);
        if (($current['html'] ?? '') === '' && ($current['plain'] ?? '') === '') {
            $current = $this->fetchCurrentReverbDescription($token, $listingId, $identifier);
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
                $responseJson = $response->json();
                $verify = $this->verifyReverbBulletsOnListing(is_array($responseJson) ? $responseJson : null, $features);

                if ($verify['ok']) {
                    $readBack = $this->fetchCurrentReverbDescriptionFromApi($token, $listingId);
                    $verify = $this->verifyReverbBulletsOnListing(
                        ['listing' => ['description' => (string) ($readBack['html'] ?? '')]],
                        $features
                    );
                }

                Log::info('Reverb updateBulletPoints API response', [
                    'identifier' => $identifier,
                    'listing_id' => $listingId,
                    'status' => $response->status(),
                    'feature_count' => count($features),
                    'verified' => $verify['ok'],
                    'body_preview' => mb_substr($response->body(), 0, 800),
                ]);

                if (! $verify['ok']) {
                    return [
                        'success' => false,
                        'message' => 'Reverb API accepted the update but bullets were not verified on listing '.$listingId.'. '.($verify['message'] ?? ''),
                        'listing_id' => $listingId,
                        'verified' => false,
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Reverb listing highlighted features updated.',
                    'listing_id' => $listingId,
                    'verified' => true,
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
                'message' => 'Reverb API error (HTTP '.$response->status().') for listing '.$listingId.': '.$response->body(),
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
     * Replace the visible Reverb highlighted-features block in listing description HTML.
     * Uses the same legacy bullet stripping rules as Shopify ({@see ShopifyBulletPointsFormatter::stripLegacyBulletBlocksForMarketplace()}).
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

        $body = ShopifyBulletPointsFormatter::stripLegacyBulletBlocksForMarketplace($body);
        if ($body === '') {
            return $replacement;
        }

        return $replacement."\n".$body;
    }

    /**
     * @param  list<string>  $features
     * @return array{ok: bool, message?: string}
     */
    private function verifyReverbBulletsOnListing(?array $responseJson, array $features): array
    {
        if ($features === []) {
            return ['ok' => true];
        }

        $html = trim((string) ($responseJson['listing']['description'] ?? ''));
        if ($html === '') {
            return ['ok' => false, 'message' => 'Reverb response did not include listing description.'];
        }

        $topHtml = mb_substr($html, 0, 1200);
        if (preg_match('/\A\s*<p\b[^>]*>[\s\S]*?【/u', $topHtml) === 1) {
            return ['ok' => false, 'message' => 'Legacy 【】 bracket bullets are still at the top of the listing.'];
        }

        if (! str_contains($topHtml, 'Highlighted Features')) {
            return ['ok' => false, 'message' => 'Highlighted Features block is missing from the top of the listing.'];
        }

        $first = trim((string) $features[0]);
        $needle = $first;
        if (preg_match('/^(.+?)\s+-\s+/u', $first, $matches) === 1) {
            $needle = trim($matches[1]);
        }
        if ($needle !== '' && ! str_contains(mb_strtolower(strip_tags($topHtml)), mb_strtolower($needle))) {
            return ['ok' => false, 'message' => 'First PM bullet label was not found near the top of the listing.'];
        }

        return ['ok' => true];
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
        $incomingPlain = DescriptionWithImagesFormatter::plainTextFromDescription($description);
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
     * and rejects hosts Reverb cannot reach (localhost / private LAN).
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

        // Guard: Reverb's image fetcher rejects URLs with unencoded characters (e.g. spaces in a
        // self-hosted "FEATURE IMG_x.jpg" path), which silently drops that photo. Percent-encode
        // each path segment so Reverb always receives a fetchable URL.
        $normalized = array_map(fn ($u) => $this->encodeReverbImageUrlPath($u), $normalized);
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

    /**
     * Percent-encode each path segment of a URL so it is safe for Reverb's image fetcher, without
     * double-encoding already-encoded segments (decode then re-encode). Scheme, host, port and
     * query are preserved. Clean URLs (e.g. Shopify CDN) are returned unchanged.
     */
    private function encodeReverbImageUrlPath(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }
        $path = $parts['path'] ?? '';
        if ($path !== '') {
            $path = implode('/', array_map(
                fn ($seg) => rawurlencode(rawurldecode($seg)),
                explode('/', $path)
            ));
        }

        return ($parts['scheme'] ?? 'https').'://'
            .$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
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
     * Build a public absolute URL for a path on the public disk.
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
     * GET a Reverb URL, backing off on 429/503 (honoring Retry-After) so the rebuild's frequent
     * polling does not worsen a rate-limit. Returns null on connection failure.
     */
    private function reverbGetWithRateLimit(string $token, string $url, int $maxRetries = 3): ?Response
    {
        $response = null;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $response = Http::withoutVerifying()
                    ->timeout(45)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/hal+json',
                        'Accept-Version' => '3.0',
                    ])->get($url);
            } catch (\Throwable) {
                return null;
            }
            if (! in_array($response->status(), [429, 503], true)) {
                return $response;
            }
            if ($attempt < $maxRetries - 1) {
                $waitMs = 700000 * ($attempt + 1);
                if ($response->status() === 429 && is_numeric($response->header('Retry-After'))) {
                    $waitMs = min(5_000_000, (int) ((float) $response->header('Retry-After') * 1_000_000));
                }
                usleep($waitMs);
            }
        }

        return $response;
    }

    /**
     * @return list<array{id: string, url: string}>
     */
    private function fetchListingImageRecords(string $token, string $listingId): array
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $seg = rawurlencode(trim((string) $listingId));

        foreach ([$apiBase.'/listings/'.$seg.'/images/', $apiBase.'/listings/'.$seg.'/images'] as $url) {
            $response = $this->reverbGetWithRateLimit($token, $url);
            if ($response && $response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    $records = $this->collectReverbListingImageRecords($json);
                    if ($records !== []) {
                        return $records;
                    }
                }
            }
        }

        $response = $this->reverbGetWithRateLimit($token, $apiBase.'/listings/'.$seg);
        if ($response && $response->successful()) {
            $json = $response->json();
            if (is_array($json)) {
                return $this->collectReverbListingImageRecords($json);
            }
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
     * Count photos in the listing's DISPLAY gallery (`listing.photos[]`) — the source of truth for
     * what is shown and which photo is main. The `/images/` endpoint is id-sorted and can report a
     * different count than the live gallery, so it must not be used to verify a replace.
     */
    private function reverbDisplayPhotoCount(string $token, string $listingId): int
    {
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $seg = rawurlencode(trim($listingId));
        $response = $this->reverbGetWithRateLimit($token, $apiBase.'/listings/'.$seg);
        if ($response && $response->successful()) {
            $json = $response->json();
            $photos = $json['photos'] ?? ($json['_embedded']['photos'] ?? null);
            if (is_array($photos)) {
                return count($photos);
            }
        }

        return 0;
    }

    /**
     * Poll until the display gallery holds at least {@see $minCount} photos.
     */
    private function waitForReverbDisplayCount(string $token, string $listingId, int $minCount, int $maxSeconds): int
    {
        $deadline = microtime(true) + $maxSeconds;
        do {
            $last = $this->reverbDisplayPhotoCount($token, $listingId);
            if ($last >= $minCount) {
                return $last;
            }
            usleep(2_500_000);
        } while (microtime(true) < $deadline);

        return $last;
    }

    /**
     * Poll until at least one listing image id is NOT in $oldIdSet (i.e. a freshly uploaded photo).
     *
     * @param  array<string, int>  $oldIdSet
     */
    private function waitForReverbNewPhoto(string $token, string $listingId, array $oldIdSet, int $maxSeconds): bool
    {
        $deadline = microtime(true) + $maxSeconds;
        do {
            foreach ($this->fetchListingImageRecords($token, $listingId) as $record) {
                if (! isset($oldIdSet[(string) ($record['id'] ?? '')])) {
                    return true;
                }
            }
            usleep(2_500_000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * True when the requested URL list (main first, then the exact gallery order) is identical to
     * what we last pushed to this listing (stored in reverb_products.image_urls). If so the live
     * gallery is already correct — same main and same order — and the slow teardown rebuild can be
     * skipped. Any change to the main OR the order falls through to a rebuild.
     *
     * @param  list<string>  $urls  main image first, in desired gallery order
     */
    private function reverbGalleryMatchesLastPush(string $listingId, array $urls): bool
    {
        if ($urls === []) {
            return false;
        }
        try {
            $raw = ReverbProduct::query()->where('reverb_listing_id', $listingId)->value('image_urls');
        } catch (\Throwable) {
            return false;
        }
        $prev = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
        if (! is_array($prev)) {
            return false;
        }

        return array_map('strval', array_values($prev)) === array_map('strval', array_values($urls));
    }

    /**
     * Full replace: the gallery becomes the pushed URLs, with urls[0] as the listing's MAIN photo.
     *
     * Unlike Shopify (which accepts an explicit per-photo `position`), Reverb has no position field
     * and ignores the photos-array order for an existing gallery — the main photo is simply the
     * OLDEST-uploaded one. `PUT photos` is also add-only and dedupes by source URL. So to place the
     * chosen main we control UPLOAD ORDER: clear the old photos, upload the main first (making it
     * the oldest), then upload the rest. Counts are verified against the display gallery
     * (`listing.photos[]`), since the `/images/` endpoint is id-sorted and can disagree.
     *
     * @param  list<string>  $urls  caller passes the chosen main image first
     *
     * @throws \RuntimeException when Reverb does not ingest the full set within the wait budget
     */
    private function replaceReverbListingImages(string $token, string $listingId, array $urls): Response
    {
        $targetCount = count($urls);
        $main = $urls[0];
        $records = $this->fetchListingImageRecords($token, $listingId);
        $maxWait = min(150, 30 + ($targetCount * 6));
        // Wait the full budget for the main to ingest before deciding it deduped onto the anchor:
        // if the chosen main is slow and we give up early, the wrong (anchor) photo stays as main.
        $primeWait = $maxWait;

        // Empty gallery: upload the main first (so it is the oldest = main), then the rest.
        if ($records === []) {
            $prime = $this->putReverbListingPhotos($token, $listingId, [$main]);
            if (! $prime->successful()) {
                return $prime;
            }
            $this->waitForReverbDisplayCount($token, $listingId, 1, $primeWait);
            $response = $this->putReverbListingPhotos($token, $listingId, $urls);
            if (! $response->successful()) {
                return $response;
            }
            $shown = $this->waitForReverbDisplayCount($token, $listingId, $targetCount, $maxWait);
            Log::info('Reverb: replaced listing images (empty gallery)', [
                'listing_id' => $listingId,
                'target_count' => $targetCount,
                'display_count' => $shown,
            ]);

            return $response;
        }

        // Fast path: if the requested main + set match what we last pushed, the gallery should
        // already be correct (the main is the oldest photo), so skip the slow teardown rebuild.
        // Only commit to the skip if the display count actually matches; otherwise rebuild.
        if ($this->reverbGalleryMatchesLastPush($listingId, $urls)) {
            $shown = $this->waitForReverbDisplayCount($token, $listingId, $targetCount, $primeWait);
            if ($shown === $targetCount) {
                Log::info('Reverb: gallery already current, skipped rebuild', [
                    'listing_id' => $listingId,
                    'target_count' => $targetCount,
                    'display_count' => $shown,
                ]);

                return $this->putReverbListingPhotos($token, $listingId, $urls);
            }
        }

        $oldIdSet = array_flip(array_map(fn ($r) => (string) $r['id'], $records));
        $anchorId = (string) $records[0]['id'];

        // 1. Delete every old photo except one temp anchor (Reverb forbids an empty gallery).
        $deletedOld = 0;
        foreach (array_slice($records, 1) as $record) {
            if ($this->deleteReverbListingImageById($token, $listingId, (string) $record['id'])) {
                $deletedOld++;
            }
            usleep(120000);
        }

        // 2. Upload the MAIN first so it becomes the oldest of the new photos = the listing's main.
        $prime = $this->putReverbListingPhotos($token, $listingId, [$main]);
        if (! $prime->successful()) {
            return $prime;
        }
        // A distinct new image id means the main is its own fresh photo. If none ever appears, the
        // main URL deduped onto the anchor itself, so the anchor already IS the main and must stay.
        $mainIsNew = $this->waitForReverbNewPhoto($token, $listingId, $oldIdSet, $primeWait);

        // 3. Drop the temp anchor so the main is the oldest remaining photo (skip if it is the main).
        $deletedAnchor = false;
        if ($mainIsNew) {
            $deletedAnchor = $this->deleteReverbListingImageById($token, $listingId, $anchorId);
            if ($deletedAnchor) {
                $deletedOld++;
            }
        }

        // 4. Upload the rest one at a time, in order, waiting for each to ingest before the next so
        //    its upload time lands in sequence — Reverb orders the gallery oldest-first, so the
        //    display order then matches urls order (main, then 2nd, 3rd…). Sending the cumulative
        //    list each step is correct whether Reverb treats `PUT photos` as append or replace.
        $response = $prime;
        $accumulated = [$main];
        foreach (array_slice($urls, 1) as $next) {
            $accumulated[] = $next;
            $put = $this->putReverbListingPhotos($token, $listingId, $accumulated);
            if ($put->successful()) {
                $response = $put;
            }
            $this->waitForReverbDisplayCount($token, $listingId, count($accumulated), min(40, $maxWait));
        }

        // 5. Verify against the DISPLAY gallery, nudging stragglers with a re-PUT of the full set.
        $shown = $this->waitForReverbDisplayCount($token, $listingId, $targetCount, $maxWait);
        for ($nudge = 0; $nudge < 3 && $shown < $targetCount; $nudge++) {
            $put = $this->putReverbListingPhotos($token, $listingId, $urls);
            if ($put->successful()) {
                $response = $put;
            }
            $shown = $this->waitForReverbDisplayCount($token, $listingId, $targetCount, min(60, $maxWait));
        }

        // 6. If we kept the anchor but the gallery overshot, the main turned out to be a fresh photo
        //    after all (slow ingest) and the anchor is a stale extra — remove it now.
        if (! $deletedAnchor && $shown > $targetCount
            && in_array($anchorId, array_column($this->fetchListingImageRecords($token, $listingId), 'id'), true)) {
            if ($this->deleteReverbListingImageById($token, $listingId, $anchorId)) {
                $deletedOld++;
                $deletedAnchor = true;
                $shown = $this->reverbDisplayPhotoCount($token, $listingId);
            }
        }

        if ($shown < $targetCount) {
            Log::warning('Reverb replace: display gallery short of target', [
                'listing_id' => $listingId,
                'target_count' => $targetCount,
                'display_count' => $shown,
            ]);

            throw new \RuntimeException(
                "Reverb shows only {$shown} of {$targetCount} images. Please retry."
            );
        }

        Log::info('Reverb: replaced listing images', [
            'listing_id' => $listingId,
            'target_count' => $targetCount,
            'deleted_old' => $deletedOld,
            'deleted_anchor' => $deletedAnchor,
            'main_is_new' => $mainIsNew,
            'display_count' => $shown,
        ]);

        return $response;
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
     * Try payloads Reverb accepts when applying a product video URL.
     */
    private function putReverbListingVideo(string $token, string $listingId, string $videoUrl): Response
    {
        $variants = [
            ['video_url' => $videoUrl],
            ['product_video_url' => $videoUrl],
            ['video' => ['url' => $videoUrl]],
            ['videos' => [$videoUrl]],
        ];
        $last = null;
        foreach ($variants as $payload) {
            $last = $this->reverbPutListingWithRetry($token, $listingId, $payload);
            if ($last->successful()) {
                return $last;
            }
            Log::warning('Reverb PUT listing video attempt failed', [
                'listing_id' => $listingId,
                'status' => $last->status(),
                'body' => mb_substr($last->body(), 0, 800),
            ]);
        }

        return $last;
    }

    /**
     * Replace listing product video by public HTTPS URL.
     *
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string, listing_id?: string, normalized_urls?: list<string>}
     */
    public function updateListingVideos(string $identifier, array $videoUrls, string $mode = 'replace'): array
    {
        $token = self::getReverbBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb API token not configured (set REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).'];
        }

        $urls = array_values(array_filter(array_map('trim', $videoUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 3);
        foreach ($urls as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }
        if ($urls === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $trim = trim($identifier);
        if ($trim === '') {
            return ['success' => false, 'message' => 'SKU or listing_id is required.'];
        }

        $listingId = $this->resolveReverbListingId($trim);
        if ($listingId === null) {
            return ['success' => false, 'message' => 'No Reverb listing found for SKU or reverb_listing_id.'];
        }

        try {
            $response = $this->putReverbListingVideo($token, (string) $listingId, $urls[0]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Reverb listing video updated.',
                    'listing_id' => $listingId,
                    'normalized_urls' => [$urls[0]],
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
     * Video Master compatibility method: push video then persist video_master_json in reverb_products.
     *
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, listing_id?: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 3);
        if ($videos === [] && strtolower(trim($mode)) !== 'replace') {
            return ['success' => true, 'message' => 'No videos to add; skipped.'];
        }
        if ($videos === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $res = $this->updateListingVideos($identifier, $videos, $mode);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $trim = trim($identifier);
        $listingId = (string) ($res['listing_id'] ?? '');
        $toSave = isset($res['normalized_urls']) && is_array($res['normalized_urls']) ? $res['normalized_urls'] : $videos;
        $saved = $this->saveVideoUrlsToReverbProducts($trim, $listingId, $toSave);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Reverb listing video updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * @param  list<string>  $videos
     */
    private function saveVideoUrlsToReverbProducts(string $identifier, string $listingId, array $videos): bool
    {
        try {
            if (! Schema::hasTable('reverb_products')) {
                return false;
            }
            $payload = json_encode(array_values($videos), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('reverb_products', 'video_master_json')) {
                $update['video_master_json'] = $payload;
            }
            if (Schema::hasColumn('reverb_products', 'video_urls')) {
                $update['video_urls'] = $payload;
            }
            if ($update === []) {
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
                    ->update($update);
                $matched = $count > 0;
            }
            if (! $matched && $listingId !== '') {
                $count = ReverbProduct::query()->where('reverb_listing_id', $listingId)->update($update);
                $matched = $count > 0;
            }

            return $matched;
        } catch (\Throwable $e) {
            Log::warning('Reverb video metrics save failed', ['identifier' => $identifier, 'listing_id' => $listingId, 'error' => $e->getMessage()]);

            return false;
        }
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
    /**
     * Description Master: return the current Reverb listing description (HTML) for one SKU. Read-only
     * (DB-first via reverb_products, then Reverb API fallback).
     *
     * @return array{success: bool, message: string, html?: string}
     */
    public function fetchDescriptionHtml(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $token = self::getReverbBearerToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Reverb token not configured.'];
        }

        $listingId = $this->getListingIdBySku($identifier);
        if (! $listingId) {
            return ['success' => false, 'message' => 'No Reverb listing found for this SKU.'];
        }

        $res = $this->fetchCurrentReverbDescription($token, (string) $listingId, $identifier);
        $html = trim((string) ($res['html'] ?? ''));
        if ($html === '') {
            $html = trim((string) ($res['plain'] ?? ''));
        }
        if ($html === '') {
            return ['success' => false, 'message' => 'Reverb listing has no description.'];
        }

        return ['success' => true, 'message' => 'Reverb description loaded.', 'html' => $html];
    }

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

    /**
     * Search the Reverb category tree for Listing Manager (uuid + full path).
     *
     * @return array{success: bool, categories: list<array{id: string, path: string}>, conditions: list<array{id: string, name: string}>, message?: string}
     */
    public function searchListingCategories(string $query, string $title = ''): array
    {
        $flat = $this->flattenedListingCategories();
        if ($flat === []) {
            return [
                'success' => false,
                'categories' => [],
                'conditions' => $this->listingConditions(),
                'message' => 'Could not load Reverb categories. Check Reverb API credentials.',
            ];
        }

        $q = trim($query);
        if ($q === '') {
            $q = trim($title);
        }

        $matched = [];
        if ($q === '') {
            foreach ($flat as $row) {
                if (substr_count((string) ($row['path'] ?? ''), '>') === 0) {
                    $matched[] = $row;
                }
            }
            if ($matched === []) {
                $matched = array_slice($flat, 0, 40);
            }
        } else {
            $needle = mb_strtolower($q);
            $looksUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $q);
            foreach ($flat as $row) {
                $id = (string) ($row['id'] ?? '');
                $path = (string) ($row['path'] ?? '');
                if ($looksUuid && strcasecmp($id, $q) === 0) {
                    array_unshift($matched, $row);

                    continue;
                }
                $hay = mb_strtolower($path.' '.(string) ($row['search'] ?? '').' '.$id);
                if (str_contains($hay, $needle)) {
                    $matched[] = $row;
                }
            }
        }

        $seen = [];
        $out = [];
        foreach ($matched as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $row;
        }
        if ($q !== '' && $out !== []) {
            $needle = mb_strtolower($q);
            usort($out, function ($a, $b) use ($needle) {
                $pa = mb_strtolower((string) ($a['path'] ?? ''));
                $pb = mb_strtolower((string) ($b['path'] ?? ''));
                $la = trim((string) substr($pa, (int) strrpos($pa, '>') + 1));
                $lb = trim((string) substr($pb, (int) strrpos($pb, '>') + 1));
                $aLeaf = str_contains($la, $needle) ? 1 : 0;
                $bLeaf = str_contains($lb, $needle) ? 1 : 0;
                if ($aLeaf !== $bLeaf) {
                    return $bLeaf <=> $aLeaf;
                }

                return substr_count($pb, '>') <=> substr_count($pa, '>');
            });
        }
        $out = array_slice($out, 0, 60);

        if ($out === [] && $q !== '') {
            foreach ($this->scoreListingCategories($q.' '.$title) as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = ['id' => $id, 'path' => (string) ($row['path'] ?? '')];
                if (count($out) >= 20) {
                    break;
                }
            }
        }

        return [
            'success' => true,
            'categories' => $out,
            'conditions' => $this->listingConditions(),
            'message' => $out === [] ? 'No Reverb categories matched that search.' : null,
        ];
    }

    /**
     * Pick the best Reverb leaf category for a product title / type.
     *
     * @return array{id: string, path: string, score: int}
     */
    public function suggestListingCategory(string $title, array $hints = []): array
    {
        $text = trim(implode(' ', array_filter(array_merge([$title], $hints), fn ($v) => trim((string) $v) !== '')));
        $ranked = $this->scoreListingCategories($text);
        $best = $ranked[0] ?? null;
        if (! is_array($best) || (int) ($best['score'] ?? 0) < 12) {
            return ['id' => '', 'path' => '', 'score' => 0];
        }

        return [
            'id' => (string) ($best['id'] ?? ''),
            'path' => (string) ($best['path'] ?? ''),
            'score' => (int) ($best['score'] ?? 0),
        ];
    }

    /**
     * Resolve a typed / Product Master category name to a Reverb leaf category.
     *
     * @return array{id: string, path: string}
     */
    public function resolveCategoryByName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['id' => '', 'path' => ''];
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $name)) {
            return ['id' => $name, 'path' => ''];
        }

        $search = $this->searchListingCategories($name);
        $rows = is_array($search['categories'] ?? null) ? $search['categories'] : [];
        $needle = mb_strtolower($name);
        $best = ['id' => '', 'path' => ''];
        $bestScore = -1;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $path = trim((string) ($row['path'] ?? ''));
            if ($id === '' || $path === '') {
                continue;
            }
            $low = mb_strtolower($path);
            $leaf = trim((string) substr($path, (int) strrpos($path, '>') + 1));
            $leaf = trim($leaf, " \t>");
            $score = 0;
            if (mb_strtolower($leaf) === $needle) {
                $score += 100;
            } elseif (str_contains(mb_strtolower($leaf), $needle)) {
                $score += 70;
            } elseif (str_contains($low, $needle)) {
                $score += 40;
            }
            $score += min(10, substr_count($path, '>'));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['id' => $id, 'path' => $path];
            }
        }
        if ($bestScore >= 40 && $best['id'] !== '') {
            return $best;
        }

        $suggested = $this->suggestListingCategory($name, [$name]);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) ($suggested['id'] ?? ''))) {
            return [
                'id' => (string) $suggested['id'],
                'path' => (string) ($suggested['path'] ?? ''),
            ];
        }

        return $bestScore > 0 ? $best : ['id' => '', 'path' => ''];
    }

    /**
     * @return list<array{id: string, path: string, score: int}>
     */
    private function scoreListingCategories(string $text): array
    {
        $flat = $this->flattenedListingCategories();
        if ($flat === []) {
            return [];
        }

        $text = mb_strtolower($text);
        $text = str_replace(['celling', '5-core', '5 core', '5core'], ['ceiling', ' ', ' ', ' '], $text);
        $phrases = $this->listingCategoryPhrases($text);
        $words = $this->listingCategoryWords($text, $phrases);
        if ($phrases === [] && $words === []) {
            return [];
        }

        $ranked = [];
        foreach ($flat as $row) {
            $path = mb_strtolower((string) ($row['path'] ?? ''));
            $haystack = mb_strtolower(trim((string) ($row['search'] ?? $path)));
            $id = trim((string) ($row['id'] ?? ''));
            if ($haystack === '' || $id === '') {
                continue;
            }
            $score = 0;
            foreach ($phrases as $phrase) {
                $parts = preg_split('/\s+/', $phrase) ?: [];
                $hits = 0;
                foreach ($parts as $part) {
                    if ($part !== '' && str_contains($haystack, $part)) {
                        $hits++;
                    }
                }
                if ($parts !== [] && $hits === count($parts)) {
                    $score += 30 + (count($parts) * 5);
                    $leaf = trim((string) substr($path, (int) strrpos($path, '>') + 1));
                    if ($leaf !== '' && str_contains($leaf, $parts[0])) {
                        $score += 10;
                    }
                } elseif ($hits > 0) {
                    $score += $hits * 4;
                }
            }
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    $score += 3;
                }
            }
            $score += min(4, substr_count($path, '>'));
            if (! empty($row['listable'])) {
                $score += 2;
            }
            if ($score < 12) {
                continue;
            }
            $ranked[] = [
                'id' => $id,
                'path' => (string) ($row['path'] ?? ''),
                'score' => $score,
            ];
        }

        usort($ranked, function ($a, $b) {
            $diff = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            if ($diff !== 0) {
                return $diff;
            }

            return substr_count((string) ($b['path'] ?? ''), '>') <=> substr_count((string) ($a['path'] ?? ''), '>');
        });

        return $ranked;
    }

    /**
     * @return list<string>
     */
    private function listingCategoryPhrases(string $text): array
    {
        $catalog = [
            'ceiling speaker' => ['ceiling speaker', 'in-ceiling', 'in ceiling', 'in-wall speaker', 'in wall speaker', 'flush mount speaker'],
            'car speaker' => ['car speaker', 'car audio speaker', 'coaxial speaker'],
            'car tweeter' => ['car tweeter', 'tweeter'],
            'pa speaker' => ['pa speaker', 'pa speakers', 'powered speaker'],
            'naked speaker' => ['naked speaker', 'raw speaker'],
            'subwoofer' => ['subwoofer', 'sub woofer', 'sub-woofer'],
            'drum microphone' => ['drum microphone', 'drum mic'],
            'microphone' => ['microphone', ' mic ', 'mic'],
            'drum stool' => ['drum stool', 'drum throne'],
            'mixer' => ['mixer', 'mixing console'],
            'projector stand' => ['projector stand'],
            'foot pedal' => ['foot pedal', 'ft pdl'],
            'speaker cable' => ['speaker cable', 'instrument cable', 'xlr cable'],
            'amplifier' => ['amplifier', 'power amp'],
            'microphone stand' => ['microphone stand', 'mic stand', 'vocal stand', 'boom stand'],
            'keyboard stand' => ['keyboard stand'],
            'guitar stand' => ['guitar stand'],
            'speaker stand' => ['speaker stand'],
            'capo' => ['capo', 'capos'],
            'speaker' => ['speaker', 'speakers'],
        ];

        $found = [];
        foreach ($catalog as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($alias === 'mic') {
                    if (preg_match('/\bmic\b/', $text)) {
                        $found[] = $canonical;
                        break;
                    }
                    continue;
                }
                if (str_contains($text, $alias)) {
                    $found[] = $canonical;
                    break;
                }
            }
        }

        $specificSpeaker = array_intersect($found, ['ceiling speaker', 'car speaker', 'pa speaker', 'naked speaker']);
        if ($specificSpeaker !== []) {
            $found = array_values(array_filter($found, fn ($phrase) => $phrase !== 'speaker'));
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  list<string>  $phrases
     * @return list<string>
     */
    private function listingCategoryWords(string $text, array $phrases): array
    {
        $stop = [
            'core', 'inch', 'pair', 'watt', 'watts', 'ohm', 'ohms', 'way', 'for', 'the', 'and',
            'with', 'from', 'home', 'space', 'commercial', 'professional', 'black', 'white',
            'pack', 'pcs', 'new', 'flush', 'mount',
        ];
        $used = [];
        foreach ($phrases as $phrase) {
            foreach (preg_split('/\s+/', $phrase) ?: [] as $part) {
                if ($part !== '') {
                    $used[$part] = true;
                }
            }
        }
        $words = [];
        foreach (preg_split('/[^a-z0-9]+/', $text) ?: [] as $token) {
            if (strlen($token) < 5 || isset($used[$token]) || in_array($token, $stop, true)) {
                continue;
            }
            $words[] = $token;
        }

        return array_values(array_unique($words));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listingConditions(): array
    {
        $cached = Cache::get('reverb_listing_conditions_v1');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $json = $this->reverbApiGet('/listing_conditions');
        $rows = is_array($json['conditions'] ?? null) ? $json['conditions'] : [];
        if ($rows === [] && is_array($json['listing_conditions'] ?? null)) {
            $rows = $json['listing_conditions'];
        }
        if ($rows === [] && is_array($json['_embedded']['conditions'] ?? null)) {
            $rows = $json['_embedded']['conditions'];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['uuid'] ?? $row['id'] ?? ''));
            $name = trim((string) ($row['display_name'] ?? $row['name'] ?? $row['slug'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = ['id' => $id !== '' ? $id : $name, 'name' => $name];
        }

        if ($out === []) {
            foreach (['Brand New', 'Mint', 'Excellent', 'Very Good', 'Good', 'Fair', 'Poor', 'Non Functioning', 'B-Stock'] as $name) {
                $out[] = ['id' => $name, 'name' => $name];
            }

            return $out;
        }

        Cache::put('reverb_listing_conditions_v1', $out, now()->addHours(12));

        return $out;
    }

    /**
     * @return list<array{id: string, path: string}>
     */
    private function flattenedListingCategories(): array
    {
        $cached = Cache::get('reverb_listing_categories_flat_v2');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $roots = [];
        foreach (['/categories/flat', '/categories'] as $path) {
            $json = $this->reverbApiGet($path);
            $roots = is_array($json['categories'] ?? null) ? $json['categories'] : [];
            if ($roots === [] && is_array($json['_embedded']['categories'] ?? null)) {
                $roots = $json['_embedded']['categories'];
            }
            if ($roots !== []) {
                break;
            }
        }

        $out = [];
        $this->flattenReverbCategoryNodes($roots, $out, '');
        if ($out !== []) {
            Cache::put('reverb_listing_categories_flat_v2', $out, now()->addHours(12));
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  list<array{id: string, path: string}>  $out
     */
    private function flattenReverbCategoryNodes(array $nodes, array &$out, string $parentPath): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $uuid = trim((string) ($node['uuid'] ?? $node['id'] ?? ''));
            $name = trim((string) ($node['name'] ?? ''));
            $path = trim((string) ($node['full_name'] ?? ''));
            if ($path === '') {
                $path = $parentPath !== '' && $name !== '' ? $parentPath.' > '.$name : $name;
            }
            $search = strtolower(trim(implode(' ', array_filter([
                $path,
                $name,
                (string) ($node['collection_title'] ?? ''),
                str_replace('-', ' ', (string) ($node['slug'] ?? '')),
            ]))));
            if ($uuid !== '' && $path !== '') {
                $out[] = [
                    'id' => $uuid,
                    'path' => $path,
                    'search' => $search,
                    'listable' => ! empty($node['listable']),
                ];
            }
            $subs = $node['subcategories'] ?? $node['categories'] ?? null;
            if (! is_array($subs) && is_array($node['_embedded']['subcategories'] ?? null)) {
                $subs = $node['_embedded']['subcategories'];
            }
            if (is_array($subs) && $subs !== []) {
                $this->flattenReverbCategoryNodes($subs, $out, $path !== '' ? $path : $parentPath);
            }
        }
    }

    /**
     * @param  list<string>  $urls
     * @return list<string|array<string, mixed>>
     */
    private function photosAsReverbLinks(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $out[] = [
                '_links' => [
                    'photo' => ['href' => $url],
                ],
            ];
        }

        return $out;
    }

    /**
     * @return array{local: bool, rates: list<array<string, mixed>>}
     */
    private function defaultCreateShippingRates(): array
    {
        return [
            'local' => false,
            'rates' => [[
                'region_code' => 'US',
                'rate' => ['amount' => '0.00', 'currency' => 'USD'],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractCreatedListingId(array $json): string
    {
        foreach (['id', 'listing_id'] as $key) {
            $value = $json[$key] ?? null;
            if (is_numeric($value) || (is_string($value) && trim($value) !== '')) {
                return trim((string) $value);
            }
        }
        $nested = $json['listing'] ?? null;
        if (is_array($nested)) {
            $value = $nested['id'] ?? $nested['listing_id'] ?? null;
            if (is_numeric($value) || (is_string($value) && trim((string) $value) !== '')) {
                return trim((string) $value);
            }
        }
        $href = (string) ($json['_links']['self']['href'] ?? $json['_links']['web']['href'] ?? '');
        if (preg_match('#/(?:listings|item)/(\d+)#', $href, $m)) {
            return $m[1];
        }

        return '';
    }

    private function formatReverbErrorBody(Response $response): string
    {
        $json = $response->json();
        if (! is_array($json)) {
            return mb_substr($response->body(), 0, 2000);
        }
        $parts = [];
        if (! empty($json['message']) && is_string($json['message'])) {
            $parts[] = $json['message'];
        }
        $errors = $json['errors'] ?? [];
        if (is_array($errors)) {
            foreach ($errors as $field => $msgs) {
                if (is_string($field) && ! is_numeric($field)) {
                    $text = is_array($msgs) ? implode('; ', array_map('strval', $msgs)) : (string) $msgs;
                    $parts[] = $field.': '.$text;
                } elseif (is_string($msgs)) {
                    $parts[] = $msgs;
                } elseif (is_array($msgs)) {
                    $parts[] = implode('; ', array_map('strval', $msgs));
                }
            }
        }

        return $parts !== [] ? implode(' ', $parts) : mb_substr($response->body(), 0, 2000);
    }

    /**
     * @return array<string, mixed>
     */
    private function reverbApiGet(string $path): array
    {
        $token = self::getReverbBearerToken();
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $url = $apiBase.'/'.ltrim($path, '/');
        $headers = [
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ];
        if (is_string($token) && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(45)
                ->withHeaders($headers)
                ->get($url);

            if ($response->failed()) {
                Log::warning('Reverb API GET failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 400),
                ]);

                return [];
            }

            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            Log::warning('Reverb API GET exception', ['url' => $url, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
