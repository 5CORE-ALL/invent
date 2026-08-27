<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

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
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;

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
     * MM adapter: product detail by Newegg Item # or Seller Part #.
     * Returns AE-shaped envelope for SyncController compatibility.
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function getProductInfo(string $productId): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['success' => false, 'message' => 'Empty product id'];
        }

        // Prefer Seller Part # lookup (type 1); Item # is type 0 when it looks like 9SI…
        $type = str_starts_with(strtoupper($productId), '9SI') ? 0 : 1;
        $inv = $this->getItemInventory($productId, $type);
        $price = $this->getItemPrice($productId, ['USA'], $type);

        if (! empty($inv['blocked_by_cloudflare']) || ! empty($price['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }

        return [
            'success' => true,
            'data' => [
                'product_id' => $productId,
                'inventory' => $inv['json'] ?? null,
                'price' => $price['json'] ?? null,
            ],
        ];
    }

    /**
     * Match AliExpress SyncController call shape:
     *   extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null)
     *
     * @param  array<string, mixed>  $info  from getProductInfo()['data']
     * @return list<array{sku: string, product_id: string, inventory?: int|null, price?: float|null, product_name?: ?string}>
     */
    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $productId = trim($productId);
        $sku = trim((string) ($info['sku'] ?? $info['SellerPartNumber'] ?? $info['seller_part_number'] ?? ''));

        // Inventory payload shapes vary by Newegg tenant.
        $invJson = is_array($info['inventory'] ?? null) ? $info['inventory'] : $info;
        $qty = data_get($invJson, 'Inventory')
            ?? data_get($invJson, 'AvailableQuantity')
            ?? data_get($invJson, 'NeweggAPIResponse.ResponseBody.Inventory')
            ?? data_get($invJson, 'ResponseBody.Inventory');

        $priceJson = is_array($info['price'] ?? null) ? $info['price'] : [];
        $price = data_get($priceJson, 'PriceList.Price.0.SellingPrice')
            ?? data_get($priceJson, 'PriceList.0.SellingPrice')
            ?? data_get($priceJson, 'SellingPrice');

        if ($sku === '') {
            // Seller Part # lookup when product_id was an Item #, fall back to product_id.
            $sku = trim((string) (
                data_get($invJson, 'SellerPartNumber')
                ?? data_get($priceJson, 'SellerPartNumber')
                ?? $productId
            ));
        }

        $itemNumber = trim((string) (
            data_get($invJson, 'ItemNumber')
            ?? data_get($priceJson, 'ItemNumber')
            ?? ($info['product_id'] ?? '')
            ?? $productId
        ));

        return [[
            'sku' => $sku !== '' ? $sku : $productId,
            'product_id' => $itemNumber !== '' ? $itemNumber : $productId,
            'inventory' => is_numeric($qty) ? (int) $qty : null,
            'price' => is_numeric($price) ? (float) $price : null,
            'product_name' => $productName,
        ]];
    }

    /**
     * @param  mixed  $item
     * @return list<array{sku: string, product_id: string}>
     */
    public function extractSkuRowsFromListItem($item, bool $fetchDetail = false): array
    {
        if (! is_array($item)) {
            return [];
        }
        $sku = trim((string) ($item['sku'] ?? $item['SellerPartNumber'] ?? ''));
        $pid = trim((string) ($item['product_id'] ?? $item['NeweggItemNumber'] ?? $sku));
        if ($sku === '') {
            return [];
        }

        return [['sku' => $sku, 'product_id' => $pid !== '' ? $pid : $sku]];
    }

    /**
     * True when Seller ID + API key + secret key are present in config.
     */
    public function isConfigured(): bool
    {
        return filled($this->sellerId) && filled($this->apiKey) && filled($this->secretKey);
    }

    public function getSellerId(): ?string
    {
        return filled($this->sellerId) ? (string) $this->sellerId : null;
    }

    /**
     * Ship Order (Action 2) — mark SBS order shipped with tracking.
     *   PUT /marketplace/ordermgmt/orderstatus/orders/{ordernumber}?sellerid=XXXX&version=304
     *
     * @param  list<array{seller_part_number: string, quantity: int, newegg_item_number?: string|null}>  $items
     * @return array{success: bool, message: string, order_status?: string|null, blocked_by_cloudflare?: bool, raw?: string|null}
     */
    public function shipOrder(
        string $orderNumber,
        string $trackingNumber,
        string $shipCarrier,
        string $shipService,
        array $items
    ): array {
        $orderNumber = trim($orderNumber);
        $trackingNumber = trim($trackingNumber);
        $shipCarrier = trim($shipCarrier);
        $shipService = trim($shipService);
        $sellerId = $this->getSellerId();

        if ($orderNumber === '' || $trackingNumber === '' || $sellerId === null) {
            return [
                'success' => false,
                'message' => 'Order number, tracking number, and seller ID are required.',
            ];
        }

        if ($shipCarrier === '') {
            $shipCarrier = 'Other Carrier';
        }
        if ($shipService === '') {
            $shipService = 'Other Service';
        }

        $packageItems = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['seller_part_number'] ?? ''));
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
                continue;
            }
            $row = [
                'SellerPartNumber' => $sku,
                'ShippedQty' => (string) $qty,
            ];
            $neItem = trim((string) ($item['newegg_item_number'] ?? ''));
            if ($neItem !== '') {
                $row['NeweggItemNumber'] = $neItem;
            }
            $packageItems[] = $row;
        }

        if ($packageItems === []) {
            return [
                'success' => false,
                'message' => 'No shippable Newegg line items (Seller Part #) found for this order.',
            ];
        }

        $itemList = count($packageItems) === 1
            ? ['Item' => $packageItems[0]]
            : ['Item' => $packageItems];

        $body = [
            'Action' => '2',
            'Value' => [
                'Shipment' => [
                    'Header' => [
                        'SellerID' => $sellerId,
                        'SONumber' => $orderNumber,
                    ],
                    'PackageList' => [
                        'Package' => [[
                            'TrackingNumber' => $trackingNumber,
                            'ShipCarrier' => $shipCarrier,
                            'ShipService' => $shipService,
                            'ItemList' => $itemList,
                        ]],
                    ],
                ],
            ],
        ];

        $res = $this->request(
            'PUT',
            '/marketplace/ordermgmt/orderstatus/orders/'.$orderNumber,
            ['version' => '304'],
            $body
        );

        if (! empty($res['blocked_by_cloudflare'])) {
            return [
                'success' => false,
                'message' => 'Blocked by Cloudflare',
                'blocked_by_cloudflare' => true,
                'raw' => $res['raw'] ?? null,
            ];
        }

        $json = is_array($res['json'] ?? null) ? $res['json'] : null;
        $isSuccess = false;
        if (is_array($json)) {
            $flag = $json['IsSuccess'] ?? data_get($json, 'ResponseBody.IsSuccess');
            $isSuccess = $flag === true || $flag === 'true' || $flag === 1 || $flag === '1';
            $failCount = (int) (data_get($json, 'PackageProcessingSummary.FailCount') ?? 0);
            if ($failCount > 0) {
                $isSuccess = false;
            }
        }

        if (! empty($res['ok']) && $isSuccess) {
            return [
                'success' => true,
                'message' => 'Newegg order marked shipped.',
                'order_status' => (string) (data_get($json, 'Result.OrderStatus') ?? ''),
                'raw' => $res['raw'] ?? null,
            ];
        }

        $error = $this->extractItemError($res);
        if ($error === '' && is_array($json)) {
            $code = (string) (data_get($json, '0.Code') ?? data_get($json, 'Errors.Error.Code') ?? '');
            $msg = (string) (data_get($json, '0.Message') ?? data_get($json, 'Errors.Error.Message') ?? '');
            $error = trim($code.($msg !== '' ? ': '.$msg : ''));
        }
        if ($error === '') {
            $error = 'HTTP '.($res['status'] ?? 0).': '.mb_substr((string) ($res['raw'] ?? ''), 0, 300);
        }

        return [
            'success' => false,
            'message' => $error,
            'raw' => $res['raw'] ?? null,
        ];
    }

    /**
     * Update inventory for one Seller Part # (USA B2C contentmgmt).
     *   PUT /marketplace/contentmgmt/item/inventoryandprice?sellerid=XXXX
     * Inventory-only body — does not change Active / price.
     *
     * @return array{success:bool,message:string,sku:string,quantity:int,raw:?string,blocked_by_cloudflare:bool}
     */
    public function updateItemInventory(string $sellerPartNumber, int $quantity): array
    {
        $sellerPartNumber = trim($sellerPartNumber);
        $quantity = max(0, (int) $quantity);

        if ($sellerPartNumber === '') {
            return [
                'success' => false,
                'message' => 'SellerPartNumber is required.',
                'sku' => $sellerPartNumber,
                'quantity' => $quantity,
                'raw' => null,
                'blocked_by_cloudflare' => false,
            ];
        }

        $bulk = $this->updateItemInventoryBulk([
            ['seller_part_number' => $sellerPartNumber, 'quantity' => $quantity],
        ]);
        $first = $bulk['results'][0] ?? null;

        if ($bulk['blocked_by_cloudflare']) {
            return [
                'success' => false,
                'message' => 'Blocked by Cloudflare (managed challenge). Whitelist this server IP in the Newegg Seller Portal.',
                'sku' => $sellerPartNumber,
                'quantity' => $quantity,
                'raw' => $first['raw'] ?? null,
                'blocked_by_cloudflare' => true,
            ];
        }

        if (! ($first['success'] ?? false)) {
            return [
                'success' => false,
                'message' => (string) ($first['error'] ?? ($bulk['error_message'] ?? 'Inventory update failed')),
                'sku' => $sellerPartNumber,
                'quantity' => $quantity,
                'raw' => $first['raw'] ?? null,
                'blocked_by_cloudflare' => false,
            ];
        }

        return [
            'success' => true,
            'message' => "Inventory {$quantity} pushed to Newegg for SPN: {$sellerPartNumber}.",
            'sku' => $sellerPartNumber,
            'quantity' => $quantity,
            'raw' => $first['raw'] ?? null,
            'blocked_by_cloudflare' => false,
        ];
    }

    /**
     * @param  list<array{seller_part_number:string,quantity:int|string}>  $items
     * @return array{ok:bool,pushed:int,failed:int,blocked_by_cloudflare:bool,error_message:?string,results:list<array{seller_part_number:string,success:bool,status:int,error:?string,raw:?string}>}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        $results = [];
        $pushed = 0;
        $failed = 0;
        $blockedAny = false;

        foreach ($items as $i) {
            $spn = trim((string) ($i['seller_part_number'] ?? ''));
            $qty = max(0, (int) ($i['quantity'] ?? 0));
            if ($spn === '') {
                $results[] = [
                    'seller_part_number' => $spn,
                    'success' => false,
                    'status' => 0,
                    'error' => 'Missing SellerPartNumber',
                    'raw' => null,
                ];
                $failed++;
                continue;
            }

            $res = $this->request('PUT', '/marketplace/contentmgmt/item/inventoryandprice', [], [
                'Type' => '1',
                'Value' => $spn,
                'Inventory' => (string) $qty,
            ]);

            $ok = false;
            $err = null;
            if ($res['blocked_by_cloudflare']) {
                $blockedAny = true;
                $err = 'Cloudflare managed challenge (IP not whitelisted for writes).';
            } else {
                $j = $res['json'];
                $resultFlag = data_get($j, 'UpdateInventoryAndPriceResult.Result')
                    ?? data_get($j, 'Result');
                if ($resultFlag === 1 || $resultFlag === '1' || $resultFlag === true) {
                    $ok = true;
                } elseif ($this->extractItemSuccess($res) && $resultFlag === null) {
                    // Some tenants return IsSuccess without Result wrapper.
                    $ok = true;
                } else {
                    $err = $this->extractItemError($res);
                    if ($err === 'HTTP '.$res['status'] && is_array($j)) {
                        $err = json_encode($j);
                    }
                }
            }

            $results[] = [
                'seller_part_number' => $spn,
                'success' => $ok,
                'status' => $res['status'],
                'error' => $ok ? null : $err,
                'raw' => $res['raw'],
            ];
            if ($ok) {
                $pushed++;
            } else {
                $failed++;
            }
        }

        return [
            'ok' => $pushed > 0,
            'pushed' => $pushed,
            'failed' => $failed,
            'blocked_by_cloudflare' => $blockedAny,
            'error_message' => $pushed === 0 ? ($results[0]['error'] ?? 'No items pushed') : null,
            'results' => $results,
        ];
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

            // Newegg's XSD: <PriceList><Price>…</Price></PriceList>.  In JSON the
            // repeating <Price> element maps to a "Price" array inside "PriceList".
            $body = [
                'Type'      => '1',
                'Value'     => $spn,
                'PriceList' => [
                    'Price' => [$priceRow],
                ],
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

    /**
     * @param  array<string, mixed>  $itemFields
     * @return array{success: bool, message: string}
     */
    protected function pushItemContent(string $sku, array $itemFields): array
    {
        $sku = trim($sku);
        if ($sku === '' || $itemFields === []) {
            return ['success' => false, 'message' => 'SKU and content fields are required.'];
        }

        if (! $this->sellerId || ! $this->apiKey || ! $this->secretKey) {
            return ['success' => false, 'message' => 'Newegg API credentials are not configured.'];
        }

        $lastMessage = 'Newegg content update failed.';
        $paths = [
            '/marketplace/contentmgmt/item/basicinfo',
            '/marketplace/contentmgmt/item/update',
        ];
        foreach ($this->neweggSkuCandidates($sku) as $candidate) {
            $body = ['Item' => array_merge(['SellerPartNumber' => $candidate], $itemFields)];
            foreach ($paths as $path) {
                $res = $this->request('PUT', $path, [], $body);
                if ($this->extractItemSuccess($res)) {
                    return ['success' => true, 'message' => 'Newegg product content updated.'];
                }
                $lastMessage = $this->extractItemError($res);
            }
        }

        $feed = $this->submitItemBasicInfoFeed($sku, $itemFields);
        if ($feed['success'] ?? false) {
            return $feed;
        }
        if (trim((string) ($feed['message'] ?? '')) !== '') {
            $lastMessage = (string) $feed['message'];
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @return list<string>
     */
    protected function neweggSkuCandidates(string $sku): array
    {
        $sku = trim($sku);
        $out = [];
        foreach ([
            $sku,
            str_replace(' ', '', $sku),
            preg_replace('/\s+/', '-', $sku) ?: '',
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * Newegg title/content REST paths often 404; ITEM_DATA feed is the documented write API.
     *
     * @param  array<string, mixed>  $itemFields
     * @return array{success: bool, message: string}
     */
    protected function submitItemBasicInfoFeed(string $sku, array $itemFields): array
    {
        if (! $this->sellerId || ! $this->apiKey || ! $this->secretKey) {
            return ['success' => false, 'message' => 'Newegg API credentials are not configured.'];
        }

        $title = trim((string) ($itemFields['WebsiteShortTitle'] ?? $itemFields['Title'] ?? ''));
        $sellerPart = htmlspecialchars($this->neweggSkuCandidates($sku)[0] ?? $sku, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $safeTitle = str_replace(']]>', ']] >', $title);
        $imageXml = $this->neweggItemImagesXml($itemFields);
        $titleXml = $title !== ''
            ? '<WebsiteShortTitle><![CDATA['.$safeTitle.']]></WebsiteShortTitle>'
            : '';
        if ($titleXml === '' && $imageXml === '') {
            return ['success' => false, 'message' => 'No Newegg title or image fields to submit.'];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<NeweggEnvelope>'
            .'<Header><DocumentVersion>2.0</DocumentVersion></Header>'
            .'<MessageType>BatchItemCreation</MessageType>'
            .'<Message><Itemfeed><Item>'
            .'<Action>UpdateItem</Action>'
            .'<BasicInfo>'
            .'<SellerPartNumber>'.$sellerPart.'</SellerPartNumber>'
            .$titleXml
            .$imageXml
            .'</BasicInfo>'
            .'</Item></Itemfeed></Message>'
            .'</NeweggEnvelope>';

        $url = $this->baseUrl.'/marketplace/datafeedmgmt/feeds/submitfeed?'
            .http_build_query([
                'sellerid' => $this->sellerId,
                'requesttype' => 'ITEM_DATA',
            ]);
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'SecretKey' => $this->secretKey,
                'Content-Type' => 'application/xml',
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withBody($xml, 'application/xml')
                ->post($url);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Newegg feed submit failed: '.$e->getMessage()];
        }

        if ($response->successful() || in_array($response->status(), [200, 201, 202], true)) {
            $json = $response->json();
            if (! is_array($json)) {
                return ['success' => false, 'message' => $this->extractItemError($this->normalize($response))];
            }
            if (! empty($json[0]['Message']) || data_get($json, 'NeweggAPIResponse.IsSuccess') === false) {
                return ['success' => false, 'message' => $this->extractItemError($this->normalize($response))];
            }
            $requestId = (string) (data_get($json, 'NeweggAPIResponse.ResponseBody.ResponseList.0.RequestId')
                ?? data_get($json, 'ResponseBody.ResponseList.0.RequestId')
                ?? '');

            return [
                'success' => true,
                'message' => $requestId !== ''
                    ? 'Newegg item feed submitted (RequestId '.$requestId.').'
                    : 'Newegg item feed submitted.',
            ];
        }

        $normalized = $this->normalize($response);

        return ['success' => false, 'message' => $this->extractItemError($normalized)];
    }

    /**
     * @param  array<string, mixed>  $itemFields
     */
    protected function neweggItemImagesXml(array $itemFields): string
    {
        $urls = [];
        foreach (['Image', 'PrimaryImage', 'ImageUrl'] as $key) {
            $value = trim((string) ($itemFields[$key] ?? ''));
            if ($value !== '' && preg_match('#^https?://#i', $value)) {
                $urls[] = $value;
            }
        }
        if (! empty($itemFields['ItemImages']) && is_array($itemFields['ItemImages'])) {
            foreach ($itemFields['ItemImages'] as $img) {
                if (is_string($img) && preg_match('#^https?://#i', trim($img))) {
                    $urls[] = trim($img);
                } elseif (is_array($img)) {
                    $url = trim((string) ($img['ImageUrl'] ?? $img['Url'] ?? $img['url'] ?? ''));
                    if ($url !== '' && preg_match('#^https?://#i', $url)) {
                        $urls[] = $url;
                    }
                }
            }
        }
        if (! empty($itemFields['AdditionalImages']) && is_array($itemFields['AdditionalImages'])) {
            foreach ($itemFields['AdditionalImages'] as $img) {
                if (is_string($img) && preg_match('#^https?://#i', trim($img))) {
                    $urls[] = trim($img);
                }
            }
        }
        $urls = array_values(array_unique($urls));
        if ($urls === []) {
            return '';
        }

        $xml = '<ItemImages>';
        foreach ($urls as $i => $url) {
            $safe = htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $primary = $i === 0 ? 'true' : 'false';
            $xml .= '<Image><ImageUrl>'.$safe.'</ImageUrl><IsPrimary>'.$primary.'</IsPrimary></Image>';
        }

        return $xml.'</ItemImages>';
    }

    public function updateTitle(string $sku, string $title): array
    {
        return $this->pushItemContent($sku, [
            'WebsiteShortTitle' => $title,
            'Title' => $title,
        ]);
    }

    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        return $this->pushItemContent($identifier, [
            'BulletDescription' => $bulletPoints,
        ]);
    }

    public function updateProductDescription(string $identifier, string $description): array
    {
        return $this->updateDescription($identifier, $description);
    }

    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->pushItemContent($identifier, [
            'ItemDescription' => $description,
            'ProductDescription' => $description,
        ]);
    }

    /**
     * @param  list<string>  $images
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_values(array_filter(array_map('trim', $images), fn ($v) => $v !== ''));
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        return $this->pushItemContent($identifier, [
            'Image' => $images[0],
            'PrimaryImage' => $images[0],
            'ImageUrl' => $images[0],
            'ItemImages' => array_map(fn ($url, $i) => [
                'ImageUrl' => $url,
                'IsPrimary' => $i === 0 ? 'true' : 'false',
            ], $images, array_keys($images)),
            'AdditionalImages' => array_slice($images, 1),
        ]);
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 1);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'Seller part number and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        if (! $this->sellerId || ! $this->apiKey || ! $this->secretKey) {
            return ['success' => false, 'message' => 'Newegg API credentials are not configured.'];
        }

        $sku = trim($identifier);
        $body = [
            'Item' => [
                'SellerPartNumber' => $sku,
                'VideoUrl' => $videos[0],
                'ProductVideoUrl' => $videos[0],
            ],
        ];

        $paths = [
            "/contentmgmt/item/basicinfo?sellerid={$this->sellerId}",
            "/contentmgmt/item/update?sellerid={$this->sellerId}",
        ];

        $lastMessage = 'Newegg video update failed.';
        foreach ($paths as $path) {
            $res = $this->request('PUT', $path, [], $body);
            if ($this->extractItemSuccess($res)) {
                $this->saveVideoUrlsToMetricsRow('newegg_metrics', $sku, $videos);

                return ['success' => true, 'message' => 'Newegg product video updated.', 'normalized_urls' => $videos];
            }
            $lastMessage = $this->extractItemError($res);
        }

        return ['success' => false, 'message' => $lastMessage];
    }
}
