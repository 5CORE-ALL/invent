<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Persist Macy / Best Buy seller-portal offer active flags from MCM OF21
 * (GET /api/offers.active). Does not treat missing price or unlisted SKUs as Inactive.
 */
class MiraklMcmOfferStatusSync
{
    /**
     * @return array{ok: bool, done: bool, active: int, inactive: int, error?: string}
     */
    public function sync(string $channel, int $timeBudgetSeconds = 90): array
    {
        $channel = strtolower(trim($channel));
        $table = match ($channel) {
            'bestbuy' => 'bestbuy_usa_products',
            default => 'macy_products',
        };
        $configKey = $channel === 'bestbuy' ? 'bestbuy' : 'macy';
        $connectCode = $channel === 'bestbuy' ? 'bestbuyusa' : 'macys';

        if (! Schema::hasTable($table)) {
            return ['ok' => false, 'done' => false, 'active' => 0, 'inactive' => 0, 'error' => $table.' missing'];
        }
        $this->ensureListingStatusColumn($table);

        $apiKey = trim((string) config('services.'.$configKey.'.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.'.$configKey.'.mcm_base_url', ''), '/');
        if ($apiKey !== '' && $baseUrl !== '') {
            return $this->syncFromMcm($channel, $table, $configKey, $apiKey, $baseUrl, $timeBudgetSeconds);
        }

        return $this->syncFromConnect($channel, $table, $connectCode, $timeBudgetSeconds);
    }

    /**
     * @return array{ok: bool, done: bool, active: int, inactive: int, error?: string}
     */
    protected function syncFromMcm(
        string $channel,
        string $table,
        string $configKey,
        string $apiKey,
        string $baseUrl,
        int $timeBudgetSeconds
    ): array {
        $offsetKey = 'mm.'.$channel.'.mcm_offer_status_offset_v1';
        $offset = (int) Cache::get($offsetKey, 0);
        $deadline = microtime(true) + max(15, $timeBudgetSeconds);
        $max = 100;
        $active = 0;
        $inactive = 0;
        $ok = false;

        $shopId = config('services.'.$configKey.'.shop_id');

        try {
            do {
                if (microtime(true) >= $deadline) {
                    Cache::put($offsetKey, $offset, now()->addHours(6));

                    return ['ok' => true, 'done' => false, 'active' => $active, 'inactive' => $inactive];
                }

                $params = [
                    'max' => $max,
                    'offset' => $offset,
                ];
                if ($shopId !== null && $shopId !== '') {
                    $params['shop_id'] = (int) $shopId;
                }

                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(60)
                    ->get($baseUrl.'/api/offers', $params);

                if (! $response->successful()) {
                    Log::warning('MiraklMcmOfferStatusSync: OF21 failed', [
                        'channel' => $channel,
                        'status' => $response->status(),
                    ]);

                    return [
                        'ok' => $ok,
                        'done' => false,
                        'active' => $active,
                        'inactive' => $inactive,
                        'error' => 'OF21 HTTP '.$response->status(),
                    ];
                }

                $ok = true;
                $offers = $response->json('offers') ?? [];
                if (! is_array($offers)) {
                    $offers = [];
                }
                $totalCount = (int) ($response->json('total_count') ?? 0);

                foreach ($offers as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }
                    $status = $this->statusFromMcmOffer($offer);
                    if ($status === null) {
                        continue;
                    }
                    $sku = trim((string) ($offer['shop_sku'] ?? $offer['sku'] ?? $offer['product_sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $this->persistStatus($table, $sku, $status);
                    if ($status === 'inactive') {
                        $inactive++;
                    } else {
                        $active++;
                    }
                }

                $fetched = count($offers);
                $offset += $max;
                $hasMore = $fetched >= $max && ($totalCount === 0 || $offset < $totalCount);
                if (! $hasMore) {
                    Cache::forget($offsetKey);

                    return ['ok' => true, 'done' => true, 'active' => $active, 'inactive' => $inactive];
                }
            } while (true);
        } catch (\Throwable $e) {
            Log::warning('MiraklMcmOfferStatusSync: MCM exception', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => $ok,
                'done' => false,
                'active' => $active,
                'inactive' => $inactive,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, done: bool, active: int, inactive: int, error?: string}
     */
    protected function syncFromConnect(string $channel, string $table, string $connectCode, int $timeBudgetSeconds): array
    {
        $token = $this->connectToken();
        if (! $token) {
            return ['ok' => false, 'done' => false, 'active' => 0, 'inactive' => 0, 'error' => 'Connect token missing'];
        }

        $pageKey = 'mm.'.$channel.'.connect_status_page_token_v1';
        $pageToken = Cache::get($pageKey);
        $pageToken = is_string($pageToken) && $pageToken !== '' ? $pageToken : null;
        $deadline = microtime(true) + max(15, $timeBudgetSeconds);
        $active = 0;
        $inactive = 0;
        $ok = false;
        $sawStatusField = false;

        try {
            do {
                if (microtime(true) >= $deadline) {
                    if ($pageToken) {
                        Cache::put($pageKey, $pageToken, now()->addHours(6));
                    }

                    return ['ok' => $ok, 'done' => false, 'active' => $active, 'inactive' => $inactive];
                }

                $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code='.$connectCode;
                if ($pageToken) {
                    $url .= '&page_token='.urlencode($pageToken);
                }
                $response = Http::withoutVerifying()->withToken($token)->timeout(60)->get($url);
                if (! $response->successful()) {
                    return [
                        'ok' => $ok,
                        'done' => false,
                        'active' => $active,
                        'inactive' => $inactive,
                        'error' => 'Connect HTTP '.$response->status(),
                    ];
                }
                $ok = true;
                $json = $response->json();
                $products = is_array($json['data'] ?? null) ? $json['data'] : [];
                $pageToken = isset($json['next_page_token']) ? (string) $json['next_page_token'] : null;
                if ($pageToken === '') {
                    $pageToken = null;
                }

                foreach ($products as $product) {
                    if (! is_array($product)) {
                        continue;
                    }
                    $status = $this->statusFromConnectProduct($product);
                    if ($status === null) {
                        continue;
                    }
                    $sawStatusField = true;
                    $sku = trim((string) ($product['id'] ?? $product['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $this->persistStatus($table, $sku, $status);
                    if ($status === 'inactive') {
                        $inactive++;
                    } else {
                        $active++;
                    }
                }

                if ($pageToken === null) {
                    Cache::forget($pageKey);
                    if (! $sawStatusField) {
                        Log::info('MiraklMcmOfferStatusSync: Connect products had no offer status fields', [
                            'channel' => $channel,
                        ]);
                    }

                    return ['ok' => true, 'done' => true, 'active' => $active, 'inactive' => $inactive];
                }
            } while (true);
        } catch (\Throwable $e) {
            Log::warning('MiraklMcmOfferStatusSync: Connect exception', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => $ok,
                'done' => false,
                'active' => $active,
                'inactive' => $inactive,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function statusFromMcmOffer(array $offer): ?string
    {
        if (array_key_exists('active', $offer) && ! is_array($offer['active'])) {
            $raw = $offer['active'];
            if (is_bool($raw)) {
                return $raw ? 'active' : 'inactive';
            }
            if (is_numeric($raw)) {
                return ((int) $raw) === 1 ? 'active' : 'inactive';
            }
            $s = strtolower(trim((string) $raw));
            if (in_array($s, ['true', '1', 'yes', 'active'], true)) {
                return 'active';
            }
            if (in_array($s, ['false', '0', 'no', 'inactive'], true)) {
                return 'inactive';
            }
        }

        $state = strtolower(trim((string) ($offer['offer_state_code'] ?? $offer['state_code'] ?? '')));
        if ($state === '11') {
            return 'active';
        }
        if ($state !== '' && $state !== '11') {
            $bucket = MarketplacePortalStatusTabs::bucket($state);

            return $bucket === 'other' ? 'inactive' : $bucket;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function statusFromConnectProduct(array $product): ?string
    {
        if (array_key_exists('active', $product) && ! is_array($product['active'])) {
            $raw = $product['active'];
            if (is_bool($raw)) {
                return $raw ? 'active' : 'inactive';
            }
            if (is_numeric($raw)) {
                return ((int) $raw) === 1 ? 'active' : 'inactive';
            }
        }
        foreach (['status', 'offer_status', 'product_status', 'state'] as $key) {
            if (! array_key_exists($key, $product) || is_array($product[$key])) {
                continue;
            }
            $raw = trim((string) $product[$key]);
            if ($raw === '') {
                continue;
            }
            $bucket = MarketplacePortalStatusTabs::bucket($raw);
            if ($bucket !== 'other') {
                return $bucket;
            }
        }
        $offers = $product['offers'] ?? null;
        if (! is_array($offers) || $offers === []) {
            return null;
        }
        $anyActive = false;
        $anyInactive = false;
        foreach ($offers as $offer) {
            if (! is_array($offer) || ! array_key_exists('active', $offer) || is_array($offer['active'])) {
                continue;
            }
            if (filter_var($offer['active'], FILTER_VALIDATE_BOOLEAN)) {
                $anyActive = true;
            } else {
                $anyInactive = true;
            }
        }
        if ($anyInactive && ! $anyActive) {
            return 'inactive';
        }
        if ($anyActive) {
            return 'active';
        }

        return null;
    }

    protected function persistStatus(string $table, string $sku, string $status): void
    {
        try {
            $now = now();
            $existing = DB::table($table)->where('sku', $sku)->first();
            if ($existing) {
                DB::table($table)->where('id', $existing->id)->update([
                    'listing_status' => $status,
                    'updated_at' => $now,
                ]);

                return;
            }
            DB::table($table)->insert([
                'sku' => $sku,
                'listing_status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MiraklMcmOfferStatusSync: persist failed', [
                'table' => $table,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function connectToken(): ?string
    {
        try {
            $cached = Cache::get('macy_access_token');
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable $e) {
            // continue
        }
        $clientId = config('services.macy.client_id');
        $clientSecret = config('services.macy.client_secret');
        if (! $clientId || ! $clientSecret) {
            return null;
        }
        try {
            $response = Http::withoutVerifying()->asForm()->timeout(30)->post('https://auth.mirakl.net/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
            if (! $response->successful()) {
                return null;
            }
            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                return null;
            }
            Cache::put('macy_access_token', $token, 3000);

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function ensureListingStatusColumn(string $table): void
    {
        if (Schema::hasColumn($table, 'listing_status')) {
            return;
        }
        try {
            Schema::table($table, function ($blueprint) {
                $blueprint->string('listing_status', 32)->nullable()->index();
            });
        } catch (\Throwable $e) {
            Log::warning('MiraklMcmOfferStatusSync: could not add listing_status', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
