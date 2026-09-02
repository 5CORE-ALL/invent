<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\TemuOrder;
use App\Services\ShopifyStoreSelector;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Temu (self-fulfilled shipment confirm).
 *
 * Flow: Shopify tracking_company + tracking_number
 *    → map carrier via bg.logistics.companies.get
 *    → bg.logistics.shipment.v2.confirm
 */
class TemuTrackingSyncService
{
    public function __construct(
        protected TemuApiService $temuApi,
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
    public function pushTrackingForOrder(TemuOrder $line): array
    {
        if (! $this->temuApi->isConfigured()) {
            return ['success' => false, 'message' => 'Temu API credentials missing.'];
        }

        $parentOrderSn = trim((string) $line->parent_order_sn);
        if ($parentOrderSn === '') {
            return ['success' => false, 'message' => 'Temu parent_order_sn missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: TemuOrder::query()
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

        if ($this->orderAlreadyShipped($line) && $this->localTrackingMatches($line, $shopifyTracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Temu order already appears shipped with this tracking.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'ship_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $regionId = (int) (
            $line->region_id
            ?: TemuOrder::query()->where('parent_order_sn', $parentOrderSn)->value('region_id')
            ?: 211
        );

        $carrierId = $this->resolveCarrierId($shopifyCarrier, $regionId);
        if ($carrierId === null) {
            return [
                'success' => false,
                'message' => 'Could not map Shopify carrier "'.($shopifyCarrier !== '' ? $shopifyCarrier : 'unknown').'" to a Temu logistics company. Check bg.logistics.companies.get for region '.$regionId.'.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            ];
        }

        $warehouseId = $this->resolveWarehouseId();
        if ($warehouseId === null || $warehouseId === '') {
            return [
                'success' => false,
                'message' => 'No Temu self-shipping warehouse found. Configure a warehouse in Temu Seller Center, then retry.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'ship_carrier' => $this->carrierLabelForId($carrierId, $shopifyCarrier, $regionId),
            ];
        }

        $orderSendInfoList = $this->buildOrderSendInfoList($parentOrderSn);
        if ($orderSendInfoList === []) {
            return [
                'success' => false,
                'message' => 'No Temu order line items found to ship. Pull/refresh the order, then retry.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'ship_carrier' => $this->carrierLabelForId($carrierId, $shopifyCarrier, $regionId),
            ];
        }

        $shipCarrierLabel = $this->carrierLabelForId($carrierId, $shopifyCarrier, $regionId);
        $result = $this->temuApi->confirmSelfShipment(
            $shopifyTracking,
            $carrierId,
            $warehouseId,
            $orderSendInfoList,
            0
        );

        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Failed to push tracking to Temu.');

            if ($this->looksLikeAlreadyShipped($message)) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'action' => 'already_shipped',
                    'message' => 'Temu order already shipped (or tracking already used).',
                    'shopify_tracking' => $shopifyTracking,
                    'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                    'ship_carrier' => $shipCarrierLabel,
                ];
            }

            Log::warning('TemuTrackingSyncService: push failed', [
                'parent_order_sn' => $parentOrderSn,
                'shopify_order_id' => $shopifyOrderId,
                'shopify_tracking' => $shopifyTracking,
                'carrier_id' => $carrierId,
                'warehouse_id' => $warehouseId,
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

        try {
            app(TemuOrderDetailService::class)->fetchAndPersistOrderDetail($parentOrderSn);
        } catch (\Throwable $e) {
            Log::info('TemuTrackingSyncService: post-push refresh failed', [
                'parent_order_sn' => $parentOrderSn,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('TemuTrackingSyncService: tracking pushed', [
            'parent_order_sn' => $parentOrderSn,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'carrier_id' => $carrierId,
            'ship_carrier' => $shipCarrierLabel,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Temu order shipped with tracking {$shopifyTracking} ({$shipCarrierLabel}).",
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

        $rows = TemuOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get(['id', 'parent_order_sn', 'shopify_order_id', 'parent_order_status_text', 'order_status_text']);

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

        if ($unique === []) {
            $total = (int) TemuOrder::query()->count();
            $linked = (int) TemuOrder::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->count();

            return [
                'success' => true,
                'attempted' => 0,
                'checked' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'message' => "Tracking sync: no Shopify-linked Temu orders found (temu_orders={$total}, with shopify_order_id={$linked}). Import/push orders to Shopify first, or use --order=PO-xxx after linking.",
            ];
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
            'message' => "Tracking sync: checked {$checked}, pushed {$pushed}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('temu');

        return (bool) ($settings['order']['push_tracking_to_temu'] ?? true);
    }

    /**
     * @return list<array{parentOrderSn:string,orderSn:string,quantity:int,goodsId?:int,skuId?:int}>
     */
    protected function buildOrderSendInfoList(string $parentOrderSn): array
    {
        $lines = TemuOrder::query()
            ->where('parent_order_sn', $parentOrderSn)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($lines as $row) {
            $orderSn = trim((string) $row->order_sn);
            if ($orderSn === '') {
                continue;
            }
            $qty = max(1, (int) ($row->quantity ?? $row->original_order_quantity ?? 1));
            $item = [
                'parentOrderSn' => $parentOrderSn,
                'orderSn' => $orderSn,
                'quantity' => $qty,
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
        $cacheKey = 'temu:logistics-companies:'.$regionId;
        $companies = Cache::remember($cacheKey, now()->addHours(6), function () use ($regionId) {
            $res = $this->temuApi->listLogisticsCompanies($regionId > 0 ? $regionId : null);
            if (empty($res['success'])) {
                // Retry without region filter.
                $res = $this->temuApi->listLogisticsCompanies(null);
            }

            return ! empty($res['success']) ? ($res['companies'] ?? []) : [];
        });

        if (! is_array($companies) || $companies === []) {
            Cache::forget($cacheKey);

            return null;
        }

        $needles = $this->carrierMatchNeedles($shopifyCarrier);
        foreach ($needles as $needle) {
            foreach ($companies as $company) {
                $hay = strtolower(trim(($company['name'] ?? '').' '.($company['brand'] ?? '')));
                if ($hay !== '' && (str_contains($hay, $needle) || $needle === $hay)) {
                    return (int) $company['id'];
                }
            }
        }

        // Last resort: exact-ish brand tokens for common carriers even if Shopify label odd.
        foreach (['usps', 'ups', 'fedex', 'dhl', 'ontrac', 'amazon'] as $fallback) {
            if ($needles !== [] && ! in_array($fallback, $needles, true)) {
                continue;
            }
            foreach ($companies as $company) {
                $hay = strtolower(trim(($company['name'] ?? '').' '.($company['brand'] ?? '')));
                if (str_contains($hay, $fallback)) {
                    return (int) $company['id'];
                }
            }
        }

        return null;
    }

    protected function carrierLabelForId(int $carrierId, string $shopifyCarrier, int $regionId): string
    {
        $cacheKey = 'temu:logistics-companies:'.$regionId;
        $companies = Cache::get($cacheKey, []);
        if (is_array($companies)) {
            foreach ($companies as $company) {
                if ((int) ($company['id'] ?? 0) === $carrierId) {
                    return (string) ($company['brand'] ?? $company['name'] ?? $shopifyCarrier);
                }
            }
        }

        return $shopifyCarrier !== '' ? $shopifyCarrier : (string) $carrierId;
    }

    /**
     * @return list<string>
     */
    protected function carrierMatchNeedles(string $shopifyCarrier): array
    {
        $c = strtolower(trim($shopifyCarrier));
        if ($c === '') {
            return ['usps', 'ups', 'fedex'];
        }

        $needles = [$c];
        $map = [
            'usps' => ['usps', 'united states postal', 'postal'],
            'ups' => ['ups', 'united parcel'],
            'fedex' => ['fedex', 'federal express'],
            'dhl' => ['dhl'],
            'ontrac' => ['ontrac', 'on trac'],
            'amazon' => ['amazon', 'amazon shipping', 'amzl'],
            'gofo' => ['gofo'],
            'veeqo' => ['veeqo'],
        ];

        foreach ($map as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($c, $alias) || $c === $alias) {
                    $needles = array_merge($needles, $aliases, [$key]);
                    break 2;
                }
            }
        }

        return array_values(array_unique(array_filter($needles)));
    }

    protected function resolveWarehouseId(): ?string
    {
        $configured = trim((string) config('services.temu.self_shipping_warehouse_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $warehouses = Cache::remember('temu:warehouses', now()->addHours(6), function () {
            $res = $this->temuApi->listWarehouses();

            return ! empty($res['success']) ? ($res['warehouses'] ?? []) : [];
        });

        if (! is_array($warehouses) || $warehouses === []) {
            Cache::forget('temu:warehouses');

            return null;
        }

        foreach ($warehouses as $wh) {
            if (! empty($wh['default']) && ! empty($wh['id'])) {
                return (string) $wh['id'];
            }
        }

        return ! empty($warehouses[0]['id']) ? (string) $warehouses[0]['id'] : null;
    }

    protected function orderAlreadyShipped(TemuOrder $line): bool
    {
        $status = strtolower(trim(
            (string) ($line->parent_order_status_text ?? '').' '.
            (string) ($line->order_status_text ?? '').' '.
            (string) ($line->parent_order_status ?? '').' '.
            (string) ($line->order_status ?? '')
        ));

        return str_contains($status, 'ship')
            || str_contains($status, 'deliver')
            || in_array((string) ($line->parent_order_status ?? ''), ['3', '4', '5'], true)
            || in_array((string) ($line->order_status ?? ''), ['3', '4', '5'], true);
    }

    protected function localTrackingMatches(TemuOrder $line, string $shopifyTracking): bool
    {
        $raw = is_array($line->raw_json) ? $line->raw_json : [];
        $blob = strtoupper(json_encode($raw) ?: '');
        $needle = strtoupper(preg_replace('/[\s\-]/', '', $shopifyTracking) ?? $shopifyTracking);

        return $needle !== '' && str_contains(preg_replace('/[\s\-]/', '', $blob) ?? $blob, $needle);
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
            'TemuTrackingSyncService'
        );
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

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('temu');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
