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
     * Update Item Price (international) for a SINGLE item.
     *   PUT /marketplace/contentmgmt/item/international/price?sellerid=XXXX
     *
     * Wraps {@see updateItemPriceBulk()} for the common single-SKU push case.
     * Returns a normalized result mirroring ReverbApiService::updatePrice():
     *   ['success' => bool, 'message' => string, 'sku' => string, 'price' => float]
     *
     * @return array{success:bool,message:string,sku:string,price:float,raw:?string,blocked_by_cloudflare:bool}
     */
    public function updateItemPrice(string $sellerPartNumber, float $price, string $currency = 'USD', string $country = 'USA'): array
    {
        $sellerPartNumber = trim($sellerPartNumber);
        if ($sellerPartNumber === '') {
            return [
                'success' => false,
                'message' => 'SellerPartNumber is required.',
                'sku' => $sellerPartNumber,
                'price' => $price,
                'raw' => null,
                'blocked_by_cloudflare' => false,
            ];
        }

        $price = round($price, 2);
        if ($price <= 0) {
            return [
                'success' => false,
                'message' => 'Price must be greater than 0.',
                'sku' => $sellerPartNumber,
                'price' => $price,
                'raw' => null,
                'blocked_by_cloudflare' => false,
            ];
        }

        $bulk = $this->updateItemPriceBulk([
            ['seller_part_number' => $sellerPartNumber, 'price' => $price, 'currency' => $currency],
        ], $country);

        $first = $bulk['results'][0] ?? null;
        $raw = $first['raw'] ?? null;

        if ($bulk['blocked_by_cloudflare']) {
            return [
                'success' => false,
                'message' => 'Blocked by Cloudflare (managed challenge). Whitelist this server IP in the Newegg Seller Portal.',
                'sku' => $sellerPartNumber,
                'price' => $price,
                'raw' => $raw,
                'blocked_by_cloudflare' => true,
            ];
        }

        if (!$bulk['ok']) {
            $err = $first['error'] ?? ($bulk['error_message'] ?? 'Unknown error');
            return [
                'success' => false,
                'message' => "Newegg API rejected the update: {$err}",
                'sku' => $sellerPartNumber,
                'price' => $price,
                'raw' => $raw,
                'blocked_by_cloudflare' => false,
            ];
        }

        Log::info('Newegg price updated', [
            'seller_part_number' => $sellerPartNumber,
            'price' => $price,
            'currency' => $currency,
            'country' => $country,
        ]);

        return [
            'success' => true,
            'message' => "Price \${$price} pushed to Newegg for SPN: {$sellerPartNumber}.",
            'sku' => $sellerPartNumber,
            'price' => $price,
            'raw' => $raw,
            'blocked_by_cloudflare' => false,
        ];
    }

    /**
     * Update Item Price for many items by looping the per-SKU endpoint:
     *
     *   POST /marketplace/contentmgmt/item/international/price?sellerid=XXXX
     *
     * Newegg's Update Item Price endpoint is **per-item** (one POST per SKU),
     * with a FLAT body — no OperationType/SellerID/RequestBody wrapper.
     *
     * Body shape Newegg actually validates (from the schema-error self-report):
     *   {
     *     "Type":      "1",            // 1 = SellerPartNumber, 2 = NeweggItemNumber, 3 = UPC
     *     "Value":     "<seller part>",
     *     "Condition": "New" | "Refurbished" | ... (optional)
     *     "PriceList": [
     *       {
     *         "CountryCode":  "USA",
     *         "Currency":     "USD",       // must match CountryCode (USD↔USA, CAD↔CAN, …)
     *         "SellingPrice": "19.99",     // decimal as string
     *         "MAP":          "0.00",      // optional
     *         "CheckoutMAP":  "0",         // optional
     *         "MSRP":         "25.99",     // optional
     *         "Active":       "1"          // optional ("1" active, "0" inactive)
     *       }
     *     ]
     *   }
     *
     * For thousands of SKUs in one shot consider the async Price Update Feed
     * (POST /marketplace/datafeedmgmt/feeds/submitfeed?requesttype=PRICE_DATA).
     *
     * @param  list<array{seller_part_number:string,price:float|int|string,currency?:string,country?:string,msrp?:float|int|string,map?:float|int|string,checkout_map?:bool,active?:bool,condition?:string}>  $items
     * @return array{ok:bool,pushed:int,failed:int,blocked_by_cloudflare:bool,error_message:?string,results:list<array{seller_part_number:string,success:bool,status:int,error:?string,raw:?string}>}
     */
    public function updateItemPriceBulk(array $items, string $defaultCountry = 'USA'): array
    {
        $defaultCountry = strtoupper(trim($defaultCountry) ?: 'USA');

        $results = [];
        $pushed = 0;
        $failed = 0;
        $blockedAny = false;

        foreach ($items as $i) {
            $spn   = trim((string) ($i['seller_part_number'] ?? ''));
            $price = isset($i['price']) ? round((float) $i['price'], 2) : 0.0;
            if ($spn === '' || $price <= 0) {
                $results[] = [
                    'seller_part_number' => $spn,
                    'success' => false,
                    'status'  => 0,
                    'error'   => 'Missing SellerPartNumber or non-positive price',
                    'raw'     => null,
                ];
                $failed++;
                continue;
            }

            $country  = strtoupper((string) ($i['country']  ?? $defaultCountry));
            $currency = strtoupper((string) ($i['currency'] ?? ($this->defaultCurrencyForCountry($country) ?? 'USD')));
            $expectedCurrency = $this->defaultCurrencyForCountry($country);
            if ($expectedCurrency !== null && $currency !== $expectedCurrency) {
                $results[] = [
                    'seller_part_number' => $spn,
                    'success' => false,
                    'status'  => 0,
                    'error'   => "Currency {$currency} does not match CountryCode {$country} (expected {$expectedCurrency})",
                    'raw'     => null,
                ];
                $failed++;
                continue;
            }

            $priceRow = [
                'CountryCode'  => $country,
                'Currency'     => $currency,
                'SellingPrice' => number_format($price, 2, '.', ''),
            ];
            if (isset($i['msrp']) && (float) $i['msrp'] > 0) {
                $priceRow['MSRP'] = number_format((float) $i['msrp'], 2, '.', '');
            }
            if (isset($i['map']) && (float) $i['map'] >= 0) {
                $priceRow['MAP'] = number_format((float) $i['map'], 2, '.', '');
            }
            if (isset($i['checkout_map'])) {
                $priceRow['CheckoutMAP'] = $i['checkout_map'] ? '1' : '0';
            }
            if (isset($i['active'])) {
                $priceRow['Active'] = $i['active'] ? '1' : '0';
            }

            $body = [
                'Type'      => '1',
                'Value'     => $spn,
                'PriceList' => [$priceRow],
            ];
            if (!empty($i['condition'])) {
                $body['Condition'] = (string) $i['condition'];
            }

            $res = $this->request('POST', '/marketplace/contentmgmt/item/international/price', [], $body);

            $ok = $this->extractItemSuccess($res);
            $err = $ok ? null : $this->extractItemError($res);
            if ($res['blocked_by_cloudflare']) {
                $blockedAny = true;
                $err = 'Cloudflare managed challenge (IP not whitelisted for writes).';
            }

            $results[] = [
                'seller_part_number' => $spn,
                'success' => $ok,
                'status'  => $res['status'],
                'error'   => $err,
                'raw'     => $res['raw'],
            ];
            if ($ok) {
                $pushed++;
            } else {
                $failed++;
            }
        }

        return [
            'ok'                    => $pushed > 0,
            'pushed'                => $pushed,
            'failed'                => $failed,
            'blocked_by_cloudflare' => $blockedAny,
            'error_message'         => $pushed === 0 ? ($results[0]['error'] ?? 'No items pushed') : null,
            'results'               => $results,
        ];
    }

    /**
     * Did Newegg's per-item response indicate success? Tolerates both the
     * legacy {NeweggAPIResponse:{IsSuccess:"true",...}} envelope and the
     * plainer {IsSuccess:true,...} flat response.
     *
     * @param  array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}  $res
     */
    private function extractItemSuccess(array $res): bool
    {
        if ($res['blocked_by_cloudflare']) {
            return false;
        }
        if (!$res['ok']) {
            // 'ok' here only means HTTP success + JSON; we'll fall through to inspect IsSuccess.
        }
        $j = $res['json'];
        if (!is_array($j)) {
            return false;
        }
        $flag = $j['NeweggAPIResponse']['IsSuccess'] ?? ($j['IsSuccess'] ?? null);
        if ($flag === null) {
            // No IsSuccess field but HTTP 200 + JSON → treat as success.
            return $res['status'] >= 200 && $res['status'] < 300;
        }
        return $flag === true || strtolower((string) $flag) === 'true';
    }

    /**
     * Pull a human-readable error out of whichever shape Newegg returned.
     *
     * @param  array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}  $res
     */
    private function extractItemError(array $res): string
    {
        $j = $res['json'];
        if (is_array($j)) {
            // Flat array form: [{"Code":"CE003","Message":"..."}]
            if (isset($j[0]['Message'])) {
                return (string) $j[0]['Message'];
            }
            // Envelope form: {NeweggAPIResponse:{Errors:[{Description}]}}
            $errs = data_get($j, 'NeweggAPIResponse.Errors', null);
            if (is_array($errs)) {
                $first = isset($errs['Description']) ? $errs : (is_array(reset($errs)) ? reset($errs) : null);
                if (is_array($first)) {
                    $msg = (string) ($first['Description'] ?? ($first['Message'] ?? ''));
                    if ($msg !== '') {
                        return $msg;
                    }
                }
            }
        }
        if (!empty($res['error'])) {
            return (string) $res['error'];
        }
        return 'HTTP ' . $res['status'];
    }

    /**
     * Newegg requires CountryCode ↔ Currency alignment. Returns the canonical
     * currency for a Newegg-supported destination country (null for unknown so
     * callers can pass through whatever they were given).
     */
    private function defaultCurrencyForCountry(string $country): ?string
    {
        return [
            'USA' => 'USD',
            'CAN' => 'CAD',
            'CHN' => 'CNY',
            'JPN' => 'JPY',
            'GBR' => 'GBP',
            'AUS' => 'AUD',
        ][strtoupper($country)] ?? null;
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
