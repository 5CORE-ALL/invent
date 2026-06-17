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
     *
     * URL format (per Newegg docs):
     *   GET https://api.newegg.com/marketplace/{servicegroup}/servicestatus?sellerid=XXXX
     *
     * $servicegroup is one of: contentmgmt, ordermgmt, reportmgmt, sellermgmt, ...
     * URLs must be all lowercase (Seller ID excepted).
     *
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function getServiceStatus(string $servicegroup = 'contentmgmt'): array
    {
        $servicegroup = strtolower(trim($servicegroup));

        return $this->request('GET', "/marketplace/{$servicegroup}/servicestatus");
    }

    /**
     * Get Order Information.
     *   PUT /marketplace/ordermgmt/order/orderinfo?sellerid=XXXX&version=NNN
     *
     * Pass any subset of Newegg RequestCriteria fields, e.g.:
     *   ['Status' => 0, 'OrderDateFrom' => '2026-05-01 00:00:00', 'OrderDateTo' => '2026-06-01 00:00:00']
     *
     * Dates must be Pacific Standard Time.
     *
     * @param  array<string,mixed>  $criteria
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function getOrders(array $criteria = [], int $pageIndex = 1, int $pageSize = 100, string $version = '315'): array
    {
        $body = [
            'OperationType' => 'GetOrderInfoRequest',
            'RequestBody'   => [
                'PageIndex'       => (string) $pageIndex,
                'PageSize'        => (string) min(max($pageSize, 1), 100),
                'RequestCriteria' => (object) $criteria,
            ],
        ];

        return $this->request('PUT', '/marketplace/ordermgmt/order/orderinfo', ['version' => $version], $body);
    }

    /**
     * Get Item Inventory for a single item.
     *   POST /marketplace/contentmgmt/item/inventory?sellerid=XXXX
     *
     * @param  int  $type  0 = NE Item#, 1 = Seller Part#, 2 = UPC
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function getItemInventory(string $value, int $type = 1): array
    {
        return $this->request('POST', '/marketplace/contentmgmt/item/inventory', [], [
            'Type'  => (string) $type,
            'Value' => $value,
        ]);
    }

    /**
     * Get Item Price (international) for a single item.
     *   PUT /marketplace/contentmgmt/item/international/price?sellerid=XXXX
     *
     * @param  list<string>  $countries  ISO 3-letter codes; defaults to USA.
     * @param  int  $type  0 = NE Item#, 1 = Seller Part#, 2 = UPC
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function getItemPrice(string $value, array $countries = ['USA'], int $type = 1): array
    {
        return $this->request('PUT', '/marketplace/contentmgmt/item/international/price', [], [
            'Type'        => (string) $type,
            'Value'       => $value,
            'CountryList' => ['CountryCode' => array_values($countries)],
        ]);
    }

    /**
     * Submit an Item Basic Information Report request (async). Returns a
     * RequestID you then poll with getReportResult().
     *   POST /marketplace/reportmgmt/report/submitrequest?sellerid=XXXX
     *
     * @param  int  $status  0 = All, 1 = Active, 2 = Inactive
     * @param  string  $fileType  TXT | CSV | XLS
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function submitItemBasicInfoReport(int $status = 0, string $fileType = 'CSV'): array
    {
        return $this->request('POST', '/marketplace/reportmgmt/report/submitrequest', [], [
            'OperationType' => 'ItemBasicInfoReportRequest',
            'RequestBody'   => [
                'ItemBasicInfoReportCriteria' => [
                    'RequestType' => 'ITEM_BASIC_INFO_REPORT',
                    'Status'      => $status,
                    'FileType'    => $fileType,
                ],
            ],
        ]);
    }

    /**
     * Poll a previously submitted report. When ready the response carries a
     * ReportFileURL (an ftp:// link to the result file).
     *   PUT /marketplace/reportmgmt/report/result?sellerid=XXXX
     *
     * @return array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}
     */
    public function getReportResult(string $requestId, string $operationType = 'ItemBasicInfoReportRequest'): array
    {
        return $this->request('PUT', '/marketplace/reportmgmt/report/result', [], [
            'OperationType' => $operationType,
            'RequestBody'   => ['RequestID' => $requestId],
        ]);
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
