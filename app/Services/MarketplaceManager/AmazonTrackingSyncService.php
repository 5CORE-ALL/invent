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
 * After a shipping label is bought in Shopify / ShipStation / Veeqo / 4Seller (GOFO) / any connected software,
 * read the Shopify fulfillment tracking number and confirmShipment on Amazon.
 */
class AmazonTrackingSyncService
{
    public function __construct(
        protected AmazonSpOrdersClient $ordersClient,
        protected VeeqoShopifyFulfillmentService $veeqoFulfillment,
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
        $hasShopify = $shopifyOrderId !== '' && ! str_starts_with($shopifyOrderId, 'manual');

        $status = strtoupper(trim((string) ($order->status ?? '')));
        $amazonOrderId = trim((string) ($order->amazon_order_id ?? ''));
        $itemSkus = $order->items()
            ->orderBy('id')
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))
            ->unique()
            ->values();

        $shopifyFulfillment = ['tracking' => null, 'carrier' => null, 'error' => null];
        $matchedSku = '';
        if ($hasShopify) {
            foreach ($itemSkus as $sku) {
                $hit = $this->fetchShopifyTracking($shopifyOrderId, $amazonOrderId, $sku);
                if (! empty($hit['tracking'])) {
                    $shopifyFulfillment = $hit;
                    $matchedSku = $sku;
                    break;
                }
                $shopifyFulfillment = $hit;
            }
            if (empty($shopifyFulfillment['tracking'])) {
                $veeqo = $this->veeqoFulfillment->fulfillMarketplaceOrder('amazon', (int) $order->id);
                if (! empty($veeqo['success'])) {
                    foreach ($itemSkus as $sku) {
                        $hit = $this->fetchShopifyTracking($shopifyOrderId, $amazonOrderId, $sku);
                        if (! empty($hit['tracking'])) {
                            $shopifyFulfillment = $hit;
                            $matchedSku = $sku;
                            break;
                        }
                    }
                }
            }
        }
        if (empty($shopifyFulfillment['tracking'])) {
            $warehouse = $this->lookupWarehouseTracking($order);
            if ($warehouse !== null) {
                $shopifyFulfillment = $warehouse;
            }
        }

        $foundTracking = trim((string) ($shopifyFulfillment['tracking'] ?? ''));
        if ($foundTracking !== '') {
            $this->persistLocalTracking(
                $order,
                $foundTracking,
                (string) ($shopifyFulfillment['carrier'] ?? '')
            );
        }

        if (in_array($status, ['SHIPPED', 'PARTIALLYSHIPPED', 'CANCELED', 'CANCELLED'], true)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_shipped',
                'message' => $foundTracking === ''
                    ? 'Amazon order is already '.$status.'.'
                    : 'Shopify tracking '.$foundTracking.' saved for SOF. Amazon is already '.$status.'.',
                'shopify_tracking' => $foundTracking !== '' ? $foundTracking : null,
                'shopify_carrier' => $shopifyFulfillment['carrier'] ?? null,
            ];
        }
        if (empty($shopifyFulfillment['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => $shopifyFulfillment['error']
                    ?: 'No tracking number on Shopify/Veeqo/GOFO yet. Buy the label first.',
                'shopify_tracking' => null,
                'shopify_carrier' => $shopifyFulfillment['carrier'] ?? null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');
        [$carrierCode, $carrierName] = $this->mapAmazonCarrier($shopifyCarrier);

        $orderItems = $this->buildConfirmShipmentItems($order, $matchedSku);
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
                $this->persistLocalTracking($order, $shopifyTracking, $shopifyCarrier);

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

        $this->persistLocalTracking($order, $shopifyTracking, $shopifyCarrier);

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
        $sizes = self::trackingBatchSizes($limit);
        $rows = AmazonOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('fulfillment_channel')
                    ->orWhere('fulfillment_channel', '!=', 'AFN');
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) NOT IN (?, ?, ?, ?)", [
                        'SHIPPED', 'PARTIALLYSHIPPED', 'CANCELED', 'CANCELLED',
                    ]);
            })
            ->orderByDesc('id')
            ->limit($sizes['unshipped'])
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

    /**
     * SHIPPED Amazon MFN rows that never got tracking onto raw_data (SP-API getOrder
     * does not include it). Copy Veeqo / GOFO / Shopify tracking for SOF.
     *
     * @return array{success: bool, message: string, checked: int, filled: int, skipped: int}
     */
    public function fillMissingSofTracking(int $limit = 80): array
    {
        if (! Schema::hasTable('amazon_orders')) {
            return [
                'success' => true,
                'message' => 'amazon_orders table missing.',
                'checked' => 0,
                'filled' => 0,
                'skipped' => 0,
            ];
        }

        $limit = max(1, min(400, $limit));
        $scan = min(800, max($limit * 8, 200));
        $query = AmazonOrder::query()
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['SHIPPED', 'PARTIALLYSHIPPED'])
            ->where(function ($q) {
                $q->whereNull('fulfillment_channel')
                    ->orWhereRaw("UPPER(TRIM(COALESCE(fulfillment_channel, ''))) != ?", ['AFN']);
            })
            ->where('order_date', '>=', now()->subDays(45))
            ->orderByRaw("CASE WHEN order_date >= ? THEN 0 ELSE 1 END", [now('America/Los_Angeles')->subDays(7)->startOfDay()])
            ->orderByRaw("CASE WHEN shopify_order_id IS NULL OR shopify_order_id = '' OR shopify_order_id LIKE 'manual%' THEN 1 ELSE 0 END")
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit($scan);

        try {
            $orders = (clone $query)
                ->where(function ($q) {
                    $q->whereNull('raw_data')
                        ->orWhereRaw("IFNULL(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.tracking_number')), '') = ''");
                })
                ->get();
        } catch (\Throwable $e) {
            Log::warning('AmazonTrackingSyncService: JSON tracking filter failed, scanning recent shipped', [
                'error' => $e->getMessage(),
            ]);
            $orders = $query->get();
        }

        $checked = 0;
        $filled = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if ($checked >= $limit) {
                break;
            }
            if ($order->isFba() || $order->isCancelled()) {
                continue;
            }
            if (trim((string) ($order->localTracking()['tracking'] ?? '')) !== '') {
                continue;
            }
            $checked++;
            $result = $this->fillTrackingForOrder($order);
            if (! empty($result['success']) && trim((string) ($result['tracking'] ?? '')) !== '') {
                $filled++;
            } else {
                $skipped++;
            }
            usleep(150000);
        }

        return [
            'success' => true,
            'checked' => $checked,
            'filled' => $filled,
            'skipped' => $skipped,
            'message' => "Amazon SOF tracking fill: checked {$checked}, filled {$filled}, still missing {$skipped}.",
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{success: bool, checked: int, filled: int, skipped: int, message: string}
     */
    public function fillMissingSofTrackingForIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $ids),
            static fn (int $id) => $id > 0
        )));
        $checked = 0;
        $filled = 0;
        $skipped = 0;
        if ($ids === []) {
            return [
                'success' => true,
                'checked' => 0,
                'filled' => 0,
                'skipped' => 0,
                'message' => 'Amazon SOF tracking fill: no ids.',
            ];
        }

        $orders = AmazonOrder::query()->whereIn('id', array_slice($ids, 0, 80))->get();
        foreach ($orders as $order) {
            if ($order->isFba() || $order->isCancelled()) {
                continue;
            }
            if (trim((string) ($order->localTracking()['tracking'] ?? '')) !== '') {
                continue;
            }
            $checked++;
            $result = $this->fillTrackingForOrder($order);
            if (! empty($result['success']) && trim((string) ($result['tracking'] ?? '')) !== '') {
                $filled++;
            } else {
                $skipped++;
            }
            usleep(120000);
        }

        return [
            'success' => true,
            'checked' => $checked,
            'filled' => $filled,
            'skipped' => $skipped,
            'message' => "Amazon SOF tracking fill (ids): checked {$checked}, filled {$filled}, still missing {$skipped}.",
        ];
    }

    /**
     * @return array{success: bool, tracking: ?string, carrier: ?string, message?: string}
     */
    public function fillTrackingForOrder(AmazonOrder $order): array
    {
        $existing = $order->localTracking();
        if (trim((string) ($existing['tracking'] ?? '')) !== '') {
            return [
                'success' => true,
                'tracking' => $existing['tracking'],
                'carrier' => $existing['carrier'] !== '' ? $existing['carrier'] : null,
            ];
        }

        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        $amazonOrderId = trim((string) ($order->amazon_order_id ?? ''));
        $hit = ['tracking' => null, 'carrier' => null];

        if ($shopifyOrderId !== '' && ! str_starts_with($shopifyOrderId, 'manual')) {
            $itemSkus = $order->items()
                ->orderBy('id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))
                ->unique()
                ->values();
            $itemSkus->push('');
            foreach ($itemSkus as $sku) {
                $one = $this->fetchShopifyTracking($shopifyOrderId, $amazonOrderId, $sku);
                if (! empty($one['tracking'])) {
                    $hit = $one;
                    break;
                }
            }
        }

        if (empty($hit['tracking'])) {
            $warehouse = $this->lookupWarehouseTracking($order);
            if ($warehouse !== null) {
                $hit = $warehouse;
            }
        }

        if (empty($hit['tracking'])) {
            $fromAmazon = $this->ordersClient->lookupTrackingForOrder($amazonOrderId);
            if ($fromAmazon !== null && trim((string) ($fromAmazon['tracking'] ?? '')) !== '') {
                $hit = $fromAmazon;
            }
        }

        $tn = trim((string) ($hit['tracking'] ?? ''));
        if ($tn === '') {
            return [
                'success' => false,
                'tracking' => null,
                'carrier' => null,
                'message' => 'No Shopify/Veeqo/GOFO/Amazon tracking found.',
            ];
        }

        $carrier = trim((string) ($hit['carrier'] ?? ''));
        $this->persistLocalTracking($order, $tn, $carrier);

        return [
            'success' => true,
            'tracking' => $tn,
            'carrier' => $carrier !== '' ? $carrier : null,
            'message' => 'Saved tracking '.$tn.' for SOF.',
        ];
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function lookupWarehouseTracking(AmazonOrder $order): ?array
    {
        $refs = $order->trackingLookupRefs();
        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId !== '' && ! str_starts_with($shopifyOrderId, 'manual')) {
            $refs[] = $shopifyOrderId;
        }
        $local = $order->localTracking();
        $localHit = trim((string) ($local['tracking'] ?? '')) !== '' ? $local : null;
        $sku = '';
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->orderBy('id')->get();
        foreach ($items as $item) {
            $one = trim((string) ($item->sku ?? ''));
            if ($one !== '' && ! in_array($one, ['__order__', '__unknown__'], true)) {
                $sku = $one;
                break;
            }
        }
        $found = $this->veeqoFulfillment->lookupLabelTracking($refs, $localHit, false, $sku);
        $tn = trim((string) ($found['tracking'] ?? ''));
        if ($tn === '') {
            return null;
        }

        return [
            'tracking' => $tn,
            'carrier' => trim((string) ($found['carrier'] ?? '')) ?: 'Other',
        ];
    }

    /**
     * Unshipped confirmShipment and shipped-missing-tracking must not share one
     * 40-row queue — otherwise 25k Unshipped starves yesterday’s Shipped orders.
     *
     * @return array{unshipped: int, missing: int}
     */
    public static function trackingBatchSizes(int $limit): array
    {
        $limit = max(1, min(200, $limit));

        return [
            'unshipped' => $limit,
            'missing' => max(120, $limit * 3),
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('amazon');

        return (bool) ($settings['order']['push_tracking_to_amazon'] ?? true);
    }

    /**
     * @return array{tracking: ?string, carrier: ?string, tracking_url: ?string, error?: ?string}
     */
    protected function fetchShopifyTracking(string $shopifyOrderId, string $marketplaceOrderId = '', string $sku = ''): array
    {
        return app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $marketplaceOrderId,
            $sku,
            [],
            'AmazonTrackingSyncService'
        );
    }

    /**
     * Copy Shopify/Veeqo tracking onto amazon_orders + shopify_raw_orders so SOF Label Created shows it.
     */
    protected function persistLocalTracking(AmazonOrder $order, string $tracking, string $carrier): void
    {
        $this->veeqoFulfillment->persistTrackingOntoMarketplaceOrder(
            'amazon',
            (int) $order->id,
            (string) ($order->shopify_order_id ?? ''),
            $tracking,
            $carrier
        );
    }

    /**
     * @return list<array{orderItemId: string, quantity: int}>
     */
    protected function buildConfirmShipmentItems(AmazonOrder $order, string $onlySku = ''): array
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->orderBy('id')->get();

        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $onlySku = $matcher->normalizeSku($onlySku);

        $out = [];
        foreach ($items as $item) {
            /** @var AmazonOrderItem $item */
            $itemSku = trim((string) ($item->sku ?? ''));
            if ($onlySku !== '' && $itemSku !== '' && ! $matcher->skusEqual($itemSku, $onlySku)) {
                continue;
            }
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
