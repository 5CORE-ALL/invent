<?php

namespace App\Services\MarketplaceManager;

use App\Models\BestBuyOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\BestBuyApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Log;

/**
 * Copy Veeqo labels onto Shopify, then ship each Best Buy order line
 * with its own tracking number.
 */
class BestBuyTrackingSyncService
{
    public function __construct(
        protected BestBuyApiService $bestBuyApi,
    ) {}

    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null, shopify_tracking?: string|null, shopify_carrier?: string|null, ship_carrier?: string|null}
     */
    public function pushTrackingForOrder(BestBuyOrderMetric $order, bool $tryVeeqoCopy = true): array
    {
        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Best Buy is Off in settings.',
            ];
        }

        $connectOrderId = trim((string) ($order->order_id ?? ''));
        $channelOrderId = trim((string) ($order->channel_order_id ?? ''));
        $lineId = trim((string) ($order->order_line_id ?? ''));
        $sku = trim((string) ($order->sku ?? ''));
        if ($connectOrderId === '' || $lineId === '') {
            return ['success' => false, 'message' => 'Best Buy order line id is missing.'];
        }

        $status = strtolower(trim((string) ($order->status ?? '')));
        if ($status !== '' && (str_contains($status, 'cancel') || str_contains($status, 'refus'))) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'closed',
                'message' => 'Best Buy order line is cancelled — tracking not pushed.',
            ];
        }

        $shopifyOrderId = trim((string) ($order->shopify_order_id ?? ''));
        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        if ($tryVeeqoCopy) {
            try {
                app(VeeqoShopifyFulfillmentService::class)->fulfillMarketplaceOrder('bestbuy', (int) $order->id);
            } catch (\Throwable $e) {
                Log::debug('BestBuyTrackingSyncService: Veeqo copy skipped', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $order->refresh();
        }

        $shopify = $this->shopifyTrackingForLine($order, $shopifyOrderId, $sku, $channelOrderId !== '' ? $channelOrderId : $connectOrderId);
        if (empty($shopify['tracking'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => $shopify['error'] ?? 'No Shopify tracking for this Best Buy SKU yet.',
                'shopify_tracking' => null,
            ];
        }

        $tracking = (string) $shopify['tracking'];
        $carrier = trim((string) ($shopify['carrier'] ?? 'USPS')) ?: 'USPS';

        if ($this->alreadyPushedLocally($order, $tracking)) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Best Buy already has this Shopify tracking number.',
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier,
                'ship_carrier' => $carrier,
            ];
        }

        $ship = $this->bestBuyApi->createOrderShipment(
            $connectOrderId,
            $lineId,
            $tracking,
            $carrier,
            max(1, (int) ($order->quantity ?? 1)),
            $sku
        );

        if (empty($ship['success'])) {
            return [
                'success' => false,
                'action' => 'ship',
                'message' => (string) ($ship['message'] ?? 'Failed to push tracking to Best Buy.'),
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier,
                'ship_carrier' => $carrier,
            ];
        }

        $this->markTrackingPushed($order, $tracking, $carrier);

        return [
            'success' => true,
            'action' => 'shipped',
            'message' => 'Marked Best Buy line shipped with tracking '.$tracking.' ('.$carrier.').',
            'shopify_tracking' => $tracking,
            'shopify_carrier' => $carrier,
            'ship_carrier' => $carrier,
        ];
    }

    /**
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncPending(int $limit = 40): array
    {
        if (! self::canPushTracking()) {
            return [
                'success' => true,
                'message' => 'Tracking push disabled.',
                'attempted' => 0,
                'pushed' => 0,
                'skipped' => 0,
            ];
        }

        $limit = max(1, min(80, $limit));
        $rows = BestBuyOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->whereNotNull('order_line_id')
            ->where('order_line_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $attempted = 0;
        $pushed = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if ($this->alreadyPushedLocally($row)) {
                $skipped++;

                continue;
            }
            $attempted++;
            $result = $this->pushTrackingForOrder($row, true);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $pushed++;
            } else {
                $skipped++;
            }
        }

        return [
            'success' => true,
            'message' => "Best Buy tracking: {$pushed} pushed, {$skipped} skipped.",
            'attempted' => $attempted,
            'pushed' => $pushed,
            'skipped' => $skipped,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('bestbuy');

        return (bool) ($settings['order']['push_tracking_to_bestbuy'] ?? true);
    }

    /**
     * @return array{tracking: ?string, carrier: ?string, error: ?string}
     */
    protected function shopifyTrackingForLine(
        BestBuyOrderMetric $line,
        string $shopifyOrderId,
        string $sku,
        string $marketplaceOrderId
    ): array {
        $exclude = [];
        foreach (
            BestBuyOrderMetric::query()
                ->where('order_id', $line->order_id)
                ->where('id', '!=', $line->id)
                ->get() as $sibling
        ) {
            $tn = $this->pushedTracking($sibling);
            if ($tn !== '') {
                $exclude[] = $tn;
            }
        }

        $matched = app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $marketplaceOrderId,
            $sku,
            array_values(array_filter([$line->order_id, $line->channel_order_id])),
            'BestBuyTrackingSyncService',
            $exclude
        );

        return [
            'tracking' => $matched['tracking'] ?? null,
            'carrier' => $matched['carrier'] ?? null,
            'error' => $matched['error'] ?? null,
        ];
    }

    protected function pushedTracking(BestBuyOrderMetric $line): string
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];

        return strtoupper(preg_replace('/\s+/', '', (string) ($raw['shopify_tracking_pushed'] ?? $raw['tracking_number'] ?? '')) ?? '');
    }

    protected function alreadyPushedLocally(BestBuyOrderMetric $line, string $shopifyTracking = ''): bool
    {
        $pushed = $this->pushedTracking($line);
        if ($pushed === '') {
            return false;
        }
        if ($shopifyTracking === '') {
            return true;
        }

        return strtoupper(preg_replace('/\s+/', '', $shopifyTracking) ?? $shopifyTracking) === $pushed;
    }

    protected function markTrackingPushed(BestBuyOrderMetric $line, string $tracking, string $carrier = ''): void
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        $raw['shopify_tracking_pushed'] = $tracking;
        $raw['tracking_number'] = $tracking;
        $raw['tracking_pushed_at'] = now()->toIso8601String();
        if ($carrier !== '') {
            $raw['carrier'] = $carrier;
        }
        $updates = ['raw_payload' => $raw];
        if ($carrier !== '') {
            $updates['shipping_carrier'] = mb_substr($carrier, 0, 50);
        }
        $line->update($updates);
    }

    /**
     * @return array{store_url: string, token: string, store_key?: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('bestbuy');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
    }
}
