<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * After a shipping label is bought in Shopify / ShipStation / any connected software,
 * read the Shopify fulfillment tracking number and confirmShipment on Amazon.
 */
class AmazonTrackingSyncService
{
    public function __construct(
        protected AmazonSpOrdersClient $ordersClient,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null,
     *   ship_carrier?: string|null
     * }
     */
    public function pushTrackingForOrder(AmazonOrder $order): array
    {
        if ($order->isFba()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'FBA (AFN) orders are not confirmed from Shopify tracking.',
            ];
        }
        if ($order->isCancelled()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Cancelled Amazon orders are not shipped from Shopify tracking.',
            ];
        }

        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $status = strtoupper(trim((string) ($order->status ?? '')));
        if (in_array($status, ['SHIPPED', 'CANCELED', 'CANCELLED'], true)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_shipped',
                'message' => 'Amazon order is already '.$status.'.',
            ];
        }

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId);
        if (empty($shopifyFulfillment['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No tracking number on Shopify yet. Buy a shipping label in Shopify, ShipStation, or any connected shipping software first.',
                'shopify_tracking' => null,
                'shopify_carrier' => $shopifyFulfillment['carrier'] ?? null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');
        [$carrierCode, $carrierName] = $this->mapAmazonCarrier($shopifyCarrier);

        $orderItems = $this->buildConfirmShipmentItems($order);
        if ($orderItems === []) {
            return [
                'success' => false,
                'message' => 'No Amazon order item IDs found to confirm shipment.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $packageDetail = [
            'packageReferenceId' => substr(preg_replace('/[^A-Za-z0-9]/', '', $shopifyTracking) ?: '1', -5) ?: '1',
            'carrierCode' => $carrierCode,
            'trackingNumber' => $shopifyTracking,
            'shipDate' => gmdate('Y-m-d\TH:i:s\Z'),
            'orderItems' => $orderItems,
        ];
        if ($carrierName !== '') {
            $packageDetail['carrierName'] = $carrierName;
        }
        if ($shopifyCarrier !== '') {
            $packageDetail['shippingMethod'] = $shopifyCarrier;
        }

        $result = $this->ordersClient->confirmShipment((string) $order->amazon_order_id, $packageDetail);
        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Failed to push tracking to Amazon.');
            if ($this->looksLikeAlreadyShipped($message)) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'action' => 'already_shipped',
                    'message' => 'Amazon already has a shipment for this order.',
                    'shopify_tracking' => $shopifyTracking,
                    'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                    'ship_carrier' => $carrierCode,
                ];
            }

            return [
                'success' => false,
                'action' => 'ship',
                'message' => $message,
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'ship_carrier' => $carrierCode,
            ];
        }

        if (Schema::hasColumn('amazon_orders', 'status')) {
            $order->update(['status' => 'Shipped']);
        }

        Log::info('AmazonTrackingSyncService: tracking pushed', [
            'amazon_order_id' => $order->amazon_order_id,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'carrier_code' => $carrierCode,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Amazon order shipped with tracking {$shopifyTracking} ({$carrierCode}).",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'ship_carrier' => $carrierCode,
        ];
    }

    /**
     * @return array{success: bool, message: string, attempted?: int, checked?: int, pushed: int, skipped: int, failed?: int}
     */
    public function syncPending(int $limit = 40): array
    {
        return $this->syncFromShopify($limit);
    }

    /**
     * @return array{success: bool, message: string, checked: int, pushed: int, skipped: int, failed: int}
     */
    public function syncFromShopify(int $limit = 40): array
    {
        if (! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            return [
                'success' => true,
                'message' => 'Shopify import columns missing on amazon_orders.',
                'checked' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $limit = max(1, min(200, $limit));
        $rows = AmazonOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('fulfillment_channel')
                    ->orWhere('fulfillment_channel', '!=', 'AFN');
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['Shipped', 'Canceled', 'Cancelled']);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $checked = 0;
        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $order) {
            $checked++;
            $result = $this->pushTrackingForOrder($order);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $pushed++;
            } elseif (! empty($result['skipped'])) {
                $skipped++;
            } else {
                $failed++;
            }
            usleep(250000);
        }

        return [
            'success' => $failed === 0,
            'checked' => $checked,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Tracking sync: checked {$checked}, pushed {$pushed}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('amazon');

        return (bool) ($settings['order']['push_tracking_to_amazon'] ?? true);
    }

    /**
     * @return array{tracking: ?string, carrier: ?string, tracking_url: ?string}
     */
    protected function fetchShopifyTracking(string $shopifyOrderId): array
    {
        $config = $this->shopifyConfig();
        $storeUrl = (string) ($config['store_url'] ?? '');
        $token = (string) ($config['token'] ?? '');

        if ($storeUrl === '' || $token === '') {
            return ['tracking' => null, 'carrier' => null, 'tracking_url' => null];
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,fulfillments,fulfillment_status',
            ]);

            if (! $response->successful()) {
                Log::warning('AmazonTrackingSyncService: Shopify order fetch failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'status' => $response->status(),
                ]);

                return ['tracking' => null, 'carrier' => null, 'tracking_url' => null];
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

                $number = null;
                if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                    $number = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
                }
                if (($number === null || $number === '') && ! empty($fulfillment['tracking_number'])) {
                    $number = trim((string) $fulfillment['tracking_number']);
                }
                if ($number === null || $number === '') {
                    continue;
                }

                $url = null;
                if (! empty($fulfillment['tracking_urls']) && is_array($fulfillment['tracking_urls'])) {
                    $url = trim((string) ($fulfillment['tracking_urls'][0] ?? ''));
                }
                if (($url === null || $url === '') && ! empty($fulfillment['tracking_url'])) {
                    $url = trim((string) $fulfillment['tracking_url']);
                }

                return [
                    'tracking' => $number,
                    'carrier' => trim((string) ($fulfillment['tracking_company'] ?? '')) ?: null,
                    'tracking_url' => $url !== '' ? $url : null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonTrackingSyncService: Shopify tracking exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['tracking' => null, 'carrier' => null, 'tracking_url' => null];
    }

    /**
     * @return list<array{orderItemId: string, quantity: int}>
     */
    protected function buildConfirmShipmentItems(AmazonOrder $order): array
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->orderBy('id')->get();

        $out = [];
        foreach ($items as $item) {
            /** @var AmazonOrderItem $item */
            $raw = AmazonOrder::decodeRawPayload($item->raw_data ?? null);
            $orderItemId = trim((string) ($raw['OrderItemId'] ?? $raw['orderItemId'] ?? ''));
            if ($orderItemId === '') {
                continue;
            }
            $qty = (int) ($item->quantity ?? $raw['QuantityOrdered'] ?? $raw['quantityOrdered'] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $out[] = [
                'orderItemId' => $orderItemId,
                'quantity' => $qty,
            ];
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function mapAmazonCarrier(string $shopifyCarrier): array
    {
        $c = strtolower(trim($shopifyCarrier));
        $map = [
            'usps' => 'USPS',
            'united states postal service' => 'USPS',
            'ups' => 'UPS',
            'fedex' => 'FedEx',
            'federal express' => 'FedEx',
            'dhl ecommerce' => 'DHL Global Mail',
            'dhl e-commerce' => 'DHL Global Mail',
            'dhl express' => 'DHL',
            'dhl' => 'DHL',
            'ontrac' => 'OnTrac',
            'canada post' => 'Canada Post',
        ];

        if (isset($map[$c])) {
            return [$map[$c], ''];
        }
        foreach ($map as $needle => $code) {
            if ($c !== '' && str_contains($c, $needle)) {
                return [$code, ''];
            }
        }

        $name = trim($shopifyCarrier);

        return ['Other', $name !== '' ? $name : 'Other'];
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'already')
            && (str_contains($m, 'ship') || str_contains($m, 'confirm') || str_contains($m, 'fulfill'));
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('amazon');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
