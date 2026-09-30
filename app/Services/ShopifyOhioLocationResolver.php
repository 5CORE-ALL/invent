<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve the Shopify warehouse location for inventory updates.
 * Prefers SHOPIFY_INVENTORY_LOCATION_ID, then a location named "Ohio" or "Main Warehouse".
 */
class ShopifyOhioLocationResolver
{
    /**
     * Configured / cached preferred location id (Ohio).
     */
    public static function preferredLocationId(): ?string
    {
        $configured = config('services.shopify.inventory_location_id');
        if (! empty($configured)) {
            return (string) $configured;
        }

        $cached = Cache::get('shopify_ohio_preferred_location_id');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $locationId = self::fetchOhioLocationId();
        if (is_string($locationId) && $locationId !== '') {
            Cache::put('shopify_ohio_preferred_location_id', $locationId, 3600);
        }

        return $locationId;
    }

    /**
     * Shopify location named Main Warehouse only. Other locations are ignored.
     */
    public static function mainWarehouseLocationId(): ?string
    {
        $cached = Cache::get('shopify_main_warehouse_location_id');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $locations = self::fetchLocations();
        if ($locations === null) {
            return null;
        }

        foreach ($locations as $loc) {
            if (! is_array($loc) || ($loc['active'] ?? true) === false || empty($loc['id'])) {
                continue;
            }
            if (self::locationNameRank((string) ($loc['name'] ?? '')) !== 1) {
                continue;
            }

            $id = (string) $loc['id'];
            Cache::put('shopify_main_warehouse_location_id', $id, 3600);

            return $id;
        }

        return null;
    }

    /**
     * locations.json is shared by every Accept. A 429 must be retried; a miss must not be cached.
     */
    private static function fetchOhioLocationId(): ?string
    {
        $locations = self::fetchLocations();
        if ($locations === null) {
            return null;
        }

        return self::pickLocationId($locations);
    }

    /**
     * @return array<int, array<string, mixed>>|null null when the request failed
     */
    private static function fetchLocations(): ?array
    {
        $domain = config('services.shopify.store_url');
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        if (! $domain || ! $token) {
            return null;
        }

        $maxAttempts = 3;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                ShopifyAdminCallPacer::wait();
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get("https://{$domain}/admin/api/2025-01/locations.json");
            } catch (\Throwable $e) {
                Log::warning('ShopifyOhioLocationResolver: could not resolve Ohio location', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            if ($response->status() === 429 && $attempt < $maxAttempts - 1) {
                $retryAfter = $response->header('Retry-After');
                $wait = (is_numeric($retryAfter) && (int) $retryAfter >= 0)
                    ? min(8, (int) $retryAfter)
                    : min(1 << $attempt, 4);
                Log::info('ShopifyOhioLocationResolver: locations.json rate limited, retrying', [
                    'attempt' => $attempt + 1,
                    'wait_seconds' => $wait,
                ]);
                if ($wait > 0) {
                    sleep($wait);
                }

                continue;
            }

            if (! $response->successful()) {
                Log::warning('ShopifyOhioLocationResolver: locations.json failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('locations') ?? [];
        }

        return null;
    }

    /**
     * Ohio first (older stores), then Main Warehouse, then the only active location.
     *
     * @param  array<int, array<string, mixed>>  $locations
     */
    private static function pickLocationId(array $locations): ?string
    {
        $active = [];
        foreach ($locations as $loc) {
            if (! is_array($loc) || ($loc['active'] ?? true) === false || empty($loc['id'])) {
                continue;
            }
            $active[] = $loc;
        }

        $bestId = null;
        $bestRank = PHP_INT_MAX;
        foreach ($active as $loc) {
            $rank = self::locationNameRank((string) ($loc['name'] ?? ''));
            if ($rank !== null && $rank < $bestRank) {
                $bestRank = $rank;
                $bestId = (string) $loc['id'];
            }
        }

        if ($bestId !== null) {
            return $bestId;
        }

        if (count($active) === 1) {
            return (string) $active[0]['id'];
        }

        return null;
    }

    private static function locationNameRank(string $name): ?int
    {
        $compact = strtolower((string) preg_replace('/\s+/', '', trim($name)));
        if (str_contains(strtolower($name), 'ohio')) {
            return 0;
        }
        if ($compact === 'mainwarehouse') {
            return 1;
        }

        return null;
    }

    /**
     * Pick Ohio from inventory_levels when present; otherwise first level (last resort).
     *
     * @param  array<int, array<string, mixed>>  $levels
     */
    public static function fromLevels(array $levels): ?string
    {
        if ($levels === []) {
            return null;
        }

        $preferredId = self::preferredLocationId();
        if ($preferredId !== null && $preferredId !== '') {
            foreach ($levels as $level) {
                if (isset($level['location_id']) && (string) $level['location_id'] === (string) $preferredId) {
                    return (string) $level['location_id'];
                }
            }

            Log::warning('ShopifyOhioLocationResolver: preferred Ohio location missing from inventory levels', [
                'preferred_location_id' => $preferredId,
                'available_location_ids' => array_column($levels, 'location_id'),
            ]);
        }

        // Fallback: Ohio or Main Warehouse, when that location is one of these levels.
        $locationIds = array_map('strval', array_column($levels, 'location_id'));
        if (count($locationIds) > 1) {
            try {
                $domain = config('services.shopify.store_url');
                $token = config('services.shopify.access_token') ?: config('services.shopify.password');
                if ($domain && $token) {
                    $locResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(15)->get("https://{$domain}/admin/api/2025-01/locations.json");

                    if ($locResponse->successful()) {
                        $picked = self::pickLocationId($locResponse->json('locations') ?? []);
                        if ($picked !== null && in_array($picked, $locationIds, true)) {
                            return $picked;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ShopifyOhioLocationResolver: Ohio name lookup failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return isset($levels[0]['location_id']) ? (string) $levels[0]['location_id'] : null;
    }

    /**
     * Available qty at the Ohio level (or first level if Ohio missing).
     *
     * @param  array<int, array<string, mixed>>  $levels
     * @return array{location_id: ?string, available: int}
     */
    public static function levelFromLevels(array $levels): array
    {
        $locationId = self::fromLevels($levels);
        $available = 0;

        if ($locationId !== null) {
            foreach ($levels as $level) {
                if (isset($level['location_id']) && (string) $level['location_id'] === (string) $locationId) {
                    $available = (int) ($level['available'] ?? 0);
                    break;
                }
            }
        }

        return [
            'location_id' => $locationId,
            'available' => $available,
        ];
    }
}
