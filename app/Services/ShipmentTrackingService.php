<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-carrier shipment tracking.
 *
 * Resolves a normalized shipment status from a tracking number across carriers
 * (GOFO, USPS, UPS, FedEx, ...) using a tracking aggregator. Default driver is
 * 17TRACK (https://api.17track.net), configured in config/services.php under
 * `tracking`. Swapping providers only requires implementing fetch() for the new
 * provider and returning the same normalized shape.
 */
class ShipmentTrackingService
{
    /** Normalized statuses (provider-agnostic). */
    public const STATUS_PENDING       = 'Pending';
    public const STATUS_INFO_RECEIVED = 'InfoReceived';
    public const STATUS_IN_TRANSIT    = 'InTransit';
    public const STATUS_OUT_FOR_DELIV = 'OutForDelivery';
    public const STATUS_PICKUP        = 'AvailableForPickup';
    public const STATUS_DELIVERED     = 'Delivered';
    public const STATUS_EXCEPTION     = 'Exception';
    public const STATUS_FAILED        = 'DeliveryFailure';
    public const STATUS_EXPIRED       = 'Expired';
    public const STATUS_NOT_FOUND     = 'NotFound';

    private string $provider;
    private string $apiKey;
    private string $apiBase;
    private int $timeout;

    public function __construct()
    {
        $cfg          = config('services.tracking');
        $this->provider = $cfg['provider'] ?? '17track';
        $this->apiKey   = $cfg['api_key']  ?? '';
        $this->apiBase  = rtrim($cfg['api_base'] ?? 'https://api.17track.net/track/v2.2', '/');
        $this->timeout  = (int) ($cfg['http_timeout'] ?? 30);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Fetch normalized statuses for a batch of shipments.
     *
     * @param  array  $shipments  list of ['number' => string, 'carrier' => string|null]
     * @return array  map: trackingNumber => ['status' => string, 'detail' => string|null]
     */
    public function track(array $shipments): array
    {
        if (!$this->isConfigured() || empty($shipments)) {
            return [];
        }

        switch ($this->provider) {
            case '17track':
            default:
                return $this->track17Track($shipments);
        }
    }

    // ── 17TRACK driver ───────────────────────────────────────────────────────

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
                $item['carrier'] = $code;   // omit to let 17TRACK auto-detect
            }
            $payload[] = $item;
        }

        if (empty($payload)) {
            return [];
        }

        // Ensure the numbers are registered (idempotent: already-registered just gets rejected).
        $this->post17Track('/register', $payload);

        // Pull tracking info.
        $resp = $this->post17Track('/gettrackinfo', $payload);

        $out = [];
        $accepted = data_get($resp, 'data.accepted', []);
        foreach ($accepted as $row) {
            $number = (string) data_get($row, 'number', '');
            if ($number === '') {
                continue;
            }
            $rawStatus = (string) data_get($row, 'track_info.latest_status.status', '');
            $detail    = (string) (data_get($row, 'track_info.latest_event.description')
                ?? data_get($row, 'track_info.latest_status.sub_status')
                ?? '');

            $out[$number] = [
                'status' => $this->normalize17TrackStatus($rawStatus),
                'detail' => $detail !== '' ? mb_substr($detail, 0, 480) : null,
            ];
        }

        // Anything rejected / not found -> mark NotFound so we don't retry blindly forever.
        $rejected = data_get($resp, 'data.rejected', []);
        foreach ($rejected as $row) {
            $number = (string) data_get($row, 'number', '');
            if ($number !== '' && !isset($out[$number])) {
                $out[$number] = ['status' => self::STATUS_NOT_FOUND, 'detail' => null];
            }
        }

        return $out;
    }

    private function post17Track(string $path, array $body): array
    {
        try {
            $response = Http::withHeaders([
                '17token'      => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post($this->apiBase . $path, $body);

            if (!$response->successful()) {
                Log::warning('ShipmentTracking: 17track HTTP error', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('ShipmentTracking: 17track request failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Map a carrier name to a 17TRACK numeric carrier code.
     * Returns null when unknown so 17TRACK auto-detects from the number.
     * Codes per https://res.17track.net/asset/carrier/info/apicarrier.all.json
     */
    private function carrierCode17Track(?string $carrier): ?int
    {
        $c = strtolower(trim((string) $carrier));
        if ($c === '') {
            return null;
        }

        // 17TRACK numeric carrier codes (verified against apicarrier.all.json)
        $map = [
            'usps'           => 21051,
            'united states postal service' => 21051,
            'ups'            => 100002,
            'fedex'          => 100003,
            'fedex express'  => 100003,
            'fedex ground'   => 100003,
            'gofo'           => 100996,
            'gofo express'   => 100996,
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
            'NotFound'           => self::STATUS_NOT_FOUND,
            'InfoReceived'       => self::STATUS_INFO_RECEIVED,
            'InTransit'          => self::STATUS_IN_TRANSIT,
            'Expired'            => self::STATUS_EXPIRED,
            'AvailableForPickup' => self::STATUS_PICKUP,
            'OutForDelivery'     => self::STATUS_OUT_FOR_DELIV,
            'DeliveryFailure'    => self::STATUS_FAILED,
            'Delivered'          => self::STATUS_DELIVERED,
            'Exception'          => self::STATUS_EXCEPTION,
        ];

        return $known[$status] ?? ($status !== '' ? $status : self::STATUS_PENDING);
    }
}
