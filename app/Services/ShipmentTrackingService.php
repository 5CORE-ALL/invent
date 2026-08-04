<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-carrier shipment tracking.
 *
 * Prefer native USPS / UPS APIs when credentials are configured; fall back to
 * 17TRACK aggregator when available.
 */
class ShipmentTrackingService
{
    /** Normalized statuses (provider-agnostic). */
    public const STATUS_PENDING = 'Pending';

    public const STATUS_INFO_RECEIVED = 'InfoReceived';

    public const STATUS_IN_TRANSIT = 'InTransit';

    public const STATUS_OUT_FOR_DELIV = 'OutForDelivery';

    public const STATUS_PICKUP = 'AvailableForPickup';

    public const STATUS_DELIVERED = 'Delivered';

    public const STATUS_EXCEPTION = 'Exception';

    public const STATUS_FAILED = 'DeliveryFailure';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUS_NOT_FOUND = 'NotFound';

    private string $provider;

    private string $apiKey;

    private string $apiBase;

    private int $timeout;

    public function __construct()
    {
        $cfg = config('services.tracking');
        $this->provider = $cfg['provider'] ?? '17track';
        $this->apiKey = $cfg['api_key'] ?? '';
        $this->apiBase = rtrim($cfg['api_base'] ?? 'https://api.17track.net/track/v2.2', '/');
        $this->timeout = (int) ($cfg['http_timeout'] ?? 30);
    }

    public function isConfigured(): bool
    {
        return $this->has17Track() || $this->hasUsps() || $this->hasUps();
    }

    public function has17Track(): bool
    {
        return $this->apiKey !== '';
    }

    public function hasUsps(): bool
    {
        return trim((string) config('services.usps.consumer_key')) !== ''
            && trim((string) config('services.usps.consumer_secret')) !== '';
    }

    public function hasUps(): bool
    {
        return trim((string) config('services.ups.client_id')) !== ''
            && trim((string) config('services.ups.client_secret')) !== '';
    }

    /**
     * Fetch normalized statuses for a batch of shipments.
     *
     * @param  array  $shipments  list of ['number' => string, 'carrier' => string|null]
     * @return array  map: trackingNumber => ['status' => string, 'detail' => string|null, 'provider' => string]
     */
    public function track(array $shipments): array
    {
        if (! $this->isConfigured() || empty($shipments)) {
            return [];
        }

        $byProvider = [
            'usps' => [],
            'ups' => [],
            '17track' => [],
        ];

        foreach ($shipments as $s) {
            $number = trim((string) ($s['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $carrier = trim((string) ($s['carrier'] ?? ''));
            $provider = $this->resolveProvider($number, $carrier);
            $byProvider[$provider][] = [
                'number' => $number,
                'carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        $out = [];

        if (! empty($byProvider['usps']) && $this->hasUsps()) {
            $out += $this->trackUsps($byProvider['usps']);
        } elseif (! empty($byProvider['usps']) && $this->has17Track()) {
            $out += $this->track17Track($byProvider['usps']);
        }

        if (! empty($byProvider['ups']) && $this->hasUps()) {
            $out += $this->trackUps($byProvider['ups']);
        } elseif (! empty($byProvider['ups']) && $this->has17Track()) {
            $out += $this->track17Track($byProvider['ups']);
        }

        if (! empty($byProvider['17track'])) {
            if ($this->has17Track()) {
                $out += $this->track17Track($byProvider['17track']);
            } else {
                // No 17TRACK — try USPS/UPS by number pattern as last resort.
                foreach ($byProvider['17track'] as $s) {
                    $guess = $this->guessCarrierFromNumber($s['number']);
                    if ($guess === 'usps' && $this->hasUsps()) {
                        $out += $this->trackUsps([$s]);
                    } elseif ($guess === 'ups' && $this->hasUps()) {
                        $out += $this->trackUps([$s]);
                    } else {
                        $out[$s['number']] = [
                            'status' => self::STATUS_NOT_FOUND,
                            'detail' => 'No tracking provider configured for this carrier',
                            'provider' => 'none',
                        ];
                    }
                }
            }
        }

        return $out;
    }

    protected function resolveProvider(string $number, string $carrier): string
    {
        $c = strtolower($carrier);
        if ($c !== '') {
            if (str_contains($c, 'usps') || str_contains($c, 'postal')) {
                return 'usps';
            }
            if (str_contains($c, 'ups') && ! str_contains($c, 'usps')) {
                return 'ups';
            }
        }

        $guess = $this->guessCarrierFromNumber($number);
        if ($guess === 'usps' || $guess === 'ups') {
            return $guess;
        }

        return '17track';
    }

    protected function guessCarrierFromNumber(string $number): ?string
    {
        $n = strtoupper(preg_replace('/\s+/', '', $number) ?? '');
        if ($n === '') {
            return null;
        }
        if (str_starts_with($n, '1Z')) {
            return 'ups';
        }
        // Common USPS patterns: 94/93/92/95… (20–22 digits) or international letter+digits
        if (preg_match('/^(94|93|92|95|96|91)\d{18,22}$/', $n)) {
            return 'usps';
        }
        if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $n)) {
            return 'usps';
        }

        return null;
    }

    // ── USPS ─────────────────────────────────────────────────────────────────

    /**
     * @param  list<array{number: string, carrier: ?string}>  $shipments
     * @return array<string, array{status: string, detail: ?string, provider: string}>
     */
    protected function trackUsps(array $shipments): array
    {
        $token = $this->uspsAccessToken();
        if ($token === null) {
            return [];
        }

        $base = rtrim((string) config('services.usps.api_base', 'https://apis.usps.com'), '/');
        $out = [];

        foreach ($shipments as $s) {
            $number = $s['number'];
            try {
                $resp = Http::withToken($token)
                    ->acceptJson()
                    ->timeout($this->timeout)
                    ->get($base.'/tracking/v3/tracking/'.rawurlencode($number), [
                        'expand' => 'DETAIL',
                    ]);

                if ($resp->status() === 404) {
                    $out[$number] = [
                        'status' => self::STATUS_NOT_FOUND,
                        'detail' => null,
                        'provider' => 'usps',
                    ];

                    continue;
                }

                if (! $resp->successful()) {
                    $errMsg = (string) (data_get($resp->json(), 'error.message') ?: mb_substr($resp->body(), 0, 240));
                    Log::warning('ShipmentTracking: USPS HTTP error', [
                        'number' => $number,
                        'status' => $resp->status(),
                        'body' => mb_substr($resp->body(), 0, 400),
                    ]);
                    // 403 = MID not authorized for Tracking API Access Controls (not a missing package).
                    $out[$number] = [
                        'status' => $resp->status() === 403 ? self::STATUS_EXCEPTION : self::STATUS_NOT_FOUND,
                        'detail' => $errMsg !== '' ? mb_substr($errMsg, 0, 480) : ('USPS HTTP '.$resp->status()),
                        'provider' => 'usps',
                    ];

                    continue;
                }

                $json = $resp->json() ?? [];
                $statusCode = (string) (
                    data_get($json, 'statusCategory')
                    ?? data_get($json, 'status')
                    ?? data_get($json, 'TrackSummary.Event')
                    ?? data_get($json, 'trackingEvents.0.eventType')
                    ?? ''
                );
                $detail = (string) (
                    data_get($json, 'statusSummary')
                    ?? data_get($json, 'TrackSummary.Event')
                    ?? data_get($json, 'trackingEvents.0.eventType')
                    ?? data_get($json, 'trackingEvents.0.eventDescription')
                    ?? ''
                );

                $out[$number] = [
                    'status' => $this->normalizeUspsStatus($statusCode, $detail),
                    'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : null,
                    'provider' => 'usps',
                ];
            } catch (\Throwable $e) {
                Log::error('ShipmentTracking: USPS request failed', [
                    'number' => $number,
                    'error' => $e->getMessage(),
                ]);
                $out[$number] = [
                    'status' => self::STATUS_NOT_FOUND,
                    'detail' => $e->getMessage(),
                    'provider' => 'usps',
                ];
            }
        }

        return $out;
    }

    protected function uspsAccessToken(): ?string
    {
        $cached = Cache::get('usps_oauth_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $key = trim((string) config('services.usps.consumer_key'));
        $secret = trim((string) config('services.usps.consumer_secret'));
        $base = rtrim((string) config('services.usps.api_base', 'https://apis.usps.com'), '/');

        $resp = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->post($base.'/oauth2/v3/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $key,
                'client_secret' => $secret,
            ]);

        if (! $resp->successful()) {
            Log::warning('ShipmentTracking: USPS OAuth failed', [
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 400),
            ]);

            return null;
        }

        $token = trim((string) $resp->json('access_token'));
        if ($token === '') {
            return null;
        }

        Cache::put('usps_oauth_access_token', $token, now()->addHours(7));

        return $token;
    }

    protected function normalizeUspsStatus(string $status, string $detail = ''): string
    {
        $hay = strtolower(trim($status.' '.$detail));
        if ($hay === '') {
            return self::STATUS_PENDING;
        }
        if (str_contains($hay, 'delivered')) {
            return self::STATUS_DELIVERED;
        }
        if (str_contains($hay, 'out for delivery')) {
            return self::STATUS_OUT_FOR_DELIV;
        }
        if (str_contains($hay, 'available for pickup') || str_contains($hay, 'pickup')) {
            return self::STATUS_PICKUP;
        }
        if (str_contains($hay, 'alert') || str_contains($hay, 'exception') || str_contains($hay, 'return to sender')) {
            return self::STATUS_EXCEPTION;
        }
        if (str_contains($hay, 'pre-shipment') || str_contains($hay, 'label created') || str_contains($hay, 'acceptance')) {
            return self::STATUS_INFO_RECEIVED;
        }
        if (str_contains($hay, 'in transit') || str_contains($hay, 'departed') || str_contains($hay, 'arrived') || str_contains($hay, 'forwarded')) {
            return self::STATUS_IN_TRANSIT;
        }
        if (str_contains($hay, 'not found') || str_contains($hay, 'no record')) {
            return self::STATUS_NOT_FOUND;
        }

        return self::STATUS_IN_TRANSIT;
    }

    // ── UPS ──────────────────────────────────────────────────────────────────

    /**
     * @param  list<array{number: string, carrier: ?string}>  $shipments
     * @return array<string, array{status: string, detail: ?string, provider: string}>
     */
    protected function trackUps(array $shipments): array
    {
        $token = $this->upsAccessToken();
        if ($token === null) {
            return [];
        }

        $base = rtrim((string) config('services.ups.api_base', 'https://onlinetools.ups.com'), '/');
        $out = [];

        foreach ($shipments as $s) {
            $number = $s['number'];
            try {
                $resp = Http::withToken($token)
                    ->withHeaders([
                        'transId' => (string) now()->timestamp.substr((string) mt_rand(1000, 9999), 0),
                        'transactionSrc' => 'invent-sof',
                    ])
                    ->acceptJson()
                    ->timeout($this->timeout)
                    ->get($base.'/api/track/v1/details/'.rawurlencode($number), [
                        'locale' => 'en_US',
                    ]);

                if ($resp->status() === 404) {
                    $out[$number] = [
                        'status' => self::STATUS_NOT_FOUND,
                        'detail' => null,
                        'provider' => 'ups',
                    ];

                    continue;
                }

                if (! $resp->successful()) {
                    Log::warning('ShipmentTracking: UPS HTTP error', [
                        'number' => $number,
                        'status' => $resp->status(),
                        'body' => mb_substr($resp->body(), 0, 400),
                    ]);
                    $out[$number] = [
                        'status' => self::STATUS_NOT_FOUND,
                        'detail' => 'UPS HTTP '.$resp->status(),
                        'provider' => 'ups',
                    ];

                    continue;
                }

                $pkg = data_get($resp->json(), 'trackResponse.shipment.0.package.0', []);
                $statusDesc = (string) (
                    data_get($pkg, 'currentStatus.description')
                    ?? data_get($pkg, 'currentStatus.statusCode')
                    ?? data_get($pkg, 'activity.0.status.description')
                    ?? ''
                );
                $code = (string) (
                    data_get($pkg, 'currentStatus.code')
                    ?? data_get($pkg, 'currentStatus.statusCode')
                    ?? ''
                );

                $out[$number] = [
                    'status' => $this->normalizeUpsStatus($code, $statusDesc),
                    'detail' => $statusDesc !== '' ? mb_substr($statusDesc, 0, 480) : null,
                    'provider' => 'ups',
                ];
            } catch (\Throwable $e) {
                Log::error('ShipmentTracking: UPS request failed', [
                    'number' => $number,
                    'error' => $e->getMessage(),
                ]);
                $out[$number] = [
                    'status' => self::STATUS_NOT_FOUND,
                    'detail' => $e->getMessage(),
                    'provider' => 'ups',
                ];
            }
        }

        return $out;
    }

    protected function upsAccessToken(): ?string
    {
        $cached = Cache::get('ups_oauth_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $id = trim((string) config('services.ups.client_id'));
        $secret = trim((string) config('services.ups.client_secret'));
        $base = rtrim((string) config('services.ups.api_base', 'https://onlinetools.ups.com'), '/');

        $resp = Http::withBasicAuth($id, $secret)
            ->asForm()
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($base.'/security/v1/oauth/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $resp->successful()) {
            Log::warning('ShipmentTracking: UPS OAuth failed', [
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 400),
            ]);

            return null;
        }

        $token = trim((string) $resp->json('access_token'));
        if ($token === '') {
            return null;
        }

        Cache::put('ups_oauth_access_token', $token, now()->addHours(3));

        return $token;
    }

    protected function normalizeUpsStatus(string $code, string $description): string
    {
        $hay = strtolower(trim($code.' '.$description));
        if ($hay === '') {
            return self::STATUS_PENDING;
        }
        if (str_contains($hay, 'delivered') || $code === 'D' || $code === 'FS') {
            return self::STATUS_DELIVERED;
        }
        if (str_contains($hay, 'out for delivery') || $code === 'OD') {
            return self::STATUS_OUT_FOR_DELIV;
        }
        if (str_contains($hay, 'pickup') || str_contains($hay, 'available for pickup')) {
            return self::STATUS_PICKUP;
        }
        if (str_contains($hay, 'exception') || str_contains($hay, 'delay') || str_contains($hay, 'return')) {
            return self::STATUS_EXCEPTION;
        }
        if (str_contains($hay, 'order processed') || str_contains($hay, 'label created') || str_contains($hay, 'billing information')) {
            return self::STATUS_INFO_RECEIVED;
        }
        if (str_contains($hay, 'in transit') || str_contains($hay, 'departed') || str_contains($hay, 'arrived') || $code === 'I' || $code === 'P' || $code === 'M') {
            return self::STATUS_IN_TRANSIT;
        }
        if (str_contains($hay, 'not found')) {
            return self::STATUS_NOT_FOUND;
        }

        return self::STATUS_IN_TRANSIT;
    }

    // ── 17TRACK ──────────────────────────────────────────────────────────────

    private function track17Track(array $shipments): array
    {
        $payload = [];
        foreach ($shipments as $s) {
            $number = trim((string) ($s['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $item = ['number' => $number];
            $code = $this->carrierCode17Track($s['carrier'] ?? null);
            if ($code !== null) {
                $item['carrier'] = $code;
            }
            $payload[] = $item;
        }

        if (empty($payload)) {
            return [];
        }

        $this->post17Track('/register', $payload);
        $resp = $this->post17Track('/gettrackinfo', $payload);

        $out = [];
        $accepted = data_get($resp, 'data.accepted', []);
        foreach ($accepted as $row) {
            $number = (string) data_get($row, 'number', '');
            if ($number === '') {
                continue;
            }
            $rawStatus = (string) data_get($row, 'track_info.latest_status.status', '');
            $detail = (string) (data_get($row, 'track_info.latest_event.description')
                ?? data_get($row, 'track_info.latest_status.sub_status')
                ?? '');

            $out[$number] = [
                'status' => $this->normalize17TrackStatus($rawStatus),
                'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : null,
                'provider' => '17track',
            ];
        }

        $rejected = data_get($resp, 'data.rejected', []);
        foreach ($rejected as $row) {
            $number = (string) data_get($row, 'number', '');
            if ($number !== '' && ! isset($out[$number])) {
                $out[$number] = [
                    'status' => self::STATUS_NOT_FOUND,
                    'detail' => null,
                    'provider' => '17track',
                ];
            }
        }

        return $out;
    }

    private function post17Track(string $path, array $body): array
    {
        try {
            $response = Http::withHeaders([
                '17token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post($this->apiBase.$path, $body);

            if (! $response->successful()) {
                Log::warning('ShipmentTracking: 17track HTTP error', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return [];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('ShipmentTracking: 17track request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function carrierCode17Track(?string $carrier): ?int
    {
        $c = strtolower(trim((string) $carrier));
        if ($c === '') {
            return null;
        }

        $map = [
            'usps' => 21051,
            'united states postal service' => 21051,
            'ups' => 100002,
            'fedex' => 100003,
            'fedex express' => 100003,
            'fedex ground' => 100003,
            'gofo' => 100996,
            'gofo express' => 100996,
        ];

        foreach ($map as $needle => $code) {
            if ($c === $needle || str_contains($c, $needle)) {
                return $code;
            }
        }

        return null;
    }

    private function normalize17TrackStatus(string $status): string
    {
        $known = [
            'NotFound' => self::STATUS_NOT_FOUND,
            'InfoReceived' => self::STATUS_INFO_RECEIVED,
            'InTransit' => self::STATUS_IN_TRANSIT,
            'Expired' => self::STATUS_EXPIRED,
            'AvailableForPickup' => self::STATUS_PICKUP,
            'OutForDelivery' => self::STATUS_OUT_FOR_DELIV,
            'DeliveryFailure' => self::STATUS_FAILED,
            'Delivered' => self::STATUS_DELIVERED,
            'Exception' => self::STATUS_EXCEPTION,
        ];

        return $known[$status] ?? ($status !== '' ? $status : self::STATUS_PENDING);
    }
}
