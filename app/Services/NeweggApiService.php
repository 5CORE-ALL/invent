<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the Newegg Marketplace API.
 *
 * Auth model (per Newegg docs):
 *   - Authorization header -> API Key
 *   - SecretKey header     -> Secret Key
 *   - sellerid query param -> Seller ID (required on most endpoints)
 *
 * IMPORTANT: api.newegg.com is behind Cloudflare. Requests from a
 * non-whitelisted IP get a 403 "managed challenge" HTML page (not JSON).
 * Whitelist the calling server's IP in the Newegg Seller Portal.
 */
class NeweggApiService
{
    protected ?string $sellerId;
    protected ?string $apiKey;
    protected ?string $secretKey;
    protected string $baseUrl;
    protected int $timeout;
    protected int $connectTimeout;

    public function __construct()
    {
        $this->sellerId       = config('services.newegg.seller_id');
        $this->apiKey         = config('services.newegg.api_key');
        $this->secretKey      = config('services.newegg.secret_key');
        $this->baseUrl        = rtrim((string) config('services.newegg.base_url', 'https://api.newegg.com'), '/');
        $this->timeout        = (int) config('services.newegg.http_timeout', 60);
        $this->connectTimeout = (int) config('services.newegg.connect_timeout', 15);

        if (!$this->apiKey || !$this->secretKey) {
            Log::warning('Newegg API credentials not configured. Set NEWEGG_API_KEY and NEWEGG_SECRET_KEY in .env');
        }
    }

    /**
     * Service Status API — the standard connectivity/auth test endpoint.
     * GET /marketplace/servicestatus/status?sellerid=XXXX
     *
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function getServiceStatus(): array
    {
        return $this->request('GET', '/marketplace/servicestatus/status');
    }

    /**
     * Low-level request helper. Returns a normalized result array instead of
     * throwing, so callers (and the artisan test command) can inspect exactly
     * what came back — including a Cloudflare challenge page.
     *
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>|null  $body
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $query = array_merge(['sellerid' => $this->sellerId], $query);
        $url   = $this->baseUrl . '/' . ltrim($path, '/');

        try {
            $http = Http::withHeaders([
                    'Authorization' => $this->apiKey,
                    'SecretKey'     => $this->secretKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout);

            $response = $body !== null
                ? $http->send($method, $url, ['query' => $query, 'json' => $body])
                : $http->send($method, $url, ['query' => $query]);

            return $this->normalize($response);
        } catch (\Throwable $e) {
            Log::error('Newegg API request failed', ['url' => $url, 'error' => $e->getMessage()]);

            return [
                'ok'                    => false,
                'status'                => 0,
                'blocked_by_cloudflare' => false,
                'json'                  => null,
                'raw'                   => '',
                'error'                 => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    protected function normalize(Response $response): array
    {
        $status = $response->status();
        $raw    = $response->body();
        $json   = null;

        $isCloudflare = $response->header('cf-mitigated') !== ''
            || str_contains((string) $response->header('server'), 'cloudflare') && str_contains($raw, 'CAPTCHA');

        if (!$isCloudflare) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        return [
            'ok'                    => $response->successful() && $json !== null,
            'status'                => $status,
            'blocked_by_cloudflare' => $isCloudflare,
            'json'                  => $json,
            'raw'                   => $raw,
            'error'                 => null,
        ];
    }
}
