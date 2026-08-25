<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $shopify = $this->fetchShopifyTracking($channel, $shopifyOrderId);
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

        $order = $this->getEbayOrder($token, $orderId);
        if ($order === null) {
            $raw = is_array($line->raw_payload ?? null) ? $line->raw_payload : [];
            $order = $raw !== [] ? $raw : null;
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
        if ($this->alreadyHasTracking($token, $orderId, $order, $tracking)) {
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
                    'https://api.ebay.com/sell/fulfillment/v1/order/'.rawurlencode($orderId).'/shipping_fulfillment',
                    $payload
                );
        } catch (\Throwable $e) {
            Log::warning('EbaySellFulfillmentTracking: request failed', [
                'channel' => $channel,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'eBay tracking push failed: '.$e->getMessage(),
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        if ($response->successful() || in_array($response->status(), [201, 204], true)) {
            Log::info('EbaySellFulfillmentTracking: tracking pushed', [
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

        $body = (string) $response->body();
        if ($this->looksAlreadyShipped($body, $fulfillmentStatus)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_shipped',
                'message' => 'eBay already has a shipment for this order.',
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier !== '' ? $carrier : null,
            ];
        }

        Log::warning('EbaySellFulfillmentTracking: eBay rejected tracking', [
            'channel' => $channel,
            'order_id' => $orderId,
            'status' => $response->status(),
            'body' => mb_substr($body, 0, 800),
        ]);

        return [
            'success' => false,
            'action' => 'ship',
            'message' => 'eBay tracking push failed: '.mb_substr($body !== '' ? $body : 'HTTP '.$response->status(), 0, 400),
            'shopify_tracking' => $tracking,
            'shopify_carrier' => $carrier !== '' ? $carrier : null,
        ];
    }

    /**
     * @return array{tracking: ?string, carrier: ?string}
     */
    protected function fetchShopifyTracking(string $channel, string $shopifyOrderId): array
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
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,fulfillments,fulfillment_status',
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

        return ['tracking' => null, 'carrier' => null];
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
            $id = trim((string) ($item['lineItemId'] ?? $item['legacyItemId'] ?? ''));
            if ($id === '') {
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
        if (in_array($fulfillmentStatus, ['FULFILLED', 'IN_PROGRESS'], true) && str_contains(strtolower($body), 'already')) {
            return true;
        }
        $m = strtolower($body);

        return str_contains($m, 'already been fulfilled')
            || str_contains($m, 'already shipped')
            || str_contains($m, 'fulfillment already exists');
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
                    'scope' => 'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
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
