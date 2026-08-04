<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\Tiktok2Order;
use App\Services\ShopifyStoreSelector;
use App\Services\TikTok2ShopService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTok2TrackingSyncService
{
    public function __construct(
        protected TikTok2ShopService $tiktokApi
    ) {}

    /**
     * @return array{success: bool, message: string, shopify_tracking?: string|null}
     */
    public function pushTrackingForOrder(Tiktok2Order $line): array
    {
        if (! $this->tiktokApi->isAuthenticated()) {
            return ['success' => false, 'message' => 'TikTok 2 API not authenticated.'];
        }

        $orderId = trim((string) $line->order_id);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'TikTok order_id missing.'];
        }

        $shopifyOrderId = trim((string) (
            $line->shopify_order_id
            ?: Tiktok2Order::query()
                ->where('order_id', $orderId)
                ->whereNotNull('shopify_order_id')
                ->value('shopify_order_id')
        ));

        if ($shopifyOrderId === '') {
            return ['success' => false, 'skipped' => true, 'message' => 'Order not linked to Shopify yet.'];
        }

        $shopifyFulfillment = $this->fetchShopifyTracking($shopifyOrderId);
        if (empty($shopifyFulfillment['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No tracking number on Shopify yet.',
                'shopify_tracking' => null,
            ];
        }

        $shopifyTracking = (string) $shopifyFulfillment['tracking'];
        $shopifyCarrier = (string) ($shopifyFulfillment['carrier'] ?? '');

        $shippingProviderId = $this->resolveShippingProviderId($orderId, $shopifyCarrier);

        $result = $this->tiktokApi->markOrderShipped($orderId, $shopifyTracking, $shippingProviderId);

        if (! empty($result['success'])) {
            Tiktok2Order::query()
                ->where('order_id', $orderId)
                ->update(['tracking_pushed_at' => now()]);

            Log::info('TikTok2TrackingSyncService: tracking pushed', [
                'order_id' => $orderId,
                'shopify_order_id' => $shopifyOrderId,
                'tracking' => $shopifyTracking,
            ]);

            return [
                'success' => true,
                'message' => "TikTok 2 order {$orderId} marked shipped with {$shopifyTracking}.",
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        $msg = $result['message'] ?? 'Failed to push tracking.';

        if ($this->looksLikeAlreadyShipped($msg)) {
            Tiktok2Order::query()
                ->where('order_id', $orderId)
                ->update(['tracking_pushed_at' => now()]);

            return [
                'success' => true,
                'skipped' => true,
                'message' => 'TikTok 2 order already shipped.',
                'shopify_tracking' => $shopifyTracking,
            ];
        }

        Log::warning('TikTok2TrackingSyncService: push failed', [
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

        $rows = Tiktok2Order::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->whereNull('tracking_pushed_at')
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
        $settings ??= MarketplaceSyncSettings::getFor('tiktok2');

        return (bool) ($settings['order']['push_tracking_to_tiktok2'] ?? false);
    }

    protected function fetchShopifyTracking(string $shopifyOrderId): array
    {
        $config = $this->shopifyConfig();
        $storeUrl = (string) ($config['store_url'] ?? '');
        $token = (string) ($config['token'] ?? '');

        if ($storeUrl === '' || $token === '') {
            return ['tracking' => null, 'carrier' => null];
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,fulfillments,fulfillment_status',
            ]);

            if (! $response->successful()) {
                return ['tracking' => null, 'carrier' => null];
            }

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
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok2TrackingSyncService: Shopify tracking exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['tracking' => null, 'carrier' => null];
    }

    protected function resolveShippingProviderId(string $orderId, string $shopifyCarrier): string
    {
        $c = strtolower(trim($shopifyCarrier));
        $map = [
            'usps' => 'USPS',
            'ups' => 'UPS',
            'fedex' => 'FEDEX',
            'dhl' => 'DHL',
        ];

        foreach ($map as $needle => $providerId) {
            if (str_contains($c, $needle)) {
                return $providerId;
            }
        }

        return $shopifyCarrier !== '' ? $shopifyCarrier : 'OTHER';
    }

    protected function looksLikeAlreadyShipped(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'already') && (str_contains($m, 'shipped') || str_contains($m, 'ship'));
    }

    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok2');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
