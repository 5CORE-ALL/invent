<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-carrier shipment tracking.
 *
 * Prefer native USPS / UPS / FedEx / GOFO APIs when credentials are configured; fall back to
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

    /** Transient API failure — callers must NOT overwrite a real shipment_status with this. */
    public const STATUS_RATE_LIMITED = 'RateLimited';

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
        return $this->has17Track() || $this->hasUsps() || $this->hasUps() || $this->hasFedex() || $this->hasGofo();
    }

    public function hasGofo(): bool
    {
        return app(GofoExpressService::class)->isConfigured();
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

    public function hasFedex(): bool
    {
        return trim((string) config('services.fedex.client_id')) !== ''
            && trim((string) config('services.fedex.client_secret')) !== '';
    }

    /**
     * Whether a track() result is safe to persist as shipment_status.
     * Quota / rate-limit / empty results must not overwrite real carrier status.
     */
    public static function isPersistableResult(?array $result): bool
    {
        if ($result === null || empty($result['status'])) {
            return false;
        }
        if (! empty($result['transient'])) {
            return false;
        }
        if (($result['status'] ?? '') === self::STATUS_RATE_LIMITED) {
            return false;
        }

        return true;
    }

    /**
     * Fetch normalized statuses for a batch of shipments.
     *
     * @param  array  $shipments  list of ['number' => string, 'carrier' => string|null]
     * @param  array  $options    prefer_native=true forces USPS/UPS/FedEx over 17TRACK
     * @return array  map: trackingNumber => ['status' => string, 'detail' => string|null, 'provider' => string, ...]
     */
    public function track(array $shipments, array $options = []): array
    {
        if (! $this->isConfigured() || empty($shipments)) {
            return [];
        }

        $normalized = [];
        foreach ($shipments as $s) {
            $number = trim((string) ($s['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $carrier = trim((string) ($s['carrier'] ?? ''));
            $normalized[] = [
                'number' => $number,
                'carrier' => $carrier !== '' ? $carrier : null,
            ];
        }
        if ($normalized === []) {
            return [];
        }

        $preferNative = (bool) ($options['prefer_native'] ?? false);
        $preferAggregator = (bool) config('services.tracking.prefer_aggregator', true);

        // Bulk / scheduled path: 17TRACK batches up to 40 and avoids burning USPS hourly quota.
        // GOFO still uses the native Open API when configured (carrier name contains "gofo").
        if ($this->has17Track() && $preferAggregator && ! $preferNative) {
            $gofo = [];
            $rest = [];
            foreach ($normalized as $s) {
                if ($this->resolveProvider($s['number'], (string) ($s['carrier'] ?? '')) === 'gofo' && $this->hasGofo()) {
                    $gofo[] = $s;
                } else {
                    $rest[] = $s;
                }
            }
            $out = [];
            if ($gofo !== []) {
                $out += $this->trackGofo($gofo);
            }
            if ($rest !== []) {
                $out += $this->track17Track($rest);
            }

            return $out;
        }

        $byProvider = [
            'usps' => [],
            'ups' => [],
            'fedex' => [],
            'gofo' => [],
            '17track' => [],
        ];

        foreach ($normalized as $s) {
            $provider = $this->resolveProvider($s['number'], (string) ($s['carrier'] ?? ''));
            $byProvider[$provider][] = $s;
        }

        $out = [];

        if (! empty($byProvider['usps'])) {
            if ($this->hasUsps() && $this->uspsCanRequest()) {
                $uspsOut = $this->trackUsps($byProvider['usps']);
                $out += $uspsOut;
                $retry = $this->numbersNeedingAggregatorFallback($byProvider['usps'], $uspsOut);
                if ($retry !== [] && $this->has17Track()) {
                    $out = array_replace($out, $this->track17Track($retry));
                }
            } elseif ($this->has17Track()) {
                $out += $this->track17Track($byProvider['usps']);
            }
        }

        if (! empty($byProvider['ups'])) {
            if ($this->hasUps()) {
                $upsOut = $this->trackUps($byProvider['ups']);
                $out += $upsOut;
                $retry = $this->numbersNeedingAggregatorFallback($byProvider['ups'], $upsOut);
                if ($retry !== [] && $this->has17Track()) {
                    $out = array_replace($out, $this->track17Track($retry));
                }
            } elseif ($this->has17Track()) {
                $out += $this->track17Track($byProvider['ups']);
            }
        }

        if (! empty($byProvider['fedex'])) {
            if ($this->hasFedex()) {
                $out += $this->trackFedex($byProvider['fedex']);
            } elseif ($this->has17Track()) {
                $out += $this->track17Track($byProvider['fedex']);
            }
        }

        if (! empty($byProvider['gofo'])) {
            if ($this->hasGofo()) {
                $gofoOut = $this->trackGofo($byProvider['gofo']);
                $out += $gofoOut;
                $retry = $this->numbersNeedingAggregatorFallback($byProvider['gofo'], $gofoOut);
                if ($retry !== [] && $this->has17Track()) {
                    $out = array_replace($out, $this->track17Track($retry));
                }
            } elseif ($this->has17Track()) {
                $out += $this->track17Track($byProvider['gofo']);
            }
        }

        if (! empty($byProvider['17track'])) {
            if ($this->has17Track()) {
                $out += $this->track17Track($byProvider['17track']);
            } else {
                foreach ($byProvider['17track'] as $s) {
                    $guess = $this->guessCarrierFromNumber($s['number']);
                    if ($guess === 'usps' && $this->hasUsps()) {
                        $out += $this->trackUsps([$s]);
                    } elseif ($guess === 'ups' && $this->hasUps()) {
                        $out += $this->trackUps([$s]);
                    } elseif ($guess === 'fedex' && $this->hasFedex()) {
                        $out += $this->trackFedex([$s]);
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

    /**
     * @param  list<array{number: string, carrier: ?string}>  $requested
     * @param  array<string, array<string, mixed>>  $results
     * @return list<array{number: string, carrier: ?string}>
     */
    protected function numbersNeedingAggregatorFallback(array $requested, array $results): array
    {
        $retry = [];
        foreach ($requested as $s) {
            $res = $results[$s['number']] ?? null;
            if ($res === null || ! empty($res['transient']) || ($res['status'] ?? '') === self::STATUS_RATE_LIMITED) {
                $retry[] = $s;
            }
        }

        return $retry;
    }

    public function uspsCanRequest(): bool
    {
        return $this->uspsRemainingThisHour() > 0;
    }

    /** How many native USPS tracking calls remain in the current hour budget. */
    public function uspsRemainingThisHour(): int
    {
        $blockedUntil = Cache::get('usps_tracking_quota_exhausted_until');
        if ($blockedUntil && now()->lt($blockedUntil)) {
            return 0;
        }

        $max = max(1, (int) config('services.usps.max_per_hour', 55));
        $key = 'usps_tracking_requests_'.now()->format('YmdH');
        $used = (int) Cache::get($key, 0);

        return max(0, $max - $used);
    }

    protected function uspsRecordRequest(): void
    {
        $key = 'usps_tracking_requests_'.now()->format('YmdH');
        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->copy()->endOfHour()->addMinute());
        }
        Cache::increment($key);
    }

    protected function uspsMarkQuotaExhausted(?string $detail = null): void
    {
        $until = now()->addMinutes(55);
        Cache::put('usps_tracking_quota_exhausted_until', $until, $until);
        Log::warning('ShipmentTracking: USPS quota exhausted — pausing native USPS calls', [
            'until' => $until->toIso8601String(),
            'detail' => $detail,
        ]);
    }

    protected function isQuotaOrRateLimitError(int $httpStatus, string $message): bool
    {
        if (in_array($httpStatus, [429, 503], true)) {
            return true;
        }
        $hay = strtolower($message);

        return str_contains($hay, 'quota')
            || str_contains($hay, 'rate limit')
            || str_contains($hay, 'too many requests')
            || str_contains($hay, 'throttl');
    }

    protected function transientResult(string $provider, string $detail, string $error = 'quota_exceeded'): array
    {
        return [
            'status' => self::STATUS_RATE_LIMITED,
            'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : 'Provider rate/quota limit reached',
            'provider' => $provider,
            'transient' => true,
            'error' => $error,
        ];
    }

    protected function resolveProvider(string $number, string $carrier): string
    {
        $c = strtolower($carrier);
        if ($c !== '') {
            if (str_contains($c, 'usps') || str_contains($c, 'postal')) {
                return 'usps';
            }
            if (str_contains($c, 'fedex') || str_contains($c, 'federal express')) {
                return 'fedex';
            }
            if (str_contains($c, 'gofo')) {
                return 'gofo';
            }
            if (str_contains($c, 'ups') && ! str_contains($c, 'usps')) {
                return 'ups';
            }
        }

        $guess = $this->guessCarrierFromNumber($number);
        if ($guess === 'usps' || $guess === 'ups' || $guess === 'fedex' || $guess === 'gofo') {
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
        // FedEx door-tag / express-ish: 12 digits, or 15 digits starting with 96
        if (preg_match('/^\d{12}$/', $n) === 1 || preg_match('/^96\d{13}$/', $n) === 1) {
            return 'fedex';
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
        $minIntervalMs = max(0, (int) config('services.usps.min_interval_ms', 1200));
        $out = [];
        $quotaHit = false;

        foreach ($shipments as $s) {
            $number = $s['number'];

            if ($quotaHit || ! $this->uspsCanRequest()) {
                $out[$number] = $this->transientResult('usps', 'USPS hourly quota reserved — skipped this run');
                continue;
            }

            try {
                $this->uspsRecordRequest();
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
                    if ($minIntervalMs > 0) {
                        usleep($minIntervalMs * 1000);
                    }

                    continue;
                }

                if (! $resp->successful()) {
                    $errMsg = (string) (data_get($resp->json(), 'error.message') ?: mb_substr($resp->body(), 0, 240));
                    Log::warning('ShipmentTracking: USPS HTTP error', [
                        'number' => $number,
                        'status' => $resp->status(),
                        'body' => mb_substr($resp->body(), 0, 400),
                    ]);

                    if ($this->isQuotaOrRateLimitError($resp->status(), $errMsg)) {
                        $this->uspsMarkQuotaExhausted($errMsg);
                        $quotaHit = true;
                        $out[$number] = $this->transientResult('usps', $errMsg);

                        continue;
                    }

                    // 403 = MID not authorized for Tracking API Access Controls (not a missing package).
                    $out[$number] = [
                        'status' => $resp->status() === 403 ? self::STATUS_EXCEPTION : self::STATUS_NOT_FOUND,
                        'detail' => $errMsg !== '' ? mb_substr($errMsg, 0, 480) : ('USPS HTTP '.$resp->status()),
                        'provider' => 'usps',
                    ];
                    if ($minIntervalMs > 0) {
                        usleep($minIntervalMs * 1000);
                    }

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
                if ($minIntervalMs > 0) {
                    usleep($minIntervalMs * 1000);
                }
            } catch (\Throwable $e) {
                Log::error('ShipmentTracking: USPS request failed', [
                    'number' => $number,
                    'error' => $e->getMessage(),
                ]);
                $out[$number] = [
                    'status' => self::STATUS_NOT_FOUND,
                    'detail' => $e->getMessage(),
                    'provider' => 'usps',
                    'transient' => true,
                    'error' => 'request_failed',
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
                    $errMsg = (string) (data_get($resp->json(), 'response.errors.0.message')
                        ?: ('UPS HTTP '.$resp->status()));
                    Log::warning('ShipmentTracking: UPS HTTP error', [
                        'number' => $number,
                        'status' => $resp->status(),
                        'body' => mb_substr($resp->body(), 0, 400),
                    ]);
                    if ($this->isQuotaOrRateLimitError($resp->status(), $errMsg)) {
                        $out[$number] = $this->transientResult('ups', $errMsg);

                        continue;
                    }
                    $out[$number] = [
                        'status' => self::STATUS_NOT_FOUND,
                        'detail' => mb_substr($errMsg, 0, 480),
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

    // ── FedEx ────────────────────────────────────────────────────────────────

    /**
     * @param  list<array{number: string, carrier: ?string}>  $shipments
     * @return array<string, array{status: string, detail: ?string, provider: string}>
     */
    protected function trackFedex(array $shipments): array
    {
        $token = $this->fedexAccessToken();
        if ($token === null) {
            return [];
        }

        $base = rtrim((string) config('services.fedex.api_base', 'https://apis-sandbox.fedex.com'), '/');
        $out = [];

        // FedEx Track API accepts batches; keep chunks small for reliability.
        foreach (array_chunk($shipments, 30) as $chunk) {
            $trackingInfo = [];
            foreach ($chunk as $s) {
                $trackingInfo[] = [
                    'trackingNumberInfo' => [
                        'trackingNumber' => $s['number'],
                    ],
                ];
            }

            try {
                $resp = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout($this->timeout)
                    ->withHeaders([
                        'x-locale' => 'en_US',
                    ])
                    ->post($base.'/track/v1/trackingnumbers', [
                        'includeDetailedScans' => true,
                        'trackingInfo' => $trackingInfo,
                    ]);

                if (! $resp->successful()) {
                    Log::warning('ShipmentTracking: FedEx HTTP error', [
                        'status' => $resp->status(),
                        'body' => mb_substr($resp->body(), 0, 400),
                    ]);
                    foreach ($chunk as $s) {
                        $out[$s['number']] = [
                            'status' => self::STATUS_NOT_FOUND,
                            'detail' => 'FedEx HTTP '.$resp->status(),
                            'provider' => 'fedex',
                        ];
                    }

                    continue;
                }

                $complete = data_get($resp->json(), 'output.completeTrackResults', []);
                if (! is_array($complete)) {
                    $complete = [];
                }

                $byNumber = [];
                foreach ($complete as $block) {
                    $tn = trim((string) data_get($block, 'trackingNumber', ''));
                    $result = data_get($block, 'trackResults.0', []);
                    if (! is_array($result)) {
                        $result = [];
                    }
                    if ($tn === '') {
                        $tn = trim((string) data_get($result, 'trackingNumberInfo.trackingNumber', ''));
                    }
                    if ($tn === '') {
                        continue;
                    }
                    $byNumber[$tn] = $result;
                }

                foreach ($chunk as $s) {
                    $number = $s['number'];
                    $result = $byNumber[$number] ?? null;
                    if ($result === null) {
                        $out[$number] = [
                            'status' => self::STATUS_NOT_FOUND,
                            'detail' => null,
                            'provider' => 'fedex',
                        ];

                        continue;
                    }

                    $errMsg = (string) (data_get($result, 'error.message') ?? '');
                    if ($errMsg !== '') {
                        $out[$number] = [
                            'status' => self::STATUS_NOT_FOUND,
                            'detail' => mb_substr($errMsg, 0, 480),
                            'provider' => 'fedex',
                        ];

                        continue;
                    }

                    $code = (string) (
                        data_get($result, 'latestStatusDetail.code')
                        ?? data_get($result, 'latestStatusDetail.derivedCode')
                        ?? data_get($result, 'scanEvents.0.derivedStatusCode')
                        ?? ''
                    );
                    $statusDesc = (string) (
                        data_get($result, 'latestStatusDetail.description')
                        ?? data_get($result, 'latestStatusDetail.statusByLocale')
                        ?? data_get($result, 'scanEvents.0.eventDescription')
                        ?? data_get($result, 'scanEvents.0.derivedStatus')
                        ?? ''
                    );

                    $out[$number] = [
                        'status' => $this->normalizeFedexStatus($code, $statusDesc),
                        'detail' => $statusDesc !== '' ? mb_substr($statusDesc, 0, 480) : null,
                        'provider' => 'fedex',
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('ShipmentTracking: FedEx request failed', [
                    'error' => $e->getMessage(),
                ]);
                foreach ($chunk as $s) {
                    $out[$s['number']] = [
                        'status' => self::STATUS_NOT_FOUND,
                        'detail' => $e->getMessage(),
                        'provider' => 'fedex',
                    ];
                }
            }
        }

        return $out;
    }

    protected function fedexAccessToken(): ?string
    {
        $cached = Cache::get('fedex_oauth_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $id = trim((string) config('services.fedex.client_id'));
        $secret = trim((string) config('services.fedex.client_secret'));
        $base = rtrim((string) config('services.fedex.api_base', 'https://apis-sandbox.fedex.com'), '/');

        $resp = Http::asForm()
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($base.'/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $id,
                'client_secret' => $secret,
            ]);

        if (! $resp->successful()) {
            Log::warning('ShipmentTracking: FedEx OAuth failed', [
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 400),
            ]);

            return null;
        }

        $token = trim((string) $resp->json('access_token'));
        if ($token === '') {
            return null;
        }

        $expiresIn = (int) ($resp->json('expires_in') ?: 3600);
        Cache::put('fedex_oauth_access_token', $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    protected function normalizeFedexStatus(string $code, string $description): string
    {
        $code = strtoupper(trim($code));
        $hay = strtolower(trim($code.' '.$description));
        if ($hay === '') {
            return self::STATUS_PENDING;
        }
        if ($code === 'DL' || str_contains($hay, 'delivered')) {
            return self::STATUS_DELIVERED;
        }
        if ($code === 'OD' || str_contains($hay, 'out for delivery')) {
            return self::STATUS_OUT_FOR_DELIV;
        }
        if ($code === 'AF' || str_contains($hay, 'available for pickup') || str_contains($hay, 'hold at location')) {
            return self::STATUS_PICKUP;
        }
        if (in_array($code, ['DE', 'SE', 'CA'], true)
            || str_contains($hay, 'exception')
            || str_contains($hay, 'delay')
            || str_contains($hay, 'delivery exception')) {
            return self::STATUS_EXCEPTION;
        }
        if ($code === 'OC' || str_contains($hay, 'shipment information sent') || str_contains($hay, 'label created')) {
            return self::STATUS_INFO_RECEIVED;
        }
        if (in_array($code, ['IT', 'IX', 'AR', 'DP', 'PU', 'PX'], true)
            || str_contains($hay, 'in transit')
            || str_contains($hay, 'picked up')
            || str_contains($hay, 'departed')
            || str_contains($hay, 'arrived')) {
            return self::STATUS_IN_TRANSIT;
        }
        if (str_contains($hay, 'not found')) {
            return self::STATUS_NOT_FOUND;
        }

        return self::STATUS_IN_TRANSIT;
    }

    // ── GOFO Express ─────────────────────────────────────────────────────────

    /**
     * @param  list<array{number: string, carrier: ?string}>  $shipments
     * @return array<string, array{status: string, detail: ?string, provider: string}>
     */
    protected function trackGofo(array $shipments): array
    {
        /** @var GofoExpressService $gofo */
        $gofo = app(GofoExpressService::class);
        if (! $gofo->isConfigured()) {
            return [];
        }

        $out = [];
        $total = count($shipments);
        $i = 0;
        foreach ($shipments as $s) {
            $number = trim((string) ($s['number'] ?? ''));
            if ($number === '') {
                continue;
            }

            $i++;
            // GOFO is 1 HTTP call per number — surface progress in artisan so runs don't look hung.
            if (app()->runningInConsole() && ($i === 1 || $i === $total || $i % 10 === 0)) {
                fwrite(STDERR, "    GOFO track {$i}/{$total}: {$number}\n");
            }

            $res = $gofo->track($number);
            $code = (int) ($res['code'] ?? 0);

            if ($code === 305 || ($code === 200 && empty($res['data']))) {
                $out[$number] = [
                    'status' => self::STATUS_NOT_FOUND,
                    'detail' => (string) ($res['msgEn'] ?? $res['msg'] ?? 'No data found'),
                    'provider' => 'gofo',
                ];
                continue;
            }

            if ($code === 401) {
                $out[$number] = $this->transientResult('gofo', (string) ($res['msgEn'] ?? 'GOFO auth failed'));
                continue;
            }

            if ($code !== 200 || ! is_array($res['data'] ?? null)) {
                $out[$number] = [
                    'status' => self::STATUS_NOT_FOUND,
                    'detail' => (string) ($res['msgEn'] ?? $res['msg'] ?? 'GOFO track failed'),
                    'provider' => 'gofo',
                ];
                continue;
            }

            $events = $res['data'];
            // API returns newest-first in samples; take first event as current.
            $latest = is_array($events) && array_is_list($events) ? ($events[0] ?? []) : $events;
            if (! is_array($latest)) {
                $latest = [];
            }

            $move = (string) ($latest['operationMove'] ?? '');
            $en = (string) ($latest['enContext'] ?? $latest['pubEsContext'] ?? '');
            $when = (string) ($latest['operationTime'] ?? '');
            $loc = (string) ($latest['location'] ?? '');
            $detail = trim(implode(' · ', array_filter([$en, $loc, $when])));

            $out[$number] = [
                'status' => $gofo->normalizeTrackStatus($move, $en),
                'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : null,
                'provider' => 'gofo',
                'events' => is_array($events) && array_is_list($events) ? $events : [$latest],
            ];
        }

        return $out;
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
            if ($number === '' || isset($out[$number])) {
                continue;
            }
            $errMsg = (string) (
                data_get($row, 'error.message')
                ?? data_get($row, 'message')
                ?? data_get($row, 'error')
                ?? ''
            );
            if ($this->isQuotaOrRateLimitError(0, $errMsg) || str_contains(strtolower($errMsg), 'ran out')) {
                $out[$number] = $this->transientResult('17track', $errMsg !== '' ? $errMsg : '17TRACK quota exhausted');

                continue;
            }
            $out[$number] = [
                'status' => self::STATUS_NOT_FOUND,
                'detail' => $errMsg !== '' ? mb_substr($errMsg, 0, 480) : null,
                'provider' => '17track',
            ];
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
