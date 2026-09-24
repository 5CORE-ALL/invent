<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\Temu2Order;
use App\Services\ShopifyStoreSelector;
use App\Services\Temu2ApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Temu 2 (self-fulfilled shipment confirm).
 */
class Temu2TrackingSyncService
{
    use CopiesPurchaseLabelToShopify;

    public function __construct(
        protected Temu2ApiService $temuApi,
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
    public function pushTrackingForOrder(Temu2Order $line): array
    {
        if (! $this->temuApi->isConfigured()) {
            return ['success' => false, 'message' => 'Temu 2 API credentials missing.'];
        }

        $parentOrderSn = trim((string) $line->parent_order_sn);
        if ($parentOrderSn === '') {
            return ['success' => false, 'message' => 'Temu 2 parent_order_sn missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: Temu2Order::query()
                ->where('parent_order_sn', $parentOrderSn)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->value('shopify_order_id')
        ));

        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $shopifyFulfillment = $this->fetchShopifyTracking(
            $shopifyOrderId,
            $parentOrderSn,
            (string) ($line->display_sku ?? $line->sku_id ?? ''),
            [trim((string) ($line->order_sn ?? ''))]
        );
        if (empty($shopifyFulfillment['tracking'])) {
            $copied = $this->copyPurchasedLabelToShopify('temu2', (int) ($line->id ?? 0));
            if (! empty($copied['success']) || trim((string) ($copied['tracking'] ?? '')) !== '') {
                $shopifyFulfillment = $this->fetchShopifyTracking(
                    $shopifyOrderId,
                    $parentOrderSn,
                    (string) ($line->display_sku ?? $line->sku_id ?? ''),
                    [trim((string) ($line->order_sn ?? ''))]
                );
            }
        }
        $shopifyTracking = trim((string) ($shopifyFulfillment['tracking'] ?? ''));
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');

        $temuPull = app(Temu2OrderTrackingPullService::class)->pullForParentOrder($parentOrderSn, true);
        $line->refresh();
        $temuTracking = trim((string) ($temuPull['tracking_number'] ?? $line->tracking_number ?? ''));
        $temuCarrier = trim((string) ($temuPull['carrier'] ?? $line->carrier ?? ''));
        $direction = ReverbTrackingSyncService::trackingSyncAction($shopifyTracking, $temuTracking);

        if ($direction === 'pull_from_reverb') {
            $provider = $temuCarrier !== '' ? $temuCarrier : 'Other';
            $applied = app(ShopifyFulfillmentTrackingWriter::class)->apply(
                $this->shopifyConfig(),
                $shopifyOrderId,
                $temuTracking,
                $provider
            );
            if (empty($applied['success'])) {
                return [
                    'success' => false,
                    'action' => 'pull_from_temu2',
                    'message' => $applied['message'] ?? 'Temu 2 has a tracking number, but Shopify was not updated.',
                    'shopify_tracking' => $shopifyTracking !== '' ? $shopifyTracking : null,
                    'ship_carrier' => $provider,
                ];
            }

            return [
                'success' => true,
                'action' => 'pulled_from_temu2',
                'message' => "Updated Shopify with Temu 2 tracking {$temuTracking} ({$provider}).",
                'shopify_tracking' => $temuTracking,
                'shopify_carrier' => $provider,
                'ship_carrier' => $provider,
            ];
        }

        if ($direction === 'none') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => $shopifyFulfillment['error']
                    ?: 'No tracking number on Temu 2 or Shopify yet.',
                'shopify_tracking' => null,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $regionId = (int) (
            $line->region_id
            ?: Temu2Order::query()->where('parent_order_sn', $parentOrderSn)->value('region_id')
            ?: 211
        );

        $carrierId = $this->resolveCarrierId($shopifyCarrier, $regionId);
        if ($carrierId === null) {
            return [
                'success' => false,
                'message' => 'Could not map Shopify carrier "'.($shopifyCarrier !== '' ? $shopifyCarrier : 'unknown').'" to a Temu 2 logistics company.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $warehouseId = $this->resolveWarehouseId();
        if ($warehouseId === null || $warehouseId === '') {
            return [
                'success' => false,
                'message' => 'No Temu 2 self-shipping warehouse found. Configure a warehouse in Temu Seller Center, then retry.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $orderSendInfoList = $this->buildOrderSendInfoList($parentOrderSn);
        if ($orderSendInfoList === []) {
            return [
                'success' => false,
                'message' => 'No Temu 2 order line items found to ship. Pull/refresh the order, then retry.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $shipCarrierLabel = $shopifyCarrier !== '' ? $shopifyCarrier : (string) $carrierId;
        $result = $this->temuApi->confirmSelfShipment(
            $shopifyTracking,
            $carrierId,
            $warehouseId,
            $orderSendInfoList,
            0
        );

        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Failed to push tracking to Temu 2.');
            if ($this->looksLikeAlreadyShipped($message)) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'action' => 'already_shipped',
                    'message' => 'Temu 2 order already shipped (or tracking already used).',
                    'shopify_tracking' => $shopifyTracking,
                    'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                    'ship_carrier' => $shipCarrierLabel,
                ];
            }

            Log::warning('Temu2TrackingSyncService: push failed', [
                'parent_order_sn' => $parentOrderSn,
                'shopify_tracking' => $shopifyTracking,
                'carrier_id' => $carrierId,
                'message' => $message,
            ]);

            return [
                'success' => false,
                'action' => 'ship',
                'message' => $message,
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'ship_carrier' => $shipCarrierLabel,
            ];
        }

        Log::info('Temu2TrackingSyncService: tracking pushed', [
            'parent_order_sn' => $parentOrderSn,
            'shopify_tracking' => $shopifyTracking,
            'carrier_id' => $carrierId,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Temu 2 order shipped with tracking {$shopifyTracking} ({$shipCarrierLabel}).",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'ship_carrier' => $shipCarrierLabel,
        ];
    }

    /**
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int, failed?: int, checked?: int}
     */
    public function syncPending(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = Temu2Order::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByRaw("CASE WHEN UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN ('SHIPPED', 'PARTIALLY_SHIPPED', 'DELIVERED', 'PARTIALLY_DELIVERED') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get(['id', 'parent_order_sn', 'shopify_order_id']);

        $unique = [];
        foreach ($rows as $row) {
            $ref = trim((string) $row->parent_order_sn);
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
            usleep(300000);
        }

        return [
            'success' => $failed === 0,
            'attempted' => $checked,
            'checked' => $checked,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Temu 2 tracking sync: checked {$checked}, pushed {$pushed}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('temu2');

        return (bool) ($settings['order']['push_tracking_to_temu2'] ?? true);
    }

    /**
     * @return list<array{parentOrderSn:string,orderSn:string,quantity:int,goodsId?:int,skuId?:int}>
     */
    protected function buildOrderSendInfoList(string $parentOrderSn): array
    {
        $lines = Temu2Order::query()
            ->where('parent_order_sn', $parentOrderSn)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($lines as $row) {
            $orderSn = trim((string) $row->order_sn);
            if ($orderSn === '') {
                continue;
            }
            $item = [
                'parentOrderSn' => $parentOrderSn,
                'orderSn' => $orderSn,
                'quantity' => max(1, (int) ($row->quantity ?? $row->original_order_quantity ?? 1)),
            ];
            if (! empty($row->goods_id) && is_numeric($row->goods_id)) {
                $item['goodsId'] = (int) $row->goods_id;
            }
            if (! empty($row->sku_id) && is_numeric($row->sku_id)) {
                $item['skuId'] = (int) $row->sku_id;
            }
            $out[] = $item;
        }

        return $out;
    }

    protected function resolveCarrierId(string $shopifyCarrier, int $regionId): ?int
    {
        $cacheKey = 'temu2:logistics-companies:'.$regionId;
        $companies = Cache::remember($cacheKey, now()->addHours(6), function () use ($regionId) {
            $res = $this->temuApi->listLogisticsCompanies($regionId > 0 ? $regionId : null);
            if (empty($res['success'])) {
                $res = $this->temuApi->listLogisticsCompanies(null);
            }

            return ! empty($res['success']) ? ($res['companies'] ?? []) : [];
        });

        if (! is_array($companies) || $companies === []) {
            Cache::forget($cacheKey);

            return null;
        }

        $c = strtolower(trim($shopifyCarrier));
        $needles = $c !== '' ? [$c] : ['usps', 'ups', 'fedex'];
        foreach (['usps', 'ups', 'fedex', 'dhl', 'ontrac', 'amazon'] as $token) {
            if ($c !== '' && str_contains($c, $token)) {
                $needles[] = $token;
            }
        }
        $needles = array_values(array_unique($needles));

        foreach ($needles as $needle) {
            foreach ($companies as $company) {
                $hay = strtolower(trim(($company['name'] ?? '').' '.($company['brand'] ?? '')));
                if ($hay !== '' && str_contains($hay, $needle)) {
                    return (int) $company['id'];
                }
            }
        }

        return null;
    }

    protected function resolveWarehouseId(): ?string
    {
        $configured = trim((string) config('services.temu2.self_shipping_warehouse_id', config('services.temu.self_shipping_warehouse_id', '')));
        if ($configured !== '') {
            return $configured;
        }

        $warehouses = Cache::remember('temu2:warehouses', now()->addHours(6), function () {
            $res = $this->temuApi->listWarehouses();

            return ! empty($res['success']) ? ($res['warehouses'] ?? []) : [];
        });

        if (! is_array($warehouses) || $warehouses === []) {
            Cache::forget('temu2:warehouses');

            return null;
        }

        foreach ($warehouses as $wh) {
            if (! empty($wh['default']) && ! empty($wh['id'])) {
                return (string) $wh['id'];
            }
        }

        return ! empty($warehouses[0]['id']) ? (string) $warehouses[0]['id'] : null;
    }

    /**
     * @return array{tracking: ?string, carrier: ?string, tracking_url: ?string, error?: ?string}
     */
    protected function fetchShopifyTracking(string $shopifyOrderId, string $marketplaceOrderId = '', string $sku = '', array $extraOrderIds = []): array
    {
        return app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $marketplaceOrderId,
            $sku,
            $extraOrderIds,
            'Temu2TrackingSyncService'
        );
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('temu2');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'already been shipped')
            || str_contains($m, 'already shipped')
            || str_contains($m, 'order has been shipped')
            || str_contains($m, '120012004')
            || str_contains($m, 'duplicate tracking')
            || str_contains($m, 'tracking number has been used')
            || str_contains($m, '120014003')
            || str_contains($m, '120014004');
    }
}
