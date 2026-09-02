<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ReverbOrderMetric;
use App\Services\ReverbManagerApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers back to Reverb
 * (POST /my/orders/selling/{order_number}/ship).
 */
class ReverbTrackingSyncService
{
    /** @var list<string>|null */
    protected ?array $providersCache = null;

    public function __construct(
        protected ReverbManagerApiService $reverbApi,
        protected ReverbOrderDetailService $orderDetailService,
        protected ReverbDetailFormatter $formatter,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null,
     *   reverb_tracking?: string|null,
     *   provider?: string|null
     * }
     */
    public function pushTrackingForOrder(ReverbOrderMetric $line): array
    {
        if (empty($this->reverbApi->getAccessToken())) {
            return ['success' => false, 'message' => 'REVERB token missing.'];
        }

        $orderRef = $line->orderRef();
        if ($orderRef === '') {
            return ['success' => false, 'message' => 'Reverb order id missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: ReverbOrderMetric::query()
                ->where(function ($q) use ($orderRef) {
                    $q->where('order_id', $orderRef)->orWhere('order_number', $orderRef);
                })
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

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId, $orderRef, (string) ($line->sku ?? ''));
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

        $reverbShipment = $this->resolveReverbShipment($orderRef, $line);
        $reverbTracking = trim((string) ($reverbShipment['tracking'] ?? ''));
        $reverbProvider = trim((string) ($reverbShipment['service'] ?? ''));

        if ($reverbTracking !== '' && $this->trackingEquals($reverbTracking, $shopifyTracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Reverb already has this Shopify tracking number.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'reverb_tracking' => $reverbTracking,
                'provider' => $reverbProvider !== '' ? $reverbProvider : null,
            ];
        }

        $provider = $this->resolveProvider($shopifyCarrier, $reverbProvider);
        $notify = (bool) (MarketplaceSyncSettings::getFor('reverb')['order']['tracking_send_notification'] ?? false);

        $result = $this->reverbApi->shipOrder($orderRef, $provider, $shopifyTracking, $notify);

        if (empty($result['success'])) {
            Log::warning('ReverbTrackingSyncService: push failed', [
                'order_id' => $orderRef,
                'shopify_order_id' => $shopifyOrderId,
                'shopify_tracking' => $shopifyTracking,
                'provider' => $provider,
                'message' => $result['message'] ?? null,
            ]);

            return [
                'success' => false,
                'action' => 'ship',
                'message' => $result['message'] ?? 'Failed to push tracking to Reverb.',
                'shopify_tracking' => $shopifyTracking,
                'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
                'reverb_tracking' => $reverbTracking !== '' ? $reverbTracking : null,
                'provider' => $provider,
            ];
        }

        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderRef);
        } catch (\Throwable $e) {
            Log::info('ReverbTrackingSyncService: post-push refresh failed', [
                'order_id' => $orderRef,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('ReverbTrackingSyncService: tracking pushed', [
            'order_id' => $orderRef,
            'shopify_order_id' => $shopifyOrderId,
            'shopify_tracking' => $shopifyTracking,
            'provider' => $provider,
        ]);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => "Marked Reverb order shipped with tracking {$shopifyTracking} ({$provider}).",
            'shopify_tracking' => $shopifyTracking,
            'shopify_carrier' => $shopifyCarrier !== '' ? $shopifyCarrier : null,
            'reverb_tracking' => $reverbTracking !== '' ? $reverbTracking : null,
            'provider' => $provider,
        ];
    }

    /**
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = ReverbOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderBy('id')
            ->limit($limit * 12)
            ->get(['id', 'order_id', 'order_number', 'shopify_order_id', 'status']);

        $unique = [];
        foreach ($rows as $row) {
            $ref = $row->orderRef();
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
        $settings ??= MarketplaceSyncSettings::getFor('reverb');

        return (bool) ($settings['order']['push_tracking_to_reverb'] ?? true);
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
            'ReverbTrackingSyncService'
        );
    }

    /**
     * @return array{tracking: ?string, service: ?string}
     */
    protected function resolveReverbShipment(string $orderRef, ReverbOrderMetric $line): array
    {
        try {
            $this->orderDetailService->fetchAndPersistOrderDetail($orderRef);
            $line->refresh();
        } catch (\Throwable $e) {
            // Use cached payload if live pull fails.
        }

        $lines = ReverbOrderMetric::query()
            ->where(function ($q) use ($orderRef) {
                $q->where('order_id', $orderRef)->orWhere('order_number', $orderRef);
            })
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

    protected function resolveProvider(string $shopifyCarrier, string $reverbProvider): string
    {
        if ($reverbProvider !== '') {
            $matchedExisting = $this->matchProvider($reverbProvider);
            if ($matchedExisting !== null) {
                return $matchedExisting;
            }
        }

        $mapped = $this->mapShopifyCarrierToProvider($shopifyCarrier);
        $matched = $this->matchProvider($mapped);
        if ($matched !== null) {
            return $matched;
        }

        return $mapped !== '' ? $mapped : 'Other';
    }

    protected function mapShopifyCarrierToProvider(string $carrier): string
    {
        $c = strtolower(trim($carrier));
        if ($c === '') {
            return 'Other';
        }

        $map = [
            'usps' => 'USPS',
            'ups' => 'UPS',
            'ups®' => 'UPS',
            'fedex' => 'FedEx',
            'fedex®' => 'FedEx',
            'dhl' => 'DHL',
            'dhl express' => 'DHL',
            'dhl ecommerce' => 'DHL',
            'canada post' => 'Canada Post',
            'royal mail' => 'Royal Mail',
            'australia post' => 'Australia Post',
            'purolator' => 'Purolator',
            'ontrac' => 'OnTrac',
            'lasership' => 'LaserShip',
        ];

        if (isset($map[$c])) {
            return $map[$c];
        }

        foreach ($map as $needle => $provider) {
            if (str_contains($c, $needle)) {
                return $provider;
            }
        }

        return trim($carrier);
    }

    protected function matchProvider(string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $providers = $this->providers();
        if ($providers === []) {
            return null;
        }

        $candidateLower = strtolower($candidate);
        foreach ($providers as $name) {
            if (strtolower($name) === $candidateLower) {
                return $name;
            }
        }

        foreach ($providers as $name) {
            $hay = strtolower($name);
            if (str_contains($hay, $candidateLower) || str_contains($candidateLower, $hay)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function providers(): array
    {
        if ($this->providersCache !== null) {
            return $this->providersCache;
        }

        try {
            $result = $this->reverbApi->listShippingProviders();
            $this->providersCache = ! empty($result['success']) && is_array($result['providers'] ?? null)
                ? array_values($result['providers'])
                : [];
        } catch (\Throwable $e) {
            $this->providersCache = [];
        }

        return $this->providersCache;
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
        $settings = MarketplaceSyncSettings::getFor('reverb');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
