<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve the Shopify Ohio warehouse location for inventory updates.
 * Prefers SHOPIFY_INVENTORY_LOCATION_ID, then a location named "Ohio".
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
     * locations.json is shared by every Accept. A 429 must be retried; a miss must not be cached.
     */
    private static function fetchOhioLocationId(): ?string
    {
        $domain = config('services.shopify.store_url');
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        if (! $domain || ! $token) {
            return null;
        }

        $maxAttempts = 3;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
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

            foreach ($response->json('locations') ?? [] as $loc) {
                if (stripos($loc['name'] ?? '', 'Ohio') !== false) {
                    return (string) $loc['id'];
                }
            }

            return null;
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

        // Fallback: scan all shop locations for a name containing "Ohio" that appears in levels
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
                        foreach ($locResponse->json('locations') ?? [] as $loc) {
                            $id = (string) ($loc['id'] ?? '');
                            if ($id !== '' && stripos($loc['name'] ?? '', 'Ohio') !== false && in_array($id, $locationIds, true)) {
                                return $id;
                            }
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
