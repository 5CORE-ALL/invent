<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggOrderMetric;
use App\Services\NeweggApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers back to Newegg (Ship Order Action 2).
 */
class NeweggTrackingSyncService
{
    public function __construct(
        protected NeweggApiService $neweggApi,
        protected NeweggOrderDetailService $orderDetailService,
        protected NeweggDetailFormatter $formatter,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null,
     *   newegg_tracking?: string|null,
     *   ship_carrier?: string|null
     * }
     */
    public function pushTrackingForOrder(NeweggOrderMetric $line, bool $tryVeeqoCopy = false): array
    {
        if (! $this->neweggApi->isConfigured()) {
            return ['success' => false, 'message' => 'Newegg API credentials missing.'];
        }

        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Newegg order id missing.'];
        }

        if ($this->isClosedNeweggStatus((string) ($line->status ?? ''))) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'closed',
                'message' => 'Newegg order is cancelled/voided — tracking not pushed.',
            ];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: NeweggOrderMetric::query()
                ->where('order_id', $orderId)
                ->whereNotNull('shopify_order_id')
                ->value('shopify_order_id')
        ));

        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $sku = trim((string) ($line->sku ?? ''));
        if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Marketplace SKU missing — tracking not attached.',
            ];
        }

        $extraIds = array_values(array_filter([
            trim((string) ($line->order_number ?? '')),
        ], static fn ($id) => $id !== '' && $id !== $orderId));

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId, $orderId, $sku, $extraIds);
        if (empty($shopifyFulfillment['tracking']) && $tryVeeqoCopy) {
            $copied = app(VeeqoShopifyFulfillmentService::class)->fulfillMarketplaceOrder('newegg', (int) $line->id);
            if (! empty($copied['success'])) {
                $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId, $orderId, $sku, $extraIds);
            }
        }
        if (empty($shopifyFulfillment['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => $shopifyFulfillment['error']
                    ?: 'No tracking number on Shopify yet. Buy/download a shipping label in Shopify first.',
                'shopify_tracking' => null,
                'shopify_carrier' => $shopifyFulfillment['carrier'] ?? null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');

        if ($this->alreadyPushedLocally($line, $shopifyTracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Newegg already has this Shopify tracking number.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'newegg_tracking' => $shopifyTracking,
            ];
        }

        // Cached Newegg payload only — a live getOrders on every candidate starves the batch.
        $neweggShipment = $this->resolveNeweggShipment($orderId, $line, false);
        $neweggTracking = trim((string) ($neweggShipment['tracking'] ?? ''));
        $neweggCarrier = trim((string) ($neweggShipment['service'] ?? ''));

        if ($neweggTracking !== '' && $this->trackingEquals($neweggTracking, $shopifyTracking)) {
            $this->markTrackingPushed($line, $shopifyTracking);

            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Newegg already has this Shopify tracking number.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'newegg_tracking' => $neweggTracking,
                'ship_carrier' => $neweggCarrier !== '' ? $neweggCarrier : null,
            ];
        }

        $shipCarrier = $this->resolveShipCarrier($shopifyCarrier, $neweggCarrier);
        $shipService = $this->resolveShipService($shopifyCarrier, $neweggCarrier);
        $items = $this->buildShipItems($orderId, $sku);

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'No Newegg Seller Part # line items found to ship.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $result = $this->neweggApi->shipOrder(
            $orderId,
            $shopifyTracking,
            $shipCarrier,
            $shipService,
            $items
        );

        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Failed to push tracking to Newegg.');

            // Already shipped on Newegg — treat matching post-refresh tracking as success.
            if ($this->looksLikeShippedByNewegg($message)) {
                $this->markTrackingPushed($line, $shopifyTracking, 'sbn');

                return [
                    'success' => false,
                    'skipped' => true,
                    'action' => 'sbn',
                    'message' => 'Shipped-by-Newegg order — seller tracking cannot be pushed via API.',
                    'shopify_tracking' => $shopifyTracking,
                    'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                    'newegg_tracking' => $neweggTracking !== '' ? $neweggTracking : null,
                    'ship_carrier' => $shipCarrier,
                ];
            }

            if ($this->looksLikeAlreadyShipped($message)) {
                try {
                    $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
                    $line->refresh();
                } catch (\Throwable $e) {
                    // ignore
                }
                $after = $this->resolveNeweggShipment($orderId, $line, false);
                $afterTracking = trim((string) ($after['tracking'] ?? ''));
                if ($afterTracking !== '' && $this->trackingEquals($afterTracking, $shopifyTracking)) {
                    $this->markTrackingPushed($line, $shopifyTracking);

                    return [
                        'success' => true,
                        'skipped' => true,
                        'action' => 'already_shipped',
                        'message' => 'Newegg order already shipped with this tracking number.',
                        'shopify_tracking' => $shopifyTracking,
                        'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                        'newegg_tracking' => $afterTracking,
                        'ship_carrier' => $shipCarrier,
                    ];
                }

                // Newegg Action 2 cannot add tracking after the order is already Shipped/Invoiced.
                $this->markTrackingPushed($line, $shopifyTracking, 'already_shipped_no_update');

                return [
                    'success' => false,
                    'skipped' => true,
                    'action' => 'already_shipped_no_update',
                    'message' => 'Newegg already marked this order shipped and the API cannot add tracking afterward. Update tracking in Newegg Seller Portal.',
                    'shopify_tracking' => $shopifyTracking,
                    'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                    'newegg_tracking' => $afterTracking !== '' ? $afterTracking : null,
                    'ship_carrier' => $shipCarrier,
                ];
            }

            Log::warning('NeweggTrackingSyncService: push failed', [
                'order_id' => $orderId,
                'shopify_order_id' => $shopifyOrderId,
                'shopify_tracking' => $shopifyTracking,
                'ship_carrier' => $shipCarrier,
                'message' => $message,
            ]);

            return [
                'success' => false,
                'action' => 'ship',
                'message' => $message,
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'newegg_tracking' => $neweggTracking !== '' ? $neweggTracking : null,
                'ship_carrier' => $shipCarrier,
            ];
        }

        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
        } catch (\Throwable $e) {
            Log::info('NeweggTrackingSyncService: post-push refresh failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->markTrackingPushed($line, $shopifyTracking);

        Log::info('NeweggTrackingSyncService: tracking pushed', [
            'order_id' => $orderId,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'ship_carrier' => $shipCarrier,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Newegg order shipped with tracking {$shopifyTracking} ({$shipCarrier}).",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'newegg_tracking' => $neweggTracking !== '' ? $neweggTracking : null,
            'ship_carrier' => $shipCarrier,
        ];
    }

    /**
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = NeweggOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByRaw('pushed_to_shopify_at IS NULL')
            ->orderBy('pushed_to_shopify_at')
            ->orderBy('order_date')
            ->orderBy('id')
            ->limit($limit * 40)
            ->get(['id', 'order_id', 'order_number', 'sku', 'shopify_order_id', 'status', 'raw_payload']);

        $unique = [];
        foreach ($rows as $row) {
            $ref = trim((string) $row->order_id);
            $sku = trim((string) ($row->sku ?? ''));
            if ($ref === '' || $sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
                continue;
            }
            if ($this->isClosedNeweggStatus((string) ($row->status ?? ''))) {
                continue;
            }
            if ($this->alreadyPushedLocally($row)) {
                continue;
            }
            $key = $ref.'|'.$sku;
            if (isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $row;
            if (count($unique) >= $limit) {
                break;
            }
        }

        $checked = 0;
        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($unique as $line) {
            $checked++;
            $result = $this->pushTrackingForOrder($line);
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

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('newegg');

        return (bool) ($settings['order']['push_tracking_to_newegg'] ?? true);
    }

    /**
     * @return array{tracking: ?string, carrier: ?string, tracking_url: ?string, error?: ?string}
     */
    /**
     * @param  list<string>  $extraOrderIds
     * @return array{tracking: ?string, carrier: ?string, tracking_url: ?string, error?: ?string}
     */
    protected function fetchShopifyTracking(
        string $shopifyOrderId,
        string $marketplaceOrderId = '',
        string $sku = '',
        array $extraOrderIds = []
    ): array {
        return app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $marketplaceOrderId,
            $sku,
            $extraOrderIds,
            'NeweggTrackingSyncService'
        );
    }

    /**
     * @return array{tracking: ?string, service: ?string}
     */
    protected function resolveNeweggShipment(string $orderId, NeweggOrderMetric $line, bool $livePull = true): array
    {
        if ($livePull) {
            try {
                $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
                $line->refresh();
            } catch (\Throwable $e) {
                // Use cached payload if live pull fails.
            }
        }

        $lines = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($line->fresh() ?? $line);
        $detail = $this->formatter->formatOrder($orderRoot, $lines, $line);
        $shipment = is_array($detail['shipment'] ?? null) ? $detail['shipment'] : [];

        $tracking = trim((string) ($shipment['tracking'] ?? ''));
        $service = trim((string) ($shipment['service'] ?? ''));

        return [
            'tracking' => $tracking !== '' ? $tracking : null,
            'service' => $service !== '' ? $service : null,
        ];
    }

    /**
     * @return list<array{seller_part_number: string, quantity: int, newegg_item_number?: string|null}>
     */
    protected function buildShipItems(string $orderId, string $onlySku = ''): array
    {
        $lines = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get(['sku', 'product_id', 'quantity']);

        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $onlySku = $matcher->normalizeSku($onlySku);

        $items = [];
        foreach ($lines as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
                continue;
            }
            if ($onlySku !== '' && ! $matcher->skusEqual($sku, $onlySku)) {
                continue;
            }
            $items[] = [
                'seller_part_number' => $sku,
                'quantity' => max(1, (int) ($row->quantity ?? 1)),
                'newegg_item_number' => trim((string) ($row->product_id ?? '')) ?: null,
            ];
        }

        return $items;
    }

    protected function resolveShipCarrier(string $shopifyCarrier, string $neweggCarrier): string
    {
        $fromShopify = $this->mapShopifyCarrier($shopifyCarrier);
        if ($fromShopify !== '') {
            return $fromShopify;
        }

        $existing = trim($neweggCarrier);
        if ($existing !== '') {
            // Newegg shipment "service" sometimes stores carrier-like text.
            $mapped = $this->mapShopifyCarrier($existing);
            if ($mapped !== '') {
                return $mapped;
            }
        }

        return 'Other Carrier';
    }

    protected function resolveShipService(string $shopifyCarrier, string $neweggCarrier): string
    {
        $c = strtolower(trim($shopifyCarrier.' '.$neweggCarrier));
        if (str_contains($c, 'ground')) {
            return 'Ground';
        }
        if (str_contains($c, 'express') || str_contains($c, 'overnight') || str_contains($c, 'next day')) {
            return 'Express';
        }
        if (str_contains($c, 'priority')) {
            return 'Priority';
        }
        if (str_contains($c, '2nd') || str_contains($c, '2 day') || str_contains($c, 'two day')) {
            return '2nd Day';
        }

        return 'Other Service';
    }

    protected function mapShopifyCarrier(string $carrier): string
    {
        $c = strtolower(trim($carrier));
        if ($c === '') {
            return '';
        }

        $map = [
            'usps' => 'USPS',
            'united states postal service' => 'USPS',
            'ups' => 'UPS',
            'ups®' => 'UPS',
            'fedex' => 'FedEx',
            'fedex®' => 'FedEx',
            'dhl' => 'DHL',
            'dhl express' => 'DHL',
            'dhl ecommerce' => 'DHL',
            'ontrac' => 'OnTrac',
            'lasership' => 'LaserShip',
            'purolator' => 'Purolator',
            'canada post' => 'Canada Post',
        ];

        if (isset($map[$c])) {
            return $map[$c];
        }

        foreach ($map as $needle => $name) {
            if (str_contains($c, $needle)) {
                return $name;
            }
        }

        return '';
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'so027')
            || str_contains($m, 'so025')
            || str_contains($m, 'so011')
            || str_contains($m, 'already been shipped')
            || str_contains($m, 'already shipped')
            || str_contains($m, 'only unshipped orders can be shipped');
    }

    protected function looksLikeShippedByNewegg(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'so012')
            || str_contains($m, 'so005')
            || str_contains($m, 'shipped by newegg')
            || str_contains($m, 'only shipped by seller');
    }

    protected function isClosedNeweggStatus(string $status): bool
    {
        $status = strtolower(trim($status));

        return $status !== '' && (
            str_contains($status, 'void')
            || str_contains($status, 'cancel')
        );
    }

    protected function alreadyPushedLocally(NeweggOrderMetric $line, string $shopifyTracking = ''): bool
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        $pushed = trim((string) ($raw['shopify_tracking_pushed'] ?? ''));
        if ($pushed === '') {
            return false;
        }
        if ($shopifyTracking === '') {
            return true;
        }

        return $this->trackingEquals($pushed, $shopifyTracking);
    }

    protected function markTrackingPushed(NeweggOrderMetric $line, string $tracking, string $note = ''): void
    {
        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return;
        }

        $rows = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->get();

        foreach ($rows as $row) {
            $raw = is_array($row->raw_payload) ? $row->raw_payload : [];
            $raw['shopify_tracking_pushed'] = $tracking;
            $raw['tracking_pushed_at'] = now()->toIso8601String();
            if ($note !== '') {
                $raw['tracking_push_note'] = $note;
            }
            $row->update(['raw_payload' => $raw]);
        }
    }

    protected function trackingEquals(string $a, string $b): bool
    {
        $normalize = static function (string $value): string {
            $value = strtoupper(trim($value));
            if (str_contains($value, ',')) {
                $value = trim(explode(',', $value, 2)[0]);
            }

            return preg_replace('/[\s\-]/', '', $value) ?? $value;
        };

        return $normalize($a) === $normalize($b);
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('newegg');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
