<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\TiktokOrder;
use App\Services\ShopifyStoreSelector;
use App\Services\TikTokShopService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokTrackingSyncService
{
    /**
     * Only push Shopify tracking for open TikTok orders still awaiting seller ship
     * (same intent as Reverb/AliExpress: skip delivered / completed history).
     *
     * @var list<string>
     */
    public const TRACKING_ELIGIBLE_STATUSES = [
        'AWAITING_SHIPMENT',
        'PARTIALLY_SHIPPING',
        // After RTS / pickup booked TikTok may still accept tracking updates.
        'AWAITING_COLLECTION',
        // Late push / updateShippingInfo fallback when Shopify label arrives after TikTok moved on.
        'IN_TRANSIT',
    ];

    public function __construct(
        protected TikTokShopService $tiktokApi
    ) {}

    /**
     * @return array{success: bool, message: string, shopify_tracking?: string|null}
     */
    public function pushTrackingForOrder(TiktokOrder $line): array
    {
        if (! $this->tiktokApi->isAuthenticated()) {
            return ['success' => false, 'message' => 'TikTok Shop API not authenticated.'];
        }

        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'TikTok order_id missing.'];
        }

        $status = strtoupper(trim((string) ($line->order_status ?? '')));
        if ($status !== '' && ! in_array($status, self::TRACKING_ELIGIBLE_STATUSES, true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => "Skip tracking push for status {$status} (only AWAITING_SHIPMENT / PARTIALLY_SHIPPING / AWAITING_COLLECTION / IN_TRANSIT).",
            ];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: TiktokOrder::query()
                ->where('order_id', $orderId)
                ->whereNotNull('shopify_order_id')
                ->value('shopify_order_id')
        ));

        if ($shopifyOrderId === '') {
            return ['success' => false, 'skipped' => true, 'message' => 'Order not linked to Shopify yet.'];
        }

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId);
        if (! empty($shopifyFulfillment['error'])) {
            return [
                'success' => false,
                'message' => 'Shopify tracking fetch failed: '.$shopifyFulfillment['error'],
                'shopify_tracking' => null,
            ];
        }
        if (empty($shopifyFulfillment['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No tracking number on Shopify yet. Buy/download a shipping label in Shopify first.',
                'shopify_tracking' => null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');
        $deliveryOptionId = $this->extractDeliveryOptionId($line);

        $shippingProviderId = $this->resolveShippingProviderId($orderId, $shopifyCarrier, $deliveryOptionId);
        if ($shippingProviderId === '') {
            return [
                'success' => false,
                'message' => 'Could not resolve TikTok shipping_provider_id (need delivery_option_id + Logistics providers).',
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        $result = $this->tiktokApi->markOrderShipped($orderId, $shopifyTracking, $shippingProviderId);

        if (! empty($result['success'])) {
            TiktokOrder::query()
                ->where('order_id', $orderId)
                ->update(['tracking_pushed_at' => now()]);

            Log::info('TikTokTrackingSyncService: tracking pushed', [
                'order_id' => $orderId,
                'shopify_order_id' => $shopifyOrderId,
                'tracking' => $shopifyTracking,
            ]);

            return [
                'success' => true,
                'message' => "TikTok Shop order {$orderId} marked shipped with {$shopifyTracking}.",
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        $msg = $result['message'] ?? 'Failed to push tracking.';

        if ($this->looksLikeAlreadyShipped($msg)) {
            TiktokOrder::query()
                ->where('order_id', $orderId)
                ->update(['tracking_pushed_at' => now()]);

            return [
                'success' => true,
                'skipped' => true,
                'message' => 'TikTok Shop order already shipped.',
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        Log::warning('TikTokTrackingSyncService: push failed', [
            'order_id' => $orderId,
            'tracking' => $shopifyTracking,
            'message' => $msg,
        ]);

        return ['success' => false, 'message' => $msg, 'shopify_tracking' => $shopifyTracking];
    }

    /**
     * @return array{success: bool, checked: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $rows = TiktokOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->whereNull('tracking_pushed_at')
            ->whereIn('order_status', self::TRACKING_ELIGIBLE_STATUSES)
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get(['id', 'order_id', 'shopify_order_id', 'order_status', 'tracking_pushed_at']);

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
        $settings ??= MarketplaceSyncSettings::getFor('tiktok');

        return (bool) ($settings['order']['push_tracking_to_tiktok'] ?? false);
    }

    /**
     * @return array{tracking: ?string, carrier: ?string, fulfillment_status: ?string, error?: string}
     */
    public function fetchShopifyTracking(string $shopifyOrderId): array
    {
        $config = $this->shopifyConfig();
        $storeUrl = (string) ($config['store_url'] ?? '');
        $token = (string) ($config['token'] ?? '');

        if ($storeUrl === '' || $token === '' || trim($shopifyOrderId) === '') {
            return [
                'tracking' => null,
                'carrier' => null,
                'fulfillment_status' => null,
                'error' => 'Shopify store/token not configured for TikTok channel.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,fulfillments,fulfillment_status',
            ]);

            if (! $response->successful()) {
                Log::warning('TikTokTrackingSyncService: Shopify tracking HTTP failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'store' => $storeUrl,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return [
                    'tracking' => null,
                    'carrier' => null,
                    'fulfillment_status' => null,
                    'error' => 'Shopify HTTP '.$response->status(),
                ];
            }

            $fulfillmentStatus = $response->json('order.fulfillment_status');
            $fulfillments = $response->json('order.fulfillments') ?? [];
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

                return [
                    'tracking' => $number,
                    'carrier' => trim((string) ($fulfillment['tracking_company'] ?? '')) ?: null,
                    'fulfillment_status' => is_string($fulfillmentStatus) ? $fulfillmentStatus : null,
                ];
            }

            return [
                'tracking' => null,
                'carrier' => null,
                'fulfillment_status' => is_string($fulfillmentStatus) ? $fulfillmentStatus : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('TikTokTrackingSyncService: Shopify tracking exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['tracking' => null, 'carrier' => null, 'fulfillment_status' => null];
    }

    protected function extractDeliveryOptionId(TiktokOrder $line): string
    {
        $raw = $line->raw_json;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $id = trim((string) (
            $raw['delivery_option_id']
            ?? ($raw['packages'][0]['delivery_option_id'] ?? '')
            ?? ($raw['fulfillment_type']['delivery_option_id'] ?? '')
            ?? ''
        ));

        return $id;
    }

    protected function resolveShippingProviderId(string $orderId, string $shopifyCarrier, string $deliveryOptionId = ''): string
    {
        $providers = $this->tiktokApi->getShippingProviders($orderId, $deliveryOptionId);
        $list = [];
        if (is_array($providers)) {
            if (array_is_list($providers)) {
                $list = $providers;
            } else {
                $list = $providers['shipping_providers']
                    ?? $providers['shipping_services']
                    ?? $providers['data']['shipping_providers']
                    ?? $providers['data']['shipping_services']
                    ?? [];
            }
        }

        $carrier = strtolower(trim($shopifyCarrier));
        $firstId = '';
        foreach ($list as $provider) {
            if (! is_array($provider)) {
                continue;
            }
            $id = trim((string) (
                $provider['id']
                ?? $provider['shipping_provider_id']
                ?? $provider['provider_id']
                ?? ''
            ));
            // TikTok expects provider IDs (usually numeric) — ignore "USPS"/"UPS" strings.
            if ($id === '' || ! preg_match('/^\d/', $id)) {
                continue;
            }
            if ($firstId === '') {
                $firstId = $id;
            }
            $name = strtolower(trim((string) (
                $provider['name']
                ?? $provider['shipping_provider_name']
                ?? $provider['provider_name']
                ?? ''
            )));
            if ($carrier !== '' && $name !== '' && (str_contains($name, $carrier) || str_contains($carrier, $name))) {
                return $id;
            }
            foreach (['usps', 'ups', 'fedex', 'dhl'] as $needle) {
                if ($carrier !== '' && str_contains($carrier, $needle) && str_contains($name, $needle)) {
                    return $id;
                }
            }
        }

        return $firstId;
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'already') && (str_contains($m, 'shipped') || str_contains($m, 'ship'));
    }

    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
