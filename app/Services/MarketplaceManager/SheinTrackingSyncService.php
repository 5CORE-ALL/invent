<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\SheinOrderMetric;
use App\Services\SheinApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers back to Shein (Ship Order Action 2).
 */
class SheinTrackingSyncService
{
    public function __construct(
        protected SheinApiService $sheinApi,
        protected SheinOrderDetailService $orderDetailService,
        protected SheinDetailFormatter $formatter,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null,
     *   shein_tracking?: string|null,
     *   ship_carrier?: string|null
     * }
     */
    public function pushTrackingForOrder(SheinOrderMetric $line): array
    {
        if (! $this->sheinApi->isConfigured()) {
            return ['success' => false, 'message' => 'Shein API credentials missing.'];
        }

        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Shein order id missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: SheinOrderMetric::query()
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

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId);
        if (empty($shopifyFulfillment['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No tracking number on Shopify yet. Buy/download a shipping label in Shopify first.',
                'shopify_tracking' => null,
                'shopify_carrier' => $shopifyFulfillment['carrier'] ?? null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');

        $sheinShipment = $this->resolveSheinShipment($orderId, $line);
        $sheinTracking = trim((string) ($sheinShipment['tracking'] ?? ''));
        $sheinCarrier = trim((string) ($sheinShipment['service'] ?? ''));

        if ($sheinTracking !== '' && $this->trackingEquals($sheinTracking, $shopifyTracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Shein already has this Shopify tracking number.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'shein_tracking' => $sheinTracking,
                'ship_carrier' => $sheinCarrier !== '' ? $sheinCarrier : null,
            ];
        }

        // Shein requires accept (Pending → To Be Shipped) before tracking upload works.
        if ($this->orderDetailService->isPendingOrder($line)) {
            $accept = $this->orderDetailService->acceptOrderOnShein($orderId);
            if (empty($accept['success'])) {
                return [
                    'success' => false,
                    'message' => 'Accept order on Shein first (Pending), then push tracking. '.($accept['message'] ?? ''),
                    'shopify_tracking' => $shopifyTracking,
                    'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                ];
            }
            $line->refresh();
        }

        $shipCarrier = $this->resolveShipCarrier($shopifyCarrier, $sheinCarrier);
        $shipService = $this->resolveShipService($shopifyCarrier, $sheinCarrier);
        $items = $this->buildShipItems($orderId);

        if ($items === []) {
            // Refresh order detail so goodsId is available from Shein, then retry once.
            try {
                $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
            } catch (\Throwable $e) {
                // ignore
            }
            $items = $this->buildShipItems($orderId);
        }

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'No Shein goodsId line items found to ship. Pull the order from Shein, then retry.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $result = $this->sheinApi->shipOrder(
            $orderId,
            $shopifyTracking,
            $shipCarrier,
            $shipService,
            $items
        );

        if (! empty($result['express_id_code'])) {
            $shipCarrier = (string) $result['express_id_code'];
        }

        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Failed to push tracking to Shein.');

            // Already shipped on Shein — treat matching post-refresh tracking as success.
            if ($this->looksLikeAlreadyShipped($message)) {
                try {
                    $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
                    $line->refresh();
                } catch (\Throwable $e) {
                    // ignore
                }
                $after = $this->resolveSheinShipment($orderId, $line);
                $afterTracking = trim((string) ($after['tracking'] ?? ''));
                if ($afterTracking !== '' && $this->trackingEquals($afterTracking, $shopifyTracking)) {
                    return [
                        'success' => true,
                        'skipped' => true,
                        'action' => 'already_shipped',
                        'message' => 'Shein order already shipped with this tracking number.',
                        'shopify_tracking' => $shopifyTracking,
                        'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                        'shein_tracking' => $afterTracking,
                        'ship_carrier' => $shipCarrier,
                    ];
                }
            }

            Log::warning('SheinTrackingSyncService: push failed', [
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
                'shein_tracking' => $sheinTracking !== '' ? $sheinTracking : null,
                'ship_carrier' => $shipCarrier,
            ];
        }

        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
        } catch (\Throwable $e) {
            Log::info('SheinTrackingSyncService: post-push refresh failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('SheinTrackingSyncService: tracking pushed', [
            'order_id' => $orderId,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'ship_carrier' => $shipCarrier,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Shein order shipped with tracking {$shopifyTracking} ({$shipCarrier}).",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'shein_tracking' => $sheinTracking !== '' ? $sheinTracking : null,
            'ship_carrier' => $shipCarrier,
        ];
    }

    /**
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = SheinOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderBy('id')
            ->limit($limit * 12)
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
        $settings ??= MarketplaceSyncSettings::getFor('shein');

        return (bool) ($settings['order']['push_tracking_to_shein'] ?? true);
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
                Log::warning('SheinTrackingSyncService: Shopify order fetch failed', [
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
            Log::warning('SheinTrackingSyncService: Shopify tracking exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['tracking' => null, 'carrier' => null, 'tracking_url' => null];
    }

    /**
     * @return array{tracking: ?string, service: ?string}
     */
    protected function resolveSheinShipment(string $orderId, SheinOrderMetric $line): array
    {
        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
            $line->refresh();
        } catch (\Throwable $e) {
            // Use cached payload if live pull fails.
        }

        $lines = SheinOrderMetric::query()
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
     * @return list<array{seller_part_number: string, quantity: int, goods_id: int, shein_item_number?: string|null}>
     */
    protected function buildShipItems(string $orderId): array
    {
        $lines = SheinOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $items = [];
        $seenGoods = [];

        foreach ($lines as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '__order__') {
                // Fall through — may still hold goodsIds in order payload.
            } elseif ($sku === '' || $sku === '__unknown__') {
                continue;
            }

            foreach ($this->extractGoodsIdsFromLine($row) as $goodsId) {
                if (isset($seenGoods[$goodsId])) {
                    continue;
                }
                $seenGoods[$goodsId] = true;
                $items[] = [
                    'seller_part_number' => $sku !== '' && $sku !== '__order__' ? $sku : (string) $goodsId,
                    'quantity' => max(1, (int) ($row->quantity ?? 1)),
                    'goods_id' => $goodsId,
                    'shein_item_number' => (string) $goodsId,
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    protected function extractGoodsIdsFromLine(SheinOrderMetric $row): array
    {
        $ids = [];
        $raw = is_array($row->raw_payload) ? $row->raw_payload : [];
        $line = is_array($raw['line'] ?? null) ? $raw['line'] : [];
        $order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;

        $candidates = [
            $line['goodsId'] ?? null,
            $line['goods_id'] ?? null,
            $order['goodsId'] ?? null,
        ];

        // product_id is often skuCode; only use when numeric (real goodsId).
        $productId = trim((string) ($row->product_id ?? ''));
        if ($productId !== '' && ctype_digit($productId)) {
            $candidates[] = $productId;
        }

        $goodsList = is_array($order['orderGoodsInfoList'] ?? null) ? $order['orderGoodsInfoList'] : [];
        $sku = trim((string) $row->sku);
        foreach ($goodsList as $goods) {
            if (! is_array($goods)) {
                continue;
            }
            $goodsSku = trim((string) ($goods['sellerSku'] ?? $goods['goodsSn'] ?? $goods['skuCode'] ?? ''));
            if ($sku !== '' && $sku !== '__order__' && $goodsSku !== '' && strcasecmp($sku, $goodsSku) !== 0) {
                // Still collect when __order__ placeholder or sku mismatch unknown — for __order__ take all.
                if ($sku !== '__order__') {
                    continue;
                }
            }
            $candidates[] = $goods['goodsId'] ?? $goods['goods_id'] ?? null;
        }

        // If line sku matched nothing, and this is a normal line, try any goodsId on this line node only.
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (! is_numeric($candidate)) {
                continue;
            }
            $ids[] = (int) $candidate;
        }

        return array_values(array_unique($ids));
    }

    protected function resolveShipCarrier(string $shopifyCarrier, string $sheinCarrier): string
    {
        $fromShopify = $this->mapShopifyCarrier($shopifyCarrier);
        if ($fromShopify !== '') {
            return $fromShopify;
        }

        $existing = trim($sheinCarrier);
        if ($existing !== '') {
            // Shein shipment "service" sometimes stores carrier-like text.
            $mapped = $this->mapShopifyCarrier($existing);
            if ($mapped !== '') {
                return $mapped;
            }

            return $existing;
        }

        // SheinApiService::resolveExpressIdCode will map this against express-channel list.
        return $shopifyCarrier !== '' ? $shopifyCarrier : 'UPS';
    }

    protected function resolveShipService(string $shopifyCarrier, string $sheinCarrier): string
    {
        $c = strtolower(trim($shopifyCarrier.' '.$sheinCarrier));
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
        $settings = MarketplaceSyncSettings::getFor('shein');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
