<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Veeqo private API client (x-api-key header).
 *
 * Base: https://api.veeqo.com
 * Auth: x-api-key: VEEQO_API_KEY
 * Docs: https://developers.veeqo.com/
 */
class VeeqoApiService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.veeqo.api_base', 'https://api.veeqo.com'), '/');
        $this->apiKey = trim((string) config('services.veeqo.api_key', ''));
        $this->timeout = max(5, (int) config('services.veeqo.http_timeout', 30));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function setTimeout(int $seconds): void
    {
        $this->timeout = max(3, $seconds);
    }

    public function apiBase(): string
    {
        return $this->baseUrl;
    }

    /**
     * Lightweight connectivity check via current employee/user.
     *
     * @return array{ok:bool,code:?int,msg:?string,data:mixed}
     */
    public function ping(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'code' => null, 'msg' => 'Veeqo API key not configured', 'data' => null];
        }

        $res = $this->request('GET', '/current_user');
        $ok = (bool) ($res['ok'] ?? false);

        return [
            'ok' => $ok,
            'code' => isset($res['http_status']) ? (int) $res['http_status'] : null,
            'msg' => $ok
                ? 'Connected'
                : (string) ($res['error'] ?? $res['msg'] ?? 'Veeqo ping failed'),
            'data' => $res['data'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listOrders(array $query = []): array
    {
        return $this->request('GET', '/orders', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(int|string $orderId): array
    {
        return $this->request('GET', '/orders/'.rawurlencode((string) $orderId));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listShipments(array $query = []): array
    {
        return $this->request('GET', '/shipments', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listProducts(array $query = []): array
    {
        return $this->request('GET', '/products', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listStores(array $query = []): array
    {
        return $this->request('GET', '/stores', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listCarriers(array $query = []): array
    {
        return $this->request('GET', '/carriers', $query);
    }

    /**
     * Normalized carrier options for UI filters (key/label/veeqo_id).
     *
     * @return list<array{key:string,label:string,veeqo_id:?int,name:string}>
     */
    public function carrierOptions(): array
    {
        $res = $this->listCarriers();
        if (empty($res['ok']) || ! is_array($res['data'] ?? null)) {
            return [];
        }

        $raw = $res['data'];
        $list = array_is_list($raw) ? $raw : (isset($raw['carriers']) && is_array($raw['carriers']) ? $raw['carriers'] : []);
        $out = [];
        $seen = [];

        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = $this->normalizeCarrierKey($name, (string) ($row['slug'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'label' => $this->carrierLabelForKey($key, $name),
                'veeqo_id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'name' => $name,
            ];
        }

        usort($out, fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    protected function normalizeCarrierKey(string $name, string $slug = ''): string
    {
        $hay = strtolower(trim($name.' '.$slug));
        if ($hay === '') {
            return '';
        }
        if (str_contains($hay, 'usps') || str_contains($hay, 'united states postal')) {
            return 'usps';
        }
        if (str_contains($hay, 'ups') && ! str_contains($hay, 'usps')) {
            return 'ups';
        }
        if (str_contains($hay, 'fedex') || str_contains($hay, 'federal express')) {
            return 'fedex';
        }
        if (str_contains($hay, 'dhl')) {
            return 'dhl';
        }
        if (str_contains($hay, 'amazon')) {
            return 'amazon';
        }
        if (str_contains($hay, 'ontrac') || str_contains($hay, 'on trac')) {
            return 'ontrac';
        }
        if (str_contains($hay, 'gofo')) {
            return 'gofo';
        }
        if (str_contains($hay, 'veeqo')) {
            return 'veeqo';
        }
        if (str_contains($hay, 'other')) {
            return 'other';
        }

        return 'other';
    }

    protected function carrierLabelForKey(string $key, string $fallback): string
    {
        return match ($key) {
            'usps' => 'USPS',
            'ups' => 'UPS',
            'fedex' => 'FedEx',
            'dhl' => 'DHL',
            'amazon' => 'Amz',
            'ontrac' => 'OnTrac',
            'gofo' => 'GOFO',
            'veeqo' => 'Veeqo',
            'other' => 'Other',
            default => $fallback !== '' ? $fallback : ucfirst($key),
        };
    }

    /**
     * @param  array<string, mixed>|null  $queryOrBody
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $queryOrBody = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'http_status' => 401,
                'msg' => 'Veeqo API key not configured',
                'data' => null,
                'error' => 'not_configured',
            ];
        }

        $url = $this->baseUrl.'/'.ltrim($path, '/');
        $method = strtoupper($method);

        try {
            $pending = Http::timeout($this->timeout)
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                ]);

            $response = match ($method) {
                'GET' => $pending->get($url, $queryOrBody ?? []),
                'POST' => $pending->asJson()->post($url, $queryOrBody ?? []),
                'PUT' => $pending->asJson()->put($url, $queryOrBody ?? []),
                'PATCH' => $pending->asJson()->patch($url, $queryOrBody ?? []),
                'DELETE' => $pending->delete($url, $queryOrBody ?? []),
                default => $pending->send($method, $url, ['json' => $queryOrBody ?? []]),
            };
        } catch (\Throwable $e) {
            Log::warning('Veeqo API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'http_status' => 500,
                'msg' => $e->getMessage(),
                'data' => null,
                'error' => 'http_exception',
            ];
        }

        $status = $response->status();
        $json = $response->json();
        $ok = $response->successful();

        if (! is_array($json)) {
            return [
                'ok' => $ok,
                'http_status' => $status,
                'msg' => $ok ? 'OK' : 'Non-JSON response',
                'data' => null,
                'raw' => mb_substr($response->body(), 0, 500),
                'error' => $ok ? null : 'invalid_json',
            ];
        }

        $errorMsg = null;
        if (! $ok) {
            $errorMsg = (string) ($json['error_messages'][0] ?? $json['error'] ?? $json['message'] ?? $json['error_message'] ?? 'Veeqo API error');
        }

        return [
            'ok' => $ok,
            'http_status' => $status,
            'msg' => $ok ? 'OK' : $errorMsg,
            'data' => $json,
            'error' => $ok ? null : 'api_error',
        ];
    }
}
