<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingPinZoneService
{
    public const ORIGIN_ZIP = '43311';

    /** Bellefontaine, OH — origin warehouse postal. */
    private const ORIGIN_LAT = 40.3605;

    private const ORIGIN_LNG = -83.7571;

    /**
     * Resolve state + shipping zone from a destination ZIP/pin.
     * Zone is derived from distance (miles) from origin ZIP 43311.
     *
     * @return array{pin_code: string, state: ?string, state_abbr: ?string, zone: ?string, distance_miles: ?float, place: ?string}
     */
    public function lookup(string $pinCode): array
    {
        $pin = $this->normalizeZip($pinCode);

        $empty = [
            'pin_code' => $pin,
            'state' => null,
            'state_abbr' => null,
            'zone' => null,
            'distance_miles' => null,
            'place' => null,
        ];

        if ($pin === '' || strlen($pin) < 3) {
            return $empty;
        }

        $place = $this->fetchUsZipPlace($pin);
        if ($place === null) {
            return $empty;
        }

        $lat = (float) ($place['latitude'] ?? 0);
        $lng = (float) ($place['longitude'] ?? 0);
        $miles = $this->haversineMiles(self::ORIGIN_LAT, self::ORIGIN_LNG, $lat, $lng);
        $stateAbbr = strtoupper(trim((string) ($place['state abbreviation'] ?? '')));
        $zone = $this->zoneFromDistance($miles, $stateAbbr);

        return [
            'pin_code' => $pin,
            'state' => $place['state'] ?? null,
            'state_abbr' => $stateAbbr !== '' ? $stateAbbr : null,
            'zone' => $zone,
            'distance_miles' => round($miles, 1),
            'place' => $place['place name'] ?? null,
        ];
    }

    public function normalizeZip(string $pinCode): string
    {
        $pin = strtoupper(trim($pinCode));
        // Keep ZIP+4 as 5-digit base for lookup.
        if (preg_match('/^(\d{5})(?:-\d{4})?$/', $pin, $m)) {
            return $m[1];
        }

        return preg_replace('/\s+/', '', $pin) ?? '';
    }

    /**
     * USPS-style zone bands by approximate mileage from origin.
     */
    public function zoneFromDistance(float $miles, ?string $stateAbbr = null): string
    {
        $abbr = strtoupper(trim((string) $stateAbbr));
        if (in_array($abbr, ['AK', 'HI', 'PR', 'GU', 'VI', 'AS', 'MP'], true)) {
            return 'Zone 9';
        }

        if ($miles <= 50) {
            return 'Zone 1';
        }
        if ($miles <= 150) {
            return 'Zone 2';
        }
        if ($miles <= 300) {
            return 'Zone 3';
        }
        if ($miles <= 600) {
            return 'Zone 4';
        }
        if ($miles <= 1000) {
            return 'Zone 5';
        }
        if ($miles <= 1400) {
            return 'Zone 6';
        }
        if ($miles <= 1800) {
            return 'Zone 7';
        }

        return 'Zone 8';
    }

    private function haversineMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 3958.8; // miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUsZipPlace(string $zip): ?array
    {
        $cacheKey = 'shipping_pin_zone_us_'.$zip;

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($zip) {
            try {
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get('https://api.zippopotam.us/us/'.$zip);

                if (! $response->successful()) {
                    return null;
                }

                $places = $response->json('places');
                if (! is_array($places) || $places === []) {
                    return null;
                }

                return $places[0];
            } catch (\Throwable $e) {
                Log::warning('Shipping pin lookup failed', [
                    'zip' => $zip,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }
}
