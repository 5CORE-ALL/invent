<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push Shopify tracking to eBay via Sell Fulfillment createShippingFulfillment.
 */
class EbaySellFulfillmentTracking
{
    public function __construct(
        protected ShopifyStoreSelector $stores,
    ) {}

    /**
     * @param  object{
     *   order_id?: mixed,
     *   shopify_order_id?: mixed,
     *   raw_payload?: mixed,
     *   status?: mixed,
     *   quantity?: mixed
     * }  $line
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null
     * }
     */
    public function pushForChannel(string $channel, object $line): array
    {
        $channel = strtolower(trim($channel));
        $orderId = trim((string) ($line->order_id ?? ''));
        if ($orderId === '') {
            return ['success' => false, 'message' => 'eBay order id missing.'];
        }

        $status = strtoupper(trim((string) ($line->status ?? '')));
        if (in_array($status, ['CANCELLED', 'CANCELED', 'INVALID'], true)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'cancelled',
                'message' => 'Cancelled eBay orders are not shipped from Shopify tracking.',
            ];
        }

        $shopifyOrderId = trim((string) ($line->shopify_order_id ?? ''));
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $shopify = $this->fetchShopifyTracking($channel, $shopifyOrderId, $line);
        $tracking = trim((string) ($shopify['tracking'] ?? ''));
        $carrier = trim((string) ($shopify['carrier'] ?? ''));
        if ($tracking === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No tracking number on Shopify yet. Buy/download a shipping label in Shopify first.',
                'shopify_tracking' => null,
                'shopify_carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        $token = $this->accessToken($channel);
        if ($token === null || $token === '') {
            return ['success' => false, 'message' => 'eBay API credentials missing or token failed.'];
        }

        $candidateIds = $this->ebayOrderIdCandidates($line);
        $order = null;
        $ebayOrderId = $orderId;
        foreach ($candidateIds as $candidateId) {
            $loaded = $this->getEbayOrder($token, $candidateId);
            if (is_array($loaded) && $loaded !== []) {
                $order = $loaded;
                $ebayOrderId = trim((string) ($loaded['orderId'] ?? $candidateId));
                break;
            }
        }
        if ($order === null) {
            $raw = is_array($line->raw_payload ?? null) ? $line->raw_payload : [];
            $order = $raw !== [] ? $raw : null;
            if (is_array($order) && trim((string) ($order['orderId'] ?? '')) !== '') {
                $ebayOrderId = trim((string) $order['orderId']);
            }
        }
        if (! is_array($order) || $order === []) {
            return [
                'success' => false,
                'message' => 'Could not load eBay order to attach tracking.',
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        $fulfillmentStatus = strtoupper(trim((string) ($order['orderFulfillmentStatus'] ?? $status)));
        if ($this->alreadyHasTracking($token, $ebayOrderId, $order, $tracking)) {
            $this->rememberPushedTracking($line, $tracking, $this->mapCarrier($carrier));

            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_shipped',
                'message' => 'eBay already has this Shopify tracking number.',
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        $lineItems = $this->lineItemsForFulfillment($order, $line);
        if ($lineItems === []) {
            $refreshed = $this->getEbayOrder($token, $ebayOrderId);
            if (is_array($refreshed) && $refreshed !== []) {
                $order = $refreshed;
                $lineItems = $this->lineItemsForFulfillment($order, $line);
            }
        }
        if ($lineItems === []) {
            return [
                'success' => false,
                'message' => 'No eBay lineItemId found to create a shipping fulfillment.',
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        $carrierCode = $this->mapCarrier($carrier);
        $payload = [
            'lineItems' => $lineItems,
            'shippedDate' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'shippingCarrierCode' => $carrierCode,
            'trackingNumber' => $tracking,
        ];

        $response = null;
        $lastError = '';
        foreach ($this->uniqueEbayIds(array_merge([$ebayOrderId], $candidateIds)) as $postId) {
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Content-Language' => 'en-US',
                    ])
                    ->timeout(45)
                    ->post(
                        'https://api.ebay.com/sell/fulfillment/v1/order/'.rawurlencode($postId).'/shipping_fulfillment',
                        $payload
                    );
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('EbaySellFulfillmentTracking: request failed', [
                    'channel' => $channel,
                    'order_id' => $postId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($response->successful() || in_array($response->status(), [201, 204], true)) {
                $this->rememberPushedTracking($line, $tracking, $carrierCode);
                Log::info('EbaySellFulfillmentTracking: tracking pushed', [
                    'channel' => $channel,
                    'order_id' => $postId,
                    'tracking' => $tracking,
                    'carrier' => $carrierCode,
                ]);

                return [
                    'success' => true,
                    'action' => 'ship',
                    'message' => "Marked eBay order shipped with tracking {$tracking} ({$carrierCode}).",
                    'shopify_tracking' => $tracking,
                    'shopify_carrier' => $carrier !== '' ? $carrier : $carrierCode,
                ];
            }

            $body = (string) $response->body();
            $lastError = $body !== '' ? $body : 'HTTP '.$response->status();
            if ($this->looksAlreadyShipped($body, $fulfillmentStatus)) {
                $this->rememberPushedTracking($line, $tracking, $carrierCode);

                return [
                    'success' => true,
                    'skipped' => true,
                    'action' => 'already_shipped',
                    'message' => 'eBay already has a shipment for this order.',
                    'shopify_tracking' => $tracking,
                    'shopify_carrier' => $carrier !== '' ? $carrier : null,
                ];
            }
            if (! in_array($response->status(), [400, 404], true)) {
                break;
            }
        }

        $trading = $this->completeSaleViaTradingApi($channel, $token, $candidateIds, $tracking, $carrierCode);
        if (! empty($trading['success'])) {
            $this->rememberPushedTracking($line, $tracking, $carrierCode);
            Log::info('EbaySellFulfillmentTracking: tracking pushed via CompleteSale', [
                'channel' => $channel,
                'order_id' => $orderId,
                'tracking' => $tracking,
                'carrier' => $carrierCode,
            ]);

            return [
                'success' => true,
                'action' => 'ship',
                'message' => "Marked eBay order shipped with tracking {$tracking} ({$carrierCode}).",
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier !== '' ? $carrier : $carrierCode,
            ];
        }

        $body = $lastError !== '' ? $lastError : (string) ($trading['message'] ?? 'eBay tracking push failed.');
        Log::warning('EbaySellFulfillmentTracking: eBay rejected tracking', [
            'channel' => $channel,
            'order_id' => $orderId,
            'status' => $response?->status(),
            'body' => mb_substr($body, 0, 800),
        ]);

        return [
            'success' => false,
            'action' => 'ship',
            'message' => 'eBay tracking push failed: '.mb_substr($body, 0, 400),
            'shopify_tracking' => $tracking,
            'shopify_carrier' => $carrier !== '' ? $carrier : null,
        ];
    }

    /**
     * Scan linked Shopify copies from the last 90 days. Newest unfulfilled eBay
     * orders first so older labeled orders are not starved by a short ID window.
     *
     * @param  class-string  $modelClass
     * @return array{success: bool, processed: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPending(string $channel, string $modelClass, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $since = now()->subDays(90);
        $table = (new $modelClass)->getTable();

        $query = $modelClass::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where('shopify_order_id', 'not like', 'manual%')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['CANCELLED', 'CANCELED', 'INVALID']);
            });

        if (Schema::hasColumn($table, 'order_date')) {
            $query->where(function ($q) use ($since, $table) {
                $q->where('order_date', '>=', $since);
                if (Schema::hasColumn($table, 'created_at')) {
                    $q->orWhere('created_at', '>=', $since);
                }
            });
        } elseif (Schema::hasColumn($table, 'created_at')) {
            $query->where('created_at', '>=', $since);
        }

        $rows = $query
            ->orderBy('order_date')
            ->orderBy('id')
            ->limit($limit * 16)
            ->get();

        $ranked = $rows->sortBy(function ($row) {
            $raw = is_array($row->raw_payload ?? null) ? $row->raw_payload : [];
            $pushed = trim((string) ($raw['shopify_tracking_pushed'] ?? ''));
            $localTn = trim((string) ($raw['tracking_number'] ?? ''));
            $status = strtoupper(trim((string) ($row->status ?? '')));
            if ($pushed !== '') {
                return 4;
            }
            if (strlen($localTn) >= 8) {
                return 0;
            }
            if (in_array($status, ['FULFILLED', 'SHIPPED'], true)) {
                return 3;
            }

            return 1;
        })->values();

        $unique = [];
        foreach ($ranked as $row) {
            $orderId = trim((string) ($row->order_id ?? ''));
            if ($orderId === '' || isset($unique[$orderId])) {
                continue;
            }
            $raw = is_array($row->raw_payload ?? null) ? $row->raw_payload : [];
            if (trim((string) ($raw['shopify_tracking_pushed'] ?? '')) !== '') {
                continue;
            }
            $unique[$orderId] = $row;
            if (count($unique) >= $limit) {
                break;
            }
        }

        $processed = 0;
        $pushed = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($unique as $line) {
            $processed++;
            $result = $this->pushForChannel($channel, $line);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $pushed++;
            } elseif (! empty($result['skipped'])) {
                $skipped++;
            } else {
                $failed++;
            }
            usleep(250000);
        }

        Log::info('EbaySellFulfillmentTracking: pending sync completed', [
            'channel' => $channel,
            'processed' => $processed,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return [
            'success' => $failed === 0,
            'processed' => $processed,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Tracking sync: checked {$processed}, pushed {$pushed}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * @return array{tracking: ?string, carrier: ?string}
     */
    protected function fetchShopifyTracking(string $channel, string $shopifyOrderId, ?object $line = null): array
    {
        $settings = MarketplaceSyncSettings::getFor($channel);
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');
        $config = $this->stores->getConfigForStore($storeKey);
        $storeUrl = (string) ($config['store_url'] ?? '');
        $token = (string) ($config['token'] ?? '');
        if ($storeUrl === '' || $token === '') {
            return ['tracking' => null, 'carrier' => null];
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2025-01/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,name,fulfillments,fulfillment_status',
            ]);
            if (! $response->successful()) {
                return ['tracking' => null, 'carrier' => null];
            }
            $fulfillments = $response->json('order.fulfillments') ?? [];
            if (! is_array($fulfillments)) {
                $fulfillments = [];
            }
            foreach ($fulfillments as $fulfillment) {
                if (! is_array($fulfillment)) {
                    continue;
                }
                $status = strtolower((string) ($fulfillment['status'] ?? ''));
                if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                    continue;
                }
                $number = '';
                if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                    $number = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
                }
                if ($number === '' && ! empty($fulfillment['tracking_number'])) {
                    $number = trim((string) $fulfillment['tracking_number']);
                }
                if ($number === '') {
                    continue;
                }

                return [
                    'tracking' => $number,
                    'carrier' => trim((string) ($fulfillment['tracking_company'] ?? '')) ?: null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('EbaySellFulfillmentTracking: Shopify tracking fetch failed', [
                'channel' => $channel,
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        $raw = is_array($line?->raw_payload ?? null) ? $line->raw_payload : [];
        $local = trim((string) ($raw['tracking_number'] ?? ''));
        if ($local !== '') {
            return [
                'tracking' => $local,
                'carrier' => trim((string) ($raw['carrier'] ?? $raw['carrier_name'] ?? '')) ?: null,
            ];
        }

        return ['tracking' => null, 'carrier' => null];
    }

    protected function rememberPushedTracking(object $line, string $tracking, string $carrier): void
    {
        if (! isset($line->raw_payload) || ! method_exists($line, 'save')) {
            return;
        }
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        $raw['tracking_number'] = $tracking;
        if ($carrier !== '') {
            $raw['carrier'] = $carrier;
        }
        $raw['shopify_tracking_pushed'] = $tracking;
        $raw['shopify_tracking_pushed_at'] = now()->toIso8601String();
        $line->raw_payload = $raw;
        try {
            $line->save();
        } catch (\Throwable $e) {
            Log::debug('EbaySellFulfillmentTracking: could not persist pushed tracking', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read tracking already declared on eBay (shipping_fulfillment + order payload).
     *
     * @return array{tracking: string, carrier: string}|null
     */
    public function readTrackingFromEbay(string $channel, string $orderId): ?array
    {
        $channel = strtolower(trim($channel));
        $orderId = trim($orderId);
        if ($orderId === '') {
            return null;
        }

        $token = $this->accessToken($channel);
        if ($token === null || $token === '') {
            return null;
        }

        $fulfillments = $this->listShippingFulfillments($token, $orderId);
        $fromList = self::trackingFromEbayPayload(['fulfillments' => $fulfillments]);
        if ($fromList !== null) {
            return $fromList;
        }

        $order = $this->getEbayOrder($token, $orderId);

        return is_array($order) ? self::trackingFromEbayPayload($order) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{tracking: string, carrier: string}|null
     */
    public static function trackingFromEbayPayload(array $payload): ?array
    {
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
            if ($tracking === null && preg_match('/^(tracking(_)?(number|no|id)?|shipmenttrackingnumber)$/', $k)) {
                $tn = strtoupper(preg_replace('/[\s\-]/', '', $s) ?? '');
                if (strlen($tn) >= 8 && ! preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn)) {
                    $tracking = $tn;
                }
            }
            if ($carrier === '' && preg_match('/carrier|shippingcarriercode|shipping.?service/', $k) && ! is_numeric($s)) {
                $carrier = $s;
            }
        };
        $walk($payload);

        if ($tracking === null) {
            return null;
        }

        return ['tracking' => $tracking, 'carrier' => $carrier !== '' ? $carrier : 'eBay'];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getEbayOrder(string $token, string $orderId): ?array
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ])
                ->timeout(45)
                ->get('https://api.ebay.com/sell/fulfillment/v1/order/'.rawurlencode($orderId));
            if (! $response->successful()) {
                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function alreadyHasTracking(string $token, string $orderId, array $order, string $tracking): bool
    {
        $needle = strtolower(preg_replace('/[\s\-]/', '', $tracking) ?? $tracking);
        if ($needle === '') {
            return false;
        }

        $fulfillments = $this->listShippingFulfillments($token, $orderId);
        $haystack = $fulfillments !== [] ? $fulfillments : $order;
        $blob = strtolower(preg_replace('/[\s\-]/', '', json_encode($haystack) ?: '') ?? '');

        return str_contains($blob, $needle);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listShippingFulfillments(string $token, string $orderId): array
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ])
                ->timeout(45)
                ->get('https://api.ebay.com/sell/fulfillment/v1/order/'.rawurlencode($orderId).'/shipping_fulfillment');
            if (! $response->successful()) {
                return [];
            }
            $json = $response->json();
            $list = is_array($json['fulfillments'] ?? null) ? $json['fulfillments'] : (is_array($json) ? $json : []);

            return array_values(array_filter($list, 'is_array'));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<array{lineItemId: string, quantity: int}>
     */
    protected function lineItemsForFulfillment(array $order, object $line): array
    {
        $out = [];
        $items = is_array($order['lineItems'] ?? null) ? $order['lineItems'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['lineItemId'] ?? ''));
            if ($id === '' || ! preg_match('/^\d{5,}$/', $id)) {
                continue;
            }
            $qty = max(1, (int) ($item['quantity'] ?? $item['quantityPurchased'] ?? $line->quantity ?? 1));
            $out[] = ['lineItemId' => $id, 'quantity' => $qty];
        }

        return $out;
    }

    protected function mapCarrier(string $carrier): string
    {
        $c = strtolower($carrier);
        if (str_contains($c, 'usps') || str_contains($c, 'united states postal') || str_contains($c, 'postal service')) {
            return 'USPS';
        }
        if (str_contains($c, 'ups') || str_contains($c, 'united parcel')) {
            return 'UPS';
        }
        if (str_contains($c, 'fedex') || str_contains($c, 'federal express')) {
            return 'FedEx';
        }
        if (str_contains($c, 'dhl')) {
            return 'DHL';
        }
        if (str_contains($c, 'ontrac')) {
            return 'OnTrac';
        }
        return 'Other';
    }

    protected function looksAlreadyShipped(string $body, string $fulfillmentStatus): bool
    {
        $m = strtolower($body);
        if (str_contains($m, 'already associated') || str_contains($m, 'already been used')) {
            return false;
        }

        return str_contains($m, 'already been fulfilled')
            || str_contains($m, 'already shipped')
            || str_contains($m, 'fulfillment already exists');
    }

    /**
     * @return list<string>
     */
    protected function ebayOrderIdCandidates(object $line): array
    {
        $raw = is_array($line->raw_payload ?? null) ? $line->raw_payload : [];

        return $this->uniqueEbayIds([
            $line->order_id ?? '',
            $line->order_number ?? '',
            $raw['orderId'] ?? '',
            $raw['legacyOrderId'] ?? '',
        ]);
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    protected function uniqueEbayIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Trading API CompleteSale fallback when Sell Fulfillment rejects the shipment.
     *
     * @param  list<string>  $orderIds
     * @return array{success: bool, message?: string}
     */
    protected function completeSaleViaTradingApi(string $channel, string $token, array $orderIds, string $tracking, string $carrier): array
    {
        $orderIds = $this->uniqueEbayIds($orderIds);
        if ($orderIds === [] || $token === '' || $tracking === '') {
            return ['success' => false, 'message' => 'CompleteSale skipped.'];
        }

        $compat = (string) config("services.{$channel}.compat_level", '967');
        $siteId = (string) config("services.{$channel}.site_id", '0');
        foreach ($orderIds as $orderId) {
            $xml = '<?xml version="1.0" encoding="utf-8"?>'
                .'<CompleteSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                .'<OrderID>'.htmlspecialchars($orderId, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</OrderID>'
                .'<Shipment><ShipmentTrackingDetails>'
                .'<ShippingCarrierUsed>'.htmlspecialchars($carrier, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</ShippingCarrierUsed>'
                .'<ShipmentTrackingNumber>'.htmlspecialchars($tracking, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</ShipmentTrackingNumber>'
                .'</ShipmentTrackingDetails></Shipment>'
                .'<Shipped>true</Shipped>'
                .'</CompleteSaleRequest>';

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'X-EBAY-API-IAF-TOKEN' => $token,
                        'X-EBAY-API-CALL-NAME' => 'CompleteSale',
                        'X-EBAY-API-SITEID' => $siteId,
                        'X-EBAY-API-COMPATIBILITY-LEVEL' => $compat,
                        'Content-Type' => 'text/xml',
                    ])
                    ->timeout(45)
                    ->withBody($xml, 'text/xml')
                    ->post('https://api.ebay.com/ws/api.dll');
            } catch (\Throwable $e) {
                Log::info('EbaySellFulfillmentTracking: CompleteSale request failed', [
                    'channel' => $channel,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $body = (string) $response->body();
            if ($response->successful() && (str_contains($body, '<Ack>Success</Ack>') || str_contains($body, '<Ack>Warning</Ack>'))) {
                return ['success' => true];
            }
            $low = strtolower($body);
            if (str_contains($low, 'already been fulfilled') || str_contains($low, 'already shipped')) {
                return ['success' => true];
            }
            Log::info('EbaySellFulfillmentTracking: CompleteSale rejected', [
                'channel' => $channel,
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 400),
            ]);
        }

        return ['success' => false, 'message' => 'eBay CompleteSale fallback failed.'];
    }

    protected function accessToken(string $channel): ?string
    {
        $map = [
            'ebay1' => 'ebay1',
            'ebay2' => 'ebay2',
            'ebay3' => 'ebay3',
        ];
        $key = $map[$channel] ?? $channel;
        $clientId = config("services.{$key}.app_id");
        $clientSecret = config("services.{$key}.cert_id");
        $refreshToken = config("services.{$key}.refresh_token");
        if (! $clientId || ! $clientSecret || ! $refreshToken) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth((string) $clientId, (string) $clientSecret)
                ->timeout(30)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'scope' => 'https://api.ebay.com/oauth/api_scope/sell.fulfillment https://api.ebay.com/oauth/api_scope',
                ]);
            if ($response->successful()) {
                return $response->json('access_token');
            }

            $response = Http::asForm()
                ->withBasicAuth((string) $clientId, (string) $clientSecret)
                ->timeout(30)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            return $response->successful() ? $response->json('access_token') : null;
        } catch (\Throwable $e) {
            Log::warning('EbaySellFulfillmentTracking: token failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
