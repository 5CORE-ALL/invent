<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\AlibabaApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers back to Alibaba
 * (declare / modify seller shipment).
 */
class AlibabaTrackingSyncService
{
    /** @var list<array{service_name: string, display_name?: string}>|null */
    protected ?array $logisticsServicesCache = null;

    public function __construct(
        protected AlibabaApiService $aliExpressApi,
        protected AlibabaOrderDetailService $orderDetailService,
        protected AlibabaDetailFormatter $formatter,
    ) {}

    /**
     * Push Shopify tracking for one Alibaba order (by any line row).
     *
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null,
     *   aliexpress_tracking?: string|null,
     *   service_name?: string|null
     * }
     */
    public function pushTrackingForOrder(AlibabaOrderMetric $line): array
    {
        if (empty($this->aliExpressApi->getAccessToken())) {
            return ['success' => false, 'message' => 'ALIBABA_ACCESS_TOKEN missing.'];
        }

        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Alibaba order id missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: AlibabaOrderMetric::query()
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
        $trackingUrl = (string) ($shopifyFulfillment['tracking_url'] ?? '');

        $aeShipment = $this->resolveAeShipment($orderId, $line);
        $aeTracking = trim((string) ($aeShipment['tracking'] ?? ''));
        $aeService = trim((string) ($aeShipment['service'] ?? ''));

        if ($aeTracking !== '' && $this->trackingEquals($aeTracking, $shopifyTracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Alibaba already has this Shopify tracking number.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'aliexpress_tracking' => $aeTracking,
                'service_name' => $aeService !== '' ? $aeService : null,
            ];
        }

        $serviceName = $this->resolveServiceName($shopifyCarrier, $aeService);
        $website = $this->normalizeTrackingWebsite($trackingUrl);

        if ($aeTracking === '') {
            $result = $this->aliExpressApi->declareSellerShipment([
                'out_ref' => $orderId,
                'logistics_no' => $shopifyTracking,
                'service_name' => $serviceName,
                'send_type' => 'all',
                'tracking_website' => $website,
                'actual_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'description' => 'Synced from Shopify fulfillment',
            ]);

            // Already declared on AE with different tracking — fall through to modify.
            if (empty($result['success']) && $this->looksLikeAlreadyShipped($result['message'] ?? '')) {
                $modify = $this->tryModifyWithGuessedOldTracking(
                    $orderId,
                    $shopifyTracking,
                    $serviceName,
                    $shopifyCarrier,
                    $website,
                    $aeService
                );
                if ($modify !== null) {
                    $result = $modify;
                }
            }

            $action = 'declared';
        } else {
            $result = $this->aliExpressApi->modifySellerShipment([
                'out_ref' => $orderId,
                'old_logistics_no' => $aeTracking,
                'new_logistics_no' => $shopifyTracking,
                'old_service_name' => $aeService !== '' ? $aeService : $serviceName,
                'new_service_name' => $serviceName,
                'send_type' => 'all',
                'tracking_website' => $website,
                'actual_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'description' => 'Updated from Shopify fulfillment',
            ]);
            $action = 'modified';
        }

        if (empty($result['success'])) {
            Log::warning('AlibabaTrackingSyncService: push failed', [
                'order_id' => $orderId,
                'shopify_order_id' => $shopifyOrderId,
                'shopify_tracking' => $shopifyTracking,
                'action' => $action,
                'message' => $result['message'] ?? null,
            ]);

            return [
                'success' => false,
                'action' => $action,
                'message' => $result['message'] ?? 'Failed to push tracking to Alibaba.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'aliexpress_tracking' => $aeTracking !== '' ? $aeTracking : null,
                'service_name' => $serviceName,
            ];
        }

        // Refresh local AE order cache so UI shows the new tracking.
        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
        } catch (\Throwable $e) {
            Log::info('AlibabaTrackingSyncService: post-push refresh failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('AlibabaTrackingSyncService: tracking pushed', [
            'order_id' => $orderId,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'action' => $action,
            'service_name' => $serviceName,
        ]);

        return [
            'success' => true,
            'action' => $action,
            'message' => $action === 'modified'
                ? "Updated Alibaba tracking to {$shopifyTracking}."
                : "Declared tracking {$shopifyTracking} on Alibaba.",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'aliexpress_tracking' => $aeTracking !== '' ? $aeTracking : null,
            'service_name' => $serviceName,
        ];
    }

    /**
     * Bulk: find linked AE orders and push Shopify tracking when present.
     *
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $orderIds = AlibabaOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [
                        'FINISH',
                        'Completed',
                        'BUYER_ACCEPT_GOODS',
                        'IN_CANCEL',
                        'ORDER_CANCEL',
                    ]);
            })
            ->where(function ($q) {
                $q->where('order_date', '>=', now()->subDays(90))
                    ->orWhere('created_at', '>=', now()->subDays(90));
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit($limit * 8)
            ->pluck('order_id', 'shopify_order_id');

        $uniqueOrderIds = [];
        foreach ($orderIds as $shopifyId => $aeOrderId) {
            $aeOrderId = (string) $aeOrderId;
            if ($aeOrderId === '' || isset($uniqueOrderIds[$aeOrderId])) {
                continue;
            }
            $uniqueOrderIds[$aeOrderId] = (string) $shopifyId;
            if (count($uniqueOrderIds) >= $limit) {
                break;
            }
        }

        $checked = 0;
        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($uniqueOrderIds as $aeOrderId => $shopifyId) {
            $line = AlibabaOrderMetric::query()
                ->where('order_id', $aeOrderId)
                ->where('shopify_order_id', $shopifyId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                continue;
            }

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

    /**
     * Alias used by SyncAlibabaTrackingJob / artisan command.
     *
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int, failed?: int, checked?: int}
     */
    public function syncFromShopify(int $limit = 40): array
    {
        $result = $this->syncPendingFromShopify($limit);

        return [
            'success' => ! empty($result['success']),
            'message' => (string) ($result['message'] ?? ''),
            'attempted' => (int) ($result['checked'] ?? 0),
            'checked' => (int) ($result['checked'] ?? 0),
            'pushed' => (int) ($result['pushed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
        ];
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('alibaba');

        return (bool) ($settings['order']['push_tracking_to_alibaba'] ?? true);
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
            'AlibabaTrackingSyncService'
        );
    }

    /**
     * @return array{tracking: ?string, service: ?string}
     */
    protected function resolveAeShipment(string $orderId, AlibabaOrderMetric $line): array
    {
        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderId);
            $line->refresh();
        } catch (\Throwable $e) {
            // Use cached payload if live pull fails.
        }

        $lines = AlibabaOrderMetric::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($line->fresh() ?? $line);
        $detail = $this->formatter->formatOrder($orderRoot, $lines, $line);
        $shipment = is_array($detail['shipment'] ?? null) ? $detail['shipment'] : [];

        $tracking = trim((string) ($shipment['tracking'] ?? ''));
        $service = trim((string) ($shipment['service'] ?? ''));

        if ($tracking === '' && ! empty($detail['logistics']) && is_array($detail['logistics'])) {
            foreach ($detail['logistics'] as $log) {
                if (! is_array($log)) {
                    continue;
                }
                $t = trim((string) ($log['tracking'] ?? ''));
                if ($t === '') {
                    continue;
                }
                $tracking = $t;
                $service = trim((string) ($log['service'] ?? $service));
                break;
            }
        }

        return [
            'tracking' => $tracking !== '' ? $tracking : null,
            'service' => $service !== '' ? $service : null,
        ];
    }

    protected function resolveServiceName(string $shopifyCarrier, string $aeService): string
    {
        if ($aeService !== '') {
            // Prefer AE's existing logistics service when modifying / already known.
            $matchedExisting = $this->matchLogisticsService($aeService);
            if ($matchedExisting !== null) {
                return $matchedExisting;
            }
        }

        $mapped = $this->mapShopifyCarrierToService($shopifyCarrier);
        $matched = $this->matchLogisticsService($mapped);
        if ($matched !== null) {
            return $matched;
        }

        // Last resort: common AE free-text service that often works for seller-fulfilled.
        return $mapped !== '' ? $mapped : 'Other';
    }

    protected function mapShopifyCarrierToService(string $carrier): string
    {
        $c = strtolower(trim($carrier));
        if ($c === '') {
            return 'Other';
        }

        $map = [
            'usps' => 'USPS',
            'ups' => 'UPS',
            'ups®' => 'UPS',
            'fedex' => 'Fedex',
            'fedex®' => 'Fedex',
            'dhl' => 'DHL',
            'dhl express' => 'DHL',
            'dhl ecommerce' => 'DHL',
            'china post' => 'China Post',
            'yanwen' => 'Yanwen',
            'yunexpress' => 'YunExpress',
            'cainiao' => 'Cainiao',
            'ems' => 'EMS',
            'sf express' => 'SF Express',
            'canada post' => 'Canada Post',
            'royal mail' => 'Royal Mail',
            'australia post' => 'Australia Post',
        ];

        if (isset($map[$c])) {
            return $map[$c];
        }

        foreach ($map as $needle => $service) {
            if (str_contains($c, $needle)) {
                return $service;
            }
        }

        // Keep Shopify company string — AE often accepts it as service_name / actual_carrier.
        return trim($carrier);
    }

    protected function matchLogisticsService(string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $services = $this->logisticsServices();
        if ($services === []) {
            return null;
        }

        $candidateLower = strtolower($candidate);
        foreach ($services as $row) {
            $name = (string) ($row['service_name'] ?? '');
            $display = (string) ($row['display_name'] ?? '');
            if ($name !== '' && strtolower($name) === $candidateLower) {
                return $name;
            }
            if ($display !== '' && strtolower($display) === $candidateLower) {
                return $name !== '' ? $name : $display;
            }
        }

        foreach ($services as $row) {
            $name = (string) ($row['service_name'] ?? '');
            $display = (string) ($row['display_name'] ?? '');
            $hay = strtolower($name.' '.$display);
            if ($name !== '' && (str_contains($hay, $candidateLower) || str_contains($candidateLower, strtolower($name)))) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<array{service_name: string, display_name?: string}>
     */
    protected function logisticsServices(): array
    {
        if ($this->logisticsServicesCache !== null) {
            return $this->logisticsServicesCache;
        }

        try {
            $result = $this->aliExpressApi->listLogisticsServices();
            $this->logisticsServicesCache = ! empty($result['success']) && is_array($result['services'] ?? null)
                ? array_values($result['services'])
                : [];
        } catch (\Throwable $e) {
            $this->logisticsServicesCache = [];
        }

        return $this->logisticsServicesCache;
    }

    /**
     * When declare fails because shipment already exists but we had no AE tracking locally.
     *
     * @return array{success: bool, message?: string}|null
     */
    protected function tryModifyWithGuessedOldTracking(
        string $orderId,
        string $newTracking,
        string $serviceName,
        string $shopifyCarrier,
        ?string $website,
        string $aeService
    ): ?array {
        // Re-pull; sometimes declare error means AE already has logistics we missed.
        $ae = $this->resolveAeShipment(
            $orderId,
            AlibabaOrderMetric::query()->where('order_id', $orderId)->orderBy('id')->first()
                ?? new AlibabaOrderMetric(['order_id' => $orderId])
        );

        $oldTracking = trim((string) ($ae['tracking'] ?? ''));
        if ($oldTracking === '' || $this->trackingEquals($oldTracking, $newTracking)) {
            return null;
        }

        return $this->aliExpressApi->modifySellerShipment([
            'out_ref' => $orderId,
            'old_logistics_no' => $oldTracking,
            'new_logistics_no' => $newTracking,
            'old_service_name' => trim((string) ($ae['service'] ?? '')) !== ''
                ? (string) $ae['service']
                : ($aeService !== '' ? $aeService : $serviceName),
            'new_service_name' => $serviceName,
            'send_type' => 'all',
            'tracking_website' => $website,
            'actual_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'description' => 'Updated from Shopify fulfillment',
        ]);
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'already')
            || str_contains($m, 'shipped')
            || str_contains($m, 'declared')
            || str_contains($m, 'duplicate')
            || str_contains($m, 'has been sent');
    }

    protected function trackingEquals(string $a, string $b): bool
    {
        $normalize = static function (string $value): string {
            $value = strtoupper(trim($value));
            // Shopify sometimes joins multiple numbers with ", ".
            if (str_contains($value, ',')) {
                $value = trim(explode(',', $value, 2)[0]);
            }

            return preg_replace('/[\s\-]/', '', $value) ?? $value;
        };

        return $normalize($a) === $normalize($b);
    }

    protected function normalizeTrackingWebsite(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return mb_substr(preg_replace('#^https?://#i', '', $url) ?? $url, 0, 200);
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('alibaba');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
