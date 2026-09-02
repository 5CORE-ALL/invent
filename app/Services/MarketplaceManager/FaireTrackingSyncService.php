<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\FaireOrderMetric;
use App\Services\FaireApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers back to Faire (Ship Order Action 2).
 */
class FaireTrackingSyncService
{
    public function __construct(
        protected FaireApiService $faireApi,
        protected FaireOrderDetailService $orderDetailService,
        protected FaireDetailFormatter $formatter,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null,
     *   faire_tracking?: string|null,
     *   ship_carrier?: string|null
     * }
     */
    public function pushTrackingForOrder(FaireOrderMetric $line): array
    {
        if (! $this->faireApi->isConfigured()) {
            return ['success' => false, 'message' => 'Faire API credentials missing.'];
        }

        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Faire order id missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: FaireOrderMetric::query()
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

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId, $orderId, (string) ($line->sku ?? ''));
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

        $faireShipment = $this->resolveFaireShipment($orderId, $line);
        $faireTracking = trim((string) ($faireShipment['tracking'] ?? ''));
        $faireCarrier = trim((string) ($faireShipment['service'] ?? ''));

        if ($faireTracking !== '' && $this->trackingEquals($faireTracking, $shopifyTracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Faire already has this Shopify tracking number.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'faire_tracking' => $faireTracking,
                'ship_carrier' => $faireCarrier !== '' ? $faireCarrier : null,
            ];
        }

        $shipCarrier = $this->resolveShipCarrier($shopifyCarrier, $faireCarrier);
        $shipService = $this->resolveShipService($shopifyCarrier, $faireCarrier);
        $items = $this->buildShipItems($orderId, trim((string) ($line->sku ?? '')));

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'No Faire Seller Part # line items found to ship.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $result = $this->faireApi->shipOrder(
            $orderId,
            $shopifyTracking,
            $shipCarrier !== '' ? $shipCarrier : $shipService,
            $items
        );

        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Failed to push tracking to Faire.');

            // Already shipped on Faire — treat matching post-refresh tracking as success.
            if ($this->looksLikeAlreadyShipped($message)) {
                try {
                    $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
                    $line->refresh();
                } catch (\Throwable $e) {
                    // ignore
                }
                $after = $this->resolveFaireShipment($orderId, $line);
                $afterTracking = trim((string) ($after['tracking'] ?? ''));
                if ($afterTracking !== '' && $this->trackingEquals($afterTracking, $shopifyTracking)) {
                    return [
                        'success' => true,
                        'skipped' => true,
                        'action' => 'already_shipped',
                        'message' => 'Faire order already shipped with this tracking number.',
                        'shopify_tracking' => $shopifyTracking,
                        'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                        'faire_tracking' => $afterTracking,
                        'ship_carrier' => $shipCarrier,
                    ];
                }
            }

            Log::warning('FaireTrackingSyncService: push failed', [
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
                'faire_tracking' => $faireTracking !== '' ? $faireTracking : null,
                'ship_carrier' => $shipCarrier,
            ];
        }

        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
        } catch (\Throwable $e) {
            Log::info('FaireTrackingSyncService: post-push refresh failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('FaireTrackingSyncService: tracking pushed', [
            'order_id' => $orderId,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'ship_carrier' => $shipCarrier,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Faire order shipped with tracking {$shopifyTracking} ({$shipCarrier}).",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'faire_tracking' => $faireTracking !== '' ? $faireTracking : null,
            'ship_carrier' => $shipCarrier,
        ];
    }

    /**
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = FaireOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get(['id', 'order_id', 'shopify_order_id', 'status']);

        $unique = [];
        foreach ($rows as $row) {
            $ref = trim((string) $row->order_id);
            if ($ref === '' || isset($unique[$ref])) {
                continue;
            }
            $unique[$ref] = $row;
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
        $settings ??= MarketplaceSyncSettings::getFor('faire');

        return (bool) ($settings['order']['push_tracking_to_faire'] ?? true);
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
            'FaireTrackingSyncService'
        );
    }

    /**
     * @return array{tracking: ?string, service: ?string}
     */
    protected function resolveFaireShipment(string $orderId, FaireOrderMetric $line): array
    {
        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
            $line->refresh();
        } catch (\Throwable $e) {
            // Use cached payload if live pull fails.
        }

        $lines = FaireOrderMetric::query()
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
     * @return list<array{seller_part_number: string, quantity: int, faire_item_number?: string|null}>
     */
    protected function buildShipItems(string $orderId, string $onlySku = ''): array
    {
        $lines = FaireOrderMetric::query()
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
                'faire_item_number' => trim((string) ($row->product_id ?? '')) ?: null,
            ];
        }

        return $items;
    }

    protected function resolveShipCarrier(string $shopifyCarrier, string $faireCarrier): string
    {
        $fromShopify = $this->mapShopifyCarrier($shopifyCarrier);
        if ($fromShopify !== '') {
            return $fromShopify;
        }

        $existing = trim($faireCarrier);
        if ($existing !== '') {
            // Faire shipment "service" sometimes stores carrier-like text.
            $mapped = $this->mapShopifyCarrier($existing);
            if ($mapped !== '') {
                return $mapped;
            }
        }

        return 'Other Carrier';
    }

    protected function resolveShipService(string $shopifyCarrier, string $faireCarrier): string
    {
        $c = strtolower(trim($shopifyCarrier.' '.$faireCarrier));
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
            || str_contains($m, 'already been shipped')
            || str_contains($m, 'already shipped');
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
        $settings = MarketplaceSyncSettings::getFor('faire');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
