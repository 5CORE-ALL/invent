<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GOFO Express Open API client (Basic Auth).
 *
 * Base: https://dmsapi.gofoexpress.com
 * Docs paths: /open-api/v2/order/*
 */
class GofoExpressService
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected string $productCode;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.gofo.api_base', 'https://dmsapi.gofoexpress.com'), '/');
        $this->username = (string) config('services.gofo.username', '');
        $this->password = (string) config('services.gofo.password', '');
        $this->productCode = (string) config('services.gofo.product_code', 'GOFO Parcel Pickup');
        $this->timeout = max(5, (int) config('services.gofo.http_timeout', 30));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->username !== '' && $this->password !== '';
    }

    public function setTimeout(int $seconds): void
    {
        $this->timeout = max(3, $seconds);
    }

    public function productCode(): string
    {
        return $this->productCode;
    }

    /**
     * Lightweight connectivity check (auth + delivery zip probe).
     *
     * @return array{ok:bool,code:?int,msg:?string,data:mixed}
     */
    public function ping(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'code' => null, 'msg' => 'GOFO credentials not configured', 'data' => null];
        }

        $res = $this->verifyDelivery([
            'consigneeCountry' => 'US',
            'consigneeState' => 'California',
            'consigneeCity' => 'Los Angeles',
            'consigneeCode' => '90210',
        ]);

        $code = $res['code'] ?? null;

        return [
            'ok' => $code === 200,
            'code' => is_numeric($code) ? (int) $code : null,
            'msg' => (string) ($res['msgEn'] ?? $res['msg'] ?? $res['error'] ?? ''),
            'data' => $res['data'] ?? null,
        ];
    }

    /**
     * @param  array{consigneeCountry:string,consigneeCode:string,consigneeState?:string,consigneeCity?:string,consigneeArea?:string,consigneeStreet?:string}  $consignee
     * @return array<string, mixed>
     */
    public function verifyDelivery(array $consignee): array
    {
        return $this->request('POST', '/open-api/v2/order/verifyDelivery', [
            'orderConsignee' => $consignee,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function track(string $orderNo): array
    {
        $orderNo = trim($orderNo);
        if ($orderNo === '') {
            return ['ok' => false, 'code' => 301, 'msg' => 'orderNo required', 'msgEn' => 'orderNo required', 'data' => null];
        }

        return $this->request('GET', '/open-api/v2/order/track/'.rawurlencode($orderNo));
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(string $orderNo): array
    {
        $orderNo = trim($orderNo);
        if ($orderNo === '') {
            return ['ok' => false, 'code' => 301, 'msg' => 'orderNo required', 'msgEn' => 'orderNo required', 'data' => null];
        }

        return $this->request('GET', '/open-api/v2/order/getOrderLabelUrlV2', [
            'orderNo' => $orderNo,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        if (empty($payload['productCode'])) {
            $payload['productCode'] = $this->productCode;
        }

        return $this->request('POST', '/open-api/v2/order/create', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function costEstimation(array $payload): array
    {
        if (empty($payload['productCode'])) {
            $payload['productCode'] = $this->productCode;
        }

        return $this->request('POST', '/open-api/v2/order/costEstimation', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelOrder(string $orderNo, string $remarks = ''): array
    {
        $body = ['orderNo' => trim($orderNo)];
        if (trim($remarks) !== '') {
            $body['remarks'] = trim($remarks);
        }

        return $this->request('POST', '/open-api/v2/order/cancel', $body);
    }

    /**
     * Look up a 4Seller/GOFO label by marketplace or customer order number.
     * 4Seller buys GOFO labels using the platform order id as GOFO's orderNo.
     *
     * @param  list<string>  $refs
     * @return array{tracking: string, carrier: string, source: string, gofo_order_no: string}|null
     */
    public function findShipment(array $refs, bool $fast = false): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $candidates = $this->candidateOrderNos($refs);
        if ($fast) {
            $candidates = array_slice($candidates, 0, 2);
        }

        foreach ($candidates as $orderNo) {
            $fromTrack = null;
            $res = $this->track($orderNo);
            if (! empty($res['ok'])) {
                $fromTrack = $this->trackingFromPayload($res['data'] ?? null);
            }

            $fromLabel = null;
            $trackHasNumber = $fromTrack !== null && strlen((string) ($fromTrack['tracking'] ?? '')) >= 8;
            if (! $fast || ! $trackHasNumber) {
                $label = $this->getLabel($orderNo);
                if (! empty($label['ok'])) {
                    $fromLabel = $this->trackingFromPayload($label['data'] ?? null);
                }
            }

            $tracking = $fromLabel['tracking'] ?? $fromTrack['tracking'] ?? null;
            if ($tracking === null || strlen($tracking) < 8) {
                continue;
            }
            if (preg_match('/^\d{3}-\d{7}-\d{7}$/', $tracking)) {
                continue;
            }

            $carrier = (string) ($fromLabel['carrier'] ?? $fromTrack['carrier'] ?? 'GOFO');
            if ($carrier === '') {
                $carrier = 'GOFO';
            }

            return [
                'tracking' => $tracking,
                'carrier' => $carrier,
                'source' => 'gofo',
                'gofo_order_no' => $orderNo,
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $refs
     * @return list<string>
     */
    protected function candidateOrderNos(array $refs): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }
            $variants = [$ref];
            if (str_starts_with($ref, '#')) {
                $variants[] = ltrim($ref, '#');
            }
            if (preg_match('/^PO-(.+)$/i', $ref, $m)) {
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $variants[] = $tail;
                }
            }
            foreach ($variants as $candidate) {
                $candidate = trim($candidate);
                $plain = strtolower(ltrim($candidate, '#'));
                if (strlen($candidate) < 6) {
                    continue;
                }
                // Shopify #334262 (5–10 digits) is not a GOFO orderNo we should guess.
                if (preg_match('/^\d{5,10}$/', $plain)) {
                    continue;
                }
                if (! in_array($candidate, $out, true)) {
                    $out[] = $candidate;
                }
            }
        }

        return array_slice($out, 0, 6);
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function trackingFromPayload(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $tracking = null;
        $carrier = '';
        $walk = static function ($value, $key = '') use (&$walk, &$tracking, &$carrier): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, (string) $k);
                }

                return;
            }
            $k = strtolower((string) $key);
            $s = trim((string) $value);
            if ($s === '') {
                return;
            }
            if ($carrier === '' && preg_match('/carrier|last.?mile.?carrier|logistics.?company/', $k) && ! is_numeric($s)) {
                $carrier = $s;
            }
            if ($tracking !== null) {
                return;
            }
            if (! preg_match('/(last.?mile|tracking|waybill|mail.?no|hawb|server.?hawb|shipper.?hawb|logistics.?no)/', $k)) {
                return;
            }
            if (preg_match('/url|link|status|time|date|id$|context|move/', $k)) {
                return;
            }
            $tn = strtoupper(preg_replace('/\s+/', '', $s) ?? '');
            if (strlen($tn) >= 8) {
                $tracking = $tn;
            }
        };
        $walk($data);

        if ($tracking === null) {
            return null;
        }

        return [
            'tracking' => $tracking,
            'carrier' => $carrier !== '' ? $carrier : 'GOFO',
        ];
    }

    /**
     * Map GOFO trajectory codes / English text to normalized shipment statuses.
     */
    public function normalizeTrackStatus(string $operationMove, string $enContext = ''): string
    {
        $code = trim($operationMove);
        $hay = strtolower(trim($enContext));

        return match ($code) {
            '205', '257' => ShipmentTrackingService::STATUS_DELIVERED,
            '208' => ShipmentTrackingService::STATUS_OUT_FOR_DELIV,
            '206', '300', '301' => ShipmentTrackingService::STATUS_EXCEPTION,
            '100' => ShipmentTrackingService::STATUS_INFO_RECEIVED,
            '200', '201', '202', '203' => ShipmentTrackingService::STATUS_IN_TRANSIT,
            default => (str_contains($hay, 'delivered')
                ? ShipmentTrackingService::STATUS_DELIVERED
                : (str_contains($hay, 'out for delivery')
                    ? ShipmentTrackingService::STATUS_OUT_FOR_DELIV
                    : (str_contains($hay, 'fail') || str_contains($hay, 'exception') || str_contains($hay, 'lost')
                        ? ShipmentTrackingService::STATUS_EXCEPTION
                        : ($hay !== '' || $code !== ''
                            ? ShipmentTrackingService::STATUS_IN_TRANSIT
                            : ShipmentTrackingService::STATUS_NOT_FOUND)))),
        };
    }

    /**
     * @param  array<string, mixed>|null  $queryOrBody
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $queryOrBody = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'code' => 401,
                'msg' => 'GOFO credentials not configured',
                'msgEn' => 'GOFO credentials not configured',
                'data' => null,
                'error' => 'not_configured',
            ];
        }

        $url = $this->baseUrl.'/'.ltrim($path, '/');
        $method = strtoupper($method);

        try {
            $pending = Http::timeout($this->timeout)
                ->acceptJson()
                ->withBasicAuth($this->username, $this->password);

            $response = match ($method) {
                'GET' => $pending->get($url, $queryOrBody ?? []),
                'POST' => $pending->asJson()->post($url, $queryOrBody ?? []),
                default => $pending->send($method, $url, ['json' => $queryOrBody ?? []]),
            };
        } catch (\Throwable $e) {
            Log::warning('GOFO API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'code' => 500,
                'msg' => $e->getMessage(),
                'msgEn' => $e->getMessage(),
                'data' => null,
                'error' => 'http_exception',
            ];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [
                'ok' => false,
                'code' => $response->status(),
                'msg' => 'Non-JSON response',
                'msgEn' => 'Non-JSON response',
                'data' => null,
                'raw' => mb_substr($response->body(), 0, 500),
                'error' => 'invalid_json',
            ];
        }

        $code = isset($json['code']) && is_numeric($json['code']) ? (int) $json['code'] : $response->status();

        return [
            'ok' => $code === 200,
            'code' => $code,
            'msg' => (string) ($json['msg'] ?? ''),
            'msgEn' => (string) ($json['msgEn'] ?? $json['msg'] ?? ''),
            'data' => $json['data'] ?? null,
            'http_status' => $response->status(),
        ];
    }
}
