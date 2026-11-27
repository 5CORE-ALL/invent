<?php

namespace App\Services;

use App\Support\TrackingCarrierGuesser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-carrier shipment tracking.
 *
 * Only USPS numbers are looked up, on the USPS API. There is no third-party aggregator.
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

    public const STATUS_LOST = 'LostInTransit';

    public const STATUS_PRESHIPMENT = 'Preshipment';

    /** shipment_status_detail when Scan Pending was saved with Select status (blank). */
    public const DETAIL_MANUAL_BLANK = 'Cleared manually on Scan Pending';

    /** Transient API failure — callers must NOT overwrite a real shipment_status with this. */
    public const STATUS_RATE_LIMITED = 'RateLimited';

    private int $timeout;

    public function __construct()
    {
        $cfg = config('services.tracking');
        $this->timeout = (int) ($cfg['http_timeout'] ?? 30);
    }

    public function isConfigured(): bool
    {
        return $this->hasUsps() || $this->hasUps() || $this->hasFedex() || $this->hasGofo() || $this->hasVeeqo();
    }

    public function hasGofo(): bool
    {
        return app(GofoExpressService::class)->isConfigured();
    }

    public function hasVeeqo(): bool
    {
        return app(VeeqoApiService::class)->isConfigured();
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
        return trim((string) config('services.fedex.tracking_client_id')) !== ''
            && trim((string) config('services.fedex.tracking_client_secret')) !== '';
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
        if (self::isUnusableProviderFailure((string) ($result['detail'] ?? ''))) {
            return false;
        }

        return true;
    }

    /** USPS MID / Tracking API Access Controls — not a real package exception. */
    public static function isUnusableProviderFailure(?string $detail): bool
    {
        $hay = strtolower(trim((string) $detail));
        if ($hay === '') {
            return false;
        }

        return str_contains($hay, 'not authorized')
            || str_contains($hay, 'tracking api access');
    }

    /**
     * Fetch normalized statuses for a batch of shipments. Each number is asked from its own carrier API
     * (USPS, FedEx, UPS, GOFO); any other carrier gets a blank (not saved) result.
     *
     * @param  array  $shipments  list of ['number' => string, 'carrier' => string|null]
     * @param  array  $options    kept for old callers, no longer used
     * @return array  map: trackingNumber => ['status' => string, 'detail' => string|null, 'provider' => string, ...]
     */
    public function track(array $shipments, array $options = []): array
    {
        if (! $this->isConfigured() || empty($shipments)) {
            return [];
        }

        $byProvider = ['usps' => [], 'fedex' => [], 'ups' => [], 'gofo' => [], 'other' => []];
        foreach ($shipments as $s) {
            $number = trim((string) ($s['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $carrier = trim((string) ($s['carrier'] ?? ''));
            $entry = ['number' => $number, 'carrier' => $carrier !== '' ? $carrier : null];
            $provider = $this->resolveProvider($number, $carrier);
            $byProvider[in_array($provider, ['usps', 'fedex', 'ups', 'gofo'], true) ? $provider : 'other'][] = $entry;
        }

        $out = [];
        if ($byProvider['usps'] !== []) {
            if ($this->hasUsps()) {
                $out += $this->trackUsps($byProvider['usps']);
            } else {
                foreach ($byProvider['usps'] as $s) {
                    $out[$s['number']] = $this->transientResult('usps', 'USPS API credentials are not configured', 'not_configured');
                }
            }
        }

        // FedEx (Wayfair shipments mostly go FedEx) is asked from the FedEx Track API.
        if ($byProvider['fedex'] !== []) {
            if ($this->hasFedex()) {
                $fedex = $this->trackFedex($byProvider['fedex']);
                foreach ($byProvider['fedex'] as $s) {
                    $out[$s['number']] = $fedex[$s['number']]
                        ?? $this->transientResult('fedex', 'FedEx API did not answer (check FEDEX_TRACKING_CLIENT_ID / SECRET)', 'fedex_unavailable');
                }
            } else {
                foreach ($byProvider['fedex'] as $s) {
                    $out[$s['number']] = $this->transientResult('fedex', 'FedEx API credentials are not configured', 'not_configured');
                }
            }
        }

        // UPS (1Z…) is asked from the UPS Track API.
        if ($byProvider['ups'] !== []) {
            if ($this->hasUps()) {
                $ups = $this->trackUps($byProvider['ups']);
                foreach ($byProvider['ups'] as $s) {
                    $out[$s['number']] = $ups[$s['number']]
                        ?? $this->transientResult('ups', 'UPS API did not answer (check UPS_CLIENT_ID / SECRET)', 'ups_unavailable');
                }
            } else {
                foreach ($byProvider['ups'] as $s) {
                    $out[$s['number']] = $this->transientResult('ups', 'UPS API credentials are not configured', 'not_configured');
                }
            }
        }

        // GOFO (GFUS…): GOFO's own track API, then the USPS / UPS last-mile number it reports.
        // Without this a scanned GOFO parcel stayed on "Label Created / No Scan" on the Scan Pending page.
        if ($byProvider['gofo'] !== []) {
            if ($this->hasGofo()) {
                $gofo = $this->trackGofo($byProvider['gofo']);
                foreach ($byProvider['gofo'] as $s) {
                    $out[$s['number']] = $gofo[$s['number']]
                        ?? $this->transientResult('gofo', 'GOFO API did not answer', 'gofo_unavailable');
                }
            } else {
                foreach ($byProvider['gofo'] as $s) {
                    $out[$s['number']] = $this->transientResult('gofo', 'GOFO API credentials are not configured', 'not_configured');
                }
            }
        }

        // Every other carrier is left blank, not stored as a status.
        foreach ($byProvider['other'] as $s) {
            $out[$s['number']] = $this->transientResult('none', 'Not looked up: carrier is not USPS, UPS, FedEx or GOFO', 'unsupported_carrier');
        }

        return $out;
    }

    public function uspsCanRequest(): bool
    {
        return $this->uspsRemainingThisHour() > 0;
    }

    /** How many native USPS tracking calls remain in the current hour budget. */
    public function uspsRemainingThisHour(): int
    {
        $unauthorizedUntil = Cache::get('usps_tracking_unauthorized_until');
        if ($unauthorizedUntil && now()->lt($unauthorizedUntil)) {
            return 0;
        }

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
        if ($provider === 'usps' && self::isUnusableProviderFailure($detail)) {
            $until = now()->addHours(12);
            if (! Cache::has('usps_tracking_unauthorized_until')) {
                Log::warning('ShipmentTracking: USPS tracking API is not authorized for this mailer — USPS lookups paused for 12 hours', [
                    'until' => $until->toIso8601String(),
                ]);
            }
            Cache::put('usps_tracking_unauthorized_until', $until, $until);
            $error = 'not_authorized';
        }

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
            // Veeqo is a shipping platform — resolve via tracking number when possible.
            if (str_contains($c, 'veeqo')) {
                $guess = $this->guessCarrierFromNumber($number);
                if ($guess === 'usps' || $guess === 'ups' || $guess === 'fedex' || $guess === 'gofo') {
                    return $guess;
                }

                return 'none';
            }
            if (str_contains($c, 'ups') && ! str_contains($c, 'usps')) {
                return 'ups';
            }
        }

        $guess = $this->guessCarrierFromNumber($number);
        if ($guess === 'usps' || $guess === 'ups' || $guess === 'fedex' || $guess === 'gofo') {
            return $guess;
        }

        return 'none';
    }

    protected function guessCarrierFromNumber(string $number): ?string
    {
        return TrackingCarrierGuesser::slugFromNumber($number);
    }

    // ── USPS ─────────────────────────────────────────────────────────────────

    /**
     * @param  list<array{number: string, carrier: ?string}>  $shipments
     * @return array<string, array{status: string, detail: ?string, provider: string}>
     */
    protected function trackUsps(array $shipments): array
    {
        // USPS_TRACKING_API=v3 switches back to the old one-number-per-call endpoint.
        if (strtolower((string) config('services.usps.tracking_api', 'v3r2')) === 'v3') {
            return $this->trackUspsLegacy($shipments);
        }

        $token = $this->uspsAccessToken();
        if ($token === null) {
            // Say why: this is the only lookup left, so a silent miss would look like a package with no status.
            $failed = [];
            foreach ($shipments as $s) {
                $failed[$s['number']] = $this->transientResult('usps', 'USPS OAuth login failed (check USPS_CONSUMER_KEY / SECRET)', 'oauth_failed');
            }

            return $failed;
        }

        $base = rtrim((string) config('services.usps.api_base', 'https://apis.usps.com'), '/');
        $out = [];
        $quotaHit = false;

        // Tracking API v3r2: POST /tracking/v3r2/tracking takes up to 35 numbers per request,
        // so one hourly quota unit now covers 35 packages instead of 1.
        foreach (array_chunk($shipments, 35) as $chunk) {
            if ($quotaHit || ! $this->uspsCanRequest()) {
                $denied = Cache::get('usps_tracking_unauthorized_until');
                $reason = $denied && now()->lt($denied)
                    ? 'USPS tracking API is not authorized for this mailer'
                    : 'USPS hourly quota reserved — skipped this run';
                foreach ($chunk as $s) {
                    $out[$s['number']] = $this->transientResult('usps', $reason);
                }

                continue;
            }

            $body = [];
            foreach ($chunk as $s) {
                $body[] = ['trackingNumber' => $s['number']];
            }

            try {
                $this->uspsRecordRequest();
                $resp = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout($this->timeout)
                    ->post($base.'/tracking/v3r2/tracking', $body);
            } catch (\Throwable $e) {
                Log::error('ShipmentTracking: USPS v3r2 request failed', ['error' => $e->getMessage()]);
                foreach ($chunk as $s) {
                    $out[$s['number']] = $this->transientResult('usps', $e->getMessage(), 'request_failed');
                }

                continue;
            }

            $json = $resp->json();
            $status = $resp->status();

            if ($status !== 200 && $status !== 207) {
                $errMsg = (string) (data_get($json, 'error.message') ?: mb_substr($resp->body(), 0, 240));
                Log::warning('ShipmentTracking: USPS v3r2 HTTP error', [
                    'status' => $status,
                    'count' => count($chunk),
                    'body' => mb_substr($resp->body(), 0, 400),
                ]);

                if ($this->isQuotaOrRateLimitError($status, $errMsg)) {
                    $this->uspsMarkQuotaExhausted($errMsg);
                    $quotaHit = true;
                }
                // A 404 on a batch says nothing about each package (one unknown number can fail the whole
                // request). Ask USPS one number at a time so every number gets its own answer.
                if ($status === 404 && count($chunk) > 1 && ! $quotaHit) {
                    $out = array_replace($out, $this->trackUspsLegacy($chunk));

                    continue;
                }

                foreach ($chunk as $s) {
                    // A 404 on a one-number request means that number is unknown to USPS.
                    // Anything else (401/403/400/5xx, or 404 on a batch) says nothing about
                    // the packages, so it must not be stored as a status.
                    if ($status === 404 && count($chunk) === 1) {
                        $out[$s['number']] = ['status' => self::STATUS_NOT_FOUND, 'detail' => $errMsg !== '' ? mb_substr($errMsg, 0, 480) : null, 'provider' => 'usps'];
                    } else {
                        $out[$s['number']] = $this->transientResult('usps', $errMsg !== '' ? $errMsg : 'USPS HTTP '.$status);
                    }
                }

                continue;
            }

            $byNumber = [];
            foreach ((array) $json as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $tn = strtoupper(preg_replace('/\s+/', '', (string) ($item['trackingNumber'] ?? '')) ?? '');
                if ($tn !== '') {
                    $byNumber[$tn] = $item;
                }
            }

            foreach ($chunk as $s) {
                $number = $s['number'];
                $item = $byNumber[strtoupper((string) preg_replace('/\s+/', '', $number))] ?? null;
                $out[$number] = $item === null
                    ? $this->transientResult('usps', 'USPS returned no entry for this number', 'missing')
                    : $this->uspsResultFromDetail($item);
            }
        }

        return $out;
    }

    /**
     * One element of a v3r2 response (TrackingDetail, or a FailureResponse inside a 207).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function uspsResultFromDetail(array $item): array
    {
        $code = (int) ($item['statusCode'] ?? 200);
        if ($code >= 400) {
            $msg = (string) (data_get($item, 'error.message') ?? data_get($item, 'error.errors.0.detail') ?? '');
            if ($code === 404) {
                return ['status' => self::STATUS_NOT_FOUND, 'detail' => $msg !== '' ? mb_substr($msg, 0, 480) : null, 'provider' => 'usps'];
            }

            return $this->transientResult('usps', $msg !== '' ? $msg : 'USPS error '.$code);
        }

        $category = (string) ($item['statusCategory'] ?? '');
        $summary = (string) ($item['statusSummary'] ?? '');
        $latest = (string) (data_get($item, 'trackingEvents.0.eventType') ?? '');
        $statusText = $category !== '' ? $category : (string) ($item['status'] ?? $latest);
        $detail = $summary !== '' ? $summary : $latest;

        return [
            'status' => $this->normalizeUspsStatus($statusText, $detail),
            'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : null,
            'provider' => 'usps',
        ];
    }

    /**
     * Old v3 endpoint: one request (and one quota unit) per tracking number.
     *
     * @param  list<array{number: string, carrier: ?string}>  $shipments
     * @return array<string, array<string, mixed>>
     */
    protected function trackUspsLegacy(array $shipments): array
    {
        $token = $this->uspsAccessToken();
        if ($token === null) {
            // Say why: this is the only lookup left, so a silent miss would look like a package with no status.
            $failed = [];
            foreach ($shipments as $s) {
                $failed[$s['number']] = $this->transientResult('usps', 'USPS OAuth login failed (check USPS_CONSUMER_KEY / SECRET)', 'oauth_failed');
            }

            return $failed;
        }

        $base = rtrim((string) config('services.usps.api_base', 'https://apis.usps.com'), '/');
        $minIntervalMs = max(0, (int) config('services.usps.min_interval_ms', 1200));
        $out = [];
        $quotaHit = false;

        foreach ($shipments as $s) {
            $number = $s['number'];

            if ($quotaHit || ! $this->uspsCanRequest()) {
                $denied = Cache::get('usps_tracking_unauthorized_until');
                $reason = $denied && now()->lt($denied)
                    ? 'USPS tracking API is not authorized for this mailer'
                    : 'USPS hourly quota reserved — skipped this run';
                $out[$number] = $this->transientResult('usps', $reason);
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

                    // 403 MID / Tracking API Access Controls is a credential problem, not
                    // a package exception. Do not persist it.
                    if ($resp->status() === 403 || self::isUnusableProviderFailure($errMsg)) {
                        $out[$number] = $this->transientResult(
                            'usps',
                            $errMsg !== '' ? $errMsg : 'USPS Tracking API not authorized'
                        );
                        continue;
                    }

                    $out[$number] = [
                        'status' => self::STATUS_NOT_FOUND,
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

        try {
            $resp = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($base.'/oauth2/v3/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $key,
                    'client_secret' => $secret,
                ]);
        } catch (\Throwable $e) {
            Log::warning('ShipmentTracking: USPS OAuth request failed', ['error' => $e->getMessage()]);

            return null;
        }

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

        try {
            $resp = Http::withBasicAuth($id, $secret)
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($base.'/security/v1/oauth/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (\Throwable $e) {
            Log::warning('ShipmentTracking: UPS OAuth request failed', ['error' => $e->getMessage()]);

            return null;
        }

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

        $base = rtrim((string) config('services.fedex.tracking_api_base', 'https://apis-sandbox.fedex.com'), '/');
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
        // Tracking uses its own FedEx project (FEDEX_TRACKING_*), falling back to FEDEX_*.
        $id = trim((string) config('services.fedex.tracking_client_id'));
        $secret = trim((string) config('services.fedex.tracking_client_secret'));
        $base = rtrim((string) config('services.fedex.tracking_api_base', 'https://apis-sandbox.fedex.com'), '/');

        // Keyed by project so a credential change never reuses the old project's token.
        $cacheKey = 'fedex_tracking_oauth_'.md5($id.'|'.$base);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        // A bad FEDEX_API_BASE (e.g. a broken .env line) must only lose FedEx lookups, not the whole batch.
        try {
            $resp = Http::asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($base.'/oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $id,
                    'client_secret' => $secret,
                ]);
        } catch (\Throwable $e) {
            Log::warning('ShipmentTracking: FedEx OAuth request failed', ['error' => $e->getMessage()]);

            return null;
        }

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
        Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    /**
     * Find a FedEx package by the PO / order number printed on the label (FedEx Track by Reference).
     * FedEx needs the destination ZIP and a ship date window. Wayfair POs are stored as the label's
     * PURCHASE_ORDER reference, so this finds shipments whose tracking number was never given to us.
     *
     * @return array{tracking: string, status: string, detail: ?string}|null
     */
    public function findFedexByReference(string $reference, string $postalCode, string $shipDateBegin, string $shipDateEnd, string $countryCode = 'US'): ?array
    {
        $reference = trim($reference);
        $zip = substr(preg_replace('/[^0-9A-Za-z]/', '', $postalCode) ?? '', 0, 5);
        if ($reference === '' || strlen($zip) < 5 || ! $this->hasFedex()) {
            return null;
        }
        $token = $this->fedexAccessToken();
        if ($token === null) {
            return null;
        }
        $base = rtrim((string) config('services.fedex.tracking_api_base', 'https://apis-sandbox.fedex.com'), '/');

        foreach (['PURCHASE_ORDER', 'FREE_FORM_REFERENCE'] as $type) {
            try {
                $resp = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout($this->timeout)
                    ->withHeaders(['x-locale' => 'en_US'])
                    ->post($base.'/track/v1/referencenumbers', [
                        'includeDetailedScans' => false,
                        'referencesInformation' => [
                            'type' => $type,
                            'value' => $reference,
                            'carrierCode' => 'FDXG',
                            'shipDateBegin' => $shipDateBegin,
                            'shipDateEnd' => $shipDateEnd,
                            'destinationPostalCode' => $zip,
                            'destinationCountryCode' => strtoupper(substr($countryCode !== '' ? $countryCode : 'US', 0, 2)),
                        ],
                    ]);
            } catch (\Throwable $e) {
                Log::info('ShipmentTracking: FedEx reference lookup failed', ['reference' => $reference, 'error' => $e->getMessage()]);

                return null;
            }

            if (! $resp->successful()) {
                continue;
            }

            foreach ((array) data_get($resp->json(), 'output.completeTrackResults', []) as $block) {
                foreach ((array) data_get($block, 'trackResults', []) as $result) {
                    if (! is_array($result) || (string) data_get($result, 'error.message', '') !== '') {
                        continue;
                    }
                    $tn = trim((string) data_get($result, 'trackingNumberInfo.trackingNumber', ''));
                    if (preg_match('/^\d{10,22}$/', $tn) !== 1) {
                        continue;
                    }
                    $code = (string) (data_get($result, 'latestStatusDetail.code') ?? data_get($result, 'latestStatusDetail.derivedCode') ?? '');
                    $desc = (string) (data_get($result, 'latestStatusDetail.description') ?? data_get($result, 'latestStatusDetail.statusByLocale') ?? '');

                    return [
                        'tracking' => $tn,
                        'status' => $this->normalizeFedexStatus($code, $desc),
                        'detail' => $desc !== '' ? mb_substr($desc, 0, 480) : null,
                    ];
                }
            }
        }

        return null;
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
        $lastMile = [];
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

            $events = $this->gofoEventList($res['data']);
            // The list is sometimes oldest-first, so the first row is only "Label Created"
            // while a later row is Delivered. Keep the furthest-along event.
            $bestStatus = self::STATUS_NOT_FOUND;
            $bestDetail = null;
            $bestRank = -1;
            $bestWhen = '';
            foreach ($events as $event) {
                if (! is_array($event)) {
                    continue;
                }
                $move = (string) ($event['operationMove'] ?? '');
                $en = (string) ($event['enContext'] ?? $event['pubEsContext'] ?? '');
                $status = $gofo->normalizeTrackStatus($move, $en);
                $rank = $this->shipmentStatusRank($status);
                $when = (string) ($event['operationTime'] ?? '');
                if ($rank < $bestRank || ($rank === $bestRank && $when !== '' && $bestWhen !== '' && $when < $bestWhen)) {
                    continue;
                }
                $loc = (string) ($event['location'] ?? '');
                $detail = trim(implode(' · ', array_filter([$en, $loc, $when])));
                $bestRank = $rank;
                $bestWhen = $when;
                $bestStatus = $status;
                $bestDetail = $detail !== '' ? mb_substr($detail, 0, 480) : null;
            }

            $out[$number] = [
                'status' => $bestStatus,
                'detail' => $bestDetail,
                'provider' => 'gofo',
                'events' => $events,
            ];

            // GOFO often stays on "label created" after USPS has already delivered the last mile.
            if ($bestRank < $this->shipmentStatusRank(self::STATUS_IN_TRANSIT)) {
                $mile = $this->gofoLastMileNumber($res['data'], $number);
                if ($mile !== null) {
                    $lastMile[$number] = $mile;
                }
            }
        }

        if ($lastMile !== [] && ($this->hasUsps() || $this->hasUps())) {
            $asked = [];
            foreach ($lastMile as $mile) {
                $asked[] = ['number' => $mile, 'carrier' => null];
            }
            try {
                $mileResults = $this->track($asked);
            } catch (\Throwable $e) {
                Log::warning('ShipmentTracking: GOFO last-mile lookup failed', ['error' => $e->getMessage()]);
                $mileResults = [];
            }
            foreach ($lastMile as $gofoNo => $mile) {
                $mileRes = null;
                foreach ($mileResults as $key => $value) {
                    if (strcasecmp((string) $key, $mile) === 0 && is_array($value)) {
                        $mileRes = $value;
                        break;
                    }
                }
                if (! self::isPersistableResult($mileRes)) {
                    continue;
                }
                if ($this->shipmentStatusRank((string) $mileRes['status']) <= $this->shipmentStatusRank((string) ($out[$gofoNo]['status'] ?? ''))) {
                    continue;
                }
                $out[$gofoNo]['status'] = (string) $mileRes['status'];
                $extra = trim((string) ($mileRes['detail'] ?? ''));
                $out[$gofoNo]['detail'] = mb_substr(trim($extra.' (last mile '.$mile.')'), 0, 480);
                $out[$gofoNo]['provider'] = 'gofo+carrier';
            }
        }

        return $out;
    }

    /** Higher means the package is further along. Delivered beats "Label Created". */
    protected function shipmentStatusRank(string $status): int
    {
        return match ($status) {
            self::STATUS_DELIVERED => 60,
            self::STATUS_OUT_FOR_DELIV, self::STATUS_PICKUP => 50,
            self::STATUS_IN_TRANSIT => 40,
            self::STATUS_FAILED, self::STATUS_EXCEPTION => 30,
            self::STATUS_INFO_RECEIVED, self::STATUS_PENDING => 20,
            self::STATUS_EXPIRED => 10,
            default => 0,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function gofoEventList(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }
        foreach (['trackList', 'tracks', 'events', 'list', 'trajectory', 'trajectoryList', 'details'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }

        return [$data];
    }

    /** A USPS/UPS number carried inside a GOFO track response, other than the GFUS waybill itself. */
    protected function gofoLastMileNumber(mixed $data, string $gofoNumber): ?string
    {
        $found = null;
        $walk = function (mixed $node) use (&$walk, &$found, $gofoNumber): void {
            if ($found !== null || ! is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $walk($value);

                    continue;
                }
                if (! is_scalar($value)) {
                    continue;
                }
                $tn = strtoupper((string) preg_replace('/\s+/', '', (string) $value));
                if ($tn === '' || strcasecmp($tn, $gofoNumber) === 0 || preg_match('/^GF[A-Z]{2,4}\d{8,}$/', $tn) === 1) {
                    continue;
                }
                $name = strtolower((string) $key);
                if ($name !== '' && preg_match('/url|time|date|status|context|move|code|msg/', $name) === 1) {
                    continue;
                }
                if (preg_match('/^1Z[A-Z0-9]{16}$/', $tn) === 1 || preg_match('/^9\d{15,25}$/', $tn) === 1) {
                    $found = $tn;

                    return;
                }
            }
        };
        $walk($data);

        return $found;
    }


}
