<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort 4Seller ERP lookup for a platform order's shipping label.
 * Labels bought in 4Seller are purchased via GOFO; GOFO lookup is the primary
 * source. This client is only used when FOURSELLER_ACCESS_TOKEN is set.
 */
class FourSellerApiService
{
    protected string $baseUrl;

    protected string $token;

    protected string $appKey;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.fourseller.api_base', ''), '/');
        $this->token = trim((string) config('services.fourseller.access_token', ''));
        $this->appKey = trim((string) config('services.fourseller.app_key', ''));
        $this->timeout = max(5, (int) config('services.fourseller.http_timeout', 20));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    public function setTimeout(int $seconds): void
    {
        $this->timeout = max(3, $seconds);
    }

    /**
     * @param  list<string>  $refs
     * @return array{tracking: string, carrier: string, source: string}|null
     */
    public function findShipment(array $refs): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        foreach ($this->cleanRefs($refs) as $ref) {
            $hit = $this->searchOne($ref);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $refs
     * @return list<string>
     */
    protected function cleanRefs(array $refs): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if (preg_match('/^(?:TT2?|tiktok2?)-(.+)$/i', ltrim($ref, '#'), $m)) {
                $ref = trim((string) $m[1]);
            }
            $plain = strtolower(ltrim($ref, '#'));
            if (strlen($ref) < 6) {
                continue;
            }
            if (preg_match('/^\d{5,10}$/', $plain) || preg_match('/^\d{12,14}$/', $plain)) {
                continue;
            }
            if (! in_array($ref, $out, true)) {
                $out[] = $ref;
            }
            if (preg_match('/^\d{3}-\d{7}-\d{7}$/', $ref) === 1) {
                $plainAmz = str_replace('-', '', $ref);
                if (! in_array($plainAmz, $out, true)) {
                    $out[] = $plainAmz;
                }
                foreach (['Amz'.$ref, '#Amz'.$ref] as $amzRef) {
                    if (! in_array($amzRef, $out, true)) {
                        $out[] = $amzRef;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return array{tracking: string, carrier: string, source: string}|null
     */
    protected function searchOne(string $ref): ?array
    {
        $attempts = [
            ['GET', '/open-api/v1/order/detail', ['platformOrderNo' => $ref, 'orderNo' => $ref, 'keyword' => $ref]],
            ['GET', '/api/v1/orders', ['keyword' => $ref, 'platformOrderId' => $ref, 'orderNo' => $ref]],
            ['POST', '/open-api/v1/order/query', ['platformOrderNo' => $ref, 'orderNo' => $ref, 'keyword' => $ref]],
            ['GET', '/erp/api/order/search', ['q' => $ref, 'keyword' => $ref]],
        ];

        foreach ($attempts as [$method, $path, $payload]) {
            $res = $this->request($method, $path, $payload);
            if (empty($res['ok']) || ! is_array($res['data'] ?? null)) {
                continue;
            }
            $ship = $this->extractShipment($res['data']);
            if ($ship !== null) {
                $ship['source'] = '4seller';

                return $ship;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $queryOrBody
     * @return array{ok: bool, data: mixed}
     */
    protected function request(string $method, string $path, array $queryOrBody): array
    {
        $url = $this->baseUrl.'/'.ltrim($path, '/');
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
            'token' => $this->token,
            'accessToken' => $this->token,
        ];
        if ($this->appKey !== '') {
            $headers['appKey'] = $this->appKey;
            $headers['x-app-key'] = $this->appKey;
        }

        try {
            $pending = Http::timeout($this->timeout)
                ->connectTimeout(min(5, $this->timeout))
                ->withOptions([
                    'curl' => [
                        CURLOPT_LOW_SPEED_LIMIT => 100,
                        CURLOPT_LOW_SPEED_TIME => min(20, max(8, $this->timeout)),
                    ],
                ])
                ->withoutVerifying()
                ->acceptJson()
                ->withHeaders($headers);
            $response = strtoupper($method) === 'POST'
                ? $pending->asJson()->post($url, $queryOrBody)
                : $pending->get($url, $queryOrBody);
        } catch (\Throwable $e) {
            Log::info('FourSeller API request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'data' => null];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'data' => null];
        }

        $json = $response->json();

        return ['ok' => true, 'data' => is_array($json) ? $json : null];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{tracking: string, carrier: string, gofo_order_no?: string}|null
     */
    protected function extractShipment(array $payload): ?array
    {
        $tracking = null;
        $carrier = '';
        $gofoOrderNo = '';
        $walk = static function ($value, $key = '') use (&$walk, &$tracking, &$carrier, &$gofoOrderNo): void {
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
            if ($gofoOrderNo === '' && preg_match('/^(order.?no|orderno|gofo.?order|seller.?order.?no)$/', $k)
                && preg_match('/^S\d{10,}$/i', $s) === 1) {
                $gofoOrderNo = $s;
            }
            if ($carrier === '' && preg_match('/carrier|logistics.?company|shipping.?company|ship.?method/', $k) && ! is_numeric($s)) {
                $carrier = $s;
            }
            if ($tracking !== null) {
                return;
            }
            if (! preg_match('/track|waybill|mail.?no|logistics.?no|ship.?code/', $k)) {
                return;
            }
            if (preg_match('/url|link|status|time|date|id$/', $k)) {
                return;
            }
            $tn = strtoupper(preg_replace('/\s+/', '', $s) ?? '');
            if (strlen($tn) >= 8 && ! preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn)) {
                $tracking = $tn;
            }
        };
        $walk($payload);
        if ($tracking === null) {
            return null;
        }

        $out = [
            'tracking' => $tracking,
            'carrier' => $carrier !== '' ? $carrier : 'Other',
        ];
        if ($gofoOrderNo !== '') {
            $out['gofo_order_no'] = $gofoOrderNo;
        }

        return $out;
    }
}
