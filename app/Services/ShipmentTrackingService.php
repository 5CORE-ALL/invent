<?php

namespace App\Services;

use App\Support\TrackingCarrierGuesser;
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
        return $this->has17Track() || $this->hasUsps() || $this->hasUps() || $this->hasFedex() || $this->hasGofo() || $this->hasVeeqo();
    }

    public function hasGofo(): bool
    {
        return app(GofoExpressService::class)->isConfigured();
    }

    public function hasVeeqo(): bool
    {
        return app(VeeqoApiService::class)->isConfigured();
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
                $out = $this->retryNatively($rest, $out);
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
                if ($retry !== [] && $this->canAggregate()) {
                    $out = array_replace($out, $this->track17Track($retry));
                }
            } elseif ($this->canAggregate()) {
                $out += $this->track17Track($byProvider['usps']);
            }
        }

        if (! empty($byProvider['ups'])) {
            if ($this->hasUps()) {
                $upsOut = $this->trackUps($byProvider['ups']);
                $out += $upsOut;
                $retry = $this->numbersNeedingAggregatorFallback($byProvider['ups'], $upsOut);
                if ($retry !== [] && $this->canAggregate()) {
                    $out = array_replace($out, $this->track17Track($retry));
                }
            } elseif ($this->canAggregate()) {
                $out += $this->track17Track($byProvider['ups']);
            }
        }

        if (! empty($byProvider['fedex'])) {
            if ($this->hasFedex()) {
                $out += $this->trackFedex($byProvider['fedex']);
            } elseif ($this->canAggregate()) {
                $out += $this->track17Track($byProvider['fedex']);
            }
        }

        if (! empty($byProvider['gofo'])) {
            if ($this->hasGofo()) {
                $gofoOut = $this->trackGofo($byProvider['gofo']);
                $out += $gofoOut;
                $retry = $this->numbersNeedingAggregatorFallback($byProvider['gofo'], $gofoOut);
                if ($retry !== [] && $this->canAggregate()) {
                    $out = array_replace($out, $this->track17Track($retry));
                }
            } elseif ($this->canAggregate()) {
                $out += $this->track17Track($byProvider['gofo']);
            }
        }

        if (! empty($byProvider['17track'])) {
            if ($this->canAggregate()) {
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

    /** True while a native-only retry runs, so the carrier APIs never loop back into 17TRACK. */
    private bool $nativeOnly = false;

    protected function canAggregate(): bool
    {
        return $this->has17Track() && ! $this->nativeOnly;
    }

    /**
     * 17TRACK answers asynchronously, so a number is often "not returned yet" or NotFound there while
     * the carrier's own API (USPS / UPS / FedEx) already knows it. Ask the carrier for every number the
     * aggregator could not give a real status for.
     *
     * @param  list<array{number: string, carrier: ?string}>  $requested
     * @param  array<string, array<string, mixed>>  $results
     * @return array<string, array<string, mixed>>
     */
    protected function retryNatively(array $requested, array $results): array
    {
        if ($this->nativeOnly) {
            return $results;
        }

        $retry = [];
        foreach ($requested as $s) {
            $res = $results[$s['number']] ?? null;
            $res = is_array($res) ? $res : null;
            if (self::isPersistableResult($res) && ($res['status'] ?? '') !== self::STATUS_NOT_FOUND) {
                continue;
            }
            $provider = $this->resolveProvider($s['number'], (string) ($s['carrier'] ?? ''));
            if (($provider === 'usps' && $this->hasUsps() && $this->uspsCanRequest())
                || ($provider === 'ups' && $this->hasUps())
                || ($provider === 'fedex' && $this->hasFedex())) {
                $retry[] = $s;
            }
        }
        if ($retry === []) {
            return $results;
        }

        $this->nativeOnly = true;
        try {
            $native = $this->track($retry, ['prefer_native' => true]);
        } catch (\Throwable $e) {
            Log::warning('ShipmentTracking: native retry failed', ['error' => $e->getMessage()]);
            $native = [];
        } finally {
            $this->nativeOnly = false;
        }

        foreach ($native as $number => $res) {
            $res = is_array($res) ? $res : null;
            if (! self::isPersistableResult($res)) {
                continue;
            }
            $before = $results[$number] ?? null;
            $beforeOk = self::isPersistableResult(is_array($before) ? $before : null);
            // A native NotFound never replaces an aggregator answer that was already a real status.
            if (($res['status'] ?? '') === self::STATUS_NOT_FOUND && $beforeOk) {
                continue;
            }
            $results[$number] = $res;
        }

        return $results;
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
            if ($res === null
                || ! empty($res['transient'])
                || ($res['status'] ?? '') === self::STATUS_RATE_LIMITED
                || self::isUnusableProviderFailure((string) ($res['detail'] ?? ''))
            ) {
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
                Log::warning('ShipmentTracking: USPS tracking API is not authorized for this mailer — using 17TRACK instead', [
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

                return '17track';
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
            return [];
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
            return [];
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
                    // a package exception. Do not persist it — fall back to 17TRACK.
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

        if ($lastMile !== [] && $this->has17Track()) {
            $asked = [];
            foreach ($lastMile as $mile) {
                $asked[] = ['number' => $mile, 'carrier' => null];
            }
            try {
                $mileResults = $this->track17Track($asked);
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
                $out[$gofoNo]['provider'] = 'gofo+17track';
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
            // Register is async. An empty status means 17TRACK has not heard
            // back from the carrier yet — do not store that as a real status.
            if ($rawStatus === '') {
                $out[$number] = $this->transientResult('17track', 'Carrier has not returned a status yet');

                continue;
            }

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
            $errLower = strtolower($errMsg);
            if ($this->isQuotaOrRateLimitError(0, $errMsg)
                || str_contains($errLower, 'ran out')
                || str_contains($errLower, 'not been registered')
                || str_contains($errLower, 'no tracking information')
                || str_contains($errLower, 'try again')
            ) {
                $out[$number] = $this->transientResult('17track', $errMsg !== '' ? $errMsg : '17TRACK quota exhausted');

                continue;
            }
            $out[$number] = [
                'status' => self::STATUS_NOT_FOUND,
                'detail' => $errMsg !== '' ? mb_substr($errMsg, 0, 480) : null,
                'provider' => '17track',
            ];
        }

        // Register is async: the first read is often empty. Ask once more before giving up,
        // so a delivered package is not left blank for a whole run.
        $waiting = [];
        foreach ($payload as $item) {
            $res = $out[$item['number']] ?? null;
            if (! is_array($res) || ! empty($res['transient']) || ($res['status'] ?? '') === self::STATUS_NOT_FOUND) {
                $waiting[] = $item;
            }
        }
        if ($waiting !== []) {
            // The page request has a 50 second limit. Only the background sync can wait for 17TRACK.
            if (app()->runningInConsole()) {
                sleep(3);
            }
            $again = $this->post17Track('/gettrackinfo', $waiting);
            $accepted = data_get($again, 'data.accepted', []);
            foreach ($accepted as $row) {
                $number = (string) data_get($row, 'number', '');
                $rawStatus = (string) data_get($row, 'track_info.latest_status.status', '');
                if ($number === '' || $rawStatus === '') {
                    continue;
                }
                $detail = (string) (data_get($row, 'track_info.latest_event.description')
                    ?? data_get($row, 'track_info.latest_status.sub_status')
                    ?? '');
                $out[$number] = [
                    'status' => $this->normalize17TrackStatus($rawStatus),
                    'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : null,
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
            'dhl' => 100001,
            'ontrac' => 100027,
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
