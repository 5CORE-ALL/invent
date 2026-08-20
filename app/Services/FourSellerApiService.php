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
            if (strlen($ref) < 6) {
                continue;
            }
            if (! in_array($ref, $out, true)) {
                $out[] = $ref;
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
            $pending = Http::timeout($this->timeout)->acceptJson()->withHeaders($headers);
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
     * @return array{tracking: string, carrier: string}|null
     */
    protected function extractShipment(array $payload): ?array
    {
        $tracking = $this->firstTracking($payload);
        if ($tracking === null) {
            return null;
        }

        return [
            'tracking' => $tracking,
            'carrier' => $this->firstCarrier($payload) ?: 'Other',
        ];
    }

    protected function firstTracking(array $data): ?string
    {
        $found = null;
        $walk = static function ($value, $key = '') use (&$walk, &$found): void {
            if ($found !== null) {
                return;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, (string) $k);
                }

                return;
            }
            $k = strtolower((string) $key);
            if (! preg_match('/track|waybill|mail.?no|logistics.?no|ship.?code/', $k)) {
                return;
            }
            if (preg_match('/url|link|status|time|date|id$/', $k)) {
                return;
            }
            $tn = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
            if (strlen($tn) >= 8 && ! preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn)) {
                $found = $tn;
            }
        };
        $walk($data);

        return $found;
    }

    protected function firstCarrier(array $data): string
    {
        $found = '';
        $walk = static function ($value, $key = '') use (&$walk, &$found): void {
            if ($found !== '') {
                return;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, (string) $k);
                }

                return;
            }
            $k = strtolower((string) $key);
            if (! preg_match('/carrier|logistics.?company|shipping.?company|ship.?method/', $k)) {
                return;
            }
            $name = trim((string) $value);
            if ($name !== '' && ! is_numeric($name)) {
                $found = $name;
            }
        };
        $walk($data);

        return $found;
    }
}
